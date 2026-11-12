<?php
// owner/orders.php — semua pesanan + ringkasan + grafik penjualan + tombol unduh Excel
include __DIR__ . '/../init.php';
require_role('owner');

$f = order_filters();
$orders = fetch_orders($conn, $f);
$cashiers = db_all($conn, "SELECT id, name FROM users WHERE role = 'cashier' ORDER BY name");

// ---- Ringkasan & data grafik (pesanan dibatalkan tidak dihitung sebagai pendapatan) ----
$revenue = 0;
$discountGiven = 0;
$paidCount = 0;
$daily = [];
for ($d = new DateTime($f['from']); $d->format('Y-m-d') <= $f['to']; $d->modify('+1 day')) {
    $daily[$d->format('Y-m-d')] = 0;
}
$paidIds = [];
foreach ($orders as $o) {
    if ($o['status'] === 'cancelled') {
        continue;
    }
    $paidCount++;
    $revenue += (int) $o['total'];
    $discountGiven += (int) $o['discount_amount'];
    $day = substr($o['created_at'], 0, 10);
    $daily[$day] = ($daily[$day] ?? 0) + (int) $o['total'];
    $paidIds[] = (int) $o['id'];
}

$topProducts = [];
if ($paidIds) {
    $topProducts = db_all(
        $conn,
        "SELECT product_name, SUM(qty) AS qty FROM order_items
         WHERE order_id IN (" . implode(',', $paidIds) . ")
         GROUP BY product_name ORDER BY qty DESC, product_name LIMIT 5"
    );
}

$qs = http_build_query($f);
$shown = array_slice($orders, 0, 300);

$pageTitle = 'Pesanan & Laporan';

// Script grafik (Chart.js) — dirangkai di sini lalu dicetak oleh footer.php
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const rp = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('d M', strtotime($d)), array_keys($daily)), $jsonFlags) ?>,
            datasets: [{ label: 'Pendapatan', data: <?= json_encode(array_values($daily)) ?>, backgroundColor: '#c8803c', borderRadius: 4 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => rp(c.parsed.y) } } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => rp(v) } } }
        }
    });
    <?php if ($topProducts): ?>
    new Chart(document.getElementById('topChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($topProducts, 'product_name'), $jsonFlags) ?>,
            datasets: [{ label: 'Terjual', data: <?= json_encode(array_map('intval', array_column($topProducts, 'qty'))) ?>, backgroundColor: '#8a5230', borderRadius: 4 }]
        },
        options: {
            indexAxis: 'y', responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    <?php endif; ?>
</script>
<?php $extraScripts = ob_get_clean();

include __DIR__ . '/../header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Pesanan &amp; Laporan Penjualan</h1>
    <a href="<?= e(url('owner/export.php?' . $qs)) ?>" class="btn btn-success">⬇ Unduh Excel</a>
</div>

<form method="GET" class="card kp-card p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="from">Dari</label>
            <input type="date" class="form-control form-control-sm" id="from" name="from" value="<?= e($f['from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="to">Sampai</label>
            <input type="date" class="form-control form-control-sm" id="to" name="to" value="<?= e($f['to']) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select form-select-sm" id="status" name="status">
                <option value="all">Semua status</option>
                <?php foreach (['pending', 'processing', 'completed', 'cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="cashier">Kasir</label>
            <select class="form-select form-select-sm" id="cashier" name="cashier">
                <option value="all">Semua kasir</option>
                <option value="none" <?= $f['cashier'] === 'none' ? 'selected' : '' ?>>Belum ditentukan</option>
                <?php foreach ($cashiers as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $f['cashier'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2 d-grid"><button type="submit" class="btn btn-kp btn-sm">Terapkan</button></div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card kp-card kp-stat p-3"><div class="small text-muted">Total pesanan</div><div class="h4 mb-0"><?= count($orders) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kp-card kp-stat p-3"><div class="small text-muted">Pendapatan</div><div class="h4 mb-0"><?= rupiah($revenue) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kp-card kp-stat p-3"><div class="small text-muted">Rata-rata per pesanan</div><div class="h4 mb-0"><?= rupiah($paidCount ? round($revenue / $paidCount) : 0) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kp-card kp-stat p-3"><div class="small text-muted">Diskon diberikan</div><div class="h4 mb-0"><?= rupiah($discountGiven) ?></div></div></div>
</div>
<p class="small text-muted mt-n2">Pendapatan tidak termasuk pesanan yang dibatalkan.</p>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card kp-card p-3">
            <h2 class="h6">Penjualan harian</h2>
            <canvas id="salesChart" height="120"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card kp-card p-3 h-100">
            <h2 class="h6">5 produk terlaris</h2>
            <?php if ($topProducts): ?>
                <canvas id="topChart"></canvas>
            <?php else: ?>
                <p class="text-muted small mb-0">Belum ada data pada rentang ini.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card kp-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Kode</th><th>Waktu</th><th>Pelanggan</th><th>Kasir</th>
                    <th class="text-center">Item</th><th class="text-end">Total</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$shown): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pesanan pada filter ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($shown as $o): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($o['order_code']) ?></td>
                        <td><?= e(date('d M Y, H:i', strtotime($o['created_at']))) ?></td>
                        <td><?= e($o['customer_name']) ?>
                            <?= $o['user_id'] ? '<span class="badge bg-success">member</span>' : '<span class="badge bg-light text-dark border">tamu</span>' ?></td>
                        <td><?= $o['cashier_name'] ? e($o['cashier_name']) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-center"><?= (int) $o['item_count'] ?></td>
                        <td class="text-end"><?= rupiah($o['total']) ?></td>
                        <td><?= status_badge($o['status']) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-kp" href="<?= e(url('owner/order_detail.php?id=' . (int) $o['id'])) ?>">Detail</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($orders) > count($shown)): ?>
        <div class="card-footer small text-muted">Menampilkan 300 dari <?= count($orders) ?> pesanan. Unduh Excel untuk data lengkap.</div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
