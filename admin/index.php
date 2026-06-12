<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$pageTitle = 'Dashboard Admin - ' . APP_NAME;
$showAdminNav = true;
$showTopbar = false;
$activePage = 'dashboard';

$stats = [
    'total_products' => (int) db()->query('SELECT COUNT(*) AS total FROM products')->fetch()['total'],
    'total_stock' => (int) db()->query('SELECT COALESCE(SUM(stock), 0) AS total FROM products')->fetch()['total'],
    'total_orders' => (int) db()->query('SELECT COUNT(*) AS total FROM orders')->fetch()['total'],
    'total_revenue' => (float) db()->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE status = 'completed'")->fetch()['total'],
];

$soldItems = (int) db()->query("
    SELECT COALESCE(SUM(oi.qty), 0) AS total
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status = 'completed'
")->fetch()['total'];

$recentOrders = db()->query("
    SELECT order_code, customer_name, total_amount, status, created_at
    FROM orders
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll();

$threshold = low_stock_threshold();
$lowStockStmt = db()->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.stock <= ?
    ORDER BY p.stock ASC, p.name ASC
    LIMIT 10
");
$lowStockStmt->execute([$threshold]);
$lowStockProducts = $lowStockStmt->fetchAll();

require __DIR__ . '/../partials/header.php';
?>

<?php if ($message = flash('success')): ?>
    <div class="notice success"><?= e($message) ?></div>
<?php endif; ?>

<div class="admin-layout">
    <?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>

    <section class="admin-main">
        <div class="admin-page-head card">
            <div>
                <span class="tag tag-primary">Ringkasan penjualan</span>
                <h1>Dashboard admin</h1>
                <p>Pantau performa toko, jumlah order, dan barang yang hampir kehabisan stok.</p>
            </div>
        </div>

        <div class="section-head">
            <div>
                <h2>Dashboard statistik</h2>
                <p>Pantau penjualan, stok, dan barang yang hampir habis.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="tag tag-primary">Total produk</span>
                <strong><?= $stats['total_products'] ?></strong>
                <small>Seluruh barang aktif di katalog.</small>
            </div>
            <div class="stat-card">
                <span class="tag tag-accent">Total stok</span>
                <strong><?= $stats['total_stock'] ?></strong>
                <small>Ketersediaan barang saat ini di gudang.</small>
            </div>
            <div class="stat-card">
                <span class="tag tag-success">Total order</span>
                <strong><?= $stats['total_orders'] ?></strong>
                <small>Order yang masuk dari checkout user.</small>
            </div>
            <div class="stat-card">
                <span class="tag tag-primary">Pendapatan</span>
                <strong><?= format_currency($stats['total_revenue']) ?></strong>
                <small>jumlah pendapatan dari order yang telah selesai.</small>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card panel">
                <div class="section-head">
                    <div>
                        <h3>Stok hampir habis</h3>
                        <p>Produk dengan stok di bawah atau sama dengan <?= (int) $threshold ?>.</p>
                    </div>
                </div>

                <?php if (!$lowStockProducts): ?>
                    <p class="muted">Tidak ada produk yang masuk kategori stok hampir habis saat ini.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th>Kategori</th>
                                    <th>Stok</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                    <tr>
                                        <td><?= e($product['name']) ?></td>
                                        <td><?= e($product['category_name']) ?></td>
                                        <td><strong><?= (int) $product['stock'] ?></strong></td>
                                        <td>
                                            <span class="tag tag-danger">
                                                <?= (int) $product['stock'] === 0 ? 'Habis' : 'Hampir habis' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card panel">
                <div class="section-head">
                    <div>
                        <h3>Riwayat order terbaru</h3>
                        <p>Total item terjual: <?= $soldItems ?></p>
                    </div>
                </div>

                <?php if (!$recentOrders): ?>
                    <p class="muted">Belum ada transaksi masuk.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Pelanggan</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($order['order_code']) ?></strong><br>
                                            <span class="muted"><?= e($order['created_at']) ?></span>
                                        </td>
                                        <td><?= e($order['customer_name']) ?></td>
                                        <td><span class="tag <?= order_status_class($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span></td>
                                        <td><?= format_currency($order['total_amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div style="margin-top:16px">
                    <a class="btn btn-soft" href="<?= url('admin/orders.php') ?>">Buka Kelola Order</a>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
