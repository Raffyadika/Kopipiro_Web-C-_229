<?php
// init.php — di-include di BARIS PERTAMA setiap halaman.
// Memuat konfigurasi, memulai session, dan memuat fungsi bantu + autentikasi.
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
?>
