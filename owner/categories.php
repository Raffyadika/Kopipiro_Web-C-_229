<?php
// owner/categories.php — kelola kategori produk (Kopi, Non-Kopi, Toast, dst.)
include __DIR__ . '/../init.php';
require_role('owner');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            flash('danger', 'Nama kategori harus 2–50 karakter.');
        } else {
            try {
                db_run($conn, "INSERT INTO categories (name) VALUES (?)", 's', [$name]);
                flash('success', 'Kategori ditambahkan.');
            } catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() !== 1062) {
                    throw $ex;
                }
                flash('danger', 'Nama kategori sudah ada.');
            }
        }
    } elseif ($action === 'delete') {
        try {
            db_run($conn, "DELETE FROM categories WHERE id = ?", 'i', [(int) ($_POST['id'] ?? 0)]);
            flash('success', 'Kategori dihapus.');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== 1451) { // 1451 = masih dipakai produk (foreign key)
                throw $ex;
            }
            flash('danger', 'Kategori tidak bisa dihapus karena masih dipakai produk.');
        }
    }
    redirect('owner/categories.php');
}

$categories = db_all($conn, "SELECT c.id, c.name, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.id");

$pageTitle = 'Kategori';
include __DIR__ . '/../header.php';
?>
<div class="row g-4">
    <div class="col-md-4">
        <div class="card kp-card">
            <div class="card-header bg-success text-white"><h5 class="mb-0">Tambah Kategori</h5></div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama kategori</label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="50" required placeholder="mis. Pastry">
                    </div>
                    <div class="d-grid"><button class="btn btn-success" type="submit">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card kp-card">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">Daftar Kategori</h5></div>
            <table class="table table-striped align-middle mb-0">
                <thead class="table-dark"><tr><th>No</th><th>Nama</th><th class="text-center">Jumlah produk</th><th></th></tr></thead>
                <tbody>
                    <?php $no = 1; foreach ($categories as $c): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($c['name']) ?></td>
                            <td class="text-center"><?= (int) $c['product_count'] ?></td>
                            <td class="text-end">
                                <form method="POST" action="" class="m-0" onsubmit="return confirm('Hapus kategori ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" <?= $c['product_count'] > 0 ? 'disabled title="Masih dipakai produk"' : '' ?>>Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
