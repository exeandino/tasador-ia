<?php
/**
 * TasadorIA — api/check_tier.php
 * Verifica si el plan del usuario autenticado ya fue activado.
 * Usado por payment_callback.php para polling post-pago.
 *
 * GET ?plan=pro  →  {"activated": true|false, "tier": "pro"}
 */
header('Content-Type: application/json; charset=utf-8');

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
require __DIR__.'/../auth/middleware.php';

$user = getCurrentUser($cfg);
if (!$user) {
    echo json_encode(['activated' => false, 'tier' => 'free']);
    exit;
}

try {
    $pdo  = authPdo($cfg);
    $stmt = $pdo->prepare("SELECT tier FROM users WHERE id=? AND status='active'");
    $stmt->execute([$user['id']]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $tier = $row['tier'] ?? 'free';

    $plan      = preg_replace('/[^a-z_]/', '', $_GET['plan'] ?? 'pro');
    $paidTiers = ['pro', 'agency', 'enterprise'];
    $activated = in_array($tier, $paidTiers);

    // Si se pasó un plan específico, verificar que sea ese o superior
    if ($plan && $plan !== 'pro') {
        $tierRank = ['free'=>0,'pro'=>1,'agency'=>2,'enterprise'=>3];
        $activated = ($tierRank[$tier] ?? 0) >= ($tierRank[$plan] ?? 1);
    }

    echo json_encode(['activated' => $activated, 'tier' => $tier]);
} catch (\Throwable $e) {
    echo json_encode(['activated' => false, 'tier' => 'free', 'error' => $e->getMessage()]);
}
