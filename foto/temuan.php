<?php
/**
 * Endpoint Serve Foto Temuan dari MySQL (BLOB)
 * URL: foto/temuan.php?id={detail_id}
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

$detail_id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
if (!$detail_id) {
    http_response_code(400);
    exit('ID tidak valid.');
}

$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(500);
    exit('Koneksi database gagal.');
}

$stmt = $pdo->prepare("SELECT foto_temuan, foto_temuan_mime FROM gelar_alat_detail WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $detail_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['foto_temuan'])) {
    http_response_code(404);
    exit('Foto tidak ditemukan.');
}

$mime = !empty($row['foto_temuan_mime']) ? $row['foto_temuan_mime'] : 'image/jpeg';

// Validasi mime aman
$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed_mimes, true)) {
    $mime = 'image/jpeg';
}

// Kirim header cache ringan (5 menit)
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=300');
header('Content-Length: ' . strlen($row['foto_temuan']));
echo $row['foto_temuan'];
exit;
