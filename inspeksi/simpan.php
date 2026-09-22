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

$edit_id = intval($_POST['edit_id'] ?? 0);
$existing_header = null;
if ($edit_id > 0) {
    $stmt_cek = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = :id AND petugas_id = :uid LIMIT 1");
    $stmt_cek->execute([':id' => $edit_id, ':uid' => $user['id']]);
    $existing_header = $stmt_cek->fetch();
    if (!$existing_header || !in_array($existing_header['status'], ['draft', 'rejected'])) {
        set_flash('error', "Dokumen tidak dapat diubah.");
        header("Location: index.php"); exit;
    }
}

// Validasi TTD untuk submit
if ($action === 'submit') {
    $ttd = trim($_POST['ttd_petugas'] ?? '');
    $has_existing_ttd = ($existing_header && !empty($existing_header['ttd_petugas']));
    if ((empty($ttd) || strlen($ttd) < 100) && !$has_existing_ttd) {
        set_flash('error', "Tanda tangan digital petugas pemeriksa belum diisi. Silakan tanda tangan pada kolom yang tersedia.");
        $redirect_back = $edit_id ? "form.php?edit_id={$edit_id}" : "form.php?jenis={$jenis_pekerjaan}&regu_id={$regu_id}";
        header("Location: " . $redirect_back); exit;
    }
}

try {
    $pdo->beginTransaction();

    // 1. Upload foto kegiatan
    $foto_kegiatan_path = $_POST['existing_foto_kegiatan'] ?? ($existing_header['foto_kegiatan'] ?? null);
    if (!empty($_FILES['foto_kegiatan']['tmp_name'])) {
        $dir = __DIR__ . '/../uploads/foto_kegiatan/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['foto_kegiatan']['name'], PATHINFO_EXTENSION);
        $fname = 'apel_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['foto_kegiatan']['tmp_name'], $dir . $fname)) {
            $foto_kegiatan_path = $fname;
        }
    }

    // 2. Simpan atau Update header gelar alat
    $status = ($action === 'submit') ? 'submitted' : 'draft';
    $raw_ttd = trim($_POST['ttd_petugas'] ?? '');
    $ttd_petugas = (!empty($raw_ttd) && strlen($raw_ttd) >= 100) ? $raw_ttd : ($existing_header['ttd_petugas'] ?? null);

    if ($edit_id > 0) {
        $header_id = $edit_id;
        $nomor_dok = $existing_header['nomor_dokumen'];
        if (empty($nomor_dok)) {
            $nomor_dok = 'GA/' . strtoupper($jenis_pekerjaan) . '/' . date('Ym') . '/' . str_pad($header_id, 4, '0', STR_PAD_LEFT);
        }

        $stmt_header = $pdo->prepare("
            UPDATE gelar_alat_header SET
                nomor_dokumen    = :nomor_dok,
                tanggal_inspeksi = :tanggal,
                bulan_tahun      = :bulan_tahun,
                jenis_pekerjaan  = :jenis,
                regu_id          = :regu_id,
                nama_pelaksana_1 = :nama1,
                nama_pelaksana_2 = :nama2,
                pendamping_admin = :pendamping,
                catatan_umum     = :catatan,
                foto_kegiatan    = :foto,
                status           = :status,
                ttd_petugas      = :ttd,
                updated_at       = NOW()
            WHERE id = :id
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
            ':id'          => $header_id
        ]);

        // Hapus detail lama untuk di-refresh
        $pdo->prepare("DELETE FROM gelar_alat_detail WHERE gelar_alat_id = :id")->execute([':id' => $header_id]);

    } else {
        // Generate nomor dokumen baru
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
    }

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

        // Foto temuan item (gunakan yang sudah ada jika tidak upload baru)
        $foto_temuan_path = !empty($item_data['existing_foto']) ? $item_data['existing_foto'] : null;
        $foto_key_1 = 'foto_item_' . $item_tpl_id;
        $foto_key_2 = 'foto_item_m_' . $item_tpl_id;
        $foto_src_key = isset($_FILES[$foto_key_1]) && !empty($_FILES[$foto_key_1]['tmp_name']) ? $foto_key_1 :
                       (isset($_FILES[$foto_key_2]) && !empty($_FILES[$foto_key_2]['tmp_name']) ? $foto_key_2 : null);

        if ($foto_src_key) {
            $dir_t = __DIR__ . '/../uploads/foto_temuan/';
            if (!is_dir($dir_t)) mkdir($dir_t, 0777, true);
            $ext_t = pathinfo($_FILES[$foto_src_key]['name'], PATHINFO_EXTENSION);
            $fname_t = 'temuan_' . $header_id . '_' . $barang_id . '_' . uniqid() . '.' . $ext_t;
            if (move_uploaded_file($_FILES[$foto_src_key]['tmp_name'], $dir_t . $fname_t)) {
                $foto_temuan_path = $fname_t;
            }
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

    // 4. Kirim Notifikasi ke Manajemen jika disubmit
    if ($action === 'submit') {
        try {
            $manajemen_users = $pdo->query("SELECT id FROM users WHERE role = 'manajemen'")->fetchAll(PDO::FETCH_COLUMN);
            $stmt_notif = $pdo->prepare("
                INSERT INTO notifikasi (user_id, judul, pesan, tipe, url_aksi)
                VALUES (:uid, :judul, :pesan, 'info', :url)
            ");
            foreach ($manajemen_users as $mid) {
                $stmt_notif->execute([
                    ':uid'   => $mid,
                    ':judul' => ($edit_id > 0 ? "Revisi Gelar Alat Masuk: " : "Laporan Gelar Alat Baru: ") . $nomor_dok,
                    ':pesan' => "Petugas {$user['nama_lengkap']} telah " . ($edit_id > 0 ? "mengirimkan revisi dokumen" : "mengirimkan laporan") . " gelar alat untuk direview.",
                    ':url'   => 'approval/review.php?id=' . $header_id
                ]);
            }
        } catch (Exception $e) {
            // Abaikan jika ada kendala notifikasi
        }
    }

    $pdo->commit();

    if ($action === 'submit') {
        $msg_prefix = ($edit_id > 0) ? "Revisi Laporan Gelar Alat berhasil dikirimkan kembali ke Manajemen Atasan!" : "Laporan Gelar Alat berhasil dikirimkan ke Manajemen Atasan!";
        set_flash('success', "{$msg_prefix} Nomor Dokumen: <strong>{$nomor_dok}</strong>. Menunggu persetujuan & tanda tangan atasan.");
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
