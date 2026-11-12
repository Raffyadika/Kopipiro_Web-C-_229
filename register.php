<?php
// register.php — pendaftaran customer (peran SELALU 'customer', tidak bisa dipilih dari form)
include 'init.php';

$allowedNext = ['checkout.php', 'cart.php'];
$next = $_POST['next'] ?? $_GET['next'] ?? '';
$next = in_array($next, $allowedNext, true) ? $next : '';

if (is_logged_in()) {
    redirect(home_for_role());
}

$errors = [];
$name = '';
$username = '';

if (isset($_POST['register'])) {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Nama harus 2–100 karakter.';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $errors[] = 'Username 3–30 karakter: huruf, angka, titik, atau underscore.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db_run($conn, "INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, 'customer')", 'sss', [$name, $username, $hash]);
            $user = ['id' => $conn->insert_id, 'name' => $name, 'username' => $username, 'role' => 'customer'];
            login_user($user);
            flash('success', 'Pendaftaran berhasil! Diskon ' . DISCOUNT_PERCENT . '% otomatis berlaku untuk pesanan Anda.');
            redirect($next ?: 'index.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) { // duplikat di kolom UNIQUE (username)
                $errors[] = 'Username sudah dipakai, pilih yang lain.';
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle = 'Daftar';
include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card kp-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Daftar Akun Customer</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning py-2 small">☕ Member mendapat <strong>diskon <?= DISCOUNT_PERCENT ?>%</strong> untuk setiap pesanan.</div>
                <?php foreach ($errors as $er): ?>
                    <div class="alert alert-danger py-2"><?= e($er) ?></div>
                <?php endforeach; ?>
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama lengkap</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name) ?>" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" required maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                    </div>
                    <div class="mb-4">
                        <label for="confirm" class="form-label">Ulangi password</label>
                        <input type="password" class="form-control" id="confirm" name="confirm" minlength="6" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="register" class="btn btn-success">Daftar</button>
                    </div>
                </form>
                <p class="small text-center mt-3 mb-0">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Login</a></p>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
