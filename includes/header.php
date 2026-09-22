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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>SIGAP - Sistem Informasi Gelar Alat & Perlengkapan</title>
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
<!-- Jika belum login, tampilkan header minimal -->
<main class="main-content">
    <div class="container">
<?php endif; ?>
