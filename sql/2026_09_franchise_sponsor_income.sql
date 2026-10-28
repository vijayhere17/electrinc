-- =====================================================================
-- Franchise finance correction — Sponsor Income vs Turnover Income
--
--  * 5% SPONSOR INCOME  : paid ONCE, when a franchise invests in a model,
--    to that franchise's SPONSOR FRANCHISE, on the model investment amount.
--  * 0.5% TURNOVER INCOME : the COMPANY earns it, on showroom sale turnover.
--
-- Rates already live in `franchise_settings` (sponsor_income_rate = 5.00,
-- turnover_income_rate = 0.50). This migration only adds the sponsor link.
-- =====================================================================

ALTER TABLE `franchies_holder`
    ADD COLUMN IF NOT EXISTS `sponsor_franchise_code` VARCHAR(50) NULL AFTER `investment_start_date`;

-- city franchises (only if the table is present in this install)
ALTER TABLE `city_franchies_holder`
    ADD COLUMN IF NOT EXISTS `sponsor_franchise_code` VARCHAR(50) NULL;
