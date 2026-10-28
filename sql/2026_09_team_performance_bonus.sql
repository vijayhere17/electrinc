-- ============================================================
-- Income Plan #6 — Team Performance Bonus (monthly, matching-business rank)
--
-- Each calendar month:
--   * a member is a Business Leader if they meet the configured
--     leader rule (min active directs + optionally one on each leg)
--   * their monthly Left Business / Right Business = the joining
--     business of members who joined that month in their left /
--     right placement subtree
--   * Matching Business = MIN(Left, Right)
--   * Business Leaders are ranked by Matching Business (desc)
--   * Top `team_perf_winners` (3) share the pool: 40% / 30% / 30%
--
-- Pool for the month is admin-configurable:
--   mode 'percent_of_business' -> team_perf_pool_percent % of the
--        month's total joining business
--   mode 'fixed'              -> team_perf_pool_fixed
--   team_perf_pool_override > 0 forces that exact amount.
--
-- Monthly history keeps the pool, rankings, %s and the rule
-- snapshot actually used for that month.
-- ============================================================

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('team_perf_enabled',              '1',                    'Team Performance Bonus active (1/0)'),
  ('team_perf_period',               'monthly',              'Ranking period: monthly'),
  ('team_perf_pool_mode',            'fixed',                'Pool source: fixed | percent_of_business'),
  ('team_perf_pool_percent',         '2.00',                 '% of the month total joining business (percent_of_business mode)'),
  ('team_perf_pool_fixed',           '0.00',                 'Monthly pool amount (fixed mode) — admin sets this'),
  ('team_perf_pool_override',        '0.00',                 'If > 0, use this exact pool amount for the month'),
  ('team_perf_winners',              '3',                    'Number of top ranks that receive the bonus'),
  ('team_perf_rank_1_percent',       '40.00',                '1st Rank share of the monthly pool'),
  ('team_perf_rank_2_percent',       '30.00',                '2nd Rank share of the monthly pool'),
  ('team_perf_rank_3_percent',       '30.00',                '3rd Rank share of the monthly pool'),
  ('team_perf_leader_min_directs',   '2',                    'Min active direct sponsors to qualify as a Business Leader'),
  ('team_perf_leader_require_both_legs', '1',                'Business Leader also needs >=1 active direct on each leg (1/0)'),
  ('team_perf_min_matching',         '1.00',                 'Minimum matching business in the month to be eligible for a rank'),
  ('team_perf_cap_exempt',           '1',                    'Team Performance Bonus exempt from the withdrawal-cycle cap (1/0)')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- one row per ranking period ---
CREATE TABLE IF NOT EXISTS `team_perf_pool` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_key`         VARCHAR(10)   NOT NULL,
  `business_total`     DECIMAL(22,2) NOT NULL DEFAULT 0.00,   -- month joining business
  `pool_mode`          VARCHAR(30)   DEFAULT NULL,
  `pool_percent`       DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  `pool_fixed`         DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `pool_override`      DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `pool_final`         DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `winners_count`      INT           NOT NULL DEFAULT 0,
  `rank_percents_json` VARCHAR(255)  DEFAULT NULL,
  `leader_rule_json`   VARCHAR(255)  DEFAULT NULL,
  `status`             VARCHAR(20)   NOT NULL DEFAULT 'open',  -- open | distributed
  `distributed_at`     DATETIME      DEFAULT NULL,
  `distributed_by`     VARCHAR(120)  DEFAULT NULL,
  `created_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_period` (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- monthly leg-business attribution (one row per ancestor per new join) ---
CREATE TABLE IF NOT EXISTS `team_business_contributions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_key`     VARCHAR(10)   NOT NULL,
  `leader_id`      VARCHAR(50)   NOT NULL,        -- placement ancestor
  `from_member_id` VARCHAR(50)   NOT NULL,        -- member who joined this month
  `side`           CHAR(1)       NOT NULL,        -- 'L' or 'R' leg of the leader
  `amount`         DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_leg` (`period_key`, `leader_id`, `from_member_id`),
  KEY `idx_period_leader` (`period_key`, `leader_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- one row per winning rank per period ---
CREATE TABLE IF NOT EXISTS `team_perf_payouts` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_key`       VARCHAR(10)   NOT NULL,
  `rank_no`          INT           NOT NULL,
  `customer_id`      VARCHAR(50)   NOT NULL,
  `left_business`    DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `right_business`   DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `matching_business` DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `percent`          DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `pool_final`       DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `amount`           DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `paid_amount`      DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `flushed_amount`   DECIMAL(22,2) NOT NULL DEFAULT 0.00,
  `ledger_id`        INT           DEFAULT NULL,
  `status`           VARCHAR(20)   NOT NULL DEFAULT 'Credit',
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_period_rank` (`period_key`, `rank_no`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_panel_pages`
  (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES
  ('team-performance-bonus', 'team-performance-bonus.php', 'Team Performance Bonus', 'accounts', 'users-round', 43, 1)
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`), `page_title` = VALUES(`page_title`),
  `section_key` = VALUES(`section_key`), `icon` = VALUES(`icon`), `is_active` = 1;
