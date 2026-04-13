<?php
// tasador/api/save_settings.php
// Guarda configuración sin problemas de caché PHP
// Llamado por AJAX desde admin.php

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ob_end_clean(); echo json_encode(['error'=>'POST only']); exit; }

define('ADMIN_PASS', 'anper2025');

$raw  = json_decode(file_get_contents('php://input'), true) ?? [];
$key  = $raw['key']  ?? $_POST['key']  ?? '';
$val  = $raw['val']  ?? $_POST['val']  ?? '';
$pass = $raw['pass'] ?? $_POST['pass'] ?? '';

if ($pass !== ADMIN_PASS) { ob_end_clean(); echo json_encode(['error'=>'Unauthorized']); exit; }
if (!$key || $val === '') { ob_end_clean(); echo json_encode(['error'=>'key y val requeridos']); exit; }

$settingsFile = __DIR__ . '/../config/settings.php';

function updateSetting(string $file, string $key, string $newVal): array {
    if (!file_exists($file)) return ['ok'=>false,'error'=>"Archivo no encontrado: $file"];
    if (!is_writable($file)) return ['ok'=>false,'error'=>"Archivo sin permisos de escritura: $file"];

    $content = file_get_contents($file);
    if ($content === false) return ['ok'=>false,'error'=>'No se pudo leer el archivo'];

    // Reemplazar el valor — soporta integers, floats, y strings
    $pattern = "/('$key'\s*=>\s*)(\d+(?:\.\d+)?|'[^']*')/";
    $isNum   = is_numeric($newVal);
    $replace  = '${1}' . ($isNum ? $newVal : "'$newVal'");
    $new      = preg_replace($pattern, $replace, $content, 1, $count);

    if ($count === 0) return ['ok'=>false,'error'=>"Clave '$key' no encontrada en settings.php"];
    if ($new === $content) return ['ok'=>true,'msg'=>'Sin cambios (valor igual)','value'=>$newVal];

    $bytes = file_put_contents($file, $new, LOCK_EX);
    if ($bytes === false) return ['ok'=>false,'error'=>'No se pudo escribir el archivo'];

    // Limpiar OPcache si está activo
    if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
    if (function_exists('opcache_reset'))      opcache_reset();

    // Verificar que se guardó correctamente leyendo de nuevo
    $verify = file_get_contents($file);
    if (!str_contains($verify, "'$key' => $newVal") && !str_contains($verify, "'$key' => '$newVal'")) {
        // Intento alternativo con más variantes de formato
        $altPattern = "/\"$key\"\s*=>\s*[\d.]+/";
        $new2 = preg_replace($altPattern, "\"$key\" => $newVal", $content, 1, $c2);
        if ($c2 > 0) {
            file_put_contents($file, $new2, LOCK_EX);
            if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
        }
    }

    return ['ok'=>true,'msg'=>"'$key' actualizado a: $newVal",'value'=>$newVal,'bytes'=>$bytes];
}

$result = updateSetting($settingsFile, $key, $val);

// Leer el valor actual después de guardar (sin caché)
$currentVal = null;
try {
    // Leer directamente del archivo, no de require (que está cacheado)
    $content = file_get_contents($settingsFile);
    if (preg_match("/'$key'\s*=>\s*([^\s,]+)/", $content, $m)) {
        $currentVal = trim($m[1], "'");
    }
} catch (\Throwable $e) {}

$result['current_value'] = $currentVal;
$result['file']          = basename($settingsFile);
$result['writable']      = is_writable($settingsFile);

ob_end_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE);
