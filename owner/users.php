<?php
// owner/users.php — kelola pengguna (owner, kasir, customer). Dikembangkan dari users.php lama.
include __DIR__ . '/../init.php';
require_role('owner');

$message = "";

if (isset($_POST['add_user'])) {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $message = "<div class='alert alert-danger'>Nama harus 2–100 karakter.</div>";
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $message = "<div class='alert alert-danger'>Username 3–30 karakter: huruf, angka, titik, atau underscore.</div>";
    } elseif (strlen($password) < 6) {
        $message = "<div class='alert alert-danger'>Password minimal 6 karakter.</div>";
    } elseif (!in_array($role, ['owner', 'cashier', 'customer'], true)) {
        $message = "<div class='alert alert-danger'>Peran tidak valid.</div>";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db_run($conn, "INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)", 'ssss', [$name, $username, $hash, $role]);
            $message = "<div class='alert alert-success'>Pengguna berhasil ditambahkan.</div>";
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== 1062) { // 1062 = nilai ganda di kolom UNIQUE (username)
                throw $ex;
            }
            $message = "<div class='alert alert-danger'>Username sudah dipakai.</div>";
        }
    }
}

$users = db_all(
    $conn,
    "SELECT u.id, u.name, u.username, u.role, u.created_at,
            (SELECT COUNT(*) FROM orders WHERE cashier_id = u.id) AS handled,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS placed
     FROM users u ORDER BY FIELD(u.role, 'owner', 'cashier', 'customer'), u.id"
);
$roleBadge = ['owner' => 'bg-danger', 'cashier' => 'bg-primary', 'customer' => 'bg-secondary'];

$pageTitle = 'Pengguna';
include __DIR__ . '/../header.php';
?>
<?= $message ?>
<div class="row g-4">
    <div class="col-md-4">
        <div class="card kp-card">
            <div class="card-header bg-success text-white"><h5 class="mb-0">Tambah Pengguna</h5></div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama</label>
                        <input type="text" class="form-control" id="name" name="name" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                    </div>
                    <div class="mb-4">
                        <label for="role" class="form-label">Peran</label>
                        <select class="form-select" id="role" name="role">
                            <option value="cashier">Kasir</option>
                            <option value="customer">Customer</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    <div class="d-grid"><button type="submit" name="add_user" class="btn btn-success">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card kp-card">
            <div class="card-header bg-primary text-white"><h5 class="mb-0">Daftar Pengguna</h5></div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>No</th><th>Nama</th><th>Username</th><th>Peran</th><th class="text-center">Pesanan</th></tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($users as $u): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= e($u['name']) ?>
                                    <?php if ((int) $u['id'] === current_user_id()): ?><span class="badge bg-info">Anda</span><?php endif; ?>
                                </td>
                                <td><?= e($u['username']) ?></td>
                                <td><span class="badge <?= $roleBadge[$u['role']] ?? 'bg-secondary' ?>"><?= e($u['role']) ?></span></td>
                                <td class="text-center small">
                                    <?php if ($u['role'] === 'cashier'): ?><?= (int) $u['handled'] ?> dikelola
                                    <?php elseif ($u['role'] === 'customer'): ?><?= (int) $u['placed'] ?> dipesan
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../footer.php'; ?>
