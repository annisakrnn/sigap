<?php
/**
 * Master Data: Template Checklist Item per Jenis Pekerjaan
 * SIGAP - Role: Manajemen
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_role('manajemen');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../../setup.php"); exit; }

$user = get_logged_user();

// Handle POST (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $jenis_pekerjaan  = trim($_POST['jenis_pekerjaan'] ?? '');
        $regu_tipe        = trim($_POST['regu_tipe'] ?? 'all');
        $master_barang_id = intval($_POST['master_barang_id'] ?? 0);
        $jumlah_standar   = max(1, intval($_POST['jumlah_standar'] ?? 1));
        $urutan           = intval($_POST['urutan'] ?? 0);

        if (empty($jenis_pekerjaan) || $master_barang_id <= 0) {
            set_flash('error', 'Jenis pekerjaan dan nama barang wajib dipilih.');
        } else {
            // Cek apakah barang sudah ada di template jenis pekerjaan tersebut
            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM template_checklist_item WHERE jenis_pekerjaan = :jenis AND master_barang_id = :bid AND regu_tipe = :tipe");
            $stmt_cek->execute([
                ':jenis' => $jenis_pekerjaan,
                ':bid'   => $master_barang_id,
                ':tipe'  => $regu_tipe
            ]);
            if ($stmt_cek->fetchColumn() > 0) {
                set_flash('error', 'Barang ini sudah ada dalam template checklist untuk jenis pekerjaan dan tipe regu tersebut.');
            } else {
                // Tentukan urutan otomatis jika 0
                if ($urutan <= 0) {
                    $stmt_max = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 FROM template_checklist_item WHERE jenis_pekerjaan = :jenis");
                    $stmt_max->execute([':jenis' => $jenis_pekerjaan]);
                    $urutan = (int)$stmt_max->fetchColumn();
                }

                $stmt = $pdo->prepare("INSERT INTO template_checklist_item (jenis_pekerjaan, regu_tipe, master_barang_id, jumlah_standar, urutan) VALUES (:jenis, :tipe, :bid, :jml, :urutan)");
                $stmt->execute([
                    ':jenis'  => $jenis_pekerjaan,
                    ':tipe'   => $regu_tipe,
                    ':bid'    => $master_barang_id,
                    ':jml'    => $jumlah_standar,
                    ':urutan' => $urutan
                ]);
                set_flash('success', 'Item berhasil ditambahkan ke template checklist.');
            }
        }
        header("Location: index.php?jenis=" . urlencode($jenis_pekerjaan));
        exit;
    }

    if ($action === 'update') {
        $id               = intval($_POST['id'] ?? 0);
        $jenis_pekerjaan  = trim($_POST['jenis_pekerjaan'] ?? '');
        $regu_tipe        = trim($_POST['regu_tipe'] ?? 'all');
        $master_barang_id = intval($_POST['master_barang_id'] ?? 0);
        $jumlah_standar   = max(1, intval($_POST['jumlah_standar'] ?? 1));
        $urutan           = intval($_POST['urutan'] ?? 1);

        if ($id <= 0 || empty($jenis_pekerjaan) || $master_barang_id <= 0) {
            set_flash('error', 'Data tidak valid.');
        } else {
            // Cek duplikasi dengan item lain
            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM template_checklist_item WHERE jenis_pekerjaan = :jenis AND master_barang_id = :bid AND regu_tipe = :tipe AND id != :id");
            $stmt_cek->execute([
                ':jenis' => $jenis_pekerjaan,
                ':bid'   => $master_barang_id,
                ':tipe'  => $regu_tipe,
                ':id'    => $id
            ]);
            if ($stmt_cek->fetchColumn() > 0) {
                set_flash('error', 'Barang ini sudah ada dalam template checklist untuk jenis pekerjaan dan tipe regu tersebut.');
            } else {
                $stmt = $pdo->prepare("UPDATE template_checklist_item SET jenis_pekerjaan = :jenis, regu_tipe = :tipe, master_barang_id = :bid, jumlah_standar = :jml, urutan = :urutan WHERE id = :id");
                $stmt->execute([
                    ':id'     => $id,
                    ':jenis'  => $jenis_pekerjaan,
                    ':tipe'   => $regu_tipe,
                    ':bid'    => $master_barang_id,
                    ':jml'    => $jumlah_standar,
                    ':urutan' => $urutan
                ]);
                set_flash('success', 'Item template checklist berhasil diperbarui.');
            }
        }
        header("Location: index.php?jenis=" . urlencode($jenis_pekerjaan));
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $redirect_jenis = $_POST['jenis_pekerjaan'] ?? 'p2tl';
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM template_checklist_item WHERE id = :id");
            $stmt->execute([':id' => $id]);
            set_flash('success', 'Item berhasil dihapus dari template checklist.');
        }
        header("Location: index.php?jenis=" . urlencode($redirect_jenis));
        exit;
    }
}

// Active tab jenis pekerjaan
$active_jenis = $_GET['jenis'] ?? 'p2tl';
if (!in_array($active_jenis, ['p2tl', 'sr_app', 'yandal', 'har'])) {
    $active_jenis = 'p2tl';
}

$search = trim($_GET['q'] ?? '');

// Ambil daftar barang untuk dropdown modal
$semua_barang = $pdo->query("SELECT id, nama_barang, kategori, satuan_default FROM master_barang ORDER BY kategori, nama_barang")->fetchAll();

// Hitung total item per jenis pekerjaan
$counts_stmt = $pdo->query("SELECT jenis_pekerjaan, COUNT(*) as total FROM template_checklist_item GROUP BY jenis_pekerjaan");
$counts_raw = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$tab_counts = [
    'p2tl'   => $counts_raw['p2tl'] ?? 0,
    'sr_app' => $counts_raw['sr_app'] ?? 0,
    'yandal' => $counts_raw['yandal'] ?? 0,
    'har'    => $counts_raw['har'] ?? 0,
];

// Query template checklist untuk tab aktif
$where = ["tci.jenis_pekerjaan = :jenis"];
$params = [':jenis' => $active_jenis];

if (!empty($search)) {
    $where[] = "(mb.nama_barang LIKE :q OR mb.satuan_default LIKE :q)";
    $params[':q'] = "%$search%";
}

$where_sql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT tci.*, mb.nama_barang, mb.kategori, mb.satuan_default, mb.gambar_referensi
    FROM template_checklist_item tci
    JOIN master_barang mb ON tci.master_barang_id = mb.id
    WHERE {$where_sql}
    ORDER BY mb.kategori, tci.urutan, mb.nama_barang
");
$stmt->execute($params);
$items = $stmt->fetchAll();

$kategori_labels = [
    'alat_kerja'          => ['label' => 'Alat Kerja', 'badge' => 'badge-submitted', 'icon' => 'fa-toolbox'],
    'k3_safety'           => ['label' => 'K3 & APD', 'badge' => 'badge-rejected', 'icon' => 'fa-hard-hat'],
    'kendaraan_pendukung' => ['label' => 'Kendaraan Pendukung', 'badge' => 'badge-draft', 'icon' => 'fa-truck'],
    'administrasi'        => ['label' => 'Administrasi', 'badge' => 'badge-approved', 'icon' => 'fa-folder-closed']
];

$jenis_titles = [
    'p2tl'   => 'P2TL (Penertiban Pemakaian Tenaga Listrik)',
    'sr_app' => 'SR APP 1 Phasa (Penyambungan & Bongkar)',
    'yandal' => 'YANDAL & ULC (Pelayanan Gangguan)',
    'har'    => 'HAR (Pemeliharaan Jaringan & Gardu)',
];

$page_title = "Template Checklist - " . strtoupper($active_jenis);
require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.75rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:4px; color:var(--text-main);">Template Checklist Peralatan</h1>
        <p style="color:var(--text-muted); font-size:0.9rem;">Atur standar daftar alat & perlengkapan yang wajib diperiksa pada form gelar alat untuk setiap jenis pekerjaan.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="<?= base_url('master/barang/index.php') ?>" class="btn btn-outline">
            <i class="fa-solid fa-toolbox"></i> Buka Katalog Alat
        </a>
        <button onclick="openModalTambah()" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Tambah Item Template
        </button>
    </div>
</div>

<!-- Tabs Navigasi Jenis Pekerjaan -->
<div style="display:flex; gap:8px; margin-bottom:1.5rem; overflow-x:auto; padding-bottom:4px; border-bottom:2px solid var(--border-color);">
    <a href="index.php?jenis=p2tl" class="btn <?= $active_jenis === 'p2tl' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:var(--radius-md) var(--radius-md) 0 0; padding:10px 18px; font-weight:600; text-decoration:none;">
        <i class="fa-solid fa-bolt"></i> P2TL
        <span class="badge" style="margin-left:6px; background:<?= $active_jenis === 'p2tl' ? 'rgba(255,255,255,0.25)' : 'var(--bg-card)' ?>;"><?= $tab_counts['p2tl'] ?></span>
    </a>
    <a href="index.php?jenis=sr_app" class="btn <?= $active_jenis === 'sr_app' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:var(--radius-md) var(--radius-md) 0 0; padding:10px 18px; font-weight:600; text-decoration:none;">
        <i class="fa-solid fa-plug"></i> SR APP 1 Phasa
        <span class="badge" style="margin-left:6px; background:<?= $active_jenis === 'sr_app' ? 'rgba(255,255,255,0.25)' : 'var(--bg-card)' ?>;"><?= $tab_counts['sr_app'] ?></span>
    </a>
    <a href="index.php?jenis=yandal" class="btn <?= $active_jenis === 'yandal' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:var(--radius-md) var(--radius-md) 0 0; padding:10px 18px; font-weight:600; text-decoration:none;">
        <i class="fa-solid fa-headset"></i> YANDAL & ULC
        <span class="badge" style="margin-left:6px; background:<?= $active_jenis === 'yandal' ? 'rgba(255,255,255,0.25)' : 'var(--bg-card)' ?>;"><?= $tab_counts['yandal'] ?></span>
    </a>
    <a href="index.php?jenis=har" class="btn <?= $active_jenis === 'har' ? 'btn-primary' : 'btn-outline' ?>" style="border-radius:var(--radius-md) var(--radius-md) 0 0; padding:10px 18px; font-weight:600; text-decoration:none;">
        <i class="fa-solid fa-wrench"></i> HAR (Pemeliharaan)
        <span class="badge" style="margin-left:6px; background:<?= $active_jenis === 'har' ? 'rgba(255,255,255,0.25)' : 'var(--bg-card)' ?>;"><?= $tab_counts['har'] ?></span>
    </a>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:1.5rem; padding:1.25rem 1.5rem;">
    <form method="GET" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
        <input type="hidden" name="jenis" value="<?= htmlspecialchars($active_jenis) ?>">
        <div style="flex:1; min-width:240px;">
            <label class="form-label" style="font-size:0.8rem;">Cari Nama Alat atau Satuan</label>
            <input type="text" name="q" class="form-control" placeholder="Contoh: Tang, Helm, Harness, Unit..." value="<?= htmlspecialchars($search) ?>" style="font-size:0.85rem; padding:6px 12px;">
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:7px 16px; font-size:0.85rem;">
                <i class="fa-solid fa-magnifying-glass"></i> Cari
            </button>
            <?php if (!empty($search)): ?>
                <a href="index.php?jenis=<?= htmlspecialchars($active_jenis) ?>" class="btn btn-outline" style="padding:7px 16px; font-size:0.85rem;">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-list-check" style="color:var(--primary);"></i>
            Template: <?= htmlspecialchars($jenis_titles[$active_jenis] ?? strtoupper($active_jenis)) ?>
            <span class="badge badge-submitted" style="font-size:0.7rem; margin-left:6px;"><?= count($items) ?> item</span>
        </h2>
    </div>

    <?php if (empty($items)): ?>
        <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
            <div style="width:48px; height:48px; border-radius:50%; background:#f1f5f9; color:#64748b; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.3rem;">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <strong style="color:var(--text-main);">Belum ada item checklist untuk jenis pekerjaan ini</strong>
            <p style="font-size:0.825rem; margin-top:2px;">Klik tombol "Tambah Item Template" di atas untuk menambahkan alat ke dalam template checklist.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="40px" style="text-align:center;">Urut</th>
                        <th width="50px">Foto</th>
                        <th>Nama Alat / Perlengkapan</th>
                        <th>Kategori</th>
                        <th style="text-align:center;">Tipe Regu</th>
                        <th style="text-align:center;">Jumlah Standar</th>
                        <th>Satuan</th>
                        <th style="text-align:center; width:120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $row): ?>
                        <?php $kat_info = $kategori_labels[$row['kategori']] ?? ['label' => $row['kategori'], 'badge' => 'badge-draft', 'icon' => 'fa-tag']; ?>
                        <tr>
                            <td style="text-align:center; font-weight:700; color:var(--text-muted); font-size:0.8rem;">
                                <?= $row['urutan'] ?>
                            </td>
                            <td>
                                <?php if (!empty($row['gambar_referensi'])): ?>
                                    <img src="<?= base_url('uploads/referensi/' . htmlspecialchars($row['gambar_referensi'])) ?>" 
                                         style="width:38px; height:38px; object-fit:cover; border-radius:var(--radius-sm); border:1px solid var(--border-color); cursor:pointer;"
                                         onclick="previewImage(this.src, '<?= addslashes(htmlspecialchars($row['nama_barang'])) ?>')"
                                         title="Klik perbesar">
                                <?php else: ?>
                                    <div style="width:38px; height:38px; border-radius:var(--radius-sm); background:#f1f5f9; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-size:0.9rem;">
                                        <i class="fa-solid fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--text-main); font-size:0.875rem;"><?= htmlspecialchars($row['nama_barang']) ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $kat_info['badge'] ?>" style="font-size:0.7rem;">
                                    <i class="fa-solid <?= $kat_info['icon'] ?>"></i> <?= $kat_info['label'] ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($row['regu_tipe'] === 'all'): ?>
                                    <span class="badge" style="background:#e2e8f0; color:#334155; font-size:0.7rem;">Semua</span>
                                <?php elseif ($row['regu_tipe'] === 'roda_3'): ?>
                                    <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:0.7rem;">Roda 3</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#fef3c7; color:#92400e; font-size:0.7rem;">Roda 2</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <strong style="font-size:1rem; color:var(--primary);"><?= $row['jumlah_standar'] ?></strong>
                            </td>
                            <td style="font-size:0.85rem; color:var(--text-muted);">
                                <?= htmlspecialchars($row['satuan_default']) ?>
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button onclick='openModalEdit(<?= json_encode($row) ?>)' class="btn btn-outline btn-sm" title="Edit Item">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button onclick="confirmHapus(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['nama_barang'])) ?>')" class="btn btn-danger btn-sm" style="padding:4px 8px;" title="Hapus">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Form (Tambah / Edit) -->
<div id="modalTemplate" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:var(--radius-xl); padding:2rem; max-width:500px; width:92%; box-shadow:var(--shadow-xl); animation:modalIn 0.22s cubic-bezier(0.16, 1, 0.3, 1); max-height:90vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border-light);">
            <h3 id="modalTitle" style="font-size:1.15rem; font-weight:700; color:var(--text-main);">Tambah Item Checklist</h3>
            <button onclick="closeModalTemplate()" style="background:none; border:none; font-size:1.25rem; color:var(--text-muted); cursor:pointer;">&times;</button>
        </div>

        <form method="POST" id="formTemplate">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="templateId" value="">

            <div class="form-group">
                <label class="form-label" for="jenisPekerjaan">Jenis Pekerjaan <span style="color:#ef4444;">*</span></label>
                <select id="jenisPekerjaan" name="jenis_pekerjaan" class="form-control" required>
                    <option value="p2tl" <?= $active_jenis === 'p2tl' ? 'selected' : '' ?>>P2TL (Penertiban Pemakaian Tenaga Listrik)</option>
                    <option value="sr_app" <?= $active_jenis === 'sr_app' ? 'selected' : '' ?>>SR APP 1 Phasa (Penyambungan & Bongkar)</option>
                    <option value="yandal" <?= $active_jenis === 'yandal' ? 'selected' : '' ?>>YANDAL & ULC (Pelayanan Gangguan)</option>
                    <option value="har" <?= $active_jenis === 'har' ? 'selected' : '' ?>>HAR (Pemeliharaan Jaringan & Gardu)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="masterBarangId">Pilih Alat / Perlengkapan dari Katalog <span style="color:#ef4444;">*</span></label>
                <select id="masterBarangId" name="master_barang_id" class="form-control" required>
                    <option value="">-- Pilih Alat dari Katalog --</option>
                    <?php 
                    $curr_kat = '';
                    foreach ($semua_barang as $b): 
                        if ($curr_kat !== $b['kategori']) {
                            if ($curr_kat !== '') echo '</optgroup>';
                            $curr_kat = $b['kategori'];
                            $kat_name = $kategori_labels[$curr_kat]['label'] ?? ucfirst($curr_kat);
                            echo '<optgroup label="' . htmlspecialchars($kat_name) . '">';
                        }
                    ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama_barang']) ?> (<?= htmlspecialchars($b['satuan_default']) ?>)</option>
                    <?php endforeach; ?>
                    <?php if ($curr_kat !== '') echo '</optgroup>'; ?>
                </select>
                <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">
                    Belum ada di katalog? <a href="<?= base_url('master/barang/index.php') ?>" target="_blank" style="color:var(--primary); font-weight:600;">Tambah di Katalog Peralatan &rarr;</a>
                </small>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="jumlahStandar">Jumlah Standar <span style="color:#ef4444;">*</span></label>
                    <input type="number" id="jumlahStandar" name="jumlah_standar" class="form-control" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="urutan">Nomor Urutan</label>
                    <input type="number" id="urutan" name="urutan" class="form-control" min="0" value="0" placeholder="0 = otomatis">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="reguTipe">Berlaku untuk Tipe Regu</label>
                <select id="reguTipe" name="regu_tipe" class="form-control">
                    <option value="all">Semua Tipe Regu (Default)</option>
                    <option value="roda_3">Khusus Armada Roda 3</option>
                    <option value="roda_2">Khusus Armada Roda 2</option>
                </select>
                <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">
                    Khusus pekerjaan SR APP 1 Phasa yang memiliki pembagian armada Roda 2 dan Roda 3.
                </small>
            </div>

            <div style="display:flex; gap:10px; margin-top:1.5rem;">
                <button type="button" onclick="closeModalTemplate()" class="btn btn-outline" style="flex:1;">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;" id="btnSubmit">
                    <i class="fa-solid fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div id="modalHapus" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:var(--radius-xl); padding:2rem; max-width:420px; width:90%; box-shadow:var(--shadow-xl); text-align:center;">
        <div style="width:52px; height:52px; border-radius:50%; background:#fef2f2; color:#dc2626; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:1.5rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-main); margin-bottom:6px;">Hapus Item Template?</h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">
            Apakah Anda yakin ingin menghapus item <strong id="hapusBarangName" style="color:var(--text-main);"></strong> dari template checklist?
        </p>

        <form method="POST" id="formHapus">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="hapusId" value="">
            <input type="hidden" name="jenis_pekerjaan" value="<?= htmlspecialchars($active_jenis) ?>">
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeModalHapus()" class="btn btn-outline" style="flex:1;">Batal</button>
                <button type="submit" class="btn btn-danger" style="flex:1;">
                    <i class="fa-solid fa-trash"></i> Hapus Item
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Preview Gambar -->
<div id="modalPreview" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(15,23,42,0.75); backdrop-filter:blur(4px); align-items:center; justify-content:center; cursor:pointer;" onclick="this.classList.remove('show')">
    <div style="background:#ffffff; padding:12px; border-radius:var(--radius-lg); max-width:500px; width:90%; text-align:center;" onclick="event.stopPropagation()">
        <img id="previewImg" src="" style="max-width:100%; max-height:70vh; border-radius:var(--radius-sm); object-fit:contain;">
        <div id="previewCaption" style="font-weight:600; margin-top:10px; color:var(--text-main); font-size:0.9rem;"></div>
        <button onclick="document.getElementById('modalPreview').classList.remove('show')" class="btn btn-outline btn-sm" style="margin-top:10px;">Tutup</button>
    </div>
</div>

<style>
#modalTemplate.show, #modalHapus.show, #modalPreview.show {
    display: flex !important;
}
</style>

<script>
function openModalTambah() {
    document.getElementById('modalTitle').textContent = 'Tambah Item ke Template Checklist';
    document.getElementById('formAction').value = 'create';
    document.getElementById('templateId').value = '';
    document.getElementById('jenisPekerjaan').value = '<?= $active_jenis ?>';
    document.getElementById('masterBarangId').value = '';
    document.getElementById('jumlahStandar').value = '1';
    document.getElementById('urutan').value = '0';
    document.getElementById('reguTipe').value = 'all';
    document.getElementById('modalTemplate').classList.add('show');
}

function openModalEdit(row) {
    document.getElementById('modalTitle').textContent = 'Edit Item Template Checklist';
    document.getElementById('formAction').value = 'update';
    document.getElementById('templateId').value = row.id;
    document.getElementById('jenisPekerjaan').value = row.jenis_pekerjaan || '<?= $active_jenis ?>';
    document.getElementById('masterBarangId').value = row.master_barang_id || '';
    document.getElementById('jumlahStandar').value = row.jumlah_standar || 1;
    document.getElementById('urutan').value = row.urutan || 0;
    document.getElementById('reguTipe').value = row.regu_tipe || 'all';
    document.getElementById('modalTemplate').classList.add('show');
}

function closeModalTemplate() {
    document.getElementById('modalTemplate').classList.remove('show');
}

function confirmHapus(id, nama) {
    document.getElementById('hapusId').value = id;
    document.getElementById('hapusBarangName').textContent = nama;
    document.getElementById('modalHapus').classList.add('show');
}

function closeModalHapus() {
    document.getElementById('modalHapus').classList.remove('show');
}

function previewImage(src, caption) {
    document.getElementById('previewImg').src = src;
    document.getElementById('previewCaption').textContent = caption;
    document.getElementById('modalPreview').classList.add('show');
}

// Close on backdrop click
document.getElementById('modalTemplate').addEventListener('click', function(e) {
    if (e.target === this) closeModalTemplate();
});
document.getElementById('modalHapus').addEventListener('click', function(e) {
    if (e.target === this) closeModalHapus();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
