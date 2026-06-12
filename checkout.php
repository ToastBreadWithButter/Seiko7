<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Checkout - ' . APP_NAME;
$activePage = 'cart';

$cartProducts = get_cart_products();
$cartData = normalize_cart_against_stock();

if (!$cartProducts) {
    flash('error', 'Keranjang masih kosong.');
    redirect('index.php');
}

$items = [];
$totalAmount = 0;
$totalQty = 0;

foreach ($cartProducts as $product) {
    $qty = (int) ($cartData[(int) $product['id']] ?? 0);
    if ($qty <= 0) {
        continue;
    }
    $subtotal = $qty * (float) $product['display_price'];
    $totalAmount += $subtotal;
    $totalQty += $qty;
    $items[] = [
        'id' => (int) $product['id'],
        'name' => $product['name'],
        'qty' => $qty,
        'price' => (float) $product['display_price'],
        'subtotal' => $subtotal,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customerName === '' || $phone === '' || $address === '') {
        flash('error', 'Lengkapi nama, nomor HP, dan alamat.');
        redirect('checkout.php');
    }

    // --- TAMBAHAN: Pastikan keranjang masih valid (stok) sebelum transaksi ---
    if (empty($items)) {
        flash('error', 'Tidak ada item yang bisa dipesan.');
        redirect('checkout.php');
    }

    $pdo = null;
    try {
        $pdo = db();
        $pdo->beginTransaction();

        // --- TAMBAHAN: Kunci & validasi stok setiap produk ---
        $stockCheckStmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? FOR UPDATE");
        $updateStockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

        foreach ($items as $item) {
            $stockCheckStmt->execute([$item['id']]);
            $productRow = $stockCheckStmt->fetch();

            if (!$productRow) {
                throw new RuntimeException("Produk \"" . $item['name'] . "\" tidak tersedia lagi.");
            }
            if ((int) $productRow['stock'] < $item['qty']) {
                throw new RuntimeException(
                    "Stok \"" . $item['name'] . "\" tidak mencukupi. Tersedia " . (int) $productRow['stock'] . ", diminta " . $item['qty']
                );
            }
        }
        // --- END TAMBAHAN ---

        $orderCode = 'ORD-' . date('YmdHis') . '-' . random_int(100, 999);
        $stmt = $pdo->prepare(
            'INSERT INTO orders (order_code, customer_name, phone, address, total_amount, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orderCode,
            $customerName,
            $phone,
            $address,
            $totalAmount,
            'pending',
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, qty, price, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($items as $item) {
            $itemStmt->execute([
                $orderId,
                $item['id'],
                $item['name'],
                $item['qty'],
                $item['price'],
                $item['subtotal'],
            ]);

            // --- TAMBAHAN: Kurangi stok setelah item berhasil dimasukkan ---
            $updateStockStmt->execute([$item['qty'], $item['id']]);
            // --- END TAMBAHAN ---
        }

        $pdo->commit();
        clear_cart();
        register_recent_order($orderCode, $phone);
        flash('success', 'Checkout berhasil. Order masuk ke sistem dan menunggu diproses admin. Nomor order: ' . $orderCode);
        redirect('orders.php?phone=' . urlencode($phone) . '&order_code=' . urlencode($orderCode));
    } catch (Throwable $throwable) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Checkout gagal: ' . $throwable->getMessage());
        redirect('checkout.php');
    }
}

require __DIR__ . '/partials/header.php';
?>

<?php if ($message = flash('error')): ?>
    <div class="notice error"><?= e($message) ?></div>
<?php endif; ?>

<div class="section-head">
    <div>
        <h2>Checkout barang</h2>
        <p></p>
    </div>
</div>

<div class="split">
    <section class="card panel">
        <form method="post" class="mini-grid">
            <div class="field">
                <label for="customer_name">Nama pembeli</label>
                <input id="customer_name" name="customer_name" required>
            </div>
            <div class="field">
                <label for="phone">Nomor HP</label>
                <input id="phone" name="phone" required>
            </div>
            <div class="field" style="grid-column:1/-1">
                <label for="address">Alamat pengiriman</label>
                <textarea id="address" name="address" rows="4" required></textarea>
            </div>

            <div class="table-card" style="grid-column:1/-1">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e($item['name']) ?></td>
                                    <td><?= format_currency($item['price']) ?></td>
                                    <td><?= (int) $item['qty'] ?></td>
                                    <td><strong><?= format_currency($item['subtotal']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="field" style="grid-column:1/-1;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <strong>Total barang: <?= (int) $totalQty ?></strong><br>
                    <span class="muted">Total checkout: <?= format_currency($totalAmount) ?></span>
                </div>
                <button class="btn btn-primary" type="submit" name="place_order" value="1">Buat Order</button>
            </div>
        </form>
    </section>

    <aside class="card summary-box">
        <h3>Catatan sistem</h3>
        <p class="muted">
            <!-- --- UBAH: Komentar disesuaikan --- -->
            Setelah order dibuat, sistem akan memvalidasi stok dan langsung mengurangi stok produk.
            Jika stok tersisa di bawah atau sama dengan threshold, produk akan muncul di dashboard admin sebagai stok hampir habis.
        </p>
        <div class="summary-line">
            <span>Threshold default</span>
            <strong><?= low_stock_threshold() ?></strong>
        </div>
        <div class="summary-line">
            <span>Item unik</span>
            <strong><?= count($items) ?></strong>
        </div>
        <a class="btn btn-soft" href="<?= url('cart.php') ?>" style="width:100%">Kembali ke Keranjang</a>
    </aside>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>