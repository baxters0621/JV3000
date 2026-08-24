<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
$nombre_user = $_SESSION['usuario'] ?? 'Usuario';
$rol_user_id = $_SESSION['id_rol'] ?? 0;
$rol_data = $db->fetchOne("SELECT nombre_rol FROM roles WHERE id_rol = ?", [$rol_user_id]);
$rol_user = $rol_data ? $rol_data['nombre_rol'] : 'Sin Rol';

$esAdmin = ($rol_user_id === 1);
$esOpVentas = ($rol_user_id === 3);
$esOpCarga = ($rol_user_id === 2);

$fecha_hoy = date('d/m/Y');

$alertas = jv_alertas_por_rol($rol_user_id);

// ==========================================
// CONSULTAS UNIFICADAS DEL DASHBOARD
// ==========================================
function obtenerDatosDashboard(Database $db): array
{
    $datos = [];

    // El KPI de ventas del panel debe reflejar la última venta registrada, no solo
    // las ventas del día actual; de ese modo la tarjeta y la tabla muestran un
    // mismo criterio de "reciente" incluso cuando la última nota no pertenece a hoy.
    $vd = $db->fetchOne("SELECT COALESCE(ds.cantidad * ds.precio_venta, 0) as total
        FROM salidas s
        JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
        WHERE s.id_tipo_mov = 1 AND s.status = 'Activa'
        ORDER BY s.fecha_salida DESC, s.id_salida DESC
        LIMIT 1");
    $datos['ventas_dia'] = number_format((float)($vd['total'] ?? 0), 2);

    $vi = $db->fetchOne("SELECT COALESCE(SUM(CASE WHEN lotes.valor_lotes IS NULL THEN p.stock_actual * p.precio_costo ELSE lotes.valor_lotes END), 0) AS valor
        FROM productos p
        LEFT JOIN (
            SELECT id_producto, SUM(cantidad_restante * precio_costo) AS valor_lotes
            FROM lotes
            WHERE cantidad_restante > 0
            GROUP BY id_producto
        ) lotes ON lotes.id_producto = p.id_producto
        WHERE p.status = 'Activo'");
    $datos['valor_inventario'] = number_format((float)($vi['valor'] ?? 0), 2);

    $fac = $db->fetchAll("SELECT s.cliente, MAX(s.fecha_salida) as fecha_salida, SUM(ds.cantidad * ds.precio_venta) as total, s.nro_factura_manual FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY s.id_salida, s.nro_factura_manual ORDER BY MAX(s.fecha_salida) DESC LIMIT 5");
    $datos['ultimas_facturas'] = array_map(fn($r) => ['cliente' => $r['cliente'] ?: 'S/N', 'fecha' => date('d/m/Y', strtotime($r['fecha_salida'])), 'total' => number_format($r['total'], 2)], $fac);

    $compras = $db->fetchAll("SELECT c.nro_factura, c.fecha_compra, c.total, COALESCE(pr.nombre_empresa, 'S/P') as proveedor FROM compras c LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor WHERE c.status = 'Activa' ORDER BY c.fecha_compra DESC LIMIT 5");
    $datos['tabla_compras'] = array_map(fn($r) => [
        'proveedor' => $r['proveedor'],
        'fecha' => date('d/m/Y', strtotime($r['fecha_compra'])),
        'total' => number_format($r['total'], 2)
    ], $compras);

    $crit = $db->fetchAll("SELECT nombre_producto, stock_actual, stock_minimo FROM productos WHERE (stock_actual <= stock_minimo OR stock_actual = 0) AND status = 'Activo' ORDER BY stock_actual ASC LIMIT 5");
    $datos['tabla_criticos'] = array_map(fn($r) => [
        'producto' => $r['nombre_producto'],
        'stock' => (int)$r['stock_actual'],
        'estado' => $r['stock_actual'] <= 0 ? 'critico' : 'bajo'
    ], $crit);

    return $datos;
}

// ==========================================
// ENDPOINT AJAX DEL DASHBOARD
// ==========================================
if (isset($_GET['ajax_dashboard'])) {
    header('Content-Type: application/json');
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        echo json_encode(['success' => false, 'error' => 'acceso_denegado']);
        exit;
    }
    echo json_encode(['success' => true] + obtenerDatosDashboard($db));
    exit();
}

// ==========================================
// CONSULTAS INICIALES DEL DASHBOARD
// ==========================================
$datos = obtenerDatosDashboard($db);
$ventas_dia = $datos['ventas_dia'];
$valor_inventario = $datos['valor_inventario'];
$ultimas_facturas = $datos['ultimas_facturas'];
$tabla_criticos = $datos['tabla_criticos'];
$tabla_compras = $datos['tabla_compras'];
?>
<!DOCTYPE html>
<html lang="es">

<?php // ==========================================
// HEAD Y ESTILOS HTML
// ========================================== 
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Inicio | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>

    <link rel="stylesheet" href="../assets/dashboard/index.css?v=24">
</head>

<?php
// ==========================================
// LAYOUT DEL DASHBOARD
// ========================================== 
?>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">

            <!-- ENCABEZADO DE MARCA -->
            <header class="dash-hero">
                <div class="dash-brand">
                    <img class="dash-logo-badge" src="../assets/img/logo-mark.svg?v=1" alt="JV3000">
                    <div class="dash-brand-meta">
                        <div class="dash-brand-title">JV<span class="num">3000</span> <span class="dash-brand-ca">C.A.</span></div>
                        <p class="dash-brand-tag">Centro de Gestión de Inventario, Compras y Ventas</p>
                    </div>
                </div>
                <div class="dash-hero-info">
                    <div class="dash-user">
                        <div class="dash-user-avatar"><i class="bi bi-person-fill"></i></div>
                        <div>
                            <div class="dash-user-name"><?php echo htmlspecialchars($nombre_user); ?></div>
                            <div class="dash-user-role"><?php echo strtoupper($rol_user); ?></div>
                        </div>
                    </div>
                    <div class="dash-date">
                        <i class="bi bi-calendar3 me-2"></i><?php echo $fecha_hoy; ?>
                    </div>
                    <div class="dash-bell-wrap">
                        <button type="button" class="dash-bell" id="dashBellBtn" onclick="toggleAlertas(event)" title="Alertas críticas de stock">
                            <i class="bi bi-bell"></i>
                            <?php if ($alertas['total'] > 0): ?><span class="dash-bell-badge" id="dashBellBadge"><?php echo min($alertas['total'], 99); ?></span><?php endif; ?>
                        </button>
                        <div class="dash-bell-panel" id="dashBellPanel">
                            <div class="dash-bell-head">ALERTAS CRÍTICAS</div>
                            <?php if ($alertas['total'] === 0): ?>
                                <div class="dash-bell-empty"><i class="bi bi-check-circle"></i> Sin alertas críticas</div>
                            <?php else: ?>
                                <?php
                                $secciones = [
                                    'vencidos' => ['titulo' => 'VENCIDOS', 'clase' => 'ven', 'alerta' => 'vencidos', 'count' => $alertas['counts']['vencidos'], 'items' => $alertas['vencidos']],
                                    'proximos' => ['titulo' => 'PRÓXIMOS (1-7 DÍAS)', 'clase' => 'prox', 'alerta' => 'proximos', 'count' => $alertas['counts']['proximos'], 'items' => $alertas['proximos']],
                                    'prontos' => ['titulo' => 'PRONTO (8-30 DÍAS)', 'clase' => 'pronto', 'alerta' => 'prontos', 'count' => $alertas['counts']['prontos'], 'items' => $alertas['prontos']],
                                ];
                                if ($rol_user_id !== 3) {
                                    $secciones['bajos'] = ['titulo' => 'STOCK BAJO', 'clase' => 'bajo', 'alerta' => 'bajos', 'count' => $alertas['counts']['bajos'], 'items' => $alertas['bajos']];
                                }
                                foreach ($secciones as $clave => $sec):
                                    if ($sec['count'] <= 0) {
                                        continue;
                                    }
                                ?>
                                    <div class="dash-bell-sec dash-bell-<?php echo $sec['clase']; ?>">
                                        <div class="dash-bell-sec-titulo">
                                            <span><?php echo $sec['titulo']; ?> (<?php echo $sec['count']; ?>)</span>
                                            <a href="<?php echo BASE_PATH; ?>index.php?url=productos&alerta=<?php echo $sec['alerta']; ?>" class="dash-bell-ver">Ver todos</a>
                                        </div>
                                        <?php foreach ($sec['items'] as $it): ?>
                                            <a class="dash-bell-item" href="<?php echo BASE_PATH; ?>index.php?url=productos&producto=<?php echo (int)$it['id']; ?>">
                                                <i class="bi bi-<?php echo $clave === 'bajos' ? 'exclamation-triangle' : ($clave === 'proximos' ? 'clock-history' : ($clave === 'prontos' ? 'calendar3' : 'x-octagon')); ?>"></i>
                                                <span class="dash-bell-item-nombre"><?php echo htmlspecialchars($it['nombre']); ?></span>
                                                <span class="dash-bell-item-meta">
                                                    <?php if ($clave === 'bajos'): ?>
                                                        <?php echo (int)$it['stock']; ?> / mín <?php echo (int)$it['minimo']; ?>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($it['fecha']); ?>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ACCESOS RÁPIDOS -->
            <section class="dash-section">
                <div class="quick-grid">
                    <?php if ($esAdmin || $esOpVentas): ?>
                        <a href="<?php echo BASE_PATH; ?>index.php?url=salidas" class="quick-btn quick-venta">
                            <span class="quick-icon"><i class="bi bi-cart-fill"></i></span>
                            <span class="quick-text">
                                <span class="quick-label">Nueva Venta</span>
                                <span class="quick-sub">Registrar una nota de Entrega</span>
                            </span>
                            <span class="quick-arrow"><i class="bi bi-arrow-right"></i></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($esAdmin || $esOpCarga): ?>
                        <a href="<?php echo BASE_PATH; ?>index.php?url=compras" class="quick-btn quick-entrada">
                            <span class="quick-icon"><i class="bi bi-box-arrow-in-down"></i></span>
                            <span class="quick-text">
                                <span class="quick-label">Nueva Entrada</span>
                                <span class="quick-sub">Registrar una compra</span>
                            </span>
                            <span class="quick-arrow"><i class="bi bi-arrow-right"></i></span>
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <!-- INDICADORES CLAVE -->
            <section class="dash-section">
                <h2 class="sec-title"><i class="bi bi-speedometer2"></i> Indicadores Clave</h2>
                <div class="kpi-grid">
                    <div class="kpi-card kpi-card-verde">
                        <div class="kpi-icon kpi-icon-verde"><i class="bi bi-currency-dollar"></i></div>
                        <div class="kpi-label">Última Venta</div>
                        <div class="kpi-value" id="kpi-ventas-dia">$<?php echo $ventas_dia; ?></div>
                    </div>
                    <div class="kpi-card kpi-card-teal">
                        <div class="kpi-icon kpi-icon-teal"><i class="bi bi-box-seam"></i></div>
                        <div class="kpi-label">Valor del Inventario</div>
                        <div class="kpi-value" id="kpi-valor-inv">$<?php echo $valor_inventario; ?></div>
                    </div>
                </div>
            </section>

            <!-- ACTIVIDAD RECIENTE -->
            <section class="dash-section">
                <h2 class="sec-title"><i class="bi bi-clock-history"></i> Actividad Reciente</h2>
                <div class="tables-grid">
                    <?php if ($esAdmin || $esOpVentas): ?>
                        <div class="table-card table-card-ventas">
                            <h3>Últimas Notas de Entrega</h3>
                            <p class="card-desc">Ventas registradas más recientes.</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-facturas">
                                    <?php if (!empty($ultimas_facturas)): ?>
                                        <?php foreach ($ultimas_facturas as $f): ?>
                                            <tr>
                                                <td class="producto-tooltip" data-nombre="<?php echo htmlspecialchars($f['cliente'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($f['cliente']); ?></td>
                                                <td><?php echo $f['fecha']; ?></td>
                                                <td class="monto">$<?php echo $f['total']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="vacio">Sin ventas registradas</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($esAdmin || $esOpCarga): ?>
                        <div class="table-card table-card-criticos">
                            <h3>Productos Críticos</h3>
                            <p class="card-desc">Stock agotado o bajo el mínimo: reponer o reparar lo antes posible.</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Stock</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-criticos">
                                    <?php if (!empty($tabla_criticos)): ?>
                                        <?php foreach ($tabla_criticos as $c): ?>
                                            <tr>
                                                <td class="producto-tooltip" data-nombre="<?php echo htmlspecialchars($c['producto'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($c['producto']); ?></td>
                                                <td><?php echo $c['stock']; ?></td>
                                                <td><span class="stock-badge <?php echo $c['estado']; ?>"><?php echo $c['estado'] === 'critico' ? 'Crítico' : 'Bajo'; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="vacio">Sin productos críticos</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-card table-card-compras">
                            <h3>Últimas Compras</h3>
                            <p class="card-desc">Total de factura, IVA incluido.</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Proveedor</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-compras">
                                    <?php if (!empty($tabla_compras)): ?>
                                        <?php foreach ($tabla_compras as $co): ?>
                                            <tr>
                                                <td class="producto-tooltip" data-nombre="<?php echo htmlspecialchars($co['proveedor'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($co['proveedor']); ?></td>
                                                <td><?php echo $co['fecha']; ?></td>
                                                <td class="monto">$<?php echo $co['total']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="vacio">Sin compras registradas</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </div>

    <?php // ==========================================
    // JAVASCRIPT
    // ========================================== 
    ?>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/dashboard/index.js?v=11"></script>
</body>

</html>