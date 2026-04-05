<?php
/**
 * TasadorIA — send_email.php
 * Envía email via SMTP Brevo con sockets PHP nativos (sin PHPMailer).
 * Fallback a mail() si SMTP no está configurado.
 *
 * Seguridad / UX: CORS desde config, rate limit por IP, test SMTP con token,
 * Bearer opcional (api.send_email.bearer_token), HTML escapado, sin debug en JSON por defecto.
 */
function sendEmailApiDefaults(): array {
    return [
        'bearer_token'            => '',
        'smtp_test_token'         => '',
        'cors_origins'            => null,
        'rate_limit_per_hour'     => 25,
        'debug_response'          => false,
        'expose_exception_detail' => false,
    ];
}

ob_start();
$settingsPath = __DIR__ . '/../config/settings.php';
$bootCfg      = is_file($settingsPath) ? require $settingsPath : [];
$sendApiCfg   = array_replace(sendEmailApiDefaults(), $bootCfg['api']['send_email'] ?? []);

$corsList = $sendApiCfg['cors_origins'];
if ($corsList === null) {
    $corsList = $bootCfg['embed']['allowed_origins'] ?? [];
}
$wildcardCors = $corsList === [] || $corsList === ['*']
    || (count($corsList) === 1 && ($corsList[0] ?? '') === '*');
if ($wildcardCors) {
    header('Access-Control-Allow-Origin: *');
} else {
    $reqOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($reqOrigin !== '' && in_array($reqOrigin, $corsList, true)) {
        header('Access-Control-Allow-Origin: ' . $reqOrigin);
        header('Vary: Origin');
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['test'])) {
        $tok = $sendApiCfg['smtp_test_token'] ?? '';
        if ($tok === '' || !hash_equals($tok, trim((string)($_GET['token'] ?? '')))) {
            ob_end_clean();
            http_response_code(403);
            echo json_encode(['ok' => false, 'err' => 'Diagnóstico SMTP deshabilitado o token inválido. Definí api.send_email.smtp_test_token y usá ?test=1&token=…'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!is_file($settingsPath)) {
            ob_end_clean();
            echo json_encode(['ok' => false, 'err' => 'settings.php no encontrado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $cfg = require $settingsPath;
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
    $health = ['ok' => true, 'smtp' => 'brevo', 'php' => PHP_VERSION];
    if (($sendApiCfg['smtp_test_token'] ?? '') !== '') {
        $health['smtp_test'] = 'GET ?test=1&token=(api.send_email.smtp_test_token)';
    }
    echo json_encode($health, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonOut(array $d, int $http = 200): void {
    if ($http !== 200) {
        http_response_code($http);
    }
    ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clientIp(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $xff = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($xff, FILTER_VALIDATE_IP)) {
            return $xff;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0';
}

/** @return bool true si se permite el envío */
function rateLimitAllow(string $ip, int $maxPerHour): bool {
    if ($maxPerHour <= 0) {
        return true;
    }
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tasador_se_' . hash('sha256', $ip) . '.rl';
    $now  = time();
    $fp   = @fopen($path, 'c+');
    if (!$fp) {
        return true;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return true;
    }
    $raw = stream_get_contents($fp);
    $t0  = $now;
    $n   = 0;
    if ($raw !== '' && $raw !== false) {
        $parts = explode('|', trim($raw), 2);
        $t0 = (int)($parts[0] ?? $now);
        $n  = (int)($parts[1] ?? 0);
    }
    if ($now - $t0 > 3600) {
        $t0 = $now;
        $n  = 0;
    }
    if ($n >= $maxPerHour) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    $n++;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $t0 . '|' . $n);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

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

    $replyHdr = '';
    if (!empty($s['reply_to']) && filter_var($s['reply_to'], FILTER_VALIDATE_EMAIL)) {
        $replyHdr = 'Reply-To: <' . $s['reply_to'] . ">\r\n";
    }

    $body = "From: $encodedFrom\r\n"
          . $replyHdr
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

// ── HTML del email ────────────────────────────────────────────────────────────
function buildHtml(array $p): string {
    $gold = '#c9a84c';
    $dark = '#1a1a2e';
    $bg   = '#f8f8fb';

    $fn     = h((string)($p['fullName'] ?? ''));
    $code   = h((string)($p['code'] ?? ''));
    $em     = h((string)($p['email'] ?? ''));
    $ph     = h((string)($p['phone'] ?? ''));
    $zone   = h((string)($p['zone'] ?? ''));
    $city   = h((string)($p['city'] ?? ''));
    $dt     = h((string)($p['date'] ?? ''));
    $agency = h((string)($p['agencyName'] ?? ''));
    $web    = h((string)($p['agencyWeb'] ?? ''));
    $pUSD   = h((string)($p['priceUSD'] ?? ''));
    $pMin   = h((string)($p['priceMin'] ?? ''));
    $pMax   = h((string)($p['priceMax'] ?? ''));
    $pARS   = h((string)($p['priceARS'] ?? ''));
    $ppm2e  = h((string)($p['ppm2'] ?? ''));
    $arsRt  = (int)($p['ars_usd_rate'] ?? 1400);

    // Saludo / lead info
    $greet = $p['forUser']
        ? "<tr><td style='padding:18px 30px 0;font-size:14px;color:#444;line-height:1.5'>
            Hola <strong>$fn</strong>, tu tasación está lista.<br>
            <span style='font-size:12px;color:#888'>Cualquier consulta respondemos sin cargo. Código: <strong>$code</strong></span>
           </td></tr>"
        : "<tr><td style='padding:14px 30px 0;background:#fff8e6;border-left:3px solid $gold;font-size:13px;color:#555;margin:0 30px'>
            <strong>Nuevo lead:</strong> $fn<br>
            📧 $em · 📞 $ph
           </td></tr>";

    // ── Factores ──
    $factRows = '';
    foreach ((array)($p['multipliers'] ?? []) as $k => $v) {
        $pct   = round(((float)($v['factor'] ?? 1) - 1) * 100, 1);
        $label = htmlspecialchars((string)($v['label'] ?? ''));
        $s     = $pct > 0 ? '+' : '';
        $color = $pct > 0 ? '#007a40' : ($pct < 0 ? '#c00' : '#888');
        $badge = $pct == 0 ? "<span style='font-size:10px;color:#aaa'>base</span>"
               : "<span style='font-weight:700;color:$color'>$s$pct%</span>";
        $factRows .= "<tr>
          <td style='padding:6px 12px;font-size:12px;color:#333;border-bottom:1px solid #eee'>"
              . htmlspecialchars($k) . " <span style='color:#aaa;font-size:11px'>$label</span></td>
          <td style='padding:6px 12px;font-size:12px;text-align:right;border-bottom:1px solid #eee'>$badge</td>
        </tr>";
    }

    // ── Comparables ──
    $compHtml = '';
    $comps = array_slice((array)($p['comparables'] ?? []), 0, 5);
    if (count($comps) > 0) {
        $compRows = '';
        foreach ($comps as $c) {
            $lbl  = htmlspecialchars((string)($c['label'] ?? $c['address'] ?? 'Propiedad'));
            $sup  = number_format((float)($c['surface'] ?? 0), 0, ',', '.') . ' m²';
            $ppm2c = number_format((float)($c['price_per_m2'] ?? 0), 0, ',', '.');
            $zona = htmlspecialchars((string)($c['zone'] ?? ''));
            $priceC = 'USD ' . number_format((float)($c['price'] ?? 0), 0, ',', '.');
            $url  = !empty($c['url']) ? "<a href='" . htmlspecialchars($c['url']) . "' style='color:$gold;font-size:10px;text-decoration:none'>ver →</a>" : '';
            $compRows .= "<tr style='border-bottom:1px solid #eee'>
              <td style='padding:8px 12px;font-size:12px'>
                <div style='font-weight:600;color:#222'>$lbl $url</div>
                <div style='color:#aaa;font-size:11px;margin-top:2px'>$sup · USD $ppm2c/m² · $zona</div>
              </td>
              <td style='padding:8px 12px;font-size:13px;font-weight:700;color:$dark;text-align:right;white-space:nowrap'>$priceC</td>
            </tr>";
        }
        $cnt = (int)($p['market_count'] ?? count($comps));
        $cntH = h((string)$cnt);
        $compHtml = "<tr><td style='padding:14px 30px 0'>
          <h3 style='margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:$dark'>
            📊 Comparables en base de datos <span style='font-weight:400;color:#aaa'>($cntH reales)</span>
          </h3>
          <table width='100%' cellpadding='0' cellspacing='0' style='background:$bg;border-radius:8px'>$compRows</table>
        </td></tr>";
    }

    // ── POI cercanos ──
    $poiHtml = '';
    $poiData = $p['poi'] ?? [];
    $poiCats = [
        'escuelas'  => ['🎓', 'Escuelas / Colegios'],
        'parques'   => ['🌳', 'Parques y plazas'],
        'hospitales'=> ['🏥', 'Salud'],
        'shoppings' => ['🛍', 'Comercial'],
        'transporte'=> ['🚌', 'Transporte'],
    ];
    $poiCells = [];
    foreach ($poiCats as $key => [$icon, $label]) {
        $items = $poiData[$key] ?? [];
        if (empty($items)) continue;
        $iconH = h($icon);
        $labelH = h($label);
        $list = implode('', array_map(function ($item) {
            $dist = isset($item['dist']) ? ' <span style="color:#bbb;font-size:10px">· ' . h((string)(int)$item['dist']) . ' m</span>' : '';
            return "<div style='padding:3px 0;font-size:12px;color:#444'>" . h((string)($item['name'] ?? '')) . $dist . '</div>';
        }, $items));
        $poiCells[] = "<td style='padding:8px 14px;vertical-align:top;width:50%'>
            <div style='font-size:10px;font-weight:700;text-transform:uppercase;color:#aaa;margin-bottom:5px'>$iconH $labelH</div>
            $list
          </td>";
    }
    if ($poiCells !== []) {
        $poiRowsBuilt = '';
        foreach (array_chunk($poiCells, 2) as $pair) {
            $poiRowsBuilt .= '<tr>' . implode('', $pair);
            if (count($pair) === 1) {
                $poiRowsBuilt .= '<td style="width:50%"></td>';
            }
            $poiRowsBuilt .= '</tr>';
        }
        $poiHtml = "<tr><td style='padding:14px 30px 0'>
          <h3 style='margin:0 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:$dark'>📍 Cercanías del inmueble</h3>
          <table width='100%' cellpadding='0' cellspacing='0' style='background:$bg;border-radius:8px;padding:8px'>
            $poiRowsBuilt
          </table>
        </td></tr>";
    }

    // ── Datos avanzados ──
    $advRows = '';
    if (!empty($p['advanced'])) {
        $adv = $p['advanced'];
        $advMap = ['ac'=>'Aire acondicionado','calef'=>'Calefacción','solar'=>'Paneles solares',
                   'eficiencia'=>'Eficiencia energética','cocina'=>'Cocina','agua_caliente'=>'Agua caliente'];
        foreach ($advMap as $k => $label) {
            if (!empty($adv[$k]) && $adv[$k] !== 'no' && $adv[$k] !== '') {
                $advRows .= "<tr><td style='padding:5px 12px;color:#888;font-size:12px;border-bottom:1px solid #eee'>$label</td>
                  <td style='padding:5px 12px;font-size:12px;font-weight:600;border-bottom:1px solid #eee'>"
                  . htmlspecialchars($adv[$k]) . "</td></tr>";
            }
        }
    }
    $advSection = $advRows
        ? "<tr><td style='padding:14px 30px 0'>
            <h3 style='margin:0 0 6px;font-size:11px;text-transform:uppercase;color:$dark'>Datos adicionales</h3>
            <table width='100%' style='background:$bg;border-radius:8px'>$advRows</table>
           </td></tr>"
        : '';

    // ── ARS block ──
    $arsMin = '$ ' . number_format((float)($p['price_ars_min'] ?? 0), 0, ',', '.');
    $arsMax = '$ ' . number_format((float)($p['price_ars_max'] ?? 0), 0, ',', '.');

    $propRowsRaw = trim((string)($p['propRows'] ?? ''));
    $propSection = $propRowsRaw !== ''
        ? "<tr><td style='padding:14px 32px 0'>
            <h3 style='margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:$dark'>Inmueble tasado</h3>
            <table width='100%' cellpadding='0' cellspacing='0' style='background:$bg;border-radius:8px'>$propRowsRaw</table>
          </td></tr>"
        : '';

    $preheader = "<div style=\"display:none;max-height:0;overflow:hidden;font-size:1px;line-height:1px;color:transparent;opacity:0\">
  $pUSD · $zone · $city · TasadorIA
</div>";

    return "<!DOCTYPE html>
<html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Tasación $code</title></head>
<body style='margin:0;padding:0;background:#e8e8f0;font-family:Arial,Helvetica,sans-serif'>
$preheader
<table width='100%' cellpadding='0' cellspacing='0' style='padding:28px 0'>
<tr><td align='center'>
<table width='580' cellpadding='0' cellspacing='0' style='max-width:580px;width:100%;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 6px 32px rgba(0,0,0,.14)'>

  <!-- HEADER -->
  <tr><td style='background:$dark;padding:28px 32px;text-align:center'>
    <img src='https://anperprimo.com/wp-content/uploads/2025/07/WHITE111.png' alt='ANPR Primo'
         style='height:40px;display:block;margin:0 auto 10px'>
    <div style='font-size:22px;color:$gold;font-family:Georgia,serif;font-weight:bold;letter-spacing:.5px'>TasadorIA</div>
    <div style='font-size:11px;color:rgba(255,255,255,.4);letter-spacing:2px;margin-top:4px;text-transform:uppercase'>
      Valuación inteligente de propiedades · Argentina
    </div>
  </td></tr>

  <!-- SALUDO -->
  {$greet}

  <!-- PRECIO PRINCIPAL -->
  <tr><td style='padding:28px 32px 24px;text-align:center;border-bottom:2px solid #f0f0f0'>
    <div style='font-size:11px;color:#bbb;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px'>
      Tasación · $code · $dt
    </div>
    <div style='font-size:13px;color:$gold;font-weight:600;margin-bottom:16px'>
      📍 $zone · $city
    </div>
    <div style='font-size:10px;color:#bbb;letter-spacing:2px;text-transform:uppercase;margin-bottom:4px'>VALOR SUGERIDO</div>
    <div style='font-size:52px;font-weight:900;color:$dark;font-family:Georgia,serif;line-height:1;letter-spacing:-1px'>
      $pUSD
    </div>
    <div style='font-size:14px;color:#666;margin-top:12px;font-weight:500'>
      Rango: <strong>$pMin</strong> — <strong>$pMax</strong>
    </div>
    <div style='font-size:12px;color:#999;margin-top:5px'>$ppm2e</div>
  </td></tr>

  <!-- BLOQUE MERCADO: USD + ARS -->
  <tr><td style='padding:0'>
    <table width='100%' cellpadding='0' cellspacing='0'>
      <tr>
        <td width='50%' style='padding:18px 16px 18px 32px;border-right:1px solid #f0f0f0;vertical-align:top'>
          <div style='font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#aaa;margin-bottom:8px'>Rango de mercado</div>
          " . (!empty($p['market_count']) ? "<div style='font-size:11px;color:#4a8ff7;margin-bottom:8px'>✓ " . h((string)(int)$p['market_count']) . " comparables reales</div>" : '') . "
          <div style='font-size:11px;color:#888;margin-bottom:4px'><span style='color:#aaa'>Mín:</span> <strong>$pMin</strong></div>
          <div style='font-size:13px;color:$dark;font-weight:700;margin-bottom:4px'>$pUSD</div>
          <div style='font-size:11px;color:#888'><span style='color:#aaa'>Máx:</span> <strong>$pMax</strong></div>
        </td>
        <td width='50%' style='padding:18px 32px 18px 16px;vertical-align:top'>
          <div style='font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#aaa;margin-bottom:8px'>En pesos argentinos</div>
          <div style='font-size:18px;font-weight:800;color:$dark;margin-bottom:4px'>$pARS</div>
          <div style='font-size:11px;color:#aaa;margin-bottom:6px'>Mín: $arsMin</div>
          <div style='font-size:11px;color:#aaa'>Máx: $arsMax</div>
          <div style='font-size:10px;color:#ccc;margin-top:6px'>Tipo de cambio referencia: 1 USD ≈ {$arsRt} ARS</div>
        </td>
      </tr>
    </table>
  </td></tr>

  <!-- FACTORES -->
  <tr><td style='padding:14px 32px 0'>
    <h3 style='margin:0 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:$dark'>Factores de ajuste</h3>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:$bg;border-radius:10px'>
      <tr style='background:#ededf5'>
        <th style='padding:6px 12px;font-size:10px;color:#999;text-align:left;font-weight:600;letter-spacing:.5px'>FACTOR</th>
        <th style='padding:6px 12px;font-size:10px;color:#999;text-align:right;font-weight:600;letter-spacing:.5px'>AJUSTE</th>
      </tr>
      {$factRows}
    </table>
  </td></tr>

  <!-- COMPARABLES -->
  {$compHtml}

  <!-- INMUEBLE -->
  {$propSection}

  <!-- CERCANÍAS POI -->
  {$poiHtml}

  <!-- DATOS AVANZADOS -->
  {$advSection}

  <!-- AVISO LEGAL -->
  <tr><td style='padding:20px 32px;border-top:1px solid #f0f0f0;margin-top:8px'>
    <p style='margin:0;font-size:11px;color:#ccc;line-height:1.7;text-align:center'>
      ⚠ Esta tasación es orientativa y no constituye oferta ni documento legal.<br>
      Para una valuación oficial contactar a un martillero matriculado.
    </p>
  </td></tr>

  <!-- FOOTER -->
  <tr><td style='background:$dark;padding:18px 32px;text-align:center'>
    <div style='color:$gold;font-size:14px;font-weight:700;letter-spacing:.5px'>$agency</div>
    <div style='color:rgba(255,255,255,.35);font-size:11px;margin-top:4px'>$web</div>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";
}

// ── Main ──────────────────────────────────────────────────────────────────────
try {
    if ($bootCfg === []) {
        jsonOut(['success' => false, 'error' => 'Configuración no encontrada', 'request_id' => bin2hex(random_bytes(8))], 500);
    }
    $cfg     = $bootCfg;
    $sendApi = array_replace(sendEmailApiDefaults(), $cfg['api']['send_email'] ?? []);

    if ($sendApi['bearer_token'] !== '') {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $ok  = preg_match('/Bearer\s+(\S+)/i', $hdr, $m) && hash_equals($sendApi['bearer_token'], $m[1]);
        if (!$ok) {
            jsonOut(['success' => false, 'error' => 'No autorizado', 'request_id' => bin2hex(random_bytes(8))], 403);
        }
    }

    if (!rateLimitAllow(clientIp(), (int)$sendApi['rate_limit_per_hour'])) {
        jsonOut(['success' => false, 'error' => 'Demasiados envíos. Intentá más tarde.', 'request_id' => bin2hex(random_bytes(8))], 429);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $name    = trim((string)($data['name']    ?? ''));
    $surname = trim((string)($data['surname'] ?? ''));
    $email   = trim((string)($data['email']   ?? ''));
    $phone   = trim((string)($data['phone']   ?? ''));
    $result  = $data['result']   ?? [];
    $prop    = $data['property'] ?? [];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['success' => false, 'error' => 'Email inválido', 'request_id' => bin2hex(random_bytes(8))]);
    }

    $fullName   = trim("$name $surname") ?: 'Consulta';
    $toAdmin    = $cfg['agency_email'] ?? 'info@anperprimo.com';
    $agencyName = $cfg['agency_name']  ?? 'AnperPrimo';
    $agencyWeb  = $cfg['agency_web']   ?? 'anperprimo.com';
    $smtpCfg    = $cfg['smtp'] ?? [];
    $useSmtp    = !empty($smtpCfg['host']) && !empty($smtpCfg['user']);

    if (empty($smtpCfg['reply_to']) && !empty($cfg['agency_email']) && filter_var($cfg['agency_email'], FILTER_VALIDATE_EMAIL)) {
        $smtpCfg['reply_to'] = $cfg['agency_email'];
    }

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
    $params['ars_usd_rate']  = (int)($cfg['ars_usd_rate'] ?? 1400);

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

    $requestId = bin2hex(random_bytes(8));

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
        } catch (\Throwable $e) {
            error_log('send_email lead insert: ' . $e->getMessage());
        }
    } catch (\Throwable $e) {
        error_log('send_email db: ' . $e->getMessage());
    }

    $out = [
        'success'    => $okAdmin || $okUser,
        'sent_admin' => $okAdmin,
        'sent_user'  => $okUser,
        'method'     => $useSmtp ? 'brevo_smtp' : 'mail()',
        'request_id' => $requestId,
        'message'    => ($okAdmin || $okUser) ? "✓ Emails enviados a $email y $toAdmin" : "Error al enviar",
    ];
    if (!empty($sendApi['debug_response'])) {
        $out['debug'] = $results;
    }
    jsonOut($out);

} catch (\Throwable $e) {
    error_log('send_email: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    $ex = (bool)(($bootCfg['api']['send_email']['expose_exception_detail'] ?? false));
    $err = ['success' => false, 'request_id' => bin2hex(random_bytes(8))];
    if ($ex) {
        $err['error'] = $e->getMessage();
        $err['line'] = $e->getLine();
    } else {
        $err['error'] = 'Error interno';
    }
    jsonOut($err, 500);
}
