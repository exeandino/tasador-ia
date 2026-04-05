<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>TasadorIA — Tasación inteligente de propiedades</title>
<meta name="description" content="Tasación online de propiedades con inteligencia artificial. Gratis, instantánea y confiable.">
<!-- PWA -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0d0f14">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="TasadorIA">
<link rel="apple-touch-icon" href="icons/icon-192.png">
<link rel="apple-touch-icon" sizes="152x152" href="icons/icon-152.png">
<link rel="apple-touch-icon" sizes="144x144" href="icons/icon-144.png">
<link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="icons/icon-512.png">
<!-- OG/Social -->
<meta property="og:title" content="TasadorIA — Valuación de propiedades con IA">
<meta property="og:description" content="Tasación online gratuita con inteligencia artificial. Argentina.">
<meta property="og:type" content="website">
<meta property="og:image" content="icons/icon-512.png">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
:root{--bg:#0d0f14;--bg2:#141720;--bg3:#1c2030;--card:#1e2235;--border:#2a2f45;--gold:#c9a84c;--gold2:#f0cc7a;--text:#e8e8f0;--muted:#7a7a9a;--green:#00c896;--red:#ff4f6e;--blue:#4a8ff7;--r:14px;--shadow:0 24px 60px rgba(0,0,0,.6)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden}
::placeholder{color:var(--muted)}
input,select,textarea,button{font-family:inherit}
.bg-grid{position:fixed;inset:0;background-image:linear-gradient(rgba(201,168,76,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(201,168,76,.03) 1px,transparent 1px);background-size:60px 60px;pointer-events:none;z-index:0}
.bg-glow{position:fixed;top:-20%;right:-10%;width:60vw;height:60vw;background:radial-gradient(circle,rgba(201,168,76,.06) 0%,transparent 70%);pointer-events:none;z-index:0}
.wrap{position:relative;z-index:1;max-width:880px;margin:0 auto;padding:20px}
.header{text-align:center;padding:36px 20px 24px}
.logo{font-family:'Playfair Display',serif;font-size:30px;color:var(--gold);letter-spacing:-1px}
.logo span{font-style:italic;color:var(--text)}
.tagline{font-size:13px;color:var(--muted);margin-top:5px;letter-spacing:1px;text-transform:uppercase}
.ai-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.3);color:var(--gold);padding:5px 14px;border-radius:20px;font-size:12px;font-weight:500;margin-top:10px}
.ai-dot{width:6px;height:6px;background:var(--gold);border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(1.3)}}
.progress-wrap{display:flex;align-items:center;margin:0 0 28px;background:var(--bg2);border-radius:100px;padding:6px;border:1px solid var(--border);overflow-x:auto}
.step-dot{flex:1;min-width:34px;display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer;padding:6px 2px;border-radius:100px;transition:background .2s}
.step-dot.active{background:rgba(201,168,76,.15)}
.step-num{width:26px;height:26px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--muted);transition:all .3s}
.step-dot.active .step-num{border-color:var(--gold);color:var(--gold);background:rgba(201,168,76,.1)}
.step-dot.done .step-num{background:var(--gold);border-color:var(--gold);color:#000}
.step-label{font-size:9px;color:var(--muted);text-align:center;display:none;white-space:nowrap}
@media(min-width:560px){.step-label{display:block}}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:28px;box-shadow:var(--shadow);display:none;animation:fadeUp .3s ease}
.card.active{display:block}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.step-title{font-family:'Playfair Display',serif;font-size:22px;margin-bottom:4px}
.step-sub{font-size:13px;color:var(--muted);margin-bottom:24px;line-height:1.6}
.field{margin-bottom:18px}
.field label{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:7px}
.field input,.field select{width:100%;padding:12px 15px;background:var(--bg3);border:1.5px solid var(--border);border-radius:10px;color:var(--text);font-size:14px;outline:none;transition:border-color .2s}
.field input:focus,.field select:focus{border-color:var(--gold)}
.field select option{background:var(--bg3)}
.field input[type=number]{text-align:center;font-size:18px;font-weight:700}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.grid4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px}
@media(max-width:600px){.grid2,.grid3,.grid4{grid-template-columns:1fr 1fr}}
@media(max-width:400px){.grid2,.grid3,.grid4{grid-template-columns:1fr}}
#map{height:210px;border-radius:10px;margin-top:8px;
  border:1.5px solid rgba(0,210,255,.4);
  box-shadow:0 0 20px rgba(0,210,255,.18),0 0 40px rgba(0,210,255,.08);
  background:#060810}
#map .leaflet-tile-pane{filter:invert(1) hue-rotate(180deg) saturate(3.5) brightness(1.0) contrast(1.45)}
#map .leaflet-control-zoom a{background:#0d0f14!important;color:#0af!important;border-color:#0af3!important}
#map .leaflet-control-attribution{background:rgba(0,0,0,.75)!important;color:#444!important;font-size:9px}
#map .leaflet-control-attribution a{color:#0af!important}
.map-hint{font-size:11px;color:var(--muted);margin-top:5px}
/* POI markers en mapa */
.map-poi-dot{display:flex;align-items:center;justify-content:center;border-radius:50%;border:2px solid rgba(255,255,255,.35);font-size:13px;transition:transform .15s}
.map-poi-dot:hover{transform:scale(1.2)}
.leaflet-tooltip.map-poi-tip{background:#0d0f14cc;color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px;font-size:11px;padding:3px 8px;box-shadow:0 2px 8px rgba(0,0,0,.6);white-space:nowrap;backdrop-filter:blur(4px)}
/* Tooltip */
.tip{position:relative;display:inline-flex;align-items:center;gap:4px;cursor:help}
.tip .tip-icon{width:15px;height:15px;border-radius:50%;background:rgba(201,168,76,.2);border:1px solid rgba(201,168,76,.4);color:var(--gold);font-size:9px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.tip .tip-box{display:none;position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1a1d2e;border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:12px;color:var(--text);width:220px;z-index:999;line-height:1.5;box-shadow:0 8px 24px rgba(0,0,0,.5)}
.tip .tip-box::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:#1a1d2e}
.tip:hover .tip-box{display:block}
/* Sección avanzada */
.adv-toggle{display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px 14px;background:rgba(201,168,76,.05);border:1px dashed rgba(201,168,76,.25);border-radius:10px;margin-top:16px;font-size:13px;color:var(--muted);user-select:none;transition:all .2s}
.adv-toggle:hover{border-color:rgba(201,168,76,.45);color:var(--gold)}
.adv-toggle .adv-arrow{margin-left:auto;transition:transform .3s;font-size:11px}
.adv-toggle.open .adv-arrow{transform:rotate(180deg)}
.adv-body{display:none;margin-top:12px;padding:14px;background:rgba(201,168,76,.03);border:1px solid var(--border);border-radius:10px}
.adv-body.open{display:block}
/* Deudas */
.deuda-item{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)}
.deuda-item:last-child{border-bottom:none}
.deuda-item label{font-size:13px}
.deuda-item input{width:110px;padding:6px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:13px;text-align:right}
/* Print mejorado */
@media print{
  .adv-toggle,.no-print,.nav,.result-actions,.rating-widget,
  .progress-wrap,.bg-grid,.bg-glow,#embed-modal{display:none!important}
  body{background:white!important;color:#111!important}
  .card{display:none!important}
  #step8{display:block!important;border:none!important;box-shadow:none!important;background:white!important}
  .result-box{border:1px solid #ddd!important;background:#fafafa!important;break-inside:avoid}
  .result-box h4{color:#888!important}
  .mult-row span:last-child[style*="green"]{color:#007a00!important}
  .mult-row span:last-child[style*="red"]{color:#c00!important}
  .print-header{display:flex!important}
  .price-main{color:#c9a84c!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .print-hide-empty{display:none}
}
.geo-badge{display:none;align-items:center;gap:6px;background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.3);color:var(--green);padding:5px 12px;border-radius:20px;font-size:12px;margin-top:7px}
.opt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(108px,1fr));gap:9px;margin-top:7px}
.opt{background:var(--bg3);border:1.5px solid var(--border);border-radius:10px;padding:12px 8px;text-align:center;cursor:pointer;transition:all .2s;user-select:none}
.opt:hover{border-color:var(--gold);background:rgba(201,168,76,.07)}
.opt.selected{border-color:var(--gold);background:rgba(201,168,76,.12);color:var(--gold)}
.opt .icon{font-size:20px;margin-bottom:4px}
.opt .lbl{font-size:11px;font-weight:500;line-height:1.3}
.slider-wrap{margin-top:7px}
input[type=range]{width:100%;-webkit-appearance:none;height:5px;background:var(--border);border-radius:3px;outline:none}
input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;background:var(--gold);border-radius:50%;cursor:pointer}
.slider-val{text-align:center;font-size:20px;font-weight:700;color:var(--gold);margin-top:6px}
.slider-val span{font-size:13px;color:var(--muted);font-weight:400}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;background:var(--bg3);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;user-select:none;transition:border-color .2s;gap:12px}
.toggle-row:hover{border-color:var(--gold)}
.toggle-row.on{border-color:rgba(201,168,76,.5);background:rgba(201,168,76,.07)}
.t-label{font-size:14px;font-weight:500}
.t-desc{font-size:11px;color:var(--muted);margin-top:2px}
.toggle-sw{width:38px;height:22px;background:var(--border);border-radius:11px;position:relative;transition:background .2s;flex-shrink:0}
.toggle-sw::after{content:'';position:absolute;top:3px;left:3px;width:16px;height:16px;background:#fff;border-radius:50%;transition:left .2s}
.toggle-row.on .toggle-sw{background:var(--gold)}
.toggle-row.on .toggle-sw::after{left:19px}
.amen-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:8px}
.amen-check{display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--bg3);border:1.5px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;transition:all .15s}
.amen-check:hover{border-color:var(--gold)}
.amen-check input{accent-color:var(--gold)}
.amen-check.checked{border-color:rgba(201,168,76,.5);background:rgba(201,168,76,.07)}
.photo-drop{border:2px dashed var(--border);border-radius:12px;padding:36px;text-align:center;cursor:pointer;transition:all .2s;position:relative}
.photo-drop:hover,.photo-drop.dragging{border-color:var(--gold);background:rgba(201,168,76,.05)}
.photo-drop input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.photo-drop .icon{font-size:36px;margin-bottom:10px}
.photo-drop p{font-size:13px;color:var(--muted)}
.photo-preview{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.photo-thumb{width:76px;height:57px;object-fit:cover;border-radius:6px;border:1.5px solid var(--border)}
.doc-drop{border:2px dashed var(--border);border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:all .2s;background:rgba(74,143,247,.03)}
.doc-drop:hover,.doc-drop.dragging{border-color:var(--blue);background:rgba(74,143,247,.07)}
.doc-drop p{font-size:13px;color:var(--muted)}
.doc-chip{display:inline-flex;align-items:center;gap:6px;background:var(--bg3);border:1px solid var(--border);border-radius:20px;padding:5px 12px;font-size:12px;max-width:200px}
.doc-chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.doc-chip button{background:none;border:none;color:var(--muted);cursor:pointer;font-size:14px;line-height:1;padding:0;flex-shrink:0}
.doc-chip button:hover{color:var(--red)}
/* ── Informe escritura ── */
.escritura-report{background:var(--bg2);border:1px solid rgba(201,168,76,.3);border-radius:12px;padding:18px;margin-top:10px}
.escritura-report h5{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:var(--gold);margin-bottom:8px;margin-top:14px}
.escritura-report h5:first-child{margin-top:0}
.escritura-report p,.escritura-report li{font-size:13px;color:var(--text);line-height:1.6}
.escritura-report ul{padding-left:18px;margin:4px 0}
.escritura-report .transcripcion{background:var(--bg3);border-radius:8px;padding:12px;font-size:12px;line-height:1.7;color:var(--muted);white-space:pre-wrap;max-height:260px;overflow-y:auto;margin-top:6px}
.escritura-report .badge{display:inline-block;background:rgba(201,168,76,.15);color:var(--gold);border:1px solid rgba(201,168,76,.3);border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600;margin:2px}
.video-thumb{width:72px;height:48px;object-fit:cover;border-radius:5px;border:1.5px solid var(--border)}
.ai-analyzing{background:rgba(74,143,247,.08);border:1px solid rgba(74,143,247,.3);border-radius:10px;padding:14px;margin-top:14px;display:none}
.ai-analyzing.show{display:flex;align-items:center;gap:10px}
.ai-spinner{width:18px;height:18px;border:2px solid rgba(74,143,247,.3);border-top-color:var(--blue);border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0}
@keyframes spin{to{transform:rotate(360deg)}}
.ai-result{background:rgba(0,200,150,.07);border:1px solid rgba(0,200,150,.3);border-radius:10px;padding:14px;margin-top:12px;display:none}
.ai-result.show{display:block}
.nav{display:flex;gap:12px;margin-top:24px;justify-content:space-between;align-items:center}
.btn-prev{padding:12px 22px;background:transparent;border:1.5px solid var(--border);border-radius:10px;color:var(--muted);font-size:14px;transition:all .2s;cursor:pointer}
.btn-prev:hover{border-color:var(--gold);color:var(--gold)}
.btn-next{padding:12px 30px;background:var(--gold);border:none;border-radius:10px;color:#0d0f14;font-size:14px;font-weight:700;transition:background .2s;margin-left:auto;cursor:pointer}
.btn-next:hover{background:var(--gold2)}
.btn-next:disabled{opacity:.5;cursor:not-allowed}
.error-box{background:rgba(255,79,110,.08);border:1px solid rgba(255,79,110,.4);border-radius:10px;padding:14px;margin-top:14px;font-size:13px;color:var(--red);display:none}
.error-box.show{display:block}
.result-hero{text-align:center;padding:14px 0 24px;border-bottom:1px solid var(--border);margin-bottom:22px}
.result-code{font-size:11px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:6px}
.result-zone{font-size:13px;color:var(--gold);margin-bottom:12px}
.price-display{display:flex;align-items:flex-end;justify-content:center;gap:7px;margin:10px 0}
.price-curr{font-size:17px;color:var(--muted);margin-bottom:7px}
.price-main{font-family:'Playfair Display',serif;font-size:48px;color:var(--gold);line-height:1}
.price-range{font-size:13px;color:var(--muted);margin-top:6px}
.price-range strong{color:var(--text)}
.price-ars{font-size:13px;color:var(--muted);margin-top:3px}
.price-ppm2{display:inline-block;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.2);color:var(--gold);padding:4px 13px;border-radius:20px;font-size:13px;margin-top:10px}
.result-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
@media(max-width:600px){.result-grid{grid-template-columns:1fr}}
.result-box{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px}
.result-box h4{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:10px}
.mult-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px}
.mult-row:last-child{border:none}
.mult-factor{font-size:11px;font-weight:600;padding:2px 7px;border-radius:5px}
.mult-up{background:rgba(0,200,150,.15);color:var(--green)}
.mult-dn{background:rgba(255,79,110,.15);color:var(--red)}
.mult-eq{background:rgba(255,255,255,.06);color:var(--muted)}
.confidence-bar{height:5px;background:var(--border);border-radius:3px;margin-top:8px;overflow:hidden}
.confidence-fill{height:100%;background:linear-gradient(90deg,var(--red),var(--gold),var(--green));border-radius:3px}
.poi-item{display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid var(--border)}
.poi-item:last-child{border:none}
.poi-dist{color:var(--muted);font-size:11px}
.comp-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px}
.comp-row:last-child{border:none}
.result-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.btn-print{flex:1;min-width:130px;padding:11px;background:transparent;border:1.5px solid var(--gold);border-radius:10px;color:var(--gold);font-size:13px;font-weight:600;cursor:pointer}
.btn-new{flex:1;min-width:130px;padding:11px;background:var(--gold);border:none;border-radius:10px;color:#0d0f14;font-size:13px;font-weight:700;cursor:pointer}
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}
.modal-bg.open{display:flex}
.modal{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:26px;max-width:540px;width:100%}
.modal h3{font-family:'Playfair Display',serif;font-size:19px;margin-bottom:14px}
.modal pre{background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:13px;font-size:12px;overflow-x:auto;color:var(--gold);line-height:1.7;white-space:pre-wrap;word-break:break-all}
.modal-close{margin-top:14px;padding:10px 20px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--muted);width:100%;cursor:pointer}
.toast{position:fixed;bottom:22px;right:22px;background:var(--card);border:1px solid var(--border);padding:11px 18px;border-radius:10px;font-size:13px;z-index:9999;opacity:0;transform:translateY(10px);transition:all .3s;border-left:3px solid var(--green);max-width:300px}
.toast.show{opacity:1;transform:translateY(0)}
.toast.error{border-left-color:var(--red)}
/* ── Rating de precio ── */
.rating-widget{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;margin-top:4px}
.rating-widget p{font-size:13px;color:var(--muted);margin-bottom:12px}
.rating-btns{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.rating-btn{padding:9px 18px;border-radius:24px;border:1.5px solid var(--border);background:transparent;color:var(--text);font-size:13px;cursor:pointer;transition:all .2s;font-family:inherit}
.rating-btn:hover{border-color:var(--gold);background:rgba(201,168,76,.1)}
.rating-btn.selected{border-color:var(--gold);background:rgba(201,168,76,.15);color:var(--gold);font-weight:600}
.rating-btn:disabled{opacity:.5;cursor:default}
.rating-thanks{font-size:13px;color:var(--green);margin-top:10px;display:none}
/* ── Print ── */
@media print{
  body{background:white!important;color:#111!important;font-size:12pt}
  .bg-grid,.bg-glow,.progress-wrap,.nav,.result-actions,.rating-widget,.no-print{display:none!important}
  .card{border:none!important;box-shadow:none!important;background:white!important;display:block!important;page-break-inside:avoid}
  .price-main{color:#c9a84c!important}
  .result-zone{color:#444!important}
  .wrap{max-width:100%!important;padding:0!important}
  .result-grid{grid-template-columns:1fr 1fr!important}
  .result-box{border:1px solid #ddd!important;background:#fafafa!important;color:#111!important}
  .result-box h4{color:#888!important}
  .mult-row{border-bottom:1px solid #eee!important}
  .print-header{display:flex!important}
  .comp-row{border-bottom:1px solid #eee!important}
  a[href]:after{content:none!important}
}
</style>
</head>
<body>
<div class="bg-grid"></div><div class="bg-glow"></div>
<div class="wrap">
  <!-- Header de impresión (solo visible al imprimir) -->
  <div id="print-header" class="print-header" style="display:none;align-items:center;justify-content:space-between;padding:10px 0 16px;border-bottom:2px solid #c9a84c;margin-bottom:16px">
    <img src="https://anperprimo.com/wp-content/uploads/2025/07/WHITE111.png" alt="ANPR" style="height:42px;filter:invert(1) sepia(1) saturate(2) hue-rotate(5deg)">
    <div style="text-align:right;font-size:11px;color:#888">
      <div style="font-weight:700;font-size:14px;color:#c9a84c">TasadorIA — Informe de Tasación</div>
      <div id="print-date"></div>
    </div>
  </div>

  <div class="header">
    <img src="https://anperprimo.com/wp-content/uploads/2025/07/WHITE111.png" alt="ANPR Primo" style="height:48px;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto" onerror="this.style.display='none'">
    <div class="logo">Tasador<span>IA</span></div>
    <div class="tagline">Valuación inteligente de propiedades · Argentina</div>
    <div class="ai-pill"><span class="ai-dot"></span> IA activada · Análisis visual de fotos</div>
  </div>

  <div class="progress-wrap" id="progress-wrap">
    <div class="step-dot active" onclick="goTo(1)"><div class="step-num">1</div><div class="step-label">Ubicación</div></div>
    <div class="step-dot" onclick="goTo(2)"><div class="step-num">2</div><div class="step-label">Propiedad</div></div>
    <div class="step-dot" onclick="goTo(3)"><div class="step-num">3</div><div class="step-label">Detalles</div></div>
    <div class="step-dot" onclick="goTo(4)"><div class="step-num">4</div><div class="step-label">Estado</div></div>
    <div class="step-dot" onclick="goTo(5)"><div class="step-num">5</div><div class="step-label">Amenities</div></div>
    <div class="step-dot" onclick="goTo(6)"><div class="step-num">6</div><div class="step-label">Fotos IA</div></div>
    <div class="step-dot" onclick="goTo(7)"><div class="step-num">7</div><div class="step-label">Contacto</div></div>
  </div>

  <!-- PASO 1: UBICACIÓN -->
  <div class="card active" id="step1">
    <div class="step-title">¿Dónde está la propiedad?</div>
    <div class="step-sub">La ubicación define el precio base. Escribí la dirección o hacé clic en el mapa.</div>
    <div class="field"><label>Dirección completa</label>
      <input type="text" id="address" placeholder="Ej: San Jerónimo 1841, Santa Fe, Capital" oninput="debouncedGeocode()"></div>
    <div class="grid2">
      <div class="field"><label>Ciudad</label>
        <select id="city_sel" onchange="onCityChange()">
          <option value="santa_fe_capital" selected>Santa Fe Capital</option>
          <option value="buenos_aires">Buenos Aires CABA</option>
          <option value="puerto_madero">Puerto Madero</option>
          <option value="gba_norte">GBA Norte (San Isidro, Olivos…)</option>
          <option value="rosario">Rosario</option>
          <option value="cordoba">Córdoba Capital</option>
        </select></div>
      <div class="field"><label>Zona / Barrio</label>
        <select id="zone_sel"><option value="">Detectar automáticamente</option></select></div>
    </div>
    <div id="map"></div>
    <p class="map-hint">📍 Clic en el mapa o arrastrá el pin para ajustar la posición exacta.</p>
    <div class="geo-badge" id="geo-badge">✓ Ubicación en el mapa</div>
    <input type="hidden" id="lat"><input type="hidden" id="lng">
    <div class="nav"><button class="btn-next" onclick="next(1)">Continuar →</button></div>
  </div>

  <!-- PASO 2: PROPIEDAD -->
  <div class="card" id="step2">
    <div class="step-title">Tipo y superficies</div>
    <div class="step-sub">Tipo de propiedad, operación y superficies en m².</div>
    <div class="field"><label>Tipo de propiedad</label>
      <div class="opt-grid">
        <div class="opt selected" data-field="property_type" data-val="departamento" onclick="selectOpt(this)"><div class="icon">🏢</div><div class="lbl">Departamento</div></div>
        <div class="opt" data-field="property_type" data-val="casa" onclick="selectOpt(this)"><div class="icon">🏠</div><div class="lbl">Casa</div></div>
        <div class="opt" data-field="property_type" data-val="ph" onclick="selectOpt(this)"><div class="icon">🏡</div><div class="lbl">PH</div></div>
        <div class="opt" data-field="property_type" data-val="local" onclick="selectOpt(this)"><div class="icon">🏪</div><div class="lbl">Local</div></div>
        <div class="opt" data-field="property_type" data-val="oficina" onclick="selectOpt(this)"><div class="icon">🏗</div><div class="lbl">Oficina</div></div>
        <div class="opt" data-field="property_type" data-val="terreno" onclick="selectOpt(this)"><div class="icon">📐</div><div class="lbl">Terreno</div></div>
      </div></div>
    <div class="field"><label>Operación</label>
      <div class="opt-grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="opt selected" data-field="operation" data-val="venta" onclick="selectOpt(this)"><div class="icon">🤝</div><div class="lbl">Venta</div></div>
        <div class="opt" data-field="operation" data-val="alquiler" onclick="selectOpt(this)"><div class="icon">🔑</div><div class="lbl">Alquiler</div></div>
        <div class="opt" data-field="operation" data-val="temporal" onclick="selectOpt(this)"><div class="icon">📅</div><div class="lbl">Temporal</div></div>
      </div></div>
    <div class="grid2">
      <div class="field"><label>Superficie cubierta (m²)</label>
        <div class="slider-wrap">
          <input type="range" id="covered_area" min="20" max="600" value="65" step="5" oninput="updateSlider('covered_area','m²')">
          <div class="slider-val" id="covered_area_val">65 <span>m²</span></div>
        </div></div>
      <div class="field"><label>Superficie total (m²)</label>
        <div class="slider-wrap">
          <input type="range" id="total_area" min="20" max="800" value="65" step="5" oninput="updateSlider('total_area','m²')">
          <div class="slider-val" id="total_area_val">65 <span>m²</span></div>
        </div></div>
    </div>
    <div class="nav">
      <button class="btn-prev" onclick="prev(2)">← Atrás</button>
      <button class="btn-next" onclick="next(2)">Continuar →</button>
    </div>
  </div>

  <!-- PASO 3: CARACTERÍSTICAS DETALLADAS -->
  <div class="card" id="step3">
    <div class="step-title">Características</div>
    <div class="step-sub">Completá los detalles. Cuanto más preciso, mejor la valuación.</div>
    <div class="grid4">
      <div class="field"><label>Ambientes</label>
        <div class="slider-wrap">
          <input type="range" id="ambientes" min="0" max="10" value="3" step="1" oninput="updateSlider('ambientes','')">
          <div class="slider-val" id="ambientes_val">3</div>
        </div></div>
      <div class="field"><label>Dormitorios</label>
        <div class="slider-wrap">
          <input type="range" id="bedrooms" min="0" max="8" value="2" step="1" oninput="updateSlider('bedrooms','')">
          <div class="slider-val" id="bedrooms_val">2</div>
        </div></div>
      <div class="field"><label>Baños</label>
        <div class="slider-wrap">
          <input type="range" id="bathrooms" min="0" max="6" value="1" step="1" oninput="updateSlider('bathrooms','')">
          <div class="slider-val" id="bathrooms_val">1</div>
        </div></div>
      <div class="field"><label>Cocheras</label>
        <div class="slider-wrap">
          <input type="range" id="garages" min="0" max="5" value="0" step="1" oninput="updateSlider('garages','')">
          <div class="slider-val" id="garages_val">0</div>
        </div></div>
    </div>
    <div class="grid2">
      <div class="field"><label>Piso / Nivel (0 = PB)</label>
        <input type="number" id="floor" value="1" min="0" max="80"></div>
      <div class="field" style="display:flex;flex-direction:column;justify-content:center">
        <label>Ascensor</label>
        <div class="toggle-row" id="toggle-elevator" onclick="toggleSwitch('has_elevator','toggle-elevator')">
          <div><div class="t-label">¿Tiene ascensor?</div><div class="t-desc">Afecta valor según el piso</div></div>
          <div class="toggle-sw"></div>
        </div></div>
    </div>
    <div class="grid2">
      <div class="field"><label>Vista principal</label>
        <div class="opt-grid" style="grid-template-columns:repeat(2,1fr)">
          <div class="opt" data-field="view" data-val="rio_mar" onclick="selectOpt(this)"><div class="icon">🌊</div><div class="lbl">Río / Mar</div></div>
          <div class="opt" data-field="view" data-val="parque" onclick="selectOpt(this)"><div class="icon">🌳</div><div class="lbl">Parque</div></div>
          <div class="opt selected" data-field="view" data-val="exterior" onclick="selectOpt(this)"><div class="icon">🏙</div><div class="lbl">Exterior</div></div>
          <div class="opt" data-field="view" data-val="interno" onclick="selectOpt(this)"><div class="icon">🏠</div><div class="lbl">Interno</div></div>
        </div></div>
      <div class="field"><label>Orientación</label>
        <div class="opt-grid" style="grid-template-columns:repeat(3,1fr)">
          <div class="opt selected" data-field="orientation" data-val="norte" onclick="selectOpt(this)"><div class="icon">⬆️</div><div class="lbl">Norte</div></div>
          <div class="opt" data-field="orientation" data-val="noreste" onclick="selectOpt(this)"><div class="icon">↗️</div><div class="lbl">Noreste</div></div>
          <div class="opt" data-field="orientation" data-val="este" onclick="selectOpt(this)"><div class="icon">➡️</div><div class="lbl">Este</div></div>
          <div class="opt" data-field="orientation" data-val="oeste" onclick="selectOpt(this)"><div class="icon">⬅️</div><div class="lbl">Oeste</div></div>
          <div class="opt" data-field="orientation" data-val="noroeste" onclick="selectOpt(this)"><div class="icon">↖️</div><div class="lbl">Noroeste</div></div>
          <div class="opt" data-field="orientation" data-val="sur" onclick="selectOpt(this)"><div class="icon">⬇️</div><div class="lbl">Sur</div></div>
        </div></div>
    </div>
    <div class="field"><label>Luminosidad natural</label>
      <div class="opt-grid" style="grid-template-columns:repeat(5,1fr)">
        <div class="opt" data-field="luminosity" data-val="muy_luminoso" onclick="selectOpt(this)"><div class="icon">☀️</div><div class="lbl">Muy luminoso</div></div>
        <div class="opt" data-field="luminosity" data-val="luminoso" onclick="selectOpt(this)"><div class="icon">🌤</div><div class="lbl">Luminoso</div></div>
        <div class="opt selected" data-field="luminosity" data-val="normal" onclick="selectOpt(this)"><div class="icon">🌥</div><div class="lbl">Normal</div></div>
        <div class="opt" data-field="luminosity" data-val="poco" onclick="selectOpt(this)"><div class="icon">🌦</div><div class="lbl">Poco</div></div>
        <div class="opt" data-field="luminosity" data-val="oscuro" onclick="selectOpt(this)"><div class="icon">☁️</div><div class="lbl">Oscuro</div></div>
      </div></div>

    <!-- DATOS AVANZADOS desplegable -->
    <div class="adv-toggle" id="adv-toggle-3" onclick="toggleAdv('adv-body-3','adv-toggle-3')">
      <span>⚙️</span><span>Datos avanzados opcionales</span>
      <span style="font-size:11px;color:var(--muted)">(energía, AC, cocina, certificados)</span>
      <span class="adv-arrow">▼</span>
    </div>
    <div class="adv-body" id="adv-body-3">
      <div class="grid2" style="margin-bottom:12px">
        <div class="field"><label>❄️ Aire acondicionado</label>
          <select id="adv_ac" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;width:100%;font-size:13px">
            <option value="no">Sin AC</option>
            <option value="split_1">1 split</option>
            <option value="split_2">2 splits</option>
            <option value="split_3">3+ splits</option>
            <option value="central">Central</option>
          </select></div>
        <div class="field"><label>🔥 Calefacción</label>
          <select id="adv_calef" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;width:100%;font-size:13px">
            <option value="no">Sin calefacción</option>
            <option value="gas_natural">Gas natural</option>
            <option value="losa">Losa radiante</option>
            <option value="electrica">Eléctrica</option>
            <option value="garrafa">Garrafa</option>
            <option value="split">Split/Inverter</option>
          </select></div>
      </div>
      <div class="grid2" style="margin-bottom:12px">
        <div class="field"><label>🍳 Cocina / Hornalla</label>
          <select id="adv_cocina" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;width:100%;font-size:13px">
            <option value="no">No especificado</option>
            <option value="gas_natural">Gas natural</option>
            <option value="electrica">Eléctrica</option>
            <option value="garrafa">Garrafa</option>
            <option value="induccion">Inducción</option>
          </select></div>
        <div class="field"><label>🚿 Agua caliente</label>
          <select id="adv_agua" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;width:100%;font-size:13px">
            <option value="no">No especificado</option>
            <option value="gas">Gas (termotanque/calefón)</option>
            <option value="electrico">Eléctrico</option>
            <option value="solar">Panel solar</option>
            <option value="combinado">Combinado</option>
          </select></div>
      </div>
      <div class="grid2" style="margin-bottom:12px">
        <div class="field"><label>☀️ Paneles solares</label>
          <select id="adv_solar" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;width:100%;font-size:13px">
            <option value="no">No tiene</option>
            <option value="parcial">Sí · parcial (agua caliente)</option>
            <option value="total">Sí · total (red eléctrica)</option>
          </select></div>
        <div class="field"><label>📊 Eficiencia energética</label>
          <select id="adv_eficiencia" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;width:100%;font-size:13px">
            <option value="">No especificado</option>
            <option value="A">A · Muy eficiente</option>
            <option value="B">B · Eficiente</option>
            <option value="C">C · Media-alta</option>
            <option value="D">D · Media</option>
            <option value="E">E · Media-baja</option>
            <option value="F">F · Poco eficiente</option>
            <option value="G">G · No eficiente</option>
          </select></div>
      </div>
      <div class="grid2">
        <div class="field"><label>💧 Agua corriente</label>
          <select id="adv_agua_corriente" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;width:100%;font-size:13px">
            <option value="red">Red municipal</option>
            <option value="pozo">Pozo / Perforación</option>
            <option value="cisterna">Cisterna</option>
          </select></div>
        <div class="field"><label>🌐 Internet disponible</label>
          <select id="adv_internet" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;width:100%;font-size:13px">
            <option value="">No especificado</option>
            <option value="fibra">Fibra óptica</option>
            <option value="cable">Cable / HFC</option>
            <option value="adsl">ADSL</option>
            <option value="satelital">Satelital</option>
          </select></div>
      </div>
    </div>

    <div class="nav">
      <button class="btn-prev" onclick="prev(3)">← Atrás</button>
      <button class="btn-next" onclick="next(3)">Continuar →</button>
    </div>
  </div>

  <!-- PASO 4: ESTADO + EXPENSAS + LEGAL -->
  <div class="card" id="step4">
    <div class="step-title">Estado, expensas y situación legal</div>
    <div class="step-sub">Estos datos impactan directamente en el precio de tasación.</div>
    <div class="grid2">
      <div class="field"><label>Antigüedad (años · 0 = a estrenar)</label>
        <input type="number" id="age_years" value="10" min="0" max="120"></div>
      <div class="field"><label>Estado general</label>
        <select id="condition_sel">
          <option value="excelente">⭐ Excelente / Reciclado (+12%)</option>
          <option value="muy_bueno">✨ Muy bueno (+6%)</option>
          <option value="bueno" selected>👍 Bueno (base)</option>
          <option value="regular">🔧 Regular (-12%)</option>
          <option value="a_refaccionar">🏚 A refaccionar (-25%)</option>
        </select></div>
    </div>
    <div class="field"><label>Situación legal / Escritura</label>
      <div class="opt-grid" style="grid-template-columns:repeat(2,1fr)">
        <div class="opt selected" data-field="escritura" data-val="escriturado" onclick="selectOpt(this)">
          <div class="icon">📜</div>
          <div class="lbl">Escriturado <span style="color:var(--green);font-size:10px">(base)</span></div>
          <div class="tip" style="position:absolute;top:6px;right:6px"><span class="tip-icon">?</span><div class="tip-box">La propiedad tiene escritura pública a nombre del vendedor. Máxima seguridad jurídica.</div></div>
        </div>
        <div class="opt" data-field="escritura" data-val="boleto" onclick="selectOpt(this)" style="position:relative">
          <div class="icon">📄</div>
          <div class="lbl">Boleto c/v <span style="color:var(--red);font-size:10px">-6%</span></div>
          <div class="tip" style="position:absolute;top:6px;right:6px"><span class="tip-icon">?</span><div class="tip-box">Boleto de compraventa: contrato privado que promete la escritura futura. Menor seguridad que la escritura.</div></div>
        </div>
        <div class="opt" data-field="escritura" data-val="posesion" onclick="selectOpt(this)" style="position:relative">
          <div class="icon">🔑</div>
          <div class="lbl">Posesión <span style="color:var(--red);font-size:10px">-12%</span></div>
          <div class="tip" style="position:absolute;top:6px;right:6px"><span class="tip-icon">?</span><div class="tip-box">El comprador tiene la posesión del inmueble pero no la escritura. Sin título formal, mayor riesgo legal.</div></div>
        </div>
        <div class="opt" data-field="escritura" data-val="sucesion" onclick="selectOpt(this)" style="position:relative">
          <div class="icon">⚖️</div>
          <div class="lbl">Sucesión <span style="color:var(--red);font-size:10px">-15%</span></div>
          <div class="tip" style="position:absolute;top:6px;right:6px"><span class="tip-icon">?</span><div class="tip-box">Bien heredado pendiente de inscripción. Requiere juicio sucesorio. Trámite largo y costoso.</div></div>
        </div>
      </div></div>
    <div class="field"><label>Expensas mensuales (ARS · 0 si no tiene)</label>
      <div class="slider-wrap">
        <input type="range" id="expensas_ars" min="0" max="1000000" value="0" step="10000" oninput="updateExpensas()">
        <div class="slider-val" id="expensas_val">Sin expensas <span>/ mes</span></div>
      </div>
      <div id="expensas_impacto" style="font-size:11px;color:var(--muted);text-align:center;margin-top:4px"></div>
    </div>
    <div class="field">
      <div class="toggle-row" id="toggle-deuda" onclick="toggleDeuda()">
        <div>
          <div class="t-label">💳 ¿Tiene deudas sobre el inmueble?</div>
          <div class="t-desc">Se descuentan del precio final de tasación</div>
        </div>
        <div class="toggle-sw"></div>
      </div>
    </div>
    <div id="deuda-panel" style="display:none;padding:14px;background:rgba(255,79,110,.05);border:1px solid rgba(255,79,110,.2);border-radius:10px;margin-top:4px">
      <div style="font-size:12px;color:var(--muted);margin-bottom:10px">Completá solo los que aplican. El total se descuenta del precio sugerido.</div>
      <div class="deuda-item">
        <label>🏦 Hipoteca / Crédito bancario <span class="tip"><span class="tip-icon">?</span><div class="tip-box">Préstamo hipotecario pendiente de pago al banco.</div></span></label>
        <input type="number" id="deuda_hipoteca" value="0" min="0" placeholder="USD 0" oninput="calcDeudaTotal()">
      </div>
      <div class="deuda-item">
        <label>⚖️ Embargo judicial <span class="tip"><span class="tip-icon">?</span><div class="tip-box">Medida cautelar que limita la disposición del inmueble por orden judicial.</div></span></label>
        <input type="number" id="deuda_embargo" value="0" min="0" placeholder="USD 0" oninput="calcDeudaTotal()">
      </div>
      <div class="deuda-item">
        <label>🏛️ Deuda de impuestos (ABL / inmueble) <span class="tip"><span class="tip-icon">?</span><div class="tip-box">Impuestos inmobiliarios o ABL adeudados al municipio o provincia.</div></span></label>
        <input type="number" id="deuda_impuestos" value="0" min="0" placeholder="USD 0" oninput="calcDeudaTotal()">
      </div>
      <div class="deuda-item">
        <label>📋 Expensas adeudadas <span class="tip"><span class="tip-icon">?</span><div class="tip-box">Expensas del consorcio pendientes de pago.</div></span></label>
        <input type="number" id="deuda_expensas" value="0" min="0" placeholder="USD 0" oninput="calcDeudaTotal()">
      </div>
      <div class="deuda-item">
        <label>⚠️ Multas / Infracciones municipales</label>
        <input type="number" id="deuda_multas" value="0" min="0" placeholder="USD 0" oninput="calcDeudaTotal()">
      </div>
      <div class="deuda-item" style="border-bottom:none;margin-top:4px">
        <label style="font-weight:700;color:var(--text)">TOTAL a descontar</label>
        <div id="deuda_total_disp" style="font-size:15px;font-weight:700;color:var(--red);text-align:right">USD 0</div>
      </div>
      <!-- campo oculto para el total -->
      <input type="hidden" id="deuda_usd" value="0">
    </div>
    <div class="nav">
      <button class="btn-prev" onclick="prev(4)">← Atrás</button>
      <button class="btn-next" onclick="next(4)">Continuar →</button>
    </div>
  </div>

  <!-- PASO 5: AMENITIES -->
  <div class="card" id="step5">
    <div class="step-title">Amenities y servicios</div>
    <div class="step-sub">Seleccioná lo que tiene la propiedad o el edificio. Cada amenity ajusta el precio.</div>
    <div class="amen-grid" id="amenities-grid"></div>
    <div class="nav">
      <button class="btn-prev" onclick="prev(5)">← Atrás</button>
      <button class="btn-next" onclick="next(5)">Continuar →</button>
    </div>
  </div>

  <!-- PASO 6: FOTOS IA -->
  <div class="card" id="step6">
    <div class="step-title">Fotos y documentos — análisis con IA</div>
    <div class="step-sub">Opcional · La IA analiza fotos y documentos del inmueble para una tasación más precisa</div>

    <!-- FOTOS -->
    <div style="font-weight:600;font-size:14px;margin-bottom:10px">📸 Fotos de la propiedad</div>
    <div class="photo-drop" id="drop-zone">
      <input type="file" accept="image/*" multiple onchange="handlePhotos(this.files)" id="photo-input">
      <div class="icon">📸</div>
      <p><strong>Arrastrá fotos acá</strong> o hacé clic para seleccionar</p>
      <p style="margin-top:5px;font-size:12px">JPG, PNG · Hasta 6 fotos · Ajuste ±15%</p>
    </div>
    <div class="photo-preview" id="photo-preview"></div>
    <div style="font-size:12px;color:var(--muted);margin-top:8px" id="photo-count"></div>
    <div class="ai-analyzing" id="ai-analyzing">
      <div class="ai-spinner"></div>
      <div><strong style="font-size:13px">IA analizando fotos…</strong><br>
        <span style="font-size:12px;color:var(--muted)">Claude Vision evalúa luminosidad, terminaciones y estado</span></div>
    </div>
    <div class="ai-result" id="ai-result"></div>

    <!-- VIDEO -->
    <div style="font-weight:600;font-size:14px;margin:22px 0 6px">🎥 Video de la propiedad <span style="font-weight:400;font-size:12px;color:var(--muted)">(opcional)</span></div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:12px">Subí un video filmado de la propiedad. El sistema extrae fotogramas automáticamente y la IA los analiza.</div>
    <div class="doc-drop" id="video-drop-zone" onclick="document.getElementById('video-input').click()" ondragover="event.preventDefault();this.classList.add('dragging')" ondragleave="this.classList.remove('dragging')" ondrop="handleVideo(event)">
      <input type="file" accept="video/*" onchange="handleVideo(event)" id="video-input" style="display:none">
      <div style="font-size:28px;margin-bottom:8px">🎬</div>
      <p><strong>Arrastrá un video acá</strong> o hacé clic</p>
      <p style="margin-top:4px;font-size:11px;color:var(--muted)">MP4, MOV, AVI · Se extraen 8 fotogramas automáticamente</p>
    </div>
    <div id="video-status" style="margin-top:8px;font-size:12px;color:var(--muted)"></div>
    <div id="video-frames-preview" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px"></div>
    <div id="video-ai-result" style="margin-top:10px"></div>

    <!-- DOCUMENTOS -->
    <div style="font-weight:600;font-size:14px;margin:22px 0 6px">📄 Documentos del inmueble <span style="font-weight:400;font-size:12px;color:var(--muted)">(opcional)</span></div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:12px">Subí boletas de expensas, ABL, luz, gas, planos. La IA extrae datos relevantes para la tasación.</div>
    <div class="doc-drop" id="doc-drop-zone" onclick="document.getElementById('doc-input').click()" ondragover="event.preventDefault();this.classList.add('dragging')" ondragleave="this.classList.remove('dragging')" ondrop="handleDocs(event)">
      <input type="file" accept="image/*,.pdf" multiple onchange="handleDocs(event)" id="doc-input" style="display:none">
      <div style="font-size:28px;margin-bottom:8px">📂</div>
      <p><strong>Arrastrá documentos acá</strong> o hacé clic</p>
      <p style="margin-top:4px;font-size:11px;color:var(--muted)">PDF, JPG, PNG · Expensas · ABL · Luz · Gas · Plano</p>
    </div>
    <div id="doc-list" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px"></div>
    <div id="doc-ai-result" style="margin-top:10px"></div>

    <!-- ESCRITURA -->
    <div style="font-weight:600;font-size:14px;margin:22px 0 6px">📜 Escritura del inmueble <span style="font-weight:400;font-size:12px;color:var(--muted)">(opcional)</span></div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:12px">Subí fotos o PDF de la escritura. La IA genera un informe completo: titulares, límites, estado del bien, transcripción, impuestos y permisos.</div>
    <div class="doc-drop" id="escritura-drop-zone" onclick="document.getElementById('escritura-input').click()" ondragover="event.preventDefault();this.classList.add('dragging')" ondragleave="this.classList.remove('dragging')" ondrop="handleEscritura(event)">
      <input type="file" accept="image/*,.pdf" multiple onchange="handleEscritura(event)" id="escritura-input" style="display:none">
      <div style="font-size:28px;margin-bottom:8px">📜</div>
      <p><strong>Subí la escritura acá</strong> o hacé clic</p>
      <p style="margin-top:4px;font-size:11px;color:var(--muted)">Fotos de cada hoja o PDF escaneado · Hasta 10 páginas</p>
    </div>
    <div id="escritura-status" style="margin-top:8px;font-size:12px;color:var(--muted)"></div>
    <div id="escritura-ai-result" style="margin-top:10px"></div>

    <div class="error-box" id="error-step6"></div>
    <div class="nav">
      <button class="btn-prev" onclick="prev(6)">← Atrás</button>
      <button class="btn-next" onclick="next(6)">Continuar →</button>
    </div>
  </div>

  <!-- PASO 7: CONTACTO -->
  <div class="card" id="step7">
    <div class="step-title">Tus datos de contacto</div>
    <div class="step-sub">Para enviarte el resultado completo por email. Tus datos son confidenciales.</div>
    <div class="grid2">
      <div class="field"><label>Nombre *</label><input type="text" id="contact-name" placeholder="Tu nombre"></div>
      <div class="field"><label>Apellido</label><input type="text" id="contact-surname" placeholder="Tu apellido"></div>
    </div>
    <div class="grid2">
      <div class="field"><label>Email *</label><input type="email" id="contact-email" placeholder="tu@email.com"></div>
      <div class="field"><label>Teléfono / WhatsApp</label><input type="tel" id="contact-phone" placeholder="+54 342 000-0000"></div>
    </div>
    <div style="background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.2);border-radius:10px;padding:13px 15px;font-size:12px;color:var(--muted);margin-top:4px">
      🔒 Tus datos no se comparten con terceros. Te enviaremos el resultado por email.
    </div>
    <div class="error-box" id="error-contact"></div>
    <div class="nav">
      <button class="btn-prev" onclick="prev(7)">← Atrás</button>
      <button class="btn-next" id="btn-calcular" onclick="calcularConContacto()">✦ Ver mi tasación</button>
    </div>
  </div>

  <!-- RESULTADO -->
  <div class="card" id="step8">
    <div class="result-hero" id="result-content"></div>
    <div class="result-grid" id="result-details"></div>

    <!-- Rating de precio -->
    <div class="rating-widget no-print" id="rating-widget" style="display:none">
      <p>📊 ¿Cómo te pareció el precio de tasación?</p>
      <div class="rating-btns">
        <button class="rating-btn" onclick="sendRating('barato')">📉 Barato</button>
        <button class="rating-btn" onclick="sendRating('justo')">✅ En precio</button>
        <button class="rating-btn" onclick="sendRating('caro')">💰 Caro</button>
      </div>
      <div class="rating-thanks" id="rating-thanks">¡Gracias por tu opinión! Nos ayuda a mejorar el algoritmo.</div>
    </div>

    <div class="result-actions no-print">
      <button class="btn-print" onclick="printResult()">🖨 Imprimir / PDF</button>
      <button class="btn-new" onclick="resetWizard()">+ Nueva tasación</button>
      <button class="btn-print" onclick="showEmbed()">🔗 Embeber</button>
      <button class="btn-print no-print" id="btn-reenviar-email" onclick="reenviarEmail()" style="display:none">📧 Reenviar email</button>
    </div>
  </div>
</div>

<div class="modal-bg" id="embed-modal">
  <div class="modal">
    <h3>Embeber en tu sitio</h3>
    <p style="font-size:13px;color:var(--muted);margin-bottom:13px">Pegá este código en cualquier página:</p>
    <pre id="embed-code"></pre>
    <button class="modal-close" onclick="document.getElementById('embed-modal').classList.remove('open')">Cerrar</button>
  </div>
</div>
<div class="toast" id="toast"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const API_BASE = 'api';
const USD_RATE_DISP = 1450;

const ZONES_BY_CITY = {
  santa_fe_capital:[
    {val:'',label:'Detectar automáticamente'},
    {val:'centro',label:'Centro / Microcentro'},
    {val:'candioti_norte',label:'Candioti Norte'},
    {val:'candioti_sur',label:'Candioti Sur'},
    {val:'el_pozo',label:'El Pozo / Belgrano'},
    {val:'general_obligado',label:'Villa del Parque'},
    {val:'la_costanera',label:'Costanera / Universitario'},
    {val:'alto_verde',label:'Alto Verde'},
    {val:'sur_industrial',label:'Zona Sur'},
  ],
  buenos_aires:[
    {val:'',label:'Detectar automáticamente'},
    {val:'palermo',label:'Palermo'},
    {val:'recoleta',label:'Recoleta / Barrio Norte'},
    {val:'belgrano',label:'Belgrano'},
    {val:'nuñez',label:'Núñez / Saavedra'},
    {val:'villa_crespo',label:'Villa Crespo'},
    {val:'san_telmo',label:'San Telmo / Monserrat'},
    {val:'almagro',label:'Almagro / Boedo'},
    {val:'villa_urquiza',label:'Villa Urquiza'},
    {val:'liniers',label:'Liniers / Lugano'},
  ],
  puerto_madero:[
    {val:'',label:'Detectar automáticamente'},
    {val:'pm_este',label:'Puerto Madero Este (Torres)'},
    {val:'pm_oeste',label:'Puerto Madero Oeste (Diques)'},
  ],
  gba_norte:[
    {val:'',label:'Detectar automáticamente'},
    {val:'san_isidro',label:'San Isidro'},
    {val:'vicente_lopez',label:'Vicente López / Olivos'},
    {val:'tigre',label:'Tigre / Nordelta'},
  ],
  rosario:[{val:'',label:'Detectar automáticamente'},{val:'centro_rosario',label:'Centro / Pichincha'}],
  cordoba:[{val:'',label:'Detectar automáticamente'},{val:'nueva_cordoba',label:'Nueva Córdoba'},{val:'cerro_rosas',label:'Cerro de las Rosas'}],
};

const MAP_CENTERS = {
  santa_fe_capital:{lat:-31.629,lng:-60.701,z:13},
  buenos_aires:{lat:-34.604,lng:-58.382,z:13},
  puerto_madero:{lat:-34.609,lng:-58.363,z:15},
  gba_norte:{lat:-34.473,lng:-58.516,z:12},
  rosario:{lat:-32.947,lng:-60.639,z:13},
  cordoba:{lat:-31.417,lng:-64.183,z:13},
};

const AMENITIES = [
  {slug:'pileta',label:'Pileta',icon:'🏊'},{slug:'gimnasio',label:'Gimnasio',icon:'💪'},
  {slug:'sum',label:'SUM / Salón',icon:'🎉'},{slug:'solarium',label:'Solarium',icon:'🌅'},
  {slug:'spa',label:'SPA / Jacuzzi',icon:'🛁'},{slug:'roof_top',label:'Roof Top',icon:'🏙'},
  {slug:'seguridad',label:'Seguridad 24hs',icon:'🔒'},{slug:'porteria',label:'Portería',icon:'🏢'},
  {slug:'ascensor',label:'Ascensor',icon:'🛗'},{slug:'lavadero',label:'Lavadero',icon:'👕'},
  {slug:'baulera',label:'Baulera',icon:'📦'},{slug:'bike_room',label:'Bike Room',icon:'🚴'},
  {slug:'cowork',label:'Cowork',icon:'💻'},{slug:'jardin',label:'Jardín',icon:'🌳'},
  {slug:'gas_natural',label:'Gas natural',icon:'🔥'},{slug:'mascotas',label:'Mascotas ok',icon:'🐾'},
];

let ST = {
  step:1,lat:null,lng:null,
  property_type:'departamento',operation:'venta',
  city:'santa_fe_capital',
  covered_area:65,total_area:65,
  ambientes:3,bedrooms:2,bathrooms:1,garages:0,
  floor:1,has_elevator:false,
  view:'exterior',orientation:'norte',luminosity:'normal',
  escritura:'escriturado',expensas_ars:0,
  tiene_deuda:false,deuda_usd:0,
  amenities:{},photos:[],ai_photo_score:null,contact:null,
  docs:[],doc_ai_notes:null,
};

// ── MAPA ─────────────────────────────────────────────────────
let map,mapMarker,mapPOILayers=[];

// ── Ícono dorado con glow ─────────────────────────────────────────────────────
const goldIcon=()=>L.divIcon({className:'',html:`
  <div style="width:22px;height:22px;background:var(--gold);border-radius:50% 50% 50% 0;transform:rotate(-45deg);
    border:2px solid rgba(255,255,255,.4);box-shadow:0 0 12px rgba(201,168,76,.8),0 0 24px rgba(201,168,76,.4)">
    <div style="width:8px;height:8px;background:#0d0f14;border-radius:50%;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%)"></div>
  </div>`,iconSize:[22,22],iconAnchor:[11,22]});

// ── Ícono POI circular con emoji ─────────────────────────────────────────────
function poiIcon(emoji,bg,glow){
  return L.divIcon({className:'',
    html:`<div class="map-poi-dot" style="width:26px;height:26px;background:${bg};box-shadow:0 0 8px ${glow}88,0 0 18px ${glow}44">${emoji}</div>`,
    iconSize:[26,26],iconAnchor:[13,13]});
}

function clearMapPOIs(){mapPOILayers.forEach(l=>map.removeLayer(l));mapPOILayers=[];}

// ── POI en mapa ───────────────────────────────────────────────────────────────
let _poiTimer=null;
function fetchMapPOIs(lat,lng){
  clearTimeout(_poiTimer);
  _poiTimer=setTimeout(()=>_doFetchMapPOIs(lat,lng),600);
}
async function _doFetchMapPOIs(lat,lng){
  clearMapPOIs();
  const q=`[out:json][timeout:12];
(
  node["amenity"~"school|college|university"](around:700,${lat},${lng});
  node["amenity"~"hospital|clinic|pharmacy"](around:700,${lat},${lng});
  node["amenity"~"supermarket|mall"](around:700,${lat},${lng});
  node["shop"="supermarket"](around:700,${lat},${lng});
  node["highway"="bus_stop"](around:500,${lat},${lng});
  node["leisure"~"park|garden|playground"](around:700,${lat},${lng});
  way["leisure"~"park|garden"](around:700,${lat},${lng});
  relation["leisure"~"park|garden"](around:700,${lat},${lng});
);out center 60;`;
  const cats={
    school:    {e:'🎓',bg:'#1a3a6e',gl:'#4a8ff7'},
    college:   {e:'🎓',bg:'#1a3a6e',gl:'#4a8ff7'},
    university:{e:'🎓',bg:'#1a3a6e',gl:'#4a8ff7'},
    hospital:  {e:'🏥',bg:'#6e1a1a',gl:'#f74a4a'},
    clinic:    {e:'⚕️',bg:'#6e1a3a',gl:'#f74a8f'},
    pharmacy:  {e:'💊',bg:'#1a6e4a',gl:'#4af7a0'},
    supermarket:{e:'🛒',bg:'#4a1a6e',gl:'#af4af7'},
    mall:      {e:'🛍',bg:'#4a1a6e',gl:'#af4af7'},
    bus_stop:  {e:'🚌',bg:'#5a4a00',gl:'#f7c74a'},
    park:      {e:'🌳',bg:'#0d3320',gl:'#4af77a'},
    garden:    {e:'🌷',bg:'#0d3320',gl:'#4af77a'},
    playground:{e:'🎠',bg:'#0d2a33',gl:'#4af7e0'},
  };
  try{
    const res=await fetch('https://overpass-api.de/api/interpreter',
      {method:'POST',body:'data='+encodeURIComponent(q)});
    if(!res.ok){console.warn('[POI] Overpass HTTP',res.status);return;}
    const js=await res.json();
    const seen=new Set();
    let added=0;
    (js.elements||[]).forEach(el=>{
      const elLat=el.lat??el.center?.lat;
      const elLng=el.lon??el.center?.lon;
      if(!elLat||!elLng)return;
      const key=`${elLat.toFixed(3)},${elLng.toFixed(3)}`;
      if(seen.has(key))return;
      seen.add(key);
      const t=el.tags||{};
      const type=t.amenity||t.leisure||t.highway||t.shop||'';
      const cat=cats[type];
      if(!cat)return;
      const name=t.name||t['name:es']||(type.charAt(0).toUpperCase()+type.slice(1));
      const dlat=elLat-lat,dlng=(elLng-lng)*Math.cos(lat*Math.PI/180);
      const dist=Math.round(Math.sqrt(dlat*dlat+dlng*dlng)*111000);
      const m=L.marker([elLat,elLng],{icon:poiIcon(cat.e,cat.bg,cat.gl),zIndexOffset:200})
        .bindTooltip(`${name} · ${dist}m`,
          {permanent:false,direction:'top',className:'map-poi-tip',offset:[0,-6]});
      m.addTo(map);
      mapPOILayers.push(m);
      added++;
    });
    if(mapPOILayers.length>0){
      const circle=L.circle([lat,lng],{radius:700,color:'rgba(0,210,255,.5)',weight:1,
        fillColor:'rgba(0,210,255,.04)',fillOpacity:1,dashArray:'4,6',interactive:false});
      circle.addTo(map);
      mapPOILayers.push(circle);
    }
    console.info(`[TasadorIA] POIs cargados: ${added}`);
  }catch(e){console.warn('[TasadorIA] POI fetch err:',e);}
}

function initMap(){
  map=L.map('map',{zoomControl:true}).setView([-31.629,-60.701],13);
  // OpenStreetMap estándar — compatible con todos los servidores, neon via CSS
  const osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
    attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom:19,crossOrigin:true});
  const cartoLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',{
    attribution:'&copy; OpenStreetMap &copy; CARTO',subdomains:'abcd',maxZoom:20,crossOrigin:true});
  osmLayer.addTo(map);
  osmLayer.on('tileerror',()=>{map.removeLayer(osmLayer);cartoLayer.addTo(map);});
  map.on('click',e=>{ST.lat=e.latlng.lat;ST.lng=e.latlng.lng;setPin(e.latlng.lat,e.latlng.lng);});
}
function setPin(lat,lng){
  if(mapMarker)map.removeLayer(mapMarker);
  mapMarker=L.marker([lat,lng],{draggable:true,icon:goldIcon()}).addTo(map);
  map.setView([lat,lng],Math.max(map.getZoom(),15));
  mapMarker.on('dragend',e=>{const p=e.target.getLatLng();ST.lat=p.lat;ST.lng=p.lng;document.getElementById('lat').value=p.lat;document.getElementById('lng').value=p.lng;fetchMapPOIs(p.lat,p.lng);});
  document.getElementById('lat').value=lat;
  document.getElementById('lng').value=lng;
  document.getElementById('geo-badge').style.display='inline-flex';
  fetchMapPOIs(lat,lng); // POIs cercanos al pin
}
let geoTimer;
function debouncedGeocode(){clearTimeout(geoTimer);geoTimer=setTimeout(geocodeAddr,1200);}
async function geocodeAddr(){
  const addr=document.getElementById('address').value.trim();
  if(addr.length<5)return;
  const city=document.getElementById('city_sel').value;
  const sfx={santa_fe_capital:', Santa Fe, Argentina',buenos_aires:', Buenos Aires, Argentina',
    puerto_madero:', Buenos Aires, Argentina',gba_norte:', Buenos Aires, Argentina',
    rosario:', Rosario, Argentina',cordoba:', Córdoba, Argentina'}[city]||', Argentina';
  try{
    const r=await fetch('https://nominatim.openstreetmap.org/search?q='+encodeURIComponent(addr+sfx)+'&format=json&limit=1',{headers:{'User-Agent':'TasadorIA/5.0'}});
    const d=await r.json();
    if(d&&d[0]){ST.lat=+d[0].lat;ST.lng=+d[0].lon;setPin(ST.lat,ST.lng);}
  }catch(e){}
}
function onCityChange(){
  const city=document.getElementById('city_sel').value;
  ST.city=city;
  const sel=document.getElementById('zone_sel');
  sel.innerHTML=(ZONES_BY_CITY[city]||[{val:'',label:'General'}]).map(z=>`<option value="${z.val}">${z.label}</option>`).join('');
  const c=MAP_CENTERS[city];
  if(c&&map)map.setView([c.lat,c.lng],c.z);
}

// ── OPCIONES VISUALES ─────────────────────────────────────────
function selectOpt(el){
  const f=el.dataset.field;
  document.querySelectorAll(`[data-field="${f}"]`).forEach(e=>e.classList.remove('selected'));
  el.classList.add('selected');
  ST[f]=el.dataset.val;
}

// ── SLIDERS ───────────────────────────────────────────────────
function updateSlider(id,unit){
  const v=document.getElementById(id).value;
  ST[id]=+v;
  const el=document.getElementById(id+'_val');
  if(el)el.innerHTML=v+(unit?` <span>${unit}</span>`:'');
}
function updateExpensas(){
  const v=+document.getElementById('expensas_ars').value;
  ST.expensas_ars=v;
  const elV=document.getElementById('expensas_val');
  const elI=document.getElementById('expensas_impacto');
  if(v===0){elV.innerHTML='Sin expensas <span>/ mes</span>';elI.textContent='';return;}
  elV.innerHTML='$'+v.toLocaleString('es-AR')+' <span>ARS/mes</span>';
  const usd=v/USD_RATE_DISP;
  if(usd>30){
    const pen=Math.min(15,((usd-30)/10)*0.5);
    elI.innerHTML=`≈ USD ${usd.toFixed(0)}/mes · impacto: <span style="color:var(--red)">-${pen.toFixed(1)}%</span>`;
  }else{
    elI.innerHTML=`≈ USD ${usd.toFixed(0)}/mes · sin impacto`;
  }
}

// ── TOGGLES ───────────────────────────────────────────────────
function toggleSwitch(field,toggleId){
  ST[field]=!ST[field];
  document.getElementById(toggleId).classList.toggle('on',ST[field]);
}
function toggleDeuda(){
  ST.tiene_deuda=!ST.tiene_deuda;
  document.getElementById('toggle-deuda').classList.toggle('on',ST.tiene_deuda);
  document.getElementById('deuda-panel').style.display=ST.tiene_deuda?'block':'none';
  if(ST.tiene_deuda)calcDeudaTotal();
}
function calcDeudaTotal(){
  const ids=['deuda_hipoteca','deuda_embargo','deuda_impuestos','deuda_expensas','deuda_multas'];
  const total=ids.reduce((s,id)=>s+(+document.getElementById(id).value||0),0);
  document.getElementById('deuda_usd').value=total;
  document.getElementById('deuda_total_disp').textContent='USD '+total.toLocaleString('es-AR');
}
function toggleAdv(bodyId,toggleId){
  const body=document.getElementById(bodyId);
  const tog=document.getElementById(toggleId);
  body.classList.toggle('open');
  tog.classList.toggle('open');
}

// ── AMENITIES ─────────────────────────────────────────────────
function initAmenities(){
  document.getElementById('amenities-grid').innerHTML=AMENITIES.map(a=>
    `<label class="amen-check" id="amen-${a.slug}">
      <input type="checkbox" value="${a.slug}" onchange="toggleAmen('${a.slug}',this.checked)">
      ${a.icon} ${a.label}</label>`).join('');
}
function toggleAmen(slug,checked){
  document.getElementById('amen-'+slug)?.classList.toggle('checked',checked);
  if(checked)ST.amenities[slug]=true;else delete ST.amenities[slug];
}

// ── FOTOS ─────────────────────────────────────────────────────
function handlePhotos(files){
  const arr=Array.from(files).slice(0,6);
  document.getElementById('photo-preview').innerHTML='';
  ST.photos=[];
  arr.forEach(f=>{
    const reader=new FileReader();
    reader.onload=e=>{
      ST.photos.push(e.target.result);
      const img=document.createElement('img');
      img.src=e.target.result;img.className='photo-thumb';
      document.getElementById('photo-preview').appendChild(img);
    };
    reader.readAsDataURL(f);
  });
  document.getElementById('photo-count').textContent=`${arr.length} foto${arr.length!==1?'s':''} seleccionada${arr.length!==1?'s':''}`;
}
window.addEventListener('DOMContentLoaded',()=>{
  const dz=document.getElementById('drop-zone');
  dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('dragging');});
  dz.addEventListener('dragleave',()=>dz.classList.remove('dragging'));
  dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('dragging');handlePhotos(e.dataTransfer.files);});
});

// ── DOCUMENTOS ────────────────────────────────────────────────
const DOC_ICONS={'application/pdf':'📄','image/jpeg':'🖼️','image/png':'🖼️','image/webp':'🖼️'};
const DOC_LABELS={'expensas':'Expensas','abl':'ABL','luz':'Luz','gas':'Gas','plano':'Plano',
  'escritura':'Escritura','sucesion':'Sucesión','boleto':'Boleto c/v','impuesto':'Impuesto','otro':'Documento'};

function handleDocs(event){
  event.preventDefault();
  document.getElementById('doc-drop-zone').classList.remove('dragging');
  const files=event.dataTransfer?event.dataTransfer.files:event.target.files;
  const arr=Array.from(files).slice(0,6);
  arr.forEach(f=>{
    if(ST.docs.length>=6)return;
    const reader=new FileReader();
    reader.onload=e=>{
      const docType=detectDocType(f.name);
      const docObj={name:f.name,type:f.type,dataUrl:e.target.result,docType};
      ST.docs.push(docObj);
      renderDocList();
    };
    reader.readAsDataURL(f);
  });
  // reset input
  const inp=document.getElementById('doc-input');
  if(inp)inp.value='';
}

function detectDocType(name){
  const n=name.toLowerCase();
  for(const k of Object.keys(DOC_LABELS)){if(n.includes(k))return k;}
  return 'otro';
}

function renderDocList(){
  const el=document.getElementById('doc-list');
  el.innerHTML=ST.docs.map((d,i)=>`
    <div class="doc-chip">
      <span>${DOC_ICONS[d.type]||'📎'}</span>
      <span title="${d.name}">${DOC_LABELS[d.docType]||d.name.substring(0,16)}</span>
      <button onclick="removeDoc(${i})" title="Quitar">✕</button>
    </div>`).join('');
}

function removeDoc(i){
  ST.docs.splice(i,1);
  renderDocList();
}

async function analyzeDocs(){
  if(!ST.docs.length)return;
  const imgDocs=ST.docs.filter(d=>d.type.startsWith('image/'));
  if(!imgDocs.length)return; // PDFs se describen por nombre
  const docEl=document.getElementById('doc-ai-result');
  docEl.innerHTML=`<div class="ai-analyzing show" style="display:flex"><div class="ai-spinner"></div><div><strong>Analizando documentos…</strong></div></div>`;
  try{
    const res=await fetch(API_BASE+'/analyze.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({images:imgDocs.map(d=>d.dataUrl),mode:'docs',
        property:{property_type:ST.property_type,covered_area:ST.covered_area}})});
    const d=await res.json();
    if(d.notes){
      ST.doc_ai_notes=d.notes;
      docEl.innerHTML=`<div class="ai-result show" style="display:flex;gap:10px;margin-top:8px">
        <span style="font-size:18px">📋</span>
        <div><strong style="font-size:13px">Documentos analizados</strong><br>
        <span style="font-size:12px;color:var(--muted)">${d.notes}</span></div></div>`;
    } else { docEl.innerHTML=''; }
  }catch(e){docEl.innerHTML='';}
}

// ── VIDEO ─────────────────────────────────────────────────────
let ST_video_frames=[];

function handleVideo(event){
  event.preventDefault();
  document.getElementById('video-drop-zone').classList.remove('dragging');
  const file=event.dataTransfer?event.dataTransfer.files[0]:event.target.files[0];
  if(!file||!file.type.startsWith('video/'))return;
  const status=document.getElementById('video-status');
  status.textContent='Cargando video y extrayendo fotogramas…';
  document.getElementById('video-frames-preview').innerHTML='';
  ST_video_frames=[];
  extractVideoFrames(file).then(frames=>{
    ST_video_frames=frames;
    const preview=document.getElementById('video-frames-preview');
    frames.forEach(f=>{const img=document.createElement('img');img.src=f;img.className='video-thumb';preview.appendChild(img);});
    status.textContent=`✓ ${frames.length} fotogramas extraídos · La IA los analizará al tasar`;
  }).catch(()=>{status.textContent='Error leyendo el video. Probá con fotos directamente.';});
  const inp=document.getElementById('video-input');if(inp)inp.value='';
}

function extractVideoFrames(file,count=8){
  return new Promise((resolve,reject)=>{
    const url=URL.createObjectURL(file);
    const video=document.createElement('video');
    video.src=url;video.muted=true;video.preload='metadata';
    video.onerror=()=>{URL.revokeObjectURL(url);reject();};
    video.onloadeddata=()=>{
      const dur=video.duration;
      const canvas=document.createElement('canvas');
      canvas.width=640;canvas.height=360;
      const ctx=canvas.getContext('2d');
      const frames=[];let i=0;
      const capture=()=>{
        if(i>=count){URL.revokeObjectURL(url);resolve(frames);return;}
        video.currentTime=(dur/(count+1))*(i+1);
        video.onseeked=()=>{
          ctx.drawImage(video,0,0,640,360);
          frames.push(canvas.toDataURL('image/jpeg',0.65));
          i++;capture();
        };
      };
      capture();
    };
  });
}

async function analyzeVideoFrames(){
  if(!ST_video_frames.length)return;
  const el=document.getElementById('video-ai-result');
  el.innerHTML=`<div class="ai-analyzing show" style="display:flex"><div class="ai-spinner"></div><div><strong>IA analizando video…</strong><br><span style="font-size:12px;color:var(--muted)">Evaluando ${ST_video_frames.length} fotogramas</span></div></div>`;
  try{
    const res=await fetch(API_BASE+'/analyze.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({images:ST_video_frames,mode:'video',
        property:{property_type:ST.property_type,covered_area:ST.covered_area}})});
    const d=await res.json();
    if(d.score!==undefined){
      if(!ST.ai_photo_score||Math.abs(d.score)>Math.abs(ST.ai_photo_score))ST.ai_photo_score=d.score;
      el.innerHTML=`<div class="ai-result show" style="display:flex;gap:10px;margin-top:8px">
        <span style="font-size:18px">🎬</span>
        <div><strong style="font-size:13px">Video analizado · Ajuste IA: ${d.score>0?'+':''}${d.score}%</strong><br>
        <span style="font-size:12px;color:var(--muted)">${d.summary||''}</span></div></div>`;
    } else { el.innerHTML=''; }
  }catch(e){el.innerHTML='';}
}

// ── ESCRITURA ─────────────────────────────────────────────────
let ST_escritura_imgs=[];

function handleEscritura(event){
  event.preventDefault();
  document.getElementById('escritura-drop-zone').classList.remove('dragging');
  const files=event.dataTransfer?event.dataTransfer.files:event.target.files;
  const arr=Array.from(files).slice(0,10);
  ST_escritura_imgs=[];
  document.getElementById('escritura-status').textContent='';
  document.getElementById('escritura-ai-result').innerHTML='';
  let loaded=0;
  arr.forEach(f=>{
    if(!f.type.startsWith('image/'))return; // skip PDF por ahora
    const r=new FileReader();
    r.onload=e=>{ST_escritura_imgs.push(e.target.result);loaded++;
      if(loaded===arr.filter(f2=>f2.type.startsWith('image/')).length){
        document.getElementById('escritura-status').textContent=`${loaded} página${loaded!==1?'s':''} de escritura cargada${loaded!==1?'s':''}. Hacé clic en "Analizar escritura →"`;
        renderEscrituraBtn();
      }};
    r.readAsDataURL(f);
  });
  const inp=document.getElementById('escritura-input');if(inp)inp.value='';
}

function renderEscrituraBtn(){
  const el=document.getElementById('escritura-ai-result');
  el.innerHTML=`<button onclick="analyzeEscritura()" style="background:rgba(201,168,76,.15);border:1.5px solid var(--gold);color:var(--gold);border-radius:10px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;width:100%;margin-top:6px">📜 Analizar escritura →</button>`;
}

async function analyzeEscritura(){
  if(!ST_escritura_imgs.length){showToast('Subí fotos de la escritura primero','error');return;}
  const el=document.getElementById('escritura-ai-result');
  el.innerHTML=`<div class="ai-analyzing show" style="display:flex"><div class="ai-spinner"></div><div><strong>IA interpretando escritura…</strong><br><span style="font-size:12px;color:var(--muted)">Extrayendo titulares, límites, estado del bien y más</span></div></div>`;
  try{
    const res=await fetch(API_BASE+'/analyze_escritura.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({images:ST_escritura_imgs})});
    const d=await res.json();
    if(d.success&&d.report){
      el.innerHTML=renderEscrituraReport(d.report);
    } else {
      el.innerHTML=`<div class="error-box" style="display:block">${d.error||'No se pudo analizar la escritura'}</div>`;
    }
  }catch(e){el.innerHTML=`<div class="error-box" style="display:block">Error de conexión al analizar la escritura</div>`;}
}

function renderEscrituraReport(r){
  const badge=v=>v?`<span class="badge">${v}</span>`:'';
  const list=arr=>(arr&&arr.length)?`<ul>${arr.map(i=>`<li>${i}</li>`).join('')}</ul>`:'<p style="color:var(--muted)">No encontrado</p>';
  return `<div class="escritura-report">
    <h5>🏠 Bien inmueble</h5>
    <p>${r.descripcion_bien||'—'}</p>
    ${r.matricula?`<p style="margin-top:4px"><strong>Matrícula/Folio:</strong> ${r.matricula}</p>`:''}
    ${r.numero_escritura?`<p><strong>N° Escritura:</strong> ${r.numero_escritura}</p>`:''}

    <h5>👥 Titulares</h5>
    ${r.titulares&&r.titulares.length?r.titulares.map(t=>`<div style="margin-bottom:4px">${badge(t.porcentaje)} <strong>${t.nombre}</strong>${t.dni?` · DNI ${t.dni}`:''}</div>`).join(''):'<p style="color:var(--muted)">No identificados</p>'}

    <h5>📐 Límites del inmueble</h5>
    ${r.limites?`<p><strong>Norte:</strong> ${r.limites.norte||'—'}</p>
    <p><strong>Sur:</strong> ${r.limites.sur||'—'}</p>
    <p><strong>Este:</strong> ${r.limites.este||'—'}</p>
    <p><strong>Oeste:</strong> ${r.limites.oeste||'—'}</p>`:'<p style="color:var(--muted)">No encontrados</p>'}
    ${r.superficie_escritura?`<p style="margin-top:4px"><strong>Superficie escriturada:</strong> ${r.superficie_escritura}</p>`:''}

    <h5>⚖️ Estado legal y cargas</h5>
    <p>${r.estado_legal||'—'}</p>
    ${r.cargas&&r.cargas.length?`<ul>${r.cargas.map(c=>`<li>${c}</li>`).join('')}</ul>`:''}

    <h5>📅 Datos notariales</h5>
    ${r.notario?`<p><strong>Notario:</strong> ${r.notario}</p>`:''}
    ${r.fecha_escritura?`<p><strong>Fecha:</strong> ${r.fecha_escritura}</p>`:''}
    ${r.registro?`<p><strong>Registro:</strong> ${r.registro}</p>`:''}

    <h5>🏗️ Permisos e impuestos registrados</h5>
    ${list(r.permisos_construccion)}
    ${r.impuestos_info?`<p>${r.impuestos_info}</p>`:''}

    <h5>📝 Transcripción resumida</h5>
    <div class="transcripcion">${r.transcripcion||'No disponible'}</div>

    ${r.observaciones?`<h5>⚠️ Observaciones</h5><p>${r.observaciones}</p>`:''}

    <div style="margin-top:14px;display:flex;gap:8px">
      <button onclick="printEscritura()" style="background:transparent;border:1.5px solid var(--gold);color:var(--gold);border-radius:8px;padding:8px 16px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit">🖨 Imprimir informe</button>
    </div>
  </div>`;
}

function printEscritura(){
  const content=document.getElementById('escritura-ai-result').innerHTML;
  const w=window.open('','_blank');
  w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Informe de Escritura — TasadorIA</title>
  <style>body{font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:30px;color:#111}
  h1{color:#c9a84c;border-bottom:2px solid #c9a84c;padding-bottom:10px}
  .escritura-report{border:1px solid #ddd;border-radius:8px;padding:20px}
  h5{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:#c9a84c;margin:14px 0 6px}
  .badge{background:#fff3d4;color:#8b6914;border:1px solid #d4ac0d;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:600;margin:2px;display:inline-block}
  .transcripcion{background:#f8f8f8;padding:12px;border-radius:6px;font-size:12px;line-height:1.7;white-space:pre-wrap;max-height:none}
  button{display:none}</style></head><body>
  <img src="https://anperprimo.com/wp-content/uploads/2025/07/WHITE111.png" style="height:40px;margin-bottom:16px">
  <h1>Informe de Escritura Inmobiliaria</h1>
  ${content}
  <p style="margin-top:20px;font-size:10px;color:#aaa">Generado por TasadorIA · anperprimo.com · ${new Date().toLocaleDateString('es-AR')}</p>
  </body></html>`);
  w.document.close();w.print();
}

async function analyzePhotos(){
  if(!ST.photos.length)return 0;
  document.getElementById('ai-analyzing').classList.add('show');
  try{
    const res=await fetch(API_BASE+'/analyze.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({images:ST.photos,property:{property_type:ST.property_type,covered_area:ST.covered_area,age_years:+document.getElementById('age_years').value||10,condition:document.getElementById('condition_sel').value}})});
    const d=await res.json();
    document.getElementById('ai-analyzing').classList.remove('show');
    ST.ai_photo_score=d.score||0;
    if(d.summary){
      const color=d.score>5?'var(--green)':d.score<-5?'var(--red)':'var(--gold)';
      const el=document.getElementById('ai-result');
      el.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <strong style="font-size:13px">✨ Análisis IA</strong>
        <span style="font-size:15px;font-weight:700;color:${color}">${d.score>0?'+':''}${d.score}%</span></div>
        <p style="font-size:13px;color:var(--muted)">${d.summary}</p>`;
      el.classList.add('show');
    }
    return ST.ai_photo_score;
  }catch(e){document.getElementById('ai-analyzing').classList.remove('show');return 0;}
}

// ── CALCULAR ──────────────────────────────────────────────────
async function calcularConContacto(){
  const name=document.getElementById('contact-name').value.trim();
  const surname=document.getElementById('contact-surname').value.trim();
  const email=document.getElementById('contact-email').value.trim();
  const phone=document.getElementById('contact-phone').value.trim();
  const errBox=document.getElementById('error-contact');
  errBox.classList.remove('show');
  if(!name){errBox.innerHTML='Por favor ingresá tu nombre.';errBox.classList.add('show');return;}
  if(!email||!/^[^@]+@[^@]+\.[^@]+$/.test(email)){errBox.innerHTML='Email inválido.';errBox.classList.add('show');return;}
  ST.contact={name,surname,email,phone};
  const btn=document.getElementById('btn-calcular');
  btn.textContent='Calculando…';btn.disabled=true;
  try{
    if(ST.photos.length>0)await analyzePhotos();
    if(ST_video_frames.length>0)await analyzeVideoFrames();
    if(ST.docs.length>0)await analyzeDocs();
    const docsMeta=ST.docs.map(d=>({docType:d.docType,name:d.name}));
    const payload={
      address:document.getElementById('address').value||'',
      lat:ST.lat,lng:ST.lng,
      city:ST.city,
      zone:document.getElementById('zone_sel').value||'',
      property_type:ST.property_type,
      operation:ST.operation,
      covered_area:ST.covered_area,
      total_area:ST.total_area,
      ambientes:ST.ambientes,
      bedrooms:ST.bedrooms,
      bathrooms:ST.bathrooms,
      garages:ST.garages,
      floor:+document.getElementById('floor').value||0,
      has_elevator:ST.has_elevator,
      view:ST.view,
      orientation:ST.orientation,
      luminosity:ST.luminosity,
      age_years:+document.getElementById('age_years').value||10,
      condition:document.getElementById('condition_sel').value,
      escritura:ST.escritura,
      expensas_ars:ST.expensas_ars,
      tiene_deuda:ST.tiene_deuda,
      deuda_usd:ST.tiene_deuda?(+document.getElementById('deuda_usd').value||0):0,
      amenities:ST.amenities,
      ai_adjustment:ST.ai_photo_score||0,
      doc_notes:ST.doc_ai_notes||null,
      docs_meta:docsMeta,
      advanced:{
        ac:document.getElementById('adv_ac')?.value||'no',
        calef:document.getElementById('adv_calef')?.value||'no',
        cocina:document.getElementById('adv_cocina')?.value||'no',
        agua_caliente:document.getElementById('adv_agua')?.value||'no',
        solar:document.getElementById('adv_solar')?.value||'no',
        eficiencia:document.getElementById('adv_eficiencia')?.value||'',
        agua_corriente:document.getElementById('adv_agua_corriente')?.value||'red',
        internet:document.getElementById('adv_internet')?.value||'',
      },
    };
    console.log('Payload v5:',payload);
    const res=await fetch(API_BASE+'/valuar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const text=await res.text();
    let d;
    try{const s=text.indexOf('{');d=JSON.parse(s>=0?text.substring(s):text);}
    catch(e){throw new Error('Respuesta inesperada del servidor.');}
    if(!d.success)throw new Error(d.error||'Error en la tasación');
    renderResult(d);
    goTo(8);
    // Mostrar rating widget y código para rating
    window._tasacion_code=d.code;
    document.getElementById('rating-widget').style.display='block';
    document.getElementById('print-date').textContent=new Date().toLocaleDateString('es-AR',{day:'2-digit',month:'long',year:'numeric'});
    // Guardar payload para reenvío manual
    window._lastEmailPayload={name,surname,email,phone,result:d,property:payload};
    if(email){
      const btnR=document.getElementById('btn-reenviar-email');
      if(btnR) btnR.style.display='';
      sendResultEmail(name,surname,email,phone,d,payload,true);
    }
  }catch(e){
    console.error(e);
    document.getElementById('error-contact').innerHTML='<strong>Error:</strong> '+e.message;
    document.getElementById('error-contact').classList.add('show');
  }
  btn.textContent='✦ Ver mi tasación';btn.disabled=false;
}

// ── RENDER RESULTADO ──────────────────────────────────────────
function renderResult(d){
  const fmt=n=>Number(n).toLocaleString('es-AR');
  const fmtM=n=>'$'+Number(Math.round(n/1000)*1000).toLocaleString('es-AR');
  const name=ST.contact?(ST.contact.name+' '+(ST.contact.surname||'')).trim():'';

  document.getElementById('result-content').innerHTML=`
    ${name?`<div style="font-size:12px;color:var(--muted);margin-bottom:5px">Resultado para <strong>${name}</strong></div>`:''}
    <div class="result-code">Tasación · ${d.code} · ${new Date().toLocaleDateString('es-AR',{day:'2-digit',month:'long',year:'numeric'})}</div>
    <div class="result-zone">📍 ${d.zone.zone} · ${d.zone.city}</div>
    <div class="price-display"><div class="price-curr">USD</div><div class="price-main">${fmt(d.price.suggested)}</div></div>
    <div class="price-range">Rango: <strong>USD ${fmt(d.price.min)}</strong> — <strong>USD ${fmt(d.price.max)}</strong></div>
    ${d.deuda&&d.deuda.tiene_deuda&&d.deuda.monto_usd>0?
      `<div style="font-size:12px;color:var(--red);margin-top:3px">⚠ Deuda descontada: USD ${fmt(d.deuda.monto_usd)} · Precio bruto: USD ${fmt(d.price.gross)}</div>`:''}
    <div class="price-ars">≈ ${fmtM(d.price_ars.suggested)} ARS (a $${fmt(d.price_ars.rate)}/USD)</div>
    <div class="price-ppm2">USD ${fmt(d.price.ppm2)} / m²</div>`;

  // Factores: separar los que tienen impacto real de los "base"
  const mEntries=Object.entries(d.multipliers);
  const impactFactors=mEntries.filter(([,v])=>Math.abs(v.factor-1)>=0.005);
  const baseFactors=mEntries.filter(([,v])=>Math.abs(v.factor-1)<0.005);
  const baseNames=baseFactors.map(([k])=>k).join(' · ');

  const multsHtml=impactFactors.map(([k,v])=>{
    const pctNum=((v.factor-1)*100);
    const pct=(pctNum>0?'+':'')+pctNum.toFixed(1)+'%';
    const cls=v.factor>1?'mult-up':'mult-dn';
    return `<div class="mult-row">
      <span style="font-weight:500">${k} <small style="color:var(--muted);font-weight:400">${v.label}</small></span>
      <span class="mult-factor ${cls}" style="font-size:13px;font-weight:700">${pct}</span>
    </div>`;
  }).join('')
  + (baseNames?`<div style="font-size:11px;color:var(--muted);margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">Sin ajuste: ${baseNames}</div>`:'');

  const expHtml=d.expensas&&d.expensas.ars_mes>0?`
    <div class="result-box">
      <h4>💳 Expensas</h4>
      <div style="font-size:15px;font-weight:600">${fmtM(d.expensas.ars_mes)} <span style="font-size:11px;color:var(--muted)">ARS/mes</span></div>
      <div style="font-size:12px;color:var(--muted);margin-top:3px">≈ USD ${d.expensas.usd_mes}/mes</div>
      ${d.expensas.impacto_pct>0?`<div style="font-size:12px;color:var(--red);margin-top:4px">Impacto: -${d.expensas.impacto_pct}%</div>`:`<div style="font-size:12px;color:var(--green);margin-top:4px">Sin impacto en precio</div>`}
    </div>`:'';

  const poi=d.poi||{};
  const hasPoi=Object.values(poi).some(a=>a&&a.length>0);
  const poiCat=(title,icon,arr)=>arr&&arr.length?`<div>
    <div style="font-size:10px;font-weight:600;color:var(--muted);margin-bottom:5px">${icon} ${title}</div>
    ${arr.map(p=>`<div class="poi-item"><strong>${p.name}</strong><span class="poi-dist">${p.dist?p.dist+'m':''}</span></div>`).join('')}
  </div>`:'';
  const poiHtml=hasPoi?`<div class="result-box" style="grid-column:1/-1">
    <h4>📍 Puntos de interés cercanos</h4>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:16px">
      ${poiCat('Escuelas','🎓',poi.escuelas)}
      ${poiCat('Parques','🌳',poi.parques)}
      ${poiCat('Salud','🏥',poi.hospitales)}
      ${poiCat('Comercial','🛍',poi.shoppings)}
      ${poiCat('Transporte','🚌',poi.transporte)}
    </div></div>`:'';

  const compHtml=(()=>{
    if(!d.comparables||!d.comparables.length) return '';
    const allSameZone=d.comparables.every(c=>c.same_zone!==false);
    const crossNote=allSameZone?'':
      `<div style="font-size:11px;color:var(--muted);margin-bottom:8px;padding:6px 10px;background:rgba(255,255,255,.04);border-radius:6px;border-left:2px solid rgba(201,168,76,.4)">
        ⚠️ Sin comparables en la misma zona — se muestran propiedades de precio similar de la ciudad
      </div>`;
    const rows=d.comparables.map(c=>{
      const name=c.label||c.address||'Propiedad comparable';
      const link=c.url?`<a href="${c.url}" target="_blank" rel="noopener" style="color:var(--gold);font-size:10px;margin-left:6px">ver →</a>`:'';
      const zoneBadge=(!c.same_zone&&c.zone)?
        `<span style="color:var(--muted);font-size:10px;opacity:.7"> · ${c.zone.replace(/_/g,' ')} ↗</span>`
        : (c.zone?`<span style="color:var(--muted);font-size:10px"> · ${c.zone.replace(/_/g,' ')}</span>`:'');
      return `<div class="comp-row">
        <div><div style="font-weight:500">${name}${link}</div>
          <div style="color:var(--muted);font-size:11px">${c.area?c.area+'m² · ':''} ${fmt(c.ppm2)}/m²${zoneBadge}</div></div>
        <div style="font-weight:700;color:var(--gold)">USD ${fmt(c.price)}</div>
      </div>`;
    }).join('');
    return `<div class="result-box" style="grid-column:1/-1">
      <h4>📊 Comparables en base de datos</h4>${crossNote}${rows}</div>`;
  })();

  document.getElementById('result-details').innerHTML=`
    <div class="result-box" style="grid-column:1/-1">
      <h4>Factores de ajuste</h4>${multsHtml}
      <div class="mult-row" style="margin-top:6px;font-weight:700">
        <span>Factor total</span><span>${(d.total_factor*100).toFixed(1)}%</span>
      </div></div>
    <div class="result-box">
      <h4>Rango de mercado</h4>
      <div style="font-size:12px;color:var(--muted);margin-bottom:8px">
        ${d.market_data.used?`✓ ${d.market_data.count} comparables reales`:'Precios de configuración'}
      </div>
      <div style="font-size:13px;margin-bottom:4px"><span style="color:var(--green);font-weight:700">USD ${fmt(d.price.min)}</span> mínimo</div>
      <div style="font-size:15px;margin-bottom:4px"><span style="color:var(--gold);font-weight:700">USD ${fmt(d.price.suggested)}</span> sugerido</div>
      <div style="font-size:13px"><span style="color:var(--red);font-weight:700">USD ${fmt(d.price.max)}</span> máximo</div>
      <div class="confidence-bar"><div class="confidence-fill" style="width:72%"></div></div></div>
    <div class="result-box">
      <h4>En pesos argentinos</h4>
      <div style="font-size:20px;font-weight:700;color:var(--gold);margin-bottom:6px">${fmtM(d.price_ars.suggested)}</div>
      <div style="font-size:12px;color:var(--muted)">Mín: ${fmtM(d.price_ars.min)}</div>
      <div style="font-size:12px;color:var(--muted)">Máx: ${fmtM(d.price_ars.max)}</div>
      <div style="font-size:11px;color:var(--muted);margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">$${fmt(d.price_ars.rate)}/USD</div></div>
    ${expHtml}${poiHtml}${compHtml}
    <div class="result-box" style="grid-column:1/-1">
      <h4>⚠ Aviso legal</h4>
      <p style="font-size:12px;color:var(--muted);line-height:1.6">Esta tasación es orientativa y no constituye oferta ni documento legal. Para una valuación oficial contactar a un martillero matriculado.</p></div>`;

  const baseUrl=window.location.href.replace(/\/[^/]*$/,'/');
  document.getElementById('embed-code').textContent=`<script src="${baseUrl}embed.js"><\/script>\n<div data-tasador data-ciudad="${ST.city}"></div>`;
}

// ── NAVEGACIÓN ────────────────────────────────────────────────
function goTo(n){
  ST.step=n;
  document.querySelectorAll('.card').forEach((c,i)=>c.classList.toggle('active',i===n-1));
  document.querySelectorAll('.step-dot').forEach((d,i)=>{
    d.classList.toggle('active',i===n-1);
    d.classList.toggle('done',i<n-1);
    d.querySelector('.step-num').textContent=i<n-1?'✓':(i+1);
  });
  document.getElementById('progress-wrap').style.display=n>7?'none':'flex';
  window.scrollTo(0,0);
}
function next(from){goTo(from+1);}
function prev(from){goTo(from-1);}

function showEmbed(){document.getElementById('embed-modal').classList.add('open');}

// ── ENVÍO DE EMAIL ───────────────────────────────────────────
async function sendResultEmail(name,surname,email,phone,result,property,auto=false){
  const btn=document.getElementById('btn-reenviar-email');
  if(btn){btn.textContent='Enviando…';btn.disabled=true;}
  try{
    const resp=await fetch(API_BASE+'/send_email.php',{method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({name,surname,email,phone,result,property})});
    const j=await resp.json();
    if(j.success){
      showToast('📧 Enviado a '+email);
      if(btn){btn.textContent='✓ Email enviado';btn.style.borderColor='#4af7a0';btn.style.color='#4af7a0';}
    } else {
      const errMsg=j.debug?.admin?.err||j.debug?.user?.err||j.error||'Error SMTP';
      if(auto) console.warn('Email falló:',errMsg,j);
      showToast('⚠️ Email no enviado: '+errMsg.substring(0,60),4000);
      if(btn){btn.textContent='↺ Reintentar email';btn.disabled=false;}
    }
  }catch(e){
    if(auto) console.warn('Email fetch error:',e);
    showToast('⚠️ No se pudo conectar con el servidor de email',3500);
    if(btn){btn.textContent='↺ Reintentar email';btn.disabled=false;}
  }
}
function reenviarEmail(){
  const p=window._lastEmailPayload;
  if(!p)return;
  sendResultEmail(p.name,p.surname,p.email,p.phone,p.result,p.property,false);
}

// ── RATING DE PRECIO ─────────────────────────────────────────
async function sendRating(val){
  const btns=document.querySelectorAll('.rating-btn');
  btns.forEach(b=>{b.disabled=true;});
  const clicked=Array.from(btns).find(b=>b.onclick.toString().includes(`'${val}'`));
  if(clicked)clicked.classList.add('selected');
  document.getElementById('rating-thanks').style.display='block';
  try{
    await fetch(API_BASE+'/rating.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({code:window._tasacion_code||'',rating:val})});
  }catch(e){}
}

// ── IMPRIMIR ─────────────────────────────────────────────────
function printResult(){
  document.getElementById('print-header').style.display='flex';
  window.print();
  setTimeout(()=>{document.getElementById('print-header').style.display='none';},500);
}

function resetWizard(){
  ST={step:1,lat:null,lng:null,property_type:'departamento',operation:'venta',
    city:'santa_fe_capital',covered_area:65,total_area:65,
    ambientes:3,bedrooms:2,bathrooms:1,garages:0,floor:1,has_elevator:false,
    view:'exterior',orientation:'norte',luminosity:'normal',
    escritura:'escriturado',expensas_ars:0,tiene_deuda:false,deuda_usd:0,
    amenities:{},photos:[],ai_photo_score:null,contact:null,
    docs:[],doc_ai_notes:null};
  document.getElementById('toggle-elevator').classList.remove('on');
  document.getElementById('toggle-deuda').classList.remove('on');
  document.getElementById('deuda-panel').style.display='none';
  document.querySelectorAll('.amen-check').forEach(el=>{el.classList.remove('checked');el.querySelector('input').checked=false;});
  document.getElementById('photo-preview').innerHTML='';
  document.getElementById('photo-count').textContent='';
  document.getElementById('ai-result').classList.remove('show');
  document.getElementById('ai-result').innerHTML='';
  document.getElementById('doc-list').innerHTML='';
  document.getElementById('doc-ai-result').innerHTML='';
  document.getElementById('video-frames-preview').innerHTML='';
  document.getElementById('video-status').textContent='';
  document.getElementById('video-ai-result').innerHTML='';
  document.getElementById('escritura-status').textContent='';
  document.getElementById('escritura-ai-result').innerHTML='';
  document.getElementById('rating-widget').style.display='none';
  document.querySelectorAll('.rating-btn').forEach(b=>{b.disabled=false;b.classList.remove('selected');});
  document.getElementById('rating-thanks').style.display='none';
  ST_video_frames=[];ST_escritura_imgs=[];
  // Reset deudas
  ['deuda_hipoteca','deuda_embargo','deuda_impuestos','deuda_expensas','deuda_multas'].forEach(id=>{const el=document.getElementById(id);if(el)el.value=0;});
  const dt=document.getElementById('deuda_total_disp');if(dt)dt.textContent='USD 0';
  // Reset avanzados
  ['adv_ac','adv_calef','adv_cocina','adv_agua','adv_solar','adv_eficiencia','adv_agua_corriente','adv_internet'].forEach(id=>{const el=document.getElementById(id);if(el)el.selectedIndex=0;});
  document.querySelectorAll('.adv-body.open').forEach(el=>el.classList.remove('open'));
  document.querySelectorAll('.adv-toggle.open').forEach(el=>el.classList.remove('open'));
  goTo(1);
}
function showToast(msg,typeOrMs){
  const t=document.getElementById('toast');
  const isErr=typeOrMs==='error';
  const ms=typeof typeOrMs==='number'?typeOrMs:4000;
  t.textContent=msg;t.className='toast show'+(isErr?' error':'');
  clearTimeout(t._timer);t._timer=setTimeout(()=>t.classList.remove('show'),ms);
}

window.addEventListener('DOMContentLoaded',()=>{
  initMap();initAmenities();onCityChange();
  ['covered_area','total_area','ambientes','bedrooms','bathrooms','garages'].forEach(id=>{
    const el=document.getElementById(id);
    if(el)updateSlider(id,['covered_area','total_area'].includes(id)?'m²':'');
  });
});
</script>

<!-- ── PWA: Service Worker + Banner de instalación ──────────────────────── -->
<style>
#pwa-banner{
  position:fixed;bottom:0;left:0;right:0;z-index:9999;
  background:#141720;border-top:1px solid #2a2f45;
  padding:12px 16px;display:flex;align-items:center;gap:12px;
  box-shadow:0 -4px 24px rgba(0,0,0,.5);
  transform:translateY(100%);transition:transform .35s cubic-bezier(.4,0,.2,1);
}
#pwa-banner.show{transform:translateY(0)}
#pwa-banner img{width:40px;height:40px;border-radius:10px;flex-shrink:0}
#pwa-banner-text{flex:1;font-size:13px;color:#e8e8f0;line-height:1.4}
#pwa-banner-text strong{color:#c9a84c;display:block;margin-bottom:2px}
#pwa-install{padding:9px 18px;background:#c9a84c;color:#0d0f14;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap;font-family:inherit}
#pwa-dismiss{padding:8px;background:none;border:none;color:#7a7a9a;cursor:pointer;font-size:18px;line-height:1;flex-shrink:0}
</style>

<div id="pwa-banner" role="dialog" aria-label="Instalar aplicación">
  <img src="icons/icon-192.png" alt="TasadorIA">
  <div id="pwa-banner-text">
    <strong>Instalar TasadorIA</strong>
    Agregá la app a tu pantalla de inicio para usarla sin internet
  </div>
  <button id="pwa-install">Instalar</button>
  <button id="pwa-dismiss" aria-label="Cerrar">✕</button>
</div>

<script>
// ── Registro del Service Worker ───────────────────────────────────────────────
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js')
      .then(reg => {
        console.info('[TasadorIA] SW registrado:', reg.scope);
        // Notificar al SW que puede activarse inmediatamente
        if (reg.waiting) reg.waiting.postMessage('skipWaiting');
      })
      .catch(err => console.warn('[TasadorIA] SW error:', err));
  });
}

// ── Banner de instalación (beforeinstallprompt) ───────────────────────────────
let _deferredPrompt = null;
const banner   = document.getElementById('pwa-banner');
const btnInst  = document.getElementById('pwa-install');
const btnDismiss = document.getElementById('pwa-dismiss');

window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();
  _deferredPrompt = e;
  // Mostrar banner solo si el usuario no lo descartó antes
  if (!localStorage.getItem('pwa-dismissed')) {
    setTimeout(() => banner.classList.add('show'), 2500);
  }
});

btnInst.addEventListener('click', async () => {
  if (!_deferredPrompt) return;
  banner.classList.remove('show');
  _deferredPrompt.prompt();
  const { outcome } = await _deferredPrompt.userChoice;
  console.info('[TasadorIA] PWA install:', outcome);
  _deferredPrompt = null;
});

btnDismiss.addEventListener('click', () => {
  banner.classList.remove('show');
  localStorage.setItem('pwa-dismissed', '1');
});

// iOS: mostrar instrucción manual (Safari no soporta beforeinstallprompt)
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
if (isIos && !isStandalone && !localStorage.getItem('pwa-dismissed')) {
  setTimeout(() => {
    banner.querySelector('#pwa-banner-text').innerHTML =
      '<strong>Instalar en iPhone/iPad</strong>Tocá <strong>Compartir</strong> → <strong>"Agregar a inicio"</strong>';
    btnInst.style.display = 'none';
    banner.classList.add('show');
  }, 3000);
}

// Si ya está instalada como PWA, ocultar banner para siempre
if (isStandalone) banner.style.display = 'none';
</script>
</body>
</html>
