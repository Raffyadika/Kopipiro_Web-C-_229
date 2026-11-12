<?php
// owner/products.php — daftar produk: tambah/ubah/hapus, aktif/nonaktif
include __DIR__ . '/../init.php';
require_role('owner');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        db_run($conn, "UPDATE products SET is_available = 1 - is_available WHERE id = ?", 'i', [$id]);
        flash('success', 'Status ketersediaan produk diperbarui.');
    } elseif ($action === 'delete') {
        $p = db_one($conn, "SELECT image FROM products WHERE id = ?", 'i', [$id]);
        if ($p) {
            db_run($conn, "DELETE FROM products WHERE id = ?", 'i', [$id]); // riwayat pesanan tetap aman (nama & harga tersalin)
            delete_product_image($p['image']);
            flash('success', 'Produk dihapus.');
        }
    }
    redirect('owner/products.php');
}

$products = db_all(
    $conn,
    "SELECT p.id, p.name, p.price, p.image, p.is_available, c.name AS category_name,
            COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count
     FROM products p JOIN categories c ON c.id = p.category_id
     LEFT JOIN reviews r ON r.product_id = p.id
     GROUP BY p.id, c.name ORDER BY c.id, p.name"
);

$pageTitle = 'Kelola Produk';
include __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Kelola Produk</h1>
    <a href="<?= e(url('owner/product_form.php')) ?>" class="btn btn-success">+ Tambah Produk</a>
</div>

<div class="card kp-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width:72px">Gambar</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th class="text-end">Harga</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$products): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada produk.</td></tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= product_img($p, 'kp-thumb') ?></td>
                        <td class="fw-semibold"><?= e($p['name']) ?></td>
                        <td><?= e($p['category_name']) ?></td>
                        <td class="text-end"><?= rupiah($p['price']) ?></td>
                        <td>
                            <?php if ($p['review_count'] > 0): ?>
                                <?= stars((float) $p['avg_rating']) ?> <small class="text-muted"><?= number_format((float) $p['avg_rating'], 1) ?> (<?= (int) $p['review_count'] ?>)</small>
                            <?php else: ?><small class="text-muted">-</small><?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $p['is_available'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                                    title="Klik untuk mengubah"><?= $p['is_available'] ? 'Tersedia' : 'Disembunyikan' ?></button>
                            </form>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('owner/product_form.php?id=' . (int) $p['id'])) ?>" class="btn btn-warning btn-sm">Edit</a>
                            <form method="POST" action="" class="d-inline"
                                onsubmit="return confirm('Hapus produk ini? Riwayat pesanan tetap tersimpan.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
