<?php
// product.php — detail produk: deskripsi, harga, rating & ulasan
include 'init.php';

$id = (int) ($_GET['id'] ?? 0);
$product = db_one(
    $conn,
    "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.is_available = 1",
    'i',
    [$id]
);
if (!$product) {
    http_response_code(404);
    die("Produk tidak ditemukan atau sedang tidak tersedia.");
}

// Hanya customer yang pernah memesan produk ini (dan tidak dibatalkan) yang boleh memberi rating
$canReview = false;
if (is_customer()) {
    $canReview = (bool) db_one(
        $conn,
        "SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = ? AND oi.product_id = ? AND o.status <> 'cancelled' LIMIT 1",
        'ii',
        [current_user_id(), $id]
    );
}

if (($_POST['action'] ?? '') === 'review') {
    csrf_verify();
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = mb_substr(trim($_POST['comment'] ?? ''), 0, 500);
    if (!$canReview) {
        flash('danger', 'Anda hanya bisa memberi rating untuk produk yang pernah Anda pesan.');
    } elseif ($rating < 1 || $rating > 5) {
        flash('danger', 'Pilih rating 1 sampai 5.');
    } else {
        db_run(
            $conn,
            "INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = NOW()",
            'iiis',
            [$id, current_user_id(), $rating, $comment !== '' ? $comment : null]
        );
        flash('success', 'Terima kasih, rating Anda tersimpan.');
    }
    redirect('product.php?id=' . $id);
}

$stat = db_one($conn, "SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS cnt FROM reviews WHERE product_id = ?", 'i', [$id]);
$reviews = db_all(
    $conn,
    "SELECT r.rating, r.comment, r.created_at, u.name FROM reviews r JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ? ORDER BY r.created_at DESC LIMIT 50",
    'i',
    [$id]
);
$myReview = is_customer() ? db_one($conn, "SELECT rating, comment FROM reviews WHERE product_id = ? AND user_id = ?", 'ii', [$id, current_user_id()]) : null;

$pct = member_discount_percent();
$price = (int) $product['price'];

$pageTitle = $product['name'];
include 'header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>">Menu</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('index.php?cat=' . (int) $product['category_id'])) ?>"><?= e($product['category_name']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($product['name']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-md-5"><?= product_img($product, 'kp-detail') ?></div>
    <div class="col-md-7">
        <span class="badge bg-light text-dark border"><?= e($product['category_name']) ?></span>
        <h1 class="h3 mt-2"><?= e($product['name']) ?></h1>
        <div class="mb-3">
            <?php if ($stat['cnt'] > 0): ?>
                <?= stars((float) $stat['avg_rating']) ?>
                <span class="text-muted"><?= number_format((float) $stat['avg_rating'], 1) ?> dari 5 · <?= (int) $stat['cnt'] ?> ulasan</span>
            <?php else: ?>
                <span class="text-muted">Belum ada rating</span>
            <?php endif; ?>
        </div>
        <p><?= nl2br(e($product['description'])) ?></p>

        <div class="mb-3">
            <?php if ($pct > 0): ?>
                <span class="kp-price-old"><?= rupiah($price) ?></span> <span class="badge bg-success">Diskon member <?= $pct ?>%</span><br>
                <span class="kp-price fs-3"><?= rupiah(price_after_discount($price, $pct)) ?></span>
            <?php else: ?>
                <span class="kp-price fs-3"><?= rupiah($price) ?></span>
                <?php if (!is_logged_in()): ?>
                    <div class="small text-muted"><a href="<?= e(url('register.php')) ?>">Daftar</a> / <a href="<?= e(url('login.php')) ?>">login</a> untuk harga <?= rupiah(price_after_discount($price, DISCOUNT_PERCENT)) ?> (diskon <?= DISCOUNT_PERCENT ?>%).</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (can_order()): ?>
            <form method="POST" action="<?= e(url('cart.php')) ?>" class="d-flex gap-2" style="max-width: 320px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <input type="number" name="qty" value="1" min="1" max="20" class="form-control kp-qty" aria-label="Jumlah">
                <button class="btn btn-kp flex-grow-1" type="submit">+ Tambah ke Keranjang</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<hr class="my-4">
<h2 class="h5">Rating &amp; Ulasan</h2>

<?php if (is_customer()): ?>
    <?php if ($canReview): ?>
        <form method="POST" action="" class="card kp-card p-3 mb-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" for="rating"><?= $myReview ? 'Ubah rating Anda' : 'Rating Anda' ?></label>
                    <select name="rating" id="rating" class="form-select" required>
                        <option value="" disabled <?= $myReview ? '' : 'selected' ?>>Pilih…</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>" <?= ($myReview['rating'] ?? 0) == $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="comment">Komentar (opsional)</label>
                    <input type="text" name="comment" id="comment" class="form-control" maxlength="500" value="<?= e($myReview['comment'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-grid"><button class="btn btn-kp" type="submit">Kirim</button></div>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-light border small">Pesan produk ini terlebih dahulu untuk bisa memberi rating.</div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$reviews): ?>
    <p class="text-muted">Belum ada ulasan.</p>
<?php endif; ?>
<?php foreach ($reviews as $r): ?>
    <div class="border-bottom py-2">
        <strong><?= e($r['name']) ?></strong>
        <?= stars((float) $r['rating']) ?>
        <small class="text-muted ms-1"><?= e(date('d M Y', strtotime($r['created_at']))) ?></small>
        <?php if ($r['comment']): ?><div><?= e($r['comment']) ?></div><?php endif; ?>
    </div>
<?php endforeach; ?>

<?php include 'footer.php'; ?>
