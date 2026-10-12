<?php
/**
 * Hapus Berita Acara Gelar Alat (Hanya untuk Draft / Rejected)
 * SIGAP
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$pdo = get_db_connection();
if (!$pdo) { header("Location: ../setup.php"); exit; }

$user = get_logged_user();
$id = $_GET['id'] ?? '';

if (empty($id)) {
    die("ID tidak ditemukan.");
}

$stmt = $pdo->prepare("SELECT * FROM gelar_alat_header WHERE id = ?");
$stmt->execute([$id]);
$header = $stmt->fetch();

if (!$header) {
    die("Data tidak ditemukan.");
}

// Hapus status check, semua status bisa dihapus sekarang

// Pastikan petugas hanya menghapus miliknya (jika bukan admin)
if ($user['role'] === 'petugas' && $header['petugas_id'] !== $user['id']) {
    die("Anda tidak dapat menghapus dokumen ini.");
}

try {
    $pdo->beginTransaction();
    
    // Hapus detail
    $stmt = $pdo->prepare("DELETE FROM gelar_alat_detail WHERE gelar_alat_id = ?");
    $stmt->execute([$id]);
    
    // Hapus header
    $stmt = $pdo->prepare("DELETE FROM gelar_alat_header WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    header("Location: index.php?msg=deleted");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("Gagal menghapus data: " . $e->getMessage());
}
