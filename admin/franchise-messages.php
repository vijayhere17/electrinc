<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

$dbPaths = [
    __DIR__ . '/config/db.php',
    __DIR__ . '/../config/db.php',
    dirname(__DIR__) . '/config/db.php'
];

$dbLoaded = false;

foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded || !isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection file not found.");
}

$success = '';
$error   = '';

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$memberName   = $_SESSION['full_name'] ?? 'Administrator';
$memberId     = $_SESSION['member_id'] ?? 'ADMIN001';
$profileImage = $_SESSION['profile_img'] ?? 'https://i.pravatar.cc/80?img=12';

if (isset($_POST['send_reply'])) {
    $message_id = (int)$_POST['message_id'];
    $reply      = trim($_POST['reply']);

    if ($message_id > 0 && $reply !== '') {
        $stmt = $conn->prepare("
            UPDATE franchise_messages
            SET
                admin_reply = ?,
                status      = 'replied',
                reply_date  = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param("si", $reply, $message_id);

        if ($stmt->execute()) {
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'     => true,
                    'message'     => 'Reply Sent Successfully',
                    'admin_reply' => $reply,
                    'status'      => 'replied'
                ]);
                $stmt->close();
                exit;
            }

            $_SESSION['success'] = "Reply Sent Successfully";
        } else {
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Database Error'
                ]);
                $stmt->close();
                exit;
            }

            $_SESSION['error'] = "Database Error";
        }

        $stmt->close();
    }

    header("Location: franchise-messages.php");
    exit;
}

$result = $conn->query("
    SELECT *
    FROM franchise_messages
    ORDER BY id DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Messages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <style>
        #toast {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,.18);
            max-width: calc(100vw - 24px);
            text-align: center;
        }

        .alert {
            margin-top: 16px;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            word-break: break-word;
        }

        .alert-success {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #15803d;
        }

        .alert-error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
        }

        .alert-fade {
            animation: alertFade 0.4s ease 4s forwards;
        }

        @keyframes alertFade {
            to {
                opacity: 0;
                max-height: 0;
                margin-top: 0;
                padding-top: 0;
                padding-bottom: 0;
                overflow: hidden;
            }
        }

        .alert .close-btn {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            opacity: .6;
            color: inherit;
            flex-shrink: 0;
        }

        .alert .close-btn:hover {
            opacity: 1;
        }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
        }

        .badge-state  { background: #2563eb; }
        .badge-city   { background: #f97316; }
        .badge-mobile { background: #16a34a; }

        .status-replied { color: #16a34a; font-weight: 600; font-size: 13px; }
        .status-pending { color: #ef4444; font-weight: 600; font-size: 13px; }

        .reply-box {
            border-left: 4px solid #16a34a;
            background: #f0fdf4;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            color: #166534;
            min-width: 200px;
            max-width: 100%;
            word-break: break-word;
        }

        .reply-box strong {
            display: block;
            margin-bottom: 6px;
            color: #14532d;
        }

        textarea.reply-input {
            width: 100%;
            min-width: 200px;
            max-width: 100%;
            height: 96px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            outline: none;
            resize: vertical;
            display: block;
            background: #fff;
        }

        textarea.reply-input:focus {
            border-color: #c9983b;
            box-shadow: 0 0 0 2px rgba(201,152,59,0.15);
        }

        .btn-reply {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
            min-height: 40px;
            width: 100%;
        }

        .btn-reply:hover { background: #15803d; }

        .msg-table {
            width: 100%;
            min-width: 1080px;
            border-collapse: collapse;
        }

        .msg-table thead tr {
            background: #cd9737;
            color: #fff;
            font-size: 13px;
            font-weight: 500;
        }

        .msg-table thead th {
            padding: 10px 14px;
            text-align: left;
            white-space: nowrap;
        }

        .msg-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.1s;
        }

        .msg-table tbody tr:hover { background: #fafafa; }

        .msg-table tbody td {
            padding: 12px 14px;
            font-size: 13px;
            color: #2d2d2d;
            vertical-align: top;
        }


        @media (min-width: 640px) {
            #toast {
                bottom: 28px;
                right: 28px;
                left: auto;
                transform: none;
                max-width: 420px;
                text-align: left;
            }

            .btn-reply { width: auto; }
        }
    </style>
</head>
<body class="m-0 bg-[#f3f4f6] font-sans overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>


<div id="toast"></div>

<div class="lg:ml-64 min-h-screen">

    <div class="sticky top-0 z-30 bg-white border-b border-[#e8e8e8] flex items-center justify-between gap-3 px-3 sm:px-5 py-3 min-h-[56px]">

        <button
            type="button"
            onclick="openSidebar()"
            class="lg:hidden w-9 h-9 rounded-lg bg-[#f5eee3] flex items-center justify-center shrink-0">
            <i data-lucide="menu" class="w-5 h-5 text-[#c9983b]"></i>
        </button>

        <div class="flex flex-col min-w-0">
            <span class="text-[14px] sm:text-[15px] font-semibold text-[#1a1a2e] leading-tight">Dashboard</span>
            <span class="text-[11px] text-[#999] tracking-wide">MEMBER ID: <?= htmlspecialchars($memberId) ?></span>
        </div>

        <div class="flex items-center gap-2 sm:gap-3 ml-auto min-w-0">
            <img
                src="<?= htmlspecialchars($profileImage) ?>"
                alt="profile"
                class="w-8 h-8 sm:w-9 sm:h-9 rounded-full object-cover border-2 border-[#e8e8e8] shrink-0"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div style="display:none; width:36px; height:36px; border-radius:50%; background:#c9983b; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff; flex-shrink:0;">
                <?= strtoupper(substr($memberName, 0, 1)) ?>
            </div>
            <div class="min-w-0 hidden sm:block">
                <div class="text-[13px] font-semibold text-[#1a1a2e] leading-tight truncate max-w-[130px]"><?= htmlspecialchars($memberName) ?></div>
                <div class="text-[11px] text-[#999]">Distributor</div>
            </div>
        </div>
    </div>

    <main class="px-3 py-4 sm:px-5 sm:py-5 md:px-6">

        <?php if ($success != ''): ?>
            <div class="alert alert-success alert-fade">
                <i class="fa-solid fa-circle-check mt-[2px]"></i>
                <span><?= htmlspecialchars($success) ?></span>
                <button class="close-btn" type="button" onclick="this.closest('.alert').remove()">×</button>
            </div>
        <?php endif; ?>

        <?php if ($error != ''): ?>
            <div class="alert alert-error alert-fade">
                <i class="fa-solid fa-circle-xmark mt-[2px]"></i>
                <span><?= htmlspecialchars($error) ?></span>
                <button class="close-btn" type="button" onclick="this.closest('.alert').remove()">×</button>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap items-center gap-3 mt-4 mb-4 sm:mb-5">
            <div class="w-9 h-9 bg-[#f6f2ea] rounded-[8px] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-envelope text-[#c9983b] text-[15px]"></i>
            </div>
            <h2 class="text-[16px] sm:text-[18px] font-normal text-[#2f2f2f] whitespace-nowrap"
                style="font-family: Georgia, 'Times New Roman', serif;">FRANCHISE QUERY MESSAGES</h2>
            <div class="hidden sm:block flex-1 h-[1px] bg-[#c9983b]"></div>
        </div>

        <div class="bg-white rounded-[12px] border border-[#dfdfdf] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="msg-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Admin Reply</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-[#888] text-[13px] whitespace-nowrap"><?= (int)$row['id'] ?></td>

                                    <td>
                                        <?php $ft = strtolower($row['franchise_type'] ?? ''); ?>
                                        <?php if ($ft === 'state'): ?>
                                            <span class="badge badge-state">STATE</span>
                                        <?php elseif ($ft === 'city'): ?>
                                            <span class="badge badge-city">CITY</span>
                                        <?php else: ?>
                                            <span class="badge badge-mobile">MOBILE</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="font-medium whitespace-nowrap"><?= htmlspecialchars($row['franchise_code'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['from_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['subject'] ?? '-') ?></td>

                                    <td class="max-w-[220px] break-words">
                                        <?= nl2br(htmlspecialchars($row['message'] ?? '-')) ?>
                                    </td>

                                    <td class="whitespace-nowrap">
                                        <?php if (($row['status'] ?? '') === 'replied'): ?>
                                            <span class="status-replied">Replied</span>
                                        <?php else: ?>
                                            <span class="status-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-[13px] text-[#666] whitespace-nowrap">
                                        <?= !empty($row['message_date']) ? date("d M Y h:i A", strtotime($row['message_date'])) : '-' ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($row['admin_reply'])): ?>
                                            <div class="reply-box">
                                                <strong>Admin Reply:</strong>
                                                <?= nl2br(htmlspecialchars($row['admin_reply'])) ?>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" class="reply-form" style="min-width:220px;">
                                                <input type="hidden" name="message_id" value="<?= (int)$row['id'] ?>">
                                                <textarea
                                                    name="reply"
                                                    required
                                                    placeholder="Write admin reply..."
                                                    class="reply-input"></textarea>
                                                <button type="submit" name="send_reply" class="btn-reply">
                                                    <i class="fa-solid fa-paper-plane"></i>
                                                    Send Reply
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="py-16 text-center text-[#999]">
                                    <i class="fa-solid fa-inbox mb-3 block text-[32px] text-[#ddd]"></i>
                                    No Messages Found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.style.background = type === 'success' ? '#166534' : '#991b1b';
    t.textContent = msg;
    t.style.display = 'flex';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.display = 'none'; }, 3000);
}

document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.reply-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const btn = form.querySelector('button[type="submit"]');
            const fd = new FormData(form);
            fd.append('ajax', '1');
            fd.append('send_reply', '1');

            if (btn) btn.disabled = true;

            fetch(window.location.pathname, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(res => {
                const ct = res.headers.get('content-type') || '';
                if (ct.indexOf('application/json') !== -1) return res.json();
                return res.text().then(t => {
                    try { return JSON.parse(t); }
                    catch (e) { return { success: false, message: 'Invalid server response' }; }
                });
            })
            .then(data => {
                if (btn) btn.disabled = false;

                if (data && data.success) {
                    const replyHtml = document.createElement('div');
                    replyHtml.className = 'reply-box';

                    const strong = document.createElement('strong');
                    strong.textContent = 'Admin Reply:';
                    replyHtml.appendChild(strong);

                    const text = document.createElement('div');
                    text.textContent = data.admin_reply;
                    replyHtml.appendChild(text);

                    form.parentNode.replaceChild(replyHtml, form);

                    const tr = replyHtml.closest('tr');
                    if (tr) {
                        const cells = tr.querySelectorAll('td');
                        if (cells.length >= 7) {
                            cells[6].innerHTML = '<span class="status-replied">Replied</span>';
                        }
                    }

                    showToast(data.message || 'Reply sent', 'success');
                } else {
                    showToast((data && data.message) || 'Failed to send reply', 'error');
                }
            })
            .catch(() => {
                if (btn) btn.disabled = false;
                showToast('Network error', 'error');
            });
        });
    });

    <?php if ($success != ''): ?>
    showToast("<?= addslashes(htmlspecialchars($success)) ?>", 'success');
    <?php endif; ?>

    <?php if ($error != ''): ?>
    showToast("<?= addslashes(htmlspecialchars($error)) ?>", 'error');
    <?php endif; ?>

    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
</body>
</html>