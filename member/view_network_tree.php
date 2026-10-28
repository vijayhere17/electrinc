<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check config/db.php");
}

$conn->set_charset("utf8mb4");

$memberName   = $_SESSION['full_name'] ?? 'Member';
$customerId   = $_SESSION['customer_id'] ?? 'fresh0003';
$managerName  = $_SESSION['full_name'] ?? 'Member';
$managerRole  = 'Distributor';
$profileImage = "https://i.pravatar.cc/80?img=12";

$rootId = trim($_GET['customer_id'] ?? '');
if ($rootId === '') {
    $rootId = $customerId;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return !empty($row['c']);
}

function getMember(mysqli $conn, string $customerId): ?array
{
    $hasStatus = columnExists($conn, 'signup_users', 'registration_status');

    $sql = $hasStatus
        ? "SELECT customer_id, full_name, placement_id, position, registration_status
           FROM signup_users
           WHERE customer_id = ?
           LIMIT 1"
        : "SELECT customer_id, full_name, placement_id, position, 'Active' AS registration_status
           FROM signup_users
           WHERE customer_id = ?
           LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function getChildren(mysqli $conn, string $placementId): array
{
    $hasStatus = columnExists($conn, 'signup_users', 'registration_status');

    $sql = $hasStatus
        ? "SELECT customer_id, full_name, placement_id, position, registration_status
           FROM signup_users
           WHERE placement_id = ?
           ORDER BY id ASC"
        : "SELECT customer_id, full_name, placement_id, position, 'Active' AS registration_status
           FROM signup_users
           WHERE placement_id = ?
           ORDER BY id ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['L' => null, 'R' => null];
    }

    $stmt->bind_param("s", $placementId);
    $stmt->execute();
    $result = $stmt->get_result();

    $children = ['L' => null, 'R' => null];

    while ($row = $result->fetch_assoc()) {
        $pos = strtoupper(trim((string)($row['position'] ?? '')));
        if ($pos === 'L') {
            $children['L'] = $row;
        } elseif ($pos === 'R') {
            $children['R'] = $row;
        }
    }

    $stmt->close();
    return $children;
}

function countDirectSide(mysqli $conn, string $memberId, string $side): int
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total_count
        FROM signup_users
        WHERE placement_id = ? AND position = ?
    ");

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("ss", $memberId, $side);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total_count'] ?? 0);
}

function buildTree(mysqli $conn, ?array $member, int $depth): ?array
{
    if ($member === null) {
        if ($depth <= 0) {
            return null;
        }

        return [
            'member' => null,
            'left'   => buildTree($conn, null, $depth - 1),
            'right'  => buildTree($conn, null, $depth - 1),
        ];
    }

    if ($depth <= 0) {
        return [
            'member' => $member,
            'left'   => null,
            'right'  => null,
        ];
    }

    $children = getChildren($conn, $member['customer_id']);

    return [
        'member' => $member,
        'left'   => buildTree($conn, $children['L'], $depth - 1),
        'right'  => buildTree($conn, $children['R'], $depth - 1),
    ];
}

function getNodeMeta(?array $member): array
{
    if (!$member) {
        return [
            'boxClass' => 'bg-[#eef1f4] text-[#64748b]',
            'id' => '',
            'name' => '',
        ];
    }

    $status = strtolower(trim((string)($member['registration_status'] ?? 'active')));

    if ($status === 'active') {
        return [
            'boxClass' => 'bg-[#dff3e8] text-[#18a558]',
            'id' => $member['customer_id'] ?? '',
            'name' => $member['full_name'] ?? '',
        ];
    }

    return [
        'boxClass' => 'bg-[#f8e2df] text-[#d44734]',
        'id' => $member['customer_id'] ?? '',
        'name' => $member['full_name'] ?? '',
    ];
}

function renderTreeNode(?array $treeNode): string
{
    if ($treeNode === null) {
        return '';
    }

    $member = $treeNode['member'] ?? null;
    $left   = $treeNode['left'] ?? null;
    $right  = $treeNode['right'] ?? null;

    $meta = getNodeMeta($member);
    $hasChildren = ($left !== null || $right !== null);

    $html = '<li>';
    $html .= '<div class="tree-node-wrap">';
    $html .= '<div class="tree-icon '.$meta['boxClass'].'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-[20px] h-[20px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.5 20.25a7.5 7.5 0 1 1 15 0" />
                </svg>
              </div>';

    if ($member !== null) {
        $html .= '<div class="tree-id">'.htmlspecialchars($meta['id']).'</div>';
        $html .= '<div class="tree-name">'.htmlspecialchars($meta['name']).'</div>';
    }

    $html .= '</div>';

    if ($hasChildren) {
        $html .= '<ul>';
        $html .= renderTreeNode($left);
        $html .= renderTreeNode($right);
        $html .= '</ul>';
    }

    $html .= '</li>';

    return $html;
}

$rootMember = getMember($conn, $rootId);

if (!$rootMember) {
    die("Member not found.");
}

$tree = buildTree($conn, $rootMember, 3);

$leftCount  = countDirectSide($conn, $rootId, 'L');
$rightCount = countDirectSide($conn, $rootId, 'R');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Builtup Tree</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body{
            font-family: Arial, Helvetica, sans-serif;
            background:#f3f3f3;
        }

        .tree,
        .tree ul{
            padding-top: 26px;
            position: relative;
            transition: all .2s;
        }

        .tree ul{
            display: flex;
            justify-content: center;
        }

        .tree li{
            list-style-type: none;
            text-align: center;
            position: relative;
            padding: 26px 18px 0 18px;
            transition: all .2s;
        }

        .tree li::before,
        .tree li::after{
            content: '';
            position: absolute;
            top: 0;
            right: 50%;
            border-top: 1px solid #d6d6d6;
            width: 50%;
            height: 26px;
        }

        .tree li::after{
            right: auto;
            left: 50%;
            border-left: 1px solid #d6d6d6;
        }

        .tree li:only-child::after,
        .tree li:only-child::before{
            display: none;
        }

        .tree li:only-child{
            padding-top: 0;
        }

        .tree li:first-child::before,
        .tree li:last-child::after{
            border: 0 none;
        }

        .tree li:last-child::before{
            border-right: 1px solid #d6d6d6;
            border-radius: 0 5px 0 0;
        }

        .tree li:first-child::after{
            border-radius: 5px 0 0 0;
        }

        .tree ul ul::before{
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            border-left: 1px solid #d6d6d6;
            width: 0;
            height: 26px;
        }

        .tree-node-wrap{
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            min-width: 88px;
        }

        .tree-icon{
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }

        .tree-id{
            margin-top: 8px;
            font-size: 12px;
            color: #7a7a7a;
            line-height: 1;
            white-space: nowrap;
        }

        .tree-name{
            margin-top: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #2f2f2f;
            line-height: 1.2;
            white-space: nowrap;
        }

        @media (max-width: 1200px){
            .tree-scale{
                transform: scale(.9);
                transform-origin: top center;
            }
        }

        @media (max-width: 992px){
            .tree-scale{
                transform: scale(.78);
                transform-origin: top center;
            }
        }
    </style>
</head>
<body class="bg-[#f3f3f3]">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <div class="px-6 py-6">
            <div class="max-w-[1320px] mx-auto">
                <div class="mt-5 flex items-center gap-4">
                    <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                        <i data-lucide="badge-check" class="w-[18px] h-[18px] text-[#cd9737]"></i>
                    </div>

                    <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]" style="font-family: Georgia, 'Times New Roman', serif;">
                        NETWORK TREE
                    </h2>

                    <div class="w-full h-[2px] bg-[#d1a14b]"></div>
                </div>

                <div class="mt-6 rounded-[16px] border border-[#dddddd] bg-[#f8f8f8] px-8 py-8 min-h-[780px] overflow-x-auto">
                    <form method="GET" class="flex items-center justify-center gap-4 mb-12">
                        <label class="text-[16px] text-[#333]">Search Associate :</label>
                        <input
                            type="text"
                            name="customer_id"
                            value="<?php echo htmlspecialchars($rootId); ?>"
                            class="w-[180px] h-[38px] rounded-[10px] border border-[#e2e2e2] bg-[#eef0f3] px-3 outline-none"
                        >
                        <button
                            type="submit"
                            class="h-[38px] px-8 rounded-[10px] bg-[#ba4a2c] text-white text-[14px] font-semibold"
                        >
                            Search
                        </button>
                    </form>

                    <div class="flex items-center justify-center gap-8 mb-12">
                        <div class="flex flex-col items-center">
                            <div class="w-[50px] h-[50px] rounded-[14px] bg-[#eef1f4] text-[#64748b] flex items-center justify-center">
                                <i data-lucide="user-round" class="w-[22px] h-[22px]"></i>
                            </div>
                            <div class="mt-2 text-[13px] text-[#666]">Empty</div>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="w-[50px] h-[50px] rounded-[14px] bg-[#f8e2df] text-[#d44734] flex items-center justify-center">
                                <i data-lucide="user-round" class="w-[22px] h-[22px]"></i>
                            </div>
                            <div class="mt-2 text-[13px] text-[#666]">In Process</div>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="w-[50px] h-[50px] rounded-[14px] bg-[#dff3e8] text-[#18a558] flex items-center justify-center">
                                <i data-lucide="user-round" class="w-[22px] h-[22px]"></i>
                            </div>
                            <div class="mt-2 text-[13px] text-[#666]">Active</div>
                        </div>
                    </div>

                    <div class="flex justify-between items-start mb-8 text-[#1d67c5] text-[15px] font-semibold leading-8">
                        <div>
                            <div>Left : <?php echo $leftCount; ?></div>
                            <div>Left PV : 0</div>
                            <div>Left BV : 0</div>
                        </div>

                        <div class="text-right">
                            <div>Right : <?php echo $rightCount; ?></div>
                            <div>Right PV : 0</div>
                            <div>Right BV : 0</div>
                        </div>
                    </div>

                    <div class="flex justify-center overflow-x-auto">
                        <div class="tree-scale min-w-[980px]">
                            <div class="tree">
                                <ul>
                                    <?php echo renderTreeNode($tree); ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>