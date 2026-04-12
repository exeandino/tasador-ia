<?php
/**
 * TasadorIA — api/download_plugin.php
 * Sirve el ZIP de un plugin comprado, validando el token de descarga.
 *
 * GET ?token=<64-char-hex>
 *
 * Reglas:
 *  - Token debe existir en tasador_purchases
 *  - status debe ser 'approved'
 *  - download_expires no debe estar vencido
 *  - Si download_used=1 y download_count>3, rechaza (máx 3 descargas)
 */

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

function fail(string $msg, int $code = 403): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html><html><head><meta charset="utf-8"><title>Error — TasadorIA</title>
    <style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f5}
    .box{background:#fff;border-radius:12px;padding:40px;max-width:420px;text-align:center;box-shadow:0 2px 16px rgba(0,0,0,.1)}
    h2{color:#c0392b;margin-bottom:12px}p{color:#555;line-height:1.6}
    a{color:#c9a84c;font-weight:700}</style></head>
    <body><div class="box">
    <h2>⚠️ Error de descarga</h2>
    <p>$msg</p>
    <p style="margin-top:20px"><a href="../admin_plugins.php">← Volver al panel</a></p>
    </div></body></html>
    HTML;
    exit;
}

$token = preg_replace('/[^a-f0-9]/i', '', $_GET['token'] ?? '');
if (strlen($token) !== 64) fail('Token de descarga inválido.');

// BD
try {
    $pdo = new PDO(
        'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
        $cfg['db']['user']??'', $cfg['db']['pass']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (\Throwable $e) {
    fail('Error de base de datos. Contactá a soporte: exeandino@gmail.com', 500);
}

$stmt = $pdo->prepare("SELECT * FROM tasador_purchases WHERE download_token=? LIMIT 1");
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) fail('Token de descarga no encontrado o ya fue utilizado.');
if ($row['status'] !== 'approved') fail('Esta compra aún no fue aprobada. Si acabás de pagar, esperá unos segundos y recargá el email.');
if ($row['download_expires'] && strtotime($row['download_expires']) < time()) {
    fail('El link de descarga venció el ' . date('d/m/Y H:i', strtotime($row['download_expires'])) . '. Contactá a soporte para obtener un nuevo link.');
}

$maxDownloads = 5; // máximo 5 descargas por compra
if ($row['download_count'] >= $maxDownloads) {
    fail("Alcanzaste el límite de {$maxDownloads} descargas para esta compra. Contactá a soporte si necesitás más: exeandino@gmail.com");
}

// Buscar el ZIP del plugin
$slug    = $row['plugin_slug'];
$zipDir  = __DIR__ . '/../plugins_zips/';
$zipPath = $zipDir . $slug . '.zip';

// Fallback: buscar en plugins/{slug}/ y crear ZIP al vuelo si no existe ZIP pregenerado
if (!is_file($zipPath)) {
    $pluginDir = __DIR__ . '/../plugins/' . $slug . '/';
    if (!is_dir($pluginDir)) {
        error_log("[TasadorIA Download] Plugin dir not found: $pluginDir");
        fail('El archivo del plugin no está disponible en este momento. Contactá a soporte: exeandino@gmail.com', 404);
    }
    // Crear ZIP al vuelo
    if (!is_dir($zipDir)) mkdir($zipDir, 0755, true);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail('No se pudo generar el archivo ZIP. Contactá a soporte.', 500);
    }
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        $filePath = $file->getRealPath();
        $relPath  = $slug . '/' . substr($filePath, strlen($pluginDir));
        $zip->addFile($filePath, str_replace('\\', '/', $relPath));
    }
    $zip->close();
}

// Registrar descarga
$pdo->prepare("UPDATE tasador_purchases
    SET download_count = download_count + 1,
        download_used  = 1,
        updated_at     = NOW()
    WHERE id = ?")
    ->execute([$row['id']]);

error_log("[TasadorIA Download] Serving plugin '$slug' for order {$row['order_id']} (download #{$row['download_count']} + 1)");

// Servir el ZIP
$filename = $slug . '-plugin.zip';
$filesize = filesize($zipPath);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Limpiar output buffer
while (ob_get_level()) ob_end_clean();

readfile($zipPath);
exit;
