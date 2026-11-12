<?php
// my_orders.php — riwayat pesanan customer yang login
include 'init.php';
require_role('customer');

$orders = db_all(
    $conn,
    "SELECT o.id, o.order_code, o.total, o.discount_amount, o.status, o.created_at,
            (SELECT COALESCE(SUM(qty), 0) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC",
    'i',
    [current_user_id()]
);

$pageTitle = 'Pesanan Saya';
include 'header.php';
?>
<h1 class="h4 mb-3">Pesanan Saya</h1>

<?php if (!$orders): ?>
    <div class="alert alert-secondary">Belum ada pesanan. <a href="<?= e(url('index.php')) ?>">Pesan sekarang</a></div>
<?php else: ?>
    <div class="card kp-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Waktu</th>
                        <th class="text-center">Item</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Hemat</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($o['order_code']) ?></td>
                            <td><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></td>
                            <td class="text-center"><?= (int) $o['item_count'] ?></td>
                            <td class="text-end"><?= rupiah($o['total']) ?></td>
                            <td class="text-end text-success"><?= rupiah($o['discount_amount']) ?></td>
                            <td><?= status_badge($o['status']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-kp" href="<?= e(url('receipt.php?id=' . (int) $o['id'])) ?>">Lihat</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php include 'footer.php'; ?>
