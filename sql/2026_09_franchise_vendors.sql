-- =====================================================================
-- Franchise Panel · Daily Operations Step 2 — Vendor / Supplier Management
-- Additive only. All tables franchise-scoped. No commission/cashback here.
-- =====================================================================

-- --- vendor master -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fr_vendors` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_id`   INT(11) NOT NULL,
    `franchise_code` VARCHAR(50) NULL,
    `vendor_name`    VARCHAR(200) NOT NULL,
    `company_name`   VARCHAR(200) NULL,
    `mobile`         VARCHAR(30) NULL,
    `email`          VARCHAR(150) NULL,
    `gst_no`         VARCHAR(50) NULL,
    `address`        VARCHAR(400) NULL,
    `state`          VARCHAR(120) NULL,
    `city`           VARCHAR(120) NULL,
    `district`       VARCHAR(120) NULL,
    `pincode`        VARCHAR(20) NULL,
    `country`        VARCHAR(120) NULL DEFAULT 'India',
    `status`         VARCHAR(20) NOT NULL DEFAULT 'Active',
    `remarks`        TEXT NULL,
    `created_by`     VARCHAR(120) NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fr` (`franchise_id`, `status`),
    KEY `idx_name` (`franchise_id`, `vendor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- custom field DEFINITIONS (franchise-wide, applied to every vendor) --
CREATE TABLE IF NOT EXISTS `fr_vendor_fields` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `franchise_id`  INT(11) NOT NULL,
    `field_key`     VARCHAR(60) NOT NULL,
    `field_label`   VARCHAR(120) NOT NULL,
    `field_type`    VARCHAR(20) NOT NULL DEFAULT 'text',
    `is_searchable` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`    INT(11) NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_vfield` (`franchise_id`, `field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- custom field VALUES (per vendor) ----------------------------------
CREATE TABLE IF NOT EXISTS `fr_vendor_values` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`    BIGINT UNSIGNED NOT NULL,
    `franchise_id` INT(11) NOT NULL,
    `field_key`    VARCHAR(60) NOT NULL,
    `field_value`  VARCHAR(500) DEFAULT NULL,
    `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_vv` (`vendor_id`, `field_key`),
    KEY `idx_search` (`franchise_id`, `field_key`, `field_value`(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- vendor audit / history ------------------------------------------
CREATE TABLE IF NOT EXISTS `fr_vendor_history` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`    BIGINT UNSIGNED NOT NULL,
    `franchise_id` INT(11) NOT NULL,
    `action`       VARCHAR(40) NOT NULL,
    `detail`       VARCHAR(500) NULL,
    `performed_by` VARCHAR(120) NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vendor` (`vendor_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- link stock receiving + units to a vendor -------------------------
ALTER TABLE `fr_stock_entries` ADD COLUMN IF NOT EXISTS `vendor_id` BIGINT UNSIGNED NULL AFTER `source`;
ALTER TABLE `fr_units`         ADD COLUMN IF NOT EXISTS `vendor_id` BIGINT UNSIGNED NULL AFTER `source_vendor`;
