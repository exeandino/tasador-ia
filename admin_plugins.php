<?php
/**
 * TasadorIA — admin_plugins.php
 *
 * Modo CENTRAL   (site_url == marketplace_url o no configurado):
 *   El dueño del marketplace. Gestiona catálogo, ventas, ZIPs.
 *
 * Modo DISTRIBUIDO (marketplace_url apunta a otro servidor):
 *   Instalación de tercero (descargó de GitHub).
 *   Marketplace carga desde el servidor central.
 *   Comprar redirige al central para pagar.
 *   Solo ve: Marketplace + Instalados + Instalar ZIP.
 */
session_start();
$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];
if (!defined('ADMIN_PASS')) define('ADMIN_PASS', $cfg['admin_pass'] ?? $cfg['admin_password'] ?? 'anper2025');
if (!isset($_SESSION['ta_admin'])) {
    if (($_POST['p'] ?? '') === ADMIN_PASS) { $_SESSION['ta_admin'] = true; }
    else {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Plugins — TasadorIA</title>
        <style>*{box-sizing:border-box}body{font-family:system-ui;background:#0f0f0f;color:#e0e0e0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
        form{background:#1a1a1a;padding:32px;border-radius:14px;border:1px solid #2a2a2a;min-width:280px;text-align:center}
        h2{margin:0 0 4px;color:#c9a84c}p{color:#555;font-size:11px;margin:0 0 20px}
        input{width:100%;padding:10px 14px;background:#111;border:1px solid #333;border-radius:8px;color:#fff;font-size:14px;margin-bottom:12px}
        button{width:100%;padding:10px;background:#c9a84c;color:#000;font-weight:700;border:none;border-radius:8px;cursor:pointer}</style></head>
        <body><form method="post"><h2>&#x1F50C; TasadorIA</h2><p>Panel de Plugins</p>
        <input type="password" name="p" placeholder="Contrasena admin" autofocus><button>Ingresar</button></form></body></html>';
        exit;
    }
}

$siteUrl        = rtrim($cfg['site_url']        ?? '', '/');
$marketplaceUrl = rtrim($cfg['marketplace_url'] ?? 'https://anperprimo.com/tasador', '/');
$usdRate        = (float)($cfg['ars_usd_rate']  ?? 1450);
$brandName      = $cfg['brand_name'] ?? 'TasadorIA';

// Modo central: este servidor ES el marketplace.
// Se detecta por configuración explícita (site_url == marketplace_url)
// o automáticamente por HTTP_HOST si site_url no está configurado aún.
$httpHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$mktHost  = strtolower(parse_url($marketplaceUrl, PHP_URL_HOST) ?? '');
$isCentral = ($siteUrl !== '' && $siteUrl === $marketplaceUrl)
          || (empty($siteUrl) && $httpHost !== '' && $httpHost === $mktHost);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Plugins &mdash; <?=htmlspecialchars($brandName)?></title>
<style>
:root{--bg:#0f0f0f;--bg2:#181818;--bg3:#222;--surface:#1e1e1e;--border:#2a2a2a;--gold:#c9a84c;--text:#e0e0e0;--muted:#888;--green:#4caf50;--red:#f44336;--blue:#4a8ff7;--font:system-ui,-apple-system,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
.tabs{display:flex;gap:2px;padding:0 24px;border-bottom:1px solid var(--border);background:var(--bg2);flex-shrink:0}
.tab{padding:12px 18px;font-size:12px;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;transition:.15s;white-space:nowrap}
.tab.active{color:var(--gold);border-bottom-color:var(--gold)}
.tab:hover:not(.active){color:var(--text)}
.panel{display:none;flex:1;overflow-y:auto;padding:24px}.panel.active{display:block}
.sec-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.sec-header h2{font-size:16px;font-weight:700}.sec-header p{font-size:11px;color:var(--muted)}.sec-htext{flex:1}
.market-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:32px}
.mkt-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px;transition:.2s}
.mkt-card:hover{border-color:#333}.mkt-card.installed{border-color:rgba(76,175,80,.35);background:#0e1a0e}
.mkt-card .icon{font-size:26px;margin-bottom:10px}.mkt-card h3{font-size:13px;font-weight:700;margin-bottom:5px;line-height:1.3}
.mkt-card .desc{font-size:11px;color:var(--muted);line-height:1.55;margin-bottom:14px;min-height:44px}
.mkt-card .price{font-size:20px;font-weight:800;color:var(--gold);margin-bottom:12px}
.mkt-card .price span{font-size:11px;color:var(--muted);font-weight:400}
.badge-inst{display:inline-block;padding:4px 12px;background:rgba(76,175,80,.15);color:var(--green);border:1px solid rgba(76,175,80,.3);border-radius:6px;font-size:11px;font-weight:600}
.badge-nzip{display:inline-block;padding:3px 8px;background:rgba(255,152,0,.1);color:#ff9800;border:1px solid rgba(255,152,0,.3);border-radius:5px;font-size:10px;margin-top:8px}
.btn-buy{width:100%;padding:9px;background:var(--gold);color:#000;font-weight:700;border:none;border-radius:8px;cursor:pointer;font-size:13px}
.btn-buy:disabled{background:#333;color:var(--muted);cursor:default}
.plugins-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.plugin-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px}
.plugin-card.active-pl{border-color:rgba(76,175,80,.4);background:#0f1e0f}
.plugin-card h3{font-size:13px;font-weight:700;margin-bottom:4px}
.plugin-card .ver{font-size:10px;color:var(--muted);margin-bottom:8px}
.plugin-card .dsc{font-size:11px;color:var(--muted);margin-bottom:14px;line-height:1.5}
.plugin-card .acts{display:flex;gap:8px;flex-wrap:wrap}
.btn{padding:6px 12px;border-radius:7px;border:1px solid var(--border);background:var(--bg3);color:var(--text);font-size:11px;cursor:pointer;font-weight:600;font-family:var(--font)}
.btn-sm{font-size:10px;padding:3px 8px}
.btn-activate{border-color:rgba(76,175,80,.5);color:var(--green)}
.btn-deactivate{border-color:rgba(255,152,0,.5);color:#ff9800}
.btn-danger{border-color:rgba(244,67,54,.4);color:var(--red)}
.btn-primary{background:var(--gold);color:#000;border-color:var(--gold);font-weight:700}
.btn-primary:hover{opacity:.85}
.catalog-table,.sales-table{width:100%;border-collapse:collapse;font-size:12px}
.catalog-table th,.sales-table th{color:var(--muted);font-weight:600;padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);font-size:10px;text-transform:uppercase;letter-spacing:.4px;background:var(--bg2)}
.catalog-table td,.sales-table td{padding:9px 10px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle}
.catalog-table tr:hover td,.sales-table tr:hover td{background:rgba(255,255,255,.02)}
.badge-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.badge-status.approved{background:rgba(76,175,80,.15);color:var(--green);border:1px solid rgba(76,175,80,.3)}
.badge-status.pending{background:rgba(255,152,0,.12);color:#ff9800;border:1px solid rgba(255,152,0,.3)}
.badge-status.rejected,.badge-status.cancelled{background:rgba(244,67,54,.1);color:var(--red);border:1px solid rgba(244,67,54,.3)}
.stats-row{display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 18px;min-width:130px}
.stat-card .val{font-size:24px;font-weight:800;color:var(--gold)}
.stat-card .lbl{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-top:2px}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center}
.modal-box{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:28px;width:90%;max-width:460px;max-height:90vh;overflow-y:auto}
.modal-box h3{font-size:15px;font-weight:700;margin-bottom:18px;color:var(--gold)}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:11px;color:var(--muted);margin-bottom:5px}
.form-group input,.form-group textarea,.form-group select{width:100%;padding:8px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:7px;color:var(--text);font-size:13px;font-family:var(--font)}
.form-group textarea{resize:vertical;min-height:80px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-actions{display:flex;gap:10px;margin-top:20px;justify-content:flex-end}
.upload-zone{border:2px dashed var(--border);border-radius:10px;padding:28px;text-align:center;cursor:pointer;margin-bottom:12px}
.upload-zone:hover{border-color:var(--gold)}
.upload-zone p{font-size:12px;color:var(--muted);margin-top:8px}
#buy-modal .modal-box{max-width:400px;text-align:center}
.gw-btn{width:100%;padding:13px;border-radius:10px;border:1px solid var(--border);background:var(--bg3);color:var(--text);font-size:14px;font-weight:700;cursor:pointer;margin-bottom:10px;font-family:var(--font)}
.gw-btn.mp{border-color:rgba(0,185,128,.4);color:#00b980}
.gw-btn.stripe{border-color:rgba(99,102,241,.4);color:#818cf8}
.gw-btn:disabled{opacity:.4;cursor:default}
.empty-state{text-align:center;padding:40px;color:var(--muted);font-size:13px}
.tag-active{color:var(--green);font-size:12px}.tag-inactive{color:var(--red);font-size:12px}
input[type="file"]{display:none}
.info-box{background:rgba(74,143,247,.08);border:1px solid rgba(74,143,247,.25);border-radius:8px;padding:12px 16px;font-size:12px;color:#a0c4ff;margin-bottom:20px;line-height:1.6}
.warn-box{background:rgba(201,168,76,.07);border:1px solid rgba(201,168,76,.25);border-radius:8px;padding:12px 16px;font-size:12px;color:#e8d08a;margin-bottom:20px;line-height:1.6}
.central-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.3);border-radius:8px;padding:4px 12px;font-size:11px;color:var(--gold);font-weight:600}
</style>
</head>
<body>
<?php $currentPanel = 'plugins'; require __DIR__.'/includes/admin_topnav.php'; ?>

<div class="tabs">
  <div class="tab active" data-tab="marketplace">&#x1F6CD; Marketplace</div>
  <div class="tab" data-tab="installed">&#x1F50C; Instalados</div>
  <?php if ($isCentral): ?>
  <div class="tab" data-tab="sales">&#x1F4B3; Ventas</div>
  <div class="tab" data-tab="catalog">&#x2699;&#xFE0F; Cat&#225;logo</div>
  <?php endif; ?>
  <div class="tab" data-tab="upload">&#x1F4E4; Instalar ZIP</div>
</div>

<!-- MARKETPLACE -->
<div class="panel active" id="panel-marketplace">
  <div class="sec-header">
    <div class="sec-htext">
      <h2>Marketplace de plugins</h2>
      <?php if ($isCentral): ?>
        <p>Modo central &mdash; sos el vendedor. Los pagos van a tu cuenta MP / Stripe.</p>
      <?php else: ?>
        <p>Plugins disponibles en <strong><?=htmlspecialchars($marketplaceUrl)?></strong> &mdash; el pago se procesa all&#225;</p>
      <?php endif; ?>
    </div>
    <?php if ($isCentral): ?>
      <span class="central-badge">&#11088; Servidor central</span>
      <button class="btn btn-primary" onclick="openModal('add-modal')">+ Nuevo plugin</button>
    <?php endif; ?>
  </div>
  <div class="market-grid" id="market-grid"><div class="empty-state">Cargando...</div></div>
</div>

<!-- INSTALADOS -->
<div class="panel" id="panel-installed">
  <div class="sec-header"><div class="sec-htext"><h2>Plugins instalados</h2><p>M&#243;dulos activos en este servidor</p></div></div>
  <div class="info-box">
    &#x1F4E6; Instal&#225; plugins desde <strong>Instalar ZIP</strong>.
    <?php if (!$isCentral): ?>
    Compr&#225;los en el <a href="<?=htmlspecialchars($marketplaceUrl)?>/admin_plugins.php" target="_blank" style="color:var(--gold)">marketplace central</a>, descarg&#225; el ZIP e inst&#225;lalo aqu&#237;.
    <?php endif; ?>
  </div>
  <div class="plugins-grid" id="plugins-grid"><div class="empty-state">Cargando...</div></div>
</div>

<?php if ($isCentral): ?>
<!-- VENTAS (solo central) -->
<div class="panel" id="panel-sales">
  <div class="sec-header">
    <div class="sec-htext"><h2>Ventas de plugins</h2><p>Historial de compras de tus clientes</p></div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <select id="sales-status-filter" class="btn" style="font-weight:400" onchange="loadSales()">
        <option value="">Todas</option><option value="approved">Aprobadas</option>
        <option value="pending">Pendientes</option><option value="rejected">Rechazadas</option>
      </select>
      <button class="btn btn-primary" onclick="openManualSaleModal()">+ Venta manual</button>
      <button class="btn" onclick="loadSales()">&#8635;</button>
    </div>
  </div>
  <div class="stats-row" id="sales-stats"></div>
  <div style="overflow-x:auto">
    <table class="sales-table">
      <thead><tr><th>Fecha</th><th>Plugin</th><th>Comprador</th><th>Gateway</th><th>USD</th><th>Estado</th><th>DL</th><th>Acciones</th></tr></thead>
      <tbody id="sales-body"><tr><td colspan="8" style="padding:20px;text-align:center;color:var(--muted)">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- CATÁLOGO (solo central) -->
<div class="panel" id="panel-catalog">
  <div class="sec-header">
    <div class="sec-htext"><h2>Gestionar cat&#225;logo</h2><p>Configur&#225; qu&#233; plugins vend&#233;s, precios y ZIPs</p></div>
    <button class="btn btn-primary" onclick="openModal('add-modal')">+ Agregar plugin</button>
  </div>
  <div class="warn-box">
    &#x1F4A1; Los instaladores que bajaron TasadorIA de GitHub ver&#225;n este cat&#225;logo en su admin y comprar&#225;n aqu&#237;. Aseg&#250;rate de subir el ZIP antes de activar cada plugin.
  </div>
  <div style="overflow-x:auto">
    <table class="catalog-table">
      <thead><tr><th>Icono</th><th>Slug</th><th>Nombre</th><th>USD</th><th>ZIP venta</th><th>Estado</th><th>Orden</th><th>Acciones</th></tr></thead>
      <tbody id="catalog-body"><tr><td colspan="8" style="padding:16px;text-align:center;color:var(--muted)">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- INSTALAR ZIP -->
<div class="panel" id="panel-upload">
  <div class="sec-header"><div class="sec-htext"><h2>Instalar plugin en este servidor</h2><p>Sub&#237; un ZIP para activarlo</p></div></div>
  <div class="info-box">
    &#x1F4CB; El ZIP debe tener una carpeta ra&#237;z con el slug del plugin y un <code>plugin.json</code>.<br>
    Ej: <code>mi-plugin/plugin.json</code> &middot; <code>mi-plugin/main.php</code>
  </div>
  <div style="max-width:480px">
    <div class="upload-zone" id="upload-zone" onclick="document.getElementById('zip-input').click()">
      <div style="font-size:36px">&#x1F4E6;</div>
      <strong style="font-size:14px">Clic para seleccionar ZIP</strong>
      <p>o arrastre el archivo aqu&#237;</p>
      <p id="upload-filename" style="color:var(--gold);margin-top:8px"></p>
    </div>
    <input type="file" id="zip-input" accept=".zip">
    <button class="btn btn-primary" onclick="uploadInstallZip()" style="width:100%;padding:11px;font-size:14px">&#x1F4E6; Instalar plugin</button>
    <div id="upload-status" style="margin-top:12px;font-size:12px"></div>
  </div>
</div>

<!-- MODAL: Comprar (redirige al central si no es central) -->
<div class="modal-overlay" id="buy-modal">
  <div class="modal-box">
    <h3 id="bm-title">Comprar plugin</h3>
    <div style="font-size:24px;font-weight:800;color:var(--text);margin-bottom:2px" id="bm-price-usd"></div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:20px" id="bm-price-ars"></div>
    <div class="form-group"><label>Tu email</label><input type="email" id="bm-email" placeholder="nombre@empresa.com"></div>
    <div class="form-group"><label>Nombre (opcional)</label><input type="text" id="bm-name" placeholder="Tu nombre"></div>
    <input type="hidden" id="bm-slug"><input type="hidden" id="bm-price">
    <div id="bm-error" style="color:var(--red);font-size:12px;margin-bottom:10px;display:none"></div>
    <button class="gw-btn mp" id="btn-mp" onclick="doBuy('mercadopago')">&#x1F1E6;&#x1F1F7; Pagar con Mercado Pago (ARS)</button>
    <button class="gw-btn stripe" id="btn-stripe" onclick="doBuy('stripe')">&#x1F30E; Pagar con Stripe (USD)</button>
    <button class="btn" onclick="closeModal('buy-modal')" style="width:100%;margin-top:4px">Cancelar</button>
  </div>
</div>

<?php if ($isCentral): ?>
<!-- MODAL: Agregar/editar plugin (solo central) -->
<div class="modal-overlay" id="add-modal">
  <div class="modal-box">
    <h3 id="add-modal-title">Agregar plugin al cat&#225;logo</h3>
    <input type="hidden" id="edit-id">
    <div class="form-row">
      <div class="form-group"><label>Icono (emoji)</label><input type="text" id="edit-icon" value="&#x1F50C;" maxlength="6"></div>
      <div class="form-group"><label>Slug (&#250;nico)</label><input type="text" id="edit-slug" placeholder="mi-plugin"></div>
    </div>
    <div class="form-group"><label>Nombre</label><input type="text" id="edit-name" placeholder="Nombre del plugin"></div>
    <div class="form-group"><label>Descripci&#243;n</label><textarea id="edit-desc" placeholder="Descripcion breve (2-3 lineas)"></textarea></div>
    <div class="form-row">
      <div class="form-group"><label>Precio USD</label><input type="number" id="edit-price" min="0" step="1" value="19"></div>
      <div class="form-group"><label>Orden</label><input type="number" id="edit-order" min="1" max="99" value="99"></div>
    </div>
    <div class="form-group"><label>Estado</label>
      <select id="edit-active"><option value="1">Activo (visible)</option><option value="0">Inactivo (oculto)</option></select>
    </div>
    <div id="add-modal-error" style="color:var(--red);font-size:12px;margin-top:8px;display:none"></div>
    <div class="form-actions">
      <button class="btn" onclick="closeModal('add-modal')">Cancelar</button>
      <button class="btn btn-primary" onclick="savePlugin()">Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL: ZIP para venta (solo central) -->
<div class="modal-overlay" id="zip-modal">
  <div class="modal-box">
    <h3>ZIP para venta &mdash; <span id="zip-modal-slug" style="color:var(--text);font-weight:400"></span></h3>
    <div class="info-box" style="font-size:11px">Este ZIP es lo que el comprador descarga. Debe contener todo lo necesario para instalar el plugin.</div>
    <div class="upload-zone" onclick="document.getElementById('cat-zip-input').click()">
      <div style="font-size:28px">&#x1F4E6;</div><strong>Clic para seleccionar ZIP</strong>
      <p id="cat-zip-filename" style="color:var(--gold);margin-top:6px;font-size:13px"></p>
    </div>
    <input type="file" id="cat-zip-input" accept=".zip">
    <div id="cat-zip-error" style="color:var(--red);font-size:12px;margin-top:8px;display:none"></div>
    <div class="form-actions">
      <button class="btn" onclick="closeModal('zip-modal')">Cancelar</button>
      <button class="btn btn-primary" onclick="uploadCatalogZip()">Subir ZIP</button>
    </div>
  </div>
</div>

<!-- MODAL: Venta manual (solo central) -->
<div class="modal-overlay" id="manual-sale-modal">
  <div class="modal-box">
    <h3>Registrar venta manual</h3>
    <div class="info-box" style="font-size:11px">Para pagos en efectivo, transferencia, etc. Genera un link de descarga v&#225;lido 30 d&#237;as.</div>
    <div class="form-group"><label>Plugin</label><select id="ms-slug"></select></div>
    <div class="form-group"><label>Email del comprador</label><input type="email" id="ms-email" placeholder="cliente@email.com"></div>
    <div class="form-group"><label>Nombre (opcional)</label><input type="text" id="ms-name" placeholder="Nombre"></div>
    <div class="form-group"><label>Precio cobrado USD (0 = precio cat&#225;logo)</label><input type="number" id="ms-price" min="0" step="1" value="0"></div>
    <div id="ms-result" style="display:none;margin-top:12px"></div>
    <div id="ms-error" style="color:var(--red);font-size:12px;margin-top:8px;display:none"></div>
    <div class="form-actions">
      <button class="btn" onclick="closeModal('manual-sale-modal')">Cerrar</button>
      <button class="btn btn-primary" onclick="submitManualSale()">Registrar y generar link</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const USD_RATE       = <?= (float)($cfg['ars_usd_rate'] ?? 1450) ?>;
const MARKETPLACE    = <?= json_encode($marketplaceUrl) ?>;
const IS_CENTRAL     = <?= $isCentral ? 'true' : 'false' ?>;
let _catPlugins = [];

document.addEventListener('DOMContentLoaded', () => {
  // Tabs
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const n = tab.dataset.tab;
      document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
      document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById('panel-'+n).classList.add('active');
      if (n==='marketplace'||n==='catalog') loadMarketplace();
      if (n==='installed') loadInstalled();
      if (n==='sales') loadSales();
    });
  });

  // Drag & drop install
  const zone = document.getElementById('upload-zone');
  zone.addEventListener('dragover', e=>{e.preventDefault();zone.style.borderColor='var(--gold)'});
  zone.addEventListener('dragleave', ()=>{zone.style.borderColor='var(--border)'});
  zone.addEventListener('drop', e=>{
    e.preventDefault();zone.style.borderColor='var(--border)';
    const f=e.dataTransfer.files[0];
    if(f){window._dropFile=f;document.getElementById('upload-filename').textContent=f.name}
  });
  document.getElementById('zip-input').addEventListener('change',e=>{
    const f=e.target.files[0];
    if(f){document.getElementById('upload-filename').textContent=f.name;window._dropFile=null}
  });
  if (document.getElementById('cat-zip-input')) {
    document.getElementById('cat-zip-input').addEventListener('change',e=>{
      const f=e.target.files[0];
      if(f) document.getElementById('cat-zip-filename').textContent=f.name;
    });
  }
  document.querySelectorAll('.modal-overlay').forEach(m=>{
    m.addEventListener('click',e=>{if(e.target===m)m.style.display='none'});
  });

  loadMarketplace();
  loadInstalled();
});

// ── MARKETPLACE ─────────────────────────────────────────────────────────────
async function loadMarketplace() {
  const grid = document.getElementById('market-grid');
  grid.innerHTML = '<div class="empty-state" style="color:var(--muted)">Cargando cat&aacute;logo&hellip;</div>';
  try {
    // Catálogo siempre desde el servidor central (puede ser local si IS_CENTRAL)
    const mktUrl = MARKETPLACE + '/api/plugin_prices.php?action=list';
    let mr, ir;
    try {
      [mr, ir] = await Promise.all([
        fetch(mktUrl),
        fetch('api/plugin_manager.php?action=list'),
      ]);
    } catch(fetchErr) {
      throw new Error('No se pudo conectar: ' + fetchErr.message);
    }
    if (!mr.ok) {
      throw new Error(`El servidor central devolvió HTTP ${mr.status}.<br><small>Verificá que <code>api/plugin_prices.php</code> esté subido al servidor central y que hayas ejecutado el <code>install.sql</code> actualizado.</small>`);
    }
    const mktText = await mr.text();
    if (!mktText || !mktText.trim()) {
      throw new Error('El servidor central devolvió respuesta vacía.<br><small>Verificá que <code>api/plugin_prices.php</code> esté subido al servidor y que <code>install.sql</code> se haya ejecutado.</small>');
    }
    let mkt, inst;
    try {
      mkt = JSON.parse(mktText);
    } catch(pe) {
      const preview = mktText.substring(0, 300).replace(/</g,'&lt;');
      throw new Error(`Respuesta inválida del catálogo (${pe.message}).<br><small>${preview}</small>`);
    }
    try { inst = await ir.json(); } catch(e) { inst = {plugins:[]}; }
    _catPlugins = mkt.plugins || [];
    const isl   = (inst.plugins || []).map(p => p.slug);
    renderMarket(_catPlugins, isl);
    if (IS_CENTRAL) renderCatalog(_catPlugins);
  } catch(e) {
    grid.innerHTML = `<div class="empty-state">⚠ ${e.message}</div>`;
  }
}

function renderMarket(plugins, isl) {
  const g = document.getElementById('market-grid');
  const active = plugins.filter(p => p.active);
  if (!active.length) {
    g.innerHTML = '<div class="empty-state">Sin plugins en el cat&aacute;logo a&uacute;n.</div>';
    return;
  }
  g.innerHTML = active.map(p => {
    const ins = isl.includes(p.slug);
    return `<div class="mkt-card ${ins?'installed':''}">
      <div class="icon">${p.icon}</div>
      <h3>${esc(p.name)}</h3>
      <div class="desc">${esc(p.description||'')}</div>
      <div class="price">USD ${(+p.price_usd).toFixed(0)} <span>&middot; ARS ${Math.round(p.price_usd*USD_RATE).toLocaleString('es-AR')}</span></div>
      ${ins
        ? '<span class="badge-inst">&#x2713; Instalado</span>'
        : p.has_zip
          ? `<button class="btn-buy" onclick="buyPlugin('${esc(p.slug)}','${esc(p.name)}',${p.price_usd})">Comprar &rarr;</button>`
          : '<button class="btn-buy" disabled>Pr&oacute;ximamente</button>'
      }
      ${!p.has_zip && !ins ? '<div class="badge-nzip">&#x26A0; ZIP no cargado a&uacute;n</div>' : ''}
    </div>`;
  }).join('');
}

// ── INSTALADOS ───────────────────────────────────────────────────────────────
async function loadInstalled() {
  try {
    const j = await (await fetch('api/plugin_manager.php?action=list')).json();
    renderInstalled(j.plugins || []);
  } catch(e) {
    document.getElementById('plugins-grid').innerHTML = `<div class="empty-state">Error: ${e.message}</div>`;
  }
}

function renderInstalled(plugins) {
  const g = document.getElementById('plugins-grid');
  if (!plugins.length) {
    g.innerHTML = '<div class="empty-state" style="grid-column:1/-1">Sin plugins instalados.<br>Sub&iacute; un ZIP en <strong>Instalar ZIP</strong>.</div>';
    return;
  }
  g.innerHTML = plugins.map(p=>`
    <div class="plugin-card ${p.active=='1'?'active-pl':''}" id="card-${p.slug}">
      <h3>${esc(p.name)}</h3>
      <div class="ver">v${esc(p.version)}${p.author?' &middot; '+esc(p.author):''}</div>
      <div class="dsc">${esc(p.description||'&mdash;')}</div>
      <div class="acts">
        ${p.active=='1'
          ? `<button class="btn btn-deactivate" onclick="toggleInstalled('${esc(p.slug)}',false)">&#x23F8; Desactivar</button>`
          : `<button class="btn btn-activate"   onclick="toggleInstalled('${esc(p.slug)}',true)">&#x25B6; Activar</button>`}
        <button class="btn btn-danger" onclick="uninstallPlugin('${esc(p.slug)}','${esc(p.name)}')">&#x1F5D1; Quitar</button>
      </div>
    </div>`).join('');
}

async function toggleInstalled(slug, activate) {
  await fetch('api/plugin_manager.php?action='+(activate?'activate':'deactivate'),
    {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug})});
  loadInstalled();
}
async function uninstallPlugin(slug, name) {
  if (!confirm(`Desinstalar "${name}"?`)) return;
  await fetch('api/plugin_manager.php?action=uninstall',
    {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({slug})});
  loadInstalled();
}

// ── INSTALAR ZIP ─────────────────────────────────────────────────────────────
async function uploadInstallZip() {
  const file = window._dropFile || document.getElementById('zip-input').files[0];
  const st   = document.getElementById('upload-status');
  if (!file) { st.innerHTML='<span style="color:var(--red)">Selecci&#243; un ZIP</span>'; return; }
  st.innerHTML = '<span style="color:var(--muted)">&#x23F3; Instalando&hellip;</span>';
  const fd = new FormData(); fd.append('zip', file);
  try {
    const j = await (await fetch('api/plugin_manager.php?action=upload',{method:'POST',body:fd})).json();
    if (j.success) {
      st.innerHTML = `<span style="color:var(--green)">&#x2713; ${j.msg||'Plugin instalado'}</span>`;
      loadInstalled();
      setTimeout(()=>document.querySelector('.tab[data-tab=installed]').click(), 1500);
    } else { st.innerHTML=`<span style="color:var(--red)">&#x26A0; ${j.error}</span>`; }
  } catch(e) { st.innerHTML=`<span style="color:var(--red)">&#x26A0; ${e.message}</span>`; }
}

// ── BUY FLOW ─────────────────────────────────────────────────────────────────
function buyPlugin(slug, name, price) {
  document.getElementById('bm-slug').value  = slug;
  document.getElementById('bm-price').value = price;
  document.getElementById('bm-title').textContent = name;
  document.getElementById('bm-price-usd').textContent = 'USD ' + parseFloat(price).toFixed(0);
  document.getElementById('bm-price-ars').textContent = '&#x2248; ARS ' + Math.round(price*USD_RATE).toLocaleString('es-AR');
  document.getElementById('bm-error').style.display = 'none';
  openModal('buy-modal');
}

async function doBuy(gateway) {
  const slug  = document.getElementById('bm-slug').value;
  const email = document.getElementById('bm-email').value.trim();
  const name  = document.getElementById('bm-name').value.trim();
  const errEl = document.getElementById('bm-error');
  errEl.style.display = 'none';
  if (!email || !email.includes('@')) {
    errEl.textContent='Ingres&#225; tu email'; errEl.style.display='block'; return;
  }
  const bm=document.getElementById('btn-mp'), bs=document.getElementById('btn-stripe');
  bm.disabled=bs.disabled=true;
  bm.textContent=bs.textContent='&#x23F3; Procesando&hellip;';

  // Siempre checkout en el servidor central
  const checkoutUrl = MARKETPLACE + '/api/checkout.php';
  try {
    const j = await (await fetch(checkoutUrl,{
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({slug, gateway, email, buyer_name:name})
    })).json();
    if (j.success && j.checkout_url) {
      window.location.href = j.checkout_url;
    } else {
      errEl.textContent = j.error||'Error al iniciar el pago'; errEl.style.display='block';
      bm.disabled=bs.disabled=false;
      bm.textContent='&#x1F1E6;&#x1F1F7; Pagar con Mercado Pago (ARS)';
      bs.textContent='&#x1F30E; Pagar con Stripe (USD)';
    }
  } catch(e) {
    errEl.textContent=e.message; errEl.style.display='block';
    bm.disabled=bs.disabled=false;
    bm.textContent='&#x1F1E6;&#x1F1F7; Pagar con Mercado Pago (ARS)';
    bs.textContent='&#x1F30E; Pagar con Stripe (USD)';
  }
}

// ── VENTAS (solo central) ────────────────────────────────────────────────────
async function loadSales() {
  if (!IS_CENTRAL) return;
  const status = document.getElementById('sales-status-filter')?.value||'';
  const tbody  = document.getElementById('sales-body');
  const stats  = document.getElementById('sales-stats');
  tbody.innerHTML='<tr><td colspan="8" style="padding:20px;text-align:center;color:var(--muted)">Cargando&hellip;</td></tr>';
  try {
    const j = await (await fetch(`api/plugin_prices.php?action=sales&status=${status}&limit=100`)).json();
    const sales = j.sales || [];
    const rev   = sales.filter(s=>s.status==='approved').reduce((a,s)=>a+(+s.price_usd),0);
    const pend  = sales.filter(s=>s.status==='pending').length;
    stats.innerHTML=`
      <div class="stat-card"><div class="val">USD ${rev.toFixed(0)}</div><div class="lbl">Ingresos aprobados</div></div>
      <div class="stat-card"><div class="val">${sales.filter(s=>s.status==='approved').length}</div><div class="lbl">Aprobadas</div></div>
      <div class="stat-card"><div class="val">${pend}</div><div class="lbl">Pendientes</div></div>
      <div class="stat-card"><div class="val">${j.total}</div><div class="lbl">Total</div></div>`;
    if (!sales.length) {
      tbody.innerHTML='<tr><td colspan="8" style="padding:20px;text-align:center;color:var(--muted)">Sin ventas a&uacute;n</td></tr>';
      return;
    }
    tbody.innerHTML = sales.map(s=>{
      const dt = new Date(s.created_at).toLocaleString('es-AR',{day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'});
      return `<tr>
        <td style="white-space:nowrap;color:var(--muted)">${dt}</td>
        <td><strong>${esc(s.plugin_name)}</strong><br><code style="font-size:9px;color:var(--muted)">${esc(s.plugin_slug)}</code></td>
        <td>${esc(s.buyer_email)}<br><span style="font-size:10px;color:var(--muted)">${esc(s.buyer_name||'')}</span></td>
        <td style="text-transform:capitalize">${s.gateway}</td>
        <td><strong>USD ${(+s.price_usd).toFixed(0)}</strong></td>
        <td><span class="badge-status ${s.status}">${s.status}</span></td>
        <td>${s.status==='approved'?`${s.download_count}/5`:'&mdash;'}</td>
        <td><div style="display:flex;gap:4px">
          ${s.status==='pending'?`<button class="btn btn-sm" onclick="approveManually('${esc(s.order_id)}')">&#x2713; Aprobar</button>`:''}
          ${s.status==='approved'?`<button class="btn btn-sm" onclick="getDownloadLink('${esc(s.order_id)}')">&#x1F4CB; Link</button>`:''}
        </div></td>
      </tr>`;
    }).join('');
  } catch(e) {
    tbody.innerHTML=`<tr><td colspan="8" style="padding:20px;text-align:center;color:var(--red)">Error: ${e.message}</td></tr>`;
  }
}

async function approveManually(orderId) {
  if (!confirm('Aprobar esta compra manualmente?')) return;
  const j = await (await fetch('api/plugin_prices.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'manual_approve',order_id:orderId})})).json();
  if (j.success) { alert('Aprobado.\nLink:\n'+j.download_url+'\nExpira: '+j.expires); loadSales(); }
  else alert('Error: '+j.error);
}
async function getDownloadLink(orderId) {
  const j = await (await fetch('api/plugin_prices.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'manual_approve',order_id:orderId})})).json();
  if (j.success) { try{await navigator.clipboard.writeText(j.download_url);alert('Link copiado')}catch{alert('Link:\n'+j.download_url)} }
  else alert('Error: '+j.error);
}

// ── CATÁLOGO CRUD (solo central) ─────────────────────────────────────────────
function renderCatalog(plugins) {
  const t = document.getElementById('catalog-body');
  if (!t) return;
  if (!plugins.length) { t.innerHTML='<tr><td colspan="8" style="padding:20px;text-align:center;color:var(--muted)">Sin plugins. Agreg&#225; uno.</td></tr>'; return; }
  t.innerHTML = plugins.map(p=>`<tr>
    <td style="font-size:20px">${p.icon}</td>
    <td><code style="font-size:11px;color:var(--gold)">${esc(p.slug)}</code></td>
    <td style="font-weight:600">${esc(p.name)}</td>
    <td>USD ${(+p.price_usd).toFixed(0)}</td>
    <td>${p.has_zip?'<span style="color:var(--green)">&#x2713; Listo</span>':`<span style="color:#ff9800">&#x2717; Falta</span> <button class="btn btn-sm" style="margin-left:6px" onclick="openZipModal('${esc(p.slug)}')">Subir &rarr;</button>`}</td>
    <td>${p.active?'<span class="tag-active">&#x25CF; Activo</span>':'<span class="tag-inactive">&#x25CF; Oculto</span>'}</td>
    <td style="color:var(--muted)">${p.sort_order}</td>
    <td><div style="display:flex;gap:5px;flex-wrap:wrap">
      <button class="btn btn-sm" onclick="editPlugin(${p.id})">&#x270F; Editar</button>
      <button class="btn btn-sm" onclick="toggleCatalog('${esc(p.slug)}',${p.active?0:1})">${p.active?'&#x23F8; Ocultar':'&#x25B6; Activar'}</button>
      ${p.has_zip?`<button class="btn btn-sm" onclick="openZipModal('${esc(p.slug)}')">&#x1F4E6; ZIP</button>`:''}
    </div></td>
  </tr>`).join('');
}

function editPlugin(id) {
  const p = _catPlugins.find(x=>x.id==id); if (!p) return;
  document.getElementById('add-modal-title').textContent='Editar plugin';
  document.getElementById('edit-id').value=p.id;
  document.getElementById('edit-icon').value=p.icon;
  document.getElementById('edit-slug').value=p.slug;
  document.getElementById('edit-name').value=p.name;
  document.getElementById('edit-desc').value=p.description||'';
  document.getElementById('edit-price').value=p.price_usd;
  document.getElementById('edit-order').value=p.sort_order;
  document.getElementById('edit-active').value=p.active?'1':'0';
  openModal('add-modal');
}
async function savePlugin() {
  const e=document.getElementById('add-modal-error'); e.style.display='none';
  const data={action:'save',
    id:parseInt(document.getElementById('edit-id').value||0),
    icon:document.getElementById('edit-icon').value,
    slug:document.getElementById('edit-slug').value,
    name:document.getElementById('edit-name').value,
    description:document.getElementById('edit-desc').value,
    price_usd:parseFloat(document.getElementById('edit-price').value||0),
    sort_order:parseInt(document.getElementById('edit-order').value||99),
    active:document.getElementById('edit-active').value==='1'};
  try {
    const j=await(await fetch('api/plugin_prices.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})).json();
    if (j.success) { closeModal('add-modal'); document.getElementById('edit-id').value=''; document.getElementById('add-modal-title').textContent='Agregar plugin al cat\u00e1logo'; loadMarketplace(); }
    else { e.textContent=j.error; e.style.display='block'; }
  } catch(err) { e.textContent=err.message; e.style.display='block'; }
}
async function toggleCatalog(slug, active) {
  await fetch('api/plugin_prices.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'toggle',slug,active:!!active})});
  loadMarketplace();
}
function openZipModal(slug) {
  document.getElementById('zip-modal-slug').textContent=slug;
  document.getElementById('zip-modal').dataset.slug=slug;
  document.getElementById('cat-zip-filename').textContent='';
  document.getElementById('cat-zip-error').style.display='none';
  openModal('zip-modal');
}
async function uploadCatalogZip() {
  const slug=document.getElementById('zip-modal').dataset.slug;
  const file=document.getElementById('cat-zip-input').files[0];
  const e=document.getElementById('cat-zip-error'); e.style.display='none';
  if (!file) { e.textContent='Selecci&#243; un ZIP'; e.style.display='block'; return; }
  const fd=new FormData(); fd.append('action','upload_zip'); fd.append('slug',slug); fd.append('zip',file);
  try {
    const j=await(await fetch('api/plugin_prices.php',{method:'POST',body:fd})).json();
    if (j.success) { closeModal('zip-modal'); alert(j.msg); loadMarketplace(); }
    else { e.textContent=j.error; e.style.display='block'; }
  } catch(err) { e.textContent=err.message; e.style.display='block'; }
}

async function openManualSaleModal() {
  const j=await(await fetch('api/plugin_prices.php?action=list')).json();
  document.getElementById('ms-slug').innerHTML=(j.plugins||[]).filter(p=>p.active).map(p=>`<option value="${esc(p.slug)}">${p.icon} ${esc(p.name)} &mdash; USD ${(+p.price_usd).toFixed(0)}</option>`).join('');
  document.getElementById('ms-result').style.display='none';
  document.getElementById('ms-error').style.display='none';
  openModal('manual-sale-modal');
}
async function submitManualSale() {
  const slug=document.getElementById('ms-slug').value;
  const email=document.getElementById('ms-email').value.trim();
  const name=document.getElementById('ms-name').value.trim();
  const price=parseFloat(document.getElementById('ms-price').value||0);
  const re=document.getElementById('ms-result'), er=document.getElementById('ms-error');
  er.style.display=re.style.display='none';
  if (!email) { er.textContent='Email requerido'; er.style.display='block'; return; }
  const j=await(await fetch('api/plugin_prices.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'manual_sale',slug,email,buyer_name:name,price_usd:price})})).json();
  if (j.success) {
    re.style.display='block';
    re.innerHTML=`<div style="background:rgba(76,175,80,.1);border:1px solid rgba(76,175,80,.3);border-radius:8px;padding:12px;font-size:12px">
      <div style="color:var(--green);font-weight:700;margin-bottom:6px">&#x2713; Venta registrada &middot; ${esc(j.order_id)}</div>
      <div style="margin-bottom:6px">Link (v&aacute;lido 30 d&iacute;as):</div>
      <div style="word-break:break-all;color:var(--gold);margin-bottom:8px">${esc(j.download_url)}</div>
      <button class="btn btn-sm" onclick="navigator.clipboard.writeText(${JSON.stringify(j.download_url)}).then(()=>this.textContent='&#x2713; Copiado')">&#x1F4CB; Copiar link</button>
    </div>`;
    loadSales();
  } else { er.textContent=j.error; er.style.display='block'; }
}

// ── UTILS ────────────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).style.display='flex'; }
function closeModal(id) { document.getElementById(id).style.display='none'; }
function esc(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
</script>
</body>
</html>
