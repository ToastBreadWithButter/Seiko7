<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Keranjang - ' . APP_NAME;
$activePage = 'cart';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_id'])) {
        remove_from_cart((int) $_POST['remove_id']);
        flash('success', 'Barang dihapus dari keranjang.');
        redirect('cart.php');
    }

    if (isset($_POST['update_cart'])) {
        $qtys = $_POST['qty'] ?? [];
        foreach ($qtys as $productId => $qty) {
            update_cart_qty((int) $productId, max(0, (int) $qty));
        }
        normalize_cart_against_stock();
        flash('success', 'Keranjang diperbarui.');
        redirect('cart.php');
    }
}

$products = get_cart_products();
$cartData = normalize_cart_against_stock();
$totalQty = 0;
$totalPrice = 0;

foreach ($products as $product) {
    $qty = (int) ($cartData[(int) $product['id']] ?? 0);
    $line = $qty * (float) $product['display_price'];
    $totalQty += $qty;
    $totalPrice += $line;
}

require __DIR__ . '/partials/header.php';
?>

<?php if ($message = flash('success')): ?>
    <div class="notice success"><?= e($message) ?></div>
<?php endif; ?>

<div class="section-head">
    <div>
        <h2>Keranjang belanja</h2>
        <p>Pastikan produk dan jumlah barang sudah benar sebelum checkout.</p>
    </div>
</div>

<?php if (!$products): ?>
    <div class="card panel">
        <h3>Keranjang masih kosong</h3>
        <p class="muted">Silakan kembali ke etalase dan tambahkan barang yang kamu butuhkan.</p>
        <a class="btn btn-primary" href="<?= url('index.php') ?>">Kembali ke Etalase</a>
    </div>
<?php else: ?>
    <div class="split">
        <div class="table-card">
            <div class="table-wrap">
                <form method="post">
                    <table>
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $qty = (int) ($cartData[(int) $product['id']] ?? 0);
                                $line = $qty * (float) $product['display_price'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($product['name']) ?></strong><br>
                                        <span class="muted"><?= e($product['category_name']) ?></span>
                                    </td>
                                    <td><?= format_currency($product['display_price']) ?></td>
                                    <td>
                                        <input class="field quantity" type="number" min="0" max="<?= (int) $product['stock'] ?>" name="qty[<?= (int) $product['id'] ?>]" value="<?= $qty ?>">
                                        <div class="muted">Stok tersedia: <?= (int) $product['stock'] ?></div>
                                    </td>
                                    <td><strong><?= format_currency($line) ?></strong></td>
                                    <td>
                                        <button class="btn btn-danger" type="submit" name="remove_id" value="<?= (int) $product['id'] ?>">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="panel" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <button class="btn btn-soft" type="submit" name="update_cart" value="1">Perbarui Keranjang</button>
                        <a class="btn btn-primary" href="<?= url('checkout.php') ?>">Checkout</a>
                    </div>
                </form>
            </div>
        </div>

        <aside class="card summary-box">
            <h3>Ringkasan checkout</h3>
            <div class="summary-line">
                <span>Total barang</span>
                <strong><?= (int) $totalQty ?></strong>
            </div>
            <div class="summary-line">
                <span>Total checkout</span>
                <strong><?= format_currency($totalPrice) ?></strong>
            </div>
            <div class="summary-line">
                <span>Jumlah produk</span>
                <strong><?= count($products) ?></strong>
            </div>
            <p class="muted">Checkout akan membuat order baru di database dan stok produk berkurang otomatis melalui trigger MySQL.</p>
            <a class="btn btn-primary" href="<?= url('checkout.php') ?>" style="width:100%">Lanjut Checkout</a>
        </aside>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
