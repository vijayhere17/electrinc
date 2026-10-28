-- =====================================================================
-- Franchise sales — automatic invoice numbering
-- Per franchise, per financial year, gap-free running sequence.
-- Format produced:  <CODE>/<YY-YY>/<0001>   e.g.  FR001/26-27/0001
-- =====================================================================

CREATE TABLE IF NOT EXISTS `fr_invoice_counters` (
    `franchise_id` INT(11)     NOT NULL,
    `fy`           VARCHAR(9)   NOT NULL,          -- '26-27'
    `last_no`      INT(11)      NOT NULL DEFAULT 0,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`franchise_id`, `fy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
