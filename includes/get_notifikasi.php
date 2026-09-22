<?php
/**
 * SIGAP - AJAX: Ambil notifikasi unread untuk user yang login
 * GET: jumlah=1 → hanya count | jumlah=0 → list lengkap
 */
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$user = get_logged_user();
if (!$user) {
    echo json_encode(['ok' => false, 'count' => 0, 'items' => []]);
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['ok' => false, 'count' => 0, 'items' => []]);
    exit;
}

$hanya_count = isset($_GET['jumlah']) && $_GET['jumlah'] == '1';

try {
    if ($hanya_count) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = :uid AND sudah_dibaca = 0");
        $stmt->execute([':uid' => $user['id']]);
        echo json_encode(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
    } else {
        // Ambil 20 notifikasi terbaru (read + unread)
        $stmt = $pdo->prepare("
            SELECT id, judul, pesan, tipe, url_aksi, sudah_dibaca, created_at
            FROM notifikasi
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([':uid' => $user['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count unread
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = :uid AND sudah_dibaca = 0");
        $count_stmt->execute([':uid' => $user['id']]);
        $count = (int)$count_stmt->fetchColumn();

        // Format waktu relatif
        foreach ($items as &$item) {
            $diff = time() - strtotime($item['created_at']);
            if ($diff < 60) $item['waktu'] = 'Baru saja';
            elseif ($diff < 3600) $item['waktu'] = floor($diff/60) . ' mnt lalu';
            elseif ($diff < 86400) $item['waktu'] = floor($diff/3600) . ' jam lalu';
            else $item['waktu'] = date('d/m/Y', strtotime($item['created_at']));
        }

        echo json_encode(['ok' => true, 'count' => $count, 'items' => $items]);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'count' => 0, 'items' => []]);
}
