<?php
// login.php
include 'init.php';

// Halaman tujuan setelah login (hanya dari daftar putih, mencegah open redirect)
$allowedNext = ['checkout.php', 'cart.php'];
$next = $_POST['next'] ?? $_GET['next'] ?? '';
$next = in_array($next, $allowedNext, true) ? $next : '';

if (is_logged_in()) {
    redirect($next ?: home_for_role());
}

$error = "";

if (isset($_POST['login'])) {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = db_one($conn, "SELECT id, name, username, password, role FROM users WHERE username = ?", 's', [$username]);

    if ($user && password_verify($password, $user['password'])) {
        login_user($user);
        flash('success', 'Selamat datang, ' . $user['name'] . '!' . ($user['role'] === 'customer' ? ' Diskon ' . DISCOUNT_PERCENT . '% otomatis berlaku untuk pesanan Anda.' : ''));
        redirect($user['role'] === 'customer' && $next ? $next : home_for_role());
    }
    // Sengaja samar: tidak membocorkan field mana yang salah
    $error = "Username atau password salah.";
}

$pageTitle = 'Login';
include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card kp-card">
            <div class="card-header bg-dark text-white text-center">
                <h5 class="mb-0">Login Kopipiro</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="login" class="btn btn-kp">Login</button>
                    </div>
                </form>
                <hr>
                <p class="small text-center mb-0">
                    Belum punya akun? <a href="<?= e(url('register.php' . ($next ? '?next=' . urlencode($next) : ''))) ?>">Daftar</a>
                    dan nikmati diskon <?= DISCOUNT_PERCENT ?>%.<br>
                    <a href="<?= e(url('index.php')) ?>">Lanjut tanpa login</a> (harga normal)
                </p>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
