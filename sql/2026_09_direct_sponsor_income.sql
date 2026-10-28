-- ============================================================
-- Direct Sponsor Income  (Income Plan #1)
-- Rate: default 5% of member booking/activation amount, paid to
-- the direct sponsor (signup_users.referral_id) when the sponsored
-- member becomes Active. Sponsor must be Active to earn.
-- 50% of the income goes to the sponsor's product-collection fund
-- and 50% to withdrawable balance until product_purchased = 1.
-- Withdrawals allowed on 5th / 15th / 25th (handled by payout flow).
-- ============================================================

-- --- plan-wide, admin-editable key/value settings -----------
CREATE TABLE IF NOT EXISTS `income_settings` (
  `setting_key`   VARCHAR(60)  NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `description`   VARCHAR(255) DEFAULT NULL,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('direct_sponsor_percent',      '5.00',     'Direct Sponsor Income % of booking amount'),
  ('direct_sponsor_base_default', '10000.00', 'Fallback booking amount when none is recorded on activation / product'),
  ('product_fund_split_percent',  '50.00',    '% of every income routed to the member product-collection fund until product_purchased = 1'),
  ('withdrawal_days',             '5,15,25',  'Days of the month withdrawals are allowed')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- direct sponsor income ledger ---------------------------
CREATE TABLE IF NOT EXISTS `direct_sponsor_income` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sponsor_id`          VARCHAR(50)  NOT NULL,           -- earns the income
  `from_member_id`      VARCHAR(50)  NOT NULL,           -- the new direct who joined
  `from_member_name`    VARCHAR(255) DEFAULT NULL,
  `report_date`         DATE         DEFAULT NULL,
  `base_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `percentage`          DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `income_amount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `fund_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- 50% -> product fund
  `withdrawable_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- 50% -> wallet
  `credit`              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `debit`               DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- sponsor wallet_balance after credit
  `source`              VARCHAR(30)  NOT NULL DEFAULT 'Activation',
  `ref_id`              INT          DEFAULT NULL,            -- activation_requests.id when known
  `status`              VARCHAR(20)  NOT NULL DEFAULT 'Credit',
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_direct_pay` (`sponsor_id`, `from_member_id`, `source`),
  KEY `idx_sponsor` (`sponsor_id`),
  KEY `idx_from` (`from_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- member balance columns --------------------------------
-- product-collection fund balance (the 50% reserved portion)
SET @has_fund := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'signup_users' AND COLUMN_NAME = 'product_fund_balance');
SET @sql := IF(@has_fund = 0,
  'ALTER TABLE `signup_users` ADD COLUMN `product_fund_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `wallet_balance`',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- flag: member has completed their EV product purchase -> income becomes 100% withdrawable
SET @has_pp := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'signup_users' AND COLUMN_NAME = 'product_purchased');
SET @sql := IF(@has_pp = 0,
  'ALTER TABLE `signup_users` ADD COLUMN `product_purchased` TINYINT(1) NOT NULL DEFAULT 0 AFTER `product_fund_balance`',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- --- seed a default direct % on existing products that have none ---
UPDATE `products` SET `sponsor_percentage` = 5.00 WHERE `sponsor_percentage` = 0.00;

-- --- register the admin page ------------------------------------
INSERT INTO `admin_panel_pages`
  (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES
  ('direct-sponsor-income', 'direct-sponsor-income.php', 'Direct Sponsor Income', 'accounts', 'hand-coins', 49, 1)
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`), `page_title` = VALUES(`page_title`),
  `section_key` = VALUES(`section_key`), `icon` = VALUES(`icon`), `is_active` = 1;
