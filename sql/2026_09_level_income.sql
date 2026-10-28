-- ============================================================
-- Income Plan #3 — Level Income  + full admin-configurable ruleset
--
-- Level Income: qualifying business by a member is distributed up
-- that member's SPONSOR (referral) chain. Level 1 = the booking
-- member's direct sponsor, Level 2 = that sponsor's sponsor, etc.
--   L1 20%  L2 10%  L3 8%  L4 7%  L5 5%   (total 50%)
-- Eligibility: the upline at a level only needs to be ACTIVE.
-- If a level's upline is missing or inactive, that level's share
-- is FLUSHED (no compression — deeper levels keep their own %).
--
-- Every percentage / amount / cap / level / eligibility rule below
-- is stored in income_settings and editable from the Admin Panel
-- "Income & Business Rules" page. Changes are recorded in
-- income_settings_history. Completed income rows keep the % that
-- was in force when they were generated.
-- ============================================================

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  -- A. Direct Sponsor
  ('direct_sponsor_instant',       '1',        'Direct Sponsor Income credited instantly on qualifying booking (1/0)'),
  ('direct_sponsor_cap_exempt',    '1',        'Direct Sponsor Income is exempt from the withdrawal-cycle cap (1/0)'),
  -- B. Binary  (Left % and Right % kept separate)
  ('binary_left_percent',          '2.50',     'Binary Income % applied to LEFT leg booking per Jodi'),
  ('binary_right_percent',         '2.50',     'Binary Income % applied to RIGHT leg booking per Jodi'),
  ('binary_base_default',          '10000.00', 'Fallback qualifying booking amount for Binary when none recorded'),
  ('binary_qualify_left_directs',  '1',        'Active direct sponsors required on LEFT to qualify for Binary'),
  ('binary_qualify_right_directs', '1',        'Active direct sponsors required on RIGHT to qualify for Binary'),
  ('binary_cap_exempt',            '0',        'Binary Income is exempt from the withdrawal-cycle cap (1/0)'),
  -- C. Binary Cap
  ('cap_directs_step',             '2',        'Number of additional active directs that unlock one more cap step'),
  ('cap_excess_rule',              'flush',    'What happens to income above the cap: flush | carry | hold'),
  -- D. Level Income
  ('level_income_count',           '5',        'Number of paid team levels'),
  ('level_1_percent',              '20.00',    'Level 1 Income %'),
  ('level_2_percent',              '10.00',    'Level 2 Income %'),
  ('level_3_percent',              '8.00',     'Level 3 Income %'),
  ('level_4_percent',              '7.00',     'Level 4 Income %'),
  ('level_5_percent',              '5.00',     'Level 5 Income %'),
  ('level_base_default',           '10000.00', 'Fallback qualifying business amount for Level Income'),
  ('level_require_active',         '1',        'Upline must be Active to earn that level (1/0)'),
  ('level_missing_rule',           'flush',    'Missing/inactive level behaviour: flush | compress'),
  ('level_cap_exempt',             '1',        'Level Income is exempt from the withdrawal-cycle cap (1/0)'),
  -- E. Business
  ('business_qualifying_amount',   '10000.00', 'Standard qualifying booking / business amount')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- member_income_ledger: make every transaction self-describing ---
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_income_ledger' AND COLUMN_NAME='base_amount');
SET @s := IF(@c=0,'ALTER TABLE `member_income_ledger` ADD COLUMN `base_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `counts_to_cap`','SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_income_ledger' AND COLUMN_NAME='percentage');
SET @s := IF(@c=0,'ALTER TABLE `member_income_ledger` ADD COLUMN `percentage` DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `base_amount`','SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_income_ledger' AND COLUMN_NAME='from_member_id');
SET @s := IF(@c=0,'ALTER TABLE `member_income_ledger` ADD COLUMN `from_member_id` VARCHAR(50) DEFAULT NULL AFTER `source_ref`','SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='member_income_ledger' AND COLUMN_NAME='from_member_name');
SET @s := IF(@c=0,'ALTER TABLE `member_income_ledger` ADD COLUMN `from_member_name` VARCHAR(255) DEFAULT NULL AFTER `from_member_id`','SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

-- --- level income detail (one row per level per generating booking) ---
CREATE TABLE IF NOT EXISTS `level_income` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`         VARCHAR(50)  NOT NULL,           -- upline that earns this level
  `from_member_id`      VARCHAR(50)  NOT NULL,           -- member whose booking generated the business
  `from_member_name`    VARCHAR(255) DEFAULT NULL,
  `level_no`            INT          NOT NULL,
  `report_date`         DATE         DEFAULT NULL,
  `business_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `percentage`          DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `gross_amount`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `flushed_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `fund_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `withdrawable_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `flush_reason`        VARCHAR(40)  DEFAULT NULL,        -- inactive_upline | cap | null
  `source`             VARCHAR(30)  NOT NULL DEFAULT 'Activation',
  `ref_id`             INT          DEFAULT NULL,
  `status`             VARCHAR(20)  NOT NULL DEFAULT 'Credit',
  `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_level_pay` (`customer_id`, `from_member_id`, `level_no`, `source`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_from` (`from_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- configuration change history ---
CREATE TABLE IF NOT EXISTS `income_settings_history` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(60)  NOT NULL,
  `old_value`     VARCHAR(255) DEFAULT NULL,
  `new_value`     VARCHAR(255) DEFAULT NULL,
  `changed_by`    VARCHAR(120) DEFAULT NULL,
  `changed_by_id` VARCHAR(50)  DEFAULT NULL,
  `effective_date` DATE        DEFAULT NULL,
  `reason`        VARCHAR(255) DEFAULT NULL,
  `changed_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_key` (`setting_key`),
  KEY `idx_when` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- register admin pages ---
INSERT INTO `admin_panel_pages`
  (`page_slug`, `file_name`, `page_title`, `section_key`, `icon`, `sort_order`, `is_active`)
VALUES
  ('income-rules', 'income-rules.php', 'Income & Business Rules', 'accounts', 'sliders-horizontal', 46, 1),
  ('level-income', 'level-income.php', 'Level Income',            'accounts', 'list-tree',          47, 1)
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`), `page_title` = VALUES(`page_title`),
  `section_key` = VALUES(`section_key`), `icon` = VALUES(`icon`), `is_active` = 1;
