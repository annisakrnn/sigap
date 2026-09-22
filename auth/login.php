<?php
/**
 * Halaman Login & Quick Demo Switch
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = get_db_connection();
$db_error = false;
if (!$pdo) {
    $db_error = true;
}

// Jika sudah login, redirect sesuai role
if (is_logged_in()) {
    $user = get_logged_user();
    if ($user['role'] === 'manajemen') {
        header("Location: " . base_url('dashboard/index.php'));
    } else {
        header("Location: " . base_url('inspeksi/index.php'));
    }
    exit;
}

$error_msg = '';

// Handle POST Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error_msg = "Silakan masukkan username dan password.";
    } elseif ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set Session
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['jabatan']      = $user['jabatan'];
            $_SESSION['no_hp']        = $user['no_hp'];

            set_flash('success', "Selamat datang kembali, <strong>" . htmlspecialchars($user['nama_lengkap']) . "</strong>!");
            
            if ($user['role'] === 'manajemen') {
                header("Location: " . base_url('dashboard/index.php'));
            } else {
                header("Location: " . base_url('inspeksi/index.php'));
            }
            exit;
        } else {
            $error_msg = "Username atau password yang Anda masukkan tidak sesuai.";
        }
    } else {
        $error_msg = "Tidak dapat terhubung ke database. Silakan jalankan setup database.";
    }
}

// Handle Quick Demo Login via GET (?demo=...)
if (isset($_GET['demo']) && $pdo) {
    $demo_user = trim($_GET['demo']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $demo_user]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['jabatan']      = $user['jabatan'];
        $_SESSION['no_hp']        = $user['no_hp'];

        set_flash('success', "Login Demo Berhasil sebagai <strong>" . htmlspecialchars($user['nama_lengkap']) . "</strong> (" . ($user['role'] === 'manajemen' ? 'Manajemen Atasan' : 'Petugas') . ")");
        if ($user['role'] === 'manajemen') {
            header("Location: " . base_url('dashboard/index.php'));
        } else {
            header("Location: " . base_url('inspeksi/index.php'));
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIGAP | Sistem Informasi Gelar Alat & Perlengkapan</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: radial-gradient(circle at 50% 0%, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -20%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.12) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 440px;
            padding: 2.5rem 2.25rem;
            position: relative;
            z-index: 1;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo .icon {
            width: 52px;
            height: 52px;
            margin: 0 auto 14px;
            background: linear-gradient(135deg, #2563eb 0%, #38bdf8 100%);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            box-shadow: 0 8px 20px -4px rgba(37, 99, 235, 0.4);
        }
        .demo-section {
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }
        .demo-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 9px 12px;
            margin-bottom: 8px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.825rem;
            font-weight: 600;
            color: var(--text-body);
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .demo-btn:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">
        <div class="icon">
            <i class="fa-solid fa-bolt-lightning"></i>
        </div>
        <h2 style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px; color: var(--text-main);">SIGAP</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);">Sistem Informasi Gelar Alat & Perlengkapan</p>
    </div>

    <?php if ($db_error): ?>
        <div class="alert alert-warning" style="font-size:0.85rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                Database belum terpasang atau MySQL belum aktif.<br>
                <a href="<?= base_url('setup.php') ?>" style="font-weight:bold;text-decoration:underline;">Klik di sini untuk Setup Otomatis &rarr;</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error" style="font-size:0.85rem;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?= htmlspecialchars($error_msg) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: petugas_yandal / manager_balong" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password Anda" required>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 6px;">
            <i class="fa-solid fa-right-to-bracket"></i> Masuk ke Sistem
        </button>
    </form>

    <div style="text-align: center; margin-top: 18px;">
        <a href="<?= base_url('setup.php') ?>" style="font-size: 0.8rem; color: var(--text-muted); transition: color 0.15s;">
            <i class="fa-solid fa-gear" style="margin-right:4px;"></i> Reset / Setup Database Ulang
        </a>
    </div>
</div>

</body>
</html>
