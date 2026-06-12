<?php
require_once __DIR__ . '/../app/bootstrap.php';

$activePage = $activePage ?? '';
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark">A</span>
        <div>
            <h2>Admin Panel</h2>
            <p><?= e($_SESSION['admin_name'] ?? 'Admin') ?></p>
        </div>
    </div>

    <div class="side-links">
        <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= url('admin/index.php') ?>">Dashboard</a>
        <a class="<?= $activePage === 'products' ? 'active' : '' ?>" href="<?= url('admin/products.php') ?>">Kelola Barang</a>
        <a class="<?= $activePage === 'orders' ? 'active' : '' ?>" href="<?= url('admin/orders.php') ?>">Kelola Order</a>
        <a href="<?= url('index.php') ?>" target="_blank" rel="noreferrer">Buka Etalase</a>
        <a href="<?= url('admin/logout.php') ?>">Logout</a>
    </div>
</aside>
