<?php
/**
 * Master Data: Regu & Armada Kendaraan
 * SIGAP - Role: Manajemen
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_role('manajemen');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../../setup.php"); exit; }

$user = get_logged_user();
$error_msg = '';

// Handle POST (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nama_regu       = trim($_POST['nama_regu'] ?? '');
        $jenis_pekerjaan = trim($_POST['jenis_pekerjaan'] ?? '');
        $kendaraan       = trim($_POST['kendaraan'] ?? '');
        $nopol           = trim($_POST['nopol'] ?? '');
        $keterangan      = trim($_POST['keterangan'] ?? '');

        if (empty($nama_regu) || empty($jenis_pekerjaan)) {
            set_flash('error', 'Nama Regu dan Jenis Pekerjaan wajib diisi.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO regu (nama_regu, jenis_pekerjaan, kendaraan, nopol, keterangan) VALUES (:nama, :jenis, :kendaraan, :nopol, :ket)");
            $stmt->execute([
                ':nama'      => $nama_regu,
                ':jenis'     => $jenis_pekerjaan,
                ':kendaraan' => $kendaraan ?: null,
                ':nopol'     => $nopol ?: null,
                ':ket'       => $keterangan ?: null
            ]);
            set_flash('success', 'Regu <strong>' . htmlspecialchars($nama_regu) . '</strong> berhasil ditambahkan.');
        }
        header("Location: index.php");
        exit;
    }

    if ($action === 'update') {
        $id              = intval($_POST['id'] ?? 0);
        $nama_regu       = trim($_POST['nama_regu'] ?? '');
        $jenis_pekerjaan = trim($_POST['jenis_pekerjaan'] ?? '');
        $kendaraan       = trim($_POST['kendaraan'] ?? '');
        $nopol           = trim($_POST['nopol'] ?? '');
        $keterangan      = trim($_POST['keterangan'] ?? '');

        if ($id <= 0 || empty($nama_regu) || empty($jenis_pekerjaan)) {
            set_flash('error', 'Data tidak valid.');
        } else {
            $stmt = $pdo->prepare("UPDATE regu SET nama_regu = :nama, jenis_pekerjaan = :jenis, kendaraan = :kendaraan, nopol = :nopol, keterangan = :ket WHERE id = :id");
            $stmt->execute([
                ':id'        => $id,
                ':nama'      => $nama_regu,
                ':jenis'     => $jenis_pekerjaan,
                ':kendaraan' => $kendaraan ?: null,
                ':nopol'     => $nopol ?: null,
                ':ket'       => $keterangan ?: null
            ]);
            set_flash('success', 'Data regu <strong>' . htmlspecialchars($nama_regu) . '</strong> berhasil diperbarui.');
        }
        header("Location: index.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Cek apakah regu sudah pernah digunakan di transaksi gelar alat
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM gelar_alat_header WHERE regu_id = :id");
            $stmt_check->execute([':id' => $id]);
            $count = $stmt_check->fetchColumn();

            if ($count > 0) {
                set_flash('error', 'Regu ini tidak dapat dihapus karena sudah memiliki ' . $count . ' riwayat Berita Acara inspeksi.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM regu WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash('success', 'Regu berhasil dihapus.');
            }
        }
        header("Location: index.php");
        exit;
    }
}

// Filter query
$filter_jenis = $_GET['jenis'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if (!empty($filter_jenis) && in_array($filter_jenis, ['p2tl', 'sr_app', 'yandal', 'har'])) {
    $where[] = "r.jenis_pekerjaan = :jenis";
    $params[':jenis'] = $filter_jenis;
}

if (!empty($search)) {
    $where[] = "(r.nama_regu LIKE :q OR r.kendaraan LIKE :q OR r.nopol LIKE :q)";
    $params[':q'] = "%$search%";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT r.*,
           (SELECT COUNT(*) FROM gelar_alat_header h WHERE h.regu_id = r.id) AS total_laporan
    FROM regu r
    {$where_sql}
    ORDER BY r.jenis_pekerjaan, r.nama_regu
");
$stmt->execute($params);
$regu_list = $stmt->fetchAll();

$page_title = "Master Regu & Armada";
require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.75rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:4px; color:var(--text-main);">Master Regu & Armada</h1>
        <p style="color:var(--text-muted); font-size:0.9rem;">Kelola data regu operasional dan kendaraan dinas yang digunakan saat apel gelar alat.</p>
    </div>
    <button onclick="openModalTambah()" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Tambah Regu Baru
    </button>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:1.5rem; padding:1.25rem 1.5rem;">
    <form method="GET" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:1; min-width:200px;">
            <label class="form-label" style="font-size:0.8rem;">Cari Nama / Armada / Nopol</label>
            <input type="text" name="q" class="form-control" placeholder="Contoh: Hilux, Rangga, P2TL, AE 8297..." value="<?= htmlspecialchars($search) ?>" style="font-size:0.85rem; padding:6px 12px;">
        </div>
        <div>
            <label class="form-label" style="font-size:0.8rem;">Jenis Pekerjaan</label>
            <select name="jenis" class="form-control" style="font-size:0.85rem; padding:6px 12px; min-width:160px;">
                <option value="">Semua Jenis</option>
                <option value="p2tl" <?= $filter_jenis === 'p2tl' ? 'selected' : '' ?>>P2TL</option>
                <option value="sr_app" <?= $filter_jenis === 'sr_app' ? 'selected' : '' ?>>SR APP 1 Phasa</option>
                <option value="yandal" <?= $filter_jenis === 'yandal' ? 'selected' : '' ?>>YANDAL & ULC</option>
                <option value="har" <?= $filter_jenis === 'har' ? 'selected' : '' ?>>HAR (Pemeliharaan)</option>
            </select>
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:7px 16px; font-size:0.85rem;">
                <i class="fa-solid fa-magnifying-glass"></i> Cari
            </button>
            <?php if (!empty($search) || !empty($filter_jenis)): ?>
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
            <i class="fa-solid fa-truck-ramp-box" style="color:var(--primary);"></i>
            Daftar Regu & Armada
            <span class="badge badge-submitted" style="font-size:0.7rem; margin-left:6px;"><?= count($regu_list) ?> regu</span>
        </h2>
    </div>

    <?php if (empty($regu_list)): ?>
        <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
            <div style="width:48px; height:48px; border-radius:50%; background:#f1f5f9; color:#64748b; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.3rem;">
                <i class="fa-solid fa-truck"></i>
            </div>
            <strong style="color:var(--text-main);">Tidak ada data regu ditemukan</strong>
            <p style="font-size:0.825rem; margin-top:2px;">Silakan tambahkan data regu baru atau ubah kata kunci filter Anda.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="40px">#</th>
                        <th>Nama Regu</th>
                        <th>Jenis Pekerjaan</th>
                        <th>Armada Kendaraan</th>
                        <th>Nomor Polisi</th>
                        <th>Keterangan</th>
                        <th style="text-align:center;">Laporan</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regu_list as $i => $row): ?>
                        <tr>
                            <td style="color:var(--text-muted); font-size:0.8rem;"><?= $i + 1 ?></td>
                            <td>
                                <strong style="color:var(--text-main); font-size:0.875rem;"><?= htmlspecialchars($row['nama_regu']) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-submitted" style="font-size:0.68rem;">
                                    <?= strtoupper($row['jenis_pekerjaan']) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['kendaraan'] ?? '-') ?>
                            </td>
                            <td>
                                <?php if ($row['nopol']): ?>
                                    <span class="badge" style="background:#f1f5f9; color:#334155; font-size:0.75rem; font-family:monospace;">
                                        <?= htmlspecialchars($row['nopol']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.8rem; color:var(--text-muted);">
                                <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge <?= $row['total_laporan'] > 0 ? 'badge-approved' : 'badge-draft' ?>" style="font-size:0.7rem;">
                                    <?= $row['total_laporan'] ?> Dokumen
                                </span>
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button onclick='openModalEdit(<?= json_encode($row) ?>)' class="btn btn-outline btn-sm" title="Edit Regu">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <button onclick="confirmHapus(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['nama_regu'])) ?>', <?= $row['total_laporan'] ?>)" class="btn btn-danger btn-sm" style="padding:4px 8px;" title="Hapus">
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
<div id="modalRegu" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:var(--radius-xl); padding:2rem; max-width:480px; width:92%; box-shadow:var(--shadow-xl); animation:modalIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border-light);">
            <h3 id="modalTitle" style="font-size:1.15rem; font-weight:700; color:var(--text-main);">Tambah Regu Baru</h3>
            <button onclick="closeModalRegu()" style="background:none; border:none; font-size:1.25rem; color:var(--text-muted); cursor:pointer;">&times;</button>
        </div>

        <form method="POST" id="formRegu">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="reguId" value="">

            <div class="form-group">
                <label class="form-label" for="namaRegu">Nama Regu <span style="color:#ef4444;">*</span></label>
                <input type="text" id="namaRegu" name="nama_regu" class="form-control" placeholder="Contoh: Regu 2 P2TL Balong, Mobil Yandal Rangga" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="jenisPekerjaan">Jenis Pekerjaan <span style="color:#ef4444;">*</span></label>
                <select id="jenisPekerjaan" name="jenis_pekerjaan" class="form-control" required>
                    <option value="">-- Pilih Jenis Pekerjaan --</option>
                    <option value="p2tl">P2TL (Penertiban Pemakaian Tenaga Listrik)</option>
                    <option value="sr_app">SR APP 1 Phasa (Penyambungan & Bongkar)</option>
                    <option value="yandal">YANDAL & ULC (Pelayanan Gangguan)</option>
                    <option value="har">HAR (Pemeliharaan Jaringan & Gardu)</option>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="kendaraan">Armada Kendaraan</label>
                    <input type="text" id="kendaraan" name="kendaraan" class="form-control" placeholder="Contoh: Hilux Rangga, Truk Hino">
                </div>
                <div class="form-group">
                    <label class="form-label" for="nopol">Nomor Polisi</label>
                    <input type="text" id="nopol" name="nopol" class="form-control" placeholder="Contoh: AE 8297 BH">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="keterangan">Keterangan / Catatan</label>
                <textarea id="keterangan" name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan regu (opsional)"></textarea>
            </div>

            <div style="display:flex; gap:10px; margin-top:1.5rem;">
                <button type="button" onclick="closeModalRegu()" class="btn btn-outline" style="flex:1;">Batal</button>
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
        <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-main); margin-bottom:6px;">Hapus Data Regu?</h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">
            Apakah Anda yakin ingin menghapus regu <strong id="hapusReguName" style="color:var(--text-main);"></strong>? Tindakan ini tidak dapat dibatalkan.
        </p>

        <form method="POST" id="formHapus">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="hapusId" value="">
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeModalHapus()" class="btn btn-outline" style="flex:1;">Batal</button>
                <button type="submit" class="btn btn-danger" style="flex:1;">
                    <i class="fa-solid fa-trash"></i> Hapus Regu
                </button>
            </div>
        </form>
    </div>
</div>

<style>
#modalRegu.show, #modalHapus.show {
    display: flex !important;
}
</style>

<script>
function openModalTambah() {
    document.getElementById('modalTitle').textContent = 'Tambah Regu Baru';
    document.getElementById('formAction').value = 'create';
    document.getElementById('reguId').value = '';
    document.getElementById('namaRegu').value = '';
    document.getElementById('jenisPekerjaan').value = '';
    document.getElementById('kendaraan').value = '';
    document.getElementById('nopol').value = '';
    document.getElementById('keterangan').value = '';
    document.getElementById('modalRegu').classList.add('show');
    document.getElementById('namaRegu').focus();
}

function openModalEdit(row) {
    document.getElementById('modalTitle').textContent = 'Edit Data Regu';
    document.getElementById('formAction').value = 'update';
    document.getElementById('reguId').value = row.id;
    document.getElementById('namaRegu').value = row.nama_regu || '';
    document.getElementById('jenisPekerjaan').value = row.jenis_pekerjaan || '';
    document.getElementById('kendaraan').value = row.kendaraan || '';
    document.getElementById('nopol').value = row.nopol || '';
    document.getElementById('keterangan').value = row.keterangan || '';
    document.getElementById('modalRegu').classList.add('show');
}

function closeModalRegu() {
    document.getElementById('modalRegu').classList.remove('show');
}

function confirmHapus(id, nama, totalLaporan) {
    if (totalLaporan > 0) {
        alert('Regu "' + nama + '" tidak dapat dihapus karena sudah memiliki ' + totalLaporan + ' Berita Acara terkait.');
        return;
    }
    document.getElementById('hapusId').value = id;
    document.getElementById('hapusReguName').textContent = nama;
    document.getElementById('modalHapus').classList.add('show');
}

function closeModalHapus() {
    document.getElementById('modalHapus').classList.remove('show');
}

// Close on backdrop click
document.getElementById('modalRegu').addEventListener('click', function(e) {
    if (e.target === this) closeModalRegu();
});
document.getElementById('modalHapus').addEventListener('click', function(e) {
    if (e.target === this) closeModalHapus();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
