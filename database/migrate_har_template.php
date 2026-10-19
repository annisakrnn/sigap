<?php
/**
 * Migration Script: Update Template Checklist HAR menjadi mandiri (Regu HAR A Truk Hino AE 8280 BH)
 * Persis sesuai urutan fisik pada foto dokumen:
 * - ALAT KERJA (49 Item: 1 - 49)
 * - ALAT K3 BEREGU (13 Item: 1 - 13)
 * - LAIN LAIN (3 Item: 1 - 3)
 * Total 65 Item.
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
if (!$pdo) {
    echo "Koneksi database gagal.\n";
    exit(1);
}

// 65 Item Checklist HAR Persis Sesuai Foto
$har_items = [
    // ===== ALAT KERJA (49 Item: No 1 - 49) =====
    ['Tangga Fiber ( 2 section ladder ) 9,6 mtr', 'alat_kerja', 'Bh', 1],
    ['Telescopic hotstick 20 kV; 10,5 mtr', 'alat_kerja', 'Bh', 1],
    ['Telescopic hotstick 20 kV; 3 mtr', 'alat_kerja', 'Bh', 1],
    ['Chain saw 14"', 'alat_kerja', 'Bh', 2],
    ['Groundcluster lengkap', 'alat_kerja', 'Set', 2],
    ['Insulation tester 1.000 Volt Digital', 'alat_kerja', 'Bh', 1],
    ['Insulation tester 5.000 Volt Digital', 'alat_kerja', 'Bh', 1],
    ['Insulation tester 10.000 Volt Analog', 'alat_kerja', 'Bh', 1],
    ['Earth Tester clip on / online', 'alat_kerja', 'Bh', 1],
    ['Comealong (Wire Grip ) 70 - 240 mm²', 'alat_kerja', 'Set', 2],
    ['Strainging Device 2 Ton', 'alat_kerja', 'Bh', 2],
    ['Tang Scoon Hydraulic 10 s/d 70 mm2', 'alat_kerja', 'Bh', 1],
    ['Compression Dies 50 mm - 300 mm', 'alat_kerja', 'Set', 1],
    ['Tang Ampere (Clip on AVO Meter digital ) 600 A', 'alat_kerja', 'Bh', 1],
    ['Phase Sequence Indicator', 'alat_kerja', 'Set', 1],
    ['Wire Cutter s.d 240 mm²', 'alat_kerja', 'Bh', 1],
    ['Tool set lengkap (besar) mobil', 'alat_kerja', 'Bh', 1],
    ['Head Lamp 70 watt charger', 'alat_kerja', 'Bh', 2],
    ['Lampu senter battery (Re-charger)', 'alat_kerja', 'Bh', 1],
    ['Lampu Sorot Halogen 50 Watt', 'alat_kerja', 'Bh', 1],
    ['Power Inverter 100 W', 'alat_kerja', 'Bh', 1],
    ['Fuse Puller', 'alat_kerja', 'Bh', 1],
    ['Palu kecil', 'alat_kerja', 'Bh', 1],
    ['Palu Besar 5 kg', 'alat_kerja', 'Bh', 1],
    ['Linggis Besar', 'alat_kerja', 'Bh', 1],
    ['Parang', 'alat_kerja', 'Bh', 2],
    ['Angkus', 'alat_kerja', 'Bh', 1],
    ['Sosrok Tiang', 'alat_kerja', 'Bh', 1],
    ['Handle LBS Berisolasi', 'alat_kerja', 'Bh', 1],
    ['Isolasi Listrik (3 Warna)', 'alat_kerja', 'Set', 1],
    ['Smarthphone (RAM 8 GB, 128 GB)', 'alat_kerja', 'Bh', 1],
    ['Handheld Holder Smarthphone', 'alat_kerja', 'Bh', 1],
    ['Power bank 20.000 mAh', 'alat_kerja', 'Unit', 1],
    ['GPS Tracker', 'alat_kerja', 'Bh', 1],
    ['Roll Besi (kerekan kecil)', 'alat_kerja', 'Unit', 1],
    ['Plat Form (pijakan di atas tiang)', 'alat_kerja', 'Unit', 1],
    ['Amr Tie band lengkap ring', 'alat_kerja', 'Unit', 1],
    ['Kunci L hexa', 'alat_kerja', 'Set', 1],
    ['Kunci L bintang', 'alat_kerja', 'Set', 1],
    ['Kunci Moment', 'alat_kerja', 'Set', 1],
    ['Kunci Pipa 4 "', 'alat_kerja', 'Bh', 1],
    ['Gergaji besi', 'alat_kerja', 'Bh', 1],
    ['Tali baja (Seling) 20 mtr', 'alat_kerja', 'Bh', 1],
    ['Tali Nilon 12 mm 20 meter', 'alat_kerja', 'Bh', 1],
    ['Tali Manila 12mm2 (20m)', 'alat_kerja', 'Bh', 1],
    ['Tali Tampar 20 Mtr', 'alat_kerja', 'Bh', 1],
    ['Tali Pengikat Tangga', 'alat_kerja', 'Bh', 1],
    ['Majun', 'alat_kerja', 'Lot', 1],
    ['Cairan WD 40', 'alat_kerja', 'Bh', 1],

    // ===== ALAT K3 BEREGU (13 Item: No 1 - 13) =====
    ['Full Body Hardness + double lanyard', 'k3_safety', 'Bh', 2],
    ['Sarung Tangan Tahan Tegangan 1kV (karet)', 'k3_safety', 'Psg', 2],
    ['Sarung Tangan 30 kV kelas 3', 'k3_safety', 'Psg', 2],
    ['Sepatu karet 30 kV kelas 3', 'k3_safety', 'Psg', 2],
    ['Sepatu Boots', 'k3_safety', 'Psg', 1],
    ['Voltage Detector (Low & High Voltage) Non Contact', 'k3_safety', 'Bh', 1],
    ['Tanda Peringatan kerja', 'k3_safety', 'Bh', 2],
    ['LoTo (Lock Out dan Tag Out), Gembok & Rantai', 'k3_safety', 'Bh', 4],
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

// Hapus template HAR lama
$pdo->exec("DELETE FROM template_checklist_item WHERE jenis_pekerjaan = 'har'");

$urutan = 1;
foreach ($har_items as $item) {
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
    } else {
        // Update kategori dan satuan agar sesuai foto
        $pdo->prepare("UPDATE master_barang SET kategori = :kategori, satuan_default = :satuan WHERE id = :id")
            ->execute([':kategori' => $kategori, ':satuan' => $satuan, ':id' => $b['id']]);
    }

    // 2. Hubungkan ke template_checklist_item dengan urutan tepat
    $stmt_tpl->execute([
        ':jenis' => 'har',
        ':regu_tipe' => 'all',
        ':barang_id' => $b['id'],
        ':jml' => $jml,
        ':urutan' => $urutan++
    ]);
}

echo "Migrasi template HAR selesai. Total " . ($urutan - 1) . " item checklist HAR mandiri berhasil disimpan sesuai foto.\n";
