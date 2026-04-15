<?php
// ================================================================
// Konfigurasi Database & Helper
// PERHATIAN: File ini diproteksi .htaccess — jangan diakses langsung
// ================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'smkn2_wali');
define('DB_USER', 'root');   // Ganti dengan username database Anda
define('DB_PASS', '');   // Ganti dengan password database Anda

/**
 * Singleton PDO — koneksi dibuat sekali, dipakai ulang
 */
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // wajib untuk keamanan
        ]);
    }
    return $pdo;
}

/**
 * Set CORS headers & tangani preflight OPTIONS
 * Hanya origin yang terdaftar di $allowed yang diizinkan.
 */
function cors(): void
{
    $allowed = [
        'https://smkn1telagasari.sch.id',
        'https://kelastkj.github.io',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Output JSON dan akhiri eksekusi
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
