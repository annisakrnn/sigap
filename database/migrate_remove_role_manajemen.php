<?php
/**
 * Migration: Hapus role TL & Manajemen, hanya ada Petugas
 * Verifikasi & TTD dilaksanakan secara offline
 * SIGAP
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
if (!$pdo) {
    echo "Koneksi database gagal.\n";
    exit(1);
}

$steps = [];
$errors = [];

try {
    // 1. Update semua user agar memiliki role 'petugas'
    $affected = $pdo->exec("UPDATE users SET role = 'petugas'");
    $steps[] = "Seluruh akun pengguna ({$affected} baris) berhasil diubah menjadi role 'petugas'.";
} catch (PDOException $e) {
    $errors[] = "Gagal update users role: " . $e->getMessage();
}

try {
    // 2. Modifikasi kolom role pada tabel users agar hanya ENUM('petugas')
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('petugas') NOT NULL DEFAULT 'petugas'");
    $steps[] = "Kolom 'role' pada tabel users berhasil diubah menjadi ENUM('petugas') DEFAULT 'petugas'.";
} catch (PDOException $e) {
    $errors[] = "Gagal alter table users: " . $e->getMessage();
}

// Output
if (php_sapi_name() === 'cli') {
    foreach ($steps as $s) echo "[OK] $s\n";
    foreach ($errors as $err) echo "[ERR] $err\n";
    echo "Migrasi selesai.\n";
} else {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi Hapus Role Manajemen | SIGAP</title>
    <style>body { font-family: monospace; background:#0f172a; color:#f8fafc; padding:2rem; } .ok { color: #4ade80; margin-bottom: 0.5rem; } .err { color: #f87171; margin-bottom: 0.5rem; }</style>
</head>
<body>
    <h2>Migrasi Role Pengguna: Khusus Petugas (Verifikasi & TTD Offline)</h2>
    <?php foreach ($steps as $s): ?>
        <div class="ok">[OK] <?= htmlspecialchars($s) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="err">[ERR] <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <br>
    <a href="../inspeksi/index.php" style="color:#38bdf8;">&larr; Kembali ke Aplikasi</a>
</body>
</html>
<?php
}

