<?php
// api/deploy.php — Deploy automático vía Git · TasadorIA
// Llamado desde admin.php · NO exponer públicamente sin contraseña

define('DEPLOY_SECRET', 'anper2025');   // Misma contraseña del admin
define('REPO_DIR',      realpath(__DIR__ . '/..'));  // Raíz del proyecto
define('GIT_BRANCH',    'main');
define('SETTINGS_FILE', REPO_DIR . '/config/settings.php');  // NUNCA se pisa

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');

// ── Auth ──────────────────────────────────────────────────────────────────────
$secret = $_POST['secret'] ?? $_GET['secret'] ?? '';
if ($secret !== DEPLOY_SECRET) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? 'status';

// ── Helper: ejecutar comando ──────────────────────────────────────────────────
function runCmd(string $cmd, string $cwd = ''): array {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd ?: REPO_DIR);
    if (!is_resource($proc)) return ['out' => '', 'err' => 'No se pudo ejecutar comando', 'code' => -1];
    fclose($pipes[0]);
    $out  = trim(stream_get_contents($pipes[1]));
    $err  = trim(stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return ['out' => $out, 'err' => $err, 'code' => $code];
}

// ── Verificar git disponible ──────────────────────────────────────────────────
function gitAvailable(): bool {
    $r = runCmd('which git 2>/dev/null || command -v git 2>/dev/null');
    return $r['code'] === 0 && !empty($r['out']);
}

function isGitRepo(): bool {
    return is_dir(REPO_DIR . '/.git');
}

// ── ACTION: status ────────────────────────────────────────────────────────────
if ($action === 'status') {
    $gitOk   = gitAvailable();
    $isRepo  = isGitRepo();
    $branch  = '';
    $lastCommit = '';
    $remoteUrl  = '';
    $behind = 0;

    if ($gitOk && $isRepo) {
        $b = runCmd('git rev-parse --abbrev-ref HEAD');
        $branch = $b['out'];

        $lc = runCmd('git log -1 --pretty=format:"%h|%s|%ad" --date=format:"%d/%m/%Y %H:%i"');
        $lastCommit = $lc['out'];

        $ru = runCmd('git remote get-url origin 2>/dev/null');
        $remoteUrl = $ru['out'];

        // Fetch silently to check if behind
        runCmd('git fetch origin ' . GIT_BRANCH . ' --quiet 2>/dev/null');
        $beh = runCmd('git rev-list HEAD..origin/' . GIT_BRANCH . ' --count 2>/dev/null');
        $behind = (int)$beh['out'];
    }

    echo json_encode([
        'ok'          => true,
        'git_ok'      => $gitOk,
        'is_repo'     => $isRepo,
        'branch'      => $branch,
        'last_commit' => $lastCommit,
        'remote_url'  => $remoteUrl,
        'behind'      => $behind,
        'php_version' => PHP_VERSION,
        'repo_dir'    => REPO_DIR,
    ]);
    exit;
}

// ── ACTION: init ─────────────────────────────────────────────────────────────
if ($action === 'init') {
    if (!gitAvailable()) {
        echo json_encode(['ok' => false, 'error' => 'Git no está instalado en el servidor']);
        exit;
    }
    if (isGitRepo()) {
        echo json_encode(['ok' => false, 'error' => 'Ya es un repositorio git. Usá "pull" para actualizar.']);
        exit;
    }
    $repoUrl = trim($_POST['repo_url'] ?? '');
    if (empty($repoUrl) || !preg_match('/^https?:\/\//', $repoUrl)) {
        echo json_encode(['ok' => false, 'error' => 'URL del repositorio inválida']);
        exit;
    }

    // Backup settings.php
    $settingsBackup = null;
    if (file_exists(SETTINGS_FILE)) {
        $settingsBackup = file_get_contents(SETTINGS_FILE);
    }

    $log = [];

    // Init repo
    $r = runCmd('git init');
    $log[] = 'git init: ' . ($r['code'] === 0 ? '✅' : '❌ ' . $r['err']);

    $r = runCmd('git remote add origin ' . escapeshellarg($repoUrl));
    $log[] = 'git remote add: ' . ($r['code'] === 0 ? '✅' : '❌ ' . $r['err']);

    $r = runCmd('git fetch origin');
    $log[] = 'git fetch: ' . ($r['code'] === 0 ? '✅' : '❌ ' . $r['err']);
    if ($r['code'] !== 0) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo conectar al repositorio: ' . $r['err'], 'log' => $log]);
        exit;
    }

    $r = runCmd('git checkout -b ' . GIT_BRANCH . ' origin/' . GIT_BRANCH);
    if ($r['code'] !== 0) {
        $r = runCmd('git checkout ' . GIT_BRANCH);
    }
    $log[] = 'git checkout: ' . ($r['code'] === 0 ? '✅' : '❌ ' . $r['err']);

    // Restaurar settings.php (NUNCA pisar)
    if ($settingsBackup !== null) {
        file_put_contents(SETTINGS_FILE, $settingsBackup);
        $log[] = 'settings.php: ✅ restaurado (no pisado)';
    }

    echo json_encode(['ok' => $r['code'] === 0, 'log' => $log, 'message' => 'Repositorio inicializado']);
    exit;
}

// ── ACTION: pull ─────────────────────────────────────────────────────────────
if ($action === 'pull') {
    if (!gitAvailable()) {
        echo json_encode(['ok' => false, 'error' => 'Git no está instalado en el servidor']);
        exit;
    }
    if (!isGitRepo()) {
        echo json_encode(['ok' => false, 'error' => 'No hay repositorio git. Primero inicializá con "init".']);
        exit;
    }

    // Backup settings.php ANTES de todo
    $settingsBackup = null;
    if (file_exists(SETTINGS_FILE)) {
        $settingsBackup = file_get_contents(SETTINGS_FILE);
    }

    $log = [];

    // Fetch
    $r = runCmd('git fetch origin ' . GIT_BRANCH);
    $log[] = 'fetch: ' . ($r['code'] === 0 ? '✅' : '⚠️ ' . $r['err']);

    // Qué archivos van a cambiar
    $diff = runCmd('git diff HEAD..origin/' . GIT_BRANCH . ' --name-only');
    $changedFiles = array_filter(explode("\n", $diff['out']));
    $changedFiles = array_values(array_filter($changedFiles, fn($f) => $f !== ''));

    // Pull con reset (para evitar conflictos en servidor)
    $r = runCmd('git reset --hard origin/' . GIT_BRANCH);
    if ($r['code'] !== 0) {
        // Fallback: merge
        $r = runCmd('git pull origin ' . GIT_BRANCH . ' --ff-only');
    }
    $success = $r['code'] === 0;
    $log[] = 'pull: ' . ($success ? '✅ ' . $r['out'] : '❌ ' . $r['err']);

    // SIEMPRE restaurar settings.php
    if ($settingsBackup !== null) {
        file_put_contents(SETTINGS_FILE, $settingsBackup);
        $log[] = 'settings.php: ✅ preservado';
    }

    // Último commit
    $lc = runCmd('git log -1 --pretty=format:"%h — %s — %ad" --date=format:"%d/%m/%Y %H:%M"');

    echo json_encode([
        'ok'            => $success,
        'log'           => $log,
        'changed_files' => $changedFiles,
        'last_commit'   => $lc['out'],
        'message'       => $success ? '✅ Actualizado correctamente' : '❌ Error al actualizar',
    ]);
    exit;
}

// ── ACTION: check_updates ────────────────────────────────────────────────────
if ($action === 'check_updates') {
    if (!gitAvailable() || !isGitRepo()) {
        echo json_encode(['ok' => false, 'behind' => 0, 'error' => 'Git no configurado']);
        exit;
    }
    runCmd('git fetch origin ' . GIT_BRANCH . ' --quiet');
    $beh = runCmd('git rev-list HEAD..origin/' . GIT_BRANCH . ' --count');
    $diff = runCmd('git diff HEAD..origin/' . GIT_BRANCH . ' --name-only');
    $files = array_values(array_filter(explode("\n", $diff['out'])));
    echo json_encode(['ok' => true, 'behind' => (int)$beh['out'], 'pending_files' => $files]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción desconocida: ' . htmlspecialchars($action)]);
