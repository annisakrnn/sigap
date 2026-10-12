<?php
/**
 * Main Entry Point
 * SIGAP
 */
require_once __DIR__ . '/includes/auth_check.php';

$pdo = get_db_connection();
if (!$pdo) {
    header("Location: setup.php");
    exit;
}

if (is_logged_in()) {
    $user = get_logged_user();
    if ($user['role'] === 'manajemen') {
        header("Location: " . base_url('dashboard/index.php'));
        exit;
    }
}

// Tamu / Petugas Lapangan langsung diarahkan ke form inspeksi terbuka
header("Location: " . base_url('inspeksi/index.php'));
exit;

