<?php

/** @var array<string, string>|null $flash */
/** @var array<string, array<string, mixed>> $periodos */
/** @var array<string, mixed> $datos */
/** @var string $error_fechas */

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
    <div class="alert-jv alert-jv-<?php echo htmlspecialchars($flash['tipo']); ?> flash-auto mb-3">
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- ENCABEZADO -->
<div class="d-flex align-items-center gap-3 mb-4">
    <div class="stats-header-icon" aria-hidden="true">
        <i class="bi bi-graph-up-arrow"></i>
    </div>
    <div>
        <h1 class="module-title">Estadísticas</h1>
        <p class="module-subtitle">Ventas, compras y rentabilidad de JV3000 C.A.</p>
    </div>
</div>

<!-- FILTROS DE TIEMPO -->
<div class="filtros-stats mb-4">
    <div class="filtros-botones">
        <?php foreach ($periodos as $periodKey => $period): ?>
            <button type="button" class="btn-filtro-periodo <?php echo $datos['periodo'] === $periodKey ? 'activo' : ''; ?>" data-periodo="<?php echo htmlspecialchars($periodKey); ?>" aria-pressed="<?php echo $datos['periodo'] === $periodKey ? 'true' : 'false'; ?>">
                <?php echo htmlspecialchars($period['label']); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <form class="filtro-fechas" method="get" action="<?php echo APP_URL_BASE; ?>index.php?url=estadisticas">
        <input type="hidden" name="periodo" value="rango">
        <label for="desde_f" class="fecha-label">Desde</label>
        <input type="date" id="desde_f" name="desde" class="input-fecha" autocomplete="off" value="<?php echo htmlspecialchars($datos['periodo'] === 'rango' ? (isset($datos['desde']) ? $datos['desde'] : '') : ''); ?>" max="<?php echo date('Y-m-d'); ?>">
        <label for="hasta_f" class="fecha-label">Hasta</label>
        <input type="date" id="hasta_f" name="hasta" class="input-fecha" autocomplete="off" value="<?php echo htmlspecialchars($datos['periodo'] === 'rango' ? (isset($datos['hasta']) ? $datos['hasta'] : '') : ''); ?>" max="<?php echo date('Y-m-d'); ?>">
        <button type="submit" class="btn-filtrar">Filtrar</button>
    </form>
</div>

<!-- ERROR DE VALIDACIÓN DE FECHAS (mostrado/oculto por estadisticas.js) -->
<div class="alert-jv alert-jv-warning <?php echo $error_fechas !== '' ? '' : 'd-none'; ?> mb-3" id="error-fechas" role="alert" aria-live="polite">
    <?php echo htmlspecialchars($error_fechas, ENT_QUOTES, 'UTF-8'); ?>
</div>

<!-- TARJETAS KPI -->
<div class="row g-3 mb-3">
    <div class="col-lg-4 col-12">
        <div class="widget-card">
            <div class="widget-icon widget-icon-rojo" aria-hidden="true"><i class="bi bi-currency-dollar"></i></div>
            <div class="widget-cuerpo">
                <div class="widget-label">Ventas</div>
                <div class="widget-value" id="kpi-ventas">$<?php echo number_format(isset($datos['ventas']) ? $datos['ventas'] : 0, 2); ?></div>
                <div class="cmp-wrap"><?php echo jv_sello(isset($datos['pct_ventas']) ? $datos['pct_ventas'] : null); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-12">
        <div class="widget-card">
            <div class="widget-icon widget-icon-verde" aria-hidden="true"><i class="bi bi-truck"></i></div>
            <div class="widget-cuerpo">
                <div class="widget-label">Compras</div>
                <div class="widget-value" id="kpi-compras">$<?php echo number_format(isset($datos['compras']) ? $datos['compras'] : 0, 2); ?></div>
                <div class="cmp-wrap"><?php echo jv_sello(isset($datos['pct_compras']) ? $datos['pct_compras'] : null); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-12">
        <div class="widget-card">
            <div class="widget-icon widget-icon-azul" aria-hidden="true"><i class="bi bi-graph-up"></i></div>
            <div class="widget-cuerpo">
                <div class="widget-label">Ganancia</div>
                <div class="widget-value widget-value-ganancia" id="kpi-ganancia">$<?php echo number_format(isset($datos['ganancia']) ? $datos['ganancia'] : 0, 2); ?></div>
                <div class="cmp-wrap"><?php echo jv_sello(isset($datos['pct_ganancia']) ? $datos['pct_ganancia'] : null); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- MENSAJE DINÁMICO DE COMPARACIÓN -->
<div class="cmp-mensaje" id="cmp-mensaje">
    <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
    <span id="cmp-mensaje-texto"><?php echo htmlspecialchars(isset($datos['mensaje']) ? $datos['mensaje'] : 'Sin datos de comparación'); ?></span>
    <span class="cmp-periodo" id="cmp-periodo"><?php echo htmlspecialchars(isset($datos['etiqueta']) ? $datos['etiqueta'] : 'Semanal'); ?></span>
</div>

<!-- AVISO DE PERIODO SIN MOVIMIENTOS -->
<?php $v = isset($datos['ventas']) ? $datos['ventas'] : 0;
$c = isset($datos['compras']) ? $datos['compras'] : 0;
$hay_movimientos = $v != 0 || $c != 0;
$ventas_anteriores = (float)($datos['ventas_ant'] ?? 0);
$compras_anteriores = (float)($datos['compras_ant'] ?? 0); ?>
<div class="estadisticas-sin-datos alert-jv alert-jv-info mb-4 <?php echo $hay_movimientos ? 'd-none' : ''; ?>" id="aviso-sin-datos" role="status" aria-live="polite">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    <div class="estadisticas-sin-datos-cuerpo">
        <strong id="aviso-sin-datos-titulo">No hay movimientos en este período</strong>
        <span id="aviso-sin-datos-detalle">
            <?php if ($ventas_anteriores > 0 || $compras_anteriores > 0): ?>
                El período anterior sí tuvo actividad:
                ventas $<?php echo number_format($ventas_anteriores, 2); ?>,
                compras $<?php echo number_format($compras_anteriores, 2); ?>.
            <?php else: ?>
                Revisa un período más amplio para consultar las operaciones registradas.
            <?php endif; ?>
        </span>
    </div>
    <a class="estadisticas-sin-datos-enlace" href="<?php echo APP_URL_BASE; ?>index.php?url=estadisticas&periodo=quincena">
        Ver últimos 15 días <i class="bi bi-arrow-right" aria-hidden="true"></i>
    </a>
</div>

<div class="estadisticas-error alert-jv alert-jv-danger mb-4 d-none" id="error-estadisticas" role="alert" aria-live="assertive">
    <span id="error-estadisticas-texto">No se pudieron actualizar las estadísticas.</span>
    <button type="button" id="reintentar-estadisticas">Reintentar</button>
</div>

<!-- GRÁFICOS -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="chart-card h-100">
            <h2><i class="bi bi-graph-up me-2" aria-hidden="true"></i>Ventas y compras</h2>
            <div class="chart-canvas-wrap">
                <canvas id="chartFlujo" role="img" aria-label="Gráfico de ventas y compras por fecha"></canvas>
                <div class="chart-empty d-none" id="empty-flujo">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <span>Sin ventas ni compras en este periodo</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="chart-card h-100">
            <h2><i class="bi bi-bar-chart-fill me-2" aria-hidden="true"></i>Productos más vendidos</h2>
            <div class="chart-canvas-wrap">
                <canvas id="chartTop" role="img" aria-label="Gráfico de unidades por producto más vendido"></canvas>
                <div class="chart-empty d-none" id="empty-top">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <span>Sin ventas registradas en este periodo</span>
                </div>
            </div>
        </div>
    </div>
</div>