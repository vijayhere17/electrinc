-- =====================================================================
-- Franchise Panel · Daily Ops — Sales & Turnover
-- Makes fr_customer_purchases the single canonical sales-line table for
-- the franchise (walk-in sales allowed — customer optional). Additive.
-- =====================================================================

ALTER TABLE `fr_customer_purchases`
    MODIFY COLUMN `customer_id` BIGINT UNSIGNED NULL;

ALTER TABLE `fr_customer_purchases`
    ADD COLUMN IF NOT EXISTS `customer_name` VARCHAR(200) NULL AFTER `customer_id`;

ALTER TABLE `fr_customer_purchases`
    ADD INDEX IF NOT EXISTS `idx_fr_date` (`franchise_id`, `sale_date`);
