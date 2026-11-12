<?php
// index.php — menu publik: jelajahi produk (kopi / non-kopi / toast), cari, filter kategori
include 'init.php';

$catId = (int) ($_GET['cat'] ?? 0);
$q = trim($_GET['q'] ?? '');
$pct = member_discount_percent();

$categories = db_all($conn, "SELECT id, name FROM categories ORDER BY id");

$where = ['p.is_available = 1'];
$types = '';
$params = [];
if ($catId > 0) {
    $where[] = 'p.category_id = ?';
    $types .= 'i';
    $params[] = $catId;
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

$products = db_all(
    $conn,
    "SELECT p.id, p.name, p.description, p.price, p.image, c.name AS category_name,
            COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count
     FROM products p
     JOIN categories c ON c.id = p.category_id
     LEFT JOIN reviews r ON r.product_id = p.id
     WHERE " . implode(' AND ', $where) . "
     GROUP BY p.id, c.name
     ORDER BY c.id, p.name",
    $types,
    $params
);

$pageTitle = 'Menu';
include 'header.php';
?>

<div class="kp-hero mb-4">
    <h1 class="h2 fw-bold mb-1">Ngopi dulu, yuk ☕</h1>
    <?php if (is_customer()): ?>
        <p class="lead mb-0">Halo, <?= e($_SESSION['name']) ?>! Diskon member <strong><?= DISCOUNT_PERCENT ?>%</strong> sudah aktif untuk semua menu.</p>
    <?php elseif (is_logged_in()): ?>
        <p class="lead mb-0">Anda masuk sebagai <strong><?= e(current_role()) ?></strong>. Akun staf tidak dapat memesan.</p>
    <?php else: ?>
        <p class="lead mb-2">Pesan langsung tanpa login (harga normal), atau daftar untuk <strong>diskon <?= DISCOUNT_PERCENT ?>%</strong>.</p>
        <a href="<?= e(url('register.php')) ?>" class="btn btn-warning btn-sm">Daftar &amp; hemat <?= DISCOUNT_PERCENT ?>%</a>
        <a href="<?= e(url('login.php')) ?>" class="btn btn-outline-light btn-sm">Login</a>
    <?php endif; ?>
</div>

<form method="GET" class="row g-2 mb-4 align-items-center">
    <div class="col-12 col-md">
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('index.php' . ($q !== '' ? '?q=' . urlencode($q) : ''))) ?>"
                class="btn btn-sm btn-outline-kp <?= $catId === 0 ? 'active' : '' ?>">Semua</a>
            <?php foreach ($categories as $c): ?>
                <a href="<?= e(url('index.php?cat=' . (int) $c['id'] . ($q !== '' ? '&q=' . urlencode($q) : ''))) ?>"
                    class="btn btn-sm btn-outline-kp <?= $catId === (int) $c['id'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="input-group input-group-sm">
            <?php if ($catId > 0): ?><input type="hidden" name="cat" value="<?= $catId ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Cari menu…">
            <button class="btn btn-kp" type="submit">Cari</button>
        </div>
    </div>
</form>

<?php if (!$products): ?>
    <div class="alert alert-secondary">Menu tidak ditemukan. <a href="<?= e(url('index.php')) ?>">Lihat semua menu</a></div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($products as $p):
        $price = (int) $p['price'];
        $disc = price_after_discount($price, $pct); ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card kp-card h-100">
                <a href="<?= e(url('product.php?id=' . (int) $p['id'])) ?>"><?= product_img($p) ?></a>
                <div class="card-body d-flex flex-column">
                    <span class="badge bg-light text-dark border align-self-start mb-1"><?= e($p['category_name']) ?></span>
                    <h2 class="h6 mb-1">
                        <a href="<?= e(url('product.php?id=' . (int) $p['id'])) ?>" class="text-decoration-none text-dark"><?= e($p['name']) ?></a>
                    </h2>
                    <div class="small mb-2">
                        <?php if ($p['review_count'] > 0): ?>
                            <?= stars((float) $p['avg_rating']) ?>
                            <span class="text-muted"><?= number_format((float) $p['avg_rating'], 1) ?> (<?= (int) $p['review_count'] ?>)</span>
                        <?php else: ?>
                            <span class="text-muted">Belum ada rating</span>
                        <?php endif; ?>
                    </div>
                    <p class="small text-muted flex-grow-1"><?= e(mb_strimwidth((string) $p['description'], 0, 80, '…')) ?></p>
                    <div class="mb-2">
                        <?php if ($pct > 0): ?>
                            <span class="kp-price-old"><?= rupiah($price) ?></span>
                            <span class="badge bg-success">-<?= $pct ?>%</span><br>
                            <span class="kp-price"><?= rupiah($disc) ?></span>
                        <?php else: ?>
                            <span class="kp-price"><?= rupiah($price) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (can_order()): ?>
                        <form method="POST" action="<?= e(url('cart.php')) ?>" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <input type="number" name="qty" value="1" min="1" max="20" class="form-control form-control-sm kp-qty" aria-label="Jumlah">
                            <button class="btn btn-kp btn-sm flex-grow-1" type="submit">+ Keranjang</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'footer.php'; ?>
