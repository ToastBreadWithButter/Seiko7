<?php
require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Pencarian - ' . APP_NAME;
$activePage = 'search';

$search = trim($_GET['q'] ?? '');
$category = (int) ($_GET['category'] ?? 0);
$sort = $_GET['sort'] ?? 'baru';

$categories = db()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($category > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $category;
}

$orderBy = 'p.created_at DESC';
if ($sort === 'termurah') {
    $orderBy = 'p.price ASC';
} elseif ($sort === 'termahal') {
    $orderBy = 'p.price DESC';
}

$sql = "
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY {$orderBy}
";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require __DIR__ . '/partials/header.php';
?>

<?php if ($message = flash('success')): ?>
    <div class="notice success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($message = flash('error')): ?>
    <div class="notice error"><?= e($message) ?></div>
<?php endif; ?>

<section class="section card filters storefront-filters">
    <div class="section-head">
        <div>
            <h2>Pencarian Barang</h2>
            <p>Cari produk yang Anda butuhkan dengan mudah.</p>
        </div>
    </div>
    <form method="get" action="pencarian.php" class="filter-grid">
        <div class="field">
            <label for="q">Pencarian barang</label>
            <input id="q" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau deskripsi barang">
        </div>
        <div class="field">
            <label for="category">Kategori</label>
            <select id="category" name="category">
                <option value="0">Semua kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= $category === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="sort">Urutkan</label>
            <select id="sort" name="sort">
                <option value="baru" <?= $sort === 'baru' ? 'selected' : '' ?>>Terbaru</option>
                <option value="termurah" <?= $sort === 'termurah' ? 'selected' : '' ?>>Harga termurah</option>
                <option value="termahal" <?= $sort === 'termahal' ? 'selected' : '' ?>>Harga termahal</option>
            </select>
        </div>
        <div class="field" style="display:flex;align-items:end;">
            <button class="btn btn-primary" type="submit" style="width:100%">Cari</button>
        </div>
    </form>
</section>

<section class="section" id="hasil">
    <div class="section-head">
        <div>
            <h2>Hasil Pencarian</h2>
            <p>Menampilkan produk berdasarkan filter yang dipilih.</p>
        </div>
        <div class="pillbar">
            <span class="pill"><strong><?= count($products) ?></strong> barang ditemukan</span>
        </div>
    </div>
    <?php if (!$products): ?>
        <div class="card panel">
            <p class="muted">Tidak ada barang yang cocok dengan pencarian atau filter saat ini.</p>
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