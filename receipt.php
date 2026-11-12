<?php
// receipt.php — struk / detail pesanan untuk pemesan (tamu lewat session, customer lewat akun)
include 'init.php';

$id = (int) ($_GET['id'] ?? 0);
$order = db_one(
    $conn,
    "SELECT o.*, c.name AS cashier_name FROM orders o LEFT JOIN users c ON c.id = o.cashier_id WHERE o.id = ?",
    'i',
    [$id]
);

// Siapa yang boleh melihat: owner, kasir pengelola, customer pemilik, atau tamu yang memesan di sesi ini
$allowed = false;
if ($order) {
    if (is_owner()) {
        $allowed = true;
    } elseif (is_cashier()) {
        $allowed = (int) $order['cashier_id'] === current_user_id();
    } elseif (is_customer()) {
        $allowed = (int) $order['user_id'] === current_user_id();
    }
    if (!$allowed && in_array($id, $_SESSION['my_orders'] ?? [], true)) {
        $allowed = true;
    }
}
if (!$order || !$allowed) {
    http_response_code(404);
    die("Pesanan tidak ditemukan.");
}

$items = fetch_order_items($conn, $id);
$pageTitle = 'Struk ' . $order['order_code'];
include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card kp-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted">Kode pesanan</div>
                    <h1 class="h4 mb-0"><?= e($order['order_code']) ?></h1>
                </div>
                <?= status_badge($order['status']) ?>
            </div>
            <div class="card-body">
                <div class="row small mb-3">
                    <div class="col-sm-4"><span class="text-muted">Pemesan</span><br><?= e($order['customer_name']) ?>
                        <?= $order['user_id'] ? '<span class="badge bg-success">member</span>' : '<span class="badge bg-light text-dark border">tamu</span>' ?></div>
                    <div class="col-sm-4"><span class="text-muted">Waktu</span><br><?= e(date('d M Y, H:i', strtotime($order['created_at']))) ?></div>
                    <div class="col-sm-4"><span class="text-muted">Kasir</span><br><?= e($order['cashier_name'] ?? '-') ?></div>
                </div>
                <?php if ($order['notes']): ?>
                    <div class="alert alert-light border small">Catatan: <?= e($order['notes']) ?></div>
                <?php endif; ?>
                <?= order_items_table($order, $items) ?>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2 justify-content-between">
                <a href="<?= e(url(is_customer() ? 'my_orders.php' : (is_logged_in() ? home_for_role() : 'index.php'))) ?>" class="btn btn-outline-kp">
                    <?= is_customer() ? '← Pesanan Saya' : '← Kembali' ?>
                </a>
                <button class="btn btn-outline-secondary" onclick="window.print()">🖨 Cetak</button>
            </div>
        </div>
        <?php if (!is_logged_in()): ?>
            <p class="small text-muted mt-3">Simpan kode pesanan Anda. Struk ini hanya bisa dibuka dari browser ini selama sesi berlangsung.</p>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>
