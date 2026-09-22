<?php
/**
 * SIGAP - Migrasi: Tambah kolom sudah_diperbaiki & tabel notifikasi
 * Jalankan sekali via browser: http://localhost/sigap/migrate_done.php
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_gelar_alat');

function msg($text, $ok = true) {
    $c = $ok ? '#10b981' : '#ef4444';
    echo "<div style='font-family:sans-serif;padding:8px 14px;margin:6px 0;border-left:4px solid $c;background:#f8fafc;border-radius:4px;'>$text</div>";
}

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 1. Tambah kolom ke gelar_alat_detail
    $cols = $pdo->query("SHOW COLUMNS FROM gelar_alat_detail LIKE 'sudah_diperbaiki'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE gelar_alat_detail
            ADD COLUMN sudah_diperbaiki TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN tgl_diperbaiki DATETIME NULL,
            ADD COLUMN diperbaiki_oleh INT NULL,
            ADD COLUMN catatan_perbaikan VARCHAR(255) NULL");
        msg("✓ Kolom sudah_diperbaiki, tgl_diperbaiki, diperbaiki_oleh, catatan_perbaikan ditambahkan ke gelar_alat_detail");
    } else {
        msg("⚠ Kolom sudah_diperbaiki sudah ada di gelar_alat_detail (dilewati)", false);
    }

    // 2. Buat tabel notifikasi
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifikasi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'Penerima notifikasi',
        judul VARCHAR(150) NOT NULL,
        pesan TEXT NOT NULL,
        tipe ENUM('info','sukses','peringatan','perbaikan') DEFAULT 'info',
        url_aksi VARCHAR(255) NULL COMMENT 'Link terkait',
        sudah_dibaca TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_baca (user_id, sudah_dibaca)
    ) ENGINE=InnoDB");
    msg("✓ Tabel notifikasi siap");

    echo "<div style='font-family:sans-serif;margin-top:16px;padding:12px 14px;background:#d1fae5;border-radius:6px;color:#065f46;font-weight:600;'>
        ✅ Migrasi selesai! <a href='dashboard/index.php' style='color:#0072ce;margin-left:12px;'>→ Buka Dashboard</a>
    </div>";

} catch (Exception $e) {
    msg("❌ Error: " . $e->getMessage(), false);
}
?>
