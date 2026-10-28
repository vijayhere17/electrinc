-- ============================================================
-- Income Plan #5 — Performance Bonus (monthly, rank-based)
--
-- Every qualifying direct join contributes `perf_bonus_per_direct`
-- (default ₹100) to that calendar month's Performance Bonus Pool.
-- At month-end each member's qualifying directs for the month are
-- counted; the Top `perf_bonus_winners` (default 3) by direct count
-- share the pool: 1st 40%, 2nd 30%, 3rd 30%.
-- Every month keeps a full audit of pool, rankings, %s and payouts,
-- and historical months retain the rules that were actually used.
-- All values live in income_settings / admin-editable.
-- ============================================================

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('perf_bonus_enabled',        '1',       'Performance Bonus active (1/0)'),
  ('perf_bonus_per_direct',     '100.00',  'Pool contribution per qualifying direct join'),
  ('perf_bonus_pool_override',  '0.00',    'If > 0, use this fixed pool amount instead of the collected pool'),
  ('perf_bonus_period',         'monthly', 'Ranking period: monthly'),
  ('perf_bonus_winners',        '3',       'Number of top ranks that receive the bonus'),
  ('perf_bonus_min_directs',    '1',       'Minimum qualifying directs in the month to be eligible for a rank'),
  ('perf_bonus_rank_1_percent', '40.00',   '1st Rank share of the monthly pool'),
  ('perf_bonus_rank_2_percent', '30.00',   '2nd Rank share of the monthly pool'),
  ('perf_bonus_rank_3_percent', '30.00',   '3rd Rank share of the monthly pool'),
  ('perf_bonus_cap_exempt',     '1',       'Performance Bonus exempt from the withdrawal-cycle cap (1/0)')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- one row per ranking period ---
CREATE TABLE IF NOT EXISTS `performance_bonus_pool` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_key`         VARCHAR(10)   NOT NULL,           -- 'YYYY-MM'
  `per_direct_amount`  DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `qualifying_directs` BIGINT        NOT NULL DEFAULT 0,
  `pool_collected`     DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `pool_override`      DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `pool_final`         DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `winners_count`      INT           NOT NULL DEFAULT 0,
  `rank_percents_json` VARCHAR(255)  DEFAULT NULL,        -- snapshot of %s used
  `status`             VARCHAR(20)   NOT NULL DEFAULT 'open',  -- open | distributed
  `distributed_at`     DATETIME      DEFAULT NULL,
  `distributed_by`     VARCHAR(120)  DEFAULT NULL,
  `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_period` (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- audit of each ₹100 contribution (= one qualifying direct) ---
CREATE TABLE IF NOT EXISTS `performance_bonus_contributions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_key`     VARCHAR(10)   NOT NULL,
  `sponsor_id`     VARCHAR(50)   NOT NULL,        -- the member who earns the direct
  `from_member_id` VARCHAR(50)   NOT NULL,        -- the newly joined direct
  `amount`         DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_contrib` (`period_key`, `sponsor_id`, `from_member_id`),
  KEY `idx_period_sponsor` (`period_key`, `sponsor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- one row per winning rank per period ---
CREATE TABLE IF NOT EXISTS `performance_bonus_payouts` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_key`    VARCHAR(10)   NOT NULL,
  `rank_no`       INT           NOT NULL,
  `customer_id`   VARCHAR(50)   NOT NULL,
  `directs_count` BIGINT        NOT NULL DEFAULT 0,
  `percent`       DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `pool_final`    DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `amount`        DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `paid_amount`   DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `flushed_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `ledger_id`     INT           DEFAULT NULL,
  `status`        VARCHAR(20)   NOT NULL DEFAULT 'Credit',
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_period_rank` (`period_key`, `rank_no`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_panel_pages`
  (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES
  ('performance-bonus', 'performance-bonus.php', 'Performance Bonus', 'accounts', 'trophy', 44, 1)
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`), `page_title` = VALUES(`page_title`),
  `section_key` = VALUES(`section_key`), `icon` = VALUES(`icon`), `is_active` = 1;
