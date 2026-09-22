<?php
/**
 * Master Data: Kelola Pengguna (Users)
 * SIGAP - Role: Manajemen
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_role('manajemen');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../../setup.php"); exit; }

$current_user = get_logged_user();

// Handle POST (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username     = strtolower(trim($_POST['username'] ?? ''));
        $password     = $_POST['password'] ?? '';
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $role         = trim($_POST['role'] ?? 'petugas');
        $jabatan      = trim($_POST['jabatan'] ?? '');
        $no_hp        = trim($_POST['no_hp'] ?? '');

        if (empty($username) || empty($password) || empty($nama_lengkap) || empty($jabatan)) {
            set_flash('error', 'Semua kolom bertanda bintang (*) wajib diisi.');
        } elseif (strlen($password) < 6) {
            set_flash('error', 'Password minimal 6 karakter.');
        } elseif (!in_array($role, ['petugas', 'manajemen'])) {
            set_flash('error', 'Role pengguna tidak valid.');
        } else {
            // Cek apakah username sudah ada
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt_check->execute([':username' => $username]);
            if ($stmt_check->fetchColumn() > 0) {
                set_flash('error', 'Username <strong>' . htmlspecialchars($username) . '</strong> sudah digunakan. Pilih username lain.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, nama_lengkap, role, jabatan, no_hp)
                    VALUES (:username, :hash, :nama, :role, :jabatan, :hp)
                ");
                $stmt->execute([
                    ':username' => $username,
                    ':hash'     => $hash,
                    ':nama'     => $nama_lengkap,
                    ':role'     => $role,
                    ':jabatan'  => $jabatan,
                    ':hp'       => $no_hp ?: null
                ]);
                set_flash('success', 'Pengguna <strong>' . htmlspecialchars($nama_lengkap) . '</strong> (@' . htmlspecialchars($username) . ') berhasil ditambahkan.');
            }
        }
        header("Location: index.php");
        exit;
    }

    if ($action === 'update') {
        $id           = intval($_POST['id'] ?? 0);
        $username     = strtolower(trim($_POST['username'] ?? ''));
        $password     = $_POST['password'] ?? '';
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $role         = trim($_POST['role'] ?? 'petugas');
        $jabatan      = trim($_POST['jabatan'] ?? '');
        $no_hp        = trim($_POST['no_hp'] ?? '');

        if ($id <= 0 || empty($username) || empty($nama_lengkap) || empty($jabatan)) {
            set_flash('error', 'Data tidak valid.');
        } elseif (!in_array($role, ['petugas', 'manajemen'])) {
            set_flash('error', 'Role pengguna tidak valid.');
        } else {
            // Cek apakah username sudah dipakai user lain
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :id");
            $stmt_check->execute([':username' => $username, ':id' => $id]);
            if ($stmt_check->fetchColumn() > 0) {
                set_flash('error', 'Username <strong>' . htmlspecialchars($username) . '</strong> sudah digunakan pengguna lain.');
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        set_flash('error', 'Password baru minimal 6 karakter.');
                        header("Location: index.php");
                        exit;
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET username = :username, password_hash = :hash, nama_lengkap = :nama, role = :role, jabatan = :jabatan, no_hp = :hp
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':id'       => $id,
                        ':username' => $username,
                        ':hash'     => $hash,
                        ':nama'     => $nama_lengkap,
                        ':role'     => $role,
                        ':jabatan'  => $jabatan,
                        ':hp'       => $no_hp ?: null
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET username = :username, nama_lengkap = :nama, role = :role, jabatan = :jabatan, no_hp = :hp
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':id'       => $id,
                        ':username' => $username,
                        ':nama'     => $nama_lengkap,
                        ':role'     => $role,
                        ':jabatan'  => $jabatan,
                        ':hp'       => $no_hp ?: null
                    ]);
                }
                set_flash('success', 'Data pengguna <strong>' . htmlspecialchars($nama_lengkap) . '</strong> berhasil diperbarui.');
            }
        }
        header("Location: index.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            set_flash('error', 'ID pengguna tidak valid.');
        } elseif ($id === (int)$current_user['id']) {
            set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
        } else {
            // Cek apakah pengguna terkait dengan data inspeksi
            $stmt_petugas = $pdo->prepare("SELECT COUNT(*) FROM gelar_alat_header WHERE petugas_id = :id");
            $stmt_petugas->execute([':id' => $id]);
            $count_petugas = (int)$stmt_petugas->fetchColumn();

            $stmt_manajemen = $pdo->prepare("SELECT COUNT(*) FROM gelar_alat_header WHERE manajemen_id = :id");
            $stmt_manajemen->execute([':id' => $id]);
            $count_manajemen = (int)$stmt_manajemen->fetchColumn();

            $total_terkait = $count_petugas + $count_manajemen;

            if ($total_terkait > 0) {
                set_flash('error', 'Pengguna ini tidak dapat dihapus karena tercatat dalam ' . $total_terkait . ' riwayat Berita Acara inspeksi.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);
                set_flash('success', 'Pengguna berhasil dihapus.');
            }
        }
        header("Location: index.php");
        exit;
    }
}

// Filter query
$filter_role = $_GET['role'] ?? '';
$search      = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if (!empty($filter_role) && in_array($filter_role, ['petugas', 'manajemen'])) {
    $where[] = "u.role = :role";
    $params[':role'] = $filter_role;
}

if (!empty($search)) {
    $where[] = "(u.username LIKE :q OR u.nama_lengkap LIKE :q OR u.jabatan LIKE :q OR u.no_hp LIKE :q)";
    $params[':q'] = "%$search%";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM gelar_alat_header h WHERE h.petugas_id = u.id) AS total_laporan_dibuat,
           (SELECT COUNT(*) FROM gelar_alat_header h WHERE h.manajemen_id = u.id) AS total_laporan_diapprove
    FROM users u
    {$where_sql}
    ORDER BY u.role DESC, u.nama_lengkap ASC
");
$stmt->execute($params);
$users_list = $stmt->fetchAll();

// Total count
$stats_role = $pdo->query("SELECT role, COUNT(*) as jml FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$total_petugas = $stats_role['petugas'] ?? 0;
$total_manajemen = $stats_role['manajemen'] ?? 0;

$page_title = "Kelola Pengguna";
require_once __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.75rem; flex-wrap:wrap; gap:1rem;">
    <div>
        <h1 style="font-size:1.65rem; font-weight:800; letter-spacing:-0.02em; margin-bottom:4px; color:var(--text-main);">Kelola Pengguna (Users)</h1>
        <p style="color:var(--text-muted); font-size:0.9rem;">Kelola akun petugas pelaksana lapangan dan pejabat manajemen yang berwenang.</p>
    </div>
    <button onclick="openModalTambah()" class="btn btn-primary">
        <i class="fa-solid fa-user-plus"></i> Tambah Pengguna Baru
    </button>
</div>

<!-- Stats Ringkas -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
    <div class="card" style="padding:1.25rem; display:flex; align-items:center; gap:14px;">
        <div style="width:48px; height:48px; border-radius:var(--radius-lg); background:#eff6ff; color:#3b82f6; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:600;">Total Pengguna</div>
            <div style="font-size:1.4rem; font-weight:800; color:var(--text-main);"><?= $total_petugas + $total_manajemen ?></div>
        </div>
    </div>
    <div class="card" style="padding:1.25rem; display:flex; align-items:center; gap:14px;">
        <div style="width:48px; height:48px; border-radius:var(--radius-lg); background:#f0fdf4; color:#22c55e; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-user-gear"></i>
        </div>
        <div>
            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:600;">Petugas Pelaksana</div>
            <div style="font-size:1.4rem; font-weight:800; color:#15803d;"><?= $total_petugas ?></div>
        </div>
    </div>
    <div class="card" style="padding:1.25rem; display:flex; align-items:center; gap:14px;">
        <div style="width:48px; height:48px; border-radius:var(--radius-lg); background:#faf5ff; color:#a855f7; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-user-tie"></i>
        </div>
        <div>
            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:600;">Manajemen / Approval</div>
            <div style="font-size:1.4rem; font-weight:800; color:#7e22ce;"><?= $total_manajemen ?></div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:1.5rem; padding:1.25rem 1.5rem;">
    <form method="GET" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:1; min-width:220px;">
            <label class="form-label" style="font-size:0.8rem;">Cari Nama, Username, Jabatan, HP</label>
            <input type="text" name="q" class="form-control" placeholder="Contoh: Yusuf, Ropiko, 0812..." value="<?= htmlspecialchars($search) ?>" style="font-size:0.85rem; padding:6px 12px;">
        </div>
        <div>
            <label class="form-label" style="font-size:0.8rem;">Filter Role</label>
            <select name="role" class="form-control" style="font-size:0.85rem; padding:6px 12px; min-width:160px;">
                <option value="">Semua Role</option>
                <option value="petugas" <?= $filter_role === 'petugas' ? 'selected' : '' ?>>Petugas Lapangan</option>
                <option value="manajemen" <?= $filter_role === 'manajemen' ? 'selected' : '' ?>>Manajemen</option>
            </select>
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:7px 16px; font-size:0.85rem;">
                <i class="fa-solid fa-magnifying-glass"></i> Cari
            </button>
            <?php if (!empty($search) || !empty($filter_role)): ?>
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
            <i class="fa-solid fa-users-gear" style="color:var(--primary);"></i>
            Daftar Pengguna SIGAP
            <span class="badge badge-submitted" style="font-size:0.7rem; margin-left:6px;"><?= count($users_list) ?> user</span>
        </h2>
    </div>

    <?php if (empty($users_list)): ?>
        <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
            <div style="width:48px; height:48px; border-radius:50%; background:#f1f5f9; color:#64748b; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:1.3rem;">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <strong style="color:var(--text-main);">Tidak ada data pengguna ditemukan</strong>
            <p style="font-size:0.825rem; margin-top:2px;">Silakan sesuaikan filter pencarian atau tambahkan pengguna baru.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="40px">#</th>
                        <th>Nama & Username</th>
                        <th>Role Akses</th>
                        <th>Jabatan</th>
                        <th>Kontak (No HP)</th>
                        <th style="text-align:center;">Aktivitas BA</th>
                        <th style="text-align:center; width:130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_list as $i => $row): ?>
                        <?php 
                        $is_self = ($row['id'] == $current_user['id']);
                        $initials = strtoupper(substr($row['nama_lengkap'], 0, 2));
                        $total_aktif = ($row['role'] === 'petugas') ? $row['total_laporan_dibuat'] : $row['total_laporan_diapprove'];
                        ?>
                        <tr>
                            <td style="color:var(--text-muted); font-size:0.8rem;"><?= $i + 1 ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:36px; height:36px; border-radius:50%; background:<?= $row['role'] === 'manajemen' ? '#f3e8ff; color:#7e22ce' : '#e0f2fe; color:#0284c7' ?>; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.8rem; flex-shrink:0;">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700; color:var(--text-main); font-size:0.875rem;">
                                            <?= htmlspecialchars($row['nama_lengkap']) ?>
                                            <?php if ($is_self): ?>
                                                <span class="badge" style="background:#dbeafe; color:#1e40af; font-size:0.65rem; margin-left:4px;">Anda</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:0.75rem; color:var(--text-muted); font-family:monospace;">
                                            @<?= htmlspecialchars($row['username']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['role'] === 'manajemen'): ?>
                                    <span class="badge" style="background:#faf5ff; color:#7e22ce; border:1px solid #e9d5ff; font-size:0.72rem;">
                                        <i class="fa-solid fa-user-tie"></i> Manajemen
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; font-size:0.72rem;">
                                        <i class="fa-solid fa-user-gear"></i> Petugas
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.85rem; color:var(--text-main);">
                                <?= htmlspecialchars($row['jabatan']) ?>
                            </td>
                            <td style="font-size:0.825rem; color:var(--text-muted);">
                                <?= $row['no_hp'] ? '<i class="fa-solid fa-phone" style="font-size:0.75rem; margin-right:4px;"></i>' . htmlspecialchars($row['no_hp']) : '&mdash;' ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge <?= $total_aktif > 0 ? 'badge-approved' : 'badge-draft' ?>" style="font-size:0.7rem;">
                                    <?= $total_aktif ?> Dokumen
                                </span>
                            </td>
                            <td style="text-align:center; white-space:nowrap;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button onclick='openModalEdit(<?= json_encode($row) ?>)' class="btn btn-outline btn-sm" title="Edit Pengguna">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <?php if ($is_self): ?>
                                        <button disabled class="btn btn-outline btn-sm" style="opacity:0.4; cursor:not-allowed;" title="Tidak dapat menghapus akun sendiri">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="confirmHapus(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['nama_lengkap'])) ?>', '<?= addslashes(htmlspecialchars($row['username'])) ?>', <?= $total_aktif ?>)" class="btn btn-danger btn-sm" style="padding:4px 8px;" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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

<!-- Modal Form (Tambah / Edit) -->
<div id="modalUser" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.6); backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:var(--radius-xl); padding:2rem; max-width:480px; width:92%; box-shadow:var(--shadow-xl); animation:modalIn 0.22s cubic-bezier(0.16, 1, 0.3, 1); max-height:92vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border-light);">
            <h3 id="modalTitle" style="font-size:1.15rem; font-weight:700; color:var(--text-main);">Tambah Pengguna Baru</h3>
            <button onclick="closeModalUser()" style="background:none; border:none; font-size:1.25rem; color:var(--text-muted); cursor:pointer;">&times;</button>
        </div>

        <form method="POST" id="formUser">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="userId" value="">

            <div class="form-group">
                <label class="form-label" for="namaLengkap">Nama Lengkap <span style="color:#ef4444;">*</span></label>
                <input type="text" id="namaLengkap" name="nama_lengkap" class="form-control" placeholder="Contoh: Budi Santoso" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="username">Username <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: budi_sr" required style="font-family:monospace;">
                </div>
                <div class="form-group">
                    <label class="form-label" for="role">Role Akses <span style="color:#ef4444;">*</span></label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="petugas">Petugas Lapangan</option>
                        <option value="manajemen">Manajemen (Approver)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span id="pwdRequiredStar" style="color:#ef4444;">*</span></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 6 karakter">
                <small id="pwdHelp" style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">
                    Gunakan kombinasi yang aman.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label" for="jabatan">Jabatan Struktural <span style="color:#ef4444;">*</span></label>
                <input type="text" id="jabatan" name="jabatan" class="form-control" placeholder="Contoh: Pelaksana Yandal ULP Balong, Manager ULP..." required>
            </div>

            <div class="form-group">
                <label class="form-label" for="noHp">Nomor WhatsApp / HP</label>
                <input type="text" id="noHp" name="no_hp" class="form-control" placeholder="Contoh: 081234567890">
            </div>

            <div style="display:flex; gap:10px; margin-top:1.5rem;">
                <button type="button" onclick="closeModalUser()" class="btn btn-outline" style="flex:1;">Batal</button>
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
        <h3 style="font-size:1.15rem; font-weight:700; color:var(--text-main); margin-bottom:6px;">Hapus Pengguna?</h3>
        <p style="font-size:0.875rem; color:var(--text-muted); margin-bottom:1.5rem;">
            Apakah Anda yakin ingin menghapus akun <strong id="hapusUserName" style="color:var(--text-main);"></strong> (<span id="hapusUserUsername" style="font-family:monospace;"></span>)? Tindakan ini tidak dapat dibatalkan.
        </p>

        <form method="POST" id="formHapus">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="hapusId" value="">
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeModalHapus()" class="btn btn-outline" style="flex:1;">Batal</button>
                <button type="submit" class="btn btn-danger" style="flex:1;">
                    <i class="fa-solid fa-trash"></i> Hapus Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

<style>
#modalUser.show, #modalHapus.show {
    display: flex !important;
}
</style>

<script>
function openModalTambah() {
    document.getElementById('modalTitle').textContent = 'Tambah Pengguna Baru';
    document.getElementById('formAction').value = 'create';
    document.getElementById('userId').value = '';
    document.getElementById('namaLengkap').value = '';
    document.getElementById('username').value = '';
    document.getElementById('username').removeAttribute('readonly');
    document.getElementById('role').value = 'petugas';
    document.getElementById('password').value = '';
    document.getElementById('password').setAttribute('required', 'required');
    document.getElementById('pwdRequiredStar').style.display = 'inline';
    document.getElementById('pwdHelp').textContent = 'Password minimal 6 karakter.';
    document.getElementById('jabatan').value = '';
    document.getElementById('noHp').value = '';
    document.getElementById('modalUser').classList.add('show');
    document.getElementById('namaLengkap').focus();
}

function openModalEdit(row) {
    document.getElementById('modalTitle').textContent = 'Edit Data Pengguna';
    document.getElementById('formAction').value = 'update';
    document.getElementById('userId').value = row.id;
    document.getElementById('namaLengkap').value = row.nama_lengkap || '';
    document.getElementById('username').value = row.username || '';
    document.getElementById('role').value = row.role || 'petugas';
    document.getElementById('password').value = '';
    document.getElementById('password').removeAttribute('required');
    document.getElementById('pwdRequiredStar').style.display = 'none';
    document.getElementById('pwdHelp').textContent = 'Kosongkan jika tidak ingin mengubah password.';
    document.getElementById('jabatan').value = row.jabatan || '';
    document.getElementById('noHp').value = row.no_hp || '';
    document.getElementById('modalUser').classList.add('show');
}

function closeModalUser() {
    document.getElementById('modalUser').classList.remove('show');
}

function confirmHapus(id, nama, username, totalAktif) {
    if (totalAktif > 0) {
        alert('Pengguna "' + nama + '" tidak dapat dihapus karena sudah memiliki ' + totalAktif + ' Berita Acara terkait.');
        return;
    }
    document.getElementById('hapusId').value = id;
    document.getElementById('hapusUserName').textContent = nama;
    document.getElementById('hapusUserUsername').textContent = '@' + username;
    document.getElementById('modalHapus').classList.add('show');
}

function closeModalHapus() {
    document.getElementById('modalHapus').classList.remove('show');
}

// Close on backdrop click
document.getElementById('modalUser').addEventListener('click', function(e) {
    if (e.target === this) closeModalUser();
});
document.getElementById('modalHapus').addEventListener('click', function(e) {
    if (e.target === this) closeModalHapus();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
