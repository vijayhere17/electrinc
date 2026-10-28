-- ============================================================
-- Franchise — 36-month cashback engine + showroom commission
-- Additive. Existing tables untouched.
-- ============================================================

INSERT INTO `franchise_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('commission_on_sale_enabled', '1', 'Credit Showroom Sponsor + Turnover Income when a franchise sells a product (1/0)')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- planned monthly cashback rows (one per franchise per month of the model period) ---
CREATE TABLE IF NOT EXISTS `franchise_cashback_schedule` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_id`  INT           NOT NULL,
  `franchise_code` VARCHAR(50)  DEFAULT NULL,
  `model_id`      INT UNSIGNED  DEFAULT NULL,
  `month_no`      INT           NOT NULL,
  `due_date`      DATE          NOT NULL,
  `amount`        DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `status`        VARCHAR(20)   NOT NULL DEFAULT 'Pending',  -- Pending | Paid | Cancelled
  `paid_at`       DATETIME      DEFAULT NULL,
  `ref`           VARCHAR(80)   DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_month` (`franchise_id`, `model_id`, `month_no`),
  KEY `idx_fr_status` (`franchise_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- actual cashback credits (dashboard reads this as "Cashback Received") ---
CREATE TABLE IF NOT EXISTS `franchise_cashback_ledger` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_id`  INT           NOT NULL,
  `franchise_code` VARCHAR(50)  DEFAULT NULL,
  `schedule_id`   BIGINT UNSIGNED DEFAULT NULL,
  `month_no`      INT           DEFAULT NULL,
  `amount`        DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `note`          VARCHAR(200)  DEFAULT NULL,
  `performed_by`  VARCHAR(120)  DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fr` (`franchise_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- capture the actual sale value on a unit (commission base) ---
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fr_units' AND COLUMN_NAME='sale_amount');
SET @s := IF(@c=0, 'ALTER TABLE `fr_units` ADD COLUMN `sale_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `sale_ref`', 'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

-- register admin page
INSERT INTO `admin_panel_pages` (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES ('franchise-rules', 'franchise-rules.php', 'Franchise Rules & Models', 'other', 'sliders-horizontal', 800, 1)
ON DUPLICATE KEY UPDATE `file_name`=VALUES(`file_name`), `page_title`=VALUES(`page_title`), `is_active`=1;
