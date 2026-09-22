<?php
/**
 * Master Data: Katalog Peralatan & APD K3
 * SIGAP - Role: Manajemen
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_role('manajemen');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../../setup.php"); exit; }

$user = get_logged_user();

// Ensure upload directory exists
$upload_dir = __DIR__ . '/../../uploads/referensi/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// Handle POST (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Upload helper
    $handle_upload = function($file_input_name) use ($upload_dir) {
        if (!empty($_FILES[$file_input_name]['name']) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed)) {
                $filename = 'alat_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $upload_dir . $filename)) {
                    return $filename;
                }
            }
        }
        return null;
    };

    if ($action === 'create') {
        $nama_barang    = trim($_POST['nama_barang'] ?? '');
        $kategori       = trim($_POST['kategori'] ?? '');
        $satuan_default = trim($_POST['satuan_default'] ?? 'Bh');

        if (empty($nama_barang) || empty($kategori) || empty($satuan_default)) {
            set_flash('error', 'Semua kolom wajib diisi.');
        } else {
            $gambar = $handle_upload('gambar_referensi');
            $stmt = $pdo->prepare("INSERT INTO master_barang (nama_barang, kategori, satuan_default, gambar_referensi) VALUES (:nama, :kat, :sat, :gbr)");
            $stmt->execute([
                ':nama' => $nama_barang,
                ':kat'  => $kategori,
                ':sat'  => $satuan_default,
                ':gbr'  => $gambar
            ]);
            set_flash('success', 'Alat <strong>' . htmlspecialchars($nama_barang) . '</strong> berhasil ditambahkan ke katalog.');
        }
        header("Location: index.php");
        exit;
    }

    if ($action === 'update') {
        $id             = intval($_POST['id'] ?? 0);
        $nama_barang    = trim($_POST['nama_barang'] ?? '');
        $kategori       = trim($_POST['kategori'] ?? '');
        $satuan_default = trim($_POST['satuan_default'] ?? 'Bh');

        if ($id <= 0 || empty($nama_barang) || empty($kategori) || empty($satuan_default)) {
            set_flash('error', 'Data tidak valid.');
        } else {
            $gambar_baru = $handle_upload('gambar_referensi');
            if ($gambar_baru) {
                $stmt = $pdo->prepare("UPDATE master_barang SET nama_barang = :nama, kategori = :kat, satuan_default = :sat, gambar_referensi = :gbr WHERE id = :id");
                $stmt->execute([
                    ':id'   => $id,
                    ':nama' => $nama_barang,
                    ':kat'  => $kategori,
                    ':sat'  => $satuan_default,
                    ':gbr'  => $gambar_baru
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE master_barang SET nama_barang = :nama, kategori = :kat, satuan_default = :sat WHERE id = :id");
                $stmt->execute([
                    ':id'   => $id,
                    ':nama' => $nama_barang,
                    ':kat'  => $kategori,
                    ':sat'  => $satuan_default
                ]);
            }
            set_flash('success', 'Data alat <strong>' . htmlspecialchars($nama_barang) . '</strong> berhasil diperbarui.');
        }
        header("Location: index.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Cek keterkaitan dengan detail pemeriksaan
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM gelar_alat_detail WHERE master_barang_id = :id");
            $stmt_check->execute([':id' => $id]);
            $count = $stmt_check->fetchColumn();

            if ($count > 0) {
                set_flash('error', 'Item ini tidak dapat dihapus karena sudah tercatat dalam ' . $count . ' riwayat Berita Acara inspeksi.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM master_barang WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash('success', 'Item alat berhasil dihapus dari katalog.');
            }
        }
        header("Location: index.php");
        exit;
    }
}

// Filter query
$filter_kategori = $_GET['kategori'] ?? '';
$search          = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if (!empty($filter_kategori) && in_array($filter_kategori, ['alat_kerja', 'k3_safety', 'kendaraan_pendukung', 'administrasi'])) {
    $where[] = "b.kategori = :kat";
    $params[':kat'] = $filter_kategori;
}

if (!empty($search)) {
    $where[] = "b.nama_barang LIKE :q";
    $params[':q'] = "%$search%";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT b.*,
           (SELECT COUNT(*) FROM template_checklist_item t WHERE t.master_barang_id = b.id) AS total_template,
           (SELECT COUNT(*) FROM gelar_alat_detail d WHERE d.master_barang_id = b.id) AS total_inspeksi
    FROM master_barang b
    {$where_sql}
    ORDER BY b.kategori, b.nama_barang
");
$stmt->execute($params);
$barang_list = $stmt->fetchAll();

$kategori_labels = [
    'alat_kerja'          => ['label' => 'Peralatan Kerja', 'icon' => 'fa-toolbox', 'class' => 'badge-submitted'],
    'k3_safety'           => ['label' => 'APD & Keselamatan K3', 'icon' => 'fa-hard-hat', 'class' => 'badge-rejected'],
    'kendaraan_pendukung' => ['label' => 'Armada / Sarana', 'icon' => 'fa-truck-pickup', 'class' => 'badge-approved'],
    'administrasi'        => ['label' => 'Administrasi & Personel', 'icon' => 'fa-id-card-clip', 'class' => 'badge-draft'],
];

$page_title = "Katalog Peralatan & APD K3";
require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.75rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:4px; color:var(--text-main);">Katalog Peralatan & APD K3</h1>
        <p style="color:var(--text-muted); font-size:0.9rem;">Kelola master data peralatan kerja, alat uji ukur, perlengkapan K3, dan sarana pendukung.</p>
    </div>
    <button onclick="openModalTambah()" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Tambah Alat Baru
    </button>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:1.5rem; padding:1.25rem 1.5rem;">
    <form method="GET" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:1; min-width:200px;">
            <label class="form-label" style="font-size:0.8rem;">Cari Nama Alat / Perlengkapan</label>
            <input type="text" name="q" class="form-control" placeholder="Contoh: Tang Ampere, Helm, Body Harness, Chainsaw..." value="<?= htmlspecialchars($search) ?>" style="font-size:0.85rem; padding:6px 12px;">
        </div>
        <div>
            <label class="form-label" style="font-size:0.8rem;">Kategori</label>
            <select name="kategori" class="form-control" style="font-size:0.85rem; padding:6px 12px; min-width:180px;">
                <option value="">Semua Kategori</option>
                <option value="alat_kerja" <?= $filter_kategori === 'alat_kerja' ? 'selected' : '' ?>>Peralatan Kerja</option>
                <option value="k3_safety" <?= $filter_kategori === 'k3_safety' ? 'selected' : '' ?>>APD & Keselamatan K3</option>
                <option value="kendaraan_pendukung" <?= $filter_kategori === 'kendaraan_pendukung' ? 'selected' : '' ?>>Armada / Sarana</option>
                <option value="administrasi" <?= $filter_kategori === 'administrasi' ? 'selected' : '' ?>>Administrasi & Personel</option>
            </select>
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:7px 16px; font-size:0.85rem;">
                <i class="fa-solid fa-magnifying-glass"></i> Cari
            </button>
            <?php if (!empty($search) || !empty($filter_kategori)): ?>
                <a href="index.php" class="btn btn-outline" style="padding:7px 16px; font-size:0.85rem;">
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
            <i class="fa-solid fa-toolbox" style="color:var(--primary);"></i>
            Daftar Katalog Peralatan
            <span class="badge badge-submitted" style="font-size:0.7rem; margin-left:6px;"><?= count($barang_list) ?> item</span>
        </h2>
    </div>

    <?php if (empty($barang_list)): ?>
        <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
            <div style="width:48px; height:48px; border-radius:50%; background:#f1f5f9; color:#64748b; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.3rem;">
                <i class="fa-solid fa-toolbox"></i>
            </div>
            <strong style="color:var(--text-main);">Tidak ada data peralatan ditemukan</strong>
            <p style="font-size:0.825rem; margin-top:2px;">Silakan tambahkan data alat baru atau sesuaikan filter pencarian Anda.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="40px">#</th>
                        <th width="50px">Foto</th>
                        <th>Nama Alat / Perlengkapan</th>
                        <th>Kategori</th>
                        <th>Satuan Default</th>
                        <th style="text-align:center;">Template</th>
                        <th style="text-align:center;">Digunakan</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($barang_list as $i => $row): ?>
                        <?php $kat_info = $kategori_labels[$row['kategori']] ?? ['label' => $row['kategori'], 'icon' => 'fa-tag', 'class' => 'badge-draft']; ?>
                        <tr>
                            <td style="color:var(--text-muted); font-size:0.8rem;"><?= $i + 1 ?></td>
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
                                <span class="badge <?= $kat_info['class'] ?>" style="font-size:0.68rem;">
                                    <i class="fa-solid <?= $kat_info['icon'] ?>"></i> <?= $kat_info['label'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background:#f1f5f9; color:#334155; font-size:0.75rem;">
                                    <?= htmlspecialchars($row['satuan_default']) ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge <?= $row['total_template'] > 0 ? 'badge-approved' : 'badge-draft' ?>" style="font-size:0.68rem;">
                                    <?= $row['total_template'] ?> Jenis Regu
                                </span>
                            </td>
                            <td style="text-align:center; font-size:0.8rem; color:var(--text-muted);">
                                <?= $row['total_inspeksi'] ?> kali
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button onclick='openModalEdit(<?= json_encode($row) ?>)' class="btn btn-outline btn-sm" title="Edit Alat">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <button onclick="confirmHapus(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['nama_barang'])) ?>', <?= $row['total_inspeksi'] ?>)" class="btn btn-danger btn-sm" style="padding:4px 8px;" title="Hapus">
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
<div id="modalBarang" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:var(--radius-xl); padding:2rem; max-width:480px; width:92%; box-shadow:var(--shadow-xl); animation:modalIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border-light);">
            <h3 id="modalTitle" style="font-size:1.15rem; font-weight:700; color:var(--text-main);">Tambah Alat Baru</h3>
            <button onclick="closeModalBarang()" style="background:none; border:none; font-size:1.25rem; color:var(--text-muted); cursor:pointer;">&times;</button>
        </div>

        <form method="POST" id="formBarang" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="barangId" value="">

            <div class="form-group">
                <label class="form-label" for="namaBarang">Nama Alat / Perlengkapan <span style="color:#ef4444;">*</span></label>
                <input type="text" id="namaBarang" name="nama_barang" class="form-control" placeholder="Contoh: Tangga Lipat Telescopic 4M, Full Body Harness" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="kategori">Kategori <span style="color:#ef4444;">*</span></label>
                    <select id="kategori" name="kategori" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="alat_kerja">Peralatan Kerja</option>
                        <option value="k3_safety">APD & Keselamatan K3</option>
                        <option value="kendaraan_pendukung">Armada / Sarana</option>
                        <option value="administrasi">Administrasi & Personel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="satuanDefault">Satuan Standar <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="satuanDefault" name="satuan_default" class="form-control" placeholder="Contoh: Bh, Set, Psg, Unit" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="gambarRef">Foto / Gambar Referensi Katalog <span style="font-weight:400; color:var(--text-muted);">(opsional)</span></label>
                <input type="file" id="gambarRef" name="gambar_referensi" class="form-control" accept="image/jpeg,image/png,image/webp">
                <div id="previewCurrentImg" style="margin-top:8px; display:none;">
                    <span style="font-size:0.75rem; color:var(--text-muted);">Foto saat ini:</span><br>
                    <img id="currentImgTag" src="" style="width:60px; height:60px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0; margin-top:4px;">
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:1.5rem;">
                <button type="button" onclick="closeModalBarang()" class="btn btn-outline" style="flex:1;">Batal</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">
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
        <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-main); margin-bottom:6px;">Hapus Item Katalog?</h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">
            Apakah Anda yakin ingin menghapus alat <strong id="hapusBarangName" style="color:var(--text-main);"></strong> dari katalog?
        </p>

        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="hapusId" value="">
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeModalHapus()" class="btn btn-outline" style="flex:1;">Batal</button>
                <button type="submit" class="btn btn-danger" style="flex:1;">
                    <i class="fa-solid fa-trash"></i> Hapus Item
                </button>
            </div>
        </form>
    </div>
</div>

<style>
#modalBarang.show, #modalHapus.show {
    display: flex !important;
}
</style>

<script>
function openModalTambah() {
    document.getElementById('modalTitle').textContent = 'Tambah Alat Baru';
    document.getElementById('formAction').value = 'create';
    document.getElementById('barangId').value = '';
    document.getElementById('namaBarang').value = '';
    document.getElementById('kategori').value = '';
    document.getElementById('satuanDefault').value = 'Bh';
    document.getElementById('gambarRef').value = '';
    document.getElementById('previewCurrentImg').style.display = 'none';
    document.getElementById('modalBarang').classList.add('show');
    document.getElementById('namaBarang').focus();
}

function openModalEdit(row) {
    document.getElementById('modalTitle').textContent = 'Edit Data Alat';
    document.getElementById('formAction').value = 'update';
    document.getElementById('barangId').value = row.id;
    document.getElementById('namaBarang').value = row.nama_barang || '';
    document.getElementById('kategori').value = row.kategori || '';
    document.getElementById('satuanDefault').value = row.satuan_default || 'Bh';
    document.getElementById('gambarRef').value = '';
    
    if (row.gambar_referensi) {
        document.getElementById('currentImgTag').src = '<?= base_url('uploads/referensi/') ?>' + row.gambar_referensi;
        document.getElementById('previewCurrentImg').style.display = 'block';
    } else {
        document.getElementById('previewCurrentImg').style.display = 'none';
    }

    document.getElementById('modalBarang').classList.add('show');
}

function closeModalBarang() {
    document.getElementById('modalBarang').classList.remove('show');
}

function confirmHapus(id, nama, totalInspeksi) {
    if (totalInspeksi > 0) {
        alert('Item "' + nama + '" tidak dapat dihapus karena sudah ada di ' + totalInspeksi + ' laporan inspeksi.');
        return;
    }
    document.getElementById('hapusId').value = id;
    document.getElementById('hapusBarangName').textContent = nama;
    document.getElementById('modalHapus').classList.add('show');
}

function closeModalHapus() {
    document.getElementById('modalHapus').classList.remove('show');
}

function previewImage(src, title) {
    const w = window.open('');
    w.document.write('<html><head><title>' + title + '</title><style>body{margin:0;background:#0f172a;display:flex;align-items:center;justify-content:center;height:100vh;}img{max-width:90%;max-height:90%;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.5);}</style></head><body><img src="' + src + '"></body></html>');
}

document.getElementById('modalBarang').addEventListener('click', function(e) {
    if (e.target === this) closeModalBarang();
});
document.getElementById('modalHapus').addEventListener('click', function(e) {
    if (e.target === this) closeModalHapus();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
