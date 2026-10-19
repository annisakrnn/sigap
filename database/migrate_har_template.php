<?php
/**
 * Migration Script: Update Template Checklist HAR menjadi mandiri (tidak menggabungkan Yandal & ULC)
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();
if (!$pdo) {
    echo "Koneksi database gagal.\n";
    exit(1);
}

// Daftar item checklist HAR mandiri (sesuai dokumen fisik Pemeliharaan Jaringan Distribusi)
$har_items = [
    // 1. Peralatan Kerja & Alat Berat HAR
    ['Chain Saw Mesin 14 Inch', 'alat_kerja', 'Bh', 2],
    ['Parang / Golok Babat', 'alat_kerja', 'Bh', 2],
    ['Angkus', 'alat_kerja', 'Bh', 1],
    ['Linggis Besar Baja', 'alat_kerja', 'Bh', 1],
    ['Sosrok Tiang', 'alat_kerja', 'Bh', 1],
    ['Palu Besar 5 kg', 'alat_kerja', 'Bh', 1],
    ['Palu Kecil', 'alat_kerja', 'Bh', 1],
    ['Kunci Moment', 'alat_kerja', 'Set', 1],
    ['Kunci Pipa 4 Inch', 'alat_kerja', 'Bh', 1],
    ['Kunci L Hexa', 'alat_kerja', 'Set', 1],
    ['Kunci L Bintang', 'alat_kerja', 'Set', 1],
    ['Kunci Ringpas', 'alat_kerja', 'Set', 1],
    ['Tang Kombinasi', 'alat_kerja', 'Bh', 2],
    ['Tang Potong', 'alat_kerja', 'Bh', 2],
    ['Obeng Plus (+)', 'alat_kerja', 'Bh', 1],
    ['Obeng Minus (-)', 'alat_kerja', 'Bh', 1],
    ['Gergaji Besi', 'alat_kerja', 'Bh', 1],
    ['Groundcluster Lengkap', 'alat_kerja', 'Set', 2],
    ['Telescopic Hotstick 20 kV; 10.5 Mtr', 'alat_kerja', 'Bh', 1],
    ['Tangga Fiber (2 Section Ladder) 9.6 Mtr', 'alat_kerja', 'Bh', 1],
    ['Platform (Pijakan di Atas Tiang)', 'alat_kerja', 'Unit', 1],
    ['Roll Besi (Kerekan Kecil)', 'alat_kerja', 'Unit', 2],
    ['Comealong (Wire Grip) 70 - 240 mm2', 'alat_kerja', 'Bh', 2],
    ['Strainging Device 2 Ton', 'alat_kerja', 'Bh', 2],
    ['Tang Scoon Hydraulic 10 s/d 70 mm2', 'alat_kerja', 'Bh', 1],
    ['Compression Dies 50 mm - 300 mm', 'alat_kerja', 'Set', 1],
    ['Tang Ampere (Clip on AVO Meter digital) 600 A', 'alat_kerja', 'Bh', 1],
    ['Phase Sequence Indicator', 'alat_kerja', 'Set', 1],
    ['Wire Cutter s.d 240 mm2', 'alat_kerja', 'Bh', 1],
    ['Insulation Tester 1.000 Volt Digital', 'alat_kerja', 'Bh', 1],
    ['Insulation Tester 5.000 Volt Digital', 'alat_kerja', 'Bh', 1],
    ['Earth Tester Clip On / Online', 'alat_kerja', 'Bh', 1],
    ['Tali Baja (Seling) 20 Meter', 'alat_kerja', 'Bh', 1],
    ['Tali Nilon 12 mm 20 Meter', 'alat_kerja', 'Bh', 1],
    ['Tali Manila 12 mm 20 Meter', 'alat_kerja', 'Bh', 1],
    ['Tali Tampar 20 Mtr', 'alat_kerja', 'Bh', 1],
    ['Tali Pengikat Tangga', 'alat_kerja', 'Mtr', 1],
    ['Majun Pembersih', 'alat_kerja', 'Lot', 1],
    ['Cairan Pembersih & Pelumas WD 40', 'alat_kerja', 'Bh', 1],
    ['Head Lamp 70 Watt Charger', 'alat_kerja', 'Bh', 2],
    ['Lampu Senter Battery (Re-charger)', 'alat_kerja', 'Bh', 1],
    ['Lampu Sorot Halogen 50 Watt', 'alat_kerja', 'Bh', 1],
    ['Fuse Puller 20 kV', 'alat_kerja', 'Bh', 1],
    ['Isolasi Listrik (3 Warna)', 'alat_kerja', 'Lot', 1],

    // 2. APD & K3 Pemeliharaan
    ['Helm Pengaman', 'k3_safety', 'Bh', 4],
    ['Full Body Harness + Double Lanyard', 'k3_safety', 'Bh', 4],
    ['Sarung Tangan Tahan Tegangan 1 kV (Karet)', 'k3_safety', 'Psg', 2],
    ['Sarung Tangan 30 kV Kelas 3', 'k3_safety', 'Psg', 2],
    ['Tempat Penyimpanan Sarung Tangan 20 kV', 'k3_safety', 'Bh', 1],
    ['Sepatu Safety Boots', 'k3_safety', 'Psg', 4],
    ['Sepatu Karet 30 kV Kelas 3', 'k3_safety', 'Psg', 2],
    ['Voltage Detector (Low & High Voltage) Non Contact', 'k3_safety', 'Bh', 1],
    ['Tanda Peringatan Kerja', 'k3_safety', 'Bh', 2],
    ['LoTo (Lock Out and Tag Out), Gembok & Rantai', 'k3_safety', 'Set', 4],
    ['Rantai Plastik 10 Mtr', 'k3_safety', 'Bh', 1],
    ['Traffic Cone', 'k3_safety', 'Set', 4],
    ['Kotak P3K Lengkap Isi Medis', 'k3_safety', 'Bh', 1],
    ['APAR Mobil 3 kg Powder', 'k3_safety', 'Unit', 1],

    // 3. Armada & Kendaraan Pendukung
    ['Dongkrak + Kunci Ban Mobil', 'kendaraan_pendukung', 'Set', 1],
    ['Kondisi Ban Utama (4 Roda)', 'kendaraan_pendukung', 'Bh', 4],
    ['Ban Cadangan (Serep)', 'kendaraan_pendukung', 'Psg', 1],

    // 4. Administrasi HAR
    ['Surat Perintah Kerja (SPK) / Working Permit HAR', 'administrasi', 'Set', 1],
    ['Papan Dada & Alat Tulis Regu', 'administrasi', 'Set', 1],
];

$stmt_barang = $pdo->prepare("INSERT INTO master_barang (nama_barang, kategori, satuan_default) 
    VALUES (:nama_barang, :kategori, :satuan_default)
    ON DUPLICATE KEY UPDATE kategori = VALUES(kategori), satuan_default = VALUES(satuan_default)");

$stmt_find_barang = $pdo->prepare("SELECT id FROM master_barang WHERE nama_barang = :nama LIMIT 1");
$stmt_tpl = $pdo->prepare("INSERT INTO template_checklist_item (jenis_pekerjaan, regu_tipe, master_barang_id, jumlah_standar, urutan)
    VALUES (:jenis, :regu_tipe, :barang_id, :jml, :urutan)");

// Hapus template HAR lama yang sebelumnya bercampur dengan Yandal
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
            ':nama_barang' => $nama,
            ':kategori' => $kategori,
            ':satuan_default' => $satuan
        ]);
        $stmt_find_barang->execute([':nama' => $nama]);
        $b = $stmt_find_barang->fetch();
    }

    // 2. Hubungkan ke template_checklist_item
    $stmt_tpl->execute([
        ':jenis' => 'har',
        ':regu_tipe' => 'all',
        ':barang_id' => $b['id'],
        ':jml' => $jml,
        ':urutan' => $urutan++
    ]);
}

echo "Migrasi template HAR selesai. Total " . ($urutan - 1) . " item checklist HAR mandiri berhasil disimpan.\n";
