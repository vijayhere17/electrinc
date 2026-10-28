-- ============================================================
-- Joining amount 50/50 split (applied ONLY at joining/activation)
--
--  ₹10,000 joining  ->  joining_own_fund_percent%  (50%, ₹5,000)
--                        credited to the member's OWN product fund
--                        (signup_users.product_fund_balance) — held /
--                        reserved for their EV purchase, not withdrawable.
--                    ->  the remainder (50%, ₹5,000) goes to the
--                        Income Distribution Pool, the company bucket
--                        that funds all income/bonus payouts.
--
--  This split does NOT apply to income payouts (that stays 100%
--  withdrawable — product_fund_split_percent = 0).
--  All values admin-editable in income_settings.
-- ============================================================

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('joining_split_enabled',    '1',     'Split the joining amount at activation (1/0)'),
  ('joining_own_fund_percent', '50.00', '% of the joining amount reserved in the member''s own product fund; the rest goes to the Income Distribution Pool')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- one row per member joining split ---
CREATE TABLE IF NOT EXISTS `joining_splits` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`      VARCHAR(50)   NOT NULL,
  `joining_amount`   DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `own_fund_percent` DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `own_fund_amount`  DECIMAL(20,2) NOT NULL DEFAULT 0.00,   -- to member product fund
  `income_pool_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00, -- to Income Distribution Pool
  `ref_id`           INT           DEFAULT NULL,            -- activation_requests.id
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_member` (`customer_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Income Distribution Pool ledger (credits from joinings, debits as incomes pay out) ---
CREATE TABLE IF NOT EXISTS `income_pool_ledger` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `direction`    ENUM('credit','debit') NOT NULL,
  `category`     VARCHAR(30)   NOT NULL,   -- joining | payout | adjustment
  `amount`       DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `customer_id`  VARCHAR(50)   DEFAULT NULL,
  `income_type`  VARCHAR(40)   DEFAULT NULL,
  `ref`          VARCHAR(60)   DEFAULT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat` (`category`),
  KEY `idx_when` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_panel_pages`
  (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES
  ('income-pool', 'income-pool.php', 'Income Distribution Pool', 'accounts', 'piggy-bank', 40, 1)
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`), `page_title` = VALUES(`page_title`),
  `section_key` = VALUES(`section_key`), `icon` = VALUES(`icon`), `is_active` = 1;
