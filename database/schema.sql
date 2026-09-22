-- =============================================================================
-- SIGAP - Sistem Informasi Gelar Alat & Perlengkapan K3 PLN ULP Balong
-- Skema Database & Data Awal Terupdate (MySQL / MariaDB - InnoDB)
-- Versi: 2.6 (Terupdate Lengkap: Notifikasi, Tanda Tangan Digital, Audit Temuan)
-- Tanggal Ekspor: 2026-09-22 23:37:30
-- =============================================================================
-- Urutan Dependensi Relasi Foreign Key:
--   1. users
--   2. regu
--   3. master_barang
--   4. template_checklist_item (FK -> master_barang)
--   5. gelar_alat_header       (FK -> regu, users)
--   6. gelar_alat_detail       (FK -> gelar_alat_header, master_barang)
--   7. notifikasi              (FK -> users)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS db_gelar_alat
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_gelar_alat;

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. TABEL USERS
--    Role: 'petugas' (pemeriksa lapangan) & 'manajemen' (atasan approver)
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
--    Data regu operasional lapangan beserta kendaraan dinas
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
--    Daftar standar checklist peralatan per jenis pekerjaan & tipe armada
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
-- 5. TABEL HEADER GELAR ALAT (BERITA ACARA)
--    Alur status: draft -> submitted -> approved / rejected
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
    ttd_petugas            LONGTEXT      NULL,
    petugas_id             INT           NOT NULL,
    ttd_manajemen          LONGTEXT      NULL,
    manajemen_id           INT           NULL,
    nama_pejabat_manajemen VARCHAR(100)  NULL,
    jabatan_manajemen      VARCHAR(100)  NULL,
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
--    Snapshot kondisi alat + audit trail perbaikan temuan K3
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
    sudah_diperbaiki      TINYINT(1)    NOT NULL DEFAULT 0,
    tgl_diperbaiki        DATETIME      NULL,
    diperbaiki_oleh       INT           NULL,
    catatan_perbaikan     VARCHAR(255)  NULL,
    INDEX idx_detail_header    (gelar_alat_id),
    INDEX idx_detail_barang    (master_barang_id),
    INDEX idx_detail_kondisi   (kondisi),
    INDEX idx_detail_perbaikan (sudah_diperbaiki),
    FOREIGN KEY (gelar_alat_id)    REFERENCES gelar_alat_header(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (master_barang_id) REFERENCES master_barang(id)     ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. TABEL NOTIFIKASI
--    Pemberitahuan in-app approval, revisi rejected, & peringatan K3
-- =============================================================================
CREATE TABLE IF NOT EXISTS notifikasi (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- DATA SEED & MASTER DATA (Idempoten - Aman dijalankan berulang kali)
-- Password default seluruh akun awal: 123456 (bcrypt hash)
-- =============================================================================

-- == 1. DATA SEED: Users ==
INSERT INTO users (id, username, password_hash, nama_lengkap, role, jabatan, no_hp) VALUES
(1, 'petugas_yandal', '$2y$10$AcrLpOpLY85Ezl4bKAryPeGFmQf9KABwmOwzBNKBYQLz4URRFO4Pi', 'Ropiko (Pelaksana Yandal)', 'petugas', 'Petugas Pelaksana Yandal ULP Balong', '081234567891'),
(2, 'petugas_p2tl', '$2y$10$AcrLpOpLY85Ezl4bKAryPeGFmQf9KABwmOwzBNKBYQLz4URRFO4Pi', 'Sayitno (Pelaksana P2TL)', 'petugas', 'Pelaksana Lapangan PT Citacontrac', '081234567892'),
(3, 'petugas_har', '$2y$10$AcrLpOpLY85Ezl4bKAryPeGFmQf9KABwmOwzBNKBYQLz4URRFO4Pi', 'Ahmad Fauzi (Pelaksana HAR)', 'petugas', 'Pelaksana Pemeliharaan Distribusi', '081234567893'),
(4, 'petugas_sr', '$2y$10$AcrLpOpLY85Ezl4bKAryPeGFmQf9KABwmOwzBNKBYQLz4URRFO4Pi', 'Budi Santoso (Pelaksana SR APP)', 'petugas', 'Pelaksana Penyambungan SR APP 1 Phasa', '081234567894'),
(5, 'manager_balong', '$2y$10$AcrLpOpLY85Ezl4bKAryPeGFmQf9KABwmOwzBNKBYQLz4URRFO4Pi', 'Yusuf Irfan', 'manajemen', 'Manager ULP Balong', '081234567800'),
(6, 'spv_yantek', '$2y$10$AcrLpOpLY85Ezl4bKAryPeGFmQf9KABwmOwzBNKBYQLz4URRFO4Pi', 'A. Kholid', 'manajemen', 'SPV Yantek ULP Balong', '081234567801'),
(7, 'tl_k3', '$2y$10$AcrLpOpLY85Ezl4bKAryPeGFmQf9KABwmOwzBNKBYQLz4URRFO4Pi', 'Bindraerda Hanindiawan', 'manajemen', 'TL K3L dan KAM ULP Balong', '081234567802'),
(8, 'tl_teknik', '$2y$10$AcrLpOpLY85Ezl4bKAryPeGFmQf9KABwmOwzBNKBYQLz4URRFO4Pi', 'Sofyan', 'manajemen', 'TL Teknik ULP Balong', '081234567803')
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    role         = VALUES(role),
    jabatan      = VALUES(jabatan),
    no_hp        = VALUES(no_hp);

-- == 2. DATA SEED: Regu & Armada ==
INSERT INTO regu (id, nama_regu, jenis_pekerjaan, kendaraan, nopol, keterangan) VALUES
(1, 'Regu 2 P2TL Citacontrac', 'p2tl', 'Mobil Operasional Citacontrac', 'AE 1234 CC', 'Penertiban Pemakaian Tenaga Listrik'),
(2, 'Regu Roda 3 SR APP Balong', 'sr_app', 'Kendaraan Roda Tiga Listrik', 'AE 8123 R3', 'Penyambungan & Bongkar SR APP 1 Phasa Roda 3'),
(3, 'Regu Roda 2 SR APP Balong', 'sr_app', 'Kendaraan Roda Dua', 'AE 4567 R2', 'Penyambungan & Bongkar SR APP 1 Phasa Roda 2'),
(4, 'Mobil Yandal Hilux Rangga', 'yandal', 'Hilux Rangga', 'AE 8297 BH', 'Pelayanan Gangguan Distribusi 24 Jam'),
(5, 'Regu ULC Sepeda Motor', 'yandal', 'Honda Verza', 'AE 2225 CG', 'Unit Layanan Cepat Gangguan'),
(6, 'Regu HAR A Truk Hino', 'har', 'Truk Hino', 'AE 8280 BH', 'Pemeliharaan Preventif & Korektif Jaringan'),
(7, 'Regu HAR B Mobil Carry', 'har', 'Suzuki Carry', 'E 8255 BH', 'Pemeliharaan Gardu & JTR')
ON DUPLICATE KEY UPDATE
    nama_regu       = VALUES(nama_regu),
    jenis_pekerjaan = VALUES(jenis_pekerjaan),
    kendaraan       = VALUES(kendaraan),
    nopol           = VALUES(nopol),
    keterangan      = VALUES(keterangan);

-- == 3. DATA SEED: Master Barang / Katalog Peralatan (121 Item) ==
INSERT INTO master_barang (id, nama_barang, kategori, satuan_default, gambar_referensi) VALUES
(1, 'Koordinator Lapangan', 'administrasi', 'Orang', NULL),
(2, 'Pelaksana Lapangan', 'administrasi', 'Orang', NULL),
(3, 'Pendamping Administrasi', 'administrasi', 'Orang', NULL),
(4, 'Helm Pengaman', 'k3_safety', 'Bh', NULL),
(5, 'Sarung Tangan Isolasi TR', 'k3_safety', 'Psg', NULL),
(6, 'Sepatu Safety', 'k3_safety', 'Psg', NULL),
(7, 'Sabuk Pengaman', 'k3_safety', 'Bh', NULL),
(8, 'Kacamata Pelindung Mata', 'k3_safety', 'Bh', NULL),
(9, 'Kotak P3K', 'k3_safety', 'Kotak', NULL),
(10, 'Jas Hujan', 'k3_safety', 'Set', NULL),
(11, 'APAR Mobil', 'k3_safety', 'Tabung', NULL),
(12, 'Tang Ampere 3 Phasa', 'alat_kerja', 'Unit', NULL),
(13, 'Tas Peralatan', 'alat_kerja', 'Bh', NULL),
(14, 'Tang Kombinasi', 'alat_kerja', 'Bh', NULL),
(15, 'Tang Potong', 'alat_kerja', 'Bh', NULL),
(16, 'Obeng Plus (+)', 'alat_kerja', 'Bh', NULL),
(17, 'Obeng Minus (-)', 'alat_kerja', 'Bh', NULL),
(18, 'Cutter', 'alat_kerja', 'Bh', NULL),
(19, 'Kunci Ringpas', 'alat_kerja', 'Set', NULL),
(20, 'Test Pen', 'alat_kerja', 'Bh', NULL),
(21, 'Lampu Senter', 'alat_kerja', 'Bh', NULL),
(22, 'Tangga Lipat Telescopic 4M', 'alat_kerja', 'Unit', NULL),
(23, 'Spy Cam Model Pulpen Clip', 'alat_kerja', 'Unit', NULL),
(24, 'Laptop 14 Inch 8/512GB', 'alat_kerja', 'Unit', NULL),
(25, 'Meja Dada', 'administrasi', 'Bh', NULL),
(26, 'ATK (Lakban, Plastik BB P2TL)', 'administrasi', 'Set', NULL),
(27, 'Stapler, Spidol Permanen', 'administrasi', 'Set', NULL),
(28, 'Tas Berkas', 'administrasi', 'Bh', NULL),
(29, 'Alat Kebersihan Mobil', 'kendaraan_pendukung', 'Set', NULL),
(30, 'Pulsa Komunikasi', 'administrasi', 'Paket', NULL),
(31, 'Power Meter Clamp On', 'alat_kerja', 'Bh/Regu', NULL),
(32, 'Hidrolik Dies', 'alat_kerja', 'Set/Regu', NULL),
(33, 'Kunci Inggris 8 Inch', 'alat_kerja', 'Bh/Regu', NULL),
(34, 'Tang Kombinasi 8 Inch', 'alat_kerja', 'Bh/Regu', NULL),
(35, 'Tang Potong 6 Inch', 'alat_kerja', 'Bh/Regu', NULL),
(36, 'Tang Lancip 6 Inch', 'alat_kerja', 'Bh/Regu', NULL),
(37, 'Obeng Plus', 'alat_kerja', 'Bh/Regu', NULL),
(38, 'Obeng Minus', 'alat_kerja', 'Bh/Regu', NULL),
(39, 'Palu 1/2 kg', 'alat_kerja', 'Bh/Regu', NULL),
(40, 'Betel', 'alat_kerja', 'Bh/Regu', NULL),
(41, 'Tali Tambang', 'alat_kerja', 'Bh/Regu', NULL),
(42, 'Test Pen SR', 'alat_kerja', 'Bh/Regu', NULL),
(43, 'Toolbox', 'alat_kerja', 'Bh/Regu', NULL),
(44, 'Lampu Senter Cas', 'alat_kerja', 'Bh/Regu', NULL),
(45, 'Rompi Spotlight', 'k3_safety', 'Bh/Orang', NULL),
(46, 'Helmet / Helm Kerja', 'k3_safety', 'Bh/Orang', NULL),
(47, 'Full Body Harness', 'k3_safety', 'Bh/Orang', NULL),
(48, 'Sarung Tangan Karet/Kain', 'k3_safety', 'Psg/Orang', NULL),
(49, 'Sepatu Karet / Safety', 'k3_safety', 'Psg/Orang', NULL),
(50, 'Jas Hujan Standard', 'k3_safety', 'Unit/Orang', NULL),
(51, 'Masker Medis / Kain', 'k3_safety', 'Box', NULL),
(52, 'Hand Sanitizer', 'k3_safety', 'Botol', NULL),
(53, 'Kotak P3K Lengkap', 'k3_safety', 'Unit/Regu', NULL),
(54, 'Kendaraan Operasional SR', 'kendaraan_pendukung', 'Unit/Regu', NULL),
(55, 'Alat Tulis & Buku Rekap', 'administrasi', 'Set/Regu', NULL),
(56, 'Smartphone Petugas', 'administrasi', 'Unit/Regu', NULL),
(57, 'Tangga Fiber Sliding 6 Meter', 'alat_kerja', 'Unit/Regu', NULL),
(58, 'Tangga Fiber (2 Section Ladder) 9.6 Mtr', 'alat_kerja', 'Bh', NULL),
(59, 'Telescopic Hotstick 20 kV; 10.5 Mtr', 'alat_kerja', 'Bh', NULL),
(60, 'Groundcluster Lengkap', 'alat_kerja', 'Set', NULL),
(61, 'Insulation Tester 10.000 Volt Analog', 'alat_kerja', 'Bh', NULL),
(62, 'Comealong (Wire Grip) 70 - 240 mm2', 'alat_kerja', 'Bh', NULL),
(63, 'Strainging Device 2 Ton', 'alat_kerja', 'Bh', NULL),
(64, 'Tang Scoon Hydraulic 10 s/d 70 mm2', 'alat_kerja', 'Bh', NULL),
(65, 'Compression Dies 50 mm - 300 mm', 'alat_kerja', 'Set', NULL),
(66, 'Tang Ampere (Clip on AVO Meter digital) 600 A', 'alat_kerja', 'Bh', NULL),
(67, 'Phase Sequence Indicator', 'alat_kerja', 'Set', NULL),
(68, 'Wire Cutter s.d 240 mm2', 'alat_kerja', 'Bh', NULL),
(69, 'Tool Set Lengkap (Besar) Mobil', 'alat_kerja', 'Bh', NULL),
(70, 'Head Lamp 70 Watt Charger', 'alat_kerja', 'Bh', NULL),
(71, 'Lampu Senter Battery (Re-charger)', 'alat_kerja', 'Bh', NULL),
(72, 'Lampu Sorot Halogen 50 Watt', 'alat_kerja', 'Bh', NULL),
(73, 'Power Inverter 100 W', 'alat_kerja', 'Bh', NULL),
(74, 'Fuse Puller 20 kV', 'alat_kerja', 'Bh', NULL),
(75, 'Palu Kecil', 'alat_kerja', 'Bh', NULL),
(76, 'Parang / Golok Babat', 'alat_kerja', 'Bh', NULL),
(77, 'Angkus', 'alat_kerja', 'Bh', NULL),
(78, 'Handle LBS Berisolasi', 'alat_kerja', 'Bh', NULL),
(79, 'Isolasi Listrik (3 Warna)', 'alat_kerja', 'Lot', NULL),
(80, 'Smartphone RAM 8 GB / 128 GB', 'administrasi', 'Bh', NULL),
(81, 'Handheld Holder Smartphone', 'administrasi', 'Bh', NULL),
(82, 'Powerbank 20.000 mAh', 'administrasi', 'Unit', NULL),
(83, 'GPS Tracker Mobil', 'kendaraan_pendukung', 'Bh', NULL),
(84, 'Roll Besi (Kerekan Kecil)', 'alat_kerja', 'Unit', NULL),
(85, 'Kunci L Hexa', 'alat_kerja', 'Set', NULL),
(86, 'Kunci L Bintang', 'alat_kerja', 'Set', NULL),
(87, 'Kunci Pipa 4 Inch', 'alat_kerja', 'Bh', NULL),
(88, 'Tali Nilon 12 mm 20 Meter', 'alat_kerja', 'Bh', NULL),
(89, 'Tali Manila 12 mm 20 Meter', 'alat_kerja', 'Bh', NULL),
(90, 'Tali Tampar 20 Mtr', 'alat_kerja', 'Bh', NULL),
(91, 'Tali Pengikat Tangga', 'alat_kerja', 'Mtr', NULL),
(92, 'Gergaji Besi', 'alat_kerja', 'Bh', NULL),
(93, 'Platform (Pijakan di Atas Tiang)', 'alat_kerja', 'Unit', NULL),
(94, 'AMR Tie Band Lengkap Ring', 'alat_kerja', 'Unit', NULL),
(95, 'Full Body Harness + Double Lanyard', 'k3_safety', 'Bh', NULL),
(96, 'Sarung Tangan Tahan Tegangan 1 kV (Karet)', 'k3_safety', 'Psg', NULL),
(97, 'Sarung Tangan 30 kV Kelas 3', 'k3_safety', 'Psg', NULL),
(98, 'Sepatu Karet 30 kV Kelas 3', 'k3_safety', 'Psg', NULL),
(99, 'Sepatu Safety Boots', 'k3_safety', 'Psg', NULL),
(100, 'Voltage Detector (Low & High Voltage) Non Contact', 'k3_safety', 'Bh', NULL),
(101, 'Tanda Peringatan Kerja', 'k3_safety', 'Bh', NULL),
(102, 'LoTo (Lock Out and Tag Out), Gembok & Rantai', 'k3_safety', 'Set', NULL),
(103, 'Rantai Plastik 10 Mtr', 'k3_safety', 'Bh', NULL),
(104, 'Traffic Cone', 'k3_safety', 'Set', NULL),
(105, 'Tempat Penyimpanan Sarung Tangan 20 kV', 'k3_safety', 'Bh', NULL),
(106, 'Kotak P3K Lengkap Isi Medis', 'k3_safety', 'Bh', NULL),
(107, 'APAR Mobil 3 kg Powder', 'k3_safety', 'Unit', NULL),
(108, 'Dongkrak + Kunci Ban Mobil', 'kendaraan_pendukung', 'Set', NULL),
(109, 'Kondisi Ban Utama (4 Roda)', 'kendaraan_pendukung', 'Bh', NULL),
(110, 'Ban Cadangan (Serep)', 'kendaraan_pendukung', 'Psg', NULL),
(111, 'Chain Saw Mesin 14 Inch', 'alat_kerja', 'Bh', NULL),
(112, 'Insulation Tester 1.000 Volt Digital', 'alat_kerja', 'Bh', NULL),
(113, 'Insulation Tester 5.000 Volt Digital', 'alat_kerja', 'Bh', NULL),
(114, 'Earth Tester Clip On / Online', 'alat_kerja', 'Bh', NULL),
(115, 'Palu Besar 5 kg', 'alat_kerja', 'Bh', NULL),
(116, 'Linggis Besar Baja', 'alat_kerja', 'Bh', NULL),
(117, 'Sosrok Tiang', 'alat_kerja', 'Bh', NULL),
(118, 'Kunci Moment', 'alat_kerja', 'Set', NULL),
(119, 'Tali Baja (Seling) 20 Meter', 'alat_kerja', 'Bh', NULL),
(120, 'Majun Pembersih', 'alat_kerja', 'Lot', NULL),
(121, 'Cairan Pembersih & Pelumas WD 40', 'alat_kerja', 'Bh', NULL)
ON DUPLICATE KEY UPDATE
    kategori       = VALUES(kategori),
    satuan_default = VALUES(satuan_default);

-- == 4. DATA SEED: Template Checklist Item (174 Item Standar) ==
INSERT INTO template_checklist_item (id, jenis_pekerjaan, regu_tipe, master_barang_id, jumlah_standar, urutan) VALUES
(350, 'p2tl', 'all', 1, 1, 1),
(351, 'p2tl', 'all', 2, 2, 2),
(352, 'p2tl', 'all', 3, 1, 3),
(353, 'p2tl', 'all', 4, 2, 4),
(354, 'p2tl', 'all', 5, 2, 5),
(355, 'p2tl', 'all', 6, 2, 6),
(356, 'p2tl', 'all', 7, 1, 7),
(357, 'p2tl', 'all', 8, 2, 8),
(358, 'p2tl', 'all', 9, 1, 9),
(359, 'p2tl', 'all', 10, 2, 10),
(360, 'p2tl', 'all', 11, 1, 11),
(361, 'p2tl', 'all', 12, 1, 12),
(362, 'p2tl', 'all', 13, 1, 13),
(363, 'p2tl', 'all', 14, 1, 14),
(364, 'p2tl', 'all', 15, 1, 15),
(365, 'p2tl', 'all', 16, 1, 16),
(366, 'p2tl', 'all', 17, 1, 17),
(367, 'p2tl', 'all', 18, 1, 18),
(368, 'p2tl', 'all', 19, 1, 19),
(369, 'p2tl', 'all', 20, 1, 20),
(370, 'p2tl', 'all', 21, 1, 21),
(371, 'p2tl', 'all', 22, 1, 22),
(372, 'p2tl', 'all', 23, 1, 23),
(373, 'p2tl', 'all', 24, 1, 24),
(374, 'p2tl', 'all', 25, 1, 25),
(375, 'p2tl', 'all', 26, 1, 26),
(376, 'p2tl', 'all', 27, 1, 27),
(377, 'p2tl', 'all', 28, 1, 28),
(378, 'p2tl', 'all', 29, 1, 29),
(379, 'p2tl', 'all', 30, 1, 30),
(380, 'sr_app', 'all', 31, 1, 1),
(381, 'sr_app', 'all', 32, 1, 2),
(382, 'sr_app', 'all', 33, 1, 3),
(383, 'sr_app', 'all', 34, 1, 4),
(384, 'sr_app', 'all', 35, 1, 5),
(385, 'sr_app', 'all', 36, 1, 6),
(386, 'sr_app', 'all', 37, 1, 7),
(387, 'sr_app', 'all', 38, 1, 8),
(388, 'sr_app', 'all', 39, 1, 9),
(389, 'sr_app', 'all', 40, 1, 10),
(390, 'sr_app', 'all', 41, 1, 11),
(391, 'sr_app', 'all', 42, 1, 12),
(392, 'sr_app', 'all', 43, 1, 13),
(393, 'sr_app', 'all', 44, 1, 14),
(394, 'sr_app', 'all', 45, 1, 15),
(395, 'sr_app', 'all', 46, 1, 16),
(396, 'sr_app', 'all', 47, 1, 17),
(397, 'sr_app', 'all', 48, 1, 18),
(398, 'sr_app', 'all', 49, 1, 19),
(399, 'sr_app', 'all', 50, 1, 20),
(400, 'sr_app', 'all', 51, 1, 21),
(401, 'sr_app', 'all', 52, 1, 22),
(402, 'sr_app', 'all', 53, 1, 23),
(403, 'sr_app', 'all', 54, 1, 24),
(404, 'sr_app', 'all', 55, 1, 25),
(405, 'sr_app', 'all', 56, 1, 26),
(406, 'sr_app', 'all', 57, 1, 27),
(407, 'yandal', 'all', 58, 1, 1),
(408, 'yandal', 'all', 59, 1, 2),
(409, 'yandal', 'all', 60, 2, 3),
(410, 'yandal', 'all', 61, 1, 4),
(411, 'yandal', 'all', 62, 1, 5),
(412, 'yandal', 'all', 63, 2, 6),
(413, 'yandal', 'all', 64, 1, 7),
(414, 'yandal', 'all', 65, 1, 8),
(415, 'yandal', 'all', 66, 1, 9),
(416, 'yandal', 'all', 67, 1, 10),
(417, 'yandal', 'all', 68, 1, 11),
(418, 'yandal', 'all', 69, 1, 12),
(419, 'yandal', 'all', 70, 2, 13),
(420, 'yandal', 'all', 71, 1, 14),
(421, 'yandal', 'all', 72, 1, 15),
(422, 'yandal', 'all', 73, 1, 16),
(423, 'yandal', 'all', 74, 1, 17),
(424, 'yandal', 'all', 75, 1, 18),
(425, 'yandal', 'all', 76, 2, 19),
(426, 'yandal', 'all', 77, 1, 20),
(427, 'yandal', 'all', 78, 1, 21),
(428, 'yandal', 'all', 79, 1, 22),
(429, 'yandal', 'all', 80, 1, 23),
(430, 'yandal', 'all', 81, 1, 24),
(431, 'yandal', 'all', 82, 1, 25),
(432, 'yandal', 'all', 83, 1, 26),
(433, 'yandal', 'all', 84, 1, 27),
(434, 'yandal', 'all', 85, 1, 28),
(435, 'yandal', 'all', 86, 1, 29),
(436, 'yandal', 'all', 87, 1, 30),
(437, 'yandal', 'all', 88, 1, 31),
(438, 'yandal', 'all', 89, 1, 32),
(439, 'yandal', 'all', 90, 1, 33),
(440, 'yandal', 'all', 91, 1, 34),
(441, 'yandal', 'all', 92, 1, 35),
(442, 'yandal', 'all', 93, 1, 36),
(443, 'yandal', 'all', 94, 1, 37),
(444, 'yandal', 'all', 95, 2, 38),
(445, 'yandal', 'all', 96, 2, 39),
(446, 'yandal', 'all', 97, 2, 40),
(447, 'yandal', 'all', 98, 2, 41),
(448, 'yandal', 'all', 99, 1, 42),
(449, 'yandal', 'all', 100, 1, 43),
(450, 'yandal', 'all', 101, 2, 44),
(451, 'yandal', 'all', 102, 4, 45),
(452, 'yandal', 'all', 103, 1, 46),
(453, 'yandal', 'all', 104, 2, 47),
(454, 'yandal', 'all', 105, 1, 48),
(455, 'yandal', 'all', 106, 1, 49),
(456, 'yandal', 'all', 107, 1, 50),
(457, 'yandal', 'all', 108, 1, 51),
(458, 'yandal', 'all', 109, 4, 52),
(459, 'yandal', 'all', 110, 1, 53),
(460, 'har', 'all', 58, 1, 1),
(461, 'har', 'all', 59, 1, 2),
(462, 'har', 'all', 60, 2, 3),
(463, 'har', 'all', 61, 1, 4),
(464, 'har', 'all', 62, 1, 5),
(465, 'har', 'all', 63, 2, 6),
(466, 'har', 'all', 64, 1, 7),
(467, 'har', 'all', 65, 1, 8),
(468, 'har', 'all', 66, 1, 9),
(469, 'har', 'all', 67, 1, 10),
(470, 'har', 'all', 68, 1, 11),
(471, 'har', 'all', 69, 1, 12),
(472, 'har', 'all', 70, 2, 13),
(473, 'har', 'all', 71, 1, 14),
(474, 'har', 'all', 72, 1, 15),
(475, 'har', 'all', 73, 1, 16),
(476, 'har', 'all', 74, 1, 17),
(477, 'har', 'all', 75, 1, 18),
(478, 'har', 'all', 76, 2, 19),
(479, 'har', 'all', 77, 1, 20),
(480, 'har', 'all', 78, 1, 21),
(481, 'har', 'all', 79, 1, 22),
(482, 'har', 'all', 80, 1, 23),
(483, 'har', 'all', 81, 1, 24),
(484, 'har', 'all', 82, 1, 25),
(485, 'har', 'all', 83, 1, 26),
(486, 'har', 'all', 84, 1, 27),
(487, 'har', 'all', 85, 1, 28),
(488, 'har', 'all', 86, 1, 29),
(489, 'har', 'all', 87, 1, 30),
(490, 'har', 'all', 88, 1, 31),
(491, 'har', 'all', 89, 1, 32),
(492, 'har', 'all', 90, 1, 33),
(493, 'har', 'all', 91, 1, 34),
(494, 'har', 'all', 92, 1, 35),
(495, 'har', 'all', 93, 1, 36),
(496, 'har', 'all', 94, 1, 37),
(497, 'har', 'all', 95, 2, 38),
(498, 'har', 'all', 96, 2, 39),
(499, 'har', 'all', 97, 2, 40),
(500, 'har', 'all', 98, 2, 41),
(501, 'har', 'all', 99, 1, 42),
(502, 'har', 'all', 100, 1, 43),
(503, 'har', 'all', 101, 2, 44),
(504, 'har', 'all', 102, 4, 45),
(505, 'har', 'all', 103, 1, 46),
(506, 'har', 'all', 104, 2, 47),
(507, 'har', 'all', 105, 1, 48),
(508, 'har', 'all', 106, 1, 49),
(509, 'har', 'all', 107, 1, 50),
(510, 'har', 'all', 108, 1, 51),
(511, 'har', 'all', 109, 4, 52),
(512, 'har', 'all', 110, 1, 53),
(513, 'har', 'all', 111, 2, 54),
(514, 'har', 'all', 112, 1, 55),
(515, 'har', 'all', 113, 1, 56),
(516, 'har', 'all', 114, 1, 57),
(517, 'har', 'all', 115, 1, 58),
(518, 'har', 'all', 116, 1, 59),
(519, 'har', 'all', 117, 1, 60),
(520, 'har', 'all', 118, 1, 61),
(521, 'har', 'all', 119, 1, 62),
(522, 'har', 'all', 120, 1, 63),
(523, 'har', 'all', 121, 1, 64)
ON DUPLICATE KEY UPDATE
    jumlah_standar = VALUES(jumlah_standar),
    urutan         = VALUES(urutan);

-- == 5. DATA SEED: Notifikasi Awal ==
INSERT INTO notifikasi (id, user_id, judul, pesan, tipe, url_aksi, sudah_dibaca) VALUES
(1, 1, '✅ Temuan Diperbaiki: Tang Ampere 3 Phasa', 'Alat <strong>Tang Ampere 3 Phasa</strong> (RUSAK) yang Anda laporkan pada laporan Regu 2 P2TL Citacontrac (22/09/2026) telah ditandai <strong>selesai diperbaiki</strong> oleh <strong>Yusuf Irfan</strong>.', 'perbaikan', '/sigap/riwayat/detail.php?id=1', 1),
(2, 1, '✅ Temuan Diperbaiki: Tang Ampere 3 Phasa', 'Alat <strong>Tang Ampere 3 Phasa</strong> (RUSAK) yang Anda laporkan pada laporan Regu 2 P2TL Citacontrac (22/09/2026) telah ditandai <strong>selesai diperbaiki</strong> oleh <strong>Yusuf Irfan</strong>.', 'perbaikan', '/sigap/riwayat/detail.php?id=2', 1),
(3, 2, '✅ Temuan Diperbaiki: Tang Potong', 'Alat <strong>Tang Potong</strong> (RUSAK) yang Anda laporkan pada laporan Regu 2 P2TL Citacontrac (22/09/2026) telah ditandai <strong>selesai diperbaiki</strong> oleh <strong>Yusuf Irfan</strong>.', 'perbaikan', '/sigap/riwayat/detail.php?id=4', 0)
ON DUPLICATE KEY UPDATE
    judul        = VALUES(judul),
    pesan        = VALUES(pesan),
    sudah_dibaca = VALUES(sudah_dibaca);

-- =============================================================================
-- SELESAI: Skema & Data Lengkap SIGAP Siap Digunakan
-- =============================================================================
