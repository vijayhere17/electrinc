-- ============================================================
-- Income Plan #2 — Binary Income  + shared per-cycle capping
--
-- Binary Income:
--   Needs >= 1 ACTIVE directly-sponsored member on Left and
--   >= 1 on Right. Each Left+Right pairing = 1 Jodi.
--   Per Jodi = binary_percent% of the Left member's booking
--   amount + binary_percent% of the Right member's booking
--   amount (default 2.5% + 2.5%). Pays as the Jodi forms.
--   Only the member's OWN directs form Jodis.
--
-- Capping (shared, per withdrawal cycle 5/15/25):
--   ceiling = min( floor(active_directs / 2) * cap_per_2_directs, cap_max )
--   Applies to Binary Income and later team incomes.
--   Direct Sponsor Income (#1) is EXEMPT and does not consume room.
--   Income above the remaining ceiling in a cycle is flushed (lost).
-- ============================================================

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('binary_percent',     '2.50',     'Binary Income % applied to EACH side booking amount per Jodi'),
  ('cap_per_2_directs',  '10000.00', 'Capping unlocked per 2 active direct sponsors'),
  ('cap_max',            '50000.00', 'Maximum income capping per withdrawal cycle')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- unified income ledger (every capped credit passes through) ---
CREATE TABLE IF NOT EXISTS `member_income_ledger` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`         VARCHAR(50)  NOT NULL,
  `income_type`         VARCHAR(40)  NOT NULL,          -- 'Direct Sponsor', 'Binary', ...
  `source`              VARCHAR(40)  DEFAULT NULL,      -- 'Activation', 'Pair', ...
  `source_ref`          VARCHAR(120) DEFAULT NULL,
  `report_date`         DATE         DEFAULT NULL,
  `cycle_key`           VARCHAR(10)  DEFAULT NULL,      -- withdrawal-cycle end date Y-m-d
  `counts_to_cap`       TINYINT(1)   NOT NULL DEFAULT 1,
  `gross_amount`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cap_ceiling`         DECIMAL(12,2) DEFAULT NULL,
  `cap_used_before`     DECIMAL(12,2) DEFAULT NULL,
  `paid_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `flushed_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `fund_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `withdrawable_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `wallet_balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`              VARCHAR(20)  NOT NULL DEFAULT 'Credit',
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member_cycle` (`customer_id`, `cycle_key`),
  KEY `idx_type` (`income_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- binary income detail (one row per Jodi) ---
CREATE TABLE IF NOT EXISTS `binary_income` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`         VARCHAR(50)  NOT NULL,          -- earns the Jodi
  `left_member_id`      VARCHAR(50)  NOT NULL,
  `right_member_id`     VARCHAR(50)  NOT NULL,
  `pair_no`             INT          NOT NULL DEFAULT 0,
  `report_date`         DATE         DEFAULT NULL,
  `left_base`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `right_base`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `percentage`          DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `gross_amount`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `flushed_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `fund_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `withdrawable_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`              VARCHAR(20)  NOT NULL DEFAULT 'Credit',
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_jodi` (`customer_id`, `left_member_id`, `right_member_id`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- register the admin page ---
INSERT INTO `admin_panel_pages`
  (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES
  ('binary-income', 'binary-income.php', 'Binary Income', 'accounts', 'git-fork', 48, 1)
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`), `page_title` = VALUES(`page_title`),
  `section_key` = VALUES(`section_key`), `icon` = VALUES(`icon`), `is_active` = 1;
