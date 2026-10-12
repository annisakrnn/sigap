<?php
/**
 * Migration Script: Tambah kolom foto_perbaikan & pastikan tabel notifikasi ada
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
if (!$pdo) {
    echo "Koneksi database gagal\n";
    exit(1);
}

// 1. Tambah foto_perbaikan ke gelar_alat_detail jika belum ada
$cols = $pdo->query("SHOW COLUMNS FROM gelar_alat_detail LIKE 'foto_perbaikan'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE gelar_alat_detail ADD COLUMN foto_perbaikan VARCHAR(255) NULL AFTER catatan_perbaikan");
    echo "Kolom foto_perbaikan berhasil ditambahkan ke gelar_alat_detail.\n";
} else {
    echo "Kolom foto_perbaikan sudah ada.\n";
}

// 2. Pastikan tabel notifikasi ada
$tables = $pdo->query("SHOW TABLES LIKE 'notifikasi'")->fetchAll();
if (empty($tables)) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifikasi (
        id           INT          AUTO_INCREMENT PRIMARY KEY,
        user_id      INT          NOT NULL COMMENT 'Penerima notifikasi',
        judul        VARCHAR(150) NOT NULL,
        pesan        TEXT         NOT NULL,
        tipe         ENUM('info', 'sukses', 'peringatan', 'perbaikan') DEFAULT 'info',
        url_aksi     VARCHAR(255) NULL COMMENT 'Link aksi terkait',
        sudah_dibaca TINYINT(1)  NOT NULL DEFAULT 0,
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_baca (user_id, sudah_dibaca),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Tabel notifikasi berhasil dibuat.\n";
} else {
    echo "Tabel notifikasi sudah ada.\n";
}

echo "Migrasi database selesai.\n";
