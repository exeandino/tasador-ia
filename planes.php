<?php
/**
 * TasadorIA — planes.php
 * Página pública de planes y precios.
 * Integra MercadoPago (LATAM) y Stripe (USA/Internacional).
 */
$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];

$brand    = $cfg['brand_name']   ?? 'TasadorIA';
$appUrl   = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');
$color    = $cfg['primary_color'] ?? '#c9a84c';
$currency = $_GET['currency']     ?? 'USD';   // USD o ARS
$arsRate  = (float)($cfg['ars_usd_rate'] ?? 1400);

// Detectar país aproximado por zona horaria del browser (JS lo ajusta)
$plans = [
    [
        'slug'       => 'free',
        'name'       => 'Free',
        'icon'       => '🆓',
        'price_usd'  => 0,
        'price_ars'  => 0,
        'billing'    => '',
        'color'      => '#444',
        'features'   => ['5 tasaciones', 'Reporte básico en pantalla', 'Wizard completo', 'Análisis de fotos IA'],
        'missing'    => ['PDF descargable', 'Historial guardado', 'Consenso multi-IA', 'BIM materiales', 'API access'],
        'cta'        => 'Empezar gratis',
        'cta_url'    => $appUrl,
        'highlight'  => false,
    ],
    [
        'slug'       => 'pro',
        'name'       => 'Pro',
        'icon'       => '⚡',
        'price_usd'  => 9,
        'price_ars'  => 12600,
        'billing'    => '/mes',
        'color'      => '#c9a84c',
        'features'   => ['Tasaciones ilimitadas', 'PDF descargable', 'Historial completo', 'Consenso multi-IA (Claude+GPT+Gemini+Grok)', 'API access', 'Soporte por email'],
        'missing'    => ['BIM materiales', 'Usuarios adicionales', 'White-label'],
        'cta'        => 'Comenzar Pro',
        'cta_url'    => '#checkout-pro',
        'highlight'  => true,
        'badge'      => '⭐ Más popular',
    ],
    [
        'slug'       => 'agency',
        'name'       => 'Agencia',
        'icon'       => '🏢',
        'price_usd'  => 29,
        'price_ars'  => 40600,
        'billing'    => '/mes',
        'color'      => '#5a9fd4',
        'features'   => ['Todo lo de Pro', 'Hasta 5 usuarios', 'BIM Materiales + corralones', 'CRM Export (HubSpot, Pipedrive)', 'Dashboard de agencia', 'Soporte prioritario'],
        'missing'    => ['White-label / dominio propio'],
        'cta'        => 'Comenzar Agencia',
        'cta_url'    => '#checkout-agency',
        'highlight'  => false,
    ],
    [
        'slug'       => 'enterprise',
        'name'       => 'Enterprise',
        'icon'       => '🏛',
        'price_usd'  => 99,
        'price_ars'  => 138600,
        'billing'    => '/mes',
        'color'      => '#9b59b6',
        'features'   => ['Todo lo de Agencia', 'Usuarios ilimitados', 'White-label + dominio propio', 'Zonas personalizadas', 'SLA y soporte dedicado', 'Onboarding incluido'],
        'missing'    => [],
        'cta'        => 'Contactar',
        'cta_url'    => "mailto:{$cfg['admin_email']}?subject=Plan%20Enterprise%20TasadorIA",
        'highlight'  => false,
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Planes y Precios · <?= htmlspecialchars($brand) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#0e0e0e;color:#ccc;min-height:100vh}

/* Header */
.header{text-align:center;padding:60px 20px 40px;background:linear-gradient(180deg,#161616 0%,transparent 100%)}
.header h1{font-size:36px;color:#eee;margin-bottom:10px}
.header h1 span{color:<?= $color ?>}
.header p{color:#666;font-size:16px;max-width:520px;margin:0 auto}

/* Currency toggle */
.currency-toggle{display:flex;justify-content:center;gap:0;margin:20px auto;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:8px;width:fit-content;overflow:hidden}
.currency-toggle button{background:none;border:none;padding:8px 24px;color:#666;cursor:pointer;font-size:13px;font-weight:600;transition:all .15s}
.currency-toggle button.active{background:<?= $color ?>;color:#000}

/* Plans grid */
.plans{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:20px;max-width:1100px;margin:0 auto;padding:20px}
.plan-card{background:#1a1a1a;border:1px solid #252525;border-radius:14px;padding:28px 24px;position:relative;transition:transform .15s,border-color .15s}
.plan-card:hover{transform:translateY(-3px)}
.plan-card.highlight{border-color:<?= $color ?>;box-shadow:0 0 30px rgba(201,168,76,.15)}
.plan-badge{position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:<?= $color ?>;color:#000;font-size:11px;font-weight:700;padding:3px 14px;border-radius:20px;white-space:nowrap}
.plan-icon{font-size:32px;margin-bottom:12px}
.plan-name{font-size:20px;font-weight:700;color:#eee;margin-bottom:6px}
.plan-price{margin:16px 0}
.plan-price .amount{font-size:36px;font-weight:800;color:#eee}
.plan-price .amount.free{color:#5aaa5a}
.plan-price .currency{font-size:18px;color:#888;margin-right:2px}
.plan-price .billing{font-size:13px;color:#555}
.plan-features{list-style:none;margin:16px 0 20px}
.plan-features li{padding:5px 0;font-size:13px;color:#aaa;display:flex;align-items:flex-start;gap:7px}
.plan-features li::before{content:'✓';color:#5aaa5a;font-weight:700;flex-shrink:0;margin-top:1px}
.plan-features li.missing{color:#444}
.plan-features li.missing::before{content:'–';color:#333}
.cta-btn{display:block;width:100%;padding:12px;border-radius:8px;font-size:14px;font-weight:700;text-align:center;text-decoration:none;cursor:pointer;border:none;transition:opacity .15s}
.cta-btn:hover{opacity:.85}
.cta-free{background:#1e3a1e;color:#5aaa5a;border:1px solid #2a4a2a}
.cta-gold{background:<?= $color ?>;color:#000}
.cta-blue{background:#1a3a5a;color:#5a9fd4;border:1px solid #2a4a6a}
.cta-purple{background:#2a1a3a;color:#9b59b6;border:1px solid #3a2a4a}

/* FAQ */
.faq{max-width:680px;margin:60px auto;padding:0 20px 60px}
.faq h2{text-align:center;font-size:22px;color:#eee;margin-bottom:30px}
.faq-item{border-bottom:1px solid #1e1e1e;padding:16px 0}
.faq-item summary{font-size:14px;color:#ccc;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center}
.faq-item summary::after{content:'+';color:#c9a84c;font-size:18px}
details[open] summary::after{content:'–'}
.faq-item p{font-size:13px;color:#666;margin-top:10px;line-height:1.6}

/* Modal checkout */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:14px;padding:32px;max-width:420px;width:90%;position:relative}
.modal h3{color:#eee;margin-bottom:6px}
.modal p{color:#666;font-size:13px;margin-bottom:20px}
.modal-close{position:absolute;top:12px;right:16px;background:none;border:none;color:#666;font-size:20px;cursor:pointer}
.modal input{width:100%;background:#111;border:1px solid #2a2a2a;color:#ddd;padding:10px 12px;border-radius:6px;font-size:14px;margin-bottom:10px;outline:none}
.modal input:focus{border-color:<?= $color ?>}
.gateway-btns{display:flex;flex-direction:column;gap:10px;margin-top:16px}
.btn-mp{background:#009ee3;color:#fff;border:none;padding:13px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-stripe{background:#635bff;color:#fff;border:none;padding:13px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-mp:hover,.btn-stripe:hover{opacity:.9}
.separator{text-align:center;color:#444;font-size:12px;margin:4px 0;position:relative}
.separator::before,.separator::after{content:'';position:absolute;top:50%;width:40%;height:1px;background:#222}
.separator::before{left:0}.separator::after{right:0}

@media(max-width:600px){.plans{grid-template-columns:1fr}.header h1{font-size:26px}}
</style>
</head>
<body>

<div class="header">
  <h1>🏠 <span><?= htmlspecialchars($brand) ?></span> · Planes</h1>
  <p>Tasaciones inmobiliarias con IA. Empezá gratis, escalá cuando quieras.</p>
  <div class="currency-toggle">
    <button id="btn-usd" class="active" onclick="setCurrency('USD')">🇺🇸 USD</button>
    <button id="btn-ars" onclick="setCurrency('ARS')">🇦🇷 ARS</button>
  </div>
</div>

<div class="plans">
<?php foreach ($plans as $p): ?>
<div class="plan-card <?= $p['highlight'] ? 'highlight' : '' ?>">
  <?php if (!empty($p['badge'])): ?>
    <div class="plan-badge"><?= $p['badge'] ?></div>
  <?php endif; ?>
  <div class="plan-icon"><?= $p['icon'] ?></div>
  <div class="plan-name"><?= $p['name'] ?></div>
  <div class="plan-price">
    <?php if ($p['price_usd'] === 0): ?>
      <span class="amount free">Gratis</span>
    <?php else: ?>
      <span class="currency" id="curr-<?= $p['slug'] ?>">USD</span><span class="amount" id="price-<?= $p['slug'] ?>"><?= $p['price_usd'] ?></span><span class="billing"><?= $p['billing'] ?></span>
    <?php endif; ?>
  </div>
  <ul class="plan-features">
    <?php foreach ($p['features'] as $f): ?>
      <li><?= htmlspecialchars($f) ?></li>
    <?php endforeach; ?>
    <?php foreach ($p['missing'] as $f): ?>
      <li class="missing"><?= htmlspecialchars($f) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php
    $btnClass = match($p['slug']) {
        'free'       => 'cta-free',
        'pro'        => 'cta-gold',
        'agency'     => 'cta-blue',
        'enterprise' => 'cta-purple',
    };
    if ($p['slug'] === 'enterprise'): ?>
      <a href="<?= $p['cta_url'] ?>" class="cta-btn <?= $btnClass ?>"><?= $p['cta'] ?></a>
    <?php elseif ($p['slug'] === 'free'): ?>
      <a href="<?= $p['cta_url'] ?>" class="cta-btn <?= $btnClass ?>"><?= $p['cta'] ?></a>
    <?php else: ?>
      <button class="cta-btn <?= $btnClass ?>" onclick="openCheckout('<?= $p['slug'] ?>')">
        <?= $p['cta'] ?>
      </button>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<!-- FAQ -->
<div class="faq">
  <h2>Preguntas frecuentes</h2>
  <details class="faq-item">
    <summary>¿Puedo pagar en pesos argentinos?</summary>
    <p>Sí. En Argentina procesamos pagos con <strong>MercadoPago</strong> en ARS. También aceptamos tarjetas internacionales vía Stripe en USD.</p>
  </details>
  <details class="faq-item">
    <summary>¿Qué pasa cuando termino mis 5 tasaciones gratis?</summary>
    <p>El sistema te avisa en la tasación número 4. Al intentar la 6ta, te mostrará esta página para elegir un plan. Tu historial queda guardado.</p>
  </details>
  <details class="faq-item">
    <summary>¿Puedo cancelar cuando quiera?</summary>
    <p>Sí, podés cancelar en cualquier momento desde tu perfil. El acceso continúa hasta el fin del período pagado.</p>
  </details>
  <details class="faq-item">
    <summary>¿Qué es el consenso multi-IA?</summary>
    <p>En vez de usar una sola IA, el sistema consulta Claude, GPT-4o, Gemini y Grok en paralelo. Cada uno da su precio y se calcula un consenso ponderado, aumentando la precisión de la valuación.</p>
  </details>
  <details class="faq-item">
    <summary>¿El plan Agencia incluye sub-usuarios?</summary>
    <p>Sí. El plan Agencia incluye hasta 5 usuarios bajo la misma cuenta, cada uno con su propio historial. Enterprise tiene usuarios ilimitados.</p>
  </details>
  <details class="faq-item">
    <summary>¿Cómo funciona el plan Enterprise?</summary>
    <p>Es white-label: podés poner tu propio dominio, logo y colores. Contactanos y armamos el onboarding juntos.</p>
  </details>
</div>

<!-- Modal Checkout -->
<div class="modal-overlay" id="checkoutModal">
  <div class="modal">
    <button class="modal-close" onclick="closeCheckout()">×</button>
    <h3 id="modalTitle">Suscribirse</h3>
    <p id="modalDesc">Ingresá tu email para continuar</p>
    <input type="email" id="checkoutEmail" placeholder="tu@email.com">
    <div class="gateway-btns">
      <button class="btn-mp" onclick="payWith('mercadopago')">
        <svg width="20" height="20" viewBox="0 0 32 32" fill="white"><circle cx="16" cy="16" r="16" fill="#009ee3"/><text x="5" y="22" font-size="14" fill="white" font-weight="bold">MP</text></svg>
        Pagar con MercadoPago
      </button>
      <div class="separator">o</div>
      <button class="btn-stripe" onclick="payWith('stripe')">
        <svg width="20" height="12" viewBox="0 0 60 25" fill="white"><path d="M24.5 9.5c0-1.1.9-1.5 2.3-1.5 2.1 0 4.7.6 6.8 1.7V4.2C31.6 3.4 29.2 3 26.8 3c-5.5 0-9.2 2.9-9.2 7.7 0 7.5 10.3 6.3 10.3 9.5 0 1.3-1.1 1.7-2.6 1.7-2.3 0-5.2-.9-7.5-2.2v5.6c2.5 1.1 5.1 1.5 7.5 1.5 5.7 0 9.6-2.8 9.6-7.7C34.8 11.2 24.5 12.6 24.5 9.5z"/></svg>
        Pagar con Stripe
      </button>
    </div>
    <p style="font-size:11px;color:#444;text-align:center;margin-top:12px">Pago seguro · Podés cancelar cuando quieras</p>
  </div>
</div>

<script>
const prices = {
  pro:    {USD: 9,  ARS: 12600},
  agency: {USD: 29, ARS: 40600},
};
let activeCurrency = 'USD';
let activePlan     = '';

function setCurrency(c) {
  activeCurrency = c;
  document.getElementById('btn-usd').classList.toggle('active', c === 'USD');
  document.getElementById('btn-ars').classList.toggle('active', c === 'ARS');
  ['pro','agency'].forEach(slug => {
    const el = document.getElementById('price-' + slug);
    const cc = document.getElementById('curr-' + slug);
    if (el && prices[slug]) {
      el.textContent = prices[slug][c].toLocaleString();
      cc.textContent = c;
    }
  });
}

// Auto-detect ARS si el navegador está en es-AR
if (navigator.language === 'es-AR' || Intl.DateTimeFormat().resolvedOptions().timeZone?.includes('Buenos_Aires')) {
  setCurrency('ARS');
}

function openCheckout(plan) {
  activePlan = plan;
  const names = {pro: 'Pro', agency: 'Agencia'};
  document.getElementById('modalTitle').textContent = 'Plan ' + names[plan];
  document.getElementById('modalDesc').textContent  = 'Ingresá tu email para continuar con el pago seguro';
  document.getElementById('checkoutModal').classList.add('open');
  document.getElementById('checkoutEmail').focus();
}

function closeCheckout() {
  document.getElementById('checkoutModal').classList.remove('open');
}

function payWith(gateway) {
  const email = document.getElementById('checkoutEmail').value.trim();
  if (!email || !email.includes('@')) {
    document.getElementById('checkoutEmail').style.borderColor = '#c94c4c';
    document.getElementById('checkoutEmail').focus();
    return;
  }
  // Redirigir a la API de pagos con plan, email y gateway
  const url = new URL('api/payment_init.php', window.location.href);
  url.searchParams.set('plan',     activePlan);
  url.searchParams.set('email',    email);
  url.searchParams.set('gateway',  gateway);
  url.searchParams.set('currency', activeCurrency);
  window.location.href = url.toString();
}

// Cerrar al hacer click fuera
document.getElementById('checkoutModal').addEventListener('click', function(e) {
  if (e.target === this) closeCheckout();
});
</script>

</body>
</html>
