<?php
/**
 * TasadorIA — admin_croquis.php
 * Generador de croquis / planos SVG para tasaciones.
 * Requiere rol agency_admin o superior.
 */
$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];
require __DIR__.'/auth/middleware.php';
$user = requireRole($cfg, 'agency_admin');

$brand        = $cfg['brand_name']    ?? 'TasadorIA';
$color        = $cfg['primary_color'] ?? '#c9a84c';
$currentPanel = 'croquis';
$adminId      = (int)($user['id'] ?? 0);

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'],
        $cfg['db']['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

// Handle POST save
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'save') {
    try {
        $floor_data = json_decode($_POST['floor_data'], true);
        $title = $_POST['title'] ?? '';
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $zone = $_POST['zone'] ?? '';
        $total_m2 = floatval($_POST['total_m2'] ?? 0);
        $svg_preview = $_POST['svg_preview'] ?? '';
        $croquis_id = $_POST['croquis_id'] ?? null;

        if (!$title) {
            throw new Exception('El título es requerido');
        }

        if ($croquis_id) {
            // Update existing
            $stmt = $pdo->prepare("
                UPDATE property_croquis
                SET title=?, address=?, city=?, zone=?, floor_data=?, total_m2=?, svg_preview=?, updated_at=NOW()
                WHERE id=? AND admin_id=?
            ");
            $stmt->execute([$title, $address, $city, $zone, json_encode($floor_data), $total_m2, $svg_preview, $croquis_id, $adminId]);
        } else {
            // Create new
            $stmt = $pdo->prepare("
                INSERT INTO property_croquis (title, address, city, zone, floor_data, total_m2, svg_preview, admin_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$title, $address, $city, $zone, json_encode($floor_data), $total_m2, $svg_preview, $adminId]);
            $croquis_id = $pdo->lastInsertId();
        }

        $save_message = 'Croquis guardado correctamente';
        $save_success = true;
    } catch (Exception $e) {
        $save_error = $e->getMessage();
    }
}

// Load croquis list
$croquis_list = [];
try {
    $stmt = $pdo->query("
        SELECT id, title, address, city, zone, total_m2, created_at, updated_at
        FROM property_croquis
        ORDER BY updated_at DESC
        LIMIT 50
    ");
    $croquis_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Croquis Editor - Admin</title>
    <style>
        :root {
            --bg: #0f0f0f;
            --bg2: #181818;
            --bg3: #222;
            --surface: #1e1e1e;
            --border: #2a2a2a;
            --gold: #c9a84c;
            --text: #e0e0e0;
            --muted: #888;
            --green: #4caf50;
            --red: #f44336;
            --blue: #4a8ff7;
            --font: system-ui, -apple-system, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topnav-wrapper {
            width: 100%;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .content-wrapper {
            display: flex;
            flex: 1;
            overflow: hidden;
            padding-top: 0;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--bg2);
            border-right: 1px solid var(--border);
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar h3 {
            margin: 0 0 15px 0;
            font-size: 13px;
            text-transform: uppercase;
            color: var(--gold);
            letter-spacing: 0.5px;
        }

        /* Room type toolbar */
        .room-types {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 25px;
        }

        .room-btn {
            padding: 10px 8px;
            border: 1px solid var(--border);
            background: var(--bg3);
            color: var(--text);
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .room-btn:hover {
            background: var(--surface);
            border-color: var(--gold);
        }

        .room-btn.active {
            background: var(--gold);
            color: var(--bg);
            border-color: var(--gold);
        }

        /* Properties panel */
        .properties-panel {
            background: var(--bg3);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }

        .properties-panel.visible {
            display: block;
        }

        .prop-group {
            margin-bottom: 12px;
        }

        .prop-group label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .prop-group input,
        .prop-group input[type="text"],
        .prop-group input[type="number"],
        .prop-group input[type="color"] {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            border-radius: 3px;
            font-family: inherit;
            font-size: 12px;
            transition: border-color 0.2s;
        }

        .prop-group input:focus {
            outline: none;
            border-color: var(--gold);
        }

        /* Saved list */
        .saved-list {
            flex: 1;
            overflow-y: auto;
            border-top: 1px solid var(--border);
            padding-top: 15px;
            margin-top: 15px;
        }

        .saved-item {
            padding: 10px;
            margin-bottom: 8px;
            background: var(--bg3);
            border: 1px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .saved-item:hover {
            border-color: var(--gold);
            background: var(--surface);
        }

        .saved-item-title {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .saved-item-delete {
            color: var(--red);
            cursor: pointer;
            font-size: 16px;
            padding: 0 5px;
            display: none;
        }

        .saved-item:hover .saved-item-delete {
            display: block;
        }

        /* Canvas area */
        .canvas-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            overflow: hidden;
        }

        /* Top controls */
        .canvas-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .control-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .control-group label {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .control-group input {
            width: 150px;
            padding: 6px 8px;
            border: 1px solid var(--border);
            background: var(--bg2);
            color: var(--text);
            border-radius: 3px;
            font-size: 12px;
        }

        .control-group input:focus {
            outline: none;
            border-color: var(--gold);
        }

        .btn {
            padding: 8px 14px;
            border: 1px solid var(--border);
            background: var(--bg2);
            color: var(--text);
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .btn:hover {
            background: var(--surface);
            border-color: var(--gold);
        }

        .btn.primary {
            background: var(--gold);
            color: var(--bg);
            border-color: var(--gold);
        }

        .btn.primary:hover {
            background: #dab855;
        }

        .btn.danger {
            color: var(--red);
            border-color: var(--red);
        }

        .btn.danger:hover {
            background: rgba(244, 67, 54, 0.1);
        }

        /* Canvas container */
        .svg-container {
            flex: 1;
            background: #1a1a1a;
            border: 1px solid var(--border);
            border-radius: 4px;
            overflow: auto;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        svg {
            cursor: crosshair;
            background: #1a1a1a;
        }

        /* Info bar */
        .info-bar {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding: 12px 15px;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 12px;
            flex-wrap: wrap;
        }

        .info-item {
            display: flex;
            gap: 8px;
        }

        .info-label {
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            color: var(--gold);
            font-weight: 600;
        }

        /* Messages */
        .message {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 12px;
            animation: slideDown 0.3s ease;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid var(--green);
            color: #7cce6f;
        }

        .message.error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid var(--red);
            color: #ff6f5f;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border);
                max-height: 200px;
                padding: 15px;
            }

            .room-types {
                grid-template-columns: repeat(3, 1fr);
            }

            .canvas-area {
                padding: 15px;
            }

            .canvas-controls {
                gap: 10px;
            }

            .control-group input {
                width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="topnav-wrapper">
        <?php include __DIR__ . '/includes/admin_topnav.php'; ?>
    </div>

    <div class="content-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <h3>Tipos de Sala</h3>
            <div class="room-types">
                <button class="room-btn active" data-type="sala" data-color="#4a8ff7">🛋 Sala</button>
                <button class="room-btn" data-type="dormitorio" data-color="#9c64f7">🛏 Dormitorio</button>
                <button class="room-btn" data-type="cocina" data-color="#f7a84a">🍳 Cocina</button>
                <button class="room-btn" data-type="bano" data-color="#4acff7">🚿 Baño</button>
                <button class="room-btn" data-type="comedor" data-color="#f7d44a">🍽 Comedor</button>
                <button class="room-btn" data-type="garage" data-color="#888">🚗 Cochera</button>
                <button class="room-btn" data-type="patio" data-color="#4caf50">🌿 Patio</button>
                <button class="room-btn" data-type="pasillo" data-color="#555">🔲 Pasillo</button>
            </div>

            <h3>Propiedades</h3>
            <div class="properties-panel" id="propsPanel">
                <div class="prop-group">
                    <label>Nombre</label>
                    <input type="text" id="propName" placeholder="Nombre de la sala">
                </div>
                <div class="prop-group">
                    <label>Ancho (m)</label>
                    <input type="number" id="propWidth" readonly>
                </div>
                <div class="prop-group">
                    <label>Alto (m)</label>
                    <input type="number" id="propHeight" readonly>
                </div>
                <div class="prop-group">
                    <label>Área (m²)</label>
                    <input type="number" id="propArea" readonly>
                </div>
                <div class="prop-group">
                    <label>Color</label>
                    <input type="color" id="propColor">
                </div>
                <button class="btn danger" style="width: 100%; margin-top: 10px;" onclick="deleteSelectedRoom()">Eliminar Sala</button>
            </div>

            <h3 style="margin-top: 25px;">Guardados</h3>
            <div class="saved-list" id="savedList">
                <div style="color: var(--muted); font-size: 11px; padding: 10px; text-align: center;">
                    Cargue o cree un croquis para verlo aquí
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div class="canvas-area">
            <?php if (isset($save_message)): ?>
                <div class="message success"><?php echo htmlspecialchars($save_message); ?></div>
            <?php endif; ?>
            <?php if (isset($save_error)): ?>
                <div class="message error"><?php echo htmlspecialchars($save_error); ?></div>
            <?php endif; ?>

            <div class="canvas-controls">
                <div class="control-group">
                    <label>Título</label>
                    <input type="text" id="croquisTitulo" placeholder="Mi Departamento">
                </div>
                <div class="control-group">
                    <label>Dirección</label>
                    <input type="text" id="croquisDireccion" placeholder="Av. Principal 123">
                </div>
                <div class="control-group">
                    <label>Ciudad</label>
                    <input type="text" id="croquisCiudad" placeholder="Buenos Aires">
                </div>
                <div class="control-group">
                    <label>Zona</label>
                    <input type="text" id="croquisZona" placeholder="San Isidro">
                </div>
                <button class="btn primary" onclick="saveCroquis()">💾 Guardar</button>
                <button class="btn" onclick="exportPNG()">📥 Exportar PNG</button>
                <button class="btn" onclick="clearCanvas()">🗑️ Limpiar</button>
            </div>

            <div class="svg-container">
                <svg id="canvas" width="800" height="600" viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
                            <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#2a2a2a" stroke-width="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="800" height="600" fill="#1a1a1a"/>
                    <rect width="800" height="600" fill="url(#grid)"/>
                    <g id="roomsLayer"></g>
                    <g id="handlesLayer"></g>
                </svg>
            </div>

            <div class="info-bar">
                <div class="info-item">
                    <span class="info-label">Total m²</span>
                    <span class="info-value" id="totalM2">0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Habitaciones</span>
                    <span class="info-value" id="roomCount">0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Escala</span>
                    <span class="info-value">1 cuadro = 0.5m</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Constants
        const GRID = 20;
        const SCALE = 0.5;
        const CANVAS_W = 800;
        const CANVAS_H = 600;

        // State
        let rooms = [];
        let selected = null;
        let activeRoomType = 'sala';
        let activeColor = '#4a8ff7';
        let drawing = false;
        let drawStart = null;
        let isDragging = false;
        let dragStart = null;
        let dragMode = null;

        const canvas = document.getElementById('canvas');
        const roomsLayer = document.getElementById('roomsLayer');
        const handlesLayer = document.getElementById('handlesLayer');

        // Helper functions
        function snapToGrid(v) {
            return Math.round(v / GRID) * GRID;
        }

        function px2m(px) {
            return (px / GRID) * SCALE;
        }

        function m2px(m) {
            return (m / SCALE) * GRID;
        }

        function calcArea(room) {
            const w = px2m(room.w);
            const h = px2m(room.h);
            return (w * h).toFixed(1);
        }

        function totalM2() {
            return rooms.reduce((sum, room) => sum + parseFloat(calcArea(room)), 0).toFixed(1);
        }

        function updateInfo() {
            document.getElementById('totalM2').textContent = totalM2();
            document.getElementById('roomCount').textContent = rooms.length;
        }

        function generateId() {
            return 'r' + Math.random().toString(36).substr(2, 9);
        }

        // Render
        function render() {
            roomsLayer.innerHTML = '';
            handlesLayer.innerHTML = '';

            rooms.forEach(room => {
                // Draw room
                const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                rect.setAttribute('x', room.x);
                rect.setAttribute('y', room.y);
                rect.setAttribute('width', room.w);
                rect.setAttribute('height', room.h);
                rect.setAttribute('fill', room.color);
                rect.setAttribute('fill-opacity', '0.6');
                rect.setAttribute('stroke', room.color);
                rect.setAttribute('stroke-width', '2');
                rect.setAttribute('rx', '2');
                rect.setAttribute('data-id', room.id);
                rect.style.cursor = 'pointer';

                if (selected && selected.id === room.id) {
                    rect.setAttribute('stroke-dasharray', '5,5');
                    rect.setAttribute('stroke-width', '2');
                    rect.setAttribute('filter', 'drop-shadow(0 0 8px ' + room.color + ')');
                }

                rect.addEventListener('mousedown', (e) => {
                    if (e.button !== 0) return;
                    e.stopPropagation();
                    selectRoom(room.id);
                    isDragging = true;
                    dragStart = {x: e.clientX, y: e.clientY};
                    dragMode = 'move';
                });

                roomsLayer.appendChild(rect);

                // Draw label
                const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                label.setAttribute('x', room.x + room.w / 2);
                label.setAttribute('y', room.y + room.h / 2 - 5);
                label.setAttribute('text-anchor', 'middle');
                label.setAttribute('fill', '#fff');
                label.setAttribute('font-size', '12');
                label.setAttribute('font-weight', 'bold');
                label.setAttribute('pointer-events', 'none');
                label.textContent = room.label;
                roomsLayer.appendChild(label);

                // Draw area
                const area = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                area.setAttribute('x', room.x + room.w / 2);
                area.setAttribute('y', room.y + room.h / 2 + 12);
                area.setAttribute('text-anchor', 'middle');
                area.setAttribute('fill', '#aaa');
                area.setAttribute('font-size', '10');
                area.setAttribute('pointer-events', 'none');
                area.textContent = calcArea(room) + ' m²';
                roomsLayer.appendChild(area);

                // Draw handles if selected
                if (selected && selected.id === room.id) {
                    const handles = [
                        {x: room.x, y: room.y, cx: 'nw-resize'},
                        {x: room.x + room.w, y: room.y, cx: 'ne-resize'},
                        {x: room.x, y: room.y + room.h, cx: 'sw-resize'},
                        {x: room.x + room.w, y: room.y + room.h, cx: 'se-resize'},
                    ];

                    handles.forEach((h, i) => {
                        const handle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                        handle.setAttribute('cx', h.x);
                        handle.setAttribute('cy', h.y);
                        handle.setAttribute('r', '6');
                        handle.setAttribute('fill', '#fff');
                        handle.setAttribute('stroke', room.color);
                        handle.setAttribute('stroke-width', '2');
                        handle.style.cursor = h.cx;

                        handle.addEventListener('mousedown', (e) => {
                            e.stopPropagation();
                            isDragging = true;
                            dragStart = {x: e.clientX, y: e.clientY};
                            dragMode = h.cx;
                        });

                        handlesLayer.appendChild(handle);
                    });
                }
            });

            updateInfo();
        }

        // Rooms
        function selectRoom(id) {
            selected = rooms.find(r => r.id === id) || null;
            updatePropertiesPanel();
            render();
        }

        function updatePropertiesPanel() {
            const panel = document.getElementById('propsPanel');
            if (selected) {
                panel.classList.add('visible');
                document.getElementById('propName').value = selected.label;
                document.getElementById('propWidth').value = px2m(selected.w).toFixed(2);
                document.getElementById('propHeight').value = px2m(selected.h).toFixed(2);
                document.getElementById('propArea').value = calcArea(selected);
                document.getElementById('propColor').value = selected.color;
            } else {
                panel.classList.remove('visible');
            }
        }

        function deleteSelectedRoom() {
            if (!selected) return;
            rooms = rooms.filter(r => r.id !== selected.id);
            selected = null;
            render();
        }

        // Canvas interaction
        canvas.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            if (dragMode) return;

            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            // Check if clicked on existing room
            const clicked = rooms.find(r =>
                x >= r.x && x <= r.x + r.w &&
                y >= r.y && y <= r.y + r.h
            );

            if (!clicked) {
                drawing = true;
                drawStart = {x: snapToGrid(x), y: snapToGrid(y)};
                selected = null;
                updatePropertiesPanel();
            }
        });

        canvas.addEventListener('mousemove', (e) => {
            if (isDragging && dragStart) {
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const dx = x - dragStart.x;
                const dy = y - dragStart.y;

                if (dragMode === 'move') {
                    selected.x = snapToGrid(selected.x + dx);
                    selected.y = snapToGrid(selected.y + dy);
                } else {
                    // Resize
                    const [cx, cy] = dragMode.split('-');
                    if (cx === 'nw' || cx === 'sw') {
                        selected.x = snapToGrid(selected.x + dx);
                        selected.w = snapToGrid(selected.w - dx);
                    } else {
                        selected.w = snapToGrid(selected.w + dx);
                    }
                    if (cy === 'nw' || cy === 'ne') {
                        selected.y = snapToGrid(selected.y + dy);
                        selected.h = snapToGrid(selected.h - dy);
                    } else {
                        selected.h = snapToGrid(selected.h + dy);
                    }
                }

                dragStart = {x: x, y: y};
                render();
            } else if (drawing && drawStart) {
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const w = snapToGrid(x - drawStart.x);
                const h = snapToGrid(y - drawStart.y);

                if (w > 0 && h > 0) {
                    // Preview (render with a temporary room)
                    const preview = {
                        id: 'preview',
                        type: activeRoomType,
                        label: 'Preview',
                        color: activeColor,
                        x: drawStart.x,
                        y: drawStart.y,
                        w: w,
                        h: h
                    };
                    render();
                    const prevRect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    prevRect.setAttribute('x', preview.x);
                    prevRect.setAttribute('y', preview.y);
                    prevRect.setAttribute('width', preview.w);
                    prevRect.setAttribute('height', preview.h);
                    prevRect.setAttribute('fill', activeColor);
                    prevRect.setAttribute('fill-opacity', '0.3');
                    prevRect.setAttribute('stroke', activeColor);
                    prevRect.setAttribute('stroke-width', '1');
                    prevRect.setAttribute('stroke-dasharray', '4,4');
                    roomsLayer.appendChild(prevRect);
                }
            }
        });

        canvas.addEventListener('mouseup', (e) => {
            isDragging = false;
            dragMode = null;

            if (drawing && drawStart) {
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const w = snapToGrid(x - drawStart.x);
                const h = snapToGrid(y - drawStart.y);

                if (w > GRID && h > GRID) {
                    const room = {
                        id: generateId(),
                        type: activeRoomType,
                        label: getDefaultLabel(activeRoomType),
                        color: activeColor,
                        x: drawStart.x,
                        y: drawStart.y,
                        w: w,
                        h: h
                    };
                    rooms.push(room);
                    selected = room;
                    updatePropertiesPanel();
                }

                drawing = false;
                drawStart = null;
                render();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if (selected) {
                    deleteSelectedRoom();
                }
            }
        });

        // Property updates
        document.getElementById('propName').addEventListener('change', (e) => {
            if (selected) {
                selected.label = e.target.value || 'Sala';
                render();
            }
        });

        document.getElementById('propColor').addEventListener('change', (e) => {
            if (selected) {
                selected.color = e.target.value;
                render();
            }
        });

        // Room type selection
        document.querySelectorAll('.room-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.room-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeRoomType = btn.dataset.type;
                activeColor = btn.dataset.color;
            });
        });

        function getDefaultLabel(type) {
            const labels = {
                'sala': 'Sala',
                'dormitorio': 'Dormitorio',
                'cocina': 'Cocina',
                'bano': 'Baño',
                'comedor': 'Comedor',
                'garage': 'Cochera',
                'patio': 'Patio',
                'pasillo': 'Pasillo'
            };
            return labels[type] || 'Sala';
        }

        // Save/Load
        async function saveCroquis() {
            const titulo = document.getElementById('croquisTitulo').value;
            const direccion = document.getElementById('croquisDireccion').value;
            const ciudad = document.getElementById('croquisCiudad').value;
            const zona = document.getElementById('croquisZona').value;

            if (!titulo) {
                alert('Ingrese un título');
                return;
            }

            const floorData = {
                version: 1,
                grid_px: GRID,
                scale_m_per_grid: SCALE,
                canvas_w: CANVAS_W,
                canvas_h: CANVAS_H,
                rooms: rooms
            };

            const totalM2 = totalM2();
            const svgPreview = canvas.outerHTML;

            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('title', titulo);
            formData.append('address', direccion);
            formData.append('city', ciudad);
            formData.append('zone', zona);
            formData.append('floor_data', JSON.stringify(floorData));
            formData.append('total_m2', totalM2);
            formData.append('svg_preview', svgPreview);

            try {
                const response = await fetch('api/croquis.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    alert('Croquis guardado correctamente');
                    loadSavedList();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                alert('Error al guardar: ' + e.message);
            }
        }

        async function loadSavedList() {
            try {
                const response = await fetch('api/croquis.php?action=list');
                const items = await response.json();
                const list = document.getElementById('savedList');

                if (!items || items.length === 0) {
                    list.innerHTML = '<div style="color: var(--muted); font-size: 11px; padding: 10px; text-align: center;">No hay croquis guardados</div>';
                    return;
                }

                list.innerHTML = items.map(item => `
                    <div class="saved-item" onclick="loadCroquis(${item.id})">
                        <div class="saved-item-title">${escapeHtml(item.title)}</div>
                        <div class="saved-item-delete" onclick="deleteCroquis(${item.id}, event)">×</div>
                    </div>
                `).join('');
            } catch (e) {
                console.error('Error loading list:', e);
            }
        }

        async function loadCroquis(id) {
            try {
                const response = await fetch('api/croquis.php?action=load&id=' + id);
                const croquis = await response.json();

                if (!croquis || !croquis.floor_data) {
                    alert('Error cargando croquis');
                    return;
                }

                document.getElementById('croquisTitulo').value = croquis.title || '';
                document.getElementById('croquisDireccion').value = croquis.address || '';
                document.getElementById('croquisCiudad').value = croquis.city || '';
                document.getElementById('croquisZona').value = croquis.zone || '';

                rooms = croquis.floor_data.rooms || [];
                selected = null;
                updatePropertiesPanel();
                render();
            } catch (e) {
                alert('Error al cargar: ' + e.message);
            }
        }

        async function deleteCroquis(id, event) {
            event.stopPropagation();
            if (!confirm('¿Está seguro de que desea eliminar este croquis?')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                const response = await fetch('api/croquis.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    loadSavedList();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                alert('Error al eliminar: ' + e.message);
            }
        }

        function clearCanvas() {
            if (!confirm('¿Limpiar todo el canvas?')) return;
            rooms = [];
            selected = null;
            updatePropertiesPanel();
            render();
        }

        function exportPNG() {
            const svgData = canvas.outerHTML;
            const canvas2 = document.createElement('canvas');
            canvas2.width = 800;
            canvas2.height = 600;
            const ctx = canvas2.getContext('2d');
            const img = new Image();
            const svg = new Blob([svgData], {type: 'image/svg+xml'});
            const url = URL.createObjectURL(svg);

            img.onload = () => {
                ctx.fillStyle = '#1a1a1a';
                ctx.fillRect(0, 0, 800, 600);
                ctx.drawImage(img, 0, 0);

                const link = document.createElement('a');
                link.href = canvas2.toDataURL('image/png');
                link.download = 'croquis.png';
                link.click();
                URL.revokeObjectURL(url);
            };
            img.src = url;
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Initialize
        loadSavedList();
        render();
    </script>
</body>
</html>
