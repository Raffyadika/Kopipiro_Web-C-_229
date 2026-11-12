<?php // navbar.php — navigasi menyesuaikan peran: tamu, customer, kasir, owner ?>
<nav class="navbar navbar-expand-lg navbar-dark kp-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>">☕ Kopipiro</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#kpNav"
            aria-controls="kpNav" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="kpNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>">Menu</a></li>
                <?php if (is_customer()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('my_orders.php')) ?>">Pesanan Saya</a></li>
                <?php endif; ?>
                <?php if (is_cashier()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('cashier/orders.php')) ?>">Pesanan Saya (Kasir)</a></li>
                <?php endif; ?>
                <?php if (is_owner()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('owner/orders.php')) ?>">Pesanan &amp; Laporan</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('owner/products.php')) ?>">Produk</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('owner/categories.php')) ?>">Kategori</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('owner/users.php')) ?>">Pengguna</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if (can_order()): ?>
                    <a href="<?= e(url('cart.php')) ?>" class="btn btn-outline-light btn-sm">
                        🛒 Keranjang
                        <?php if (cart_count() > 0): ?><span class="badge bg-warning text-dark"><?= cart_count() ?></span><?php endif; ?>
                    </a>
                <?php endif; ?>
                <?php if (is_logged_in()): ?>
                    <span class="text-white small">
                        <?= e($_SESSION['name'] ?? $_SESSION['username']) ?>
                        <span class="badge bg-secondary"><?= e(current_role()) ?></span>
                    </span>
                    <form method="POST" action="<?= e(url('logout.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-sm">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>" class="btn btn-light btn-sm">Login</a>
                    <a href="<?= e(url('register.php')) ?>" class="btn btn-warning btn-sm">Daftar (diskon <?= DISCOUNT_PERCENT ?>%)</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
