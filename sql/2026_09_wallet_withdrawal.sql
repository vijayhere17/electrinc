-- ============================================================
-- (A) Monthly bonus auto-distribution day
-- (B) Wallet System + Withdrawal Rules
--
--  P2P wallet transfer  -> wallet_p2p_deduction_percent (15%) deducted
--  Wallet -> Bank        -> wallet_bank_deduction_percent (20%) deducted
--  These are transaction deductions, NOT income categories.
--
--  Withdrawal days: withdrawal_days (5,15,25 - shared setting)
--  Max per withdrawal: 1st = withdrawal_max_1 (500),
--                      2nd = withdrawal_max_2 (500),
--                      3rd onwards = withdrawal_max_3plus (2000)
--  All admin-configurable via income_settings.
-- ============================================================

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  -- (A)
  ('monthly_distribute_day',        '1',      'Day of month the monthly bonuses (Performance / Team Performance) auto-distribute'),
  ('monthly_distribute_last_run',   '',       'Internal: last period the monthly auto-distribution ran for'),
  -- (B) wallet
  ('wallet_p2p_enabled',            '1',      'Member-to-member (P2P) wallet transfer enabled (1/0)'),
  ('wallet_p2p_deduction_percent',  '15.00',  'Deduction % on a P2P wallet transfer'),
  ('wallet_p2p_min',                '1.00',   'Minimum P2P transfer amount'),
  ('wallet_bank_deduction_percent', '20.00',  'Deduction % on a Wallet -> Bank withdrawal'),
  -- (B) withdrawal rules
  ('withdrawal_enabled',            '1',      'Wallet -> Bank withdrawals enabled (1/0)'),
  ('withdrawal_enforce_days',       '1',      'Withdrawals only allowed on withdrawal_days (1/0)'),
  ('withdrawal_min',               '1.00',    'Minimum withdrawal request amount (gross)'),
  ('withdrawal_max_1',             '500.00',  'Max gross amount for a member''s 1st withdrawal'),
  ('withdrawal_max_2',             '500.00',  'Max gross amount for a member''s 2nd withdrawal'),
  ('withdrawal_max_3plus',         '2000.00', 'Max gross amount from the 3rd withdrawal onwards')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- unified non-income wallet ledger ---
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`    VARCHAR(50)   NOT NULL,
  `direction`      ENUM('credit','debit') NOT NULL,
  `category`       VARCHAR(30)   NOT NULL,   -- p2p_send | p2p_receive | withdrawal_hold | withdrawal_refund | adjustment
  `amount`         DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `balance_after`  DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `counterparty_id` VARCHAR(50)  DEFAULT NULL,
  `ref_type`       VARCHAR(30)   DEFAULT NULL,
  `ref_id`         BIGINT        DEFAULT NULL,
  `note`           VARCHAR(255)  DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member` (`customer_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- P2P transfers ---
CREATE TABLE IF NOT EXISTS `p2p_transfers` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_customer_id` VARCHAR(50)   NOT NULL,
  `to_customer_id`   VARCHAR(50)   NOT NULL,
  `gross_amount`     DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `deduction_percent` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `net_amount`       DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `status`           VARCHAR(20)   NOT NULL DEFAULT 'Completed',
  `note`             VARCHAR(255)  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_from` (`from_customer_id`),
  KEY `idx_to` (`to_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Wallet -> Bank withdrawal requests ---
CREATE TABLE IF NOT EXISTS `withdrawal_requests` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`      VARCHAR(50)   NOT NULL,
  `request_no`       INT           NOT NULL DEFAULT 1,   -- member's Nth withdrawal
  `gross_amount`     DECIMAL(20,2) NOT NULL DEFAULT 0.00, -- debited from wallet
  `cap_applied`      DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `deduction_percent` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `net_amount`       DECIMAL(20,2) NOT NULL DEFAULT 0.00, -- payable to bank
  `method`           VARCHAR(20)   NOT NULL DEFAULT 'bank',
  `bank_snapshot`    VARCHAR(500)  DEFAULT NULL,
  `status`           VARCHAR(20)   NOT NULL DEFAULT 'Pending', -- Pending | Approved | Paid | Rejected
  `note`             VARCHAR(255)  DEFAULT NULL,
  `admin_note`       VARCHAR(255)  DEFAULT NULL,
  `requested_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at`     DATETIME      DEFAULT NULL,
  `processed_by`     VARCHAR(120)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_member` (`customer_id`, `id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_panel_pages`
  (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES
  ('withdrawal-requests', 'withdrawal-requests.php', 'Withdrawal Requests', 'accounts', 'banknote', 42, 1),
  ('wallet-transfers',    'wallet-transfers.php',    'P2P Wallet Transfers', 'accounts', 'arrow-left-right', 41, 1)
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`), `page_title` = VALUES(`page_title`),
  `section_key` = VALUES(`section_key`), `icon` = VALUES(`icon`), `is_active` = 1;
