<?php
/**
 * Form Checklist Gelar Alat - Dinamis per Jenis Pekerjaan
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();

// Cek mode edit / revisi
$edit_id = intval(isset($_GET['edit_id']) ? $_GET['edit_id'] : 0);
$edit_header = null;
$existing_details_map = [];

if ($edit_id > 0) {
    if ($user && $user['role'] === 'manajemen') {
        $stmt_edit = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = :id LIMIT 1");
        $stmt_edit->execute([':id' => $edit_id]);
    } elseif ($user) {
        $stmt_edit = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = :id AND petugas_id = :uid LIMIT 1");
        $stmt_edit->execute([':id' => $edit_id, ':uid' => $user['id']]);
    } else {
        $stmt_edit = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = :id LIMIT 1");
        $stmt_edit->execute([':id' => $edit_id]);
    }
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
    $jenis_param = isset($_GET['jenis']) ? $_GET['jenis'] : '';
    $jenis_pekerjaan = in_array($jenis_param, ['p2tl','sr_app','yandal','har']) ? $jenis_param : null;
    $regu_id = intval(isset($_GET['regu_id']) ? $_GET['regu_id'] : 0);

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

// Deteksi tipe armada/regu (misal: 'roda_2', 'roda_3', 'yandal_mobil', 'ulc_motor', atau 'all')
$detected_regu_tipe = isset($regu['regu_tipe']) && !empty($regu['regu_tipe']) && $regu['regu_tipe'] !== 'all' ? $regu['regu_tipe'] : 'all';

if ($detected_regu_tipe === 'all') {
    $regu_info_str = strtolower(($regu['nama_regu'] ?? '') . ' ' . ($regu['kendaraan'] ?? '') . ' ' . ($regu['keterangan'] ?? ''));
    if ($jenis_pekerjaan === 'sr_app') {
        if (strpos($regu_info_str, 'roda 2') !== false || strpos($regu_info_str, 'roda_2') !== false || strpos($regu_info_str, 'r2') !== false) {
            $detected_regu_tipe = 'roda_2';
        } elseif (strpos($regu_info_str, 'roda 3') !== false || strpos($regu_info_str, 'roda_3') !== false || strpos($regu_info_str, 'r3') !== false) {
            $detected_regu_tipe = 'roda_3';
        }
    } elseif ($jenis_pekerjaan === 'yandal') {
        if (strpos($regu_info_str, 'ulc') !== false || strpos($regu_info_str, 'verza') !== false || strpos($regu_info_str, 'motor') !== false) {
            $detected_regu_tipe = 'ulc_motor';
        } else {
            $detected_regu_tipe = 'yandal_mobil';
        }
    } elseif ($jenis_pekerjaan === 'har') {
        if (strpos($regu_info_str, 'carry') !== false || strpos($regu_info_str, 'har b') !== false || strpos($regu_info_str, '8255') !== false) {
            $detected_regu_tipe = 'har_carry';
        } else {
            $detected_regu_tipe = 'har_truk';
        }
    }
}

// Ambil template checklist items (berurutan berdasarkan kategori & urutan)
if ($detected_regu_tipe !== 'all') {
    $stmt_items = $pdo->prepare("
        SELECT tci.*, mb.nama_barang, mb.kategori, mb.satuan_default, mb.gambar_referensi
        FROM template_checklist_item tci
        JOIN master_barang mb ON tci.master_barang_id = mb.id
        WHERE tci.jenis_pekerjaan = :jenis
          AND tci.regu_tipe IN ('all', :regu_tipe)
        ORDER BY mb.kategori, tci.urutan
    ");
    $stmt_items->execute([':jenis' => $jenis_pekerjaan, ':regu_tipe' => $detected_regu_tipe]);
} else {
    $stmt_items = $pdo->prepare("
        SELECT tci.*, mb.nama_barang, mb.kategori, mb.satuan_default, mb.gambar_referensi
        FROM template_checklist_item tci
        JOIN master_barang mb ON tci.master_barang_id = mb.id
        WHERE tci.jenis_pekerjaan = :jenis
        ORDER BY mb.kategori, tci.urutan
    ");
    $stmt_items->execute([':jenis' => $jenis_pekerjaan]);
}
$all_items = $stmt_items->fetchAll();

$items_by_kategori = [];
foreach ($all_items as $item) {
    $items_by_kategori[$item['kategori']][] = $item;
}

// Ambil data pejabat manajemen untuk referensi datalist
$stmt_mgr = $pdo->query("SELECT id, nama_lengkap, jabatan FROM users WHERE role = 'manajemen' ORDER BY nama_lengkap ASC");
$manager_list = $stmt_mgr ? $stmt_mgr->fetchAll() : [];

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
                    Mode Revisi Laporan: <?= htmlspecialchars(isset($edit_header['nomor_dokumen']) ? $edit_header['nomor_dokumen'] : '#' . $edit_id) ?>
                </h3>
                <p style="font-size:0.825rem; color:#b91c1c; margin:2px 0 0;">
                    Laporan ini sebelumnya dikembalikan oleh Manajemen Atasan untuk perbaikan. Silakan sesuaikan data dan kirim ulang.
                </p>
            </div>
        </div>
        <div style="background:#fff; border-radius:6px; padding:10px 14px; border-left:4px solid #ef4444; margin-top:8px;">
            <strong style="font-size:0.8rem; text-transform:uppercase; color:#7f1d1d;">Catatan Arahan dari Manajemen:</strong>
            <p style="margin:4px 0 0; font-size:0.9rem; color:#1e293b;">
                <?= nl2br(htmlspecialchars(isset($edit_header['catatan_manajemen']) ? $edit_header['catatan_manajemen'] : '-')) ?>
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
        <?= $edit_id ? 'Revisi ' : 'Checklist ' ?><?= htmlspecialchars(isset($jenis_label[$jenis_pekerjaan]) ? $jenis_label[$jenis_pekerjaan] : strtoupper($jenis_pekerjaan)) ?>
    </h1>
    <p style="color:var(--text-muted);font-size:0.9rem;">
        <?= htmlspecialchars($regu['nama_regu']) ?>
        <?php if (!empty($regu['nopol'])): ?> &mdash; <strong><?= htmlspecialchars($regu['nopol']) ?></strong><?php endif; ?>
        | <?= htmlspecialchars(isset($regu['kendaraan']) ? $regu['kendaraan'] : '') ?>
        <?php if ($edit_header): ?> &bull; No: <strong><?= htmlspecialchars($edit_header['nomor_dokumen']) ?></strong><?php endif; ?>
    </p>
</div>

<form method="POST" action="simpan.php" id="formGelarAlat" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="jenis_pekerjaan" value="<?= htmlspecialchars($jenis_pekerjaan) ?>">
    <input type="hidden" name="regu_id" value="<?= $regu_id ?>">
    <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
    <input type="hidden" name="action" id="inspeksiAction" value="submit">

    <!-- Banner Pemulihan Draft Lokal Autosave -->
    <div id="autosaveBanner" style="display:none; margin-bottom:1.25rem; background:#eff6ff; border:1px solid #bfdbfe; border-left:4px solid #3b82f6; border-radius:var(--radius-md); padding:12px 16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <i class="fa-solid fa-cloud-arrow-down" style="color:#2563eb; font-size:1.2rem;"></i>
                <div>
                    <strong style="color:#1e40af; font-size:0.875rem;">Draft Tersimpan Otomatis Terdeteksi!</strong>
                    <div style="color:#3b82f6; font-size:0.775rem;" id="autosaveTime">Tersimpan dari sesi sebelumnya di perangkat ini.</div>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" onclick="restoreDraftData()" class="btn btn-sm btn-primary" style="padding:5px 12px; font-size:0.8rem;">
                    <i class="fa-solid fa-rotate-left"></i> Pulihkan Draft
                </button>
                <button type="button" onclick="discardDraftData()" class="btn btn-sm btn-outline" style="padding:5px 10px; font-size:0.8rem; background:#fff;">
                    Abaikan
                </button>
            </div>
        </div>
    </div>

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
                <input type="date" name="tanggal_inspeksi" class="form-control" value="<?= htmlspecialchars(isset($edit_header['tanggal_inspeksi']) ? $edit_header['tanggal_inspeksi'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Bulan / Tahun</label>
                <input type="text" name="bulan_tahun" class="form-control" value="<?= htmlspecialchars(isset($edit_header['bulan_tahun']) ? $edit_header['bulan_tahun'] : date('F Y')) ?>" required>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Nama Pelaksana / Pemeriksa 1 <span style="color:var(--danger);">*</span></label>
                <input type="text" name="nama_pelaksana_1" class="form-control" value="<?= htmlspecialchars(isset($edit_header['nama_pelaksana_1']) ? $edit_header['nama_pelaksana_1'] : ($user ? $user['nama_lengkap'] : '')) ?>" placeholder="Ketik nama lengkap pemeriksa" required>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Nama Pelaksana 2 (jika ada)</label>
                <input type="text" name="nama_pelaksana_2" class="form-control" value="<?= htmlspecialchars(isset($edit_header['nama_pelaksana_2']) ? $edit_header['nama_pelaksana_2'] : '') ?>" placeholder="Opsional">
            </div>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Pendamping / Administrasi (jika ada)</label>
            <input type="text" name="pendamping_admin" class="form-control" value="<?= htmlspecialchars(isset($edit_header['pendamping_admin']) ? $edit_header['pendamping_admin'] : '') ?>" placeholder="Opsional">
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
        <?php $k = isset($kategori_labels[$kategori_key]) ? $kategori_labels[$kategori_key] : ['label' => ucfirst($kategori_key), 'icon' => 'fa-box', 'color' => '#666']; ?>
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
                            $prev_d = isset($existing_details_map[$item['master_barang_id']]) ? $existing_details_map[$item['master_barang_id']] : null;
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
                                    <input type="text" name="items[<?= $item['id'] ?>][keterangan]" class="form-control ket-input" placeholder="Opsional" value="<?= htmlspecialchars($keterangan_val ?? '') ?>" style="font-size:0.8rem;padding:5px 8px;">
                                </td>
                                <td>
                                    <input type="file" name="foto_item_<?= $item['id'] ?>" 
                                        class="foto-item-input" 
                                        accept="image/*" capture="environment"
                                        style="display:none;" id="fotoInput_<?= $item['id'] ?>">
                                    <div style="display:flex; flex-direction:column; align-items:center; gap:3px;">
                                        <button type="button" class="btn btn-outline btn-sm btn-foto-cam" 
                                            onclick="openLiveCamera('<?= $item['id'] ?>', '<?= addslashes(htmlspecialchars($item['nama_barang'])) ?>')"
                                            id="btnFoto_<?= $item['id'] ?>" title="Buka Kamera Langsung">
                                            <i class="fa-solid fa-camera"></i>
                                        </button>
                                        <?php if (!empty($foto_val)): ?>
                                            <div style="margin-top:2px; font-size:0.65rem; color:#10b981;" id="lblFoto_<?= $item['id'] ?>">
                                                <i class="fa-solid fa-check"></i> Ada foto
                                            </div>
                                        <?php endif; ?>
                                    </div>
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
                    $prev_d = isset($existing_details_map[$item['master_barang_id']]) ? $existing_details_map[$item['master_barang_id']] : null;
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
                                <button type="button" class="btn btn-outline btn-sm btn-foto-cam" 
                                    onclick="openLiveCamera('<?= $item['id'] ?>', '<?= addslashes(htmlspecialchars($item['nama_barang'])) ?>')" 
                                    id="btnFotoM_<?= $item['id'] ?>" title="Buka Kamera Langsung">
                                    <i class="fa-solid fa-camera"></i>
                                </button>
                                <?php if (!empty($foto_val)): ?>
                                    <div style="font-size:0.65rem; color:#10b981; margin-top:2px;" id="lblFotoM_<?= $item['id'] ?>">
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
                                value="<?= htmlspecialchars($keterangan_val ?? '') ?>"
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
            <label class="form-label">Foto Bersama Regu / Apel Pasukan</label>
            <?php if (!empty($edit_header['foto_kegiatan'])): ?>
                <div style="margin-bottom:8px; display:flex; align-items:center; gap:10px;" id="boxFotoKegiatanLama">
                    <img src="<?= base_url('uploads/foto_kegiatan/' . htmlspecialchars($edit_header['foto_kegiatan'])) ?>" style="max-height:70px; border-radius:6px; border:1px solid var(--border-color);">
                    <div style="font-size:0.75rem; color:var(--text-muted);">
                        Foto sebelumnya tersimpan. Ambil foto baru jika ingin mengganti.
                        <input type="hidden" name="existing_foto_kegiatan" value="<?= htmlspecialchars($edit_header['foto_kegiatan']) ?>">
                    </div>
                </div>
            <?php endif; ?>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">
                <button type="button" class="btn btn-primary btn-sm" onclick="openLiveCamera('kegiatan', 'Foto Bersama Regu / Apel Pasukan')">
                    <i class="fa-solid fa-camera"></i> Ambil Foto Langsung (Live Camera)
                </button>
                <label class="btn btn-outline btn-sm" style="margin-bottom:0; cursor:pointer;" title="Pilih dari Galeri atau File">
                    <i class="fa-solid fa-folder-open"></i> Ambil dari File
                    <input type="file" name="foto_kegiatan" id="fotoKegiatanInput" accept="image/*" capture="environment" style="display:none;">
                </label>
            </div>
            <div id="kegiatanPreviewWrap" style="display:none; margin-top:8px;">
                <img id="kegiatanPreviewImg" style="max-height:140px; border-radius:8px; border:2px solid #10b981; object-fit:cover;">
                <div id="kegiatanCompressBadge" style="margin-top:4px;"></div>
            </div>
            <small style="color:var(--text-muted);">Foto bersama seluruh anggota regu saat apel gelar alat. Maks 15MB (JPG/PNG/WEBP).</small>
        </div>
    </div>

    <!-- ===== SECTION 5: Lembar Tanda Tangan & Pengesahan Dokumen ===== -->
    <div class="card" style="margin-bottom:1.25rem; border-left: 4px solid var(--navy);">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <h2 class="card-title" style="font-size:1.05rem;">
                <i class="fa-solid fa-signature" style="color:var(--navy);margin-right:6px;"></i> C. Lembar Tanda Tangan & Pengesahan
            </h2>
            <span style="font-size:0.75rem; color:var(--text-muted);">
                <i class="fa-solid fa-circle-info"></i> TTD Manager bersifat opsional (dapat dimintakan fisik/offline saat dicetak)
            </span>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px; margin-top:8px;">
            <!-- ================= PETUGAS PEMERIKSA (WAJIB) ================= -->
            <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:8px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="font-weight:700; font-size:0.95rem; color:var(--text-main);">
                        <i class="fa-solid fa-user-pen" style="color:var(--primary);margin-right:6px;"></i> 1. Petugas Pemeriksa
                    </div>
                    <span class="badge badge-submitted" style="font-size:0.7rem; padding:3px 8px;">Wajib TTD</span>
                </div>

                <?php if (!empty($edit_header['ttd_petugas'])): ?>
                    <div style="margin-bottom:10px; padding:8px 10px; background:#fff; border-radius:6px; border:1px solid var(--border-color); display:flex; align-items:center; gap:10px;">
                        <img src="<?= htmlspecialchars($edit_header['ttd_petugas']) ?>" style="max-height:42px; border:1px solid #cbd5e1; border-radius:4px; padding:2px;">
                        <div style="font-size:0.75rem; color:var(--text-muted);">
                            <strong style="color:var(--text-main);">TTD Petugas tersimpan.</strong><br>
                            Tanda tangani ulang jika ingin mengganti.
                        </div>
                    </div>
                <?php else: ?>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;">
                        Tanda tangan digital pemeriksa lapangan:
                    </p>
                <?php endif; ?>

                <div class="signature-wrapper">
                    <canvas class="signature-canvas" id="canvasPetugas"></canvas>
                </div>
                <div class="signature-actions" style="margin-bottom:12px;">
                    <button type="button" id="clearPetugas" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:4px 10px;">
                        <i class="fa-solid fa-eraser"></i> Hapus Ulang
                    </button>
                </div>
                <input type="hidden" name="ttd_petugas" id="ttdPetugas">

                <div style="display:flex; flex-direction:column; gap:10px; border-top:1px dashed var(--border-color); padding-top:12px;">
                    <div>
                        <label for="nama_petugas_ttd" style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-main);margin-bottom:4px;">
                            Nama Petugas Pemeriksa <span style="color:var(--danger);font-weight:bold;">*</span>
                        </label>
                        <input type="text"
                               id="nama_petugas_ttd"
                               name="nama_pelaksana_1"
                               class="form-control"
                               placeholder="Nama lengkap petugas pemeriksa"
                               value="<?= htmlspecialchars(
                                   isset($edit_header['nama_pelaksana_1']) && !empty($edit_header['nama_pelaksana_1'])
                                       ? $edit_header['nama_pelaksana_1']
                                       : ($user ? $user['nama_lengkap'] : '')
                               ) ?>"
                               style="font-size:0.875rem;"
                               required>
                    </div>
                    <div>
                        <label for="jabatan_petugas_ttd" style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-main);margin-bottom:4px;">
                            Jabatan Petugas Pemeriksa <span style="color:var(--danger);font-weight:bold;">*</span>
                        </label>
                        <input type="text"
                               id="jabatan_petugas_ttd"
                               name="jabatan_petugas"
                               class="form-control"
                               placeholder="Contoh: Petugas Lapangan / Pengawas K3"
                               value="<?= htmlspecialchars(
                                   isset($edit_header['jabatan_petugas']) && !empty($edit_header['jabatan_petugas'])
                                       ? $edit_header['jabatan_petugas']
                                       : ($user ? $user['jabatan'] : '')
                               ) ?>"
                               style="font-size:0.875rem;"
                               required>
                    </div>
                </div>
            </div>

            <!-- ================= MANAGER / ATASAN (OPSIONAL / OFFLINE) ================= -->
            <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:8px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="font-weight:700; font-size:0.95rem; color:var(--text-main);">
                        <i class="fa-solid fa-user-tie" style="color:#0284c7;margin-right:6px;"></i> 2. Manager / Pengesahan Atasan
                    </div>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:0.7rem; padding:3px 8px;">
                        Opsional &bull; Bisa Offline
                    </span>
                </div>

                <?php if (!empty($edit_header['ttd_manajemen'])): ?>
                    <div style="margin-bottom:10px; padding:8px 10px; background:#fff; border-radius:6px; border:1px solid var(--border-color); display:flex; align-items:center; gap:10px;">
                        <img src="<?= htmlspecialchars($edit_header['ttd_manajemen']) ?>" style="max-height:42px; border:1px solid #cbd5e1; border-radius:4px; padding:2px;">
                        <div style="font-size:0.75rem; color:var(--text-muted);">
                            <strong style="color:var(--text-main);">TTD Manager tersimpan.</strong><br>
                            Tanda tangani ulang jika ingin memperbarui.
                        </div>
                    </div>
                <?php else: ?>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;">
                        Bisa TTD langsung atau <em>kosongkan</em> jika akan TTD offline:
                    </p>
                <?php endif; ?>

                <div class="signature-wrapper">
                    <canvas class="signature-canvas" id="canvasManager"></canvas>
                </div>
                <div class="signature-actions" style="margin-bottom:12px;">
                    <button type="button" id="clearManager" class="btn btn-outline btn-sm" style="font-size:0.75rem; padding:4px 10px;">
                        <i class="fa-solid fa-eraser"></i> Hapus Ulang
                    </button>
                </div>
                <input type="hidden" name="ttd_manajemen" id="ttdManajemen" value="<?= htmlspecialchars($edit_header['ttd_manajemen'] ?? '') ?>">

                <div style="display:flex; flex-direction:column; gap:10px; border-top:1px dashed var(--border-color); padding-top:12px;">
                    <div>
                        <label for="nama_manajer_ttd" style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-main);margin-bottom:4px;">
                            Nama Manager / Atasan <span style="font-weight:normal;color:var(--text-muted);font-size:0.75rem;">(Opsional)</span>
                        </label>
                        <input type="text"
                               id="nama_manajer_ttd"
                               name="nama_pejabat_manajemen"
                               list="managerOptions"
                               class="form-control"
                               placeholder="Pilih atau ketik nama Manager..."
                               value="<?= htmlspecialchars(
                                   isset($edit_header['nama_pejabat_manajemen']) && !empty($edit_header['nama_pejabat_manajemen'])
                                       ? $edit_header['nama_pejabat_manajemen']
                                       : ($user && $user['role'] === 'manajemen' ? $user['nama_lengkap'] : '')
                               ) ?>"
                               style="font-size:0.875rem;"
                               onchange="onSelectManager(this.value)">
                        <datalist id="managerOptions">
                            <?php foreach ($manager_list as $mgr): ?>
                                <option value="<?= htmlspecialchars($mgr['nama_lengkap']) ?>" data-jabatan="<?= htmlspecialchars($mgr['jabatan']) ?>">
                                    <?= htmlspecialchars($mgr['jabatan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div>
                        <label for="jabatan_manajer_ttd" style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-main);margin-bottom:4px;">
                            Jabatan Manager / Atasan <span style="font-weight:normal;color:var(--text-muted);font-size:0.75rem;">(Opsional)</span>
                        </label>
                        <input type="text"
                               id="jabatan_manajer_ttd"
                               name="jabatan_manajemen"
                               class="form-control"
                               placeholder="Contoh: Manager Bagian Jaringan / Team Leader K3L"
                               value="<?= htmlspecialchars(
                                   isset($edit_header['jabatan_manajemen']) && !empty($edit_header['jabatan_manajemen'])
                                       ? $edit_header['jabatan_manajemen']
                                       : ($user && $user['role'] === 'manajemen' ? $user['jabatan'] : '')
                               ) ?>"
                               style="font-size:0.875rem;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 6: Catatan Umum ===== -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h2 class="card-title" style="font-size:1.05rem;">
                <i class="fa-solid fa-note-sticky" style="color:var(--accent);margin-right:6px;"></i> D. Catatan Umum
            </h2>
        </div>
        <textarea name="catatan_umum" class="form-control" rows="3" placeholder="Catatan umum hasil gelar alat, temuan K3, atau tindak lanjut yang perlu diketahui..."><?= htmlspecialchars(isset($edit_header['catatan_umum']) ? $edit_header['catatan_umum'] : '') ?></textarea>
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

<script src="<?= base_url('assets/js/image-compress.js') ?>"></script>
<script>
const DRAFT_KEY = 'sigap_draft_<?= $jenis_pekerjaan ?>_<?= $regu_id ?>';
const IS_EDIT_MODE = <?= $edit_id > 0 ? 'true' : 'false' ?>;

// ========================================
// Init Signature Pad & Handlers
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('canvasPetugas');
    const clearBtn = document.getElementById('clearPetugas');
    const hiddenInput = document.getElementById('ttdPetugas');
    window.sigPad = new SimpleSignaturePad(canvas, clearBtn, hiddenInput);

    // Inisialisasi TTD Manager (Opsional)
    const canvasMgr = document.getElementById('canvasManager');
    const clearBtnMgr = document.getElementById('clearManager');
    const hiddenInputMgr = document.getElementById('ttdManajemen');
    if (canvasMgr && clearBtnMgr && hiddenInputMgr) {
        window.sigPadManager = new SimpleSignaturePad(canvasMgr, clearBtnMgr, hiddenInputMgr);
    }

    // ========================================
    // Cek & Tampilkan Autosave Draft Lokal
    // ========================================
    if (!IS_EDIT_MODE) {
        checkLocalDraft();
    }

    // Pasang Autosave Listener (Debounced)
    let autosaveTimeout = null;
    document.getElementById('formGelarAlat').addEventListener('input', function() {
        clearTimeout(autosaveTimeout);
        autosaveTimeout = setTimeout(saveLocalDraft, 800);
    });
    document.getElementById('formGelarAlat').addEventListener('change', function() {
        clearTimeout(autosaveTimeout);
        autosaveTimeout = setTimeout(saveLocalDraft, 400);
    });

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
        saveLocalDraft();
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
    // Preview & Kompresi Foto Temuan (Client-Side)
    // ========================================
    document.querySelectorAll('input[type="file"][name^="foto_item_"]').forEach(input => {
        input.addEventListener('change', async function() {
            const itemId = this.id.replace('fotoInput_', '').replace('fotoInputM_', '');
            const card = document.getElementById('card_' + itemId);
            const row = document.getElementById('row_' + itemId);
            if (this.files && this.files[0]) {
                const origFile = this.files[0];
                const origSizeKb = Math.round(origFile.size / 1024);
                const url = URL.createObjectURL(origFile);

                [card, row].forEach(parent => {
                    if (!parent) return;

                    // Update / Buat Badge Status
                    let badge = parent.querySelector('.compress-badge');
                    if (!badge) {
                        badge = document.createElement('div');
                        badge.className = 'compress-badge';
                        badge.style.cssText = 'font-size:0.68rem; color:#0369a1; background:#e0f2fe; padding:2px 6px; border-radius:4px; margin-top:3px; display:inline-block; font-weight:600;';
                        parent.appendChild(badge);
                    }
                    badge.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengoptimalkan...';

                    // Update / Buat Thumbnail Preview
                    let prevEl = parent.querySelector('.foto-preview');
                    if (!prevEl) {
                        prevEl = document.createElement('img');
                        prevEl.className = 'foto-preview';
                        prevEl.style.cssText = 'width:44px; height:44px; object-fit:cover; border-radius:6px; border:2px solid #10b981; margin-top:4px; cursor:pointer; display:block;';
                        prevEl.title = 'Klik perbesar foto';
                        prevEl.onclick = function() { window.open(this.src, '_blank'); };
                        parent.appendChild(prevEl);
                    }
                    prevEl.src = url;

                    // Update Tombol Kamera Menjadi Hijau Aktif
                    const camBtn = parent.querySelector('.btn-foto-cam') || parent.querySelector('button[title*="Kamera"]');
                    if (camBtn) {
                        camBtn.style.background = '#10b981';
                        camBtn.style.color = '#ffffff';
                        camBtn.style.borderColor = '#10b981';
                        camBtn.title = 'Foto Terambil - Klik untuk Mengulang';
                        camBtn.innerHTML = '<i class="fa-solid fa-camera"></i> <i class="fa-solid fa-check" style="font-size:0.7rem; margin-left:2px;"></i>';
                    }
                });

                try {
                    const compressed = await window.ImageCompressor.compress(origFile, { maxDimension: 1280, quality: 0.8 });
                    const dt = new DataTransfer();
                    dt.items.add(compressed);
                    this.files = dt.files;
                    const compSizeKb = Math.round(compressed.size / 1024);
                    [card, row].forEach(parent => {
                        if (!parent) return;
                        const badge = parent.querySelector('.compress-badge');
                        if (badge) badge.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#10b981;"></i> ${origSizeKb}KB &rarr; ${compSizeKb}KB`;
                    });
                } catch(e) {
                    [card, row].forEach(parent => {
                        if (!parent) return;
                        const badge = parent.querySelector('.compress-badge');
                        if (badge) badge.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#10b981;"></i> Foto siap upload`;
                    });
                }
            }
        });
    });

    // ========================================
    // Kompresi Foto Kegiatan Apel
    // ========================================
    const fotoKegiatanInput = document.getElementById('fotoKegiatanInput');
    if (fotoKegiatanInput) {
        fotoKegiatanInput.addEventListener('change', async function() {
            if (this.files && this.files[0]) {
                const origFile = this.files[0];
                const origSizeMb = (origFile.size / (1024*1024)).toFixed(1);
                const wrap = document.getElementById('kegiatanPreviewWrap');
                const img = document.getElementById('kegiatanPreviewImg');
                const badge = document.getElementById('kegiatanCompressBadge');
                
                if (wrap && img) {
                    img.src = URL.createObjectURL(origFile);
                    wrap.style.display = 'block';
                }
                if (badge) {
                    badge.innerHTML = '<span style="font-size:0.75rem; color:#0369a1; background:#e0f2fe; padding:2px 8px; border-radius:4px;"><i class="fa-solid fa-spinner fa-spin"></i> Mengoptimalkan...</span>';
                }

                try {
                    const compressed = await window.ImageCompressor.compress(origFile, { maxDimension: 1600, quality: 0.82 });
                    const dt = new DataTransfer();
                    dt.items.add(compressed);
                    this.files = dt.files;
                    const compSizeKb = Math.round(compressed.size / 1024);
                    if (badge) badge.innerHTML = `<span style="font-size:0.75rem; color:#047857; background:#d1fae5; padding:2px 8px; border-radius:4px; font-weight:600;"><i class="fa-solid fa-circle-check"></i> Foto dioptimalkan (${origSizeMb}MB &rarr; ${compSizeKb}KB)</span>`;
                } catch(e) {
                    if (badge) badge.innerHTML = `<span style="font-size:0.75rem; color:#047857; background:#d1fae5; padding:2px 8px; border-radius:4px; font-weight:600;"><i class="fa-solid fa-circle-check"></i> Foto siap diunggah</span>`;
                }
            }
        });
    }
});

// ========================================
// Autosave LocalStorage Logic
// ========================================
function saveLocalDraft() {
    if (IS_EDIT_MODE) return;
    const form = document.getElementById('formGelarAlat');
    if (!form) return;

    const draft = {
        savedAt: new Date().toISOString(),
        pelaksana1: form.querySelector('[name="nama_pelaksana_1"]')?.value || '',
        pelaksana2: form.querySelector('[name="nama_pelaksana_2"]')?.value || '',
        pendamping: form.querySelector('[name="pendamping_admin"]')?.value || '',
        catatanUmum: form.querySelector('[name="catatan_umum"]')?.value || '',
        items: {}
    };

    // Ambil semua radio kondisi & realisasi & keterangan
    form.querySelectorAll('.cond-radio:checked').forEach(radio => {
        const id = radio.dataset.itemId;
        if (!draft.items[id]) draft.items[id] = {};
        draft.items[id].kondisi = radio.value;
    });

    form.querySelectorAll('.input-realisasi').forEach(input => {
        const name = input.name;
        const match = name.match(/items\[(\d+)\]/);
        if (match) {
            const id = match[1];
            if (!draft.items[id]) draft.items[id] = {};
            draft.items[id].realisasi = input.value;
        }
    });

    form.querySelectorAll('.ket-input').forEach(input => {
        const name = input.name;
        const match = name.match(/items\[(\d+)\]/);
        if (match) {
            const id = match[1];
            if (!draft.items[id]) draft.items[id] = {};
            draft.items[id].keterangan = input.value;
        }
    });

    try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    } catch(e) {}
}

function checkLocalDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const draft = JSON.parse(raw);
        if (!draft || !draft.savedAt) return;

        const date = new Date(draft.savedAt);
        const timeStr = date.toLocaleDateString('id-ID', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' });
        document.getElementById('autosaveTime').textContent = `Tersimpan otomatis pada ${timeStr}`;
        document.getElementById('autosaveBanner').style.display = 'block';
    } catch(e) {}
}

function restoreDraftData() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const draft = JSON.parse(raw);
        const form = document.getElementById('formGelarAlat');

        if (draft.pelaksana1) form.querySelector('[name="nama_pelaksana_1"]').value = draft.pelaksana1;
        if (draft.pelaksana2) form.querySelector('[name="nama_pelaksana_2"]').value = draft.pelaksana2;
        if (draft.pendamping) form.querySelector('[name="pendamping_admin"]').value = draft.pendamping;
        if (draft.catatanUmum) form.querySelector('[name="catatan_umum"]').value = draft.catatanUmum;

        if (draft.items) {
            Object.keys(draft.items).forEach(id => {
                const item = draft.items[id];
                if (item.kondisi) {
                    const radios = form.querySelectorAll(`.cond-radio[data-item-id="${id}"][value="${item.kondisi}"]`);
                    radios.forEach(r => { r.checked = true; });
                    const ket = document.getElementById('ketWrapper_' + id);
                    if (ket) {
                        ket.style.display = (item.kondisi === 'rusak' || item.kondisi === 'waktu_ganti') ? 'block' : 'none';
                    }
                }
                if (item.realisasi !== undefined) {
                    const real = form.querySelector(`[name="items[${id}][realisasi]"]`);
                    if (real) real.value = item.realisasi;
                }
                if (item.keterangan !== undefined) {
                    const ketInput = form.querySelector(`[name="items[${id}][keterangan]"]`);
                    if (ketInput) ketInput.value = item.keterangan;
                }
            });
        }

        document.getElementById('autosaveBanner').style.display = 'none';
        alert('Draft pemeriksaan berhasil dipulihkan!');
    } catch(e) {
        alert('Gagal memulihkan draft.');
    }
}

    document.getElementById('autosaveBanner').style.display = 'none';
}

function onSelectManager(nama) {
    try {
        const option = document.querySelector(`#managerOptions option[value="${CSS.escape(nama)}"]`);
        if (option && option.dataset && option.dataset.jabatan) {
            const jabInput = document.getElementById('jabatan_manajer_ttd');
            if (jabInput) jabInput.value = option.dataset.jabatan;
        }
    } catch(e) {}
}

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
        // Simpan TTD Manager jika ada (opsional / tidak dipaksakan)
        if (window.sigPadManager && !window.sigPadManager.isEmpty()) {
            window.sigPadManager.updateInput();
        }
        if (!confirm('Apakah Anda yakin data hasil pemeriksaan sudah benar dan siap dikirimkan ke Manajemen Atasan?')) {
            return;
        }
    }

    // Bersihkan draft lokal setelah kirim
    try {
        localStorage.removeItem(DRAFT_KEY);
    } catch(e) {}

    const btn = document.getElementById('btnSubmit');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (action === 'submit' ? 'Mengirim...' : 'Menyimpan...');
    btn.style.pointerEvents = 'none';
    document.getElementById('formGelarAlat').submit();
}

// ========================================
// Fitur Kamera Langsung (Live Camera API)
// ========================================
let currentTargetId = null;
let activeCamStream = null;
let currentFacingMode = 'environment'; // default kamera belakang HP

async function openLiveCamera(targetId, targetTitle) {
    currentTargetId = targetId;
    document.getElementById('liveCamTitle').textContent = targetTitle || 'Ambil Foto';
    document.getElementById('modalLiveCam').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    retakeLiveSnapshot();
    await startCameraStream();
}

function closeLiveCamera() {
    stopCurrentStream();
    document.getElementById('modalLiveCam').style.display = 'none';
    document.body.style.overflow = '';
    retakeLiveSnapshot();
}

function stopCurrentStream() {
    if (activeCamStream) {
        try {
            activeCamStream.getTracks().forEach(track => track.stop());
        } catch(e) {}
        activeCamStream = null;
    }
}

async function startCameraStream() {
    stopCurrentStream();
    const loading = document.getElementById('liveCamLoading');
    const errBox = document.getElementById('liveCamError');
    const video = document.getElementById('liveCamStream');
    
    loading.style.display = 'flex';
    errBox.style.display = 'none';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        loading.style.display = 'none';
        errBox.style.display = 'flex';
        document.getElementById('liveCamErrorMsg').textContent = "Browser Tidak Mendukung Kamera Langsung";
        return;
    }

    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(d => d.kind === 'videoinput');
        if (videoDevices.length > 1) {
            document.getElementById('btnSwitchCam').style.display = 'flex';
        } else {
            document.getElementById('btnSwitchCam').style.display = 'none';
        }
    } catch(e) {}

    try {
        const constraints = {
            video: {
                facingMode: { ideal: currentFacingMode },
                width: { ideal: 1920 },
                height: { ideal: 1080 }
            },
            audio: false
        };
        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        activeCamStream = stream;
        video.srcObject = stream;
        video.onloadedmetadata = function() {
            video.play();
            loading.style.display = 'none';
        };
    } catch (err) {
        console.warn("Primary camera constraint failed, attempting fallback:", err);
        try {
            const fallbackStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            activeCamStream = fallbackStream;
            video.srcObject = fallbackStream;
            video.onloadedmetadata = function() {
                video.play();
                loading.style.display = 'none';
            };
        } catch (err2) {
            loading.style.display = 'none';
            errBox.style.display = 'flex';
            document.getElementById('liveCamErrorMsg').textContent = "Izin Kamera Ditolak / Kamera Tidak Tersedia";
        }
    }
}

async function toggleFacingCamera() {
    currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
    const btn = document.getElementById('btnSwitchCam');
    btn.style.transform = 'rotate(180deg)';
    setTimeout(() => { btn.style.transform = 'rotate(0deg)'; }, 300);
    await startCameraStream();
}

function captureLiveSnapshot() {
    if (!activeCamStream) return;
    const video = document.getElementById('liveCamStream');
    const canvas = document.getElementById('liveCamCanvas');
    const imgPreview = document.getElementById('liveCamSnapshot');
    const flash = document.getElementById('liveCamFlash');

    // Visual Flash & Haptic Vibration Feedback
    flash.style.opacity = '0.9';
    setTimeout(() => { flash.style.opacity = '0'; }, 120);

    if (navigator.vibrate) {
        try { navigator.vibrate(50); } catch(e) {}
    }

    const width = video.videoWidth || 1280;
    const height = video.videoHeight || 720;
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    if (currentFacingMode === 'user') {
        ctx.translate(width, 0);
        ctx.scale(-1, 1);
    }
    ctx.drawImage(video, 0, 0, width, height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    imgPreview.src = dataUrl;
    imgPreview.style.display = 'block';
    video.style.display = 'none';

    document.getElementById('liveCamGrid').style.display = 'none';
    document.getElementById('btnSwitchCam').style.display = 'none';
    document.getElementById('liveCamControlsStreaming').style.display = 'none';
    document.getElementById('liveCamControlsReview').style.display = 'flex';
}

function retakeLiveSnapshot() {
    const video = document.getElementById('liveCamStream');
    const imgPreview = document.getElementById('liveCamSnapshot');
    
    imgPreview.style.display = 'none';
    video.style.display = 'block';
    document.getElementById('liveCamGrid').style.display = 'block';
    document.getElementById('liveCamControlsStreaming').style.display = 'flex';
    document.getElementById('liveCamControlsReview').style.display = 'none';

    navigator.mediaDevices?.enumerateDevices().then(devices => {
        const videoDevices = devices.filter(d => d.kind === 'videoinput');
        if (videoDevices.length > 1) {
            document.getElementById('btnSwitchCam').style.display = 'flex';
        }
    }).catch(() => {});
}

function acceptLiveSnapshot() {
    const canvas = document.getElementById('liveCamCanvas');
    if (!canvas || !canvas.width) return;

    canvas.toBlob(async function(blob) {
        if (!blob) return;
        const fileName = (currentTargetId === 'kegiatan') ? 'foto_kegiatan.jpg' : `foto_item_${currentTargetId}.jpg`;
        const file = new File([blob], fileName, { type: 'image/jpeg', lastModified: Date.now() });

        if (currentTargetId === 'kegiatan') {
            const dt = new DataTransfer();
            dt.items.add(file);
            const inputKegiatan = document.getElementById('fotoKegiatanInput');
            if (inputKegiatan) {
                inputKegiatan.files = dt.files;
                inputKegiatan.dispatchEvent(new Event('change', { bubbles: true }));
            }
        } else {
            const dt1 = new DataTransfer();
            dt1.items.add(file);
            const inpDesktop = document.getElementById('fotoInput_' + currentTargetId);
            if (inpDesktop) {
                inpDesktop.files = dt1.files;
                inpDesktop.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const dt2 = new DataTransfer();
            dt2.items.add(file);
            const inpMobile = document.getElementById('fotoInputM_' + currentTargetId);
            if (inpMobile) {
                inpMobile.files = dt2.files;
                inpMobile.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        closeLiveCamera();
    }, 'image/jpeg', 0.88);
}

function triggerNativeFileInput() {
    closeLiveCamera();
    if (currentTargetId === 'kegiatan') {
        const inp = document.getElementById('fotoKegiatanInput');
        if (inp) inp.click();
    } else {
        const inpDesktop = document.getElementById('fotoInput_' + currentTargetId);
        const inpMobile = document.getElementById('fotoInputM_' + currentTargetId);
        if (inpDesktop && window.innerWidth >= 992) {
            inpDesktop.click();
        } else if (inpMobile) {
            inpMobile.click();
        } else if (inpDesktop) {
            inpDesktop.click();
        }
    }
}

// Tutup live camera dengan tombol ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('modalLiveCam');
        if (modal && modal.style.display === 'flex') {
            closeLiveCamera();
        }
    }
});
</script>

<!-- ===== Modal Live Camera Universal ===== -->
<div id="modalLiveCam" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(15, 23, 42, 0.94); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); align-items:center; justify-content:center; padding:12px;">
    <div style="background:#0f172a; border:1px solid #334155; border-radius:20px; width:100%; max-width:540px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); display:flex; flex-direction:column;">
        
        <!-- Modal Header -->
        <div style="padding:14px 18px; border-bottom:1px solid #1e293b; display:flex; justify-content:space-between; align-items:center; color:#f8fafc; background:#0f172a;">
            <div style="display:flex; align-items:center; gap:10px; overflow:hidden;">
                <div style="width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg, #2563eb, #1d4ed8); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="fa-solid fa-camera" style="color:white; font-size:1rem;"></i>
                </div>
                <div style="overflow:hidden;">
                    <div style="font-size:0.7rem; text-transform:uppercase; color:#94a3b8; font-weight:700; letter-spacing:0.5px;">Kamera Langsung</div>
                    <div id="liveCamTitle" style="font-size:0.95rem; font-weight:700; color:#ffffff; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">Ambil Foto</div>
                </div>
            </div>
            <button type="button" onclick="closeLiveCamera()" style="background:transparent; border:none; color:#94a3b8; font-size:1.6rem; cursor:pointer; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; line-height:1;" title="Tutup Kamera">&times;</button>
        </div>

        <!-- Viewfinder Viewport -->
        <div style="position:relative; width:100%; background:#000000; display:flex; align-items:center; justify-content:center; min-height:340px; max-height:62vh; overflow:hidden;">
            <!-- Live Video -->
            <video id="liveCamStream" autoplay playsinline muted style="width:100%; height:100%; max-height:62vh; object-fit:cover; display:block;"></video>
            
            <!-- Snapshot Preview Image (Hidden when streaming) -->
            <img id="liveCamSnapshot" style="display:none; width:100%; height:100%; max-height:62vh; object-fit:contain; background:#000;" alt="Hasil Foto">
            
            <!-- Hidden Canvas for capture -->
            <canvas id="liveCamCanvas" style="display:none;"></canvas>

            <!-- Loading overlay -->
            <div id="liveCamLoading" style="position:absolute; inset:0; background:rgba(15,23,42,0.85); display:flex; flex-direction:column; align-items:center; justify-content:center; color:#fff; gap:12px; z-index:2;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size:2.2rem; color:#3b82f6;"></i>
                <span style="font-size:0.875rem; font-weight:500;">Membuka kamera langsung...</span>
            </div>

            <!-- Error fallback overlay -->
            <div id="liveCamError" style="display:none; position:absolute; inset:0; background:#0f172a; padding:24px; text-align:center; flex-direction:column; align-items:center; justify-content:center; color:#fff; gap:14px; z-index:3;">
                <div style="width:54px; height:54px; border-radius:50%; background:rgba(245,158,11,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size:1.8rem; color:#f59e0b;"></i>
                </div>
                <div style="font-weight:700; font-size:1rem;" id="liveCamErrorMsg">Akses Kamera Tidak Tersedia</div>
                <p style="font-size:0.825rem; color:#94a3b8; max-width:340px; margin:0 auto; line-height:1.5;">Browser membutuhkan izin kamera, atau perangkat tidak memiliki kamera yang aktif. Anda dapat langsung memilih foto dari galeri atau kamera sistem.</p>
                <button type="button" class="btn btn-primary btn-sm" onclick="triggerNativeFileInput()" style="padding:8px 18px; font-weight:600;">
                    <i class="fa-solid fa-folder-open"></i> Buka Kamera / File Sistem
                </button>
            </div>

            <!-- Viewfinder Grid & Corner Guides -->
            <div id="liveCamGrid" style="position:absolute; inset:0; pointer-events:none; border:2px dashed rgba(255,255,255,0.18); margin:16px; border-radius:12px;">
                <div style="position:absolute; top:8px; left:8px; background:rgba(239,68,68,0.92); color:#fff; font-size:0.65rem; font-weight:700; padding:3px 9px; border-radius:20px; display:flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(0,0,0,0.4); letter-spacing:0.5px;">
                    <span style="width:7px; height:7px; border-radius:50%; background:#fff; display:inline-block; animation:liveCamBlink 1s infinite;"></span>
                    LIVE KAMERA
                </div>
            </div>

            <!-- Switch Camera Button (rear/front toggle) -->
            <button type="button" id="btnSwitchCam" onclick="toggleFacingCamera()" title="Putar Kamera" style="position:absolute; top:16px; right:16px; width:42px; height:42px; border-radius:50%; background:rgba(15,23,42,0.7); border:1px solid rgba(255,255,255,0.3); color:#fff; font-size:1.15rem; display:none; align-items:center; justify-content:center; cursor:pointer; backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); transition:transform 0.2s; z-index:4;">
                <i class="fa-solid fa-camera-rotate"></i>
            </button>

            <!-- White Flash Overlay -->
            <div id="liveCamFlash" style="position:absolute; inset:0; background:white; opacity:0; pointer-events:none; transition:opacity 0.12s ease-out; z-index:5;"></div>
        </div>

        <!-- Controls Footer -->
        <div style="padding:16px 20px; background:#0f172a; border-top:1px solid #1e293b;">
            <!-- Streaming State: Shutter -->
            <div id="liveCamControlsStreaming" style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
                <button type="button" onclick="triggerNativeFileInput()" class="btn btn-outline btn-sm" style="color:#94a3b8; border-color:#334155; background:transparent; font-size:0.8rem; padding:8px 14px;" title="Ambil dari Galeri atau File">
                    <i class="fa-solid fa-folder-open"></i> File / Galeri
                </button>

                <!-- Big Shutter Button -->
                <button type="button" id="btnLiveCamShutter" onclick="captureLiveSnapshot()" title="Jepret Foto" style="width:70px; height:70px; border-radius:50%; background:#ffffff; border:4px solid #3b82f6; box-shadow:0 0 0 4px rgba(59,130,246,0.25), 0 8px 18px rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; cursor:pointer; outline:none; transition:transform 0.1s, box-shadow 0.1s;">
                    <span style="width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg, #ef4444, #dc2626); display:flex; align-items:center; justify-content:center; color:white; font-size:1.35rem; box-shadow:inset 0 2px 4px rgba(0,0,0,0.2);">
                        <i class="fa-solid fa-camera"></i>
                    </span>
                </button>

                <button type="button" onclick="closeLiveCamera()" class="btn btn-outline btn-sm" style="color:#94a3b8; border-color:#334155; background:transparent; font-size:0.8rem; padding:8px 14px;">
                    Batal
                </button>
            </div>

            <!-- Review State: Retake / Accept -->
            <div id="liveCamControlsReview" style="display:none; align-items:center; justify-content:center; gap:12px;">
                <button type="button" onclick="retakeLiveSnapshot()" class="btn btn-outline" style="color:#f8fafc; border-color:#475569; background:#1e293b; padding:10px 18px; font-weight:600; font-size:0.9rem;">
                    <i class="fa-solid fa-rotate-left"></i> Foto Ulang
                </button>
                <button type="button" onclick="acceptLiveSnapshot()" class="btn btn-primary" style="background:#10b981; border-color:#10b981; padding:10px 22px; font-weight:700; font-size:0.95rem; box-shadow:0 4px 14px rgba(16,185,129,0.35);">
                    <i class="fa-solid fa-circle-check"></i> Gunakan Foto Ini
                </button>
            </div>
        </div>

    </div>
</div>

<style>
@keyframes liveCamBlink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.25; }
}
#btnLiveCamShutter:active {
    transform: scale(0.92);
}
.btn-foto-cam {
    transition: all 0.2s ease;
}
.btn-foto-cam:hover {
    transform: scale(1.05);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
