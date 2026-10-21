<?php
/**
 * Migrasi Kolom Tanda Tangan 3 Pihak (Petugas -> TL/Manajemen -> Manager)
 * SIGAP
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Gagal terkoneksi ke database.");
}

$columns_to_add = [
    'nama_manager'    => "VARCHAR(100) NULL AFTER jabatan_manajemen",
    'jabatan_manager' => "VARCHAR(100) NULL AFTER nama_manager",
    'ttd_manager'     => "LONGTEXT NULL AFTER jabatan_manager",
    'tanggal_manager' => "DATETIME NULL AFTER ttd_manager",
];

// Cek kolom yang sudah ada
$stmt = $pdo->query("DESCRIBE gelar_alat_header");
$existing_cols = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing_cols[] = $row['Field'];
}

echo "Memeriksa skema tabel gelar_alat_header...\n";

foreach ($columns_to_add as $col_name => $definition) {
    if (!in_array($col_name, $existing_cols)) {
        $sql = "ALTER TABLE gelar_alat_header ADD COLUMN $col_name $definition";
        $pdo->exec($sql);
        echo "[TAMBAH] Kolom $col_name berhasil ditambahkan.\n";
    } else {
        echo "[OK] Kolom $col_name sudah ada.\n";
    }
}

// Update data existing: jika nama_pejabat_manajemen sebelumnya diisi dengan manager,
// atau isi default manager ULP jika kosong
$stmt_update = $pdo->prepare("
    UPDATE gelar_alat_header 
    SET nama_manager = COALESCE(nama_manager, 'Manager ULP'),
        jabatan_manager = COALESCE(jabatan_manager, 'Manager ULP')
    WHERE nama_manager IS NULL AND status = 'approved'
");
$stmt_update->execute();

echo "Migrasi alur tanda tangan 3 tingkat selesai dengan sukses!\n";
