-- Skema Database Sistem Digitalisasi Gelar Alat (SIM-GelarAlat)
-- DBMS: MySQL / MariaDB

CREATE DATABASE IF NOT EXISTS db_gelar_alat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_gelar_alat;

-- 1. Tabel Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('petugas', 'manajemen') NOT NULL,
    jabatan VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Tabel Regu & Armada
CREATE TABLE IF NOT EXISTS regu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_regu VARCHAR(100) NOT NULL,
    jenis_pekerjaan ENUM('p2tl', 'sr_app', 'yandal', 'har') NOT NULL,
    kendaraan VARCHAR(100) NULL,
    nopol VARCHAR(20) NULL,
    keterangan VARCHAR(255) NULL
) ENGINE=InnoDB;

-- 3. Tabel Master Barang / Alat
CREATE TABLE IF NOT EXISTS master_barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_barang VARCHAR(150) NOT NULL,
    kategori ENUM('alat_kerja', 'k3_safety', 'kendaraan_pendukung', 'administrasi') NOT NULL,
    satuan_default VARCHAR(30) NOT NULL,
    gambar_referensi VARCHAR(255) NULL
) ENGINE=InnoDB;

-- 4. Tabel Template Checklist
CREATE TABLE IF NOT EXISTS template_checklist_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jenis_pekerjaan ENUM('p2tl', 'sr_app', 'yandal', 'har') NOT NULL,
    regu_tipe ENUM('all', 'roda_3', 'roda_2') DEFAULT 'all',
    master_barang_id INT NOT NULL,
    jumlah_standar INT NOT NULL DEFAULT 1,
    urutan INT DEFAULT 0,
    INDEX (jenis_pekerjaan),
    FOREIGN KEY (master_barang_id) REFERENCES master_barang(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Tabel Header Transaksi Gelar Alat
CREATE TABLE IF NOT EXISTS gelar_alat_header (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomor_dokumen VARCHAR(100) NULL,
    tanggal_inspeksi DATE NOT NULL,
    bulan_tahun VARCHAR(50) NOT NULL,
    jenis_pekerjaan ENUM('p2tl', 'sr_app', 'yandal', 'har') NOT NULL,
    regu_id INT NOT NULL,
    nama_pelaksana_1 VARCHAR(100) NOT NULL,
    nama_pelaksana_2 VARCHAR(100) NULL,
    pendamping_admin VARCHAR(100) NULL,
    catatan_umum TEXT NULL,
    foto_kegiatan VARCHAR(255) NULL,
    status ENUM('draft', 'submitted', 'approved', 'rejected') DEFAULT 'draft',
    
    -- Tanda Tangan & Metadata Petugas Pemeriksa
    ttd_petugas LONGTEXT NULL,
    petugas_id INT NOT NULL,
    
    -- Tanda Tangan & Metadata Manajemen Atasan
    ttd_manajemen LONGTEXT NULL,
    manajemen_id INT NULL,
    nama_pejabat_manajemen VARCHAR(100) NULL,
    jabatan_manajemen VARCHAR(100) NULL,
    catatan_manajemen TEXT NULL,
    tanggal_approval DATETIME NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (regu_id) REFERENCES regu(id),
    FOREIGN KEY (petugas_id) REFERENCES users(id),
    FOREIGN KEY (manajemen_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Tabel Detail Hasil Pemeriksaan
CREATE TABLE IF NOT EXISTS gelar_alat_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gelar_alat_id INT NOT NULL,
    master_barang_id INT NOT NULL,
    nama_barang_snapshot VARCHAR(150) NOT NULL,
    kategori_snapshot VARCHAR(50) NOT NULL,
    merk_type VARCHAR(100) NULL,
    jumlah_standar INT NOT NULL,
    jumlah_realisasi INT NOT NULL,
    satuan VARCHAR(30) NOT NULL,
    kondisi ENUM('baik', 'rusak', 'waktu_ganti', 'hilang', 'ada', 'tidak_ada') NOT NULL,
    keterangan VARCHAR(255) NULL,
    foto_temuan VARCHAR(255) NULL,
    INDEX (gelar_alat_id),
    FOREIGN KEY (gelar_alat_id) REFERENCES gelar_alat_header(id) ON DELETE CASCADE,
    FOREIGN KEY (master_barang_id) REFERENCES master_barang(id)
) ENGINE=InnoDB;
