-- ============================================================
-- Revision (2026-09-07): Level Income is now earned from the
-- BINARY INCOME of downline members, not from the ₹10,000 joining.
--   L1 upline = 20% of that member's Binary Income, L2 10%, L3 8%,
--   L4 7%, L5 5%. Triggered each time a Jodi pays Binary Income.
-- Auto Pool Pool-1 entry (₹100) is COMPANY funded (never from the
--   member's joining amount).
-- All values remain in income_settings / admin-editable.
-- ============================================================

INSERT INTO `income_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('level_income_base', 'binary', 'Base that Level Income % is applied to: binary (downline Binary Income) | joining')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

UPDATE `income_settings` SET `setting_value` = 'company',
  `description` = 'Where the Pool-1 entry amount is funded from: company | booking | wallet | product_fund'
  WHERE `setting_key` = 'auto_pool_fund_from';

-- level_income dedupe must allow many Jodis from the same downline member:
-- key on the source binary row (ref_id) instead of just source.
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'level_income' AND INDEX_NAME = 'uniq_level_pay');
SET @s := IF(@has > 0, 'ALTER TABLE `level_income` DROP INDEX `uniq_level_pay`', 'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

SET @has2 := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'level_income' AND INDEX_NAME = 'uniq_level_ref');
SET @s := IF(@has2 = 0,
  'ALTER TABLE `level_income` ADD UNIQUE KEY `uniq_level_ref` (`customer_id`, `from_member_id`, `level_no`, `source`, `ref_id`)',
  'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

-- widen level_income money columns (binary-derived level income can compound)
SET @s := (SELECT IF(
   (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='level_income' AND COLUMN_NAME='gross_amount') LIKE 'decimal(12,%',
   'ALTER TABLE `level_income`
      MODIFY `business_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `gross_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `paid_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `flushed_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `fund_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00,
      MODIFY `withdrawable_amount` DECIMAL(20,2) NOT NULL DEFAULT 0.00',
   'SELECT 1'));
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
