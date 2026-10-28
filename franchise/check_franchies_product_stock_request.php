<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';

// Ensure the table exists (will create if missing) and then report
ensureFranchiesProductStockRequestTable($conn);
echo tableExists($conn, 'franchies_product_stock_request') ? 'TABLE_EXISTS' : 'NO_TABLE';
