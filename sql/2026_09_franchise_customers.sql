-- =====================================================================
-- Franchise Panel · Daily Operations Step 3 — Customer / Buyer Management
-- Additive only. All tables franchise-scoped. No commission/cashback here.
-- fr_customer_purchases is the PURCHASE-history foundation that POS /
-- invoice / payment / delivery / turnover will later hang off.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `fr_customers` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_id`    INT(11) NOT NULL,
    `franchise_code`  VARCHAR(50) NULL,
    `customer_code`   VARCHAR(40) NULL,
    `customer_name`   VARCHAR(200) NOT NULL,
    `mobile`          VARCHAR(30) NULL,
    `email`           VARCHAR(150) NULL,
    `dob`             DATE NULL,
    `address`         VARCHAR(400) NULL,
    `state`           VARCHAR(120) NULL,
    `city`            VARCHAR(120) NULL,
    `district`        VARCHAR(120) NULL,
    `pincode`         VARCHAR(20) NULL,
    `country`         VARCHAR(120) NULL DEFAULT 'India',
    `status`          VARCHAR(20) NOT NULL DEFAULT 'Active',
    `remarks`         TEXT NULL,
    `registered_date` DATE NULL,
    `created_by`      VARCHAR(120) NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fr`     (`franchise_id`, `status`),
    KEY `idx_mobile` (`franchise_id`, `mobile`),
    KEY `idx_code`   (`franchise_id`, `customer_code`),
    KEY `idx_name`   (`franchise_id`, `customer_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- custom field DEFINITIONS — franchise-wide, applied to every customer
CREATE TABLE IF NOT EXISTS `fr_customer_fields` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_id`  INT(11) NOT NULL,
    `field_key`     VARCHAR(60) NOT NULL,
    `field_label`   VARCHAR(120) NOT NULL,
    `field_type`    VARCHAR(20) NOT NULL DEFAULT 'text',
    `is_searchable` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`    INT(11) NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_cfield` (`franchise_id`, `field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fr_customer_values` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`  BIGINT UNSIGNED NOT NULL,
    `franchise_id` INT(11) NOT NULL,
    `field_key`    VARCHAR(60) NOT NULL,
    `field_value`  VARCHAR(500) DEFAULT NULL,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_cv` (`customer_id`, `field_key`),
    KEY `idx_search` (`franchise_id`, `field_key`, `field_value`(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fr_customer_history` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`  BIGINT UNSIGNED NOT NULL,
    `franchise_id` INT(11) NOT NULL,
    `action`       VARCHAR(40) NOT NULL,
    `detail`       VARCHAR(500) NULL,
    `performed_by` VARCHAR(120) NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer` (`customer_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- one row per purchase line (works for unit-tracked AND quantity-only products)
CREATE TABLE IF NOT EXISTS `fr_customer_purchases` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_id`    INT(11) NOT NULL,
    `customer_id`     BIGINT UNSIGNED NOT NULL,
    `product_id`      BIGINT UNSIGNED NULL,
    `unit_id`         BIGINT UNSIGNED NULL,
    `product_name`    VARCHAR(200) NULL,
    `model`           VARCHAR(120) NULL,
    `quantity`        INT(11) NOT NULL DEFAULT 1,
    `unit_price`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `sale_amount`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `invoice_no`      VARCHAR(80) NULL,
    `sale_ref`        VARCHAR(80) NULL,
    `sale_date`       DATE NULL,
    `payment_status`  VARCHAR(20) NOT NULL DEFAULT 'Paid',
    `delivery_status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
    `remarks`         VARCHAR(255) NULL,
    `created_by`      VARCHAR(120) NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fc`   (`franchise_id`, `customer_id`),
    KEY `idx_unit` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- link a sold unit to its customer record
ALTER TABLE `fr_units` ADD COLUMN IF NOT EXISTS `customer_ref` BIGINT UNSIGNED NULL AFTER `customer_name`;
