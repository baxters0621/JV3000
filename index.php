<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/init.php';

$db = Database::getInstance();
$nombre_user = $_SESSION['usuario'] ?? 'Usuario';
$rol_user_id = $_SESSION['id_rol'] ?? 0;
$rol_data = $db->fetchOne("SELECT nombre_rol FROM roles WHERE id_rol = ?", [$rol_user_id]);
$rol_user = $rol_data ? $rol_data['nombre_rol'] : 'Sin Rol';

$esAdmin = ($rol_user_id === 1);
$esOpVentas = ($rol_user_id === 3);
$esOpCarga = ($rol_user_id === 2);

$fecha_hoy = date('d/m/Y');

// ==========================================
// CONSULTAS UNIFICADAS DEL DASHBOARD
// ==========================================
function obtenerDatosDashboard($db): array
{
    $datos = [];

    $vd = $db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) as total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.id_tipo_mov = 1 AND s.status = 'Activa' AND s.fecha_salida >= CURRENT_DATE");
    $datos['ventas_dia'] = number_format($vd['total'], 2);

    $vi = $db->fetchOne("SELECT COALESCE(SUM(stock_actual * precio_costo), 0) as valor FROM productos WHERE status = 'Activo'");
    $datos['valor_inventario'] = number_format($vi['valor'], 2);

    $pc = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE stock_actual <= stock_minimo AND status = 'Activo'");
    $datos['productos_criticos'] = (int)$pc['total'];

    $g1 = $db->fetchAll("SELECT DATE(s.fecha_salida) as fecha, SUM(ds.cantidad * ds.precio_venta) as total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY DATE(s.fecha_salida) ORDER BY fecha");
    $datos['grafico_ventas'] = array_map(fn($r) => ['fecha' => $r['fecha'], 'total' => (float)$r['total']], $g1);

    $g2 = $db->fetchAll("SELECT p.id_producto, p.nombre_producto, SUM(ds.cantidad) as cantidad FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY ds.id_producto ORDER BY cantidad DESC LIMIT 5");
    $paleta_idx = ['#EA580C', '#16A34A', '#2563EB', '#DC2626', '#7C3AED', '#D97706', '#F59E0B', '#0D9488', '#0F1A2E', '#DB2777'];
    $datos['grafico_productos'] = array_map(fn($r) => ['producto' => $r['nombre_producto'], 'cantidad' => (int)$r['cantidad'], 'color' => $paleta_idx[$r['id_producto'] % count($paleta_idx)]], $g2);

    $fac = $db->fetchAll("SELECT s.cliente, MAX(s.fecha_salida) as fecha_salida, SUM(ds.cantidad * ds.precio_venta) as total, s.nro_factura_manual FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY s.id_salida, s.nro_factura_manual ORDER BY MAX(s.fecha_salida) DESC LIMIT 5");
    $datos['ultimas_facturas'] = array_map(fn($r) => ['cliente' => $r['cliente'] ?: 'S/N', 'fecha' => date('d/m/Y', strtotime($r['fecha_salida'])), 'total' => number_format($r['total'], 2)], $fac);

    $venc_now = $db->fetchAll("SELECT id_producto, nombre_producto, fecha_vencimiento, stock_actual FROM productos WHERE fecha_vencimiento <= CURRENT_DATE() AND status = 'Activo' ORDER BY fecha_vencimiento ASC LIMIT 8");
    $venc_pending = $db->fetchAll("SELECT id_producto, nombre_producto, fecha_vencimiento, stock_actual FROM productos WHERE fecha_vencimiento <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND fecha_vencimiento > CURRENT_DATE() AND status = 'Activo' ORDER BY fecha_vencimiento ASC LIMIT 5");

    $datos['productos_vencer'] = array_map(fn($r) => [
        'id' => $r['id_producto'],
        'nombre' => $r['nombre_producto'],
        'fecha' => date('d/m/Y', strtotime($r['fecha_vencimiento'])),
        'dias' => floor((strtotime($r['fecha_vencimiento']) - time()) / 86400),
        'stock' => (int)$r['stock_actual']
    ], $venc_now);

    $datos['productos_pronto'] = array_map(fn($r) => [
        'id' => $r['id_producto'],
        'nombre' => $r['nombre_producto'],
        'fecha' => date('d/m/Y', strtotime($r['fecha_vencimiento'])),
        'dias' => floor((strtotime($r['fecha_vencimiento']) - time()) / 86400),
        'stock' => (int)$r['stock_actual']
    ], $venc_pending);

    $datos['tabla_vencer'] = array_slice(array_merge($datos['productos_vencer'], $datos['productos_pronto']), 0, 5);

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
        echo json_encode(['success' => false, 'error' => 'acceso_denegado']); exit;
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
$productos_criticos = $datos['productos_criticos'];
$grafico_ventas = $datos['grafico_ventas'];
$grafico_productos = $datos['grafico_productos'];
$ultimas_facturas = $datos['ultimas_facturas'];
$tabla_criticos = $datos['tabla_criticos'];
$tabla_vencer = $datos['tabla_vencer'];
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
    <?php include 'includes/diseno.php'; ?>
    <script src="assets/js/chart.umd.min.js"></script>

        <link rel="stylesheet" href="assets/dashboard/index.css">
</head>

<?php
// ==========================================
// LAYOUT DEL DASHBOARD
// ========================================== 
?>

<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">

            <!-- Header Panel de Inicio -->
            <div class="dashboard-header">
                <div class="dashboard-title">
                    <div class="dashboard-logo">JV<span style="color:var(--jv-orange);font-weight:300;">3000</span></div>
                    <div>
                        <div class="subtitle">Centro de Control | <?php echo htmlspecialchars($nombre_user); ?></div>
                    </div>
                </div>
                <div class="dashboard-info">
                    <button class="btn-refresh" onclick="actualizarDashboard(); this.classList.add('refreshing'); setTimeout(()=>this.classList.remove('refreshing'), 600);" title="Actualizar datos">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <div class="dashboard-user">
                        <div class="dashboard-user-avatar"><i class="bi bi-person-fill"></i></div>
                        <div>
                            <div class="dashboard-user-name"><?php echo htmlspecialchars($nombre_user); ?></div>
                            <div class="dashboard-user-role"><?php echo strtoupper($rol_user); ?></div>
                        </div>
                    </div>
                    <div class="dashboard-date">
                        <i class="bi bi-calendar3 me-2"></i><?php echo $fecha_hoy; ?>
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="section-title">
                <i class="bi bi-speedometer2"></i> Indicadores Clave
            </div>
            <div class="kpi-grid">
                <div class="kpi-card kpi-verde">
                    <div class="kpi-icon"><i class="bi bi-currency-dollar"></i></div>
                    <div class="kpi-label">Ventas Totales</div>
                    <div class="kpi-value" id="kpi-ventas-dia">$<?php echo $ventas_dia; ?></div>
                </div>
                <div class="kpi-card kpi-amarillo">
                    <div class="kpi-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="kpi-label">Valor Inventario</div>
                    <div class="kpi-value" id="kpi-valor-inv">$<?php echo $valor_inventario; ?></div>
                </div>
                <div class="kpi-card kpi-rojo">
                    <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="kpi-label">Productos Críticos</div>
                    <div class="kpi-value" id="kpi-criticos"><?php echo $productos_criticos; ?></div>
                </div>
            </div>

            <!-- Accesos Rápidos -->
            <div class="section-title">
                <i class="bi bi-lightning-charge"></i> Accesos Rápidos
            </div>
            <div class="shortcuts-grid">
                <?php if ($esAdmin || $esOpVentas): ?>
                    <a href="modules/salidas.php" class="shortcut-btn shortcut-facturar">
                        <i class="bi bi-plus-circle"></i>
                        <span>+ NUEVA VENTA</span>
                    </a>
                <?php endif; ?>
                <?php if ($esAdmin || $esOpCarga): ?>
                    <a href="modules/compras.php" class="shortcut-btn shortcut-entrada">
                        <i class="bi bi-arrow-down-circle-fill"></i>
                        <span>+ NUEVA ENTRADA</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Gráficos -->
            <div class="section-title">
                <i class="bi bi-pie-chart"></i> Análisis de Tendencias
            </div>
            <div class="charts-grid">
                <div class="chart-card">
                    <h5 style="color:var(--jv-text-primary);font-size:0.95rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--jv-border);">Ventas - Últimos 7 Días</h5>
                    <div class="chart-container">
                        <canvas id="chartVentas"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h5 style="color:var(--jv-text-primary);font-size:0.95rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--jv-border);">Productos Más Vendidos</h5>
                    <div class="chart-container">
                        <canvas id="chartProductos"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tablas de Actividad -->
            <div class="section-title">
                <i class="bi bi-clock-history"></i> Actividad Reciente
            </div>
            <div class="tables-grid">
                <div class="table-card">
                    <h5 style="color:var(--jv-text-primary);font-size:0.95rem;font-weight:700;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--jv-border);">Últimas 5 Notas de Entrega</h5>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-facturas">
                            <?php foreach ($ultimas_facturas as $f): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($f['cliente']); ?></td>
                                    <td style="font-weight:600;"><?php echo $f['fecha']; ?></td>
                                    <td class="text-jv-success fw-bold" style="text-align:right;">$<?php echo $f['total']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-card">
                    <h5 class="mb-3 fw-bold" style="color:var(--jv-text-primary);">Productos Críticos</h5>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Stock</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-criticos">
                            <?php foreach ($tabla_criticos as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['producto']); ?></td>
                                    <td><?php echo $c['stock']; ?></td>
                                    <td>
                                        <span class="stock-badge <?php echo $c['estado']; ?>">
                                            <?php echo $c['estado'] === 'critico' ? 'Crítico' : 'Bajo'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="table-card">
                <h5 class="mb-3 fw-bold" style="color:var(--jv-text-primary);">Próximos a Vencer (15 días)</h5>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Vence</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-vencer">
                        <?php if (!empty($tabla_vencer)): ?>
                            <?php foreach ($tabla_vencer as $v): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($v['nombre']); ?></td>
                                    <td style="<?php echo $v['dias'] < 0 ? 'color:#DC2626;font-weight:700;' : ($v['dias'] <= 7 ? 'color:#EA580C;font-weight:700;' : 'color:#D97706;'); ?>">
                                        <?php echo $v['fecha']; ?> (<?php echo $v['dias'] < 0 ? 'VENCIDO' : $v['dias'] . 'd'; ?>)
                                    </td>
                                    <td style="text-align:center;"><?php echo $v['stock']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align:center;color:#64748b;padding:20px;">Sin productos próximos a vencer</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php // ==========================================
    // JAVASCRIPT
    // ========================================== 
    ?>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
    window.JV_CONFIG = { c0: <?php echo json_encode($grafico_ventas); ?>, c1: <?php echo json_encode($grafico_productos); ?> };
</script>
    <script src="assets/dashboard/index.js"></script>
</body>

</html>