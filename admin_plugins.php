<?php
/**
 * TasadorIA — admin_plugins.php
 * Panel de gestión de plugins / módulos.
 */
session_start();
if (!isset($_SESSION['ta_admin'])) {
    header('Location: admin.php'); exit;
}

$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Plugins — TasadorIA</title>
<style>
:root{
  --bg:#0f0f0f;--bg2:#181818;--bg3:#222;--surface:#1e1e1e;--border:#2a2a2a;
  --gold:#c9a84c;--text:#e0e0e0;--muted:#888;--green:#4caf50;--red:#f44336;
  --font:system-ui,-apple-system,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh}
a{color:var(--gold);text-decoration:none}
a:hover{text-decoration:underline}

.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:10px 24px;display:flex;align-items:center;gap:16px}
.topbar h1{font-size:15px;font-weight:700;color:var(--gold)}
.topbar nav a{font-size:12px;color:var(--muted);padding:4px 8px;border-radius:5px}
.topbar nav a:hover{color:var(--text);background:var(--bg3);text-decoration:none}
.topbar nav a.active{color:var(--gold);background:rgba(201,168,76,.1)}

.main{max-width:1000px;margin:0 auto;padding:28px 20px}
h2{font-size:18px;font-weight:700;color:var(--gold);margin-bottom:4px}
.subtitle{font-size:12px;color:var(--muted);margin-bottom:24px}

/* Upload zone */
.upload-zone{border:2px dashed var(--border);border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:.2s;margin-bottom:28px;background:var(--surface)}
.upload-zone:hover,.upload-zone.drag{border-color:var(--gold);background:rgba(201,168,76,.05)}
.upload-zone .icon{font-size:32px;margin-bottom:8px}
.upload-zone p{font-size:13px;color:var(--muted)}
.upload-zone strong{color:var(--text)}
#upload-status{margin-top:12px;font-size:12px;min-height:20px}

/* Plugin cards */
.plugins-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:32px}
.plugin-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:18px;position:relative;transition:.2s}
.plugin-card.active{border-color:rgba(76,175,80,.4);background:#0f1e0f}
.plugin-card .badge{position:absolute;top:14px;right:14px;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:600}
.badge-active{background:rgba(76,175,80,.2);color:#4caf50;border:1px solid rgba(76,175,80,.3)}
.badge-inactive{background:var(--bg3);color:var(--muted);border:1px solid var(--border)}
.plugin-card h3{font-size:13px;font-weight:700;margin-bottom:4px;padding-right:70px}
.plugin-card .version{font-size:10px;color:var(--muted);margin-bottom:8px}
.plugin-card .desc{font-size:11px;color:var(--muted);line-height:1.6;margin-bottom:14px;min-height:36px}
.plugin-card .actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{padding:5px 14px;border-radius:6px;border:1px solid var(--border);background:var(--bg3);color:var(--text);font-size:11px;cursor:pointer;font-family:var(--font);font-weight:600;transition:.15s}
.btn:hover{border-color:var(--gold);color:var(--gold)}
.btn-activate{border-color:rgba(76,175,80,.5);color:#4caf50;background:rgba(76,175,80,.08)}
.btn-activate:hover{background:rgba(76,175,80,.15)}
.btn-deactivate{border-color:rgba(244,67,54,.4);color:#f44336;background:rgba(244,67,54,.06)}
.btn-deactivate:hover{background:rgba(244,67,54,.12)}
.btn-danger{color:#f44336;border-color:rgba(244,67,54,.3);background:transparent;font-size:10px}

/* Marketplace */
.marketplace{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-top:8px}
.marketplace h3{font-size:14px;font-weight:700;color:var(--gold);margin-bottom:4px}
.marketplace p{font-size:12px;color:var(--muted);margin-bottom:20px;line-height:1.6}
.market-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.market-card{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;text-align:center}
.market-card .icon{font-size:24px;margin-bottom:6px}
.market-card h4{font-size:12px;font-weight:700;margin-bottom:4px}
.market-card .price{font-size:16px;font-weight:800;color:var(--gold);margin:8px 0}
.market-card p{font-size:10px;color:var(--muted);line-height:1.5;margin-bottom:12px}
.btn-buy{display:inline-block;padding:5px 14px;background:var(--gold);color:#000;font-weight:700;border-radius:6px;font-size:11px;text-decoration:none;cursor:pointer;border:none;font-family:var(--font)}
.btn-buy:hover{opacity:.85;text-decoration:none}
.badge-installed{display:inline-block;padding:4px 12px;background:rgba(76,175,80,.15);color:#4caf50;border:1px solid rgba(76,175,80,.3);border-radius:6px;font-size:11px;font-weight:600}

/* Toast */
#toast{position:fixed;bottom:24px;right:24px;padding:10px 18px;border-radius:8px;font-size:12px;font-weight:600;z-index:9999;display:none;max-width:320px}
.toast-ok{background:#1b3a1b;border:1px solid #4caf50;color:#4caf50}
.toast-err{background:#3a1b1b;border:1px solid #f44336;color:#f44336}
</style>
</head>
<body>

<div class="topbar">
  <h1>🔌 TasadorIA</h1>
  <nav style="display:flex;gap:4px;margin-left:16px">
    <a href="admin.php">Dashboard</a>
    <a href="admin_bim.php">BIM</a>
    <a href="admin_market.php">Mercado</a>
    <a href="admin_plugins.php" class="active">Plugins</a>
  </nav>
  <div style="margin-left:auto;font-size:11px;color:var(--muted)">
    TasadorIA <span style="color:var(--gold)">v5.0</span>
  </div>
</div>

<div class="main">

  <h2>🔌 Gestión de Plugins</h2>
  <p class="subtitle">Instalá módulos adicionales para ampliar las funcionalidades del sistema.</p>

  <!-- Upload zone -->
  <div class="upload-zone" id="drop-zone" onclick="document.getElementById('file-input').click()"
       ondragover="event.preventDefault();this.classList.add('drag')"
       ondragleave="this.classList.remove('drag')"
       ondrop="handleDrop(event)">
    <div class="icon">📦</div>
    <p><strong>Arrastrá un ZIP aquí</strong> o hacé clic para seleccionar</p>
    <p style="margin-top:4px;font-size:11px">Archivo .zip · Plugin oficial de TasadorIA</p>
    <div id="upload-status"></div>
  </div>
  <input id="file-input" type="file" accept=".zip" style="display:none" onchange="uploadPlugin(this.files[0])">

  <!-- Installed plugins -->
  <h3 style="font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">
    Plugins instalados
  </h3>
  <div class="plugins-grid" id="plugins-grid">
    <div style="color:var(--muted);font-size:13px;padding:20px">⏳ Cargando…</div>
  </div>

  <!-- Marketplace -->
  <div class="marketplace">
    <h3>🛒 Marketplace de módulos</h3>
    <p>
      TasadorIA es open source. Los módulos de pago agregan funcionalidades avanzadas.<br>
      Comprá el ZIP, subilo arriba y activalo en segundos.
      <a href="https://github.com/exeandino/tasador-ia" target="_blank" style="color:var(--gold)">Ver repositorio ↗</a>
    </p>
    <div class="market-grid" id="market-grid">
      <!-- Se llena dinámicamente -->
    </div>
  </div>

</div>

<!-- ── Buy Modal ─────────────────────────────────────────── -->
<div id="buy-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9000;align-items:center;justify-content:center;padding:20px">
  <div style="background:#1e1e1e;border:1px solid #2a2a2a;border-radius:16px;max-width:420px;width:100%;padding:28px;position:relative">
    <button onclick="closeBuyModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;color:#888;font-size:20px;cursor:pointer;line-height:1">×</button>

    <h3 style="font-size:16px;font-weight:700;color:#c9a84c;margin-bottom:4px" id="bm-title">Plugin</h3>
    <div style="font-size:22px;font-weight:800;color:#e0e0e0;margin-bottom:2px" id="bm-price-usd"></div>
    <div style="font-size:12px;color:#888;margin-bottom:20px" id="bm-price-ars"></div>

    <input type="hidden" id="bm-slug">

    <label style="display:block;font-size:12px;color:#888;margin-bottom:6px">Email <span style="color:#c9a84c">*</span></label>
    <input id="bm-email" type="email" placeholder="tu@email.com"
      style="width:100%;background:#111;border:1px solid #333;color:#e0e0e0;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px;outline:none"
      onfocus="this.style.borderColor='#c9a84c'" onblur="this.style.borderColor='#333'">

    <label style="display:block;font-size:12px;color:#888;margin-bottom:6px">Nombre (opcional)</label>
    <input id="bm-name" type="text" placeholder="Tu nombre"
      style="width:100%;background:#111;border:1px solid #333;color:#e0e0e0;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:20px;outline:none"
      onfocus="this.style.borderColor='#c9a84c'" onblur="this.style.borderColor='#333'">

    <div id="bm-error" style="color:#f44336;font-size:12px;margin-bottom:12px;min-height:16px"></div>

    <div style="display:flex;gap:10px;margin-bottom:12px">
      <button id="bm-btn-mp" onclick="doBuy('mercadopago')"
        style="flex:1;background:#009ee3;color:#fff;border:none;border-radius:8px;padding:12px;font-weight:700;font-size:13px;cursor:pointer;font-family:var(--font)">
        🇦🇷 Pagar con Mercado Pago
      </button>
      <button id="bm-btn-stripe" onclick="doBuy('stripe')"
        style="flex:1;background:#635bff;color:#fff;border:none;border-radius:8px;padding:12px;font-weight:700;font-size:13px;cursor:pointer;font-family:var(--font)">
        🌎 Pagar con Stripe (USD)
      </button>
    </div>

    <div id="bm-loading" style="display:none;text-align:center;color:#888;font-size:12px;padding:8px 0">
      ⏳ Redirigiendo al checkout…
    </div>

    <p style="font-size:10px;color:#555;text-align:center;margin-top:8px;line-height:1.6">
      Tras el pago recibís el ZIP por email · 5 descargas · soporte: exeandino@gmail.com
    </p>
  </div>
</div>

<div id="toast"></div>

<script>
// ── Marketplace catalogue (se puede alimentar desde un JSON remoto) ─────────
const MARKETPLACE = [
  {
    slug:'bim-materiales',
    icon:'🏗',
    name:'BIM Materiales ML',
    price:29,
    desc:'Bookmarklet para extraer precios de materiales desde MercadoLibre. Mapa de calor de costos de construcción.',
  },
  {
    slug:'icc-indec',
    icon:'📈',
    name:'Actualizar por ICC INDEC',
    price:19,
    desc:'Ajuste automático de precios usando el índice oficial de costo de la construcción (INDEC).',
  },
  {
    slug:'ia-fotos',
    icon:'📸',
    name:'Análisis IA de Fotos',
    price:29,
    desc:'Analiza fotos de la propiedad con Claude Vision / GPT-4o y ajusta la valuación ±15% según el estado real.',
  },
  {
    slug:'apify-sync',
    icon:'🤖',
    name:'Scraping Automático (Apify)',
    price:29,
    desc:'Sincronización mensual automática de precios de materiales via Apify. Sin abrir MercadoLibre manualmente.',
  },
  {
    slug:'escrituras',
    icon:'📄',
    name:'Análisis de Escrituras',
    price:19,
    desc:'Procesa PDFs de escrituras con IA, extrae datos del inmueble y los carga automáticamente al wizard.',
  },
  {
    slug:'wp-publish',
    icon:'🔌',
    name:'WordPress Publisher',
    price:19,
    desc:'Publica las tasaciones como propiedades en WordPress/Houzez automáticamente desde el panel.',
  },
  {
    slug:'ciudades-extra',
    icon:'🗺',
    name:'Ciudades Extra',
    price:19,
    desc:'Agrega zonas y precios para Uruguay, Chile, Colombia, México y Miami (actualizados trimestralmente).',
  },
  {
    slug:'crm-export',
    icon:'💼',
    name:'CRM Export',
    price:29,
    desc:'Exporta leads y tasaciones a HubSpot, Zoho CRM o cualquier CRM via webhook configurable.',
  },
];

let installedSlugs = [];

// ── Cargar plugins instalados ──────────────────────────────────
async function loadPlugins() {
  try {
    const r = await fetch('api/plugin_manager.php?action=list');
    const j = await r.json();
    if (!j.success) throw new Error(j.error);

    installedSlugs = j.plugins.map(p => p.slug);
    renderInstalled(j.plugins);
    renderMarketplace();
  } catch(e) {
    document.getElementById('plugins-grid').innerHTML =
      `<div style="color:#f44336;font-size:13px">⚠ ${e.message}</div>`;
  }
}

function renderInstalled(plugins) {
  const grid = document.getElementById('plugins-grid');
  if (!plugins.length) {
    grid.innerHTML = `<div style="color:var(--muted);font-size:13px;padding:20px;grid-column:1/-1">
      Sin plugins instalados. Subí un ZIP arriba para instalar el primero.
    </div>`;
    return;
  }
  grid.innerHTML = plugins.map(p => `
    <div class="plugin-card ${p.active=='1'?'active':''}" id="card-${p.slug}">
      <span class="badge ${p.active=='1'?'badge-active':'badge-inactive'}">${p.active=='1'?'✓ Activo':'Inactivo'}</span>
      <h3>${p.name}</h3>
      <div class="version">v${p.version} ${p.author?'· '+p.author:''}</div>
      <div class="desc">${p.description||'Sin descripción.'}</div>
      <div class="actions">
        ${p.active=='1'
          ? `<button class="btn btn-deactivate" onclick="togglePlugin('${p.slug}', false)">⏸ Desactivar</button>`
          : `<button class="btn btn-activate"   onclick="togglePlugin('${p.slug}', true)">▶ Activar</button>`
        }
        <button class="btn btn-danger" onclick="uninstall('${p.slug}','${p.name}')">🗑 Quitar</button>
      </div>
    </div>
  `).join('');
}

function renderMarketplace() {
  document.getElementById('market-grid').innerHTML = MARKETPLACE.map(m => {
    const isInstalled = installedSlugs.includes(m.slug);
    return `
      <div class="market-card">
        <div class="icon">${m.icon}</div>
        <h4>${m.name}</h4>
        <p>${m.desc}</p>
        <div class="price">USD ${m.price}</div>
        ${isInstalled
          ? `<span class="badge-installed">✓ Instalado</span>`
          : `<button class="btn-buy" onclick="buyPlugin('${m.slug}','${m.name}',${m.price})">Comprar →</button>`
        }
      </div>`;
  }).join('');
}

// ── Upload plugin ZIP ──────────────────────────────────────────
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('drop-zone').classList.remove('drag');
  const file = e.dataTransfer?.files[0];
  if (file) uploadPlugin(file);
}

async function uploadPlugin(file) {
  if (!file || !file.name.endsWith('.zip')) {
    showToast('El archivo debe ser un ZIP', 'err'); return;
  }
  const status = document.getElementById('upload-status');
  status.innerHTML = `<span style="color:var(--gold)">⏳ Instalando ${file.name}…</span>`;

  const fd = new FormData();
  fd.append('file', file);
  fd.append('action', 'upload');

  try {
    const r = await fetch('api/plugin_manager.php?action=upload', { method:'POST', body:fd });
    const j = await r.json();
    if (!j.success) throw new Error(j.error);
    status.innerHTML = `<span style="color:#4caf50">✓ ${j.message}</span>`;
    showToast(j.message, 'ok');
    setTimeout(loadPlugins, 600);
  } catch(e) {
    status.innerHTML = `<span style="color:#f44336">⚠ ${e.message}</span>`;
    showToast(e.message, 'err');
  }
}

// ── Activar / Desactivar ───────────────────────────────────────
async function togglePlugin(slug, activate) {
  const action = activate ? 'activate' : 'deactivate';
  try {
    const r = await fetch(`api/plugin_manager.php?action=${action}`, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ slug })
    });
    const j = await r.json();
    if (!j.success) throw new Error(j.error);
    showToast(j.message, 'ok');
    loadPlugins();
  } catch(e) { showToast(e.message, 'err'); }
}

// ── Desinstalar ────────────────────────────────────────────────
async function uninstall(slug, name) {
  if (!confirm(`¿Desinstalar "${name}"?\nSe borrarán todos los archivos del plugin.`)) return;
  try {
    const r = await fetch('api/plugin_manager.php?action=uninstall', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ slug })
    });
    const j = await r.json();
    if (!j.success) throw new Error(j.error);
    showToast(j.message, 'ok');
    loadPlugins();
  } catch(e) { showToast(e.message, 'err'); }
}

// ── Marketplace buy ────────────────────────────────────────────
function buyPlugin(slug, name, price) {
  const modal = document.getElementById('buy-modal');
  document.getElementById('bm-title').textContent  = name;
  document.getElementById('bm-price-usd').textContent = `USD ${price}`;
  document.getElementById('bm-price-ars').textContent = `≈ ARS ${(price * 1400).toLocaleString('es-AR')}`;
  document.getElementById('bm-slug').value  = slug;
  document.getElementById('bm-email').value = '';
  document.getElementById('bm-name').value  = '';
  document.getElementById('bm-error').textContent = '';
  document.getElementById('bm-btn-mp').disabled     = false;
  document.getElementById('bm-btn-stripe').disabled = false;
  document.getElementById('bm-loading').style.display = 'none';
  modal.style.display = 'flex';
  setTimeout(() => document.getElementById('bm-email').focus(), 100);
}

function closeBuyModal() {
  document.getElementById('buy-modal').style.display = 'none';
}

async function doBuy(gateway) {
  const email = document.getElementById('bm-email').value.trim();
  const name  = document.getElementById('bm-name').value.trim();
  const slug  = document.getElementById('bm-slug').value;
  const errEl = document.getElementById('bm-error');

  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errEl.textContent = 'Ingresá un email válido para recibir el link de descarga.';
    return;
  }
  errEl.textContent = '';

  document.getElementById('bm-btn-mp').disabled     = true;
  document.getElementById('bm-btn-stripe').disabled = true;
  document.getElementById('bm-loading').style.display = 'block';

  try {
    const r = await fetch('api/checkout.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ slug, gateway, email, buyer_name: name })
    });
    const j = await r.json();
    if (!j.success) throw new Error(j.error);
    // Redirigir al gateway de pago
    window.location.href = j.checkout_url;
  } catch(e) {
    errEl.textContent = '⚠ ' + e.message;
    document.getElementById('bm-btn-mp').disabled     = false;
    document.getElementById('bm-btn-stripe').disabled = false;
    document.getElementById('bm-loading').style.display = 'none';
  }
}

// Cerrar modal con Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeBuyModal();
});

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg, type = 'ok') {
  const t = document.getElementById('toast');
  t.textContent = type === 'ok' ? '✓ ' + msg : '⚠ ' + msg;
  t.className = type === 'ok' ? 'toast-ok' : 'toast-err';
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 3500);
}

// Init
loadPlugins();
</script>
</body>
</html>
