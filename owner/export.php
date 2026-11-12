<?php
// owner/export.php — unduh laporan pesanan sebagai file Excel (.xls), mengikuti filter di halaman laporan.
// Perbaikan dari export.php lama: wajib login owner, data di-escape, dan kolom benar-benar ada.
include __DIR__ . '/../init.php';
require_role('owner');

$f = order_filters();
$orders = fetch_orders($conn, $f);

$filename = 'Laporan_Penjualan_Kopipiro_' . $f['from'] . '_sd_' . $f['to'] . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$text = 'style="mso-number-format:\'\\@\'"'; // paksa Excel membaca sebagai teks (mencegah rumus "=..." dari input)
echo "\xEF\xBB\xBF"; // BOM UTF-8 agar karakter tampil benar di Excel
?>
<table border="1">
    <thead>
        <tr><th colspan="11">Laporan Penjualan Kopipiro — <?= e($f['from']) ?> s/d <?= e($f['to']) ?></th></tr>
        <tr>
            <th>No</th><th>Kode</th><th>Tanggal</th><th>Pelanggan</th><th>Tipe</th><th>Kasir</th>
            <th>Item</th><th>Subtotal</th><th>Diskon</th><th>Total</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        $sumTotal = 0;
        $sumDiscount = 0;
        foreach ($orders as $o):
            if ($o['status'] !== 'cancelled') {
                $sumTotal += (int) $o['total'];
                $sumDiscount += (int) $o['discount_amount'];
            } ?>
            <tr>
                <td><?= $no++ ?></td>
                <td <?= $text ?>><?= e($o['order_code']) ?></td>
                <td <?= $text ?>><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td>
                <td <?= $text ?>><?= e($o['customer_name']) ?></td>
                <td><?= $o['user_id'] ? 'Member' : 'Tamu' ?></td>
                <td <?= $text ?>><?= e($o['cashier_name'] ?? '-') ?></td>
                <td <?= $text ?>><?= e($o['item_list']) ?></td>
                <td><?= (int) $o['subtotal'] ?></td>
                <td><?= (int) $o['discount_amount'] ?></td>
                <td><?= (int) $o['total'] ?></td>
                <td><?= e(status_label($o['status'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="8" align="right">Total (tanpa pesanan dibatalkan)</th>
            <th><?= $sumDiscount ?></th>
            <th><?= $sumTotal ?></th>
            <th></th>
        </tr>
    </tfoot>
</table>
