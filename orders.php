<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Pesanan Saya - ' . APP_NAME;
$activePage = 'orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_action'])) {
    $action = $_POST['order_action'];
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $lookupPhone = trim($_POST['lookup_phone'] ?? '');
    $lookupCode = trim($_POST['lookup_code'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || ($lookupPhone !== '' && $lookupPhone !== $order['phone'])) {
        flash('error', 'Pesanan tidak ditemukan atau nomor HP tidak cocok.');
        redirect('orders.php');
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        if ($action === 'cancel' && can_cancel_order($order)) {
            $stmt = $pdo->prepare('UPDATE orders SET status = ?, cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?');
            $stmt->execute(['cancelled', $reason !== '' ? $reason : 'Dibatalkan oleh user', $orderId]);
            restock_order_items($pdo, $orderId);
            flash('success', 'Pesanan berhasil dibatalkan dan stok dikembalikan.');
        } elseif ($action === 'confirm_received' && can_confirm_received($order)) {
            $stmt = $pdo->prepare('UPDATE orders SET status = ?, completed_at = NOW() WHERE id = ?');
            $stmt->execute(['completed', $orderId]);
            flash('success', 'Pesanan dikonfirmasi sudah diterima. Dana sekarang masuk ke admin.');
        } elseif ($action === 'request_refund' && can_request_refund($order)) {
            if ($reason === '') {
                throw new RuntimeException('Alasan refund wajib diisi.');
            }
            $stmt = $pdo->prepare('UPDATE orders SET status = ?, refund_reason = ? WHERE id = ?');
            $stmt->execute(['refund_requested', $reason, $orderId]);
            flash('success', 'Permintaan refund sudah dikirim ke admin untuk ditinjau.');
        } else {
            throw new RuntimeException('Aksi tidak valid untuk status pesanan ini.');
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Aksi pesanan gagal: ' . $throwable->getMessage());
    }

    $query = http_build_query([
        'phone' => $lookupPhone,
        'order_code' => $lookupCode,
    ]);
    redirect('orders.php' . ($query ? '?' . $query : ''));
}

$lookupPhone = trim($_GET['phone'] ?? ($_SESSION['order_lookup_phone'] ?? ''));
$lookupCode = trim($_GET['order_code'] ?? '');

$orders = [];
if ($lookupPhone !== '' || $lookupCode !== '') {
    $where = [];
    $params = [];

    if ($lookupPhone !== '') {
        $where[] = 'phone = ?';
        $params[] = $lookupPhone;
    }

    if ($lookupCode !== '') {
        $where[] = 'order_code = ?';
        $params[] = $lookupCode;
    }

    $stmt = db()->prepare(
        'SELECT * FROM orders WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC'
    );
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
}

$itemMap = [];
if ($orders) {
    $orderIds = array_map(static fn(array $order): int => (int) $order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC");
    $stmt->execute($orderIds);
    foreach ($stmt->fetchAll() as $item) {
        $itemMap[(int) $item['order_id']][] = $item;
    }
}

require __DIR__ . '/partials/header.php';
?>

<?php if ($message = flash('success')): ?>
    <div class="notice success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($message = flash('error')): ?>
    <div class="notice error"><?= e($message) ?></div>
<?php endif; ?>

<section class="card panel">
    <div class="section-head">
        <div>
            <h2>Lacak pesanan</h2>
            <p>Cari pesanan dengan nomor HP dan kode order.</p>
        </div>
    </div>
    <form method="get" class="filter-grid orders-search-grid">
        <div class="field">
            <label for="phone">Nomor HP</label>
            <input id="phone" name="phone" value="<?= e($lookupPhone) ?>" placeholder="Masukkan nomor HP saat checkout">
        </div>
        <div class="field">
            <label for="order_code">Kode order</label>
            <input id="order_code" name="order_code" value="<?= e($lookupCode) ?>" placeholder="Contoh: ORD-20260604-123">
        </div>
        <div class="field search-action">
            <button class="btn btn-primary" type="submit" style="width:100%">Cari Pesanan</button>
        </div>
    </form>

    <?php if ($recent = recent_order_codes()): ?>
        <div class="recent-orders-inline">
            <span class="muted">Order terbaru di browser ini:</span>
            <?php foreach ($recent as $code): ?>
                <a class="pill" href="<?= url('orders.php?order_code=' . urlencode($code) . '&phone=' . urlencode((string) ($_SESSION['order_lookup_phone'] ?? ''))) ?>"><?= e($code) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($orders): ?>
    <section class="section orders-stack">
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
                    <?php if (can_cancel_order($order)): ?>
                        <form method="post" class="inline-action-form">
                            <input type="hidden" name="order_action" value="cancel">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="lookup_phone" value="<?= e($lookupPhone) ?>">
                            <input type="hidden" name="lookup_code" value="<?= e($lookupCode) ?>">
                            <input type="text" name="reason" placeholder="Alasan pembatalan, mis. alamat salah">
                            <button class="btn btn-danger" type="submit">Batalkan Order</button>
                        </form>
                    <?php endif; ?>

                    <?php if (can_confirm_received($order)): ?>
                        <form method="post">
                            <input type="hidden" name="order_action" value="confirm_received">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="lookup_phone" value="<?= e($lookupPhone) ?>">
                            <input type="hidden" name="lookup_code" value="<?= e($lookupCode) ?>">
                            <button class="btn btn-primary" type="submit">Konfirmasi Barang Diterima</button>
                        </form>
                    <?php endif; ?>

                    <?php if (can_request_refund($order)): ?>
                        <form method="post" class="inline-action-form">
                            <input type="hidden" name="order_action" value="request_refund">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="lookup_phone" value="<?= e($lookupPhone) ?>">
                            <input type="hidden" name="lookup_code" value="<?= e($lookupCode) ?>">
                            <input type="text" name="reason" placeholder="Alasan refund">
                            <button class="btn btn-soft" type="submit">Ajukan Refund</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php elseif ($lookupPhone !== '' || $lookupCode !== ''): ?>
    <section class="section card panel">
        <p class="muted">Pesanan tidak ditemukan. Pastikan nomor HP dan kode order sudah sesuai.</p>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>

