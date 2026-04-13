<?php
/**
 * TasadorIA — includes/admin_topnav.php
 * Barra de navegación principal compartida por todos los paneles admin.
 *
 * Uso: require __DIR__.'/../includes/admin_topnav.php';
 * Antes de incluir, definir $currentPanel con: 'admin' | 'bim' | 'market' | 'plugins'
 */
$_nav_current = $currentPanel ?? 'admin';

// Stats rápidos para badges (si hay $pdo disponible)
$_nav_leadsHoy = 0;
$_nav_hasTmp   = false;
if (!empty($pdo)) {
    try {
        $_nav_leadsHoy = (int)$pdo->query("SELECT COUNT(*) FROM tasacion_leads WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    } catch (\Throwable $e) {}
}
?>
<style>
.ta-topnav {
  background: #141414;
  border-bottom: 2px solid #c9a84c;
  display: flex;
  align-items: center;
  gap: 0;
  padding: 0;
  height: 46px;
  flex-shrink: 0;
  position: relative;
  z-index: 100;
}
.ta-topnav-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 18px;
  border-right: 1px solid #2a2a2a;
  height: 100%;
  text-decoration: none;
  flex-shrink: 0;
}
.ta-topnav-brand span:first-child {
  font-family: Georgia, serif;
  font-size: 15px;
  font-weight: 700;
  color: #c9a84c;
  letter-spacing: .3px;
}
.ta-topnav-brand span:last-child {
  font-size: 9px;
  color: #555;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.ta-nav-links {
  display: flex;
  align-items: stretch;
  height: 100%;
  flex: 1;
}
.ta-nav-link {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 16px;
  font-size: 12px;
  font-weight: 500;
  color: #777;
  text-decoration: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color .15s, border-color .15s;
  white-space: nowrap;
  position: relative;
  font-family: system-ui, -apple-system, sans-serif;
}
.ta-nav-link:hover {
  color: #ccc;
  background: rgba(255,255,255,.03);
  text-decoration: none;
}
.ta-nav-link.active {
  color: #c9a84c;
  border-bottom-color: #c9a84c;
  background: rgba(201,168,76,.06);
}
.ta-nav-badge {
  background: #c9a84c;
  color: #000;
  font-size: 9px;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 8px;
  line-height: 1.4;
}
.ta-nav-right {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 16px;
  margin-left: auto;
  flex-shrink: 0;
}
.ta-nav-right a {
  font-size: 11px;
  color: #555;
  text-decoration: none;
  transition: color .15s;
}
.ta-nav-right a:hover { color: #999; }
.ta-nav-sep {
  width: 1px;
  height: 20px;
  background: #2a2a2a;
  flex-shrink: 0;
}
</style>

<nav class="ta-topnav">
  <a class="ta-topnav-brand" href="admin.php">
    <span>TasadorIA</span>
    <span>admin</span>
  </a>

  <div class="ta-nav-links">
    <a href="admin.php"
       class="ta-nav-link <?= $_nav_current==='admin' ? 'active' : '' ?>">
      📊 <span>Dashboard</span>
    </a>

    <a href="admin.php#zonas"
       class="ta-nav-link <?= $_nav_current==='zonas' ? 'active' : '' ?>"
       onclick="if(window.location.pathname.endsWith('admin.php')){event.preventDefault();showTab('zonas',this)}">
      🗺 <span>Zonas</span>
    </a>

    <a href="admin.php#leads"
       class="ta-nav-link <?= ($_nav_current==='leads'||$_nav_leadsHoy>0&&$_nav_current!=='admin') ? '' : '' ?>"
       onclick="if(window.location.pathname.endsWith('admin.php')){event.preventDefault();showTab('leads',this)}">
      👥 <span>Leads</span>
      <?php if ($_nav_leadsHoy > 0): ?>
        <span class="ta-nav-badge"><?= $_nav_leadsHoy ?></span>
      <?php endif; ?>
    </a>

    <a href="admin.php#tasaciones"
       class="ta-nav-link"
       onclick="if(window.location.pathname.endsWith('admin.php')){event.preventDefault();showTab('tasaciones',this)}">
      📋 <span>Tasaciones</span>
    </a>

    <a href="admin.php#buscador"
       class="ta-nav-link"
       onclick="if(window.location.pathname.endsWith('admin.php')){event.preventDefault();showTab('buscador',this)}">
      🔍 <span>Buscador</span>
    </a>

    <div class="ta-nav-sep"></div>

    <a href="admin_bim.php"
       class="ta-nav-link <?= $_nav_current==='bim' ? 'active' : '' ?>">
      🏗 <span>BIM</span>
    </a>

    <a href="admin_market.php"
       class="ta-nav-link <?= $_nav_current==='market' ? 'active' : '' ?>">
      📈 <span>Mercado</span>
    </a>

    <a href="admin_cierres.php"
       class="ta-nav-link <?= $_nav_current==='cierres' ? 'active' : '' ?>">
      🤝 <span>Cierres</span>
    </a>

    <a href="admin_croquis.php"
       class="ta-nav-link <?= $_nav_current==='croquis' ? 'active' : '' ?>">
      📐 <span>Croquis</span>
    </a>

    <a href="admin_plugins.php"
       class="ta-nav-link <?= $_nav_current==='plugins' ? 'active' : '' ?>">
      🔌 <span>Plugins</span>
    </a>

    <a href="admin.php#config"
       class="ta-nav-link <?= $_nav_current==='config' ? 'active' : '' ?>"
       onclick="if(window.location.pathname.endsWith('admin.php')){event.preventDefault();showTab('config',this)}">
      ⚙️ <span>Config</span>
    </a>
  </div>

  <div class="ta-nav-right">
    <a href="index.php" target="_blank">← Tasador</a>
    <div class="ta-nav-sep"></div>
    <a href="admin.php?logout=1">Salir</a>
  </div>
</nav>
