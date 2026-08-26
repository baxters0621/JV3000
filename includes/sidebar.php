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
        <a href="<?php echo $prefijo; ?>dashboard/index.php" class="brand-link">
            <img class="brand-mark" src="<?php echo $base_assets; ?>img/logo-mark.svg?v=1" alt="JV3000">
        </a>
        <span class="brand-tag">Gestión de Inventario, Compras y Ventas</span>
    </div>

    <!-- Menú de navegación -->
    <!-- Orden operativo: Configuración -> Abastecimiento -> Ventas -> Análisis -> Control -->
    <nav class="sidebar-nav">
        <!-- Panel de Inicio -->
        <div class="nav-item nav-dashboard <?php echo ($archivo_actual === 'index.php' && $ruta_mvc === '') ? 'active' : ''; ?>">
            <a href="<?php echo $prefijo; ?>dashboard/index.php" class="nav-link">
                <i class="bi bi-house-door"></i>
                <span>Panel de Inicio</span>
            </a>
        </div>

        <?php
        // Visibilidad por módulo según el rol de la sesión
        // Nota: Proveedores se gestiona desde Compras y Categorías desde
        // Inventario (pop-ups); ya no tienen entrada propia en el menú.
        $ver_usuarios   = $es_admin;                                    // Usuarios (Control)
        $ver_abasto     = $es_admin || $es_op_carga;                    // Solicitudes, Compras, Recepción
        $ver_inventario = $es_admin || $es_op_carga || $es_op_ventas;   // Inventario (consulta)
        $ver_ventas     = $es_admin || $es_op_ventas;                   // Ventas/Salidas
        $ver_estadisticas = $es_admin || $es_op_ventas;
        ?>

        <!-- ═══ OPERACIONES (ciclo diario) ═══ -->
        <div class="nav-section-title">Operaciones</div>

        <?php if ($ver_abasto): ?>
            <!-- Paso 1 del ciclo: detectar qué falta -->
            <div class="nav-item nav-solicitudes <?php echo ($archivo_actual === 'solicitudes_compra.php' || $mvc_activa('solicitudes')) ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>index.php?url=solicitudes" class="nav-link">
                    <i class="bi bi-cart-check"></i>
                    <span>Solicitudes de Reposici&oacute;n</span>
                </a>
            </div>

            <!-- Paso 2: comprar al proveedor -->
            <div class="nav-item nav-entradas <?php echo $mvc_activa('compras') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>index.php?url=compras" class="nav-link">
                    <i class="bi bi-truck"></i>
                    <span>Compras</span>
                </a>
            </div>

            <!-- Paso 3: recibir la mercancía (crea lotes y sube stock) -->
            <div class="nav-item nav-recepcion <?php echo $mvc_activa('recepcion') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>index.php?url=recepcion" class="nav-link">
                    <i class="bi bi-box-arrow-in-down"></i>
                    <span>Recepci&oacute;n</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($ver_inventario): ?>
            <!-- Paso 4: verificar el inventario resultante (consulta para todos) -->
            <div class="nav-item nav-inventario <?php echo $mvc_activa('productos') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>index.php?url=productos" class="nav-link">
                    <i class="bi bi-box-seam"></i>
                    <span>Inventario</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($ver_ventas): ?>
            <!-- Paso 5: vender (descuenta por FEFO y genera nota de entrega) -->
            <div class="nav-item nav-salidas <?php echo $mvc_activa('salidas') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>index.php?url=salidas" class="nav-link">
                    <i class="bi bi-receipt"></i>
                    <span>Ventas / Salidas</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- ═══ ANÁLISIS Y REPORTES ═══ -->
        <div class="nav-section-title">An&aacute;lisis</div>

        <?php if ($ver_estadisticas): ?>
            <div class="nav-item nav-estadisticas <?php echo $mvc_activa('estadisticas') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>index.php?url=estadisticas" class="nav-link">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Estad&iacute;sticas</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Reporte de inventario imprimible (los tres roles) -->
        <div class="nav-item nav-reportes <?php echo ($archivo_actual === 'reporte_inventario.php') ? 'active' : ''; ?>">
            <a href="#" class="nav-link" onclick="imprimirReporte(event)">
                <i class="bi bi-printer"></i>
                <span>Imprimir</span>
            </a>
        </div>

        <!-- ═══ CONTROL (solo admin) ═══ -->
        <?php if ($es_admin): ?>
            <div class="nav-section-title">Control</div>

            <div class="nav-item nav-historial <?php echo $mvc_activa('historial') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>index.php?url=historial" class="nav-link">
                    <i class="bi bi-clock-history"></i>
                    <span>Historial</span>
                </a>
            </div>

            <!-- Usuarios: cuentas del personal que usa el sistema -->
            <div class="nav-item nav-usuarios <?php echo ($archivo_actual === 'usuarios.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefijo; ?>dashboard/usuarios.php" class="nav-link">
                    <i class="bi bi-people-fill"></i>
                    <span>Usuarios</span>
                </a>
            </div>
        <?php endif; ?>

        <!-- Mi Perfil (siempre al final) -->
        <div class="nav-item nav-perfil <?php echo ($archivo_actual === 'perfil.php') ? 'active' : ''; ?>">
            <a href="<?php echo $prefijo; ?>dashboard/perfil.php" class="nav-link">
                <i class="bi bi-person-gear"></i>
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
<!-- Botón de Toggle Manual -->
<button class="sidebar-toggle-btn" id="sidebarToggle" title="Abrir/Cerrar Menú">
    <i class="bi bi-list"></i>
</button>

<!-- Backdrop para móvil -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- ESTILOS DEL SIDEBAR -->
<link rel="stylesheet" href="<?php echo $base_assets; ?>css/sidebar.css?v=11">

<script src="<?php echo $base_assets; ?>js/sweetalert2.all.min.js"></script>
<script>
    window.JV_BASE = <?php echo json_encode($prefijo); ?>;
    window.JV_CONFIG = window.JV_CONFIG || {};
    window.JV_CONFIG.prefijo = <?php echo json_encode($prefijo); ?>;
</script>
<script src="<?php echo $base_assets; ?>js/sidebar.js?v=4"></script>