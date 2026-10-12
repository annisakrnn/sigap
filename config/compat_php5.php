<?php
/**
 * Polyfill & Kompatibilitas untuk PHP 5.6.40+
 * SIGAP
 */

// Polyfill random_bytes jika belum ada (PHP < 7.0)
if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        $length = intval($length);
        if ($length <= 0) {
            return '';
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $crypto_strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $crypto_strong);
            if ($bytes !== false) {
                return $bytes;
            }
        }
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }
}
