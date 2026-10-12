<?php
/**
 * Detail Berita Acara Gelar Alat
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();
$id = intval(isset($_GET['id']) ? $_GET['id'] : 0);

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
    header("Location: " . base_url('inspeksi/index.php')); exit;
}

// Hanya jika user terautentikasi sebagai petugas lain yang dibatasi
if ($user && $user['role'] === 'petugas' && $header['petugas_id'] != $user['id']) {
    set_flash('error', "Akses ditolak. Anda hanya dapat melihat laporan milik Anda sendiri.");
    header("Location: " . base_url('inspeksi/index.php')); exit;
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
        <a href="<?= base_url('inspeksi/index.php') ?>" class="btn btn-outline btn-sm" style="margin-bottom:8px;">
            <i class="fa-solid fa-arrow-left"></i> Form Gelar Alat
        </a>
        <h1 style="font-size:1.5rem;margin-bottom:4px;">
            Detail Berita Acara Gelar Alat
            <span class="badge badge-<?= $header['status'] ?>" style="margin-left:8px;font-size:0.8rem;"><?= strtoupper($header['status']) ?></span>
        </h1>
        <p style="color:var(--text-muted);font-size:0.875rem;"><?= htmlspecialchars(isset($header['nomor_dokumen']) ? $header['nomor_dokumen'] : 'Draft') ?> &mdash; <?= date('d F Y', strtotime($header['tanggal_inspeksi'])) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($user && $user['role'] === 'manajemen' && $header['status'] === 'submitted'): ?>
            <a href="<?= base_url('approval/review.php?id=' . $id) ?>" class="btn btn-primary">
                <i class="fa-solid fa-signature"></i> Review & Berikan Persetujuan
            </a>
        <?php endif; ?>
        <?php if ((!$user || $user['role'] === 'petugas') && in_array($header['status'], ['rejected', 'draft'])): ?>
            <a href="<?= base_url('inspeksi/form.php?edit_id=' . $id) ?>" class="btn btn-warning">
                <i class="fa-solid fa-pen-to-square"></i> <?= $header['status'] === 'rejected' ? 'Revisi / Perbaiki Laporan Ini' : 'Lanjutkan Isi Draft' ?>
            </a>
        <?php endif; ?>
        <?php if ($header['status'] === 'approved'): ?>
            <a href="<?= base_url('cetak/berita_acara.php?id=' . $id) ?>" target="_blank" class="btn btn-success">
                <i class="fa-solid fa-print"></i> Cetak Berita Acara Resmi (PDF)
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($header['status'] === 'rejected'): ?>
    <div class="card" style="border:2px solid #ef4444; background:#fef2f2; margin-bottom:1.25rem; padding:1.25rem 1.5rem;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
            <div style="width:42px; height:42px; border-radius:50%; background:#ef4444; color:white; display:flex; align-items:center; justify-content:center; font-size:1.25rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 style="font-size:1.05rem; font-weight:700; color:#991b1b; margin:0;">
                    Laporan Perlu Perbaikan (Dikembalikan oleh Manajemen)
                </h3>
                <p style="font-size:0.825rem; color:#b91c1c; margin:2px 0 0;">
                    Atasan telah mengembalikan dokumen ini dengan catatan perbaikan di bawah.
                </p>
            </div>
        </div>
        <div style="background:#fff; border-radius:6px; padding:10px 14px; border-left:4px solid #ef4444; margin:10px 0;">
            <strong style="font-size:0.8rem; text-transform:uppercase; color:#7f1d1d;">Catatan Arahan Manajemen:</strong>
            <p style="margin:4px 0 0; font-size:0.9rem; color:#1e293b;">
                <?= nl2br(htmlspecialchars(isset($header['catatan_manajemen']) ? $header['catatan_manajemen'] : 'Tidak ada catatan khusus.')) ?>
            </p>
        </div>
        <?php if (!$user || $user['role'] === 'petugas'): ?>
            <div style="text-align:right; margin-top:10px;">
                <a href="<?= base_url('inspeksi/form.php?edit_id=' . $id) ?>" class="btn btn-primary" style="background:#dc2626; border-color:#dc2626;">
                    <i class="fa-solid fa-pen-to-square"></i> Buka Form untuk Revisi Sekarang &rarr;
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Info Dokumen -->
<div class="card" style="margin-bottom:1.25rem;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <div>
            <h3 style="font-size:0.875rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:10px;">Informasi Inspeksi</h3>
            <table style="font-size:0.875rem;width:100%;">
                <tr><td style="color:var(--text-muted);width:120px;padding:4px 0;">No. Dokumen</td><td><strong><?= htmlspecialchars(isset($header['nomor_dokumen']) ? $header['nomor_dokumen'] : '-') ?></strong></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Jenis Pekerjaan</td><td><?= htmlspecialchars(isset($jenis_label[$header['jenis_pekerjaan']]) ? $jenis_label[$header['jenis_pekerjaan']] : '-') ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Tanggal Apel</td><td><strong><?= date('d F Y', strtotime($header['tanggal_inspeksi'])) ?></strong></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Regu / Armada</td><td><?= htmlspecialchars($header['nama_regu']) ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Kendaraan</td><td><?= htmlspecialchars(isset($header['kendaraan']) ? $header['kendaraan'] : '-') ?></td></tr>
                <tr><td style="color:var(--text-muted);padding:4px 0;">Nopol</td><td><strong><?= htmlspecialchars(isset($header['nopol']) ? $header['nopol'] : '-') ?></strong></td></tr>
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
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Jabatan Atasan</td><td><?= htmlspecialchars(isset($header['jabatan_manajemen']) ? $header['jabatan_manajemen'] : '-') ?></td></tr>
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
        <?php $k = isset($kategori_labels[$cat_key]) ? $kategori_labels[$cat_key] : ['label' => ucfirst($cat_key), 'color' => '#666']; ?>
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
                            <th width="60px">Foto Temuan</th>
                            <th width="140px">Status Tindak Lanjut</th>
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
                                    <?php $kl = isset($kondisi_labels[$d['kondisi']]) ? $kondisi_labels[$d['kondisi']] : ['label'=>$d['kondisi'],'class'=>'']; ?>
                                    <span class="badge <?= $kl['class'] ?>"><?= $kl['label'] ?></span>
                                </td>
                                <?php
                                    $ket = isset($d['keterangan']) ? $d['keterangan'] : '-';
                                    if (strpos($ket, '<br /><b>Deprecated</b>') !== false) {
                                        $ket = '-';
                                    }
                                ?>
                                <td style="color:var(--text-muted);font-size:0.8rem;"><?= htmlspecialchars($ket) ?></td>
                                <td>
                                    <?php if (!empty($d['foto_temuan'])): ?>
                                        <a href="<?= base_url('uploads/foto_temuan/' . htmlspecialchars($d['foto_temuan'])) ?>" target="_blank">
                                            <img src="<?= base_url('uploads/foto_temuan/' . htmlspecialchars($d['foto_temuan'])) ?>"
                                                style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:2px solid #ef4444;" title="Lihat Foto Temuan">
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">–</span>
                                    <?php endif; ?>
                                </td>
                                <td id="col-status-<?= $d['id'] ?>">
                                    <?php if (in_array($d['kondisi'], ['rusak', 'waktu_ganti'])): ?>
                                        <?php if (!empty($d['sudah_diperbaiki'])): ?>
                                            <span class="badge badge-approved" style="font-size:0.68rem;">
                                                <i class="fa-solid fa-check"></i> Selesai
                                            </span>
                                            <?php if (!empty($d['tgl_diperbaiki'])): ?>
                                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">
                                                    <?= date('d/m/Y', strtotime($d['tgl_diperbaiki'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($d['catatan_perbaikan'])): ?>
                                                <div style="font-size:0.75rem; color:#15803d; font-style:italic; margin-top:2px;">
                                                    &ldquo;<?= htmlspecialchars($d['catatan_perbaikan']) ?>&rdquo;
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($d['foto_perbaikan'])): ?>
                                                <a href="<?= base_url('uploads/perbaikan/' . htmlspecialchars($d['foto_perbaikan'])) ?>" target="_blank" style="color:#047857; text-decoration:underline; font-size:0.725rem; font-weight:600; display:inline-flex; align-items:center; gap:3px; margin-top:3px;">
                                                    <i class="fa-solid fa-camera"></i> Foto Bukti
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-rejected" style="font-size:0.68rem;">
                                                Belum Selesai
                                            </span>
                                            <?php if ($user['role'] === 'manajemen'): ?>
                                                <div style="margin-top:4px;">
                                                    <button type="button" class="btn btn-sm btn-outline" style="font-size:0.7rem; padding:2px 8px;" onclick="showDoneModal(<?= $d['id'] ?>, '<?= addslashes(htmlspecialchars($d['nama_barang_snapshot'])) ?>')">
                                                        <i class="fa-solid fa-check"></i> Tindak Lanjut
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.75rem;">–</span>
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

<?php if ($user['role'] === 'manajemen'): ?>
<!-- Modal Selesai Diperbaiki -->
<div id="doneModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:var(--radius-xl); padding:2rem; max-width:440px; width:90%; box-shadow:var(--shadow-xl);">
        <div style="text-align:center; margin-bottom:1.25rem;">
            <div style="width:50px; height:50px; background:#f0fdf4; border-radius:var(--radius-lg); display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.4rem; color:#16a34a;">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
            <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-main); margin-bottom:4px;">Tindak Lanjut Perbaikan Alat</h3>
            <p style="font-size:0.85rem; color:var(--text-muted);">Alat: <strong id="doneItemName" style="color:var(--text-main);"></strong></p>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="form-label" for="catatanPerbaikan">Catatan Tindakan Perbaikan</label>
            <textarea id="catatanPerbaikan" class="form-control" rows="2" placeholder="Contoh: Diganti unit baru dari gudang..."></textarea>
        </div>

        <div class="form-group" style="margin-bottom:1.25rem;">
            <label class="form-label">Foto Bukti Perbaikan / Penggantian</label>
            <input type="file" id="fotoPerbaikanInput" class="form-control" accept="image/*">
        </div>

        <div style="display:flex; gap:10px;">
            <button onclick="closeDoneModal()" class="btn btn-outline" style="flex:1;">Batal</button>
            <button onclick="submitDone()" class="btn btn-success" style="flex:1;" id="btnSubmitDone">
                <i class="fa-solid fa-check"></i> Simpan Selesai
            </button>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/image-compress.js') ?>"></script>
<script>
let _doneDetailId = null;

function showDoneModal(detailId, namaBarang) {
    _doneDetailId = detailId;
    document.getElementById('doneItemName').textContent = namaBarang;
    document.getElementById('catatanPerbaikan').value = '';
    document.getElementById('fotoPerbaikanInput').value = '';
    document.getElementById('doneModal').style.display = 'flex';
}

function closeDoneModal() {
    document.getElementById('doneModal').style.display = 'none';
    _doneDetailId = null;
}

async function submitDone() {
    if (!_doneDetailId) return;
    const btn = document.getElementById('btnSubmitDone');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

    const catatan = document.getElementById('catatanPerbaikan').value.trim();
    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');
    formData.append('detail_id', _doneDetailId);
    formData.append('catatan_perbaikan', catatan);

    const fileInput = document.getElementById('fotoPerbaikanInput');
    if (fileInput && fileInput.files[0]) {
        try {
            const comp = await window.ImageCompressor.compress(fileInput.files[0], { maxDimension: 1280, quality: 0.8 });
            formData.append('foto_perbaikan', comp);
        } catch(e) {
            formData.append('foto_perbaikan', fileInput.files[0]);
        }
    }

    try {
        const resp = await fetch('<?= base_url('dashboard/mark_done.php') ?>', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (data.ok) {
            closeDoneModal();
            const col = document.getElementById('col-status-' + _doneDetailId);
            if (col) {
                let html = '<span class="badge badge-approved" style="font-size:0.68rem;"><i class="fa-solid fa-check"></i> Selesai</span>';
                if (catatan) html += `<div style="font-size:0.75rem; color:#15803d; font-style:italic; margin-top:2px;">&ldquo;${catatan}&rdquo;</div>`;
                if (data.foto_perbaikan) html += `<a href="${data.foto_perbaikan}" target="_blank" style="color:#047857; text-decoration:underline; font-size:0.725rem; font-weight:600; display:inline-flex; align-items:center; gap:3px; margin-top:3px;"><i class="fa-solid fa-camera"></i> Foto Bukti</a>`;
                col.innerHTML = html;
            }
            alert('Berhasil: ' + data.pesan);
        } else {
            alert('Peringatan: ' + data.pesan);
        }
    } catch(e) {
        alert('Gagal terhubung ke server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Simpan Selesai';
    }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
