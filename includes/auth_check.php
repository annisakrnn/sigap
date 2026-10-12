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
    $script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
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
        'username'     => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'nama_lengkap' => isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : '',
        'role'         => isset($_SESSION['role']) ? $_SESSION['role'] : '',
        'jabatan'      => isset($_SESSION['jabatan']) ? $_SESSION['jabatan'] : '',
        'no_hp'        => isset($_SESSION['no_hp']) ? $_SESSION['no_hp'] : ''
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
    $current_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    
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

/**
 * CSRF Protection Helpers
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    }
    $session_token = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if (empty($session_token) || empty($token)) {
        return false;
    }
    return hash_equals($session_token, $token);
}

function require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
                echo json_encode(['ok' => false, 'pesan' => 'Token CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan refresh halaman.']);
                exit;
            }
            set_flash('error', 'Token keamanan (CSRF) tidak valid atau sesi telah berakhir. Silakan ulangi kembali.');
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : base_url('index.php');
            header("Location: " . $referer);
            exit;
        }
    }
}

