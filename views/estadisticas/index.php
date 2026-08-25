<?php

/** @var array<string, string>|null $flash */
/** @var array<string, array<string, mixed>> $periodos */
/** @var array<string, mixed> $datos */

// ==========================================
// VISTA: Estadísticas (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
// Los valores de los gráficos los inyecta el
// layout en window.JV_CONFIG (js_config) y los
// actualiza estadisticas.js vía AJAX.
// El sello de comparación (▲/▼) lo provee el helper
// global jv_sello() definido en includes/helpers.php.
?>
<!-- MENSAJE FLASH -->
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo htmlspecialchars($flash['tipo']); ?> flash-auto" style="padding:12px 18px;font-size:.85rem;font-weight:600;">
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- ENCABEZADO -->
<div class="d-flex align-items-center gap-3 mb-4">
    <div class="stats-header-icon">
        <i class="bi bi-graph-up-arrow"></i>
    </div>
    <div>
        <h1 class="module-title">ESTADÍSTICAS</h1>
        <p class="module-subtitle">Resumen de rendimiento | JV3000 C.A.</p>
    </div>
</div>

<!-- FILTROS DE TIEMPO -->
<div class="filtros-stats mb-4">
    <div class="filtros-botones">
        <?php foreach ($periodos as $periodKey => $period): ?>
            <button type="button" class="btn-filtro-periodo <?php echo $datos['periodo'] === $periodKey ? 'activo' : ''; ?>" data-periodo="<?php echo htmlspecialchars($periodKey); ?>">
                <?php echo htmlspecialchars($period['label']); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <form class="filtro-fechas" method="get" action="<?php echo APP_URL_BASE; ?>index.php?url=estadisticas">
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