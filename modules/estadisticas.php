<?php
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
$rol_est = $_SESSION['rol'] ?? '';
if ($rol_est !== 'Administrador' && $rol_est !== 'Operador de Ventas') {
    header("Location: ../index.php?error=acceso_denegado"); exit();
}

// === KPIs ===
$ventas_7d = $db->fetchOne("SELECT COALESCE(SUM(cantidad * precio_venta), 0) as total FROM salidas WHERE fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND id_tipo_mov = 1 AND status = 'Activa'")['total'];

$compras_7d = $db->fetchOne("SELECT COALESCE(SUM(cantidad * precio_costo), 0) as total FROM compras WHERE fecha_compra >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND status = 'Activa'")['total'];

$margen_7d = $db->fetchOne("SELECT COALESCE(SUM(s.cantidad * (s.precio_venta - p.precio_costo)), 0) as margen FROM salidas s JOIN productos p ON s.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa'")['margen'];

$transacciones_7d = $db->fetchOne("SELECT COUNT(DISTINCT nro_factura_manual) as total FROM salidas WHERE fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND nro_factura_manual IS NOT NULL AND id_tipo_mov = 1 AND status = 'Activa'")['total'];

$productos_activos = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE status = 'Activo'")['total'];

// === Gráfico Ventas vs Compras (7 días) ===
$fechas = [];
$ventas_data = [];
$compras_data = [];

for ($i = 6; $i >= 0; $i--) {
    $f = date('Y-m-d', strtotime("-$i days"));
    $fechas[] = date('d/m', strtotime($f));
    $ventas_data[] = $db->fetchOne("SELECT COALESCE(SUM(cantidad * precio_venta), 0) as total FROM salidas WHERE DATE(fecha_salida) = ? AND id_tipo_mov = 1 AND status = 'Activa'", [$f])['total'] ?? 0;
    $compras_data[] = $db->fetchOne("SELECT COALESCE(SUM(cantidad * precio_costo), 0) as total FROM compras WHERE DATE(fecha_compra) = ? AND status = 'Activa'", [$f])['total'] ?? 0;
}

// === Costo de lo vendido (últimos 7 días) ===
$costo_vendido_7d = $db->fetchOne("SELECT COALESCE(SUM(s.cantidad * p.precio_costo), 0) as total FROM salidas s JOIN productos p ON s.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa'")['total'];

$porc_margen = ($ventas_7d > 0) ? round(($margen_7d / $ventas_7d) * 100, 1) : 0;

// === Top 5 productos por ganancia (7 días) ===
$top_ganancia = $db->fetchAll("SELECT p.id_producto, p.nombre_producto, p.sku, SUM(s.cantidad) as unidades, SUM(s.cantidad * s.precio_venta) as ingresos, SUM(s.cantidad * p.precio_costo) as costo, SUM(s.cantidad * (s.precio_venta - p.precio_costo)) as ganancia FROM salidas s JOIN productos p ON s.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY p.id_producto ORDER BY ganancia DESC LIMIT 5");

// === Top 5 productos más vendidos (30 días) ===
$top_prod_nombres = [];
$top_prod_cant = [];
$top_prod_colores = [];

$paleta = ['#38bdf8','#818cf8','#c084fc','#fb923c','#4ade80','#f472b6','#34d399','#fbbf24','#a78bfa','#fb7185'];

$res_top = $db->fetchAll("SELECT p.id_producto, p.nombre_producto, COALESCE(SUM(s.cantidad), 0) as total FROM salidas s JOIN productos p ON s.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY s.id_producto ORDER BY total DESC LIMIT 5");
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
    <style>
    /* === THEME: ESTADÍSTICAS (Cyan) ==================== */
    .stats-header-icon {
        width:52px;height:52px;border-radius:14px;
        background:linear-gradient(135deg,#38bdf8,#0ea5e9);
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-size:1.5rem;flex-shrink:0;
        box-shadow:0 0 30px rgba(56,189,248,0.3);
    }

    .pagina-estadisticas .card-jv {
        border-color:rgba(56,189,248,0.25);
        box-shadow:0 20px 50px -12px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(56,189,248,0.06);
    }

    .pagina-estadisticas .widget-card {
        border-radius:var(--jv-radius-lg);
        background:var(--jv-bg-card);
        backdrop-filter:blur(20px);
        border:1px solid var(--jv-border);
        padding:20px 22px;
        display:flex;
        align-items:center;
        gap:18px;
        transition:all .25s ease;
        min-height:90px;
    }
    .pagina-estadisticas .widget-card:hover {
        border-color:var(--jv-border-hover);
        transform:translateY(-3px);
        box-shadow:0 12px 40px -8px rgba(0,0,0,0.4);
    }
    .widget-icon {
        width:46px;height:46px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        font-size:1.3rem;flex-shrink:0;
    }
    .widget-label {
        font-size:.6rem;text-transform:uppercase;
        letter-spacing:1px;font-weight:700;
        color:rgba(148,163,184,0.7);
        margin-bottom:4px;
    }
    .widget-value {
        font-size:1.4rem;font-weight:800;color:#fff;
        line-height:1.2;
    }

    .pagina-estadisticas .chart-card {
        background:var(--jv-bg-card);
        backdrop-filter:blur(20px);
        border:1px solid var(--jv-border);
        border-radius:var(--jv-radius-lg);
        padding:24px;
        transition:all .3s ease;
    }
    .pagina-estadisticas .chart-card:hover {
        border-color:var(--jv-border-hover);
    }
    .pagina-estadisticas .chart-card h5 {
        font-size:.85rem;font-weight:700;color:rgba(255,255,255,0.7);
        text-transform:uppercase;letter-spacing:1px;
        margin-bottom:20px;padding-bottom:12px;
        border-bottom:1px solid rgba(56,189,248,0.1);
    }
    .pagina-estadisticas .chart-card h5 i { color:#38bdf8; }

    /* === PROFIT SECTION === */
    .profit-grid {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 24px;
        align-items: stretch;
    }
    .profit-summary {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 0;
    }
    .profit-summary .profit-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 16px;
        background: rgba(255,255,255,0.03);
        border-radius: 12px;
        border-left: 3px solid var(--profit-color, #38bdf8);
    }
    .profit-row .label {
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: rgba(148,163,184,0.7);
    }
    .profit-row .value {
        font-size: 1.1rem;
        font-weight: 800;
        color: #fff;
    }
    .profit-row .value.verde { color: #4ade80; }
    .profit-row .value.rojo { color: #f87171; }
    .profit-row .value.cyan { color: #38bdf8; }
    .profit-separator {
        height: 1px;
        background: rgba(255,255,255,0.08);
        margin: 4px 0;
    }
    .margen-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: .85rem;
        font-weight: 800;
        background: rgba(74,222,128,0.15);
        color: #4ade80;
        border: 1px solid rgba(74,222,128,0.3);
    }
    .margen-badge.bajo {
        background: rgba(245,158,11,0.15);
        color: #fbbf24;
        border-color: rgba(245,158,11,0.3);
    }
    .margen-badge.malo {
        background: rgba(239,68,68,0.15);
        color: #f87171;
        border-color: rgba(239,68,68,0.3);
    }
    .profit-table {
        width: 100%;
        border-collapse: collapse;
    }
    .profit-table th {
        font-size: .6rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        color: rgba(148,163,184,0.5);
        padding: 8px 10px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
        text-align: left;
    }
    .profit-table td {
        padding: 10px;
        font-size: .82rem;
        border-bottom: 1px solid rgba(255,255,255,0.03);
        color: rgba(255,255,255,0.8);
        vertical-align: middle;
    }
    .profit-table tbody tr:nth-child(odd) td { background: rgba(255,255,255,0.02); }
    .profit-table tbody tr:nth-child(even) td { background: rgba(255,255,255,0.05); }
    .profit-table td:last-child,
    .profit-table th:last-child { text-align: right; }
    .profit-table td:nth-child(3),
    .profit-table th:nth-child(3) { text-align: center; }
    .profit-table td:first-child { font-weight: 700; }
    .profit-table .pct-bar {
        display: inline-block;
        height: 6px;
        border-radius: 3px;
        background: linear-gradient(90deg, #4ade80 var(--pct), rgba(255,255,255,0.1) var(--pct));
        width: 60px;
        vertical-align: middle;
        margin-right: 6px;
    }
    @media (max-width: 992px) {
        .profit-grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
    <div class="pagina-estadisticas">
    <div class="container-fluid px-4 py-4">

        <div class="d-flex align-items-center gap-4 mb-4">
            <div class="stats-header-icon">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div>
                <h1 class="font-brand mb-1" style="font-size:1.8rem;letter-spacing:-1px;">ESTADÍSTICAS</h1>
                <p class="text-white opacity-75 small fw-bold text-uppercase mb-0">Análisis de Rendimiento | JV3000 C.A.</p>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(56,189,248,0.12);color:#38bdf8;">
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
                    <div class="widget-icon" style="background:rgba(248,113,113,0.12);color:#f87171;">
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
                    <div class="widget-icon" style="background:rgba(74,222,128,0.12);color:#4ade80;">
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
                    <div class="widget-icon" style="background:rgba(251,191,36,0.12);color:#fbbf24;">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div>
                        <div class="widget-label">FACTURAS (7d) <i class="bi bi-info-circle" style="cursor:help;font-size:.6rem;opacity:.5;" title="Cantidad de facturas de venta emitidas en los últimos 7 días"></i></div>
                        <div class="widget-value" id="kpi-tx"><?php echo $transacciones_7d; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
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

        <!-- Fila extra: resumen adicional -->
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="card-jv d-flex align-items-center justify-content-between py-3 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-box-seam fs-3" style="color:#38bdf8;"></i>
                        <div>
                            <div class="small text-secondary fw-bold text-uppercase">Productos en Inventario</div>
                            <div class="fw-bold fs-5"><?php echo $productos_activos; ?> activos</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-calendar-week fs-3" style="color:#4ade80;"></i>
                        <div class="text-end">
                            <div class="small text-secondary fw-bold text-uppercase">Periodo Analizado</div>
                            <div class="fw-bold">Últimos 7 / 30 días</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Análisis de Ganancias -->
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="card-jv p-4">
                    <h5 class="fw-bold text-uppercase mb-3" style="font-size:.8rem;letter-spacing:1px;color:rgba(255,255,255,0.6);">
                        <i class="bi bi-bar-chart-fill me-2" style="color:#4ade80;"></i>ANÁLISIS DE GANANCIAS (7D)
                    </h5>
                    <div class="profit-grid">
                        <div class="profit-summary">
                            <div class="profit-row" style="--profit-color:#38bdf8;">
                                <span class="label">Ingresos</span>
                                <span class="value cyan" id="prof-ingresos">$<?php echo number_format($ventas_7d, 2); ?></span>
                            </div>
                            <div class="profit-row" style="--profit-color:#f87171;">
                                <span class="label">Costo Vendido</span>
                                <span class="value rojo" id="prof-costo">$<?php echo number_format($costo_vendido_7d, 2); ?></span>
                            </div>
                            <div class="profit-separator"></div>
                            <div class="profit-row" style="--profit-color:#4ade80;">
                                <span class="label">Ganancia</span>
                                <span class="value verde" id="prof-ganancia">$<?php echo number_format($margen_7d, 2); ?></span>
                            </div>
                            <div class="profit-row" style="--profit-color:#4ade80;">
                                <span class="label">Margen</span>
                                <span class="margen-badge <?php echo $porc_margen < 10 ? 'malo' : ($porc_margen < 20 ? 'bajo' : ''); ?>" id="prof-margen">
                                    <i class="bi bi-percent"></i> <?php echo $porc_margen; ?>%
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="small fw-bold text-uppercase mb-2" style="color:rgba(148,163,184,0.5);letter-spacing:1px;font-size:.65rem;">Top 5 Productos por Ganancia</div>
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
                                        <td class="text-end fw-bold" style="color:#4ade80;">$<?php echo number_format($tp['ganancia'], 2); ?></td>
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

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.1)';

    const fechas = <?php echo json_encode($fechas); ?>;
    const ventas = <?php echo json_encode($ventas_data); ?>;
    const compras = <?php echo json_encode($compras_data); ?>;

    new Chart(document.getElementById('chartFlujo'), {
        type: 'line',
        data: {
            labels: fechas,
            datasets: [
                { label: 'Ventas ($)', data: ventas, borderColor: '#38bdf8', backgroundColor: 'rgba(56,189,248,0.1)', fill: true, tension: 0.4 },
                { label: 'Compras ($)', data: compras, borderColor: '#f87171', backgroundColor: 'rgba(248,113,113,0.1)', fill: true, tension: 0.4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, grid: { display: true } },
                x: { grid: { display: false } }
            }
        }
    });
    new Chart(document.getElementById('chartTop'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($top_prod_nombres); ?>,
            datasets: [{
                data: <?php echo json_encode($top_prod_cant); ?>,
                backgroundColor: <?php echo json_encode($top_prod_colores); ?>,
                borderWidth: 0, hoverOffset: 12
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        color: '#94a3b8',
                        usePointStyle: true,
                        padding: 10,
                        font: { size: 10, weight: 'bold' },
                        boxWidth: 12,
                        boxHeight: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            if (total === 0) return ctx.label + ': 0';
                            const pct = ((ctx.parsed / total) * 100).toFixed(1);
                            return ctx.label + ': ' + ctx.parsed + ' uds (' + pct + '%)';
                        }
                    }
                }
            },
            cutout: '62%'
        }
    });

    // Auto-refresh cada 30s
    function refreshKPIs() {
        fetch('../includes/estadisticas_ajax.php')
            .then(r => r.json())
            .then(d => {
                try {
                    if (d.success) {
                        document.getElementById('kpi-ventas').textContent = '$' + d.ventas_7d;
                        document.getElementById('kpi-compras').textContent = '$' + d.compras_7d;
                        document.getElementById('kpi-margen').textContent = '$' + d.margen_7d;
                        document.getElementById('kpi-tx').textContent = d.transacciones_7d;
                        document.getElementById('prof-ingresos').textContent = '$' + d.ventas_7d;
                        document.getElementById('prof-costo').textContent = '$' + d.costo_vendido_7d;
                        document.getElementById('prof-ganancia').textContent = '$' + d.margen_7d;
                        const pm = document.getElementById('prof-margen');
                        const porc = d.porc_margen;
                        pm.innerHTML = '<i class="bi bi-percent"></i> ' + porc + '%';
                        pm.className = 'margen-badge';
                        if (porc < 10) pm.classList.add('malo');
                        else if (porc < 20) pm.classList.add('bajo');
                        let htmlTop = '';
                        if (d.top_ganancia && d.top_ganancia.length > 0) {
                            d.top_ganancia.forEach(tp => {
                                htmlTop += `<tr><td>${tp.producto}</td><td class="text-center">${tp.unidades}</td><td class="text-end fw-bold" style="color:#4ade80;">$${tp.ganancia}</td><td class="text-end"><span class="profit-table-pct"><span class="pct-bar" style="--pct:${tp.pct}%"></span>${tp.pct}%</span></td></tr>`;
                            });
                        } else {
                            htmlTop = '<tr><td colspan="4" class="text-center text-secondary small py-3">Sin datos en los últimos 7 días</td></tr>';
                        }
                        document.getElementById('tabla-top-ganancia').innerHTML = htmlTop;
                    }
                } catch(e) { console.error('Stats refresh error:', e); }
            })
            .catch(() => {});
    }

    setInterval(refreshKPIs, 30000);
    </script>
    <script>
    const mainWrapper = document.getElementById('mainWrapper');
    const observer = new MutationObserver(() => {
        if (document.body.classList.contains('sidebar-open')) {
            mainWrapper.classList.add('sidebar-open');
        } else {
            mainWrapper.classList.remove('sidebar-open');
        }
    });
    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    </script>
</body>
</html>