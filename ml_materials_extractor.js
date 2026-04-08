/**
 * TasadorIA — ml_materials_extractor.js
 * Bookmarklet: extrae precios de materiales de construcción desde MercadoLibre.
 *
 * IMPORTANTE: debe correrse estando en mercadolibre.com.ar
 * Si se corre en otro dominio, redirige automáticamente.
 */
(async function () {
  'use strict';

  const SERVER  = 'https://anperprimo.com/tasador';
  const ML_BASE = 'https://listado.mercadolibre.com.ar';
  const ML_HOST = 'mercadolibre.com.ar';

  // ── Detección de dominio ─────────────────────────────────────
  if (!location.hostname.includes(ML_HOST)) {
    // No estamos en ML — mostrar overlay de instrucción
    const warn = document.createElement('div');
    warn.style.cssText = `
      position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.85);
      z-index:9999999;display:flex;align-items:center;justify-content:center;
      font-family:system-ui,sans-serif;
    `;
    warn.innerHTML = `
      <div style="background:#1a1a1a;border:2px solid #c9a84c;border-radius:14px;padding:32px;max-width:420px;text-align:center">
        <div style="font-size:40px;margin-bottom:12px">🏗</div>
        <div style="font-size:16px;font-weight:700;color:#c9a84c;margin-bottom:8px">TasadorIA — Materiales BIM</div>
        <div style="color:#aaa;font-size:13px;line-height:1.6;margin-bottom:20px">
          Este bookmarklet debe ejecutarse <strong style="color:#fff">desde MercadoLibre</strong>.<br>
          Hacé clic en el botón para ir a ML y ejecutarlo automáticamente.
        </div>
        <a href="https://www.mercadolibre.com.ar/?ta_bim=1"
           style="display:inline-block;background:#c9a84c;color:#000;font-weight:700;padding:10px 24px;border-radius:8px;text-decoration:none;font-size:14px">
          Ir a MercadoLibre y ejecutar →
        </a>
        <div style="margin-top:12px">
          <button onclick="this.closest('div').parentElement.remove()"
            style="background:none;border:none;color:#666;cursor:pointer;font-size:12px">Cerrar</button>
        </div>
      </div>`;
    document.body.appendChild(warn);
    return;
  }

  // ── Mapeo materiales ─────────────────────────────────────────
  const QUERIES = [
    { id:1,  name:'Cemento Portland Normal 50kg',       q:'cemento portland 50kg' },
    { id:2,  name:'Hierro en barra Ø8mm × 12m',        q:'hierro redondo 8mm barra 12m' },
    { id:3,  name:'Hierro en barra Ø10mm × 12m',       q:'hierro redondo 10mm barra 12m' },
    { id:4,  name:'Hierro en barra Ø12mm × 12m',       q:'hierro redondo 12mm barra 12m' },
    { id:5,  name:'Hierro en barra Ø16mm × 12m',       q:'hierro redondo 16mm barra 12m' },
    { id:6,  name:'Malla sima 15×15 panel 2.1×4.3m',   q:'malla sima electrosoldada panel' },
    { id:7,  name:'Arena gruesa 30kg',                  q:'arena gruesa construccion bolsa' },
    { id:8,  name:'Arena fina 25kg',                    q:'arena fina revoque bolsa' },
    { id:9,  name:'Piedra partida 6/20 30kg',           q:'piedra partida triturada bolsa' },
    { id:10, name:'Block hormigón 20×20×40cm',          q:'block hormigon 20x20x40' },
    { id:11, name:'Ladrillo cerámico macizo 25×12×8cm', q:'ladrillo macizo ceramico' },
    { id:12, name:'Ladrillo cerámico hueco 18×18×33cm', q:'ladrillo ceramico hueco 18x18x33' },
    { id:13, name:'Cal hidratada 30kg',                 q:'cal hidratada bolsa 30kg' },
    { id:14, name:'Yeso fino 40kg',                     q:'yeso fino bolsa construccion' },
    { id:15, name:'Adhesivo cerámico 30kg',             q:'adhesivo ceramico flexible 30kg' },
    { id:16, name:'Membrana asfáltica 4mm',             q:'membrana asfaltica 4mm aluminio' },
    { id:17, name:'Lana de vidrio 50mm rollo',          q:'lana de vidrio aislante rollo' },
    { id:18, name:'Chapa galvanizada acanalada 3m',     q:'chapa galvanizada acanalada 3m' },
    { id:19, name:'Teja colonial cerámica',             q:'teja colonial ceramica' },
    { id:20, name:'Perfil C galvanizado 100×50',        q:'perfil c galvanizado 100x50' },
    { id:21, name:'Cable unipolar 2.5mm 100m',          q:'cable unipolar 2.5mm rollo 100m' },
    { id:22, name:'Cable unipolar 4mm 100m',            q:'cable unipolar 4mm rollo 100m' },
    { id:23, name:'Cañería corrugada 20mm 25m',         q:'cañeria corrugada 20mm rollo' },
    { id:24, name:'Tablero eléctrico domiciliario',     q:'tablero electrico domiciliario llaves' },
    { id:25, name:'Llave térmica 10A',                  q:'llave termica 10 amperes' },
    { id:26, name:'Tomacorriente IRAM 10A',             q:'tomacorriente iram 10 amperes' },
    { id:27, name:'Caño PVC 110mm desagüe 3m',         q:'caño pvc 110mm desague 3m' },
    { id:28, name:'Caño PVC presión 32mm 6m',           q:'caño pvc presion 32mm 6m' },
    { id:29, name:'Inodoro cerámica mochila',           q:'inodoro ceramica mochila' },
    { id:30, name:'Lavatorio baño cerámica',            q:'lavatorio ceramica baño' },
    { id:31, name:'Grifería monocomando',               q:'griferia monocomando baño' },
    { id:32, name:'Termotanque eléctrico 80L',          q:'termotanque electrico 80 litros' },
    { id:33, name:'Porcelanato rectificado 60×60',      q:'porcelanato rectificado 60x60' },
    { id:34, name:'Cerámica piso 35×35',                q:'ceramica piso antideslizante 35x35 caja' },
    { id:35, name:'Azulejo 20×30 caja',                 q:'azulejo 20x30 caja primera' },
    { id:36, name:'Pintura látex lavable 10L',          q:'pintura latex lavable interior 10 litros' },
    { id:37, name:'Pintura exterior frente 4L',         q:'pintura exterior frente 4 litros' },
    { id:38, name:'Puerta placa interior 80×200',       q:'puerta placa interior 80x200 marco' },
    { id:39, name:'Ventana aluminio corrediza',         q:'ventana aluminio corrediza vidrio' },
    { id:40, name:'Ventana aluminio DVH',               q:'ventana aluminio dvh doble vidrio' },
    { id:41, name:'Masilla plástica 5kg',               q:'masilla plastica interior balde 5kg' },
    { id:42, name:'Pastina cerámica 2kg',               q:'pastina ceramica juntas 2kg' },
    { id:43, name:'Caño de cobre ½" 3m',               q:'caño cobre 1/2 pulgada 3m' },
    { id:44, name:'Ducha eléctrica',                    q:'ducha electrica' },
    { id:45, name:'Calefactor tiro balanceado',         q:'calefactor tiro balanceado gas' },
    { id:46, name:'Cocina gas 4 hornallas',             q:'cocina gas 4 hornallas' },
    { id:47, name:'Disyuntor termomagnetico 20A',       q:'disyuntor termomagnetico 20 amperes' },
    { id:48, name:'Revestimiento cerámico 30×60',       q:'revestimiento ceramico pared 30x60' },
    { id:49, name:'Parquet flotante laminado',          q:'parquet flotante laminado piso' },
    { id:50, name:'Porcelanato símil madera',           q:'porcelanato simil madera piso' },
  ];

  function mlUrl(q) {
    return `${ML_BASE}/${encodeURIComponent(q).replace(/%20/g,'-')}_NoIndex_True`;
  }
  function mlSearchUrl(q) {
    return `https://www.mercadolibre.com.ar/jm/search?as_word=${encodeURIComponent(q)}`;
  }
  function median(arr) {
    const s = [...arr].sort((a,b)=>a-b), n=s.length;
    return n%2===0 ? (s[n/2-1]+s[n/2])/2 : s[Math.floor(n/2)];
  }
  const fmt = n => Math.round(n).toLocaleString('es-AR');

  // ── Remover overlay anterior ─────────────────────────────────
  document.getElementById('__ta_bim_overlay')?.remove();

  // ── UI principal ─────────────────────────────────────────────
  const overlay = document.createElement('div');
  overlay.id    = '__ta_bim_overlay';
  overlay.style.cssText = `
    position:fixed;top:0;right:0;width:420px;height:100vh;background:#1a1a1a;
    color:#eee;font-family:system-ui,sans-serif;font-size:12px;z-index:999999;
    box-shadow:-4px 0 24px rgba(0,0,0,.7);display:flex;flex-direction:column;
    border-left:3px solid #c9a84c;
  `;
  overlay.innerHTML = `
    <div style="padding:12px 16px;background:#252525;border-bottom:1px solid #333;display:flex;align-items:center;gap:8px;flex-shrink:0">
      <span style="font-size:16px">🏗</span>
      <div style="flex:1">
        <div style="font-weight:700;color:#c9a84c;font-size:13px">TasadorIA — Materiales BIM</div>
        <div style="color:#666;font-size:10px">MercadoLibre · ${QUERIES.length} materiales</div>
      </div>
      <button id="__ta_close" style="background:none;border:none;color:#666;font-size:16px;cursor:pointer;padding:2px 6px">✕</button>
    </div>

    <!-- Tabs -->
    <div style="display:flex;border-bottom:1px solid #333;flex-shrink:0">
      <button id="__ta_tab_auto" onclick="__ta_switchTab('auto')"
        style="flex:1;padding:8px;background:#1a1a1a;border:none;border-bottom:2px solid #c9a84c;color:#c9a84c;font-size:11px;font-weight:700;cursor:pointer">
        ▶ Auto (${QUERIES.length})
      </button>
      <button id="__ta_tab_links" onclick="__ta_switchTab('links')"
        style="flex:1;padding:8px;background:#111;border:none;border-bottom:2px solid transparent;color:#666;font-size:11px;cursor:pointer">
        🔗 Links por material
      </button>
    </div>

    <!-- Panel Auto -->
    <div id="__ta_panel_auto" style="display:flex;flex-direction:column;flex:1;overflow:hidden">
      <div style="padding:10px 14px;background:#1e1e1e;border-bottom:1px solid #333;flex-shrink:0">
        <div style="display:flex;gap:8px;margin-bottom:8px">
          <div style="flex:1">
            <div style="font-size:9px;color:#666;margin-bottom:3px;text-transform:uppercase">USD/ARS</div>
            <input id="__ta_usd" type="number" value="1400"
              style="width:100%;background:#2a2a2a;border:1px solid #444;color:#eee;padding:5px 8px;border-radius:5px;font-size:12px">
          </div>
          <div style="flex:2">
            <div style="font-size:9px;color:#666;margin-bottom:3px;text-transform:uppercase">Servidor</div>
            <input id="__ta_srv" type="text" value="${SERVER}"
              style="width:100%;background:#2a2a2a;border:1px solid #444;color:#eee;padding:5px 8px;border-radius:5px;font-size:11px">
          </div>
        </div>
        <button id="__ta_run"
          style="width:100%;padding:8px;background:#c9a84c;color:#000;font-weight:700;border:none;border-radius:7px;cursor:pointer;font-size:12px">
          ▶ Buscar todos los materiales (${QUERIES.length})
        </button>
      </div>
      <div id="__ta_progress" style="padding:6px 14px;font-size:10px;color:#c9a84c;display:none;flex-shrink:0"></div>
      <div id="__ta_log" style="flex:1;overflow-y:auto;padding:6px 14px"></div>
      <div id="__ta_footer" style="display:none;padding:10px 14px;background:#1e1e1e;border-top:1px solid #333;flex-shrink:0">
        <div id="__ta_summary" style="font-size:11px;color:#888;margin-bottom:8px"></div>
        <button id="__ta_save"
          style="width:100%;padding:8px;background:#4caf50;color:#fff;font-weight:700;border:none;border-radius:7px;cursor:pointer;font-size:12px">
          💾 Guardar en BIM
        </button>
      </div>
    </div>

    <!-- Panel Links -->
    <div id="__ta_panel_links" style="display:none;flex:1;overflow:hidden;flex-direction:column">
      <div style="padding:8px 14px;background:#1e1e1e;border-bottom:1px solid #333;flex-shrink:0;font-size:10px;color:#888">
        Hacé clic en 🔍 para ver los resultados en ML · Los precios se actualizan al buscar en modo Auto
      </div>
      <div id="__ta_links_list" style="flex:1;overflow-y:auto;padding:6px 14px"></div>
    </div>
  `;
  document.body.appendChild(overlay);

  // ── Tab switcher ─────────────────────────────────────────────
  window.__ta_switchTab = function(tab) {
    const isAuto = tab === 'auto';
    document.getElementById('__ta_panel_auto').style.display  = isAuto ? 'flex' : 'none';
    document.getElementById('__ta_panel_auto').style.flexDirection = 'column';
    document.getElementById('__ta_panel_links').style.display = isAuto ? 'none' : 'flex';
    document.getElementById('__ta_panel_links').style.flexDirection = 'column';
    document.getElementById('__ta_tab_auto').style.cssText  = `flex:1;padding:8px;background:#1a1a1a;border:none;border-bottom:2px solid ${isAuto?'#c9a84c':'transparent'};color:${isAuto?'#c9a84c':'#666'};font-size:11px;font-weight:${isAuto?'700':'400'};cursor:pointer`;
    document.getElementById('__ta_tab_links').style.cssText = `flex:1;padding:8px;background:#111;border:none;border-bottom:2px solid ${!isAuto?'#c9a84c':'transparent'};color:${!isAuto?'#c9a84c':'#666'};font-size:11px;font-weight:${!isAuto?'700':'400'};cursor:pointer`;
  };

  // ── Poblar lista de links ─────────────────────────────────────
  const linksList = document.getElementById('__ta_links_list');
  QUERIES.forEach(mat => {
    const row = document.createElement('div');
    row.id    = `__ta_link_${mat.id}`;
    row.style.cssText = 'padding:5px 0;border-bottom:1px solid #2a2a2a;display:flex;align-items:center;gap:6px';
    row.innerHTML = `
      <span style="font-size:10px;color:#555;width:18px;text-align:right;flex-shrink:0">${mat.id}</span>
      <span style="flex:1;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${mat.name}">${mat.name}</span>
      <span id="__ta_lp_${mat.id}" style="font-size:10px;color:#555;white-space:nowrap">—</span>
      <a href="${mlSearchUrl(mat.q)}" target="_blank" rel="noopener"
        style="background:#fff1;border:1px solid #444;color:#c9a84c;padding:2px 7px;border-radius:4px;font-size:10px;text-decoration:none;flex-shrink:0;white-space:nowrap">
        🔍 ML
      </a>
    `;
    linksList.appendChild(row);
  });

  document.getElementById('__ta_close').onclick = () => overlay.remove();

  // ── Búsqueda API ML ──────────────────────────────────────────
  async function searchML(query) {
    const url = `https://api.mercadolibre.com/sites/MLA/search?q=${encodeURIComponent(query)}&limit=12&sort=relevance`;
    const r   = await fetch(url, { credentials:'include', headers:{'Accept':'application/json'} });
    if (!r.ok) throw new Error(`API HTTP ${r.status}`);
    const d = await r.json();
    return d.results || [];
  }

  // Fallback: parsear __NEXT_DATA__ de la página de resultados
  async function searchViaPage(query) {
    const url = `${ML_BASE}/${encodeURIComponent(query).replace(/%20/g,'-')}_NoIndex_True`;
    const r   = await fetch(url, { credentials:'include' });
    if (!r.ok) throw new Error(`Page HTTP ${r.status}`);
    const html = await r.text();
    const m    = html.match(/<script[^>]+id="__NEXT_DATA__"[^>]*>([^<]+)<\/script>/);
    if (!m) throw new Error('Sin __NEXT_DATA__');
    const nd  = JSON.parse(m[1]);
    const res = nd?.props?.pageProps?.initialSearchData?.results
             || nd?.props?.pageProps?.searchResult?.results
             || nd?.props?.pageProps?.results
             || [];
    if (!res.length) throw new Error('0 resultados');
    return res.map(i => ({
      price: i.price || 0,
      seller_address: i.seller_address || { state:{id:''} },
    }));
  }

  // ── Ejecución auto ────────────────────────────────────────────
  const collected = [];
  const log       = document.getElementById('__ta_log');
  const prog      = document.getElementById('__ta_progress');
  const footer    = document.getElementById('__ta_footer');
  const sumEl     = document.getElementById('__ta_summary');

  // Cabecera tabla
  function initTable() {
    log.innerHTML = `
      <div style="display:grid;grid-template-columns:14px 1fr 70px 70px 40px;gap:4px;color:#555;padding:3px 0;border-bottom:1px solid #333;font-size:9px;text-transform:uppercase;margin-bottom:2px">
        <span></span><span>Material</span><span style="text-align:right">Ant.</span><span style="text-align:right">Nuevo</span><span style="text-align:right">Δ</span>
      </div>`;
  }

  function addRow(icon, mat, newPrice, delta, color) {
    const row = document.createElement('div');
    row.style.cssText = 'display:grid;grid-template-columns:14px 1fr 70px 70px 40px;gap:4px;padding:3px 0;border-bottom:1px solid #2a2a2a;align-items:center';
    row.innerHTML = `
      <a href="${mlSearchUrl(mat.q)}" target="_blank" title="Ver en ML" style="color:#c9a84c;text-decoration:none;font-size:11px">${icon}</a>
      <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:10px" title="${mat.name}">${mat.name}</span>
      <span style="text-align:right;color:#555;font-size:10px">—</span>
      <span style="text-align:right;font-weight:600;font-size:10px;color:${color}">${newPrice ? '$'+fmt(newPrice) : '<span style="color:#f44336">error</span>'}</span>
      <span style="text-align:right;font-size:10px;color:${color}">${delta}</span>
    `;
    // Actualizar también el panel de links
    const lp = document.getElementById(`__ta_lp_${mat.id}`);
    if (lp && newPrice) lp.textContent = '$'+fmt(newPrice);
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
  }

  function addErrRow(mat, msg) {
    const row = document.createElement('div');
    row.style.cssText = 'display:grid;grid-template-columns:14px 1fr auto;gap:4px;padding:3px 0;border-bottom:1px solid #2a2a2a;align-items:center';
    row.innerHTML = `
      <a href="${mlSearchUrl(mat.q)}" target="_blank" title="Buscar manualmente en ML" style="color:#f44336;text-decoration:none;font-size:11px">✗</a>
      <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:10px;color:#f44336" title="${mat.name}">${mat.name}</span>
      <span style="font-size:9px;color:#f44336">${msg}</span>
    `;
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
  }

  document.getElementById('__ta_run').onclick = async function() {
    this.disabled = true; this.textContent = '⏳ Buscando…';
    prog.style.display = 'block';
    initTable();
    collected.length = 0;
    let ok=0, err=0, skip=0;

    for (let i=0; i<QUERIES.length; i++) {
      const mat = QUERIES[i];
      prog.innerHTML = `[${i+1}/${QUERIES.length}] <strong>${mat.name}</strong>`;

      let items = null;
      try { items = await searchML(mat.q); }
      catch(e) {
        try { items = await searchViaPage(mat.q); }
        catch(e2) { err++; addErrRow(mat, e2.message); await new Promise(r=>setTimeout(r,200)); continue; }
      }

      const prices = items.map(x=>x.price||0).filter(p=>p>100);
      if (!prices.length) { skip++; addErrRow(mat, 'sin resultados'); await new Promise(r=>setTimeout(r,200)); continue; }

      const med      = Math.round(median(prices));
      const fletes   = items.map(x => ['AR-C','AR-B'].includes(x.seller_address?.state?.id||'') ? 9 : 0);
      const avgFlete = Math.round(fletes.reduce((a,b)=>a+b,0)/fletes.length * 10)/10;

      ok++;
      collected.push({ id:mat.id, material:mat.name, price_ars:med, flete_pct:avgFlete, count:prices.length, query:mat.q });
      addRow('✓', mat, med, avgFlete>0?`+${avgFlete}%`:'—', '#4caf50');
      await new Promise(r=>setTimeout(r,350));
    }

    prog.innerHTML = `<strong style="color:#4caf50">✓ Completado</strong> — ${ok} encontrados · ${err} errores · ${skip} sin datos`;
    if (collected.length>0) { sumEl.textContent=`${collected.length} materiales listos para guardar`; footer.style.display='block'; }
    this.disabled=false; this.textContent='🔄 Buscar de nuevo';
  };

  // ── Guardar ──────────────────────────────────────────────────
  document.getElementById('__ta_save').onclick = async function() {
    this.disabled=true; this.textContent='⏳ Guardando…';
    const srv = document.getElementById('__ta_srv').value.replace(/\/$/,'');
    const usd = parseFloat(document.getElementById('__ta_usd').value)||1400;
    try {
      const r = await fetch(`${srv}/api/save_material_prices.php`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({prices:collected, usd_rate:usd}), credentials:'omit',
      });
      const j = await r.json();
      if (j.success) {
        sumEl.innerHTML = `<span style="color:#4caf50">✓ ${j.saved} materiales · ${j.zones_updated} zonas actualizadas</span>`;
        this.textContent='✓ Guardado'; this.style.background='#388e3c';
      } else { throw new Error(j.error||'Error'); }
    } catch(e) {
      sumEl.innerHTML=`<span style="color:#f44336">⚠ ${e.message}</span>`;
      this.disabled=false; this.textContent='💾 Reintentar';
    }
  };

  // Auto-run si viene de redirect desde otra página
  if (location.search.includes('ta_bim=1')) {
    setTimeout(() => document.getElementById('__ta_run')?.click(), 800);
  }

})();
