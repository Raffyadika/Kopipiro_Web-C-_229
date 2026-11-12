<?php
// checkout.php — daftar produk + harga (sudah termasuk diskon bila login), lalu buat pesanan
include 'init.php';

if (!can_order()) {
    flash('info', 'Akun staf tidak dapat memesan.');
    redirect(home_for_role());
}

$cart = cart_summary($conn);
if (!$cart['lines']) {
    flash('warning', 'Keranjang Anda kosong.');
    redirect('cart.php');
}
$pct = $cart['discount_percent'];

$errors = [];
$customerName = is_customer() ? ($_SESSION['name'] ?? '') : '';
$notes = '';

if (isset($_POST['place_order'])) {
    csrf_verify();
    $customerName = trim($_POST['customer_name'] ?? '');
    $notes = mb_substr(trim($_POST['notes'] ?? ''), 0, 255);

    if (mb_strlen($customerName) < 2 || mb_strlen($customerName) > 100) {
        $errors[] = 'Nama pemesan harus 2–100 karakter.';
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            $cashierId = pick_cashier($conn); // pesanan otomatis dikelola kasir dengan antrean paling sedikit
            $userId = is_customer() ? current_user_id() : null;

            $orderId = 0;
            for ($try = 0; $try < 5 && !$orderId; $try++) {
                $code = 'KP' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
                try {
                    db_run(
                        $conn,
                        "INSERT INTO orders (order_code, user_id, customer_name, notes, cashier_id, subtotal, discount_percent, discount_amount, total)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        'sissiiiii',
                        [$code, $userId, $customerName, $notes !== '' ? $notes : null, $cashierId,
                         $cart['subtotal'], $pct, $cart['discount_amount'], $cart['total']]
                    );
                    $orderId = (int) $conn->insert_id;
                } catch (mysqli_sql_exception $ex) {
                    if ($ex->getCode() !== 1062) { // 1062 = kode pesanan kebetulan kembar, coba lagi
                        throw $ex;
                    }
                }
            }
            if (!$orderId) {
                throw new RuntimeException('Gagal membuat kode pesanan.');
            }

            foreach ($cart['lines'] as $l) {
                db_run(
                    $conn,
                    "INSERT INTO order_items (order_id, product_id, product_name, price, qty) VALUES (?, ?, ?, ?, ?)",
                    'iisii',
                    [$orderId, $l['id'], $l['name'], $l['price'], $l['qty']]
                );
            }
            $conn->commit();
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log('Checkout gagal: ' . $ex->getMessage());
            $errors[] = 'Maaf, pesanan gagal diproses. Silakan coba lagi.';
        }

        if (!$errors) {
            unset($_SESSION['cart']);
            $_SESSION['my_orders'][] = $orderId; // izinkan tamu membuka struk pesanannya
            flash('success', 'Pesanan berhasil dibuat! Kasir kami segera memprosesnya.');
            redirect('receipt.php?id=' . $orderId);
        }
    }
}

$pageTitle = 'Checkout';
include 'header.php';
?>
<h1 class="h4 mb-3">Checkout</h1>

<?php if (!is_logged_in()): ?>
    <div class="alert alert-warning">
        Anda memesan sebagai <strong>tamu</strong> — harga normal.
        <a href="<?= e(url('login.php?next=checkout.php')) ?>">Login</a> atau
        <a href="<?= e(url('register.php?next=checkout.php')) ?>">daftar</a> dulu untuk diskon <?= DISCOUNT_PERCENT ?>% (keranjang tidak hilang).
    </div>
<?php else: ?>
    <div class="alert alert-success">Diskon member <strong><?= $pct ?>%</strong> sudah diterapkan.</div>
<?php endif; ?>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-danger"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card kp-card">
            <div class="card-header bg-white fw-semibold">Ringkasan pesanan</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produk</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart['lines'] as $l): ?>
                            <tr>
                                <td><?= e($l['name']) ?></td>
                                <td class="text-end">
                                    <?php if ($pct > 0): ?><span class="kp-price-old"><?= rupiah($l['price']) ?></span><br><?php endif; ?>
                                    <?= rupiah($l['unit_price']) ?>
                                </td>
                                <td class="text-center"><?= $l['qty'] ?></td>
                                <td class="text-end"><?= rupiah($l['line_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php if ($pct > 0): ?>
                            <tr class="text-muted">
                                <td colspan="3" class="text-end">Subtotal (harga normal)</td>
                                <td class="text-end"><?= rupiah($cart['subtotal']) ?></td>
                            </tr>
                            <tr class="text-success">
                                <td colspan="3" class="text-end">Diskon member <?= $pct ?>%</td>
                                <td class="text-end">- <?= rupiah($cart['discount_amount']) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="fw-bold fs-5">
                            <td colspan="3" class="text-end">Total bayar</td>
                            <td class="text-end"><?= rupiah($cart['total']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <a href="<?= e(url('cart.php')) ?>" class="btn btn-link px-0 mt-2">← Ubah keranjang</a>
    </div>

    <div class="col-lg-5">
        <div class="card kp-card">
            <div class="card-header bg-white fw-semibold">Data pemesan</div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Nama pemesan</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?= e($customerName) ?>" required maxlength="100">
                        <div class="form-text">Nama dipanggil saat pesanan siap.</div>
                    </div>
                    <div class="mb-4">
                        <label for="notes" class="form-label">Catatan (opsional)</label>
                        <input type="text" class="form-control" id="notes" name="notes" value="<?= e($notes) ?>" maxlength="255" placeholder="mis. less sugar, tanpa es">
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="place_order" class="btn btn-kp btn-lg">Buat Pesanan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
