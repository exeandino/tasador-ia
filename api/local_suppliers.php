<?php
/**
 * TasadorIA — api/local_suppliers.php
 * CRUD de corralones/proveedores locales + precios de materiales + flete.
 *
 * GET  ?action=list_suppliers                    → lista proveedores
 * GET  ?action=list_prices  [supplier_id] [slug] → precios de materiales
 * GET  ?action=compare_material&slug=xxx         → comparar proveedor vs ML vs promedio
 * GET  ?action=freight&from_lat&from_lng&to_lat&to_lng → cálculo de flete
 * POST {action:save_supplier, ...}
 * POST {action:save_price, ...}
 * POST {action:delete_supplier, id}
 * POST {action:delete_price, id}
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

// Auto-crear tablas
$pdo->exec("CREATE TABLE IF NOT EXISTS `local_suppliers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `city` VARCHAR(80) DEFAULT '',
    `address` VARCHAR(255) DEFAULT '',
    `phone` VARCHAR(40) DEFAULT '',
    `web` VARCHAR(255) DEFAULT '',
    `lat` DECIMAL(10,7) DEFAULT NULL,
    `lng` DECIMAL(10,7) DEFAULT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS `local_material_prices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id` INT UNSIGNED NOT NULL,
    `material_slug` VARCHAR(80) NOT NULL,
    `material_name` VARCHAR(120) NOT NULL,
    `category` VARCHAR(60) DEFAULT 'general',
    `price_ars` DECIMAL(12,2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'unidad',
    `brand` VARCHAR(80) DEFAULT '',
    `notes` VARCHAR(255) DEFAULT '',
    `price_date` DATE NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `supplier_material` (`supplier_id`,`material_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action  = $_GET['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? 'list_suppliers');
$usdRate = (float)($cfg['ars_usd_rate'] ?? 1400);

// ── FLETE (público) ────────────────────────────────────────────
if ($action === 'freight') {
    $fromLat = (float)($_GET['from_lat'] ?? 0);
    $fromLng = (float)($_GET['from_lng'] ?? 0);
    $toLat   = (float)($_GET['to_lat']   ?? 0);
    $toLng   = (float)($_GET['to_lng']   ?? 0);
    $weightKg= max(1, (float)($_GET['weight_kg'] ?? 1000));

    if (!$fromLat || !$toLat) out(['success' => false, 'error' => 'Coordenadas requeridas']);

    // Haversine distance
    $R    = 6371;
    $dLat = deg2rad($toLat - $fromLat);
    $dLng = deg2rad($toLng - $fromLng);
    $a    = sin($dLat/2)**2 + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($dLng/2)**2;
    $dist = 2 * $R * asin(sqrt($a));

    // Tarifas de referencia (ARS 2025)
    $ratePerKm   = (float)($cfg['freight_rate_per_km']   ?? 3500);   // ARS/km
    $baseCharge  = (float)($cfg['freight_base_charge']   ?? 15000);  // ARS fijo
    $ratePerTon  = (float)($cfg['freight_rate_per_ton']  ?? 8000);   // ARS/ton
    $loadingTime = max(0.5, $dist / 60);                              // horas estimadas

    $freightArs = $baseCharge + ($dist * $ratePerKm) + (($weightKg/1000) * $ratePerTon);
    $freightUsd = $freightArs / $usdRate;

    out([
        'success'      => true,
        'distance_km'  => round($dist, 1),
        'weight_kg'    => $weightKg,
        'freight_ars'  => round($freightArs, -2),
        'freight_usd'  => round($freightUsd, 2),
        'time_hours'   => round($loadingTime, 1),
        'breakdown'    => [
            'base_ars'      => $baseCharge,
            'km_ars'        => round($dist * $ratePerKm, -2),
            'tonnage_ars'   => round(($weightKg/1000) * $ratePerTon, -2),
        ],
        'rates_used'   => [
            'per_km'    => $ratePerKm,
            'base'      => $baseCharge,
            'per_ton'   => $ratePerTon,
        ],
    ]);
}

// ── LIST SUPPLIERS ─────────────────────────────────────────────
if ($action === 'list_suppliers') {
    $city  = $_GET['city'] ?? '';
    $where = $city ? 'WHERE city LIKE ?' : 'WHERE 1=1';
    $bind  = $city ? ["%$city%"] : [];
    $stmt  = $pdo->prepare("SELECT s.*, COUNT(p.id) material_count
        FROM local_suppliers s
        LEFT JOIN local_material_prices p ON p.supplier_id = s.id
        $where GROUP BY s.id ORDER BY s.name");
    $stmt->execute($bind);
    out(['success' => true, 'suppliers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ── LIST PRICES ───────────────────────────────────────────────
if ($action === 'list_prices') {
    $sid  = (int)($_GET['supplier_id'] ?? 0);
    $slug = $_GET['slug'] ?? '';
    $cat  = $_GET['category'] ?? '';

    $where = ['1=1'];
    $bind  = [];
    if ($sid)  { $where[] = 'p.supplier_id = ?'; $bind[] = $sid; }
    if ($slug) { $where[] = 'p.material_slug LIKE ?'; $bind[] = "%$slug%"; }
    if ($cat)  { $where[] = 'p.category = ?'; $bind[] = $cat; }

    $stmt = $pdo->prepare("SELECT p.*, s.name supplier_name, s.city supplier_city
        FROM local_material_prices p
        JOIN local_suppliers s ON s.id = p.supplier_id
        WHERE ".implode(' AND ', $where)."
        ORDER BY p.category, p.material_name, p.price_date DESC");
    $stmt->execute($bind);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular USD equivalente
    foreach ($rows as &$r) {
        $r['price_usd'] = round((float)$r['price_ars'] / $usdRate, 2);
    }
    out(['success' => true, 'prices' => $rows]);
}

// ── COMPARE MATERIAL ──────────────────────────────────────────
if ($action === 'compare_material') {
    $slug = $_GET['slug'] ?? '';
    if (!$slug) out(['success' => false, 'error' => 'slug requerido']);

    // Precios de corralones (más reciente por proveedor)
    $stmt = $pdo->prepare("SELECT p.supplier_id, s.name supplier_name, s.city,
        p.material_name, p.price_ars, p.unit, p.brand, p.price_date,
        p.price_ars / ? price_usd
        FROM local_material_prices p
        JOIN local_suppliers s ON s.id = p.supplier_id
        WHERE p.material_slug = ?
        AND p.price_date = (SELECT MAX(p2.price_date) FROM local_material_prices p2
                            WHERE p2.supplier_id = p.supplier_id AND p2.material_slug = p.material_slug)
        ORDER BY p.price_ars ASC");
    $stmt->execute([$usdRate, $slug]);
    $local = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Precios ML (tabla construction_materials si existe)
    $ml = [];
    try {
        $stmt2 = $pdo->prepare("SELECT price_ars, price_date FROM construction_materials
            WHERE material_slug = ? ORDER BY price_date DESC LIMIT 1");
        $stmt2->execute([$slug]);
        $ml = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {}

    $prices = array_column($local, 'price_ars');
    $avg    = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;

    out([
        'success'          => true,
        'slug'             => $slug,
        'local_suppliers'  => $local,
        'mercadolibre'     => $ml,
        'avg_local_ars'    => round($avg, 2),
        'avg_local_usd'    => round($avg / $usdRate, 2),
        'cheapest_ars'     => count($prices) ? min($prices) : null,
        'most_expensive_ars' => count($prices) ? max($prices) : null,
    ]);
}

// ── AUTH para escritura ───────────────────────────────────────
if (!isset($_SESSION['ta_admin'])) out(['success' => false, 'error' => 'No autorizado'], 403);

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ── SAVE SUPPLIER ─────────────────────────────────────────────
if ($action === 'save_supplier') {
    $id   = (int)($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    if (!$name) out(['success' => false, 'error' => 'Nombre requerido']);

    $fields = ['name','city','address','phone','web','notes'];
    $lat  = $input['lat'] ? (float)$input['lat'] : null;
    $lng  = $input['lng'] ? (float)$input['lng'] : null;
    $vals = array_map(fn($f) => trim($input[$f] ?? ''), $fields);

    if ($id > 0) {
        $pdo->prepare("UPDATE local_suppliers SET name=?,city=?,address=?,phone=?,web=?,notes=?,lat=?,lng=?,active=1 WHERE id=?")
            ->execute([...$vals, $lat, $lng, $id]);
        out(['success' => true, 'id' => $id, 'msg' => 'Proveedor actualizado']);
    } else {
        $pdo->prepare("INSERT INTO local_suppliers (name,city,address,phone,web,notes,lat,lng) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([...$vals, $lat, $lng]);
        out(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'msg' => 'Proveedor creado']);
    }
}

// ── SAVE PRICE ────────────────────────────────────────────────
if ($action === 'save_price') {
    $id    = (int)($input['id'] ?? 0);
    $sid   = (int)($input['supplier_id'] ?? 0);
    $slug  = preg_replace('/[^a-z0-9\-_]/', '-', strtolower(trim($input['material_slug'] ?? '')));
    $mname = trim($input['material_name'] ?? '');
    $cat   = trim($input['category']      ?? 'general');
    $price = max(0, (float)($input['price_ars'] ?? 0));
    $unit  = trim($input['unit']          ?? 'unidad');
    $brand = trim($input['brand']         ?? '');
    $notes = trim($input['notes']         ?? '');
    $date  = trim($input['price_date']    ?? date('Y-m-d'));

    if (!$sid || !$slug || !$mname || $price <= 0) {
        out(['success' => false, 'error' => 'supplier_id, slug, nombre y precio son requeridos']);
    }

    if ($id > 0) {
        $pdo->prepare("UPDATE local_material_prices SET supplier_id=?,material_slug=?,material_name=?,
            category=?,price_ars=?,unit=?,brand=?,notes=?,price_date=? WHERE id=?")
            ->execute([$sid,$slug,$mname,$cat,$price,$unit,$brand,$notes,$date,$id]);
    } else {
        $pdo->prepare("INSERT INTO local_material_prices
            (supplier_id,material_slug,material_name,category,price_ars,unit,brand,notes,price_date)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$sid,$slug,$mname,$cat,$price,$unit,$brand,$notes,$date]);
        $id = (int)$pdo->lastInsertId();
    }
    out(['success' => true, 'id' => $id, 'msg' => 'Precio guardado']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($action === 'delete_supplier') {
    $id = (int)($input['id'] ?? 0);
    $pdo->prepare("DELETE FROM local_suppliers WHERE id=?")->execute([$id]);
    out(['success' => true]);
}
if ($action === 'delete_price') {
    $id = (int)($input['id'] ?? 0);
    $pdo->prepare("DELETE FROM local_material_prices WHERE id=?")->execute([$id]);
    out(['success' => true]);
}

out(['success' => false, 'error' => "Acción desconocida: $action"], 400);
