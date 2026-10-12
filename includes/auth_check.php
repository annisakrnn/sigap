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
    static $base_dir = null;

    if ($base_dir === null) {
        // Hitung root project berdasarkan lokasi fisik file ini.
        // File ini ada di: <project_root>/includes/auth_check.php
        // Jadi project root = dirname(dirname(__FILE__))
        $project_root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $doc_root     = str_replace('\\', '/', realpath(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : ''));

        if ($doc_root && strpos($project_root, $doc_root) === 0) {
            // Project berada di bawah document root → hitung sub-path relatif
            $base_dir = substr($project_root, strlen($doc_root));
        } else {
            // Fallback: gunakan SCRIPT_NAME untuk menebak base path
            $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
            $base_dir = rtrim(dirname(dirname($script)), '/');
        }

        // Pastikan tidak ada trailing slash
        $base_dir = rtrim($base_dir, '/');
    }

    return $base_dir . '/' . ltrim($path, '/');
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

