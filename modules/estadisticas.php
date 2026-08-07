<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
$rol_est = (int)($_SESSION['id_rol'] ?? 0);
if ($rol_est !== 1 && $rol_est !== 3) {
    header("Location: ../dashboard/index.php?error=acceso_denegado"); exit();
}

// ==========================================
// FILTRO DE PERIODO O RANGO DE FECHAS
// ==========================================
$periodo = preg_match('/^(dia|semana|quincena|mes|trimestre|semestre|rango)$/', $_GET['periodo'] ?? '') ? $_GET['periodo'] : 'semana';
$desde_f = $_GET['desde'] ?? '';
$hasta_f = $_GET['hasta'] ?? '';
if ($periodo === 'rango' && ($desde_f === '' || $hasta_f === '')) {
    $periodo = 'semana';
}

require_once __DIR__ . '/../includes/estadisticas_logic.php';
$datos = jv_est_obtener_datos($db, $periodo, $desde_f, $hasta_f);

$periodos = jv_est_periodos();

// Helper para el sello de comparación ▲/▼
function jv_sello(?float $pct): string {
    if ($pct === null) {
        return '<span class="cmp-sello cmp-nulo" title="Sin datos en el periodo anterior">—</span>';
    }
    if ($pct >= 0) {
        return '<span class="cmp-sello cmp-subida" title="Aumento respecto al periodo anterior"><i class="bi bi-arrow-up-right"></i> +' . number_format($pct, 1) . '%</span>';
    }
    return '<span class="cmp-sello cmp-bajada" title="Descenso respecto al periodo anterior"><i class="bi bi-arrow-down-right"></i> ' . number_format($pct, 1) . '%</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/diseno.php'; ?>
    <title>Estadísticas | JV3000 C.A.</title>
    <script src="../assets/js/chart.umd.min.js"></script>
    <!-- ESTILOS -->
        <link rel="stylesheet" href="../assets/modules/estadisticas/estadisticas.css?v=3">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
    <div class="pagina-estadisticas">
    <div class="container-fluid px-4 py-4">

        <!-- ENCABEZADO -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="stats-header-icon">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div>
                <h1 class="font-brand mb-1" style="font-size:2.1rem;letter-spacing:-1px; color: var(--jv-text-primary);">ESTADÍSTICAS</h1>
                <p class="text-secondary small fw-bold text-uppercase mb-0">Resumen de rendimiento | JV3000 C.A.</p>
            </div>
        </div>

        <!-- FILTROS DE TIEMPO -->
        <div class="filtros-stats mb-4">
            <div class="filtros-botones">
                <?php foreach ($periodos as $clave => $p): ?>
                    <button type="button" class="btn-filtro-periodo <?php echo $datos['periodo'] === $clave ? 'activo' : ''; ?>" data-periodo="<?php echo $clave; ?>">
                        <?php echo $p['label']; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <form class="filtro-fechas" method="get" action="estadisticas.php">
                <input type="hidden" name="periodo" value="rango">
                <label for="desde_f" class="fecha-label">Desde</label>
                <input type="date" id="desde_f" name="desde" class="input-fecha" value="<?php echo htmlspecialchars($datos['periodo'] === 'rango' ? $datos['desde'] : ''); ?>">
                <label for="hasta_f" class="fecha-label">Hasta</label>
                <input type="date" id="hasta_f" name="hasta" class="input-fecha" value="<?php echo htmlspecialchars($datos['periodo'] === 'rango' ? $datos['hasta'] : ''); ?>">
                <button type="submit" class="btn-filtrar">Filtrar</button>
            </form>
        </div>

        <!-- TARJETAS KPI -->
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 col-12">
                <div class="widget-card">
                    <div class="widget-icon widget-icon-naranja"><i class="bi bi-currency-dollar"></i></div>
                    <div class="widget-cuerpo">
                        <div class="widget-label">Ventas</div>
                        <div class="widget-value" id="kpi-ventas">$<?php echo number_format($datos['ventas'], 2); ?></div>
                        <div class="cmp-wrap"><?php echo jv_sello($datos['pct_ventas']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12">
                <div class="widget-card">
                    <div class="widget-icon widget-icon-azul"><i class="bi bi-truck"></i></div>
                    <div class="widget-cuerpo">
                        <div class="widget-label">Compras</div>
                        <div class="widget-value" id="kpi-compras">$<?php echo number_format($datos['compras'], 2); ?></div>
                        <div class="cmp-wrap"><?php echo jv_sello($datos['pct_compras']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 col-12">
                <div class="widget-card">
                    <div class="widget-icon widget-icon-verde"><i class="bi bi-graph-up"></i></div>
                    <div class="widget-cuerpo">
                        <div class="widget-label">Ganancia</div>
                        <div class="widget-value widget-value-ganancia" id="kpi-ganancia">$<?php echo number_format($datos['ganancia'], 2); ?></div>
                        <div class="cmp-wrap"><?php echo jv_sello($datos['pct_ganancia']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MENSAJE DINÁMICO DE COMPARACIÓN -->
        <div class="cmp-mensaje" id="cmp-mensaje">
            <i class="bi bi-arrow-left-right"></i>
            <span id="cmp-mensaje-texto"><?php echo htmlspecialchars($datos['mensaje']); ?></span>
            <span class="cmp-periodo" id="cmp-periodo"><?php echo htmlspecialchars($datos['etiqueta']); ?></span>
        </div>

        <!-- GRÁFICOS -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="chart-card h-100">
                    <h5><i class="bi bi-graph-up me-2"></i>Ventas vs Compras</h5>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartFlujo"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card h-100">
                    <h5><i class="bi bi-bar-chart-fill me-2"></i>Top 5 Más Vendidos</h5>
                    <div class="chart-canvas-wrap">
                        <canvas id="chartTop"></canvas>
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
    window.JV_CONFIG = {
        periodo: <?php echo json_encode($datos['periodo']); ?>,
        labels: <?php echo json_encode($datos['labels_ventas']); ?>,
        ventas: <?php echo json_encode($datos['data_ventas']); ?>,
        compras: <?php echo json_encode($datos['data_compras']); ?>,
        topLabels: <?php echo json_encode($datos['top_labels']); ?>,
        topCant: <?php echo json_encode($datos['top_cant']); ?>
    };
</script>
    <script src="../assets/modules/estadisticas/estadisticas.js?v=2"></script>
    
</body>
</html>
