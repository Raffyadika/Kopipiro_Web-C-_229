<?php
// cashier/order_detail.php — kasir memproses pesanannya sendiri (menunggu → diproses → selesai / batal)
include __DIR__ . '/../init.php';
require_role('cashier');

$id = (int) ($_GET['id'] ?? 0);
$uid = current_user_id();

// Kepemilikan dicek di query: pesanan kasir lain sama saja dengan "tidak ada"
$order = db_one($conn, "SELECT * FROM orders WHERE id = ? AND cashier_id = ?", 'ii', [$id, $uid]);
if (!$order) {
    http_response_code(404);
    die("Pesanan tidak ditemukan atau bukan pesanan yang Anda kelola.");
}

if (isset($_POST['set_status'])) {
    csrf_verify();
    $new = $_POST['new_status'] ?? '';
    if (in_array($new, order_transitions($order['status']), true)) {
        db_run($conn, "UPDATE orders SET status = ? WHERE id = ? AND cashier_id = ?", 'sii', [$new, $id, $uid]);
        flash('success', 'Status diubah menjadi "' . status_label($new) . '".');
    } else {
        flash('danger', 'Perubahan status tidak diperbolehkan.');
    }
    redirect('cashier/order_detail.php?id=' . $id);
}

$items = fetch_order_items($conn, $id);
$next = order_transitions($order['status']);
$labels = ['processing' => ['Proses pesanan', 'btn-info'], 'completed' => ['Tandai selesai', 'btn-success'], 'cancelled' => ['Batalkan', 'btn-outline-danger']];

$pageTitle = 'Pesanan ' . $order['order_code'];
include __DIR__ . '/../header.php';
?>
<a href="<?= e(url('cashier/orders.php')) ?>" class="btn btn-link px-0 mb-2">← Pesanan saya</a>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card kp-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h1 class="h5 mb-0"><?= e($order['order_code']) ?></h1>
                <?= status_badge($order['status']) ?>
            </div>
            <div class="card-body">
                <div class="row small mb-3">
                    <div class="col-sm-6"><span class="text-muted">Pemesan</span><br><?= e($order['customer_name']) ?>
                        <?= $order['user_id'] ? '<span class="badge bg-success">member</span>' : '<span class="badge bg-light text-dark border">tamu</span>' ?></div>
                    <div class="col-sm-6"><span class="text-muted">Waktu</span><br><?= e(date('d M Y, H:i', strtotime($order['created_at']))) ?></div>
                </div>
                <?php if ($order['notes']): ?><div class="alert alert-warning small">📝 Catatan: <?= e($order['notes']) ?></div><?php endif; ?>
                <?= order_items_table($order, $items) ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card kp-card">
            <div class="card-header bg-white fw-semibold">Tindakan</div>
            <div class="card-body">
                <?php if (!$next): ?>
                    <p class="text-muted mb-0">Pesanan ini sudah <?= e(strtolower(status_label($order['status']))) ?> dan tidak dapat diubah lagi.</p>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($next as $s): ?>
                            <form method="POST" action="" class="m-0"
                                <?= $s === 'cancelled' ? 'onsubmit="return confirm(\'Batalkan pesanan ini?\')"' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="new_status" value="<?= e($s) ?>">
                                <button type="submit" name="set_status" class="btn w-100 <?= e($labels[$s][1]) ?>"><?= e($labels[$s][0]) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
