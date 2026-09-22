<?php
/**
 * Script Inisialisasi Database & Seeder Data SIGAP
 * Bisa dijalankan via Browser (http://localhost/gelar_alat/setup.php) atau CLI (php setup.php)
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_gelar_alat');

$is_cli = (php_sapi_name() === 'cli');

function output_msg($msg, $type = 'info') {
    global $is_cli;
    if ($is_cli) {
        echo "[" . strtoupper($type) . "] " . strip_tags($msg) . PHP_EOL;
    } else {
        $color = ($type === 'success') ? '#10b981' : (($type === 'error') ? '#ef4444' : '#0284c7');
        echo "<div style='font-family:sans-serif;margin:8px 0;padding:10px;border-radius:6px;background:rgba(0,0,0,0.04);border-left:4px solid $color;'>$msg</div>";
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Inisialisasi Database SIGAP</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f1f5f9; padding: 30px; color: #1e293b; }
        .card { max-width: 800px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h1 { color: #0a2540; margin-top: 0; font-size: 24px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
        .btn { display: inline-block; background: #0072ce; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; margin-top: 15px; }
        .btn:hover { background: #00569c; }
    </style>
</head>
<body>
<div class="card">
    <h1>Inisialisasi Sistem Digital Gelar Alat & K3</h1>
<?php

try {
    // 1. Hubungkan ke Server MySQL tanpa nama database
    $pdo_server = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    output_msg("Berhasil terhubung ke MySQL Server pada " . DB_HOST . ":" . DB_PORT, 'success');

    // 2. Buat Database jika belum ada
    $pdo_server->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    output_msg("Database <strong>" . DB_NAME . "</strong> siap digunakan.", 'success');

    // 3. Hubungkan ke database target
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 4. Buat Tabel-tabel
    $schema_sql = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($schema_sql);
    output_msg("Skema tabel berhasil diverifikasi dan dibuat.", 'success');

    // 5. Seed Users (2 Role: Petugas & Manajemen Atasan)
    $password_default = password_hash('123456', PASSWORD_BCRYPT);
    $users = [
        // Role: Petugas Pemeriksa
        ['username' => 'petugas_yandal', 'nama_lengkap' => 'Ropiko (Pelaksana Yandal)', 'role' => 'petugas', 'jabatan' => 'Petugas Pelaksana Yandal ULP Balong', 'no_hp' => '081234567891'],
        ['username' => 'petugas_p2tl', 'nama_lengkap' => 'Sayitno (Pelaksana P2TL)', 'role' => 'petugas', 'jabatan' => 'Pelaksana Lapangan PT Citacontrac', 'no_hp' => '081234567892'],
        ['username' => 'petugas_har', 'nama_lengkap' => 'Ahmad Fauzi (Pelaksana HAR)', 'role' => 'petugas', 'jabatan' => 'Pelaksana Pemeliharaan Distribusi', 'no_hp' => '081234567893'],
        ['username' => 'petugas_sr', 'nama_lengkap' => 'Budi Santoso (Pelaksana SR APP)', 'role' => 'petugas', 'jabatan' => 'Pelaksana Penyambungan SR APP 1 Phasa', 'no_hp' => '081234567894'],
        
        // Role: Manajemen Atasan
        ['username' => 'manager_balong', 'nama_lengkap' => 'Yusuf Irfan', 'role' => 'manajemen', 'jabatan' => 'Manager ULP Balong', 'no_hp' => '081234567800'],
        ['username' => 'spv_yantek', 'nama_lengkap' => 'A. Kholid', 'role' => 'manajemen', 'jabatan' => 'SPV Yantek ULP Balong', 'no_hp' => '081234567801'],
        ['username' => 'tl_k3', 'nama_lengkap' => 'Bindraerda Hanindiawan', 'role' => 'manajemen', 'jabatan' => 'TL K3L dan KAM ULP Balong', 'no_hp' => '081234567802'],
        ['username' => 'tl_teknik', 'nama_lengkap' => 'Sofyan', 'role' => 'manajemen', 'jabatan' => 'TL Teknik ULP Balong', 'no_hp' => '081234567803'],
    ];

    $stmt_user = $pdo->prepare("INSERT INTO users (username, password_hash, nama_lengkap, role, jabatan, no_hp) 
        VALUES (:username, :password_hash, :nama_lengkap, :role, :jabatan, :no_hp)
        ON DUPLICATE KEY UPDATE nama_lengkap = VALUES(nama_lengkap), role = VALUES(role), jabatan = VALUES(jabatan)");
    
    foreach ($users as $u) {
        $stmt_user->execute([
            ':username' => $u['username'],
            ':password_hash' => $password_default,
            ':nama_lengkap' => $u['nama_lengkap'],
            ':role' => $u['role'],
            ':jabatan' => $u['jabatan'],
            ':no_hp' => $u['no_hp']
        ]);
    }
    output_msg("Seeder Data Users berhasil dibuat (" . count($users) . " akun terdaftar: Petugas & Manajemen Atasan). Password default: <code>123456</code>", 'success');

    // 6. Seed Regu & Armada
    $regus = [
        ['nama_regu' => 'Regu 2 P2TL Citacontrac', 'jenis_pekerjaan' => 'p2tl', 'kendaraan' => 'Mobil Operasional Citacontrac', 'nopol' => 'AE 1234 CC', 'keterangan' => 'Penertiban Pemakaian Tenaga Listrik'],
        ['nama_regu' => 'Regu Roda 3 SR APP Balong', 'jenis_pekerjaan' => 'sr_app', 'kendaraan' => 'Kendaraan Roda Tiga Listrik', 'nopol' => 'AE 8123 R3', 'keterangan' => 'Penyambungan & Bongkar SR APP 1 Phasa Roda 3'],
        ['nama_regu' => 'Regu Roda 2 SR APP Balong', 'jenis_pekerjaan' => 'sr_app', 'kendaraan' => 'Kendaraan Roda Dua', 'nopol' => 'AE 4567 R2', 'keterangan' => 'Penyambungan & Bongkar SR APP 1 Phasa Roda 2'],
        ['nama_regu' => 'Mobil Yandal Hilux Rangga', 'jenis_pekerjaan' => 'yandal', 'kendaraan' => 'Hilux Rangga', 'nopol' => 'AE 8297 BH', 'keterangan' => 'Pelayanan Gangguan Distribusi 24 Jam'],
        ['nama_regu' => 'Regu ULC Sepeda Motor', 'jenis_pekerjaan' => 'yandal', 'kendaraan' => 'Honda Verza', 'nopol' => 'AE 2225 CG', 'keterangan' => 'Unit Layanan Cepat Gangguan'],
        ['nama_regu' => 'Regu HAR A Truk Hino', 'jenis_pekerjaan' => 'har', 'kendaraan' => 'Truk Hino', 'nopol' => 'AE 8280 BH', 'keterangan' => 'Pemeliharaan Preventif & Korektif Jaringan'],
        ['nama_regu' => 'Regu HAR B Mobil Carry', 'jenis_pekerjaan' => 'har', 'kendaraan' => 'Suzuki Carry', 'nopol' => 'E 8255 BH', 'keterangan' => 'Pemeliharaan Gardu & JTR'],
    ];

    $stmt_regu = $pdo->prepare("INSERT INTO regu (id, nama_regu, jenis_pekerjaan, kendaraan, nopol, keterangan)
        VALUES (:id, :nama_regu, :jenis_pekerjaan, :kendaraan, :nopol, :keterangan)
        ON DUPLICATE KEY UPDATE nama_regu = VALUES(nama_regu), kendaraan = VALUES(kendaraan), nopol = VALUES(nopol)");
    
    $id_r = 1;
    foreach ($regus as $r) {
        $stmt_regu->execute([
            ':id' => $id_r++,
            ':nama_regu' => $r['nama_regu'],
            ':jenis_pekerjaan' => $r['jenis_pekerjaan'],
            ':kendaraan' => $r['kendaraan'],
            ':nopol' => $r['nopol'],
            ':keterangan' => $r['keterangan']
        ]);
    }
    output_msg("Seeder Data Regu & Armada Kendaraan berhasil diinput (" . count($regus) . " regu operasional).", 'success');

    // 7. Seed Master Barang & Template Items
    // A. P2TL Items
    $p2tl_items = [
        ['Koordinator Lapangan', 'administrasi', 'Orang', 1],
        ['Pelaksana Lapangan', 'administrasi', 'Orang', 2],
        ['Pendamping Administrasi', 'administrasi', 'Orang', 1],
        ['Helm Pengaman', 'k3_safety', 'Bh', 2],
        ['Sarung Tangan Isolasi TR', 'k3_safety', 'Psg', 2],
        ['Sepatu Safety', 'k3_safety', 'Psg', 2],
        ['Sabuk Pengaman', 'k3_safety', 'Bh', 1],
        ['Kacamata Pelindung Mata', 'k3_safety', 'Bh', 2],
        ['Kotak P3K', 'k3_safety', 'Kotak', 1],
        ['Jas Hujan', 'k3_safety', 'Set', 2],
        ['APAR Mobil', 'k3_safety', 'Tabung', 1],
        ['Tang Ampere 3 Phasa', 'alat_kerja', 'Unit', 1],
        ['Tas Peralatan', 'alat_kerja', 'Bh', 1],
        ['Tang Kombinasi', 'alat_kerja', 'Bh', 1],
        ['Tang Potong', 'alat_kerja', 'Bh', 1],
        ['Obeng Plus (+)', 'alat_kerja', 'Bh', 1],
        ['Obeng Minus (-)', 'alat_kerja', 'Bh', 1],
        ['Cutter', 'alat_kerja', 'Bh', 1],
        ['Kunci Ringpas', 'alat_kerja', 'Set', 1],
        ['Test Pen', 'alat_kerja', 'Bh', 1],
        ['Lampu Senter', 'alat_kerja', 'Bh', 1],
        ['Tangga Lipat Telescopic 4M', 'alat_kerja', 'Unit', 1],
        ['Spy Cam Model Pulpen Clip', 'alat_kerja', 'Unit', 1],
        ['Laptop 14 Inch 8/512GB', 'alat_kerja', 'Unit', 1],
        ['Meja Dada', 'administrasi', 'Bh', 1],
        ['ATK (Lakban, Plastik BB P2TL)', 'administrasi', 'Set', 1],
        ['Stapler, Spidol Permanen', 'administrasi', 'Set', 1],
        ['Tas Berkas', 'administrasi', 'Bh', 1],
        ['Alat Kebersihan Mobil', 'kendaraan_pendukung', 'Set', 1],
        ['Pulsa Komunikasi', 'administrasi', 'Paket', 1],
    ];

    // B. SR APP Items
    $srapp_items = [
        // Peralatan Kerja
        ['Power Meter Clamp On', 'alat_kerja', 'Bh/Regu', 1],
        ['Hidrolik Dies', 'alat_kerja', 'Set/Regu', 1],
        ['Kunci Inggris 8 Inch', 'alat_kerja', 'Bh/Regu', 1],
        ['Tang Kombinasi 8 Inch', 'alat_kerja', 'Bh/Regu', 1],
        ['Tang Potong 6 Inch', 'alat_kerja', 'Bh/Regu', 1],
        ['Tang Lancip 6 Inch', 'alat_kerja', 'Bh/Regu', 1],
        ['Obeng Plus', 'alat_kerja', 'Bh/Regu', 1],
        ['Obeng Minus', 'alat_kerja', 'Bh/Regu', 1],
        ['Palu 1/2 kg', 'alat_kerja', 'Bh/Regu', 1],
        ['Betel', 'alat_kerja', 'Bh/Regu', 1],
        ['Tali Tambang', 'alat_kerja', 'Bh/Regu', 1],
        ['Test Pen SR', 'alat_kerja', 'Bh/Regu', 1],
        ['Toolbox', 'alat_kerja', 'Bh/Regu', 1],
        ['Lampu Senter Cas', 'alat_kerja', 'Bh/Regu', 1],
        // Peralatan K3
        ['Rompi Spotlight', 'k3_safety', 'Bh/Orang', 1],
        ['Helmet / Helm Kerja', 'k3_safety', 'Bh/Orang', 1],
        ['Full Body Harness', 'k3_safety', 'Bh/Orang', 1],
        ['Sarung Tangan Karet/Kain', 'k3_safety', 'Psg/Orang', 1],
        ['Sepatu Karet / Safety', 'k3_safety', 'Psg/Orang', 1],
        ['Jas Hujan Standard', 'k3_safety', 'Unit/Orang', 1],
        ['Masker Medis / Kain', 'k3_safety', 'Box', 1],
        ['Hand Sanitizer', 'k3_safety', 'Botol', 1],
        ['Kotak P3K Lengkap', 'k3_safety', 'Unit/Regu', 1],
        // Peralatan Pendukung
        ['Kendaraan Operasional SR', 'kendaraan_pendukung', 'Unit/Regu', 1],
        ['Alat Tulis & Buku Rekap', 'administrasi', 'Set/Regu', 1],
        ['Smartphone Petugas', 'administrasi', 'Unit/Regu', 1],
        ['Tangga Fiber Sliding 6 Meter', 'alat_kerja', 'Unit/Regu', 1],
    ];

    // C. YANDAL Items
    $yandal_items = [
        ['Tangga Fiber (2 Section Ladder) 9.6 Mtr', 'alat_kerja', 'Bh', 1],
        ['Telescopic Hotstick 20 kV; 10.5 Mtr', 'alat_kerja', 'Bh', 1],
        ['Groundcluster Lengkap', 'alat_kerja', 'Set', 2],
        ['Insulation Tester 10.000 Volt Analog', 'alat_kerja', 'Bh', 1],
        ['Comealong (Wire Grip) 70 - 240 mm2', 'alat_kerja', 'Bh', 1],
        ['Strainging Device 2 Ton', 'alat_kerja', 'Bh', 2],
        ['Tang Scoon Hydraulic 10 s/d 70 mm2', 'alat_kerja', 'Bh', 1],
        ['Compression Dies 50 mm - 300 mm', 'alat_kerja', 'Set', 1],
        ['Tang Ampere (Clip on AVO Meter digital) 600 A', 'alat_kerja', 'Bh', 1],
        ['Phase Sequence Indicator', 'alat_kerja', 'Set', 1],
        ['Wire Cutter s.d 240 mm2', 'alat_kerja', 'Bh', 1],
        ['Tool Set Lengkap (Besar) Mobil', 'alat_kerja', 'Bh', 1],
        ['Head Lamp 70 Watt Charger', 'alat_kerja', 'Bh', 2],
        ['Lampu Senter Battery (Re-charger)', 'alat_kerja', 'Bh', 1],
        ['Lampu Sorot Halogen 50 Watt', 'alat_kerja', 'Bh', 1],
        ['Power Inverter 100 W', 'alat_kerja', 'Bh', 1],
        ['Fuse Puller 20 kV', 'alat_kerja', 'Bh', 1],
        ['Palu Kecil', 'alat_kerja', 'Bh', 1],
        ['Parang / Golok Babat', 'alat_kerja', 'Bh', 2],
        ['Angkus', 'alat_kerja', 'Bh', 1],
        ['Handle LBS Berisolasi', 'alat_kerja', 'Bh', 1],
        ['Isolasi Listrik (3 Warna)', 'alat_kerja', 'Lot', 1],
        ['Smartphone RAM 8 GB / 128 GB', 'administrasi', 'Bh', 1],
        ['Handheld Holder Smartphone', 'administrasi', 'Bh', 1],
        ['Powerbank 20.000 mAh', 'administrasi', 'Unit', 1],
        ['GPS Tracker Mobil', 'kendaraan_pendukung', 'Bh', 1],
        ['Roll Besi (Kerekan Kecil)', 'alat_kerja', 'Unit', 1],
        ['Kunci L Hexa', 'alat_kerja', 'Set', 1],
        ['Kunci L Bintang', 'alat_kerja', 'Set', 1],
        ['Kunci Pipa 4 Inch', 'alat_kerja', 'Bh', 1],
        ['Tali Nilon 12 mm 20 Meter', 'alat_kerja', 'Bh', 1],
        ['Tali Manila 12 mm 20 Meter', 'alat_kerja', 'Bh', 1],
        ['Tali Tampar 20 Mtr', 'alat_kerja', 'Bh', 1],
        ['Tali Pengikat Tangga', 'alat_kerja', 'Mtr', 1],
        ['Gergaji Besi', 'alat_kerja', 'Bh', 1],
        ['Platform (Pijakan di Atas Tiang)', 'alat_kerja', 'Unit', 1],
        ['AMR Tie Band Lengkap Ring', 'alat_kerja', 'Unit', 1],
        // K3 Beregu
        ['Full Body Harness + Double Lanyard', 'k3_safety', 'Bh', 2],
        ['Sarung Tangan Tahan Tegangan 1 kV (Karet)', 'k3_safety', 'Psg', 2],
        ['Sarung Tangan 30 kV Kelas 3', 'k3_safety', 'Psg', 2],
        ['Sepatu Karet 30 kV Kelas 3', 'k3_safety', 'Psg', 2],
        ['Sepatu Safety Boots', 'k3_safety', 'Psg', 1],
        ['Voltage Detector (Low & High Voltage) Non Contact', 'k3_safety', 'Bh', 1],
        ['Tanda Peringatan Kerja', 'k3_safety', 'Bh', 2],
        ['LoTo (Lock Out and Tag Out), Gembok & Rantai', 'k3_safety', 'Set', 4],
        ['Rantai Plastik 10 Mtr', 'k3_safety', 'Bh', 1],
        ['Traffic Cone', 'k3_safety', 'Set', 2],
        ['Tempat Penyimpanan Sarung Tangan 20 kV', 'k3_safety', 'Bh', 1],
        ['Kotak P3K Lengkap Isi Medis', 'k3_safety', 'Bh', 1],
        ['APAR Mobil 3 kg Powder', 'k3_safety', 'Unit', 1],
        // Armada & Lain-lain
        ['Dongkrak + Kunci Ban Mobil', 'kendaraan_pendukung', 'Set', 1],
        ['Kondisi Ban Utama (4 Roda)', 'kendaraan_pendukung', 'Bh', 4],
        ['Ban Cadangan (Serep)', 'kendaraan_pendukung', 'Psg', 1],
    ];

    // D. HAR Items (Gabungan Yandal + Alat Berat HAR)
    $har_specific_items = [
        ['Chain Saw Mesin 14 Inch', 'alat_kerja', 'Bh', 2],
        ['Insulation Tester 1.000 Volt Digital', 'alat_kerja', 'Bh', 1],
        ['Insulation Tester 5.000 Volt Digital', 'alat_kerja', 'Bh', 1],
        ['Earth Tester Clip On / Online', 'alat_kerja', 'Bh', 1],
        ['Palu Besar 5 kg', 'alat_kerja', 'Bh', 1],
        ['Linggis Besar Baja', 'alat_kerja', 'Bh', 1],
        ['Sosrok Tiang', 'alat_kerja', 'Bh', 1],
        ['Kunci Moment', 'alat_kerja', 'Set', 1],
        ['Tali Baja (Seling) 20 Meter', 'alat_kerja', 'Bh', 1],
        ['Majun Pembersih', 'alat_kerja', 'Lot', 1],
        ['Cairan Pembersih & Pelumas WD 40', 'alat_kerja', 'Bh', 1],
    ];

    $stmt_barang = $pdo->prepare("INSERT INTO master_barang (nama_barang, kategori, satuan_default) 
        VALUES (:nama_barang, :kategori, :satuan_default)
        ON DUPLICATE KEY UPDATE kategori = VALUES(kategori), satuan_default = VALUES(satuan_default)");

    $stmt_find_barang = $pdo->prepare("SELECT id FROM master_barang WHERE nama_barang = :nama LIMIT 1");
    $stmt_tpl = $pdo->prepare("INSERT INTO template_checklist_item (jenis_pekerjaan, regu_tipe, master_barang_id, jumlah_standar, urutan)
        VALUES (:jenis, :regu_tipe, :barang_id, :jml, :urutan)");

    // Kosongkan template checklist sebelum re-seed
    $pdo->exec("DELETE FROM template_checklist_item");

    // Fungsi helper insert & link template
    $insert_template_group = function($items, $jenis_pekerjaan, $regu_tipe = 'all') use ($stmt_barang, $stmt_find_barang, $stmt_tpl) {
        $urutan = 1;
        foreach ($items as $item) {
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
                ':jenis' => $jenis_pekerjaan,
                ':regu_tipe' => $regu_tipe,
                ':barang_id' => $b['id'],
                ':jml' => $jml,
                ':urutan' => $urutan++
            ]);
        }
    };

    $insert_template_group($p2tl_items, 'p2tl');
    $insert_template_group($srapp_items, 'sr_app');
    $insert_template_group($yandal_items, 'yandal');
    
    // Gabungkan yandal + har_specific untuk template HAR
    $har_all_items = array_merge($yandal_items, $har_specific_items);
    $insert_template_group($har_all_items, 'har');

    output_msg("Master Katalog Peralatan & Template Checklist (P2TL, SR APP, YANDAL, HAR) berhasil dibuat lengkap sesuai dokumen fisik.", 'success');

    // 8. Buat folder uploads jika belum ada
    $upload_dirs = [
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/foto_kegiatan',
        __DIR__ . '/uploads/foto_temuan',
        __DIR__ . '/uploads/ttd'
    ];
    foreach ($upload_dirs as $d) {
        if (!is_dir($d)) {
            mkdir($d, 0777, true);
        }
    }
    output_msg("Direktori uploads media fisik & tanda tangan digital siap digunakan.", 'success');

    echo "<h3 style='color:#16a34a;margin-top:20px;'>Semua proses inisialisasi berhasil!</h3>";
    echo "<p>Anda sekarang dapat langsung membuka sistem SIGAP dan login:</p>";
    echo "<ul>";
    echo "<li><strong>Role Petugas Pemeriksa:</strong> username <code>petugas_yandal</code> / pass <code>123456</code></li>";
    echo "<li><strong>Role Manajemen Atasan:</strong> username <code>manager_balong</code> / pass <code>123456</code></li>";
    echo "</ul>";
    echo "<a href='index.php' class='btn'>Buka Aplikasi Sekarang &rarr;</a>";

} catch (Exception $e) {
    output_msg("Terjadi kesalahan koneksi atau query: " . $e->getMessage(), 'error');
    echo "<div style='background:#fee2e2;color:#b91c1c;padding:15px;border-radius:8px;margin-top:15px;'>";
    echo "<strong>Tips Perbaikan:</strong><br>";
    echo "1. Pastikan service MySQL di Laragon sudah berjalan (Buka Laragon -> Klik <strong>Start All</strong>).<br>";
    echo "2. Periksa kembali port (default: 3306) dan user/password pada <code>config/database.php</code>.";
    echo "</div>";
}
?>
</div>
</body>
</html>
