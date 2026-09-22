# SIM-GelarAlat - Sistem Digitalisasi Inspeksi Gelar Pasukan & K3

Aplikasi web berbasis **PHP Native** untuk mendigitalkan proses apel gelar pasukan dan peralatan kerja K3 kelistrikan.

## Cara Menjalankan

### 1. Pastikan Laragon Berjalan
Buka Laragon dan klik **Start All** (Apache + MySQL harus hijau ✓)

### 2. Setup Database (WAJIB, hanya 1 kali)
Buka browser dan akses:
```
http://localhost/gelar_alat/setup.php
```
Halaman setup akan otomatis membuat database, semua tabel, dan data demo.

### 3. Buka Aplikasi
```
http://localhost/gelar_alat/
```

---

## Akun Demo (Password Semua: 123456)

### 🔵 Role: Petugas Pemeriksa Alat
| Username | Nama | Regu |
|---|---|---|
| `petugas_yandal` | Ropiko | Pelaksana Yandal ULP Balong |
| `petugas_p2tl` | Sayitno | Pelaksana Lapangan P2TL |
| `petugas_har` | Ahmad Fauzi | Pelaksana Pemeliharaan |
| `petugas_sr` | Budi Santoso | Pelaksana SR APP 1 Phasa |

### 🟡 Role: Manajemen Atasan (Approver)
| Username | Nama | Jabatan |
|---|---|---|
| `manager_balong` | Yusuf Irfan | Manager ULP Balong |
| `spv_yantek` | A. Kholid | SPV Yantek ULP Balong |
| `tl_k3` | Bindraerda Hanindiawan | TL K3L dan KAM ULP Balong |
| `tl_teknik` | Sofyan | TL Teknik ULP Balong |

---

## Alur Penggunaan

1. **Petugas login** → Pilih jenis regu → Isi checklist → Tanda tangan digital → Submit
2. **Manajemen login** → Dashboard → Review laporan → Tanda tangan digital → Approve
3. Laporan `APPROVED` → Cetak Berita Acara Resmi format A4 (siap print / save PDF)

---

## Struktur File Proyek

```
gelar_alat/
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
