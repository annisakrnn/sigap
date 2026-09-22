<?php
/**
 * Form Checklist Gelar Alat - Dinamis per Jenis Pekerjaan
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('petugas');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();

// Cek mode edit / revisi
$edit_id = intval($_GET['edit_id'] ?? 0);
$edit_header = null;
$existing_details_map = [];

if ($edit_id > 0) {
    $stmt_edit = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = :id AND petugas_id = :uid LIMIT 1");
    $stmt_edit->execute([':id' => $edit_id, ':uid' => $user['id']]);
    $edit_header = $stmt_edit->fetch();

    if (!$edit_header) {
        set_flash('error', "Dokumen tidak ditemukan atau Anda tidak memiliki akses ke dokumen ini.");
        header("Location: index.php"); exit;
    }

    if (!in_array($edit_header['status'], ['draft', 'rejected'])) {
        set_flash('warning', "Dokumen ini berstatus " . strtoupper($edit_header['status']) . " dan tidak dapat diubah.");
        header("Location: " . base_url('riwayat/detail.php?id=' . $edit_id)); exit;
    }

    $jenis_pekerjaan = $edit_header['jenis_pekerjaan'];
    $regu_id = (int)$edit_header['regu_id'];

    $stmt_d_ex = $pdo->prepare("SELECT * FROM gelar_alat_detail WHERE gelar_alat_id = :id");
    $stmt_d_ex->execute([':id' => $edit_id]);
    foreach ($stmt_d_ex->fetchAll() as $d_row) {
        $existing_details_map[$d_row['master_barang_id']] = $d_row;
    }
} else {
    // Validasi parameter GET biasa
    $jenis_pekerjaan = in_array($_GET['jenis'] ?? '', ['p2tl','sr_app','yandal','har']) ? $_GET['jenis'] : null;
    $regu_id = intval($_GET['regu_id'] ?? 0);

    if (!$jenis_pekerjaan || !$regu_id) {
        set_flash('error', "Parameter tidak valid. Silakan pilih jenis gelar alat terlebih dahulu.");
        header("Location: index.php"); exit;
    }
}

// Ambil data regu
$stmt_regu = $pdo->prepare("SELECT * FROM regu WHERE id = :id AND jenis_pekerjaan = :jenis LIMIT 1");
$stmt_regu->execute([':id' => $regu_id, ':jenis' => $jenis_pekerjaan]);
$regu = $stmt_regu->fetch();
if (!$regu) {
    set_flash('error', "Data regu tidak ditemukan.");
    header("Location: index.php"); exit;
}

// Ambil template checklist items (berurutan berdasarkan kategori & urutan)
$stmt_items = $pdo->prepare("
    SELECT tci.*, mb.nama_barang, mb.kategori, mb.satuan_default, mb.gambar_referensi
    FROM template_checklist_item tci
    JOIN master_barang mb ON tci.master_barang_id = mb.id
    WHERE tci.jenis_pekerjaan = :jenis
    ORDER BY mb.kategori, tci.urutan
");
$stmt_items->execute([':jenis' => $jenis_pekerjaan]);
$all_items = $stmt_items->fetchAll();

// Kelompokkan items per kategori
$items_by_kategori = [];
foreach ($all_items as $item) {
    $items_by_kategori[$item['kategori']][] = $item;
}

$kategori_labels = [
    'alat_kerja'          => ['label' => 'Peralatan Kerja', 'icon' => 'fa-toolbox', 'color' => '#0072ce'],
    'k3_safety'           => ['label' => 'APD & Peralatan K3', 'icon' => 'fa-hard-hat', 'color' => '#ef4444'],
    'kendaraan_pendukung' => ['label' => 'Armada & Sarana Pendukung', 'icon' => 'fa-car-side', 'color' => '#f59e0b'],
    'administrasi'        => ['label' => 'Sarana Administrasi', 'icon' => 'fa-briefcase', 'color' => '#8b5cf6'],
];

$jenis_label = [
    'p2tl'   => 'P2TL - Penertiban Pemakaian Tenaga Listrik',
    'sr_app' => 'SR APP 1 Phasa - Penyambungan & Pembongkaran',
    'yandal' => 'YANDAL & ULC - Pelayanan Gangguan',
    'har'    => 'HAR - Pemeliharaan Jaringan Distribusi',
];

$page_title = ($edit_header ? "Revisi Gelar Alat " : "Form Gelar Alat ") . strtoupper($jenis_pekerjaan);
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($edit_header && $edit_header['status'] === 'rejected'): ?>
    <!-- Alert Revisi Ditolak Atasan -->
    <div class="card" style="border:2px solid #ef4444; background:#fef2f2; margin-bottom:1.5rem; padding:1.25rem 1.5rem;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
            <div style="width:42px; height:42px; border-radius:50%; background:#ef4444; color:white; display:flex; align-items:center; justify-content:center; font-size:1.25rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 style="font-size:1.05rem; font-weight:700; color:#991b1b; margin:0;">
                    Mode Revisi Laporan: <?= htmlspecialchars($edit_header['nomor_dokumen'] ?? '#' . $edit_id) ?>
                </h3>
                <p style="font-size:0.825rem; color:#b91c1c; margin:2px 0 0;">
                    Laporan ini sebelumnya dikembalikan oleh Manajemen Atasan untuk perbaikan. Silakan sesuaikan data dan kirim ulang.
                </p>
            </div>
        </div>
        <div style="background:#fff; border-radius:6px; padding:10px 14px; border-left:4px solid #ef4444; margin-top:8px;">
            <strong style="font-size:0.8rem; text-transform:uppercase; color:#7f1d1d;">Catatan Arahan dari Manajemen:</strong>
            <p style="margin:4px 0 0; font-size:0.9rem; color:#1e293b;">
                <?= nl2br(htmlspecialchars($edit_header['catatan_manajemen'] ?? '-')) ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<div style="margin-bottom:1.5rem;">
    <a href="<?= $edit_id ? base_url('riwayat/detail.php?id=' . $edit_id) : 'index.php' ?>" class="btn btn-outline btn-sm" style="margin-bottom:0.75rem;">
        <i class="fa-solid fa-arrow-left"></i> <?= $edit_id ? 'Kembali ke Detail' : 'Kembali Pilih Regu' ?>
    </a>
    <h1 style="font-size:1.5rem;margin-bottom:4px;">
        <i class="fa-solid fa-clipboard-check" style="color:var(--primary);margin-right:8px;"></i>
        <?= $edit_id ? 'Revisi ' : 'Checklist ' ?><?= htmlspecialchars($jenis_label[$jenis_pekerjaan] ?? strtoupper($jenis_pekerjaan)) ?>
    </h1>
    <p style="color:var(--text-muted);font-size:0.9rem;">
        <?= htmlspecialchars($regu['nama_regu']) ?>
        <?php if (!empty($regu['nopol'])): ?> &mdash; <strong><?= htmlspecialchars($regu['nopol']) ?></strong><?php endif; ?>
        | <?= htmlspecialchars($regu['kendaraan'] ?? '') ?>
        <?php if ($edit_header): ?> &bull; No: <strong><?= htmlspecialchars($edit_header['nomor_dokumen']) ?></strong><?php endif; ?>
    </p>
</div>

<form method="POST" action="simpan.php" id="formGelarAlat" enctype="multipart/form-data">
    <input type="hidden" name="jenis_pekerjaan" value="<?= htmlspecialchars($jenis_pekerjaan) ?>">
    <input type="hidden" name="regu_id" value="<?= $regu_id ?>">
    <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
    <input type="hidden" name="action" id="inspeksiAction" value="submit">

    <!-- ===== SECTION 1: Data Header ===== -->
    <div class="card" style="margin-bottom:1.25rem; border-left: 4px solid var(--primary);">
        <div class="card-header">
            <h2 class="card-title" style="font-size:1.05rem;">
                <i class="fa-solid fa-id-card" style="color:var(--primary);margin-right:6px;"></i> A. Identitas Regu & Tanggal Pemeriksaan
            </h2>
            <span class="badge badge-submitted">Wajib Diisi</span>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Tanggal Apel Gelar Alat</label>
                <input type="date" name="tanggal_inspeksi" class="form-control" value="<?= htmlspecialchars($edit_header['tanggal_inspeksi'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Bulan / Tahun</label>
                <input type="text" name="bulan_tahun" class="form-control" value="<?= htmlspecialchars($edit_header['bulan_tahun'] ?? date('F Y')) ?>" required>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Nama Pelaksana / Pemeriksa 1 <span style="color:var(--danger);">*</span></label>
                <input type="text" name="nama_pelaksana_1" class="form-control" value="<?= htmlspecialchars($edit_header['nama_pelaksana_1'] ?? $user['nama_lengkap']) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Nama Pelaksana 2 (jika ada)</label>
                <input type="text" name="nama_pelaksana_2" class="form-control" value="<?= htmlspecialchars($edit_header['nama_pelaksana_2'] ?? '') ?>" placeholder="Opsional">
            </div>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Pendamping / Administrasi (jika ada)</label>
            <input type="text" name="pendamping_admin" class="form-control" value="<?= htmlspecialchars($edit_header['pendamping_admin'] ?? '') ?>" placeholder="Opsional">
        </div>
    </div>

    <!-- ===== SECTION 2: Quick Action ===== -->
    <div style="background: linear-gradient(135deg, #0a2540 0%, #0f3460 100%); padding:1rem 1.5rem; border-radius:var(--radius-md); margin-bottom:1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div style="color:white;">
            <strong style="font-size:0.95rem;"><i class="fa-solid fa-bolt" style="color:#f59e0b;margin-right:6px;"></i>Aksi Cepat Inspeksi:</strong>
            <span style="font-size:0.825rem;opacity:0.8;margin-left:8px;">Klik tombol ini jika semua alat dalam kondisi baik & kuantitas terpenuhi, lalu ubah item yang bermasalah satu per satu.</span>
        </div>
        <button type="button" id="btnAllBaik" class="btn" style="background:#10b981;color:white;white-space:nowrap;">
            <i class="fa-solid fa-circle-check"></i> Tandai Semua BAIK & Lengkap
        </button>
    </div>

    <!-- ===== SECTION 3: Tabel Checklist Dinamis ===== -->
    <?php foreach ($items_by_kategori as $kategori_key => $items): ?>
        <?php $k = $kategori_labels[$kategori_key] ?? ['label' => ucfirst($kategori_key), 'icon' => 'fa-box', 'color' => '#666']; ?>
        <div class="card" style="margin-bottom:1.25rem; border-top:3px solid <?= $k['color'] ?>;">
            <div class="card-header" style="margin-bottom:0;">
                <h2 class="card-title" style="font-size:1.05rem; display:flex; align-items:center; gap:8px;">
                    <span style="width:32px;height:32px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;color:white;background:<?= $k['color'] ?>;">
                        <i class="fa-solid <?= $k['icon'] ?>" style="font-size:0.875rem;"></i>
                    </span>
                    <?= htmlspecialchars($k['label']) ?>
                    <span class="badge" style="background:<?= $k['color'] ?>22;color:<?= $k['color'] ?>;border:1px solid <?= $k['color'] ?>44;font-size:0.7rem;"><?= count($items) ?> item</span>
                </h2>
            </div>

            <!-- Desktop Table View -->
            <div class="table-responsive" style="display:none;" id="tbl_<?= $kategori_key ?>">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="35px">No</th>
                            <th>Nama Barang / Alat</th>
                            <th width="90px">Standar</th>
                            <th width="90px">Realisasi</th>
                            <th>Kondisi</th>
                            <th>Keterangan</th>
                            <th width="80px">Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $item): ?>
                            <?php
                            $prev_d = $existing_details_map[$item['master_barang_id']] ?? null;
                            $realisasi_val = $prev_d ? $prev_d['jumlah_realisasi'] : $item['jumlah_standar'];
                            $kondisi_val = $prev_d ? $prev_d['kondisi'] : 'baik';
                            $keterangan_val = $prev_d ? $prev_d['keterangan'] : '';
                            $foto_val = $prev_d ? $prev_d['foto_temuan'] : '';
                            ?>
                            <tr id="row_<?= $item['id'] ?>">
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['nama_barang']) ?></strong>
                                    <input type="hidden" name="items[<?= $item['id'] ?>][barang_id]" value="<?= $item['master_barang_id'] ?>">
                                    <input type="hidden" name="items[<?= $item['id'] ?>][jumlah_standar]" value="<?= $item['jumlah_standar'] ?>">
                                    <input type="hidden" name="items[<?= $item['id'] ?>][satuan]" value="<?= htmlspecialchars($item['satuan_default']) ?>">
                                    <input type="hidden" name="items[<?= $item['id'] ?>][kategori]" value="<?= htmlspecialchars($item['kategori']) ?>">
                                    <input type="hidden" name="items[<?= $item['id'] ?>][existing_foto]" value="<?= htmlspecialchars($foto_val) ?>">
                                </td>
                                <td class="text-center">
                                    <span class="badge" style="background:#f1f5f9;color:var(--navy);">
                                        <?= $item['jumlah_standar'] ?> <?= htmlspecialchars($item['satuan_default']) ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="number" name="items[<?= $item['id'] ?>][realisasi]" 
                                        class="form-control input-realisasi" min="0" 
                                        value="<?= $realisasi_val ?>" 
                                        data-std="<?= $item['jumlah_standar'] ?>"
                                        style="text-align:center;padding:6px;" required>
                                </td>
                                <td>
                                    <div class="condition-selector">
                                        <?php foreach (['baik' => 'Baik', 'rusak' => 'Rusak', 'waktu_ganti' => 'Waktu Ganti', 'ada' => 'Ada'] as $cval => $clabel): ?>
                                            <div class="cond-option">
                                                <input type="radio" 
                                                    name="items[<?= $item['id'] ?>][kondisi]"
                                                    id="cond_<?= $item['id'] ?>_<?= $cval ?>"
                                                    value="<?= $cval ?>"
                                                    class="cond-radio" 
                                                    data-item-id="<?= $item['id'] ?>"
                                                    <?= $cval === $kondisi_val ? 'checked' : '' ?>
                                                    required>
                                                <label class="cond-label lbl-<?= str_replace('_', '-', $cval) ?>" for="cond_<?= $item['id'] ?>_<?= $cval ?>">
                                                    <?= $clabel ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="items[<?= $item['id'] ?>][keterangan]" class="form-control ket-input" placeholder="Opsional" value="<?= htmlspecialchars($keterangan_val) ?>" style="font-size:0.8rem;padding:5px 8px;">
                                </td>
                                <td>
                                    <input type="file" name="foto_item_<?= $item['id'] ?>" 
                                        class="foto-item-input" 
                                        accept="image/*" capture="environment"
                                        style="display:none;" id="fotoInput_<?= $item['id'] ?>">
                                    <button type="button" class="btn btn-outline btn-sm btn-foto-cam" 
                                        onclick="document.getElementById('fotoInput_<?= $item['id'] ?>').click()"
                                        id="btnFoto_<?= $item['id'] ?>" title="Foto Temuan">
                                        <i class="fa-solid fa-camera"></i>
                                    </button>
                                    <?php if (!empty($foto_val)): ?>
                                        <div style="margin-top:4px; font-size:0.65rem; color:#10b981;">
                                            <i class="fa-solid fa-check"></i> Ada foto
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View (default) -->
            <div id="cards_<?= $kategori_key ?>">
                <?php foreach ($items as $idx => $item): ?>
                    <?php
                    $prev_d = $existing_details_map[$item['master_barang_id']] ?? null;
                    $realisasi_val = $prev_d ? $prev_d['jumlah_realisasi'] : $item['jumlah_standar'];
                    $kondisi_val = $prev_d ? $prev_d['kondisi'] : 'baik';
                    $keterangan_val = $prev_d ? $prev_d['keterangan'] : '';
                    $foto_val = $prev_d ? $prev_d['foto_temuan'] : '';
                    $need_ket = in_array($kondisi_val, ['rusak', 'waktu_ganti']);
                    ?>
                    <div class="item-card" style="border:1px solid var(--border-color);border-radius:var(--radius-md);padding:12px;margin-bottom:8px;background:#fafafa;" id="card_<?= $item['id'] ?>">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                            <div>
                                <span style="font-size:0.725rem;font-weight:600;color:var(--text-muted);">No.<?= $idx + 1 ?></span>
                                <div style="font-weight:700;font-size:0.9rem;color:var(--navy);"><?= htmlspecialchars($item['nama_barang']) ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);">
                                    Standar: <strong><?= $item['jumlah_standar'] ?> <?= htmlspecialchars($item['satuan_default']) ?></strong>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <input type="file" name="foto_item_<?= $item['id'] ?>" accept="image/*" capture="environment" style="display:none;" id="fotoInputM_<?= $item['id'] ?>">
                                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('fotoInputM_<?= $item['id'] ?>').click()" title="Foto Temuan">
                                    <i class="fa-solid fa-camera"></i>
                                </button>
                                <?php if (!empty($foto_val)): ?>
                                    <div style="font-size:0.65rem; color:#10b981; margin-top:2px;">
                                        <i class="fa-solid fa-check"></i> Foto tersimpan
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <input type="hidden" name="items[<?= $item['id'] ?>][barang_id]" value="<?= $item['master_barang_id'] ?>">
                        <input type="hidden" name="items[<?= $item['id'] ?>][jumlah_standar]" value="<?= $item['jumlah_standar'] ?>">
                        <input type="hidden" name="items[<?= $item['id'] ?>][satuan]" value="<?= htmlspecialchars($item['satuan_default']) ?>">
                        <input type="hidden" name="items[<?= $item['id'] ?>][kategori]" value="<?= htmlspecialchars($item['kategori']) ?>">
                        <input type="hidden" name="items[<?= $item['id'] ?>][existing_foto]" value="<?= htmlspecialchars($foto_val) ?>">

                        <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;margin-bottom:8px;align-items:center;">
                            <label style="font-size:0.8rem;font-weight:600;">Realisasi:</label>
                            <input type="number" name="items[<?= $item['id'] ?>][realisasi]"
                                class="form-control input-realisasi" min="0"
                                value="<?= $realisasi_val ?>"
                                data-std="<?= $item['jumlah_standar'] ?>"
                                style="font-size:0.9rem;padding:6px;text-align:center;max-width:90px;"
                                required>
                        </div>

                        <div style="margin-bottom:8px;">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:5px;">Kondisi:</label>
                            <div class="condition-selector" style="flex-wrap:nowrap;">
                                <?php foreach (['baik' => '✓ Baik', 'rusak' => '✗ Rusak', 'waktu_ganti' => '⚠ Waktu Ganti', 'ada' => '○ Ada'] as $cval => $clabel): ?>
                                    <div class="cond-option">
                                        <input type="radio"
                                            name="items[<?= $item['id'] ?>][kondisi]"
                                            id="condm_<?= $item['id'] ?>_<?= $cval ?>"
                                            value="<?= $cval ?>"
                                            class="cond-radio"
                                            data-item-id="<?= $item['id'] ?>"
                                            <?= $cval === $kondisi_val ? 'checked' : '' ?>
                                            required>
                                        <label class="cond-label lbl-<?= str_replace('_','-',$cval) ?>" for="condm_<?= $item['id'] ?>_<?= $cval ?>" style="font-size:0.775rem;padding:5px 9px;">
                                            <?= $clabel ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="ketWrapper_<?= $item['id'] ?>" style="display:<?= $need_ket ? 'block' : 'none' ?>;">
                            <input type="text" name="items[<?= $item['id'] ?>][keterangan]"
                                class="form-control ket-input" placeholder="Keterangan kerusakan / tindak lanjut..."
                                value="<?= htmlspecialchars($keterangan_val) ?>"
                                style="font-size:0.85rem;">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    <?php endforeach; ?>

    <!-- ===== SECTION 4: Upload Foto Kegiatan ===== -->
    <div class="card" style="margin-bottom:1.25rem; border-left: 4px solid #10b981;">
        <div class="card-header">
            <h2 class="card-title" style="font-size:1.05rem;">
                <i class="fa-solid fa-image" style="color:#10b981;margin-right:6px;"></i> B. Foto Dokumentasi Gelar Alat (Opsional)
            </h2>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Upload Foto Bersama Regu / Apel Pasukan</label>
            <?php if (!empty($edit_header['foto_kegiatan'])): ?>
                <div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
                    <img src="<?= base_url('uploads/foto_kegiatan/' . htmlspecialchars($edit_header['foto_kegiatan'])) ?>" style="max-height:70px; border-radius:6px; border:1px solid var(--border-color);">
                    <div style="font-size:0.75rem; color:var(--text-muted);">
                        Foto sebelumnya tersimpan. Unggah file baru jika ingin mengganti.
                        <input type="hidden" name="existing_foto_kegiatan" value="<?= htmlspecialchars($edit_header['foto_kegiatan']) ?>">
                    </div>
                </div>
            <?php endif; ?>
            <input type="file" name="foto_kegiatan" class="form-control" accept="image/*" capture="environment">
            <small style="color:var(--text-muted);">Foto bersama seluruh anggota regu saat apel gelar alat. Maks 5MB (JPG/PNG/WEBP).</small>
        </div>
    </div>

    <!-- ===== SECTION 5: Tanda Tangan Digital Petugas Pemeriksa ===== -->
    <div class="card" style="margin-bottom:1.25rem; border-left: 4px solid var(--navy);">
        <div class="card-header">
            <h2 class="card-title" style="font-size:1.05rem;">
                <i class="fa-solid fa-signature" style="color:var(--navy);margin-right:6px;"></i> C. Tanda Tangan Digital Petugas Pemeriksa
            </h2>
        </div>
        <?php if (!empty($edit_header['ttd_petugas'])): ?>
            <div style="margin-bottom:12px; padding:10px 14px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color); display:flex; align-items:center; gap:14px;">
                <img src="<?= htmlspecialchars($edit_header['ttd_petugas']) ?>" style="max-height:48px; border:1px solid #cbd5e1; border-radius:4px; background:#fff; padding:2px;">
                <div style="font-size:0.8rem; color:var(--text-muted);">
                    <strong style="color:var(--text-main);">Tanda tangan sebelumnya sudah tersimpan.</strong><br>
                    Tanda tangani ulang pada canvas di bawah ini hanya jika Anda ingin memperbarui tanda tangan.
                </div>
            </div>
        <?php else: ?>
            <p style="font-size:0.875rem;color:var(--text-muted);margin-bottom:1rem;">
                Tanda tangani pada bidang di bawah ini menggunakan jari atau stylus Anda sebagai pernyataan bahwa pemeriksaan telah dilakukan.
            </p>
        <?php endif; ?>
        <div class="signature-wrapper">
            <canvas class="signature-canvas" id="canvasPetugas"></canvas>
        </div>
        <div class="signature-actions">
            <button type="button" id="clearPetugas" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-eraser"></i> Hapus Ulang
            </button>
        </div>
        <input type="hidden" name="ttd_petugas" id="ttdPetugas">
        <p style="font-size:0.8rem;color:var(--text-muted);margin-top:8px;">
            Petugas Pemeriksa: <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong> — <?= htmlspecialchars($user['jabatan']) ?>
        </p>
    </div>

    <!-- ===== SECTION 6: Catatan Umum ===== -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title" style="font-size:1.05rem;">
                <i class="fa-solid fa-note-sticky" style="color:var(--accent);margin-right:6px;"></i> D. Catatan Umum
            </h2>
        </div>
        <textarea name="catatan_umum" class="form-control" rows="3" placeholder="Catatan umum hasil gelar alat, temuan K3, atau tindak lanjut yang perlu diketahui..."><?= htmlspecialchars($edit_header['catatan_umum'] ?? '') ?></textarea>
    </div>

    <!-- ===== Sticky Submit Bar ===== -->
    <div class="sticky-action-bar no-print">
        <div class="container">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div style="font-size:0.875rem;color:var(--text-muted);">
                    <strong><?= count($all_items) ?> item</strong> peralatan diperiksa &middot; <?= htmlspecialchars($regu['nama_regu']) ?>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <button type="button" onclick="kirimForm('draft')" class="btn btn-outline">
                        <i class="fa-regular fa-floppy-disk"></i> Simpan Draft
                    </button>
                    <button type="button" onclick="kirimForm('submit')" id="btnSubmit" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-paper-plane"></i> <?= ($edit_header && $edit_header['status'] === 'rejected') ? 'Kirim Ulang Revisi ke Manajemen' : 'Submit ke Manajemen' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

</form>

<script>
// ========================================
// Init Signature Pad
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('canvasPetugas');
    const clearBtn = document.getElementById('clearPetugas');
    const hiddenInput = document.getElementById('ttdPetugas');
    window.sigPad = new SimpleSignaturePad(canvas, clearBtn, hiddenInput);

    // ========================================
    // Tombol Tandai Semua BAIK
    // ========================================
    document.getElementById('btnAllBaik').addEventListener('click', function() {
        document.querySelectorAll('.cond-radio[value="baik"]').forEach(radio => {
            radio.checked = true;
            const itemId = radio.dataset.itemId;
            const ket = document.getElementById('ketWrapper_' + itemId);
            if (ket) ket.style.display = 'none';
        });
        document.querySelectorAll('.input-realisasi').forEach(input => {
            input.value = input.dataset.std;
        });
        this.innerHTML = '<i class="fa-solid fa-check-double"></i> Semua Ditandai Baik!';
        this.style.background = '#059669';
        setTimeout(() => {
            this.innerHTML = '<i class="fa-solid fa-circle-check"></i> Tandai Semua BAIK & Lengkap';
            this.style.background = '#10b981';
        }, 2000);
    });

    // ========================================
    // Tampilkan kolom keterangan jika Rusak / Waktu Ganti
    // ========================================
    document.querySelectorAll('.cond-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const itemId = this.dataset.itemId;
            const ket = document.getElementById('ketWrapper_' + itemId);
            if (ket) {
                const needKet = (this.value === 'rusak' || this.value === 'waktu_ganti');
                ket.style.display = needKet ? 'block' : 'none';
            }
        });
    });

    // ========================================
    // Preview thumbnail foto temuan
    // ========================================
    document.querySelectorAll('input[type="file"][name^="foto_item_"]').forEach(input => {
        input.addEventListener('change', function() {
            const itemId = this.name.replace('foto_item_', '');
            const card = document.getElementById('card_' + itemId) || document.getElementById('row_' + itemId);
            if (card && this.files[0]) {
                const file = this.files[0];
                const url = URL.createObjectURL(file);
                let prevEl = card.querySelector('.foto-preview');
                if (!prevEl) {
                    prevEl = document.createElement('img');
                    prevEl.className = 'foto-preview';
                    prevEl.style.cssText = 'width:50px;height:50px;object-fit:cover;border-radius:6px;border:2px solid #10b981;margin-top:4px;';
                    card.appendChild(prevEl);
                }
                prevEl.src = url;
            }
        });
    });
});

// ========================================
// Function Kirim Form Aman
// ========================================
function kirimForm(action) {
    document.getElementById('inspeksiAction').value = action;
    const hasExistingTtd = <?= (!empty($edit_header['ttd_petugas'])) ? 'true' : 'false' ?>;

    if (action === 'submit') {
        if ((!window.sigPad || window.sigPad.isEmpty()) && !hasExistingTtd) {
            alert('Tanda tangan digital Petugas Pemeriksa belum diisi.\nSilakan tanda tangan pada kolom canvas yang tersedia.');
            return;
        }
        if (window.sigPad && !window.sigPad.isEmpty()) {
            window.sigPad.updateInput();
        }
        if (!confirm('Apakah Anda yakin data hasil pemeriksaan sudah benar dan siap dikirimkan ke Manajemen Atasan?')) {
            return;
        }
    }

    const btn = document.getElementById('btnSubmit');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (action === 'submit' ? 'Mengirim...' : 'Menyimpan...');
    btn.style.pointerEvents = 'none';
    document.getElementById('formGelarAlat').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
