<?php
/**
 * TasadorIA — api/closing_prices.php
 * CRUD de precios de cierre reales (escrituras, boletos, testimonios).
 *
 * GET  ?action=list   [city] [zone] [type] [limit]  → lista + stats
 * GET  ?action=stats  [city] [zone]                  → avg por zona
 * POST {action:save, ...}                             → crear/editar
 * POST {action:delete, id}                            → eliminar
 * POST {action:import_csv, ...}                       → importar lote
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

function out(array $d, int $c = 200): void {
    http_response_code($c);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Throwable $e) {
    out(['success' => false, 'error' => 'DB: '.$e->getMessage()]);
}

// Auto-crear tabla
$pdo->exec("CREATE TABLE IF NOT EXISTS `market_closings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `address`       VARCHAR(255) NOT NULL,
  `city`          VARCHAR(80)  NOT NULL DEFAULT '',
  `zone`          VARCHAR(80)  NOT NULL DEFAULT '',
  `property_type` VARCHAR(30)  NOT NULL DEFAULT 'departamento',
  `operation`     VARCHAR(20)  NOT NULL DEFAULT 'venta',
  `covered_area`  DECIMAL(8,2) DEFAULT NULL,
  `total_area`    DECIMAL(8,2) DEFAULT NULL,
  `price_usd`     DECIMAL(12,2) NOT NULL,
  `price_per_m2`  DECIMAL(10,2) DEFAULT NULL,
  `close_date`    DATE NOT NULL,
  `source`        VARCHAR(30)  NOT NULL DEFAULT 'testimonio',
  `notes`         TEXT DEFAULT NULL,
  `bedrooms`      TINYINT UNSIGNED DEFAULT NULL,
  `bathrooms`     TINYINT UNSIGNED DEFAULT NULL,
  `lat`           DECIMAL(10,7) DEFAULT NULL,
  `lng`           DECIMAL(10,7) DEFAULT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `zone_type` (`city`,`zone`,`property_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? 'list');
$usdRate = (float)($cfg['ars_usd_rate'] ?? 1400);

// ── LIST ──────────────────────────────────────────────────────
if ($action === 'list') {
    $where = ['1=1'];
    $bind  = [];
    if ($c = ($_GET['city']  ?? '')) { $where[] = 'city  LIKE ?'; $bind[] = "%$c%"; }
    if ($z = ($_GET['zone']  ?? '')) { $where[] = 'zone  LIKE ?'; $bind[] = "%$z%"; }
    if ($t = ($_GET['type']  ?? '')) { $where[] = 'property_type = ?'; $bind[] = $t; }
    if ($o = ($_GET['operation'] ?? '')) { $where[] = 'operation = ?'; $bind[] = $o; }

    $limit  = min(500, (int)($_GET['limit'] ?? 100));
    $sql    = 'SELECT * FROM market_closings WHERE '.implode(' AND ', $where)
            . ' ORDER BY close_date DESC LIMIT ?';
    $bind[] = $limit;
    $stmt   = $pdo->prepare($sql);
    $stmt->execute($bind);
    $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats del conjunto
    $prices = array_column(array_filter($rows, fn($r) => $r['price_per_m2'] > 0), 'price_per_m2');
    sort($prices);
    $n   = count($prices);
    $avg = $n > 0 ? array_sum($prices) / $n : 0;
    $med = $n > 0 ? $prices[(int)($n/2)] : 0;

    out(['success' => true, 'closings' => $rows, 'total' => count($rows),
         'stats' => ['count' => $n, 'avg_ppm2' => round($avg), 'median_ppm2' => round($med),
                     'min_ppm2' => $n ? (float)$prices[0] : 0,
                     'max_ppm2' => $n ? (float)$prices[$n-1] : 0]]);
}

// ── STATS por zona ────────────────────────────────────────────
if ($action === 'stats') {
    $city = $_GET['city'] ?? '';
    $stmt = $pdo->prepare("SELECT zone, property_type, operation,
        COUNT(*) c,
        ROUND(AVG(price_per_m2),0) avg_ppm2,
        ROUND(MIN(price_per_m2),0) min_ppm2,
        ROUND(MAX(price_per_m2),0) max_ppm2,
        ROUND(AVG(price_usd),0) avg_price,
        MAX(close_date) last_date
        FROM market_closings
        WHERE city LIKE ? AND price_per_m2 > 0
        GROUP BY zone, property_type, operation
        ORDER BY zone, property_type");
    $stmt->execute(["%$city%"]);
    out(['success' => true, 'stats' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ── AUTH para escritura ───────────────────────────────────────
if (!isset($_SESSION['ta_admin'])) out(['success' => false, 'error' => 'No autorizado'], 403);

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ── SAVE ──────────────────────────────────────────────────────
if ($action === 'save') {
    $id      = (int)($input['id'] ?? 0);
    $addr    = trim($input['address']       ?? '');
    $city    = trim($input['city']          ?? '');
    $zone    = trim($input['zone']          ?? '');
    $type    = trim($input['property_type'] ?? 'departamento');
    $op      = trim($input['operation']     ?? 'venta');
    $cov     = max(0, (float)($input['covered_area'] ?? 0));
    $tot     = max(0, (float)($input['total_area']   ?? $cov));
    $price   = max(0, (float)($input['price_usd']    ?? 0));
    $date    = trim($input['close_date'] ?? date('Y-m-d'));
    $source  = trim($input['source']     ?? 'testimonio');
    $notes   = trim($input['notes']      ?? '');
    $beds    = $input['bedrooms']  !== '' && $input['bedrooms']  !== null ? (int)$input['bedrooms']  : null;
    $baths   = $input['bathrooms'] !== '' && $input['bathrooms'] !== null ? (int)$input['bathrooms'] : null;
    $lat     = $input['lat'] ? (float)$input['lat'] : null;
    $lng     = $input['lng'] ? (float)$input['lng'] : null;
    $ppm2    = $cov > 0 ? round($price / $cov, 2) : null;

    if (!$addr || !$price || !$date) out(['success' => false, 'error' => 'Dirección, precio y fecha son requeridos']);

    if ($id > 0) {
        $pdo->prepare("UPDATE market_closings SET address=?,city=?,zone=?,property_type=?,operation=?,
            covered_area=?,total_area=?,price_usd=?,price_per_m2=?,close_date=?,source=?,notes=?,
            bedrooms=?,bathrooms=?,lat=?,lng=? WHERE id=?")
            ->execute([$addr,$city,$zone,$type,$op,$cov,$tot,$price,$ppm2,$date,$source,$notes,$beds,$baths,$lat,$lng,$id]);
        out(['success' => true, 'msg' => 'Actualizado', 'id' => $id]);
    } else {
        $pdo->prepare("INSERT INTO market_closings
            (address,city,zone,property_type,operation,covered_area,total_area,price_usd,price_per_m2,close_date,source,notes,bedrooms,bathrooms,lat,lng)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$addr,$city,$zone,$type,$op,$cov,$tot,$price,$ppm2,$date,$source,$notes,$beds,$baths,$lat,$lng]);
        out(['success' => true, 'msg' => 'Guardado', 'id' => (int)$pdo->lastInsertId()]);
    }
}

// ── DELETE ────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) out(['success' => false, 'error' => 'ID requerido']);
    $pdo->prepare("DELETE FROM market_closings WHERE id=?")->execute([$id]);
    out(['success' => true, 'msg' => 'Eliminado']);
}

out(['success' => false, 'error' => "Acción desconocida: $action"], 400);
