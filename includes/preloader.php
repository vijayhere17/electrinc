<!-- ELECTRINC premium loading screen — letter-by-letter reveal of the REAL logo asset (sliced via
     exact pixel boundaries measured off assets/electrinc-logo.png, no redrawn/recreated typography),
     then a chrome shine sweep + fast flash + fade out. Total runtime ~1.85s. -->
<style>
    :root{ --pl-logo-w: 360px; }
    @media (max-width: 520px){ :root{ --pl-logo-w: 230px; } }

    #electrincPreloader{
        position:fixed; inset:0; z-index:99999;
        display:flex; align-items:center; justify-content:center;
        background:linear-gradient(150deg, #0A1F16 0%, #123B27 100%);
    }
    #electrincPreloader.pl-fade-out{ animation:plFadeOut .42s ease forwards; }
    @keyframes plFadeOut{ to{ opacity:0; visibility:hidden; } }

    .pl-logo-stage{ position:relative; }
    .pl-logo-row{
        display:flex; width:var(--pl-logo-w); height:calc(var(--pl-logo-w) * .1475);
    }
    .pl-letter{
        flex-shrink:0; height:100%;
        background-image:url("assets/electrinc-logo.png");
        background-repeat:no-repeat;
        background-size:var(--pl-logo-w) auto;
        opacity:0;
        transform:translate3d(-6px, 8px, 0);
        animation:plLetterIn 230ms cubic-bezier(.2,.7,.3,1) forwards;
        animation-delay:calc(var(--i) * 85ms);
    }
    @keyframes plLetterIn{ to{ opacity:1; transform:translate3d(0,0,0); } }

    /* exact pixel-measured slice widths / positions, as fractions of --pl-logo-w (source image 2000x295) */
    .pl-letter:nth-child(1){ width:calc(var(--pl-logo-w) * .1435); background-position:0 0; }
    .pl-letter:nth-child(2){ width:calc(var(--pl-logo-w) * .1015); background-position:calc(var(--pl-logo-w) * -.1435)  0; }
    .pl-letter:nth-child(3){ width:calc(var(--pl-logo-w) * .1070); background-position:calc(var(--pl-logo-w) * -.2450)  0; }
    .pl-letter:nth-child(4){ width:calc(var(--pl-logo-w) * .11525); background-position:calc(var(--pl-logo-w) * -.3520) 0; }
    .pl-letter:nth-child(5){ width:calc(var(--pl-logo-w) * .10825); background-position:calc(var(--pl-logo-w) * -.46725) 0; }
    .pl-letter:nth-child(6){ width:calc(var(--pl-logo-w) * .1165); background-position:calc(var(--pl-logo-w) * -.5755)  0; }
    .pl-letter:nth-child(7){ width:calc(var(--pl-logo-w) * .0390); background-position:calc(var(--pl-logo-w) * -.6920)  0; }
    .pl-letter:nth-child(8){ width:calc(var(--pl-logo-w) * .1190); background-position:calc(var(--pl-logo-w) * -.7310)  0; }
    .pl-letter:nth-child(9){ width:calc(var(--pl-logo-w) * .1500); background-position:calc(var(--pl-logo-w) * -.8500)  0; }

    /* chrome shine sweep — masked to the exact logo pixels so it only lights up the metal, not the gaps */
    .pl-shine{
        position:absolute; inset:0;
        background-image:linear-gradient(100deg, transparent 42%, rgba(255,255,255,.95) 50%, transparent 58%);
        background-size:300% 100%;
        background-repeat:no-repeat;
        background-position:-120% 0;
        -webkit-mask-image:url("assets/electrinc-logo.png");
        mask-image:url("assets/electrinc-logo.png");
        -webkit-mask-size:var(--pl-logo-w) auto;
        mask-size:var(--pl-logo-w) auto;
        -webkit-mask-repeat:no-repeat;
        mask-repeat:no-repeat;
        opacity:0;
        pointer-events:none;
    }
    .pl-shine-active .pl-shine{ animation:plShine 350ms ease-in-out forwards; }
    @keyframes plShine{
        0%{ background-position:-120% 0; opacity:0; }
        10%{ opacity:1; }
        88%{ opacity:1; }
        100%{ background-position:120% 0; opacity:0; }
    }

    /* fast finishing flash */
    .pl-flash-active .pl-logo-row{ animation:plFlash 150ms ease-out forwards; }
    @keyframes plFlash{
        0%{ transform:translate3d(0,0,0); filter:brightness(1); }
        35%{ transform:translate3d(-3px,0,0); filter:brightness(1.4); }
        65%{ transform:translate3d(2px,0,0); filter:brightness(1.1); }
        100%{ transform:translate3d(0,0,0); filter:brightness(1); }
    }
</style>

<div id="electrincPreloader" aria-hidden="true">
    <div class="pl-logo-stage">
        <div class="pl-logo-row">
            <span class="pl-letter" style="--i:0"></span>
            <span class="pl-letter" style="--i:1"></span>
            <span class="pl-letter" style="--i:2"></span>
            <span class="pl-letter" style="--i:3"></span>
            <span class="pl-letter" style="--i:4"></span>
            <span class="pl-letter" style="--i:5"></span>
            <span class="pl-letter" style="--i:6"></span>
            <span class="pl-letter" style="--i:7"></span>
            <span class="pl-letter" style="--i:8"></span>
        </div>
        <div class="pl-shine"></div>
    </div>
</div>

<script>
(function(){
    var overlay = document.getElementById('electrincPreloader');
    if (!overlay) return;
    var htmlEl = document.documentElement;
    htmlEl.style.overflow = 'hidden';

    var T_REVEAL = 950;   /* 8 * 85ms stagger + 230ms last-letter duration, plus a very short pause */
    var T_SHINE  = 350;
    var T_FLASH  = 150;
    var T_FADE   = 420;

    setTimeout(function(){ overlay.classList.add('pl-shine-active'); }, T_REVEAL);
    setTimeout(function(){ overlay.classList.add('pl-flash-active'); }, T_REVEAL + T_SHINE);
    setTimeout(function(){ overlay.classList.add('pl-fade-out'); }, T_REVEAL + T_SHINE + T_FLASH);
    setTimeout(function(){
        overlay.remove();
        htmlEl.style.overflow = '';
    }, T_REVEAL + T_SHINE + T_FLASH + T_FADE);
})();
</script>
