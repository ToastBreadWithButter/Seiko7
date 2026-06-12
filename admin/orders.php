<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$pageTitle = 'Kelola Order - ' . APP_NAME;
$showAdminNav = true;
$showTopbar = false;
$activePage = 'orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_order_action'])) {
    $action = $_POST['admin_order_action'];
    $orderId = (int) ($_POST['order_id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        flash('error', 'Order tidak ditemukan.');
        redirect('admin/orders.php');
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        if ($action === 'mark_shipped' && can_mark_shipped($order)) {
            $stmt = $pdo->prepare('UPDATE orders SET status = ?, shipped_at = NOW() WHERE id = ?');
            $stmt->execute(['shipped', $orderId]);
            flash('success', 'Order ditandai sudah dikirim.');
        } elseif ($action === 'approve_refund' && can_approve_refund($order)) {
            $stmt = $pdo->prepare('UPDATE orders SET status = ?, refunded_at = NOW() WHERE id = ?');
            $stmt->execute(['refunded', $orderId]);
            restock_order_items($pdo, $orderId);
            flash('success', 'Refund disetujui dan stok barang dikembalikan.');
        } else {
            throw new RuntimeException('Aksi admin tidak valid untuk status order ini.');
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Gagal memperbarui order: ' . $throwable->getMessage());
    }

    redirect('admin/orders.php');
}

$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(order_code LIKE ? OR customer_name LIKE ? OR phone LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}

$stmt = db()->prepare(
    'SELECT * FROM orders ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC'
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$itemMap = [];
if ($orders) {
    $orderIds = array_map(static fn(array $order): int => (int) $order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC");
    $itemStmt->execute($orderIds);
    foreach ($itemStmt->fetchAll() as $item) {
        $itemMap[(int) $item['order_id']][] = $item;
    }
}

require __DIR__ . '/../partials/header.php';
?>

<?php if ($message = flash('success')): ?>
    <div class="notice success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($message = flash('error')): ?>
    <div class="notice error"><?= e($message) ?></div>
<?php endif; ?>

<div class="admin-layout">
    <?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>

    <section class="admin-main">
        <div class="admin-page-head card">
            <div>
                <span class="tag tag-primary">Order lifecycle</span>
                <h1>Kelola order</h1>
                <p>Admin bisa menandai order sudah dikirim dan menyetujui refund yang diajukan user.</p>
            </div>
        </div>

        <div class="card panel">
            <form method="get" class="filter-grid admin-orders-filter">
                <div class="field">
                    <label for="q">Pencarian order</label>
                    <input id="q" name="q" value="<?= e($search) ?>" placeholder="Cari kode order, nama, atau nomor HP">
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Semua status</option>
                        <?php foreach (order_status_map() as $value => $meta): ?>
                            <option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field search-action">
                    <button class="btn btn-primary" type="submit" style="width:100%">Filter Order</button>
                </div>
            </form>
        </div>

        <div class="orders-stack">
            <?php foreach ($orders as $order): ?>
                <article class="card panel order-card">
                    <div class="section-head">
                        <div>
                            <div class="product-meta">
                                <span class="tag <?= order_status_class($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></span>
                                <span class="tag"><?= e($order['order_code']) ?></span>
                            </div>
                            <h3><?= e($order['customer_name']) ?></h3>
                            <p><?= e($order['phone']) ?> | <?= e($order['address']) ?></p>
                        </div>
                        <div class="order-total">
                            <strong><?= format_currency($order['total_amount']) ?></strong>
                            <span class="muted"><?= e($order['created_at']) ?></span>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Qty</th>
                                    <th>Harga</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itemMap[(int) $order['id']] ?? [] as $item): ?>
                                    <tr>
                                        <td><?= e($item['product_name']) ?></td>
                                        <td><?= (int) $item['qty'] ?></td>
                                        <td><?= format_currency($item['price']) ?></td>
                                        <td><?= format_currency($item['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($order['cancellation_reason'])): ?>
                        <p class="muted">Alasan batal: <?= e($order['cancellation_reason']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($order['refund_reason'])): ?>
                        <p class="muted">Alasan refund: <?= e($order['refund_reason']) ?></p>
                    <?php endif; ?>

                    <div class="order-actions">
                        <?php if (can_mark_shipped($order)): ?>
                            <form method="post">
                                <input type="hidden" name="admin_order_action" value="mark_shipped">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <button class="btn btn-primary" type="submit">Konfirmasi Sudah Dikirim</button>
                            </form>
                        <?php endif; ?>

                        <?php if (can_approve_refund($order)): ?>
                            <form method="post">
                                <input type="hidden" name="admin_order_action" value="approve_refund">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <button class="btn btn-danger" type="submit">Setujui Refund</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (!$orders): ?>
                <div class="card panel">
                    <p class="muted">Belum ada order yang cocok dengan filter saat ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
