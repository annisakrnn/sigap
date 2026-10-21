<?php
/**
 * Handler Simpan Transaksi Gelar Alat
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_csrf();

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php"); exit;
}

$user = get_logged_user();
$action = isset($_POST['action']) ? $_POST['action'] : 'draft'; // 'draft' or 'submit'

// Tentukan petugas_id untuk integritas database
$petugas_id = $user ? $user['id'] : null;
if (!$petugas_id) {
    $stmt_def = $pdo->query("SELECT id FROM users WHERE username = 'petugas_lapangan' OR role = 'petugas' ORDER BY (username = 'petugas_lapangan') DESC, id ASC LIMIT 1");
    $petugas_id = $stmt_def->fetchColumn();
    if (!$petugas_id) {
        $pdo->prepare("INSERT INTO users (username, password_hash, nama_lengkap, role, jabatan) VALUES ('petugas_lapangan', :pw, 'Petugas Lapangan', 'petugas', 'Pelaksana Lapangan')")
            ->execute([':pw' => password_hash('123456', PASSWORD_BCRYPT)]);
        $petugas_id = $pdo->lastInsertId();
    }
}

// Validasi input dasar
$jenis_param = isset($_POST['jenis_pekerjaan']) ? $_POST['jenis_pekerjaan'] : '';
$jenis_pekerjaan = in_array($jenis_param, ['p2tl','sr_app','yandal','har']) ? $jenis_param : null;
$regu_id = intval(isset($_POST['regu_id']) ? $_POST['regu_id'] : 0);

if (!$jenis_pekerjaan || !$regu_id) {
    set_flash('error', "Data formulir tidak valid. Silakan ulangi.");
    header("Location: index.php"); exit;
}

$edit_id = intval(isset($_POST['edit_id']) ? $_POST['edit_id'] : 0);
$existing_header = null;
if ($edit_id > 0) {
    if ($user && $user['role'] === 'manajemen') {
        $stmt_cek = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = :id LIMIT 1");
        $stmt_cek->execute([':id' => $edit_id]);
    } elseif ($user) {
        $stmt_cek = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = :id AND petugas_id = :uid LIMIT 1");
        $stmt_cek->execute([':id' => $edit_id, ':uid' => $user['id']]);
    } else {
        $stmt_cek = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = :id LIMIT 1");
        $stmt_cek->execute([':id' => $edit_id]);
    }
    $existing_header = $stmt_cek->fetch();
    // Allow saving if draft/rejected or if user is manajemen (they can override)
    if (!$existing_header || (!in_array($existing_header['status'], ['draft', 'rejected']) && ($user ? $user['role'] !== 'manajemen' : false))) {
        set_flash('error', "Dokumen tidak dapat diubah.");
        header("Location: index.php"); exit;
    }
}

// Validasi TTD untuk submit
if ($action === 'submit') {
    $ttd = trim(isset($_POST['ttd_petugas']) ? $_POST['ttd_petugas'] : '');
    $has_existing_ttd = ($existing_header && !empty($existing_header['ttd_petugas']));
    if ((empty($ttd) || strlen($ttd) < 100) && !$has_existing_ttd) {
        set_flash('error', "Tanda tangan digital petugas pemeriksa belum diisi. Silakan tanda tangan pada kolom yang tersedia.");
        $redirect_back = $edit_id ? "form.php?edit_id={$edit_id}" : "form.php?jenis={$jenis_pekerjaan}&regu_id={$regu_id}";
        header("Location: " . $redirect_back); exit;
    }
}

try {
    $pdo->beginTransaction();

    // 1. Upload foto kegiatan aman
    $foto_kegiatan_path = isset($_POST['existing_foto_kegiatan']) ? $_POST['existing_foto_kegiatan'] : (isset($existing_header['foto_kegiatan']) ? $existing_header['foto_kegiatan'] : null);
    if (!empty($_FILES['foto_kegiatan']['name']) && !empty($_FILES['foto_kegiatan']['tmp_name'])) {
        $up_kegiatan = secure_upload_image($_FILES['foto_kegiatan'], 'foto_kegiatan', 'apel');
        if ($up_kegiatan['success']) {
            $foto_kegiatan_path = $up_kegiatan['filename'];
        } else {
            throw new Exception("Gagal mengunggah Foto Kegiatan: " . $up_kegiatan['error']);
        }
    }

    // 2. Simpan atau Update header gelar alat
    $status = ($action === 'submit') ? 'submitted' : 'draft';
    $raw_ttd = trim(isset($_POST['ttd_petugas']) ? $_POST['ttd_petugas'] : '');
    $ttd_petugas = (!empty($raw_ttd) && strlen($raw_ttd) >= 100) ? $raw_ttd : (isset($existing_header['ttd_petugas']) ? $existing_header['ttd_petugas'] : null);

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
                jabatan_petugas  = :jabatan,
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
            ':tanggal'     => isset($_POST['tanggal_inspeksi']) ? $_POST['tanggal_inspeksi'] : date('Y-m-d'),
            ':bulan_tahun' => isset($_POST['bulan_tahun']) ? $_POST['bulan_tahun'] : date('F Y'),
            ':jenis'       => $jenis_pekerjaan,
            ':regu_id'     => $regu_id,
            ':nama1'       => trim(isset($_POST['nama_pelaksana_1']) ? $_POST['nama_pelaksana_1'] : ($user ? $user['nama_lengkap'] : 'Petugas Lapangan')),
            ':jabatan'     => trim(isset($_POST['jabatan_petugas']) ? $_POST['jabatan_petugas'] : ($user ? $user['jabatan'] : '')) ?: null,
            ':nama2'       => trim(isset($_POST['nama_pelaksana_2']) ? $_POST['nama_pelaksana_2'] : '') ?: null,
            ':pendamping'  => trim(isset($_POST['pendamping_admin']) ? $_POST['pendamping_admin'] : '') ?: null,
            ':catatan'     => trim(isset($_POST['catatan_umum']) ? $_POST['catatan_umum'] : '') ?: null,
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
                 nama_pelaksana_1, jabatan_petugas, nama_pelaksana_2, pendamping_admin, catatan_umum, 
                 foto_kegiatan, status, ttd_petugas, petugas_id)
            VALUES 
                (:nomor_dok, :tanggal, :bulan_tahun, :jenis, :regu_id,
                 :nama1, :jabatan, :nama2, :pendamping, :catatan,
                 :foto, :status, :ttd, :petugas_id)
        ");
        $stmt_header->execute([
            ':nomor_dok'   => $nomor_dok,
            ':tanggal'     => isset($_POST['tanggal_inspeksi']) ? $_POST['tanggal_inspeksi'] : date('Y-m-d'),
            ':bulan_tahun' => isset($_POST['bulan_tahun']) ? $_POST['bulan_tahun'] : date('F Y'),
            ':jenis'       => $jenis_pekerjaan,
            ':regu_id'     => $regu_id,
            ':nama1'       => trim(isset($_POST['nama_pelaksana_1']) ? $_POST['nama_pelaksana_1'] : ($user ? $user['nama_lengkap'] : 'Petugas Lapangan')),
            ':jabatan'     => trim(isset($_POST['jabatan_petugas']) ? $_POST['jabatan_petugas'] : ($user ? $user['jabatan'] : '')) ?: null,
            ':nama2'       => trim(isset($_POST['nama_pelaksana_2']) ? $_POST['nama_pelaksana_2'] : '') ?: null,
            ':pendamping'  => trim(isset($_POST['pendamping_admin']) ? $_POST['pendamping_admin'] : '') ?: null,
            ':catatan'     => trim(isset($_POST['catatan_umum']) ? $_POST['catatan_umum'] : '') ?: null,
            ':foto'        => $foto_kegiatan_path,
            ':status'      => $status,
            ':ttd'         => $ttd_petugas,
            ':petugas_id'  => $petugas_id
        ]);
        $header_id = $pdo->lastInsertId();
    }

    // 3. Simpan detail items
    $items = isset($_POST['items']) ? $_POST['items'] : [];
    $stmt_detail = $pdo->prepare("
        INSERT INTO gelar_alat_detail 
            (gelar_alat_id, master_barang_id, nama_barang_snapshot, kategori_snapshot,
             jumlah_standar, jumlah_realisasi, satuan, kondisi, keterangan, foto_temuan, foto_temuan_mime)
        VALUES 
            (:header_id, :barang_id, :nama_snapshot, :kategori_snapshot,
             :std, :realisasi, :satuan, :kondisi, :ket, :foto, :foto_mime)
    ");

    // Ambil nama barang dari DB untuk snapshot
    $stmt_nama = $pdo->prepare("SELECT nama_barang, kategori FROM master_barang WHERE id = :id LIMIT 1");

    foreach ($items as $item_tpl_id => $item_data) {
        $barang_id = intval(isset($item_data['barang_id']) ? $item_data['barang_id'] : 0);
        if (!$barang_id) continue;

        $stmt_nama->execute([':id' => $barang_id]);
        $barang = $stmt_nama->fetch();
        if (!$barang) continue;

        // Foto temuan item — simpan binary langsung ke MySQL (MEDIUMBLOB)
        $foto_temuan_blob = null;
        $foto_temuan_mime = null;

        // Jika ada foto baru di-upload, baca sebagai binary
        $foto_key_1 = 'foto_item_' . $item_tpl_id;
        $foto_key_2 = 'foto_item_m_' . $item_tpl_id;
        $foto_src_key = isset($_FILES[$foto_key_1]) && !empty($_FILES[$foto_key_1]['tmp_name']) ? $foto_key_1 :
                       (isset($_FILES[$foto_key_2]) && !empty($_FILES[$foto_key_2]['tmp_name']) ? $foto_key_2 : null);

        if ($foto_src_key && !empty($_FILES[$foto_src_key]['name'])) {
            $up_temuan = secure_read_image_for_db($_FILES[$foto_src_key]);
            if ($up_temuan['success']) {
                $foto_temuan_blob = $up_temuan['data'];
                $foto_temuan_mime = $up_temuan['mime'];
            }
            // Jika gagal baca blob, biarkan null (tidak throw exception agar item tetap tersimpan)
        } elseif (!empty($item_data['existing_foto_id'])) {
            // Jika ada foto lama (edit mode) — ambil blob dari DB berdasarkan detail_id lama
            $old_id = intval($item_data['existing_foto_id']);
            if ($old_id > 0) {
                $stmt_old = $pdo->prepare("SELECT foto_temuan, foto_temuan_mime FROM gelar_alat_detail WHERE id = :id LIMIT 1");
                $stmt_old->execute([':id' => $old_id]);
                $old_row = $stmt_old->fetch(PDO::FETCH_ASSOC);
                if ($old_row && !empty($old_row['foto_temuan'])) {
                    $foto_temuan_blob = $old_row['foto_temuan'];
                    $foto_temuan_mime = $old_row['foto_temuan_mime'];
                }
            }
        }

        $kondisi_val = isset($item_data['kondisi']) ? $item_data['kondisi'] : '';
        $stmt_detail->execute([
            ':header_id'         => $header_id,
            ':barang_id'         => $barang_id,
            ':nama_snapshot'     => $barang['nama_barang'],
            ':kategori_snapshot' => isset($item_data['kategori']) ? $item_data['kategori'] : $barang['kategori'],
            ':std'               => intval(isset($item_data['jumlah_standar']) ? $item_data['jumlah_standar'] : 1),
            ':realisasi'         => intval(isset($item_data['realisasi']) ? $item_data['realisasi'] : 0),
            ':satuan'            => isset($item_data['satuan']) ? $item_data['satuan'] : '-',
            ':kondisi'           => in_array($kondisi_val, ['baik','rusak','waktu_ganti','hilang','ada','tidak_ada']) ? $kondisi_val : 'baik',
            ':ket'               => trim(isset($item_data['keterangan']) ? $item_data['keterangan'] : '') ?: null,
            ':foto'              => $foto_temuan_blob,
            ':foto_mime'         => $foto_temuan_mime,
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
            $nama_notif_sender = trim(isset($_POST['nama_pelaksana_1']) ? $_POST['nama_pelaksana_1'] : ($user ? $user['nama_lengkap'] : 'Pelaksana Lapangan'));
            foreach ($manajemen_users as $mid) {
                $stmt_notif->execute([
                    ':uid'   => $mid,
                    ':judul' => ($edit_id > 0 ? "Revisi Gelar Alat Masuk: " : "Laporan Gelar Alat Baru: ") . $nomor_dok,
                    ':pesan' => "Petugas {$nama_notif_sender} telah " . ($edit_id > 0 ? "mengirimkan revisi dokumen" : "mengirimkan laporan") . " gelar alat untuk direview.",
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
