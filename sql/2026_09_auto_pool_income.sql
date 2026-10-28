-- ============================================================
-- Income Plan #4 — Auto Pool Income
--
-- Pool 1 entry amount = ₹100 (auto, one per activated member, FIFO).
-- Per-user amount grows x(users_per_pool * split%) each level:
--   P1 100  P2 1,000  P3 10,000 ...  P10 1,00,00,00,00,000
-- When a pool's collected balance reaches users_per_pool * per_user:
--   * pool total  = users_per_pool * per_user
--   * head member (oldest waiting) is PAID  split% of pool total
--   * the other split% RE-POOLS as one entry in the next pool for
--     that same head member (they advance)
--   * head is removed from this pool; the queue continues
-- Cascades through `auto_pool_levels` (10). Level 10 has no next pool.
--
-- The ₹100 (and every per-user amount) is a real member deduction,
-- sourced per `auto_pool_fund_from` (booking | wallet | product_fund).
-- Auto Pool Income is cap-exempt by default. All values admin-editable.
-- ============================================================

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('auto_pool_enabled',              '1',        'Auto Pool Income active (1/0)'),
  ('auto_pool_levels',               '10',       'Number of pool levels'),
  ('auto_pool_users_per_pool',       '20',       'Members needed to complete one pool cycle'),
  ('auto_pool_entry_amount',         '100.00',   'Pool 1 per-user entry amount (real deduction)'),
  ('auto_pool_income_split_percent', '50.00',    '% of a completed pool total paid to the head member; remainder re-pools'),
  ('auto_pool_fund_from',            'booking',  'Where the entry amount is deducted: booking | wallet | product_fund'),
  ('auto_pool_cap_exempt',           '1',        'Auto Pool Income exempt from the withdrawal-cycle cap (1/0)')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- widen money columns that can hold pool-scale numbers -----
SET @s := (SELECT IF(
   (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_income_ledger' AND COLUMN_NAME='gross_amount') LIKE 'decimal(12,%',
   'ALTER TABLE `member_income_ledger`
      MODIFY `base_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `gross_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `cap_ceiling` DECIMAL(20,2) NULL,
      MODIFY `cap_used_before` DECIMAL(20,2) NULL,
      MODIFY `paid_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `flushed_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `fund_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `withdrawable_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `wallet_balance_after` DECIMAL(20,2) NOT NULL DEFAULT 0.00',
   'SELECT 1'));
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

SET @s := (SELECT IF(
   (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='signup_users' AND COLUMN_NAME='wallet_balance') LIKE 'decimal(12,%'
   OR (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='signup_users' AND COLUMN_NAME='wallet_balance') LIKE 'decimal(10,%',
   'ALTER TABLE `signup_users`
      MODIFY `wallet_balance` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `product_fund_balance` DECIMAL(20,2) NOT NULL DEFAULT 0.00',
   'SELECT 1'));
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

-- --- pool state: running balance + counters per level ---
CREATE TABLE IF NOT EXISTS `auto_pool_state` (
  `pool_level`        INT           NOT NULL,
  `per_user_amount`   DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `pool_total`        DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `balance`           DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `entries_count`     BIGINT        NOT NULL DEFAULT 0,
  `completions_count` BIGINT        NOT NULL DEFAULT 0,
  `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pool_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- one row per member per pool level (their queue position) ---
CREATE TABLE IF NOT EXISTS `auto_pool_positions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`    VARCHAR(50)   NOT NULL,
  `pool_level`     INT           NOT NULL,
  `queue_no`       BIGINT        NOT NULL DEFAULT 0,   -- FIFO order within the level
  `entry_amount`   DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `entry_source`   VARCHAR(20)   DEFAULT NULL,          -- booking | wallet | product_fund | repool
  `from_position`  BIGINT UNSIGNED DEFAULT NULL,        -- the lower-pool position that re-pooled into this
  `status`         VARCHAR(20)   NOT NULL DEFAULT 'waiting', -- waiting | paid
  `income_amount`  DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `repool_amount`  DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `joined_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at`        DATETIME      DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_member_level` (`customer_id`, `pool_level`),
  KEY `idx_level_status_queue` (`pool_level`, `status`, `queue_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- one row per pool completion (audit) ---
CREATE TABLE IF NOT EXISTS `auto_pool_completions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pool_level`       INT           NOT NULL,
  `head_customer_id` VARCHAR(50)   NOT NULL,
  `pool_total`       DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `income_amount`    DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `repool_amount`    DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `repool_to_level`  INT           DEFAULT NULL,
  `paid_amount`      DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `flushed_amount`   DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `report_date`      DATE          DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_level` (`pool_level`),
  KEY `idx_head` (`head_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- member-facing entry-fee debit log ---
CREATE TABLE IF NOT EXISTS `auto_pool_debits` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` VARCHAR(50)   NOT NULL,
  `pool_level`  INT           NOT NULL,
  `amount`      DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `source`      VARCHAR(20)   DEFAULT NULL,
  `note`        VARCHAR(255)  DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_panel_pages`
  (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES
  ('auto-pool-income', 'auto-pool-income.php', 'Auto Pool Income', 'accounts', 'layers', 45, 1)
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`), `page_title` = VALUES(`page_title`),
  `section_key` = VALUES(`section_key`), `icon` = VALUES(`icon`), `is_active` = 1;
