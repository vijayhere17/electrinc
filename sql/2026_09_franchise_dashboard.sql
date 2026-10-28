-- ============================================================
-- Franchise Dashboard — Step 1
-- Minimal backend structures for the franchise overview page.
--   * franchise_settings         : admin-manageable rate config
--   * franchise_investment_models : catalogue of investment models
--   * franchies_holder / city_franchies_holder : link a model to a franchise
-- No existing data is touched. Cashback engine is a later step.
-- ============================================================

-- --- rate / plan config (key-value, admin-editable later) ---
CREATE TABLE IF NOT EXISTS `franchise_settings` (
  `setting_key`   VARCHAR(60)  NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `description`   VARCHAR(255) DEFAULT NULL,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `franchise_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('sponsor_income_rate',  '5.00', 'Showroom Sponsor Income % earned by a franchise'),
  ('turnover_income_rate', '0.50', 'Showroom Turnover Income % earned by a franchise'),
  ('cashback_total_months','36',   'Cashback period length in months')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- --- investment model catalogue ---
CREATE TABLE IF NOT EXISTS `franchise_investment_models` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `model_no`         VARCHAR(20)  NOT NULL,
  `model_name`       VARCHAR(100) NOT NULL,
  `investment_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `scooters`         INT           NOT NULL DEFAULT 0,
  `cashback_percent` DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `period_months`    INT           NOT NULL DEFAULT 36,
  `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_model_no` (`model_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `franchise_investment_models`
  (`model_no`, `model_name`, `investment_amount`, `scooters`, `cashback_percent`, `period_months`, `is_active`)
VALUES
  ('1', 'Model 1', 500000.00, 1, 100.00, 36, 1)
ON DUPLICATE KEY UPDATE `model_no` = `model_no`;

-- --- link a chosen model + start date to each franchise holder ---
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='franchies_holder' AND COLUMN_NAME='investment_model_id');
SET @s := IF(@c=0, 'ALTER TABLE `franchies_holder` ADD COLUMN `investment_model_id` INT UNSIGNED NULL DEFAULT NULL AFTER `details`', 'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='franchies_holder' AND COLUMN_NAME='investment_start_date');
SET @s := IF(@c=0, 'ALTER TABLE `franchies_holder` ADD COLUMN `investment_start_date` DATE NULL DEFAULT NULL AFTER `investment_model_id`', 'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='city_franchies_holder' AND COLUMN_NAME='investment_model_id');
SET @s := IF(@c=0 AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='city_franchies_holder')>0,
  'ALTER TABLE `city_franchies_holder` ADD COLUMN `investment_model_id` INT UNSIGNED NULL DEFAULT NULL',
  'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='city_franchies_holder' AND COLUMN_NAME='investment_start_date');
SET @s := IF(@c=0 AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='city_franchies_holder')>0,
  'ALTER TABLE `city_franchies_holder` ADD COLUMN `investment_start_date` DATE NULL DEFAULT NULL',
  'SELECT 1');
PREPARE x FROM @s; EXECUTE x; DEALLOCATE PREPARE x;
