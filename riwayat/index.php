<?php
/**
 * Riwayat Semua Berita Acara Gelar Alat
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();

// Filter params
$filter_status  = isset($_GET['status']) ? $_GET['status'] : '';
$filter_jenis   = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$filter_dari    = isset($_GET['tgl_dari']) ? $_GET['tgl_dari'] : '';
$filter_sampai  = isset($_GET['tgl_sampai']) ? $_GET['tgl_sampai'] : '';

// Query dengan filter
$where = [];
$params = [];

if ($user && $user['role'] === 'petugas') {
    $where[] = "h.petugas_id = :pid";
    $params[':pid'] = $user['id'];
}
if (!empty($filter_status) && in_array($filter_status, ['draft','submitted','approved','rejected'])) {
    $where[] = "h.status = :status";
    $params[':status'] = $filter_status;
}
if (!empty($filter_jenis) && in_array($filter_jenis, ['p2tl','sr_app','yandal','har'])) {
    $where[] = "h.jenis_pekerjaan = :jenis";
    $params[':jenis'] = $filter_jenis;
}
if (!empty($filter_dari)) {
    $where[] = "h.tanggal_inspeksi >= :tgl_dari";
    $params[':tgl_dari'] = $filter_dari;
}
if (!empty($filter_sampai)) {
    $where[] = "h.tanggal_inspeksi <= :tgl_sampai";
    $params[':tgl_sampai'] = $filter_sampai;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT h.*, r.nama_regu, r.nopol, u.nama_lengkap AS nama_petugas,
        (SELECT COUNT(*) FROM gelar_alat_detail d WHERE d.gelar_alat_id = h.id AND d.kondisi IN ('rusak','waktu_ganti')) AS jml_temuan
    FROM gelar_alat_header h
    JOIN regu r ON h.regu_id = r.id
    JOIN users u ON h.petugas_id = u.id
    {$where_sql}
    ORDER BY h.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$page_title = "Riwayat & Arsip Berita Acara";
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:1.5rem;">
    <a href="<?= base_url('inspeksi/index.php') ?>" class="btn btn-outline btn-sm" style="margin-bottom:12px; display:inline-flex; align-items:center; gap:6px;">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Form Gelar Alat
    </a>
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
        <div>
            <h1 style="font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:4px; color:var(--text-main);">Riwayat Berita Acara Gelar Alat</h1>
            <p style="color:var(--text-muted); font-size:0.9rem;">
                <?= ($user && $user['role'] === 'petugas') ? 'Daftar dokumen gelar alat yang pernah Anda laksanakan.' : 'Rekap seluruh laporan gelar alat dari seluruh regu & unit.' ?>
            </p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="<?= base_url('inspeksi/index.php') ?>" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-plus"></i> Gelar Alat Baru
            </a>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card" style="margin-bottom:1.5rem; padding:1.25rem 1.5rem;">
    <form method="GET" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
        <div>
            <label class="form-label" style="font-size:0.8rem;">Status Laporan</label>
            <select name="status" class="form-control" style="font-size:0.85rem; padding:6px 12px; min-width:140px;">
                <option value="">Semua Status</option>
                <option value="submitted" <?= $filter_status === 'submitted' ? 'selected' : '' ?>>Menunggu Approval</option>
                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Sudah Disetujui</option>
                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Dikembalikan</option>
                <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size:0.8rem;">Jenis Pekerjaan</label>
            <select name="jenis" class="form-control" style="font-size:0.85rem; padding:6px 12px; min-width:150px;">
                <option value="">Semua Jenis</option>
                <option value="p2tl" <?= $filter_jenis === 'p2tl' ? 'selected' : '' ?>>P2TL</option>
                <option value="sr_app" <?= $filter_jenis === 'sr_app' ? 'selected' : '' ?>>SR APP 1 Phasa</option>
                <option value="yandal" <?= $filter_jenis === 'yandal' ? 'selected' : '' ?>>YANDAL & ULC</option>
                <option value="har" <?= $filter_jenis === 'har' ? 'selected' : '' ?>>HAR - Pemeliharaan</option>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size:0.8rem;">Dari Tanggal</label>
            <input type="date" name="tgl_dari" class="form-control" value="<?= htmlspecialchars($filter_dari) ?>"
                style="font-size:0.85rem; padding:6px 12px;">
        </div>
        <div>
            <label class="form-label" style="font-size:0.8rem;">Sampai Tanggal</label>
            <input type="date" name="tgl_sampai" class="form-control" value="<?= htmlspecialchars($filter_sampai) ?>"
                style="font-size:0.85rem; padding:6px 12px;">
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:7px 16px; font-size:0.85rem;">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <a href="index.php" class="btn btn-outline" style="padding:7px 16px; font-size:0.85rem;">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Tabel Data -->
<div class="card">
    <div class="card-header" style="flex-wrap:wrap; gap:10px;">
        <h2 class="card-title">
            <i class="fa-solid fa-folder-open" style="color:var(--primary);"></i>
            Daftar Berita Acara
            <span class="badge badge-submitted" id="docCountBadge" style="font-size:0.7rem; margin-left:6px;"><?= count($rows) ?> dokumen</span>
        </h2>
        <!-- Live Instant Search -->
        <div style="min-width:240px; position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.8rem;"></i>
            <input type="text" id="liveSearchInput" oninput="filterRiwayatTable()" placeholder="Ketik untuk cari cepat..." class="form-control" style="padding-left:30px; font-size:0.825rem; height:34px;">
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
            <div style="width:48px; height:48px; border-radius:50%; background:#f1f5f9; color:#64748b; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.3rem;">
                <i class="fa-regular fa-folder-open"></i>
            </div>
            <strong style="color:var(--text-main);">Tidak Ada Dokumen</strong>
            <p style="font-size:0.825rem; margin-top:2px;">Coba ubah filter atau lakukan inspeksi baru.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="30px">#</th>
                        <th>No. Dokumen</th>
                        <th>Tanggal Apel</th>
                        <th>Jenis</th>
                        <th>Regu & Armada</th>
                        <th>Petugas / Pelaksana</th>
                        <th>Temuan Fisik</th>
                        <th>Status</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row): ?>
                        <?php 
                        $nama_pemeriksa = !empty($row['nama_pelaksana_1']) ? $row['nama_pelaksana_1'] : $row['nama_petugas'];
                        $search_keywords = strtolower(
                            (isset($row['nomor_dokumen']) ? $row['nomor_dokumen'] : '') . ' ' .
                            $row['nama_regu'] . ' ' .
                            (isset($row['nopol']) ? $row['nopol'] : '') . ' ' .
                            $nama_pemeriksa . ' ' .
                            (isset($row['nama_pelaksana_2']) ? $row['nama_pelaksana_2'] : '') . ' ' .
                            $row['jenis_pekerjaan'] . ' ' .
                            $row['status']
                        );
                        ?>
                        <tr class="row-riwayat" data-search="<?= htmlspecialchars($search_keywords) ?>">
                            <td style="color:var(--text-muted); font-size:0.8rem;"><?= $i + 1 ?></td>
                            <td style="font-family:monospace; font-size:0.8rem; font-weight:600; color:var(--primary);">
                                <?= htmlspecialchars(isset($row['nomor_dokumen']) ? $row['nomor_dokumen'] : '-') ?>
                            </td>
                            <td><strong style="color:var(--text-main);"><?= date('d/m/Y', strtotime($row['tanggal_inspeksi'])) ?></strong></td>
                            <td>
                                <span class="badge badge-submitted" style="font-size:0.68rem;">
                                    <?= strtoupper($row['jenis_pekerjaan']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--text-main); font-size:0.875rem;"><?= htmlspecialchars($row['nama_regu']) ?></div>
                                <?php if ($row['nopol']): ?>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($row['nopol']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--text-main); font-size:0.875rem;">
                                    <?= htmlspecialchars($nama_pemeriksa) ?>
                                </div>
                                <?php if (!empty($row['nama_pelaksana_2'])): ?>
                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                        <i class="fa-solid fa-user-group" style="font-size:0.7rem;margin-right:3px;"></i><?= htmlspecialchars($row['nama_pelaksana_2']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['jml_temuan'] > 0): ?>
                                    <span class="badge badge-rejected" style="font-size:0.68rem;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> <?= $row['jml_temuan'] ?> temuan
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-approved" style="font-size:0.68rem;">
                                        <i class="fa-solid fa-check"></i> Aman
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $row['status'] ?>" style="font-size:0.68rem;"><?= strtoupper($row['status']) ?></span>
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <div style="display:inline-flex; gap:6px;">
                                    <?php if ($user && $user['role'] === 'manajemen' && $row['status'] === 'submitted'): ?>
                                        <a href="<?= base_url('approval/review.php?id=' . $row['id']) ?>" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-signature"></i> Review
                                        </a>
                                    <?php elseif (in_array($row['status'], ['rejected', 'draft'])): ?>
                                        <a href="<?= base_url('inspeksi/form.php?edit_id=' . $row['id']) ?>" class="btn btn-warning btn-sm" title="Revisi / Edit Data">
                                            <i class="fa-solid fa-pen-to-square"></i> <?= $row['status'] === 'rejected' ? 'Revisi' : 'Lanjut' ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($user && ($user['role'] === 'manajemen' || $row['petugas_id'] == $user['id'])): ?>
                                        <a href="<?= base_url('riwayat/hapus.php?id=' . $row['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus dokumen ini secara permanen?');" title="Hapus Dokumen">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= base_url('riwayat/detail.php?id=' . $row['id']) ?>" class="btn btn-outline btn-sm">
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </a>
                                    <?php if ($row['status'] === 'approved'): ?>
                                        <a href="<?= base_url('cetak/berita_acara.php?id=' . $row['id']) ?>" target="_blank" class="btn btn-outline btn-sm" title="Cetak Berita Acara PDF">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function filterRiwayatTable() {
    const query = document.getElementById('liveSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.row-riwayat');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.dataset.search || '';
        if (!query || text.includes(query)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const badge = document.getElementById('docCountBadge');
    if (badge) {
        badge.textContent = visibleCount + ' dokumen';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
