<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = $conn->real_escape_string($column);
    $res    = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function getUser(mysqli $conn, string $id): ?array {
    $fields = [
        'customer_id','full_name','mobile','position','placement_id',
        'registration_status','total_bv','left_carry','right_carry'
    ];
    $select = [];
    foreach ($fields as $f) {
        $select[] = columnExists($conn, 'signup_users', $f) ? "`$f`" : "'' AS `$f`";
    }
    $stmt = $conn->prepare("SELECT " . implode(',', $select) . " FROM signup_users WHERE customer_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getChildren(mysqli $conn, string $parentId): array {
    $children = ['L' => null, 'R' => null];
    $stmt = $conn->prepare("SELECT customer_id, position FROM signup_users WHERE placement_id = ?");
    if (!$stmt) return $children;
    $stmt->bind_param('s', $parentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $pos = strtoupper(trim((string)($row['position'] ?? '')));
        if ($pos === 'L' || $pos === 'LEFT')  $children['L'] = $row['customer_id'];
        if ($pos === 'R' || $pos === 'RIGHT') $children['R'] = $row['customer_id'];
    }
    $stmt->close();
    return $children;
}

function buildTree(mysqli $conn, ?string $id, array &$visited = [], int $depth = 0, int $maxDepth = 4): ?array {
    if (!$id || $depth > $maxDepth || isset($visited[$id])) return null;
    $visited[$id] = true;
    $user = getUser($conn, $id);
    if (!$user) return null;
    $children = getChildren($conn, $id);
    return [
        'user'  => $user,
        'left'  => buildTree($conn, $children['L'], $visited, $depth + 1, $maxDepth),
        'right' => buildTree($conn, $children['R'], $visited, $depth + 1, $maxDepth),
    ];
}

/**
 * Count ALL descendants using an iterative BFS/queue approach.
 * Counts the given $startId itself PLUS all its descendants.
 */
function countAllDescendants(mysqli $conn, string $startId): int {
    $count   = 0;
    $queue   = [$startId];
    $visited = [];

    while (!empty($queue)) {
        $currentId = array_shift($queue);

        if (isset($visited[$currentId])) continue;
        $visited[$currentId] = true;
        $count++;

        $stmt = $conn->prepare("SELECT customer_id FROM signup_users WHERE placement_id = ?");
        if (!$stmt) continue;
        $stmt->bind_param('s', $currentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (!isset($visited[$row['customer_id']])) {
                $queue[] = $row['customer_id'];
            }
        }
        $stmt->close();
    }

    return $count;
}

/**
 * NEW: Annotate every node in the built tree with full DB-side
 * left_total_count and right_total_count (all descendants, no depth limit).
 * Also annotates left_total_bv and right_total_bv from the in-memory tree BV.
 */
function annotateTreeCounts(mysqli $conn, ?array &$node): void {
    if (!$node) return;

    $userId = $node['user']['customer_id'] ?? null;
    if (!$userId) return;

    // Get direct left/right children of this node
    $children = getChildren($conn, $userId);

    $leftChildId  = $children['L'];
    $rightChildId = $children['R'];

    // Full DB count for left and right subtrees
    $node['left_total_count']  = $leftChildId  ? countAllDescendants($conn, $leftChildId)  : 0;
    $node['right_total_count'] = $rightChildId ? countAllDescendants($conn, $rightChildId) : 0;

    // Recurse into children
    annotateTreeCounts($conn, $node['left']);
    annotateTreeCounts($conn, $node['right']);
}

// countStats for BV (uses in-memory tree)
function countStats(?array $node): array {
    if (!$node) return ['count' => 0, 'bv' => 0];
    $l = countStats($node['left']  ?? null);
    $r = countStats($node['right'] ?? null);
    return [
        'count' => 1 + $l['count'] + $r['count'],
        'bv'    => (float)($node['user']['total_bv'] ?? 0) + $l['bv'] + $r['bv'],
    ];
}

$rootId         = trim((string)$_SESSION['customer_id']);
$searchCustomer = trim($_GET['search_id'] ?? '');
if ($searchCustomer !== '') $rootId = $searchCustomer;

$visited = [];
$tree    = buildTree($conn, $rootId, $visited, 0, 4);

$searchError = '';
if (!$tree) {
    $searchError = 'Customer ID not found.';
    $rootId  = trim((string)$_SESSION['customer_id']);
    $visited = [];
    $tree    = buildTree($conn, $rootId, $visited, 0, 4);
}

// NEW: Annotate every node in tree with full DB descendant counts
annotateTreeCounts($conn, $tree);

// BV from in-memory tree
$leftStats  = countStats($tree['left']  ?? null);
$rightStats = countStats($tree['right'] ?? null);

// Get direct left/right child IDs of the ROOT node
$rootChildren  = getChildren($conn, $rootId);
$leftChildId   = $rootChildren['L'];
$rightChildId  = $rootChildren['R'];

// Count ALL descendants (including the child itself) using BFS
$leftTotalCount  = $leftChildId  ? countAllDescendants($conn, $leftChildId)  : 0;
$rightTotalCount = $rightChildId ? countAllDescendants($conn, $rightChildId) : 0;

$rootUser = $tree['user'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Matching Tree View</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    width: 100%; height: 100%;
    background: #f0f5fb;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #0f2d52;
    overflow: hidden;
}

/* ── Sidebar offset ── */
.page-wrap {
    margin-left: 256px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
@media (max-width: 1024px) {
    .page-wrap { margin-left: 0; }
}

/* ══════════════════════════════════════════
   TOP BAR
══════════════════════════════════════════ */
.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 14px 6px;
    flex-shrink: 0;
    border-bottom: 1px solid #dde8f5;
    flex-wrap: wrap;
    background: #f0f5fb;
    z-index: 10;
}
.page-title { font-size: clamp(13px, 2.5vw, 19px); font-weight: 900; color: #00255c; line-height: 1; }
.page-sub   { font-size: clamp(9px, 1.4vw, 12px); color: #5c7a9b; margin-top: 2px; }

.search-row { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; }
.search-input {
    height: 32px; border: 1.5px solid #ccdaee; border-radius: 9px;
    padding: 0 10px; font-size: 13px; background: #fff; outline: none;
    width: clamp(110px, 24vw, 200px); transition: border-color .2s;
}
.search-input:focus { border-color: #2563eb; }
.search-btn {
    height: 32px; border: 0; border-radius: 9px; background: #2563eb; color: #fff;
    padding: 0 14px; font-size: 13px; font-weight: 800; cursor: pointer; white-space: nowrap;
}
.search-btn:hover { background: #1d4ed8; }
.search-err { font-size: 11px; color: #dc2626; font-weight: 700; margin-top: 3px; text-align: right; }

/* ══════════════════════════════════════════
   STATS STRIP
══════════════════════════════════════════ */
.stats-strip {
    display: flex;
    gap: 8px;
    padding: 6px 14px 5px;
    flex-shrink: 0;
    background: #f0f5fb;
}

.stat-card {
    display: flex; align-items: center; gap: 10px;
    background: #fff; border: 1.5px solid #dde8f5; border-radius: 12px;
    padding: 6px 18px 6px 10px;
    box-shadow: 0 2px 10px rgba(15,45,82,.06);
    flex: 1 1 0; min-width: 0; cursor: default;
    transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s ease,
                border-color .22s ease, background .22s ease;
    position: relative; overflow: hidden;
}
.stat-card::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,.45) 50%, transparent 70%);
    background-size: 200% 100%; background-position: -100% 0;
    transition: background-position .5s ease;
    pointer-events: none; border-radius: inherit;
}
.stat-card:hover { transform: translateY(-3px) scale(1.025); box-shadow: 0 10px 28px rgba(15,45,82,.14); }
.stat-card:hover::after { background-position: 200% 0; }
.stat-card.left-card:hover  { border-color: #2563eb; background: linear-gradient(135deg,#eff6ff,#fff); box-shadow: 0 10px 28px rgba(37,99,235,.18); }
.stat-card.right-card:hover { border-color: #ea580c; background: linear-gradient(135deg,#fff7ed,#fff); box-shadow: 0 10px 28px rgba(234,88,12,.18); }
.stat-icon { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: transform .22s cubic-bezier(.34,1.56,.64,1); }
.stat-card:hover .stat-icon { transform: scale(1.15) rotate(-6deg); }
.stat-icon.l { background: #dbeafe; color: #2563eb; }
.stat-icon.r { background: #ffedd5; color: #ea580c; }
.stat-lbl { font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
.stat-val { font-size: 18px; font-weight: 900; color: #0f2d52; line-height: 1; transition: color .22s ease; }
.stat-card.left-card:hover  .stat-val { color: #2563eb; }
.stat-card.right-card:hover .stat-val { color: #ea580c; }
.stat-sub { font-size: 9px; color: #94a3b8; margin-top: 1px; }
.bv-badge {
    margin-left: auto; font-size: 9px; font-weight: 800;
    padding: 2px 7px; border-radius: 20px; white-space: nowrap; letter-spacing: .3px;
    transition: transform .22s ease;
}
.stat-card:hover .bv-badge { transform: scale(1.08); }
.stat-card.left-card  .bv-badge { background: #dbeafe; color: #1d4ed8; }
.stat-card.right-card .bv-badge { background: #ffedd5; color: #c2410c; }

/* ══════════════════════════════════════════
   LEGEND
══════════════════════════════════════════ */
.legend {
    display: flex; justify-content: center; align-items: center;
    gap: 14px; padding: 3px 0 2px;
    font-size: clamp(10px, 1.5vw, 12px); font-weight: 600; color: #475569;
    flex-shrink: 0; background: #f0f5fb;
}
.legend-item { display: flex; align-items: center; gap: 5px; }
.legend-dot  { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.legend-dot.active   { background: #16a34a; }
.legend-dot.inactive { background: #94a3b8; }
.legend-dot.process  { background: #dc2626; }

/* ══════════════════════════════════════════
   TREE AREA
══════════════════════════════════════════ */
.tree-area {
    flex: 1;
    min-height: 0;
    padding: 5px 8px 8px;
    display: flex;
    overflow: hidden;
    position: relative;
}

/* ── Outer scroll container ── */
.tree-box {
    flex: 1;
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(15,45,82,.07);
    position: relative;
    overflow: scroll;
    overflow: overlay;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: auto;
    cursor: grab;
    touch-action: pan-x pan-y;
    overscroll-behavior: contain;
}
.tree-box.grabbing { cursor: grabbing; }

.tree-box::-webkit-scrollbar { width: 6px; height: 6px; }
.tree-box::-webkit-scrollbar-track { background: transparent; }
.tree-box::-webkit-scrollbar-thumb { background: rgba(37,99,235,.28); border-radius: 99px; }
.tree-box::-webkit-scrollbar-thumb:hover { background: rgba(37,99,235,.55); }
.tree-box::-webkit-scrollbar-corner { background: transparent; }
.tree-box { scrollbar-width: thin; scrollbar-color: rgba(37,99,235,.28) transparent; }

/* ── Zoom wrapper ── */
#zoomWrap {
    transform-origin: 0 0;
    position: relative;
    will-change: transform;
}

/* ── Canvas inside zoom wrapper ── */
#treeCanvas {
    position: relative;
}

#treeSvg {
    position: absolute;
    top: 0; left: 0;
    pointer-events: none;
    z-index: 1;
    overflow: visible;
}

/* ══════════════════════════════════════════
   SCROLL HINT OVERLAY
══════════════════════════════════════════ */
.scroll-hint {
    position: absolute; bottom: 8px; right: 10px;
    font-size: 10px; color: #94a3b8; pointer-events: none;
    display: flex; align-items: center; gap: 4px;
    z-index: 20;
    transition: opacity .4s;
}

/* ══════════════════════════════════════════
   NODES
══════════════════════════════════════════ */
.tree-node {
    position: absolute;
    display: flex; flex-direction: column; align-items: center;
    z-index: 2;
    transform: translateX(-50%);
}
.leaf-card {
    display: flex; flex-direction: column; align-items: center;
    width: 100%; cursor: pointer; text-align: center;
    -webkit-tap-highlight-color: transparent;
    padding-bottom: 3px;
    transition: filter .15s ease;
}
.leaf-card:hover, .leaf-card.popup-open {
    filter: drop-shadow(0 4px 10px rgba(37,99,235,.4));
}
.leaf-empty { opacity: .22; cursor: default; pointer-events: none; }

.avatar-wrap {
    position: relative; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0; border-radius: 22%;
}
.avatar-ring {
    position: absolute; inset: -3px; border-radius: inherit;
    border: 2.5px solid transparent; pointer-events: none;
}
.status-dot {
    position: absolute; bottom: -1px; right: -1px;
    border-radius: 50%; border: 2px solid #fff; z-index: 3;
}
.status-dot.active   { background: #16a34a; }
.status-dot.inactive { background: #94a3b8; }
.status-dot.process  { background: #dc2626; }
.avatar-bg-active,
.avatar-bg-inactive,
.avatar-bg-process  { background: linear-gradient(135deg,#eef4ff,#dbeafe); }
.avatar-bg-empty    { background: #e9f0f7; }
.ring-active,
.ring-inactive,
.ring-process       { border-color: #e8a800; }

.node-id {
    font-weight: 800; color: #1e3a5f; line-height: 1.2;
    text-align: center; width: 100%; word-break: break-all;
    white-space: normal; margin-top: 4px;
}
.node-name {
    color: #64748b; line-height: 1.2; text-align: center;
    width: 100%; word-break: break-word; white-space: normal; margin-top: 1px;
}
.tree-line { stroke: #00255c; stroke-width: 1.4; fill: none; stroke-linecap: round; }

/* ══════════════════════════════════════════
   TOOLTIP
══════════════════════════════════════════ */
.member-tooltip {
    position: fixed;
    width: min(260px, 88vw);
    background: #07112b; color: #fff;
    border-radius: 13px; padding: 12px 14px;
    opacity: 0; visibility: hidden; z-index: 9999999;
    box-shadow: 0 16px 40px rgba(0,0,0,.4);
    pointer-events: none;
    transition: opacity .14s, transform .14s;
    transform: translateY(8px);
}
.member-tooltip.show { opacity: 1; visibility: visible; transform: translateY(0); }
.tip-section {
    font-size: 10px; font-weight: 800; color: #60a5fa;
    text-transform: uppercase; letter-spacing: .6px;
    padding: 6px 0 3px; margin-top: 2px;
    border-top: 1px solid rgba(255,255,255,.08);
}
.tip-section:first-child { border-top: 0; padding-top: 0; margin-top: 0; }
.popup-row {
    display: flex; justify-content: space-between; gap: 10px;
    padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,.06);
}
.popup-row:last-child { border-bottom: 0; }
.popup-row span   { color: #94a3b8; font-size: 12px; white-space: nowrap; }
.popup-row strong { font-size: 12px; text-align: right; word-break: break-word; }
.popup-row.bv     strong { color: #4ade80; }
.popup-row.team-l strong { color: #60a5fa; }
.popup-row.team-r strong { color: #fb923c; }

/* ══════════════════════════════════════════
   MOBILE RESPONSIVE
══════════════════════════════════════════ */
@media (max-width: 640px) {
    .top-bar { flex-direction: column; gap: 6px; align-items: stretch; }
    .search-row { width: 100%; }
    .search-input { flex: 1; min-width: 0; }
    .stats-strip { gap: 5px; }
    .stat-card { padding: 5px 10px 5px 8px; gap: 7px; }
    .stat-val { font-size: 14px; }
    .bv-badge { display: none; }
    .stat-icon { width: 26px; height: 26px; }
}
@media (max-width: 400px) {
    .stat-lbl  { font-size: 8px; }
    .stat-sub  { display: none; }
}
</style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/includes/sidebar.php')) include __DIR__ . '/includes/sidebar.php'; ?>

<div class="page-wrap">
<?php if (file_exists(__DIR__ . '/includes/navbar.php')) include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Top bar -->
    <div class="top-bar">
        <div>
            <div class="page-title">Matching Tree View</div>
            <div class="page-sub">
                Viewing network of <b><?= e($rootUser['full_name'] ?? '') ?></b>
                &middot; <?= e($rootUser['customer_id'] ?? $rootId) ?>
            </div>
        </div>
        <div>
            <form method="GET" class="search-row">
                <input type="text" name="search_id"
                       value="<?= e($searchCustomer !== '' ? $searchCustomer : $rootId) ?>"
                       placeholder="Search Customer ID" class="search-input">
                <button type="submit" class="search-btn">Search</button>
            </form>
            <?php if ($searchError !== ''): ?>
                <div class="search-err"><?= e($searchError) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats — uses full DB count (no depth limit) via BFS -->
    <div class="stats-strip">
        <div class="stat-card left-card">
            <div class="stat-icon l">
                <i data-lucide="users" style="width:16px;height:16px"></i>
            </div>
            <div>
                <div class="stat-lbl">Left Team</div>
                <div class="stat-val"><?= e($leftTotalCount) ?></div>
                <div class="stat-sub">Total Members</div>
            </div>
            <div class="bv-badge">BV: <?= e(number_format($leftStats['bv'], 0)) ?></div>
        </div>
        <div class="stat-card right-card">
            <div class="stat-icon r">
                <i data-lucide="users" style="width:16px;height:16px"></i>
            </div>
            <div>
                <div class="stat-lbl">Right Team</div>
                <div class="stat-val"><?= e($rightTotalCount) ?></div>
                <div class="stat-sub">Total Members</div>
            </div>
            <div class="bv-badge">BV: <?= e(number_format($rightStats['bv'], 0)) ?></div>
        </div>
    </div>

    <!-- Legend -->
    <div class="legend">
        <div class="legend-item"><div class="legend-dot active"></div> Active</div>
        <div class="legend-item"><div class="legend-dot inactive"></div> Inactive</div>
        <div class="legend-item"><div class="legend-dot process"></div> In Process</div>
    </div>

    <!-- Tree -->
    <div class="tree-area">
        <div class="tree-box" id="treeBox">
            <div id="zoomWrap">
                <div id="treeCanvas">
                    <svg id="treeSvg" xmlns="http://www.w3.org/2000/svg"></svg>
                </div>
            </div>
            <div class="scroll-hint" id="scrollHint">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>
                </svg>
                Scroll · Pinch to zoom
            </div>
        </div>
    </div>
</div>

<div id="memberTooltip" class="member-tooltip"></div>

<script>
/* ════════════════════════════════════════════════════════
   TREE DATA (from PHP) — now includes left_total_count / right_total_count
   on every node, computed via full DB BFS (no depth limit)
════════════════════════════════════════════════════════ */
const treeData = <?= json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const treeBox    = document.getElementById('treeBox');
const zoomWrap   = document.getElementById('zoomWrap');
const canvas     = document.getElementById('treeCanvas');
const svgEl      = document.getElementById('treeSvg');
const tooltip    = document.getElementById('memberTooltip');
const scrollHint = document.getElementById('scrollHint');

const LEVELS    = 5;
const PAD_TOP   = 24;
const PAD_BOT   = 24;
const PAD_H     = 16;
const LEVEL_GAP = 14;

/* ════════════════════════════════════════════════════════
   ZOOM STATE
════════════════════════════════════════════════════════ */
const ZOOM_MIN  = 0.25;
const ZOOM_MAX  = 3.0;
const ZOOM_STEP = 0.15;
let   scale     = 1.0;
let   canvasW   = 0;
let   canvasH   = 0;

/* Apply scale keeping focal point (cx, cy in treeBox viewport coords) fixed */
function applyZoom(newScale, focalX, focalY) {
    newScale = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, newScale));
    if (newScale === scale) return;

    const sl = treeBox.scrollLeft;
    const st = treeBox.scrollTop;

    /* Focal point in canvas space before scale change */
    const canvasFX = (sl + focalX) / scale;
    const canvasFY = (st + focalY) / scale;

    scale = newScale;

    zoomWrap.style.width        = (canvasW * scale) + 'px';
    zoomWrap.style.height       = (canvasH * scale) + 'px';
    zoomWrap.style.transform    = `scale(${scale})`;

    treeBox.scrollLeft = canvasFX * scale - focalX;
    treeBox.scrollTop  = canvasFY * scale - focalY;

    /* Disable native pan when zoomed in so single-finger drag works */
    treeBox.style.touchAction = scale > 1 ? 'none' : 'pan-x pan-y';
}

/* ════════════════════════════════════════════════════════
   MOUSE WHEEL ZOOM (plain scroll = zoom, map-style UX)
════════════════════════════════════════════════════════ */
treeBox.addEventListener('wheel', e => {
    e.preventDefault();
    const rect   = treeBox.getBoundingClientRect();
    const focalX = e.clientX - rect.left;
    const focalY = e.clientY - rect.top;
    const delta  = e.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;
    applyZoom(scale + delta, focalX, focalY);
}, { passive: false });

/* ════════════════════════════════════════════════════════
   DRAG-TO-SCROLL (mouse)
════════════════════════════════════════════════════════ */
let isDragging   = false;
let dragStartX   = 0, dragStartY   = 0;
let scrollStartX = 0, scrollStartY = 0;

treeBox.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    isDragging   = true;
    dragStartX   = e.clientX; dragStartY   = e.clientY;
    scrollStartX = treeBox.scrollLeft;
    scrollStartY = treeBox.scrollTop;
    treeBox.classList.add('grabbing');
    e.preventDefault();
});
window.addEventListener('mousemove', e => {
    if (!isDragging) return;
    treeBox.scrollLeft = scrollStartX - (e.clientX - dragStartX);
    treeBox.scrollTop  = scrollStartY - (e.clientY - dragStartY);
});
window.addEventListener('mouseup', () => {
    isDragging = false;
    treeBox.classList.remove('grabbing');
});

/* ════════════════════════════════════════════════════════
   TOUCH: single-finger drag + two-finger pinch-to-zoom
════════════════════════════════════════════════════════ */
let touchState = null;

treeBox.addEventListener('touchstart', e => {
    hideTip();
    if (e.touches.length === 1) {
        touchState = {
            type: 'pan',
            startX:  e.touches[0].clientX,
            startY:  e.touches[0].clientY,
            scrollX: treeBox.scrollLeft,
            scrollY: treeBox.scrollTop,
        };
    } else if (e.touches.length === 2) {
        const t0 = e.touches[0], t1 = e.touches[1];
        const dist = Math.hypot(t0.clientX - t1.clientX, t0.clientY - t1.clientY);
        const rect = treeBox.getBoundingClientRect();
        const midX = (t0.clientX + t1.clientX) / 2 - rect.left;
        const midY = (t0.clientY + t1.clientY) / 2 - rect.top;
        touchState = { type: 'pinch', startDist: dist, startScale: scale, midX, midY };
        e.preventDefault();
    }
}, { passive: false });

treeBox.addEventListener('touchmove', e => {
    if (!touchState) return;
    if (touchState.type === 'pan' && e.touches.length === 1 && scale > 1) {
        const dx = e.touches[0].clientX - touchState.startX;
        const dy = e.touches[0].clientY - touchState.startY;
        treeBox.scrollLeft = touchState.scrollX - dx;
        treeBox.scrollTop  = touchState.scrollY - dy;
        e.preventDefault();
    } else if (touchState.type === 'pinch' && e.touches.length === 2) {
        const t0 = e.touches[0], t1 = e.touches[1];
        const dist     = Math.hypot(t0.clientX - t1.clientX, t0.clientY - t1.clientY);
        const newScale = touchState.startScale * (dist / touchState.startDist);
        applyZoom(newScale, touchState.midX, touchState.midY);
        e.preventDefault();
    }
}, { passive: false });

treeBox.addEventListener('touchend', e => {
    if (e.touches.length === 0) touchState = null;
});

/* Safari pinch gesture fallback */
treeBox.addEventListener('gesturestart',  e => e.preventDefault(), { passive: false });
treeBox.addEventListener('gesturechange', e => {
    e.preventDefault();
    const rect = treeBox.getBoundingClientRect();
    applyZoom(scale * e.scale, rect.width / 2, rect.height / 2);
}, { passive: false });

/* ════════════════════════════════════════════════════════
   KEYBOARD SHORTCUTS  (+/- keys)
════════════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT') return;
    const cx = treeBox.clientWidth  / 2;
    const cy = treeBox.clientHeight / 2;
    if (e.key === '+' || e.key === '=') { e.preventDefault(); applyZoom(scale + ZOOM_STEP, cx, cy); }
    if (e.key === '-')                  { e.preventDefault(); applyZoom(scale - ZOOM_STEP, cx, cy); }
    if (e.key === '0')                  { e.preventDefault(); applyZoom(1.0, cx, cy); }
});

/* ════════════════════════════════════════════════════════
   SCROLL HINT hide
════════════════════════════════════════════════════════ */
treeBox.addEventListener('scroll', () => {
    scrollHint.style.opacity = '0';
}, { once: true });

/* ════════════════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════════════════ */
function subtreeBV(node) {
    if (!node) return 0;
    return (parseFloat(node.user?.total_bv) || 0) + subtreeBV(node.left) + subtreeBV(node.right);
}

function statusCls(s) {
    s = String(s || '').toLowerCase();
    if (s === 'active' || s === 'approved')                       return 'active';
    if (s === 'pending' || s === 'process' || s === 'in process') return 'process';
    return 'inactive';
}
function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g,
        m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);
}
function fmt(n) { return Number(n || 0).toLocaleString(); }

/* ════════════════════════════════════════════════════════
   AVATAR HTML
════════════════════════════════════════════════════════ */
function personSvg(size, cls) {
    const cols = { active:'#2563eb', inactive:'#94a3b8', process:'#ca8a04', empty:'#c8d8e8' };
    const col  = cols[cls] || cols.inactive;
    const ic   = Math.round(size * 0.54);
    return `<svg width="${ic}" height="${ic}" viewBox="0 0 24 24" fill="none"
        xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;display:block;">
        <circle cx="12" cy="7" r="4" fill="${col}"/>
        <path d="M4 20c0-4 3.582-7 8-7s8 3 8 7" stroke="${col}" stroke-width="2"
              stroke-linecap="round" fill="none"/>
    </svg>`;
}
function avatarHtml(size, cls, dotSz) {
    const br = Math.round(size * 0.22);
    return `<div class="avatar-wrap avatar-bg-${cls}"
                 style="width:${size}px;height:${size}px;border-radius:${br}px;">
        <div class="avatar-ring ring-${cls}" style="border-radius:${br + 3}px;"></div>
        ${personSvg(size, cls)}
        <span class="status-dot ${cls}" style="width:${dotSz}px;height:${dotSz}px;"></span>
    </div>`;
}
function emptyAvatarHtml(size) {
    const br = Math.round(size * 0.22);
    const ic = Math.round(size * 0.54);
    return `<div class="avatar-wrap avatar-bg-empty"
                 style="width:${size}px;height:${size}px;border-radius:${br}px;opacity:.25;">
        <svg width="${ic}" height="${ic}" viewBox="0 0 24 24" fill="none" style="display:block;">
            <circle cx="12" cy="7" r="4" fill="#c8d8e8"/>
            <path d="M4 20c0-4 3.582-7 8-7s8 3 8 7" stroke="#c8d8e8"
                  stroke-width="2" stroke-linecap="round" fill="none"/>
        </svg>
    </div>`;
}

/* ════════════════════════════════════════════════════════
   TOOLTIP
   KEY CHANGE: uses node.left_total_count / node.right_total_count
   (pre-computed by PHP BFS — counts ALL descendants, no depth limit)
   BV still uses in-memory subtree (only 4 levels loaded)
════════════════════════════════════════════════════════ */
let activeCard = null;

function tipHtml(u, nodeObj) {
    // ── TEAM COUNTS: use PHP-precomputed full DB counts (no depth limit) ──
    const lCount = nodeObj?.left_total_count  ?? 0;
    const rCount = nodeObj?.right_total_count ?? 0;

    // ── BV: still from in-memory 4-level tree ──
    const lBV = subtreeBV(nodeObj?.left  || null);
    const rBV = subtreeBV(nodeObj?.right || null);

    return `
        <div class="tip-section">Member Info</div>
        <div class="popup-row"><span>Name</span><strong>${esc(u.full_name||'-')}</strong></div>
        <div class="popup-row"><span>ID</span><strong>${esc(u.customer_id||'-')}</strong></div>
        <div class="popup-row"><span>Status</span><strong>${esc(u.registration_status||'-')}</strong></div>
        <div class="tip-section">Team Members</div>
        <div class="popup-row team-l"><span>Left Team</span><strong>${fmt(lCount)}</strong></div>
        <div class="popup-row team-r"><span>Right Team</span><strong>${fmt(rCount)}</strong></div>
        <div class="tip-section">BV Details</div>
        <div class="popup-row bv"><span>Left BV</span><strong>${fmt(lBV)}</strong></div>
        <div class="popup-row bv"><span>Right BV</span><strong>${fmt(rBV)}</strong></div>
        <div class="popup-row bv"><span>Total BV</span><strong>${fmt(lBV + rBV)}</strong></div>`;
}

function posTip(card) {
    const r  = card.getBoundingClientRect();
    const tw = tooltip.offsetWidth, th = tooltip.offsetHeight;
    let left = r.left + r.width / 2 - tw / 2;
    let top  = r.top  - th - 10;
    if (top  < 8)                    top  = r.bottom + 8;
    if (left < 8)                    left = 8;
    if (left + tw > innerWidth  - 8) left = innerWidth  - tw - 8;
    if (top  + th > innerHeight - 8) top  = innerHeight - th - 8;
    tooltip.style.left = left + 'px';
    tooltip.style.top  = top  + 'px';
}
function showTip(card, user, nodeObj) {
    tooltip.innerHTML = tipHtml(user, nodeObj);
    tooltip.classList.add('show');
    posTip(card);
}
function hideTip() {
    tooltip.classList.remove('show');
    if (activeCard) { activeCard.classList.remove('popup-open'); activeCard = null; }
}

/* ════════════════════════════════════════════════════════
   RENDER TREE
════════════════════════════════════════════════════════ */
function renderTree() {
    hideTip();
    canvas.querySelectorAll('.tree-node').forEach(el => el.remove());
    svgEl.innerHTML = '';

    const boxW = treeBox.clientWidth  || 360;
    const boxH = treeBox.clientHeight || 480;

    const MIN_SLOT_W = 68;
    const leaves     = Math.pow(2, LEVELS - 1);
    const naturalW   = leaves * MIN_SLOT_W + PAD_H * 2;
    canvasW          = Math.max(boxW, naturalW);

    const slotW  = d => canvasW / Math.pow(2, d);
    const fontId = d => Math.max(5.5, Math.min(11,  slotW(d) * 0.088));
    const fontNm = d => Math.max(5,   Math.min(9.5, slotW(d) * 0.076));
    const textH  = d => Math.ceil(fontId(d) * 1.3 + fontNm(d) * 1.3 + 5);
    const dotSz  = d => Math.max(4, Math.min(10, Math.round(slotW(d) * 0.024)));

    const RATIOS   = [1, 0.80, 0.64, 0.52, 0.44];
    const sumRatio = RATIOS.reduce((a,b) => a+b, 0);
    const sumText  = Array.from({length: LEVELS}, (_,d) => textH(d)).reduce((a,b) => a+b, 0);
    const vAvailMin = boxH - PAD_TOP - PAD_BOT - sumText - LEVEL_GAP * (LEVELS - 1);
    const vAvail   = Math.max(vAvailMin, 80);
    const avatarSz = RATIOS.map(r => Math.max(14, Math.floor(vAvail * r / sumRatio)));
    const cardH    = avatarSz.map((sz,d) => sz + textH(d));

    const levelY = [];
    let yy = PAD_TOP;
    for (let d = 0; d < LEVELS; d++) { levelY.push(yy); yy += cardH[d] + LEVEL_GAP; }
    canvasH = Math.max(boxH, yy + PAD_BOT);

    canvas.style.width  = canvasW + 'px';
    canvas.style.height = canvasH + 'px';
    svgEl.setAttribute('width',  canvasW);
    svgEl.setAttribute('height', canvasH);

    zoomWrap.style.width         = (canvasW * scale) + 'px';
    zoomWrap.style.height        = (canvasH * scale) + 'px';
    zoomWrap.style.transform     = `scale(${scale})`;
    zoomWrap.style.transformOrigin = '0 0';

    function build(node, depth, slotIdx, parentInfo) {
        if (depth >= LEVELS) return;

        const isEmpty = !node;
        const sw      = slotW(depth);
        const cx      = sw * slotIdx + sw / 2;
        const nodeTop = levelY[depth];
        const sz      = avatarSz[depth];
        const ch      = cardH[depth];
        const fiSz    = fontId(depth).toFixed(1);
        const fnSz    = fontNm(depth).toFixed(1);
        const ds      = dotSz(depth);

        if (parentInfo) {
            const { px, botY } = parentInfo;
            const my = (botY + nodeTop) / 2;
            const p  = document.createElementNS('http://www.w3.org/2000/svg','path');
            p.setAttribute('class','tree-line');
            p.setAttribute('d', `M ${px} ${botY} L ${px} ${my} L ${cx} ${my} L ${cx} ${nodeTop}`);
            svgEl.appendChild(p);
        }

        const div = document.createElement('div');
        div.className   = 'tree-node';
        div.style.left  = cx + 'px';
        div.style.top   = nodeTop + 'px';
        div.style.width = sw + 'px';

        if (isEmpty) {
            div.innerHTML = `<div class="leaf-card leaf-empty">${emptyAvatarHtml(sz)}</div>`;
        } else {
            const user = node.user || {};
            const cls  = statusCls(user.registration_status);
            div.innerHTML = `<div class="leaf-card" tabindex="0">
                ${avatarHtml(sz, cls, ds)}
                <div class="node-id"   style="font-size:${fiSz}px">${esc(user.customer_id||'')}</div>
                <div class="node-name" style="font-size:${fnSz}px">${esc(user.full_name||'Member')}</div>
            </div>`;

            const card = div.querySelector('.leaf-card');

            card.addEventListener('mouseenter', () => { if (!isDragging) showTip(card, user, node); });
            card.addEventListener('mousemove',  () => { if (!isDragging) posTip(card); });
            card.addEventListener('mouseleave', () => {
                if (activeCard !== card) tooltip.classList.remove('show');
            });
            card.addEventListener('click', ev => {
                ev.stopPropagation();
                const dx = Math.abs(treeBox.scrollLeft - scrollStartX);
                const dy = Math.abs(treeBox.scrollTop  - scrollStartY);
                if (dx > 5 || dy > 5) return;
                document.querySelectorAll('.leaf-card.popup-open')
                         .forEach(c => c.classList.remove('popup-open'));
                if (activeCard === card) { hideTip(); return; }
                activeCard = card;
                card.classList.add('popup-open');
                showTip(card, user, node);
            });
        }

        canvas.appendChild(div);

        const botY = nodeTop + ch;
        build(node?.left  || null, depth+1, slotIdx*2,   { px:cx, botY });
        build(node?.right || null, depth+1, slotIdx*2+1, { px:cx, botY });
    }

    build(treeData, 0, 0, null);

    requestAnimationFrame(() => {
        treeBox.scrollLeft = Math.max(0, (canvasW * scale - boxW) / 2);
        treeBox.scrollTop  = 0;
        scrollStartX = treeBox.scrollLeft;
        scrollStartY = treeBox.scrollTop;
    });
}

document.addEventListener('click', hideTip);

let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(renderTree, 120);
});

renderTree();

setTimeout(() => { scrollHint.style.opacity = '0'; }, 4000);

if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>