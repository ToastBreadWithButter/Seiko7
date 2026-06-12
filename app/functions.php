<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = rtrim(dirname($script), '/');

    if ($base === '/') {
        return '';
    }

    foreach (['/admin', '/login'] as $suffix) {
        if (str_ends_with($base, $suffix)) {
            $base = substr($base, 0, -strlen($suffix));
            break;
        }
    }

    return $base === '/' ? '' : $base;
}

function url(string $path = ''): string
{
    $base = app_base_path();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . '/' . $path;
}

function asset(string $path): string
{
    if (preg_match('#^https?://#i', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    return url($path);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!empty($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }

    return null;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function format_currency(float|int|string $value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function get_setting(string $key, mixed $default = null): mixed
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    return $row ? $row['setting_value'] : $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function low_stock_threshold(): int
{
    return (int) get_setting('low_stock_threshold', (string) APP_DEFAULT_LOW_STOCK_THRESHOLD);
}

function effective_price(array $product): float
{
    $price = (float) $product['price'];
    $promoPercent = (int) ($product['promo_percent'] ?? 0);
    $isPromo = (int) ($product['is_promo'] ?? 0) === 1;

    if ($isPromo && $promoPercent > 0) {
        return round($price * (100 - $promoPercent) / 100, 0);
    }

    return $price;
}

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(array_map('intval', cart()));
}

function add_to_cart(int $productId, int $qty = 1): void
{
    $_SESSION['cart'] ??= [];
    $qty = max(1, $qty);
    $_SESSION['cart'][$productId] = (int) ($_SESSION['cart'][$productId] ?? 0) + $qty;
}

function update_cart_qty(int $productId, int $qty): void
{
    $_SESSION['cart'] ??= [];

    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
        return;
    }

    $_SESSION['cart'][$productId] = $qty;
}

function remove_from_cart(int $productId): void
{
    unset($_SESSION['cart'][$productId]);
}

function clear_cart(): void
{
    unset($_SESSION['cart']);
}

function get_cart_products(): array
{
    $items = cart();

    if (!$items) {
        return [];
    }

    $ids = array_keys($items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = db()->prepare(
        "SELECT p.*, c.name AS category_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.id IN ($placeholders)
         ORDER BY p.name ASC"
    );
    $stmt->execute($ids);

    $products = [];
    foreach ($stmt->fetchAll() as $product) {
        $product['cart_qty'] = (int) ($items[(int) $product['id']] ?? 0);
        $product['display_price'] = effective_price($product);
        $products[] = $product;
    }

    return $products;
}

function normalize_cart_against_stock(): array
{
    $products = get_cart_products();
    $normalized = [];

    foreach ($products as $product) {
        $stock = (int) $product['stock'];
        $qty = (int) $product['cart_qty'];

        if ($stock <= 0) {
            continue;
        }

        $normalized[(int) $product['id']] = min($qty, $stock);
    }

    $_SESSION['cart'] = $normalized;
    return $normalized;
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('login');
    }
}

function order_status_map(): array
{
    return [
        'pending' => ['label' => 'Menunggu diproses', 'class' => 'tag-primary'],
        'shipped' => ['label' => 'Sedang dikirim', 'class' => 'tag-accent'],
        'completed' => ['label' => 'Selesai', 'class' => 'tag-success'],
        'refund_requested' => ['label' => 'Refund diajukan', 'class' => 'tag-warning'],
        'refunded' => ['label' => 'Dana dikembalikan', 'class' => 'tag-muted'],
        'cancelled' => ['label' => 'Dibatalkan', 'class' => 'tag-danger'],
    ];
}

function order_status_label(string $status): string
{
    $map = order_status_map();
    return $map[$status]['label'] ?? ucfirst($status);
}

function order_status_class(string $status): string
{
    $map = order_status_map();
    return $map[$status]['class'] ?? 'tag-primary';
}

function register_recent_order(string $orderCode, string $phone): void
{
    $_SESSION['recent_order_codes'] ??= [];
    array_unshift($_SESSION['recent_order_codes'], $orderCode);
    $_SESSION['recent_order_codes'] = array_slice(array_unique($_SESSION['recent_order_codes']), 0, 5);
    $_SESSION['order_lookup_phone'] = $phone;
}

function recent_order_codes(): array
{
    return $_SESSION['recent_order_codes'] ?? [];
}

function restock_order_items(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
    $stmt->execute([$orderId]);

    $update = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    foreach ($stmt->fetchAll() as $item) {
        $update->execute([(int) $item['qty'], (int) $item['product_id']]);
    }
}

function can_cancel_order(array $order): bool
{
    return ($order['status'] ?? '') === 'pending';
}

function can_confirm_received(array $order): bool
{
    return ($order['status'] ?? '') === 'shipped';
}

function can_request_refund(array $order): bool
{
    return in_array(($order['status'] ?? ''), ['shipped', 'completed'], true);
}

function can_mark_shipped(array $order): bool
{
    return ($order['status'] ?? '') === 'pending';
}

function can_approve_refund(array $order): bool
{
    return ($order['status'] ?? '') === 'refund_requested';
}
