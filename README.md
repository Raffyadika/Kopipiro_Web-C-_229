# Kopipiro — Aplikasi Pemesanan Kopi

Aplikasi web pemesanan untuk coffee shop Kopipiro (PHP + MySQL + Bootstrap 5), dikembangkan dari aplikasi
Student Management sebelumnya.

## Cara menjalankan (XAMPP)

1. Salin folder `kopipiro` ke `C:\xampp\htdocs\kopipiro`.
2. Nyalakan **Apache** dan **MySQL** di XAMPP.
3. Buka phpMyAdmin → tab **Import** → pilih `database.sql` → Go.
   (Database `db_kopipiro` dibuat otomatis. File ini menghapus & membuat ulang semua tabel.)
4. Buka <http://localhost/kopipiro/>.

Jika link atau gambar rusak, isi `$base_url_override = '/kopipiro';` di `config.php`.

## Akun demo

| Peran    | Username | Password   |
|----------|----------|------------|
| Owner    | `owner`  | `owner123` |
| Kasir    | `kasir1`, `kasir2` | `kasir123` |
| Customer | `budi`, `sari` | `budi123`, `sari123` |

**Ganti semua password ini sebelum dipakai sungguhan** (owner bisa menambah akun baru di menu Pengguna).
Data contoh (16 pesanan 14 hari terakhir) disertakan agar grafik langsung terisi.

## Aturan bisnis

- **Tamu** (tanpa login) bisa menjelajah menu dan memesan dengan **harga normal**.
- **Customer** yang register + login otomatis mendapat **diskon 10%** (ubah di `DISCOUNT_PERCENT`, `config.php`).
  Keranjang tamu tidak hilang saat login/register.
- Harga selalu dihitung ulang di server dari database; tidak pernah dipercaya dari browser.
- Harga & nama produk disalin ke pesanan saat checkout, jadi riwayat tetap benar walau produk diubah/dihapus.
- Pesanan baru otomatis diberikan ke **kasir dengan antrean aktif paling sedikit**.
  Owner bisa memindahkannya ke kasir lain di halaman detail pesanan.
- Alur status oleh kasir: Menunggu → Diproses → Selesai (atau Dibatalkan). Pesanan dibatalkan tidak dihitung sebagai pendapatan.
- Rating hanya bisa diberikan customer yang pernah memesan produk tersebut (satu rating per produk, bisa diubah).

## Hak akses

| Fitur | Tamu | Customer | Kasir | Owner |
|---|:-:|:-:|:-:|:-:|
| Lihat menu, detail, rating | ✓ | ✓ | ✓ | ✓ |
| Keranjang & checkout | ✓ (harga normal) | ✓ (diskon 10%) | – | – |
| Riwayat pesanan sendiri | – | ✓ | – | – |
| Lihat & proses pesanan | – | – | hanya yang dia kelola | semua |
| Kelola produk, kategori, pengguna | – | – | – | ✓ |
| Grafik penjualan & unduh Excel | – | – | – | ✓ |

## Struktur file

```
config.php, init.php        koneksi DB, session, pengaturan (init.php di-include di baris pertama tiap halaman)
auth.php, functions.php     peran & penjaga halaman; fungsi bantu (CSRF, keranjang, filter laporan, dll.)
header.php footer.php navbar.php  tampilan bersama
index.php product.php       menu & detail produk (rating, ulasan)
cart.php checkout.php       keranjang & checkout (harga sudah termasuk diskon)
receipt.php my_orders.php   struk & riwayat pesanan customer
login.php register.php logout.php
owner/   orders.php (laporan + grafik) · order_detail.php · export.php (Excel)
         products.php · product_form.php (unggah gambar) · categories.php · users.php
cashier/ orders.php · order_detail.php
uploads/products/           gambar produk (PHP dilarang dijalankan di sini lewat .htaccess)
database.sql                skema + data contoh
```

## Perubahan dari aplikasi lama

- `users.php` → `owner/users.php` (peran: owner / kasir / customer).
- `create.php`, `edit.php`, `delete.php`, `index.php` (CRUD mahasiswa) diganti CRUD produk & halaman menu.
- `export.php` lama rusak (membaca kolom `major` yang tidak ada dan bisa diunduh tanpa login);
  versi baru ada di `owner/export.php`: khusus owner, data di-escape, mengikuti filter laporan.
- Nama database disamakan: `config.php` dan `database.sql` sama-sama memakai `db_kopipiro`.
- Logout kini lewat POST + token CSRF; semua form POST dilindungi token CSRF.

## Catatan

- File Excel dibuat sebagai tabel HTML berekstensi `.xls` (sama seperti versi lama). Excel mungkin menampilkan
  peringatan "format dan ekstensi tidak cocok" — pilih **Yes**. Untuk `.xlsx` murni, gunakan PhpSpreadsheet.
- Grafik memakai Chart.js dan Bootstrap dari CDN, jadi butuh koneksi internet saat dibuka.
- Untuk produksi: buat user MySQL khusus (jangan `root` tanpa password), aktifkan HTTPS, dan matikan `display_errors`.
