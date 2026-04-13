<?php
/**
 * Plugin: BIM Materiales ML
 * Registra hooks para inyectar botones y funcionalidad BIM en el panel admin.
 */
if (!function_exists('add_action')) return; // Solo se carga desde el loader

// Botón en la barra del admin principal
add_action('admin_toolbar_buttons', function() {
    echo '<a href="admin_bim.php" class="btn" title="Mapa de calor de costos de construcción + precios de materiales">🏗 BIM</a>';
});

// Ítem de menú en admin.php
add_action('admin_menu_items', function() {
    echo '<a href="admin_bim.php" class="menu-item">🏗 BIM Materiales</a>';
});

// Registrar que este plugin provee el panel BIM completo
add_filter('admin_plugin_pages', function(array $pages) {
    $pages[] = [
        'slug'  => 'bim',
        'file'  => __DIR__ . '/../../admin_bim.php',
        'label' => '🏗 BIM',
    ];
    return $pages;
});
