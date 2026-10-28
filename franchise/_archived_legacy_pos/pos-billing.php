<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

/* ── Resolve logged-in franchise (same pattern as your other pages) ── */
function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

function tableExists($conn, $table) {
    $t   = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    return ($res && $res->num_rows > 0);
}
function colExists($conn, $table, $col) {
    if (!tableExists($conn, $table)) return false;
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($col);
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($r && $r->num_rows > 0);
}

function resolveLogin($conn) {
    $typeRaw = strtolower(trim((string)(
        $_SESSION['franchise_type']  ??
        $_SESSION['franchies_type']  ?? ''
    )));
    $values = array_filter(array_map('trim', [
        (string)($_SESSION['mobile_franchise_id']       ?? ''),
        (string)($_SESSION['mobile_franchise_code']     ?? ''),
        (string)($_SESSION['city_franchies_holder_id']  ?? ''),
        (string)($_SESSION['state_franchies_holder_id'] ?? ''),
        (string)($_SESSION['franchise_db_id']           ?? ''),
        (string)($_SESSION['franchise_id']              ?? ''),
        (string)($_SESSION['franchies_id']              ?? ''),
        (string)($_SESSION['franchise_code']            ?? ''),
        (string)($_SESSION['franchise_login_id']        ?? ''),
    ]), fn($v) => $v !== '');

    if (str_contains($typeRaw, 'mobile') || !empty($_SESSION['mobile_franchise_id'])) {
        $order = ['mobile', 'city', 'state'];
    } elseif (str_contains($typeRaw, 'city') || !empty($_SESSION['city_franchies_holder_id'])) {
        $order = ['city', 'state', 'mobile'];
    } else {
        $order = ['state', 'city', 'mobile'];
    }
    $tableMap = [
        'mobile' => ['table' => 'mobile_franchies_holder', 'codeCol' => 'mobile_franchies_id', 'nameCol' => 'owner_name'],
        'city'   => ['table' => 'city_franchies_holder',   'codeCol' => 'franchies_id',         'nameCol' => 'full_name'],
        'state'  => ['table' => 'franchies_holder',        'codeCol' => 'franchise_code',        'nameCol' => 'name'],
    ];
    foreach ($order as $type) {
        $map     = $tableMap[$type];
        $tbl     = $map['table'];
        $codeCol = $map['codeCol'];
        $nameCol = $map['nameCol'];
        if (!tableExists($conn, $tbl)) continue;
        foreach ($values as $val) {
            if (is_numeric($val)) {
                $id   = (int)$val;
                $stmt = $conn->prepare("SELECT * FROM `$tbl` WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $id); $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
                    if ($row) return ['type' => $type, 'id' => (int)$row['id'],
                        'code' => (string)($row[$codeCol] ?? $val),
                        'name' => (string)($row[$nameCol] ?? 'Franchise')];
                }
            }
            if (!colExists($conn, $tbl, $codeCol)) continue;
            $stmt = $conn->prepare("SELECT * FROM `$tbl` WHERE `$codeCol` = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $val); $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if ($row) return ['type' => $type, 'id' => (int)$row['id'],
                    'code' => (string)($row[$codeCol] ?? $val),
                    'name' => (string)($row[$nameCol] ?? 'Franchise')];
            }
        }
    }
    return null;
}

$login = resolveLogin($conn);
if (!$login) { header("Location: index.php"); exit; }

$franchiseType = $login['type'];
$franchiseId   = (int)$login['id'];
$franchiseCode = (string)$login['code'];
$franchiseName = (string)$login['name'];

$typeBadgeMap = [
    'mobile' => ['label' => '📱 Mobile Franchise', 'cls' => 'bg-green-100 text-green-800 border border-green-300'],
    'city'   => ['label' => '🏙 City Franchise',   'cls' => 'bg-blue-100 text-blue-800 border border-blue-300'],
    'state'  => ['label' => '🏛 State Franchise',  'cls' => 'bg-yellow-100 text-yellow-800 border border-yellow-300'],
];
$typeBadge = $typeBadgeMap[$franchiseType] ?? ['label' => 'Franchise', 'cls' => 'bg-gray-100 text-gray-700'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Billing — Netvexa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { background: #f1f2f4; font-family: 'Inter', system-ui, sans-serif; }

    /* Toast */
    #toast { position:fixed; top:20px; right:20px; z-index:9999; padding:12px 20px;
             border-radius:12px; color:#fff; font-size:13px; font-weight:600;
             display:flex; align-items:center; gap:10px; box-shadow:0 8px 32px rgba(0,0,0,.18);
             transform:translateY(-8px); opacity:0; pointer-events:none;
             transition:opacity .25s,transform .25s; }
    #toast.show { opacity:1; transform:translateY(0); pointer-events:auto; }
    #toast.success { background:#16a34a; }
    #toast.error   { background:#dc2626; }
    #toast.warning { background:#d97706; }
    #toast.info    { background:#103258; }

    /* Cart table */
    .cart-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .cart-table thead th { background:#103258; color:#fff; padding:11px 14px;
                           text-align:left; font-size:11.5px; font-weight:700;
                           letter-spacing:.04em; text-transform:uppercase; }
    .cart-table thead th:first-child { border-radius:10px 0 0 0; }
    .cart-table thead th:last-child  { border-radius:0 10px 0 0; text-align:center; }
    .cart-table tbody tr { border-bottom:1px solid #f0f3f7; transition:background .15s; }
    .cart-table tbody tr:hover { background:#f8fafc; }
    .cart-table td { padding:11px 14px; vertical-align:middle; }

    /* Qty buttons */
    .qty-wrap { display:flex; align-items:center; border:1.5px solid #e2e8f0;
                border-radius:8px; overflow:hidden; width:fit-content; background:#fff; }
    .qty-btn  { width:30px; height:32px; background:#f8fafc; border:none; cursor:pointer;
                font-size:17px; font-weight:700; color:#103258; display:flex;
                align-items:center; justify-content:center; transition:background .15s; }
    .qty-btn:hover { background:#e2e8f0; }
    .qty-num  { width:36px; text-align:center; font-weight:700; font-size:13px;
                color:#103258; border-left:1px solid #e2e8f0; border-right:1px solid #e2e8f0;
                padding:5px 0; }

    /* Member dropdown */
    #memberDropdown { position:absolute; top:100%; left:0; right:0; background:#fff;
                      border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.1);
                      z-index:100; margin-top:4px; max-height:200px; overflow-y:auto; }

    @media (max-width:1024px) { .ml-64 { margin-left:0 !important; } }
    </style>
</head>
<body class="bg-[#f1f2f4] overflow-x-hidden">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="px-5 py-6">

        <!-- Page Heading -->
        <div class="mt-5 mb-6 flex items-center gap-4">
            <div class="w-[36px] h-[36px] rounded-[8px] bg-[#f5eee3] flex items-center justify-center shrink-0">
                <i data-lucide="scan-line" class="w-[18px] h-[18px] text-[#cd9737]"></i>
            </div>
            <h2 class="whitespace-nowrap text-[20px] font-normal text-[#40352a]"
                style="font-family: Georgia, 'Times New Roman', serif;">
                POS BILLING
            </h2>
            <div class="w-full h-[2px] bg-[#d1a14b]"></div>
        </div>

        <!-- Franchise Info Bar -->
        <div class="flex flex-wrap items-center gap-3 mb-5">
            <span class="text-sm text-gray-500">
                Welcome, <strong class="text-gray-800"><?= e($franchiseName) ?></strong>
                &nbsp;·&nbsp;
                ID: <span class="font-mono font-bold text-[#103258]"><?= e($franchiseCode ?: $franchiseId) ?></span>
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold <?= e($typeBadge['cls']) ?>">
                <?= e($typeBadge['label']) ?>
            </span>
        </div>

        <!-- Toast -->
        <div id="toast">
            <i data-lucide="info" class="w-4 h-4 flex-shrink-0" id="toastIcon"></i>
            <span id="toastMsg"></span>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-sm overflow-visible">

            <!-- Card Header -->
            <div class="bg-[#103258] px-6 py-4 flex items-center justify-between rounded-t-2xl">
                <h3 class="text-white font-semibold text-lg flex items-center gap-2">
                    <i data-lucide="scan-line" class="w-5 h-5"></i>
                    POS Billing
                </h3>
                <button id="clearCartBtn"
                        class="flex items-center gap-2 text-sm bg-white/10 hover:bg-white/20
                               text-white px-3 py-1.5 rounded-lg transition">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Clear Cart
                </button>
            </div>

            <div class="p-6 space-y-6">

                <!-- Top Inputs -->
                <div class="grid md:grid-cols-3 gap-4">

                    <!-- Barcode -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            Scan Barcode
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                                <i data-lucide="scan-line" class="w-4 h-4"></i>
                            </span>
                            <input type="text" id="barcodeInput" autocomplete="off"
                                   placeholder="Focus here &amp; scan…"
                                   class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm
                                          bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#103258]"
                                   autofocus>
                        </div>
                    </div>

                    <!-- Member Search -->
                    <div class="relative">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            Member / Customer
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </span>
                            <input type="text" id="memberSearch" autocomplete="off"
                                   placeholder="Search member ID or name…"
                                   class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm
                                          bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#103258]">
                            <div id="memberDropdown" class="hidden"></div>
                        </div>
                        <input type="hidden" id="selectedMemberId"   value="">
                        <input type="hidden" id="selectedMemberName" value="">
                        <div id="memberCard"
     class="hidden mt-3 p-4 rounded-xl border border-blue-200 bg-blue-50">

    <div class="font-semibold text-[#103258] mb-2">
        Member Information
    </div>

    <div class="grid grid-cols-2 gap-3 text-sm">

        <div>
            <span class="text-gray-500">Customer ID</span><br>
            <strong id="cardMemberId"></strong>
        </div>

        <div>
            <span class="text-gray-500">Name</span><br>
            <strong id="cardMemberName"></strong>
        </div>

        <div>
            <span class="text-gray-500">Mobile</span><br>
            <strong id="cardMemberMobile"></strong>
        </div>

        <div>
            <span class="text-gray-500">Status</span><br>
            <strong id="cardMemberStatus"></strong>
        </div>

    </div>
</div>
                    </div>

                    <!-- Payment + Clear -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                            Payment Method
                        </label>
                        <select id="paymentMethod"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                                       bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#103258]">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>

                </div>

                <!-- Cart Table -->
                <div class="overflow-x-auto rounded-xl border border-gray-100">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th style="width:36px">#</th>
                                <th>Product</th>
                                <th>Price</th>
                                <th>BV / PV</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th style="width:50px;text-align:center">Del</th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr id="emptyRow">
                                <td colspan="7" class="text-center py-16 text-gray-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <i data-lucide="package-open" class="w-10 h-10 opacity-25"></i>
                                        <span class="text-sm">No products scanned yet — focus the barcode field and scan.</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Summary + Action Buttons -->
                <div class="flex justify-end">
                    <div class="w-full md:w-[320px] bg-[#F8FAFC] rounded-2xl border border-gray-100 p-5 space-y-3">

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Items in cart</span>
                            <span class="font-semibold" id="summaryItems">0</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Grand Total</span>
                            <span class="font-bold text-[#103258]">
                                ₹ <span id="summaryTotal">0.00</span>
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Total BV / PV</span>
                            <span class="font-semibold" id="summaryBV">0.00</span>
                        </div>

                        <hr class="border-gray-200">

                        <button id="generateBill"
                                class="w-full bg-green-600 hover:bg-green-700 active:scale-[.98]
                                       text-white py-2.5 rounded-xl font-semibold text-sm transition
                                       flex items-center justify-center gap-2">
                            <i data-lucide="receipt" class="w-4 h-4"></i>
                            Generate Repurchase Bill
                        </button>

                        <button id="printInvoice" disabled
                                class="w-full bg-[#103258] hover:bg-[#1A3F66] active:scale-[.98]
                                       text-white py-2.5 rounded-xl font-semibold text-sm transition
                                       flex items-center justify-center gap-2
                                       disabled:opacity-40 disabled:cursor-not-allowed">
                            <i data-lucide="printer" class="w-4 h-4"></i>
                            Print Invoice
                        </button>

                    </div>
                </div>

            </div>
        </div>

    </main>
</div>

<!-- ─── JS ─────────────────────────────────────────────────────────────────── -->
<script>
/* ═══════════════ STATE ═══════════════ */
let cart        = [];
let lastOrderId = null;
let memberTimer = null;

/* ═══════════════ DOM ════════════════ */
const barcodeInput   = document.getElementById('barcodeInput');
const cartBody       = document.getElementById('cartBody');
const emptyRow       = document.getElementById('emptyRow');
const summaryItems   = document.getElementById('summaryItems');
const summaryTotal   = document.getElementById('summaryTotal');
const summaryBV      = document.getElementById('summaryBV');
const generateBtn    = document.getElementById('generateBill');
const printBtn       = document.getElementById('printInvoice');
const clearBtn       = document.getElementById('clearCartBtn');
const memberSearch   = document.getElementById('memberSearch');
const memberDropdown = document.getElementById('memberDropdown');

/* ═══════════════ TOAST ══════════════ */
function showToast(msg, type = 'info') {
    const t = document.getElementById('toast');
    const icons = { success:'check-circle', error:'x-circle', warning:'alert-triangle', info:'info' };
    t.className = 'show ' + type;
    document.getElementById('toastMsg').textContent = msg;
    document.getElementById('toastIcon').setAttribute('data-lucide', icons[type] || 'info');
    if (window.lucide) lucide.createIcons();
    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('show'), 3500);
}

/* ═══════════════ BARCODE ════════════ */
barcodeInput.addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    const b = barcodeInput.value.trim();
    barcodeInput.value = '';
    if (!b) return;
    fetchProduct(b);
});

async function fetchProduct(barcode) {
    try {
        const res  = await fetch('api/get_product_by_barcode.php?barcode=' + encodeURIComponent(barcode));
        const data = await res.json();
        if (!data.success) { showToast(data.message || 'Product not found', 'error'); return; }
        addToCart(data.product);
    } catch { showToast('Network error — try again.', 'error'); }
}

/* ═══════════════ CART ════════════════ */
function addToCart(product) {
    const existing = cart.find(c => c.product_id === product.product_id);
    if (existing) {
        if (existing.qty >= existing.stock_balance) {
            showToast('Only ' + existing.stock_balance + ' unit(s) in stock.', 'warning'); return;
        }
        existing.qty++;
        showToast(product.product_name + ' — qty updated to ' + existing.qty, 'info');
    } else {
        if (product.stock_balance <= 0) { showToast(product.product_name + ' is out of stock.', 'error'); return; }
        cart.push({
            product_id    : product.product_id,
            product_name  : product.product_name,
            price         : parseFloat(product.price),
            bv            : parseFloat(product.bv_pv || 0),
            qty           : 1,
            image         : product.image || '',
            stock_balance : parseInt(product.stock_balance)
        });
        showToast(product.product_name + ' added to cart', 'success');
    }
    renderCart();
}

function changeQty(index, delta) {
    const item   = cart[index];
    const newQty = item.qty + delta;
    if (newQty < 1)                      { removeItem(index); return; }
    if (newQty > item.stock_balance)     { showToast('Only ' + item.stock_balance + ' unit(s) available.', 'warning'); return; }
    item.qty = newQty;
    renderCart();
}

function removeItem(index) { cart.splice(index, 1); renderCart(); }

function clearCart() { cart = []; lastOrderId = null; printBtn.disabled = true; renderCart(); }

/* ═══════════════ RENDER ══════════════ */
function renderCart() {
    if (!cart.length) {
        cartBody.innerHTML = '';
        cartBody.appendChild(emptyRow);
        updateSummary(); return;
    }
    emptyRow.remove();
    cartBody.innerHTML = cart.map((item, i) => {
        const total   = (item.price * item.qty).toFixed(2);
        const imgTag  = item.image
            ? `<img src="uploads/products/${item.image}" class="w-9 h-9 object-cover rounded-lg">`
            : `<div class="w-9 h-9 bg-gray-100 rounded-lg flex items-center justify-center">
                 <i data-lucide="package" class="w-4 h-4 text-gray-400"></i></div>`;
        return `<tr class="border-b border-gray-50">
            <td class="text-gray-400 text-xs">${i+1}</td>
            <td>
              <div class="flex items-center gap-3">
                ${imgTag}
                <span class="font-medium text-gray-800 text-sm">${esc(item.product_name)}</span>
              </div>
            </td>
            <td class="text-gray-700 text-sm">₹ ${item.price.toFixed(2)}</td>
            <td class="text-gray-500 text-sm">${item.bv.toFixed(2)}</td>
            <td>
              <div class="qty-wrap">
                <button class="qty-btn" onclick="changeQty(${i},-1)">−</button>
                <span class="qty-num">${item.qty}</span>
                <button class="qty-btn" onclick="changeQty(${i},1)">+</button>
              </div>
            </td>
            <td class="font-bold text-[#103258] text-sm">₹ ${total}</td>
            <td class="text-center">
              <button onclick="removeItem(${i})" class="text-red-400 hover:text-red-600 transition">
                <i data-lucide="x" class="w-4 h-4"></i>
              </button>
            </td>
          </tr>`;
    }).join('');
    if (window.lucide) lucide.createIcons();
    updateSummary();
}

function updateSummary() {
    summaryItems.textContent = cart.reduce((s,c) => s + c.qty, 0);
    summaryTotal.textContent = cart.reduce((s,c) => s + c.price * c.qty, 0).toFixed(2);
    summaryBV.textContent    = cart.reduce((s,c) => s + c.bv * c.qty, 0).toFixed(2);
}

/* ═══════════════ MEMBER SEARCH ═══════ */
memberSearch.addEventListener('input', () => {
    clearTimeout(memberTimer);
    const q = memberSearch.value.trim();
    if (q.length < 2) { memberDropdown.classList.add('hidden'); return; }
    memberTimer = setTimeout(() => searchMembers(q), 350);
});

async function searchMembers(q) {
    try {
        const res  = await fetch('api/search_members.php?q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data.success || !data.members.length) {
            memberDropdown.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400 text-center">No members found</div>';
        } else {
           memberDropdown.innerHTML = data.members.map(m => `
    <div class="flex justify-between px-4 py-2 hover:bg-[#F0F4F8] cursor-pointer text-sm"
         onclick='selectMember(${JSON.stringify(m)})'>
        <span class="font-medium">${esc(m.name)}</span>
        <span class="text-gray-400 text-xs">${esc(m.id)}</span>
    </div>
`).join('');
        }
        memberDropdown.classList.remove('hidden');
    } catch {}
}

function selectMember(member) {

    document.getElementById('selectedMemberId').value =
        member.id;

    document.getElementById('selectedMemberName').value =
        member.name;

    memberSearch.value =
        member.name;

    document.getElementById('cardMemberId').innerText =
        member.id;

    document.getElementById('cardMemberName').innerText =
        member.name;

    document.getElementById('cardMemberMobile').innerText =
        member.mobile || '-';

    document.getElementById('cardMemberStatus').innerText =
        member.status || 'Active';

    document.getElementById('memberCard')
        .classList.remove('hidden');

    memberDropdown.classList.add('hidden');
}
document.addEventListener('click', e => {
    if (!memberSearch.contains(e.target)) memberDropdown.classList.add('hidden');
});

/* ═══════════════ GENERATE BILL ═══════ */
generateBtn.addEventListener('click', async () => {
    if (!cart.length) { showToast('Cart is empty.', 'warning'); return; }
    const memberId   = document.getElementById('selectedMemberId').value;
    const memberName = document.getElementById('selectedMemberName').value || memberSearch.value.trim() || 'Walk-in Customer';
    const payment    = document.getElementById('paymentMethod').value;

    generateBtn.disabled = true;
    generateBtn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving…`;
    if (window.lucide) lucide.createIcons();

    try {
        const res  = await fetch('api/save_order.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ member_id: memberId, member_name: memberName, payment_method: payment, cart })
        });
        const data = await res.json();
        if (data.success) {
    lastOrderId = data.order_id;

    showToast(
        'Bill #' + lastOrderId + ' generated successfully!',
        'success'
    );

    cart = [];
    renderCart();

    printBtn.disabled = false;
} else {
            showToast(data.message || 'Failed to save order.', 'error');
        }
    } catch { showToast('Network error — order not saved.', 'error'); }
    finally {
        generateBtn.disabled = false;
        generateBtn.innerHTML = `<i data-lucide="receipt" class="w-4 h-4"></i> Generate Repurchase Bill`;
        if (window.lucide) lucide.createIcons();
    }
});

/* ═══════════════ PRINT ═══════════════ */
printBtn.addEventListener('click', () => {
    if (!lastOrderId) { showToast('Generate a bill first.', 'warning'); return; }
    window.open('pos-invoice.php?order_id=' + lastOrderId, '_blank');
});

/* ═══════════════ CLEAR ═══════════════ */
clearBtn.addEventListener('click', () => {
    if (!confirm('Clear all items from cart?')) return;
    clearCart();
    showToast('Cart cleared.', 'info');
});

/* ═══════════════ UTILS ═══════════════ */
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => lucide.createIcons());</script>
</body>
</html>