<?php
/**
 * Endpoint Serve Foto Kegiatan Apel dari MySQL (BLOB)
 * URL: foto/kegiatan.php?id={header_id}
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';

$header_id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
if (!$header_id) {
    http_response_code(400);
    exit('ID tidak valid.');
}

$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(500);
    exit('Koneksi database gagal.');
}

$stmt = $pdo->prepare("SELECT foto_kegiatan, foto_kegiatan_mime FROM gelar_alat_header WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $header_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['foto_kegiatan'])) {
    http_response_code(404);
    exit('Foto tidak ditemukan.');
}

$mime = !empty($row['foto_kegiatan_mime']) ? $row['foto_kegiatan_mime'] : 'image/jpeg';

// Validasi mime aman
$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed_mimes, true)) {
    $mime = 'image/jpeg';
}

// Kirim header cache ringan (5 menit)
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=300');
header('Content-Length: ' . strlen($row['foto_kegiatan']));
echo $row['foto_kegiatan'];
exit;
