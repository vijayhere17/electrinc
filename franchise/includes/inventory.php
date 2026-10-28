<?php
/**
 * Franchise inventory engine — Product Master, custom fields, units,
 * stock entry, stock movements, search, traceability.
 *
 * Every function is franchise-scoped: callers pass the authenticated
 * $franchiseId (from session/auth.php) and every write/read is filtered
 * by it. Nothing here trusts a URL parameter for ownership.
 *
 * No product identifier format (chassis / motor / battery / …) is
 * hard-coded — identifiers are user-defined custom fields (EAV).
 */

require_once __DIR__ . '/../../includes/franchise_finance.php';
require_once __DIR__ . '/vendors.php';
require_once __DIR__ . '/customers.php';

if (!function_exists('fr_inv_ready')) {
    function fr_inv_ready(mysqli $conn): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        $r = $conn->query("SHOW TABLES LIKE 'fr_products'");
        $ok = $r instanceof mysqli_result && $r->num_rows > 0;
        return $ok;
    }
}

if (!function_exists('fr_slug')) {
    function fr_slug(string $label): string
    {
        $s = strtolower(trim($label));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        $s = trim((string)$s, '_');
        return $s !== '' ? substr($s, 0, 60) : ('field_' . substr(md5($label), 0, 8));
    }
}

if (!function_exists('fr_inv_statuses')) {
    function fr_inv_statuses(): array
    {
        return ['Available', 'Reserved', 'Sold', 'Delivered', 'Returned', 'Damaged', 'Cancelled'];
    }
}

/* ---------------- movement log ---------------- */
if (!function_exists('fr_move')) {
    function fr_move(mysqli $conn, int $fid, int $productId, ?int $unitId, string $type, int $qtyChange, array $o = []): void
    {
        try {
            $bal    = array_key_exists('balance_after', $o) ? (int)$o['balance_after'] : null;
            $reason = (string)($o['reason'] ?? '');
            $ref    = (string)($o['reference'] ?? '');
            $by     = (string)($o['performed_by'] ?? '');
            $rem    = (string)($o['remarks'] ?? '');
            $s = $conn->prepare("INSERT INTO fr_stock_movements
                (franchise_id, product_id, unit_id, movement_type, qty_change, balance_after, reason, reference, performed_by, remarks, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
            $s->bind_param('iiisiissss', $fid, $productId, $unitId, $type, $qtyChange, $bal, $reason, $ref, $by, $rem);
            $s->execute();
            $s->close();
        } catch (\Throwable $e) { /* non-fatal */ }
    }
}

/* ---------------- products ---------------- */
if (!function_exists('fr_product_get')) {
    function fr_product_get(mysqli $conn, int $fid, int $productId): ?array
    {
        $s = $conn->prepare("SELECT * FROM fr_products WHERE id = ? AND franchise_id = ? LIMIT 1");
        $s->bind_param('ii', $productId, $fid);
        $s->execute();
        $row = $s->get_result()->fetch_assoc() ?: null;
        $s->close();
        return $row;
    }
}

if (!function_exists('fr_product_save')) {
    /** create ($productId = 0) or update. Returns ['ok'=>bool,'id'=>int,'message'=>string] */
    function fr_product_save(mysqli $conn, int $fid, string $fcode, array $d, string $by, int $productId = 0): array
    {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'Product name is required.'];

        $category  = trim((string)($d['category'] ?? ''));
        $brand     = trim((string)($d['brand'] ?? ''));
        $model     = trim((string)($d['model'] ?? ''));
        $price     = round((float)($d['price'] ?? 0), 2);
        $serial    = !empty($d['is_serialized']) ? 1 : 0;
        $status    = (in_array(($d['status'] ?? 'Active'), ['Active', 'Inactive'], true)) ? $d['status'] : 'Active';
        $imagePath = isset($d['image_path']) ? trim((string)$d['image_path']) : '';   // only overwrite when a new image was uploaded

        try {
            if ($productId > 0) {
                $chk = fr_product_get($conn, $fid, $productId);
                if (!$chk) return ['ok' => false, 'message' => 'Product not found.'];
                if ($imagePath !== '') {
                    $s = $conn->prepare("UPDATE fr_products SET name=?, category=?, brand=?, model=?, price=?, status=?, image_path=? WHERE id=? AND franchise_id=?");
                    $s->bind_param('ssssdssii', $name, $category, $brand, $model, $price, $status, $imagePath, $productId, $fid);
                } else {
                    $s = $conn->prepare("UPDATE fr_products SET name=?, category=?, brand=?, model=?, price=?, status=? WHERE id=? AND franchise_id=?");
                    $s->bind_param('ssssdsii', $name, $category, $brand, $model, $price, $status, $productId, $fid);
                }
                $s->execute();
                $s->close();
                return ['ok' => true, 'id' => $productId, 'message' => 'Product updated.'];
            }
            $openQty = max(0, (int)($d['opening_qty'] ?? 0));
            $qtyAvail = $serial ? 0 : $openQty;
            $s = $conn->prepare("INSERT INTO fr_products
                (franchise_id, franchise_code, name, category, brand, model, image_path, price, is_serialized, status, qty_available, created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $s->bind_param('issssssdisis', $fid, $fcode, $name, $category, $brand, $model, $imagePath, $price, $serial, $status, $qtyAvail, $by);
            $s->execute();
            $newId = (int)$s->insert_id;
            $s->close();
            if (!$serial && $openQty > 0) {
                fr_move($conn, $fid, $newId, null, 'Received', $openQty, ['balance_after' => $openQty, 'reason' => 'Opening stock', 'performed_by' => $by]);
            }
            return ['ok' => true, 'id' => $newId, 'message' => 'Product created.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()];
        }
    }
}

/* ---------------- custom fields ---------------- */
if (!function_exists('fr_fields_list')) {
    function fr_fields_list(mysqli $conn, int $productId): array
    {
        $out = [];
        $s = $conn->prepare("SELECT * FROM fr_product_fields WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
        $s->bind_param('i', $productId);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

if (!function_exists('fr_field_add')) {
    function fr_field_add(mysqli $conn, int $fid, int $productId, string $label, string $type = 'text', bool $isIdentifier = false): array
    {
        $label = trim($label);
        if ($label === '') return ['ok' => false, 'message' => 'Field label is required.'];
        if (!fr_product_get($conn, $fid, $productId)) return ['ok' => false, 'message' => 'Product not found.'];
        $key = fr_slug($label);
        $type = in_array($type, ['text', 'number', 'date'], true) ? $type : 'text';
        try {
            $ord = (int)fr_scalar($conn, "SELECT COALESCE(MAX(sort_order),0)+1 FROM fr_product_fields WHERE product_id=?", 'i', [$productId]);
            $ii = $isIdentifier ? 1 : 0;
            $s = $conn->prepare("INSERT INTO fr_product_fields (product_id, franchise_id, field_key, field_label, field_type, is_identifier, sort_order, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
            $s->bind_param('iisssii', $productId, $fid, $key, $label, $type, $ii, $ord);
            $s->execute();
            $s->close();
            return ['ok' => true, 'field_key' => $key, 'message' => 'Custom field added.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => (strpos($e->getMessage(), 'uniq_field') !== false) ? 'A field with this name already exists.' : $e->getMessage()];
        }
    }
}

if (!function_exists('fr_field_delete')) {
    function fr_field_delete(mysqli $conn, int $fid, int $productId, int $fieldId): array
    {
        $s = $conn->prepare("DELETE FROM fr_product_fields WHERE id=? AND product_id=? AND franchise_id=?");
        $s->bind_param('iii', $fieldId, $productId, $fid);
        $s->execute();
        $ok = $s->affected_rows > 0;
        $s->close();
        return ['ok' => $ok, 'message' => $ok ? 'Field removed.' : 'Field not found.'];
    }
}

if (!function_exists('fr_scalar')) {
    function fr_scalar(mysqli $conn, string $sql, string $types = '', array $p = [])
    {
        try {
            $s = $conn->prepare($sql);
            if (!$s) return null;
            if ($types !== '') $s->bind_param($types, ...$p);
            $s->execute();
            $row = $s->get_result()->fetch_row();
            $s->close();
            return $row ? $row[0] : null;
        } catch (\Throwable $e) { return null; }
    }
}

/* ---------------- counts ---------------- */
if (!function_exists('fr_product_counts')) {
    function fr_product_counts(mysqli $conn, array $product): array
    {
        $pid = (int)$product['id'];
        $c = ['total' => 0, 'available' => 0, 'reserved' => 0, 'sold' => 0, 'delivered' => 0, 'returned' => 0, 'damaged' => 0, 'cancelled' => 0];

        if ((int)$product['is_serialized'] === 1) {
            $r = $conn->query("SELECT status, COUNT(*) n FROM fr_units WHERE product_id = " . $pid . " GROUP BY status");
            while ($r && ($x = $r->fetch_assoc())) {
                $k = strtolower((string)$x['status']);
                if (isset($c[$k])) $c[$k] = (int)$x['n'];
            }
        } else {
            $c['available'] = (int)$product['qty_available'];
            $c['reserved']  = (int)$product['qty_reserved'];
            $c['sold']      = (int)$product['qty_sold'];
            $c['returned']  = (int)$product['qty_returned'];
            $c['damaged']   = (int)$product['qty_damaged'];
        }
        $c['total'] = $c['available'] + $c['reserved'] + $c['sold'] + $c['delivered'] + $c['returned'] + $c['damaged'];
        return $c;
    }
}

/* ---------------- stock entry (receive) ---------------- */
if (!function_exists('fr_stock_receive')) {
    function fr_stock_receive(mysqli $conn, int $fid, string $fcode, int $productId, int $qty, array $meta, string $by): array
    {
        $product = fr_product_get($conn, $fid, $productId);
        if (!$product) return ['ok' => false, 'message' => 'Product not found.'];
        $qty = (int)$qty;
        if ($qty < 1) return ['ok' => false, 'message' => 'Quantity must be at least 1.'];

        $rDate  = trim((string)($meta['received_date'] ?? date('Y-m-d')));
        $refNo  = trim((string)($meta['reference_no'] ?? ''));
        $source = trim((string)($meta['source'] ?? ''));
        $pDet   = trim((string)($meta['purchase_details'] ?? ''));
        $rem    = trim((string)($meta['remarks'] ?? ''));

        // --- vendor / source resolution -------------------------------------
        $vendorId   = (int)($meta['vendor_id'] ?? 0);
        $vendorName = '';
        if ($vendorId > 0) {
            $vs = $conn->prepare("SELECT vendor_name, status FROM fr_vendors WHERE id = ? AND franchise_id = ? LIMIT 1");
            if ($vs) {
                $vs->bind_param('ii', $vendorId, $fid);
                $vs->execute();
                $vr = $vs->get_result()->fetch_assoc();
                $vs->close();
                if (!$vr) return ['ok' => false, 'message' => 'Selected vendor was not found for this franchise.'];
                if (strtolower((string)$vr['status']) !== 'active') {
                    return ['ok' => false, 'message' => 'Vendor is inactive — new stock cannot be received against an inactive vendor.'];
                }
                $vendorName = (string)$vr['vendor_name'];
            } else {
                $vendorId = 0; // vendor table not available — fall back to free-text source
            }
        }
        if ($vendorName !== '' && $source === '') $source = $vendorName;
        $vendorIdParam = $vendorId > 0 ? $vendorId : null;

        try {
            $conn->begin_transaction();

            $s = $conn->prepare("INSERT INTO fr_stock_entries
                (franchise_id, franchise_code, product_id, quantity, received_date, reference_no, source, vendor_id, purchase_details, remarks, performed_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $s->bind_param('isiisssisss', $fid, $fcode, $productId, $qty, $rDate, $refNo, $source, $vendorIdParam, $pDet, $rem, $by);
            $s->execute();
            $entryId = (int)$s->insert_id;
            $s->close();

            $unitIds = [];
            if ((int)$product['is_serialized'] === 1) {
                $seq = (int)fr_scalar($conn, "SELECT COUNT(*) FROM fr_units WHERE product_id=?", 'i', [$productId]);
                $ins = $conn->prepare("INSERT INTO fr_units
                    (product_id, franchise_id, unit_no, status, stock_entry_id, source_vendor, vendor_id, received_date, created_at)
                    VALUES (?,?,?, 'Available', ?, ?, ?, ?, NOW())");
                for ($i = 1; $i <= $qty; $i++) {
                    $seq++;
                    $unitNo = 'U' . $productId . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
                    $ins->bind_param('iisisis', $productId, $fid, $unitNo, $entryId, $source, $vendorIdParam, $rDate);
                    $ins->execute();
                    $uid = (int)$ins->insert_id;
                    $unitIds[] = $uid;
                    fr_move($conn, $fid, $productId, $uid, 'Received', 1, ['reason' => 'Stock received', 'reference' => $refNo, 'performed_by' => $by, 'remarks' => $source]);
                }
                $ins->close();
            } else {
                $newAvail = (int)$product['qty_available'] + $qty;
                $u = $conn->prepare("UPDATE fr_products SET qty_available = ? WHERE id = ? AND franchise_id = ?");
                $u->bind_param('iii', $newAvail, $productId, $fid);
                $u->execute();
                $u->close();
                fr_move($conn, $fid, $productId, null, 'Received', $qty, ['balance_after' => $newAvail, 'reason' => 'Stock received', 'reference' => $refNo, 'performed_by' => $by, 'remarks' => $source]);
            }

            $conn->commit();

            if ($vendorId > 0 && function_exists('frv_history_add')) {
                $detail = $qty . ' × ' . $product['name'] . ($refNo !== '' ? ' · ref ' . $refNo : '') . ' · received ' . $rDate;
                frv_history_add($conn, $fid, $vendorId, 'stock_received', $detail, $by);
            }

            return ['ok' => true, 'entry_id' => $entryId, 'unit_ids' => $unitIds, 'vendor_id' => $vendorId, 'message' => 'Stock received (' . $qty . ' unit' . ($qty === 1 ? '' : 's') . ').'];
        } catch (\Throwable $e) {
            $conn->rollback();
            return ['ok' => false, 'message' => 'Receive failed: ' . $e->getMessage()];
        }
    }
}

/* ---------------- unit fetch / status / values ---------------- */
if (!function_exists('fr_unit_get')) {
    function fr_unit_get(mysqli $conn, int $fid, int $unitId): ?array
    {
        $s = $conn->prepare("SELECT u.*, p.name AS product_name, p.model AS product_model, p.is_serialized
                             FROM fr_units u JOIN fr_products p ON p.id = u.product_id
                             WHERE u.id = ? AND u.franchise_id = ? LIMIT 1");
        $s->bind_param('ii', $unitId, $fid);
        $s->execute();
        $row = $s->get_result()->fetch_assoc() ?: null;
        $s->close();
        return $row;
    }
}

if (!function_exists('fr_unit_set_status')) {
    function fr_unit_set_status(mysqli $conn, int $fid, int $unitId, string $newStatus, array $meta, string $by): array
    {
        $newStatus = ucfirst(strtolower(trim($newStatus)));
        if (!in_array($newStatus, fr_inv_statuses(), true)) return ['ok' => false, 'message' => 'Invalid status.'];
        $unit = fr_unit_get($conn, $fid, $unitId);
        if (!$unit) return ['ok' => false, 'message' => 'Unit not found.'];
        $old = (string)$unit['status'];
        if ($old === $newStatus) return ['ok' => true, 'message' => 'No change.'];

        $cust   = trim((string)($meta['customer_name'] ?? $unit['customer_name'] ?? ''));
        $custId = trim((string)($meta['customer_id'] ?? $unit['customer_id'] ?? ''));
        $ref    = trim((string)($meta['sale_ref'] ?? $unit['sale_ref'] ?? ''));

        // customer record link — validated against this franchise; keep the existing link if none passed
        $custRef = (int)($meta['customer_ref'] ?? 0);
        if ($custRef > 0 && function_exists('frc_customer_get')) {
            $cRow = frc_customer_get($conn, $fid, $custRef);
            if (!$cRow) { $custRef = 0; }
            elseif ($cust === '') { $cust = (string)$cRow['customer_name']; $custId = $custId ?: (string)$cRow['customer_code']; }
        }
        $custRefParam = $custRef > 0 ? $custRef : (!empty($unit['customer_ref']) ? (int)$unit['customer_ref'] : null);
        $inv    = trim((string)($meta['invoice_no'] ?? $unit['invoice_no'] ?? ''));
        $saleD  = trim((string)($meta['sale_date'] ?? ''));
        $delD   = trim((string)($meta['delivery_date'] ?? ''));
        $reason = trim((string)($meta['reason'] ?? ($old . ' → ' . $newStatus)));
        $rem    = trim((string)($meta['remarks'] ?? ''));

        // pricing: list/selling price − discount = net sale amount (commission base)
        $listPrice = round((float)($meta['unit_price'] ?? 0), 2);
        $discount  = max(0.0, round((float)($meta['discount'] ?? 0), 2));
        $saleAmt   = round((float)($meta['sale_amount'] ?? 0), 2);
        if ($saleAmt <= 0 && $listPrice > 0) $saleAmt = max(0.0, $listPrice - $discount);
        if ($saleAmt <= 0) $saleAmt = round((float)($unit['sale_amount'] ?? 0), 2);
        if ($saleAmt <= 0) $saleAmt = round((float)fr_scalar($conn, "SELECT price FROM fr_products WHERE id=?", 'i', [(int)$unit['product_id']]), 2);
        if ($listPrice <= 0) $listPrice = $saleAmt + $discount;

        if (in_array($newStatus, ['Sold', 'Delivered'], true)) {
            if ($saleD === '') $saleD = (string)($unit['sale_date'] ?? '') ?: date('Y-m-d');
            if ($newStatus === 'Delivered' && $delD === '') $delD = date('Y-m-d');
        }
        $saleD = ($saleD !== '' && $saleD !== '0000-00-00') ? $saleD : null;
        $delD  = ($delD  !== '' && $delD  !== '0000-00-00') ? $delD  : null;

        try {
            $s = $conn->prepare("UPDATE fr_units
                SET status=?, customer_name=?, customer_id=?, customer_ref=?, sale_ref=?, sale_amount=?, invoice_no=?, sale_date=?, delivery_date=?, remarks=?
                WHERE id=? AND franchise_id=?");
            $s->bind_param('sssisdssssii', $newStatus, $cust, $custId, $custRefParam, $ref, $saleAmt, $inv, $saleD, $delD, $rem, $unitId, $fid);
            $s->execute();
            $s->close();

            // a sale is "fresh" on the first move into Sold OR Delivered from a non-sold state
            $freshSale = (in_array($newStatus, ['Sold', 'Delivered'], true) && !in_array($old, ['Sold', 'Delivered'], true));

            // Sales-line record first, so we can reference its invoice number
            $saleInvoice = $inv;
            if ($freshSale && function_exists('frc_purchase_add')) {
                $addRes = frc_purchase_add($conn, $fid, $custRef, [
                    'franchise_code'  => (string)($meta['franchise_code'] ?? ''),
                    'customer_name'   => $cust,
                    'product_id'      => (int)$unit['product_id'],
                    'unit_id'         => $unitId,
                    'product_name'    => (string)($unit['product_name'] ?? ''),
                    'model'           => (string)($unit['product_model'] ?? ''),
                    'quantity'        => 1,
                    'unit_price'      => $listPrice,
                    'sale_amount'     => $saleAmt,
                    'invoice_no'      => $inv,
                    'sale_ref'        => $ref,
                    'sale_date'       => $saleD ?: date('Y-m-d'),
                    'discount'        => $discount,
                    'payment_method'  => (string)($meta['payment_method'] ?? ''),
                    'payment_status'  => (string)($meta['payment_status'] ?? 'Paid'),
                    'amount_paid'     => $meta['amount_paid'] ?? null,
                    'delivery_status' => ($newStatus === 'Delivered' || $delD) ? 'Delivered' : 'Pending',
                    'sale_status'     => (string)($meta['sale_status'] ?? 'Completed'),
                    'remarks'         => $rem,
                ], $by);
                if (!empty($addRes['invoice_no'])) $saleInvoice = $addRes['invoice_no'];
            }

            // Showroom commission on a fresh sale (not on re-marks or returns)
            if ($freshSale && $saleAmt > 0 && function_exists('frf_credit_commission')) {
                frf_credit_commission($conn, (string)($meta['franchise_code'] ?? ''), (string)($meta['franchise_type'] ?? 'state'), $saleAmt, [
                    'sale_type' => 'SALE', 'reference' => ($saleInvoice ?: $ref ?: $unit['unit_no']),
                ]);
            }

            $type = match ($newStatus) {
                'Sold'      => 'Sold',
                'Delivered' => 'Delivered',
                'Reserved'  => 'Reserved',
                'Returned'  => 'Returned',
                'Damaged'   => 'Damaged',
                'Cancelled' => 'Cancelled',
                default     => 'Adjustment',
            };
            $qtyChange = in_array($newStatus, ['Sold', 'Delivered', 'Damaged', 'Cancelled'], true) ? -1
                : (($old === 'Available' && $newStatus === 'Reserved') ? -1
                : (($newStatus === 'Available' || $newStatus === 'Returned') ? 1 : 0));
            fr_move($conn, $fid, (int)$unit['product_id'], $unitId, $type, $qtyChange,
                ['reason' => $reason, 'reference' => $ref, 'performed_by' => $by, 'remarks' => $rem]);

            // keep the vendor's history in sync for stock that came from a vendor
            if (!empty($unit['vendor_id']) && function_exists('frv_history_add')) {
                frv_history_add($conn, $fid, (int)$unit['vendor_id'], 'stock_change',
                    $unit['unit_no'] . ' (' . ($unit['product_name'] ?? 'unit') . '): ' . $old . ' → ' . $newStatus, $by);
            }

            return ['ok' => true, 'message' => 'Unit marked ' . $newStatus . '.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('fr_values_set')) {
    /** upsert EAV values. $table = 'fr_unit_values' (needs unit_id) or 'fr_product_values'. */
    function fr_values_set(mysqli $conn, string $table, int $ownerId, int $productId, array $values): void
    {
        foreach ($values as $key => $val) {
            $key = substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string)$key)), 0, 60);
            if ($key === '') continue;
            $val = trim((string)$val);
            try {
                if ($table === 'fr_unit_values') {
                    $s = $conn->prepare("INSERT INTO fr_unit_values (unit_id, product_id, field_key, field_value, updated_at)
                        VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = NOW()");
                    $s->bind_param('iiss', $ownerId, $productId, $key, $val);
                } else {
                    $s = $conn->prepare("INSERT INTO fr_product_values (product_id, field_key, field_value, updated_at)
                        VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = NOW()");
                    $s->bind_param('iss', $productId, $key, $val);
                }
                $s->execute();
                $s->close();
            } catch (\Throwable $e) { /* skip bad key */ }
        }
    }
}

if (!function_exists('fr_unit_values_get')) {
    function fr_unit_values_get(mysqli $conn, int $unitId): array
    {
        $out = [];
        $s = $conn->prepare("SELECT field_key, field_value FROM fr_unit_values WHERE unit_id = ?");
        $s->bind_param('i', $unitId);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[$x['field_key']] = $x['field_value'];
        $s->close();
        return $out;
    }
}

/* ---------------- non-serialized quantity moves ---------------- */
if (!function_exists('fr_nonserial_move')) {
    /** $action: sell | reserve | unreserve | return | damage | adjust  ($qty>0; adjust may be +/-) */
    function fr_nonserial_move(mysqli $conn, int $fid, int $productId, string $action, int $qty, array $meta, string $by): array
    {
        $p = fr_product_get($conn, $fid, $productId);
        if (!$p) return ['ok' => false, 'message' => 'Product not found.'];
        if ((int)$p['is_serialized'] === 1) return ['ok' => false, 'message' => 'This product is unit-tracked; use unit status changes.'];

        $a = (int)$p['qty_available']; $r = (int)$p['qty_reserved']; $s = (int)$p['qty_sold'];
        $rt = (int)$p['qty_returned']; $dm = (int)$p['qty_damaged'];
        $reason = trim((string)($meta['reason'] ?? ''));
        $ref    = trim((string)($meta['reference'] ?? ''));
        $rem    = trim((string)($meta['remarks'] ?? ''));
        $type = 'Adjustment'; $qtyChange = 0;

        switch (strtolower($action)) {
            case 'sell':
                if ($qty < 1 || $qty > $a) return ['ok' => false, 'message' => 'Not enough available stock.'];
                $a -= $qty; $s += $qty; $type = 'Sold'; $qtyChange = -$qty; break;
            case 'reserve':
                if ($qty < 1 || $qty > $a) return ['ok' => false, 'message' => 'Not enough available stock.'];
                $a -= $qty; $r += $qty; $type = 'Reserved'; $qtyChange = -$qty; break;
            case 'unreserve':
                if ($qty < 1 || $qty > $r) return ['ok' => false, 'message' => 'Not enough reserved stock.'];
                $r -= $qty; $a += $qty; $type = 'Unreserved'; $qtyChange = $qty; break;
            case 'return':
                if ($qty < 1) return ['ok' => false, 'message' => 'Quantity required.'];
                $rt += $qty; $a += $qty; $s = max(0, $s - $qty); $type = 'Returned'; $qtyChange = $qty; break;
            case 'damage':
                if ($qty < 1 || $qty > $a) return ['ok' => false, 'message' => 'Not enough available stock.'];
                $a -= $qty; $dm += $qty; $type = 'Damaged'; $qtyChange = -$qty; break;
            case 'adjust':
                if ($qty === 0) return ['ok' => false, 'message' => 'Adjustment cannot be zero.'];
                if ($a + $qty < 0) return ['ok' => false, 'message' => 'Adjustment would make available stock negative.'];
                $a += $qty; $type = 'Adjustment'; $qtyChange = $qty;
                if ($reason === '') return ['ok' => false, 'message' => 'A reason is required for manual adjustment.'];
                break;
            default:
                return ['ok' => false, 'message' => 'Unknown action.'];
        }

        try {
            $u = $conn->prepare("UPDATE fr_products SET qty_available=?, qty_reserved=?, qty_sold=?, qty_returned=?, qty_damaged=? WHERE id=? AND franchise_id=?");
            $u->bind_param('iiiiiii', $a, $r, $s, $rt, $dm, $productId, $fid);
            $u->execute();
            $u->close();
            fr_move($conn, $fid, $productId, null, $type, $qtyChange, ['balance_after' => $a, 'reason' => $reason, 'reference' => $ref, 'performed_by' => $by, 'remarks' => $rem]);

            // Customer purchase record + showroom commission on a quantity sale
            if (strtolower($action) === 'sell') {
                $unitPrice = round((float)($meta['unit_price'] ?? $p['price']), 2);
                $discount  = max(0.0, round((float)($meta['discount'] ?? 0), 2));
                $saleTotal = max(0.0, ($unitPrice * $qty) - $discount);
                $saleInvoice = trim((string)($meta['invoice_no'] ?? ''));
                if (function_exists('frc_purchase_add')) {
                    $addRes = frc_purchase_add($conn, $fid, (int)($meta['customer_ref'] ?? 0), [
                        'franchise_code'  => (string)($meta['franchise_code'] ?? ''),
                        'customer_name'   => trim((string)($meta['customer_name'] ?? '')),
                        'product_id'      => $productId,
                        'unit_id'         => 0,
                        'product_name'    => (string)$p['name'],
                        'model'           => (string)$p['model'],
                        'quantity'        => $qty,
                        'unit_price'      => $unitPrice,
                        'discount'        => $discount,
                        'sale_amount'     => $saleTotal,
                        'payment_method'  => (string)($meta['payment_method'] ?? ''),
                        'invoice_no'      => $saleInvoice,
                        'sale_ref'        => $ref,
                        'sale_date'       => trim((string)($meta['sale_date'] ?? date('Y-m-d'))),
                        'payment_status'  => (string)($meta['payment_status'] ?? 'Paid'),
                        'amount_paid'     => $meta['amount_paid'] ?? null,
                        'delivery_status' => (string)($meta['delivery_status'] ?? 'Pending'),
                        'sale_status'     => (string)($meta['sale_status'] ?? 'Completed'),
                        'remarks'         => $rem,
                    ], $by);
                    if (!empty($addRes['invoice_no'])) $saleInvoice = $addRes['invoice_no'];
                }
                if ($saleTotal > 0 && function_exists('frf_credit_commission')) {
                    frf_credit_commission($conn, (string)($meta['franchise_code'] ?? ''), (string)($meta['franchise_type'] ?? 'state'), $saleTotal, ['sale_type' => 'SALE', 'reference' => ($saleInvoice ?: $ref ?: $p['name'])]);
                }
            }
            return ['ok' => true, 'message' => ucfirst($action) . ' recorded.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Move failed: ' . $e->getMessage()];
        }
    }
}

/* ---------------- search ---------------- */
if (!function_exists('fr_search_units')) {
    /** Search this franchise's units by unit no, product name/model, or ANY custom/identifier value. */
    function fr_search_units(mysqli $conn, int $fid, string $q, int $limit = 100): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $like = '%' . $q . '%';
        $limit = max(1, min(500, $limit));
        $sql = "SELECT DISTINCT u.id, u.unit_no, u.status, u.customer_name, u.sale_date, u.received_date,
                       p.id AS product_id, p.name AS product_name, p.model
                FROM fr_units u
                JOIN fr_products p ON p.id = u.product_id
                LEFT JOIN fr_unit_values v ON v.unit_id = u.id
                WHERE u.franchise_id = ?
                  AND ( u.unit_no LIKE ? OR p.name LIKE ? OR p.model LIKE ? OR v.field_value LIKE ? )
                ORDER BY u.id DESC
                LIMIT " . $limit;
        $s = $conn->prepare($sql);
        $s->bind_param('issss', $fid, $like, $like, $like, $like);
        $s->execute();
        $r = $s->get_result();
        $out = [];
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

/* ---------------- traceability ---------------- */
if (!function_exists('fr_unit_history')) {
    function fr_unit_history(mysqli $conn, int $fid, int $unitId): array
    {
        $unit = fr_unit_get($conn, $fid, $unitId);
        if (!$unit) return [];
        $entry = null;
        if (!empty($unit['stock_entry_id'])) {
            $s = $conn->prepare("SELECT * FROM fr_stock_entries WHERE id = ? AND franchise_id = ? LIMIT 1");
            $s->bind_param('ii', $unit['stock_entry_id'], $fid);
            $s->execute();
            $entry = $s->get_result()->fetch_assoc() ?: null;
            $s->close();
        }
        $moves = [];
        $s = $conn->prepare("SELECT * FROM fr_stock_movements WHERE unit_id = ? AND franchise_id = ? ORDER BY id ASC");
        $s->bind_param('ii', $unitId, $fid);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $moves[] = $x;
        $s->close();

        return [
            'unit'   => $unit,
            'values' => fr_unit_values_get($conn, $unitId),
            'entry'  => $entry,
            'moves'  => $moves,
        ];
    }
}
