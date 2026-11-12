<?php
// owner/product_form.php — tambah (tanpa ?id) atau ubah (?id=) produk, termasuk unggah gambar
include __DIR__ . '/../init.php';
require_role('owner');

$id = (int) ($_GET['id'] ?? 0);
$product = null;
if ($id) {
    $product = db_one($conn, "SELECT * FROM products WHERE id = ?", 'i', [$id]);
    if (!$product) {
        http_response_code(404);
        die("Produk tidak ditemukan.");
    }
}

$categories = db_all($conn, "SELECT id, name FROM categories ORDER BY name");

$errors = [];
$form = [
    'name' => $product['name'] ?? '',
    'category_id' => (int) ($product['category_id'] ?? 0),
    'description' => $product['description'] ?? '',
    'price' => $product['price'] ?? '',
    'is_available' => (int) ($product['is_available'] ?? 1),
];

if (isset($_POST['save'])) {
    csrf_verify();
    $form['name'] = trim($_POST['name'] ?? '');
    $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $form['description'] = trim($_POST['description'] ?? '');
    $form['price'] = (int) ($_POST['price'] ?? 0);
    $form['is_available'] = isset($_POST['is_available']) ? 1 : 0;

    if (mb_strlen($form['name']) < 2 || mb_strlen($form['name']) > 100) {
        $errors[] = 'Nama produk harus 2–100 karakter.';
    }
    if (!in_array($form['category_id'], array_map(fn($c) => (int) $c['id'], $categories), true)) {
        $errors[] = 'Pilih kategori yang valid.';
    }
    if ($form['price'] < 500 || $form['price'] > 10000000) {
        $errors[] = 'Harga harus antara Rp 500 dan Rp 10.000.000.';
    }
    if (mb_strlen($form['description']) > 1000) {
        $errors[] = 'Deskripsi maksimal 1000 karakter.';
    }

    // Gambar (opsional). Saat edit, gambar baru menggantikan yang lama.
    $newImage = null;
    if (!$errors && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $imgErr = null;
        $newImage = save_product_image($_FILES['image'], $imgErr);
        if ($newImage === null) {
            $errors[] = $imgErr;
        }
    }

    if (!$errors) {
        if ($product) {
            if ($newImage !== null) {
                db_run($conn, "UPDATE products SET category_id=?, name=?, description=?, price=?, is_available=?, image=? WHERE id=?",
                    'issiisi', [$form['category_id'], $form['name'], $form['description'], $form['price'], $form['is_available'], $newImage, $id]);
                delete_product_image($product['image']);
            } else {
                db_run($conn, "UPDATE products SET category_id=?, name=?, description=?, price=?, is_available=? WHERE id=?",
                    'issiii', [$form['category_id'], $form['name'], $form['description'], $form['price'], $form['is_available'], $id]);
            }
            flash('success', 'Produk diperbarui.');
        } else {
            db_run($conn, "INSERT INTO products (category_id, name, description, price, is_available, image) VALUES (?, ?, ?, ?, ?, ?)",
                'issiis', [$form['category_id'], $form['name'], $form['description'], $form['price'], $form['is_available'], $newImage]);
            flash('success', 'Produk ditambahkan.');
        }
        redirect('owner/products.php');
    }
}

$pageTitle = $product ? 'Edit Produk' : 'Tambah Produk';
include __DIR__ . '/../header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card kp-card">
            <div class="card-header <?= $product ? 'bg-warning text-dark' : 'bg-success text-white' ?>">
                <h5 class="mb-0"><?= e($pageTitle) ?></h5>
            </div>
            <div class="card-body">
                <?php foreach ($errors as $er): ?>
                    <div class="alert alert-danger py-2"><?= e($er) ?></div>
                <?php endforeach; ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama produk</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($form['name']) ?>" required maxlength="100">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Kategori</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="" disabled <?= $form['category_id'] ? '' : 'selected' ?>>Pilih kategori…</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= $form['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Harga normal (Rp)</label>
                            <input type="number" class="form-control" id="price" name="price" value="<?= e($form['price']) ?>" min="500" max="10000000" step="500" required>
                            <div class="form-text">Diskon <?= DISCOUNT_PERCENT ?>% untuk member dihitung otomatis.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3" maxlength="1000"><?= e($form['description']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Gambar <?= $product ? '(kosongkan jika tidak diganti)' : '' ?></label>
                        <?php if ($product && $product['image']): ?>
                            <div class="mb-2" style="max-width:160px"><?= product_img($product, 'rounded') ?></div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG, atau WebP, maksimal 2 MB.</div>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_available" name="is_available" <?= $form['is_available'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_available">Tersedia (tampil di menu)</label>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?= e(url('owner/products.php')) ?>" class="btn btn-secondary">Batal</a>
                        <button type="submit" name="save" class="btn btn-success">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
