<?php
/**
 * Handler Proses Approval/Reject Berita Acara
 * SIGAP - Role: Manajemen Atasan
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_role('manajemen');

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard/index.php"); exit;
}

$user = get_logged_user();
$gelar_alat_id = intval($_POST['gelar_alat_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$gelar_alat_id || !in_array($action, ['approve', 'reject'])) {
    set_flash('error', "Request tidak valid.");
    header("Location: ../dashboard/index.php"); exit;
}

// Verifikasi dokumen ada dan statusnya submitted
$stmt_check = $pdo->prepare("SELECT id, status, petugas_id, nomor_dokumen, jenis_pekerjaan FROM gelar_alat_header WHERE id = :id AND status = 'submitted' LIMIT 1");
$stmt_check->execute([':id' => $gelar_alat_id]);
$doc = $stmt_check->fetch();

if (!$doc) {
    set_flash('error', "Dokumen tidak ditemukan atau sudah diproses sebelumnya.");
    header("Location: ../dashboard/index.php"); exit;
}

if ($action === 'approve') {
    // Validasi TTD
    $ttd = trim($_POST['ttd_manajemen'] ?? '');
    if (empty($ttd) || strlen($ttd) < 100) {
        set_flash('error', "Tanda tangan digital Manajemen Atasan belum diisi. Silakan tanda tangan pada kolom yang tersedia.");
        header("Location: review.php?id=" . $gelar_alat_id); exit;
    }

    $nama_pejabat = trim($_POST['nama_pejabat'] ?? $user['nama_lengkap']);
    $jabatan_pejabat = trim($_POST['jabatan_pejabat'] ?? $user['jabatan']);
    $catatan = trim($_POST['catatan_manajemen'] ?? '') ?: null;

    // Pastikan nomor dokumen ada
    $nomor_dokumen = $doc['nomor_dokumen'];
    if (empty($nomor_dokumen)) {
        $nomor_dokumen = 'GA/' . strtoupper($doc['jenis_pekerjaan']) . '/' . date('Ym') . '/' . str_pad($gelar_alat_id, 4, '0', STR_PAD_LEFT);
    }

    $stmt_update = $pdo->prepare("
        UPDATE gelar_alat_header SET
            nomor_dokumen = :no_dok,
            status = 'approved',
            ttd_manajemen = :ttd,
            manajemen_id = :mid,
            nama_pejabat_manajemen = :nama_pejabat,
            jabatan_manajemen = :jabatan,
            catatan_manajemen = :catatan,
            tanggal_approval = NOW()
        WHERE id = :id
    ");
    $stmt_update->execute([
        ':no_dok'       => $nomor_dokumen,
        ':ttd'          => $ttd,
        ':mid'          => $user['id'],
        ':nama_pejabat' => $nama_pejabat,
        ':jabatan'      => $jabatan_pejabat,
        ':catatan'      => $catatan,
        ':id'           => $gelar_alat_id
    ]);

    // Kirim notifikasi ke petugas pemeriksa
    try {
        $stmt_notif = $pdo->prepare("
            INSERT INTO notifikasi (user_id, judul, pesan, tipe, url_aksi)
            VALUES (:uid, :judul, :pesan, 'sukses', :url)
        ");
        $stmt_notif->execute([
            ':uid'   => $doc['petugas_id'],
            ':judul' => "Berita Acara Disahkan: " . $nomor_dokumen,
            ':pesan' => "Laporan gelar alat Anda telah disahkan dan ditandatangani oleh {$nama_pejabat} ({$jabatan_pejabat}).",
            ':url'   => 'riwayat/detail.php?id=' . $gelar_alat_id
        ]);
    } catch (Exception $e) {
        // Fallback aman jika tabel notifikasi belum ada
    }

    set_flash('success', "Berita Acara Gelar Alat <strong>{$nomor_dokumen}</strong> telah berhasil <strong>disahkan & ditandatangani</strong>. Dokumen resmi siap dicetak.");
    header("Location: review.php?id=" . $gelar_alat_id); exit;

} elseif ($action === 'reject') {
    $catatan_reject = trim($_POST['catatan_manajemen'] ?? '');
    if (empty($catatan_reject)) {
        $catatan_reject = "Laporan dikembalikan untuk perbaikan oleh " . $user['nama_lengkap'];
    }

    $stmt_reject = $pdo->prepare("
        UPDATE gelar_alat_header SET
            status = 'rejected',
            manajemen_id = :mid,
            nama_pejabat_manajemen = :nama_pejabat,
            jabatan_manajemen = :jabatan,
            catatan_manajemen = :catatan,
            tanggal_approval = NOW()
        WHERE id = :id
    ");
    $stmt_reject->execute([
        ':mid'          => $user['id'],
        ':nama_pejabat' => trim($_POST['nama_pejabat'] ?? $user['nama_lengkap']),
        ':jabatan'      => trim($_POST['jabatan_pejabat'] ?? $user['jabatan']),
        ':catatan'      => $catatan_reject,
        ':id'           => $gelar_alat_id
    ]);

    // Kirim notifikasi ke petugas pemeriksa
    try {
        $stmt_notif = $pdo->prepare("
            INSERT INTO notifikasi (user_id, judul, pesan, tipe, url_aksi)
            VALUES (:uid, :judul, :pesan, 'peringatan', :url)
        ");
        $stmt_notif->execute([
            ':uid'   => $doc['petugas_id'],
            ':judul' => "Laporan Dikembalikan untuk Revisi: " . ($doc['nomor_dokumen'] ?? 'Dokumen #' . $gelar_alat_id),
            ':pesan' => "Catatan Manajemen: {$catatan_reject}",
            ':url'   => 'riwayat/detail.php?id=' . $gelar_alat_id
        ]);
    } catch (Exception $e) {
        // Fallback aman
    }

    set_flash('warning', "Laporan dikembalikan ke petugas pemeriksa untuk <strong>revisi / perbaikan</strong>. Catatan arahan: <em>" . htmlspecialchars($catatan_reject) . "</em>");
    header("Location: review.php?id=" . $gelar_alat_id); exit;
}
