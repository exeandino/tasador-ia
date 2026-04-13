<?php
/**
 * TasadorIA — api/price_proxy.php
 * Proxy de precios con rotación de proxies públicas ($0).
 *
 * Estrategia (igual que el tip de Reddit: rotador proxies + rotador headers):
 *  1. Intento directo a ML API (por si el bloqueo se levantó)
 *  2. Si 403/0 → busca lista de proxies HTTPS gratis
 *  3. Rota proxies + User-Agent hasta conseguir respuesta válida
 *  4. Fallback: Easy.com.ar / Blaisten.com.ar / Sodimac.com.ar
 *
 * GET ?q=cemento+portland&limit=10
 *  → { "results": [...], "total": N, "source": "ml_proxy|easy|blaisten|sodimac", "proxy": "..." }
 *  → { "error": "...", "details": {...} }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['ta_admin']) && !isset($_SESSION['bim_ok'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']); exit;
}

$q     = trim($_GET['q'] ?? '');
$limit = min(max(intval($_GET['limit'] ?? 10), 1), 20);
if ($q === '') { http_response_code(400); echo json_encode(['error' => 'Parámetro q requerido']); exit; }

// ── Pool de User-Agents (rotación) ────────────────────────────
const UA_POOL = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:123.0) Gecko/20100101 Firefox/123.0',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Mobile/15E148 Safari/604.1',
];

function randUa(): string {
    return UA_POOL[array_rand(UA_POOL)];
}

// ── cURL base ─────────────────────────────────────────────────
function doGet(string $url, array $extraHeaders = [], ?string $proxy = null): array {
    $headers = array_merge([
        'Accept: application/json, text/html, */*;q=0.9',
        'Accept-Language: es-AR,es;q=0.9,en-US;q=0.7,en;q=0.5',
        'Accept-Encoding: gzip, deflate, br',
        'User-Agent: ' . randUa(),
        'Cache-Control: no-cache',
        'Connection: keep-alive',
    ], $extraHeaders);

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,   // algunos proxies tienen certs self-signed
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_ENCODING       => '',      // acepta gzip/br automáticamente
    ];
    if ($proxy) {
        $opts[CURLOPT_PROXY]     = $proxy;
        $opts[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
    }
    curl_setopt_array($ch, $opts);
    $body    = curl_exec($ch);
    $code    = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $curlErr = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'code' => $code, 'err' => $curlErr];
}

// ── Obtener lista de proxies HTTPS públicas ───────────────────
function fetchProxyList(): array {
    // Cache en sesión por 30 minutos
    if (!empty($_SESSION['proxy_list']) && !empty($_SESSION['proxy_list_ts'])
        && time() - $_SESSION['proxy_list_ts'] < 1800) {
        return $_SESSION['proxy_list'];
    }

    $proxies = [];

    // Fuente 1: proxyscrape (lista de texto plano)
    $r = doGet('https://api.proxyscrape.com/v2/?request=getproxies&protocol=http&timeout=8000&country=all&ssl=yes&anonymity=all');
    if ($r['code'] === 200 && $r['body']) {
        foreach (explode("\n", trim($r['body'])) as $line) {
            $line = trim($line);
            if ($line && strpos($line, ':') !== false) $proxies[] = $line;
        }
    }

    // Fuente 2: free-proxy-list.net API alternativa
    if (count($proxies) < 5) {
        $r2 = doGet('https://proxylist.geonode.com/api/proxy-list?limit=30&page=1&sort_by=lastChecked&sort_type=desc&protocols=https,http');
        if ($r2['code'] === 200) {
            $d = json_decode($r2['body'], true);
            foreach ($d['data'] ?? [] as $p) {
                if (!empty($p['ip']) && !empty($p['port'])) {
                    $proxies[] = $p['ip'] . ':' . $p['port'];
                }
            }
        }
    }

    // Mezclar aleatoriamente para no siempre usar las mismas
    shuffle($proxies);
    $proxies = array_slice($proxies, 0, 30); // máximo 30 para no tardar demasiado

    $_SESSION['proxy_list']    = $proxies;
    $_SESSION['proxy_list_ts'] = time();

    return $proxies;
}

// ── Buscar en ML con proxy rotation ──────────────────────────
function searchML(string $q, int $limit, ?string $token = null): ?array {
    $url = 'https://api.mercadolibre.com/sites/MLA/search?q=' . urlencode($q) . '&limit=' . $limit;
    $extraH = $token ? ['Authorization: Bearer '.$token] : [];

    // Intento directo primero
    $res = doGet($url, $extraH);
    if ($res['code'] === 200) {
        $data = json_decode($res['body'], true);
        if (!empty($data['results'])) return _mlResults($data['results']);
    }

    // Si 403/blocked → rotar proxies
    if ($res['code'] === 403 || $res['code'] === 0) {
        $proxies = fetchProxyList();
        foreach ($proxies as $proxy) {
            $pr = doGet($url, array_merge($extraH, [
                'X-Forwarded-For: '.long2ip(rand(167772160, 3758096383)), // IP aleatoria
                'Referer: https://www.mercadolibre.com.ar/',
            ]), $proxy);
            if ($pr['code'] === 200 && $pr['body']) {
                $data = json_decode($pr['body'], true);
                if (!empty($data['results'])) {
                    // Guardar proxy que funcionó para próximos requests
                    $_SESSION['ml_working_proxy'] = $proxy;
                    return _mlResults($data['results']);
                }
            }
            // Pequeña pausa para no saturar
            usleep(100000); // 100ms
        }
    }

    return null;
}

function _mlResults(array $items): array {
    return array_map(fn($i) => [
        'title' => $i['title'] ?? '',
        'price' => floatval($i['price'] ?? 0),
        'seller_address' => ['state' => ['id' => $i['seller_address']['state']['id'] ?? '']],
    ], array_filter($items, fn($i) => ($i['price'] ?? 0) > 0));
}

// ── Easy.com.ar (VTEX) ────────────────────────────────────────
function searchEasy(string $q, int $limit): ?array {
    $enc = urlencode($q);
    // Probar dos formatos de URL de VTEX
    $urls = [
        "https://www.easy.com.ar/api/catalog_system/pub/products/search/?fq=ft:{$enc}&_from=0&_to=".($limit-1),
        "https://www.easy.com.ar/api/catalog_system/pub/products/search/{$enc}?_from=0&_to=".($limit-1),
    ];
    foreach ($urls as $url) {
        $r = doGet($url, ['Referer: https://www.easy.com.ar/construccion']);
        if ($r['code'] === 200 && $r['body']) {
            $products = json_decode($r['body'], true);
            if (is_array($products) && count($products) > 0) {
                $results = [];
                foreach ($products as $p) {
                    $price = floatval($p['items'][0]['sellers'][0]['commertialOffer']['Price'] ?? 0);
                    if ($price > 0) $results[] = [
                        'title' => $p['productName'] ?? '',
                        'price' => $price,
                        'seller_address' => ['state' => ['id' => 'AR-B']],
                    ];
                }
                if ($results) return $results;
            }
        }
    }
    return null;
}

// ── Blaisten.com.ar (VTEX — ferretería/construcción) ─────────
function searchBlaisten(string $q, int $limit): ?array {
    $enc = urlencode($q);
    $urls = [
        "https://www.blaisten.com.ar/api/catalog_system/pub/products/search/?fq=ft:{$enc}&_from=0&_to=".($limit-1),
        "https://www.blaisten.com.ar/api/catalog_system/pub/products/search/{$enc}?_from=0&_to=".($limit-1),
    ];
    foreach ($urls as $url) {
        $r = doGet($url, ['Referer: https://www.blaisten.com.ar/']);
        if ($r['code'] === 200 && $r['body']) {
            $products = json_decode($r['body'], true);
            if (is_array($products) && count($products) > 0) {
                $results = [];
                foreach ($products as $p) {
                    $price = floatval($p['items'][0]['sellers'][0]['commertialOffer']['Price'] ?? 0);
                    if ($price > 0) $results[] = [
                        'title' => $p['productName'] ?? '',
                        'price' => $price,
                        'seller_address' => ['state' => ['id' => 'AR-C']],
                    ];
                }
                if ($results) return $results;
            }
        }
    }
    return null;
}

// ── Sodimac.com.ar (VTEX) ─────────────────────────────────────
function searchSodimac(string $q, int $limit): ?array {
    $enc = urlencode($q);
    $urls = [
        "https://www.sodimac.com.ar/sodimac-ar/store/api/catalog_system/pub/products/search/?fq=ft:{$enc}&_from=0&_to=".($limit-1),
        "https://www.sodimac.com.ar/sodimac-ar/api/catalog_system/pub/products/search/?fq=ft:{$enc}&_from=0&_to=".($limit-1),
    ];
    foreach ($urls as $url) {
        $r = doGet($url, ['Referer: https://www.sodimac.com.ar/']);
        if ($r['code'] === 200 && $r['body']) {
            $products = json_decode($r['body'], true);
            if (is_array($products) && count($products) > 0) {
                $results = [];
                foreach ($products as $p) {
                    $price = floatval($p['items'][0]['sellers'][0]['commertialOffer']['Price'] ?? 0);
                    if ($price > 0) $results[] = [
                        'title' => $p['productName'] ?? '',
                        'price' => $price,
                        'seller_address' => ['state' => ['id' => 'AR-B']],
                    ];
                }
                if ($results) return $results;
            }
        }
    }
    return null;
}

// ── Ejecutar en cascada ───────────────────────────────────────
$token = $_SESSION['ml_token']['token'] ?? null;

// Si hay un proxy que funcionó antes, intentar ML con ese primero
$workingProxy = $_SESSION['ml_working_proxy'] ?? null;
if ($workingProxy) {
    $url = 'https://api.mercadolibre.com/sites/MLA/search?q=' . urlencode($q) . '&limit=' . $limit;
    $pr  = doGet($url, $token ? ['Authorization: Bearer '.$token] : [], $workingProxy);
    if ($pr['code'] === 200) {
        $data = json_decode($pr['body'], true);
        if (!empty($data['results'])) {
            echo json_encode(['results'=>_mlResults($data['results']),'total'=>count($data['results']),'source'=>'ml_proxy','proxy'=>$workingProxy]); exit;
        }
    } else {
        unset($_SESSION['ml_working_proxy']); // proxy expiró
    }
}

$sources = [
    ['ml',      fn() => searchML($q, $limit, $token)],
    ['easy',    fn() => searchEasy($q, $limit)],
    ['blaisten',fn() => searchBlaisten($q, $limit)],
    ['sodimac', fn() => searchSodimac($q, $limit)],
];

$errors = [];
foreach ($sources as [$name, $fn]) {
    $results = $fn();
    if ($results !== null && count($results) > 0) {
        echo json_encode(['results'=>$results,'total'=>count($results),'source'=>$name]);
        exit;
    }
    $errors[] = $name;
}

http_response_code(503);
echo json_encode([
    'error'         => 'Sin precios disponibles para: ' . $q,
    'sources_tried' => $errors,
    'suggestion'    => 'Intentá de nuevo en unos minutos — los proxies rotan automáticamente',
]);
