-- ============================================================
-- Схема БД: warehouse (MySQL 8, InnoDB, utf8mb4)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `warehouse`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `warehouse`;

-- Убираем в обратном порядке FK
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `operations`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ------------------------------------------------------------
-- Пользователи
-- ------------------------------------------------------------
CREATE TABLE `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `login`         VARCHAR(64)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name`     VARCHAR(255) NOT NULL,
    `role`          ENUM('admin','manager','worker') NOT NULL DEFAULT 'worker',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Категории
-- ------------------------------------------------------------
CREATE TABLE `categories` (
    `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(128) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Товары
-- ------------------------------------------------------------
CREATE TABLE `products` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku`         VARCHAR(64)  NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `category_id` INT UNSIGNED NULL,
    `unit`        VARCHAR(16)  NOT NULL DEFAULT 'шт',
    `quantity`    DECIMAL(12,3) NOT NULL DEFAULT 0,
    `min_stock`   DECIMAL(12,3) NOT NULL DEFAULT 0,
    `price`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_sku` (`sku`),
    KEY `ix_products_category` (`category_id`),
    KEY `ix_products_name` (`name`),
    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Операции
-- ------------------------------------------------------------
CREATE TABLE `operations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`       ENUM('in','out','writeoff') NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `qty`        DECIMAL(12,3) NOT NULL,
    `comment`    VARCHAR(255) NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_operations_product`  (`product_id`),
    KEY `ix_operations_user`     (`user_id`),
    KEY `ix_operations_created`  (`created_at`),
    KEY `ix_operations_type`     (`type`),
    CONSTRAINT `fk_operations_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_operations_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `ck_operations_qty` CHECK (`qty` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Аудит
-- ------------------------------------------------------------
CREATE TABLE `audit_log` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(32)  NOT NULL,
    `entity`     VARCHAR(32)  NOT NULL,
    `entity_id`  INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_audit_user`    (`user_id`),
    KEY `ix_audit_created` (`created_at`),
    KEY `ix_audit_entity`  (`entity`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;