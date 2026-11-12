<?php
// config.php — pengaturan database & aplikasi Kopipiro
date_default_timezone_set('Asia/Jakarta');

$db_host = "localhost";
$db_user = "root";         // Username bawaan XAMPP
$db_pass = "";             // Password bawaan XAMPP (kosong)
$db_name = "db_kopipiro";  // Sama dengan nama database di database.sql

define('DISCOUNT_PERCENT', 10);              // Diskon untuk customer yang login
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024); // Batas ukuran gambar produk (2 MB)

// Alamat dasar aplikasi, mis. "/kopipiro" bila foldernya htdocs/kopipiro.
// Dideteksi otomatis. Kalau link/gambar rusak, isi manual: $base_url_override = '/kopipiro';
$base_url_override = null;

if ($base_url_override !== null) {
    define('BASE_URL', rtrim($base_url_override, '/'));
} else {
    $docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $appDir = rtrim(str_replace('\\', '/', (string) realpath(__DIR__)), '/');
    define('BASE_URL', ($docRoot !== '' && stripos($appDir, $docRoot) === 0) ? substr($appDir, strlen($docRoot)) : '');
}

// Error database dilempar sebagai exception (dipakai untuk menangkap username ganda, dll.)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    mysqli_set_charset($conn, 'utf8mb4');
    mysqli_query($conn, "SET time_zone = '+07:00'"); // Samakan dengan zona waktu PHP (WIB)
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die("Koneksi database gagal. Pastikan MySQL menyala dan database '$db_name' sudah diimport dari database.sql.");
}
?>
