<?php
/**
 * TasadorIA — api/user_tasaciones.php
 * CRUD de tasaciones guardadas por usuario.
 * Acciones: save, list, get, delete, toggle_fav
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

function out(array $d, int $c=200): void { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
require __DIR__.'/../auth/middleware.php';

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? 'list';
$pdo    = authPdo($cfg);

// Auth — acepta cookie o Bearer token
$user = getCurrentUser($cfg);
if (!$user) {
    // Intentar Bearer (para llamadas desde valuar.php server-side)
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
        $hash = hash('sha256', trim($m[1]));
        $stmt = $pdo->prepare("SELECT u.* FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.session_token=? AND s.expires_at>NOW() AND u.status='active'");
        $stmt->execute([$hash]);
        $user = $stmt->fetch() ?: null;
    }
}

// save — llamado post-tasación (puede ser sin auth si viene de auto_register)
if ($action === 'save') {
    $uid  = $input['user_id'] ?? ($user['id'] ?? null);
    if (!$uid) out(['success'=>false,'error'=>'user_id requerido']);

    $code   = $input['code']          ?? '';
    $input_ = $input['input_data']    ?? null;
    $result = $input['result_data']   ?? null;
    $type   = $input['property_type'] ?? ($result['property_type'] ?? null);
    $area   = $input['covered_area']  ?? ($input_['covered_area'] ?? null);
    $city   = $input['city']          ?? ($result['zone']['city'] ?? null);
    $zone   = $input['zone']          ?? ($result['zone']['zone'] ?? null);
    $price  = $input['price_suggested']?? ($result['price']['suggested'] ?? null);
    $min    = $input['price_min']     ?? ($result['price']['min'] ?? null);
    $max    = $input['price_max']     ?? ($result['price']['max'] ?? null);
    $title  = $input['title']         ?? null;
    if (!$title && $type && $area) $title = ucfirst($type)." {$area}m²".($zone ? " · {$zone}" : '');

    try {
        $pdo->prepare("
            INSERT INTO user_tasaciones
              (user_id,tasacion_code,title,city,zone,property_type,operation,covered_area,
               price_suggested,price_min,price_max,currency,input_json,result_json)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,'USD',?,?)
            ON DUPLICATE KEY UPDATE
              title=VALUES(title), price_suggested=VALUES(price_suggested),
              price_min=VALUES(price_min), price_max=VALUES(price_max),
              result_json=VALUES(result_json), input_json=VALUES(input_json)
        ")->execute([
            $uid, $code, $title, $city, $zone, $type,
            $input['operation'] ?? ($input_['operation'] ?? null),
            $area, $price, $min, $max,
            $input_ ? json_encode($input_) : null,
            $result ? json_encode($result) : null,
        ]);
        // Incrementar contador en users
        $pdo->prepare("UPDATE users SET tasaciones_count=tasaciones_count+1, updated_at=NOW() WHERE id=? AND tier='free'")->execute([$uid]);
        out(['success'=>true,'code'=>$code]);
    } catch (\Throwable $e) {
        out(['success'=>false,'error'=>$e->getMessage()]);
    }
}

if (!$user) out(['success'=>false,'error'=>'No autenticado'], 401);

// list
if ($action === 'list') {
    $q   = $_GET['q'] ?? '';
    $fav = $_GET['favorites'] ?? '';
    $sql = "SELECT id,tasacion_code,title,city,zone,property_type,operation,covered_area,price_suggested,is_favorite,created_at FROM user_tasaciones WHERE user_id=?";
    $p   = [$user['id']];
    if ($q)   { $sql .= " AND (city LIKE ? OR zone LIKE ? OR title LIKE ?)"; $s="%{$q}%"; array_push($p,$s,$s,$s); }
    if ($fav) { $sql .= " AND is_favorite=1"; }
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $stmt = $pdo->prepare($sql); $stmt->execute($p);
    out(['success'=>true,'tasaciones'=>$stmt->fetchAll()]);
}

// get
if ($action === 'get') {
    $code = $input['code'] ?? $_GET['code'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM user_tasaciones WHERE tasacion_code=? AND user_id=?");
    $stmt->execute([$code, $user['id']]);
    $t = $stmt->fetch();
    if (!$t) out(['success'=>false,'error'=>'No encontrado'], 404);
    $t['result_json'] = $t['result_json'] ? json_decode($t['result_json'], true) : null;
    $t['input_json']  = $t['input_json']  ? json_decode($t['input_json'], true)  : null;
    out(['success'=>true,'tasacion'=>$t]);
}

// toggle_fav
if ($action === 'toggle_fav') {
    $id   = (int)($input['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, is_favorite FROM user_tasaciones WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user['id']]);
    $t = $stmt->fetch();
    if (!$t) out(['success'=>false,'error'=>'No encontrado'], 404);
    $newFav = $t['is_favorite'] ? 0 : 1;
    $pdo->prepare("UPDATE user_tasaciones SET is_favorite=? WHERE id=?")->execute([$newFav, $id]);
    out(['success'=>true,'is_favorite'=>(bool)$newFav]);
}

// delete
if ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    $pdo->prepare("DELETE FROM user_tasaciones WHERE id=? AND user_id=?")->execute([$id, $user['id']]);
    out(['success'=>true]);
}

out(['success'=>false,'error'=>"Acción desconocida: {$action}"], 400);
