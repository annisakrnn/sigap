<?php
/**
 * Migration: Tambah kolom unit & nopol di gelar_alat_header
 * Pilihan Unit: ULP Balong, ULP Pacitan, ULP Ponorogo, ULP Trenggalek, UP3 Ponorogo
 * Pengisian Plat Nomor Kendaraan per inspeksi
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

// 1. Cek & tambah kolom unit
$stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'gelar_alat_header'
      AND COLUMN_NAME  = 'unit'");
if (!(int)$stmt->fetchColumn()) {
    try {
        $pdo->exec("ALTER TABLE gelar_alat_header ADD COLUMN unit VARCHAR(100) NOT NULL DEFAULT 'ULP Balong' AFTER bulan_tahun");
        $steps[] = "Kolom 'unit' berhasil ditambahkan ke gelar_alat_header.";
    } catch (PDOException $e) {
        $errors[] = "Gagal tambah kolom 'unit': " . $e->getMessage();
    }
} else {
    $steps[] = "Kolom 'unit' sudah ada di gelar_alat_header.";
}

// 2. Cek & tambah kolom nopol
$stmt2 = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'gelar_alat_header'
      AND COLUMN_NAME  = 'nopol'");
if (!(int)$stmt2->fetchColumn()) {
    try {
        $pdo->exec("ALTER TABLE gelar_alat_header ADD COLUMN nopol VARCHAR(30) NULL AFTER unit");
        $steps[] = "Kolom 'nopol' berhasil ditambahkan ke gelar_alat_header.";
    } catch (PDOException $e) {
        $errors[] = "Gagal tambah kolom 'nopol': " . $e->getMessage();
    }
} else {
    $steps[] = "Kolom 'nopol' sudah ada di gelar_alat_header.";
}

// 3. Backfill data lama jika ada
if (empty($errors)) {
    try {
        $affected = $pdo->exec("
            UPDATE gelar_alat_header h
            JOIN regu r ON r.id = h.regu_id
            SET h.nopol = r.nopol
            WHERE h.nopol IS NULL OR h.nopol = ''
        ");
        $steps[] = "Backfill nopol dari data regu: {$affected} baris diperbarui.";
    } catch (PDOException $e) {
        $errors[] = "Gagal backfill nopol: " . $e->getMessage();
    }
}

// Output CLI atau Browser
if (php_sapi_name() === 'cli') {
    foreach ($steps as $s) echo "[OK] $s\n";
    foreach ($errors as $err) echo "[ERR] $err\n";
    echo "Selesai.\n";
} else {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi Unit & Nopol | SIGAP</title>
    <style>
        body { font-family: monospace; background:#0f172a; color:#f8fafc; padding:2rem; }
        .ok { color: #4ade80; margin-bottom: 0.5rem; }
        .err { color: #f87171; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <h2>Migrasi Kolom Unit & Plat Nomor Kendaraan</h2>
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

