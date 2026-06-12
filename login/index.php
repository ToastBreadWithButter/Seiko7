<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (admin_logged_in()) {
    redirect('admin/index.php');
}

$pageTitle = 'Login Admin - ' . APP_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && hash_equals((string) $user['password'], $password)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_name'] = $user['name'];
        flash('success', 'Login berhasil. Selamat datang, ' . $user['name'] . '.');
        redirect('admin/index.php');
    }

    flash('error', 'Username atau password salah.');
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <a class="brand" href="<?= url('index.php') ?>" style="margin-bottom:18px;display:inline-flex;">
            <span class="brand-mark">A</span>
            <span>
                <strong>Admin Panel</strong>
                <small>kelola produk dan orderan dengan lebih mudah</small>
            </span>
        </a>

        <h1>Login Admin</h1>
        <p class="muted"><strong>Masukan Username dan password</strong> <strong></strong></p>

        <?php if ($message = flash('error')): ?>
            <div class="notice error"><?= e($message) ?></div>
        <?php endif; ?>

        <form method="post" class="mini-grid">
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%">Masuk</button>
        </form>
    </div>
</div>
</body>
</html>

