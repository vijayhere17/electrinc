-- Optional product photo, shown as a thumbnail/preview across the franchise inventory UI.
ALTER TABLE fr_products ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER model;
