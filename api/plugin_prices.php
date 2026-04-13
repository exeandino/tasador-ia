<?php
/**
 * TasadorIA — api/plugin_prices.php
 * CRUD para gestionar el catálogo de plugins disponibles para venta.
 *
 * GET  ?action=list              → lista todos los plugins del catálogo (público)
 * POST ?action=save  (admin)     → crear/editar un plugin del catálogo
 * POST ?action=delete (admin)    → eliminar plugin del catálogo
 * POST ?action=upload_zip (admin)→ subir ZIP del plugin
 * POST ?action=toggle (admin)    → activar/desactivar plugin del catálogo
 * GET  ?action=sales (admin)     → listado de compras/ventas
 * POST ?action=manual_approve (admin) → aprobar compra manual y generar token
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// CORS: el endpoint ?action=list es público y debe ser accesible
// desde cualquier instalación de TasadorIA (GitHub installs)
$action_early = $_GET['action'] ?? '';
if ($action_early === 'list' || $action_early === '') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
}

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$cfg    = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
$action = $_GET['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? ($_POST['action'] ?? ''));

// ── BD ─────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
        $cfg['db']['user']??'', $cfg['db']['pass']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    // Auto-crear tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tasador_plugin_prices` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `slug`         VARCHAR(80)  NOT NULL,
        `name`         VARCHAR(120) NOT NULL,
        `description`  TEXT         DEFAULT NULL,
        `icon`         VARCHAR(10)  DEFAULT '🔌',
        `price_usd`    DECIMAL(8,2) NOT NULL DEFAULT 0,
        `active`       TINYINT(1)   NOT NULL DEFAULT 1,
        `has_zip`      TINYINT(1)   NOT NULL DEFAULT 0,
        `sort_order`   TINYINT      NOT NULL DEFAULT 99,
        `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (\Throwable $e) {
    out(['success'=>false,'error'=>'DB: '.$e->getMessage()]);
}

$zipsDir    = __DIR__.'/../plugins_zips/';
$pluginsDir = __DIR__.'/../plugins/';

// ══════════════════════════════════════════════════════════════
// LIST — público (sin auth)
// ══════════════════════════════════════════════════════════════
if ($action === 'list' || $action === '') {
    $rows = $pdo->query(
        "SELECT slug, name, description, icon, price_usd, active, has_zip, sort_order
         FROM tasador_plugin_prices
         ORDER BY sort_order ASC, id ASC"
    )->fetchAll();

    // Marcar si el ZIP existe físicamente
    foreach ($rows as &$r) {
        $r['price_usd'] = (float)$r['price_usd'];
        $r['active']    = (bool)$r['active'];
        $r['has_zip']   = is_file($zipsDir.$r['slug'].'.zip')
                       || is_dir($pluginsDir.$r['slug']);
    }
    out(['success'=>true,'plugins'=>$rows]);
}

// Las acciones siguientes requieren auth admin
if (!isset($_SESSION['ta_admin'])) {
    out(['success'=>false,'error'=>'No autorizado'], 403);
}

// ══════════════════════════════════════════════════════════════
// SAVE — crear o editar plugin del catálogo
// ══════════════════════════════════════════════════════════════
if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id          = intval($input['id']   ?? 0);
    $slug        = preg_replace('/[^a-z0-9\-_]/', '', strtolower(trim($input['slug']        ?? '')));
    $name        = trim($input['name']        ?? '');
    $description = trim($input['description'] ?? '');
    $icon        = mb_substr(trim($input['icon'] ?? '🔌'), 0, 6);
    $price_usd   = max(0, (float)($input['price_usd'] ?? 0));
    $sort_order  = intval($input['sort_order'] ?? 99);
    $active      = (bool)($input['active'] ?? true) ? 1 : 0;

    if (!$slug || !$name) out(['success'=>false,'error'=>'Slug y nombre son requeridos']);

    if ($id > 0) {
        $pdo->prepare("UPDATE tasador_plugin_prices
            SET slug=?,name=?,description=?,icon=?,price_usd=?,sort_order=?,active=?,updated_at=NOW()
            WHERE id=?")
            ->execute([$slug,$name,$description,$icon,$price_usd,$sort_order,$active,$id]);
        out(['success'=>true,'msg'=>'Plugin actualizado','id'=>$id]);
    } else {
        $pdo->prepare("INSERT INTO tasador_plugin_prices (slug,name,description,icon,price_usd,sort_order,active)
            VALUES (?,?,?,?,?,?,?)")
            ->execute([$slug,$name,$description,$icon,$price_usd,$sort_order,$active]);
        out(['success'=>true,'msg'=>'Plugin creado','id'=>(int)$pdo->lastInsertId()]);
    }
}

// ══════════════════════════════════════════════════════════════
// TOGGLE — activar/desactivar
// ══════════════════════════════════════════════════════════════
if ($action === 'toggle') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $slug   = preg_replace('/[^a-z0-9\-_]/', '', $input['slug'] ?? '');
    $active = ($input['active'] ?? true) ? 1 : 0;
    if (!$slug) out(['success'=>false,'error'=>'Slug requerido']);
    $pdo->prepare("UPDATE tasador_plugin_prices SET active=?, updated_at=NOW() WHERE slug=?")
        ->execute([$active, $slug]);
    out(['success'=>true]);
}

// ══════════════════════════════════════════════════════════════
// DELETE — eliminar del catálogo (no borra el ZIP)
// ══════════════════════════════════════════════════════════════
if ($action === 'delete') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id    = intval($input['id'] ?? 0);
    if (!$id) out(['success'=>false,'error'=>'ID requerido']);
    $pdo->prepare("DELETE FROM tasador_plugin_prices WHERE id=?")->execute([$id]);
    out(['success'=>true,'msg'=>'Plugin eliminado del catálogo']);
}

// ══════════════════════════════════════════════════════════════
// UPLOAD_ZIP — subir el ZIP del plugin
// ══════════════════════════════════════════════════════════════
if ($action === 'upload_zip') {
    $slug = preg_replace('/[^a-z0-9\-_]/', '', $_POST['slug'] ?? '');
    if (!$slug) out(['success'=>false,'error'=>'Slug requerido']);

    if (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
        out(['success'=>false,'error'=>'Error al subir el archivo: '.($_FILES['zip']['error']??'sin archivo')]);
    }

    $file = $_FILES['zip'];
    if ($file['size'] > 50 * 1024 * 1024) out(['success'=>false,'error'=>'ZIP demasiado grande (máx 50 MB)']);

    // Verificar que es un ZIP real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['application/zip','application/x-zip-compressed','application/octet-stream'])) {
        out(['success'=>false,'error'=>"Tipo de archivo inválido: $mime. Solo se aceptan ZIPs."]);
    }

    if (!is_dir($zipsDir)) mkdir($zipsDir, 0755, true);

    $dest = $zipsDir.$slug.'.zip';
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        out(['success'=>false,'error'=>'No se pudo mover el archivo al servidor']);
    }

    // Marcar has_zip=1 en BD
    $pdo->prepare("UPDATE tasador_plugin_prices SET has_zip=1, updated_at=NOW() WHERE slug=?")
        ->execute([$slug]);

    out(['success'=>true,'msg'=>"ZIP de '$slug' subido correctamente (".(int)($file['size']/1024).' KB)']);
}

// ══════════════════════════════════════════════════════════════
// SALES — listado de ventas/compras
// ══════════════════════════════════════════════════════════════
if ($action === 'sales') {
    $limit  = min(200, intval($_GET['limit'] ?? 50));
    $offset = max(0, intval($_GET['offset'] ?? 0));
    $status = $_GET['status'] ?? '';

    $where  = $status ? "WHERE status=?" : "";
    $params = $status ? [$status] : [];

    $rows = $pdo->prepare(
        "SELECT id, order_id, plugin_slug, plugin_name, gateway, buyer_email, buyer_name,
                price_usd, price_local, currency_local, status,
                download_count, download_used, download_expires, created_at
         FROM tasador_purchases
         $where
         ORDER BY created_at DESC
         LIMIT $limit OFFSET $offset"
    );
    $rows->execute($params);

    $total = $pdo->prepare("SELECT COUNT(*) FROM tasador_purchases $where");
    $total->execute($params);

    out([
        'success' => true,
        'sales'   => $rows->fetchAll(),
        'total'   => (int)$total->fetchColumn(),
    ]);
}

// ══════════════════════════════════════════════════════════════
// MANUAL_APPROVE — aprobar manualmente y generar link de descarga
// ══════════════════════════════════════════════════════════════
if ($action === 'manual_approve') {
    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $order_id = trim($input['order_id'] ?? '');
    if (!$order_id) out(['success'=>false,'error'=>'order_id requerido']);

    $row = $pdo->prepare("SELECT * FROM tasador_purchases WHERE order_id=? LIMIT 1");
    $row->execute([$order_id]);
    $purchase = $row->fetch();
    if (!$purchase) out(['success'=>false,'error'=>'Compra no encontrada']);

    $token   = bin2hex(random_bytes(32)); // 64 chars
    $expires = date('Y-m-d H:i:s', strtotime('+72 hours'));

    $pdo->prepare("UPDATE tasador_purchases
        SET status='approved', download_token=?, download_expires=?, updated_at=NOW()
        WHERE order_id=?")
        ->execute([$token, $expires, $order_id]);

    $siteUrl     = rtrim($cfg['site_url'] ?? 'https://anperprimo.com/tasador', '/');
    $downloadUrl = "$siteUrl/api/download_plugin.php?token=$token";

    out(['success'=>true, 'token'=>$token, 'download_url'=>$downloadUrl, 'expires'=>$expires]);
}

// ══════════════════════════════════════════════════════════════
// MANUAL_SALE — registrar venta manual (pago en efectivo, etc.)
// ══════════════════════════════════════════════════════════════
if ($action === 'manual_sale') {
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $slug       = preg_replace('/[^a-z0-9\-_]/', '', $input['slug'] ?? '');
    $email      = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $buyer_name = trim($input['buyer_name'] ?? '');
    $price_usd  = max(0, (float)($input['price_usd'] ?? 0));

    if (!$slug || !$email) out(['success'=>false,'error'=>'Slug y email requeridos']);

    // Buscar el plugin en catálogo
    $prod = $pdo->prepare("SELECT * FROM tasador_plugin_prices WHERE slug=? LIMIT 1");
    $prod->execute([$slug]);
    $plugin = $prod->fetch();
    if (!$plugin) out(['success'=>false,'error'=>'Plugin no encontrado en el catálogo']);

    $orderId = 'manual_'.strtoupper(bin2hex(random_bytes(5)));
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+720 hours')); // 30 días para ventas manuales

    $pdo->prepare("INSERT INTO tasador_purchases
        (order_id, plugin_slug, plugin_name, gateway, buyer_email, buyer_name,
         price_usd, status, download_token, download_expires)
        VALUES (?,?,?,'manual',?,?,?,'approved',?,?)")
        ->execute([
            $orderId, $slug, $plugin['name'], $email, $buyer_name,
            $price_usd ?: $plugin['price_usd'], $token, $expires
        ]);

    $siteUrl     = rtrim($cfg['site_url'] ?? 'https://anperprimo.com/tasador', '/');
    $downloadUrl = "$siteUrl/api/download_plugin.php?token=$token";

    out([
        'success'      => true,
        'order_id'     => $orderId,
        'download_url' => $downloadUrl,
        'expires'      => $expires,
    ]);
}

out(['success'=>false,'error'=>"Acción desconocida: $action"], 400);
