<?php
// tasador/api/send_email.php
// Envía igual que WordPress: PHP mail() del servidor, sin SMTP
// El FROM se construye automáticamente como wordpress hace

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ob_end_clean();
    echo json_encode(['ok' => true, 'php_mail' => function_exists('mail'), 'php' => PHP_VERSION]);
    exit;
}

function jsonOut(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

try {
    $cfg  = require_once __DIR__ . '/../config/settings.php';
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // Validar
    $name    = trim((string)($data['name']    ?? ''));
    $surname = trim((string)($data['surname'] ?? ''));
    $email   = trim((string)($data['email']   ?? ''));
    $phone   = trim((string)($data['phone']   ?? ''));
    $result  = $data['result']   ?? [];
    $prop    = $data['property'] ?? [];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['success' => false, 'error' => 'Email inválido: ' . $email]);
    }

    $fullName   = trim("$name $surname") ?: 'Consulta';
    $toAdmin    = $cfg['agency_email'] ?? 'info@yourdomain.com';
    $agencyName = $cfg['agency_name']  ?? 'YourAgency';

    // ── FROM: igual que WordPress — usa el dominio del servidor ───────────────
    // WordPress hace: wordpress@{SERVER_NAME} si no está configurado
    // Nosotros usamos: noreply@yourdomain.com (email real del dominio)
    $serverDomain = $_SERVER['SERVER_NAME'] ?? parse_url($cfg['app_url'] ?? '', PHP_URL_HOST) ?? 'yourdomain.com';
    $fromEmail    = 'noreply@' . $serverDomain;  // noreply@yourdomain.com
    $fromName     = $agencyName;

    // ── Datos del resultado ────────────────────────────────────────────────────
    $priceUSD  = 'USD ' . number_format((float)($result['price']['suggested'] ?? 0), 0, ',', '.');
    $priceMin  = 'USD ' . number_format((float)($result['price']['min'] ?? 0), 0, ',', '.');
    $priceMax  = 'USD ' . number_format((float)($result['price']['max'] ?? 0), 0, ',', '.');
    $priceARS  = '$ '  . number_format((float)($result['price_ars']['suggested'] ?? 0), 0, ',', '.') . ' ARS';
    $ppm2      = 'USD ' . number_format((float)($result['price']['ppm2'] ?? 0), 0, ',', '.') . '/m²';
    $zone      = (string)($result['zone']['zone']  ?? '');
    $city      = (string)($result['zone']['city']  ?? '');
    $code      = (string)($result['code']          ?? '');
    $margin    = (int)($result['price']['margin_pct'] ?? 15);
    $gold      = '#c9a84c';
    $date      = date('d/m/Y H:i');

    // Filas de datos de la propiedad
    $propRows = '';
    foreach ([
        'property_type' => 'Tipo',        'covered_area'  => 'Sup. cubierta (m²)',
        'total_area'    => 'Sup. total',   'bedrooms'      => 'Dormitorios',
        'bathrooms'     => 'Baños',        'garages'       => 'Cocheras',
        'age_years'     => 'Antigüedad',   'condition'     => 'Estado',
        'view'          => 'Vista',        'orientation'   => 'Orientación',
        'address'       => 'Dirección',    'city'          => 'Ciudad',
    ] as $k => $label) {
        $v = (string)($prop[$k] ?? '');
        if ($v === '' || $v === '0' && !in_array($k, ['age_years'])) continue;
        if ($k === 'age_years' && $v === '0') $v = 'A estrenar';
        $propRows .= "<tr><td style='padding:6px 12px;color:#888;font-size:13px;border-bottom:1px solid #f0f0f0'>$label</td>"
                   . "<td style='padding:6px 12px;font-size:13px;font-weight:600;border-bottom:1px solid #f0f0f0'>" . htmlspecialchars($v) . "</td></tr>";
    }

    // Filas de factores
    $factRows = '';
    foreach ((array)($result['multipliers'] ?? []) as $k => $v) {
        $pct   = round(((float)($v['factor'] ?? 1) - 1) * 100, 1);
        $s     = $pct > 0 ? '+' : '';
        $color = $pct > 0 ? '#00a86b' : ($pct < 0 ? '#e53935' : '#888');
        $factRows .= "<tr><td style='padding:5px 12px;font-size:13px;border-bottom:1px solid #f5f5f5'>" . htmlspecialchars($k) . " <span style='color:#aaa;font-size:11px'>" . htmlspecialchars((string)($v['label'] ?? '')) . "</span></td>"
                   . "<td style='padding:5px 12px;font-size:13px;font-weight:700;text-align:right;color:$color;border-bottom:1px solid #f5f5f5'>$s$pct%</td></tr>";
    }

    // ── HTML del email ─────────────────────────────────────────────────────────
    function buildHtml(string $priceUSD, string $priceMin, string $priceMax, string $priceARS, string $ppm2,
                        string $zone, string $city, string $code, string $date, int $margin,
                        string $fullName, string $email, string $phone,
                        string $propRows, string $factRows, string $agencyName,
                        string $gold, array $cfg, bool $forUser = false): string {
        $greeting = $forUser
            ? "<tr><td style='padding:20px 32px 0;font-size:14px;color:#555'>Hola <strong>$fullName</strong>, aquí está el resultado de tu tasación. Podés contactarnos sin cargo para más información.</td></tr>"
            : '';
        $contactSection = !$forUser ? "
        <tr><td style='padding:20px 32px 0'>
          <h3 style='margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#1a1a2e'>Datos del solicitante</h3>
          <table width='100%' cellpadding='0' cellspacing='0' style='background:#f8f8fb;border-radius:8px'>
            <tr><td style='padding:7px 12px;color:#888;font-size:13px;border-bottom:1px solid #f0f0f0'>Nombre</td><td style='padding:7px 12px;font-size:13px;font-weight:700;border-bottom:1px solid #f0f0f0'>$fullName</td></tr>
            <tr><td style='padding:7px 12px;color:#888;font-size:13px;border-bottom:1px solid #f0f0f0'>Email</td><td style='padding:7px 12px;font-size:13px;font-weight:700;border-bottom:1px solid #f0f0f0'><a href='mailto:$email' style='color:$gold'>$email</a></td></tr>
            <tr><td style='padding:7px 12px;color:#888;font-size:13px'>Teléfono</td><td style='padding:7px 12px;font-size:13px;font-weight:700'>$phone</td></tr>
          </table>
        </td></tr>" : '';

        return "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#ededf5;font-family:Arial,sans-serif'>
<table width='100%' cellpadding='0' cellspacing='0' style='padding:20px 0'>
<tr><td align='center'>
<table width='580' cellpadding='0' cellspacing='0' style='max-width:580px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12)'>

  <tr><td style='background:#1a1a2e;padding:26px 32px;text-align:center'>
    <div style='font-size:22px;color:$gold;font-family:Georgia,serif;font-weight:bold'>TasadorIA</div>
    <div style='font-size:11px;color:rgba(255,255,255,.5);letter-spacing:1px;margin-top:4px;text-transform:uppercase'>$agencyName</div>
  </td></tr>

  $greeting
  $contactSection

  <tr><td style='padding:28px 32px;text-align:center;border-bottom:2px solid #f0f0f0'>
    <div style='font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px'>Tasación $code · $date</div>
    <div style='font-size:13px;color:$gold;font-weight:600;margin-bottom:16px'>📍 $zone · $city</div>
    <div style='font-size:11px;color:#aaa;margin-bottom:4px'>VALOR SUGERIDO</div>
    <div style='font-size:46px;font-weight:800;color:#1a1a2e;font-family:Georgia,serif;line-height:1'>$priceUSD</div>
    <div style='font-size:14px;color:#777;margin-top:10px'>Rango: <strong>$priceMin</strong> — <strong>$priceMax</strong></div>
    <div style='font-size:13px;color:#aaa;margin-top:4px'>$priceARS · $ppm2</div>
    <div style='display:inline-block;margin-top:12px;background:#f8f8fb;padding:5px 16px;border-radius:20px;font-size:12px;font-weight:700;color:#555'>Intervalo ±$margin%</div>
  </td></tr>

  <tr><td style='padding:20px 32px 0'>
    <h3 style='margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#1a1a2e'>Datos de la propiedad</h3>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:#f8f8fb;border-radius:8px'>$propRows</table>
  </td></tr>

  <tr><td style='padding:16px 32px 0'>
    <h3 style='margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#1a1a2e'>Factores aplicados</h3>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:#f8f8fb;border-radius:8px'>
      <tr style='background:#ededf5'><th style='padding:6px 12px;font-size:11px;color:#888;text-align:left'>Factor</th><th style='padding:6px 12px;font-size:11px;color:#888;text-align:right'>Ajuste</th></tr>
      $factRows
    </table>
  </td></tr>

  <tr><td style='padding:20px 32px;background:#fafafa;margin-top:16px'>
    <p style='margin:0;font-size:11px;color:#bbb;text-align:center;line-height:1.6'>Esta tasación es orientativa y no constituye oferta ni documento legal. Los valores son referenciales al momento de la consulta.</p>
  </td></tr>

  <tr><td style='background:#1a1a2e;padding:18px 32px;text-align:center'>
    <div style='color:$gold;font-size:14px;font-weight:bold'>{$cfg['agency_name']}</div>
    <div style='color:rgba(255,255,255,.45);font-size:12px;margin-top:4px'>{$cfg['agency_phone']} · {$cfg['agency_web']}</div>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";
    }

    $htmlAdmin = buildHtml($priceUSD,$priceMin,$priceMax,$priceARS,$ppm2,$zone,$city,$code,$date,$margin,$fullName,$email,$phone,$propRows,$factRows,$agencyName,$gold,$cfg,false);
    $htmlUser  = buildHtml($priceUSD,$priceMin,$priceMax,$priceARS,$ppm2,$zone,$city,$code,$date,$margin,$fullName,$email,$phone,$propRows,$factRows,$agencyName,$gold,$cfg,true);

    $textPlain = "TASACIÓN $code — $date\n$agencyName\n\nZona: $zone, $city\nValor: $priceUSD\nRango: $priceMin — $priceMax\n$priceARS\n\nNombre: $fullName\nEmail: $email\nTel: $phone\n\nEsta tasación es orientativa y no constituye oferta ni documento legal.";

    // ── ENVIAR — idéntico a WordPress sin plugin SMTP ──────────────────────────
    // WordPress usa PHPMailer con isSendmail() cuando no hay SMTP configurado.
    // Esto es equivalente: mail() llama a /usr/sbin/sendmail internamente.
    function wpStyleMail(string $to, string $subject, string $htmlBody, string $textBody, string $fromEmail, string $fromName): bool {
        $b = 'WP' . md5(uniqid());

        // Headers exactamente como WordPress los construye
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $b . '"',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        $body = '--' . $b . "\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($textBody)) . "\r\n"
              . '--' . $b . "\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($htmlBody)) . "\r\n"
              . '--' . $b . '--';

        // mb_encode_mimeheader para el subject — igual que WordPress
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

        return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    }

    // Enviar al admin
    $okAdmin = wpStyleMail(
        $toAdmin,
        "[TasadorIA] Nueva consulta: $fullName — $priceUSD",
        $htmlAdmin,
        $textPlain,
        $fromEmail,
        $fromName
    );

    // Enviar al usuario
    $okUser = wpStyleMail(
        $email,
        "Tu tasación de propiedad: $priceUSD — TasadorIA",
        $htmlUser,
        $textPlain,
        $fromEmail,
        $fromName
    );

    // Guardar en BD
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'], $cfg['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        if (!empty($code)) {
            $pdo->prepare("UPDATE tasaciones SET name=?, email=?, phone=? WHERE code=?")
                ->execute([$fullName, $email, $phone, preg_replace('/[^A-Z0-9\-]/', '', strtoupper($code))]);
        }
        try {
            $pdo->prepare("INSERT INTO tasacion_leads (name,email,phone,result_code,property_data,email_sent,created_at) VALUES(?,?,?,?,?,?,NOW())")
                ->execute([$fullName, $email, $phone, $code, json_encode($prop, JSON_UNESCAPED_UNICODE), ($okAdmin || $okUser) ? 1 : 0]);
        } catch (\Throwable $e) {} // tabla puede no existir aún
    } catch (\Throwable $e) {}

    jsonOut([
        'success'    => $okAdmin || $okUser,
        'sent_admin' => $okAdmin,
        'sent_user'  => $okUser,
        'from'       => $fromEmail,
        'to_admin'   => $toAdmin,
        'to_user'    => $email,
        'message'    => ($okAdmin || $okUser)
            ? "Email enviado a $email y a $toAdmin"
            : "mail() devolvió false. Verificar test_email.php para diagnóstico.",
    ]);

} catch (\Throwable $e) {
    jsonOut(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
}
