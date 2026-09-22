<?php
/**
 * Helper Autentikasi, Hak Akses & Sesi
 * SIGAP
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function base_url($path = '') {
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    // Ambil root folder project
    $base_dir = rtrim(dirname($script_name), '/\\');
    // Jika berada dalam subfolder seperti /auth atau /inspeksi, naikkan ke root
    $subfolders = ['/auth', '/inspeksi', '/dashboard', '/approval', '/cetak', '/riwayat'];
    foreach ($subfolders as $sf) {
        if (str_ends_with($base_dir, $sf)) {
            $base_dir = substr($base_dir, 0, -strlen($sf));
            break;
        }
    }
    return rtrim($base_dir, '/') . '/' . ltrim($path, '/');
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'           => $_SESSION['user_id'],
        'username'     => $_SESSION['username'] ?? '',
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
        'role'         => $_SESSION['role'] ?? '',
        'jabatan'      => $_SESSION['jabatan'] ?? '',
        'no_hp'        => $_SESSION['no_hp'] ?? ''
    ];
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Silakan login terlebih dahulu untuk mengakses sistem.";
        header("Location: " . base_url('auth/login.php'));
        exit;
    }
}

function require_role($allowed_roles) {
    require_login();
    $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    $current_role = $_SESSION['role'] ?? '';
    
    if (!in_array($current_role, $roles)) {
        $_SESSION['flash_error'] = "Akses ditolak: Anda tidak memiliki izin untuk membuka halaman tersebut.";
        if ($current_role === 'manajemen') {
            header("Location: " . base_url('dashboard/index.php'));
        } else {
            header("Location: " . base_url('inspeksi/index.php'));
        }
        exit;
    }
}

function set_flash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function get_flash($type) {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}
