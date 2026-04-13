<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/settings.php';

// Auth check
$is_authenticated = isset($_SESSION['ta_admin']) && $_SESSION['ta_admin'] === true;

// Helper: JSON response
function json_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Helper: Error response
function error_response($message, $status = 400) {
    json_response(['success' => false, 'error' => $message], $status);
}

// Try to connect to DB
try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'],
        $cfg['db']['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_response('Database connection error: ' . $e->getMessage(), 500);
}

// Determine action
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    error_response('Action required', 400);
}

// GET: List all croquis
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT id, title, address, city, zone, total_m2, created_at, updated_at, svg_preview
            FROM property_croquis
            ORDER BY updated_at DESC
            LIMIT 50
        ");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response($result);
    } catch (Exception $e) {
        error_response('Error listing croquis: ' . $e->getMessage(), 500);
    }
}

// GET: Load single croquis
if ($action === 'load' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        error_response('ID required', 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, title, address, city, zone, total_m2, floor_data, svg_preview, created_at, updated_at
            FROM property_croquis
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            error_response('Croquis not found', 404);
        }

        // Decode JSON fields
        if ($result['floor_data']) {
            $result['floor_data'] = json_decode($result['floor_data'], true);
        }

        json_response($result);
    } catch (Exception $e) {
        error_response('Error loading croquis: ' . $e->getMessage(), 500);
    }
}

// POST: Save croquis
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_authenticated) {
        error_response('Unauthorized', 403);
    }

    $title = $_POST['title'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $zone = $_POST['zone'] ?? '';
    $floor_data = $_POST['floor_data'] ?? '{}';
    $total_m2 = floatval($_POST['total_m2'] ?? 0);
    $svg_preview = $_POST['svg_preview'] ?? '';
    $croquis_id = intval($_POST['croquis_id'] ?? 0);

    // Validate title
    if (!$title) {
        error_response('Title required', 400);
    }

    // Validate floor_data JSON
    $decoded = json_decode($floor_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_response('Invalid floor_data JSON: ' . json_last_error_msg(), 400);
    }

    try {
        $admin_id = $_SESSION['ta_admin_id'] ?? 0;

        if ($croquis_id > 0) {
            // Update existing
            $stmt = $pdo->prepare("
                UPDATE property_croquis
                SET title = ?, address = ?, city = ?, zone = ?, floor_data = ?, total_m2 = ?, svg_preview = ?, updated_at = NOW()
                WHERE id = ? AND admin_id = ?
            ");
            $stmt->execute([$title, $address, $city, $zone, $floor_data, $total_m2, $svg_preview, $croquis_id, $admin_id]);

            if ($stmt->rowCount() === 0) {
                error_response('Croquis not found or permission denied', 404);
            }

            json_response(['success' => true, 'id' => $croquis_id, 'action' => 'updated']);
        } else {
            // Create new
            $stmt = $pdo->prepare("
                INSERT INTO property_croquis (title, address, city, zone, floor_data, total_m2, svg_preview, admin_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$title, $address, $city, $zone, $floor_data, $total_m2, $svg_preview, $admin_id]);
            $id = $pdo->lastInsertId();

            json_response(['success' => true, 'id' => $id, 'action' => 'created']);
        }
    } catch (Exception $e) {
        error_response('Error saving croquis: ' . $e->getMessage(), 500);
    }
}

// POST: Delete croquis
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_authenticated) {
        error_response('Unauthorized', 403);
    }

    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        error_response('ID required', 400);
    }

    try {
        $admin_id = $_SESSION['ta_admin_id'] ?? 0;
        $stmt = $pdo->prepare("
            DELETE FROM property_croquis
            WHERE id = ? AND admin_id = ?
        ");
        $stmt->execute([$id, $admin_id]);

        if ($stmt->rowCount() === 0) {
            error_response('Croquis not found or permission denied', 404);
        }

        json_response(['success' => true, 'action' => 'deleted']);
    } catch (Exception $e) {
        error_response('Error deleting croquis: ' . $e->getMessage(), 500);
    }
}

// Unknown action
error_response('Unknown action: ' . htmlspecialchars($action), 400);
