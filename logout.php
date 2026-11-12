<?php
// logout.php — hanya menerima POST (dengan token CSRF) agar tidak bisa dipicu lewat link/gambar
include 'init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    session_unset();   // Hapus semua variabel session (termasuk keranjang)
    session_destroy(); // Hancurkan session di server
}
redirect('login.php');
