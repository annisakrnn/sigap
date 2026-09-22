<?php
/**
 * SIGAP - AJAX: Mark Temuan Alat sebagai Sudah Diperbaiki
 * POST: detail_id, catatan_perbaikan (opsional)
 * Hanya bisa diakses oleh role: manajemen
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('manajemen');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Method tidak diizinkan']);
    exit;
}

$detail_id = (int)($_POST['detail_id'] ?? 0);
$catatan   = trim($_POST['catatan_perbaikan'] ?? '');

if ($detail_id <= 0) {
    echo json_encode(['ok' => false, 'pesan' => 'ID temuan tidak valid']);
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['ok' => false, 'pesan' => 'Koneksi database gagal']);
    exit;
}

$user = get_logged_user();

try {
    // 1. Ambil data temuan + petugas pemeriksa + info laporan
    $stmt = $pdo->prepare("
        SELECT d.id, d.nama_barang_snapshot, d.kondisi, d.sudah_diperbaiki,
               h.id AS header_id, h.petugas_id, h.tanggal_inspeksi, h.jenis_pekerjaan,
               r.nama_regu,
               u.nama_lengkap AS nama_petugas
        FROM gelar_alat_detail d
        JOIN gelar_alat_header h ON d.gelar_alat_id = h.id
        JOIN regu r ON h.regu_id = r.id
        JOIN users u ON h.petugas_id = u.id
        WHERE d.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $detail_id]);
    $detail = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$detail) {
        echo json_encode(['ok' => false, 'pesan' => 'Data temuan tidak ditemukan']);
        exit;
    }

    if ($detail['sudah_diperbaiki']) {
        echo json_encode(['ok' => false, 'pesan' => 'Temuan ini sudah ditandai selesai sebelumnya']);
        exit;
    }

    // 2. Update kolom di gelar_alat_detail
    $upd = $pdo->prepare("
        UPDATE gelar_alat_detail
        SET sudah_diperbaiki = 1,
            tgl_diperbaiki   = NOW(),
            diperbaiki_oleh  = :uid,
            catatan_perbaikan = :catatan
        WHERE id = :id
    ");
    $upd->execute([
        ':uid'     => $user['id'],
        ':catatan' => $catatan ?: null,
        ':id'      => $detail_id,
    ]);

    // 3. Kirim notifikasi ke petugas pemeriksa
    $kondisi_label = $detail['kondisi'] === 'rusak' ? 'RUSAK' : 'PERLU GANTI';
    $tgl_label     = date('d/m/Y', strtotime($detail['tanggal_inspeksi']));
    $judul_notif   = "✅ Temuan Diperbaiki: {$detail['nama_barang_snapshot']}";
    $pesan_notif   = "Alat <strong>{$detail['nama_barang_snapshot']}</strong> ({$kondisi_label}) " .
                     "yang Anda laporkan pada laporan {$detail['nama_regu']} ({$tgl_label}) " .
                     "telah ditandai <strong>selesai diperbaiki</strong> oleh " .
                     "<strong>{$user['nama_lengkap']}</strong>.";
    if ($catatan) {
        $pesan_notif .= " Catatan: <em>{$catatan}</em>";
    }

    $ins = $pdo->prepare("
        INSERT INTO notifikasi (user_id, judul, pesan, tipe, url_aksi)
        VALUES (:uid, :judul, :pesan, 'perbaikan', :url)
    ");
    $ins->execute([
        ':uid'   => $detail['petugas_id'],
        ':judul' => $judul_notif,
        ':pesan' => $pesan_notif,
        ':url'   => base_url('riwayat/detail.php?id=' . $detail['header_id']),
    ]);

    echo json_encode([
        'ok'   => true,
        'pesan' => 'Temuan berhasil ditandai selesai dan notifikasi dikirim ke petugas.',
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Error: ' . $e->getMessage()]);
}
