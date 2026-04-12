<?php
/**
 * TasadorIA — api/plugin_manager.php
 * API para gestión de plugins: listar, subir ZIP, activar, desactivar, desinstalar.
 *
 * GET  ?action=list                      → lista todos los plugins instalados
 * POST ?action=upload   (multipart file) → sube e instala un plugin ZIP
 * POST ?action=activate   {slug}         → activa un plugin
 * POST ?action=deactivate {slug}         → desactiva un plugin
 * POST ?action=uninstall  {slug}         → desinstala (borra archivos y BD)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Solo admin
if (!isset($_SESSION['ta_admin'])) {
    out(['success' => false, 'error' => 'No autorizado'], 403);
}

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
$action = $_GET['action'] ?? ($_POST['action'] ?? (json_decode(file_get_contents('php://input'),true)['action'] ?? ''));
$pluginsDir = __DIR__ . '/../plugins';

// ── BD ────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
        $cfg['db']['user']??'', $cfg['db']['pass']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasador_plugins (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        slug         VARCHAR(80) NOT NULL UNIQUE,
        name         VARCHAR(120) NOT NULL,
        version      VARCHAR(20) NOT NULL DEFAULT '1.0.0',
        author       VARCHAR(120) DEFAULT NULL,
        description  TEXT DEFAULT NULL,
        requires     VARCHAR(20) DEFAULT '5.0',
        active       TINYINT(1) NOT NULL DEFAULT 0,
        settings     JSON DEFAULT NULL,
        installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (\Throwable $e) {
    out(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
}

// ── LIST ──────────────────────────────────────────────────────
if ($action === 'list' || $action === '') {
    $installed = $pdo->query("SELECT * FROM tasador_plugins ORDER BY name")->fetchAll();

    // Detectar si hay archivos en disco pero no en BD (instalación manual)
    $dirPlugins = [];
    if (is_dir($pluginsDir)) {
        foreach (scandir($pluginsDir) as $f) {
            if ($f[0] === '.') continue;
            $meta = $pluginsDir . '/' . $f . '/plugin.json';
            if (is_file($meta)) $dirPlugins[] = $f;
        }
    }

    // Añadir los que están en disco pero no en BD
    $installedSlugs = array_column($installed, 'slug');
    foreach ($dirPlugins as $slug) {
        if (!in_array($slug, $installedSlugs)) {
            $meta = json_decode(file_get_contents($pluginsDir.'/'.$slug.'/plugin.json'), true) ?? [];
            $pdo->prepare("INSERT IGNORE INTO tasador_plugins (slug,name,version,author,description,requires,active)
                           VALUES (?,?,?,?,?,?,0)")
                ->execute([$slug, $meta['name']??$slug, $meta['version']??'1.0.0',
                           $meta['author']??'', $meta['description']??'', $meta['requires']??'5.0']);
            $installed = $pdo->query("SELECT * FROM tasador_plugins ORDER BY name")->fetchAll();
        }
    }

    out(['success' => true, 'plugins' => $installed]);
}

// ── UPLOAD ────────────────────────────────────────────────────
if ($action === 'upload') {
    if (empty($_FILES['file']['tmp_name'])) {
        out(['success' => false, 'error' => 'No se recibió archivo ZIP']);
    }

    $tmpFile = $_FILES['file']['tmp_name'];
    $origName = $_FILES['file']['name'] ?? 'plugin.zip';

    // Validar extensión
    if (!preg_match('/\.zip$/i', $origName)) {
        out(['success' => false, 'error' => 'El archivo debe ser un ZIP']);
    }

    // Abrir ZIP
    $zip = new ZipArchive();
    if ($zip->open($tmpFile) !== true) {
        out(['success' => false, 'error' => 'No se pudo abrir el ZIP. ¿Está corrupto?']);
    }

    // Buscar plugin.json en el ZIP
    $pluginJson = null;
    $pluginRoot = null;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#^([^/]+)/plugin\.json$#', $name, $m) || $name === 'plugin.json') {
            $pluginRoot = $m[1] ?? '';
            $pluginJson = json_decode($zip->getFromIndex($i), true);
            break;
        }
    }

    if (!$pluginJson) {
        $zip->close();
        out(['success' => false, 'error' => 'ZIP inválido: no contiene plugin.json en la raíz del plugin']);
    }

    // Validar campos mínimos de plugin.json
    foreach (['name', 'version', 'slug'] as $required) {
        if (empty($pluginJson[$required])) {
            $zip->close();
            out(['success' => false, 'error' => "plugin.json inválido: falta el campo '$required'"]);
        }
    }

    $slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($pluginJson['slug']));
    if (!$slug) {
        $zip->close();
        out(['success' => false, 'error' => 'Slug inválido en plugin.json']);
    }

    // Seguridad: bloquear path traversal
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_contains($name, '..') || str_starts_with($name, '/')) {
            $zip->close();
            out(['success' => false, 'error' => 'ZIP contiene rutas inseguras (path traversal)']);
        }
    }

    // Extraer a plugins/<slug>/
    $destDir = $pluginsDir . '/' . $slug;
    if (is_dir($destDir)) {
        // Actualización — borrar versión anterior
        deleteDir($destDir);
    }
    mkdir($destDir, 0755, true);

    // Extraer solo los archivos del plugin
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name    = $zip->getNameIndex($i);
        $relPath = $pluginRoot ? preg_replace('#^'.preg_quote($pluginRoot.'/', '#').'#', '', $name) : $name;
        if ($relPath === '' || $relPath === '/') continue;

        $dest = $destDir . '/' . $relPath;
        if (str_ends_with($name, '/')) {
            @mkdir($dest, 0755, true);
        } else {
            @mkdir(dirname($dest), 0755, true);
            file_put_contents($dest, $zip->getFromIndex($i));
        }
    }
    $zip->close();

    // Registrar en BD
    $pdo->prepare("INSERT INTO tasador_plugins (slug,name,version,author,description,requires,active)
                   VALUES (?,?,?,?,?,?,0)
                   ON DUPLICATE KEY UPDATE name=VALUES(name),version=VALUES(version),
                   author=VALUES(author),description=VALUES(description),updated_at=NOW()")
        ->execute([
            $slug,
            $pluginJson['name'],
            $pluginJson['version'],
            $pluginJson['author'] ?? '',
            $pluginJson['description'] ?? '',
            $pluginJson['requires'] ?? '5.0',
        ]);

    out(['success' => true, 'message' => "Plugin '{$pluginJson['name']}' instalado correctamente.", 'slug' => $slug]);
}

// ── ACTIVATE / DEACTIVATE / UNINSTALL ─────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$slug = preg_replace('/[^a-z0-9\-_]/', '', strtolower($body['slug'] ?? ''));

if (!$slug) out(['success' => false, 'error' => 'Slug requerido']);

if ($action === 'activate') {
    $pluginFile = $pluginsDir . '/' . $slug . '/index.php';
    if (!is_file($pluginFile)) {
        out(['success' => false, 'error' => 'Archivos del plugin no encontrados en disco']);
    }
    $pdo->prepare("UPDATE tasador_plugins SET active=1 WHERE slug=?")->execute([$slug]);
    out(['success' => true, 'message' => "Plugin '$slug' activado."]);
}

if ($action === 'deactivate') {
    $pdo->prepare("UPDATE tasador_plugins SET active=0 WHERE slug=?")->execute([$slug]);
    out(['success' => true, 'message' => "Plugin '$slug' desactivado."]);
}

if ($action === 'uninstall') {
    $pdo->prepare("UPDATE tasador_plugins SET active=0 WHERE slug=?")->execute([$slug]);
    $destDir = $pluginsDir . '/' . $slug;
    if (is_dir($destDir)) deleteDir($destDir);
    $pdo->prepare("DELETE FROM tasador_plugins WHERE slug=?")->execute([$slug]);
    out(['success' => true, 'message' => "Plugin '$slug' desinstalado."]);
}

out(['success' => false, 'error' => "Acción desconocida: $action"], 400);

// ── Helpers ───────────────────────────────────────────────────
function deleteDir(string $dir): void {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $f) {
        $path = "$dir/$f";
        is_dir($path) ? deleteDir($path) : unlink($path);
    }
    rmdir($dir);
}
