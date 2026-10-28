<?php
session_start();

$memberName   = $_SESSION['full_name'] ?? 'Administrator';
$customerId   = $_SESSION['member_id'] ?? '';
$managerName  = $_SESSION['full_name'] ?? 'Administrator';
$managerRole  = 'MLM Manager';
$profileImage = 'https://i.pravatar.cc/80?img=12';

$searchBy    = trim($_GET['search_by'] ?? '');
$searchValue = trim($_GET['search_value'] ?? '');
$filter      = trim($_GET['filter'] ?? 'All');

include __DIR__ . '/config/db.php';

/* ─── Handle Approve / Reject AJAX POST ────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['cid'])) {
    $action = $_POST['action'];
    $cid    = trim($_POST['cid']);

    if (in_array($action, ['Approved', 'Rejected', 'Pending'], true) && $cid !== '') {
        $stmt = $conn->prepare("UPDATE member_bank_kyc_details SET kyc_status = ? WHERE customer_id = ?");
        $stmt->bind_param('ss', $action, $cid);
        $stmt->execute();
        $stmt->close();
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'status' => $action]);
    exit;
}

/* ─── Helper: decode JSON-array photo field or plain string ─────────────── */
function extractFirstPhoto(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    $decoded = json_decode($raw, true);
    if (is_array($decoded) && !empty($decoded)) {
        return trim((string)$decoded[0]);
    }
    return $raw;
}

/* ─── Build WHERE clause ────────────────────────────────────────────────── */
$where  = '1=1';
$params = [];
$types  = '';

$allowedSearch = ['customer_id', 'account_beneficiary_name', 'account_no', 'ifsc_code', 'bank_name'];

if ($searchBy !== '' && $searchValue !== '' && in_array($searchBy, $allowedSearch, true)) {
    $where   .= " AND `{$searchBy}` LIKE ?";
    $params[] = '%' . $searchValue . '%';
    $types   .= 's';
}

if ($filter === 'With KYC') {
    $where .= " AND ((aadhar_photo IS NOT NULL AND aadhar_photo <> '' AND aadhar_photo <> '[]') OR (pan_photo IS NOT NULL AND pan_photo <> '' AND pan_photo <> '[]'))";
} elseif ($filter === 'Missing KYC') {
    $where .= " AND (aadhar_photo IS NULL OR aadhar_photo = '' OR aadhar_photo = '[]') AND (pan_photo IS NULL OR pan_photo = '' OR pan_photo = '[]')";
}

$sql  = "SELECT * FROM member_bank_kyc_details WHERE {$where} ORDER BY customer_id ASC";
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rows   = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ─── Fetch name + mobile from signup_users ────────────────────────────── */
$nameMap = [];
if (!empty($rows)) {
    $customerIds  = array_column($rows, 'customer_id');
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
    $types2       = str_repeat('s', count($customerIds));
    $s = $conn->prepare("SELECT customer_id, full_name, mobile FROM signup_users WHERE customer_id IN ({$placeholders})");
    $s->bind_param($types2, ...$customerIds);
    $s->execute();
    $r2 = $s->get_result();
    while ($row = $r2->fetch_assoc()) {
        $nameMap[$row['customer_id']] = $row;
    }
    $s->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank & KYC Info</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        #toast {
            position: fixed; bottom: 28px; right: 28px; z-index: 9999;
            display: none; align-items: center; gap: 8px;
            padding: 10px 18px; border-radius: 8px; font-size: 14px;
            font-weight: 500; color: #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,.18);
            animation: fadeIn .2s ease;
        }
        @keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

        .btn-approve {
            display:inline-flex; align-items:center; gap:3px;
            padding:2px 10px; border-radius:4px; font-size:13px;
            background:#166534; color:#fff; border:none; cursor:pointer; transition:background .15s;
        }
        .btn-approve:hover { background:#14532d; }

        .btn-reject {
            display:inline-flex; align-items:center; gap:3px;
            padding:2px 10px; border-radius:4px; font-size:13px;
            background:#991b1b; color:#fff; border:none; cursor:pointer; transition:background .15s;
        }
        .btn-reject:hover { background:#7f1d1d; }

        .badge-pending  { display:inline-block; padding:1px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#fef9c3; color:#854d0e; border:1px solid #fde68a; }
        .badge-approved { display:inline-block; padding:1px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#dcfce7; color:#166534; border:1px solid #86efac; }
        .badge-rejected { display:inline-block; padding:1px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

        .doc-thumb {
            width:42px; height:42px; object-fit:cover;
            border-radius:5px; border:1px solid #e5e7eb;
            cursor:pointer; transition:transform .15s;
        }
        .doc-thumb:hover { transform:scale(1.08); }

        /* Lightbox */
        #lightbox {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.82); z-index:99999;
            align-items:center; justify-content:center; flex-direction:column; gap:12px;
        }
        #lightbox img {
            max-width:90vw; max-height:85vh;
            border-radius:8px; box-shadow:0 8px 40px rgba(0,0,0,.6);
        }
        #lightbox-close {
            position:absolute; top:18px; right:22px;
            color:#fff; font-size:28px; cursor:pointer; line-height:1;
        }
    </style>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- Toast -->
<div id="toast"></div>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox(event)">
    <span id="lightbox-close" onclick="document.getElementById('lightbox').style.display='none'">&times;</span>
    <img id="lightbox-img" src="" alt="KYC Document">
    <a id="lightbox-link" href="#" target="_blank"
       class="text-white text-[13px] underline opacity-80">Open full image</a>
</div>

<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-4">

        <!-- Page header -->
        <div class="flex items-center gap-4 mb-4">
            <div class="w-9 h-9 rounded-[8px] bg-[#f6f2ea] flex items-center justify-center shrink-0">
                <i data-lucide="landmark" class="w-4 h-4 text-[#c9983b]"></i>
            </div>
            <h2 class="text-[18px] leading-none text-[#2f2f2f] font-normal whitespace-nowrap"
                style="font-family: Georgia, 'Times New Roman', serif;">
                BANK & KYC INFO
            </h2>
            <div class="h-[1px] bg-[#c9983b] flex-1"></div>
        </div>

        <div class="relative bg-white border border-[#dfdfdf] rounded-[12px] min-h-[310px] px-3 py-4">

            <!-- Search / Filter -->
            <form method="GET" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-y-3">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] text-[#3a3a3a] font-medium">
                        <div class="flex items-center gap-2">
                            <label class="whitespace-nowrap">Search by:</label>
                            <select name="search_by"
                                class="w-[210px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] text-[#555] outline-none">
                                <option value="">Select</option>
                                <option value="customer_id"              <?php if ($searchBy==='customer_id')              echo 'selected'; ?>>Customer ID</option>
                                <option value="account_beneficiary_name" <?php if ($searchBy==='account_beneficiary_name') echo 'selected'; ?>>Beneficiary Name</option>
                                <option value="account_no"               <?php if ($searchBy==='account_no')               echo 'selected'; ?>>Account No</option>
                                <option value="ifsc_code"                <?php if ($searchBy==='ifsc_code')                echo 'selected'; ?>>IFSC Code</option>
                                <option value="bank_name"                <?php if ($searchBy==='bank_name')                echo 'selected'; ?>>Bank Name</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-[16px] text-[#3a3a3a] font-medium">
                        <label>Filter:</label>
                        <select name="filter"
                            class="w-[130px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[14px] text-[#777] outline-none">
                            <option <?php if ($filter==='All')         echo 'selected'; ?>>All</option>
                            <option <?php if ($filter==='With KYC')    echo 'selected'; ?>>With KYC</option>
                            <option <?php if ($filter==='Missing KYC') echo 'selected'; ?>>Missing KYC</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-[16px] text-[#3a3a3a] font-medium">
                    <div class="flex items-center gap-2">
                        <label class="whitespace-nowrap">Search Value:</label>
                        <input type="text" name="search_value"
                            value="<?php echo htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-[200px] h-[24px] rounded-[4px] border border-[#d8dce2] bg-[#f2f4f7] px-2 text-[15px] outline-none">
                    </div>
                    <button type="submit"
                        class="h-[24px] min-w-[62px] rounded-[5px] bg-[#143a66] text-white text-[15px] px-3 flex items-center justify-center gap-1">
                        <i data-lucide="search" class="w-3 h-3"></i> Search
                    </button>
                    <?php if ($searchBy !== '' || $searchValue !== '' || $filter !== 'All'): ?>
                    <a href="?" class="h-[24px] rounded-[5px] bg-[#6b7280] text-white text-[14px] px-3 flex items-center justify-center gap-1">
                        <i data-lucide="x" class="w-3 h-3"></i> Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Stats pills -->
            <?php
            $total    = count($rows);
            $approved = count(array_filter($rows, fn($r) => ($r['kyc_status'] ?? 'Pending') === 'Approved'));
            $pending  = count(array_filter($rows, fn($r) => ($r['kyc_status'] ?? 'Pending') === 'Pending'));
            $rejected = count(array_filter($rows, fn($r) => ($r['kyc_status'] ?? 'Pending') === 'Rejected'));
            ?>
            <div class="flex gap-3 mt-3 mb-2 flex-wrap">
                <span class="text-[13px] px-3 py-0.5 rounded-full bg-[#f3f4f6] border border-[#e5e7eb] text-[#555]">Total: <b><?php echo $total; ?></b></span>
                <span class="text-[13px] px-3 py-0.5 rounded-full bg-[#dcfce7] border border-[#86efac] text-[#166534]">Approved: <b><?php echo $approved; ?></b></span>
                <span class="text-[13px] px-3 py-0.5 rounded-full bg-[#fef9c3] border border-[#fde68a] text-[#854d0e]">Pending: <b><?php echo $pending; ?></b></span>
                <span class="text-[13px] px-3 py-0.5 rounded-full bg-[#fee2e2] border border-[#fca5a5] text-[#991b1b]">Rejected: <b><?php echo $rejected; ?></b></span>
            </div>

            <!-- Table -->
            <div class="mt-2 rounded-[6px] overflow-hidden border border-[#d6a246]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1500px] border-collapse">
                        <thead>
                        <tr class="bg-[#cd9737] text-white font-medium text-[15px] h-[30px]">
                            <th class="px-2 font-medium whitespace-nowrap text-left">#</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Customer ID</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Name</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Mobile</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Beneficiary Name</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Bank Name</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">IFSC Code</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Account No</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Aadhaar No</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Aadhaar Photo</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">PAN No</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">PAN Photo</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">GST</th>
                            <!--<th class="px-2 font-medium whitespace-nowrap text-left">Bank Status</th>-->
                            <th class="px-2 font-medium whitespace-nowrap text-left">KYC Status</th>
                            <th class="px-2 font-medium whitespace-nowrap text-left">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $i => $row):
                                $cid        = (string)($row['customer_id'] ?? '');
                                $kycStatus  = (string)($row['kyc_status'] ?? 'Pending');
                                $memberInfo = $nameMap[$cid] ?? [];

                                /* Decode photo fields */
                                $aadharPath = extractFirstPhoto((string)($row['aadhar_photo'] ?? ''));
                                $panPath    = extractFirstPhoto((string)($row['pan_photo']    ?? ''));

                             
                                $aadharSrc = $aadharPath !== '' ? htmlspecialchars('../member/' . ltrim($aadharPath, '/'), ENT_QUOTES, 'UTF-8') : '';
                                $panSrc    = $panPath    !== '' ? htmlspecialchars('../member/' . ltrim($panPath,    '/'), ENT_QUOTES, 'UTF-8') : '';

                                $badgeClass = match($kycStatus) {
                                    'Approved' => 'badge-approved',
                                    'Rejected' => 'badge-rejected',
                                    default    => 'badge-pending',
                                };
                            ?>
                            <tr id="row-<?php echo htmlspecialchars($cid, ENT_QUOTES, 'UTF-8'); ?>"
                                class="<?php echo $i % 2 === 0 ? 'bg-white' : 'bg-[#fafafa]'; ?> h-[44px] text-[14px] text-[#2d2d2d] border-t border-[#ececec] hover:bg-[#fffbf0] transition-colors">

                                <td class="px-2 whitespace-nowrap text-[#aaa] text-[13px]"><?php echo $i + 1; ?></td>

                                <td class="px-2 whitespace-nowrap font-medium text-[#143a66]">
                                    <?php echo htmlspecialchars($cid, ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td class="px-2 whitespace-nowrap">
                                    <?php echo htmlspecialchars($memberInfo['full_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td class="px-2 whitespace-nowrap">
                                    <?php echo htmlspecialchars($memberInfo['mobile'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td class="px-2 whitespace-nowrap">
                                    <?php echo htmlspecialchars($row['account_beneficiary_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td class="px-2 whitespace-nowrap">
                                    <?php echo htmlspecialchars($row['bank_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td class="px-2 whitespace-nowrap font-mono text-[13px]">
                                    <?php echo htmlspecialchars($row['ifsc_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td class="px-2 whitespace-nowrap font-mono text-[13px]">
                                    <?php echo htmlspecialchars($row['account_no'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td class="px-2 whitespace-nowrap font-mono text-[13px]">
                                    <?php echo htmlspecialchars($row['aadhar_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <!-- Aadhaar Photo -->
                                <td class="px-2 whitespace-nowrap">
                                    <?php if ($aadharSrc !== ''): ?>
                                        <div class="flex flex-col items-center gap-0.5">
                                            <img src="<?php echo $aadharSrc; ?>"
                                                 alt="Aadhaar"
                                                 class="doc-thumb"
                                                 onclick="openLightbox('<?php echo $aadharSrc; ?>')"
                                                 onerror="this.parentElement.innerHTML='<span class=\'text-red-400 text-[11px]\'>Not found</span>'">
                                            <a href="<?php echo $aadharSrc; ?>" target="_blank"
                                               class="text-[10px] text-[#143a66] underline">View</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-[#ccc] text-[13px]">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-2 whitespace-nowrap font-mono text-[13px]">
                                    <?php echo htmlspecialchars($row['pan_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <!-- PAN Photo -->
                                <td class="px-2 whitespace-nowrap">
                                    <?php if ($panSrc !== ''): ?>
                                        <div class="flex flex-col items-center gap-0.5">
                                            <img src="<?php echo $panSrc; ?>"
                                                 alt="PAN"
                                                 class="doc-thumb"
                                                 onclick="openLightbox('<?php echo $panSrc; ?>')"
                                                 onerror="this.parentElement.innerHTML='<span class=\'text-red-400 text-[11px]\'>Not found</span>'">
                                            <a href="<?php echo $panSrc; ?>" target="_blank"
                                               class="text-[10px] text-[#143a66] underline">View</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-[#ccc] text-[13px]">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-2 whitespace-nowrap text-[13px]">
                                    <?php echo htmlspecialchars($row['gst'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <!-- Bank Locked -->
                                <!--<td class="px-2 whitespace-nowrap">-->
                                <!--    <?php if (!empty($row['is_bank_locked'])): ?>-->
                                <!--        <span class="text-[11px] px-2 py-0.5 rounded-full bg-red-100 text-red-700 border border-red-200">Locked</span>-->
                                <!--    <?php else: ?>-->
                                <!--        <span class="text-[11px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 border border-green-200">Active</span>-->
                                <!--    <?php endif; ?>-->
                                <!--</td>-->

                                <!-- KYC Status badge -->
                                <td class="px-2 whitespace-nowrap">
                                    <span id="badge-<?php echo htmlspecialchars($cid, ENT_QUOTES, 'UTF-8'); ?>"
                                          class="<?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($kycStatus, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>

                                <!-- Action buttons -->
                                <td class="px-2 whitespace-nowrap">
                                    <div class="flex items-center gap-1"
                                         id="actions-<?php echo htmlspecialchars($cid, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php if ($kycStatus !== 'Approved'): ?>
                                        <button class="btn-approve"
                                            onclick="updateKyc('<?php echo htmlspecialchars($cid, ENT_QUOTES, 'UTF-8'); ?>', 'Approved')">
                                            <i data-lucide="check" style="width:12px;height:12px;"></i> Approve
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($kycStatus !== 'Rejected'): ?>
                                        <button class="btn-reject"
                                            onclick="updateKyc('<?php echo htmlspecialchars($cid, ENT_QUOTES, 'UTF-8'); ?>', 'Rejected')">
                                            <i data-lucide="x" style="width:12px;height:12px;"></i> Reject
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($kycStatus === 'Approved' || $kycStatus === 'Rejected'): ?>
                                        <button class="btn-approve" style="background:#6b7280;"
                                            onclick="updateKyc('<?php echo htmlspecialchars($cid, ENT_QUOTES, 'UTF-8'); ?>', 'Pending')">
                                            <i data-lucide="rotate-ccw" style="width:12px;height:12px;"></i> Reset
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="16" class="h-[260px] bg-white text-center text-[#999]">
                                    <div class="flex flex-col items-center justify-center gap-2 h-full py-10">
                                        <i data-lucide="inbox" class="w-10 h-10 text-[#d1d5db]"></i>
                                        <span class="text-[15px]">No records found</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
lucide.createIcons();

/* ── Lightbox ── */
function openLightbox(src) {
    document.getElementById('lightbox-img').src  = src;
    document.getElementById('lightbox-link').href = src;
    document.getElementById('lightbox').style.display = 'flex';
}
function closeLightbox(e) {
    if (e.target === document.getElementById('lightbox')) {
        document.getElementById('lightbox').style.display = 'none';
    }
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('lightbox').style.display = 'none';
});

/* ── Toast ── */
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.style.background = type === 'success' ? '#166534'
                       : type === 'warning'  ? '#92400e'
                       : '#991b1b';
    t.innerHTML = (type === 'success'
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
    ) + msg;
    t.style.display = 'flex';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.display = 'none'; }, 3000);
}

/* ── Re-render badge + buttons without page reload ── */
function renderActions(cid, status) {
    const badge = document.getElementById('badge-' + cid);
    badge.className  = status === 'Approved' ? 'badge-approved'
                     : status === 'Rejected' ? 'badge-rejected'
                     : 'badge-pending';
    badge.textContent = status;

    const actions = document.getElementById('actions-' + cid);
    let html = '';
    if (status !== 'Approved') {
        html += `<button class="btn-approve" onclick="updateKyc('${cid}','Approved')">
                    <i data-lucide="check" style="width:12px;height:12px;"></i> Approve</button>`;
    }
    if (status !== 'Rejected') {
        html += `<button class="btn-reject" onclick="updateKyc('${cid}','Rejected')">
                    <i data-lucide="x" style="width:12px;height:12px;"></i> Reject</button>`;
    }
    if (status === 'Approved' || status === 'Rejected') {
        html += `<button class="btn-approve" style="background:#6b7280;" onclick="updateKyc('${cid}','Pending')">
                    <i data-lucide="rotate-ccw" style="width:12px;height:12px;"></i> Reset</button>`;
    }
    actions.innerHTML = html;
    lucide.createIcons();
}

/* ── AJAX KYC update ── */
async function updateKyc(cid, action) {
    try {
        const fd = new FormData();
        fd.append('cid',    cid);
        fd.append('action', action);
        const res  = await fetch(window.location.pathname, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            renderActions(cid, data.status);
            const label = action === 'Approved' ? 'Approved ✓'
                        : action === 'Rejected' ? 'Rejected ✗'
                        : 'Reset to Pending';
            showToast(`KYC ${label} for ${cid}`,
                action === 'Approved' ? 'success' : action === 'Pending' ? 'warning' : 'error');
        } else {
            showToast('Something went wrong.', 'error');
        }
    } catch (e) {
        showToast('Network error. Try again.', 'error');
    }
}
</script>
</body>
</html>