<?php
/**
 * Cetak Berita Acara Gelar Alat - Format Resmi A4
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$pdo = get_db_connection();
if (!$pdo) exit("Database error.");

$user = get_logged_user();
$id = intval($_GET['id'] ?? 0);

$stmt_h = $pdo->prepare("
    SELECT h.*, r.nama_regu, r.nopol, r.kendaraan,
           u.nama_lengkap AS nama_petugas, u.jabatan AS jabatan_petugas
    FROM gelar_alat_header h
    JOIN regu r ON h.regu_id = r.id
    JOIN users u ON h.petugas_id = u.id
    WHERE h.id = :id AND h.status = 'approved' LIMIT 1
");
$stmt_h->execute([':id' => $id]);
$header = $stmt_h->fetch();

if (!$header) {
    exit("Dokumen tidak ditemukan atau belum berstatus APPROVED.");
}

$stmt_d = $pdo->prepare("SELECT * FROM gelar_alat_detail WHERE gelar_alat_id = :id ORDER BY kategori_snapshot, id");
$stmt_d->execute([':id' => $id]);
$details = $stmt_d->fetchAll();

$details_by_cat = [];
foreach ($details as $d) {
    $details_by_cat[$d['kategori_snapshot']][] = $d;
}

$kategori_labels = [
    'alat_kerja'          => 'Peralatan Kerja',
    'k3_safety'           => 'APD & Peralatan K3',
    'kendaraan_pendukung' => 'Armada & Sarana Pendukung',
    'administrasi'        => 'Sarana Administrasi',
];

$jenis_label_full = [
    'p2tl'   => 'Penertiban Pemakaian Tenaga Listrik (P2TL)',
    'sr_app' => 'Jasa Penyambungan & Pembongkaran SR APP 1 Phasa',
    'yandal' => 'Pelayanan Gangguan Distribusi (YANDAL / ULC)',
    'har'    => 'Pemeliharaan Jaringan Distribusi (HAR)',
];

$kondisi_label_print = [
    'baik'        => 'BAIK',
    'rusak'       => 'RUSAK',
    'waktu_ganti' => 'GANTI',
    'ada'         => 'ADA',
    'hilang'      => 'HILANG',
    'tidak_ada'   => 'TDK ADA',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Gelar Alat - <?= htmlspecialchars($header['nomor_dokumen']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            background: #f0f0f0;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 10px auto;
            padding: 15mm 12mm 15mm 12mm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* KOP SURAT */
        .kop {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #000;
            border-bottom: 2px solid #000;
            padding: 10px 15px;
            margin-bottom: 0;
        }
        .kop-text {
            text-align: center;
            flex: 1;
        }
        .kop-text .unit { font-size: 11pt; font-weight: bold; }
        .kop-text .alamat { font-size: 8pt; margin-top: 2px; }
        .kop-judul {
            text-align: center;
            border: 2px solid #000;
            border-top: none;
            padding: 6px;
            margin-bottom: 6px;
        }
        .kop-judul h2 { font-size: 12pt; font-weight: bold; letter-spacing: 1px; }
        .kop-judul h3 { font-size: 10pt; font-weight: bold; }

        /* INFO TABEL */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 9.5pt; }
        .info-table td { padding: 2px 4px; vertical-align: top; }

        /* MAIN TABLE */
        .main-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 6px; }
        .main-table th { background: #e8e8e8; font-weight: bold; padding: 4px 5px; border: 1px solid #000; text-align: center; font-size: 8.5pt; }
        .main-table td { padding: 3px 5px; border: 1px solid #000; vertical-align: middle; }
        .main-table .cat-row { background: #f5f5f5; font-weight: bold; font-size: 8.5pt; }
        .main-table .cond-baik { text-align: center; font-weight: bold; color: #145a32; }
        .main-table .cond-rusak { text-align: center; font-weight: bold; color: #7b241c; background: #fdebd0; }
        .main-table .cond-waktu_ganti { text-align: center; font-weight: bold; color: #784212; background: #fef9e7; }

        /* TTD SECTION */
        .ttd-section { margin-top: 10px; }
        .ttd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .ttd-box { border: 1px solid #000; padding: 8px 10px; text-align: center; }
        .ttd-box .ttd-title { font-weight: bold; font-size: 9pt; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 6px; }
        .ttd-box .ttd-canvas { height: 60px; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
        .ttd-box .ttd-name { font-weight: bold; font-size: 9pt; border-top: 1px solid #000; margin-top: 6px; padding-top: 4px; }
        .ttd-box .ttd-jabatan { font-size: 8pt; color: #333; }

        /* No Print */
        .no-print { background: #0a2540; color: white; text-align: center; padding: 15px; font-family: sans-serif; font-size: 13px; }
        .no-print a { background: #10b981; color: white; padding: 8px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-block; }
        .no-print .btn-back { background: rgba(255,255,255,0.15); margin-right: 8px; }

        @media print {
            body { background: white; }
            .page { box-shadow: none; margin: 0; padding: 12mm 10mm; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Tombol Cetak (tidak tercetak) -->
<div class="no-print" style="position:sticky;top:0;z-index:99;display:flex;align-items:center;justify-content:space-between;padding:12px 24px;background:#0f172a;box-shadow:0 4px 12px rgba(0,0,0,0.15);flex-wrap:wrap;gap:10px;">
    <div>
        <a href="<?= base_url('riwayat/detail.php?id=' . $id) ?>" class="btn-back" style="background:#334155;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.85rem;display:inline-block;">
            &larr; Kembali ke Detail
        </a>
    </div>
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <span style="color:#94a3b8;font-size:0.825rem;">
            Tips: Pilih printer <strong>"Save as PDF"</strong> pada dialog print untuk mengunduh PDF resmi.
        </span>
        <button onclick="window.print()" style="background:#10b981;color:white;border:none;padding:9px 20px;border-radius:6px;font-weight:700;font-size:0.9rem;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
            🖨 Cetak / Simpan PDF
        </button>
    </div>
</div>

<div class="page">

    <!-- KOP SURAT -->
    <div class="kop">
        <div class="kop-text">
            <div class="unit">PT PLN (PERSERO) UP3 PONOROGO</div>
            <div class="unit">UNIT LAYANAN PELANGGAN (ULP) BALONG</div>
            <div class="alamat">JL. RAYA BALONG KM 12, BALONG, PONOROGO &mdash; JAWA TIMUR &mdash; 63471</div>
        </div>
    </div>
    <div class="kop-judul">
        <h2>BERITA ACARA GELAR PASUKAN DAN PERALATAN KERJA</h2>
        <h3><?= htmlspecialchars(strtoupper($jenis_label_full[$header['jenis_pekerjaan']] ?? $header['jenis_pekerjaan'])) ?></h3>
    </div>

    <!-- Info Header Dokumen -->
    <table class="info-table" style="border:1px solid #000;margin-bottom:4px;">
        <tr>
            <td style="width:20%;font-weight:bold;background:#f5f5f5;border-right:1px solid #ccc;border-bottom:1px solid #ccc;">No. Dokumen</td>
            <td style="width:30%;border-right:1px solid #ccc;border-bottom:1px solid #ccc;"><?= htmlspecialchars($header['nomor_dokumen'] ?? '-') ?></td>
            <td style="width:20%;font-weight:bold;background:#f5f5f5;border-right:1px solid #ccc;border-bottom:1px solid #ccc;">Tanggal Gelar</td>
            <td style="border-bottom:1px solid #ccc;"><?= date('d F Y', strtotime($header['tanggal_inspeksi'])) ?></td>
        </tr>
        <tr>
            <td style="font-weight:bold;background:#f5f5f5;border-right:1px solid #ccc;border-bottom:1px solid #ccc;">Regu / Unit</td>
            <td style="border-right:1px solid #ccc;border-bottom:1px solid #ccc;"><?= htmlspecialchars($header['nama_regu']) ?></td>
            <td style="font-weight:bold;background:#f5f5f5;border-right:1px solid #ccc;border-bottom:1px solid #ccc;">Kendaraan / Nopol</td>
            <td style="border-bottom:1px solid #ccc;"><?= htmlspecialchars($header['kendaraan'] ?? '-') ?> / <?= htmlspecialchars($header['nopol'] ?? '-') ?></td>
        </tr>
        <tr>
            <td style="font-weight:bold;background:#f5f5f5;border-right:1px solid #ccc;">Pelaksana 1</td>
            <td style="border-right:1px solid #ccc;"><?= htmlspecialchars($header['nama_pelaksana_1']) ?></td>
            <td style="font-weight:bold;background:#f5f5f5;border-right:1px solid #ccc;">Pelaksana 2</td>
            <td><?= htmlspecialchars($header['nama_pelaksana_2'] ?? '-') ?></td>
        </tr>
    </table>

    <!-- MAIN TABLE CHECKLIST -->
    <table class="main-table">
        <thead>
            <tr>
                <th style="width:28px;">No</th>
                <th>Nama Barang / Alat Kerja & APD</th>
                <th style="width:60px;">Standar</th>
                <th style="width:55px;">Realisasi</th>
                <th style="width:55px;">Kondisi</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($details_by_cat as $cat_key => $cat_items): ?>
                <tr class="cat-row">
                    <td colspan="6" style="padding:3px 6px;">
                        <?= strtoupper($kategori_labels[$cat_key] ?? ucfirst($cat_key)) ?>
                    </td>
                </tr>
                <?php foreach ($cat_items as $d): ?>
                    <tr>
                        <td style="text-align:center;"><?= $no++ ?></td>
                        <td><?= htmlspecialchars($d['nama_barang_snapshot']) ?></td>
                        <td style="text-align:center;"><?= $d['jumlah_standar'] ?> <?= htmlspecialchars($d['satuan']) ?></td>
                        <td style="text-align:center;font-weight:bold;<?= $d['jumlah_realisasi'] < $d['jumlah_standar'] ? 'color:#7b241c;' : '' ?>">
                            <?= $d['jumlah_realisasi'] ?>
                        </td>
                        <td class="cond-<?= $d['kondisi'] ?>"><?= $kondisi_label_print[$d['kondisi']] ?? $d['kondisi'] ?></td>
                        <td style="font-size:8.5pt;"><?= htmlspecialchars($d['keterangan'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($header['catatan_umum'] || $header['catatan_manajemen']): ?>
    <div style="border:1px solid #ccc;padding:6px 8px;margin-bottom:8px;font-size:8.5pt;">
        <?php if ($header['catatan_umum']): ?>
            <strong>Catatan Petugas:</strong> <?= nl2br(htmlspecialchars($header['catatan_umum'])) ?><br>
        <?php endif; ?>
        <?php if ($header['catatan_manajemen']): ?>
            <strong>Catatan Manajemen:</strong> <?= nl2br(htmlspecialchars($header['catatan_manajemen'])) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Tanda Tangan -->
    <div class="ttd-section">
        <div class="ttd-grid">
            <!-- Petugas Pemeriksa -->
            <div class="ttd-box">
                <div class="ttd-title">Petugas Pemeriksa Lapangan</div>
                <div class="ttd-canvas">
                    <?php if (!empty($header['ttd_petugas'])): ?>
                        <img src="<?= htmlspecialchars($header['ttd_petugas']) ?>" style="max-height:55px;max-width:160px;">
                    <?php else: ?>
                        <div style="height:55px;"></div>
                    <?php endif; ?>
                </div>
                <div class="ttd-name"><?= htmlspecialchars($header['nama_pelaksana_1']) ?></div>
                <div class="ttd-jabatan"><?= htmlspecialchars($header['jabatan_petugas']) ?></div>
                <div class="ttd-jabatan" style="margin-top:2px;">Tanggal: <?= date('d/m/Y', strtotime($header['tanggal_inspeksi'])) ?></div>
            </div>

            <!-- Manajemen Atasan / Pengesahan -->
            <div class="ttd-box">
                <div class="ttd-title">Diketahui & Disahkan Oleh</div>
                <div class="ttd-canvas">
                    <?php if (!empty($header['ttd_manajemen'])): ?>
                        <img src="<?= htmlspecialchars($header['ttd_manajemen']) ?>" style="max-height:55px;max-width:160px;">
                    <?php else: ?>
                        <div style="height:55px;"></div>
                    <?php endif; ?>
                </div>
                <div class="ttd-name"><?= htmlspecialchars($header['nama_pejabat_manajemen'] ?? '-') ?></div>
                <div class="ttd-jabatan"><?= htmlspecialchars($header['jabatan_manajemen'] ?? '-') ?></div>
                <div class="ttd-jabatan" style="margin-top:2px;">Tanggal: <?= $header['tanggal_approval'] ? date('d/m/Y', strtotime($header['tanggal_approval'])) : '-' ?></div>
            </div>
        </div>
    </div>

    <!-- Footer Dokumen -->
    <div style="margin-top:12px;text-align:center;font-size:8pt;color:#777;border-top:1px solid #ddd;padding-top:6px;">
        Dicetak dari SIGAP &mdash; Sistem Informasi Gelar Alat & Perlengkapan &mdash; <?= date('d/m/Y H:i') ?>
    </div>

</div>

</body>
</html>
