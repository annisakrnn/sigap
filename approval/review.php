<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_login();
$id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
set_flash('info', 'Verifikasi dan tanda tangan pengesahan dilaksanakan secara offline pada lembar Berita Acara cetak.');
header("Location: " . base_url('riwayat/detail.php?id=' . $id));
exit;
