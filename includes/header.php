<?php
/**
 * Global Header SIGAP - Sidebar Layout
 */
require_once __DIR__ . '/auth_check.php';
$user = get_logged_user();
$current_page = basename($_SERVER['PHP_SELF']);

// Hitung laporan menunggu approval untuk badge manajemen
$pending_count = 0;
if ($user && $user['role'] === 'manajemen') {
    $pdo = get_db_connection();
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM gelar_alat_header WHERE status = 'submitted'");
            $pending_count = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {}
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="theme-color" content="#0072ce">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>SIGAP - Sistem Informasi Gelar Alat & Perlengkapan</title>
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/icons/icon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('assets/icons/icon-192.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php if ($user): ?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay no-print" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar Kiri -->
<aside class="sidebar no-print" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-bolt-lightning"></i>
        </div>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-name">SIGAP</span>
            <span class="sidebar-brand-sub">Gelar Alat & Perlengkapan</span>
        </div>
        <button class="sidebar-close-btn" onclick="closeSidebar()" title="Tutup menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- User Info Card -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
            <span class="user-role-badge role-<?= $user['role'] ?>">
                <?= $user['role'] === 'manajemen' ? 'Manajemen' : 'Petugas' ?>
            </span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="sidebar-nav-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <?php if ($user['role'] === 'petugas'): ?>
                <li>
                    <a href="<?= base_url('inspeksi/index.php') ?>" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/inspeksi') !== false ? 'active' : '' ?>">
                        <i class="fa-solid fa-clipboard-check"></i>
                        <span>Form Gelar Alat</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('riwayat/index.php') ?>" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/riwayat') !== false ? 'active' : '' ?>">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Riwayat Saya</span>
                    </a>
                </li>
            <?php else: // manajemen ?>
                <li>
                    <a href="<?= base_url('dashboard/index.php') ?>" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dashboard K3</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('riwayat/index.php?status=submitted') ?>" class="sidebar-link <?= isset($_GET['status']) && $_GET['status'] === 'submitted' ? 'active' : '' ?>">
                        <i class="fa-solid fa-file-signature"></i>
                        <span>Antrean Approval</span>
                        <?php if ($pending_count > 0): ?>
                            <span class="sidebar-badge"><?= $pending_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('riwayat/index.php') ?>" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/riwayat') !== false && (!isset($_GET['status']) || $_GET['status'] !== 'submitted') ? 'active' : '' ?>">
                        <i class="fa-solid fa-folder-open"></i>
                        <span>Rekap Berita Acara</span>
                    </a>
                </li>

                <div class="sidebar-nav-label" style="margin-top:14px;">Master Data</div>
                <li>
                    <a href="<?= base_url('master/regu/index.php') ?>" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/master/regu') !== false ? 'active' : '' ?>">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                        <span>Regu & Armada</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('master/barang/index.php') ?>" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/master/barang') !== false ? 'active' : '' ?>">
                        <i class="fa-solid fa-toolbox"></i>
                        <span>Katalog Peralatan</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('master/template/index.php') ?>" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/master/template') !== false ? 'active' : '' ?>">
                        <i class="fa-solid fa-list-check"></i>
                        <span>Template Checklist</span>
                    </a>
                </li>
                <li>
                    <a href="<?= base_url('master/users/index.php') ?>" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/master/users') !== false ? 'active' : '' ?>">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Kelola Pengguna</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Sidebar Footer: Logout -->
    <div class="sidebar-footer">
        <a href="<?= base_url('auth/logout.php') ?>" class="sidebar-logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Keluar</span>
        </a>
    </div>
</aside>

<!-- Top Bar (hanya judul halaman + toggle) -->
<div class="topbar no-print">
    <button class="topbar-toggle" id="sidebarToggle" onclick="openSidebar()" title="Buka menu">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="topbar-title">
        <?= isset($page_title) ? htmlspecialchars($page_title) : 'SIGAP' ?>
    </div>
    <div class="topbar-right">
        <span class="topbar-date">
            <i class="fa-regular fa-calendar"></i>
            <?= date('d M Y') ?>
        </span>
        <!-- Notifikasi Bell -->
        <div class="notif-wrap" id="notifWrap">
            <button class="notif-bell" id="notifBell" onclick="toggleNotifPanel()" title="Notifikasi">
                <i class="fa-solid fa-bell"></i>
                <span class="notif-count" id="notifCount" style="display:none;">0</span>
            </button>
            <!-- Panel Dropdown -->
            <div class="notif-panel" id="notifPanel" style="display:none;">
                <div class="notif-panel-header">
                    <span><i class="fa-solid fa-bell" style="margin-right:6px;"></i>Notifikasi</span>
                    <button class="notif-baca-semua" id="notifBacaSemua" onclick="bacaSemuaNotif()" style="display:none;">Tandai semua dibaca</button>
                </div>
                <div class="notif-list" id="notifList">
                    <div class="notif-empty"><i class="fa-regular fa-bell-slash"></i><br>Belum ada notifikasi</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Wrapper -->
<div class="app-layout">
    <div class="sidebar-spacer no-print"></div>
    <main class="main-content">
        <div class="container">
            <?php if ($flash_success = get_flash('success')): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div><?= $flash_success ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flash_error = get_flash('error')): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><?= $flash_error ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flash_warning = get_flash('warning')): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= $flash_warning ?></div>
                </div>
            <?php endif; ?>

<?php else: ?>
<!-- Header Publik / Mode Petugas Lapangan Tanpa Login -->
<header class="topbar no-print" style="left:0; right:0; padding:0 24px; background:rgba(255,255,255,0.92); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
    <div style="display:flex; align-items:center; gap:12px;">
        <div style="width:38px; height:38px; border-radius:var(--radius-md); background:linear-gradient(135deg, #2563eb, #1d4ed8); display:flex; align-items:center; justify-content:center; color:#ffffff; font-size:1.1rem; box-shadow:0 2px 6px rgba(37,99,235,0.3);">
            <i class="fa-solid fa-bolt-lightning"></i>
        </div>
        <div>
            <div style="font-weight:800; font-size:1.05rem; letter-spacing:-0.02em; line-height:1.2; color:var(--text-main);">
                <a href="<?= base_url('inspeksi/index.php') ?>" style="color:var(--text-main); text-decoration:none;">SIGAP</a>
            </div>
            <div style="font-size:0.725rem; color:var(--text-muted); line-height:1.1;">Digitalisasi Gelar Alat & K3 &middot; PLN ULP Balong</div>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:10px;">
        <span class="badge" style="background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; font-size:0.72rem; padding:4px 10px;">
            <i class="fa-solid fa-clipboard-check"></i> Mode Petugas Lapangan
        </span>
        <a href="<?= base_url('auth/login.php') ?>" class="btn btn-outline btn-sm" style="font-size:0.8rem; padding:6px 14px; border-radius:var(--radius-md); display:inline-flex; align-items:center; gap:6px;">
            <i class="fa-solid fa-lock"></i> Login Manajemen
        </a>
    </div>
</header>

<div class="app-layout">
    <main class="main-content" style="width:100%;">
        <div class="container">
            <?php if ($flash_success = get_flash('success')): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div><?= $flash_success ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flash_error = get_flash('error')): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><?= $flash_error ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flash_warning = get_flash('warning')): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= $flash_warning ?></div>
                </div>
            <?php endif; ?>
<?php endif; ?>

