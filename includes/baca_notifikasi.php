<?php
/**
 * SIGAP - AJAX: Tandai notifikasi sebagai sudah dibaca
 * POST: id (spesifik) atau semua jika tidak ada id
 */
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$user = get_logged_user();
if (!$user) { echo json_encode(['ok' => false]); exit; }

$pdo = get_db_connection();
if (!$pdo) { echo json_encode(['ok' => false]); exit; }

$notif_id = (int)($_POST['id'] ?? 0);

try {
    if ($notif_id > 0) {
        $stmt = $pdo->prepare("UPDATE notifikasi SET sudah_dibaca = 1 WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $notif_id, ':uid' => $user['id']]);
    } else {
        // Tandai semua
        $stmt = $pdo->prepare("UPDATE notifikasi SET sudah_dibaca = 1 WHERE user_id = :uid AND sudah_dibaca = 0");
        $stmt->execute([':uid' => $user['id']]);
    }
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false]);
}
