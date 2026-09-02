<?php
// ==========================================
// INICIALIZACIÓN
// ==========================================
if (!isset($base_assets)) {
    $base_assets = BASE_PATH . 'assets/';
}
$archivo_actual = basename($_SERVER['PHP_SELF']);
$ruta_mvc = trim($_GET['url'] ?? '', '/');
$mvc_activa = function (string $prefix) use ($ruta_mvc) {
    return $ruta_mvc === $prefix || strpos($ruta_mvc, $prefix . '/') === 0;
};

$prefijo = BASE_PATH;

$nombre_visual = ucfirst($_SESSION['usuario'] ?? 'Invitado');
$id_rol = (int)($_SESSION['id_rol'] ?? 0);
$roles_map = [1 => 'Administrador', 2 => 'Operador de Carga', 3 => 'Operador de Ventas'];
$rol_visual = $roles_map[$id_rol] ?? 'Sin rol';

// Roles de usuario
$es_admin = Security::esAdmin();
$es_op_carga = $id_rol === 2;
$es_op_ventas = $id_rol === 3;
?>

<!-- SIDEBAR v2 — Limpio, ordenado por flujo del negocio -->
<aside class="sidebar" id="sidebar">
    <!-- Encabezado / Marca -->
    <div class="sidebar-header">
        <a href="<?php echo $prefijo; ?>dashboard/index.php" class="brand-link">
            <img class="brand-mark" src="<?php echo $base_assets; ?>img/logo-mark.svg?v=1" alt="JV3000">
        </a>
        <span class="brand-tag">Gestión de Inventario, Compras y Ventas</span>
    </div>

    <!-- Menú de navegación — orden por flujo del negocio:
         Panel → Inventario → Compras → Ventas → Análisis →
         Administración (admin) → Guía → Mi Perfil -->
    <nav class="sidebar-nav">
        <!-- Panel de Inicio -->
        <div class="nav-item nav-ram-panel <?php echo ($archivo_actual === 'index.php' && $ruta_mvc === '') ? 'active' : ''; ?>">
            <a href="<?php echo $prefijo; ?>dashboard/index.php" class="nav-link">
                <i class="bi bi-house-door"></i>
                <span>Panel de Inicio</span>
            </a>
        </div>

        <?php
        // Visibilidad por módulo según el rol de la sesión
        $ver_inventario = $es_admin || $es_op_carga || $es_op_ventas;
        $ver_abasto     = $es_admin || $es_op_carga;
        $ver_ventas     = $es_admin || $es_op_ventas;
        $ver_estadisticas = $es_admin || $es_op_ventas;
        $ver_usuarios   = $es_admin;

        // Detecta rama activa para auto-expandir
        $rama_inventario_activa = $mvc_activa('productos');
        $rama_compras_activa = $mvc_activa('compras');
        $rama_salidas_activa = $mvc_activa('salidas');
        $rama_analisis_activo = $mvc_activa('estadisticas') || $mvc_activa('reporte_inventario');
        $rama_admin_activo = $mvc_activa('historial') || $mvc_activa('usuarios');
        ?>

        <!-- ═══ INVENTARIO (consulta: los tres roles) ═══ -->
        <?php if ($ver_inventario): ?>
        <div class="nav-group nav-ram-inv <?php echo $rama_inventario_activa ? 'open' : ''; ?>">
            <button type="button" class="nav-group-toggle" aria-expanded="<?php echo $rama_inventario_activa ? 'true' : 'false'; ?>">
                <i class="bi bi-box-seam"></i>
                <span>Inventario</span>
                <i class="bi bi-chevron-down nav-chevron"></i>
            </button>
            <div class="nav-group-items">
                <div class="nav-item <?php echo $mvc_activa('productos') ? 'active' : ''; ?>">
                    <a href="<?php echo $prefijo; ?>index.php?url=productos" class="nav-link">
                        <i class="bi bi-boxes"></i>
                        <span>Productos · Categorías</span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ COMPRAS (admin + carga) ═══ -->
        <?php if ($ver_abasto): ?>
        <div class="nav-group nav-ram-comp <?php echo $rama_compras_activa ? 'open' : ''; ?>">
            <button type="button" class="nav-group-toggle" aria-expanded="<?php echo $rama_compras_activa ? 'true' : 'false'; ?>">
                <i class="bi bi-bag-check"></i>
                <span>Compras</span>
                <i class="bi bi-chevron-down nav-chevron"></i>
            </button>
            <div class="nav-group-items">
                <div class="nav-item <?php echo $mvc_activa('compras') ? 'active' : ''; ?>">
                    <a href="<?php echo $prefijo; ?>index.php?url=compras" class="nav-link">
                        <i class="bi bi-bag"></i>
                        <span>Compras · Proveedores</span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ VENTAS (admin + ventas) ═══ -->
        <?php if ($ver_ventas): ?>
        <div class="nav-group nav-ram-vent <?php echo $rama_salidas_activa ? 'open' : ''; ?>">
            <button type="button" class="nav-group-toggle" aria-expanded="<?php echo $rama_salidas_activa ? 'true' : 'false'; ?>">
                <i class="bi bi-credit-card"></i>
                <span>Ventas</span>
                <i class="bi bi-chevron-down nav-chevron"></i>
            </button>
            <div class="nav-group-items">
                <div class="nav-item <?php echo $mvc_activa('salidas') ? 'active' : ''; ?>">
                    <a href="<?php echo $prefijo; ?>index.php?url=salidas" class="nav-link">
                        <i class="bi bi-receipt"></i>
                        <span>Ventas · Salidas</span>
                    </a>
                </div>
                <div class="nav-item <?php echo $mvc_activa('devoluciones') ? 'active' : ''; ?>">
                    <a href="<?php echo $prefijo; ?>index.php?url=devoluciones" class="nav-link">
                        <i class="bi bi-arrow-return-left"></i>
                        <span>Devoluciones</span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ ANÁLISIS (admin + ventas) ═══ -->
        <?php if ($ver_estadisticas): ?>
        <div class="nav-group nav-ram-anal <?php echo $rama_analisis_activo ? 'open' : ''; ?>">
            <button type="button" class="nav-group-toggle" aria-expanded="<?php echo $rama_analisis_activo ? 'true' : 'false'; ?>">
                <i class="bi bi-graph-up"></i>
                <span>Análisis</span>
                <i class="bi bi-chevron-down nav-chevron"></i>
            </button>
            <div class="nav-group-items">
                <div class="nav-item <?php echo $mvc_activa('estadisticas') ? 'active' : ''; ?>">
                    <a href="<?php echo $prefijo; ?>index.php?url=estadisticas" class="nav-link">
                        <i class="bi bi-bar-chart-line"></i>
                        <span>Estadísticas</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link" onclick="imprimirReporte(event)">
                        <i class="bi bi-printer"></i>
                        <span>Imprimir Reporte</span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ ADMINISTRACIÓN (solo admin) ═══ -->
        <?php if ($ver_usuarios): ?>
        <div class="nav-group nav-ram-admin <?php echo $rama_admin_activo ? 'open' : ''; ?>">
            <button type="button" class="nav-group-toggle" aria-expanded="<?php echo $rama_admin_activo ? 'true' : 'false'; ?>">
                <i class="bi bi-gear"></i>
                <span>Administración</span>
                <i class="bi bi-chevron-down nav-chevron"></i>
            </button>
            <div class="nav-group-items">
                <div class="nav-item <?php echo $mvc_activa('usuarios') ? 'active' : ''; ?>">
                    <a href="<?php echo $prefijo; ?>index.php?url=usuarios" class="nav-link">
                        <i class="bi bi-people"></i>
                        <span>Usuarios</span>
                    </a>
                </div>
                <div class="nav-item <?php echo $mvc_activa('historial') ? 'active' : ''; ?>">
                    <a href="<?php echo $prefijo; ?>index.php?url=historial" class="nav-link">
                        <i class="bi bi-clock-history"></i>
                        <span>Historial</span>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Guía de Uso (todos los roles) -->
        <div class="nav-item nav-ram-guia <?php echo $mvc_activa('manual') ? 'active' : ''; ?>">
            <a href="<?php echo $prefijo; ?>index.php?url=manual" class="nav-link">
                <i class="bi bi-book"></i>
                <span>Guía de Uso</span>
            </a>
        </div>

        <!-- Mi Perfil -->
        <div class="nav-item nav-ram-perfil <?php echo $mvc_activa('perfil') ? 'active' : ''; ?>">
            <a href="<?php echo $prefijo; ?>index.php?url=perfil" class="nav-link">
                <i class="bi bi-person"></i>
                <span>Mi Perfil</span>
            </a>
        </div>
    </nav>

    <!-- Pie / Info de usuario -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($nombre_visual); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($rol_visual); ?></span>
            </div>
            <a href="<?php echo $prefijo; ?>login/logout.php" class="btn-logout" title="Cerrar Sesión">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>
</aside>

<!-- BOTÓN TOGGLE Y BACKDROP -->
<button class="sidebar-toggle-btn" id="sidebarToggle" title="Abrir/Cerrar Menú">
    <i class="bi bi-list"></i>
</button>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- ESTILOS DEL SIDEBAR -->
<link rel="stylesheet" href="<?php echo $base_assets; ?>css/sidebar.css?v=17">

<script src="<?php echo $base_assets; ?>js/sweetalert2.all.min.js"></script>
<script>
    window.JV_BASE = <?php echo json_encode($prefijo); ?>;
    window.JV_CONFIG = window.JV_CONFIG || {};
    window.JV_CONFIG.prefijo = <?php echo json_encode($prefijo); ?>;
</script>
<script src="<?php echo $base_assets; ?>js/sidebar.js?v=5"></script>