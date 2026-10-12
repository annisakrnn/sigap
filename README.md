# SIM-GelarAlat - Sistem Digitalisasi Inspeksi Gelar Pasukan & K3

Aplikasi web berbasis **PHP Native** untuk mendigitalkan proses apel gelar pasukan dan peralatan kerja K3 kelistrikan.

## Cara Menjalankan

### 1. Pastikan Laragon Berjalan
Buka Laragon dan klik **Start All** (Apache + MySQL harus hijau ✓)

### 2. Setup Database (WAJIB, hanya 1 kali)
Buka browser dan akses:
```
http://localhost/sigap/setup.php
```
Halaman setup akan otomatis membuat database, semua tabel, dan data demo.

### 3. Buka Aplikasi
```
http://localhost/sigap/
```
> **Catatan Alur Petugas Lapangan:**
> Petugas pemeriksa di lapangan **tidak perlu login lagi**. Begitu membuka web, form pemilihan regu dan checklist peralatan langsung tampil. Petugas cukup mengisi nama pelaksana, centang kondisi alat, foto apel, dan tanda tangan digital.
>
> Login hanya diperuntukkan bagi **Manajemen Atasan / Approver** untuk mereview dan menyetujui laporan.

---

## Akun Demo Manajemen (Password: 123456)
Akses login: `http://localhost/sigap/auth/login.php`

| Username | Nama | Jabatan | Role |
|---|---|---|---|
| `manager_balong` | Yusuf Irfan | Manager ULP Balong | Manajemen Approver |
| `spv_yantek` | A. Kholid | SPV Yantek ULP Balong | Manajemen Approver |
| `tl_k3` | Bindraerda Hanindiawan | TL K3L dan KAM ULP Balong | Manajemen Approver |
| `tl_teknik` | Sofyan | TL Teknik ULP Balong | Manajemen Approver |

---

## Alur Penggunaan

1. **Petugas Lapangan (Tanpa Login)**:
   - Buka `http://localhost/sigap/`
   - Pilih Jenis Pekerjaan (P2TL, YANDAL, HAR, SR APP) & Regu
   - Isi Nama Pelaksana 1 & 2
   - Isi checklist kelengkapan & kondisi alat, unggah foto kegiatan, dan bubuhkan tanda tangan digital
   - Klik **Kirim Laporan ke Atasan**

2. **Manajemen Atasan (Login)**:
   - Buka `http://localhost/sigap/auth/login.php` (atau klik tombol *Login Manajemen* di pojok kanan atas)
   - Buka **Dashboard K3** atau **Antrean Approval**
   - Review laporan & foto kegiatan, bubuhkan tanda tangan digital manajemen, lalu klik **Setujui (Approve)**
   - Dokumen langsung siap dicetak menjadi **Berita Acara Resmi (A4/PDF)**

---

## Struktur File Proyek

```
sigap/
├── index.php              # Entry point (redirect otomatis sesuai role)
├── setup.php              # Setup database & seeder (jalankan 1x)
├── config/
│   └── database.php       # Konfigurasi koneksi MySQL PDO
├── database/
│   └── schema.sql         # Skema DDL tabel
├── includes/
│   ├── auth_check.php     # Middleware autentikasi & role
│   ├── header.php         # HTML header & navigasi
│   └── footer.php         # HTML footer & script
├── assets/
│   ├── css/style.css      # Stylesheet modern
│   └── js/signature.js    # Library canvas tanda tangan digital
├── auth/
│   ├── login.php          # Halaman login (+ 1-click demo)
│   └── logout.php         # Handler logout
├── inspeksi/
│   ├── index.php          # Pilih jenis & regu gelar alat
│   ├── form.php           # Form checklist dinamis (mobile-friendly)
│   └── simpan.php         # Backend save transaksi & upload foto
├── dashboard/
│   └── index.php          # Dashboard monitoring manajemen atasan
├── approval/
│   ├── review.php         # Review detail & form tanda tangan atasan
│   └── proses.php         # Backend approve/reject
├── riwayat/
│   ├── index.php          # Riwayat & arsip berita acara (dengan filter)
│   └── detail.php         # Detail satu berita acara
├── cetak/
│   └── berita_acara.php   # Cetak Berita Acara Resmi format A4
└── uploads/               # (Auto-created) Folder foto & tanda tangan
    ├── foto_kegiatan/
    ├── foto_temuan/
    └── ttd/
```

---

## Fitur Utama

- ✅ **4 Template Checklist Dinamis:** P2TL, SR APP 1 Phasa, YANDAL/ULC, HAR A/B
- ✅ **Tombol Cepat "Tandai Semua Baik"** untuk efisiensi inspeksi lapangan
- ✅ **Upload Foto Temuan** langsung dari kamera HP
- ✅ **Tanda Tangan Digital Canvas** (Touch-friendly, mobile)
- ✅ **Dashboard Monitoring K3** dengan Red Flag alert alat rusak
- ✅ **Workflow Approval** 2 Role (Petugas → Manajemen Atasan)
- ✅ **Cetak Berita Acara Resmi** format A4 dengan tanda tangan berdampingan
- ✅ **Riwayat & Arsip** dengan filter status/jenis/bulan
