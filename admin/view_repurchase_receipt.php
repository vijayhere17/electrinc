<?php
session_start();

include __DIR__ . '/config/db.php';

function failWithStatus(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

function firstExistingColumn(array $columns, array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return '';
}

function normalizeRelativePath(string $path): string
{
    $cleanPath = trim(str_replace('\\', '/', $path));
    if ($cleanPath === '' || str_contains($cleanPath, '..') || str_contains($cleanPath, "\0")) {
        return '';
    }
    return ltrim($cleanPath, '/');
}

function buildReceiptCandidatePaths(string $relativePath): array
{
    $relativeSystemPath = str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $fileName = basename($relativeSystemPath);

    $adminDir = __DIR__;
    $projectRoot = dirname(__DIR__);
    $htdocsRoot = dirname($projectRoot);

    $candidates = [];

    $candidates[] = $adminDir . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $adminDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;

    // Current workspace paths
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'member' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment-slips' . DIRECTORY_SEPARATOR . $fileName;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment_slips' . DIRECTORY_SEPARATOR . $fileName;

    // Legacy/alternate folder names that may exist on older setups
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshiya_member' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshiya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshiya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment-slips' . DIRECTORY_SEPARATOR . $fileName;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshiya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment_slips' . DIRECTORY_SEPARATOR . $fileName;

    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshya_member' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment-slips' . DIRECTORY_SEPARATOR . $fileName;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payment_slips' . DIRECTORY_SEPARATOR . $fileName;

    return array_values(array_unique($candidates));
}

$rowId = (int)($_GET['row_id'] ?? 0);
if ($rowId <= 0) {
    failWithStatus(400, 'Invalid request id.');
}

$tableRes = $conn->query("SHOW TABLES LIKE 'repurchase_orders'");
if (!$tableRes || $tableRes->num_rows === 0) {
    failWithStatus(404, 'repurchase_orders table not found.');
}

$columns = [];
$colRes = $conn->query('SHOW COLUMNS FROM repurchase_orders');
if (!$colRes) {
    failWithStatus(500, 'Unable to read repurchase_orders columns.');
}
while ($col = $colRes->fetch_assoc()) {
    $columns[] = (string)($col['Field'] ?? '');
}

$idColumn = firstExistingColumn($columns, ['id']);
$receiptColumn = firstExistingColumn($columns, ['payment_slip', 'receipt', 'receipt_file', 'slip', 'payment_receipt']);

if ($idColumn === '' || $receiptColumn === '') {
    failWithStatus(404, 'Required columns not found in repurchase_orders.');
}

$sql = "SELECT {$receiptColumn} AS receipt_file FROM repurchase_orders WHERE {$idColumn} = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    failWithStatus(500, 'Failed to prepare query.');
}

$stmt->bind_param('i', $rowId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
$stmt->close();

if (!is_array($row)) {
    failWithStatus(404, 'Receipt row not found.');
}

$receiptValue = trim((string)($row['receipt_file'] ?? ''));
if ($receiptValue === '') {
    failWithStatus(404, 'Receipt not submitted.');
}

$relativePath = normalizeRelativePath($receiptValue);
if ($relativePath === '') {
    failWithStatus(404, 'Receipt path is invalid.');
}

$candidatePaths = buildReceiptCandidatePaths($relativePath);

$absolutePath = '';
foreach ($candidatePaths as $candidatePath) {
    if (is_file($candidatePath)) {
        $absolutePath = $candidatePath;
        break;
    }
}

if ($absolutePath === '') {
    failWithStatus(404, 'Receipt file not found on server.');
}

$mimeType = function_exists('mime_content_type') ? (string)mime_content_type($absolutePath) : '';
if ($mimeType === '') {
    $extension = strtolower((string)pathinfo($absolutePath, PATHINFO_EXTENSION));
    $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];
    $mimeType = $mimeMap[$extension] ?? 'application/octet-stream';
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($absolutePath));
header('Content-Disposition: inline; filename="' . basename($absolutePath) . '"');
header('X-Content-Type-Options: nosniff');

readfile($absolutePath);
exit;
