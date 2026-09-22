<?php
/**
 * Halaman Review & Approval Berita Acara Gelar Alat
 * SIGAP - Role: Manajemen Atasan
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('manajemen');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    set_flash('error', "Parameter tidak valid.");
    header("Location: ../dashboard/index.php"); exit;
}

// Ambil header dokumen
$stmt_h = $pdo->prepare("
    SELECT h.*, r.nama_regu, r.nopol, r.kendaraan, r.jenis_pekerjaan AS regu_jenis,
           u.nama_lengkap AS nama_petugas, u.jabatan AS jabatan_petugas, u.no_hp AS hp_petugas
    FROM gelar_alat_header h
    JOIN regu r ON h.regu_id = r.id
    JOIN users u ON h.petugas_id = u.id
    WHERE h.id = :id LIMIT 1
");
$stmt_h->execute([':id' => $id]);
$header = $stmt_h->fetch();

if (!$header) {
    set_flash('error', "Data laporan tidak ditemukan.");
    header("Location: ../dashboard/index.php"); exit;
}

// Cek akses: hanya bisa review laporan submitted (atau sudah approved untuk lihat)
if (!in_array($header['status'], ['submitted', 'approved', 'rejected'])) {
    set_flash('warning', "Laporan ini masih berstatus draft dan belum bisa ditinjau.");
    header("Location: ../riwayat/detail.php?id=" . $id); exit;
}

// Ambil detail checklist items
$stmt_d = $pdo->prepare("
    SELECT * FROM gelar_alat_detail
    WHERE gelar_alat_id = :id
    ORDER BY kategori_snapshot, id
");
$stmt_d->execute([':id' => $id]);
$all_details = $stmt_d->fetchAll();

// Kelompokkan per kategori
$details_by_cat = [];
foreach ($all_details as $d) {
    $details_by_cat[$d['kategori_snapshot']][] = $d;
}

$kategori_labels = [
    'alat_kerja'          => ['label' => 'Peralatan Kerja', 'icon' => 'fa-toolbox', 'color' => '#0072ce'],
    'k3_safety'           => ['label' => 'APD & Peralatan K3', 'icon' => 'fa-hard-hat', 'color' => '#ef4444'],
    'kendaraan_pendukung' => ['label' => 'Armada & Sarana Pendukung', 'icon' => 'fa-car-side', 'color' => '#f59e0b'],
    'administrasi'        => ['label' => 'Sarana Administrasi', 'icon' => 'fa-briefcase', 'color' => '#8b5cf6'],
];

$kondisi_labels = [
    'baik'       => ['label' => 'Baik', 'class' => 'badge cond-baik'],
    'rusak'      => ['label' => 'Rusak', 'class' => 'badge cond-rusak'],
    'waktu_ganti'=> ['label' => 'Waktu Ganti', 'class' => 'badge cond-waktu_ganti'],
    'ada'        => ['label' => 'Ada', 'class' => 'badge cond-ada'],
    'hilang'     => ['label' => 'Hilang', 'class' => 'badge badge-rejected'],
    'tidak_ada'  => ['label' => 'Tidak Ada', 'class' => 'badge badge-rejected'],
];

$jenis_label = [
    'p2tl'   => 'P2TL - Penertiban Pemakaian Tenaga Listrik',
    'sr_app' => 'SR APP 1 Phasa - Penyambungan & Pembongkaran',
    'yandal' => 'YANDAL & ULC - Pelayanan Gangguan',
    'har'    => 'HAR - Pemeliharaan Jaringan Distribusi',
];

// Hitung statistik temuan
$total_items = count($all_details);
$total_baik = count(array_filter($all_details, fn($d) => $d['kondisi'] === 'baik'));
$total_rusak = count(array_filter($all_details, fn($d) => $d['kondisi'] === 'rusak'));
$total_ganti = count(array_filter($all_details, fn($d) => $d['kondisi'] === 'waktu_ganti'));

$page_title = "Review Laporan Gelar Alat #" . $id;
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:1.5rem;">
    <a href="<?= base_url('dashboard/index.php') ?>" class="btn btn-outline btn-sm" style="margin-bottom:0.75rem;">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
    </a>
    
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;margin-bottom:4px;">
                Review Berita Acara Gelar Alat
                <span class="badge badge-<?= $header['status'] ?>" style="margin-left:8px;font-size:0.8rem;"><?= strtoupper($header['status']) ?></span>
            </h1>
            <p style="color:var(--text-muted);font-size:0.9rem;">
                <?= htmlspecialchars($jenis_label[$header['jenis_pekerjaan']] ?? strtoupper($header['jenis_pekerjaan'])) ?>
                &mdash; <?= htmlspecialchars($header['nama_regu']) ?>
                <?php if ($header['nopol']): ?>(<?= htmlspecialchars($header['nopol']) ?>)<?php endif; ?>
            </p>
        </div>
        <?php if ($header['status'] === 'approved'): ?>
            <a href="<?= base_url('cetak/berita_acara.php?id=' . $id) ?>" target="_blank" class="btn btn-primary">
                <i class="fa-solid fa-print"></i> Cetak Berita Acara Resmi
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
    <div style="background:#e0f2fe;padding:12px;border-radius:var(--radius-md);text-align:center;">
        <div style="font-size:1.5rem;font-weight:800;color:#0369a1;"><?= $total_items ?></div>
        <div style="font-size:0.775rem;color:#0369a1;font-weight:600;">Total Item</div>
    </div>
    <div style="background:#d1fae5;padding:12px;border-radius:var(--radius-md);text-align:center;">
        <div style="font-size:1.5rem;font-weight:800;color:#065f46;"><?= $total_baik ?></div>
        <div style="font-size:0.775rem;color:#065f46;font-weight:600;">Baik / Ada</div>
    </div>
    <div style="background:#fef3c7;padding:12px;border-radius:var(--radius-md);text-align:center;">
        <div style="font-size:1.5rem;font-weight:800;color:#92400e;"><?= $total_ganti ?></div>
        <div style="font-size:0.775rem;color:#92400e;font-weight:600;">Waktu Ganti</div>
    </div>
    <div style="background:#fee2e2;padding:12px;border-radius:var(--radius-md);text-align:center;">
        <div style="font-size:1.5rem;font-weight:800;color:#991b1b;"><?= $total_rusak ?></div>
        <div style="font-size:0.775rem;color:#991b1b;font-weight:600;">Rusak / Hilang</div>
    </div>
</div>

<!-- Info Header Dokumen -->
<div class="card" style="margin-bottom:1.25rem;background:linear-gradient(135deg,#f8fafc,#fff);">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
        <div>
            <h3 style="font-size:0.875rem;color:var(--text-muted);font-weight:600;margin-bottom:10px;text-transform:uppercase;">Informasi Inspeksi</h3>
            <table style="width:100%;font-size:0.875rem;">
                <tr><td style="color:var(--text-muted);padding:3px 0;width:130px;">Nomor Dokumen</td><td><strong><?= htmlspecialchars($header['nomor_dokumen'] ?? '-') ?></strong></td></tr>
                <tr><td style="color:var(--text-muted);padding:3px 0;">Tanggal Apel</td><td><strong><?= date('d F Y', strtotime($header['tanggal_inspeksi'])) ?></strong></td></tr>
                <tr><td style="color:var(--text-muted);padding:3px 0;">Bulan / Tahun</td><td><?= htmlspecialchars($header['bulan_tahun']) ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:3px 0;">Jenis Pekerjaan</td><td><?= strtoupper($header['jenis_pekerjaan']) ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:3px 0;">Regu</td><td><?= htmlspecialchars($header['nama_regu']) ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:3px 0;">Kendaraan / Nopol</td><td><?= htmlspecialchars($header['kendaraan'] ?? '-') ?> / <?= htmlspecialchars($header['nopol'] ?? '-') ?></td></tr>
            </table>
        </div>
        <div>
            <h3 style="font-size:0.875rem;color:var(--text-muted);font-weight:600;margin-bottom:10px;text-transform:uppercase;">Petugas Pemeriksa</h3>
            <table style="width:100%;font-size:0.875rem;">
                <tr><td style="color:var(--text-muted);padding:3px 0;width:130px;">Pelaksana 1</td><td><strong><?= htmlspecialchars($header['nama_pelaksana_1']) ?></strong></td></tr>
                <?php if ($header['nama_pelaksana_2']): ?>
                    <tr><td style="color:var(--text-muted);padding:3px 0;">Pelaksana 2</td><td><?= htmlspecialchars($header['nama_pelaksana_2']) ?></td></tr>
                <?php endif; ?>
                <tr><td style="color:var(--text-muted);padding:3px 0;">Jabatan</td><td><?= htmlspecialchars($header['jabatan_petugas']) ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:3px 0;">Status Laporan</td><td><span class="badge badge-<?= $header['status'] ?>"><?= strtoupper($header['status']) ?></span></td></tr>
                <tr><td style="color:var(--text-muted);padding:3px 0;">Waktu Submit</td><td><?= date('d/m/Y H:i', strtotime($header['created_at'])) ?></td></tr>
            </table>
            <?php if (!empty($header['ttd_petugas'])): ?>
                <div style="margin-top:12px;">
                    <div style="font-size:0.75rem;color:var(--text-muted);font-weight:600;margin-bottom:4px;">Tanda Tangan Petugas Pemeriksa:</div>
                    <img src="<?= htmlspecialchars($header['ttd_petugas']) ?>" style="max-width:180px;border:1px solid var(--border-color);border-radius:6px;background:white;padding:4px;">
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($header['catatan_umum'])): ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-color);">
            <div style="font-size:0.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;margin-bottom:4px;">Catatan Umum Petugas:</div>
            <p style="font-size:0.875rem;background:#f8fafc;padding:8px 12px;border-radius:var(--radius-sm);border-left:3px solid var(--primary);">
                <?= nl2br(htmlspecialchars($header['catatan_umum'])) ?>
            </p>
        </div>
    <?php endif; ?>
    <?php if (!empty($header['foto_kegiatan'])): ?>
        <div style="margin-top:12px;">
            <div style="font-size:0.75rem;color:var(--text-muted);font-weight:600;margin-bottom:6px;">Foto Dokumentasi Gelar Alat:</div>
            <img src="<?= base_url('uploads/foto_kegiatan/' . htmlspecialchars($header['foto_kegiatan'])) ?>" 
                 style="max-width:200px;border-radius:var(--radius-md);border:1px solid var(--border-color);">
        </div>
    <?php endif; ?>
</div>

<!-- ===== Detail Checklist Alat ===== -->
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h2 class="card-title" style="font-size:1.05rem;">
            <i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:6px;"></i> Hasil Pemeriksaan Peralatan
        </h2>
    </div>

    <?php foreach ($details_by_cat as $cat_key => $cat_items): ?>
        <?php $k = $kategori_labels[$cat_key] ?? ['label' => ucfirst($cat_key), 'icon' => 'fa-box', 'color' => '#666']; ?>
        <div style="margin-bottom:1.25rem;">
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:<?= $k['color'] ?>15;border-radius:var(--radius-sm);margin-bottom:8px;border-left:3px solid <?= $k['color'] ?>;">
                <i class="fa-solid <?= $k['icon'] ?>" style="color:<?= $k['color'] ?>;"></i>
                <strong style="color:<?= $k['color'] ?>;font-size:0.9rem;"><?= $k['label'] ?></strong>
                <span style="font-size:0.775rem;color:var(--text-muted);">(<?= count($cat_items) ?> item)</span>
            </div>
            <div class="table-responsive">
                <table class="data-table" style="font-size:0.875rem;">
                    <thead>
                        <tr>
                            <th width="30px">No</th>
                            <th>Nama Barang / Alat</th>
                            <th width="80px" style="text-align:center;">Standar</th>
                            <th width="80px" style="text-align:center;">Realisasi</th>
                            <th width="110px">Kondisi</th>
                            <th>Keterangan</th>
                            <th width="70px">Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cat_items as $idx => $d): ?>
                            <tr style="<?= in_array($d['kondisi'], ['rusak','hilang','tidak_ada']) ? 'background:#fff5f5;' : ($d['kondisi'] === 'waktu_ganti' ? 'background:#fffbeb;' : '') ?>">
                                <td><?= $idx + 1 ?></td>
                                <td><strong><?= htmlspecialchars($d['nama_barang_snapshot']) ?></strong></td>
                                <td style="text-align:center;"><?= $d['jumlah_standar'] ?> <?= htmlspecialchars($d['satuan']) ?></td>
                                <td style="text-align:center;">
                                    <span style="font-weight:700;color:<?= $d['jumlah_realisasi'] < $d['jumlah_standar'] ? '#ef4444' : '#10b981' ?>;">
                                        <?= $d['jumlah_realisasi'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $kl = $kondisi_labels[$d['kondisi']] ?? ['label' => $d['kondisi'], 'class' => 'badge']; ?>
                                    <span class="<?= $kl['class'] ?>"><?= $kl['label'] ?></span>
                                </td>
                                <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($d['keterangan'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($d['foto_temuan'])): ?>
                                        <a href="<?= base_url('uploads/foto_temuan/' . htmlspecialchars($d['foto_temuan'])) ?>" target="_blank">
                                            <img src="<?= base_url('uploads/foto_temuan/' . htmlspecialchars($d['foto_temuan'])) ?>"
                                                style="width:44px;height:44px;object-fit:cover;border-radius:4px;border:2px solid #ef4444;">
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:0.75rem;">–</span>
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

<?php if ($header['status'] === 'submitted'): ?>
<!-- ===== FORM APPROVAL / REJECT ===== -->
<div class="card" style="border: 2px solid var(--navy); border-radius: var(--radius-lg);">
    <div class="card-header">
        <h2 class="card-title" style="font-size:1.1rem;">
            <i class="fa-solid fa-signature" style="color:var(--navy);margin-right:6px;"></i> Keputusan & Tanda Tangan Manajemen Atasan
        </h2>
    </div>

    <form method="POST" action="proses.php" id="formApproval">
        <input type="hidden" name="gelar_alat_id" value="<?= $id ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Nama Pejabat Penandatangan <span style="color:var(--danger);">*</span></label>
                <input type="text" name="nama_pejabat" class="form-control" 
                    value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Jabatan Penandatangan <span style="color:var(--danger);">*</span></label>
                <input type="text" name="jabatan_pejabat" class="form-control"
                    value="<?= htmlspecialchars($user['jabatan']) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Catatan Evaluasi K3 / Arahan Manajemen</label>
            <textarea name="catatan_manajemen" class="form-control" rows="3"
                placeholder="Isi catatan evaluasi, arahan perbaikan, atau persetujuan terhadap hasil inspeksi gelar alat..."></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Tanda Tangan Digital Manajemen Atasan <span style="color:var(--danger);">*</span></label>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;">Tanda tangani di bawah ini sebagai bukti pengesahan Berita Acara Gelar Alat.</p>
            <div class="signature-wrapper">
                <canvas class="signature-canvas" id="canvasManajemen"></canvas>
            </div>
            <div class="signature-actions">
                <button type="button" id="clearManajemen" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-eraser"></i> Hapus Ulang
                </button>
            </div>
            <input type="hidden" name="ttd_manajemen" id="ttdManajemen">
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:1rem;border-top:1px solid var(--border-color);">
            <button type="submit" name="action" value="reject" class="btn btn-danger">
                <i class="fa-solid fa-times-circle"></i> Tolak & Kembalikan untuk Revisi
            </button>
            <button type="submit" name="action" value="approve" id="btnApprove" class="btn btn-success btn-lg">
                <i class="fa-solid fa-check-circle"></i> Setujui & Sahkan Berita Acara
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('canvasManajemen');
    const clearBtn = document.getElementById('clearManajemen');
    const hiddenInput = document.getElementById('ttdManajemen');
    window.sigPadMgmt = new SimpleSignaturePad(canvas, clearBtn, hiddenInput);

    document.getElementById('formApproval').addEventListener('submit', function(e) {
        const action = document.activeElement.value;
        if (action === 'approve') {
            if (window.sigPadMgmt && window.sigPadMgmt.isEmpty()) {
                e.preventDefault();
                alert('Tanda tangan digital Manajemen Atasan belum diisi.\nSilakan tanda tangan pada kolom yang tersedia.');
                return;
            }
        }
        const btn = document.getElementById('btnApprove');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
        btn.disabled = true;
    });
});
</script>

<?php elseif ($header['status'] === 'approved' && !empty($header['ttd_manajemen'])): ?>
<!-- Tampilkan TTD Manajemen jika sudah approved -->
<div class="card" style="border:1px solid #a7f3d0;background:#f0fdf4;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:1rem;">
        <div style="width:42px;height:42px;background:#10b981;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div style="font-weight:700;color:#065f46;font-size:1.05rem;">Berita Acara Telah Disahkan</div>
            <div style="font-size:0.8rem;color:#059669;"><?= date('d F Y, H:i', strtotime($header['tanggal_approval'] ?? $header['updated_at'])) ?></div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div>
            <div style="font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px;">Disahkan Oleh:</div>
            <div style="font-weight:700;"><?= htmlspecialchars($header['nama_pejabat_manajemen'] ?? '-') ?></div>
            <div style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($header['jabatan_manajemen'] ?? '-') ?></div>
            <?php if ($header['catatan_manajemen']): ?>
                <div style="margin-top:8px;font-size:0.825rem;padding:8px;background:rgba(255,255,255,0.6);border-radius:6px;border-left:3px solid #10b981;">
                    <?= nl2br(htmlspecialchars($header['catatan_manajemen'])) ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <div style="font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px;">Tanda Tangan:</div>
            <img src="<?= htmlspecialchars($header['ttd_manajemen']) ?>" style="max-width:200px;border:1px solid #a7f3d0;border-radius:6px;background:white;padding:4px;">
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
