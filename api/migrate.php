<?php
// ================================================================
// /api/migrate.php — Migrasi data histori dari Google Sheets → MySQL
// Jalankan SATU KALI kemudian hapus file ini
// Akses: https://smkn1telagasari.sch.id/form-gw/api/migrate.php?token=TOKEN
// ================================================================

require_once __DIR__ . '/config.php';

define('MIGRATE_TOKEN', 'migrate_smkn2_2026');
define('GAS_URL', 'https://script.google.com/macros/s/AKfycbxmLhmcE4di_21eVPAUVQ7M8f0f_cbpJHDGY-2nEE-SbJgnEHA6uMgS-tW5e-QFMIdH/exec');

// Rentang tanggal — ambil semua data dari awal hingga hari ini
define('DATE_FROM', '2020-01-01');
define('DATE_TO',   date('Y-m-d'));

// Batas waktu eksekusi untuk data besar
set_time_limit(120);

// --- Proteksi token ---
if (!isset($_GET['token']) || $_GET['token'] !== MIGRATE_TOKEN) {
    http_response_code(403);
    exit('403 Forbidden');
}

header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no'); // pastikan output streaming di Nginx
ob_implicit_flush(true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi Google Sheets → MySQL</title>
    <style>
        body  { font-family: 'Segoe UI', sans-serif; background:#f1f5f9; padding:24px; }
        h2    { color:#1e40af; }
        .box  { background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:16px; }
        .ok   { color:#16a34a; font-weight:600; }
        .err  { color:#dc2626; font-weight:600; }
        .warn { color:#d97706; }
        pre   { background:#0f172a; color:#e2e8f0; padding:16px; border-radius:8px;
                font-size:12px; max-height:400px; overflow:auto; }
        table { border-collapse:collapse; width:100%; font-size:13px; }
        th,td { border:1px solid #e2e8f0; padding:6px 10px; text-align:left; }
        th    { background:#eff6ff; color:#1e40af; }
    </style>
</head>
<body>
<h2>Migrasi Google Sheets → MySQL</h2>

<?php

// ============================================================
// LANGKAH 1 — Ambil data dari GAS API
// ============================================================
echo '<div class="box"><h3>Langkah 1 — Mengambil data dari Google Sheets…</h3>';

$url = GAS_URL . '?action=getReport&from=' . DATE_FROM . '&to=' . DATE_TO;

$ctx = stream_context_create([
    'http' => [
        'timeout'        => 90,
        'follow_location'=> true,
        'method'         => 'GET',
        'header'         => "Accept: application/json\r\n",
    ],
    'ssl' => ['verify_peer' => true],
]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    echo '<p class="err">❌ Gagal mengambil data dari GAS API. Cek koneksi dan URL.</p></div></body></html>';
    exit;
}

$json = json_decode($raw, true);
if (!$json || ($json['status'] ?? '') !== 'success' || !is_array($json['data'])) {
    echo '<p class="err">❌ Response API tidak valid.</p>';
    echo '<pre>' . htmlspecialchars(substr($raw, 0, 2000)) . '</pre></div></body></html>';
    exit;
}

$rows = $json['data'];
$total = count($rows);
echo "<p class=\"ok\">✅ Berhasil mengambil <strong>$total baris</strong> dari Google Sheets.</p></div>";

if ($total === 0) {
    echo '<div class="box"><p class="warn">⚠ Tidak ada data untuk dimigrasikan.</p></div></body></html>';
    exit;
}

// ============================================================
// LANGKAH 2 — Kelompokkan berdasarkan tanggal + guru
// ============================================================
echo '<div class="box"><h3>Langkah 2 — Mengelompokkan data…</h3>';

$sessions = []; // ['YYYY-MM-DD|NamaGuru'] => [rows...]
foreach ($rows as $r) {
    $key = ($r['tanggal'] ?? '') . '|' . ($r['guru'] ?? '');
    $sessions[$key][] = $r;
}

$sessionCount = count($sessions);
$guruSet = [];
foreach ($sessions as $key => $_) {
    [, $guru] = explode('|', $key, 2);
    $guruSet[$guru] = true;
}
echo '<p>Sesi pembinaan unik : <strong>' . $sessionCount . '</strong></p>';
echo '<p>Guru unik           : <strong>' . count($guruSet) . '</strong></p>';
echo '</div>';

// ============================================================
// LANGKAH 2b — Deduplikasi: data duplikat (tanggal+guru+nama)
//              → ambil baris terakhir berdasarkan urutan dari API
// ============================================================
foreach ($sessions as $key => &$sessionRows) {
    // API mengembalikan data ORDER BY tanggal DESC → baris pertama per siswa = terbaru
    // Kita balik agar baris terakhir menang (overwrite)
    $deduped = [];
    foreach ($sessionRows as $r) {
        $namaKey = trim(strtolower($r['nama'] ?? ''));
        $deduped[$namaKey] = $r; // baris terakhir dengan nama sama menang
    }
    $sessionRows = array_values($deduped);
}
unset($sessionRows);

$dupeRemoved = array_sum(array_map(fn($s) => max(0, count($s) - count(array_unique(array_column($s, 'nama')))), $sessions));
echo "<p>Duplikat dihapus : <strong>$dupeRemoved</strong></p></div>";

echo '<div class="box"><h3>Langkah 3 — Deduplikasi selesai</h3></div>';

// ============================================================
// LANGKAH 3 — Insert ke MySQL
// ============================================================
echo '<div class="box"><h3>Langkah 3 — Menyimpan ke MySQL…</h3>';

$allowAbsen    = ['H', 'T', 'L', 'A', 'S', 'I', '-'];
$allowZuhurAsr = ['H', 'S', 'I', 'A', 'Haid', '-'];
$allowMingguan = ['H', 'T', 'X', 'I', '-'];

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Prepared statements
    $stmtGuru = $pdo->prepare(
        'INSERT INTO guru_wali (nama) VALUES (?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $stmtGetGuru = $pdo->prepare('SELECT id FROM guru_wali WHERE nama = ? LIMIT 1');

    // INSERT IGNORE: jika sesi sudah ada di DB, skip
    $stmtPem = $pdo->prepare(
        'INSERT IGNORE INTO pembinaan (tanggal, guru_wali_id) VALUES (?, ?)'
    );
    $stmtGetPem = $pdo->prepare(
        'SELECT id FROM pembinaan WHERE tanggal = ? AND guru_wali_id = ? LIMIT 1'
    );

    $stmtFindNisn = $pdo->prepare(
        'SELECT id FROM siswa WHERE nisn = ? AND guru_wali_id = ? LIMIT 1'
    );
    $stmtFindName = $pdo->prepare(
        'SELECT id FROM siswa WHERE nama = ? AND kelas = ? AND guru_wali_id = ? LIMIT 1'
    );
    $stmtInsSiswa = $pdo->prepare(
        'INSERT INTO siswa (nisn, nama, kelas, guru_wali_id) VALUES (?, ?, ?, ?)'
    );
    $stmtDetail = $pdo->prepare(
        'INSERT INTO pembinaan_detail
             (pembinaan_id, siswa_id, absen, zuhur, ashar, mingguan, tambahan)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             absen=VALUES(absen), zuhur=VALUES(zuhur), ashar=VALUES(ashar),
             mingguan=VALUES(mingguan), tambahan=VALUES(tambahan)'
    );

    // Cache guru & siswa agar tidak query berulang
    $guruCache  = []; // nama => id
    $siswaCache = []; // "{guruId}|{nisn_or_nama}|{kelas}" => siswaId

    $inserted = 0;
    $skipped  = 0;
    $log      = [];

    foreach ($sessions as $key => $sessionRows) {
        [$tanggal, $guruNama] = explode('|', $key, 2);

        if (empty($tanggal) || empty($guruNama)) { $skipped++; continue; }

        // --- Guru ---
        if (!isset($guruCache[$guruNama])) {
            $stmtGuru->execute([$guruNama]);
            $stmtGetGuru->execute([$guruNama]);
            $guruCache[$guruNama] = (int) $stmtGetGuru->fetchColumn();
        }
        $guruId = $guruCache[$guruNama];

        // --- Sesi pembinaan ---
        // Cek dulu apakah sesi sudah ada di DB
        $stmtGetPem->execute([$tanggal, $guruId]);
        $existingPemId = $stmtGetPem->fetchColumn();
        if ($existingPemId) {
            // Sesi sudah ada — lewati seluruh batch ini
            $skipped += count($sessionRows);
            $log[] = "⏭ SKIP  $tanggal | $guruNama (sudah ada di DB)";
            continue;
        }

        $stmtPem->execute([$tanggal, $guruId]);
        $stmtGetPem->execute([$tanggal, $guruId]);
        $pemId = (int) $stmtGetPem->fetchColumn();

        $addedInSession = 0;
        foreach ($sessionRows as $r) {
            $nama     = trim($r['nama']     ?? '');
            $nisn     = trim($r['nisn']     ?? '');
            $kelas    = trim($r['kelas']    ?? '');
            $absen    = in_array($r['absen']    ?? '', $allowAbsen,    true) ? $r['absen']    : '-';
            $zuhur    = in_array($r['zuhur']    ?? '', $allowZuhurAsr, true) ? $r['zuhur']    : '-';
            $ashar    = in_array($r['ashar']    ?? '', $allowZuhurAsr, true) ? $r['ashar']    : '-';
            $mingguan = in_array($r['mingguan'] ?? '', $allowMingguan, true) ? $r['mingguan'] : '-';
            $tambahan = substr(trim($r['tambahan'] ?? ''), 0, 500);

            if ($nama === '') { $skipped++; continue; }

            // --- Siswa ---
            $cacheKey = "$guruId|$nisn|$nama|$kelas";
            if (!isset($siswaCache[$cacheKey])) {
                $siswaId = null;
                if ($nisn !== '') {
                    $stmtFindNisn->execute([$nisn, $guruId]);
                    $siswaId = $stmtFindNisn->fetchColumn() ?: null;
                }
                if (!$siswaId) {
                    $stmtFindName->execute([$nama, $kelas, $guruId]);
                    $siswaId = $stmtFindName->fetchColumn() ?: null;
                }
                if (!$siswaId) {
                    $stmtInsSiswa->execute([$nisn ?: null, $nama, $kelas, $guruId]);
                    $siswaId = (int) $pdo->lastInsertId();
                }
                $siswaCache[$cacheKey] = $siswaId;
            }
            $siswaId = $siswaCache[$cacheKey];

            $stmtDetail->execute([$pemId, $siswaId, $absen, $zuhur, $ashar, $mingguan, $tambahan]);
            $inserted++;
            $addedInSession++;
        }

        $log[] = "$tanggal | $guruNama — $addedInSession rekam";
    }

    $pdo->commit();

    echo "<p class=\"ok\">✅ Migrasi selesai!</p>";
    echo "<ul>
        <li>Baris berhasil dimigrasi : <strong>$inserted</strong></li>
        <li>Baris dilewati (kosong)  : <strong>$skipped</strong></li>
        <li>Sesi pembinaan           : <strong>$sessionCount</strong></li>
    </ul>";
    echo '<details><summary style="cursor:pointer;color:#3b82f6">Lihat log per sesi</summary><pre>' . implode("\n", $log) . '</pre></details>';
    echo '<p class="err" style="margin-top:12px">⚠ <strong>Segera hapus file api/migrate.php dari server!</strong></p>';

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo '<p class="err">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</div></body></html>';
