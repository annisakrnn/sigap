<?php
/**
 * Migration: Tambah kolom jabatan_petugas di gelar_alat_header
 * Agar jabatan petugas bisa diisi manual pada form dan tersimpan per dokumen.
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = get_db_connection();
if (!$pdo) { die("Koneksi database gagal."); }

$steps  = [];
$errors = [];

// 1. Cek/tambah kolom jabatan_petugas
$stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'gelar_alat_header'
      AND COLUMN_NAME  = 'jabatan_petugas'");
if (!(int)$stmt->fetchColumn()) {
    try {
        $pdo->exec("ALTER TABLE gelar_alat_header ADD COLUMN jabatan_petugas VARCHAR(100) NULL AFTER nama_pelaksana_1");
        $steps[] = "Kolom jabatan_petugas ditambahkan ke gelar_alat_header.";
    } catch (PDOException $e) {
        $errors[] = "Gagal tambah jabatan_petugas: " . $e->getMessage();
    }
} else {
    $steps[] = "Kolom jabatan_petugas sudah ada.";
}

// 2. Isi jabatan_petugas dari users.jabatan untuk data lama yang belum punya
if (empty($errors)) {
    try {
        $affected = $pdo->exec("
            UPDATE gelar_alat_header h
            JOIN users u ON u.id = h.petugas_id
            SET h.jabatan_petugas = u.jabatan
            WHERE h.jabatan_petugas IS NULL OR h.jabatan_petugas = ''
        ");
        $steps[] = "Backfill jabatan_petugas dari users.jabatan: {$affected} baris diperbarui.";
    } catch (PDOException $e) {
        $errors[] = "Gagal backfill jabatan_petugas: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Migration jabatan_petugas | SIGAP</title>
<style>body{font-family:monospace;background:#111;color:#e5e7eb;padding:2rem;}h2{color:#60a5fa;}.ok{color:#4ade80;}.err{color:#f87171;}</style>
</head>
<body>
<h2>Migration: jabatan_petugas di gelar_alat_header</h2><hr>
<?php foreach ($steps  as $s): ?><p class="ok"><?= htmlspecialchars($s) ?></p><?php endforeach; ?>
<?php foreach ($errors as $e): ?><p class="err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
<hr><p class="<?= empty($errors) ? 'ok' : 'err' ?>"><?= empty($errors) ? 'Migration selesai tanpa error.' : 'Migration selesai dengan error.' ?></p>
</body></html>
