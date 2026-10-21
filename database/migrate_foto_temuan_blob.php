<?php
/**
 * Migration: Ubah kolom foto_temuan dari VARCHAR(255) ke MEDIUMBLOB
 * Agar foto langsung tersimpan di MySQL tanpa menyentuh folder server.
 *
 * Jalankan sekali: php migrate_foto_temuan_blob.php
 * atau akses via browser (pastikan hanya diakses oleh admin).
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$steps = [];
$errors = [];

// 1. Cek apakah kolom foto_temuan sudah MEDIUMBLOB
$stmt = $pdo->prepare("
    SELECT DATA_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'gelar_alat_detail'
      AND COLUMN_NAME  = 'foto_temuan'
");
$stmt->execute();
$col_type = $stmt->fetchColumn();

if (strtolower($col_type) !== 'mediumblob') {
    try {
        $pdo->exec("ALTER TABLE gelar_alat_detail MODIFY COLUMN foto_temuan MEDIUMBLOB NULL");
        $steps[] = "Kolom foto_temuan diubah ke MEDIUMBLOB.";
    } catch (PDOException $e) {
        $errors[] = "Gagal mengubah foto_temuan: " . $e->getMessage();
    }
} else {
    $steps[] = "Kolom foto_temuan sudah MEDIUMBLOB, tidak perlu diubah.";
}

// 2. Cek/tambah kolom foto_temuan_mime (VARCHAR untuk simpan mime type: image/jpeg, dll)
$stmt2 = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'gelar_alat_detail'
      AND COLUMN_NAME  = 'foto_temuan_mime'
");
$stmt2->execute();
$mime_exists = (int)$stmt2->fetchColumn();

if (!$mime_exists) {
    try {
        $pdo->exec("ALTER TABLE gelar_alat_detail ADD COLUMN foto_temuan_mime VARCHAR(30) NULL AFTER foto_temuan");
        $steps[] = "Kolom foto_temuan_mime ditambahkan.";
    } catch (PDOException $e) {
        $errors[] = "Gagal menambah foto_temuan_mime: " . $e->getMessage();
    }
} else {
    $steps[] = "Kolom foto_temuan_mime sudah ada.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migration: foto_temuan ke BLOB | SIGAP</title>
    <style>
        body { font-family: monospace; background: #111; color: #e5e7eb; padding: 2rem; }
        h2 { color: #60a5fa; }
        .ok  { color: #4ade80; }
        .err { color: #f87171; }
        .info{ color: #fbbf24; }
    </style>
</head>
<body>
<h2>Migration: foto_temuan ke MEDIUMBLOB</h2>
<hr>
<?php foreach ($steps as $s): ?>
    <p class="ok"><?= htmlspecialchars($s) ?></p>
<?php endforeach; ?>
<?php foreach ($errors as $e): ?>
    <p class="err"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>
<?php if (empty($errors)): ?>
    <hr><p class="ok">Migration selesai tanpa error.</p>
<?php else: ?>
    <hr><p class="err">Migration selesai dengan beberapa error di atas.</p>
<?php endif; ?>
</body>
</html>
