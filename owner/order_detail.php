<?php
// owner/order_detail.php — detail satu pesanan; owner bisa mengubah status dan kasir pengelola
include __DIR__ . '/../init.php';
require_role('owner');

$id = (int) ($_GET['id'] ?? 0);
$order = db_one(
    $conn,
    "SELECT o.*, c.name AS cashier_name FROM orders o LEFT JOIN users c ON c.id = o.cashier_id WHERE o.id = ?",
    'i',
    [$id]
);
if (!$order) {
    http_response_code(404);
    die("Pesanan tidak ditemukan.");
}

$cashiers = db_all($conn, "SELECT id, name FROM users WHERE role = 'cashier' ORDER BY name");

if (isset($_POST['update'])) {
    csrf_verify();
    $status = $_POST['status'] ?? '';
    $cashierId = ($_POST['cashier_id'] ?? '') === '' ? null : (int) $_POST['cashier_id'];

    $validCashier = $cashierId === null || in_array($cashierId, array_map(fn($c) => (int) $c['id'], $cashiers), true);
    if (!in_array($status, ['pending', 'processing', 'completed', 'cancelled'], true) || !$validCashier) {
        flash('danger', 'Data tidak valid.');
    } else {
        db_run($conn, "UPDATE orders SET status = ?, cashier_id = ? WHERE id = ?", 'sii', [$status, $cashierId, $id]);
        flash('success', 'Pesanan diperbarui.');
    }
    redirect('owner/order_detail.php?id=' . $id);
}

$items = fetch_order_items($conn, $id);
$pageTitle = 'Pesanan ' . $order['order_code'];
include __DIR__ . '/../header.php';
?>
<a href="<?= e(url('owner/orders.php')) ?>" class="btn btn-link px-0 mb-2">← Semua pesanan</a>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card kp-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h1 class="h5 mb-0"><?= e($order['order_code']) ?></h1>
                <?= status_badge($order['status']) ?>
            </div>
            <div class="card-body">
                <div class="row small mb-3">
                    <div class="col-sm-4"><span class="text-muted">Pemesan</span><br><?= e($order['customer_name']) ?>
                        <?= $order['user_id'] ? '<span class="badge bg-success">member</span>' : '<span class="badge bg-light text-dark border">tamu</span>' ?></div>
                    <div class="col-sm-4"><span class="text-muted">Waktu</span><br><?= e(date('d M Y, H:i', strtotime($order['created_at']))) ?></div>
                    <div class="col-sm-4"><span class="text-muted">Kasir</span><br><?= e($order['cashier_name'] ?? 'Belum ditentukan') ?></div>
                </div>
                <?php if ($order['notes']): ?><div class="alert alert-light border small">Catatan: <?= e($order['notes']) ?></div><?php endif; ?>
                <?= order_items_table($order, $items) ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card kp-card">
            <div class="card-header bg-warning text-dark fw-semibold">Kelola pesanan</div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <?php foreach (['pending', 'processing', 'completed', 'cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="cashier_id" class="form-label">Kasir pengelola</label>
                        <select name="cashier_id" id="cashier_id" class="form-select">
                            <option value="">— Belum ditentukan —</option>
                            <?php foreach ($cashiers as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) $order['cashier_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pesanan hanya terlihat oleh kasir yang dipilih.</div>
                    </div>
                    <div class="d-grid"><button type="submit" name="update" class="btn btn-warning">Simpan perubahan</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
