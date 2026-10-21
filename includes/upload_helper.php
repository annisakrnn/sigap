<?php
/**
 * Helper Validasi & Upload File Gambar Aman (Anti-RCE & Security Hardening)
 * SIGAP
 */

require_once __DIR__ . '/../config/compat_php5.php';

if (!defined('ALLOWED_IMAGE_EXTENSIONS')) {
    define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
}

if (!defined('ALLOWED_IMAGE_MIMES')) {
    define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/webp']);
}

if (!defined('MAX_UPLOAD_SIZE_BYTES')) {
    define('MAX_UPLOAD_SIZE_BYTES', 15 * 1024 * 1024); // 15 MB
}

/**
 * Pastikan direktori uploads aman dari eksekusi skrip web server
 */
function ensure_upload_security() {
    $base_upload_dir = __DIR__ . '/../uploads';
    $subdirs = ['', 'foto_kegiatan', 'foto_temuan', 'ttd', 'perbaikan', 'referensi'];

    $htaccess_content = "# Proteksi Direktori Upload SIGAP\n"
        . "# Mencegah eksekusi skrip PHP, CGI, dan skrip eksekusi lainnya\n"
        . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phps|inc|pl|py|cgi|sh|bash|exe|asp|aspx|jsp)$\">\n"
        . "    Order Deny,Allow\n"
        . "    Deny from all\n"
        . "</FilesMatch>\n\n"
        . "Options -Indexes -ExecCGI\n"
        . "<IfModule mod_php.c>\n"
        . "    php_flag engine off\n"
        . "</IfModule>\n"
        . "<IfModule mod_php7.c>\n"
        . "    php_flag engine off\n"
        . "</IfModule>\n"
        . "<IfModule mod_php8.c>\n"
        . "    php_flag engine off\n"
        . "</IfModule>\n";

    foreach ($subdirs as $sub) {
        $dir = $sub === '' ? $base_upload_dir : $base_upload_dir . '/' . $sub;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ht_file = $dir . '/.htaccess';
        if (!file_exists($ht_file)) {
            @file_put_contents($ht_file, $htaccess_content);
        }
        $idx_file = $dir . '/index.html';
        if (!file_exists($idx_file)) {
            @file_put_contents($idx_file, '<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>403 Forbidden</h1></body></html>');
        }
    }
}

/**
 * Validasi dan simpan file gambar upload secara aman
 * 
 * @param array $file_array Elemen dari $_FILES (misal: $_FILES['foto'])
 * @param string $subfolder Nama subfolder di dalam /uploads/ (misal: 'foto_kegiatan')
 * @param string $prefix Prefix nama file (misal: 'apel', 'temuan', 'perbaikan')
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function secure_upload_image($file_array, $subfolder, $prefix = 'img') {
    ensure_upload_security();

    if (!isset($file_array) || !is_array($file_array) || empty($file_array['name'])) {
        return ['success' => false, 'filename' => null, 'error' => 'Tidak ada file yang diunggah.'];
    }

    if ($file_array['error'] !== UPLOAD_ERR_OK) {
        $err_messages = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas upload server (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terunggah sebagian. Silakan ulangi.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk server.',
            UPLOAD_ERR_EXTENSION  => 'Unggahan dihentikan oleh ekstensi PHP.',
        ];
        return ['success' => false, 'filename' => null, 'error' => isset($err_messages[$file_array['error']]) ? $err_messages[$file_array['error']] : 'Error upload tidak diketahui.'];
    }

    // 1. Cek ukuran file
    if ($file_array['size'] > MAX_UPLOAD_SIZE_BYTES) {
        return ['success' => false, 'filename' => null, 'error' => 'Ukuran file terlalu besar (maksimal 15MB).'];
    }

    // 2. Cek ekstensi whitelist
    $orig_name = $file_array['name'];
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'Format file tidak diizinkan. Hanya file JPG, PNG, dan WEBP yang diperbolehkan.'];
    }

    // Normalisasi ekstensi jpg/jpeg
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    // 3. Cek MIME Type menggunakan Fileinfo
    $tmp_path = $file_array['tmp_name'];
    if (!is_uploaded_file($tmp_path)) {
        return ['success' => false, 'filename' => null, 'error' => 'File tidak valid (bukan uploaded file).'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_MIMES, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'Tipe konten file tidak valid (' . htmlspecialchars($mime) . '). File harus berupa gambar asli.'];
    }

    // 4. Verifikasi gambar valid menggunakan getimagesize
    $img_info = @getimagesize($tmp_path);
    if ($img_info === false) {
        return ['success' => false, 'filename' => null, 'error' => 'File gambar rusak atau tidak dapat dibaca.'];
    }

    // 5. Generate nama file unik & aman tanpa membawa nama asli pengguna
    $safe_prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
    try {
        $random_suffix = bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $random_suffix = substr(md5(uniqid('', true)), 0, 12);
    }
    $new_filename = $safe_prefix . '_' . date('Ymd_His') . '_' . $random_suffix . '.' . $ext;

    // 6. Siapkan folder target
    $clean_sub = trim(str_replace(['..', '/', '\\'], '', $subfolder));
    $dest_dir = __DIR__ . '/../uploads/' . $clean_sub . '/';
    if (!is_dir($dest_dir)) {
        @mkdir($dest_dir, 0755, true);
    }

    $dest_path = $dest_dir . $new_filename;

    // 7. Pindahkan file
    if (!move_uploaded_file($tmp_path, $dest_path)) {
        return ['success' => false, 'filename' => null, 'error' => 'Gagal memindahkan file ke folder tujuan.'];
    }

    @chmod($dest_path, 0644);

    return [
        'success'  => true,
        'filename' => $new_filename,
        'path'     => $dest_path,
        'error'    => null
    ];
}

/**
 * Validasi gambar upload dan kembalikan binary data + mime type
 * untuk disimpan langsung ke kolom MEDIUMBLOB MySQL.
 * Tidak ada file yang ditulis ke disk server.
 *
 * @param array $file_array Elemen dari $_FILES (misal: $_FILES['foto_item_1'])
 * @return array ['success' => bool, 'data' => string|null, 'mime' => string|null, 'error' => string|null]
 */
function secure_read_image_for_db($file_array) {
    if (!isset($file_array) || !is_array($file_array) || empty($file_array['name'])) {
        return ['success' => false, 'data' => null, 'mime' => null, 'error' => 'Tidak ada file yang diunggah.'];
    }

    if ($file_array['error'] !== UPLOAD_ERR_OK) {
        $err_messages = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas upload server (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terunggah sebagian. Silakan ulangi.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk server.',
            UPLOAD_ERR_EXTENSION  => 'Unggahan dihentikan oleh ekstensi PHP.',
        ];
        return ['success' => false, 'data' => null, 'mime' => null, 'error' => isset($err_messages[$file_array['error']]) ? $err_messages[$file_array['error']] : 'Error upload tidak diketahui.'];
    }

    // 1. Cek ukuran
    if ($file_array['size'] > MAX_UPLOAD_SIZE_BYTES) {
        return ['success' => false, 'data' => null, 'mime' => null, 'error' => 'Ukuran file terlalu besar (maksimal 15MB).'];
    }

    // 2. Cek ekstensi whitelist
    $ext = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
        return ['success' => false, 'data' => null, 'mime' => null, 'error' => 'Format file tidak diizinkan. Hanya JPG, PNG, dan WEBP yang diperbolehkan.'];
    }

    // 3. Cek file adalah uploaded file yang sah
    $tmp_path = $file_array['tmp_name'];
    if (!is_uploaded_file($tmp_path)) {
        return ['success' => false, 'data' => null, 'mime' => null, 'error' => 'File tidak valid (bukan uploaded file).'];
    }

    // 4. Cek MIME Type via Fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_MIMES, true)) {
        return ['success' => false, 'data' => null, 'mime' => null, 'error' => 'Tipe konten file tidak valid (' . htmlspecialchars($mime) . '). File harus berupa gambar asli.'];
    }

    // 5. Verifikasi gambar valid
    $img_info = @getimagesize($tmp_path);
    if ($img_info === false) {
        return ['success' => false, 'data' => null, 'mime' => null, 'error' => 'File gambar rusak atau tidak dapat dibaca.'];
    }

    // 6. Baca binary data
    $binary = file_get_contents($tmp_path);
    if ($binary === false) {
        return ['success' => false, 'data' => null, 'mime' => null, 'error' => 'Gagal membaca isi file gambar.'];
    }

    // Normalisasi mime jpeg
    if ($mime === 'image/pjpeg') {
        $mime = 'image/jpeg';
    }
    if ($mime === 'image/x-png') {
        $mime = 'image/png';
    }

    return [
        'success' => true,
        'data'    => $binary,
        'mime'    => $mime,
        'error'   => null,
    ];
}
