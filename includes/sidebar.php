<?php
// ==========================================
// INICIALIZACIÓN
// ==========================================
if (!isset($base_assets)) {
    $base_assets = (basename(dirname($_SERVER['PHP_SELF'])) === 'modules') ? '../assets/' : 'assets/';
}
$donde_estoy = basename(dirname($_SERVER['PHP_SELF']));
$archivo_actual = basename($_SERVER['PHP_SELF']);

$es_modulo = ($donde_estoy === 'modules');
$prefijo = $es_modulo ? '../' : '';

$nombre_visual = ucfirst($_SESSION['usuario'] ?? 'Invitado');
$id_rol = (int)($_SESSION['id_rol'] ?? 0);
$roles_map = [1 => 'Administrador', 2 => 'Operador de Carga', 3 => 'Operador de Ventas'];
$rol_visual = $roles_map[$id_rol] ?? 'Sin rol';

// Roles de usuario
$es_admin = Security::esAdmin();
$es_op_carga = $id_rol === 2;
$es_op_ventas = $id_rol === 3;

// Alertas críticas para la campana (solo admin)
$db = Database::getInstance();
$notif_vencidos = 0;
$notif_proximos = 0;
$notif_bajos = 0;
if ($es_admin) {
    $notif_vencidos = (int)($db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE cantidad_restante > 0 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= CURDATE()")['n'] ?? 0);
    $notif_proximos = (int)($db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE cantidad_restante > 0 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento > CURDATE() AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")['n'] ?? 0);
    $notif_bajos = (int)($db->fetchOne("SELECT COUNT(*) as n FROM productos WHERE status = 'Activo' AND stock_actual <= stock_minimo")['n'] ?? 0);
}
$notif_total = $notif_vencidos + $notif_proximos + $notif_bajos;

// Detección de página activa
function es_activo(string $pagina, string $modulo = ''): string
{
    global $archivo_actual;
    if (!empty($modulo)) {
        return $archivo_actual === $pagina ? 'active' : '';
    }
    return ($archivo_actual === $pagina) ? 'active' : '';
}
?>

<!-- SIDEBAR HTML -->
<aside class="sidebar" id="sidebar">
    <!-- Encabezado / Marca -->
    <div class="sidebar-header">
        <a href="<?php echo $prefijo; ?>index.php" class="brand-link">
            <span class="brand-jv">JV</span><span class="brand-num">3000</span><span class="brand-ca"> C.A.</span>
        </a>
    </div>

    <!-- Menú de navegación -->
    <nav class="sidebar-nav">
        <!-- Panel de Inicio -->
        <div class="nav-item nav-dashboard <?php echo ($archivo_actual === 'index.php') ? 'active' : ''; ?>">
            <a href="<?php echo $prefijo; ?>index.php" class="nav-link">
                <i class="bi bi-house-door"></i>
                <span>Panel de Inicio</span>
            </a>
        </div>

        <!-- --- Statistics (Admin / Sales) --- -->
        <!-- Estadísticas -->
        <?php if ($es_admin || $es_op_ventas): ?>
            <div class="nav-item nav-estadisticas <?php echo ($archivo_actual === 'estadisticas.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>modules/estadisticas.php" class="nav-link">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Estadísticas</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- --- Sales / Outputs (Admin / Sales) --- -->
        <!-- Ventas / Salidas -->
        <?php if ($es_admin || $es_op_ventas): ?>
            <div class="nav-item nav-salidas <?php echo ($archivo_actual === 'salidas.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>modules/salidas.php" class="nav-link">
                    <i class="bi bi-receipt"></i>
                    <span>Ventas / Salidas</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- --- Inventory (All operators) --- -->
        <!-- Inventario -->
        <?php if ($es_admin || $es_op_carga || $es_op_ventas): ?>
            <div class="nav-item nav-inventario <?php echo ($archivo_actual === 'productos.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>modules/productos.php" class="nav-link">
                    <i class="bi bi-box-seam"></i>
                    <span>Inventario</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- --- Purchases (Admin / Load) --- -->
        <!-- Compras -->
        <?php if ($es_admin || $es_op_carga): ?>
            <div class="nav-item nav-entradas <?php echo ($archivo_actual === 'compras.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>modules/compras.php" class="nav-link">
                    <i class="bi bi-truck"></i>
                    <span>Compras</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- --- Admin-only menu items --- -->
        <!-- --- Suppliers --- -->
        <!-- Proveedores -->
        <?php if ($es_admin): ?>
            <div class="nav-item nav-clientes <?php echo ($archivo_actual === 'proveedores.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>modules/proveedores.php" class="nav-link">
                    <i class="bi bi-building"></i>
                    <span>Proveedores</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- --- Categories --- -->
        <!-- Categorías -->
        <?php if ($es_admin): ?>
            <div class="nav-item nav-inventario <?php echo ($archivo_actual === 'categorias.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>modules/categorias.php" class="nav-link">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <span>Categorías</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- --- Users --- -->
        <!-- Usuarios -->
        <?php if ($es_admin): ?>
            <div class="nav-item nav-usuarios <?php echo ($archivo_actual === 'usuarios.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>usuarios.php" class="nav-link">
                    <i class="bi bi-people-fill"></i>
                    <span>Usuarios</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- --- History --- -->
        <!-- Historial -->
        <?php if ($es_admin): ?>
            <div class="nav-item nav-historial <?php echo ($archivo_actual === 'historial.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>modules/historial.php" class="nav-link">
                    <i class="bi bi-clock-history"></i>
                    <span>Historial</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- --- Print (Admin / Sales) --- -->
        <!-- Imprimir -->
        <?php if ($es_admin || $es_op_ventas): ?>
            <div class="nav-item nav-reportes <?php echo ($archivo_actual === 'reporte_inventario.php') ? 'active' : ''; ?>">
                <a href="#" class="nav-link" onclick="imprimirReporte(event)">
                    <i class="bi bi-printer"></i>
                    <span>Imprimir</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Mi Perfil -->
        <div class="nav-item nav-perfil <?php echo ($archivo_actual === 'perfil.php') ? 'active' : ''; ?>">
            <a href="<?php echo $prefijo; ?>perfil.php" class="nav-link">
                <i class="bi bi-person-gear"></i>
                <span>Mi Perfil</span>
            </a>
        </div>
    </nav>

    <!-- Pie / Info de usuario -->
    <div class="sidebar-footer">
        <?php if ($es_admin): ?>
        <div class="notif-wrap">
            <button type="button" class="notif-btn" id="notifBtn" onclick="toggleNotif(event)" title="Alertas críticas">
                <i class="bi bi-bell"></i>
                <?php if ($notif_total > 0): ?><span class="notif-badge"><?php echo min($notif_total, 99); ?></span><?php endif; ?>
            </button>
            <div class="notif-panel" id="notifPanel">
                <div class="notif-head">ALERTAS CRÍTICAS</div>
                <?php if ($notif_total === 0): ?>
                    <div class="notif-empty"><i class="bi bi-check-circle"></i> Sin alertas críticas</div>
                <?php else: ?>
                    <a class="notif-item notif-danger" href="<?php echo $prefijo; ?>modules/productos.php">
                        <i class="bi bi-x-octagon"></i>
                        <div><strong>Productos vencidos</strong><small><?php echo $notif_vencidos; ?> lote(s) caducado(s)</small></div>
                    </a>
                    <a class="notif-item notif-warn" href="<?php echo $prefijo; ?>modules/productos.php">
                        <i class="bi bi-clock-history"></i>
                        <div><strong>Próximos a vencer</strong><small><?php echo $notif_proximos; ?> lote(s) en ≤ 30 días</small></div>
                    </a>
                    <a class="notif-item notif-info" href="<?php echo $prefijo; ?>modules/productos.php">
                        <i class="bi bi-exclamation-triangle"></i>
                        <div><strong>Stock bajo / agotado</strong><small><?php echo $notif_bajos; ?> producto(s) bajo mínimo</small></div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="user-info">
            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($nombre_visual); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($rol_visual); ?></span>
            </div>
            <a href="<?php echo $prefijo; ?>logout.php" class="btn-logout" title="Cerrar Sesión">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>
</aside>

<!-- BOTÓN TOGGLE Y BACKDROP -->
<!-- Botón de Toggle Manual -->
<button class="sidebar-toggle-btn" id="sidebarToggle" title="Abrir/Cerrar Menú">
    <i class="bi bi-list"></i>
</button>

<!-- Backdrop para móvil -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- ESTILOS DEL SIDEBAR -->
<link rel="stylesheet" href="<?php echo $base_assets; ?>css/sidebar.css">

<script src="<?php echo $base_assets; ?>js/sweetalert2.all.min.js"></script>
<script>
    window.JV_CONFIG = window.JV_CONFIG || {};
    window.JV_CONFIG.prefijo = <?php echo json_encode($prefijo); ?>;
</script>
<script src="<?php echo $base_assets; ?>js/sidebar.js"></script>