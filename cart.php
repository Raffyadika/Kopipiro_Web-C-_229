<?php
// cart.php — keranjang belanja (tamu & customer). Disimpan di session, harga dihitung ulang dari database.
include 'init.php';

if (!can_order()) {
    flash('info', 'Akun staf tidak dapat memesan. Gunakan akun customer atau pesan sebagai tamu.');
    redirect(home_for_role());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, min(20, (int) ($_POST['qty'] ?? 1)));
        $p = db_one($conn, "SELECT id, name FROM products WHERE id = ? AND is_available = 1", 'i', [$pid]);
        if (!$p) {
            flash('danger', 'Produk tidak tersedia.');
            safe_back('index.php');
        }
        $_SESSION['cart'][$pid] = min(20, ($_SESSION['cart'][$pid] ?? 0) + $qty);
        flash('success', $p['name'] . ' ditambahkan ke keranjang.');
        safe_back('index.php'); // kembali ke halaman asal (menu / detail produk)
    }

    if ($action === 'update') {
        foreach ((array) ($_POST['qty'] ?? []) as $pid => $q) {
            $pid = (int) $pid;
            $q = (int) $q;
            if (!isset($_SESSION['cart'][$pid])) {
                continue;
            }
            if ($q <= 0) {
                unset($_SESSION['cart'][$pid]);
            } else {
                $_SESSION['cart'][$pid] = min(20, $q);
            }
        }
        flash('success', 'Keranjang diperbarui.');
    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][(int) ($_POST['product_id'] ?? 0)]);
    } elseif ($action === 'clear') {
        unset($_SESSION['cart']);
    }
    redirect('cart.php');
}

$cart = cart_summary($conn);
$pct = $cart['discount_percent'];

$pageTitle = 'Keranjang';
include 'header.php';
?>
<h1 class="h4 mb-3">🛒 Keranjang</h1>

<?php if (!$cart['lines']): ?>
    <div class="alert alert-secondary">Keranjang masih kosong. <a href="<?= e(url('index.php')) ?>">Lihat menu</a></div>
<?php else: ?>
    <?php if (!is_logged_in()): ?>
        <div class="alert alert-warning">
            Anda memesan sebagai tamu (harga normal).
            <a href="<?= e(url('login.php?next=cart.php')) ?>">Login</a> atau
            <a href="<?= e(url('register.php?next=cart.php')) ?>">daftar</a> untuk diskon <?= DISCOUNT_PERCENT ?>% — keranjang Anda tetap tersimpan.
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <div class="card kp-card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produk</th>
                            <th class="text-end">Harga</th>
                            <th style="width: 110px;">Jumlah</th>
                            <th class="text-end">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart['lines'] as $l): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:56px;flex:none"><?= product_img(['image' => $l['image'], 'name' => $l['name']], 'kp-thumb') ?></div>
                                        <a href="<?= e(url('product.php?id=' . $l['id'])) ?>" class="text-decoration-none"><?= e($l['name']) ?></a>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <?php if ($pct > 0): ?><span class="kp-price-old"><?= rupiah($l['price']) ?></span><br><?php endif; ?>
                                    <?= rupiah($l['unit_price']) ?>
                                </td>
                                <td><input type="number" name="qty[<?= $l['id'] ?>]" value="<?= $l['qty'] ?>" min="0" max="20" class="form-control form-control-sm"></td>
                                <td class="text-end"><?= rupiah($l['line_total']) ?></td>
                                <td class="text-end">
                                    <button type="submit" form="rm-<?= $l['id'] ?>" class="btn btn-sm btn-outline-danger" aria-label="Hapus <?= e($l['name']) ?>">✕</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php if ($pct > 0): ?>
                            <tr class="text-muted">
                                <td colspan="3" class="text-end">Subtotal (harga normal)</td>
                                <td class="text-end"><?= rupiah($cart['subtotal']) ?></td>
                                <td></td>
                            </tr>
                            <tr class="text-success">
                                <td colspan="3" class="text-end">Diskon member <?= $pct ?>%</td>
                                <td class="text-end">- <?= rupiah($cart['discount_amount']) ?></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="fw-bold fs-5">
                            <td colspan="3" class="text-end">Total</td>
                            <td class="text-end"><?= rupiah($cart['total']) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-between">
            <div class="d-flex gap-2">
                <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-kp">← Tambah menu</a>
                <button type="submit" class="btn btn-outline-secondary">Perbarui jumlah</button>
                <button type="submit" form="clear-cart" class="btn btn-outline-danger">Kosongkan</button>
            </div>
            <a href="<?= e(url('checkout.php')) ?>" class="btn btn-kp btn-lg">Lanjut ke Checkout →</a>
        </div>
    </form>

    <?php // Form terpisah untuk tombol hapus (form HTML tidak boleh bersarang) ?>
    <?php foreach ($cart['lines'] as $l): ?>
        <form id="rm-<?= $l['id'] ?>" method="POST" action="">
            <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= $l['id'] ?>">
        </form>
    <?php endforeach; ?>
    <form id="clear-cart" method="POST" action="" onsubmit="return confirm('Kosongkan semua isi keranjang?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="clear">
    </form>
<?php endif; ?>

<?php include 'footer.php'; ?>
