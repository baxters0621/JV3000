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
$rol_visual = ucfirst($_SESSION['rol'] ?? 'Sin rol');

// Roles de usuario
$es_admin = Security::esAdmin();
$es_op_carga = ($_SESSION['rol'] ?? '') === 'Operador de Carga';
$es_op_ventas = ($_SESSION['rol'] ?? '') === 'Operador de Ventas';

// Detección de página activa
function es_activo(string $pagina, string $modulo = ''): string {
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
        <div class="nav-item nav-facturacion <?php echo ($archivo_actual === 'salidas.php') ? 'active' : ''; ?>">
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

        <!-- --- Audit --- -->
        <!-- Auditoría -->
        <?php if ($es_admin): ?>
        <div class="nav-item nav-auditoria <?php echo ($archivo_actual === 'auditoria.php') ? 'active' : ''; ?>">
            <a href="<?php echo $prefijo; ?>modules/auditoria.php" class="nav-link">
                <i class="bi bi-shield-check"></i>
                <span>Auditoría</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- --- Print (Admin / Sales) --- -->
        <!-- Imprimir -->
        <?php if ($es_admin || $es_op_ventas): ?>
        <div class="nav-item nav-reportes">
            <a href="#" class="nav-link" onclick="imprimirReporte(event)">
                <i class="bi bi-printer"></i>
                <span>Imprimir</span>
            </a>
        </div>
        <?php endif; ?>
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
<style>
/* =============================================
   SIDEBAR - JV3000
   ============================================= */

/* --- Main Sidebar --- */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: 260px;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(25px);
    border-right: 1px solid rgba(56, 189, 248, 0.15);
    display: flex;
    flex-direction: column;
    z-index: 1000;
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 4px 0 40px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}

.sidebar.open {
    transform: translateX(0);
}

/* Backdrop para móvil */
.sidebar-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(2,6,23,0.6);
    z-index: 999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
}
.sidebar-backdrop.visible {
    opacity: 1;
    pointer-events: auto;
}

/* --- Header / Brand --- */
/* Header */
.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.brand-link {
    text-decoration: none;
    font-family: 'Orbitron', sans-serif;
    font-size: 1.6rem;
    font-weight: 900;
    letter-spacing: -1px;
}

.brand-jv {
    color: #38bdf8;
}

.brand-num {
    color: #fff;
}

.brand-ca {
    color: #64748b;
    font-size: .65rem;
    letter-spacing: 1px;
    margin-left: 2px;
}

/* --- Navigation --- */
/* Navegación */
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 12px 0;
}

/* --- Module Colors --- */
/* Colores por módulo */
.nav-dashboard { --mod-color: #94a3b8; }
.nav-estadisticas { --mod-color: #14b8a6; }
.nav-inventario { --mod-color: #38bdf8; }
.nav-entradas { --mod-color: #22c55e; }
.nav-salidas { --mod-color: #ef4444; }
.nav-clientes { --mod-color: #a855f7; }
.nav-usuarios { --mod-color: #ea580c; }
.nav-reportes { --mod-color: #f59e0b; }

/* Aplicar colores a los grupos */
.nav-group.nav-dashboard { --mod-color: #94a3b8; }
.nav-group.nav-inventario { --mod-color: #38bdf8; }
.nav-group.nav-entradas { --mod-color: #22c55e; }
.nav-group.nav-salidas { --mod-color: #ef4444; }
.nav-group.nav-clientes { --mod-color: #a855f7; }
.nav-group.nav-usuarios { --mod-color: #ea580c; }
.nav-group.nav-reportes { --mod-color: #f59e0b; }
.nav-item.nav-auditoria { --mod-color: #8b5cf6; }
.nav-item.nav-usuarios { --mod-color: #ea580c; }
.nav-item.nav-estadisticas { --mod-color: #14b8a6; }

/* --- Nav Items & Links --- */
.nav-item {
    margin: 6px 12px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    border-radius: 18px;
    transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    font-weight: 700;
    font-size: 0.95rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    position: relative;
    overflow: hidden;
}

.nav-link::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 100%;
    background: var(--mod-color);
    opacity: 0;
    transition: 0.3s;
    border-radius: 0 4px 4px 0;
}

.nav-link:hover {
    transform: scale(1.02) translateY(-2px);
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--mod-color);
    box-shadow: 0 8px 25px -8px var(--mod-color);
}

.nav-link:hover::before {
    opacity: 1;
}

.nav-link i:first-child {
    font-size: 1.3rem;
    width: 26px;
    text-align: center;
    position: relative;
    z-index: 1;
    color: var(--mod-color);
}

.nav-item.active > .nav-link,
.nav-link.active {
    background: linear-gradient(135deg, var(--mod-color) 0%, rgba(0,0,0,0.3) 100%);
    color: #fff;
    border: 1px solid var(--mod-color);
    box-shadow: 0 8px 25px -5px var(--mod-color);
}

.nav-item.active > .nav-link i:first-child,
.nav-link.active i:first-child {
    color: #fff;
}

/* --- Expandable Groups / Submenu --- */
/* Grupo expansible */
.nav-group > .nav-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 12px 16px;
    background: rgba(30, 41, 59, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 600;
    font-size: 0.85rem;
    text-align: left;
    position: relative;
}

.nav-group > .nav-toggle::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 100%;
    background: var(--mod-color);
    opacity: 0;
    transition: 0.3s;
    border-radius: 0 4px 4px 0;
}

.nav-group > .nav-toggle:hover {
    color: #fff;
    background: rgba(56, 189, 248, 0.1);
}

.nav-group > .nav-toggle:hover::before {
    opacity: 1;
}

.nav-toggle-content {
    display: flex;
    align-items: center;
    gap: 14px;
    position: relative;
    z-index: 1;
}

.nav-toggle-content i:first-child {
    font-size: 1.3rem;
    width: 26px;
    text-align: center;
    color: var(--mod-color);
}

.nav-toggle .arrow {
    font-size: 0.75rem;
    transition: transform 0.3s ease;
    position: relative;
    z-index: 1;
    color: var(--mod-color);
}

.nav-group.open > .nav-toggle {
    background: rgba(56, 189, 248, 0.15);
    border-color: rgba(56, 189, 248, 0.3);
    color: #fff;
}

.nav-group.open > .nav-toggle .arrow {
    transform: rotate(90deg);
    color: #fff;
}

.nav-group.open > .nav-toggle .nav-toggle-content i:first-child {
    color: #fff;
}

/* Submenú - más compacto y sutil */
.nav-submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.25s ease;
    padding-left: 8px;
    margin-top: 4px;
}

.nav-group.open > .nav-submenu {
    max-height: 400px;
}

.nav-submenu a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px 8px 28px;
    color: rgba(255, 255, 255, 0.5);
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.8rem;
    transition: all 0.2s ease;
    background: transparent;
    border: none;
    margin-bottom: 2px;
}

.nav-submenu a i {
    font-size: 0.85rem;
    width: 16px;
    color: inherit;
}

.nav-submenu a:hover {
    color: rgba(255, 255, 255, 0.8);
    background: rgba(56, 189, 248, 0.08);
}

.nav-submenu a.active {
    color: var(--mod-color, #38bdf8);
    background: rgba(56, 189, 248, 0.1);
    font-weight: 500;
}

/* --- Footer / User Info / Logout --- */
/* Footer */
.sidebar-footer {
    padding: 12px 14px;
    border-top: 1px solid rgba(56, 189, 248, 0.1);
    background: rgba(8, 12, 28, 0.95);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    background: rgba(56, 189, 248, 0.08);
    border-radius: 10px;
    border: 1px solid rgba(56, 189, 248, 0.1);
}

.user-avatar {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: linear-gradient(135deg, #38bdf8 0%, #a855f7 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.95rem;
    line-height: 1;
    flex-shrink: 0;
}

.user-details {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
    flex: 1;
}

.user-name {
    color: #fff;
    font-weight: 600;
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    color: rgba(56, 189, 248, 0.8);
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.btn-logout {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ef4444;
    text-decoration: none;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.btn-logout:hover {
    background: #ef4444;
    color: #fff;
}

.btn-logout i {
    font-size: 0.85rem;
}

.btn-logout:hover {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    color: #fff;
}

.btn-logout i {
    font-size: 0.9rem;
}

/* --- Main Content Wrapper --- */
/* Main content wrapper */
.main-wrapper {
    margin-left: 0;
    transition: margin-left 0.2s ease-out;
    min-height: 100vh;
}

.main-wrapper.sidebar-open {
    margin-left: 260px;
}

/* --- Toggle Button --- */
/* Botón de Toggle Manual */
.sidebar-toggle-btn {
    position: fixed;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 64px;
    background: linear-gradient(180deg, rgba(56, 189, 248, 0.2) 0%, rgba(8, 12, 28, 0.95) 100%);
    backdrop-filter: blur(25px);
    border: 1px solid rgba(56, 189, 248, 0.25);
    border-left: none;
    border-radius: 0 20px 20px 0;
    color: #38bdf8;
    cursor: pointer;
    z-index: 1001;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 6px 0 25px rgba(0, 0, 0, 0.4);
}

.sidebar-toggle-btn:hover {
    background: linear-gradient(180deg, rgba(56, 189, 248, 0.4) 0%, rgba(56, 189, 248, 0.2) 100%);
    color: #fff;
    box-shadow: 0 6px 30px rgba(56, 189, 248, 0.5);
}

.sidebar-toggle-btn i {
    font-size: 1.5rem;
    transition: transform 0.3s ease;
}

body.sidebar-open .sidebar-toggle-btn {
    left: 260px;
}

body.sidebar-open .sidebar-toggle-btn i {
    transform: rotate(180deg);
}
</style>

<script src="<?php echo $base_assets; ?>js/sweetalert2.all.min.js"></script>
<script>
// JAVASCRIPT: TOGGLE DEL SIDEBAR
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggle');
const backdrop = document.getElementById('sidebarBackdrop');
const mainWrapper = document.getElementById('mainWrapper');
let sidebarOpen = false;

function abrirSidebar() {
    sidebarOpen = true;
    sidebar.classList.add('open');
    document.body.classList.add('sidebar-open');
    if (backdrop) backdrop.classList.add('visible');
    if (mainWrapper) mainWrapper.style.marginLeft = '260px';
}
function cerrarSidebar() {
    sidebarOpen = false;
    sidebar.classList.remove('open');
    document.body.classList.remove('sidebar-open');
    if (backdrop) backdrop.classList.remove('visible');
    if (mainWrapper) mainWrapper.style.marginLeft = '0';
}

// Evento del botón toggle
// Botón de toggle
toggleBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebarOpen ? cerrarSidebar() : abrirSidebar();
});

// Cerrar al hacer clic fuera (móvil) o en backdrop
if (backdrop) backdrop.addEventListener('click', cerrarSidebar);

// Cerrar sidebar en móvil al hacer clic en un enlace
document.querySelectorAll('.sidebar .nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) cerrarSidebar();
    });
});

// Imprimir reporte
function imprimirReporte(e) {
    e.preventDefault();
    window.open('<?php echo $prefijo; ?>modules/reporte_inventario.php', '_blank');
}

</script>