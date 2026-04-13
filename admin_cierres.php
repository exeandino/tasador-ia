<?php
/**
 * TasadorIA — admin_cierres.php
 * Panel de precios de cierre reales (escrituras, boletos, testimonios).
 * Permite cargar, editar y analizar precios reales de venta para calibrar el tasador.
 */

session_start();
$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];

// Auth
$adminPass = $cfg['admin_password'] ?? 'admin123';
if (isset($_GET['logout'])) { unset($_SESSION['ta_admin']); header('Location: admin_cierres.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $adminPass) { $_SESSION['ta_admin'] = true; header('Location: admin_cierres.php'); exit; }
    $loginError = 'Contraseña incorrecta';
}
$logged = !empty($_SESSION['ta_admin']);

// DB
$pdo = null;
if ($logged) {
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'], $cfg['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (\Throwable $e) { $dbError = $e->getMessage(); }
}

$currentPanel = 'cierres';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cierres Reales · TasadorIA Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#0e0e0e;color:#ccc;display:flex;flex-direction:column;min-height:100vh}
.wrap{padding:24px;max-width:1400px;margin:0 auto;width:100%;flex:1}

/* Login */
.login-card{max-width:380px;margin:80px auto;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:36px}
.login-card h2{color:#c9a84c;font-family:Georgia,serif;margin-bottom:24px;text-align:center}
.login-card input{width:100%;background:#111;border:1px solid #333;color:#eee;border-radius:6px;padding:10px 14px;font-size:14px;margin-bottom:12px}
.login-card button{width:100%;background:#c9a84c;color:#000;border:none;padding:11px;border-radius:6px;font-weight:700;font-size:14px;cursor:pointer}
.login-card .err{color:#e06060;font-size:13px;margin-bottom:10px;text-align:center}

/* Page header */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.page-title{font-size:20px;font-weight:700;color:#eee}
.page-title span{color:#c9a84c}

/* Stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px}
.stat-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:10px;padding:16px 18px}
.stat-card .val{font-size:22px;font-weight:700;color:#c9a84c}
.stat-card .lbl{font-size:11px;color:#666;margin-top:4px;text-transform:uppercase;letter-spacing:.5px}

/* Tabs */
.tabs{display:flex;gap:0;border-bottom:2px solid #222;margin-bottom:20px}
.tab-btn{background:none;border:none;color:#666;font-size:13px;font-weight:500;padding:10px 18px;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s}
.tab-btn.active{color:#c9a84c;border-bottom-color:#c9a84c}
.tab-pane{display:none}
.tab-pane.active{display:block}

/* Form card */
.form-card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:10px;padding:24px;margin-bottom:24px}
.form-card h3{color:#eee;font-size:14px;margin-bottom:16px;font-weight:600}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:12px}
.form-row.wide{grid-template-columns:1fr 1fr 1fr 1fr}
label{font-size:11px;color:#777;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px}
input[type=text],input[type=number],input[type=date],select,textarea{
  width:100%;background:#111;border:1px solid #2a2a2a;color:#ddd;border-radius:6px;
  padding:8px 10px;font-size:13px;outline:none;transition:border-color .15s}
input:focus,select:focus,textarea:focus{border-color:#c9a84c}
textarea{resize:vertical;min-height:60px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:opacity .15s}
.btn:hover{opacity:.85}
.btn-gold{background:#c9a84c;color:#000}
.btn-sm{padding:5px 12px;font-size:12px}
.btn-outline{background:transparent;border:1px solid #333;color:#aaa}
.btn-danger{background:#7a2020;color:#fff}
.btn-green{background:#1a5c2a;color:#9de8ab}

/* Table */
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#161616;color:#777;font-weight:600;text-align:left;padding:9px 12px;border-bottom:1px solid #222;white-space:nowrap}
td{padding:8px 12px;border-bottom:1px solid #1e1e1e;color:#ccc;vertical-align:top}
tr:hover td{background:#141414}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.badge-gold{background:rgba(201,168,76,.15);color:#c9a84c}
.badge-green{background:rgba(80,200,100,.12);color:#4dc06a}
.badge-blue{background:rgba(80,140,200,.12);color:#5a9fd4}
.badge-gray{background:#2a2a2a;color:#888}

/* Stats zone table */
.zone-stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.zone-card{background:#1a1a1a;border:1px solid #252525;border-radius:8px;padding:14px 16px}
.zone-card .zname{font-weight:600;color:#eee;font-size:13px;margin-bottom:8px}
.zone-card .zrow{display:flex;justify-content:space-between;font-size:12px;color:#666;padding:2px 0}
.zone-card .zrow span:last-child{color:#c9a84c;font-weight:600}

/* Import/export bar */
.toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
.toolbar input[type=text]{max-width:200px}

/* Empty state */
.empty{text-align:center;padding:60px 20px;color:#444}
.empty .icon{font-size:48px;margin-bottom:12px}
.empty p{font-size:14px}

/* Alert */
.alert{padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:16px}
.alert-success{background:rgba(80,160,80,.12);border:1px solid #1a4a1a;color:#5aaa5a}
.alert-error{background:rgba(160,60,60,.12);border:1px solid #4a1a1a;color:#cc6060}

/* Compare panel */
.compare-row{display:flex;gap:16px;align-items:stretch;margin-bottom:16px;flex-wrap:wrap}
.compare-box{flex:1;min-width:200px;background:#161616;border:1px solid #252525;border-radius:8px;padding:14px}
.compare-box .src{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:#555;margin-bottom:4px}
.compare-box .price{font-size:24px;font-weight:700;color:#c9a84c}
.compare-box .sub{font-size:12px;color:#666;margin-top:4px}
.compare-box.portal{border-color:#1a3a5a}
.compare-box.portal .price{color:#5a9fd4}
.compare-box.cierres{border-color:#1a3a1a}
.compare-box.cierres .price{color:#4dc06a}

/* Mobile */
@media(max-width:640px){
  .form-row{grid-template-columns:1fr 1fr}
  .compare-row{flex-direction:column}
  .stat-grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>
<?php if (!$logged): ?>
<div class="wrap">
  <div class="login-card">
    <h2>🤝 Cierres · TasadorIA</h2>
    <?php if (!empty($loginError)): ?><div class="err"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
    <form method="POST">
      <input type="password" name="password" placeholder="Contraseña admin" autofocus>
      <button type="submit">Ingresar</button>
    </form>
  </div>
</div>
<?php else: ?>
<?php require __DIR__.'/includes/admin_topnav.php'; ?>
<div class="wrap">

<?php if (!empty($dbError)): ?>
<div class="alert alert-error">❌ Error DB: <?= htmlspecialchars($dbError) ?></div>
<?php else: ?>

<!-- Stats row -->
<div id="statsBar" class="stat-grid">
  <div class="stat-card"><div class="val" id="st-total">–</div><div class="lbl">Cierres cargados</div></div>
  <div class="stat-card"><div class="val" id="st-avg">–</div><div class="lbl">Avg USD/m² (todos)</div></div>
  <div class="stat-card"><div class="val" id="st-max">–</div><div class="lbl">Máximo USD/m²</div></div>
  <div class="stat-card"><div class="val" id="st-min">–</div><div class="lbl">Mínimo USD/m²</div></div>
  <div class="stat-card"><div class="val" id="st-escritura">–</div><div class="lbl">Escrituras</div></div>
  <div class="stat-card"><div class="val" id="st-boleto">–</div><div class="lbl">Boletos</div></div>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn active" onclick="showTab('lista',this)">📋 Cierres</button>
  <button class="tab-btn" onclick="showTab('nuevo',this)">➕ Cargar cierre</button>
  <button class="tab-btn" onclick="showTab('zonas',this)">🗺 Por zonas</button>
  <button class="tab-btn" onclick="showTab('comparar',this)">📊 Comparar</button>
  <button class="tab-btn" onclick="showTab('importar',this)">📥 Importar CSV</button>
</div>

<!-- ══ TAB LISTA ══ -->
<div id="tab-lista" class="tab-pane active">
  <div class="toolbar">
    <input type="text" id="filterCity" placeholder="Ciudad..." oninput="loadCierres()" style="max-width:150px">
    <input type="text" id="filterZone" placeholder="Zona..." oninput="loadCierres()" style="max-width:150px">
    <select id="filterType" onchange="loadCierres()" style="max-width:160px">
      <option value="">Todos los tipos</option>
      <option value="departamento">Departamento</option>
      <option value="casa">Casa</option>
      <option value="ph">PH</option>
      <option value="local">Local</option>
      <option value="oficina">Oficina</option>
      <option value="terreno">Terreno</option>
    </select>
    <select id="filterOp" onchange="loadCierres()" style="max-width:130px">
      <option value="">Todas las ops</option>
      <option value="venta">Venta</option>
      <option value="alquiler">Alquiler</option>
    </select>
    <button class="btn btn-outline btn-sm" onclick="exportCsv()">⬇ CSV</button>
    <button class="btn btn-gold btn-sm" onclick="showTab('nuevo', document.querySelector('.tab-btn:nth-child(2)'))">+ Agregar</button>
  </div>
  <div class="table-wrap">
    <table id="cierresTable">
      <thead>
        <tr>
          <th>Dirección</th><th>Ciudad / Zona</th><th>Tipo</th><th>m²</th>
          <th>USD</th><th>USD/m²</th><th>Fecha cierre</th><th>Fuente</th><th></th>
        </tr>
      </thead>
      <tbody id="cierresTbody"><tr><td colspan="9" style="text-align:center;padding:40px;color:#444">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- ══ TAB NUEVO ══ -->
<div id="tab-nuevo" class="tab-pane">
  <div class="form-card">
    <h3 id="formTitle">➕ Registrar precio de cierre real</h3>
    <input type="hidden" id="editId" value="">

    <div class="form-row">
      <div>
        <label>Dirección *</label>
        <input type="text" id="fAddress" placeholder="Ej: Rivadavia 1234, 3° B">
      </div>
      <div>
        <label>Ciudad</label>
        <input type="text" id="fCity" placeholder="Santa Fe Capital">
      </div>
      <div>
        <label>Zona / Barrio</label>
        <input type="text" id="fZone" placeholder="Candioti Norte">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>Tipo de propiedad</label>
        <select id="fType">
          <option value="departamento">Departamento</option>
          <option value="casa">Casa</option>
          <option value="ph">PH</option>
          <option value="local">Local comercial</option>
          <option value="oficina">Oficina</option>
          <option value="terreno">Terreno</option>
        </select>
      </div>
      <div>
        <label>Operación</label>
        <select id="fOp">
          <option value="venta">Venta</option>
          <option value="alquiler">Alquiler mensual</option>
        </select>
      </div>
      <div>
        <label>Sup. cubierta (m²)</label>
        <input type="number" id="fArea" placeholder="65" min="1" step="0.5">
      </div>
      <div>
        <label>Sup. total (m²)</label>
        <input type="number" id="fTotalArea" placeholder="70" min="1" step="0.5">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>Precio USD *</label>
        <input type="number" id="fPrice" placeholder="75000" min="1" step="1000">
      </div>
      <div>
        <label>Fecha de cierre *</label>
        <input type="date" id="fDate">
      </div>
      <div>
        <label>Fuente / Documentación</label>
        <select id="fSource">
          <option value="escritura">Escritura</option>
          <option value="boleto">Boleto de compraventa</option>
          <option value="testimonio">Testimonio directo</option>
          <option value="gestor">Gestor / Martillero</option>
          <option value="registro">Registro de la propiedad</option>
          <option value="banco">Tasación bancaria</option>
          <option value="otro">Otro</option>
        </select>
      </div>
      <div>
        <label>Dormitorios</label>
        <input type="number" id="fBeds" placeholder="2" min="0" max="10">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>Baños</label>
        <input type="number" id="fBaths" placeholder="1" min="0" max="10">
      </div>
      <div>
        <label>Latitud</label>
        <input type="number" id="fLat" placeholder="-31.6333" step="0.0001">
      </div>
      <div>
        <label>Longitud</label>
        <input type="number" id="fLng" placeholder="-60.7000" step="0.0001">
      </div>
    </div>

    <div style="margin-bottom:12px">
      <label>Notas</label>
      <textarea id="fNotes" placeholder="Observaciones, condiciones especiales, estado del inmueble..."></textarea>
    </div>

    <div style="display:flex;gap:10px;align-items:center">
      <button class="btn btn-gold" onclick="saveCierre()">💾 Guardar cierre</button>
      <button class="btn btn-outline" onclick="resetForm()">✕ Cancelar</button>
      <span id="saveMsg" style="font-size:13px;color:#5a9a5a;display:none"></span>
    </div>
  </div>
</div>

<!-- ══ TAB ZONAS ══ -->
<div id="tab-zonas" class="tab-pane">
  <div class="toolbar">
    <input type="text" id="filterZonaCity" placeholder="Ciudad..." oninput="loadZonaStats()" style="max-width:200px">
    <button class="btn btn-outline btn-sm" onclick="loadZonaStats()">🔄 Actualizar</button>
  </div>
  <div id="zonaStatsWrap" class="zone-stats-grid"></div>
</div>

<!-- ══ TAB COMPARAR ══ -->
<div id="tab-comparar" class="tab-pane">
  <div class="form-card">
    <h3>📊 Comparar precio de cierre vs portal vs tasador</h3>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr auto">
      <div>
        <label>Ciudad</label>
        <input type="text" id="cmpCity" placeholder="Santa Fe Capital">
      </div>
      <div>
        <label>Zona</label>
        <input type="text" id="cmpZone" placeholder="Candioti Norte">
      </div>
      <div>
        <label>Tipo</label>
        <select id="cmpType">
          <option value="departamento">Departamento</option>
          <option value="casa">Casa</option>
          <option value="ph">PH</option>
          <option value="terreno">Terreno</option>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end">
        <button class="btn btn-gold" onclick="loadComparacion()">Comparar</button>
      </div>
    </div>
  </div>

  <div id="cmpResult" style="display:none">
    <div class="compare-row">
      <div class="compare-box cierres">
        <div class="src">💼 Precios de cierre reales</div>
        <div class="price" id="cmpCierresPrice">–</div>
        <div class="sub" id="cmpCierresSub">–</div>
      </div>
      <div class="compare-box portal">
        <div class="src">🌐 Portales (Zonaprop, etc.)</div>
        <div class="price" id="cmpPortalPrice">–</div>
        <div class="sub" id="cmpPortalSub">–</div>
      </div>
      <div class="compare-box">
        <div class="src">🏠 TasadorIA (motor)</div>
        <div class="price" id="cmpTasadorPrice">–</div>
        <div class="sub" id="cmpTasadorSub">–</div>
      </div>
    </div>
    <div id="cmpAnalysis" style="background:#161616;border:1px solid #252525;border-radius:8px;padding:16px;font-size:13px;color:#aaa;line-height:1.6"></div>
  </div>
</div>

<!-- ══ TAB IMPORTAR ══ -->
<div id="tab-importar" class="tab-pane">
  <div class="form-card">
    <h3>📥 Importar cierres desde CSV</h3>
    <p style="font-size:13px;color:#666;margin-bottom:16px">
      Columnas: <code>address, city, zone, property_type, operation, covered_area, price_usd, close_date, source, notes, bedrooms, bathrooms</code>
    </p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
      <button class="btn btn-green btn-sm" onclick="downloadTemplate()">⬇ Descargar plantilla CSV</button>
    </div>
    <div>
      <label>Pegar CSV aquí (o pegar desde Excel)</label>
      <textarea id="csvPaste" rows="8" placeholder="address,city,zone,property_type,operation,covered_area,price_usd,close_date,source&#10;Rivadavia 1234,Santa Fe Capital,Candioti Norte,departamento,venta,65,75000,2025-03-15,escritura" style="font-family:monospace;font-size:12px"></textarea>
    </div>
    <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
      <button class="btn btn-gold" onclick="importCsv()">📥 Importar</button>
      <span id="importMsg" style="font-size:13px;display:none"></span>
    </div>
  </div>
</div>

<?php endif; ?>
</div><!-- /wrap -->

<script>
// ── Estado ──────────────────────────────────────────────
let cierresData = [];

function showTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  if (btn) btn.classList.add('active');
  if (id === 'lista')    loadCierres();
  if (id === 'zonas')    loadZonaStats();
  if (id === 'comparar') {}
}

// ── Load cierres ─────────────────────────────────────────
async function loadCierres() {
  const city = document.getElementById('filterCity').value;
  const zone = document.getElementById('filterZone').value;
  const type = document.getElementById('filterType').value;
  const op   = document.getElementById('filterOp').value;
  const params = new URLSearchParams({action:'list', limit:200});
  if (city) params.set('city', city);
  if (zone) params.set('zone', zone);
  if (type) params.set('type', type);
  if (op)   params.set('operation', op);

  const res  = await fetch('api/closing_prices.php?' + params);
  const data = await res.json();
  cierresData = data.closings || [];
  renderTable(cierresData);

  const s = data.stats || {};
  document.getElementById('st-total').textContent = cierresData.length;
  document.getElementById('st-avg').textContent   = s.avg_ppm2  ? '$' + s.avg_ppm2.toLocaleString() : '–';
  document.getElementById('st-max').textContent   = s.max_ppm2  ? '$' + s.max_ppm2.toLocaleString() : '–';
  document.getElementById('st-min').textContent   = s.min_ppm2  ? '$' + s.min_ppm2.toLocaleString() : '–';
  // Count by source
  const esc = cierresData.filter(r => r.source === 'escritura').length;
  const bol = cierresData.filter(r => r.source === 'boleto').length;
  document.getElementById('st-escritura').textContent = esc;
  document.getElementById('st-boleto').textContent    = bol;
}

function renderTable(rows) {
  const tbody = document.getElementById('cierresTbody');
  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="9" class="empty"><div class="icon">📋</div><p>Sin cierres cargados todavía.</p></td></tr>`;
    return;
  }
  tbody.innerHTML = rows.map(r => {
    const ppm2 = r.price_per_m2 ? '$' + parseFloat(r.price_per_m2).toLocaleString(undefined, {maximumFractionDigits:0}) : '–';
    const badgeClass = {escritura:'badge-gold',boleto:'badge-green',testimonio:'badge-blue'}[r.source] || 'badge-gray';
    return `<tr>
      <td><strong>${esc(r.address)}</strong></td>
      <td>${esc(r.city)}${r.zone ? '<br><small style="color:#555">'+esc(r.zone)+'</small>' : ''}</td>
      <td>${esc(r.property_type)} <small style="color:#555">${esc(r.operation)}</small></td>
      <td>${r.covered_area ? parseFloat(r.covered_area).toLocaleString() : '–'}</td>
      <td>USD ${parseFloat(r.price_usd).toLocaleString(undefined,{maximumFractionDigits:0})}</td>
      <td>${ppm2}</td>
      <td>${r.close_date}</td>
      <td><span class="badge ${badgeClass}">${esc(r.source)}</span></td>
      <td>
        <button class="btn btn-outline btn-sm" onclick="editCierre(${r.id})" style="margin-right:4px">✏️</button>
        <button class="btn btn-danger btn-sm" onclick="deleteCierre(${r.id})">🗑</button>
      </td>
    </tr>`;
  }).join('');
}

function esc(s) { return (s||'').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── CRUD ─────────────────────────────────────────────────
async function saveCierre() {
  const id    = document.getElementById('editId').value;
  const addr  = document.getElementById('fAddress').value.trim();
  const price = parseFloat(document.getElementById('fPrice').value);
  const date  = document.getElementById('fDate').value;

  if (!addr || !price || !date) { alert('Dirección, precio y fecha son requeridos'); return; }

  const body = {
    action:        'save',
    id:            id ? parseInt(id) : 0,
    address:       addr,
    city:          document.getElementById('fCity').value,
    zone:          document.getElementById('fZone').value,
    property_type: document.getElementById('fType').value,
    operation:     document.getElementById('fOp').value,
    covered_area:  parseFloat(document.getElementById('fArea').value) || 0,
    total_area:    parseFloat(document.getElementById('fTotalArea').value) || 0,
    price_usd:     price,
    close_date:    date,
    source:        document.getElementById('fSource').value,
    bedrooms:      document.getElementById('fBeds').value,
    bathrooms:     document.getElementById('fBaths').value,
    lat:           document.getElementById('fLat').value,
    lng:           document.getElementById('fLng').value,
    notes:         document.getElementById('fNotes').value,
  };

  const res  = await fetch('api/closing_prices.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
  const data = await res.json();
  if (data.success) {
    const msg = document.getElementById('saveMsg');
    msg.textContent = '✅ ' + data.msg;
    msg.style.display = 'inline';
    setTimeout(() => { msg.style.display = 'none'; resetForm(); showTab('lista', document.querySelector('.tab-btn')); }, 1500);
  } else {
    alert('Error: ' + data.error);
  }
}

function editCierre(id) {
  const r = cierresData.find(c => c.id == id);
  if (!r) return;
  document.getElementById('editId').value          = r.id;
  document.getElementById('fAddress').value        = r.address || '';
  document.getElementById('fCity').value           = r.city || '';
  document.getElementById('fZone').value           = r.zone || '';
  document.getElementById('fType').value           = r.property_type || 'departamento';
  document.getElementById('fOp').value             = r.operation || 'venta';
  document.getElementById('fArea').value           = r.covered_area || '';
  document.getElementById('fTotalArea').value      = r.total_area || '';
  document.getElementById('fPrice').value          = r.price_usd || '';
  document.getElementById('fDate').value           = r.close_date || '';
  document.getElementById('fSource').value         = r.source || 'escritura';
  document.getElementById('fBeds').value           = r.bedrooms || '';
  document.getElementById('fBaths').value          = r.bathrooms || '';
  document.getElementById('fLat').value            = r.lat || '';
  document.getElementById('fLng').value            = r.lng || '';
  document.getElementById('fNotes').value          = r.notes || '';
  document.getElementById('formTitle').textContent = '✏️ Editar cierre #' + r.id;
  showTab('nuevo', document.querySelectorAll('.tab-btn')[1]);
}

function resetForm() {
  document.getElementById('editId').value  = '';
  document.getElementById('formTitle').textContent = '➕ Registrar precio de cierre real';
  ['fAddress','fCity','fZone','fArea','fTotalArea','fPrice','fBeds','fBaths','fLat','fLng','fNotes'].forEach(f => {
    document.getElementById(f).value = '';
  });
  document.getElementById('fType').value   = 'departamento';
  document.getElementById('fOp').value     = 'venta';
  document.getElementById('fSource').value = 'escritura';
  document.getElementById('fDate').value   = new Date().toISOString().split('T')[0];
}

async function deleteCierre(id) {
  if (!confirm('¿Eliminar este cierre?')) return;
  const res = await fetch('api/closing_prices.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete', id})});
  const data = await res.json();
  if (data.success) loadCierres();
  else alert('Error: ' + data.error);
}

// ── Zona stats ───────────────────────────────────────────
async function loadZonaStats() {
  const city = document.getElementById('filterZonaCity').value;
  const res  = await fetch('api/closing_prices.php?action=stats&city=' + encodeURIComponent(city));
  const data = await res.json();
  const wrap = document.getElementById('zonaStatsWrap');

  if (!data.stats || !data.stats.length) {
    wrap.innerHTML = `<div class="empty"><div class="icon">🗺</div><p>Sin datos por zona todavía.</p></div>`;
    return;
  }

  // Agrupar por zona
  const byZone = {};
  data.stats.forEach(s => {
    if (!byZone[s.zone]) byZone[s.zone] = [];
    byZone[s.zone].push(s);
  });

  wrap.innerHTML = Object.entries(byZone).map(([zone, rows]) => `
    <div class="zone-card">
      <div class="zname">📍 ${esc(zone) || '(sin zona)'}</div>
      ${rows.map(r => `
        <div class="zrow">
          <span>${esc(r.property_type)} ${esc(r.operation)} (${r.c})</span>
          <span>USD ${parseInt(r.avg_ppm2).toLocaleString()}/m²</span>
        </div>
        <div class="zrow" style="font-size:11px;color:#444">
          <span>Min ${parseInt(r.min_ppm2).toLocaleString()} · Max ${parseInt(r.max_ppm2).toLocaleString()}</span>
          <span>Último: ${r.last_date}</span>
        </div>
      `).join('')}
    </div>
  `).join('');
}

// ── Comparación ──────────────────────────────────────────
async function loadComparacion() {
  const city = document.getElementById('cmpCity').value;
  const zone = document.getElementById('cmpZone').value;
  const type = document.getElementById('cmpType').value;

  // Cierres reales
  const r1 = await fetch(`api/closing_prices.php?action=stats&city=${encodeURIComponent(city)}`);
  const d1 = await r1.json();
  const cierreRow = (d1.stats||[]).find(s => s.zone?.toLowerCase().includes(zone.toLowerCase()) && s.property_type === type && s.operation === 'venta');

  // Portales
  const r2 = await fetch(`api/valuar.php?action=market_stats&city=${encodeURIComponent(city)}&zone=${encodeURIComponent(zone)}&type=${encodeURIComponent(type)}`).catch(() => ({json: () => ({})}));
  const d2 = (typeof r2.json === 'function' ? await r2.json() : {});

  document.getElementById('cmpResult').style.display = 'block';

  if (cierreRow) {
    document.getElementById('cmpCierresPrice').textContent = 'USD ' + parseInt(cierreRow.avg_ppm2).toLocaleString() + '/m²';
    document.getElementById('cmpCierresSub').textContent   = `${cierreRow.c} operaciones · Min ${parseInt(cierreRow.min_ppm2).toLocaleString()} · Max ${parseInt(cierreRow.max_ppm2).toLocaleString()}`;
  } else {
    document.getElementById('cmpCierresPrice').textContent = 'Sin datos';
    document.getElementById('cmpCierresSub').textContent   = 'No hay cierres registrados para esta zona/tipo';
  }

  document.getElementById('cmpPortalPrice').textContent = d2.avg_ppm2 ? 'USD ' + d2.avg_ppm2.toLocaleString() + '/m²' : 'Sin datos';
  document.getElementById('cmpPortalSub').textContent   = d2.count    ? d2.count + ' listings en portales' : 'No hay listings importados';

  document.getElementById('cmpTasadorPrice').textContent = '(tasar para comparar)';
  document.getElementById('cmpTasadorSub').textContent   = 'Usá el wizard con esta zona para ver el resultado del motor';

  // Análisis automático
  let analysis = '';
  if (cierreRow && d2.avg_ppm2) {
    const diff = ((d2.avg_ppm2 - cierreRow.avg_ppm2) / cierreRow.avg_ppm2 * 100).toFixed(1);
    const dir  = diff > 0 ? 'sobrevalúan' : 'subvalúan';
    analysis = `📊 Los portales ${dir} en promedio un <strong>${Math.abs(diff)}%</strong> respecto a los precios reales de cierre en ${esc(zone) || 'esta zona'}.
    Esto es normal: los portales muestran precios de publicación, que generalmente tienen un margen de negociación.<br><br>
    <strong>Consejo:</strong> Para una tasación más precisa, podés ajustar los precios base en la sección Zonas usando como referencia estos cierres reales.`;
  } else if (cierreRow) {
    analysis = `ℹ️ Solo hay datos de cierres reales. Promedio de cierre: <strong>USD ${parseInt(cierreRow.avg_ppm2).toLocaleString()}/m²</strong> sobre ${cierreRow.c} operación${cierreRow.c>1?'es':''}. Importá listings desde la pestaña Mercado para tener comparación con portales.`;
  } else {
    analysis = `⚠️ No hay datos de cierres reales para esta combinación. Comenzá cargando precios reales de escrituras, boletos o testimonios de martilleros.`;
  }
  document.getElementById('cmpAnalysis').innerHTML = analysis;
}

// ── Export CSV ───────────────────────────────────────────
function exportCsv() {
  const headers = ['id','address','city','zone','property_type','operation','covered_area','price_usd','price_per_m2','close_date','source','bedrooms','bathrooms','notes'];
  const rows    = cierresData.map(r => headers.map(h => `"${(r[h]||'').toString().replace(/"/g,'""')}"`).join(','));
  const csv     = [headers.join(','), ...rows].join('\n');
  const a       = document.createElement('a');
  a.href        = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
  a.download    = 'cierres-' + new Date().toISOString().split('T')[0] + '.csv';
  a.click();
}

// ── Import CSV ───────────────────────────────────────────
async function importCsv() {
  const raw  = document.getElementById('csvPaste').value.trim();
  const msg  = document.getElementById('importMsg');
  if (!raw)  { alert('Pegá el CSV primero'); return; }

  const lines = raw.split('\n').filter(l => l.trim());
  const header= lines[0].split(',').map(h => h.trim().toLowerCase().replace(/"/g,''));
  const rows  = lines.slice(1).map(line => {
    const vals = line.match(/(".*?"|[^,]+)/g) || [];
    const obj  = {};
    header.forEach((h,i) => obj[h] = (vals[i]||'').replace(/^"|"$/g,'').trim());
    return obj;
  });

  let ok = 0, err = 0;
  for (const row of rows) {
    if (!row.address || !row.price_usd) { err++; continue; }
    const res  = await fetch('api/closing_prices.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'save', id:0, ...row})
    });
    const data = await res.json();
    if (data.success) ok++; else err++;
  }
  msg.textContent  = `✅ ${ok} importados · ${err} con error`;
  msg.style.color  = ok > 0 ? '#5aaa5a' : '#cc6060';
  msg.style.display = 'inline';
  if (ok > 0) document.getElementById('csvPaste').value = '';
}

// ── Plantilla CSV ────────────────────────────────────────
function downloadTemplate() {
  const header = 'address,city,zone,property_type,operation,covered_area,price_usd,close_date,source,notes,bedrooms,bathrooms';
  const ex1    = '"Rivadavia 1234 3B","Santa Fe Capital","Candioti Norte","departamento","venta","65","75000","2025-03-15","escritura","3 ambientes vista calle","2","1"';
  const ex2    = '"San Martin 555 PB","Santa Fe Capital","Centro","casa","venta","120","145000","2025-01-20","boleto","PH en esquina con jardín","3","2"';
  const csv    = [header, ex1, ex2].join('\n');
  const a      = document.createElement('a');
  a.href       = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
  a.download   = 'plantilla-cierres.csv';
  a.click();
}

// ── Init ─────────────────────────────────────────────────
document.getElementById('fDate').value = new Date().toISOString().split('T')[0];
loadCierres();
</script>

<?php endif; ?>
</body>
</html>
