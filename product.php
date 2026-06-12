<?php
require_once __DIR__ . '/app/bootstrap.php';

$productId = (int) ($_GET['id'] ?? 0);

if ($productId <= 0) {
    flash('error', 'Produk tidak ditemukan.');
    redirect('index.php');
}

$stmt = db()->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Produk tidak ditemukan.');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $qty = max(1, (int) ($_POST['qty'] ?? 1));

    if ((int) $product['stock'] <= 0) {
        flash('error', 'Stok produk habis.');
    } else {
        $qty = min($qty, (int) $product['stock']);
        add_to_cart((int) $product['id'], $qty);
        flash('success', 'Produk masuk ke keranjang.');
    }

    redirect('product.php?id=' . (int) $product['id']);
}

$relatedStmt = db()->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.category_id = ? AND p.id <> ?
    ORDER BY p.updated_at DESC
    LIMIT 3
");
$relatedStmt->execute([(int) $product['category_id'], (int) $product['id']]);
$relatedProducts = $relatedStmt->fetchAll();

$pageTitle = $product['name'] . ' - ' . APP_NAME;
$activePage = 'home';

require __DIR__ . '/partials/header.php';
?>

<?php if ($message = flash('success')): ?>
    <div class="notice success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($message = flash('error')): ?>
    <div class="notice error"><?= e($message) ?></div>
<?php endif; ?>

<section class="detail-hero card">
    <div class="detail-grid">
        <div class="detail-media">
            <img src="<?= asset($product['image']) ?>" alt="<?= e($product['name']) ?>">
        </div>
        <div class="detail-content">
            <div class="product-meta">
                <span class="tag tag-primary"><?= e($product['category_name']) ?></span>
                <?php if ((int) $product['is_promo'] === 1): ?>
                    <span class="tag tag-accent">Promo <?= (int) $product['promo_percent'] ?>%</span>
                <?php endif; ?>
            </div>
            <h1><?= e($product['name']) ?></h1>
            <p class="muted detail-description"><?= e($product['description']) ?></p>
            <div class="price detail-price">
                <?php if (effective_price($product) < (float) $product['price']): ?>
                    <del><?= format_currency($product['price']) ?></del>
                <?php endif; ?>
                <?= format_currency(effective_price($product)) ?>
            </div>
            <div class="pillbar">
                <span class="pill"><strong>Stok</strong> <?= (int) $product['stock'] ?></span>
                <span class="pill"><strong>Kategori</strong> <?= e($product['category_name']) ?></span>
            </div>

            <div class="detail-purchase card">
                <div>
                    <strong>Atur jumlah beli</strong>
                    <p class="muted">Tambah ke keranjang dari halaman detail agar user fokus pada satu produk.</p>
                </div>
                <form method="post" class="detail-form">
                    <input type="hidden" name="add_to_cart" value="1">
                    <div class="field quantity-field">
                        <label for="qty">Jumlah beli</label>
                        <input id="qty" class="quantity quantity-compact" type="number" name="qty" value="1" min="1" max="<?= max(1, (int) $product['stock']) ?>">
                    </div>
                    <button class="btn btn-primary" type="submit" <?= (int) $product['stock'] <= 0 ? 'disabled' : '' ?>>Masuk Keranjang</button>
                    <a class="btn btn-outline" href="<?= url('index.php') ?>">Kembali ke Etalase</a>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if ($relatedProducts): ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2>Produk serupa</h2>
            <p>Barang lain dari kategori yang sama untuk membantu user membandingkan pilihan.</p>
        </div>
    </div>
    <div class="product-grid">
        <?php foreach ($relatedProducts as $related): ?>
            <?php $displayPrice = effective_price($related); ?>
            <article class="product-card">
                <a class="product-link" href="<?= url('product.php?id=' . (int) $related['id']) ?>">
                    <div class="product-media">
                        <img src="<?= asset($related['image']) ?>" alt="<?= e($related['name']) ?>">
                    </div>
                </a>
                <div class="product-body">
                    <div class="product-meta">
                        <span class="tag tag-primary"><?= e($related['category_name']) ?></span>
                    </div>
                    <h3><a href="<?= url('product.php?id=' . (int) $related['id']) ?>"><?= e($related['name']) ?></a></h3>
                    <p class="muted"><?= e($related['description']) ?></p>
                    <div class="price">
                        <?php if ($displayPrice < (float) $related['price']): ?>
                            <del><?= format_currency($related['price']) ?></del>
                        <?php endif; ?>
                        <?= format_currency($displayPrice) ?>
                    </div>
                    <a class="btn btn-soft" href="<?= url('product.php?id=' . (int) $related['id']) ?>">Buka Produk</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
