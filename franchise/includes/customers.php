<?php
/**
 * Franchise · Customer / Buyer management engine.
 * Every function is scoped by the authenticated $fid (franchise PK from session).
 * fr_customer_purchases is the purchase-history foundation — no commission /
 * cashback / turnover logic lives here.
 */

if (!function_exists('frc_tbl')) {
    function frc_tbl(mysqli $c, string $t): bool
    {
        $t = $c->real_escape_string($t);
        $r = $c->query("SHOW TABLES LIKE '$t'");
        return $r instanceof mysqli_result && $r->num_rows > 0;
    }
}

if (!function_exists('frc_ready')) {
    function frc_ready(mysqli $c): bool
    {
        return frc_tbl($c, 'fr_customers') && frc_tbl($c, 'fr_customer_fields') && frc_tbl($c, 'fr_customer_purchases');
    }
}

if (!function_exists('frc_slug')) {
    function frc_slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim((string)substr((string)$s, 0, 60), '_');
    }
}

if (!function_exists('frc_history_add')) {
    function frc_history_add(mysqli $conn, int $fid, int $customerId, string $action, string $detail, string $by): void
    {
        if (!frc_tbl($conn, 'fr_customer_history') || $customerId <= 0) return;
        try {
            $detail = substr($detail, 0, 500);
            $s = $conn->prepare("INSERT INTO fr_customer_history (customer_id, franchise_id, action, detail, performed_by, created_at)
                                 VALUES (?,?,?,?,?,NOW())");
            $s->bind_param('iisss', $customerId, $fid, $action, $detail, $by);
            $s->execute();
            $s->close();
        } catch (\Throwable $e) { /* history is best-effort */ }
    }
}

if (!function_exists('frc_history')) {
    function frc_history(mysqli $conn, int $fid, int $customerId, int $limit = 150): array
    {
        $out = [];
        if (!frc_tbl($conn, 'fr_customer_history')) return $out;
        $limit = max(1, min(500, $limit));
        $s = $conn->prepare("SELECT * FROM fr_customer_history WHERE customer_id = ? AND franchise_id = ? ORDER BY id DESC LIMIT " . $limit);
        $s->bind_param('ii', $customerId, $fid);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

/* ---------------- customer CRUD ---------------- */
if (!function_exists('frc_customer_get')) {
    function frc_customer_get(mysqli $conn, int $fid, int $customerId): ?array
    {
        if ($customerId <= 0) return null;
        $s = $conn->prepare("SELECT * FROM fr_customers WHERE id = ? AND franchise_id = ? LIMIT 1");
        $s->bind_param('ii', $customerId, $fid);
        $s->execute();
        $row = $s->get_result()->fetch_assoc() ?: null;
        $s->close();
        return $row;
    }
}

if (!function_exists('frc_next_code')) {
    function frc_next_code(mysqli $conn, int $fid, string $fcode): string
    {
        $n = (int)($conn->query("SELECT COUNT(*) c FROM fr_customers WHERE franchise_id = " . $fid)->fetch_assoc()['c'] ?? 0) + 1;
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', $fcode ?: ('FR' . $fid));
        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . '-C' . str_pad((string)($n + $i), 4, '0', STR_PAD_LEFT);
            $exists = (int)($conn->query("SELECT COUNT(*) c FROM fr_customers WHERE franchise_id = " . $fid . " AND customer_code = '" . $conn->real_escape_string($code) . "'")->fetch_assoc()['c'] ?? 0);
            if (!$exists) return $code;
        }
        return $prefix . '-C' . time();
    }
}

if (!function_exists('frc_find_duplicates')) {
    /** Possible duplicate customers by mobile or email. Never merges — just informs. */
    function frc_find_duplicates(mysqli $conn, int $fid, string $mobile, string $email, int $excludeId = 0): array
    {
        $mobile = trim($mobile);
        $email  = trim($email);
        if ($mobile === '' && $email === '') return [];

        $cond = [];
        $types = 'i';
        $par = [$fid];
        if ($mobile !== '') { $cond[] = 'mobile = ?';        $types .= 's'; $par[] = $mobile; }
        if ($email !== '')  { $cond[] = 'LOWER(email) = ?';   $types .= 's'; $par[] = strtolower($email); }
        $sql = "SELECT id, customer_code, customer_name, mobile, email, status FROM fr_customers
                WHERE franchise_id = ? AND (" . implode(' OR ', $cond) . ")";
        if ($excludeId > 0) { $sql .= ' AND id <> ?'; $types .= 'i'; $par[] = $excludeId; }
        $sql .= ' LIMIT 20';
        $s = $conn->prepare($sql);
        $s->bind_param($types, ...$par);
        $s->execute();
        $r = $s->get_result();
        $out = [];
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_customer_save')) {
    /**
     * @return array ok, id, message, duplicates[]  (duplicates are informational only)
     */
    function frc_customer_save(mysqli $conn, int $fid, string $fcode, array $d, string $by, int $customerId = 0): array
    {
        $name = trim((string)($d['customer_name'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'Customer name is required.', 'duplicates' => []];

        $mobile = trim((string)($d['mobile'] ?? ''));
        $email  = trim((string)($d['email'] ?? ''));
        $dob    = trim((string)($d['dob'] ?? ''));
        $dob    = ($dob !== '' && $dob !== '0000-00-00') ? $dob : null;

        $f = [
            'address'  => trim((string)($d['address'] ?? '')),
            'state'    => trim((string)($d['state'] ?? '')),
            'city'     => trim((string)($d['city'] ?? '')),
            'district' => trim((string)($d['district'] ?? '')),
            'pincode'  => trim((string)($d['pincode'] ?? '')),
            'country'  => trim((string)($d['country'] ?? 'India')) ?: 'India',
            'remarks'  => trim((string)($d['remarks'] ?? '')),
        ];
        $status = trim((string)($d['status'] ?? 'Active'));
        if (!in_array($status, ['Active', 'Inactive'], true)) $status = 'Active';

        $dupes = frc_find_duplicates($conn, $fid, $mobile, $email, $customerId);

        try {
            if ($customerId > 0) {
                if (!frc_customer_get($conn, $fid, $customerId)) return ['ok' => false, 'message' => 'Customer not found.', 'duplicates' => []];
                $s = $conn->prepare("UPDATE fr_customers SET
                        customer_name=?, mobile=?, email=?, dob=?, address=?, state=?, city=?, district=?, pincode=?, country=?, status=?, remarks=?
                    WHERE id=? AND franchise_id=?");
                $s->bind_param('ssssssssssssii',
                    $name, $mobile, $email, $dob, $f['address'], $f['state'], $f['city'], $f['district'],
                    $f['pincode'], $f['country'], $status, $f['remarks'], $customerId, $fid);
                $s->execute();
                $s->close();
                frc_history_add($conn, $fid, $customerId, 'updated', 'Customer details updated.', $by);
                return ['ok' => true, 'id' => $customerId, 'message' => 'Customer updated.', 'duplicates' => $dupes];
            }

            $code = frc_next_code($conn, $fid, $fcode);
            $today = date('Y-m-d');
            $s = $conn->prepare("INSERT INTO fr_customers
                    (franchise_id, franchise_code, customer_code, customer_name, mobile, email, dob, address, state, city, district, pincode, country, status, remarks, registered_date, created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $s->bind_param('issssssssssssssss',
                $fid, $fcode, $code, $name, $mobile, $email, $dob, $f['address'], $f['state'], $f['city'],
                $f['district'], $f['pincode'], $f['country'], $status, $f['remarks'], $today, $by);
            $s->execute();
            $newId = (int)$s->insert_id;
            $s->close();
            frc_history_add($conn, $fid, $newId, 'created', 'Customer "' . $name . '" added (' . $code . ').', $by);
            return ['ok' => true, 'id' => $newId, 'message' => 'Customer added.', 'code' => $code, 'duplicates' => $dupes];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Save failed: ' . $e->getMessage(), 'duplicates' => []];
        }
    }
}

if (!function_exists('frc_customer_set_status')) {
    function frc_customer_set_status(mysqli $conn, int $fid, int $customerId, string $status, string $by): array
    {
        $status = ucfirst(strtolower(trim($status)));
        if (!in_array($status, ['Active', 'Inactive'], true)) return ['ok' => false, 'message' => 'Invalid status.'];
        $cst = frc_customer_get($conn, $fid, $customerId);
        if (!$cst) return ['ok' => false, 'message' => 'Customer not found.'];
        if ($cst['status'] === $status) return ['ok' => true, 'message' => 'No change.'];
        $s = $conn->prepare("UPDATE fr_customers SET status = ? WHERE id = ? AND franchise_id = ?");
        $s->bind_param('sii', $status, $customerId, $fid);
        $s->execute();
        $s->close();
        frc_history_add($conn, $fid, $customerId, 'status_changed', $cst['status'] . ' → ' . $status, $by);
        return ['ok' => true, 'message' => 'Customer marked ' . $status . '.'];
    }
}

if (!function_exists('frc_active_customers')) {
    function frc_active_customers(mysqli $conn, int $fid): array
    {
        $out = [];
        if (!frc_tbl($conn, 'fr_customers')) return $out;
        $s = $conn->prepare("SELECT id, customer_code, customer_name, mobile FROM fr_customers WHERE franchise_id = ? AND status = 'Active' ORDER BY customer_name ASC");
        $s->bind_param('i', $fid);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

/* ---------------- custom fields (franchise-wide) ---------------- */
if (!function_exists('frc_fields_list')) {
    function frc_fields_list(mysqli $conn, int $fid): array
    {
        $out = [];
        if (!frc_tbl($conn, 'fr_customer_fields')) return $out;
        $s = $conn->prepare("SELECT * FROM fr_customer_fields WHERE franchise_id = ? ORDER BY sort_order ASC, id ASC");
        $s->bind_param('i', $fid);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_field_add')) {
    function frc_field_add(mysqli $conn, int $fid, string $label, string $type = 'text', bool $searchable = true): array
    {
        $label = trim($label);
        if ($label === '') return ['ok' => false, 'message' => 'Field label is required.'];
        $key = frc_slug($label);
        if ($key === '') return ['ok' => false, 'message' => 'Field label must contain letters or numbers.'];
        $type = in_array($type, ['text', 'number', 'date'], true) ? $type : 'text';
        try {
            $ord = (int)($conn->query("SELECT COALESCE(MAX(sort_order),0)+1 n FROM fr_customer_fields WHERE franchise_id = " . $fid)->fetch_assoc()['n'] ?? 1);
            $se  = $searchable ? 1 : 0;
            $s = $conn->prepare("INSERT INTO fr_customer_fields (franchise_id, field_key, field_label, field_type, is_searchable, sort_order, created_at)
                                 VALUES (?,?,?,?,?,?,NOW())");
            $s->bind_param('isssii', $fid, $key, $label, $type, $se, $ord);
            $s->execute();
            $s->close();
            return ['ok' => true, 'field_key' => $key, 'message' => 'Custom field added.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => (strpos($e->getMessage(), 'uniq_cfield') !== false) ? 'A field with this name already exists.' : $e->getMessage()];
        }
    }
}

if (!function_exists('frc_field_delete')) {
    function frc_field_delete(mysqli $conn, int $fid, int $fieldId): array
    {
        $s = $conn->prepare("SELECT field_key FROM fr_customer_fields WHERE id = ? AND franchise_id = ? LIMIT 1");
        $s->bind_param('ii', $fieldId, $fid);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row) return ['ok' => false, 'message' => 'Field not found.'];
        $conn->query("DELETE FROM fr_customer_fields WHERE id = " . (int)$fieldId . " AND franchise_id = " . $fid);
        $k = $conn->real_escape_string($row['field_key']);
        $conn->query("DELETE FROM fr_customer_values WHERE franchise_id = " . $fid . " AND field_key = '$k'");
        return ['ok' => true, 'message' => 'Field removed.'];
    }
}

if (!function_exists('frc_values_get')) {
    function frc_values_get(mysqli $conn, int $customerId): array
    {
        $out = [];
        if (!frc_tbl($conn, 'fr_customer_values')) return $out;
        $s = $conn->prepare("SELECT field_key, field_value FROM fr_customer_values WHERE customer_id = ?");
        $s->bind_param('i', $customerId);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[$x['field_key']] = $x['field_value'];
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_values_set')) {
    function frc_values_set(mysqli $conn, int $fid, int $customerId, array $values): void
    {
        if (!frc_customer_get($conn, $fid, $customerId)) return;
        $allowed = [];
        foreach (frc_fields_list($conn, $fid) as $f) $allowed[$f['field_key']] = true;
        foreach ($values as $key => $val) {
            $key = frc_slug((string)$key);
            if ($key === '' || !isset($allowed[$key])) continue;
            $val = trim((string)$val);
            try {
                $s = $conn->prepare("INSERT INTO fr_customer_values (customer_id, franchise_id, field_key, field_value, updated_at)
                                     VALUES (?,?,?,?,NOW())
                                     ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = NOW()");
                $s->bind_param('iiss', $customerId, $fid, $key, $val);
                $s->execute();
                $s->close();
            } catch (\Throwable $e) { /* skip bad key */ }
        }
    }
}

/* ---------------- customer list / search ---------------- */
if (!function_exists('frc_customer_list')) {
    /** @param array $opts  q => term, status => 'Active'|'Inactive'|'', active_only => bool */
    function frc_customer_list(mysqli $conn, int $fid, array $opts = []): array
    {
        $q          = trim((string)($opts['q'] ?? ''));
        $status     = trim((string)($opts['status'] ?? ''));
        $activeOnly = !empty($opts['active_only']);

        $where = 'c.franchise_id = ?';
        $types = 'i';
        $par   = [$fid];

        if ($activeOnly) {
            $where .= " AND c.status = 'Active'";
        } elseif ($status !== '') {
            $where .= ' AND c.status = ?';
            $types .= 's';
            $par[]  = $status;
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= " AND (
                c.customer_name LIKE ? OR c.mobile LIKE ? OR c.email LIKE ? OR c.customer_code LIKE ?
                OR c.id IN (SELECT customer_id FROM fr_customer_values WHERE franchise_id = ? AND field_value LIKE ?)
                OR c.id IN (SELECT customer_id FROM fr_customer_purchases WHERE franchise_id = ? AND (invoice_no LIKE ? OR sale_ref LIKE ? OR product_name LIKE ?))
                OR c.id IN (SELECT u.customer_ref FROM fr_units u WHERE u.franchise_id = ? AND u.customer_ref IS NOT NULL
                            AND ( u.unit_no LIKE ? OR u.id IN (SELECT unit_id FROM fr_unit_values WHERE field_value LIKE ?) ))
            )";
            $types .= 'ssss' . 'is' . 'isss' . 'iss';
            array_push($par, $like, $like, $like, $like, $fid, $like, $fid, $like, $like, $like, $fid, $like, $like);
        }

        $sql = "SELECT c.* FROM fr_customers c WHERE $where ORDER BY c.customer_name ASC LIMIT 500";
        $s = $conn->prepare($sql);
        $s->bind_param($types, ...$par);
        $s->execute();
        $r = $s->get_result();
        $out = [];
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

/* ---------------- purchases ---------------- */
if (!function_exists('frc_purchase_add')) {
    /**
     * Record one sale line. This is the franchise's canonical sales table.
     * Called by the sale hooks (serialized unit → Sold, non-serialized "sell").
     * $customerId <= 0 means a walk-in sale (no linked customer record).
     */
    function frc_purchase_add(mysqli $conn, int $fid, int $customerId, array $d, string $by): array
    {
        if (!frc_tbl($conn, 'fr_customer_purchases')) return ['ok' => false, 'message' => 'purchases table missing'];

        $custName = trim((string)($d['customer_name'] ?? ''));
        if ($customerId > 0) {
            $crow = frc_customer_get($conn, $fid, $customerId);
            if (!$crow) return ['ok' => false, 'message' => 'Customer not found for this franchise.'];
            if ($custName === '') $custName = (string)$crow['customer_name'];
        } else {
            $customerId = 0; // walk-in
        }
        $custIdParam = $customerId > 0 ? $customerId : null;

        $productId = (int)($d['product_id'] ?? 0) ?: null;
        $unitId    = (int)($d['unit_id'] ?? 0) ?: null;
        $pname     = trim((string)($d['product_name'] ?? ''));
        $model     = trim((string)($d['model'] ?? ''));
        $qty       = max(1, (int)($d['quantity'] ?? 1));
        $unitPrice = round((float)($d['unit_price'] ?? 0), 2);
        $discount  = max(0.0, round((float)($d['discount'] ?? 0), 2));
        $amount    = round((float)($d['sale_amount'] ?? (($unitPrice * $qty) - $discount)), 2);
        if ($amount < 0) $amount = 0.0;
        $invoice   = trim((string)($d['invoice_no'] ?? ''));
        $saleRef   = trim((string)($d['sale_ref'] ?? ''));
        $saleDate  = trim((string)($d['sale_date'] ?? date('Y-m-d')));
        $saleDate  = ($saleDate !== '' && $saleDate !== '0000-00-00') ? $saleDate : date('Y-m-d');
        $pay       = trim((string)($d['payment_status'] ?? 'Paid')) ?: 'Paid';
        $del       = trim((string)($d['delivery_status'] ?? 'Pending')) ?: 'Pending';
        $payMethod = trim((string)($d['payment_method'] ?? ''));
        $paidAmt   = array_key_exists('amount_paid', $d) ? max(0.0, round((float)$d['amount_paid'], 2)) : ($pay === 'Paid' ? $amount : 0.0);
        $saleStat  = trim((string)($d['sale_status'] ?? 'Completed')) ?: 'Completed';
        $rem       = trim((string)($d['remarks'] ?? ''));

        // auto invoice number when none supplied
        if ($invoice === '' && function_exists('frc_next_invoice_no')) {
            $fcodeArg = trim((string)($d['franchise_code'] ?? '')) ?: ('FR' . $fid);
            $invoice  = frc_next_invoice_no($conn, $fid, $fcodeArg, $saleDate);
        }

        try {
            $s = $conn->prepare("INSERT INTO fr_customer_purchases
                (franchise_id, customer_id, customer_name, product_id, unit_id, product_name, model, quantity, unit_price, discount, sale_amount,
                 payment_method, invoice_no, sale_ref, sale_date, payment_status, amount_paid, delivery_status, sale_status, remarks, created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $s->bind_param('iisiissidddsssssdssss',
                $fid, $custIdParam, $custName, $productId, $unitId, $pname, $model, $qty, $unitPrice, $discount, $amount,
                $payMethod, $invoice, $saleRef, $saleDate, $pay, $paidAmt, $del, $saleStat, $rem, $by);
            $s->execute();
            $pid = (int)$s->insert_id;
            $s->close();
            if ($customerId > 0) {
                frc_history_add($conn, $fid, $customerId, 'purchase',
                    trim($qty . ' × ' . ($pname ?: 'product') . ($invoice !== '' ? ' · inv ' . $invoice : '') . ' · ₹' . number_format($amount, 2)), $by);
            }
            return ['ok' => true, 'id' => $pid, 'invoice_no' => $invoice, 'message' => 'Sale recorded.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

/* ---------------- auto invoice numbering (per franchise, per FY) ---------------- */
if (!function_exists('frc_fy_label')) {
    /** Indian financial year label for a date, e.g. '26-27' (FY starts 1 Apr). */
    function frc_fy_label(?string $date = null): string
    {
        $ts = $date ? strtotime($date) : time();
        if (!$ts) $ts = time();
        $y = (int)date('Y', $ts);
        $m = (int)date('n', $ts);
        $start = $m >= 4 ? $y : $y - 1;
        return sprintf('%02d-%02d', $start % 100, ($start + 1) % 100);
    }
}

if (!function_exists('frc_next_invoice_no')) {
    /**
     * Next gap-free invoice number for this franchise + financial year.
     * Format: <CODE>/<YY-YY>/<0001>  (atomic via LAST_INSERT_ID trick).
     */
    function frc_next_invoice_no(mysqli $conn, int $fid, string $fcode, ?string $onDate = null): string
    {
        $fy  = frc_fy_label($onDate);
        $pfx = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $fcode ?: ('FR' . $fid)));
        if ($pfx === '') $pfx = 'FR' . $fid;
        $pfx = substr($pfx, 0, 6);

        $seq = 0;
        try {
            if (frc_tbl($conn, 'fr_invoice_counters')) {
                $s = $conn->prepare("INSERT INTO fr_invoice_counters (franchise_id, fy, last_no)
                                     VALUES (?, ?, LAST_INSERT_ID(1))
                                     ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1)");
                $s->bind_param('is', $fid, $fy);
                $s->execute();
                $seq = (int)$conn->insert_id;
                $s->close();
                if ($seq <= 0) {
                    $r = $conn->prepare("SELECT last_no FROM fr_invoice_counters WHERE franchise_id = ? AND fy = ?");
                    $r->bind_param('is', $fid, $fy);
                    $r->execute();
                    $seq = (int)($r->get_result()->fetch_assoc()['last_no'] ?? 1);
                    $r->close();
                }
            } else {
                $seq = 1 + (int)($conn->query("SELECT COUNT(*) c FROM fr_customer_purchases WHERE franchise_id = " . (int)$fid)->fetch_assoc()['c'] ?? 0);
            }
        } catch (\Throwable $e) {
            $seq = (int)(time() % 100000);
        }

        return $pfx . '/' . $fy . '/' . str_pad((string)max(1, $seq), 4, '0', STR_PAD_LEFT);
    }
}

/* ---------------- sales list / summary / turnover ---------------- */
if (!function_exists('frc_sales_where')) {
    /** builds the shared WHERE for the sales queries. returns [sql, types, params] */
    function frc_sales_where(int $fid, array $o): array
    {
        $w = ['cp.franchise_id = ?']; $t = 'i'; $p = [$fid];
        $from = trim((string)($o['from'] ?? ''));
        $to   = trim((string)($o['to'] ?? ''));
        $pay  = trim((string)($o['payment'] ?? ''));
        $del  = trim((string)($o['delivery'] ?? ''));
        $q    = trim((string)($o['q'] ?? ''));
        if ($from !== '') { $w[] = 'cp.sale_date >= ?'; $t .= 's'; $p[] = $from; }
        if ($to   !== '') { $w[] = 'cp.sale_date <= ?'; $t .= 's'; $p[] = $to; }
        if ($pay  !== '') { $w[] = 'cp.payment_status = ?'; $t .= 's'; $p[] = $pay; }
        if ($del  !== '') { $w[] = 'cp.delivery_status = ?'; $t .= 's'; $p[] = $del; }
        if ($q    !== '') {
            $like = '%' . $q . '%';
            $w[] = '(cp.product_name LIKE ? OR cp.model LIKE ? OR cp.invoice_no LIKE ? OR cp.sale_ref LIKE ? OR cp.customer_name LIKE ? OR c.customer_name LIKE ? OR c.mobile LIKE ?)';
            $t .= 'sssssss';
            array_push($p, $like, $like, $like, $like, $like, $like, $like);
        }
        return ['WHERE ' . implode(' AND ', $w), $t, $p];
    }
}

if (!function_exists('frc_sales_list')) {
    function frc_sales_list(mysqli $conn, int $fid, array $o = [], int $limit = 500): array
    {
        $out = [];
        if (!frc_tbl($conn, 'fr_customer_purchases')) return $out;
        [$where, $t, $p] = frc_sales_where($fid, $o);
        $limit = max(1, min(2000, $limit));
        $sql = "SELECT cp.*, c.customer_code, c.mobile AS customer_mobile,
                       COALESCE(NULLIF(c.customer_name,''), cp.customer_name) AS buyer_name,
                       pr.name AS current_product_name, pr.model AS current_model,
                       u.unit_no, u.status AS unit_status
                FROM fr_customer_purchases cp
                LEFT JOIN fr_customers c ON c.id = cp.customer_id
                LEFT JOIN fr_products  pr ON pr.id = cp.product_id
                LEFT JOIN fr_units     u ON u.id = cp.unit_id
                $where
                ORDER BY cp.sale_date DESC, cp.id DESC
                LIMIT $limit";
        $s = $conn->prepare($sql);
        $s->bind_param($t, ...$p);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_sales_summary')) {
    function frc_sales_summary(mysqli $conn, int $fid, array $o = []): array
    {
        $res = ['sales' => 0, 'units' => 0, 'turnover' => 0.0, 'paid' => 0.0, 'pending_amt' => 0.0,
                'pending_payment' => 0, 'pending_delivery' => 0];
        if (!frc_tbl($conn, 'fr_customer_purchases')) return $res;
        [$where, $t, $p] = frc_sales_where($fid, $o);
        $sql = "SELECT COUNT(*) sales, COALESCE(SUM(cp.quantity),0) units,
                       COALESCE(SUM(cp.sale_amount),0) turnover,
                       COALESCE(SUM(CASE WHEN cp.payment_status = 'Paid' THEN cp.sale_amount ELSE 0 END),0) paid,
                       COALESCE(SUM(CASE WHEN cp.payment_status <> 'Paid' THEN cp.sale_amount ELSE 0 END),0) pending_amt,
                       COALESCE(SUM(cp.payment_status <> 'Paid'),0) pending_payment,
                       COALESCE(SUM(cp.delivery_status <> 'Delivered'),0) pending_delivery
                FROM fr_customer_purchases cp
                LEFT JOIN fr_customers c ON c.id = cp.customer_id
                $where";
        $s = $conn->prepare($sql);
        $s->bind_param($t, ...$p);
        $s->execute();
        $x = $s->get_result()->fetch_assoc();
        $s->close();
        if ($x) {
            $res['sales'] = (int)$x['sales'];   $res['units'] = (int)$x['units'];
            $res['turnover'] = (float)$x['turnover']; $res['paid'] = (float)$x['paid'];
            $res['pending_amt'] = (float)$x['pending_amt'];
            $res['pending_payment'] = (int)$x['pending_payment'];
            $res['pending_delivery'] = (int)$x['pending_delivery'];
        }
        return $res;
    }
}

if (!function_exists('frc_turnover_total')) {
    /** SUM(sale_amount) for a franchise (all time, or within optional from/to). */
    function frc_turnover_total(mysqli $conn, int $fid, array $o = []): float
    {
        if (!frc_tbl($conn, 'fr_customer_purchases')) return 0.0;
        [$where, $t, $p] = frc_sales_where($fid, $o);
        $s = $conn->prepare("SELECT COALESCE(SUM(cp.sale_amount),0) v FROM fr_customer_purchases cp
                             LEFT JOIN fr_customers c ON c.id = cp.customer_id $where");
        $s->bind_param($t, ...$p);
        $s->execute();
        $v = (float)($s->get_result()->fetch_assoc()['v'] ?? 0);
        $s->close();
        return $v;
    }
}

if (!function_exists('frc_turnover_by_month')) {
    function frc_turnover_by_month(mysqli $conn, int $fid, int $months = 12): array
    {
        $out = [];
        if (!frc_tbl($conn, 'fr_customer_purchases')) return $out;
        $months = max(1, min(60, $months));
        $s = $conn->prepare("SELECT DATE_FORMAT(sale_date, '%Y-%m') ym,
                                    COUNT(*) sales, COALESCE(SUM(quantity),0) units, COALESCE(SUM(sale_amount),0) turnover
                             FROM fr_customer_purchases
                             WHERE franchise_id = ? AND sale_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL ? MONTH)
                             GROUP BY ym ORDER BY ym DESC");
        $m = $months - 1;
        $s->bind_param('ii', $fid, $m);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_turnover_by_product')) {
    function frc_turnover_by_product(mysqli $conn, int $fid, array $o = [], int $limit = 20): array
    {
        $out = [];
        if (!frc_tbl($conn, 'fr_customer_purchases')) return $out;
        [$where, $t, $p] = frc_sales_where($fid, $o);
        $limit = max(1, min(100, $limit));
        $s = $conn->prepare("SELECT COALESCE(NULLIF(cp.product_name,''), 'Unspecified') pname, cp.model,
                                    COUNT(*) sales, COALESCE(SUM(cp.quantity),0) units, COALESCE(SUM(cp.sale_amount),0) turnover
                             FROM fr_customer_purchases cp
                             LEFT JOIN fr_customers c ON c.id = cp.customer_id
                             $where
                             GROUP BY pname, cp.model ORDER BY turnover DESC LIMIT $limit");
        $s->bind_param($t, ...$p);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_customer_purchases')) {
    /** Full purchase history for a customer (unit-tracked + quantity-only). */
    function frc_customer_purchases(mysqli $conn, int $fid, int $customerId): array
    {
        $out = [];
        if (!frc_tbl($conn, 'fr_customer_purchases')) return $out;
        $s = $conn->prepare("SELECT cp.*, p.name AS current_product_name, p.model AS current_model, p.is_serialized,
                                    u.unit_no, u.status AS unit_status, u.delivery_date AS unit_delivery_date
                             FROM fr_customer_purchases cp
                             LEFT JOIN fr_products p ON p.id = cp.product_id
                             LEFT JOIN fr_units u ON u.id = cp.unit_id
                             WHERE cp.franchise_id = ? AND cp.customer_id = ?
                             ORDER BY cp.sale_date DESC, cp.id DESC");
        $s->bind_param('ii', $fid, $customerId);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) {
            $x['identifiers'] = ($x['unit_id']) ? frc_unit_identifiers($conn, (int)$x['unit_id']) : [];
            $out[] = $x;
        }
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_unit_identifiers')) {
    function frc_unit_identifiers(mysqli $conn, int $unitId): array
    {
        $out = [];
        $s = $conn->prepare("SELECT v.field_key, v.field_value, f.field_label
                             FROM fr_unit_values v
                             JOIN fr_units u ON u.id = v.unit_id
                             LEFT JOIN fr_product_fields f ON f.product_id = u.product_id AND f.field_key = v.field_key
                             WHERE v.unit_id = ?");
        $s->bind_param('i', $unitId);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) {
            $label = $x['field_label'] ?: ucwords(str_replace('_', ' ', $x['field_key']));
            $out[$label] = $x['field_value'];
        }
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_customer_vehicles')) {
    /** Unit-tracked products currently linked to this customer (vehicles / items owned). */
    function frc_customer_vehicles(mysqli $conn, int $fid, int $customerId): array
    {
        $out = [];
        $s = $conn->prepare("SELECT u.id, u.unit_no, u.status, u.sale_date, u.delivery_date, u.invoice_no, u.sale_amount,
                                    p.name AS product_name, p.model
                             FROM fr_units u
                             JOIN fr_products p ON p.id = u.product_id
                             WHERE u.franchise_id = ? AND u.customer_ref = ?
                             ORDER BY u.sale_date DESC, u.id DESC");
        $s->bind_param('ii', $fid, $customerId);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) {
            $x['identifiers'] = frc_unit_identifiers($conn, (int)$x['id']);
            $out[] = $x;
        }
        $s->close();
        return $out;
    }
}

if (!function_exists('frc_customer_summary')) {
    function frc_customer_summary(mysqli $conn, int $fid, int $customerId): array
    {
        $o = ['purchases' => 0, 'total_value' => 0.0, 'vehicles' => 0, 'pending_payment' => 0, 'pending_delivery' => 0];
        if (!frc_tbl($conn, 'fr_customer_purchases')) return $o;
        $r = $conn->query("SELECT COUNT(*) n, COALESCE(SUM(sale_amount),0) v,
                                  SUM(payment_status <> 'Paid') pp, SUM(delivery_status <> 'Delivered') pd
                           FROM fr_customer_purchases WHERE franchise_id = " . $fid . " AND customer_id = " . $customerId)->fetch_assoc();
        if ($r) {
            $o['purchases'] = (int)$r['n'];
            $o['total_value'] = (float)$r['v'];
            $o['pending_payment'] = (int)$r['pp'];
            $o['pending_delivery'] = (int)$r['pd'];
        }
        $v = $conn->query("SELECT COUNT(*) n FROM fr_units WHERE franchise_id = " . $fid . " AND customer_ref = " . $customerId)->fetch_assoc();
        $o['vehicles'] = (int)($v['n'] ?? 0);
        return $o;
    }
}
