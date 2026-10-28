-- Run this in phpMyAdmin SQL tab after taking a backup.
-- It standardizes member_id -> customer_id and can also replace an old customer value in all tables.

-- ------------------------------------------------------------
-- STEP 1: Rename member_id columns to customer_id in all tables
-- ------------------------------------------------------------
SET @db := DATABASE();

SELECT GROUP_CONCAT(
  CONCAT('ALTER TABLE `', TABLE_NAME, '` RENAME COLUMN `member_id` TO `customer_id`')
  SEPARATOR '; '
) INTO @rename_sql
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db
  AND COLUMN_NAME = 'member_id';

SET @rename_sql := IFNULL(@rename_sql, 'SELECT ''No member_id columns found''');
PREPARE stmt_rename FROM @rename_sql;
EXECUTE stmt_rename;
DEALLOCATE PREPARE stmt_rename;

-- ------------------------------------------------------------
-- STEP 2 (optional): Replace old ID value in all customer_id columns
-- Example: old value MLM-00484 -> new value fresh0001
-- ------------------------------------------------------------
SET @old_customer_id := 'MLM-00484';
SET @new_customer_id := 'fresh0001';

SELECT GROUP_CONCAT(
  CONCAT(
    'UPDATE `', TABLE_NAME, '` SET `customer_id` = ''',
    REPLACE(@new_customer_id, '''', ''''''),
    ''' WHERE `customer_id` = ''',
    REPLACE(@old_customer_id, '''', ''''''),
    ''''
  )
  SEPARATOR '; '
) INTO @update_sql
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db
  AND COLUMN_NAME = 'customer_id';

SET @update_sql := IFNULL(@update_sql, 'SELECT ''No customer_id columns found''');
PREPARE stmt_update FROM @update_sql;
EXECUTE stmt_update;
DEALLOCATE PREPARE stmt_update;

-- ------------------------------------------------------------
-- STEP 3: Verify results
-- ------------------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME IN ('member_id', 'customer_id')
ORDER BY TABLE_NAME, COLUMN_NAME;
