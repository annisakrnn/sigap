-- =============================================================================
-- SIGAP - Sistem Informasi Gelar Alat & Perlengkapan
-- Skema Database Lengkap (MySQL / MariaDB - InnoDB)
-- Versi: 2.0 | Disesuaikan dengan implementasi web terbaru
-- =============================================================================
-- Urutan pembuatan tabel mengikuti dependency foreign key:
--   1. users
--   2. regu
--   3. master_barang
--   4. template_checklist_item (FK -> master_barang)
--   5. gelar_alat_header       (FK -> regu, users)
--   6. gelar_alat_detail       (FK -> gelar_alat_header, master_barang)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS db_gelar_alat
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_gelar_alat;

-- =============================================================================
-- 1. TABEL USERS
--    Mendukung dua role: 'petugas' (pemeriksa lapangan) dan 'manajemen' (atasan)
-- =============================================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT           AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)   NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    nama_lengkap  VARCHAR(100)  NOT NULL,
    role          ENUM('petugas', 'manajemen') NOT NULL,
    jabatan       VARCHAR(100)  NOT NULL,
    no_hp         VARCHAR(20)   NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. TABEL REGU & ARMADA
--    Data regu operasional beserta kendaraan dinas yang digunakan
-- =============================================================================
CREATE TABLE IF NOT EXISTS regu (
    id               INT          AUTO_INCREMENT PRIMARY KEY,
    nama_regu        VARCHAR(100) NOT NULL,
    jenis_pekerjaan  ENUM('p2tl', 'sr_app', 'yandal', 'har') NOT NULL,
    kendaraan        VARCHAR(100) NULL,
    nopol            VARCHAR(20)  NULL,
    keterangan       VARCHAR(255) NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_regu_jenis (jenis_pekerjaan),
    INDEX idx_regu_nama  (nama_regu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. TABEL MASTER BARANG / KATALOG PERALATAN
-- =============================================================================
CREATE TABLE IF NOT EXISTS master_barang (
    id               INT          AUTO_INCREMENT PRIMARY KEY,
    nama_barang      VARCHAR(150) NOT NULL UNIQUE,
    kategori         ENUM('alat_kerja', 'k3_safety', 'kendaraan_pendukung', 'administrasi') NOT NULL,
    satuan_default   VARCHAR(30)  NOT NULL,
    gambar_referensi VARCHAR(255) NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_barang_kategori (kategori),
    INDEX idx_barang_nama     (nama_barang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. TABEL TEMPLATE CHECKLIST ITEM
-- =============================================================================
CREATE TABLE IF NOT EXISTS template_checklist_item (
    id               INT  AUTO_INCREMENT PRIMARY KEY,
    jenis_pekerjaan  ENUM('p2tl', 'sr_app', 'yandal', 'har') NOT NULL,
    regu_tipe        ENUM('all', 'roda_3', 'roda_2') NOT NULL DEFAULT 'all',
    master_barang_id INT  NOT NULL,
    jumlah_standar   INT  NOT NULL DEFAULT 1,
    urutan           INT  NOT NULL DEFAULT 0,
    INDEX idx_tpl_jenis  (jenis_pekerjaan),
    INDEX idx_tpl_barang (master_barang_id),
    FOREIGN KEY (master_barang_id)
        REFERENCES master_barang(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. TABEL HEADER TRANSAKSI GELAR ALAT (Berita Acara)
--    Status alur: draft -> submitted -> approved / rejected
-- =============================================================================
CREATE TABLE IF NOT EXISTS gelar_alat_header (
    id                     INT           AUTO_INCREMENT PRIMARY KEY,
    nomor_dokumen          VARCHAR(100)  NULL,
    tanggal_inspeksi       DATE          NOT NULL,
    bulan_tahun            VARCHAR(50)   NOT NULL,
    jenis_pekerjaan        ENUM('p2tl', 'sr_app', 'yandal', 'har') NOT NULL,
    regu_id                INT           NOT NULL,
    nama_pelaksana_1       VARCHAR(100)  NOT NULL,
    nama_pelaksana_2       VARCHAR(100)  NULL,
    pendamping_admin       VARCHAR(100)  NULL,
    catatan_umum           TEXT          NULL,
    foto_kegiatan          VARCHAR(255)  NULL,
    status                 ENUM('draft', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    petugas_id             INT           NOT NULL,
    ttd_petugas            LONGTEXT      NULL,
    manajemen_id           INT           NULL,
    nama_pejabat_manajemen VARCHAR(100)  NULL,
    jabatan_manajemen      VARCHAR(100)  NULL,
    ttd_manajemen          LONGTEXT      NULL,
    catatan_manajemen      TEXT          NULL,
    tanggal_approval       DATETIME      NULL,
    created_at             TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_header_status  (status),
    INDEX idx_header_jenis   (jenis_pekerjaan),
    INDEX idx_header_tanggal (tanggal_inspeksi),
    INDEX idx_header_petugas (petugas_id),
    INDEX idx_header_regu    (regu_id),
    FOREIGN KEY (regu_id)      REFERENCES regu(id)  ON UPDATE CASCADE,
    FOREIGN KEY (petugas_id)   REFERENCES users(id) ON UPDATE CASCADE,
    FOREIGN KEY (manajemen_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. TABEL DETAIL HASIL PEMERIKSAAN
--    Snapshot data barang agar tidak terpengaruh perubahan master
-- =============================================================================
CREATE TABLE IF NOT EXISTS gelar_alat_detail (
    id                    INT           AUTO_INCREMENT PRIMARY KEY,
    gelar_alat_id         INT           NOT NULL,
    master_barang_id      INT           NOT NULL,
    nama_barang_snapshot  VARCHAR(150)  NOT NULL,
    kategori_snapshot     VARCHAR(50)   NOT NULL,
    merk_type             VARCHAR(100)  NULL,
    jumlah_standar        INT           NOT NULL DEFAULT 1,
    jumlah_realisasi      INT           NOT NULL DEFAULT 0,
    satuan                VARCHAR(30)   NOT NULL,
    kondisi               ENUM('baik', 'rusak', 'waktu_ganti', 'hilang', 'ada', 'tidak_ada') NOT NULL DEFAULT 'baik',
    keterangan            VARCHAR(255)  NULL,
    foto_temuan           VARCHAR(255)  NULL,
    INDEX idx_detail_header  (gelar_alat_id),
    INDEX idx_detail_barang  (master_barang_id),
    INDEX idx_detail_kondisi (kondisi),
    FOREIGN KEY (gelar_alat_id)    REFERENCES gelar_alat_header(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (master_barang_id) REFERENCES master_barang(id)     ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SEED DATA  (idempoten - aman dijalankan berulang)
-- Password default semua akun: 123456  (bcrypt hash Laravel-compatible)
-- =============================================================================

-- SEED: Users
INSERT INTO users (username, password_hash, nama_lengkap, role, jabatan, no_hp) VALUES
('petugas_yandal', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ropiko (Pelaksana Yandal)',       'petugas',   'Petugas Pelaksana Yandal ULP Balong',       '081234567891'),
('petugas_p2tl',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sayitno (Pelaksana P2TL)',        'petugas',   'Pelaksana Lapangan PT Citacontrac',         '081234567892'),
('petugas_har',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ahmad Fauzi (Pelaksana HAR)',     'petugas',   'Pelaksana Pemeliharaan Distribusi',         '081234567893'),
('petugas_sr',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso (Pelaksana SR APP)', 'petugas',   'Pelaksana Penyambungan SR APP 1 Phasa',    '081234567894'),
('manager_balong', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Yusuf Irfan',                    'manajemen', 'Manager ULP Balong',                       '081234567800'),
('spv_yantek',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'A. Kholid',                      'manajemen', 'SPV Yantek ULP Balong',                    '081234567801'),
('tl_k3',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bindraerda Hanindiawan',         'manajemen', 'TL K3L dan KAM ULP Balong',                '081234567802'),
('tl_teknik',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sofyan',                         'manajemen', 'TL Teknik ULP Balong',                     '081234567803')
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    role         = VALUES(role),
    jabatan      = VALUES(jabatan);

-- SEED: Regu & Armada
INSERT INTO regu (id, nama_regu, jenis_pekerjaan, kendaraan, nopol, keterangan) VALUES
(1, 'Regu 2 P2TL Citacontrac',   'p2tl',   'Mobil Operasional Citacontrac', 'AE 1234 CC', 'Penertiban Pemakaian Tenaga Listrik'),
(2, 'Regu Roda 3 SR APP Balong', 'sr_app', 'Kendaraan Roda Tiga Listrik',   'AE 8123 R3', 'Penyambungan & Bongkar SR APP 1 Phasa Roda 3'),
(3, 'Regu Roda 2 SR APP Balong', 'sr_app', 'Kendaraan Roda Dua',            'AE 4567 R2', 'Penyambungan & Bongkar SR APP 1 Phasa Roda 2'),
(4, 'Mobil Yandal Hilux Rangga', 'yandal', 'Hilux Rangga',                  'AE 8297 BH', 'Pelayanan Gangguan Distribusi 24 Jam'),
(5, 'Regu ULC Sepeda Motor',     'yandal', 'Honda Verza',                   'AE 2225 CG', 'Unit Layanan Cepat Gangguan'),
(6, 'Regu HAR A Truk Hino',      'har',    'Truk Hino',                     'AE 8280 BH', 'Pemeliharaan Preventif & Korektif Jaringan'),
(7, 'Regu HAR B Mobil Carry',    'har',    'Suzuki Carry',                  'E 8255 BH',  'Pemeliharaan Gardu & JTR')
ON DUPLICATE KEY UPDATE
    nama_regu  = VALUES(nama_regu),
    kendaraan  = VALUES(kendaraan),
    nopol      = VALUES(nopol),
    keterangan = VALUES(keterangan);

-- SEED: Master Barang / Katalog Peralatan (INSERT IGNORE = idempoten)

-- == P2TL ==
INSERT IGNORE INTO master_barang (nama_barang, kategori, satuan_default) VALUES
('Koordinator Lapangan',          'administrasi',        'Orang'),
('Pelaksana Lapangan',            'administrasi',        'Orang'),
('Pendamping Administrasi',       'administrasi',        'Orang'),
('Helm Pengaman',                 'k3_safety',           'Bh'),
('Sarung Tangan Isolasi TR',      'k3_safety',           'Psg'),
('Sepatu Safety',                 'k3_safety',           'Psg'),
('Sabuk Pengaman',                'k3_safety',           'Bh'),
('Kacamata Pelindung Mata',       'k3_safety',           'Bh'),
('Kotak P3K',                     'k3_safety',           'Kotak'),
('Jas Hujan',                     'k3_safety',           'Set'),
('APAR Mobil',                    'k3_safety',           'Tabung'),
('Tang Ampere 3 Phasa',           'alat_kerja',          'Unit'),
('Tas Peralatan',                 'alat_kerja',          'Bh'),
('Tang Kombinasi',                'alat_kerja',          'Bh'),
('Tang Potong',                   'alat_kerja',          'Bh'),
('Obeng Plus (+)',                 'alat_kerja',          'Bh'),
('Obeng Minus (-)',                'alat_kerja',          'Bh'),
('Cutter',                        'alat_kerja',          'Bh'),
('Kunci Ringpas',                 'alat_kerja',          'Set'),
('Test Pen',                      'alat_kerja',          'Bh'),
('Lampu Senter',                  'alat_kerja',          'Bh'),
('Tangga Lipat Telescopic 4M',    'alat_kerja',          'Unit'),
('Spy Cam Model Pulpen Clip',     'alat_kerja',          'Unit'),
('Laptop 14 Inch 8/512GB',        'alat_kerja',          'Unit'),
('Meja Dada',                     'administrasi',        'Bh'),
('ATK (Lakban, Plastik BB P2TL)', 'administrasi',        'Set'),
('Stapler, Spidol Permanen',      'administrasi',        'Set'),
('Tas Berkas',                    'administrasi',        'Bh'),
('Alat Kebersihan Mobil',         'kendaraan_pendukung', 'Set'),
('Pulsa Komunikasi',              'administrasi',        'Paket');

-- == SR APP ==
INSERT IGNORE INTO master_barang (nama_barang, kategori, satuan_default) VALUES
('Power Meter Clamp On',          'alat_kerja',          'Bh/Regu'),
('Hidrolik Dies',                 'alat_kerja',          'Set/Regu'),
('Kunci Inggris 8 Inch',          'alat_kerja',          'Bh/Regu'),
('Tang Kombinasi 8 Inch',         'alat_kerja',          'Bh/Regu'),
('Tang Potong 6 Inch',            'alat_kerja',          'Bh/Regu'),
('Tang Lancip 6 Inch',            'alat_kerja',          'Bh/Regu'),
('Obeng Plus',                    'alat_kerja',          'Bh/Regu'),
('Obeng Minus',                   'alat_kerja',          'Bh/Regu'),
('Palu 1/2 kg',                   'alat_kerja',          'Bh/Regu'),
('Betel',                         'alat_kerja',          'Bh/Regu'),
('Tali Tambang',                  'alat_kerja',          'Bh/Regu'),
('Test Pen SR',                   'alat_kerja',          'Bh/Regu'),
('Toolbox',                       'alat_kerja',          'Bh/Regu'),
('Lampu Senter Cas',              'alat_kerja',          'Bh/Regu'),
('Rompi Spotlight',               'k3_safety',           'Bh/Orang'),
('Helmet / Helm Kerja',           'k3_safety',           'Bh/Orang'),
('Full Body Harness',             'k3_safety',           'Bh/Orang'),
('Sarung Tangan Karet/Kain',      'k3_safety',           'Psg/Orang'),
('Sepatu Karet / Safety',         'k3_safety',           'Psg/Orang'),
('Jas Hujan Standard',            'k3_safety',           'Unit/Orang'),
('Masker Medis / Kain',           'k3_safety',           'Box'),
('Hand Sanitizer',                'k3_safety',           'Botol'),
('Kotak P3K Lengkap',            'k3_safety',           'Unit/Regu'),
('Kendaraan Operasional SR',      'kendaraan_pendukung', 'Unit/Regu'),
('Alat Tulis & Buku Rekap',       'administrasi',        'Set/Regu'),
('Smartphone Petugas',            'administrasi',        'Unit/Regu'),
('Tangga Fiber Sliding 6 Meter',  'alat_kerja',          'Unit/Regu');

-- == YANDAL ==
INSERT IGNORE INTO master_barang (nama_barang, kategori, satuan_default) VALUES
('Tangga Fiber (2 Section Ladder) 9.6 Mtr',     'alat_kerja',          'Bh'),
('Telescopic Hotstick 20 kV; 10.5 Mtr',          'alat_kerja',          'Bh'),
('Groundcluster Lengkap',                         'alat_kerja',          'Set'),
('Insulation Tester 10.000 Volt Analog',          'alat_kerja',          'Bh'),
('Comealong (Wire Grip) 70 - 240 mm2',            'alat_kerja',          'Bh'),
('Strainging Device 2 Ton',                       'alat_kerja',          'Bh'),
('Tang Scoon Hydraulic 10 s/d 70 mm2',            'alat_kerja',          'Bh'),
('Compression Dies 50 mm - 300 mm',               'alat_kerja',          'Set'),
('Tang Ampere (Clip on AVO Meter digital) 600 A', 'alat_kerja',          'Bh'),
('Phase Sequence Indicator',                      'alat_kerja',          'Set'),
('Wire Cutter s.d 240 mm2',                       'alat_kerja',          'Bh'),
('Tool Set Lengkap (Besar) Mobil',                'alat_kerja',          'Bh'),
('Head Lamp 70 Watt Charger',                     'alat_kerja',          'Bh'),
('Lampu Senter Battery (Re-charger)',              'alat_kerja',          'Bh'),
('Lampu Sorot Halogen 50 Watt',                   'alat_kerja',          'Bh'),
('Power Inverter 100 W',                          'alat_kerja',          'Bh'),
('Fuse Puller 20 kV',                             'alat_kerja',          'Bh'),
('Palu Kecil',                                    'alat_kerja',          'Bh'),
('Parang / Golok Babat',                          'alat_kerja',          'Bh'),
('Angkus',                                        'alat_kerja',          'Bh'),
('Handle LBS Berisolasi',                         'alat_kerja',          'Bh'),
('Isolasi Listrik (3 Warna)',                      'alat_kerja',          'Lot'),
('Smartphone RAM 8 GB / 128 GB',                  'administrasi',        'Bh'),
('Handheld Holder Smartphone',                    'administrasi',        'Bh'),
('Powerbank 20.000 mAh',                          'administrasi',        'Unit'),
('GPS Tracker Mobil',                             'kendaraan_pendukung', 'Bh'),
('Roll Besi (Kerekan Kecil)',                     'alat_kerja',          'Unit'),
('Kunci L Hexa',                                  'alat_kerja',          'Set'),
('Kunci L Bintang',                               'alat_kerja',          'Set'),
('Kunci Pipa 4 Inch',                             'alat_kerja',          'Bh'),
('Tali Nilon 12 mm 20 Meter',                     'alat_kerja',          'Bh'),
('Tali Manila 12 mm 20 Meter',                    'alat_kerja',          'Bh'),
('Tali Tampar 20 Mtr',                            'alat_kerja',          'Bh'),
('Tali Pengikat Tangga',                          'alat_kerja',          'Mtr'),
('Gergaji Besi',                                  'alat_kerja',          'Bh'),
('Platform (Pijakan di Atas Tiang)',              'alat_kerja',          'Unit'),
('AMR Tie Band Lengkap Ring',                     'alat_kerja',          'Unit'),
('Full Body Harness + Double Lanyard',            'k3_safety',           'Bh'),
('Sarung Tangan Tahan Tegangan 1 kV (Karet)',    'k3_safety',           'Psg'),
('Sarung Tangan 30 kV Kelas 3',                  'k3_safety',           'Psg'),
('Sepatu Karet 30 kV Kelas 3',                   'k3_safety',           'Psg'),
('Sepatu Safety Boots',                           'k3_safety',           'Psg'),
('Voltage Detector (Low & High Voltage) Non Contact', 'k3_safety',      'Bh'),
('Tanda Peringatan Kerja',                        'k3_safety',           'Bh'),
('LoTo (Lock Out and Tag Out), Gembok & Rantai',  'k3_safety',           'Set'),
('Rantai Plastik 10 Mtr',                         'k3_safety',           'Bh'),
('Traffic Cone',                                  'k3_safety',           'Set'),
('Tempat Penyimpanan Sarung Tangan 20 kV',       'k3_safety',           'Bh'),
('Kotak P3K Lengkap Isi Medis',                  'k3_safety',           'Bh'),
('APAR Mobil 3 kg Powder',                        'k3_safety',           'Unit'),
('Dongkrak + Kunci Ban Mobil',                    'kendaraan_pendukung', 'Set'),
('Kondisi Ban Utama (4 Roda)',                    'kendaraan_pendukung', 'Bh'),
('Ban Cadangan (Serep)',                           'kendaraan_pendukung', 'Psg');

-- == HAR Specific ==
INSERT IGNORE INTO master_barang (nama_barang, kategori, satuan_default) VALUES
('Chain Saw Mesin 14 Inch',               'alat_kerja', 'Bh'),
('Insulation Tester 1.000 Volt Digital',  'alat_kerja', 'Bh'),
('Insulation Tester 5.000 Volt Digital',  'alat_kerja', 'Bh'),
('Earth Tester Clip On / Online',         'alat_kerja', 'Bh'),
('Palu Besar 5 kg',                       'alat_kerja', 'Bh'),
('Linggis Besar Baja',                    'alat_kerja', 'Bh'),
('Sosrok Tiang',                          'alat_kerja', 'Bh'),
('Kunci Moment',                          'alat_kerja', 'Set'),
('Tali Baja (Seling) 20 Meter',          'alat_kerja', 'Bh'),
('Majun Pembersih',                       'alat_kerja', 'Lot'),
('Cairan Pembersih & Pelumas WD 40',      'alat_kerja', 'Bh');

-- =============================================================================
-- END OF SCHEMA
-- Catatan:
--   * template_checklist_item di-seed melalui setup.php (memerlukan lookup ID).
--   * Jalankan setup.php untuk inisialisasi penuh termasuk template checklist.
-- =============================================================================
