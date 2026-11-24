<?php
// ============================================================
// SIGAP — Konfigurasi Database & Base URL
// Sistem Informasi Gelar Alat & Perlengkapan K3 PLN
// ============================================================

// Tampilkan error untuk mempermudah diagnosa masalah di server hosting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/compat_php5.php';

// Deteksi apakah sedang di lingkungan lokal (Laragon/Windows) atau server hosting (Linux cPanel)
$isLocal = (strpos(__DIR__, 'C:\\') === 0 || strpos(__DIR__, 'C:/') === 0)
    || (isset($_SERVER['HTTP_HOST']) && in_array(explode(':', $_SERVER['HTTP_HOST'])[0], ['localhost', '127.0.0.1']));

// 1. PENGATURAN DATABASE HOSTING & LOKAL
if ($isLocal) {
    // Pengaturan Database Lokal (Laragon / PC Anda)
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'db_gelar_alat');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
} else {
    // Pengaturan Database Hosting cPanel (jaringanponorogo.com)
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'jart2779_sigap');           // Nama database SIGAP di cPanel
    define('DB_USER', 'jart2779');                 // Nama user MySQL cPanel Anda
    define('DB_PASS', 'J4ringan2026!!@');          // Password MySQL cPanel Anda
    define('DB_CHARSET', 'utf8mb4');
}

// 2. AUTO-DETECT BASE_URL
if (!defined('BASE_URL')) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

    // Deteksi direktori relatif project SIGAP secara otomatis
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot     = str_replace('\\', '/', isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '');

    $subDir = '';
    if ($docRoot && strpos($projectRoot, $docRoot) === 0) {
        $subDir = substr($projectRoot, strlen($docRoot));
    } else {
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $subDir = str_replace('\\', '/', dirname($scriptName));
        if (in_array(basename($subDir), ['actions', 'inspeksi', 'master', 'riwayat', 'auth', 'dashboard', 'cetak', 'foto'])) {
            $subDir = dirname($subDir);
        }
    }
    $subDir = ($subDir === '/' || $subDir === '\\' || $subDir === '.') ? '' : rtrim(str_replace('\\', '/', $subDir), '/');
    define('BASE_URL', $scheme . '://' . $host . $subDir);
}

// 3. FOLDER UPLOAD & STORAGE SIGAP
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// 4. KONEKSI DATABASE PDO
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            return null;
        }
    }
    return $pdo;
}
