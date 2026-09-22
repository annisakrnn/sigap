<?php
/**
 * Riwayat Semua Berita Acara Gelar Alat
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();

// Filter params
$filter_status  = $_GET['status'] ?? '';
$filter_jenis   = $_GET['jenis'] ?? '';
$filter_bulan   = $_GET['bulan'] ?? '';

// Query dengan filter
$where = [];
$params = [];

if ($user['role'] === 'petugas') {
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
if (!empty($filter_bulan)) {
    $where[] = "DATE_FORMAT(h.tanggal_inspeksi, '%Y-%m') = :bulan";
    $params[':bulan'] = $filter_bulan;
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

<div style="margin-bottom:1.75rem; display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:4px; color:var(--text-main);">Riwayat Berita Acara Gelar Alat</h1>
        <p style="color:var(--text-muted); font-size:0.9rem;">
            <?= $user['role'] === 'petugas' ? 'Daftar dokumen gelar alat yang pernah Anda laksanakan.' : 'Rekap seluruh laporan gelar alat dari seluruh regu & unit.' ?>
        </p>
    </div>
    <?php if ($user['role'] === 'petugas'): ?>
        <a href="<?= base_url('inspeksi/index.php') ?>" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Gelar Alat Baru
        </a>
    <?php endif; ?>
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
            <label class="form-label" style="font-size:0.8rem;">Bulan / Tahun</label>
            <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($filter_bulan) ?>"
                style="font-size:0.85rem; padding:6px 12px; min-width:140px;">
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
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-folder-open" style="color:var(--primary);"></i>
            Daftar Berita Acara
            <span class="badge badge-submitted" style="font-size:0.7rem; margin-left:6px;"><?= count($rows) ?> dokumen</span>
        </h2>
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
                        <th>Petugas</th>
                        <th>Temuan Fisik</th>
                        <th>Status</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row): ?>
                        <tr>
                            <td style="color:var(--text-muted); font-size:0.8rem;"><?= $i + 1 ?></td>
                            <td style="font-family:monospace; font-size:0.8rem; font-weight:600; color:var(--primary);">
                                <?= htmlspecialchars($row['nomor_dokumen'] ?? '-') ?>
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
                            <td style="font-size:0.85rem; color:var(--text-main); font-weight:500;"><?= htmlspecialchars($row['nama_petugas']) ?></td>
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
                                <?php if ($user['role'] === 'manajemen' && $row['status'] === 'submitted'): ?>
                                    <a href="<?= base_url('approval/review.php?id=' . $row['id']) ?>" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-signature"></i> Setujui
                                    </a>
                                <?php else: ?>
                                    <a href="<?= base_url('riwayat/detail.php?id=' . $row['id']) ?>" class="btn btn-outline btn-sm">
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </a>
                                <?php endif; ?>
                                <?php if ($row['status'] === 'approved'): ?>
                                    <a href="<?= base_url('cetak/berita_acara.php?id=' . $row['id']) ?>" target="_blank" class="btn btn-outline btn-sm" title="Cetak PDF">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
