# Kopipiro — Web Pemesanan Kopi

Web pemesanan untuk coffee shop Kopipiro (PHP + MySQL + Bootstrap 5).

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

## Aturan bisnis

- **Tamu** (tanpa login) bisa menjelajah menu dan memesan dengan **harga normal**.
- **Customer** yang register + login otomatis mendapat **diskon 10%**.
  Keranjang tamu tidak hilang saat login/register.
- Harga selalu dihitung ulang di server dari database.
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

## Catatan

- File Excel dibuat sebagai tabel HTML berekstensi `.xls`. Excel mungkin menampilkan
  peringatan "format dan ekstensi tidak cocok" pilih **Yes**. Untuk `.xlsx`, gunakan PhpSpreadsheet.
- Grafik memakai Chart.js dan Bootstrap dari CDN, jadi butuh koneksi internet.
