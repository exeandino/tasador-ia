<?php
/**
 * TasadorIA — send_email.php
 * Envía email via SMTP Brevo con sockets PHP nativos (sin PHPMailer).
 * Fallback a mail() si SMTP no está configurado.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ?test=1 hace una conexión SMTP real y devuelve el diagnóstico
    if (isset($_GET['test'])) {
        $cfg = require __DIR__ . '/../config/settings.php';
        $s   = $cfg['smtp'] ?? [];
        $log = [];
        if (empty($s['host'])) { ob_end_clean(); echo json_encode(['ok'=>false,'err'=>'SMTP no configurado']); exit; }
        $sock = @stream_socket_client("tcp://{$s['host']}:{$s['port']}", $errno, $errstr, 10);
        if (!$sock) { ob_end_clean(); echo json_encode(['ok'=>false,'step'=>'connect','err'=>"$errstr ($errno)"]); exit; }
        stream_set_timeout($sock, 15);
        $rd = function() use ($sock): string {
            $o=''; while(!feof($sock)){$l=fgets($sock,512);if($l===false)break;$o.=$l;if(strlen($l)>=4&&$l[3]===' ')break;} return $o;
        };
        $log[] = 'greeting: ' . trim($rd());
        fwrite($sock, "EHLO test.local\r\n"); $log[] = 'ehlo: ' . trim($rd());
        fwrite($sock, "STARTTLS\r\n"); $r=$rd(); $log[] = 'starttls: ' . trim($r);
        if (strpos($r,'220')!==false) {
            $ok = stream_socket_enable_crypto($sock,true,STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)
               || stream_socket_enable_crypto($sock,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $log[] = 'tls_upgrade: ' . ($ok?'OK':'FAIL');
            if ($ok) {
                fwrite($sock,"EHLO test.local\r\n"); $log[]='ehlo2: '.trim($rd());
                fwrite($sock,"AUTH LOGIN\r\n"); $log[]='auth_login: '.trim($rd());
                fwrite($sock,base64_encode($s['user'])."\r\n"); $log[]='user: '.trim($rd());
                fwrite($sock,base64_encode($s['pass'])."\r\n"); $ar=$rd(); $log[]='pass: '.trim($ar);
                $log[] = 'auth_ok: ' . (strpos($ar,'235')!==false?'YES':'NO');
            }
        }
        fwrite($sock,"QUIT\r\n"); fclose($sock);
        ob_end_clean();
        echo json_encode(['ok'=>true,'php'=>PHP_VERSION,'log'=>$log], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ob_end_clean();
    echo json_encode(['ok' => true, 'smtp' => 'brevo', 'php' => PHP_VERSION, 'test_url' => '?test=1']);
    exit;
}

function jsonOut(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

// ── SMTP nativo via sockets ───────────────────────────────────────────────────
function smtpSend(string $to, string $toName, string $subject, string $html, string $text, array $s, string $bcc = ''): array {
    $host   = $s['host'];
    $port   = (int)($s['port'] ?? 587);
    $log    = [];

    // Intentar TCP plano (587 STARTTLS) o SSL directo (465)
    $sock = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 20);
    if (!$sock && $port === 587) {
        // fallback a SSL directo port 465
        $sock = @stream_socket_client("ssl://$host:465", $errno, $errstr, 20);
        if ($sock) $port = 465;
    }
    if (!$sock) return ['ok' => false, 'err' => "Conexión fallida $host:$port — $errstr ($errno)"];
    stream_set_timeout($sock, 30);

    $read = function() use ($sock, &$log): string {
        $out = '';
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            if ($line === false) break;
            $out .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $log[] = 'S: ' . trim($out);
        return $out;
    };
    $cmd = function(string $c) use ($sock, $read, &$log): string {
        $log[] = 'C: ' . (strlen($c) > 40 ? substr($c,0,40).'…' : $c);
        fwrite($sock, $c . "\r\n");
        return $read();
    };

    $ehlo = gethostname() ?: 'tasador.local';
    $read(); // 220 greeting

    if ($port !== 465) {
        // STARTTLS flow
        $cmd("EHLO $ehlo");
        $r = $cmd('STARTTLS');
        if (strpos($r, '220') === false) {
            fclose($sock);
            return ['ok' => false, 'err' => 'STARTTLS rechazado: ' . trim($r), 'log' => $log];
        }
        $ok = stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)
           || stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$ok) { fclose($sock); return ['ok' => false, 'err' => 'TLS upgrade falló', 'log' => $log]; }
        $log[] = 'TLS: OK';
    }

    $cmd("EHLO $ehlo");

    // AUTH LOGIN
    $cmd('AUTH LOGIN');
    $cmd(base64_encode($s['user']));
    $r = $cmd(base64_encode($s['pass']));
    if (strpos($r, '235') === false) {
        fclose($sock);
        return ['ok' => false, 'err' => 'Auth SMTP fallida (verificá usuario/clave Brevo): ' . trim($r), 'log' => $log];
    }

    // Envelope
    $rMail = $cmd("MAIL FROM:<{$s['from']}>");
    if (strpos($rMail, '250') === false) {
        fclose($sock);
        return ['ok' => false, 'err' => 'MAIL FROM rechazado (verificá sender en Brevo): ' . trim($rMail), 'log' => $log];
    }
    $cmd("RCPT TO:<$to>");
    // BCC: agregar segundo destinatario en el envelope (invisible para el usuario)
    if ($bcc && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
        $cmd("RCPT TO:<$bcc>");
    }

    // Cuerpo MIME
    $boundary = 'b' . bin2hex(random_bytes(8));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFrom    = '=?UTF-8?B?' . base64_encode($s['from_name']) . '?= <' . $s['from'] . '>';
    $encodedTo      = $toName ? ('=?UTF-8?B?' . base64_encode($toName) . '?= <' . $to . '>') : $to;
    // Nota: BCC NO va en los headers del mensaje (es solo envelope), así queda oculto

    $body = "From: $encodedFrom\r\n"
          . "To: $encodedTo\r\n"
          . "Subject: $encodedSubject\r\n"
          . "MIME-Version: 1.0\r\n"
          . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n"
          . "Date: " . date('r') . "\r\n"
          . "X-Mailer: TasadorIA/5.0\r\n"
          . "\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text)) . "\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . "--$boundary--";

    $cmd('DATA');
    // Dot-stuffing RFC 5321: duplicar CUALQUIER línea que empiece con punto
    $escaped = preg_replace('/^\./m', '..', $body);
    fwrite($sock, $escaped . "\r\n");
    $r = $cmd('.');   // línea final de terminación DATA

    $cmd('QUIT');
    fclose($sock);

    return ['ok' => strpos($r, '250') !== false, 'resp' => trim($r), 'log' => $log];
}

// ── Fallback: mail() nativo ───────────────────────────────────────────────────
function nativeMail(string $to, string $subject, string $html, string $text, string $from, string $fromName): bool {
    $b = 'b' . md5(uniqid());
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$b\"",
        "From: $fromName <$from>",
        "Reply-To: $from",
    ]);
    $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text)) . "\r\n"
          . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n--$b--";
    return @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B'), $body, $headers);
}

// ── HTML del email (tema oscuro, diseño igual a la app) ───────────────────────
function buildHtml(array $p): string {
    // Paleta
    $gold    = '#c9a84c';
    $gold2   = '#e8c86a';
    $dark    = '#0d0f14';
    $card    = '#141720';
    $card2   = '#1a1d28';
    $border  = '#2a2d3a';
    $text    = '#e5e7eb';
    $muted   = '#9ca3af';
    $green   = '#00e676';
    $red     = '#ff5252';

    // Mapeo value → label legible para datos avanzados
    $valLabels = [
        // AC
        'split_1'=>'1 split','split_2'=>'2 splits','split_3'=>'3+ splits','central'=>'Central',
        // Calef
        'gas_natural'=>'Gas natural','losa'=>'Losa radiante','electrica'=>'Eléctrica',
        'garrafa'=>'Garrafa','split'=>'Split/Inverter',
        // Cocina (mismos que calef para gas/electrica ya definidos arriba)
        'induccion'=>'Inducción',
        // Agua caliente
        'gas'=>'Gas (termotanque/calefón)','electrico'=>'Eléctrico',
        'solar'=>'Panel solar','combinado'=>'Combinado',
        // Solar
        'parcial'=>'Sí · parcial (agua caliente)','total'=>'Sí · total (red eléctrica)',
        // Agua corriente / internet
        'red'=>'Red municipal','pozo'=>'Pozo / Perforación',
        'fibra'=>'Fibra óptica','satelital'=>'Satélite','cable'=>'Cable',
    ];
    $fmtVal = fn(string $v): string => $valLabels[$v] ?? ucwords(str_replace('_',' ',$v));

    // Estilo base para celdas de cards
    $cardStyle = "background:$card;border-radius:10px;border:1px solid $border;";

    // Saludo / lead info
    $greet = $p['forUser']
        ? "<tr><td style='padding:20px 30px 4px;font-size:14px;color:$text;line-height:1.6'>
            Hola <strong style='color:$gold2'>{$p['fullName']}</strong>, tu tasación está lista.<br>
            <span style='font-size:12px;color:$muted'>Cualquier consulta respondemos sin cargo. Código: <strong>{$p['code']}</strong></span>
           </td></tr>"
        : "<tr><td style='padding:12px 24px;margin:16px 0'>
            <div style='background:#1e1a0a;border:1px solid rgba(201,168,76,.35);border-radius:8px;padding:12px 16px;font-size:13px;color:$text'>
              <strong style='color:$gold'>Nuevo lead:</strong> {$p['fullName']}<br>
              <span style='color:$muted'>📧 {$p['email']} · 📞 {$p['phone']}</span>
            </div>
           </td></tr>";

    // ── Factores ──
    $factRows = '';
    foreach ((array)($p['multipliers'] ?? []) as $k => $v) {
        $pct   = round(((float)($v['factor'] ?? 1) - 1) * 100, 1);
        $label = htmlspecialchars((string)($v['label'] ?? ''));
        $s     = $pct > 0 ? '+' : '';
        if ($pct > 0) {
            $badge = "<span style='display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;color:$green;background:rgba(0,230,118,.12)'>$s$pct%</span>";
        } elseif ($pct < 0) {
            $badge = "<span style='display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;color:$red;background:rgba(255,82,82,.12)'>$pct%</span>";
        } else {
            $badge = "<span style='display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;color:$muted;background:rgba(255,255,255,.06)'>base</span>";
        }
        $factRows .= "<tr style='border-bottom:1px solid $border'>
          <td style='padding:7px 14px;font-size:12px;color:$text'>"
              . htmlspecialchars($k) . " <span style='color:$muted;font-size:11px'>$label</span></td>
          <td style='padding:7px 14px;text-align:right'>$badge</td>
        </tr>";
    }

    // ── Comparables ──
    $compHtml = '';
    $comps = array_slice((array)($p['comparables'] ?? []), 0, 5);
    if (count($comps) > 0) {
        $has_cross = array_reduce($comps, fn($carry, $c) => $carry || ($c['same_zone'] === false), false);
        $crossNote = $has_cross
            ? "<div style='font-size:11px;color:#b59c50;margin-bottom:10px;padding:7px 12px;background:rgba(201,168,76,.08);border-left:3px solid $gold;border-radius:0 6px 6px 0'>
                ⚠ Sin comparables en la misma zona — precio similar de la ciudad
               </div>"
            : '';
        $compRows = '';
        foreach ($comps as $c) {
            $lbl   = htmlspecialchars((string)($c['label'] ?? $c['address'] ?? 'Propiedad'));
            $area  = (float)($c['area'] ?? $c['surface'] ?? 0);
            $sup   = $area > 0 ? number_format($area, 0, ',', '.') . ' m²' : '';
            $ppm2c = number_format((float)($c['ppm2'] ?? $c['price_per_m2'] ?? 0), 0, ',', '.');
            $zona  = htmlspecialchars((string)($c['zone'] ?? ''));
            $sameZ = ($c['same_zone'] ?? true) !== false;
            $zonaTxt = $zona ? ($sameZ ? $zona : "$zona ↗") : '';
            $priceC  = 'USD ' . number_format((float)($c['price'] ?? 0), 0, ',', '.');
            $url     = !empty($c['url']) ? " <a href='" . htmlspecialchars($c['url']) . "' style='color:$gold;font-size:10px;text-decoration:none'>ver →</a>" : '';
            $meta    = array_filter([$sup ? "USD $ppm2c/m²" : '', $sup, $zonaTxt]);
            $compRows .= "<tr style='border-bottom:1px solid $border'>
              <td style='padding:8px 14px;font-size:12px'>
                <div style='font-weight:600;color:$text'>$lbl$url</div>
                <div style='color:$muted;font-size:11px;margin-top:2px'>" . implode(' · ', $meta) . "</div>
              </td>
              <td style='padding:8px 14px;font-size:13px;font-weight:700;color:$gold;text-align:right;white-space:nowrap'>$priceC</td>
            </tr>";
        }
        $cnt = (int)($p['market_count'] ?? count($comps));
        $compHtml = "<tr><td style='padding:0 24px 16px'>
          <div style='font-size:10px;color:$muted;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>
            📊 Comparables en base de datos <span style='color:rgba(156,163,175,.6)'>($cnt reales)</span>
          </div>
          $crossNote
          <table width='100%' cellpadding='0' cellspacing='0' style='$cardStyle'>$compRows</table>
        </td></tr>";
    }

    // ── POI cercanos ──
    $poiHtml = '';
    $poiData = $p['poi'] ?? [];
    $poiCats = [
        'escuelas'  => ['🎓', 'Escuelas'],
        'parques'   => ['🌳', 'Parques'],
        'hospitales'=> ['🏥', 'Salud'],
        'shoppings' => ['🛍', 'Comercial'],
        'transporte'=> ['🚌', 'Transporte'],
    ];
    // Construir tabla de 2 columnas de POI
    $poiCells = [];
    foreach ($poiCats as $key => [$icon, $label]) {
        $items = $poiData[$key] ?? [];
        if (empty($items)) continue;
        $list = implode('', array_map(function($item) use ($muted) {
            $dist = isset($item['dist']) ? " <span style='color:$muted;font-size:10px'>{$item['dist']}m</span>" : '';
            return "<div style='padding:3px 0;font-size:11px;color:#d1d5db'>" . htmlspecialchars($item['name']) . $dist . "</div>";
        }, $items));
        $poiCells[] = "<td style='padding:10px 12px;vertical-align:top;width:50%'>
            <div style='font-size:10px;font-weight:700;text-transform:uppercase;color:$gold;margin-bottom:5px;letter-spacing:.5px'>$icon $label</div>
            $list
          </td>";
    }
    if ($poiCells) {
        // Agrupar de a 2 por fila
        $poiTableRows = '';
        for ($i = 0; $i < count($poiCells); $i += 2) {
            $c1 = $poiCells[$i];
            $c2 = isset($poiCells[$i+1]) ? $poiCells[$i+1] : "<td></td>";
            $poiTableRows .= "<tr>$c1$c2</tr>";
        }
        $poiHtml = "<tr><td style='padding:0 24px 16px'>
          <div style='font-size:10px;color:$muted;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>📍 Cercanías del inmueble</div>
          <table width='100%' cellpadding='0' cellspacing='0' style='$cardStyle'>$poiTableRows</table>
        </td></tr>";
    }

    // ── Datos avanzados (solo campos explícitamente elegidos) ──
    $advRows = '';
    if (!empty($p['advanced'])) {
        $adv = $p['advanced'];
        $advMap = [
            'ac'           => ['❄️', 'Aire acondicionado'],
            'calef'        => ['🔥', 'Calefacción'],
            'cocina'       => ['🍳', 'Cocina'],
            'agua_caliente'=> ['🚿', 'Agua caliente'],
            'solar'        => ['☀️', 'Paneles solares'],
            'eficiencia'   => ['📊', 'Eficiencia energética'],
        ];
        foreach ($advMap as $k => [$icon, $label]) {
            $val = trim((string)($adv[$k] ?? ''));
            if ($val === '' || $val === 'no') continue;
            $valFmt = htmlspecialchars($fmtVal($val));
            $advRows .= "<tr style='border-bottom:1px solid $border'>
              <td style='padding:7px 14px;font-size:12px;color:$muted'>$icon $label</td>
              <td style='padding:7px 14px;font-size:12px;font-weight:600;color:$text;text-align:right'>$valFmt</td>
            </tr>";
        }
    }
    $advSection = $advRows
        ? "<tr><td style='padding:0 24px 16px'>
            <div style='font-size:10px;color:$muted;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px'>🔧 Datos adicionales</div>
            <table width='100%' cellpadding='0' cellspacing='0' style='$cardStyle'>$advRows</table>
           </td></tr>"
        : '';

    // ARS
    $arsMin = '$ ' . number_format((float)($p['price_ars_min'] ?? 0), 0, ',', '.');
    $arsMax = '$ ' . number_format((float)($p['price_ars_max'] ?? 0), 0, ',', '.');

    return "<!DOCTYPE html>
<html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Tasación {$p['code']}</title></head>
<body style='margin:0;padding:0;background:#060810;font-family:-apple-system,BlinkMacSystemFont,Arial,sans-serif'>
<table width='100%' cellpadding='0' cellspacing='0' style='padding:28px 0'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='max-width:600px;width:100%;background:$dark;border-radius:16px;overflow:hidden;border:1px solid $border'>

  <!-- HEADER -->
  <tr><td style='background:linear-gradient(135deg,#0a0c14 0%,#111422 100%);padding:28px 32px 24px;text-align:center;border-bottom:1px solid $border'>
    <img src='https://anperprimo.com/wp-content/uploads/2025/07/WHITE111.png' alt='ANPR Primo'
         style='height:38px;display:block;margin:0 auto 12px'>
    <div style='font-size:20px;color:$gold;font-family:Georgia,serif;font-weight:bold;letter-spacing:.5px'>TasadorIA</div>
    <div style='font-size:10px;color:rgba(255,255,255,.3);letter-spacing:2.5px;margin-top:5px;text-transform:uppercase'>
      Valuación inteligente · Argentina
    </div>
  </td></tr>

  <!-- SALUDO -->
  {$greet}

  <!-- PRECIO PRINCIPAL -->
  <tr><td style='padding:24px 24px 20px;text-align:center;border-bottom:1px solid $border'>
    <div style='font-size:10px;color:$muted;text-transform:uppercase;letter-spacing:2px;margin-bottom:5px'>
      Tasación · {$p['code']} · {$p['date']}
    </div>
    <div style='font-size:12px;color:$gold;font-weight:600;margin-bottom:18px'>
      📍 {$p['zone']} · {$p['city']}
    </div>
    <div style='font-size:9px;color:$muted;letter-spacing:2.5px;text-transform:uppercase;margin-bottom:6px'>VALOR SUGERIDO</div>
    <div style='font-size:54px;font-weight:900;color:$gold2;font-family:Georgia,serif;line-height:1;letter-spacing:-2px'>
      {$p['priceUSD']}
    </div>
    <div style='font-size:13px;color:$muted;margin-top:14px'>
      Rango: <strong style='color:$text'>{$p['priceMin']}</strong> — <strong style='color:$text'>{$p['priceMax']}</strong>
    </div>
    <div style='font-size:11px;color:rgba(156,163,175,.6);margin-top:5px'>{$p['ppm2']}</div>
  </td></tr>

  <!-- BLOQUE MERCADO: USD + ARS -->
  <tr><td style='padding:0;border-bottom:1px solid $border'>
    <table width='100%' cellpadding='0' cellspacing='0'>
      <tr>
        <td width='50%' style='padding:16px 14px 16px 24px;border-right:1px solid $border;vertical-align:top'>
          <div style='font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:$muted;margin-bottom:8px'>Rango de mercado</div>
          " . (!empty($p['market_count']) ? "<div style='font-size:10px;color:#4a9eff;margin-bottom:8px'>✓ {$p['market_count']} comparables reales</div>" : '') . "
          <div style='font-size:11px;color:$muted;margin-bottom:3px'>Mín: <strong style='color:$text'>{$p['priceMin']}</strong></div>
          <div style='font-size:15px;color:$gold;font-weight:800;margin-bottom:3px'>{$p['priceUSD']}</div>
          <div style='font-size:11px;color:$muted'>Máx: <strong style='color:$text'>{$p['priceMax']}</strong></div>
        </td>
        <td width='50%' style='padding:16px 24px 16px 14px;vertical-align:top'>
          <div style='font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:$muted;margin-bottom:8px'>En pesos argentinos</div>
          <div style='font-size:20px;font-weight:800;color:$text;margin-bottom:3px'>{$p['priceARS']}</div>
          <div style='font-size:10px;color:$muted;margin-bottom:3px'>Mín: $arsMin</div>
          <div style='font-size:10px;color:$muted'>Máx: $arsMax</div>
          <div style='font-size:10px;color:rgba(156,163,175,.4);margin-top:6px'>\$1.400/USD</div>
        </td>
      </tr>
    </table>
  </td></tr>

  <!-- FACTORES -->
  <tr><td style='padding:16px 24px'>
    <div style='font-size:10px;color:$muted;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px'>⚡ Factores de ajuste</div>
    <table width='100%' cellpadding='0' cellspacing='0' style='$cardStyle'>
      {$factRows}
    </table>
  </td></tr>

  <!-- COMPARABLES -->
  {$compHtml}

  <!-- CERCANÍAS POI -->
  {$poiHtml}

  <!-- DATOS AVANZADOS -->
  {$advSection}

  <!-- AVISO LEGAL -->
  <tr><td style='padding:16px 24px;border-top:1px solid $border'>
    <p style='margin:0;font-size:10px;color:rgba(156,163,175,.5);line-height:1.7;text-align:center'>
      ⚠ Esta tasación es orientativa y no constituye oferta ni documento legal.<br>
      Para una valuación oficial contactar a un martillero matriculado.
    </p>
  </td></tr>

  <!-- FOOTER -->
  <tr><td style='background:#0a0c14;padding:16px 32px;text-align:center;border-top:1px solid $border'>
    <div style='color:$gold;font-size:14px;font-weight:700;letter-spacing:.5px'>{$p['agencyName']}</div>
    <div style='color:rgba(255,255,255,.25);font-size:11px;margin-top:4px'>{$p['agencyWeb']}</div>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";
}

// ── Main ──────────────────────────────────────────────────────────────────────
try {
    $cfg  = require_once __DIR__ . '/../config/settings.php';
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $name    = trim((string)($data['name']    ?? ''));
    $surname = trim((string)($data['surname'] ?? ''));
    $email   = trim((string)($data['email']   ?? ''));
    $phone   = trim((string)($data['phone']   ?? ''));
    $result  = $data['result']   ?? [];
    $prop    = $data['property'] ?? [];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['success' => false, 'error' => 'Email inválido']);
    }

    $fullName   = trim("$name $surname") ?: 'Consulta';
    $toAdmin    = $cfg['agency_email'] ?? 'info@anperprimo.com';
    $agencyName = $cfg['agency_name']  ?? 'AnperPrimo';
    $agencyWeb  = $cfg['agency_web']   ?? 'anperprimo.com';
    $smtpCfg    = $cfg['smtp'] ?? [];
    $useSmtp    = !empty($smtpCfg['host']) && !empty($smtpCfg['user']);

    // Formatear precios
    $fmt = fn($n) => 'USD ' . number_format((float)$n, 0, ',', '.');
    $priceUSD = $fmt($result['price']['suggested'] ?? 0);
    $priceMin = $fmt($result['price']['min'] ?? 0);
    $priceMax = $fmt($result['price']['max'] ?? 0);
    $priceARS = '$ ' . number_format((float)($result['price_ars']['suggested'] ?? 0), 0, ',', '.') . ' ARS';
    $ppm2     = 'USD ' . number_format((float)($result['price']['ppm2'] ?? 0), 0, ',', '.') . '/m²';
    $zone     = (string)($result['zone']['zone'] ?? '');
    $city     = (string)($result['zone']['city'] ?? '');
    $code     = (string)($result['code'] ?? '');
    $date     = date('d/m/Y H:i');

    // Filas de propiedad
    $propRows = '';
    $propMap = ['property_type'=>'Tipo','covered_area'=>'Superficie (m²)','bedrooms'=>'Dormitorios',
                'bathrooms'=>'Baños','garages'=>'Cocheras','age_years'=>'Antigüedad',
                'condition'=>'Estado','address'=>'Dirección','escritura'=>'Escritura'];
    foreach ($propMap as $k => $label) {
        $v = (string)($prop[$k] ?? '');
        if ($v === '' || $v === '0' && $k !== 'age_years') continue;
        if ($k === 'age_years' && $v === '0') $v = 'A estrenar';
        $propRows .= "<tr><td style='padding:5px 12px;color:#888;font-size:12px;border-bottom:1px solid #f5f5f5'>$label</td>"
                   . "<td style='padding:5px 12px;font-size:12px;font-weight:600;border-bottom:1px solid #f5f5f5'>" . htmlspecialchars($v) . "</td></tr>";
    }

    // Filas de factores
    $factRows = '';
    foreach ((array)($result['multipliers'] ?? []) as $k => $v) {
        $pct   = round(((float)($v['factor'] ?? 1) - 1) * 100, 1);
        $s     = $pct > 0 ? '+' : '';
        $color = $pct > 0 ? '#007a40' : ($pct < 0 ? '#c00' : '#888');
        $factRows .= "<tr><td style='padding:5px 12px;font-size:12px;border-bottom:1px solid #f5f5f5'>"
                   . htmlspecialchars($k) . " <span style='color:#aaa;font-size:10px'>" . htmlspecialchars((string)($v['label'] ?? '')) . "</span></td>"
                   . "<td style='padding:5px 12px;font-size:12px;font-weight:700;text-align:right;color:$color;border-bottom:1px solid #f5f5f5'>$s$pct%</td></tr>";
    }

    $params = compact('priceUSD','priceMin','priceMax','priceARS','ppm2','zone','city','code','date',
                      'fullName','email','phone','propRows','agencyName','agencyWeb');
    $params['multipliers']   = $result['multipliers'] ?? [];
    $params['comparables']   = $result['comparables'] ?? [];
    $params['market_count']  = (int)($result['market_data']['count'] ?? 0);
    $params['poi']           = $result['poi'] ?? [];
    $params['price_ars_min'] = $result['price_ars']['min'] ?? 0;
    $params['price_ars_max'] = $result['price_ars']['max'] ?? 0;
    $params['advanced']      = $prop['advanced'] ?? [];

    $htmlAdmin = buildHtml(array_merge($params, ['forUser' => false]));
    $htmlUser  = buildHtml(array_merge($params, ['forUser' => true]));
    $textPlain = "TASACIÓN $code — $date\n$agencyName\n\nZona: $zone, $city\nValor: $priceUSD\nRango: $priceMin — $priceMax\n$priceARS\n\nNombre: $fullName\nEmail: $email\nTel: $phone\n\nEsta tasación es orientativa.";

    $results = ['admin' => null, 'user' => null];

    // Asunto del email al usuario
    $subjectUser  = "Tu tasación: $priceUSD — $zone, $city";
    $subjectAdmin = "[TasadorIA] Nueva tasación: $fullName — $priceUSD";

    if ($useSmtp) {
        // ── Usuario: email principal + BCC oculto a info@ ───────────────────
        $results['user'] = smtpSend($email, $fullName,
            $subjectUser, $htmlUser, $textPlain, $smtpCfg, $toAdmin);

        // ── Admin: email separado con datos del lead (nombre, tel, email) ───
        $results['admin'] = smtpSend($toAdmin, $agencyName,
            $subjectAdmin, $htmlAdmin, $textPlain, $smtpCfg);
    } else {
        // ── Fallback PHP mail() ───────────────────────────────────────────────
        $from     = $smtpCfg['from']      ?? ('noreply@' . ($_SERVER['SERVER_NAME'] ?? 'anperprimo.com'));
        $fromName = $smtpCfg['from_name'] ?? $agencyName;
        $results['user']  = ['ok' => nativeMail($email,    $subjectUser,  $htmlUser,  $textPlain, $from, $fromName)];
        $results['admin'] = ['ok' => nativeMail($toAdmin,  $subjectAdmin, $htmlAdmin, $textPlain, $from, $fromName)];
    }

    $okAdmin = (bool)($results['admin']['ok'] ?? false);
    $okUser  = (bool)($results['user']['ok']  ?? false);

    // Guardar en BD
    try {
        $pdo = new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($code) {
            $pdo->prepare("UPDATE tasaciones SET name=?,email=?,phone=? WHERE code=? LIMIT 1")
                ->execute([$fullName, $email, $phone, preg_replace('/[^A-Z0-9\-]/', '', strtoupper($code))]);
        }
        try {
            $pdo->prepare("INSERT INTO tasacion_leads (name,email,phone,result_code,email_sent,created_at) VALUES(?,?,?,?,?,NOW())")
                ->execute([$fullName, $email, $phone, $code, ($okAdmin || $okUser) ? 1 : 0]);
        } catch (\Throwable $ignored) {}
    } catch (\Throwable $ignored) {}

    jsonOut([
        'success'    => $okAdmin || $okUser,
        'sent_admin' => $okAdmin,
        'sent_user'  => $okUser,
        'method'     => $useSmtp ? 'brevo_smtp' : 'mail()',
        'debug'      => $results,
        'message'    => ($okAdmin || $okUser) ? "✓ Emails enviados a $email y $toAdmin" : "Error al enviar",
    ]);

} catch (\Throwable $e) {
    jsonOut(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
}
