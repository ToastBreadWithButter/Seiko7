CREATE DATABASE IF NOT EXISTS penjualan_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE penjualan_online;

SET FOREIGN_KEY_CHECKS = 0;
DROP TRIGGER IF EXISTS trg_order_items_before_insert;
DROP TRIGGER IF EXISTS trg_order_items_after_insert;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    price DECIMAL(14,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    image VARCHAR(255) NOT NULL,
    is_promo TINYINT(1) NOT NULL DEFAULT 0,
    promo_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    customer_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address TEXT NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'shipped', 'completed', 'refund_requested', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    cancellation_reason TEXT NULL,
    refund_reason TEXT NULL,
    shipped_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    refunded_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(150) NOT NULL,
    qty INT NOT NULL,
    price DECIMAL(14,2) NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

DELIMITER //
CREATE TRIGGER trg_order_items_before_insert
BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
    DECLARE v_stock INT;
    DECLARE v_price DECIMAL(14,2);
    DECLARE v_name VARCHAR(150);
    DECLARE v_is_promo TINYINT(1);
    DECLARE v_promo_percent TINYINT UNSIGNED;

    IF NEW.qty <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jumlah barang harus lebih dari 0';
    END IF;

    SELECT stock, price, name, is_promo, promo_percent
      INTO v_stock, v_price, v_name, v_is_promo, v_promo_percent
    FROM products
    WHERE id = NEW.product_id;

    IF v_stock < NEW.qty THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok barang tidak mencukupi';
    END IF;

    IF v_is_promo = 1 AND v_promo_percent > 0 THEN
        SET v_price = ROUND(v_price * (100 - v_promo_percent) / 100, 0);
    END IF;

    SET NEW.product_name = v_name;
    SET NEW.price = v_price;
    SET NEW.subtotal = ROUND(v_price * NEW.qty, 0);
END//

CREATE TRIGGER trg_order_items_after_insert
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE products
    SET stock = stock - NEW.qty
    WHERE id = NEW.product_id;
END//
DELIMITER ;

INSERT INTO categories (name, slug) VALUES
('Elektronik', 'elektronik'),
('Makanan & Minuman', 'makanan-minuman'),
('Peralatan & perkakas', 'peralatan-perkakas'),
('Mainan', 'mainan'),
('Alat Rumah tangga', 'alat-rumah-tangga'),
('Pakaian', 'pakaian');

INSERT INTO settings (setting_key, setting_value) VALUES
('low_stock_threshold', '10');

INSERT INTO users (name, username, password, role) VALUES
('Administrator', 'admin', 'admin123', 'admin');

INSERT INTO products (category_id, name, slug, description, price, stock, image, is_promo, promo_percent) VALUES
(1, 'Smart TV 43 Inch', 'smart-tv-43-inch', 'TV LED hemat energi dengan tampilan tajam dan konektivitas lengkap.', 3899000, 18, 'assets/images/electronics.svg', 1, 15),
(1, 'Wireless Headset', 'wireless-headset', 'Headset nyaman dengan suara jernih untuk kerja dan hiburan.', 499000, 25, 'assets/images/electronics.svg', 0, 0),
(2, 'Kopi Arabika Premium', 'kopi-arabika-premium', 'Kopi pilihan dengan aroma kuat dan rasa seimbang.', 89000, 34, 'assets/images/food.svg', 1, 10),
(2, 'Snack Box Hemat', 'snack-box-hemat', 'Paket camilan praktis untuk keluarga dan acara kantor.', 65000, 42, 'assets/images/food.svg', 0, 0),
(3, 'Bor Listrik Serbaguna', 'bor-listrik-serbaguna', 'Bor ringkas untuk kebutuhan rumah dan proyek ringan.', 549000, 12, 'assets/images/tools.svg', 0, 0),
(3, 'Obeng Set 31 in 1', 'obeng-set-31-in-1', 'Set obeng multifungsi untuk perbaikan harian.', 129000, 19, 'assets/images/tools.svg', 1, 20),
(4, 'Robot Mainan Edukasi', 'robot-mainan-edukasi', 'Mainan interaktif yang membantu perkembangan logika anak.', 279000, 9, 'assets/images/toys.svg', 1, 12),
(4, 'Balok Susun Kreatif', 'balok-susun-kreatif', 'Balok warna-warni untuk belajar dan bermain.', 159000, 21, 'assets/images/toys.svg', 0, 0),
(5, 'Set Wajan Anti Lengket', 'set-wajan-anti-lengket', 'Peralatan masak praktis untuk dapur modern.', 299000, 14, 'assets/images/home.svg', 0, 0),
(5, 'Vacuum Cleaner Mini', 'vacuum-cleaner-mini', 'Membersihkan debu dengan desain ringkas dan kuat.', 459000, 8, 'assets/images/home.svg', 1, 18),
(6, 'Kaos Polos Cotton', 'kaos-polos-cotton', 'Bahan adem, nyaman, dan cocok untuk harian.', 99000, 50, 'assets/images/clothes.svg', 0, 0),
(6, 'Jaket Hoodie Casual', 'jaket-hoodie-casual', 'Hoodie hangat dengan gaya santai dan modern.', 249000, 16, 'assets/images/clothes.svg', 1, 15);
