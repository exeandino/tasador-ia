<?php
/**
 * TasadorIA — api/payment_success.php
 * Página de retorno tras pago exitoso (MP back_url / Stripe success_url).
 *
 * GET ?gateway=mp&ref=<order_id>
 * GET ?gateway=stripe&session_id=<cs_xxx>&ref=<order_id>
 *
 * Muestra mensaje de éxito y enlace de descarga si el pago ya está aprobado.
 * Si aún está pendiente (webhook no llegó), muestra spinner con polling.
 */
session_start();
header('Content-Type: text/html; charset=utf-8');

$cfg     = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
$gateway = $_GET['gateway'] ?? 'mp';
$ref     = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['ref'] ?? '');
$siteUrl = rtrim($cfg['site_url'] ?? 'https://anperprimo.com/tasador', '/');

// BD
$row = null;
try {
    $pdo = new PDO(
        'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
        $cfg['db']['user']??'', $cfg['db']['pass']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    if ($ref) {
        $stmt = $pdo->prepare("SELECT * FROM tasador_purchases WHERE order_id=? LIMIT 1");
        $stmt->execute([$ref]);
        $row = $stmt->fetch();
    }
} catch (\Throwable $e) {
    // silently continue — UI will show generic message
}

$approved     = ($row && $row['status'] === 'approved');
$downloadUrl  = ($approved && $row['download_token'])
    ? "$siteUrl/api/download_plugin.php?token={$row['download_token']}"
    : null;
$pluginName   = $row['plugin_name'] ?? 'Plugin';
$buyerEmail   = $row['email']       ?? '';
$expires      = ($approved && $row['download_expires'])
    ? date('d/m/Y H:i', strtotime($row['download_expires']))
    : null;
$gatewayLabel = ($gateway === 'stripe') ? 'Stripe' : 'Mercado Pago';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pago exitoso — TasadorIA</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f5f5f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:16px;overflow:hidden;max-width:540px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.1)}
.header{background:#1a1a1a;padding:28px;text-align:center}
.header h1{color:#c9a84c;font-size:22px}
.header p{color:#888;font-size:12px;margin-top:4px}
.body{padding:36px}
.icon{font-size:52px;text-align:center;margin-bottom:16px}
h2{font-size:22px;color:#1a1a1a;text-align:center;margin-bottom:8px}
.subtitle{color:#666;text-align:center;font-size:14px;line-height:1.6;margin-bottom:24px}
.btn-download{display:block;background:#c9a84c;color:#000;font-weight:700;padding:16px 32px;border-radius:10px;text-decoration:none;font-size:16px;text-align:center;margin:0 auto 16px;transition:opacity .2s}
.btn-download:hover{opacity:.85}
.info-box{background:#f9f9f9;border-radius:8px;padding:16px;font-size:13px;color:#666;line-height:1.8;margin-bottom:20px}
.info-box strong{color:#333}
.steps{background:#fff8e6;border:1px solid #f0d080;border-radius:8px;padding:16px;font-size:13px;color:#555;line-height:1.9}
.steps ol{padding-left:20px}
.footer{background:#f0f0f0;padding:16px;text-align:center;font-size:11px;color:#aaa}
.footer a{color:#c9a84c}
/* Spinner for pending */
.spinner{display:inline-block;width:40px;height:40px;border:4px solid #ddd;border-top-color:#c9a84c;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 20px;display:block}
@keyframes spin{to{transform:rotate(360deg)}}
.pending-msg{text-align:center;color:#888;font-size:14px}
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>🏗 TasadorIA</h1>
    <p>Sistema de valuación inmobiliaria</p>
  </div>
  <div class="body">

<?php if ($approved && $downloadUrl): ?>

    <div class="icon">✅</div>
    <h2>¡Pago aprobado!</h2>
    <p class="subtitle">Tu compra fue procesada exitosamente vía <strong><?= $gatewayLabel ?></strong>.<br>
    <?php if ($buyerEmail): ?>También enviamos el link a <strong><?= htmlspecialchars($buyerEmail) ?></strong>.<?php endif; ?></p>

    <div class="info-box">
      📦 <strong>Plugin:</strong> <?= htmlspecialchars($pluginName) ?><br>
      ⏰ <strong>Link válido hasta:</strong> <?= $expires ?><br>
      🔁 <strong>Descargas permitidas:</strong> hasta 5 veces
    </div>

    <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn-download">
      📥 Descargar Plugin Ahora
    </a>

    <div class="steps">
      <strong>¿Cómo instalarlo?</strong>
      <ol>
        <li>Descargá el ZIP con el botón de arriba</li>
        <li>Andá al panel admin → <strong>Plugins</strong></li>
        <li>Arrastrá el ZIP a la zona de upload</li>
        <li>Activá el plugin con un clic</li>
      </ol>
    </div>

<?php elseif ($row && $row['status'] === 'pending'): ?>

    <div id="spinner-area">
      <div class="icon">⏳</div>
      <h2>Procesando pago...</h2>
      <p class="subtitle">El pago fue recibido y está siendo confirmado.<br>Esto suele tardar menos de 30 segundos.</p>
      <div class="spinner" id="spinner"></div>
      <p class="pending-msg" id="pending-msg">Verificando confirmación del pago...</p>
    </div>
    <div id="approved-area" style="display:none">
      <div class="icon">✅</div>
      <h2>¡Pago confirmado!</h2>
      <p class="subtitle">Recargando...</p>
    </div>

    <script>
    (function() {
      var attempts = 0;
      var maxAttempts = 30;
      var ref = <?= json_encode($ref) ?>;
      var siteUrl = <?= json_encode($siteUrl) ?>;

      function check() {
        attempts++;
        if (attempts > maxAttempts) {
          document.getElementById('pending-msg').textContent =
            'La confirmación está tardando más de lo esperado. Revisá tu email en unos minutos.';
          document.getElementById('spinner').style.display = 'none';
          return;
        }
        fetch(siteUrl + '/api/checkout.php?action=check&ref=' + encodeURIComponent(ref))
          .then(function(r){ return r.json(); })
          .then(function(d) {
            if (d.status === 'approved') {
              document.getElementById('spinner-area').style.display = 'none';
              document.getElementById('approved-area').style.display = 'block';
              setTimeout(function(){ window.location.reload(); }, 1200);
            } else {
              setTimeout(check, 2000);
            }
          })
          .catch(function(){ setTimeout(check, 3000); });
      }
      setTimeout(check, 2000);
    })();
    </script>

<?php else: ?>

    <div class="icon">🎉</div>
    <h2>¡Gracias por tu compra!</h2>
    <p class="subtitle">El pago fue procesado vía <strong><?= $gatewayLabel ?></strong>.<br>
    Recibirás el link de descarga en tu email en breve.</p>

    <div class="info-box">
      📧 Si no recibís el email en 5 minutos, revisá la carpeta de spam.<br>
      💬 Soporte: <a href="mailto:exeandino@gmail.com" style="color:#c9a84c">exeandino@gmail.com</a>
    </div>

<?php endif; ?>

  </div>
  <div class="footer">
    TasadorIA · <a href="<?= $siteUrl ?>">anperprimo.com</a> · soporte: <a href="mailto:exeandino@gmail.com">exeandino@gmail.com</a>
  </div>
</div>
</body>
</html>
