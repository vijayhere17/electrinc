<?php
require_once __DIR__ . '/config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn->set_charset('utf8mb4');

// ── SESSION-BASED DUPLICATE SUBMIT GUARD ──────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

$success         = '';
$error           = '';

$full_name       = $email = $mobile = $state = $city = '';
$placement_id    = $referral_id = $position = '';
$new_customer_id = '';
$sponsor_name    = '';
$plain_password  = '';   // ONLY used for showing on success screen — NEVER stored in DB
$hashed_password = '';   // ONLY this goes into the DB

$fromReferralLink = false;
$referralReadonly = '';
$positionDisabled = '';

if (isset($_GET['ref'])) {
    $referral_id      = $_GET['ref'];
    $placement_id     = '';
    $fromReferralLink = true;
    $referralReadonly = 'readonly';
    $position         = '';

    // Fetch sponsor name
    $stmt = $conn->prepare("SELECT full_name FROM signup_users WHERE customer_id = ? LIMIT 1");
    $stmt->bind_param('s', $referral_id);
    $stmt->execute();
    $sponsorData  = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($sponsorData) {
        $sponsor_name = $sponsorData['full_name'];
    }
}

$indianStates = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
    'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
    'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
    'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
];

function generateCustomerId(mysqli $conn): string
{
    do {
        $randomNumber = str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        $customerId   = 'NA' . $randomNumber;
        $stmt = $conn->prepare("SELECT id FROM signup_users WHERE customer_id = ? LIMIT 1");
        $stmt->bind_param('s', $customerId);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $customerId;
}

// ════════════════════════════════════════════════════════════════════════════
//  POST HANDLER
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── DOUBLE-SUBMIT GUARD (token-based) ───────────────────────────────────
    $submittedToken = trim($_POST['_form_token'] ?? '');
    $sessionToken   = $_SESSION['_signup_token'] ?? '';

    // If token already used, replay last success
    if ($submittedToken !== '' && $submittedToken === $sessionToken && isset($_SESSION['_signup_done'])) {
        $success         = 'success';
        $new_customer_id = $_SESSION['_signup_done']['id']   ?? '';
        $full_name       = $_SESSION['_signup_done']['name'] ?? '';
        $plain_password  = $_SESSION['_signup_done']['pass'] ?? '';
    } else {

        $full_name    = trim($_POST['full_name']    ?? '');
        $email        = strtolower(trim($_POST['email']        ?? ''));
        $mobile       = trim($_POST['mobile']       ?? '');
        $plain_password = trim($_POST['password']   ?? '');   // raw input — display only
        $state        = trim($_POST['state']        ?? '');
        $city         = trim($_POST['city']         ?? '');
        $referral_id  = trim($_POST['referral_id']  ?? '');
        $placement_id = trim($_POST['placement_id'] ?? '');
        $position     = strtoupper(trim($_POST['position'] ?? ''));
        $terms        = isset($_POST['terms'])       ? 1 : 0;
        $age_confirm  = isset($_POST['age_confirm']) ? 1 : 0;

        // ── Hash password immediately — plain text is never used for DB ────
        // $hashed_password is the ONLY variable that goes into the INSERT.
        if ($plain_password !== '') {
           $hashed_password = $plain_password;
        }

        if (!in_array($position, ['L', 'R'], true)) $position = '';

        // ── CHECK REFERRAL EXISTS ──────────────────────────────────────────
        $stmt = $conn->prepare("SELECT id FROM signup_users WHERE customer_id = ? LIMIT 1");
        $stmt->bind_param('s', $referral_id);
        $stmt->execute();
        $referralExists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        // ── CHECK EMAIL UNIQUE ─────────────────────────────────────────────
        $stmt = $conn->prepare("SELECT id FROM signup_users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $emailExists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        // ── CHECK MOBILE UNIQUE ────────────────────────────────────────────
        $stmt = $conn->prepare("SELECT id FROM signup_users WHERE mobile = ? LIMIT 1");
        $stmt->bind_param('s', $mobile);
        $stmt->execute();
        $mobileExists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        // ── CHECK MANUAL PLACEMENT ID EXISTS ──────────────────────────────
        $placementExists = true;
        if ($placement_id !== '') {
            $stmt = $conn->prepare("SELECT id FROM signup_users WHERE customer_id = ? LIMIT 1");
            $stmt->bind_param('s', $placement_id);
            $stmt->execute();
            $placementExists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        // ── AUTO PLACEMENT (only when placement_id is empty) ──────────────
        if ($placement_id === '' && $referral_id !== '' && $position !== '') {
            $placement_id = $referral_id;
            while (true) {
                $stmt = $conn->prepare("SELECT id FROM signup_users WHERE placement_id = ? AND position = ? LIMIT 1");
                $stmt->bind_param('ss', $placement_id, $position);
                $stmt->execute();
                $occupied = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if (!$occupied) break;

                $stmt = $conn->prepare("SELECT customer_id FROM signup_users WHERE placement_id = ? AND position = ? LIMIT 1");
                $stmt->bind_param('ss', $placement_id, $position);
                $stmt->execute();
                $next = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$next) break;
                $placement_id = $next['customer_id'];
            }
        }

        // ── CHECK POSITION ALREADY USED ───────────────────────────────────
        $positionUsed = false;
        if ($placement_id !== '' && $position !== '') {
            $stmt = $conn->prepare("SELECT id FROM signup_users WHERE placement_id = ? AND position = ? LIMIT 1");
            $stmt->bind_param('ss', $placement_id, $position);
            $stmt->execute();
            $positionUsed = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        // ── VALIDATION ────────────────────────────────────────────────────
        if (
            $full_name === '' || $email === '' || $mobile === '' || $plain_password === '' ||
            $state === '' || $city === '' || $referral_id === '' || $position === ''
        ) {
            $error = 'Please fill all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
            $error = 'Mobile number must be exactly 10 digits.';
        } elseif (strlen($plain_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($emailExists) {
            $error = 'This email address is already registered. Please use a different email.';
        } elseif ($mobileExists) {
            $error = 'This mobile number is already registered. Please use a different number.';
        } elseif (!$referralExists) {
            $error = 'Referral ID not found. Please enter a valid Referral ID.';
        } elseif (!$placementExists) {
            $error = 'Placement ID not found. Please enter a valid Placement ID.';
        } elseif ($positionUsed) {
            $error = 'This position is already filled. Please choose another position or placement.';
        } else {

            $customer_id = generateCustomerId($conn);

            // ── CRITICAL: Only $hashed_password goes into the database ─────
            // $plain_password is NEVER passed to the INSERT statement.
           $plain_password = trim($_POST['password'] ?? '');

$stmt = $conn->prepare("
    INSERT INTO signup_users
        (customer_id, referral_id, placement_id, position,
         full_name, email, mobile, password, state, city,
         terms_accepted, age_confirmed, registration_status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Inactive')
");

$stmt->bind_param(
    'ssssssssssii',
    $customer_id,
    $referral_id,
    $placement_id,
    $position,
    $full_name,
    $email,
    $mobile,
    $plain_password,
    $state,
    $city,
    $terms,
    $age_confirm
);

            if ($stmt->execute()) {

                // UPDATE ALL UPLINE COUNTS
                $currentParent = $placement_id;
                $currentSide   = $position;

                while ($currentParent !== '') {
                    if ($currentSide === 'L') {
                        $update = $conn->prepare("UPDATE signup_users SET total_left_members = total_left_members + 1 WHERE customer_id = ?");
                    } else {
                        $update = $conn->prepare("UPDATE signup_users SET total_right_members = total_right_members + 1 WHERE customer_id = ?");
                    }
                    if ($update) {
                        $update->bind_param('s', $currentParent);
                        $update->execute();
                        $update->close();
                    }

                    $parentStmt = $conn->prepare("SELECT placement_id, position FROM signup_users WHERE customer_id = ? LIMIT 1");
                    if (!$parentStmt) break;
                    $parentStmt->bind_param('s', $currentParent);
                    $parentStmt->execute();
                    $parentData = $parentStmt->get_result()->fetch_assoc();
                    $parentStmt->close();
                    if (!$parentData || empty($parentData['placement_id'])) break;

                    $currentSide   = $parentData['position'];
                    $currentParent = $parentData['placement_id'];
                }

                // ── STORE SUCCESS IN SESSION (duplicate-submit protection) ──
                // plain_password stored in session ONLY for the one-time display
                // on the success screen. It is never written to the DB.
                $_SESSION['_signup_token'] = $submittedToken;
                $_SESSION['_signup_done']  = [
                    'id'   => $customer_id,
                    'name' => $full_name,
                    'pass' => $plain_password,   // display only — not in DB
                ];

                $success         = 'success';
                $new_customer_id = $customer_id;

            } else {
                if ($conn->errno === 1062) {
                    $error = 'Registration failed: A record with this email or mobile already exists.';
                } else {
                    $error = 'Registration failed: ' . $stmt->error;
                }
            }
            $stmt->close();
        }
    } // end duplicate-submit guard
} // end POST

// Generate a fresh token for this page load (used to detect double-submit)
$formToken = bin2hex(random_bytes(16));
$_SESSION['_signup_token'] = null; // reset so next fresh submit works
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Netvexa – Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * { box-sizing: border-box; }

        /* ── Success overlay ── */
        #success-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(10, 18, 30, 0.72);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        #success-overlay.show { display: flex; animation: fadeIn .35s ease; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes cardUp {
            from { opacity: 0; transform: translateY(36px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes popIn {
            0%   { transform: scale(0);    opacity: 0; }
            70%  { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(1); }
        }

        .success-card  { animation: cardUp .45s cubic-bezier(.22,1,.36,1) forwards; }
        .check-circle  { animation: popIn  .5s  cubic-bezier(.22,1,.36,1) .25s both; }

        /* ── Credential boxes ── */
        .cred-box {
            background: #f0f4f8;
            border: 1.5px dashed #b0bec8;
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .cred-value {
            font-family: 'Courier New', monospace;
            font-size: 17px;
            font-weight: 700;
            color: #103258;
            letter-spacing: .04em;
            word-break: break-all;
        }
        .copy-btn {
            flex-shrink: 0;
            font-size: 12px;
            font-weight: 600;
            color: #103258;
            background: #dce8f5;
            border: none;
            border-radius: 6px;
            padding: 4px 10px;
            cursor: pointer;
            transition: background .2s;
        }
        .copy-btn:hover { background: #b9d4ef; }

        /* ── Form field base ── */
        .field-input {
            width: 100%;
            background: transparent;
            border: 0;
            border-bottom: 1.5px solid #ddd4ce;
            padding-bottom: 10px;
            font-size: 15px;
            color: #354052;
            outline: none;
            transition: border-color .2s;
            -webkit-appearance: none;
            appearance: none;
        }
        .field-input::placeholder { color: #a8b4be; }
        .field-input:focus { border-color: #b9472f; }
        select.field-input { cursor: pointer; }

        /* ── Section card ── */
        .section-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,.07);
            border: 1px solid #f0ece9;
        }
        @media (min-width: 640px) { .section-card { padding: 28px 28px; } }

        /* ── Position radio pills ── */
        .pos-radio { position: absolute; opacity: 0; width: 0; height: 0; }
        .pos-label {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 0;
            border-radius: 12px;
            border: 1.5px solid #ddd4ce;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #7b8a9a;
            transition: all .2s;
            background: #fafafa;
            user-select: none;
        }
        .pos-label:hover { border-color: #103258; color: #103258; background: #f0f5fb; }
        .pos-radio:checked + .pos-label {
            background: #103258;
            border-color: #103258;
            color: #fff;
            box-shadow: 0 4px 14px rgba(16,50,88,.22);
        }

        /* ── Submit button loading state ── */
        #submit-btn:disabled {
            opacity: .65;
            cursor: not-allowed;
        }
    </style>
</head>

<body class="min-h-screen bg-[#f4ede8] text-[#2b2b2b]">

<!-- ════════════════════════════════════════
     SUCCESS OVERLAY
════════════════════════════════════════ -->
<?php if ($success === 'success'): ?>
<div id="success-overlay" class="show">
    <div class="success-card w-full max-w-[400px] rounded-[18px] bg-white shadow-[0_20px_60px_rgba(0,0,0,.18)]">

        <!-- Top section -->
        <div class="px-8 pt-8 pb-6 flex flex-col items-center text-center border-b border-[#f0f0f0]">
            <div class="bg-[#103258] rounded-xl px-4 py-3 inline-flex items-center mb-5">
                <img src="assets/electrinc-logo.png" alt="Electrinc" class="w-[130px] object-contain">
            </div>
            <div class="check-circle w-[52px] h-[52px] rounded-full bg-[#f0faf4] flex items-center justify-center mb-4" style="border:2px solid #34c471;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M5 12.5L10 17.5L19 8" stroke="#34c471" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2 class="text-[#0f2a4a] text-[20px] font-bold mb-1">Registration Successful</h2>
            <p class="text-[14px] text-[#64748b]">
                Welcome, <strong class="text-[#0f2a4a] font-semibold"><?php echo htmlspecialchars($full_name); ?></strong>.
                Your account has been created.
            </p>
        </div>

        <!-- Credentials -->
        <div class="px-8 py-6 space-y-4">
            <div>
                <p class="text-[12px] font-semibold text-[#94a3b8] mb-1.5">Member ID</p>
                <div class="cred-box">
                    <span class="cred-value" id="member-id"><?php echo htmlspecialchars($new_customer_id); ?></span>
                    <button type="button" class="copy-btn" onclick="copyText('member-id', this)">Copy</button>
                </div>
            </div>
            <div>
                <p class="text-[12px] font-semibold text-[#94a3b8] mb-1.5">Password</p>
                <div class="cred-box">
                    <span class="cred-value" id="member-pass"><?php echo htmlspecialchars($plain_password); ?></span>
                    <button type="button" class="copy-btn" onclick="copyText('member-pass', this)">Copy</button>
                </div>
            </div>
            <p class="text-[12px] text-[#94a3b8] text-center pt-1">
                Save these credentials — you'll need them to sign in.
            </p>
        </div>

        <!-- CTA -->
        <div class="px-8 pb-8">
            <a href="login.php"
               class="block w-full h-[48px] rounded-[10px] bg-[#103258] hover:bg-[#0c2540] text-white text-[15px] font-semibold text-center leading-[48px] transition-colors duration-200">
                Sign In to Your Account
            </a>
        </div>

    </div>
</div>
<?php endif; ?>


<div class="w-full flex justify-center px-4 py-8 md:py-12">
<div class="w-full max-w-[720px]">

    <!-- Logo -->
    <div class="flex justify-center mb-5">
        <div class="bg-[#103258] rounded-xl px-5 py-3.5 inline-flex items-center drop-shadow">
            <img src="assets/electrinc-logo.png" alt="Electrinc" class="w-[150px] md:w-[170px] object-contain">
        </div>
    </div>

    <!-- Header banner -->
    <div class="rounded-[18px] bg-[#103258] px-6 py-5 text-center mb-5 shadow-[0_10px_30px_rgba(16,50,88,.25)]">
        <h1 class="text-white text-[22px] md:text-[26px] font-bold leading-none tracking-tight">Create Your Account</h1>
        <p class="mt-1.5 text-white/80 text-[14px] md:text-[15px]">Join Netvexa – Trading Company</p>
    </div>

    <!-- Referral link notice -->
    <?php if ($fromReferralLink && ($referral_id !== '' || $position !== '')): ?>
    <div class="mb-4 rounded-[14px] border border-[#d7dceb] bg-[#eef4ff] px-5 py-3.5 text-center text-[#35507a] text-[14px]">
        Referral link detected —
        <?php if ($referral_id !== ''): ?>
            Referral ID <strong><?php echo htmlspecialchars($referral_id); ?></strong>
        <?php endif; ?>
        <?php if ($sponsor_name !== ''): ?>
            &nbsp;|&nbsp; Sponsor Name <strong><?php echo htmlspecialchars($sponsor_name); ?></strong>
        <?php endif; ?>
        <?php if ($position !== ''): ?>
            &nbsp;|&nbsp; Position <strong><?php echo htmlspecialchars($position); ?></strong>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Error message -->
    <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-[14px] border border-red-200 bg-red-50 px-5 py-3.5 text-center text-red-700 text-[14px] font-medium">
        ⚠ &nbsp;<?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form id="signup-form" action="" method="POST" class="space-y-5" onsubmit="return handleSubmit(this)">

        <!-- Hidden token to prevent double-submit -->
        <input type="hidden" name="_form_token" value="<?php echo htmlspecialchars($formToken); ?>">

        <!-- ══ SECTION 1: SPONSOR INFO ══ -->
        <div class="section-card">
            <div class="flex items-center gap-3 mb-6">
                <span class="block w-[4px] h-[22px] rounded-full bg-[#103258]"></span>
                <h2 class="text-[#103258] text-[18px] md:text-[20px] font-bold">Sponsor Info</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-6 mb-6">
                <div>
                    <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-2">
                        Referral ID <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="referral_id"
                           value="<?php echo htmlspecialchars($referral_id); ?>"
                           <?php echo $referralReadonly; ?>
                           placeholder="e.g. NA1234567"
                           class="field-input">
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-2">
                        Placement ID
                        <span class="normal-case font-normal text-[#a8b4be] tracking-normal ml-1">(Optional)</span>
                    </label>
                    <input type="text" name="placement_id"
                           value="<?php echo htmlspecialchars($placement_id); ?>"
                           placeholder="Leave blank to auto-assign"
                           class="field-input">
                </div>
            </div>

            <!-- Position pills -->
            <div>
                <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-3">
                    Position <span class="text-red-400">*</span>
                </label>
                <div class="flex gap-3">
                    <div class="relative flex-1">
                        <input type="radio" name="position" value="L" id="pos_l" class="pos-radio"
                               <?php echo $position === 'L' ? 'checked' : ''; ?>>
                        <label for="pos_l" class="pos-label">
                            <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                                <path d="M10 3L5 8L10 13" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Left
                        </label>
                    </div>
                    <div class="relative flex-1">
                        <input type="radio" name="position" value="R" id="pos_r" class="pos-radio"
                               <?php echo $position === 'R' ? 'checked' : ''; ?>>
                        <label for="pos_r" class="pos-label">
                            Right
                            <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                                <path d="M6 3L11 8L6 13" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ SECTION 2: PERSONAL DETAILS ══ -->
        <div class="section-card">
            <div class="flex items-center gap-3 mb-6">
                <span class="block w-[4px] h-[22px] rounded-full bg-[#103258]"></span>
                <h2 class="text-[#103258] text-[18px] md:text-[20px] font-bold">Personal Details</h2>
            </div>

            <div class="space-y-6">

                <div>
                    <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-2">
                        Full Name <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="full_name"
                           value="<?php echo htmlspecialchars($full_name); ?>"
                           placeholder="Your full name"
                           class="field-input">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-6">
                    <div>
                        <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-2">
                            Email Address <span class="text-red-400">*</span>
                        </label>
                        <input type="email" name="email"
                               value="<?php echo htmlspecialchars($email); ?>"
                               placeholder="you@example.com"
                               class="field-input">
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-2">
                            Mobile No. <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="mobile" maxlength="10"
                               value="<?php echo htmlspecialchars($mobile); ?>"
                               placeholder="10-digit number"
                               inputmode="numeric"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                               class="field-input">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-2">
                        Password <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="password-field"
                               placeholder="Min. 6 characters"
                               class="field-input pr-8">
                        <button type="button" onclick="togglePassword()"
                                class="absolute right-0 bottom-2.5 text-[#a8b4be] hover:text-[#103258] transition-colors">
                            <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- State + City -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-6">
                    <div>
                        <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-2">
                            State <span class="text-red-400">*</span>
                        </label>
                        <select name="state" class="field-input">
                            <option value="">Select State</option>
                            <?php foreach ($indianStates as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>"
                                    <?php echo $state === $s ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-[#7b8a9a] uppercase tracking-wider mb-2">
                            City <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="city"
                               value="<?php echo htmlspecialchars($city); ?>"
                               placeholder="Enter your city"
                               class="field-input">
                    </div>
                </div>
            </div>

            <div class="my-6 border-t border-[#f0ece9]"></div>

            <!-- Terms checkbox -->
            <div class="space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="terms" id="chk_terms"
                           class="mt-0.5 w-[18px] h-[18px] rounded border-2 border-[#c5b9b3] accent-[#103258] flex-shrink-0 cursor-pointer">
                    <span class="text-[14px] text-[#6b5a54] leading-[1.65]">
                        I have read &amp; agree to the
                        <a href="#" class="text-[#103258] font-semibold underline underline-offset-2">Terms and Conditions</a>
                    </span>
                </label>
            </div>
        </div>

        <!-- Submit button -->
        <button type="submit" id="submit-btn"
                class="w-full h-[56px] rounded-[16px] bg-[#103258] hover:bg-[#b9472f] active:scale-[.98] text-white text-[18px] font-bold tracking-wide shadow-[0_10px_28px_rgba(16,50,88,.28)] transition-all duration-300">
            Create Account
        </button>

        <p class="text-center pb-3 text-[14px] text-[#7b6b65]">
            Already have an account?
            <a href="login.php" class="text-[#103258] font-bold underline underline-offset-4 ml-1">Sign In</a>
        </p>

    </form>
</div>
</div>

<script>
// ── Prevent double-submit ─────────────────────────────────────────────────
let _formSubmitted = false;

function handleSubmit(form) {
    if (_formSubmitted) return false;
    _formSubmitted = true;

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Creating Account…';

    return true;
}

// ── Copy to clipboard ─────────────────────────────────────────────────────
function copyText(id, btn) {
    navigator.clipboard.writeText(document.getElementById(id).textContent.trim()).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Copied';
        btn.style.cssText = 'background:#d4edda;color:#155724;';
        setTimeout(() => { btn.textContent = orig; btn.style.cssText = ''; }, 2000);
    });
}

// ── Password show / hide ──────────────────────────────────────────────────
function togglePassword() {
    const f = document.getElementById('password-field');
    const showing = f.type === 'text';
    f.type = showing ? 'password' : 'text';
    document.getElementById('eye-icon').innerHTML = showing
        ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
        : '<line x1="1" y1="1" x2="23" y2="23"/><path d="M10.5 6.1A9 9 0 0 1 12 6c7 0 11 6 11 6a18 18 0 0 1-5.2 4.9M6.5 6.5A18 18 0 0 0 1 12s4 6 11 6a9 9 0 0 0 4.9-1.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>';
}
</script>
</body>
</html>