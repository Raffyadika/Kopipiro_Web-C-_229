<?php
// cashier/orders.php — kasir HANYA melihat pesanan yang dia kelola (cashier_id = dirinya sendiri)
include __DIR__ . '/../init.php';
require_role('cashier');

$uid = current_user_id();
$tab = $_GET['tab'] ?? 'active';
if (!in_array($tab, ['active', 'completed', 'cancelled', 'all'], true)) {
    $tab = 'active';
}

$where = ['o.cashier_id = ?']; // kunci kepemilikan: tidak ada cara melihat pesanan kasir lain
$types = 'i';
$params = [$uid];
if ($tab === 'active') {
    $where[] = "o.status IN ('pending', 'processing')";
} elseif ($tab !== 'all') {
    $where[] = 'o.status = ?';
    $types .= 's';
    $params[] = $tab;
}

$orders = db_all(
    $conn,
    "SELECT o.id, o.order_code, o.customer_name, o.user_id, o.total, o.status, o.created_at, o.notes,
            (SELECT GROUP_CONCAT(CONCAT(product_name, ' x', qty) ORDER BY id SEPARATOR ', ')
               FROM order_items WHERE order_id = o.id) AS item_list
     FROM orders o WHERE " . implode(' AND ', $where) . "
     ORDER BY (o.status = 'pending') DESC, o.created_at " . ($tab === 'active' ? 'ASC' : 'DESC') . " LIMIT 200",
    $types,
    $params
);

$counts = db_one(
    $conn,
    "SELECT SUM(status = 'pending') AS pending, SUM(status = 'processing') AS processing
     FROM orders WHERE cashier_id = ?",
    'i',
    [$uid]
);

$pageTitle = 'Pesanan Saya (Kasir)';
$metaRefresh = $tab === 'active' ? 30 : 0; // antrean aktif memuat ulang otomatis tiap 30 detik
include __DIR__ . '/../header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Pesanan yang Saya Kelola</h1>
    <div>
        <span class="badge bg-warning text-dark fs-6">Menunggu: <?= (int) $counts['pending'] ?></span>
        <span class="badge bg-info text-dark fs-6">Diproses: <?= (int) $counts['processing'] ?></span>
    </div>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach (['active' => 'Antrean aktif', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', 'all' => 'Semua'] as $k => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="<?= e(url('cashier/orders.php?tab=' . $k)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$orders): ?>
    <div class="alert alert-secondary">Tidak ada pesanan di tab ini.</div>
<?php else: ?>
    <div class="card kp-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr><th>Kode</th><th>Waktu</th><th>Pemesan</th><th>Pesanan</th><th class="text-end">Total</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($o['order_code']) ?></td>
                            <td><?= e(date('d M, H:i', strtotime($o['created_at']))) ?></td>
                            <td><?= e($o['customer_name']) ?>
                                <?= $o['user_id'] ? '<span class="badge bg-success">member</span>' : '' ?></td>
                            <td class="small">
                                <?= e($o['item_list']) ?>
                                <?php if ($o['notes']): ?><div class="text-danger">📝 <?= e($o['notes']) ?></div><?php endif; ?>
                            </td>
                            <td class="text-end"><?= rupiah($o['total']) ?></td>
                            <td><?= status_badge($o['status']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-kp" href="<?= e(url('cashier/order_detail.php?id=' . (int) $o['id'])) ?>">Buka</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/../footer.php'; ?>
