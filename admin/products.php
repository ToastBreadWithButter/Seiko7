<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$pageTitle = 'Kelola Barang - ' . APP_NAME;
$showAdminNav = true;
$showTopbar = false;
$activePage = 'products';

$categories = db()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$search = trim($_GET['q'] ?? '');
$categoryFilter = (int) ($_GET['category'] ?? 0);
$promoFilter = $_GET['promo'] ?? '';
$stockFilter = $_GET['stock'] ?? '';
$editId = (int) ($_GET['edit'] ?? 0);

// --- Pengaturan upload gambar ---
define('UPLOAD_DIR', __DIR__ . '/../assets/images/'); // folder absolut untuk upload
define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_SIZE', 2 * 1024 * 1024); // 2 MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_threshold'])) {
        $threshold = max(1, (int) ($_POST['threshold'] ?? APP_DEFAULT_LOW_STOCK_THRESHOLD));
        set_setting('low_stock_threshold', (string) $threshold);
        flash('success', 'Threshold stok hampir habis diperbarui.');
        redirect('admin/products.php');
    }

    if (isset($_POST['save_product'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $stock = max(0, (int) ($_POST['stock'] ?? 0));
        $isPromo = isset($_POST['is_promo']) ? 1 : 0;
        $promoPercent = max(0, min(90, (int) ($_POST['promo_percent'] ?? 0)));
        $slugBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $slug = $slugBase . '-' . substr(md5($name . microtime(true)), 0, 6);

        // --- Proses upload gambar ---
        $imagePath = ''; // akan diisi dengan path yang akan disimpan ke DB

        if ($id > 0) {
            // Saat edit, ambil gambar lama sebagai fallback
            $stmt = db()->prepare('SELECT image FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $oldProduct = $stmt->fetch();
            $imagePath = $oldProduct['image'] ?? '';
        }

        // Apakah ada file gambar yang diunggah?
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // Validasi ekstensi
            if (!in_array($ext, ALLOWED_EXT)) {
                flash('error', 'Format gambar tidak didukung. Gunakan: ' . implode(', ', ALLOWED_EXT));
                redirect('admin/products.php' . ($id ? '?edit=' . $id : ''));
            }

            // Validasi ukuran
            if ($file['size'] > MAX_SIZE) {
                flash('error', 'Ukuran file maksimal 2 MB.');
                redirect('admin/products.php' . ($id ? '?edit=' . $id : ''));
            }

            // Generate nama unik
            $filename = uniqid('prod_') . '.' . $ext;
            $destination = UPLOAD_DIR . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                flash('error', 'Gagal mengunggah gambar. Periksa izin folder.');
                redirect('admin/products.php' . ($id ? '?edit=' . $id : ''));
            }

            // Simpan path relatif ke kolom image
            $imagePath = 'assets/images/' . $filename;

            // (Opsional) Hapus gambar lama jika sedang edit dan file baru berhasil diunggah
            if ($id > 0 && !empty($oldProduct['image']) && file_exists(UPLOAD_DIR . basename($oldProduct['image']))) {
                unlink(UPLOAD_DIR . basename($oldProduct['image']));
            }
        } elseif ($id === 0) {
            // Produk baru tanpa gambar tidak diizinkan
            flash('error', 'Gambar produk wajib diunggah.');
            redirect('admin/products.php');
        }

        // --- Validasi data lainnya ---
        if ($name === '' || $description === '' || $categoryId <= 0) {
            flash('error', 'Lengkapi data barang (nama, deskripsi, kategori).');
            redirect('admin/products.php' . ($id ? '?edit=' . $id : ''));
        }

        // --- Simpan ke database ---
        if ($id > 0) {
            $stmt = db()->prepare(
                'UPDATE products
                 SET category_id = ?, name = ?, slug = ?, description = ?, price = ?, stock = ?, image = ?, is_promo = ?, promo_percent = ?
                 WHERE id = ?'
            );
            $stmt->execute([$categoryId, $name, $slug, $description, $price, $stock, $imagePath, $isPromo, $promoPercent, $id]);
            flash('success', 'Barang berhasil diperbarui.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO products (category_id, name, slug, description, price, stock, image, is_promo, promo_percent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$categoryId, $name, $slug, $description, $price, $stock, $imagePath, $isPromo, $promoPercent]);
            flash('success', 'Barang berhasil ditambahkan.');
        }

        redirect('admin/products.php');
    }

    if (isset($_POST['delete_product'])) {
        $id = (int) $_POST['id'];
        // Hapus gambar fisik jika ada (opsional)
        $stmt = db()->prepare('SELECT image FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if ($product && !empty($product['image'])) {
            $filePath = UPLOAD_DIR . basename($product['image']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'Barang berhasil dihapus.');
        redirect('admin/products.php');
    }
}

$product = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$editId]);
    $product = $stmt->fetch();
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($categoryFilter > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryFilter;
}

if ($promoFilter === 'promo') {
    $where[] = 'p.is_promo = 1';
}

if ($promoFilter === 'normal') {
    $where[] = 'p.is_promo = 0';
}

if ($stockFilter === 'low') {
    $where[] = 'p.stock <= ?';
    $params[] = low_stock_threshold();
}

if ($stockFilter === 'ready') {
    $where[] = 'p.stock > ?';
    $params[] = low_stock_threshold();
}

$sql = "
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY p.updated_at DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

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
                <span class="tag tag-accent">Katalog & stok</span>
                <h1>Kelola barang</h1>
                <p>Tambah, edit, filter, dan pantau barang di halaman ini.</p>
            </div>
        </div>

        <div class="section-head">
            <div>
                <h2>Filter barang</h2>
                <p>Gunakan pencarian dan filter untuk mempercepat pengelolaan produk.</p>
            </div>
        </div>

        <div class="card panel">
            <form method="get" class="filter-grid">
                <div class="field">
                    <label for="q">Pencarian barang</label>
                    <input id="q" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau deskripsi">
                </div>
                <div class="field">
                    <label for="category">Kategori</label>
                    <select id="category" name="category">
                        <option value="0">Semua kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="promo">Status promo</label>
                    <select id="promo" name="promo">
                        <option value="">Semua status</option>
                        <option value="promo" <?= $promoFilter === 'promo' ? 'selected' : '' ?>>Sedang promo</option>
                        <option value="normal" <?= $promoFilter === 'normal' ? 'selected' : '' ?>>Non promo</option>
                    </select>
                </div>
                <div class="field">
                    <label for="stock">Stok</label>
                    <select id="stock" name="stock">
                        <option value="">Semua stok</option>
                        <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Hampir habis</option>
                        <option value="ready" <?= $stockFilter === 'ready' ? 'selected' : '' ?>>Stok aman</option>
                    </select>
                </div>
                <div class="field" style="display:flex;align-items:end;">
                    <button class="btn btn-soft" type="submit" style="width:100%">Cari</button>
                </div>
            </form>
        </div>

        <div class="section-head">
            <div>
                <h2>Form barang</h2>
                <p>Gunakan form ini untuk menambah atau memperbarui data produk.</p>
            </div>
        </div>

        <div class="dashboard-grid" id="form-barang">
            <div class="card panel">
                <div class="section-head">
                    <div>
                        <h3><?= $product ? 'Edit barang' : 'Tambah barang' ?></h3>
                        <p>Isi formulir di bawah untuk menambah atau memperbarui data produk.</p>
                    </div>
                </div>

                <form method="post" enctype="multipart/form-data" class="mini-grid">
                    <input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>">
                    <div class="field">
                        <label for="category_id">Kategori</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Pilih kategori</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= isset($product['category_id']) && (int) $product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="name">Nama barang</label>
                        <input id="name" name="name" value="<?= e($product['name'] ?? '') ?>" required>
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label for="description">Deskripsi singkat</label>
                        <textarea id="description" name="description" rows="4" required><?= e($product['description'] ?? '') ?></textarea>
                    </div>
                    <div class="field">
                        <label for="price">Harga</label>
                        <input id="price" type="number" name="price" min="0" step="1" value="<?= e((string) ($product['price'] ?? '0')) ?>" required>
                    </div>
                    <div class="field">
                        <label for="stock">Stok</label>
                        <input id="stock" type="number" name="stock" min="0" value="<?= e((string) ($product['stock'] ?? '0')) ?>" required>
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label for="image">Gambar produk</label>
                        <?php if ($product && !empty($product['image'])): ?>
                            <div style="margin-bottom: 8px;">
                                <img src="<?= e(url($product['image'])) ?>" alt="Preview" style="max-height: 100px; border-radius: 6px;">
                                <p class="muted">Gambar saat ini: <?= e(basename($product['image'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <input id="image" type="file" name="image" accept="image/*">
                        <?php if (!$product): ?>
                            <small class="muted">Wajib diisi untuk produk baru. Format: jpg, png, gif, webp (maks 2 MB).</small>
                        <?php else: ?>
                            <small class="muted">Kosongkan jika tidak ingin mengubah gambar. Format: jpg, png, gif, webp (maks 2 MB).</small>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="promo_percent">Promo persen</label>
                        <input id="promo_percent" type="number" name="promo_percent" min="0" max="90" value="<?= e((string) ($product['promo_percent'] ?? 0)) ?>">
                    </div>
                    <div class="field" style="display:flex;align-items:end;gap:10px;flex-wrap:wrap">
                        <label style="display:flex;align-items:center;gap:10px;margin:0;">
                            <input type="checkbox" name="is_promo" value="1" <?= !empty($product['is_promo']) ? 'checked' : '' ?>>
                            Jadikan promo
                        </label>
                    </div>
                    <div class="field" style="grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap">
                        <button class="btn btn-primary" type="submit" name="save_product" value="1">Simpan</button>
                        <?php if ($product): ?>
                            <a class="btn btn-soft" href="<?= url('admin/products.php') ?>">Batal edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card panel">
                <div class="section-head">
                    <div>
                        <h3>Pengaturan stok</h3>
                        <p>Atur threshold jumlah stok hampir habis disini</p>
                    </div>
                </div>

                <form method="post" class="mini-grid">
                    <div class="field">
                        <label for="threshold">Threshold stok hampir habis</label>
                        <input id="threshold" type="number" name="threshold" min="1" value="<?= low_stock_threshold() ?>">
                    </div>
                    <button class="btn btn-primary" type="submit" name="save_threshold" value="1">Terapkan</button>
                </form>

                <div class="summary-line">
                    <span>Total barang</span>
                    <strong><?= count($products) ?></strong>
                </div>
                <div class="summary-line">
                    <span>Threshold stok habis</span>
                    <strong><?= low_stock_threshold() ?></strong>
                </div>
                <p class="muted">
                </p>
            </div>
        </div>

        <div class="card table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama barang</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Promo</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['name']) ?></strong><br>
                                    <span class="muted"><?= e($row['description']) ?></span>
                                </td>
                                <td><?= e($row['category_name']) ?></td>
                                <td><?= format_currency($row['price']) ?></td>
                                <td><?= (int) $row['stock'] ?></td>
                                <td>
                                    <?php if ((int) $row['is_promo'] === 1): ?>
                                        <span class="tag tag-accent">Promo <?= (int) $row['promo_percent'] ?>%</span>
                                    <?php else: ?>
                                        <span class="tag">Non-promo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-soft" href="<?= url('admin/products.php?edit=' . (int) $row['id']) ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Hapus barang ini?');">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button class="btn btn-danger" type="submit" name="delete_product" value="1">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>