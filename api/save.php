<?php
// ================================================================
// POST /api/save.php
// Menerima data form pembinaan dari index.html
// Format body (JSON):
//   { tanggal, guru, responses: [{nama, nisn, kelas, absen, zuhur, ashar, mingguan, tambahan}] }
// ================================================================

require_once __DIR__ . '/config.php';

cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

// --- Parse & validasi body ---
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (
    !is_array($body) ||
    empty($body['tanggal']) ||
    empty($body['guru']) ||
    !isset($body['responses']) || !is_array($body['responses'])
) {
    jsonResponse(['status' => 'error', 'message' => 'Data tidak lengkap'], 400);
}

$tanggal   = $body['tanggal'];
$guruNama  = trim($body['guru']);
$responses = $body['responses'];

// Validasi format tanggal (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || !checkdate(
    (int) substr($tanggal, 5, 2),
    (int) substr($tanggal, 8, 2),
    (int) substr($tanggal, 0, 4)
)) {
    jsonResponse(['status' => 'error', 'message' => 'Format tanggal tidak valid'], 400);
}

if (strlen($guruNama) > 200 || count($responses) > 50) {
    jsonResponse(['status' => 'error', 'message' => 'Data melebihi batas'], 400);
}

// Nilai yang diizinkan untuk setiap kolom
$allowAbsen    = ['H', 'T', 'L', 'A', 'S', 'I', '-'];
$allowZuhurAsr = ['H', 'S', 'I', 'A', 'Haid', '-'];
$allowMingguan = ['H', 'T', 'X', 'I', '-'];

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // --- Cari atau buat guru_wali ---
    $stmt = $pdo->prepare('SELECT id FROM guru_wali WHERE nama = ? LIMIT 1');
    $stmt->execute([$guruNama]);
    $guruId = $stmt->fetchColumn();

    if (!$guruId) {
        $pdo->prepare('INSERT INTO guru_wali (nama) VALUES (?)')->execute([$guruNama]);
        $guruId = (int) $pdo->lastInsertId();
    }

    // --- Upsert sesi pembinaan ---
    $pdo->prepare(
        'INSERT INTO pembinaan (tanggal, guru_wali_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP'
    )->execute([$tanggal, $guruId]);

    $stmt = $pdo->prepare('SELECT id FROM pembinaan WHERE tanggal = ? AND guru_wali_id = ?');
    $stmt->execute([$tanggal, $guruId]);
    $pembinaanId = (int) $stmt->fetchColumn();

    // --- Prepare statements yang dipakai berulang ---
    $stmtFindNisn = $pdo->prepare(
        'SELECT id FROM siswa WHERE nisn = ? AND guru_wali_id = ? LIMIT 1'
    );
    $stmtFindName = $pdo->prepare(
        'SELECT id, nisn FROM siswa WHERE nama = ? AND kelas = ? AND guru_wali_id = ?'
    );
    $stmtInsertSiswa = $pdo->prepare(
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

    foreach ($responses as $row) {
        $nama     = trim($row['nama']     ?? '');
        $nisn     = trim($row['nisn']     ?? '');
        $kelas    = trim($row['kelas']    ?? '');
        $absen    = in_array($row['absen']    ?? '', $allowAbsen,    true) ? $row['absen']    : '-';
        $zuhur    = in_array($row['zuhur']    ?? '', $allowZuhurAsr, true) ? $row['zuhur']    : '-';
        $ashar    = in_array($row['ashar']    ?? '', $allowZuhurAsr, true) ? $row['ashar']    : '-';
        $mingguan = in_array($row['mingguan'] ?? '', $allowMingguan, true) ? $row['mingguan'] : '-';
        $tambahan = substr(trim($row['tambahan'] ?? ''), 0, 500);

        if ($nama === '') continue;

        // Cari siswa_id — prioritas: NISN, fallback: nama+kelas+nisn_null, last resort: nama+kelas LIMIT 1
        $siswaId = null;
        if ($nisn !== '') {
            $stmtFindNisn->execute([$nisn, $guruId]);
            $siswaId = $stmtFindNisn->fetchColumn() ?: null;
        }
        if (!$siswaId) {
            // Fallback: nama+kelas — ambil yang NISN-nya sama atau NULL dulu
            $stmtFindName->execute([$nama, $kelas, $guruId]);
            $rows = $stmtFindName->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) === 1) {
                $siswaId = $rows[0]['id'];
            } elseif (count($rows) > 1) {
                // Nama duplikat di kelas yang sama: cari yang NISN-nya cocok, atau yang NISN NULL
                foreach ($rows as $sr) {
                    if ($nisn !== '' && $sr['nisn'] === $nisn) { $siswaId = $sr['id']; break; }
                }
                if (!$siswaId) {
                    foreach ($rows as $sr) {
                        if (empty($sr['nisn'])) { $siswaId = $sr['id']; break; }
                    }
                }
                // Jika masih belum ketemu, ambil baris pertama (last resort)
                if (!$siswaId) $siswaId = $rows[0]['id'];
            }
        }
        if (!$siswaId) {
            // Siswa belum ada di DB — insert otomatis
            $stmtInsertSiswa->execute([$nisn ?: null, $nama, $kelas, $guruId]);
            $siswaId = (int) $pdo->lastInsertId();
        }

        $stmtDetail->execute([$pembinaanId, $siswaId, $absen, $zuhur, $ashar, $mingguan, $tambahan]);
    }

    $pdo->commit();
    jsonResponse(['status' => 'success']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[save.php] ' . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Terjadi kesalahan pada server'], 500);
}
