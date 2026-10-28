<?php
/**
 * Franchise · Vendor / Supplier management engine.
 * Every function is scoped by the authenticated $fid (franchise PK from session).
 * No commission / cashback / turnover logic lives here.
 */

if (!function_exists('frv_tbl')) {
    function frv_tbl(mysqli $c, string $t): bool
    {
        $t = $c->real_escape_string($t);
        $r = $c->query("SHOW TABLES LIKE '$t'");
        return $r instanceof mysqli_result && $r->num_rows > 0;
    }
}

if (!function_exists('frv_ready')) {
    function frv_ready(mysqli $c): bool
    {
        return frv_tbl($c, 'fr_vendors') && frv_tbl($c, 'fr_vendor_fields') && frv_tbl($c, 'fr_vendor_values');
    }
}

if (!function_exists('frv_slug')) {
    function frv_slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim((string)substr((string)$s, 0, 60), '_');
    }
}

if (!function_exists('frv_history_add')) {
    function frv_history_add(mysqli $conn, int $fid, int $vendorId, string $action, string $detail, string $by): void
    {
        if (!frv_tbl($conn, 'fr_vendor_history') || $vendorId <= 0) return;
        try {
            $s = $conn->prepare("INSERT INTO fr_vendor_history (vendor_id, franchise_id, action, detail, performed_by, created_at)
                                 VALUES (?,?,?,?,?,NOW())");
            $detail = substr($detail, 0, 500);
            $s->bind_param('iisss', $vendorId, $fid, $action, $detail, $by);
            $s->execute();
            $s->close();
        } catch (\Throwable $e) { /* history is best-effort */ }
    }
}

if (!function_exists('frv_history')) {
    function frv_history(mysqli $conn, int $fid, int $vendorId, int $limit = 100): array
    {
        $out = [];
        if (!frv_tbl($conn, 'fr_vendor_history')) return $out;
        $limit = max(1, min(500, $limit));
        $s = $conn->prepare("SELECT * FROM fr_vendor_history WHERE vendor_id = ? AND franchise_id = ? ORDER BY id DESC LIMIT " . $limit);
        $s->bind_param('ii', $vendorId, $fid);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

/* ---------------- vendor CRUD ---------------- */
if (!function_exists('frv_vendor_get')) {
    function frv_vendor_get(mysqli $conn, int $fid, int $vendorId): ?array
    {
        if ($vendorId <= 0) return null;
        $s = $conn->prepare("SELECT * FROM fr_vendors WHERE id = ? AND franchise_id = ? LIMIT 1");
        $s->bind_param('ii', $vendorId, $fid);
        $s->execute();
        $row = $s->get_result()->fetch_assoc() ?: null;
        $s->close();
        return $row;
    }
}

if (!function_exists('frv_vendor_list')) {
    /**
     * @param array $opts  q => search term, status => 'Active'|'Inactive'|'' (all), active_only => bool
     */
    function frv_vendor_list(mysqli $conn, int $fid, array $opts = []): array
    {
        $q          = trim((string)($opts['q'] ?? ''));
        $status     = trim((string)($opts['status'] ?? ''));
        $activeOnly = !empty($opts['active_only']);

        $where = 'v.franchise_id = ?';
        $types = 'i';
        $par   = [$fid];

        if ($activeOnly) {
            $where .= " AND v.status = 'Active'";
        } elseif ($status !== '') {
            $where .= ' AND v.status = ?';
            $types .= 's';
            $par[]  = $status;
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= " AND ( v.vendor_name LIKE ? OR v.company_name LIKE ? OR v.mobile LIKE ? OR v.gst_no LIKE ? OR v.email LIKE ?
                              OR v.id IN (SELECT vendor_id FROM fr_vendor_values WHERE franchise_id = ? AND field_value LIKE ?) )";
            $types .= 'sssss' . 'is';
            array_push($par, $like, $like, $like, $like, $like, $fid, $like);
        }

        $sql = "SELECT v.* FROM fr_vendors v WHERE $where ORDER BY v.vendor_name ASC LIMIT 500";
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

if (!function_exists('frv_active_vendors')) {
    function frv_active_vendors(mysqli $conn, int $fid): array
    {
        $out = [];
        if (!frv_tbl($conn, 'fr_vendors')) return $out;
        $s = $conn->prepare("SELECT id, vendor_name, company_name FROM fr_vendors WHERE franchise_id = ? AND status = 'Active' ORDER BY vendor_name ASC");
        $s->bind_param('i', $fid);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

if (!function_exists('frv_vendor_save')) {
    function frv_vendor_save(mysqli $conn, int $fid, string $fcode, array $d, string $by, int $vendorId = 0): array
    {
        $name = trim((string)($d['vendor_name'] ?? ''));
        if ($name === '') return ['ok' => false, 'message' => 'Vendor name is required.'];

        $f = [
            'company_name' => trim((string)($d['company_name'] ?? '')),
            'mobile'       => trim((string)($d['mobile'] ?? '')),
            'email'        => trim((string)($d['email'] ?? '')),
            'gst_no'       => trim((string)($d['gst_no'] ?? '')),
            'address'      => trim((string)($d['address'] ?? '')),
            'state'        => trim((string)($d['state'] ?? '')),
            'city'         => trim((string)($d['city'] ?? '')),
            'district'     => trim((string)($d['district'] ?? '')),
            'pincode'      => trim((string)($d['pincode'] ?? '')),
            'country'      => trim((string)($d['country'] ?? 'India')) ?: 'India',
            'remarks'      => trim((string)($d['remarks'] ?? '')),
        ];
        $status = trim((string)($d['status'] ?? 'Active'));
        if (!in_array($status, ['Active', 'Inactive'], true)) $status = 'Active';

        try {
            if ($vendorId > 0) {
                if (!frv_vendor_get($conn, $fid, $vendorId)) return ['ok' => false, 'message' => 'Vendor not found.'];
                $s = $conn->prepare("UPDATE fr_vendors SET
                        vendor_name=?, company_name=?, mobile=?, email=?, gst_no=?, address=?, state=?, city=?, district=?, pincode=?, country=?, status=?, remarks=?
                    WHERE id=? AND franchise_id=?");
                $s->bind_param('sssssssssssssii',
                    $name, $f['company_name'], $f['mobile'], $f['email'], $f['gst_no'], $f['address'],
                    $f['state'], $f['city'], $f['district'], $f['pincode'], $f['country'], $status, $f['remarks'],
                    $vendorId, $fid);
                $s->execute();
                $s->close();
                frv_history_add($conn, $fid, $vendorId, 'updated', 'Vendor details updated.', $by);
                return ['ok' => true, 'id' => $vendorId, 'message' => 'Vendor updated.'];
            }

            $s = $conn->prepare("INSERT INTO fr_vendors
                    (franchise_id, franchise_code, vendor_name, company_name, mobile, email, gst_no, address, state, city, district, pincode, country, status, remarks, created_by, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $s->bind_param('isssssssssssssss',
                $fid, $fcode, $name, $f['company_name'], $f['mobile'], $f['email'], $f['gst_no'], $f['address'],
                $f['state'], $f['city'], $f['district'], $f['pincode'], $f['country'], $status, $f['remarks'], $by);
            $s->execute();
            $newId = (int)$s->insert_id;
            $s->close();
            frv_history_add($conn, $fid, $newId, 'created', 'Vendor "' . $name . '" added.', $by);
            return ['ok' => true, 'id' => $newId, 'message' => 'Vendor added.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('frv_vendor_set_status')) {
    function frv_vendor_set_status(mysqli $conn, int $fid, int $vendorId, string $status, string $by): array
    {
        $status = ucfirst(strtolower(trim($status)));
        if (!in_array($status, ['Active', 'Inactive'], true)) return ['ok' => false, 'message' => 'Invalid status.'];
        $v = frv_vendor_get($conn, $fid, $vendorId);
        if (!$v) return ['ok' => false, 'message' => 'Vendor not found.'];
        if ($v['status'] === $status) return ['ok' => true, 'message' => 'No change.'];
        $s = $conn->prepare("UPDATE fr_vendors SET status = ? WHERE id = ? AND franchise_id = ?");
        $s->bind_param('sii', $status, $vendorId, $fid);
        $s->execute();
        $s->close();
        frv_history_add($conn, $fid, $vendorId, 'status_changed', $v['status'] . ' → ' . $status, $by);
        return ['ok' => true, 'message' => 'Vendor marked ' . $status . '.'];
    }
}

/* ---------------- custom fields (franchise-wide definitions) ---------------- */
if (!function_exists('frv_fields_list')) {
    function frv_fields_list(mysqli $conn, int $fid): array
    {
        $out = [];
        if (!frv_tbl($conn, 'fr_vendor_fields')) return $out;
        $s = $conn->prepare("SELECT * FROM fr_vendor_fields WHERE franchise_id = ? ORDER BY sort_order ASC, id ASC");
        $s->bind_param('i', $fid);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[] = $x;
        $s->close();
        return $out;
    }
}

if (!function_exists('frv_field_add')) {
    function frv_field_add(mysqli $conn, int $fid, string $label, string $type = 'text', bool $searchable = true): array
    {
        $label = trim($label);
        if ($label === '') return ['ok' => false, 'message' => 'Field label is required.'];
        $key = frv_slug($label);
        if ($key === '') return ['ok' => false, 'message' => 'Field label must contain letters or numbers.'];
        $type = in_array($type, ['text', 'number', 'date'], true) ? $type : 'text';
        try {
            $ord = (int)($conn->query("SELECT COALESCE(MAX(sort_order),0)+1 n FROM fr_vendor_fields WHERE franchise_id = " . $fid)->fetch_assoc()['n'] ?? 1);
            $se  = $searchable ? 1 : 0;
            $s = $conn->prepare("INSERT INTO fr_vendor_fields (franchise_id, field_key, field_label, field_type, is_searchable, sort_order, created_at)
                                 VALUES (?,?,?,?,?,?,NOW())");
            $s->bind_param('isssii', $fid, $key, $label, $type, $se, $ord);
            $s->execute();
            $s->close();
            return ['ok' => true, 'field_key' => $key, 'message' => 'Custom field added.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => (strpos($e->getMessage(), 'uniq_vfield') !== false) ? 'A field with this name already exists.' : $e->getMessage()];
        }
    }
}

if (!function_exists('frv_field_delete')) {
    function frv_field_delete(mysqli $conn, int $fid, int $fieldId): array
    {
        $row = null;
        $s = $conn->prepare("SELECT field_key FROM fr_vendor_fields WHERE id = ? AND franchise_id = ? LIMIT 1");
        $s->bind_param('ii', $fieldId, $fid);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row) return ['ok' => false, 'message' => 'Field not found.'];

        $conn->query("DELETE FROM fr_vendor_fields WHERE id = " . (int)$fieldId . " AND franchise_id = " . $fid);
        $k = $conn->real_escape_string($row['field_key']);
        $conn->query("DELETE FROM fr_vendor_values WHERE franchise_id = " . $fid . " AND field_key = '$k'");
        return ['ok' => true, 'message' => 'Field removed.'];
    }
}

if (!function_exists('frv_values_get')) {
    function frv_values_get(mysqli $conn, int $vendorId): array
    {
        $out = [];
        if (!frv_tbl($conn, 'fr_vendor_values')) return $out;
        $s = $conn->prepare("SELECT field_key, field_value FROM fr_vendor_values WHERE vendor_id = ?");
        $s->bind_param('i', $vendorId);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) $out[$x['field_key']] = $x['field_value'];
        $s->close();
        return $out;
    }
}

if (!function_exists('frv_values_set')) {
    function frv_values_set(mysqli $conn, int $fid, int $vendorId, array $values): void
    {
        if (!frv_vendor_get($conn, $fid, $vendorId)) return;
        // only accept keys that are defined for this franchise
        $allowed = [];
        foreach (frv_fields_list($conn, $fid) as $f) $allowed[$f['field_key']] = true;

        foreach ($values as $key => $val) {
            $key = frv_slug((string)$key);
            if ($key === '' || !isset($allowed[$key])) continue;
            $val = trim((string)$val);
            try {
                $s = $conn->prepare("INSERT INTO fr_vendor_values (vendor_id, franchise_id, field_key, field_value, updated_at)
                                     VALUES (?,?,?,?,NOW())
                                     ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = NOW()");
                $s->bind_param('iiss', $vendorId, $fid, $key, $val);
                $s->execute();
                $s->close();
            } catch (\Throwable $e) { /* skip bad key */ }
        }
    }
}

/* ---------------- vendor → product supply summary ---------------- */
if (!function_exists('frv_vendor_product_summary')) {
    /**
     * Products supplied by a vendor + quantities and current status.
     * Serialized status counts come from fr_units.vendor_id; non-serialized
     * shows supplied quantity only (aggregate counters cannot be split by vendor).
     */
    function frv_vendor_product_summary(mysqli $conn, int $fid, int $vendorId): array
    {
        $rows = [];
        $totals = ['supplied' => 0, 'in_stock' => 0, 'sold' => 0, 'returned' => 0, 'damaged' => 0];

        // supplied quantities from stock entries
        $s = $conn->prepare("SELECT e.product_id, p.name, p.model, p.is_serialized,
                                    SUM(e.quantity) AS supplied,
                                    MIN(e.received_date) AS first_date, MAX(e.received_date) AS last_date,
                                    GROUP_CONCAT(DISTINCT NULLIF(e.reference_no,'') ORDER BY e.id SEPARATOR ', ') AS refs
                             FROM fr_stock_entries e
                             JOIN fr_products p ON p.id = e.product_id
                             WHERE e.franchise_id = ? AND e.vendor_id = ?
                             GROUP BY e.product_id, p.name, p.model, p.is_serialized
                             ORDER BY p.name ASC");
        $s->bind_param('ii', $fid, $vendorId);
        $s->execute();
        $r = $s->get_result();
        while ($x = $r->fetch_assoc()) {
            $pid = (int)$x['product_id'];
            $row = [
                'product_id'   => $pid,
                'name'         => $x['name'],
                'model'        => $x['model'],
                'is_serialized' => (int)$x['is_serialized'],
                'supplied'     => (int)$x['supplied'],
                'first_date'   => $x['first_date'],
                'last_date'    => $x['last_date'],
                'refs'         => $x['refs'],
                'available'    => 0, 'reserved' => 0, 'sold' => 0, 'delivered' => 0, 'returned' => 0, 'damaged' => 0, 'cancelled' => 0,
                'in_stock'     => null,
            ];
            if ((int)$x['is_serialized'] === 1) {
                $u = $conn->query("SELECT status, COUNT(*) n FROM fr_units
                                   WHERE franchise_id = " . $fid . " AND vendor_id = " . $vendorId . " AND product_id = " . $pid . "
                                   GROUP BY status");
                while ($u && ($ur = $u->fetch_assoc())) {
                    $k = strtolower((string)$ur['status']);
                    if (array_key_exists($k, $row)) $row[$k] = (int)$ur['n'];
                }
                $row['in_stock'] = $row['available'] + $row['reserved'];
                $totals['in_stock'] += $row['in_stock'];
                $totals['sold']     += $row['sold'] + $row['delivered'];
                $totals['returned'] += $row['returned'];
                $totals['damaged']  += $row['damaged'];
            }
            $totals['supplied'] += $row['supplied'];
            $rows[] = $row;
        }
        $s->close();

        return ['rows' => $rows, 'totals' => $totals];
    }
}
