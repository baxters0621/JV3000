<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
$rol_est = (int)($_SESSION['id_rol'] ?? 0);
if ($rol_est !== 1 && $rol_est !== 3) {
    header("Location: ../index.php?error=acceso_denegado"); exit();
}

// ==========================================
// OBTENER KPI
// ==========================================
$ventas_7d = $db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) as total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa'")['total'];

$compras_7d = $db->fetchOne("SELECT COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) as total FROM compras c JOIN detalle_compras dc ON c.id_compra = dc.id_compra WHERE c.fecha_compra >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND c.status = 'Activa'")['total'];

$margen_7d = $db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * (ds.precio_venta - p.precio_costo)), 0) as margen FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa'")['margen'];

$transacciones_7d = $db->fetchOne("SELECT COUNT(DISTINCT nro_factura_manual) as total FROM salidas WHERE fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND nro_factura_manual IS NOT NULL AND id_tipo_mov = 1 AND status = 'Activa'")['total'];

$productos_activos = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE status = 'Activo'")['total'];

// ==========================================
// OBTENER DATOS PARA GRÁFICOS
// ==========================================
// Ventas vs Compras (7 días)
$fechas = [];
$ventas_data = [];
$compras_data = [];

$ventas_7d_raw = $db->fetchAll("SELECT DATE(s.fecha_salida) as fecha, COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) as total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY DATE(s.fecha_salida)");
$compras_7d_raw = $db->fetchAll("SELECT DATE(c.fecha_compra) as fecha, COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) as total FROM compras c JOIN detalle_compras dc ON c.id_compra = dc.id_compra WHERE c.fecha_compra >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND c.status = 'Activa' GROUP BY DATE(c.fecha_compra)");

$ventas_idx = [];
foreach ($ventas_7d_raw as $r) { $ventas_idx[$r['fecha']] = $r['total']; }
$compras_idx = [];
foreach ($compras_7d_raw as $r) { $compras_idx[$r['fecha']] = $r['total']; }

for ($i = 6; $i >= 0; $i--) {
    $f = date('Y-m-d', strtotime("-$i days"));
    $fechas[] = date('d/m', strtotime($f));
    $ventas_data[] = $ventas_idx[$f] ?? 0;
    $compras_data[] = $compras_idx[$f] ?? 0;
}

// Costo de venta (7 días)
$costo_vendido_7d = $db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * p.precio_costo), 0) as total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa'")['total'];

$porc_margen = ($ventas_7d > 0) ? round(($margen_7d / $ventas_7d) * 100, 1) : 0;

// Top 5 productos por ganancia (7 días)
$top_ganancia = $db->fetchAll("SELECT p.id_producto, p.nombre_producto, p.sku, SUM(ds.cantidad) as unidades, SUM(ds.cantidad * ds.precio_venta) as ingresos, SUM(ds.cantidad * p.precio_costo) as costo, SUM(ds.cantidad * (ds.precio_venta - p.precio_costo)) as ganancia FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY p.id_producto ORDER BY ganancia DESC LIMIT 5");

// Top 5 más vendidos (30 días)
$top_prod_nombres = [];
$top_prod_cant = [];
$top_prod_colores = [];

$paleta = ['#EA580C','#2563EB','#6F42C1','#FD7E14','#16A34A','#DC2626','#D97706','#20C997','#0F1A2E','#E83E8C'];

$res_top = $db->fetchAll("SELECT p.id_producto, p.nombre_producto, COALESCE(SUM(ds.cantidad), 0) as total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY ds.id_producto ORDER BY total DESC LIMIT 5");
foreach ($res_top as $row) {
    $top_prod_nombres[] = $row['nombre_producto'];
    $top_prod_cant[] = (int)$row['total'];
    $top_prod_colores[] = $paleta[$row['id_producto'] % count($paleta)];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/diseno.php'; ?>
    <title>Estadísticas | JV3000 C.A.</title>
    <script src="../assets/js/chart.umd.min.js"></script>
    <!-- ESTILOS -->
        <link rel="stylesheet" href="../assets/modules/estadisticas/estadisticas.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
    <div class="pagina-estadisticas">
    <div class="container-fluid px-4 py-4">

        <!-- ENCABEZADO -->
        <div class="d-flex align-items-center gap-4 mb-4">
            <div class="stats-header-icon">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div>
                <h1 class="font-brand mb-1" style="font-size:1.8rem;letter-spacing:-1px; color: var(--jv-text-primary);">ESTADÍSTICAS</h1>
                <p class="text-secondary small fw-bold text-uppercase mb-0">Análisis de Rendimiento | JV3000 C.A.</p>
            </div>
        </div>

        <!-- WIDGETS KPI -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(234,88,12,0.12);color:var(--jv-orange);">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <div>
                        <div class="widget-label">VENTAS (7d) <i class="bi bi-info-circle" style="cursor:help;font-size:.6rem;opacity:.5;" title="Dinero total recibido por ventas en los últimos 7 días"></i></div>
                        <div class="widget-value" id="kpi-ventas">$<?php echo number_format($ventas_7d, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(13,110,253,0.12);color:var(--jv-info);">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <div class="widget-label">COMPRAS (7d) <i class="bi bi-info-circle" style="cursor:help;font-size:.6rem;opacity:.5;" title="Dinero total gastado en comprar productos los últimos 7 días"></i></div>
                        <div class="widget-value" id="kpi-compras">$<?php echo number_format($compras_7d, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(25,135,84,0.12);color:var(--jv-success);">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <div>
                        <div class="widget-label">GANANCIA (7d) <i class="bi bi-info-circle" style="cursor:help;font-size:.6rem;opacity:.5;" title="Ventas menos lo que costaron los productos. Tu ganancia real."></i></div>
                        <div class="widget-value" id="kpi-margen">$<?php echo number_format($margen_7d, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(255,193,7,0.12);color:#856404;">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div>
                        <div class="widget-label">FACTURAS (7d) <i class="bi bi-info-circle" style="cursor:help;font-size:.6rem;opacity:.5;" title="Cantidad de facturas de venta emitidas en los últimos 7 días"></i></div>
                        <div class="widget-value" id="kpi-tx"><?php echo $transacciones_7d; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRÁFICOS -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="chart-card h-100">
                    <h5><i class="bi bi-graph-up me-2"></i>INGRESOS VS EGRESOS (7D)</h5>
                    <canvas id="chartFlujo" style="max-height:320px;"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card h-100">
                    <h5><i class="bi bi-pie-chart-fill me-2"></i>TOP 5 MÁS VENDIDOS</h5>
                    <canvas id="chartTop" style="max-height:260px;"></canvas>
                    <div class="mt-3 small text-secondary fw-bold text-center">Basado en unidades despachadas (30d)</div>
                </div>
            </div>
        </div>

        <!-- RESUMEN ADICIONAL -->
        <div class="section-title-est mb-2">
            <i class="bi bi-clipboard-data"></i> Resumen General
        </div>
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="card-jv d-flex align-items-center justify-content-between py-3 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-box-seam fs-3" style="color:var(--jv-info);"></i>
                        <div>
                            <div class="small text-secondary fw-bold text-uppercase">Productos en Inventario</div>
                            <div class="fw-bold fs-5"><?php echo $productos_activos; ?> activos</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-calendar-week fs-3" style="color:var(--jv-success);"></i>
                        <div class="text-end">
                            <div class="small text-secondary fw-bold text-uppercase">Periodo Analizado</div>
                            <div class="fw-bold">Últimos 7 / 30 días</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ANÁLISIS DE GANANCIAS -->
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="card-jv p-4">
                    <h5 class="fw-bold text-uppercase mb-3" style="font-size:.8rem;letter-spacing:1px;color:var(--jv-text-primary);">
                        <i class="bi bi-bar-chart-fill me-2" style="color:var(--jv-success);"></i>ANÁLISIS DE GANANCIAS (7D)
                    </h5>
                    <div class="profit-grid">
                        <div class="profit-summary">
                            <div class="profit-row" style="--profit-color:var(--jv-danger);">
                                <span class="label">Ingresos</span>
                                <span class="value rojo" id="prof-ingresos">$<?php echo number_format($ventas_7d, 2); ?></span>
                            </div>
                            <div class="profit-row" style="--profit-color:var(--jv-info);">
                                <span class="label">Costo Vendido</span>
                                <span class="value cyan" id="prof-costo">$<?php echo number_format($costo_vendido_7d, 2); ?></span>
                            </div>
                            <div class="profit-separator"></div>
                            <div class="profit-row" style="--profit-color:var(--jv-success);">
                                <span class="label">Ganancia</span>
                                <span class="value verde" id="prof-ganancia">$<?php echo number_format($margen_7d, 2); ?></span>
                            </div>
                            <div class="profit-row" style="--profit-color:var(--jv-success);">
                                <span class="label">Margen</span>
                                <span class="margen-badge <?php echo $porc_margen < 10 ? 'malo' : ($porc_margen < 20 ? 'bajo' : ''); ?>" id="prof-margen">
                                    <i class="bi bi-percent"></i> <?php echo $porc_margen; ?>%
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="small fw-bold text-uppercase mb-2" style="color:var(--jv-text-muted);letter-spacing:1px;font-size:.65rem;">Top 5 Productos por Ganancia</div>
                            <table class="profit-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Unids</th>
                                        <th class="text-end">Ganancia</th>
                                        <th class="text-end">%</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-top-ganancia">
                                    <?php $sum_prof = array_sum(array_column($top_ganancia, 'ganancia')); ?>
                                    <?php foreach ($top_ganancia as $tp):
                                        $pct_prod = ($sum_prof > 0) ? round(($tp['ganancia'] / $sum_prof) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tp['nombre_producto']); ?></td>
                                        <td class="text-center"><?php echo $tp['unidades']; ?></td>
                                        <td class="text-end fw-bold" style="color:var(--jv-success);">$<?php echo number_format($tp['ganancia'], 2); ?></td>
                                        <td class="text-end">
                                            <span class="profit-table-pct">
                                                <span class="pct-bar" style="--pct:<?php echo $pct_prod; ?>%"></span>
                                                <?php echo $pct_prod; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($top_ganancia)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary small py-3">Sin datos en los últimos 7 días</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    window.JV_CONFIG = { c0: <?php echo json_encode($fechas); ?>, c1: <?php echo json_encode($ventas_data); ?>, c2: <?php echo json_encode($compras_data); ?>, c3: <?php echo json_encode($top_prod_nombres); ?>, c4: <?php echo json_encode($top_prod_cant); ?>, c5: <?php echo json_encode($top_prod_colores); ?> };
</script>
    <script src="../assets/modules/estadisticas/estadisticas.js"></script>
    
</body>
</html>