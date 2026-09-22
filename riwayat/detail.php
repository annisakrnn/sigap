<?php
/**
 * Detail Berita Acara Gelar Alat
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();
$id = intval($_GET['id'] ?? 0);

$stmt_h = $pdo->prepare("
    SELECT h.*, r.nama_regu, r.nopol, r.kendaraan,
           u.nama_lengkap AS nama_petugas, u.jabatan AS jabatan_petugas
    FROM gelar_alat_header h
    JOIN regu r ON h.regu_id = r.id
    JOIN users u ON h.petugas_id = u.id
    WHERE h.id = :id LIMIT 1
");
$stmt_h->execute([':id' => $id]);
$header = $stmt_h->fetch();

if (!$header) {
    set_flash('error', "Data tidak ditemukan.");
    header("Location: index.php"); exit;
}

// Hanya petugas pemilik atau manajemen yang bisa melihat
if ($user['role'] === 'petugas' && $header['petugas_id'] != $user['id']) {
    set_flash('error', "Akses ditolak. Anda hanya dapat melihat laporan milik Anda sendiri.");
    header("Location: index.php"); exit;
}

$stmt_d = $pdo->prepare("SELECT * FROM gelar_alat_detail WHERE gelar_alat_id = :id ORDER BY kategori_snapshot, id");
$stmt_d->execute([':id' => $id]);
$details = $stmt_d->fetchAll();

$details_by_cat = [];
foreach ($details as $d) {
    $details_by_cat[$d['kategori_snapshot']][] = $d;
}

$kategori_labels = [
    'alat_kerja'          => ['label' => 'Peralatan Kerja', 'color' => '#0072ce'],
    'k3_safety'           => ['label' => 'APD & K3', 'color' => '#ef4444'],
    'kendaraan_pendukung' => ['label' => 'Armada & Sarana', 'color' => '#f59e0b'],
    'administrasi'        => ['label' => 'Administrasi', 'color' => '#8b5cf6'],
];

$jenis_label = [
    'p2tl'   => 'P2TL - Penertiban Pemakaian Tenaga Listrik',
    'sr_app' => 'SR APP 1 Phasa',
    'yandal' => 'YANDAL & ULC - Pelayanan Gangguan',
    'har'    => 'HAR - Pemeliharaan Jaringan Distribusi',
];

$kondisi_labels = [
    'baik'        => ['label' => '✓ Baik', 'class' => 'cond-baik'],
    'rusak'       => ['label' => '✗ Rusak', 'class' => 'cond-rusak'],
    'waktu_ganti' => ['label' => '⚠ Waktu Ganti', 'class' => 'cond-waktu_ganti'],
    'ada'         => ['label' => '○ Ada', 'class' => 'cond-ada'],
    'hilang'      => ['label' => '⚠ Hilang', 'class' => 'badge-rejected'],
    'tidak_ada'   => ['label' => '✗ Tidak Ada', 'class' => 'badge-rejected'],
];

$page_title = "Detail BA Gelar Alat #" . $id;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <a href="index.php" class="btn btn-outline btn-sm" style="margin-bottom:8px;">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
        <h1 style="font-size:1.5rem;margin-bottom:4px;">
            Detail Berita Acara Gelar Alat
            <span class="badge badge-<?= $header['status'] ?>" style="margin-left:8px;font-size:0.8rem;"><?= strtoupper($header['status']) ?></span>
        </h1>
        <p style="color:var(--text-muted);font-size:0.875rem;"><?= htmlspecialchars($header['nomor_dokumen'] ?? 'Draft') ?> &mdash; <?= date('d F Y', strtotime($header['tanggal_inspeksi'])) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($user['role'] === 'manajemen' && $header['status'] === 'submitted'): ?>
            <a href="<?= base_url('approval/review.php?id=' . $id) ?>" class="btn btn-primary">
                <i class="fa-solid fa-signature"></i> Review & Setujui
            </a>
        <?php endif; ?>
        <?php if ($header['status'] === 'approved'): ?>
            <a href="<?= base_url('cetak/berita_acara.php?id=' . $id) ?>" target="_blank" class="btn btn-success">
                <i class="fa-solid fa-print"></i> Cetak Berita Acara Resmi
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Info Dokumen -->
<div class="card" style="margin-bottom:1.25rem;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <div>
            <h3 style="font-size:0.875rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:10px;">Informasi Inspeksi</h3>
            <table style="font-size:0.875rem;width:100%;">
                <tr><td style="color:var(--text-muted);width:120px;padding:4px 0;">No. Dokumen</td><td><strong><?= htmlspecialchars($header['nomor_dokumen'] ?? '-') ?></strong></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Jenis Pekerjaan</td><td><?= htmlspecialchars($jenis_label[$header['jenis_pekerjaan']] ?? '-') ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Tanggal Apel</td><td><strong><?= date('d F Y', strtotime($header['tanggal_inspeksi'])) ?></strong></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Regu / Armada</td><td><?= htmlspecialchars($header['nama_regu']) ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Kendaraan</td><td><?= htmlspecialchars($header['kendaraan'] ?? '-') ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Nopol</td><td><strong><?= htmlspecialchars($header['nopol'] ?? '-') ?></strong></td></tr>
            </table>
        </div>
        <div>
            <h3 style="font-size:0.875rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:10px;">Petugas & Pengesahan</h3>
            <table style="font-size:0.875rem;width:100%;">
                <tr><td style="color:var(--text-muted);width:120px;padding:4px 0;">Pelaksana 1</td><td><strong><?= htmlspecialchars($header['nama_pelaksana_1']) ?></strong></td></tr>
                <?php if ($header['nama_pelaksana_2']): ?>
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Pelaksana 2</td><td><?= htmlspecialchars($header['nama_pelaksana_2']) ?></td></tr>
                <?php endif; ?>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Jabatan</td><td><?= htmlspecialchars($header['jabatan_petugas']) ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Status</td><td><span class="badge badge-<?= $header['status'] ?>"><?= strtoupper($header['status']) ?></span></td></tr>
                <?php if ($header['status'] === 'approved' && $header['nama_pejabat_manajemen']): ?>
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Disahkan Oleh</td><td><strong><?= htmlspecialchars($header['nama_pejabat_manajemen']) ?></strong></td></tr>
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Jabatan Atasan</td><td><?= htmlspecialchars($header['jabatan_manajemen'] ?? '-') ?></td></tr>
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Tgl Pengesahan</td><td><?= date('d/m/Y H:i', strtotime($header['tanggal_approval'])) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php if ($header['catatan_manajemen'] && in_array($header['status'], ['approved', 'rejected'])): ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-color);">
            <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">Catatan Manajemen:</div>
            <p style="font-size:0.875rem;padding:8px 12px;border-radius:6px;background:#f8fafc;border-left:3px solid <?= $header['status'] === 'approved' ? '#10b981' : '#ef4444' ?>;">
                <?= nl2br(htmlspecialchars($header['catatan_manajemen'])) ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Tabel Detail -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title" style="font-size:1.05rem;">Daftar Hasil Pemeriksaan (<?= count($details) ?> item)</h2>
    </div>

    <?php foreach ($details_by_cat as $cat_key => $cat_items): ?>
        <?php $k = $kategori_labels[$cat_key] ?? ['label' => ucfirst($cat_key), 'color' => '#666']; ?>
        <div style="margin-bottom:1.25rem;">
            <div style="padding:6px 12px;background:<?= $k['color'] ?>15;border-radius:6px;margin-bottom:6px;border-left:3px solid <?= $k['color'] ?>;">
                <strong style="color:<?= $k['color'] ?>;font-size:0.875rem;"><?= $k['label'] ?></strong>
            </div>
            <div class="table-responsive">
                <table class="data-table" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th width="30px">No</th>
                            <th>Nama Barang</th>
                            <th width="80px" style="text-align:center;">Standar</th>
                            <th width="80px" style="text-align:center;">Realisasi</th>
                            <th width="110px">Kondisi</th>
                            <th>Keterangan</th>
                            <th width="60px">Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cat_items as $i => $d): ?>
                            <tr style="<?= in_array($d['kondisi'], ['rusak','hilang']) ? 'background:#fff5f5;' : ($d['kondisi'] === 'waktu_ganti' ? 'background:#fffbeb;' : '') ?>">
                                <td><?= $i+1 ?></td>
                                <td><strong><?= htmlspecialchars($d['nama_barang_snapshot']) ?></strong></td>
                                <td style="text-align:center;"><?= $d['jumlah_standar'] ?> <?= htmlspecialchars($d['satuan']) ?></td>
                                <td style="text-align:center;font-weight:700;color:<?= $d['jumlah_realisasi'] < $d['jumlah_standar'] ? '#ef4444' : '#10b981' ?>;">
                                    <?= $d['jumlah_realisasi'] ?>
                                </td>
                                <td>
                                    <?php $kl = $kondisi_labels[$d['kondisi']] ?? ['label'=>$d['kondisi'],'class'=>'']; ?>
                                    <span class="badge <?= $kl['class'] ?>"><?= $kl['label'] ?></span>
                                </td>
                                <td style="color:var(--text-muted);font-size:0.8rem;"><?= htmlspecialchars($d['keterangan'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($d['foto_temuan'])): ?>
                                        <a href="<?= base_url('uploads/foto_temuan/' . htmlspecialchars($d['foto_temuan'])) ?>" target="_blank">
                                            <img src="<?= base_url('uploads/foto_temuan/' . htmlspecialchars($d['foto_temuan'])) ?>"
                                                style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:2px solid #ef4444;">
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">–</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
