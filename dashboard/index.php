<?php
/**
 * Dashboard Monitoring Manajemen Atasan
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('manajemen');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();

// ===== KPI Stats =====
$bulan_ini = date('Y-m');

// Total gelar alat bulan ini
$stmt = $pdo->prepare("SELECT COUNT(*) FROM gelar_alat_header WHERE DATE_FORMAT(tanggal_inspeksi, '%Y-%m') = :bln");
$stmt->execute([':bln' => $bulan_ini]);
$total_bulan = $stmt->fetchColumn();

// Menunggu approval (submitted)
$stmt2 = $pdo->query("SELECT COUNT(*) FROM gelar_alat_header WHERE status = 'submitted'");
$pending = $stmt2->fetchColumn();

// Sudah disetujui bulan ini
$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM gelar_alat_header WHERE status = 'approved' AND DATE_FORMAT(tanggal_inspeksi, '%Y-%m') = :bln");
$stmt3->execute([':bln' => $bulan_ini]);
$approved_bln = $stmt3->fetchColumn();

// Total alat rusak/perlu ganti (temuan bulan ini)
$stmt4 = $pdo->prepare("
    SELECT COUNT(*) FROM gelar_alat_detail d
    JOIN gelar_alat_header h ON d.gelar_alat_id = h.id
    WHERE d.kondisi IN ('rusak','waktu_ganti')
    AND DATE_FORMAT(h.tanggal_inspeksi, '%Y-%m') = :bln
");
$stmt4->execute([':bln' => $bulan_ini]);
$total_temuan = $stmt4->fetchColumn();

// ===== Daftar Antrean Persetujuan (Submitted) =====
$stmt_pending = $pdo->query("
    SELECT h.*, r.nama_regu, r.nopol, r.kendaraan, u.nama_lengkap AS nama_petugas, u.jabatan AS jabatan_petugas,
        (SELECT COUNT(*) FROM gelar_alat_detail d WHERE d.gelar_alat_id = h.id AND d.kondisi IN ('rusak','waktu_ganti')) AS jumlah_temuan
    FROM gelar_alat_header h
    JOIN regu r ON h.regu_id = r.id
    JOIN users u ON h.petugas_id = u.id
    WHERE h.status = 'submitted'
    ORDER BY h.created_at ASC
");
$pending_list = $stmt_pending->fetchAll();

// ===== Red Flag: Alat Rusak Terbaru (belum diperbaiki) =====
$stmt_rf = $pdo->query("
    SELECT d.id AS detail_id, d.nama_barang_snapshot, d.kondisi, d.keterangan, d.foto_temuan,
           d.sudah_diperbaiki,
           h.tanggal_inspeksi, h.jenis_pekerjaan, h.id AS header_id,
           r.nama_regu
    FROM gelar_alat_detail d
    JOIN gelar_alat_header h ON d.gelar_alat_id = h.id
    JOIN regu r ON h.regu_id = r.id
    WHERE d.kondisi IN ('rusak','waktu_ganti')
    ORDER BY d.sudah_diperbaiki ASC, h.created_at DESC
    LIMIT 20
");
$red_flags = $stmt_rf->fetchAll();

// ===== Riwayat Semua Laporan (Recent) =====
$stmt_hist = $pdo->query("
    SELECT h.*, r.nama_regu, r.nopol, u.nama_lengkap AS nama_petugas
    FROM gelar_alat_header h
    JOIN regu r ON h.regu_id = r.id
    JOIN users u ON h.petugas_id = u.id
    ORDER BY h.created_at DESC
    LIMIT 10
");
$recent_all = $stmt_hist->fetchAll();

$page_title = "Dashboard Monitoring K3";
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.75rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:4px; color:var(--text-main);">Dashboard Monitoring K3</h1>
        <p style="color:var(--text-muted); font-size:0.9rem;">Selamat datang, <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong> &mdash; <?= htmlspecialchars($user['jabatan']) ?></p>
    </div>
    <div class="topbar-date">
        <i class="fa-regular fa-calendar"></i>
        <span><?= date('d F Y') ?></span>
    </div>
</div>

<!-- ===== KPI Cards ===== -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-blue"><i class="fa-solid fa-clipboard-list"></i></div>
        <div>
            <div class="stat-value"><?= $total_bulan ?></div>
            <div class="stat-label">Gelar Alat Bulan Ini</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-red"><i class="fa-solid fa-hourglass-half"></i></div>
        <div>
            <div class="stat-value" style="<?= $pending > 0 ? 'color:#dc2626;' : '' ?>"><?= $pending ?></div>
            <div class="stat-label">Menunggu Persetujuan</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-green"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <div class="stat-value" style="color:#16a34a;"><?= $approved_bln ?></div>
            <div class="stat-label">Disetujui Bulan Ini</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <div class="stat-value" style="<?= $total_temuan > 0 ? 'color:#d97706;' : '' ?>"><?= $total_temuan ?></div>
            <div class="stat-label">Temuan Alat Rusak / Ganti</div>
        </div>
    </div>
</div>

<!-- ===== Antrean Persetujuan ===== -->
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-file-signature" style="color:var(--primary);"></i>
            Antrean Menunggu Tanda Tangan Anda
            <?php if ($pending > 0): ?>
                <span class="badge badge-rejected" style="font-size:0.7rem;"><?= $pending ?> laporan</span>
            <?php endif; ?>
        </h2>
        <a href="<?= base_url('riwayat/index.php') ?>" class="btn btn-outline btn-sm">Lihat Semua &rarr;</a>
    </div>

    <?php if (empty($pending_list)): ?>
        <div style="text-align:center; padding:2.75rem 1rem; color:var(--text-muted);">
            <div style="width:52px; height:52px; border-radius:50%; background:#f0fdf4; color:#16a34a; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:1.5rem;">
                <i class="fa-solid fa-check"></i>
            </div>
            <strong style="color:var(--text-main); font-size:0.95rem;">Semua Laporan Telah Ditinjau</strong>
            <p style="font-size:0.825rem; margin-top:2px;">Tidak ada dokumen gelar alat yang menunggu tanda tangan Anda saat ini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal Apel</th>
                        <th>Jenis & Regu</th>
                        <th>Petugas Pemeriksa</th>
                        <th>Temuan Fisik</th>
                        <th>Waktu Masuk</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_list as $row): ?>
                        <tr>
                            <td><strong style="color:var(--text-main);"><?= date('d/m/Y', strtotime($row['tanggal_inspeksi'])) ?></strong></td>
                            <td>
                                <span class="badge badge-submitted" style="margin-bottom:4px; font-size:0.68rem;">
                                    <?= strtoupper($row['jenis_pekerjaan']) ?>
                                </span>
                                <div style="font-weight:600; color:var(--text-main); font-size:0.875rem;">
                                    <?= htmlspecialchars($row['nama_regu']) ?>
                                    <?php if ($row['nopol']): ?>
                                        <span style="font-weight:400; font-size:0.785rem; color:var(--text-muted);"> (<?= htmlspecialchars($row['nopol']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--text-main); font-size:0.875rem;"><?= htmlspecialchars($row['nama_petugas']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($row['jabatan_petugas']) ?></div>
                            </td>
                            <td>
                                <?php if ($row['jumlah_temuan'] > 0): ?>
                                    <span class="badge badge-rejected">
                                        <i class="fa-solid fa-triangle-exclamation"></i> <?= $row['jumlah_temuan'] ?> alat rusak
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-approved">
                                        <i class="fa-solid fa-check"></i> Lengkap & Baik
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                <?= date('d M, H:i', strtotime($row['created_at'])) ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="<?= base_url('approval/review.php?id=' . $row['id']) ?>" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-signature"></i> Review & Sahkan
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">

    <!-- ===== Red Flag Alert: Alat Rusak ===== -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;"></i> Alert K3 &ndash; Temuan Kerusakan
            </h2>
            <span style="font-size:0.75rem; color:var(--text-muted); font-weight:500;">Perlu Tindak Lanjut</span>
        </div>
        <?php if (empty($red_flags)): ?>
            <div style="text-align:center; color:var(--text-muted); padding:2rem 1rem; font-size:0.875rem;">
                <div style="width:44px; height:44px; border-radius:50%; background:#f0fdf4; color:#16a34a; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.2rem;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <strong style="color:var(--text-main);">Kondisi Aman</strong>
                <p style="font-size:0.8rem; margin-top:2px;">Tidak ada temuan alat kerja atau APD rusak yang tercatat.</p>
            </div>
        <?php else: ?>
            <div style="max-height:420px; overflow-y:auto;" id="redFlagList">
                <?php foreach ($red_flags as $rf): ?>
                    <div class="rf-item <?= $rf['sudah_diperbaiki'] ? 'rf-done' : '' ?>" id="rf-<?= $rf['detail_id'] ?>" style="background:<?= $rf['sudah_diperbaiki'] ? '#f0fdf4' : ($rf['kondisi'] === 'rusak' ? '#fef2f2' : '#fffbeb') ?>; border:1px solid <?= $rf['sudah_diperbaiki'] ? '#bbf7d0' : ($rf['kondisi'] === 'rusak' ? '#fecaca' : '#fde68a') ?>;">
                        <!-- Icon/Foto -->
                        <?php if (!empty($rf['foto_temuan'])): ?>
                            <img src="<?= base_url('uploads/foto_temuan/' . htmlspecialchars($rf['foto_temuan'])) ?>" 
                                 style="width:42px; height:42px; object-fit:cover; border-radius:var(--radius-sm); border:1px solid rgba(0,0,0,0.1); flex-shrink:0;">
                        <?php else: ?>
                            <div style="width:42px; height:42px; border-radius:var(--radius-sm); background:<?= $rf['sudah_diperbaiki'] ? '#dcfce7' : ($rf['kondisi'] === 'rusak' ? '#fee2e2' : '#fef3c7') ?>; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; color:<?= $rf['sudah_diperbaiki'] ? '#15803d' : ($rf['kondisi'] === 'rusak' ? '#b91c1c' : '#b45309') ?>;">
                                <i class="fa-solid <?= $rf['sudah_diperbaiki'] ? 'fa-check' : ($rf['kondisi'] === 'rusak' ? 'fa-triangle-exclamation' : 'fa-wrench') ?>"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Info -->
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:0.85rem; color:<?= $rf['sudah_diperbaiki'] ? '#15803d' : ($rf['kondisi'] === 'rusak' ? '#b91c1c' : '#b45309') ?>; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= htmlspecialchars($rf['nama_barang_snapshot']) ?>
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                <?= htmlspecialchars($rf['nama_regu']) ?> &middot; <?= date('d/m/Y', strtotime($rf['tanggal_inspeksi'])) ?>
                            </div>
                            <?php if (!empty($rf['keterangan'])): ?>
                                <div style="font-size:0.75rem; color:var(--text-body); margin-top:3px; background:rgba(255,255,255,0.6); padding:2px 6px; border-radius:4px; display:inline-block;">
                                    <?= htmlspecialchars($rf['keterangan']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Status + Tombol Selesai -->
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0;">
                            <?php if ($rf['sudah_diperbaiki']): ?>
                                <span class="badge badge-approved" style="font-size:0.68rem;">
                                    <i class="fa-solid fa-check"></i> Selesai
                                </span>
                            <?php else: ?>
                                <span class="badge <?= $rf['kondisi'] === 'rusak' ? 'badge-rejected' : 'cond-waktu_ganti' ?>" style="font-size:0.68rem;">
                                    <?= $rf['kondisi'] === 'rusak' ? 'RUSAK' : 'WAKTU GANTI' ?>
                                </span>
                                <button class="btn-done-rf" onclick="showDoneModal(<?= $rf['detail_id'] ?>, '<?= addslashes(htmlspecialchars($rf['nama_barang_snapshot'])) ?>')" title="Tandai sudah diperbaiki">
                                    <i class="fa-solid fa-check"></i> Selesai
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== Riwayat Berita Acara Terbaru ===== -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-folder-open" style="color:var(--primary);"></i> Rekap Dokumen Terbaru
            </h2>
            <a href="<?= base_url('riwayat/index.php') ?>" class="btn btn-outline btn-sm">Semua &rarr;</a>
        </div>
        <?php if (empty($recent_all)): ?>
            <div style="text-align:center; color:var(--text-muted); padding:2rem 1rem; font-size:0.875rem;">
                Belum ada data laporan gelar alat.
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:6px;">
                <?php foreach ($recent_all as $row): ?>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 12px; border-radius:var(--radius-md); background:#f8fafc; border:1px solid var(--border-light); gap:12px; transition:background 0.15s ease;">
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:600; font-size:0.875rem; color:var(--text-main); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?= htmlspecialchars($row['nama_regu']) ?>
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                <span class="badge" style="background:#e2e8f0; color:#475569; font-size:0.65rem; padding:1px 6px;"><?= strtoupper($row['jenis_pekerjaan']) ?></span>
                                &middot; <?= date('d/m/Y', strtotime($row['tanggal_inspeksi'])) ?>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                            <span class="badge badge-<?= $row['status'] ?>" style="font-size:0.68rem;">
                                <?= strtoupper($row['status']) ?>
                            </span>
                            <a href="<?= base_url('riwayat/detail.php?id=' . $row['id']) ?>" class="btn btn-outline btn-sm" style="padding:4px 8px;" title="Lihat Detail">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Modal Konfirmasi Selesai Diperbaiki ===== -->
<div id="doneModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:var(--radius-xl); padding:2rem; max-width:440px; width:90%; box-shadow:var(--shadow-xl); animation:modalIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div style="width:54px; height:54px; background:#f0fdf4; border-radius:var(--radius-lg); display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:1.5rem; color:#16a34a; box-shadow:0 4px 12px rgba(22,163,74,0.15);">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
            <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-main); margin-bottom:4px;">Tandai Selesai Diperbaiki?</h3>
            <p style="font-size:0.85rem; color:var(--text-muted);">Alat: <strong id="doneItemName" style="color:var(--text-main);"></strong></p>
        </div>
        <div class="form-group" style="margin-bottom:1.25rem;">
            <label class="form-label" for="catatanPerbaikan">Catatan Perbaikan <span style="font-weight:400; color:var(--text-muted);">(opsional)</span></label>
            <textarea id="catatanPerbaikan" class="form-control" rows="3" placeholder="Contoh: Diganti unit baru dari gudang, sudah diuji..."></textarea>
        </div>
        <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:var(--radius-md); padding:10px 14px; font-size:0.8rem; color:#b45309; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:8px;">
            <i class="fa-solid fa-bell" style="margin-top:2px;"></i>
            <span>Notifikasi otomatis akan dikirim ke <strong>petugas pemeriksa</strong> yang melaporkan temuan ini.</span>
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="closeDoneModal()" class="btn btn-outline" style="flex:1;">Batal</button>
            <button onclick="submitDone()" class="btn btn-success" style="flex:1;" id="btnSubmitDone">
                <i class="fa-solid fa-check"></i> Simpan Selesai
            </button>
        </div>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity:0; transform:scale(0.96) translateY(8px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
.rf-done {
    opacity:0.75;
}
#doneModal.show {
    display:flex !important;
}
</style>

<script>
let _doneDetailId = null;

function showDoneModal(detailId, namaBarang) {
    _doneDetailId = detailId;
    document.getElementById('doneItemName').textContent = namaBarang;
    document.getElementById('catatanPerbaikan').value = '';
    document.getElementById('doneModal').classList.add('show');
}
function closeDoneModal() {
    document.getElementById('doneModal').classList.remove('show');
    _doneDetailId = null;
}

async function submitDone() {
    if (!_doneDetailId) return;
    const btn = document.getElementById('btnSubmitDone');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

    const catatan = document.getElementById('catatanPerbaikan').value.trim();
    const formData = new FormData();
    formData.append('detail_id', _doneDetailId);
    formData.append('catatan_perbaikan', catatan);

    try {
        const resp = await fetch('<?= base_url('dashboard/mark_done.php') ?>', {
            method: 'POST',
            body: formData,
        });
        const data = await resp.json();

        if (data.ok) {
            closeDoneModal();
            // Animasi item berubah jadi "selesai"
            const item = document.getElementById('rf-' + _doneDetailId);
            if (item) {
                item.style.background = '#f0fdf4';
                item.style.border = '1px solid #bbf7d0';
                const btnDone = item.querySelector('.btn-done-rf');
                if (btnDone) {
                    btnDone.parentElement.innerHTML =
                        '<span class="badge" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;"><i class="fa-solid fa-circle-check"></i> Selesai</span>';
                }
                item.classList.add('rf-done');
            }
            showToast('✅ ' + data.pesan, 'sukses');
        } else {
            showToast('⚠ ' + data.pesan, 'peringatan');
        }
    } catch (e) {
        showToast('❌ Gagal terhubung ke server.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Selesai Diperbaiki';
    }
}

function showToast(pesan, tipe = 'sukses') {
    const colors = {
        sukses: { bg:'#d1fae5', border:'#a7f3d0', color:'#065f46' },
        peringatan: { bg:'#fef3c7', border:'#fde68a', color:'#92400e' },
        error: { bg:'#fee2e2', border:'#fecaca', color:'#991b1b' },
    };
    const c = colors[tipe] || colors.sukses;
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:99999;padding:14px 20px;border-radius:12px;
        background:${c.bg};border:1px solid ${c.border};color:${c.color};
        font-size:0.9rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.12);
        animation:modalIn 0.25s ease;max-width:340px;font-family:'Inter',sans-serif;`;
    toast.innerHTML = pesan;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// Tutup modal jika klik backdrop
document.getElementById('doneModal').addEventListener('click', function(e) {
    if (e.target === this) closeDoneModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
