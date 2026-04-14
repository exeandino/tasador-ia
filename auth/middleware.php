<?php
/**
 * TasadorIA — auth/middleware.php
 * Incluir al inicio de cualquier página que requiera autenticación.
 *
 * Uso:
 *   require __DIR__.'/../auth/middleware.php';
 *   $user = requireAuth();           // redirige a login si no está autenticado
 *   $user = optionalAuth();          // retorna null si no está autenticado
 *   requireTier('pro');              // redirige a planes si tier es insuficiente
 *   requireRole('agency_admin');     // redirige si no tiene el rol
 */

if (!defined('TASADOR_AUTH_LOADED')) {
    define('TASADOR_AUTH_LOADED', true);
}

if (!isset($cfg)) {
    $cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
}

// ── DB helper ────────────────────────────────────────────────
function authPdo(array $cfg): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'], $cfg['db']['pass'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

// ── Obtener usuario desde cookie session ─────────────────────
function getCurrentUser(array $cfg): ?array {
    $token = $_COOKIE['ta_session'] ?? '';
    if (!$token) return null;
    $hash = hash('sha256', $token);
    try {
        $stmt = authPdo($cfg)->prepare("
            SELECT u.* FROM user_sessions s
            JOIN users u ON u.id = s.user_id
            WHERE s.session_token = ? AND s.expires_at > NOW() AND u.status = 'active'
        ");
        $stmt->execute([$hash]);
        return $stmt->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

// ── Requerir autenticación ────────────────────────────────────
function requireAuth(array $cfg, string $redirectTo = '/tasador/auth/login.php'): array {
    $user = getCurrentUser($cfg);
    if (!$user) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$redirectTo}?next={$back}");
        exit;
    }
    return $user;
}

// ── Auth opcional (no redirige) ───────────────────────────────
function optionalAuth(array $cfg): ?array {
    return getCurrentUser($cfg);
}

// ── Requerir tier mínimo ──────────────────────────────────────
function requireTier(array $cfg, string $minTier, string $redirectTo = '/tasador/planes.php'): array {
    $tierOrder = ['free'=>0,'pro'=>1,'agency'=>2,'enterprise'=>3];
    $user = requireAuth($cfg);
    $userLevel = $tierOrder[$user['tier']] ?? 0;
    $minLevel  = $tierOrder[$minTier]      ?? 0;
    if ($userLevel < $minLevel) {
        header("Location: {$redirectTo}?upgrade={$minTier}");
        exit;
    }
    return $user;
}

// ── Requerir rol ──────────────────────────────────────────────
function requireRole(array $cfg, string $role, string $redirectTo = '/tasador/'): array {
    $roleOrder = ['user'=>0,'agent'=>1,'agency_admin'=>2,'super_admin'=>3];
    $user = requireAuth($cfg);
    $userLevel = $roleOrder[$user['role']] ?? 0;
    $minLevel  = $roleOrder[$role]         ?? 0;
    if ($userLevel < $minLevel) {
        header("Location: {$redirectTo}?error=forbidden");
        exit;
    }
    return $user;
}

// ── Crear sesión (escribe cookie) ─────────────────────────────
function createUserSession(array $cfg, int $userId, bool $remember = false): string {
    $token    = bin2hex(random_bytes(32));
    $hash     = hash('sha256', $token);
    $days     = $remember ? 30 : 1;
    $expires  = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    try {
        authPdo($cfg)->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $userId, $hash,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
            $expires,
        ]);
    } catch (\Throwable $e) {}
    // Escribir cookie
    setcookie('ta_session', $token, [
        'expires'  => strtotime("+{$days} days"),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']),
    ]);
    return $token;
}

// ── Destruir sesión ───────────────────────────────────────────
function destroyUserSession(array $cfg): void {
    $token = $_COOKIE['ta_session'] ?? '';
    if ($token) {
        $hash = hash('sha256', $token);
        try { authPdo($cfg)->prepare("DELETE FROM user_sessions WHERE session_token=?")->execute([$hash]); }
        catch (\Throwable $e) {}
        setcookie('ta_session', '', ['expires'=>time()-3600,'path'=>'/']);
    }
}

// ── Chequear si puede tasar (freemium gate) ───────────────────
function canTasar(array $cfg, ?array $user): array {
    if (!$user) {
        // Anónimo: contar por IP
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = authPdo($cfg)->prepare("SELECT COUNT(*) FROM tasaciones WHERE ip_address=? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$ip]);
        $used = (int)$stmt->fetchColumn();
        return ['allowed'=>$used<5, 'used'=>$used, 'limit'=>5, 'tier'=>'anonymous'];
    }
    if ($user['tier'] !== 'free') {
        return ['allowed'=>true, 'used'=>$user['tasaciones_count'], 'limit'=>null, 'tier'=>$user['tier']];
    }
    $used  = (int)$user['tasaciones_count'];
    $limit = (int)$user['tasaciones_limit'];
    return ['allowed'=>$used<$limit, 'used'=>$used, 'limit'=>$limit, 'tier'=>'free'];
}
