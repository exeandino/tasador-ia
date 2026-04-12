<?php
/**
 * TasadorIA — Plugin Loader
 * Carga todos los plugins activos desde la BD y registra sus hooks.
 *
 * Uso en cualquier página del sistema:
 *   $plugins = require __DIR__.'/../plugins/loader.php';
 *
 * Hooks disponibles:
 *   do_action('admin_toolbar_buttons')  → botones extra en barras de admin
 *   do_action('admin_modals')           → modales extra en paneles admin
 *   do_action('admin_scripts')          → JS/CSS extra en páginas admin
 *   do_action('valuation_extra_factors', &$factors, $input)  → factores de valuación
 *   do_action('after_valuation', $result, $input)            → post-valuación
 *   apply_filters('price_result', $price, $input)            → modificar precio final
 *   do_action('lead_saved', $lead)      → cuando se guarda un lead
 */

// ── Sistema de hooks (ultra-liviano) ─────────────────────────
class PluginHooks {
    private static array $actions  = [];
    private static array $filters  = [];

    public static function addAction(string $hook, callable $cb, int $priority = 10): void {
        self::$actions[$hook][$priority][] = $cb;
    }
    public static function doAction(string $hook, ...$args): void {
        if (empty(self::$actions[$hook])) return;
        ksort(self::$actions[$hook]);
        foreach (self::$actions[$hook] as $prio => $cbs) {
            foreach ($cbs as $cb) call_user_func_array($cb, $args);
        }
    }
    public static function addFilter(string $hook, callable $cb, int $priority = 10): void {
        self::$filters[$hook][$priority][] = $cb;
    }
    public static function applyFilters(string $hook, mixed $value, ...$args): mixed {
        if (empty(self::$filters[$hook])) return $value;
        ksort(self::$filters[$hook]);
        foreach (self::$filters[$hook] as $prio => $cbs) {
            foreach ($cbs as $cb) $value = call_user_func_array($cb, [$value, ...$args]);
        }
        return $value;
    }
    public static function registeredActions(): array { return array_keys(self::$actions); }
}

// Funciones globales de conveniencia
function add_action(string $hook, callable $cb, int $priority = 10): void {
    PluginHooks::addAction($hook, $cb, $priority);
}
function do_action(string $hook, mixed ...$args): void {
    PluginHooks::doAction($hook, ...$args);
}
function add_filter(string $hook, callable $cb, int $priority = 10): void {
    PluginHooks::addFilter($hook, $cb, $priority);
}
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed {
    return PluginHooks::applyFilters($hook, $value, ...$args);
}

// ── Cargar plugins activos ────────────────────────────────────
$_loadedPlugins = [];

function loadPlugins(?PDO $pdo = null): array {
    global $_loadedPlugins;
    $pluginsDir = __DIR__;

    if (!$pdo) return [];

    try {
        // Asegurar tabla
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

        $active = $pdo->query(
            "SELECT slug FROM tasador_plugins WHERE active=1 ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($active as $slug) {
            $entryFile = $pluginsDir . '/' . $slug . '/index.php';
            if (is_file($entryFile)) {
                try {
                    require_once $entryFile;
                    $_loadedPlugins[] = $slug;
                } catch (\Throwable $e) {
                    error_log("TasadorIA Plugin Error [{$slug}]: " . $e->getMessage());
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("TasadorIA Plugin Loader Error: " . $e->getMessage());
    }

    return $_loadedPlugins;
}

return 'PluginHooks';
