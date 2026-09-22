<?php
/**
 * Logout Handler
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

session_unset();
session_destroy();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('success', "Anda telah berhasil keluar dari sistem.");
header("Location: " . base_url('auth/login.php'));
exit;
