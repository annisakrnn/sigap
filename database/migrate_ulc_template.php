<?php
/**
 * Migration Script: Tambah Template Checklist Khusus ULC Sepeda Motor Honda Verza
 * dan pisahkan dari Mobil Yandal sesuai dokumen fisik gambar.
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
if (!$pdo) {
    echo "Koneksi database gagal.\n";
    exit(1);
}

// 1. Ubah regu_tipe di template_checklist_item menjadi VARCHAR(50) jika masih ENUM
$pdo->exec("ALTER TABLE template_checklist_item MODIFY COLUMN regu_tipe VARCHAR(50) NOT NULL DEFAULT 'all'");

// 2. Tambah kolom regu_tipe di tabel regu jika belum ada
$cols = $pdo->query("SHOW COLUMNS FROM regu LIKE 'regu_tipe'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE regu ADD COLUMN regu_tipe VARCHAR(50) NOT NULL DEFAULT 'all' AFTER jenis_pekerjaan");
    echo "Kolom regu_tipe berhasil ditambahkan ke tabel regu.\n";
}

// Update data regu
$pdo->exec("UPDATE regu SET regu_tipe = 'yandal_mobil' WHERE id = 4");
$pdo->exec("UPDATE regu SET regu_tipe = 'ulc_motor', nopol = 'E 2225 CG' WHERE id = 5");
$pdo->exec("UPDATE regu SET regu_tipe = 'roda_3' WHERE id = 2");
$pdo->exec("UPDATE regu SET regu_tipe = 'roda_2' WHERE id = 3");

// 3. Pastikan item yandal lama yang masih 'all' diubah ke 'yandal_mobil'
$pdo->exec("UPDATE template_checklist_item SET regu_tipe = 'yandal_mobil' WHERE jenis_pekerjaan = 'yandal' AND regu_tipe = 'all'");

// 4. Daftar 27 Item Checklist ULC Sepeda Motor Verza persis sesuai gambar
$ulc_items = [
    // ALAT KERJA (17 Item)
    ['Telescopic hotstick 20 kV ; 10,5 mtr', 'alat_kerja', 'Bh', 1],
    ['Tangga Telescopic 3,8 meter; 150 kg', 'alat_kerja', 'Bh', 1],
    ['Fuse Puller', 'alat_kerja', 'Bh', 1],
    ['GPS Tracker', 'alat_kerja', 'Bh', 1],
    ['Tool set lengkap (kecil) motor', 'alat_kerja', 'Bh', 1],
    ['Kotak alat kerja di motor lengkap braket', 'alat_kerja', 'Bh', 1],
    ['Smarthphone (RAM 8 GB, 128 GB)', 'alat_kerja', 'Bh', 1],
    ['Handheld Holder Smarthphone', 'alat_kerja', 'Bh', 1],
    ['Power bank 20.000 mAh', 'alat_kerja', 'Unit', 1],
    ['Kunci L hexa', 'alat_kerja', 'Set', 1],
    ['Kunci L bintang', 'alat_kerja', 'Set', 1],
    ['Tang Ampere (Clip on AVO Meter digital) 600 A', 'alat_kerja', 'Bh', 1],
    ['Phase Sequence Indicator', 'alat_kerja', 'Unit', 1],
    ['Head Lamp 70 watt charger', 'alat_kerja', 'Bh', 2],
    ['Tang Scoon Hydraulic 10 s/d 70 mm2', 'alat_kerja', 'Set', 1],
    ['Handle LBS Berisolasi', 'alat_kerja', 'Bh', 1],
    ['Isolasi Listrik (3 Warna)', 'alat_kerja', 'Set', 1],

    // ALAT K3 BEREGU (9 Item)
    ['Full Body Hardness + double lanyard', 'k3_safety', 'Bh', 1],
    ['Sarung Tangan Tahan Tegangan 1kV (karet)', 'k3_safety', 'Psg', 1],
    ['Sarung Tangan 30 kV kelas 3', 'k3_safety', 'Psg', 1],
    ['Sepatu karet 30 kV kelas 3', 'k3_safety', 'Psg', 1],
    ['Voltage Detector (Low & High Voltage) Non Contact', 'k3_safety', 'Bh', 1],
    ['Tempat penyimpanan sarung tangan 20 kv', 'k3_safety', 'Bh', 1],
    ['LoTo (Lock Out dan Tag Out), Gembok & Rantai', 'k3_safety', 'Bh', 4],
    ['Kotak P3K lengkap isi', 'k3_safety', 'Bh', 1],
    ['Helm Motor SNI', 'k3_safety', 'Bh', 2],

    // LAIN LAIN (1 Item)
    ['Kondisi Ban', 'kendaraan_pendukung', 'Bh', 2],
];

// Helper insert barang jika belum ada
$stmt_barang = $pdo->prepare("INSERT INTO master_barang (nama_barang, kategori, satuan_default) 
    VALUES (:nama, :kategori, :satuan)
    ON DUPLICATE KEY UPDATE kategori = VALUES(kategori), satuan_default = VALUES(satuan_default)");

$stmt_find_barang = $pdo->prepare("SELECT id FROM master_barang WHERE nama_barang = :nama LIMIT 1");
$stmt_tpl = $pdo->prepare("INSERT INTO template_checklist_item (jenis_pekerjaan, regu_tipe, master_barang_id, jumlah_standar, urutan)
    VALUES (:jenis, :regu_tipe, :barang_id, :jml, :urutan)");

// Hapus template ULC lama jika ada
$pdo->exec("DELETE FROM template_checklist_item WHERE jenis_pekerjaan = 'yandal' AND regu_tipe = 'ulc_motor'");

$urutan = 1;
foreach ($ulc_items as $item) {
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

    // 2. Hubungkan ke template_checklist_item
    $stmt_tpl->execute([
        ':jenis' => 'yandal',
        ':regu_tipe' => 'ulc_motor',
        ':barang_id' => $b['id'],
        ':jml' => $jml,
        ':urutan' => $urutan++
    ]);
}

echo "Migrasi template ULC Sepeda Motor Honda Verza selesai.\n";
echo "Total " . ($urutan - 1) . " item checklist ULC berhasil disimpan.\n";
