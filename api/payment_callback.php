<?php
/**
 * TasadorIA — api/payment_callback.php
 * Retorno post-pago desde MercadoPago (back_url) y Stripe (success_url).
 *
 * GET ?gw=mp&status=success&plan=pro
 * GET ?gw=stripe&status=success&plan=pro&session_id=cs_xxx
 *
 * Verifica que la suscripción ya esté activa (el webhook puede llegar
 * antes o después de este redirect). Muestra pantalla de éxito.
 */

$cfg     = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/config/settings.php' : [];
require  __DIR__.'/../auth/middleware.php';

$brand   = $cfg['brand_name']   ?? 'TasadorIA';
$color   = $cfg['primary_color']?? '#c9a84c';
$appUrl  = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');
$gw      = $_GET['gw']      ?? 'mp';
$plan    = preg_replace('/[^a-z_]/', '', $_GET['plan'] ?? 'pro');
$status  = $_GET['status']  ?? '';
$sessId  = $_GET['session_id'] ?? '';  // Stripe

// ── Intentar leer usuario autenticado ──────────────────────────
$user = null;
try {
    $pdo  = authPdo($cfg);
    $user = getCurrentUser($cfg);

    // Si hay usuario autenticado, verificar si el tier ya se actualizó
    $activated = false;
    if ($user) {
        $stmt = $pdo->prepare("SELECT tier FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $activated = $row && in_array($row['tier'], ['pro','agency','enterprise']);
        $currentTier = $row['tier'] ?? 'free';
    }
} catch (\Throwable $e) {
    $activated   = false;
    $currentTier = 'free';
}

$planLabels = [
    'pro'        => 'Pro',
    'agency'     => 'Agencia',
    'enterprise' => 'Enterprise',
];
$planLabel = $planLabels[$plan] ?? ucfirst($plan);

$gwLabel = $gw === 'stripe' ? 'Stripe' : 'MercadoPago';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>¡Pago exitoso! · <?= htmlspecialchars($brand) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0e0e0e;color:#ccc;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}
.card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:16px;padding:40px 36px;max-width:480px;width:100%;text-align:center}
.icon{font-size:52px;margin-bottom:16px}
h1{color:#eee;font-size:22px;margin-bottom:8px}
.sub{color:#666;font-size:14px;margin-bottom:28px;line-height:1.5}
.plan-badge{display:inline-block;background:rgba(201,168,76,.15);color:<?= $color ?>;border:1px solid rgba(201,168,76,.3);border-radius:20px;padding:6px 18px;font-size:13px;font-weight:700;margin-bottom:28px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 24px;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;border:none;text-decoration:none;transition:opacity .15s;width:100%;margin-bottom:10px}
.btn:hover{opacity:.85}
.btn-gold{background:<?= $color ?>;color:#000}
.btn-outline{background:transparent;border:1px solid #2a2a2a;color:#888}
.spinner{display:inline-block;width:20px;height:20px;border:2px solid #333;border-top-color:<?= $color ?>;border-radius:50%;animation:spin .7s linear infinite;margin-bottom:12px}
@keyframes spin{to{transform:rotate(360deg)}}
.pending-msg{color:#666;font-size:13px}
.divider{border:none;border-top:1px solid #1e1e1e;margin:20px 0}
.small{font-size:12px;color:#555}
</style>
</head><body>

<div class="card" id="card">
<?php if ($status === 'success'): ?>

  <div class="icon">🎉</div>
  <h1>¡Pago recibido!</h1>
  <p class="sub">Tu pago via <strong><?= $gwLabel ?></strong> fue procesado.<br>
  Activando plan <strong><?= htmlspecialchars($planLabel) ?></strong>…</p>

  <div id="planBadge" style="display:<?= $activated ? 'block' : 'none' ?>">
    <div class="plan-badge">✅ Plan <?= htmlspecialchars($planLabel) ?> activo</div>
    <a href="<?= $appUrl ?>/mi-cuenta.php" class="btn btn-gold">🏠 Ir a mi cuenta</a>
    <a href="<?= $appUrl ?>" class="btn btn-outline">Hacer una tasación</a>
  </div>

  <div id="pendingBlock" style="<?= $activated ? 'display:none' : '' ?>">
    <div class="spinner"></div>
    <p class="pending-msg">Verificando activación del plan… <span id="dots">.</span></p>
    <p class="small" style="margin-top:8px">Esto puede tomar unos segundos.</p>
  </div>

  <hr class="divider">
  <p class="small">¿Problemas? Escribinos a <a href="mailto:<?= htmlspecialchars($cfg['smtp']['from'] ?? 'hola@tasadoria.com') ?>" style="color:<?= $color ?>"><?= htmlspecialchars($cfg['smtp']['from'] ?? 'hola@tasadoria.com') ?></a></p>

<?php elseif ($status === 'pending'): ?>
  <div class="icon">⏳</div>
  <h1>Pago pendiente</h1>
  <p class="sub">Tu pago está siendo procesado por <?= $gwLabel ?>. Recibirás un email cuando se confirme.</p>
  <a href="<?= $appUrl ?>/mi-cuenta.php" class="btn btn-outline">Ir a mi cuenta</a>

<?php else: ?>
  <div class="icon">❌</div>
  <h1>Algo salió mal</h1>
  <p class="sub">No pudimos confirmar tu pago. Por favor intentá de nuevo.</p>
  <a href="<?= $appUrl ?>/planes.php" class="btn btn-gold">Ver planes</a>

<?php endif; ?>
</div>

<?php if ($status === 'success' && !$activated): ?>
<script>
// Polling: verificar cada 2s si el webhook activó el plan (máx 30s)
let attempts = 0;
const maxAttempts = 15;
const dots = document.getElementById('dots');
let dotCount = 1;

const timer = setInterval(async () => {
  attempts++;
  dotCount = (dotCount % 3) + 1;
  dots.textContent = '.'.repeat(dotCount);

  try {
    const r = await fetch('<?= $appUrl ?>/api/check_tier.php?plan=<?= urlencode($plan) ?>', {credentials: 'include'});
    const d = await r.json();
    if (d.activated) {
      clearInterval(timer);
      document.getElementById('pendingBlock').style.display = 'none';
      document.getElementById('planBadge').style.display = 'block';
    }
  } catch(e) {}

  if (attempts >= maxAttempts) {
    clearInterval(timer);
    document.getElementById('pendingBlock').innerHTML =
      '<p style="color:#888;font-size:13px">La activación puede demorar unos minutos. Revisá <a href="<?= $appUrl ?>/mi-cuenta.php" style="color:<?= $color ?>">tu cuenta</a>.</p>';
  }
}, 2000);
</script>
<?php endif; ?>

</body></html>
