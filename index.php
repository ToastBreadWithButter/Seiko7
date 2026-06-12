<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Etalase - ' . APP_NAME;
$activePage = 'home';

// Kategori tetap diambil untuk keperluan form (tetap bisa dipakai di form walau action ke pencarian.php)
$categories = db()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

// Produk etalase: semua produk, tanpa filter
$products = db()->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    ORDER BY p.created_at DESC
")->fetchAll();

$promoStmt = db()->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.is_promo = 1
    ORDER BY p.updated_at DESC
    LIMIT 6
");
$promoProducts = $promoStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = (int) $_POST['product_id'];
    $qty = max(1, (int) ($_POST['qty'] ?? 1));

    $stmt = db()->prepare('SELECT stock FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        flash('error', 'Produk tidak ditemukan.');
    } elseif ((int) $product['stock'] <= 0) {
        flash('error', 'Stok produk habis.');
    } else {
        $qty = min($qty, (int) $product['stock']);
        add_to_cart($productId, $qty);
        flash('success', 'Produk masuk ke keranjang.');
    }

    redirect('index.php');
}

require __DIR__ . '/partials/header.php';
?>

<?php if ($message = flash('success')): ?>
    <div class="notice success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($message = flash('error')): ?>
    <div class="notice error"><?= e($message) ?></div>
<?php endif; ?>
<section class="hero">
    <div class="hero-grid">
        <div>
            <h1>Belanja hanya di Seiko7</h1>
            <p>
                Temukan berbagai produk berkualitas dengan harga terbaik. Nikmati kemudahan berbelanja online
                dengan layanan cepat dan aman hanya di <?= e(APP_NAME) ?>. Ayo mulai belanja sekarang!
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="#produk">Lihat Produk</a>
                <a class="btn btn-secondary" href="<?= url('cart.php') ?>">Buka Keranjang</a>
            </div>
        </div>
        <div class="hero-stat">
            <div class="stat-chip"><strong><?= count($products) ?></strong><span>Produk aktif</span></div>
            <div class="stat-chip"><strong><?= count($promoProducts) ?></strong><span>Promo berjalan</span></div>
            <div class="stat-chip"><strong><?= cart_count() ?></strong><span>Item di keranjang</span></div>
        </div>
    </div>
</section>

<section class="section card filters storefront-filters">
    <div class="section-head">
        <div>
            <h2>Temukan barang</h2>
            <p>Cari produk yang Anda butuhkan dengan mudah.</p>
        </div>
    </div>
    <!-- Action sekarang menuju pencarian.php -->
    <form method="get" action="pencarian.php" class="filter-grid">
        <div class="field">
            <label for="q">Pencarian barang</label>
            <input id="q" name="q" value="" placeholder="Cari nama atau deskripsi barang">
        </div>
        <div class="field">
            <label for="category">Kategori</label>
            <select id="category" name="category">
                <option value="0">Semua kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>">
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="sort">Urutkan</label>
            <select id="sort" name="sort">
                <option value="baru">Terbaru</option>
                <option value="termurah">Harga termurah</option>
                <option value="termahal">Harga termahal</option>
            </select>
        </div>
        <div class="field" style="display:flex;align-items:end;">
            <button class="btn btn-primary" type="submit" style="width:100%">Cari</button>
        </div>
    </form>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Promo pilihan</h2>
            <p>Temukan segala jenis kebutuhanmu dengan harga spesial hanya di <?= e(APP_NAME) ?>.</p>
        </div>
    </div>
    <?php if (!$promoProducts): ?>
        <div class="card panel">
            <p class="muted">Saat ini belum ada produk yang sedang promo.</p>
        </div>
    <?php else: ?>
        <div class="promo-grid">
            <?php foreach ($promoProducts as $product): ?>
                <?php $displayPrice = effective_price($product); ?>
                <article class="promo-card product-card">
                    <a class="product-link" href="<?= url('product.php?id=' . (int) $product['id']) ?>">
                        <div class="product-media">
                            <img src="<?= asset($product['image']) ?>" alt="<?= e($product['name']) ?>">
                        </div>
                    </a>
                    <div class="product-body">
                        <div class="product-meta">
                            <span class="tag tag-accent">Promo <?= (int) $product['promo_percent'] ?>%</span>
                            <span class="tag tag-primary"><?= e($product['category_name']) ?></span>
                        </div>
                        <h3><a href="<?= url('product.php?id=' . (int) $product['id']) ?>"><?= e($product['name']) ?></a></h3>
                        <p class="muted product-desc"><?= e($product['description']) ?></p>
                        <div class="price">
                            <del><?= format_currency($product['price']) ?></del>
                            <?= format_currency($displayPrice) ?>
                        </div>
                        <div class="product-meta">
                            <span class="tag <?= (int) $product['stock'] > 0 ? 'tag-success' : 'tag-danger' ?>">
                                Stok <?= (int) $product['stock'] ?>
                            </span>
                        </div>
                        <a class="btn btn-soft" href="<?= url('product.php?id=' . (int) $product['id']) ?>">Buka Produk</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section" id="produk">
    <div class="section-head">
        <div>
            <h2>Etalase barang</h2>
            <p>Temukan berbagai produk berkualitas dengan harga terbaik.</p>
        </div>
        <div class="pillbar">
            <span class="pill"><strong><?= count($products) ?></strong> barang ditemukan</span>
            <span class="pill"><strong><?= low_stock_threshold() ?></strong> threshold stok habis</span>
        </div>
    </div>
    <?php if (!$products): ?>
        <div class="card panel">
            <p class="muted">Tidak ada barang yang tersedia saat ini.</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
            <?php $displayPrice = effective_price($product); ?>
            <article class="product-card">
                <a class="product-link" href="<?= url('product.php?id=' . (int) $product['id']) ?>">
                    <div class="product-media">
                        <img src="<?= asset($product['image']) ?>" alt="<?= e($product['name']) ?>">
                    </div>
                </a>
                <div class="product-body">
                    <div class="product-meta">
                        <span class="tag tag-primary"><?= e($product['category_name']) ?></span>
                        <?php if ((int) $product['is_promo'] === 1): ?>
                            <span class="tag tag-accent">Promo <?= (int) $product['promo_percent'] ?>%</span>
                        <?php endif; ?>
                    </div>
                    <h3><a href="<?= url('product.php?id=' . (int) $product['id']) ?>"><?= e($product['name']) ?></a></h3>
                    <p class="muted product-desc"><?= e($product['description']) ?></p>
                    <div class="price">
                        <?php if ($displayPrice < (float) $product['price']): ?>
                            <del><?= format_currency($product['price']) ?></del>
                        <?php endif; ?>
                        <?= format_currency($displayPrice) ?>
                    </div>
                    <div class="product-meta">
                        <span class="tag <?= (int) $product['stock'] > 0 ? 'tag-success' : 'tag-danger' ?>">
                            Stok <?= (int) $product['stock'] ?>
                        </span>
                        <span class="tag"><?= e($product['category_name']) ?></span>
                    </div>
                    <div class="product-actions">
                        <a class="btn btn-primary" href="<?= url('product.php?id=' . (int) $product['id']) ?>">Buka Produk</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>