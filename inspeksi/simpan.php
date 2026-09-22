<?php
/**
 * Handler Simpan Transaksi Gelar Alat
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('petugas');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php"); exit;
}

$user = get_logged_user();
$action = $_POST['action'] ?? 'draft'; // 'draft' or 'submit'

// Validasi input dasar
$jenis_pekerjaan = in_array($_POST['jenis_pekerjaan'] ?? '', ['p2tl','sr_app','yandal','har']) ? $_POST['jenis_pekerjaan'] : null;
$regu_id = intval($_POST['regu_id'] ?? 0);

if (!$jenis_pekerjaan || !$regu_id) {
    set_flash('error', "Data formulir tidak valid. Silakan ulangi.");
    header("Location: index.php"); exit;
}

// Validasi TTD untuk submit
if ($action === 'submit') {
    $ttd = trim($_POST['ttd_petugas'] ?? '');
    if (empty($ttd) || strlen($ttd) < 100) {
        set_flash('error', "Tanda tangan digital petugas pemeriksa belum diisi. Silakan tanda tangan pada kolom yang tersedia.");
        header("Location: form.php?jenis={$jenis_pekerjaan}&regu_id={$regu_id}"); exit;
    }
}

try {
    $pdo->beginTransaction();

    // 1. Upload foto kegiatan
    $foto_kegiatan_path = null;
    if (!empty($_FILES['foto_kegiatan']['tmp_name'])) {
        $dir = __DIR__ . '/../uploads/foto_kegiatan/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['foto_kegiatan']['name'], PATHINFO_EXTENSION);
        $fname = 'apel_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['foto_kegiatan']['tmp_name'], $dir . $fname);
        $foto_kegiatan_path = $fname;
    }

    // 2. Simpan header gelar alat
    $status = ($action === 'submit') ? 'submitted' : 'draft';
    $ttd_petugas = ($action === 'submit') ? trim($_POST['ttd_petugas'] ?? '') : null;
    
    // Generate nomor dokumen
    $nomor_dok = 'GA/' . strtoupper($jenis_pekerjaan) . '/' . date('Ym') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $stmt_header = $pdo->prepare("
        INSERT INTO gelar_alat_header 
            (nomor_dokumen, tanggal_inspeksi, bulan_tahun, jenis_pekerjaan, regu_id, 
             nama_pelaksana_1, nama_pelaksana_2, pendamping_admin, catatan_umum, 
             foto_kegiatan, status, ttd_petugas, petugas_id)
        VALUES 
            (:nomor_dok, :tanggal, :bulan_tahun, :jenis, :regu_id,
             :nama1, :nama2, :pendamping, :catatan,
             :foto, :status, :ttd, :petugas_id)
    ");
    $stmt_header->execute([
        ':nomor_dok'   => $nomor_dok,
        ':tanggal'     => $_POST['tanggal_inspeksi'] ?? date('Y-m-d'),
        ':bulan_tahun' => $_POST['bulan_tahun'] ?? date('F Y'),
        ':jenis'       => $jenis_pekerjaan,
        ':regu_id'     => $regu_id,
        ':nama1'       => trim($_POST['nama_pelaksana_1'] ?? $user['nama_lengkap']),
        ':nama2'       => trim($_POST['nama_pelaksana_2'] ?? '') ?: null,
        ':pendamping'  => trim($_POST['pendamping_admin'] ?? '') ?: null,
        ':catatan'     => trim($_POST['catatan_umum'] ?? '') ?: null,
        ':foto'        => $foto_kegiatan_path,
        ':status'      => $status,
        ':ttd'         => $ttd_petugas,
        ':petugas_id'  => $user['id']
    ]);
    $header_id = $pdo->lastInsertId();

    // 3. Simpan detail items
    $items = $_POST['items'] ?? [];
    $stmt_detail = $pdo->prepare("
        INSERT INTO gelar_alat_detail 
            (gelar_alat_id, master_barang_id, nama_barang_snapshot, kategori_snapshot,
             jumlah_standar, jumlah_realisasi, satuan, kondisi, keterangan, foto_temuan)
        VALUES 
            (:header_id, :barang_id, :nama_snapshot, :kategori_snapshot,
             :std, :realisasi, :satuan, :kondisi, :ket, :foto)
    ");

    // Ambil nama barang dari DB untuk snapshot
    $stmt_nama = $pdo->prepare("SELECT nama_barang, kategori FROM master_barang WHERE id = :id LIMIT 1");

    foreach ($items as $item_tpl_id => $item_data) {
        $barang_id = intval($item_data['barang_id'] ?? 0);
        if (!$barang_id) continue;

        $stmt_nama->execute([':id' => $barang_id]);
        $barang = $stmt_nama->fetch();
        if (!$barang) continue;

        // Upload foto temuan item (jika ada)
        $foto_temuan_path = null;
        $foto_key_1 = 'foto_item_' . $item_tpl_id;
        $foto_key_2 = 'foto_item_m_' . $item_tpl_id;
        $foto_src_key = isset($_FILES[$foto_key_1]) && !empty($_FILES[$foto_key_1]['tmp_name']) ? $foto_key_1 :
                       (isset($_FILES[$foto_key_2]) && !empty($_FILES[$foto_key_2]['tmp_name']) ? $foto_key_2 : null);

        if ($foto_src_key) {
            $dir_t = __DIR__ . '/../uploads/foto_temuan/';
            if (!is_dir($dir_t)) mkdir($dir_t, 0777, true);
            $ext_t = pathinfo($_FILES[$foto_src_key]['name'], PATHINFO_EXTENSION);
            $fname_t = 'temuan_' . $header_id . '_' . $barang_id . '_' . uniqid() . '.' . $ext_t;
            move_uploaded_file($_FILES[$foto_src_key]['tmp_name'], $dir_t . $fname_t);
            $foto_temuan_path = $fname_t;
        }

        $stmt_detail->execute([
            ':header_id'        => $header_id,
            ':barang_id'        => $barang_id,
            ':nama_snapshot'    => $barang['nama_barang'],
            ':kategori_snapshot'=> $item_data['kategori'] ?? $barang['kategori'],
            ':std'              => intval($item_data['jumlah_standar'] ?? 1),
            ':realisasi'        => intval($item_data['realisasi'] ?? 0),
            ':satuan'           => $item_data['satuan'] ?? '-',
            ':kondisi'          => in_array($item_data['kondisi'] ?? '', ['baik','rusak','waktu_ganti','hilang','ada','tidak_ada']) ? $item_data['kondisi'] : 'baik',
            ':ket'              => trim($item_data['keterangan'] ?? '') ?: null,
            ':foto'             => $foto_temuan_path
        ]);
    }

    $pdo->commit();

    if ($action === 'submit') {
        set_flash('success', "Laporan Gelar Alat berhasil dikirimkan ke Manajemen Atasan! Nomor Dokumen: <strong>{$nomor_dok}</strong>. Menunggu persetujuan & tanda tangan atasan.");
    } else {
        set_flash('success', "Draft berhasil disimpan dengan Nomor Dokumen: <strong>{$nomor_dok}</strong>. Anda dapat melanjutkan pengisian kapan saja.");
    }

    header("Location: " . base_url('riwayat/detail.php?id=' . $header_id));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('error', "Terjadi kesalahan saat menyimpan data: " . $e->getMessage());
    header("Location: form.php?jenis={$jenis_pekerjaan}&regu_id={$regu_id}");
    exit;
}
