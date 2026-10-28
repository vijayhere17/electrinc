<?php
/**
 * ELECTRINC — public EV product landing page.
 * Static marketing content; product data lives here as a plain array
 * (no DB dependency) so the page works standalone at the site root.
 */
$products = [
    ['img' => 'auto-l3.jpeg',        'cat' => 'rickshaw', 'name' => 'Auto L-3',                    'tag' => 'Passenger E-Rickshaw', 'range' => '90–100 KM+',   'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months'],
    ['img' => 'erikshaw-solar.jpeg', 'cat' => 'rickshaw', 'name' => 'E-Rickshaw with Solar',        'tag' => 'Passenger · Solar Roof', 'range' => '100 KM+',      'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months'],
    ['img' => 'mini-eloader.jpeg',   'cat' => 'rickshaw', 'name' => 'Mini E-Loader',                'tag' => 'Cargo · LXI / VXI / ZXI', 'range' => '70–120 KM+',   'battery' => '51V / 45–75AH Lithium', 'warranty' => '18 Months'],
    ['img' => 'eloader-solar.jpeg',  'cat' => 'rickshaw', 'name' => 'E-Loader with Solar',          'tag' => 'Cargo · Solar Roof',      'range' => '100 KM+',      'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months'],
    ['img' => 'evelocy-m2.jpeg',     'cat' => 'scooter',  'name' => 'E-Velocy M2',                  'tag' => 'Premium · Dual Disc Brakes', 'range' => '125–150 KM+', 'battery' => '3.0KW / 51V/60AH Lithium', 'warranty' => '36 Months'],
    ['img' => 'mania-zxi.jpeg',      'cat' => 'scooter',  'name' => 'Electer Mania ZXI',            'tag' => 'Sport Scooter',           'range' => '135–150 KM+',  'battery' => '60V / 60AH Lithium',    'warranty' => '18 Months'],
    ['img' => 'active-chetaak.jpeg', 'cat' => 'scooter',  'name' => 'Active Chetaak',               'tag' => 'VXI / ZXI',               'range' => '55 – 125 KM+', 'battery' => '60V / 45AH Lithium',    'warranty' => '36 Months'],
    ['img' => 'max-vxi.jpeg',        'cat' => 'scooter',  'name' => 'Max VXI',                      'tag' => 'City Scooter',            'range' => '55 KM+',       'battery' => '51V / 30AH Lithium',    'warranty' => '18 Months'],
    ['img' => 'dmi.jpeg',            'cat' => 'scooter',  'name' => 'DMI',                          'tag' => 'Value Scooter',           'range' => '40–50 KM+',    'battery' => 'Lead Acid',             'warranty' => '12 Months'],
    ['img' => 'bike-v6.jpeg',        'cat' => 'bike',     'name' => 'Bike V6 2.0',                  'tag' => 'Sports E-Bike',           'range' => '120 KM+',      'battery' => '72V / 60AH Lithium',    'warranty' => '36 Months'],
    ['img' => 'bike-kn1.jpeg',       'cat' => 'bike',     'name' => 'Bike KN1',                     'tag' => 'Commuter E-Bike',         'range' => '100 KM+',      'battery' => '72V / 45AH Lithium',    'warranty' => '36 Months'],
];

$catLabels = ['rickshaw' => 'E-Rickshaw', 'scooter' => 'E-Scooter', 'bike' => 'E-Bike'];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ELECTRINC — Clean Energy. Brighter Business.</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="ELECTRINC — a venture of Incredible Group of Companies. Premium electric rickshaws, scooters and bikes, with a pan-India franchise network.">
<link rel="icon" href="assets/electrinc-logo.png">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
    :root{
        --saffron:#E0700C; --saffron-d:#B25400; --saffron-soft:#FFF3E7;
        --green:#0E7A3B;   --green-d:#0A5E2D;   --green-soft:#EAF5EC;
        --night:#0A1F16;   --night-2:#123B27;
        --ink:#20241F;     --ink-2:#5F6960;
        --page:#FBFAF6;    --line:#ECE7DD;
    }
    *{ font-family:'Manrope',system-ui,sans-serif; }
    html{ scroll-behavior:smooth; }
    body{ background:var(--page); color:var(--ink); }

    .dot-grid{ background-image:radial-gradient(rgba(255,255,255,.10) 1px, transparent 1px); background-size:22px 22px; }
    .dot-grid-dark{ background-image:radial-gradient(rgba(20,20,20,.06) 1px, transparent 1px); background-size:22px 22px; }

    .hero-bg{
        background-color:var(--night);
        background-image:
            url("assets/leaf-bg.svg"),
            linear-gradient(150deg,var(--night) 0%,var(--night-2) 55%,#1E5C36 100%);
        background-position:right top, 0 0;
        background-size:520px auto, cover;
        background-repeat:no-repeat, no-repeat;
        position:relative; overflow:hidden;
    }
    .glow-a{ position:absolute; z-index:0; width:520px; height:520px; border-radius:999px; top:-200px; right:-160px;
        background:radial-gradient(circle, rgba(224,112,12,.35), transparent 70%); filter:blur(10px); }
    .glow-b{ position:absolute; z-index:0; width:560px; height:560px; border-radius:999px; bottom:-260px; left:-180px;
        background:radial-gradient(circle, rgba(111,214,154,.22), transparent 70%); filter:blur(10px); }

    .tricolor-rule{ height:4px; background:linear-gradient(90deg,var(--saffron) 0%, #F0A44F 45%, #7FBF8E 55%, var(--green) 100%); }

    .btn-primary{ background:linear-gradient(100deg,var(--saffron) 0%, var(--saffron-d) 100%); color:#fff; box-shadow:0 14px 28px -14px rgba(224,112,12,.55); transition:transform .15s ease, box-shadow .15s ease; }
    .btn-primary:hover{ transform:translateY(-1px); box-shadow:0 18px 34px -14px rgba(224,112,12,.65); }
    .btn-ghost{ background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.22); color:#fff; transition:background .15s ease; }
    .btn-ghost:hover{ background:rgba(255,255,255,.16); }

    .product-card{ background:#fff; border:1px solid var(--line); border-radius:20px; overflow:hidden;
        box-shadow:0 1px 2px rgba(20,16,10,.05); transition:transform .2s ease, box-shadow .2s ease; }
    .product-card:hover{ transform:translateY(-4px); box-shadow:0 22px 40px -22px rgba(14,58,42,.28); }
    .product-cat-badge{ position:absolute; top:12px; left:12px; }

    .feature-card{ background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:0 1px 2px rgba(20,16,10,.04); }
    .feature-chip{ width:46px; height:46px; border-radius:13px; display:flex; align-items:center; justify-content:center; }

    .filter-btn{ border:1px solid var(--line); background:#fff; color:var(--ink-2); transition:all .15s ease; }
    .filter-btn.active, .filter-btn:hover{ background:linear-gradient(90deg,var(--saffron),var(--green)); color:#fff; border-color:transparent; }

    .nav-link{ position:relative; color:var(--ink-2); font-weight:600; font-size:14px; }
    .nav-link:hover{ color:var(--ink); }

    .franchise-cta{ background:linear-gradient(120deg,var(--night) 0%, var(--night-2) 55%, #1E5C36 100%); position:relative; overflow:hidden; }

    .hero-slide-wrap{ position:relative; aspect-ratio:4/5; }
    .hero-slide{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:0; transition:opacity 1.1s ease; }
    .hero-slide.active{ opacity:1; }

    #mobileNav{ transition:max-height .25s ease; overflow:hidden; }
</style>
</head>
<body class="overflow-x-hidden">

<?php include __DIR__ . '/includes/preloader.php'; ?>

<!-- ============ NAV ============ -->
<header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-[#ECE7DD]">
    <div class="max-w-7xl mx-auto px-5 sm:px-6 h-[76px] flex items-center justify-between">
        <a href="#top" class="flex items-center gap-2 shrink-0">
            <img src="assets/electrinc-logo.png" alt="ELECTRINC" class="h-8 w-auto object-contain" style="filter:brightness(0) saturate(100%);">
        </a>
        <nav class="hidden lg:flex items-center gap-8">
            <a href="#top" class="nav-link">Home</a>
            <a href="#products" class="nav-link">Products</a>
            <a href="#why-us" class="nav-link">Why ELECTRINC</a>
            <a href="#franchise" class="nav-link">Franchise</a>
            <a href="#contact" class="nav-link">Contact</a>
        </nav>
        <div class="hidden lg:flex items-center gap-3">
            <a href="member/login.php" class="text-[13px] font-semibold px-4 py-2.5 rounded-lg border border-[#ECE7DD] hover:bg-[#FBF8F2] transition">Member Login</a>
            <a href="franchise/index.php" class="text-[13px] font-semibold px-4 py-2.5 rounded-lg text-white btn-primary">Franchise Login</a>
        </div>
        <button type="button" onclick="document.getElementById('mobileNav').classList.toggle('max-h-0'); document.getElementById('mobileNav').classList.toggle('max-h-[320px]');" class="lg:hidden h-10 w-10 flex items-center justify-center rounded-lg border border-[#ECE7DD]">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
    </div>
    <div id="mobileNav" class="lg:hidden max-h-0 border-t border-[#ECE7DD] bg-white">
        <div class="px-5 py-4 flex flex-col gap-1 text-[14px] font-semibold">
            <a href="#top" class="py-2.5">Home</a>
            <a href="#products" class="py-2.5">Products</a>
            <a href="#why-us" class="py-2.5">Why ELECTRINC</a>
            <a href="#franchise" class="py-2.5">Franchise</a>
            <a href="#contact" class="py-2.5">Contact</a>
            <div class="flex gap-2 mt-2 pb-1">
                <a href="member/login.php" class="flex-1 text-center text-[13px] font-semibold px-4 py-2.5 rounded-lg border border-[#ECE7DD]">Member Login</a>
                <a href="franchise/index.php" class="flex-1 text-center text-[13px] font-semibold px-4 py-2.5 rounded-lg text-white btn-primary">Franchise Login</a>
            </div>
        </div>
    </div>
</header>
<div class="tricolor-rule"></div>

<!-- ============ HERO ============ -->
<section id="top" class="hero-bg dot-grid">
    <svg class="route-line" width="420" height="420" style="position:absolute;z-index:0;opacity:.35;top:10%;left:4%;" viewBox="0 0 360 360" fill="none">
        <path d="M10 300 C 90 300, 90 200, 170 200 S 250 100, 350 60" stroke="url(#heroRoute)" stroke-width="2" stroke-dasharray="2 10" stroke-linecap="round"/>
        <defs><linearGradient id="heroRoute" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#E0700C"/><stop offset="1" stop-color="#6FD69A"/></linearGradient></defs>
    </svg>
    <div class="glow-a"></div>
    <div class="glow-b"></div>
    <div class="relative z-10 max-w-7xl mx-auto px-5 sm:px-6 pt-8 sm:pt-10 pb-16 sm:pb-20 grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-12 items-center">
        <div class="flex flex-col items-center text-center lg:items-start lg:text-left">
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-[.12em] text-white" style="background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.2);">
                <i data-lucide="leaf" class="w-3.5 h-3.5" style="color:#F0A44F"></i> A venture of Incredible Group of Companies
            </span>
            <h1 class="mt-5 text-[32px] sm:text-[44px] lg:text-[50px] font-extrabold leading-[1.08] text-white">
                Drive the Future.<br>Drive <span style="background:linear-gradient(90deg,#F0A44F,#7FBF8E);-webkit-background-clip:text;background-clip:text;color:transparent;">Electric</span>.
            </h1>
            <p class="mt-4 text-[15px] sm:text-[17px] leading-relaxed max-w-lg" style="color:rgba(255,255,255,.75)">
                ELECTRINC builds premium electric rickshaws, scooters and bikes — engineered for range, backed by warranty, and sold through a pan-India franchise network.
            </p>
            <div class="mt-6 flex flex-wrap justify-center lg:justify-start gap-3">
                <a href="#products" class="btn-primary px-6 py-3.5 rounded-xl text-[14px] font-bold inline-flex items-center gap-2">
                    Explore Products <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
                <a href="#franchise" class="btn-ghost px-6 py-3.5 rounded-xl text-[14px] font-bold inline-flex items-center gap-2">
                    <i data-lucide="handshake" class="w-4 h-4"></i> Become a Franchise Partner
                </a>
            </div>
            <div class="mt-8 flex flex-wrap justify-center lg:justify-start gap-x-8 gap-y-3">
                <?php foreach ([['bolt', 'Lithium-Ion Battery'], ['shield-check', 'Up to 36 Months Warranty'], ['map-pin', 'Pan-India Network']] as $b): ?>
                    <div class="flex items-center gap-2 text-[13px] font-semibold text-white/85">
                        <i data-lucide="<?php echo $b[0]; ?>" class="w-4 h-4" style="color:#F0A44F"></i> <?php echo $b[1]; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="relative">
            <div class="hero-slide-wrap rounded-[28px] overflow-hidden border border-white/10 shadow-2xl" style="box-shadow:0 40px 80px -30px rgba(0,0,0,.6)">
                <?php
                $heroSlides = [
                    ['img' => 'evelocy-m2.jpeg',     'name' => 'E-Velocy M2',       'range' => '125–150 KM+'],
                    ['img' => 'mania-zxi.jpeg',      'name' => 'Electer Mania ZXI', 'range' => '135–150 KM+'],
                    ['img' => 'bike-v6.jpeg',        'name' => 'Bike V6 2.0',       'range' => '120 KM+'],
                    ['img' => 'active-chetaak.jpeg', 'name' => 'Active Chetaak',    'range' => '55–125 KM+'],
                    ['img' => 'auto-l3.jpeg',        'name' => 'Auto L-3',          'range' => '90–100 KM+'],
                    ['img' => 'erikshaw-solar.jpeg', 'name' => 'E-Rickshaw Solar',  'range' => '100 KM+'],
                ];
                foreach ($heroSlides as $i => $s): ?>
                    <img src="assets/ev-products/<?php echo e($s['img']); ?>" alt="<?php echo e($s['name']); ?>"
                         class="hero-slide<?php echo $i === 0 ? ' active' : ''; ?>"
                         data-name="<?php echo e($s['name']); ?>" data-range="<?php echo e($s['range']); ?>">
                <?php endforeach; ?>
            </div>
            <div class="absolute -bottom-6 -left-6 sm:-left-10 bg-white rounded-2xl px-5 py-4 shadow-xl hidden sm:block">
                <p class="text-[10px] uppercase tracking-wide font-bold" style="color:var(--ink-2)" id="heroSlideName">Flagship Range</p>
                <p class="text-[20px] font-extrabold" style="color:var(--green)" id="heroSlideRange">125–150 KM+</p>
            </div>
        </div>
    </div>
</section>

<!-- ============ PRODUCTS ============ -->
<section id="products" class="max-w-7xl mx-auto px-5 sm:px-6 py-16 sm:py-20">
    <div class="text-center max-w-2xl mx-auto">
        <span class="text-[12px] font-bold uppercase tracking-[.12em]" style="color:var(--saffron-d)">Our Line-Up</span>
        <h2 class="mt-3 text-[28px] sm:text-[36px] font-extrabold" style="color:var(--ink)">Electric vehicles for every need</h2>
        <p class="mt-3 text-[14px] sm:text-[15px]" style="color:var(--ink-2)">From passenger rickshaws to sports bikes — every model runs on lithium-ion technology with real-world tested range.</p>
    </div>

    <div class="mt-9 flex flex-wrap justify-center gap-2.5" id="filterBar">
        <button type="button" class="filter-btn active px-5 py-2.5 rounded-full text-[13px] font-bold" data-filter="all">All Products</button>
        <?php foreach ($catLabels as $key => $label): ?>
            <button type="button" class="filter-btn px-5 py-2.5 rounded-full text-[13px] font-bold" data-filter="<?php echo e($key); ?>"><?php echo e($label); ?></button>
        <?php endforeach; ?>
    </div>

    <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" id="productGrid">
        <?php foreach ($products as $p): ?>
            <div class="product-card" data-cat="<?php echo e($p['cat']); ?>">
                <div class="relative bg-white" style="aspect-ratio:604/724;">
                    <img src="assets/ev-products/<?php echo e($p['img']); ?>" alt="<?php echo e($p['name']); ?>" class="w-full h-full object-contain">
                    <span class="product-cat-badge text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full text-white" style="background:rgba(10,31,22,.75);backdrop-filter:blur(2px);">
                        <?php echo e($catLabels[$p['cat']]); ?>
                    </span>
                </div>
                <div class="p-5">
                    <h3 class="text-[17px] font-extrabold" style="color:var(--ink)"><?php echo e($p['name']); ?></h3>
                    <p class="text-[12px] font-semibold mt-0.5" style="color:var(--saffron-d)"><?php echo e($p['tag']); ?></p>
                    <div class="mt-4 space-y-2 text-[12.5px]">
                        <div class="flex items-center justify-between border-t border-dashed border-[#ECE7DD] pt-2">
                            <span class="flex items-center gap-1.5" style="color:var(--ink-2)"><i data-lucide="gauge" class="w-3.5 h-3.5"></i> Range</span>
                            <span class="font-bold" style="color:var(--green)"><?php echo e($p['range']); ?></span>
                        </div>
                        <div class="flex items-center justify-between border-t border-dashed border-[#ECE7DD] pt-2">
                            <span class="flex items-center gap-1.5" style="color:var(--ink-2)"><i data-lucide="battery-charging" class="w-3.5 h-3.5"></i> Battery</span>
                            <span class="font-semibold text-right" style="color:var(--ink)"><?php echo e($p['battery']); ?></span>
                        </div>
                        <div class="flex items-center justify-between border-t border-dashed border-[#ECE7DD] pt-2">
                            <span class="flex items-center gap-1.5" style="color:var(--ink-2)"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Warranty</span>
                            <span class="font-semibold" style="color:var(--ink)"><?php echo e($p['warranty']); ?></span>
                        </div>
                    </div>
                    <a href="#contact" class="mt-5 w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-lg text-[13px] font-bold text-white btn-primary">
                        Enquire Now <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ WHY US ============ -->
<section id="why-us" class="py-16 sm:py-20" style="background:#fff; border-top:1px solid var(--line); border-bottom:1px solid var(--line);">
    <div class="max-w-7xl mx-auto px-5 sm:px-6">
        <div class="text-center max-w-2xl mx-auto">
            <span class="text-[12px] font-bold uppercase tracking-[.12em]" style="color:var(--green-d)">Why ELECTRINC</span>
            <h2 class="mt-3 text-[28px] sm:text-[36px] font-extrabold" style="color:var(--ink)">Built for range. Backed by trust.</h2>
        </div>
        <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php
            $features = [
                ['icon' => 'battery-full',  'tone' => 'saffron', 'title' => 'Lithium-Ion Technology',  'body' => 'Every model runs on lithium-ion battery packs for faster charging and a longer lifespan than lead-acid.'],
                ['icon' => 'shield-check',  'tone' => 'green',   'title' => 'Up to 36-Month Warranty', 'body' => 'Industry-leading battery and vehicle warranty on our flagship range, so you ride with confidence.'],
                ['icon' => 'sun',           'tone' => 'saffron', 'title' => 'Solar-Ready Variants',    'body' => 'Rooftop solar options on our E-Rickshaw and E-Loader models cut charging costs further.'],
                ['icon' => 'map-pinned',    'tone' => 'green',   'title' => 'Pan-India Franchise Network', 'body' => 'A growing network of State, City and Mobile franchise partners bringing service closer to you.'],
                ['icon' => 'wrench',        'tone' => 'saffron', 'title' => 'After-Sales Support',     'body' => 'Genuine spares and trained service support through every franchise showroom.'],
                ['icon' => 'indian-rupee',  'tone' => 'green',   'title' => 'Franchise Income Model',  'body' => 'Transparent turnover and sponsor income plans for every franchise partner — fully tracked, always visible.'],
            ];
            foreach ($features as $f):
                $soft = $f['tone'] === 'green' ? 'var(--green-soft)' : 'var(--saffron-soft)';
                $col  = $f['tone'] === 'green' ? 'var(--green)' : 'var(--saffron)';
            ?>
                <div class="feature-card p-6">
                    <span class="feature-chip" style="background:<?php echo $soft; ?>;color:<?php echo $col; ?>">
                        <i data-lucide="<?php echo e($f['icon']); ?>" class="w-5 h-5"></i>
                    </span>
                    <h3 class="mt-4 text-[15.5px] font-extrabold" style="color:var(--ink)"><?php echo e($f['title']); ?></h3>
                    <p class="mt-1.5 text-[13px] leading-relaxed" style="color:var(--ink-2)"><?php echo e($f['body']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ FRANCHISE CTA ============ -->
<section id="franchise" class="franchise-cta dot-grid py-16 sm:py-20">
    <div class="glow-a" style="top:-160px;right:-120px;"></div>
    <div class="glow-b" style="bottom:-200px;left:-140px;"></div>
    <div class="relative z-10 max-w-5xl mx-auto px-5 sm:px-6 text-center">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-[.12em] text-white" style="background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.2);">
            <i data-lucide="handshake" class="w-3.5 h-3.5" style="color:#F0A44F"></i> Franchise Opportunity
        </span>
        <h2 class="mt-5 text-[28px] sm:text-[38px] font-extrabold text-white leading-tight">Own an ELECTRINC showroom.<br>Run a business that pays back.</h2>
        <p class="mt-4 text-[14px] sm:text-[15px] max-w-xl mx-auto" style="color:rgba(255,255,255,.75)">
            Get a fully tracked franchise panel — inventory, billing, customers, and income reports — from day one. State, City and Mobile franchise tiers available.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="franchise/index.php" class="btn-primary px-7 py-3.5 rounded-xl text-[14px] font-bold inline-flex items-center gap-2">
                Franchise Login <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
            <a href="#contact" class="btn-ghost px-7 py-3.5 rounded-xl text-[14px] font-bold">Talk to Our Team</a>
        </div>
    </div>
</section>

<!-- ============ CONTACT / FOOTER ============ -->
<footer id="contact" class="pt-16 pb-8" style="background:var(--night); color:rgba(255,255,255,.7);">
    <div class="max-w-7xl mx-auto px-5 sm:px-6">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-10">
            <div class="lg:col-span-2">
                <img src="assets/electrinc-logo.png" alt="ELECTRINC" class="h-9 w-auto object-contain">
                <p class="mt-4 text-[13px] leading-relaxed max-w-sm">A venture of Incredible Group of Companies. Premium electric rickshaws, scooters and bikes — sold and serviced through our pan-India franchise network.</p>
                <div class="mt-5 flex items-center gap-3 text-[13px] font-semibold text-white">
                    <span class="h-9 w-9 rounded-full flex items-center justify-center" style="background:rgba(255,255,255,.08)"><i data-lucide="leaf" class="w-4 h-4" style="color:#F0A44F"></i></span>
                    Clean Energy. Brighter Business.
                </div>
            </div>
            <div>
                <p class="text-[12px] font-bold uppercase tracking-wide text-white">Quick Links</p>
                <div class="mt-4 flex flex-col gap-2.5 text-[13px]">
                    <a href="#products" class="hover:text-white transition">Products</a>
                    <a href="#why-us" class="hover:text-white transition">Why ELECTRINC</a>
                    <a href="#franchise" class="hover:text-white transition">Franchise Opportunity</a>
                    <a href="member/login.php" class="hover:text-white transition">Member Login</a>
                    <a href="franchise/index.php" class="hover:text-white transition">Franchise Login</a>
                    <a href="admin/login.php" class="hover:text-white transition">Admin Login</a>
                </div>
            </div>
            <div>
                <p class="text-[12px] font-bold uppercase tracking-wide text-white">Get in Touch</p>
                <div class="mt-4 flex flex-col gap-3 text-[13px]">
                    <span class="flex items-center gap-2.5"><i data-lucide="phone" class="w-4 h-4" style="color:#F0A44F"></i> +91 00000 00000</span>
                    <span class="flex items-center gap-2.5"><i data-lucide="mail" class="w-4 h-4" style="color:#F0A44F"></i> info@electrinc.in</span>
                    <span class="flex items-start gap-2.5"><i data-lucide="map-pin" class="w-4 h-4 mt-0.5 shrink-0" style="color:#F0A44F"></i> Pan-India Franchise Network</span>
                </div>
            </div>
        </div>
        <div class="mt-12 pt-6 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3 text-[12px]">
            <p>&copy; <?php echo date('Y'); ?> ELECTRINC — a venture of Incredible Group of Companies. All rights reserved.</p>
            <p>Built with clean energy in mind.</p>
        </div>
    </div>
</footer>

<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();

    /* hero image carousel — slow auto-rotate with crossfade */
    (function () {
        const slides = document.querySelectorAll('.hero-slide');
        const nameEl = document.getElementById('heroSlideName');
        const rangeEl = document.getElementById('heroSlideRange');
        if (!slides.length) return;
        let idx = 0;
        setInterval(() => {
            slides[idx].classList.remove('active');
            idx = (idx + 1) % slides.length;
            slides[idx].classList.add('active');
            if (nameEl) nameEl.textContent = slides[idx].dataset.name;
            if (rangeEl) rangeEl.textContent = slides[idx].dataset.range;
        }, 4000);
    })();

    document.querySelectorAll('#filterBar .filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#filterBar .filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const f = btn.dataset.filter;
            document.querySelectorAll('#productGrid > div').forEach(card => {
                card.style.display = (f === 'all' || card.dataset.cat === f) ? '' : 'none';
            });
        });
    });
</script>
</body>
</html>
