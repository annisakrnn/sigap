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
    // Strategi: cari posisi folder project di dalam script_name
    // Nama folder yang dikenal sebagai folder project root
    $project_folders = ['sigap'];
    
    $segments = explode('/', trim($script_name, '/'));
    $root_index = -1;
    
    // Cari folder project root dari kiri
    foreach ($segments as $idx => $seg) {
        if (in_array(strtolower($seg), $project_folders)) {
            $root_index = $idx;
            break;
        }
    }
    
    if ($root_index >= 0) {
        // Ada nama folder project, bangun root sampai (dan termasuk) folder project
        $root_parts = array_slice($segments, 0, $root_index + 1);
        $base_dir = '/' . implode('/', $root_parts);
    } else {
        // Tidak ada folder project yang dikenali (dijalankan langsung di root web server)
        // Cari subfolder yang dikenal dan strip semuanya
        $known_subs = ['auth', 'inspeksi', 'dashboard', 'approval', 'cetak', 'riwayat',
                       'master', 'includes', 'config', 'database', 'assets'];
        $base_parts = [];
        foreach ($segments as $seg) {
            if (in_array(strtolower($seg), $known_subs) || pathinfo($seg, PATHINFO_EXTENSION)) {
                break;
            }
            $base_parts[] = $seg;
        }
        $base_dir = $base_parts ? '/' . implode('/', $base_parts) : '';
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
