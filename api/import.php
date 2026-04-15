<?php
// ================================================================
// /api/import.php — Script SATU KALI untuk import data_siswa.json
// ⚠ HAPUS file ini segera setelah import berhasil!
// Akses: http://domain.com/api/import.php?token=TOKEN_RAHASIA_ANDA
// ================================================================

require_once __DIR__ . '/config.php';

// --- Token proteksi (ganti sebelum digunakan) ---
define('IMPORT_TOKEN', '3007');

if (!isset($_GET['token']) || $_GET['token'] !== IMPORT_TOKEN) {
    http_response_code(403);
    exit('403 Forbidden');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import data_siswa.json</title>
    <style>
        body { font-family: monospace; padding: 24px; background: #f8fafc; }
        .ok  { color: #16a34a; } .err { color: #dc2626; }
        pre  { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; }
    </style>
</head>
<body>
<h2>Import data_siswa.json → MySQL</h2>
<?php

$jsonPath = __DIR__ . '/../data_siswa.json';
if (!file_exists($jsonPath)) {
    echo '<p class="err">❌ File data_siswa.json tidak ditemukan di ' . htmlspecialchars($jsonPath) . '</p>';
    exit;
}

$raw  = file_get_contents($jsonPath);
$data = json_decode($raw, true);
if (!$data) {
    echo '<p class="err">❌ Gagal parse JSON: ' . json_last_error_msg() . '</p>';
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    $stmtGuru = $pdo->prepare(
        'INSERT INTO guru_wali (nama, kelas) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE kelas = VALUES(kelas)'
    );
    $stmtGetGuru = $pdo->prepare('SELECT id FROM guru_wali WHERE nama = ? LIMIT 1');
    $stmtSiswa   = $pdo->prepare(
        'INSERT IGNORE INTO siswa (nisn, nama, kelas, guru_wali_id) VALUES (?, ?, ?, ?)'
    );

    $guruCount  = 0;
    $siswaCount = 0;
    $log        = [];

    foreach ($data as $guruNama => $info) {
        $kelas = $info['kelas'] ?? '';
        $stmtGuru->execute([$guruNama, $kelas]);
        $stmtGetGuru->execute([$guruNama]);
        $guruId = (int) $stmtGetGuru->fetchColumn();
        $guruCount++;

        $added = 0;
        foreach ($info['siswa'] as $s) {
            $stmtSiswa->execute([
                $s['nisn'] ?? null,
                $s['nama'],
                $s['kelas'],
                $guruId,
            ]);
            $siswaCount++;
            $added++;
        }
        $log[] = "[$guruCount] $guruNama — $added siswa";
    }

    $pdo->commit();

    echo '<p class="ok"><strong>✅ Import berhasil!</strong></p>';
    echo "<ul><li>Guru Wali : <strong>$guruCount</strong></li>";
    echo "<li>Siswa     : <strong>$siswaCount</strong></li></ul>";
    echo '<pre>' . implode("\n", $log) . '</pre>';
    echo '<p class="err"><strong>⚠ Segera hapus file api/import.php dari server!</strong></p>';
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo '<p class="err">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
</body>
</html>
