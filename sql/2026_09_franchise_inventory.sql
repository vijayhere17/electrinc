-- ============================================================
-- Franchise Panel — Daily Operations · Step 1
-- Product & Inventory Management foundation.
--
-- Additive only — existing tables (franchise_stock, franchies_*)
-- are NOT touched. Scalable custom fields via EAV so new products
-- can add identifiers without schema changes. No brand / chassis /
-- motor / battery format is hard-coded anywhere.
--
-- Tables (all prefixed fr_ , all scoped by franchise_id):
--   fr_products         product master (per franchise)
--   fr_product_fields   custom field DEFINITIONS per product
--   fr_product_values   custom field VALUES for non-serialized products
--   fr_units            individual units / vehicles (serialized products)
--   fr_unit_values      identifier / custom field VALUES per unit
--   fr_stock_entries    stock receipt records (company/state -> franchise)
--   fr_stock_movements  every stock movement (audit trail)
-- ============================================================

CREATE TABLE IF NOT EXISTS `fr_products` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_id`   INT           NOT NULL,
  `franchise_code` VARCHAR(50)   DEFAULT NULL,
  `name`           VARCHAR(200)  NOT NULL,
  `category`       VARCHAR(120)  DEFAULT NULL,
  `brand`          VARCHAR(120)  DEFAULT NULL,
  `model`          VARCHAR(120)  DEFAULT NULL,
  `price`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_serialized`  TINYINT(1)    NOT NULL DEFAULT 1,   -- 1 = track individual units + identifiers
  `status`         VARCHAR(20)   NOT NULL DEFAULT 'Active',
  -- counters used only for non-serialized products (serialized counts derive from fr_units)
  `qty_available`  INT NOT NULL DEFAULT 0,
  `qty_reserved`   INT NOT NULL DEFAULT 0,
  `qty_sold`       INT NOT NULL DEFAULT 0,
  `qty_returned`   INT NOT NULL DEFAULT 0,
  `qty_damaged`    INT NOT NULL DEFAULT 0,
  `created_by`     VARCHAR(120)  DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_franchise` (`franchise_id`),
  KEY `idx_name` (`franchise_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fr_product_fields` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `franchise_id`  INT           NOT NULL,
  `field_key`     VARCHAR(60)   NOT NULL,
  `field_label`   VARCHAR(120)  NOT NULL,
  `field_type`    VARCHAR(20)   NOT NULL DEFAULT 'text',   -- text | number | date
  `is_identifier` TINYINT(1)    NOT NULL DEFAULT 0,        -- searchable unique-ish id (chassis, motor, serial…)
  `sort_order`    INT           NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_field` (`product_id`, `field_key`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fr_product_values` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `field_key`   VARCHAR(60)   NOT NULL,
  `field_value` VARCHAR(255)  DEFAULT NULL,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pv` (`product_id`, `field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fr_units` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`     BIGINT UNSIGNED NOT NULL,
  `franchise_id`   INT           NOT NULL,
  `unit_no`        VARCHAR(60)   DEFAULT NULL,
  `status`         VARCHAR(20)   NOT NULL DEFAULT 'Available', -- Available|Reserved|Sold|Delivered|Returned|Damaged|Cancelled
  `stock_entry_id` BIGINT UNSIGNED DEFAULT NULL,
  `source_vendor`  VARCHAR(150)  DEFAULT NULL,
  `received_date`  DATE          DEFAULT NULL,
  `customer_id`    VARCHAR(60)   DEFAULT NULL,
  `customer_name`  VARCHAR(200)  DEFAULT NULL,
  `sale_ref`       VARCHAR(80)   DEFAULT NULL,
  `invoice_no`     VARCHAR(80)   DEFAULT NULL,
  `sale_date`      DATE          DEFAULT NULL,
  `delivery_date`  DATE          DEFAULT NULL,
  `remarks`        VARCHAR(255)  DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_fr_status` (`franchise_id`, `status`),
  KEY `idx_entry` (`stock_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fr_unit_values` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id`     BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `field_key`   VARCHAR(60)   NOT NULL,
  `field_value` VARCHAR(255)  DEFAULT NULL,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_uv` (`unit_id`, `field_key`),
  KEY `idx_search` (`product_id`, `field_key`, `field_value`(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fr_stock_entries` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_id`    INT           NOT NULL,
  `franchise_code`  VARCHAR(50)   DEFAULT NULL,
  `product_id`      BIGINT UNSIGNED NOT NULL,
  `quantity`        INT           NOT NULL DEFAULT 0,
  `received_date`   DATE          DEFAULT NULL,
  `reference_no`    VARCHAR(80)   DEFAULT NULL,
  `source`          VARCHAR(150)  DEFAULT NULL,     -- Company | State Franchise | vendor name
  `purchase_details` TEXT         DEFAULT NULL,
  `remarks`         TEXT          DEFAULT NULL,
  `performed_by`    VARCHAR(120)  DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fr_product` (`franchise_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fr_stock_movements` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_id`  INT           NOT NULL,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `unit_id`       BIGINT UNSIGNED DEFAULT NULL,
  `movement_type` VARCHAR(20)   NOT NULL,   -- Received|Sold|Delivered|Reserved|Unreserved|Returned|Reduced|Damaged|Transferred|Adjustment|Cancelled
  `qty_change`    INT           NOT NULL DEFAULT 0,  -- signed
  `balance_after` INT           DEFAULT NULL,        -- available balance snapshot (non-serialized)
  `reason`        VARCHAR(160)  DEFAULT NULL,
  `reference`     VARCHAR(120)  DEFAULT NULL,
  `performed_by`  VARCHAR(120)  DEFAULT NULL,
  `remarks`       VARCHAR(255)  DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fr_product` (`franchise_id`, `product_id`),
  KEY `idx_unit` (`unit_id`),
  KEY `idx_when` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
