<?php
// auth.php — status login, peran (role), dan penjaga halaman.
// Dimuat oleh init.php; tidak perlu di-include manual.

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}
function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}
function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}
function is_owner(): bool
{
    return current_role() === 'owner';
}
function is_cashier(): bool
{
    return current_role() === 'cashier';
}
function is_customer(): bool
{
    return current_role() === 'customer';
}

// Hanya tamu (belum login) dan customer yang boleh memesan; owner & kasir tidak.
function can_order(): bool
{
    return !is_logged_in() || is_customer();
}

// Halaman beranda sesuai peran
function home_for_role(): string
{
    return match (current_role()) {
        'owner' => 'owner/orders.php',
        'cashier' => 'cashier/orders.php',
        default => 'index.php',
    };
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

// Contoh: require_role('owner'); atau require_role('owner', 'cashier');
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        die("403 - Akses ditolak. Halaman ini tidak tersedia untuk peran Anda.");
    }
}

// Simpan data user ke session setelah login/registrasi berhasil
function login_user(array $user): void
{
    session_regenerate_id(true); // Cegah session fixation (keranjang tamu tetap terbawa)
    unset($_SESSION['csrf']);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
}
?>
