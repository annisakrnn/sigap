<?php
/**
 * Migration Script: Atur regu_tipe = 'roda_3' untuk 4 alat SR APP
 * (Hidrolik Dies, Kunci Inggris 8 Inch, Palu 1/2 kg, Betel)
 * agar terhapus/tidak muncul pada Regu Roda 2
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
if (!$pdo) {
    echo "Koneksi database gagal.\n";
    exit(1);
}

// 4 Barang yang dihapus dari Regu Roda 2 (hanya untuk Roda 3)
$target_barang = [
    'Hidrolik Dies',
    'Kunci Inggris 8 Inch',
    'Palu 1/2 kg',
    'Betel'
];

$stmt_find = $pdo->prepare("SELECT id, nama_barang FROM master_barang WHERE nama_barang = :nama");
$stmt_update = $pdo->prepare("UPDATE template_checklist_item SET regu_tipe = 'roda_3' WHERE jenis_pekerjaan = 'sr_app' AND master_barang_id = :bid");

foreach ($target_barang as $nama) {
    $stmt_find->execute([':nama' => $nama]);
    $b = $stmt_find->fetch(PDO::FETCH_ASSOC);
    if ($b) {
        $stmt_update->execute([':bid' => $b['id']]);
        echo "Berhasil update '{$b['nama_barang']}' (ID {$b['id']}) menjadi regu_tipe = 'roda_3'.\n";
    } else {
        echo "Barang '{$nama}' tidak ditemukan di master_barang.\n";
    }
}

echo "Migrasi SR APP Regu Roda 2 selesai.\n";
