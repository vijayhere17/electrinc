<?php
/**
 * Franchise finance helpers — showroom commission (Sponsor 5% + Turnover 0.5%)
 * and the 36-month cashback engine. Rates/periods come from `franchise_settings`
 * (admin-managed) — nothing is hard-coded across PHP files.
 */

if (!function_exists('frf_tbl')) {
    function frf_tbl(mysqli $c, string $t): bool {
        $t = $c->real_escape_string($t);
        $r = $c->query("SHOW TABLES LIKE '$t'");
        return $r instanceof mysqli_result && $r->num_rows > 0;
    }
}

if (!function_exists('frf_setting')) {
    function frf_setting(mysqli $c, string $key, string $default = ''): string {
        if (!frf_tbl($c, 'franchise_settings')) return $default;
        $s = $c->prepare("SELECT setting_value FROM franchise_settings WHERE setting_key = ? LIMIT 1");
        if (!$s) return $default;
        $s->bind_param('s', $key); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return (isset($r['setting_value']) && $r['setting_value'] !== '') ? (string)$r['setting_value'] : $default;
    }
}
if (!function_exists('frf_setting_num')) {
    function frf_setting_num(mysqli $c, string $k, float $d = 0.0): float {
        $v = frf_setting($c, $k, (string)$d);
        return is_numeric($v) ? (float)$v : $d;
    }
}
if (!function_exists('frf_setting_bool')) {
    function frf_setting_bool(mysqli $c, string $k, bool $d = true): bool {
        return in_array(strtolower(trim(frf_setting($c, $k, $d ? '1' : '0'))), ['1', 'true', 'yes', 'on'], true);
    }
}

/* ---------------- showroom turnover income (company) ---------------- */
if (!function_exists('frf_credit_commission')) {
    /**
     * A franchise showroom sale earns the COMPANY a 0.5% Turnover Income
     * (rate from franchise_settings.turnover_income_rate). The 5% Sponsor
     * Income is NOT a per-sale item — it is paid once on the franchise's
     * model investment, see frf_credit_franchise_sponsor_income().
     *
     * @param array $meta ['sale_type'=>'SALE'|'ACTIVATION','sale_id'=>int,'reference'=>string]
     */
    function frf_credit_commission(mysqli $conn, string $franchiseCode, string $franchiseType, float $amount, array $meta = []): array
    {
        try {
            if (!frf_tbl($conn, 'commission_ledger')) return ['ok' => false, 'message' => 'commission_ledger missing'];
            if (!frf_setting_bool($conn, 'commission_on_sale_enabled', true)) return ['ok' => false, 'message' => 'disabled'];
            $amount = round($amount, 2);
            if ($amount <= 0 || trim($franchiseCode) === '') return ['ok' => false, 'message' => 'nothing to credit'];

            $pct = round(frf_setting_num($conn, 'turnover_income_rate', 0.5), 2);
            if ($pct <= 0) return ['ok' => false, 'message' => 'turnover rate is 0'];

            $saleId = (int)($meta['sale_id'] ?? 0);
            $moId   = (int)($meta['member_order_id'] ?? 0);
            $stype  = strtoupper((string)($meta['sale_type'] ?? 'SALE'));
            $ref    = trim((string)($meta['reference'] ?? ''));

            $camt = round($amount * $pct / 100, 2);
            $recvType = 'COMPANY';
            $recvId   = 'COMPANY';
            $rem = 'Showroom Turnover Income (company) · ' . $franchiseCode . ($ref !== '' ? ' · ' . $ref : '');

            $ins = $conn->prepare("INSERT INTO commission_ledger
                (sale_id, member_order_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks, created_at, payout_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')");
            $ins->bind_param('iissddss', $saleId, $moId, $recvType, $recvId, $pct, $camt, $stype, $rem);
            $ins->execute();
            $ins->close();
            return ['ok' => true, 'credited' => $camt, 'message' => 'Company turnover income credited: ' . number_format($camt, 2)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

/* ---------------- franchise sponsor income (5% of model investment) ---------------- */
if (!function_exists('frf_credit_franchise_sponsor_income')) {
    /**
     * Pay the 5% Sponsor Income, ONCE, when a franchise takes up an investment
     * model. The money goes to that franchise's SPONSOR FRANCHISE
     * (franchies_holder.sponsor_franchise_code), 5% of the model investment
     * amount (rate from franchise_settings.sponsor_income_rate).
     *
     * Idempotent: keyed on (sale_id = sponsored holder id, member_order_id = model id,
     * from_sale_type = 'FRANCHISE_INVESTMENT') in commission_ledger.
     *
     * @param int    $holderId    franchies_holder.id of the INVESTING franchise
     * @param string $franchiseCode code of the investing franchise (for the remark)
     * @param int    $modelId     franchise_investment_models.id
     * @param string $sponsorCode optional override; else read from the holder row
     */
    function frf_credit_franchise_sponsor_income(mysqli $conn, int $holderId, string $franchiseCode, int $modelId, string $sponsorCode = ''): array
    {
        try {
            if (!frf_tbl($conn, 'commission_ledger')) return ['ok' => false, 'message' => 'commission_ledger missing'];
            if ($holderId <= 0 || $modelId <= 0)      return ['ok' => false, 'message' => 'invalid franchise/model'];

            $model = frf_model($conn, $modelId);
            if (!$model) return ['ok' => false, 'message' => 'Investment model not found.'];
            $investment = round((float)$model['investment_amount'], 2);
            if ($investment <= 0) return ['ok' => false, 'message' => 'Model investment amount is 0.'];

            $sponsorCode = trim($sponsorCode);
            if ($sponsorCode === '') {
                $s = $conn->prepare("SELECT sponsor_franchise_code FROM franchies_holder WHERE id = ? LIMIT 1");
                if ($s) {
                    $s->bind_param('i', $holderId);
                    $s->execute();
                    $sponsorCode = trim((string)($s->get_result()->fetch_assoc()['sponsor_franchise_code'] ?? ''));
                    $s->close();
                }
            }
            if ($sponsorCode === '') {
                return ['ok' => false, 'message' => 'No sponsor franchise set — 5% Sponsor Income not credited.'];
            }
            if (strcasecmp($sponsorCode, $franchiseCode) === 0) {
                return ['ok' => false, 'message' => 'Sponsor franchise cannot be the same franchise.'];
            }

            // already paid for this franchise + model?
            $chk = $conn->prepare("SELECT id FROM commission_ledger
                                   WHERE from_sale_type = 'FRANCHISE_INVESTMENT' AND sale_id = ? AND member_order_id = ? LIMIT 1");
            $chk->bind_param('ii', $holderId, $modelId);
            $chk->execute();
            $already = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($already) return ['ok' => true, 'skipped' => true, 'message' => 'Sponsor Income already credited for this franchise + model.'];

            $pct  = round(frf_setting_num($conn, 'sponsor_income_rate', 5.0), 2);
            if ($pct <= 0) return ['ok' => false, 'message' => 'sponsor rate is 0'];
            $camt = round($investment * $pct / 100, 2);
            $rt   = 'FRANCHISE';
            $stype = 'FRANCHISE_INVESTMENT';
            $rem  = 'Franchise Sponsor Income · ' . ($franchiseCode ?: ('holder#' . $holderId)) . ' invested ₹' . number_format($investment, 2);

            $ins = $conn->prepare("INSERT INTO commission_ledger
                (sale_id, member_order_id, receiver_type, receiver_id, commission_percent, commission_amount, from_sale_type, remarks, created_at, payout_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')");
            $ins->bind_param('iissddss', $holderId, $modelId, $rt, $sponsorCode, $pct, $camt, $stype, $rem);
            $ins->execute();
            $ins->close();
            return ['ok' => true, 'credited' => $camt, 'sponsor' => $sponsorCode,
                    'message' => 'Sponsor Income ₹' . number_format($camt, 2) . ' credited to ' . $sponsorCode . ' (Pending).'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

/* ---------------- franchises sponsored BY this franchise (report) ---------------- */
if (!function_exists('frf_sponsored_franchises')) {
    /**
     * Franchises this franchise has SPONSORED/DIRECTED — i.e. franchies_holder /
     * city_franchies_holder rows whose sponsor_franchise_code matches this
     * franchise's own code — with the one-time 5% Sponsor Income row from
     * commission_ledger (if already credited) attached to each.
     */
    function frf_sponsored_franchises(mysqli $conn, string $myCode): array
    {
        $myCode = trim($myCode);
        $out = [];
        if ($myCode === '') return $out;

        $sources = [
            ['table' => 'franchies_holder',      'type' => 'State', 'name_col' => 'name',      'date_col' => 'created_date'],
            ['table' => 'city_franchies_holder',  'type' => 'City',  'name_col' => 'full_name', 'date_col' => 'created_at'],
        ];

        foreach ($sources as $src) {
            if (!frf_tbl($conn, $src['table'])) continue;
            $nameCol = $src['name_col'];
            $dateCol = $src['date_col'];
            $s = $conn->prepare("SELECT id, franchies_id, franchise_code, {$nameCol} AS holder_name, city, state,
                                        status, investment_model_id, investment_start_date, {$dateCol} AS created_date
                                 FROM `{$src['table']}` WHERE sponsor_franchise_code = ? ORDER BY id DESC");
            if (!$s) continue;
            $s->bind_param('s', $myCode);
            $s->execute();
            $rs = $s->get_result();
            while ($row = $rs->fetch_assoc()) {
                $modelId = (int)($row['investment_model_id'] ?? 0);
                $model   = $modelId > 0 ? frf_model($conn, $modelId) : null;

                $credit = null;
                if ($modelId > 0) {
                    $cq = $conn->prepare("SELECT commission_amount, commission_percent, payout_status, created_at
                                          FROM commission_ledger
                                          WHERE from_sale_type = 'FRANCHISE_INVESTMENT' AND sale_id = ? AND member_order_id = ?
                                          LIMIT 1");
                    if ($cq) {
                        $rid = (int)$row['id'];
                        $cq->bind_param('ii', $rid, $modelId);
                        $cq->execute();
                        $credit = $cq->get_result()->fetch_assoc() ?: null;
                        $cq->close();
                    }
                }

                $out[] = [
                    'holder_id'             => (int)$row['id'],
                    'type'                  => $src['type'],
                    'code'                  => (string)($row['franchise_code'] ?: $row['franchies_id']),
                    'name'                  => (string)$row['holder_name'],
                    'city'                  => (string)($row['city'] ?? ''),
                    'state'                 => (string)($row['state'] ?? ''),
                    'status'                => (string)($row['status'] ?? ''),
                    'model_name'            => $model['model_name'] ?? null,
                    'investment_amount'     => $model['investment_amount'] ?? null,
                    'investment_start_date' => $row['investment_start_date'] ?? null,
                    'joined_at'             => $row['created_date'] ?? null,
                    'commission_amount'     => isset($credit['commission_amount']) ? (float)$credit['commission_amount'] : null,
                    'commission_percent'    => isset($credit['commission_percent']) ? (float)$credit['commission_percent'] : null,
                    'payout_status'         => $credit['payout_status'] ?? ($modelId > 0 ? 'Not credited yet' : '—'),
                    'credited_at'           => $credit['created_at'] ?? null,
                ];
            }
            $s->close();
        }

        usort($out, fn($a, $b) => strcmp((string)$b['joined_at'], (string)$a['joined_at']));
        return $out;
    }
}

/* ---------------- cashback: model helpers ---------------- */
if (!function_exists('frf_model')) {
    function frf_model(mysqli $conn, int $modelId): ?array {
        if ($modelId <= 0 || !frf_tbl($conn, 'franchise_investment_models')) return null;
        $s = $conn->prepare("SELECT * FROM franchise_investment_models WHERE id = ? LIMIT 1");
        $s->bind_param('i', $modelId); $s->execute();
        $r = $s->get_result()->fetch_assoc() ?: null; $s->close();
        return $r;
    }
}

if (!function_exists('frf_cashback_generate')) {
    /** Build the monthly cashback schedule for a franchise+model. Idempotent. */
    function frf_cashback_generate(mysqli $conn, int $franchiseId, string $fcode, int $modelId, string $startDate): array
    {
        $m = frf_model($conn, $modelId);
        if (!$m) return ['ok' => false, 'message' => 'Model not found.'];
        $start = trim($startDate);
        if ($start === '' || $start === '0000-00-00') return ['ok' => false, 'message' => 'Investment start date required.'];
        $months = max(1, (int)$m['period_months']);
        $entitlement = round((float)$m['investment_amount'] * (float)$m['cashback_percent'] / 100, 2);
        $per = round($entitlement / $months, 2);

        try {
            $ins = $conn->prepare("INSERT INTO franchise_cashback_schedule
                (franchise_id, franchise_code, model_id, month_no, due_date, amount, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())
                ON DUPLICATE KEY UPDATE due_date = VALUES(due_date), amount = VALUES(amount)");
            $made = 0; $running = 0.0;
            for ($n = 1; $n <= $months; $n++) {
                $due = date('Y-m-d', strtotime($start . ' +' . $n . ' month'));
                $amt = ($n === $months) ? round($entitlement - $running, 2) : $per; // last month absorbs rounding
                $running += $amt;
                $ins->bind_param('isiisd', $franchiseId, $fcode, $modelId, $n, $due, $amt);
                $ins->execute();
                $made++;
            }
            $ins->close();
            return ['ok' => true, 'months' => $made, 'entitlement' => $entitlement, 'per_month' => $per,
                    'message' => "Schedule created — {$made} months, ₹" . number_format($per, 2) . "/mo."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('frf_cashback_run_due')) {
    /** Credit every Pending schedule row whose due_date has passed. Returns totals. */
    function frf_cashback_run_due(mysqli $conn, int $franchiseId = 0, string $by = 'system'): array
    {
        if (!frf_tbl($conn, 'franchise_cashback_schedule')) return ['ok' => false, 'credited' => 0, 'rows' => 0];
        $where = "status = 'Pending' AND due_date <= CURDATE()";
        if ($franchiseId > 0) $where .= " AND franchise_id = " . (int)$franchiseId;

        $due = [];
        $r = $conn->query("SELECT * FROM franchise_cashback_schedule WHERE $where ORDER BY franchise_id, month_no");
        while ($r && ($x = $r->fetch_assoc())) $due[] = $x;

        $credited = 0.0; $count = 0;
        $insL = $conn->prepare("INSERT INTO franchise_cashback_ledger
            (franchise_id, franchise_code, schedule_id, month_no, amount, note, performed_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $upd = $conn->prepare("UPDATE franchise_cashback_schedule SET status='Paid', paid_at=NOW(), ref=? WHERE id=? AND status='Pending'");
        foreach ($due as $d) {
            $sid = (int)$d['id']; $fidV = (int)$d['franchise_id']; $fc = (string)$d['franchise_code'];
            $mn = (int)$d['month_no']; $amt = (float)$d['amount'];
            $note = 'Cashback month ' . $mn;
            $refTxt = 'CB-' . $sid;
            $upd->bind_param('si', $refTxt, $sid);
            $upd->execute();
            if ($upd->affected_rows > 0) {
                $insL->bind_param('isiidss', $fidV, $fc, $sid, $mn, $amt, $note, $by);
                $insL->execute();
                $credited += $amt; $count++;
            }
        }
        $insL->close(); $upd->close();
        return ['ok' => true, 'credited' => round($credited, 2), 'rows' => $count,
                'message' => $count > 0 ? "Credited {$count} month(s), ₹" . number_format($credited, 2) : 'No cashback due right now.'];
    }
}

if (!function_exists('frf_cashback_summary')) {
    function frf_cashback_summary(mysqli $conn, int $franchiseId): array
    {
        $out = ['scheduled' => 0.0, 'paid' => 0.0, 'pending' => 0.0, 'months_total' => 0, 'months_done' => 0, 'due_now' => 0.0];
        if (!frf_tbl($conn, 'franchise_cashback_schedule')) return $out;
        $r = $conn->query("SELECT
                COALESCE(SUM(amount),0) sched,
                COALESCE(SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END),0) paid,
                COALESCE(SUM(CASE WHEN status='Pending' THEN amount ELSE 0 END),0) pend,
                COUNT(*) mtot,
                COALESCE(SUM(status='Paid'),0) mdone,
                COALESCE(SUM(CASE WHEN status='Pending' AND due_date <= CURDATE() THEN amount ELSE 0 END),0) due_now
            FROM franchise_cashback_schedule WHERE franchise_id = " . (int)$franchiseId)->fetch_assoc();
        if ($r) {
            $out['scheduled'] = (float)$r['sched']; $out['paid'] = (float)$r['paid'];
            $out['pending'] = (float)$r['pend']; $out['months_total'] = (int)$r['mtot'];
            $out['months_done'] = (int)$r['mdone']; $out['due_now'] = (float)$r['due_now'];
        }
        return $out;
    }
}
