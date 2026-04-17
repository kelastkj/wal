<?php
// ================================================================
// GET /api/report.php
//
// MODE 1 — Summary (dashboard)
//   ?summary=1
//   Response: { status, data: [{guru, last_tanggal, total_sesi}] }
//   Hanya 52 baris (1 per guru), sangat cepat.
//
// MODE 2 — Laporan per guru (laporan.html)
//   ?guru=NamaGuru&from=YYYY-MM-DD&to=YYYY-MM-DD
//   Parameter 'guru' WAJIB diisi.
//   Response: { status, data: [{tanggal, guru, nama, nisn, kelas,
//                               absen, zuhur, ashar, mingguan, tambahan}] }
// ================================================================

require_once __DIR__ . '/config.php';

cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$dateRegex = '/^\d{4}-\d{2}-\d{2}$/';

try {
    $pdo = getDB();

    // ── MODE 1: Summary untuk dashboard ──────────────────────────
    if (!empty($_GET['summary'])) {
        $stmt = $pdo->query('
            SELECT
                g.nama          AS guru,
                MAX(p.tanggal)  AS last_tanggal,
                COUNT(p.id)     AS total_sesi,
                (
                    SELECT COUNT(pd2.id)
                    FROM pembinaan p2
                    JOIN pembinaan_detail pd2 ON pd2.pembinaan_id = p2.id
                    WHERE p2.guru_wali_id = g.id
                      AND (pd2.zuhur = \'Haid\' OR pd2.ashar = \'Haid\')
                      AND p2.tanggal = (
                          SELECT MAX(tanggal) FROM pembinaan
                          WHERE guru_wali_id = g.id
                      )
                ) AS haid_last
            FROM guru_wali g
            LEFT JOIN pembinaan p ON p.guru_wali_id = g.id
            GROUP BY g.id, g.nama
            ORDER BY g.nama
        ');
        $rows = $stmt->fetchAll();

        $result = array_map(static function (array $r): array {
            return [
                'guru'         => $r['guru'],
                'last_tanggal' => $r['last_tanggal'],  // null jika belum pernah input
                'total_sesi'   => (int) $r['total_sesi'],
                'haid_last'    => (int) $r['haid_last'],
            ];
        }, $rows);

        jsonResponse(['status' => 'success', 'data' => $result]);
    }

    // ── MODE 3: Siswa Kritis (lintas semua guru) ─────────────────
    // ?kritis=1&from=YYYY-MM-DD&to=YYYY-MM-DD
    //           &min_alpa=3&min_lambat=5&min_sholat=5
    // Response: { status, data: [{nama,kelas,nisn,guru,total_alpa,total_lambat,
    //             total_sakit,total_izin,alpa_zuhur,alpa_ashar,total_record}] }
    if (!empty($_GET['kritis'])) {
        $from = isset($_GET['from']) ? trim($_GET['from']) : null;
        $to   = isset($_GET['to'])   ? trim($_GET['to'])   : null;
        if ($from && !preg_match($dateRegex, $from)) $from = null;
        if ($to   && !preg_match($dateRegex, $to))   $to   = null;

        $minAlpa   = max(0, (int) ($_GET['min_alpa']   ?? 3));
        $minLambat = max(0, (int) ($_GET['min_lambat'] ?? 5));
        $minSholat = max(0, (int) ($_GET['min_sholat'] ?? 5));

        $whereParts = ['1 = 1'];
        $params     = [];
        if ($from) { $whereParts[] = 'p.tanggal >= ?'; $params[] = $from; }
        if ($to)   { $whereParts[] = 'p.tanggal <= ?'; $params[] = $to;   }
        $where = implode(' AND ', $whereParts);

        $sql = "
            SELECT
                s.nama,
                s.kelas,
                COALESCE(s.nisn, '')          AS nisn,
                g.nama                        AS guru,
                SUM(d.absen = 'A')            AS total_alpa,
                SUM(d.absen = 'L')            AS total_lambat,
                SUM(d.absen = 'S')            AS total_sakit,
                SUM(d.absen = 'I')            AS total_izin,
                SUM(d.zuhur = 'A')            AS alpa_zuhur,
                SUM(d.ashar = 'A')            AS alpa_ashar,
                COUNT(*)                      AS total_record
            FROM pembinaan_detail d
            JOIN pembinaan p ON p.id = d.pembinaan_id
            JOIN guru_wali g ON g.id = p.guru_wali_id
            JOIN siswa     s ON s.id = d.siswa_id
            WHERE $where
            GROUP BY s.id, s.nama, s.kelas, s.nisn, g.nama
            HAVING total_alpa >= ? OR total_lambat >= ? OR (alpa_zuhur + alpa_ashar) >= ?
            ORDER BY total_alpa DESC, total_lambat DESC, s.nama
        ";
        $params[] = $minAlpa;
        $params[] = $minLambat;
        $params[] = $minSholat;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $result = array_map(static function (array $r): array {
            return [
                'nama'         => $r['nama'],
                'kelas'        => $r['kelas'],
                'nisn'         => $r['nisn'],
                'guru'         => $r['guru'],
                'total_alpa'   => (int) $r['total_alpa'],
                'total_lambat' => (int) $r['total_lambat'],
                'total_sakit'  => (int) $r['total_sakit'],
                'total_izin'   => (int) $r['total_izin'],
                'alpa_zuhur'   => (int) $r['alpa_zuhur'],
                'alpa_ashar'   => (int) $r['alpa_ashar'],
                'total_record' => (int) $r['total_record'],
            ];
        }, $rows);

        jsonResponse(['status' => 'success', 'data' => $result]);
    }

    // ── MODE 4: Rekap Guru (semua guru, agregasi per periode) ───────
    // ?rekap_guru=1&from=YYYY-MM-DD&to=YYYY-MM-DD
    // Response: { status, data: [{guru,kelas,jml_siswa,total_sesi,last_tanggal,
    //             total_hadir,total_lambat,total_alpa,total_sakit,total_izin,
    //             total_detail,pct_hadir}] }
    // Guru tanpa input di periode tetap muncul dengan angka 0.
    if (!empty($_GET['rekap_guru'])) {
        $from = isset($_GET['from']) ? trim($_GET['from']) : null;
        $to   = isset($_GET['to'])   ? trim($_GET['to'])   : null;
        if ($from && !preg_match($dateRegex, $from)) $from = null;
        if ($to   && !preg_match($dateRegex, $to))   $to   = null;

        // Kondisi tanggal masuk ke JOIN agar guru tanpa data tetap muncul
        $joinCond  = 'p.guru_wali_id = g.id';
        $params    = [];
        if ($from) { $joinCond .= ' AND p.tanggal >= ?'; $params[] = $from; }
        if ($to)   { $joinCond .= ' AND p.tanggal <= ?'; $params[] = $to;   }

        $sql = "
            SELECT
                g.nama                                          AS guru,
                g.kelas,
                (SELECT COUNT(*) FROM siswa WHERE guru_wali_id = g.id) AS jml_siswa,
                COUNT(DISTINCT p.id)                           AS total_sesi,
                MAX(p.tanggal)                                 AS last_tanggal,
                COALESCE(SUM(d.absen = 'T'), 0)                AS total_hadir,
                COALESCE(SUM(d.absen = 'L'), 0)                AS total_lambat,
                COALESCE(SUM(d.absen = 'A'), 0)                AS total_alpa,
                COALESCE(SUM(d.absen = 'S'), 0)                AS total_sakit,
                COALESCE(SUM(d.absen = 'I'), 0)                AS total_izin,
                COUNT(d.id)                                    AS total_detail
            FROM guru_wali g
            LEFT JOIN pembinaan p ON $joinCond
            LEFT JOIN pembinaan_detail d ON d.pembinaan_id = p.id
            GROUP BY g.id, g.nama, g.kelas
            ORDER BY total_sesi DESC, last_tanggal DESC, g.nama
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $result = array_map(static function (array $r): array {
            $detail = (int) $r['total_detail'];
            $hadir  = (int) $r['total_hadir'];
            return [
                'guru'         => $r['guru'],
                'kelas'        => $r['kelas'],
                'jml_siswa'    => (int) $r['jml_siswa'],
                'total_sesi'   => (int) $r['total_sesi'],
                'last_tanggal' => $r['last_tanggal'],
                'total_hadir'  => $hadir,
                'total_lambat' => (int) $r['total_lambat'],
                'total_alpa'   => (int) $r['total_alpa'],
                'total_sakit'  => (int) $r['total_sakit'],
                'total_izin'   => (int) $r['total_izin'],
                'total_detail' => $detail,
                'pct_hadir'    => $detail > 0 ? round($hadir / $detail * 100, 1) : null,
            ];
        }, $rows);

        jsonResponse(['status' => 'success', 'data' => $result]);
    }

    // ── MODE 2: Laporan detail per guru ───────────────────────────

    // 'guru' wajib — tolak di backend jika kosong
    $guruParam = isset($_GET['guru']) ? trim($_GET['guru']) : '';
    if ($guruParam === '') {
        jsonResponse([
            'status'  => 'error',
            'message' => 'Parameter guru wajib diisi untuk laporan detail.'
        ], 400);
    }

    $from = isset($_GET['from']) ? trim($_GET['from']) : null;
    $to   = isset($_GET['to'])   ? trim($_GET['to'])   : null;

    // Validasi format tanggal — abaikan jika tidak valid
    if ($from && !preg_match($dateRegex, $from)) $from = null;
    if ($to   && !preg_match($dateRegex, $to))   $to   = null;

    $sql = '
        SELECT
            p.tanggal,
            g.nama    AS guru,
            s.nama,
            s.nisn,
            s.kelas,
            d.absen,
            d.zuhur,
            d.ashar,
            d.mingguan,
            d.tambahan
        FROM pembinaan_detail d
        JOIN pembinaan  p ON p.id = d.pembinaan_id
        JOIN guru_wali  g ON g.id = p.guru_wali_id
        JOIN siswa      s ON s.id = d.siswa_id
        WHERE g.nama LIKE ?
    ';

    $params = ['%' . $guruParam . '%'];

    if ($from) {
        $sql     .= ' AND p.tanggal >= ?';
        $params[] = $from;
    }
    if ($to) {
        $sql     .= ' AND p.tanggal <= ?';
        $params[] = $to;
    }

    $sql .= ' ORDER BY p.tanggal DESC, s.nama';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Format output agar identik dengan response GAS sebelumnya
    $result = array_map(static function (array $r): array {
        return [
            'tanggal'  => $r['tanggal'],
            'guru'     => $r['guru'],
            'nama'     => $r['nama'],
            'nisn'     => $r['nisn'] ?? '',
            'kelas'    => $r['kelas'],
            'absen'    => $r['absen'],
            'zuhur'    => $r['zuhur'],
            'ashar'    => $r['ashar'],
            'mingguan' => $r['mingguan'],
            'tambahan' => $r['tambahan'],
        ];
    }, $rows);

    jsonResponse(['status' => 'success', 'data' => $result]);

} catch (Throwable $e) {
    error_log('[report.php] ' . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Terjadi kesalahan pada server'], 500);
}
