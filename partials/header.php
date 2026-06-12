<?php
require_once __DIR__ . '/../app/bootstrap.php';

$pageTitle = $pageTitle ?? APP_NAME;
$showAdminNav = $showAdminNav ?? false;
$showTopbar = $showTopbar ?? true;
$activePage = $activePage ?? '';
$cartCount = cart_count();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/x-icon" href="/penjualan/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<body class="<?= $showAdminNav ? 'admin-body' : 'store-body' ?>">
<div class="page-shell">
    <?php if ($showTopbar): ?>
    <header class="topbar">
        <a class="brand" href="<?= $showAdminNav ? url('admin/index.php') : url('index.php') ?>">
            <span class="brand-mark">S</span>
            <span>
                <strong><?= e(APP_NAME) ?></strong>
                <small><?= $showAdminNav ? 'Dashboard admin dan manajemen barang' : 'Promo termurah hanya di ' . e(APP_NAME) ?></small>
            </span>
        </a>

        <nav class="nav-links">
            <?php if ($showAdminNav): ?>
                <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= url('admin/index.php') ?>">Dashboard</a>
                <a class="<?= $activePage === 'products' ? 'active' : '' ?>" href="<?= url('admin/products.php') ?>">Kelola Barang</a>
                <a class="<?= $activePage === 'orders' ? 'active' : '' ?>" href="<?= url('admin/orders.php') ?>">Kelola Order</a>
                <a href="<?= url('index.php') ?>">Lihat Etalase</a>
                <a href="<?= url('admin/logout.php') ?>">Logout</a>
            <?php else: ?>
                <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="<?= url('index.php') ?>"><i class="fa fa-home" style="font-size:24px; color:black;"></i></a>
                <a class="<?= $activePage === 'cart' ? 'active' : '' ?>" href="<?= url('cart.php') ?>"><i class="fa fa-shopping-cart" style="font-size:24px; color:black;"></i> <span class="badge"><?= (int) $cartCount ?></span></a>
                <a class="<?= $activePage === 'orders' ? 'active' : '' ?>" href="<?= url('orders.php') ?>"><i class="fa fa-truck fa-flip-horizontal" style="font-size:24px; color:#000000;"></i></a>
                <a href="<?= url('login') ?>"><i class="fa fa-sign-in" style="font-size:24px; color:black;"></i></a>
            <?php endif; ?>
        </nav>
    </header>
    <?php endif; ?>

    <main class="content">
