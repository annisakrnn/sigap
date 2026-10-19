<?php
/**
 * Migration Script: Tambah Template Checklist Khusus Regu HAR B Mobil Carry (E 8255 BH)
 * dan pisahkan tipe regu HAR menjadi 'har_truk' dan 'har_carry'
 * Sesuai dokumen fisik gambar:
 * - ALAT KERJA (23 Item)
 * - ALAT K3 BEREGU (13 Item)
 * - LAIN LAIN (3 Item)
 * Total: 39 Item.
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
if (!$pdo) {
    echo "Koneksi database gagal.\n";
    exit(1);
}

// 1. Update kolom regu_tipe di tabel regu
$pdo->exec("UPDATE regu SET regu_tipe = 'har_truk' WHERE id = 6");
$pdo->exec("UPDATE regu SET regu_tipe = 'har_carry' WHERE id = 7");

// 2. Update template HAR Truk Hino lama yang masih 'all' menjadi 'har_truk'
$pdo->exec("UPDATE template_checklist_item SET regu_tipe = 'har_truk' WHERE jenis_pekerjaan = 'har' AND regu_tipe = 'all'");

// 3. Daftar 39 Item Checklist Regu HAR B Mobil Carry (E 8255 BH) persis sesuai foto
$carry_items = [
    // ===== ALAT KERJA (23 Item: No 1 - 23) =====
    ['Tangga Fiber ( 2 section ladder ) 9,6 mtr', 'alat_kerja', 'Bh', 1],
    ['Telescopic hotstick 20 kV; 10,5 mtr', 'alat_kerja', 'Bh', 1],
    ['Chain saw 14"', 'alat_kerja', 'Bh', 2],
    ['Tool set lengkap (besar) mobil', 'alat_kerja', 'Bh', 1],
    ['Head Lamp 70 watt charger', 'alat_kerja', 'Bh', 2],
    ['Lampu senter battery (Re-charger)', 'alat_kerja', 'Bh', 1],
    ['Lampu Sorot Halogen 50 Watt', 'alat_kerja', 'Bh', 1],
    ['Power Inverter 100 W', 'alat_kerja', 'Bh', 1],
    ['Fuse Puller', 'alat_kerja', 'Bh', 1],
    ['Parang', 'alat_kerja', 'Bh', 4],
    ['Angkus', 'alat_kerja', 'Bh', 2],
    ['Handle LBS Berisolasi', 'alat_kerja', 'Set', 1],
    ['Smarthphone (RAM 8 GB, 128 GB)', 'alat_kerja', 'Bh', 1],
    ['Handheld Holder Smarthphone', 'alat_kerja', 'Unit', 1],
    ['Power bank 20.000 mAh', 'alat_kerja', 'Bh', 1],
    ['GPS Tracker', 'alat_kerja', 'Unit', 1],
    ['Kunci L hexa', 'alat_kerja', 'Set', 1],
    ['Kunci L bintang', 'alat_kerja', 'Set', 1],
    ['Tali Nilon 12 mm 20 meter', 'alat_kerja', 'Bh', 1],
    ['Tali Manila 12mm2 (20m)', 'alat_kerja', 'Bh', 1],
    ['Tali Tampar 20 Mtr', 'alat_kerja', 'Bh', 1],
    ['Tali Pengikat Tangga', 'alat_kerja', 'Bh', 1],
    ['Majun', 'alat_kerja', 'Lot', 1],

    // ===== ALAT K3 BEREGU (13 Item: No 1 - 13) =====
    ['Full Body Hardness + double lanyard', 'k3_safety', 'Bh', 2],
    ['Sarung Tangan Tahan Tegangan 1kV (karet)', 'k3_safety', 'Psg', 1],
    ['Sarung Tangan 30 kV kelas 3', 'k3_safety', 'Psg', 1],
    ['Sepatu karet 30 kV kelas 3', 'k3_safety', 'Psg', 1],
    ['Sepatu Boots', 'k3_safety', 'Psg', 1],
    ['Voltage Detector (Low & High Voltage) Non Contact', 'k3_safety', 'Bh', 1],
    ['Tanda Peringatan kerja', 'k3_safety', 'Bh', 2],
    ['LoTo (Lock Out dan Tag Out), Gembok & Rantai', 'k3_safety', 'Bh', 2],
    ['Rantai Plastik 10 m', 'k3_safety', 'Bh', 1],
    ['Traffic Cone', 'k3_safety', 'Set', 2],
    ['Tempat penyimpanan sarung tangan 20 kv', 'k3_safety', 'Bh', 1],
    ['Kotak P3K lengkap isi', 'k3_safety', 'Bh', 1],
    ['APAR 3 kg', 'k3_safety', 'Unit', 1],

    // ===== LAIN LAIN (3 Item: No 1 - 3) =====
    ['Dongkrak + Kunci Ban', 'kendaraan_pendukung', 'Set', 1],
    ['Kondisi Ban', 'kendaraan_pendukung', 'Bh', 4],
    ['Ban Cadangan', 'kendaraan_pendukung', 'Psg', 1],
];

// Helper prepare queries
$stmt_barang = $pdo->prepare("INSERT INTO master_barang (nama_barang, kategori, satuan_default) 
    VALUES (:nama, :kategori, :satuan)
    ON DUPLICATE KEY UPDATE kategori = VALUES(kategori), satuan_default = VALUES(satuan_default)");

$stmt_find_barang = $pdo->prepare("SELECT id FROM master_barang WHERE nama_barang = :nama LIMIT 1");
$stmt_tpl = $pdo->prepare("INSERT INTO template_checklist_item (jenis_pekerjaan, regu_tipe, master_barang_id, jumlah_standar, urutan)
    VALUES (:jenis, :regu_tipe, :barang_id, :jml, :urutan)");

// Hapus template HAR carry lama jika ada
$pdo->exec("DELETE FROM template_checklist_item WHERE jenis_pekerjaan = 'har' AND regu_tipe = 'har_carry'");

$urutan = 1;
foreach ($carry_items as $item) {
    $nama = $item[0];
    $kategori = $item[1];
    $satuan = $item[2];
    $jml = $item[3];

    // 1. Pastikan barang ada di master_barang
    $stmt_find_barang->execute([':nama' => $nama]);
    $b = $stmt_find_barang->fetch();
    if (!$b) {
        $stmt_barang->execute([
            ':nama' => $nama,
            ':kategori' => $kategori,
            ':satuan' => $satuan
        ]);
        $stmt_find_barang->execute([':nama' => $nama]);
        $b = $stmt_find_barang->fetch();
    }

    // 2. Hubungkan ke template_checklist_item untuk regu_tipe 'har_carry'
    $stmt_tpl->execute([
        ':jenis' => 'har',
        ':regu_tipe' => 'har_carry',
        ':barang_id' => $b['id'],
        ':jml' => $jml,
        ':urutan' => $urutan++
    ]);
}

echo "Total " . ($urutan - 1) . " item checklist HAR Mobil Carry (E 8255 BH) berhasil disimpan.\n";
