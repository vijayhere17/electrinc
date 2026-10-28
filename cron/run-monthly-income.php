<?php
/**
 * Monthly bonus auto-distribution.
 *
 * Settles the previous month's Performance Bonus and Team Performance /
 * Matching Bonus. Safe to run repeatedly — it distributes each finished
 * month only once (guarded by the `monthly_distribute_last_run` setting).
 *
 * The admin-configurable day lives in Admin > Income & Business Rules
 * ("Monthly bonus payout day", default 1). This script respects it, unless
 * you pass  --force  to distribute any finished month right now.
 *
 * Run from the shell / Task Scheduler:
 *   "C:\xampp\php\php.exe" "C:\xampp\htdocs\IND\cron\run-monthly-income.php"
 *
 * Windows Task Scheduler (monthly, day 1, 00:05):
 *   schtasks /Create /SC MONTHLY /D 1 /ST 00:05 /TN "IND Monthly Bonus" ^
 *     /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\IND\cron\run-monthly-income.php\""
 *
 * Or hit it over HTTP from an external cron:
 *   http://localhost/IND/cron/run-monthly-income.php?key=<cron_key>
 */

$force = in_array('--force', $argv ?? [], true) || isset($_GET['force']);

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli('localhost', 'root', '', 'ind_new');
if ($conn->connect_error) {
    // live-server fallback
    $conn = @new mysqli('localhost', 'u594386032_freshiya22', 'Freshiya@123', 'u594386032_freshiya22');
}
if ($conn->connect_error) {
    fwrite(STDERR, "DB connection failed\n");
    http_response_code(500);
    exit(1);
}
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/../includes/income_common.php';

// optional shared-secret when called over HTTP
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    $expected = income_setting($conn, 'cron_key', '');
    if ($expected !== '' && (($_GET['key'] ?? '') !== $expected)) {
        http_response_code(403);
        echo "forbidden\n";
        exit;
    }
}

$res = income_maybe_run_monthly($conn, $force);

echo "Monthly income run: " . ($res['status'] ?? 'unknown') . "\n";
if (!empty($res['period'])) {
    echo "Current period: {$res['period']}\n";
}
foreach (['perf_bonus', 'team_perf'] as $k) {
    if (!isset($res[$k])) {
        continue;
    }
    $r = $res[$k];
    echo strtoupper($k) . ": " . ($r['status'] ?? 'n/a');
    if (!empty($r['periods'])) {
        $done = array_map(static fn($p) => ($p['period'] ?? '?') . '=' . ($p['status'] ?? '?'), $r['periods']);
        echo " [" . implode(', ', $done) . "]";
    }
    echo "\n";
}
