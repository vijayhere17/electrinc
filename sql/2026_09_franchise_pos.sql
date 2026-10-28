-- =====================================================================
-- Franchise Billing POS — extra sale fields on fr_customer_purchases
-- (discount, payment method, amount paid for partial payment, sale status)
-- Additive, idempotent.
-- =====================================================================

ALTER TABLE `fr_customer_purchases`
    ADD COLUMN IF NOT EXISTS `discount`       DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `unit_price`;

ALTER TABLE `fr_customer_purchases`
    ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(40) NULL AFTER `discount`;

ALTER TABLE `fr_customer_purchases`
    ADD COLUMN IF NOT EXISTS `amount_paid`    DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `payment_status`;

ALTER TABLE `fr_customer_purchases`
    ADD COLUMN IF NOT EXISTS `sale_status`    VARCHAR(20) NOT NULL DEFAULT 'Completed' AFTER `delivery_status`;
