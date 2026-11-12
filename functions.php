<?php
// functions.php — fungsi bantu: output, database, keranjang, pesanan.
// Dimuat oleh init.php.

/* ===================== Output & URL ===================== */

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function rupiah($n): string
{
    return 'Rp ' . number_format((int) $n, 0, ',', '.');
}
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}
function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

// Kembali ke halaman sebelumnya (hanya jika dari situs yang sama), kalau tidak ke $default
function safe_back(string $default)
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '') {
        $p = parse_url($ref);
        $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
        if (!empty($p['host']) && strcasecmp($p['host'], $host) === 0) {
            $path = '/' . ltrim($p['path'] ?? '/', '/'); // cegah "//evil.com"
            if (isset($p['query'])) {
                $path .= '?' . $p['query'];
            }
            header('Location: ' . $path);
            exit;
        }
    }
    redirect($default);
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function flash_render(): string
{
    $out = '';
    foreach (($_SESSION['flash'] ?? []) as $f) {
        $out .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
            . e($f['msg'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ===================== CSRF ===================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
// Panggil di awal setiap pemrosesan POST
function csrf_verify(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        die("Sesi form kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.");
    }
}

/* ===================== Database ===================== */

function db_run(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}
function db_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = db_run($conn, $sql, $types, $params);
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}
function db_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    return db_all($conn, $sql, $types, $params)[0] ?? null;
}

/* ===================== Diskon & harga ===================== */

// Diskon hanya untuk customer yang login; tamu membayar harga normal.
function member_discount_percent(): int
{
    return is_customer() ? DISCOUNT_PERCENT : 0;
}
function price_after_discount(int $price, int $pct): int
{
    return (int) round($price * (100 - $pct) / 100);
}

/* ===================== Tampilan produk ===================== */

function product_img(array $p, string $class = ''): string
{
    if (!empty($p['image'])) {
        return '<img src="' . e(url('uploads/products/' . rawurlencode($p['image']))) . '" alt="' . e($p['name'])
            . '" class="kp-img ' . e($class) . '" loading="lazy">';
    }
    return '<div class="kp-img kp-ph ' . e($class) . '" aria-hidden="true">☕</div>';
}
function stars(float $avg): string
{
    $full = (int) round($avg);
    return '<span class="kp-stars" title="' . number_format($avg, 1) . ' dari 5">'
        . str_repeat('★', $full) . str_repeat('☆', 5 - $full) . '</span>';
}

// Simpan gambar produk yang diunggah. Mengembalikan nama file, atau null + isi $err.
function save_product_image(array $file, ?string &$err): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $err = 'Gagal mengunggah gambar (kode ' . (int) $file['error'] . ').';
        return null;
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        $err = 'Ukuran gambar maksimal 2 MB.';
        return null;
    }
    // Periksa isi file yang sebenarnya, bukan hanya ekstensi/MIME kiriman browser
    $info = @getimagesize($file['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$info || !$ext) {
        $err = 'Format gambar harus JPG, PNG, atau WebP.';
        return null;
    }
    $dir = __DIR__ . '/uploads/products';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'p_' . bin2hex(random_bytes(8)) . '.' . $ext; // nama acak, abaikan nama asli
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $err = 'Tidak bisa menyimpan gambar. Periksa izin folder uploads/products.';
        return null;
    }
    return $name;
}
function delete_product_image(?string $name): void
{
    if ($name) {
        $path = __DIR__ . '/uploads/products/' . basename($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/* ===================== Keranjang (disimpan di session) ===================== */

function cart_all(): array
{
    return $_SESSION['cart'] ?? [];
}
function cart_count(): int
{
    return (int) array_sum(cart_all());
}

// Rangkuman keranjang. Harga SELALU diambil dari database, bukan dari browser.
function cart_summary(mysqli $conn): array
{
    $pct = member_discount_percent();
    $lines = [];
    $subtotal = 0;
    $total = 0;
    $cart = cart_all();

    if ($cart) {
        $ids = array_map('intval', array_keys($cart));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_all(
            $conn,
            "SELECT id, name, price, image FROM products WHERE is_available = 1 AND id IN ($ph)",
            str_repeat('i', count($ids)),
            $ids
        );
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        foreach ($cart as $pid => $qty) {
            $pid = (int) $pid;
            if (!isset($byId[$pid])) {
                unset($_SESSION['cart'][$pid]); // produk sudah tidak tersedia
                continue;
            }
            $p = $byId[$pid];
            $price = (int) $p['price'];
            $unit = price_after_discount($price, $pct);
            $qty = (int) $qty;
            $lines[] = [
                'id' => $pid, 'name' => $p['name'], 'image' => $p['image'],
                'price' => $price, 'unit_price' => $unit, 'qty' => $qty,
                'line_total' => $unit * $qty,
            ];
            $subtotal += $price * $qty;
            $total += $unit * $qty;
        }
    }
    return [
        'lines' => $lines,
        'subtotal' => $subtotal,
        'discount_percent' => $pct,
        'discount_amount' => $subtotal - $total,
        'total' => $total,
    ];
}

/* ===================== Pesanan ===================== */

function status_label(string $s): string
{
    return ['pending' => 'Menunggu', 'processing' => 'Diproses', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$s] ?? $s;
}
function status_badge(string $s): string
{
    $cls = ['pending' => 'bg-warning text-dark', 'processing' => 'bg-info text-dark', 'completed' => 'bg-success', 'cancelled' => 'bg-secondary'][$s] ?? 'bg-light text-dark';
    return '<span class="badge ' . $cls . '">' . e(status_label($s)) . '</span>';
}
// Alur status yang boleh dilakukan kasir
function order_transitions(string $from): array
{
    return ['pending' => ['processing', 'cancelled'], 'processing' => ['completed', 'cancelled']][$from] ?? [];
}

// Pilih kasir dengan antrean aktif paling sedikit (pesanan baru otomatis dikelola kasir ini)
function pick_cashier(mysqli $conn): ?int
{
    $r = db_one(
        $conn,
        "SELECT u.id FROM users u
         LEFT JOIN orders o ON o.cashier_id = u.id AND o.status IN ('pending','processing')
         WHERE u.role = 'cashier'
         GROUP BY u.id ORDER BY COUNT(o.id) ASC, u.id ASC LIMIT 1"
    );
    return $r ? (int) $r['id'] : null;
}

function fetch_order_items(mysqli $conn, int $orderId): array
{
    return db_all($conn, "SELECT product_name, price, qty FROM order_items WHERE order_id = ? ORDER BY id", 'i', [$orderId]);
}

function valid_date($s): ?string
{
    if (!is_string($s)) {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

// Filter laporan dari query string (?from=&to=&status=&cashier=). Bawaan: 30 hari terakhir.
function order_filters(): array
{
    $today = new DateTime('today');
    $to = valid_date($_GET['to'] ?? '') ?? $today->format('Y-m-d');
    $from = valid_date($_GET['from'] ?? '') ?? (new DateTime($to))->modify('-29 days')->format('Y-m-d');
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    if ((new DateTime($from))->diff(new DateTime($to))->days > 366) { // batasi rentang maksimal ±1 tahun
        $from = (new DateTime($to))->modify('-365 days')->format('Y-m-d');
    }
    $status = $_GET['status'] ?? 'all';
    if (!in_array($status, ['all', 'pending', 'processing', 'completed', 'cancelled'], true)) {
        $status = 'all';
    }
    $cashier = (string) ($_GET['cashier'] ?? 'all');
    if ($cashier !== 'all' && $cashier !== 'none' && !ctype_digit($cashier)) {
        $cashier = 'all';
    }
    return compact('from', 'to', 'status', 'cashier');
}

// Ambil daftar pesanan sesuai filter. $forceCashierId (untuk kasir) mengunci hanya pesanan miliknya.
function fetch_orders(mysqli $conn, array $f, ?int $forceCashierId = null): array
{
    $where = ['o.created_at >= ?', 'o.created_at < ?'];
    $types = 'ss';
    $params = [$f['from'] . ' 00:00:00', (new DateTime($f['to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'];

    if ($f['status'] !== 'all') {
        $where[] = 'o.status = ?';
        $types .= 's';
        $params[] = $f['status'];
    }
    if ($forceCashierId !== null) {
        $where[] = 'o.cashier_id = ?';
        $types .= 'i';
        $params[] = $forceCashierId;
    } elseif ($f['cashier'] === 'none') {
        $where[] = 'o.cashier_id IS NULL';
    } elseif ($f['cashier'] !== 'all') {
        $where[] = 'o.cashier_id = ?';
        $types .= 'i';
        $params[] = (int) $f['cashier'];
    }

    return db_all(
        $conn,
        "SELECT o.*, c.name AS cashier_name,
                (SELECT COALESCE(SUM(qty), 0) FROM order_items WHERE order_id = o.id) AS item_count,
                (SELECT GROUP_CONCAT(CONCAT(product_name, ' x', qty) ORDER BY id SEPARATOR ', ')
                   FROM order_items WHERE order_id = o.id) AS item_list
         FROM orders o LEFT JOIN users c ON c.id = o.cashier_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY o.created_at DESC, o.id DESC",
        $types,
        $params
    );
}

// Tabel rincian pesanan (dipakai di struk, detail owner, dan detail kasir)
function order_items_table(array $order, array $items): string
{
    $pct = (int) $order['discount_percent'];
    ob_start(); ?>
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
                <?php foreach ($items as $it):
                    $unit = price_after_discount((int) $it['price'], $pct); ?>
                    <tr>
                        <td><?= e($it['product_name']) ?></td>
                        <td class="text-end">
                            <?php if ($pct > 0): ?><del class="text-muted small"><?= rupiah($it['price']) ?></del><br><?php endif; ?>
                            <?= rupiah($unit) ?>
                        </td>
                        <td class="text-center"><?= (int) $it['qty'] ?></td>
                        <td class="text-end"><?= rupiah($unit * (int) $it['qty']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php if ($pct > 0): ?>
                    <tr>
                        <td colspan="3" class="text-end text-muted">Subtotal (harga normal)</td>
                        <td class="text-end text-muted"><?= rupiah($order['subtotal']) ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end text-success">Diskon member <?= $pct ?>%</td>
                        <td class="text-end text-success">- <?= rupiah($order['discount_amount']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="fw-bold fs-5">
                    <td colspan="3" class="text-end">Total</td>
                    <td class="text-end"><?= rupiah($order['total']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
?>
