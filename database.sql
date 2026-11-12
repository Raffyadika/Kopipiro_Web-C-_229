-- Kopipiro — skema database + data contoh
-- Import lewat phpMyAdmin (tab Import) atau: mysql -u root < database.sql
-- PERHATIAN: file ini menghapus dan membuat ulang semua tabel.

CREATE DATABASE IF NOT EXISTS `db_kopipiro` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_kopipiro`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `reviews`, `order_items`, `orders`, `products`, `categories`, `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Akun: owner (pemilik), cashier (kasir), customer (pembeli terdaftar)
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','cashier','customer') NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` int(10) unsigned NOT NULL COMMENT 'Harga normal (Rupiah)',
  `image` varchar(255) DEFAULT NULL COMMENT 'Nama file di uploads/products/',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- user_id NULL = pembeli tamu (tanpa login, tanpa diskon)
-- cashier_id = kasir yang mengelola pesanan (ditentukan otomatis saat checkout)
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(20) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `cashier_id` int(10) unsigned DEFAULT NULL,
  `subtotal` int(10) unsigned NOT NULL,
  `discount_percent` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `discount_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `total` int(10) unsigned NOT NULL,
  `status` enum('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_code` (`order_code`),
  KEY `idx_orders_user` (`user_id`),
  KEY `idx_orders_cashier_status` (`cashier_id`,`status`),
  KEY `idx_orders_created` (`created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- product_name & price disalin saat checkout supaya riwayat tetap benar walau produk diubah/dihapus
CREATE TABLE `order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `price` int(10) unsigned NOT NULL COMMENT 'Harga normal saat dipesan',
  `qty` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_items_order` (`order_id`),
  KEY `idx_items_product` (`product_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Satu customer hanya punya satu rating per produk
CREATE TABLE `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_user_product` (`product_id`,`user_id`),
  KEY `idx_reviews_user` (`user_id`),
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================== DATA CONTOH =====================
-- Password demo: owner/owner123, kasir1 & kasir2/kasir123, budi/budi123, sari/sari123
-- GANTI semuanya sebelum dipakai sungguhan.

INSERT INTO `users` (`id`,`name`,`username`,`password`,`role`) VALUES
(1, 'Pemilik Kopipiro', 'owner', '$2y$10$80vANUFVgaJw6.q0oqJkwu0EsLAIx5OrFg3t.97U.qjgZL9I3N19u', 'owner'),
(2, 'Kasir Satu', 'kasir1', '$2y$10$l00RI6JWAQQlr96O.Np3KOVJb.1oEh7Oml5d2D4y/lP2qsS8rLRfu', 'cashier'),
(3, 'Kasir Dua', 'kasir2', '$2y$10$q3wKCcI4wATDtHAhsIrl8uo2EnV/RuCtuGnx5pBDIzXC8Aag7gcv.', 'cashier'),
(4, 'Budi Santoso', 'budi', '$2y$10$kqvXvMQj7gE.G5tmu2N5XOGubnAxCg9tabvStHGpv8nzbafFBINCm', 'customer'),
(5, 'Sari Wulandari', 'sari', '$2y$10$HgZZyW33c6I8DBYbyXDFSuH8cXQgARLrbaojThWwyDRcI93FQdaMG', 'customer');

INSERT INTO `categories` (`id`,`name`) VALUES
(1, 'Kopi'),
(2, 'Non-Kopi (Tanpa Kafein)'),
(3, 'Toast & Snack');

INSERT INTO `products` (`id`,`category_id`,`name`,`description`,`price`) VALUES
(1, 1, 'Espresso', 'Single shot espresso pekat dengan crema tebal. Untuk yang suka kopi murni.', 18000),
(2, 1, 'Americano', 'Espresso dengan air panas, ringan dan bersih di lidah.', 22000),
(3, 1, 'Kopi Susu Gula Aren', 'Signature Kopipiro: espresso, susu segar, dan gula aren asli. Manis dan wangi.', 25000),
(4, 1, 'Cappuccino', 'Espresso, susu steam, dan foam tebal dengan taburan kakao.', 28000),
(5, 1, 'Caffe Latte', 'Espresso lembut dengan susu steam yang creamy.', 28000),
(6, 2, 'Coklat Dingin', 'Coklat Belgia dengan susu dingin. Tanpa kafein (kadar sangat rendah).', 25000),
(7, 2, 'Lemon Squash', 'Perasan lemon segar, soda, dan sedikit mint. Menyegarkan.', 20000),
(8, 2, 'Strawberry Yogurt', 'Yogurt segar dengan stroberi blend. Tanpa kafein.', 27000),
(9, 3, 'Cheese Toast', 'Roti tebal panggang dengan keju leleh dan mentega.', 22000),
(10, 3, 'Choco Banana Toast', 'Roti panggang dengan selai coklat dan irisan pisang.', 24000),
(11, 3, 'Butter Croissant', 'Croissant berlapis, renyah di luar dan lembut di dalam.', 20000);

INSERT INTO `orders` (`id`,`order_code`,`user_id`,`customer_name`,`cashier_id`,`subtotal`,`discount_percent`,`discount_amount`,`total`,`status`,`created_at`) VALUES
(1, 'KPDEMO-001', NULL, 'Rina', 2, 40000, 0, 0, 40000, 'completed', DATE_SUB(CURDATE(), INTERVAL 13 DAY) + INTERVAL 9 HOUR),
(2, 'KPDEMO-002', 4, 'Budi Santoso', 3, 74000, 10, 7400, 66600, 'completed', DATE_SUB(CURDATE(), INTERVAL 12 DAY) + INTERVAL 14 HOUR),
(3, 'KPDEMO-003', NULL, 'Dimas', 2, 62000, 0, 0, 62000, 'completed', DATE_SUB(CURDATE(), INTERVAL 11 DAY) + INTERVAL 10 HOUR),
(4, 'KPDEMO-004', 5, 'Sari Wulandari', 3, 55000, 10, 5500, 49500, 'completed', DATE_SUB(CURDATE(), INTERVAL 10 DAY) + INTERVAL 16 HOUR),
(5, 'KPDEMO-005', NULL, 'Agus', 2, 56000, 0, 0, 56000, 'completed', DATE_SUB(CURDATE(), INTERVAL 9 DAY) + INTERVAL 8 HOUR),
(6, 'KPDEMO-006', 4, 'Budi Santoso', 2, 67000, 10, 6700, 60300, 'completed', DATE_SUB(CURDATE(), INTERVAL 8 DAY) + INTERVAL 13 HOUR),
(7, 'KPDEMO-007', NULL, 'Lia', 3, 98000, 0, 0, 98000, 'completed', DATE_SUB(CURDATE(), INTERVAL 7 DAY) + INTERVAL 19 HOUR),
(8, 'KPDEMO-008', NULL, 'Hendra', 2, 58000, 0, 0, 58000, 'completed', DATE_SUB(CURDATE(), INTERVAL 6 DAY) + INTERVAL 11 HOUR),
(9, 'KPDEMO-009', 5, 'Sari Wulandari', 3, 45000, 10, 4500, 40500, 'completed', DATE_SUB(CURDATE(), INTERVAL 5 DAY) + INTERVAL 15 HOUR),
(10, 'KPDEMO-010', NULL, 'Tono', 3, 28000, 0, 0, 28000, 'cancelled', DATE_SUB(CURDATE(), INTERVAL 4 DAY) + INTERVAL 9 HOUR),
(11, 'KPDEMO-011', 4, 'Budi Santoso', 2, 100000, 10, 10000, 90000, 'completed', DATE_SUB(CURDATE(), INTERVAL 3 DAY) + INTERVAL 12 HOUR),
(12, 'KPDEMO-012', NULL, 'Maya', 3, 102000, 0, 0, 102000, 'completed', DATE_SUB(CURDATE(), INTERVAL 2 DAY) + INTERVAL 17 HOUR),
(13, 'KPDEMO-013', 5, 'Sari Wulandari', 2, 46000, 10, 4600, 41400, 'completed', DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 10 HOUR),
(14, 'KPDEMO-014', NULL, 'Eko', 3, 50000, 0, 0, 50000, 'completed', DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 18 HOUR),
(15, 'KPDEMO-015', NULL, 'Fitri', 2, 50000, 0, 0, 50000, 'processing', DATE_SUB(CURDATE(), INTERVAL 0 DAY) + INTERVAL 8 HOUR),
(16, 'KPDEMO-016', 4, 'Budi Santoso', 3, 70000, 10, 7000, 63000, 'pending', DATE_SUB(CURDATE(), INTERVAL 0 DAY) + INTERVAL 9 HOUR);

INSERT INTO `order_items` (`id`,`order_id`,`product_id`,`product_name`,`price`,`qty`) VALUES
(1, 1, 1, 'Espresso', 18000, 1),
(2, 1, 9, 'Cheese Toast', 22000, 1),
(3, 2, 3, 'Kopi Susu Gula Aren', 25000, 2),
(4, 2, 10, 'Choco Banana Toast', 24000, 1),
(5, 3, 2, 'Americano', 22000, 1),
(6, 3, 11, 'Butter Croissant', 20000, 2),
(7, 4, 5, 'Caffe Latte', 28000, 1),
(8, 4, 8, 'Strawberry Yogurt', 27000, 1),
(9, 5, 4, 'Cappuccino', 28000, 2),
(10, 6, 3, 'Kopi Susu Gula Aren', 25000, 1),
(11, 6, 9, 'Cheese Toast', 22000, 1),
(12, 6, 7, 'Lemon Squash', 20000, 1),
(13, 7, 6, 'Coklat Dingin', 25000, 2),
(14, 7, 10, 'Choco Banana Toast', 24000, 2),
(15, 8, 1, 'Espresso', 18000, 2),
(16, 8, 2, 'Americano', 22000, 1),
(17, 9, 3, 'Kopi Susu Gula Aren', 25000, 1),
(18, 9, 11, 'Butter Croissant', 20000, 1),
(19, 10, 4, 'Cappuccino', 28000, 1),
(20, 11, 5, 'Caffe Latte', 28000, 2),
(21, 11, 9, 'Cheese Toast', 22000, 2),
(22, 12, 3, 'Kopi Susu Gula Aren', 25000, 3),
(23, 12, 8, 'Strawberry Yogurt', 27000, 1),
(24, 13, 2, 'Americano', 22000, 1),
(25, 13, 10, 'Choco Banana Toast', 24000, 1),
(26, 14, 3, 'Kopi Susu Gula Aren', 25000, 1),
(27, 14, 6, 'Coklat Dingin', 25000, 1),
(28, 15, 5, 'Caffe Latte', 28000, 1),
(29, 15, 9, 'Cheese Toast', 22000, 1),
(30, 16, 3, 'Kopi Susu Gula Aren', 25000, 2),
(31, 16, 11, 'Butter Croissant', 20000, 1);

INSERT INTO `reviews` (`product_id`,`user_id`,`rating`,`comment`) VALUES
(3, 4, 5, 'Manis dan wangi, favorit saya!'),
(3, 5, 5, 'Gula arennya kerasa banget.'),
(4, 4, 4, 'Foam-nya tebal, enak.'),
(5, 5, 4, 'Latte yang lembut.'),
(9, 4, 5, 'Kejunya melimpah.'),
(2, 5, 3, 'Agak pahit buat saya.'),
(10, 5, 4, 'Cocok buat teman ngopi.'),
(8, 4, 5, 'Segar banget!');
