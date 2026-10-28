<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/admin_access_control.php';

if (!isset($_SESSION['member_id']) && !isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');
adminRefreshSessionPermissions($conn);
adminRequireAccessOrRedirect();

$managerName = $_SESSION['full_name'] ?? 'Admin';
$managerRole = !empty($_SESSION['is_super_admin']) ? 'Administrator' : 'Member Admin';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$flash = '';
$error = '';
$selectedMemberId = trim((string)($_GET['member_id'] ?? ($_POST['member_id'] ?? '')));

if (empty($_SESSION['is_super_admin'])) {
    $error = 'Only full admin can update member page access.';
}

$allPages = adminLoadActivePages($conn);
$selectablePages = array_values(array_filter($allPages, static function (array $row): bool {
    $file = $row['file_name'] ?? '';
    return $file !== 'dashboard.php' && $file !== 'member-admin-access.php';
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $selectedPageIds = $_POST['page_ids'] ?? [];
    if (!is_array($selectedPageIds)) {
        $selectedPageIds = [];
    }

    $selectedPageIds = array_values(array_unique(array_map('intval', $selectedPageIds)));

    if ($selectedMemberId === '') {
        $error = 'Please select a member.';
    } else {
        $deleteStmt = $conn->prepare('DELETE FROM member_admin_page_access WHERE member_customer_id = ?');
        if ($deleteStmt) {
            $deleteStmt->bind_param('s', $selectedMemberId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        if (!empty($selectedPageIds)) {
            $insertStmt = $conn->prepare('INSERT IGNORE INTO member_admin_page_access (member_customer_id, page_id) VALUES (?, ?)');
            if ($insertStmt) {
                foreach ($selectedPageIds as $pageId) {
                    $insertStmt->bind_param('si', $selectedMemberId, $pageId);
                    $insertStmt->execute();
                }
                $insertStmt->close();
            }
        }

        $flash = 'Member access updated successfully.';
    }
}

$members = [];
$memberResult = $conn->query("SELECT customer_id, full_name, email, mobile FROM signup_users ORDER BY full_name ASC");
if ($memberResult instanceof mysqli_result) {
    while ($row = $memberResult->fetch_assoc()) {
        $members[] = $row;
    }
}

$memberAccessMap = [];
if ($selectedMemberId !== '') {
    $stmt = $conn->prepare('SELECT page_id FROM member_admin_page_access WHERE member_customer_id = ?');
    if ($stmt) {
        $stmt->bind_param('s', $selectedMemberId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $memberAccessMap[(int)$row['page_id']] = true;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Admin Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
        <?php include __DIR__ . '/includes/navbar.php'; ?>

        <main class="px-8 py-5">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-9 h-9 rounded-[8px] border border-[#ead9b5] bg-[#f8f1e7] flex items-center justify-center shrink-0">
                    <i data-lucide="user-cog" class="w-5 h-5 text-[#c79435]"></i>
                </div>

                <h2 class="text-[24px] font-normal text-[#313131] leading-none tracking-wide whitespace-nowrap" style="font-family: Georgia, 'Times New Roman', serif;">MEMBER ADMIN ACCESS</h2>
                <div class="h-[1px] bg-[#c9963b] flex-1"></div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($flash !== ''): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    <?php echo htmlspecialchars($flash); ?>
                </div>
            <?php endif; ?>

            <div class="w-full rounded-[18px] border border-[#dddddd] bg-white px-6 py-6">
                <form method="GET" class="mb-6 flex flex-wrap items-center gap-3 text-sm text-[#374151]">
                    <label for="member_id" class="font-medium">Select Member</label>
                    <select id="member_id" name="member_id" class="h-10 min-w-[280px] rounded-md border border-[#d1d5db] px-3 outline-none bg-white">
                        <option value="">Choose member</option>
                        <?php foreach ($members as $member): ?>
                            <?php $cid = (string)($member['customer_id'] ?? ''); ?>
                            <option value="<?php echo htmlspecialchars($cid); ?>" <?php echo $selectedMemberId === $cid ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($member['full_name'] ?? 'Member') . ' (' . $cid . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="h-10 rounded-md bg-[#143a66] px-4 text-white font-medium">Load Access</button>
                </form>

                <?php if ($selectedMemberId !== ''): ?>
                    <form method="POST">
                        <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($selectedMemberId); ?>">

                        <div class="mb-4 text-sm text-[#374151]">
                            Member ID: <span class="font-semibold"><?php echo htmlspecialchars($selectedMemberId); ?></span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <?php foreach ($selectablePages as $page): ?>
                                <?php
                                $pageId = (int)$page['id'];
                                $checked = !empty($memberAccessMap[$pageId]);
                                ?>
                                <label class="flex items-center gap-3 rounded-lg border border-[#e2e8f0] px-3 py-2 text-sm text-[#1f2937]">
                                    <input type="checkbox" name="page_ids[]" value="<?php echo $pageId; ?>" <?php echo $checked ? 'checked' : ''; ?> class="h-4 w-4">
                                    <span><?php echo htmlspecialchars($page['page_title']); ?> (<?php echo htmlspecialchars($page['file_name']); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-6 flex items-center gap-3">
                            <button type="submit" class="h-10 rounded-md bg-[#c7922f] px-6 text-white font-medium">Save Access</button>
                            <span class="text-xs text-[#6b7280]">Dashboard is always allowed.</span>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
