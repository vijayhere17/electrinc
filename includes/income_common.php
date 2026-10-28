<?php
/**
 * Shared income-plan engine.  All rates / amounts / caps / levels /
 * eligibility rules live in the `income_settings` table and are edited
 * from Admin > Income & Business Rules. Nothing here is hard-coded except
 * the last-resort fallbacks used when a setting row is missing entirely.
 *
 * Income types (kept strictly separate, never merged):
 *   Direct Sponsor  -> credit_direct_sponsor_income()   (cap-exempt by default)
 *   Binary          -> credit_binary_income()           (capped by default)
 *   Level 1..N      -> credit_level_income()             (cap-exempt by default)
 *
 * Activation entry point -> process_member_income_on_activation()
 *
 * Plan-wide rules:
 *   - Product-fund split: until signup_users.product_purchased = 1, every
 *     PAID income is split product_fund_split_percent% to
 *     product_fund_balance, the remainder to wallet_balance (withdrawable).
 *   - Cap (capped incomes only): per withdrawal cycle,
 *     ceiling = min( floor(active_directs / cap_directs_step) * cap_per_2_directs, cap_max ).
 *     Excess above the remaining ceiling in a cycle is flushed (lost).
 *
 * Every function is defensive: failures are swallowed and surfaced in the
 * return array so the activation flow can never break.
 */

/* ===========================================================================
 * Settings
 * ========================================================================= */

if (!function_exists('income_setting')) {
    function income_setting(mysqli $conn, string $key, string $default = ''): string
    {
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM income_settings WHERE setting_key = ? LIMIT 1");
            if (!$stmt) {
                return $default;
            }
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && isset($row['setting_value']) && $row['setting_value'] !== '') {
                return (string)$row['setting_value'];
            }
        } catch (\Throwable $e) {
            // default
        }
        return $default;
    }
}

if (!function_exists('income_setting_num')) {
    function income_setting_num(mysqli $conn, string $key, float $default = 0.0): float
    {
        $v = income_setting($conn, $key, (string)$default);
        return is_numeric($v) ? (float)$v : $default;
    }
}

if (!function_exists('income_setting_bool')) {
    function income_setting_bool(mysqli $conn, string $key, bool $default = false): bool
    {
        $v = strtolower(trim(income_setting($conn, $key, $default ? '1' : '0')));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('income_set_setting_with_history')) {
    /**
     * Update a setting and append a history row. Returns true if it changed.
     */
    function income_set_setting_with_history(
        mysqli $conn,
        string $key,
        string $newValue,
        string $changedBy = '',
        string $changedById = '',
        string $reason = '',
        string $effectiveDate = ''
    ): bool {
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM income_settings WHERE setting_key = ? LIMIT 1");
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $old = $row['setting_value'] ?? null;

            if ($old === null) {
                $ins = $conn->prepare("INSERT INTO income_settings (setting_key, setting_value) VALUES (?, ?)");
                $ins->bind_param('ss', $key, $newValue);
                $ins->execute();
                $ins->close();
            } elseif ((string)$old !== (string)$newValue) {
                $upd = $conn->prepare("UPDATE income_settings SET setting_value = ? WHERE setting_key = ?");
                $upd->bind_param('ss', $newValue, $key);
                $upd->execute();
                $upd->close();
            } else {
                return false; // unchanged
            }

            if (income_table_exists($conn, 'income_settings_history')) {
                $eff = $effectiveDate !== '' ? $effectiveDate : date('Y-m-d');
                $h = $conn->prepare("INSERT INTO income_settings_history
                    (setting_key, old_value, new_value, changed_by, changed_by_id, effective_date, reason, changed_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $h->bind_param('sssssss', $key, $old, $newValue, $changedBy, $changedById, $eff, $reason);
                $h->execute();
                $h->close();
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('income_table_exists')) {
    function income_table_exists(mysqli $conn, string $table): bool
    {
        try {
            $stmt = $conn->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $ok = (bool)$stmt->get_result()->fetch_row();
            $stmt->close();
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('income_withdrawal_days')) {
    /** @return int[] sorted, e.g. [5,15,25] */
    function income_withdrawal_days(mysqli $conn): array
    {
        $raw = income_setting($conn, 'withdrawal_days', '5,15,25');
        $days = [];
        foreach (explode(',', $raw) as $d) {
            $d = (int)trim($d);
            if ($d >= 1 && $d <= 31) {
                $days[] = $d;
            }
        }
        $days = array_values(array_unique($days));
        sort($days);
        return $days ?: [5, 15, 25];
    }
}

/* ===========================================================================
 * Booking amount / percentages
 * ========================================================================= */

if (!function_exists('income_resolve_booking_amount')) {
    /**
     * override -> activation_requests -> member_activation -> product master -> $defaultKey setting
     */
    function income_resolve_booking_amount(mysqli $conn, string $memberId, string $productName = '', float $override = 0.0, string $defaultKey = 'business_qualifying_amount'): float
    {
        if ($override > 0) {
            return round($override, 2);
        }

        foreach (['activation_requests', 'member_activation'] as $tbl) {
            if (!income_table_exists($conn, $tbl)) {
                continue;
            }
            try {
                $stmt = $conn->prepare("SELECT amount FROM `$tbl` WHERE customer_id = ? AND amount > 0 ORDER BY id DESC LIMIT 1");
                $stmt->bind_param('s', $memberId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row && (float)$row['amount'] > 0) {
                    return round((float)$row['amount'], 2);
                }
            } catch (\Throwable $e) {
                // next
            }
        }

        if ($productName === '') {
            try {
                $stmt = $conn->prepare("SELECT product_name FROM signup_users WHERE customer_id = ? LIMIT 1");
                $stmt->bind_param('s', $memberId);
                $stmt->execute();
                $r = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $productName = (string)($r['product_name'] ?? '');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($productName !== '' && income_table_exists($conn, 'products')) {
            try {
                $stmt = $conn->prepare("SELECT amount FROM products WHERE product_name = ? AND amount > 0 ORDER BY id ASC LIMIT 1");
                $stmt->bind_param('s', $productName);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row && (float)$row['amount'] > 0) {
                    return round((float)$row['amount'], 2);
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $fallback = income_setting_num($conn, $defaultKey, 0.0);
        if ($fallback <= 0) {
            $fallback = income_setting_num($conn, 'business_qualifying_amount', 10000.0);
        }
        return round($fallback, 2);
    }
}

if (!function_exists('income_resolve_direct_percent')) {
    function income_resolve_direct_percent(mysqli $conn, string $productName = ''): float
    {
        if ($productName !== '' && income_table_exists($conn, 'products')) {
            try {
                $stmt = $conn->prepare("SELECT sponsor_percentage FROM products WHERE product_name = ? AND sponsor_percentage > 0 ORDER BY id ASC LIMIT 1");
                $stmt->bind_param('s', $productName);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row && (float)$row['sponsor_percentage'] > 0) {
                    return (float)$row['sponsor_percentage'];
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }
        return income_setting_num($conn, 'direct_sponsor_percent', 5.0);
    }
}

/* ===========================================================================
 * Member status / directs / chains
 * ========================================================================= */

if (!function_exists('income_member_is_active')) {
    function income_member_is_active(mysqli $conn, string $memberId): bool
    {
        try {
            $stmt = $conn->prepare("SELECT registration_status, status, active_status FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $memberId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                return false;
            }
            foreach (['registration_status', 'status', 'active_status'] as $c) {
                if (strtolower(trim((string)($row[$c] ?? ''))) === 'active') {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return false;
    }
}

if (!function_exists('income_active_direct_count')) {
    function income_active_direct_count(mysqli $conn, string $memberId): int
    {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) c FROM signup_users
                WHERE referral_id = ?
                  AND ( LOWER(TRIM(registration_status)) = 'active'
                     OR LOWER(TRIM(status)) = 'active'
                     OR LOWER(TRIM(active_status)) = 'active' )");
            $stmt->bind_param('s', $memberId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('income_active_directs_by_side')) {
    /** @return string[] active direct customer_ids on a leg, oldest activation first */
    function income_active_directs_by_side(mysqli $conn, string $memberId, string $side): array
    {
        $side = strtoupper($side) === 'R' ? 'R' : 'L';
        $ids = [];
        try {
            $stmt = $conn->prepare("SELECT customer_id FROM signup_users
                WHERE referral_id = ?
                  AND UPPER(TRIM(position)) = ?
                  AND ( LOWER(TRIM(registration_status)) = 'active'
                     OR LOWER(TRIM(status)) = 'active'
                     OR LOWER(TRIM(active_status)) = 'active' )
                ORDER BY COALESCE(activated_at, created_at) ASC, id ASC");
            $stmt->bind_param('ss', $memberId, $side);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $ids[] = (string)$r['customer_id'];
            }
            $stmt->close();
        } catch (\Throwable $e) {
            // ignore
        }
        return $ids;
    }
}

if (!function_exists('income_sponsor_of')) {
    function income_sponsor_of(mysqli $conn, string $memberId): string
    {
        try {
            $stmt = $conn->prepare("SELECT referral_id FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $memberId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return trim((string)($row['referral_id'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }
}

/* ===========================================================================
 * Withdrawal cycle + capping
 * ========================================================================= */

if (!function_exists('income_current_cycle')) {
    /**
     * @return array{start:string,end:string,key:string}
     * cycle end  = next withdrawal-day boundary strictly after today
     * cycle start = most recent boundary on/before today
     */
    function income_current_cycle(mysqli $conn, ?string $todayYmd = null): array
    {
        $days = income_withdrawal_days($conn);
        $today = $todayYmd ? new DateTime($todayYmd) : new DateTime('today');
        $today->setTime(0, 0, 0);

        $boundaries = [];
        $base = (clone $today)->modify('first day of this month');
        foreach ([-1, 0, 1] as $mOff) {
            $monthRef = (clone $base)->modify(($mOff >= 0 ? '+' : '-') . abs($mOff) . ' month');
            $y = (int)$monthRef->format('Y');
            $m = (int)$monthRef->format('n');
            $lastDay = (int)$monthRef->format('t');
            foreach ($days as $d) {
                $dd = min($d, $lastDay);
                $boundaries[] = (new DateTime())->setDate($y, $m, $dd)->setTime(0, 0, 0);
            }
        }
        usort($boundaries, static fn($a, $b) => $a <=> $b);

        $start = null;
        $end = null;
        foreach ($boundaries as $b) {
            if ($b <= $today) {
                $start = $b;
            } elseif ($end === null) {
                $end = $b;
            }
        }
        if ($start === null) {
            $start = (clone $today)->modify('-15 day');
        }
        if ($end === null) {
            $end = (clone $today)->modify('+15 day');
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
            'key'   => $end->format('Y-m-d'),
        ];
    }
}

if (!function_exists('income_cap_ceiling')) {
    function income_cap_ceiling(mysqli $conn, string $memberId): float
    {
        $directs = income_active_direct_count($conn, $memberId);
        $step = max(1, (int)income_setting_num($conn, 'cap_directs_step', 2));
        $per  = income_setting_num($conn, 'cap_per_2_directs', 10000.0);
        $max  = income_setting_num($conn, 'cap_max', 50000.0);
        $ceiling = floor($directs / $step) * $per;
        return (float)min($ceiling, $max);
    }
}

if (!function_exists('income_cycle_capped_paid')) {
    function income_cycle_capped_paid(mysqli $conn, string $memberId, string $cycleKey): float
    {
        if (!income_table_exists($conn, 'member_income_ledger')) {
            return 0.0;
        }
        try {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(paid_amount),0) s
                                    FROM member_income_ledger
                                    WHERE customer_id = ? AND cycle_key = ? AND counts_to_cap = 1");
            $stmt->bind_param('ss', $memberId, $cycleKey);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (float)($row['s'] ?? 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}

/* ===========================================================================
 * Core credit — split + cap + unified ledger row
 * ========================================================================= */

if (!function_exists('credit_member_income')) {
    /**
     * @param array $opts [
     *   'capped'       => bool   (default true)
     *   'force_flush'  => bool   (default false; recipient ineligible - record 100% flushed, no balance change)
     *   'flush_reason' => string
     *   'source'       => string
     *   'source_ref'   => string
     *   'from_member_id' => string
     *   'from_member_name' => string
     *   'base_amount'  => float  (business the income was computed from - for the transaction row)
     *   'percentage'   => float
     *   'report_date'  => 'Y-m-d'
     * ]
     * @return array{ok:bool,gross:float,paid:float,flushed:float,fund:float,withdrawable:float,
     *               wallet_after:float,cycle_key:string,ceiling:?float,used_before:?float,ledger_id:int}
     */
    function credit_member_income(mysqli $conn, string $memberId, string $incomeType, float $gross, array $opts = []): array
    {
        $capped      = array_key_exists('capped', $opts) ? (bool)$opts['capped'] : true;
        $forceFlush  = (bool)($opts['force_flush'] ?? false);
        $flushReason = (string)($opts['flush_reason'] ?? '');
        $source      = (string)($opts['source'] ?? '');
        $sourceRef   = (string)($opts['source_ref'] ?? '');
        $fromId      = (string)($opts['from_member_id'] ?? '');
        $fromName    = (string)($opts['from_member_name'] ?? '');
        $baseAmount  = round((float)($opts['base_amount'] ?? 0), 2);
        $percentage  = round((float)($opts['percentage'] ?? 0), 2);
        $reportDate  = (string)($opts['report_date'] ?? date('Y-m-d'));

        $out = [
            'ok' => false, 'gross' => round($gross, 2), 'paid' => 0.0, 'flushed' => 0.0,
            'fund' => 0.0, 'withdrawable' => 0.0, 'wallet_after' => 0.0,
            'cycle_key' => '', 'ceiling' => null, 'used_before' => null, 'ledger_id' => 0,
        ];

        try {
            $gross = round($gross, 2);
            if ($gross <= 0 || $memberId === '') {
                return $out;
            }

            $cycle = income_current_cycle($conn);
            $out['cycle_key'] = $cycle['key'];

            $ceiling = null;
            $usedBefore = null;
            $paid = $gross;

            if ($forceFlush) {
                $paid = 0.0;
            } elseif ($capped) {
                $ceiling = income_cap_ceiling($conn, $memberId);
                $usedBefore = income_cycle_capped_paid($conn, $memberId, $cycle['key']);
                $room = max(0.0, round($ceiling - $usedBefore, 2));
                $paid = min($gross, $room);
            }
            $paid = round($paid, 2);
            $flushed = round($gross - $paid, 2);

            // product-fund split on the PAID portion
            $productPurchased = false;
            try {
                $stmt = $conn->prepare("SELECT product_purchased FROM signup_users WHERE customer_id = ? LIMIT 1");
                $stmt->bind_param('s', $memberId);
                $stmt->execute();
                $pp = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $productPurchased = (int)($pp['product_purchased'] ?? 0) === 1;
            } catch (\Throwable $e) {
                // treat as not purchased
            }

            $splitPercent = income_setting_num($conn, 'product_fund_split_percent', 50.0);
            if ($productPurchased || $paid <= 0) {
                $fund = 0.0;
                $withdrawable = $paid;
            } else {
                $fund = round($paid * $splitPercent / 100, 2);
                $withdrawable = round($paid - $fund, 2);
            }

            $walletBefore = 0.0;
            try {
                $stmt = $conn->prepare("SELECT wallet_balance FROM signup_users WHERE customer_id = ? LIMIT 1");
                $stmt->bind_param('s', $memberId);
                $stmt->execute();
                $b = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $walletBefore = (float)($b['wallet_balance'] ?? 0);
            } catch (\Throwable $e) {
                // ignore
            }
            $walletAfter = round($walletBefore + $withdrawable, 2);

            $countsToCap = ($capped && !$forceFlush) ? 1 : 0;
            if ($forceFlush) {
                $status = 'Flushed';
            } elseif ($flushed > 0) {
                $status = $paid > 0 ? 'Partial' : 'Flushed';
            } else {
                $status = 'Credit';
            }

            $ledgerId = 0;
            if (income_table_exists($conn, 'member_income_ledger')) {
                $ins = $conn->prepare("INSERT INTO member_income_ledger
                    (customer_id, income_type, source, source_ref, from_member_id, from_member_name,
                     report_date, cycle_key, counts_to_cap, base_amount, percentage,
                     gross_amount, cap_ceiling, cap_used_before, paid_amount, flushed_amount,
                     fund_amount, withdrawable_amount, wallet_balance_after, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param(
                    'ssssssssidddddddddds',
                    $memberId,
                    $incomeType,
                    $source,
                    $sourceRef,
                    $fromId,
                    $fromName,
                    $reportDate,
                    $cycle['key'],
                    $countsToCap,
                    $baseAmount,
                    $percentage,
                    $gross,
                    $ceiling,
                    $usedBefore,
                    $paid,
                    $flushed,
                    $fund,
                    $withdrawable,
                    $walletAfter,
                    $status
                );
                $ins->execute();
                $ledgerId = (int)$ins->insert_id;
                $ins->close();
            }

            if ($paid > 0) {
                $upd = $conn->prepare("UPDATE signup_users
                                       SET wallet_balance = wallet_balance + ?,
                                           product_fund_balance = product_fund_balance + ?
                                       WHERE customer_id = ?");
                $upd->bind_param('dds', $withdrawable, $fund, $memberId);
                $upd->execute();
                $upd->close();

                // this payout is funded from the Income Distribution Pool
                if (function_exists('income_pool_add')) {
                    income_pool_add($conn, 'debit', 'payout', $paid, [
                        'customer_id' => $memberId, 'income_type' => $incomeType,
                        'ref' => $ledgerId ? 'LGR-' . $ledgerId : '',
                    ]);
                }
            }

            $out['ok'] = true;
            $out['paid'] = $paid;
            $out['flushed'] = $flushed;
            $out['fund'] = $fund;
            $out['withdrawable'] = $withdrawable;
            $out['wallet_after'] = $walletAfter;
            $out['ceiling'] = $ceiling;
            $out['used_before'] = $usedBefore;
            $out['ledger_id'] = $ledgerId;
            return $out;
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }
    }
}

/* ===========================================================================
 * Income #1 — Direct Sponsor (Referral) Income
 * ========================================================================= */

if (!function_exists('credit_direct_sponsor_income')) {
    function credit_direct_sponsor_income(mysqli $conn, string $newMemberId, array $opts = []): array
    {
        $source = (string)($opts['source'] ?? 'Activation');
        $refId  = isset($opts['ref_id']) ? (int)$opts['ref_id'] : null;

        try {
            if ($newMemberId === '' || !income_table_exists($conn, 'direct_sponsor_income')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }

            $stmt = $conn->prepare("SELECT referral_id, full_name, product_name FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $newMemberId);
            $stmt->execute();
            $member = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$member) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'member_not_found'];
            }

            $sponsorId   = trim((string)($member['referral_id'] ?? ''));
            $memberName  = (string)($member['full_name'] ?? '');
            $productName = (string)($member['product_name'] ?? '');

            if ($sponsorId === '' || $sponsorId === $newMemberId) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'no_sponsor'];
            }
            if (!income_member_is_active($conn, $sponsorId)) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'sponsor_inactive', 'sponsor_id' => $sponsorId];
            }

            $stmt = $conn->prepare("SELECT id FROM direct_sponsor_income WHERE sponsor_id = ? AND from_member_id = ? AND source = ? LIMIT 1");
            $stmt->bind_param('sss', $sponsorId, $newMemberId, $source);
            $stmt->execute();
            $dupe = $stmt->get_result()->fetch_row();
            $stmt->close();
            if ($dupe) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'already_paid', 'sponsor_id' => $sponsorId];
            }

            $base    = income_resolve_booking_amount($conn, $newMemberId, $productName, (float)($opts['base_amount'] ?? 0), 'direct_sponsor_base_default');
            $percent = income_resolve_direct_percent($conn, $productName);
            $gross   = round($base * $percent / 100, 2);
            if ($gross <= 0) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'zero_income', 'sponsor_id' => $sponsorId];
            }

            $capExempt = income_setting_bool($conn, 'direct_sponsor_cap_exempt', true);

            $res = credit_member_income($conn, $sponsorId, 'Direct Sponsor', $gross, [
                'capped'         => !$capExempt,
                'source'         => $source,
                'source_ref'     => $newMemberId,
                'from_member_id' => $newMemberId,
                'from_member_name' => $memberName,
                'base_amount'    => $base,
                'percentage'     => $percent,
            ]);
            if (empty($res['ok'])) {
                return ['ok' => false, 'status' => 'error', 'message' => $res['error'] ?? 'credit_failed', 'sponsor_id' => $sponsorId];
            }

            $ins = $conn->prepare("INSERT INTO direct_sponsor_income
                (sponsor_id, from_member_id, from_member_name, report_date,
                 base_amount, percentage, income_amount, fund_amount, withdrawable_amount,
                 credit, debit, balance, source, ref_id, status, created_at)
                VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'Credit', NOW())");
            $ins->bind_param(
                'sssdddddddsi',
                $sponsorId,
                $newMemberId,
                $memberName,
                $base,
                $percent,
                $gross,
                $res['fund'],
                $res['withdrawable'],
                $gross,
                $res['wallet_after'],
                $source,
                $refId
            );
            $ins->execute();
            $ins->close();

            return [
                'ok'            => true,
                'status'        => 'paid',
                'message'       => 'direct_sponsor_income_credited',
                'sponsor_id'    => $sponsorId,
                'from_member'   => $newMemberId,
                'base_amount'   => $base,
                'percentage'    => $percent,
                'income_amount' => $gross,
                'fund_amount'   => $res['fund'],
                'withdrawable'  => $res['withdrawable'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

/* ===========================================================================
 * Income #2 — Binary Income
 * ========================================================================= */

if (!function_exists('credit_binary_income')) {
    function credit_binary_income(mysqli $conn, string $memberId): array
    {
        try {
            if ($memberId === '' || !income_table_exists($conn, 'binary_income')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }
            if (!income_member_is_active($conn, $memberId)) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'member_inactive'];
            }

            $needL = max(1, (int)income_setting_num($conn, 'binary_qualify_left_directs', 1));
            $needR = max(1, (int)income_setting_num($conn, 'binary_qualify_right_directs', 1));

            $left  = income_active_directs_by_side($conn, $memberId, 'L');
            $right = income_active_directs_by_side($conn, $memberId, 'R');

            if (count($left) < $needL || count($right) < $needR) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_qualified',
                        'left_directs' => count($left), 'right_directs' => count($right)];
            }

            $stmt = $conn->prepare("SELECT COUNT(*) c FROM binary_income WHERE customer_id = ?");
            $stmt->bind_param('s', $memberId);
            $stmt->execute();
            $paidPairs = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();

            $possible = min(count($left), count($right));
            if ($possible <= $paidPairs) {
                return ['ok' => true, 'status' => 'no_new_pairs', 'pairs' => $paidPairs];
            }

            $leftPct  = income_setting_num($conn, 'binary_left_percent', income_setting_num($conn, 'binary_percent', 2.5));
            $rightPct = income_setting_num($conn, 'binary_right_percent', income_setting_num($conn, 'binary_percent', 2.5));
            $capExempt = income_setting_bool($conn, 'binary_cap_exempt', false);
            $jodis = [];

            for ($i = $paidPairs; $i < $possible; $i++) {
                $leftId  = $left[$i];
                $rightId = $right[$i];

                $chk = $conn->prepare("SELECT id FROM binary_income WHERE customer_id = ? AND left_member_id = ? AND right_member_id = ? LIMIT 1");
                $chk->bind_param('sss', $memberId, $leftId, $rightId);
                $chk->execute();
                $exists = $chk->get_result()->fetch_row();
                $chk->close();
                if ($exists) {
                    continue;
                }

                $leftBase  = income_resolve_booking_amount($conn, $leftId, '', 0.0, 'binary_base_default');
                $rightBase = income_resolve_booking_amount($conn, $rightId, '', 0.0, 'binary_base_default');
                $gross = round($leftBase * $leftPct / 100, 2) + round($rightBase * $rightPct / 100, 2);
                $gross = round($gross, 2);
                $effPct = ($leftBase + $rightBase) > 0 ? round($gross / ($leftBase + $rightBase) * 100, 2) : 0.0;

                $res = credit_member_income($conn, $memberId, 'Binary', $gross, [
                    'capped'         => !$capExempt,
                    'source'         => 'Pair',
                    'source_ref'     => $leftId . '|' . $rightId,
                    'from_member_id' => $leftId . '+' . $rightId,
                    'base_amount'    => round($leftBase + $rightBase, 2),
                    'percentage'     => $effPct,
                ]);
                if (empty($res['ok'])) {
                    continue;
                }

                $pairNo = $i + 1;
                $status = $res['flushed'] > 0 ? ($res['paid'] > 0 ? 'Partial' : 'Flushed') : 'Credit';

                $ins = $conn->prepare("INSERT INTO binary_income
                    (customer_id, left_member_id, right_member_id, pair_no, report_date,
                     left_base, right_base, percentage, gross_amount, paid_amount, flushed_amount,
                     fund_amount, withdrawable_amount, status, created_at)
                    VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param(
                    'sssidddddddds',
                    $memberId,
                    $leftId,
                    $rightId,
                    $pairNo,
                    $leftBase,
                    $rightBase,
                    $effPct,
                    $gross,
                    $res['paid'],
                    $res['flushed'],
                    $res['fund'],
                    $res['withdrawable'],
                    $status
                );
                $ins->execute();
                $binaryRowId = (int)$ins->insert_id;
                $ins->close();

                // Level Income feeds off this Binary Income (paid portion), up the sponsor chain
                $levelRes = null;
                if ((float)$res['paid'] > 0) {
                    $levelRes = credit_level_income($conn, $memberId, (float)$res['paid'], [
                        'source' => 'Binary',
                        'ref_id' => $binaryRowId,
                    ]);
                }

                $jodis[] = [
                    'pair_no' => $pairNo, 'left' => $leftId, 'right' => $rightId,
                    'gross' => $gross, 'paid' => $res['paid'], 'flushed' => $res['flushed'],
                    'level_income' => $levelRes,
                ];
            }

            return [
                'ok' => true, 'status' => $jodis ? 'paid' : 'no_new_pairs',
                'member_id' => $memberId, 'total_pairs' => $paidPairs + count($jodis), 'new_jodis' => $jodis,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

/* ===========================================================================
 * Income #3 — Level Income (sponsor chain, L1..Ln, no compression)
 * ========================================================================= */

if (!function_exists('credit_level_income')) {
    /**
     * Level Income is earned from the BINARY INCOME of a downline member.
     * When $earnerId receives Binary Income of $sourceAmount, walk up the
     * SPONSOR (referral_id) chain: Level 1 upline gets level_1_percent% of
     * $sourceAmount, Level 2 gets level_2_percent%, etc.
     * Chain end -> stop. Inactive upline -> that level flushed, keep walking
     * (NO compression). Nothing here touches the ₹10,000 joining amount.
     *
     * @param float $sourceAmount  Binary Income the downline member earned (paid).
     * @param array $opts ['source'=>'Binary', 'ref_id'=>binary_income.id]
     */
    function credit_level_income(mysqli $conn, string $earnerId, float $sourceAmount, array $opts = []): array
    {
        $source = (string)($opts['source'] ?? 'Binary');
        $refId  = isset($opts['ref_id']) ? (int)$opts['ref_id'] : null;
        $sourceAmount = round((float)$sourceAmount, 2);

        try {
            if ($earnerId === '' || !income_table_exists($conn, 'level_income')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }
            if ($sourceAmount <= 0) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'zero_source'];
            }

            $levels = max(1, (int)income_setting_num($conn, 'level_income_count', 5));
            $requireActive = income_setting_bool($conn, 'level_require_active', true);
            $capExempt = income_setting_bool($conn, 'level_cap_exempt', true);

            $stmt = $conn->prepare("SELECT full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $earnerId);
            $stmt->execute();
            $em = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $earnerName = (string)($em['full_name'] ?? '');

            $rows = [];
            $current = $earnerId;
            $seen = [$earnerId => true];

            for ($lvl = 1; $lvl <= $levels; $lvl++) {
                $upline = income_sponsor_of($conn, $current);
                if ($upline === '' || isset($seen[$upline])) {
                    break; // chain ended (or loop guard)
                }
                $seen[$upline] = true;

                $pct = income_setting_num($conn, "level_{$lvl}_percent", 0.0);
                $gross = round($sourceAmount * $pct / 100, 2);

                // idempotency: one payout per (upline, earner, level, source, binary row)
                $chk = $conn->prepare("SELECT id FROM level_income WHERE customer_id = ? AND from_member_id = ? AND level_no = ? AND source = ? AND ref_id <=> ? LIMIT 1");
                $chk->bind_param('ssisi', $upline, $earnerId, $lvl, $source, $refId);
                $chk->execute();
                $exists = $chk->get_result()->fetch_row();
                $chk->close();
                if ($exists) {
                    $current = $upline;
                    continue;
                }

                if ($gross <= 0) {
                    $current = $upline;
                    continue;
                }

                $uplineActive = income_member_is_active($conn, $upline);

                if ($requireActive && !$uplineActive) {
                    // flush this level's share, no compression
                    credit_member_income($conn, $upline, "Level {$lvl}", $gross, [
                        'capped'         => !$capExempt,
                        'force_flush'    => true,
                        'flush_reason'   => 'inactive_upline',
                        'source'         => $source,
                        'source_ref'     => $earnerId,
                        'from_member_id' => $earnerId,
                        'from_member_name' => $earnerName,
                        'base_amount'    => $sourceAmount,
                        'percentage'     => $pct,
                    ]);
                    $paid = 0.0; $flushed = $gross; $fund = 0.0; $wd = 0.0; $st = 'Flushed'; $fr = 'inactive_upline';
                } else {
                    $res = credit_member_income($conn, $upline, "Level {$lvl}", $gross, [
                        'capped'         => !$capExempt,
                        'source'         => $source,
                        'source_ref'     => $earnerId,
                        'from_member_id' => $earnerId,
                        'from_member_name' => $earnerName,
                        'base_amount'    => $sourceAmount,
                        'percentage'     => $pct,
                    ]);
                    if (empty($res['ok'])) {
                        $current = $upline;
                        continue;
                    }
                    $paid = $res['paid']; $flushed = $res['flushed']; $fund = $res['fund']; $wd = $res['withdrawable'];
                    $fr = $flushed > 0 ? 'cap' : null;
                    $st = $flushed > 0 ? ($paid > 0 ? 'Partial' : 'Flushed') : 'Credit';
                }

                $ins = $conn->prepare("INSERT INTO level_income
                    (customer_id, from_member_id, from_member_name, level_no, report_date,
                     business_amount, percentage, gross_amount, paid_amount, flushed_amount,
                     fund_amount, withdrawable_amount, flush_reason, source, ref_id, status, created_at)
                    VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->bind_param(
                    'sssidddddddssis',
                    $upline,
                    $earnerId,
                    $earnerName,
                    $lvl,
                    $sourceAmount,
                    $pct,
                    $gross,
                    $paid,
                    $flushed,
                    $fund,
                    $wd,
                    $fr,
                    $source,
                    $refId,
                    $st
                );
                $ins->execute();
                $ins->close();

                $rows[] = [
                    'level' => $lvl, 'upline' => $upline, 'percent' => $pct,
                    'gross' => $gross, 'paid' => $paid, 'flushed' => $flushed, 'active' => $uplineActive,
                ];

                $current = $upline;
            }

            return ['ok' => true, 'status' => $rows ? 'processed' : 'no_upline',
                    'earner' => $earnerId, 'source_amount' => $sourceAmount, 'levels' => $rows];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

/* ===========================================================================
 * Activation entry point
 * ========================================================================= */

if (!function_exists('process_member_income_on_activation')) {
    function process_member_income_on_activation(mysqli $conn, string $newMemberId, array $opts = []): array
    {
        $result = ['direct_sponsor' => null, 'binary' => null, 'level' => null];

        // joining 50/50 split — 50% to the member's own product fund,
        // 50% to the Income Distribution Pool (which funds the payouts below)
        if (function_exists('apply_joining_split')) {
            $joinAmt = income_resolve_booking_amount($conn, $newMemberId, '', (float)($opts['base_amount'] ?? 0));
            $result['joining_split'] = apply_joining_split($conn, $newMemberId, $joinAmt, isset($opts['ref_id']) ? (int)$opts['ref_id'] : null);
        }

        // settle any finished monthly-bonus period before touching the new one
        if (function_exists('perf_bonus_autoclose')) {
            $result['perf_bonus_autoclose'] = perf_bonus_autoclose($conn);
        }
        if (function_exists('team_perf_autoclose')) {
            $result['team_perf_autoclose'] = team_perf_autoclose($conn);
        }

        // #1 Direct Sponsor — to the new member's direct sponsor
        if (income_setting_bool($conn, 'direct_sponsor_instant', true)) {
            $result['direct_sponsor'] = credit_direct_sponsor_income($conn, $newMemberId, $opts);
        }

        // #2 Binary — the sponsor may gain a Jodi from this new direct.
        //    Binary payout internally triggers #3 Level Income up the sponsor chain.
        $sponsorId = income_sponsor_of($conn, $newMemberId);
        if ($sponsorId !== '' && $sponsorId !== $newMemberId) {
            $result['binary'] = credit_binary_income($conn, $sponsorId);
        }

        // #4 Auto Pool — the new member auto-enters Pool 1
        if (income_setting_bool($conn, 'auto_pool_enabled', true)) {
            $result['auto_pool'] = auto_pool_enter($conn, $newMemberId, $opts);
        }

        // #5 Performance Bonus — the sponsor's monthly qualifying-direct pool contribution
        if ($sponsorId !== '' && $sponsorId !== $newMemberId) {
            $result['perf_bonus'] = perf_bonus_contribute($conn, $sponsorId, $newMemberId);
        }

        // #6 Team Performance Bonus — attribute this member's joining business to
        //    every placement ancestor's left/right leg for the month
        if (function_exists('team_business_record')) {
            $teamBiz = income_resolve_booking_amount($conn, $newMemberId, '', (float)($opts['base_amount'] ?? 0));
            $result['team_perf'] = team_business_record($conn, $newMemberId, $teamBiz);
        }

        return $result;
    }
}

/* ===========================================================================
 * Income #4 — Auto Pool Income
 * ========================================================================= */

if (!function_exists('auto_pool_config')) {
    function auto_pool_config(mysqli $conn): array
    {
        $levels = max(1, (int)income_setting_num($conn, 'auto_pool_levels', 10));
        $perPool = max(2, (int)income_setting_num($conn, 'auto_pool_users_per_pool', 20));
        $entry = round(income_setting_num($conn, 'auto_pool_entry_amount', 100.0), 2);
        $split = income_setting_num($conn, 'auto_pool_income_split_percent', 50.0);
        // per-user grows by (users_per_pool * split/100) each level
        $multiplier = $perPool * $split / 100.0;
        return [
            'levels' => $levels, 'per_pool' => $perPool, 'entry' => $entry,
            'split' => $split, 'multiplier' => $multiplier > 0 ? $multiplier : 1,
            'fund_from' => strtolower(trim(income_setting($conn, 'auto_pool_fund_from', 'booking'))),
            'cap_exempt' => income_setting_bool($conn, 'auto_pool_cap_exempt', true),
        ];
    }
}

if (!function_exists('auto_pool_per_user')) {
    function auto_pool_per_user(array $cfg, int $level): float
    {
        return round($cfg['entry'] * pow($cfg['multiplier'], max(0, $level - 1)), 2);
    }
}

if (!function_exists('auto_pool_ensure_state')) {
    function auto_pool_ensure_state(mysqli $conn, int $level, array $cfg): void
    {
        $perUser = auto_pool_per_user($cfg, $level);
        $poolTotal = round($perUser * $cfg['per_pool'], 2);
        $stmt = $conn->prepare("INSERT INTO auto_pool_state (pool_level, per_user_amount, pool_total, balance)
                                VALUES (?, ?, ?, 0)
                                ON DUPLICATE KEY UPDATE per_user_amount = VALUES(per_user_amount), pool_total = VALUES(pool_total)");
        $stmt->bind_param('idd', $level, $perUser, $poolTotal);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('auto_pool_add_entry')) {
    /** Insert a queue position at $level and add its amount to that pool's balance. */
    function auto_pool_add_entry(mysqli $conn, string $customerId, int $level, float $amount, string $source, ?int $fromPos, array $cfg): ?int
    {
        auto_pool_ensure_state($conn, $level, $cfg);

        // one position per member per level
        $chk = $conn->prepare("SELECT id FROM auto_pool_positions WHERE customer_id = ? AND pool_level = ? LIMIT 1");
        $chk->bind_param('si', $customerId, $level);
        $chk->execute();
        if ($chk->get_result()->fetch_row()) {
            $chk->close();
            return null; // already in this level
        }
        $chk->close();

        $conn->query("UPDATE auto_pool_state SET entries_count = entries_count + 1, balance = balance + " . (float)$amount . " WHERE pool_level = " . (int)$level);

        $q = $conn->query("SELECT entries_count FROM auto_pool_state WHERE pool_level = " . (int)$level);
        $queueNo = (int)($q->fetch_assoc()['entries_count'] ?? 0);

        $ins = $conn->prepare("INSERT INTO auto_pool_positions
            (customer_id, pool_level, queue_no, entry_amount, entry_source, from_position, status, joined_at)
            VALUES (?, ?, ?, ?, ?, ?, 'waiting', NOW())");
        $ins->bind_param('siidsi', $customerId, $level, $queueNo, $amount, $source, $fromPos);
        $ins->execute();
        $id = (int)$ins->insert_id;
        $ins->close();
        return $id;
    }
}

if (!function_exists('auto_pool_process')) {
    /** Drain every level whose balance has reached one pool total, cascading upward. */
    function auto_pool_process(mysqli $conn, array $cfg): array
    {
        $events = [];
        for ($level = 1; $level <= $cfg['levels']; $level++) {
            auto_pool_ensure_state($conn, $level, $cfg);
            $poolTotal = round(auto_pool_per_user($cfg, $level) * $cfg['per_pool'], 2);
            if ($poolTotal <= 0) {
                continue;
            }

            $guard = 0;
            while ($guard++ < 100000) {
                $st = $conn->query("SELECT balance FROM auto_pool_state WHERE pool_level = " . (int)$level)->fetch_assoc();
                $balance = (float)($st['balance'] ?? 0);
                if ($balance + 0.001 < $poolTotal) {
                    break;
                }

                // head = oldest waiting position in this level
                $head = $conn->query("SELECT id, customer_id FROM auto_pool_positions
                                      WHERE pool_level = " . (int)$level . " AND status = 'waiting'
                                      ORDER BY queue_no ASC, id ASC LIMIT 1")->fetch_assoc();
                if (!$head) {
                    break; // balance present but nobody waiting (shouldn't happen)
                }

                $income = round($poolTotal * $cfg['split'] / 100, 2);
                $repool = round($poolTotal - $income, 2);
                $headId = (int)$head['id'];
                $headCust = (string)$head['customer_id'];

                // pay the head member
                $res = credit_member_income($conn, $headCust, 'Auto Pool', $income, [
                    'capped'         => !$cfg['cap_exempt'],
                    'source'         => 'Pool ' . $level,
                    'source_ref'     => 'P' . $level,
                    'from_member_id' => 'POOL-' . $level,
                    'base_amount'    => $poolTotal,
                    'percentage'     => $cfg['split'],
                ]);
                $paid = $res['ok'] ? (float)$res['paid'] : 0.0;
                $flushed = $res['ok'] ? (float)$res['flushed'] : 0.0;

                // consume one pool total from the balance, mark head paid
                $conn->query("UPDATE auto_pool_state
                              SET balance = balance - " . $poolTotal . ", completions_count = completions_count + 1
                              WHERE pool_level = " . (int)$level);

                $upd = $conn->prepare("UPDATE auto_pool_positions
                    SET status = 'paid', income_amount = ?, repool_amount = ?, paid_at = NOW()
                    WHERE id = ?");
                $upd->bind_param('ddi', $income, $repool, $headId);
                $upd->execute();
                $upd->close();

                // re-pool into the next level for the same member
                $repoolTo = null;
                if ($level < $cfg['levels'] && $repool > 0) {
                    $repoolTo = $level + 1;
                    auto_pool_add_entry($conn, $headCust, $level + 1, $repool, 'repool', $headId, $cfg);
                }
                $flushedRepool = ($repoolTo === null) ? $repool : 0.0;
                $totalFlushed = round($flushed + $flushedRepool, 2);

                $ins = $conn->prepare("INSERT INTO auto_pool_completions
                    (pool_level, head_customer_id, pool_total, income_amount, repool_amount, repool_to_level,
                     paid_amount, flushed_amount, report_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())");
                $ins->bind_param('isdddidd', $level, $headCust, $poolTotal, $income, $repool, $repoolTo, $paid, $totalFlushed);
                $ins->execute();
                $ins->close();

                $events[] = [
                    'level' => $level, 'head' => $headCust, 'pool_total' => $poolTotal,
                    'income' => $income, 'paid' => $paid, 'repool' => $repool, 'repool_to' => $repoolTo,
                ];
            }
        }
        return $events;
    }
}

if (!function_exists('auto_pool_enter')) {
    /** New member auto-joins Pool 1 (real entry-fee deduction), then processes. */
    function auto_pool_enter(mysqli $conn, string $customerId, array $opts = []): array
    {
        try {
            if ($customerId === '' || !income_table_exists($conn, 'auto_pool_positions')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }
            $cfg = auto_pool_config($conn);

            // already entered Pool 1?
            $chk = $conn->prepare("SELECT id FROM auto_pool_positions WHERE customer_id = ? AND pool_level = 1 LIMIT 1");
            $chk->bind_param('s', $customerId);
            $chk->execute();
            if ($chk->get_result()->fetch_row()) {
                $chk->close();
                return ['ok' => false, 'status' => 'skipped', 'message' => 'already_in_pool1'];
            }
            $chk->close();

            $amount = $cfg['entry'];
            $source = $cfg['fund_from'] ?: 'company';
            $note = '';

            if ($source === 'wallet' || $source === 'product_fund') {
                $col = $source === 'wallet' ? 'wallet_balance' : 'product_fund_balance';
                $br = $conn->query("SELECT `$col` b FROM signup_users WHERE customer_id = '" . $conn->real_escape_string($customerId) . "' LIMIT 1")->fetch_assoc();
                $bal = (float)($br['b'] ?? 0);
                if ($bal + 0.001 >= $amount) {
                    $u = $conn->prepare("UPDATE signup_users SET `$col` = `$col` - ? WHERE customer_id = ?");
                    $u->bind_param('ds', $amount, $customerId);
                    $u->execute();
                    $u->close();
                    $note = 'deducted from ' . $col;
                } else {
                    $source = 'company';
                    $note = 'insufficient ' . $col . ', company funded';
                }
            } elseif ($source === 'booking') {
                $note = 'allocated from joining booking';
            } else {
                $source = 'company';
                $note = 'company funded (not from member joining)';
            }

            $dbg = $conn->prepare("INSERT INTO auto_pool_debits (customer_id, pool_level, amount, source, note, created_at) VALUES (?, 1, ?, ?, ?, NOW())");
            $dbg->bind_param('sdss', $customerId, $amount, $source, $note);
            $dbg->execute();
            $dbg->close();

            $posId = auto_pool_add_entry($conn, $customerId, 1, $amount, $source, null, $cfg);
            $events = auto_pool_process($conn, $cfg);

            return ['ok' => true, 'status' => 'entered', 'position_id' => $posId, 'entry_amount' => $amount, 'source' => $source, 'completions' => $events];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('auto_pool_status')) {
    /** Per-level snapshot for admin / member displays. */
    function auto_pool_status(mysqli $conn): array
    {
        $cfg = auto_pool_config($conn);
        $out = [];
        for ($level = 1; $level <= $cfg['levels']; $level++) {
            auto_pool_ensure_state($conn, $level, $cfg);
            $perUser = auto_pool_per_user($cfg, $level);
            $poolTotal = round($perUser * $cfg['per_pool'], 2);
            $st = $conn->query("SELECT balance, entries_count, completions_count FROM auto_pool_state WHERE pool_level = " . (int)$level)->fetch_assoc();
            $waiting = (int)($conn->query("SELECT COUNT(*) c FROM auto_pool_positions WHERE pool_level = " . (int)$level . " AND status = 'waiting'")->fetch_assoc()['c'] ?? 0);
            $balance = (float)($st['balance'] ?? 0);
            $out[] = [
                'level' => $level,
                'per_user' => $perUser,
                'pool_total' => $poolTotal,
                'income' => round($poolTotal * $cfg['split'] / 100, 2),
                'repool' => round($poolTotal - $poolTotal * $cfg['split'] / 100, 2),
                'balance' => $balance,
                'progress_pct' => $poolTotal > 0 ? min(100, round($balance / $poolTotal * 100, 1)) : 0,
                'waiting' => $waiting,
                'entries' => (int)($st['entries_count'] ?? 0),
                'completions' => (int)($st['completions_count'] ?? 0),
            ];
        }
        return $out;
    }
}

/* ===========================================================================
 * Income #5 — Performance Bonus (monthly, rank by direct count)
 * ========================================================================= */

if (!function_exists('perf_bonus_period_key')) {
    function perf_bonus_period_key(mysqli $conn, ?string $ymd = null): string
    {
        // only 'monthly' is implemented; the setting is future-proofing
        $d = $ymd ? new DateTime($ymd) : new DateTime('today');
        return $d->format('Y-m');
    }
}

if (!function_exists('perf_bonus_ensure_pool')) {
    function perf_bonus_ensure_pool(mysqli $conn, string $periodKey): void
    {
        $per = round(income_setting_num($conn, 'perf_bonus_per_direct', 100.0), 2);
        $stmt = $conn->prepare("INSERT INTO performance_bonus_pool (period_key, per_direct_amount)
                                VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE per_direct_amount = IF(status='open', VALUES(per_direct_amount), per_direct_amount)");
        $stmt->bind_param('sd', $periodKey, $per);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('perf_bonus_contribute')) {
    /** A qualifying direct joined under $sponsorId — add one pool contribution for the current month. */
    function perf_bonus_contribute(mysqli $conn, string $sponsorId, string $fromMemberId): array
    {
        try {
            if ($sponsorId === '' || $fromMemberId === '' || $sponsorId === $fromMemberId
                || !income_table_exists($conn, 'performance_bonus_pool')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }
            if (!income_setting_bool($conn, 'perf_bonus_enabled', true)) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'disabled'];
            }

            $periodKey = perf_bonus_period_key($conn);
            perf_bonus_ensure_pool($conn, $periodKey);

            $amount = round(income_setting_num($conn, 'perf_bonus_per_direct', 100.0), 2);

            // one contribution per (period, sponsor, direct) — idempotent
            $ins = $conn->prepare("INSERT IGNORE INTO performance_bonus_contributions
                (period_key, sponsor_id, from_member_id, amount, created_at) VALUES (?, ?, ?, ?, NOW())");
            $ins->bind_param('sssd', $periodKey, $sponsorId, $fromMemberId, $amount);
            $ins->execute();
            $added = $ins->affected_rows > 0;
            $ins->close();

            if ($added) {
                $conn->query("UPDATE performance_bonus_pool
                              SET qualifying_directs = qualifying_directs + 1,
                                  pool_collected = pool_collected + " . $amount . "
                              WHERE period_key = '" . $conn->real_escape_string($periodKey) . "' AND status = 'open'");
            }

            return ['ok' => true, 'status' => $added ? 'contributed' : 'duplicate',
                    'period' => $periodKey, 'amount' => $amount];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('perf_bonus_leaderboard')) {
    /** @return array of ['customer_id','directs','first_id'] ordered rank 1..n */
    function perf_bonus_leaderboard(mysqli $conn, string $periodKey, int $limit = 50): array
    {
        $out = [];
        $limit = max(1, min(1000, $limit));
        $res = $conn->query("SELECT sponsor_id, COUNT(*) directs, MIN(id) first_id
                             FROM performance_bonus_contributions
                             WHERE period_key = '" . $conn->real_escape_string($periodKey) . "'
                             GROUP BY sponsor_id
                             ORDER BY directs DESC, first_id ASC
                             LIMIT " . $limit);
        while ($res && ($r = $res->fetch_assoc())) {
            $out[] = ['customer_id' => (string)$r['sponsor_id'], 'directs' => (int)$r['directs'], 'first_id' => (int)$r['first_id']];
        }
        return $out;
    }
}

if (!function_exists('perf_bonus_distribute')) {
    /**
     * Close and pay out a period. Safe to call repeatedly (no-op once distributed).
     * @param bool $force allow distributing the CURRENT (not-yet-ended) period
     */
    function perf_bonus_distribute(mysqli $conn, string $periodKey, string $adminName = '', string $adminId = '', bool $force = false): array
    {
        try {
            if (!income_table_exists($conn, 'performance_bonus_pool')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }
            perf_bonus_ensure_pool($conn, $periodKey);

            $row = $conn->query("SELECT * FROM performance_bonus_pool WHERE period_key = '" . $conn->real_escape_string($periodKey) . "' LIMIT 1")->fetch_assoc();
            if (!$row) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'no_pool'];
            }
            if ($row['status'] === 'distributed') {
                return ['ok' => true, 'status' => 'already_distributed', 'period' => $periodKey];
            }
            if (!$force && $periodKey >= perf_bonus_period_key($conn)) {
                return ['ok' => false, 'status' => 'not_ended', 'period' => $periodKey,
                        'message' => 'period has not ended yet'];
            }

            $winners = max(1, (int)income_setting_num($conn, 'perf_bonus_winners', 3));
            $minDirects = max(0, (int)income_setting_num($conn, 'perf_bonus_min_directs', 1));
            $capExempt = income_setting_bool($conn, 'perf_bonus_cap_exempt', true);

            $override = round(income_setting_num($conn, 'perf_bonus_pool_override', 0.0), 2);
            $poolFinal = $override > 0 ? $override : round((float)$row['pool_collected'], 2);

            $percents = [];
            for ($r = 1; $r <= $winners; $r++) {
                $percents[$r] = income_setting_num($conn, "perf_bonus_rank_{$r}_percent", 0.0);
            }

            $board = perf_bonus_leaderboard($conn, $periodKey, $winners);
            $payouts = [];

            foreach ($board as $idx => $entry) {
                $rank = $idx + 1;
                if ($rank > $winners) break;
                if ($entry['directs'] < $minDirects) continue;

                $pct = $percents[$rank] ?? 0.0;
                $amount = round($poolFinal * $pct / 100, 2);
                $winnerId = $entry['customer_id'];

                $paid = 0.0; $flushed = 0.0; $ledgerId = 0; $status = 'Credit';
                if ($amount > 0) {
                    $res = credit_member_income($conn, $winnerId, 'Performance Bonus', $amount, [
                        'capped'         => !$capExempt,
                        'source'         => 'Rank ' . $rank . ' ' . $periodKey,
                        'source_ref'     => $periodKey,
                        'from_member_id' => 'PERF-' . $periodKey,
                        'base_amount'    => $poolFinal,
                        'percentage'     => $pct,
                    ]);
                    if (!empty($res['ok'])) {
                        $paid = (float)$res['paid']; $flushed = (float)$res['flushed'];
                        $ledgerId = (int)$res['ledger_id'];
                        $status = $flushed > 0 ? ($paid > 0 ? 'Partial' : 'Flushed') : 'Credit';
                    }
                }

                $pi = $conn->prepare("INSERT INTO performance_bonus_payouts
                    (period_key, rank_no, customer_id, directs_count, percent, pool_final, amount, paid_amount, flushed_amount, ledger_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id), directs_count=VALUES(directs_count),
                        percent=VALUES(percent), pool_final=VALUES(pool_final), amount=VALUES(amount),
                        paid_amount=VALUES(paid_amount), flushed_amount=VALUES(flushed_amount), ledger_id=VALUES(ledger_id), status=VALUES(status)");
                $dc = $entry['directs'];
                $pi->bind_param('sisidddddis', $periodKey, $rank, $winnerId, $dc, $pct, $poolFinal, $amount, $paid, $flushed, $ledgerId, $status);
                $pi->execute();
                $pi->close();

                $payouts[] = ['rank' => $rank, 'customer_id' => $winnerId, 'directs' => $dc,
                              'percent' => $pct, 'amount' => $amount, 'paid' => $paid, 'flushed' => $flushed];
            }

            $percJson = json_encode($percents);
            $upd = $conn->prepare("UPDATE performance_bonus_pool
                SET pool_override = ?, pool_final = ?, winners_count = ?, rank_percents_json = ?,
                    status = 'distributed', distributed_at = NOW(), distributed_by = ?
                WHERE period_key = ?");
            $cnt = count($payouts);
            $upd->bind_param('ddisss', $override, $poolFinal, $cnt, $percJson, $adminName, $periodKey);
            $upd->execute();
            $upd->close();

            return ['ok' => true, 'status' => 'distributed', 'period' => $periodKey,
                    'pool_final' => $poolFinal, 'payouts' => $payouts];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('perf_bonus_autoclose')) {
    /** Distribute any still-open period that has fully ended (called lazily on activation). */
    function perf_bonus_autoclose(mysqli $conn): array
    {
        try {
            if (!income_table_exists($conn, 'performance_bonus_pool')) {
                return ['ok' => false, 'status' => 'skipped'];
            }
            $cur = perf_bonus_period_key($conn);
            $res = $conn->query("SELECT period_key FROM performance_bonus_pool
                                 WHERE status = 'open' AND period_key < '" . $conn->real_escape_string($cur) . "'
                                 ORDER BY period_key ASC");
            $periods = [];
            while ($res && ($r = $res->fetch_assoc())) {
                $periods[] = (string)$r['period_key'];
            }
            $done = [];
            foreach ($periods as $pk) {
                $done[] = perf_bonus_distribute($conn, $pk, 'system (auto)', '');
            }
            return ['ok' => true, 'status' => $done ? 'closed' : 'none', 'periods' => $done];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

/* ===========================================================================
 * Income #6 — Team Performance Bonus (monthly, matching-business rank)
 * ========================================================================= */

if (!function_exists('team_perf_period_key')) {
    function team_perf_period_key(mysqli $conn, ?string $ymd = null): string
    {
        $d = $ymd ? new DateTime($ymd) : new DateTime('today');
        return $d->format('Y-m');
    }
}

if (!function_exists('team_perf_ensure_pool')) {
    function team_perf_ensure_pool(mysqli $conn, string $periodKey): void
    {
        $stmt = $conn->prepare("INSERT IGNORE INTO team_perf_pool (period_key) VALUES (?)");
        $stmt->bind_param('s', $periodKey);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('team_business_record')) {
    /**
     * A member joined this month with $businessAmount. Attribute that business
     * to every placement ancestor's left or right leg, and add it once to the
     * month's total joining business.
     */
    function team_business_record(mysqli $conn, string $newMemberId, float $businessAmount): array
    {
        try {
            if ($newMemberId === '' || $businessAmount <= 0 || !income_table_exists($conn, 'team_business_contributions')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }
            if (!income_setting_bool($conn, 'team_perf_enabled', true)) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'disabled'];
            }

            $periodKey = team_perf_period_key($conn);
            team_perf_ensure_pool($conn, $periodKey);
            $businessAmount = round($businessAmount, 2);

            // bump month total once for this member
            $chk = $conn->prepare("SELECT 1 FROM team_business_contributions WHERE period_key = ? AND from_member_id = ? LIMIT 1");
            $chk->bind_param('ss', $periodKey, $newMemberId);
            $chk->execute();
            $already = (bool)$chk->get_result()->fetch_row();
            $chk->close();

            if (!$already) {
                $conn->query("UPDATE team_perf_pool SET business_total = business_total + " . $businessAmount .
                             " WHERE period_key = '" . $conn->real_escape_string($periodKey) . "' AND status = 'open'");
            }

            // walk the placement chain: for each ancestor, which of its legs holds this member
            $ancestors = 0;
            $current = $newMemberId;
            $seen = [$newMemberId => true];

            // side of $current under its own placement parent
            $row = $conn->query("SELECT placement_id, position FROM signup_users WHERE customer_id = '" . $conn->real_escape_string($current) . "' LIMIT 1")->fetch_assoc();
            $parent = trim((string)($row['placement_id'] ?? ''));
            $childSide = strtoupper(trim((string)($row['position'] ?? '')));

            while ($parent !== '' && !isset($seen[$parent]) && $ancestors < 500) {
                $seen[$parent] = true;
                $side = ($childSide === 'R') ? 'R' : 'L';

                $ins = $conn->prepare("INSERT IGNORE INTO team_business_contributions
                    (period_key, leader_id, from_member_id, side, amount, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())");
                $ins->bind_param('ssssd', $periodKey, $parent, $newMemberId, $side, $businessAmount);
                $ins->execute();
                $ins->close();
                $ancestors++;

                $pr = $conn->query("SELECT placement_id, position FROM signup_users WHERE customer_id = '" . $conn->real_escape_string($parent) . "' LIMIT 1")->fetch_assoc();
                $childSide = strtoupper(trim((string)($pr['position'] ?? '')));
                $parent = trim((string)($pr['placement_id'] ?? ''));
            }

            return ['ok' => true, 'status' => 'recorded', 'period' => $periodKey,
                    'ancestors' => $ancestors, 'amount' => $businessAmount, 'counted_total' => !$already];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('team_perf_is_leader')) {
    function team_perf_is_leader(mysqli $conn, string $memberId): bool
    {
        if (!income_member_is_active($conn, $memberId)) {
            return false;
        }
        $minDirects = max(0, (int)income_setting_num($conn, 'team_perf_leader_min_directs', 2));
        $bothLegs = income_setting_bool($conn, 'team_perf_leader_require_both_legs', true);

        $directs = income_active_direct_count($conn, $memberId);
        if ($directs < $minDirects) {
            return false;
        }
        if ($bothLegs) {
            $l = count(income_active_directs_by_side($conn, $memberId, 'L'));
            $r = count(income_active_directs_by_side($conn, $memberId, 'R'));
            if ($l < 1 || $r < 1) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('team_perf_leaderboard')) {
    /** @return array of ['customer_id','left','right','matching'] ranked desc by matching */
    function team_perf_leaderboard(mysqli $conn, string $periodKey, int $limit = 100): array
    {
        $limit = max(1, min(2000, $limit));
        $minMatch = income_setting_num($conn, 'team_perf_min_matching', 1.0);

        $rows = [];
        $res = $conn->query("SELECT leader_id,
                                    COALESCE(SUM(CASE WHEN side='L' THEN amount ELSE 0 END),0) AS left_b,
                                    COALESCE(SUM(CASE WHEN side='R' THEN amount ELSE 0 END),0) AS right_b
                             FROM team_business_contributions
                             WHERE period_key = '" . $conn->real_escape_string($periodKey) . "'
                             GROUP BY leader_id");
        while ($res && ($r = $res->fetch_assoc())) {
            $left = (float)$r['left_b'];
            $right = (float)$r['right_b'];
            $match = min($left, $right);
            if ($match < $minMatch) {
                continue;
            }
            if (!team_perf_is_leader($conn, (string)$r['leader_id'])) {
                continue;
            }
            $rows[] = ['customer_id' => (string)$r['leader_id'], 'left' => $left, 'right' => $right, 'matching' => $match];
        }

        usort($rows, static function ($a, $b) {
            if ($a['matching'] !== $b['matching']) {
                return $b['matching'] <=> $a['matching'];
            }
            return ($b['left'] + $b['right']) <=> ($a['left'] + $a['right']);
        });

        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('team_perf_pool_amount')) {
    /** Computed pool for a period given current settings + a business_total. */
    function team_perf_pool_amount(mysqli $conn, float $businessTotal): float
    {
        $override = round(income_setting_num($conn, 'team_perf_pool_override', 0.0), 2);
        if ($override > 0) {
            return $override;
        }
        $mode = strtolower(trim(income_setting($conn, 'team_perf_pool_mode', 'percent_of_business')));
        if ($mode === 'fixed') {
            return round(income_setting_num($conn, 'team_perf_pool_fixed', 0.0), 2);
        }
        $pct = income_setting_num($conn, 'team_perf_pool_percent', 0.0);
        return round($businessTotal * $pct / 100, 2);
    }
}

if (!function_exists('team_perf_distribute')) {
    function team_perf_distribute(mysqli $conn, string $periodKey, string $adminName = '', string $adminId = '', bool $force = false): array
    {
        try {
            if (!income_table_exists($conn, 'team_perf_pool')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }
            team_perf_ensure_pool($conn, $periodKey);
            $row = $conn->query("SELECT * FROM team_perf_pool WHERE period_key = '" . $conn->real_escape_string($periodKey) . "' LIMIT 1")->fetch_assoc();
            if (!$row) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'no_pool'];
            }
            if ($row['status'] === 'distributed') {
                return ['ok' => true, 'status' => 'already_distributed', 'period' => $periodKey];
            }
            if (!$force && $periodKey >= team_perf_period_key($conn)) {
                return ['ok' => false, 'status' => 'not_ended', 'period' => $periodKey, 'message' => 'period has not ended yet'];
            }

            $winners = max(1, (int)income_setting_num($conn, 'team_perf_winners', 3));
            $capExempt = income_setting_bool($conn, 'team_perf_cap_exempt', true);
            $businessTotal = (float)$row['business_total'];
            $poolFinal = team_perf_pool_amount($conn, $businessTotal);

            $mode = strtolower(trim(income_setting($conn, 'team_perf_pool_mode', 'percent_of_business')));
            $poolPct = income_setting_num($conn, 'team_perf_pool_percent', 0.0);
            $poolFixed = income_setting_num($conn, 'team_perf_pool_fixed', 0.0);
            $override = income_setting_num($conn, 'team_perf_pool_override', 0.0);

            $percents = [];
            for ($r = 1; $r <= $winners; $r++) {
                $percents[$r] = income_setting_num($conn, "team_perf_rank_{$r}_percent", 0.0);
            }
            $leaderRule = [
                'min_directs' => (int)income_setting_num($conn, 'team_perf_leader_min_directs', 2),
                'both_legs'   => income_setting_bool($conn, 'team_perf_leader_require_both_legs', true) ? 1 : 0,
                'min_matching' => income_setting_num($conn, 'team_perf_min_matching', 1.0),
            ];

            $board = team_perf_leaderboard($conn, $periodKey, $winners);
            $payouts = [];

            foreach ($board as $idx => $entry) {
                $rank = $idx + 1;
                if ($rank > $winners) break;

                $pct = $percents[$rank] ?? 0.0;
                $amount = round($poolFinal * $pct / 100, 2);
                $winnerId = $entry['customer_id'];

                $paid = 0.0; $flushed = 0.0; $ledgerId = 0; $status = 'Credit';
                if ($amount > 0) {
                    $res = credit_member_income($conn, $winnerId, 'Team Performance Bonus', $amount, [
                        'capped'         => !$capExempt,
                        'source'         => 'Rank ' . $rank . ' ' . $periodKey,
                        'source_ref'     => $periodKey,
                        'from_member_id' => 'TEAMPERF-' . $periodKey,
                        'base_amount'    => $poolFinal,
                        'percentage'     => $pct,
                    ]);
                    if (!empty($res['ok'])) {
                        $paid = (float)$res['paid']; $flushed = (float)$res['flushed'];
                        $ledgerId = (int)$res['ledger_id'];
                        $status = $flushed > 0 ? ($paid > 0 ? 'Partial' : 'Flushed') : 'Credit';
                    }
                }

                $lb = $entry['left']; $rb = $entry['right']; $mb = $entry['matching'];
                $pi = $conn->prepare("INSERT INTO team_perf_payouts
                    (period_key, rank_no, customer_id, left_business, right_business, matching_business,
                     percent, pool_final, amount, paid_amount, flushed_amount, ledger_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id), left_business=VALUES(left_business),
                        right_business=VALUES(right_business), matching_business=VALUES(matching_business),
                        percent=VALUES(percent), pool_final=VALUES(pool_final), amount=VALUES(amount),
                        paid_amount=VALUES(paid_amount), flushed_amount=VALUES(flushed_amount),
                        ledger_id=VALUES(ledger_id), status=VALUES(status)");
                $pi->bind_param('sisddddddddis', $periodKey, $rank, $winnerId, $lb, $rb, $mb, $pct, $poolFinal, $amount, $paid, $flushed, $ledgerId, $status);
                $pi->execute();
                $pi->close();

                $payouts[] = ['rank' => $rank, 'customer_id' => $winnerId, 'left' => $lb, 'right' => $rb,
                              'matching' => $mb, 'percent' => $pct, 'amount' => $amount, 'paid' => $paid, 'flushed' => $flushed];
            }

            $percJson = json_encode($percents);
            $ruleJson = json_encode($leaderRule);
            $cnt = count($payouts);
            $upd = $conn->prepare("UPDATE team_perf_pool
                SET pool_mode = ?, pool_percent = ?, pool_fixed = ?, pool_override = ?, pool_final = ?,
                    winners_count = ?, rank_percents_json = ?, leader_rule_json = ?,
                    status = 'distributed', distributed_at = NOW(), distributed_by = ?
                WHERE period_key = ?");
            $upd->bind_param('sddddissss', $mode, $poolPct, $poolFixed, $override, $poolFinal, $cnt, $percJson, $ruleJson, $adminName, $periodKey);
            $upd->execute();
            $upd->close();

            return ['ok' => true, 'status' => 'distributed', 'period' => $periodKey,
                    'business_total' => $businessTotal, 'pool_final' => $poolFinal, 'payouts' => $payouts];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('team_perf_autoclose')) {
    function team_perf_autoclose(mysqli $conn): array
    {
        try {
            if (!income_table_exists($conn, 'team_perf_pool')) {
                return ['ok' => false, 'status' => 'skipped'];
            }
            $cur = team_perf_period_key($conn);
            $res = $conn->query("SELECT period_key FROM team_perf_pool
                                 WHERE status = 'open' AND period_key < '" . $conn->real_escape_string($cur) . "'
                                 ORDER BY period_key ASC");
            $periods = [];
            while ($res && ($r = $res->fetch_assoc())) {
                $periods[] = (string)$r['period_key'];
            }
            $done = [];
            foreach ($periods as $pk) {
                $done[] = team_perf_distribute($conn, $pk, 'system (auto)', '');
            }
            return ['ok' => true, 'status' => $done ? 'closed' : 'none', 'periods' => $done];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

/* ===========================================================================
 * (A) Monthly bonus auto-distribution — lazy trigger
 * ========================================================================= */

if (!function_exists('income_maybe_run_monthly')) {
    /**
     * Runs on/after `monthly_distribute_day` each month, once per month.
     * Settles the previous month's Performance Bonus + Team Performance Bonus.
     * Cheap to call on every admin page load.
     */
    function income_maybe_run_monthly(mysqli $conn, bool $force = false): array
    {
        try {
            if (!income_table_exists($conn, 'income_settings')) {
                return ['ok' => false, 'status' => 'skipped'];
            }
            $day = max(1, min(28, (int)income_setting_num($conn, 'monthly_distribute_day', 1)));
            $curPeriod = date('Y-m');
            $lastRun = income_setting($conn, 'monthly_distribute_last_run', '');

            if (!$force) {
                if ((int)date('j') < $day) {
                    return ['ok' => true, 'status' => 'waiting', 'day' => $day];
                }
                if ($lastRun === $curPeriod) {
                    return ['ok' => true, 'status' => 'already_ran', 'period' => $curPeriod];
                }
            }

            $out = ['ok' => true, 'status' => 'ran', 'period' => $curPeriod];
            if (function_exists('perf_bonus_autoclose')) {
                $out['perf_bonus'] = perf_bonus_autoclose($conn);
            }
            if (function_exists('team_perf_autoclose')) {
                $out['team_perf'] = team_perf_autoclose($conn);
            }

            $stmt = $conn->prepare("INSERT INTO income_settings (setting_key, setting_value)
                                    VALUES ('monthly_distribute_last_run', ?)
                                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param('s', $curPeriod);
            $stmt->execute();
            $stmt->close();

            return $out;
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

/* ===========================================================================
 * (B) Wallet System — P2P transfer + Wallet->Bank withdrawal
 * ========================================================================= */

if (!function_exists('wallet_balance_of')) {
    function wallet_balance_of(mysqli $conn, string $customerId): float
    {
        try {
            $stmt = $conn->prepare("SELECT wallet_balance FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $customerId);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return (float)($r['wallet_balance'] ?? 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}

if (!function_exists('wallet_ledger_add')) {
    function wallet_ledger_add(mysqli $conn, string $customerId, string $direction, string $category, float $amount, float $balanceAfter, array $opts = []): void
    {
        if (!income_table_exists($conn, 'wallet_transactions')) {
            return;
        }
        $dir = $direction === 'debit' ? 'debit' : 'credit';
        $cp = (string)($opts['counterparty_id'] ?? '');
        $rt = (string)($opts['ref_type'] ?? '');
        $ri = isset($opts['ref_id']) ? (int)$opts['ref_id'] : null;
        $note = (string)($opts['note'] ?? '');
        $ins = $conn->prepare("INSERT INTO wallet_transactions
            (customer_id, direction, category, amount, balance_after, counterparty_id, ref_type, ref_id, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $ins->bind_param('sssddssis', $customerId, $dir, $category, $amount, $balanceAfter, $cp, $rt, $ri, $note);
        $ins->execute();
        $ins->close();
    }
}

if (!function_exists('wallet_p2p_transfer')) {
    /**
     * Member-to-member wallet transfer. `wallet_p2p_deduction_percent` is
     * deducted from the amount; the recipient receives the net.
     */
    function wallet_p2p_transfer(mysqli $conn, string $fromId, string $toId, float $amount, string $note = ''): array
    {
        try {
            if (!income_table_exists($conn, 'p2p_transfers')) {
                return ['ok' => false, 'message' => 'Wallet transfers are not available.'];
            }
            if (!income_setting_bool($conn, 'wallet_p2p_enabled', true)) {
                return ['ok' => false, 'message' => 'P2P transfers are currently disabled.'];
            }
            $fromId = trim($fromId);
            $toId = trim($toId);
            $amount = round((float)$amount, 2);

            if ($fromId === '' || $toId === '') {
                return ['ok' => false, 'message' => 'Sender and receiver are required.'];
            }
            if ($fromId === $toId) {
                return ['ok' => false, 'message' => 'You cannot transfer to your own wallet.'];
            }
            $min = income_setting_num($conn, 'wallet_p2p_min', 1.0);
            if ($amount < $min) {
                return ['ok' => false, 'message' => 'Minimum transfer is ' . number_format($min, 2) . '.'];
            }

            // recipient must exist
            $stmt = $conn->prepare("SELECT full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $toId);
            $stmt->execute();
            $to = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$to) {
                return ['ok' => false, 'message' => 'Receiver ID not found.'];
            }
            if (!income_member_is_active($conn, $fromId)) {
                return ['ok' => false, 'message' => 'Your account is not active.'];
            }

            $bal = wallet_balance_of($conn, $fromId);
            if ($bal + 0.001 < $amount) {
                return ['ok' => false, 'message' => 'Insufficient wallet balance.'];
            }

            $pct = income_setting_num($conn, 'wallet_p2p_deduction_percent', 15.0);
            $deduction = round($amount * $pct / 100, 2);
            $net = round($amount - $deduction, 2);

            // debit sender
            $u = $conn->prepare("UPDATE signup_users SET wallet_balance = wallet_balance - ? WHERE customer_id = ?");
            $u->bind_param('ds', $amount, $fromId);
            $u->execute();
            $u->close();
            $fromAfter = wallet_balance_of($conn, $fromId);

            // credit receiver (net)
            $u = $conn->prepare("UPDATE signup_users SET wallet_balance = wallet_balance + ? WHERE customer_id = ?");
            $u->bind_param('ds', $net, $toId);
            $u->execute();
            $u->close();
            $toAfter = wallet_balance_of($conn, $toId);

            $ins = $conn->prepare("INSERT INTO p2p_transfers
                (from_customer_id, to_customer_id, gross_amount, deduction_percent, deduction_amount, net_amount, status, note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Completed', ?, NOW())");
            $ins->bind_param('ssdddds', $fromId, $toId, $amount, $pct, $deduction, $net, $note);
            $ins->execute();
            $tid = (int)$ins->insert_id;
            $ins->close();

            wallet_ledger_add($conn, $fromId, 'debit', 'p2p_send', $amount, $fromAfter, ['counterparty_id' => $toId, 'ref_type' => 'p2p', 'ref_id' => $tid, 'note' => $note]);
            wallet_ledger_add($conn, $toId, 'credit', 'p2p_receive', $net, $toAfter, ['counterparty_id' => $fromId, 'ref_type' => 'p2p', 'ref_id' => $tid, 'note' => $note]);

            return ['ok' => true, 'transfer_id' => $tid, 'gross' => $amount, 'deduction_percent' => $pct,
                    'deduction' => $deduction, 'net' => $net, 'to_name' => (string)($to['full_name'] ?? ''),
                    'message' => 'Transferred ' . number_format($net, 2) . ' to ' . $toId . ' (' . number_format($deduction, 2) . ' deducted).'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Transfer failed: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('withdrawal_allowed_today')) {
    function withdrawal_allowed_today(mysqli $conn, ?string $ymd = null): bool
    {
        if (!income_setting_bool($conn, 'withdrawal_enforce_days', true)) {
            return true;
        }
        $d = $ymd ? (int)(new DateTime($ymd))->format('j') : (int)date('j');
        return in_array($d, income_withdrawal_days($conn), true);
    }
}

if (!function_exists('withdrawal_count')) {
    /** Count of the member's non-rejected withdrawal requests. */
    function withdrawal_count(mysqli $conn, string $customerId): int
    {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) c FROM withdrawal_requests WHERE customer_id = ? AND status <> 'Rejected'");
            $stmt->bind_param('s', $customerId);
            $stmt->execute();
            $c = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
            return $c;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('withdrawal_cap_for')) {
    function withdrawal_cap_for(mysqli $conn, int $requestNo): float
    {
        if ($requestNo <= 1) {
            return income_setting_num($conn, 'withdrawal_max_1', 500.0);
        }
        if ($requestNo === 2) {
            return income_setting_num($conn, 'withdrawal_max_2', 500.0);
        }
        return income_setting_num($conn, 'withdrawal_max_3plus', 2000.0);
    }
}

if (!function_exists('withdrawal_request_create')) {
    function withdrawal_request_create(mysqli $conn, string $customerId, float $amount, string $note = ''): array
    {
        try {
            if (!income_table_exists($conn, 'withdrawal_requests')) {
                return ['ok' => false, 'message' => 'Withdrawals are not available.'];
            }
            if (!income_setting_bool($conn, 'withdrawal_enabled', true)) {
                return ['ok' => false, 'message' => 'Withdrawals are currently disabled.'];
            }
            $customerId = trim($customerId);
            $amount = round((float)$amount, 2);
            if ($customerId === '') {
                return ['ok' => false, 'message' => 'Member is required.'];
            }
            if (!income_member_is_active($conn, $customerId)) {
                return ['ok' => false, 'message' => 'Your account is not active.'];
            }
            if (!withdrawal_allowed_today($conn)) {
                $days = implode(', ', income_withdrawal_days($conn));
                return ['ok' => false, 'message' => 'Withdrawals are only allowed on: ' . $days . ' of each month.'];
            }

            // a member can have only one Pending request at a time
            $stmt = $conn->prepare("SELECT id FROM withdrawal_requests WHERE customer_id = ? AND status = 'Pending' LIMIT 1");
            $stmt->bind_param('s', $customerId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_row()) {
                $stmt->close();
                return ['ok' => false, 'message' => 'You already have a pending withdrawal request.'];
            }
            $stmt->close();

            $min = income_setting_num($conn, 'withdrawal_min', 1.0);
            if ($amount < $min) {
                return ['ok' => false, 'message' => 'Minimum withdrawal is ' . number_format($min, 2) . '.'];
            }

            $requestNo = withdrawal_count($conn, $customerId) + 1;
            $cap = withdrawal_cap_for($conn, $requestNo);
            if ($amount > $cap + 0.001) {
                $sfx = ($requestNo % 10 === 1 && $requestNo % 100 !== 11) ? 'st'
                     : (($requestNo % 10 === 2 && $requestNo % 100 !== 12) ? 'nd'
                     : (($requestNo % 10 === 3 && $requestNo % 100 !== 13) ? 'rd' : 'th'));
                return ['ok' => false, 'message' => 'Your ' . $requestNo . $sfx . ' withdrawal is capped at ' . number_format($cap, 2) . '.'];
            }

            $bal = wallet_balance_of($conn, $customerId);
            if ($bal + 0.001 < $amount) {
                return ['ok' => false, 'message' => 'Insufficient wallet balance.'];
            }

            $pct = income_setting_num($conn, 'wallet_bank_deduction_percent', 20.0);
            $deduction = round($amount * $pct / 100, 2);
            $net = round($amount - $deduction, 2);

            // bank snapshot (best effort)
            $bankSnap = null;
            if (income_table_exists($conn, 'member_bank_kyc_details')) {
                try {
                    $bs = $conn->query("SELECT * FROM member_bank_kyc_details WHERE customer_id = '" . $conn->real_escape_string($customerId) . "' ORDER BY id DESC LIMIT 1")->fetch_assoc();
                    if ($bs) {
                        unset($bs['id'], $bs['customer_id']);
                        $bankSnap = substr(json_encode($bs), 0, 500);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // hold: debit wallet now
            $u = $conn->prepare("UPDATE signup_users SET wallet_balance = wallet_balance - ? WHERE customer_id = ?");
            $u->bind_param('ds', $amount, $customerId);
            $u->execute();
            $u->close();
            $after = wallet_balance_of($conn, $customerId);

            $ins = $conn->prepare("INSERT INTO withdrawal_requests
                (customer_id, request_no, gross_amount, cap_applied, deduction_percent, deduction_amount, net_amount, method, bank_snapshot, status, note, requested_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'bank', ?, 'Pending', ?, NOW())");
            $ins->bind_param('sidddddss', $customerId, $requestNo, $amount, $cap, $pct, $deduction, $net, $bankSnap, $note);
            $ins->execute();
            $wid = (int)$ins->insert_id;
            $ins->close();

            wallet_ledger_add($conn, $customerId, 'debit', 'withdrawal_hold', $amount, $after, ['ref_type' => 'withdrawal', 'ref_id' => $wid, 'note' => 'Withdrawal request #' . $requestNo]);

            return ['ok' => true, 'request_id' => $wid, 'request_no' => $requestNo, 'gross' => $amount,
                    'cap' => $cap, 'deduction_percent' => $pct, 'deduction' => $deduction, 'net' => $net,
                    'message' => 'Withdrawal request submitted. Payable to bank: ' . number_format($net, 2) .
                                 ' (' . number_format($deduction, 2) . ' deducted).'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Request failed: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('withdrawal_process')) {
    /**
     * @param string $action approve | pay | reject
     */
    function withdrawal_process(mysqli $conn, int $requestId, string $action, string $adminName = '', string $adminId = '', string $adminNote = ''): array
    {
        try {
            $row = $conn->query("SELECT * FROM withdrawal_requests WHERE id = " . (int)$requestId . " LIMIT 1")->fetch_assoc();
            if (!$row) {
                return ['ok' => false, 'message' => 'Request not found.'];
            }
            if (in_array($row['status'], ['Paid', 'Rejected'], true)) {
                return ['ok' => false, 'message' => 'Request is already ' . $row['status'] . '.'];
            }

            $action = strtolower($action);
            if ($action === 'approve') {
                $st = $conn->prepare("UPDATE withdrawal_requests SET status='Approved', processed_at=NOW(), processed_by=?, admin_note=? WHERE id=?");
                $st->bind_param('ssi', $adminName, $adminNote, $requestId);
                $st->execute();
                $st->close();
                return ['ok' => true, 'status' => 'Approved'];
            }
            if ($action === 'pay') {
                $st = $conn->prepare("UPDATE withdrawal_requests SET status='Paid', processed_at=NOW(), processed_by=?, admin_note=? WHERE id=?");
                $st->bind_param('ssi', $adminName, $adminNote, $requestId);
                $st->execute();
                $st->close();
                return ['ok' => true, 'status' => 'Paid'];
            }
            if ($action === 'reject') {
                // refund the held amount
                $cid = (string)$row['customer_id'];
                $amt = (float)$row['gross_amount'];
                $u = $conn->prepare("UPDATE signup_users SET wallet_balance = wallet_balance + ? WHERE customer_id = ?");
                $u->bind_param('ds', $amt, $cid);
                $u->execute();
                $u->close();
                $after = wallet_balance_of($conn, $cid);
                wallet_ledger_add($conn, $cid, 'credit', 'withdrawal_refund', $amt, $after, ['ref_type' => 'withdrawal', 'ref_id' => $requestId, 'note' => 'Rejected withdrawal refund']);

                $st = $conn->prepare("UPDATE withdrawal_requests SET status='Rejected', processed_at=NOW(), processed_by=?, admin_note=? WHERE id=?");
                $st->bind_param('ssi', $adminName, $adminNote, $requestId);
                $st->execute();
                $st->close();
                return ['ok' => true, 'status' => 'Rejected', 'refunded' => $amt];
            }
            return ['ok' => false, 'message' => 'Unknown action.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Process failed: ' . $e->getMessage()];
        }
    }
}

/* ===========================================================================
 * Joining 50/50 split + Income Distribution Pool
 * ========================================================================= */

if (!function_exists('income_pool_balance')) {
    function income_pool_balance(mysqli $conn): float
    {
        if (!income_table_exists($conn, 'income_pool_ledger')) {
            return 0.0;
        }
        try {
            $r = $conn->query("SELECT
                COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN direction='debit'  THEN amount ELSE 0 END),0) AS bal
              FROM income_pool_ledger")->fetch_assoc();
            return (float)($r['bal'] ?? 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}

if (!function_exists('income_pool_add')) {
    function income_pool_add(mysqli $conn, string $direction, string $category, float $amount, array $opts = []): void
    {
        if (!income_table_exists($conn, 'income_pool_ledger') || $amount <= 0) {
            return;
        }
        try {
            $dir = $direction === 'debit' ? 'debit' : 'credit';
            $bal = income_pool_balance($conn);
            $balAfter = $dir === 'credit' ? $bal + $amount : $bal - $amount;
            $cid = (string)($opts['customer_id'] ?? '');
            $itype = (string)($opts['income_type'] ?? '');
            $ref = (string)($opts['ref'] ?? '');
            $ins = $conn->prepare("INSERT INTO income_pool_ledger
                (direction, category, amount, balance_after, customer_id, income_type, ref, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $ins->bind_param('ssddsss', $dir, $category, $amount, $balAfter, $cid, $itype, $ref);
            $ins->execute();
            $ins->close();
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}

if (!function_exists('apply_joining_split')) {
    /**
     * At activation: split the joining amount.
     *   own_fund_percent%  -> member's own product fund (held for their EV)
     *   remainder          -> Income Distribution Pool
     * Idempotent (one split per member).
     */
    function apply_joining_split(mysqli $conn, string $memberId, float $joiningAmount, ?int $refId = null): array
    {
        try {
            if ($memberId === '' || !income_table_exists($conn, 'joining_splits')) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'not_configured'];
            }
            if (!income_setting_bool($conn, 'joining_split_enabled', true)) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'disabled'];
            }
            $joiningAmount = round((float)$joiningAmount, 2);
            if ($joiningAmount <= 0) {
                return ['ok' => false, 'status' => 'skipped', 'message' => 'zero_amount'];
            }

            // already split?
            $chk = $conn->prepare("SELECT id FROM joining_splits WHERE customer_id = ? LIMIT 1");
            $chk->bind_param('s', $memberId);
            $chk->execute();
            if ($chk->get_result()->fetch_row()) {
                $chk->close();
                return ['ok' => false, 'status' => 'skipped', 'message' => 'already_split'];
            }
            $chk->close();

            $pct = income_setting_num($conn, 'joining_own_fund_percent', 50.0);
            $ownFund = round($joiningAmount * $pct / 100, 2);
            $incomePool = round($joiningAmount - $ownFund, 2);

            // member's own product fund (held)
            if ($ownFund > 0) {
                $u = $conn->prepare("UPDATE signup_users SET product_fund_balance = product_fund_balance + ? WHERE customer_id = ?");
                $u->bind_param('ds', $ownFund, $memberId);
                $u->execute();
                $u->close();
            }

            $ins = $conn->prepare("INSERT INTO joining_splits
                (customer_id, joining_amount, own_fund_percent, own_fund_amount, income_pool_amount, ref_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $ins->bind_param('sddddi', $memberId, $joiningAmount, $pct, $ownFund, $incomePool, $refId);
            $ins->execute();
            $ins->close();

            income_pool_add($conn, 'credit', 'joining', $incomePool, [
                'customer_id' => $memberId, 'ref' => 'JOIN-' . $memberId,
            ]);

            return ['ok' => true, 'status' => 'split', 'member' => $memberId,
                    'joining_amount' => $joiningAmount, 'own_fund' => $ownFund, 'income_pool' => $incomePool];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
