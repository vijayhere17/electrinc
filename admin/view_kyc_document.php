<?php
session_start();
include __DIR__ . '/config/db.php';

/*
|--------------------------------------------------------------------------
| Error Function
|--------------------------------------------------------------------------
*/
function stopMessage($msg)
{
    echo "<h3 style='font-family:Arial'>" . htmlspecialchars($msg) . "</h3>";
    exit;
}

function tableExists(mysqli $conn, string $tableName): bool
{
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeName}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function columnExists(mysqli $conn, string $tableName, string $column): bool
{
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function firstExistingColumn(mysqli $conn, string $tableName, array $candidates): ?string
{
    foreach ($candidates as $column) {
        if (columnExists($conn, $tableName, $column)) {
            return $column;
        }
    }
    return null;
}

function normalizeRelativePath(string $path): string
{
    $cleanPath = trim(str_replace('\\', '/', $path));
    if ($cleanPath === '' || str_contains($cleanPath, '..') || str_contains($cleanPath, "\0")) {
        return '';
    }
    return ltrim($cleanPath, '/');
}

function buildCandidatePaths(string $relativePath): array
{
    $relativeSystemPath = str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $fileName = basename($relativeSystemPath);

    $adminDir = __DIR__;
    $projectRoot = dirname(__DIR__);
    $htdocsRoot = dirname($projectRoot);

    $candidates = [];
    $candidates[] = $adminDir . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'member' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kyc' . DIRECTORY_SEPARATOR . $fileName;
    $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'member_kyc' . DIRECTORY_SEPARATOR . $fileName;

    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshiya_member' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshiya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshiya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kyc' . DIRECTORY_SEPARATOR . $fileName;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshiya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'member_kyc' . DIRECTORY_SEPARATOR . $fileName;

    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshya_member' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $relativeSystemPath;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kyc' . DIRECTORY_SEPARATOR . $fileName;
    $candidates[] = $htdocsRoot . DIRECTORY_SEPARATOR . 'freshya_member' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'member_kyc' . DIRECTORY_SEPARATOR . $fileName;

    return array_values(array_unique($candidates));
}

/*
|--------------------------------------------------------------------------
| Validate Input
|--------------------------------------------------------------------------
*/
$customerId = $_GET['customer_id'] ?? '';
$docType    = $_GET['doc'] ?? '';

if ($customerId === '' || !in_array($docType, ['pan', 'aadhar', 'aadhar_front', 'aadhar_back'], true)) {
    stopMessage("Invalid request.");
}

/*
|--------------------------------------------------------------------------
| Get File Name From Database
|--------------------------------------------------------------------------
*/
$primaryCandidates = [];
$fallbackCandidates = [];

if ($docType === 'pan') {
    $primaryCandidates = ['pan_file', 'pan_photo', 'pan_image'];
    $fallbackCandidates = ['pan_photo', 'pan_file', 'pan_image'];
} elseif ($docType === 'aadhar_front') {
    $primaryCandidates = ['aadhar_front_file', 'aadhaar_front_file', 'aadhar_front_photo', 'aadhaar_front_photo', 'aadhar_file', 'aadhaar_file'];
    $fallbackCandidates = ['aadhar_front_photo', 'aadhaar_front_photo', 'aadhar_photo', 'aadhaar_photo', 'aadhar_file', 'aadhaar_file'];
} elseif ($docType === 'aadhar_back') {
    $primaryCandidates = ['aadhar_back_file', 'aadhaar_back_file', 'aadhar_back_photo', 'aadhaar_back_photo', 'aadhar_file', 'aadhaar_file'];
    $fallbackCandidates = ['aadhar_back_photo', 'aadhaar_back_photo', 'aadhar_photo', 'aadhaar_photo', 'aadhar_file', 'aadhaar_file'];
} else {
    $primaryCandidates = ['aadhar_file', 'aadhaar_file', 'aadhar_photo', 'aadhaar_photo'];
    $fallbackCandidates = ['aadhar_photo', 'aadhaar_photo', 'aadhar_file', 'aadhaar_file'];
}

$primaryColumn = firstExistingColumn($conn, 'member_kyc_files', $primaryCandidates);
$fallbackColumn = firstExistingColumn($conn, 'member_bank_kyc_details', $fallbackCandidates);

$rawFilePath = '';

if ($primaryColumn !== null && tableExists($conn, 'member_kyc_files') && columnExists($conn, 'member_kyc_files', 'customer_id')) {
    $stmt = $conn->prepare(" 
        SELECT {$primaryColumn} AS doc_file
        FROM member_kyc_files
        WHERE customer_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('s', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (is_array($row)) {
            $rawFilePath = trim((string)($row['doc_file'] ?? ''));
        }
    }
}

if ($rawFilePath === '' && $fallbackColumn !== null && tableExists($conn, 'member_bank_kyc_details') && columnExists($conn, 'member_bank_kyc_details', 'customer_id')) {
    $stmt = $conn->prepare(" 
        SELECT {$fallbackColumn} AS doc_file
        FROM member_bank_kyc_details
        WHERE customer_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('s', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (is_array($row)) {
            $rawFilePath = trim((string)($row['doc_file'] ?? ''));
        }
    }
}

if ($rawFilePath === '') {
    stopMessage("KYC file not found in database.");
}

/*
|--------------------------------------------------------------------------
| Clean Path
|--------------------------------------------------------------------------
*/
$filePath = trim(str_replace('\\','/',$rawFilePath));

$jsonCheck = json_decode($filePath,true);
if(is_array($jsonCheck)){
    $filePath = $jsonCheck[0] ?? '';
}

$relativePath = normalizeRelativePath($filePath);
$fileName = basename($relativePath);

if($fileName==''){
    stopMessage("Invalid file path.");
}

$candidatePaths = buildCandidatePaths($relativePath);
$realFile = '';
foreach ($candidatePaths as $candidatePath) {
    if (is_file($candidatePath)) {
        $realFile = $candidatePath;
        break;
    }
}

if ($realFile == '') {
    stopMessage("Receipt file not found on server.");
}

/*
|--------------------------------------------------------------------------
| Detect MIME TYPE
|--------------------------------------------------------------------------
*/
$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));

$mime = [
    'jpg'=>'image/jpeg',
    'jpeg'=>'image/jpeg',
    'png'=>'image/png',
    'pdf'=>'application/pdf',
    'webp'=>'image/webp'
];

$contentType = $mime[$ext] ?? 'application/octet-stream';

/*
|--------------------------------------------------------------------------
| OUTPUT FILE
|--------------------------------------------------------------------------
*/
header("Content-Type: ".$contentType);
header("Content-Length: ".filesize($realFile));
header("Content-Disposition: inline; filename=\"".basename($realFile)."\"");

readfile($realFile);
exit;
?>