<?php

/** @var array<string, string>|null $flash */
/** @var array<string, array<string, mixed>> $periodos */
/** @var array<int, array<string, mixed>> $categorias */
/** @var array<int, array<string, mixed>> $proveedores */
/** @var array<string, mixed> $datos */
/** @var string $error_fechas */
/** @var array<string, mixed> $inventario_critico */
/** @var array<string, mixed> $rotacion */
/** @var array<string, mixed> $vencimientos */
/** @var array<string, mixed> $proveedores_tabla */
/** @var float|null $tasa_bcv Tasa BCV para mostrar los montos en Bs. */

// ==========================================
// VISTA: Estadísticas (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
// Los valores de los gráficos los inyecta el
// layout en window.JV_CONFIG (js_config) y los
// actualiza estadisticas.js vía AJAX.
// El sello de comparación (▲/▼) lo provee el helper
// global jv_sello() definido en includes/helpers.php.

$top_rows = is_array($datos['top_rows'] ?? null) ? $datos['top_rows'] : [];
$rent_rows = is_array($datos['rent_rows'] ?? null) ? $datos['rent_rows'] : [];
$top_total = 0.0;
$top_max = 0.0;
foreach ($top_rows as $fila_top) {
    $top_total += (float)$fila_top['unidades'];
    $top_max = max($top_max, (float)$fila_top['unidades']);
}
$folio = static fn($valor): string => number_format((float)$valor, 0, ',', '.');
$rent_desde = $rent_rows === [] ? 0 : 1;
$rent_hasta = $rent_rows === [] ? 0 : count($rent_rows);

$criticos = is_array($inventario_critico['filas'] ?? null) ? $inventario_critico['filas'] : [];
$rotacion_filas = is_array($rotacion['filas'] ?? null) ? $rotacion['filas'] : [];
$venc_filas = is_array($vencimientos['filas'] ?? null) ? $vencimientos['filas'] : [];
$venc_conteos = is_array($vencimientos['conteos'] ?? null) ? $vencimientos['conteos'] : ['vencido' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'total' => 0];
$link_producto = static fn($sku): string => APP_URL_BASE . 'index.php?url=productos&sku=' . rawurlencode((string)$sku);
$link_reponer = static function (array $fila): string {
    $url = APP_URL_BASE . 'index.php?url=compras&reponer_producto=' . (int)$fila['id_producto'];
    if (!empty($fila['id_proveedor'])) {
        $url .= '&reponer_proveedor=' . (int)$fila['id_proveedor'];
    }
    return $url;
};
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
        <p class="module-subtitle">Ventas, rentabilidad e inteligencia comercial de JV3000 C.A.</p>
    </div>
</div>

<!-- FILTROS GLOBALES -->
<form class="filtros-stats mb-4" id="formFiltrosStats" method="get" action="<?php echo APP_URL_BASE; ?>index.php">
    <input type="hidden" name="url" value="estadisticas">
    <input type="hidden" name="periodo" id="filtro-periodo" value="<?php echo htmlspecialchars($datos['periodo']); ?>">

    <div class="filtros-fila">
        <span class="filtros-etiqueta" id="etiqueta-periodo">Periodo</span>
        <div class="filtros-botones" role="group" aria-labelledby="etiqueta-periodo">
            <?php foreach ($periodos as $periodKey => $period): ?>
                <button type="submit" name="periodo" value="<?php echo htmlspecialchars($periodKey); ?>" class="btn-filtro-periodo <?php echo $datos['periodo'] === $periodKey ? 'activo' : ''; ?>" data-periodo="<?php echo htmlspecialchars($periodKey); ?>" aria-pressed="<?php echo $datos['periodo'] === $periodKey ? 'true' : 'false'; ?>">
                    <?php echo htmlspecialchars($period['label']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="filtros-fila filtros-fila--campos filtro-fechas">
        <div class="filtros-campo">
            <label for="desde_f" class="fecha-label">Desde</label>
            <input type="date" id="desde_f" name="desde" class="input-fecha" autocomplete="off" value="<?php echo htmlspecialchars($datos['periodo'] === 'rango' ? (isset($datos['desde']) ? $datos['desde'] : '') : ''); ?>" max="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="filtros-campo">
            <label for="hasta_f" class="fecha-label">Hasta</label>
            <input type="date" id="hasta_f" name="hasta" class="input-fecha" autocomplete="off" value="<?php echo htmlspecialchars($datos['periodo'] === 'rango' ? (isset($datos['hasta']) ? $datos['hasta'] : '') : ''); ?>" max="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="filtros-campo">
            <label for="filtro-categoria" class="fecha-label">Categoría</label>
            <select id="filtro-categoria" name="categoria" class="select-filtro">
                <option value="0">Todas las categorías</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?php echo (int)$categoria['id_categoria']; ?>" <?php echo (int)($datos['filtro_categoria'] ?? 0) === (int)$categoria['id_categoria'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(str_repeat('— ', max(0, (int)($categoria['nivel'] ?? 0))) . $categoria['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtros-campo">
            <label for="filtro-proveedor" class="fecha-label">Proveedor</label>
            <select id="filtro-proveedor" name="proveedor" class="select-filtro">
                <option value="0">Todos los proveedores</option>
                <?php foreach ($proveedores as $proveedor): ?>
                    <option value="<?php echo (int)$proveedor['id_proveedor']; ?>" <?php echo (int)($datos['filtro_proveedor'] ?? 0) === (int)$proveedor['id_proveedor'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($proveedor['nombre_empresa']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtros-acciones">
            <button type="submit" class="btn-filtrar">Aplicar</button>
            <a class="btn-limpiar-filtros" href="<?php echo APP_URL_BASE; ?>index.php?url=estadisticas">Limpiar</a>
        </div>
    </div>
</form>

<!-- ERROR DE VALIDACIÓN DE FECHAS (mostrado/oculto por estadisticas.js) -->
<div class="alert-jv alert-jv-warning <?php echo $error_fechas !== '' ? '' : 'd-none'; ?> mb-3" id="error-fechas" role="alert" aria-live="polite">
    <?php echo htmlspecialchars($error_fechas, ENT_QUOTES, 'UTF-8'); ?>
</div>

<!-- TARJETAS KPI -->
<div class="row g-3 mb-3 kpi-grid">
    <div class="col-xl-3 col-md-6 col-12">
        <div class="widget-card">
            <div class="widget-icon widget-icon-rojo" aria-hidden="true"><i class="bi bi-cash-coin"></i></div>
            <div class="widget-cuerpo">
                <div class="widget-label">Ventas</div>
                <div class="widget-value" id="kpi-ventas"><?php echo format_bs((float)(isset($datos['ventas']) ? $datos['ventas'] : 0), $tasa_bcv ?? null); ?></div>
                <div class="widget-detalle" id="kpi-operaciones"><?php echo $folio($datos['operaciones'] ?? 0); ?> operaciones</div>
                <div class="cmp-wrap"><?php echo jv_sello(isset($datos['pct_ventas']) ? $datos['pct_ventas'] : null); ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-12">
        <div class="widget-card">
            <div class="widget-icon widget-icon-ganancia" aria-hidden="true"><i class="bi bi-graph-up"></i></div>
            <div class="widget-cuerpo">
                <div class="widget-label">Margen bruto</div>
                <div class="widget-value widget-value-ganancia" id="kpi-ganancia"><?php echo format_bs((float)(isset($datos['ganancia']) ? $datos['ganancia'] : 0), $tasa_bcv ?? null); ?></div>
                <div class="widget-detalle" id="kpi-margen-pct"><?php echo $datos['margen_pct'] !== null ? number_format((float)$datos['margen_pct'], 1, ',', '.') . ' % sobre ventas' : 'Sin ventas en el periodo'; ?></div>
                <div class="cmp-wrap"><?php echo jv_sello(isset($datos['pct_ganancia']) ? $datos['pct_ganancia'] : null); ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-12">
        <div class="widget-card">
            <div class="widget-icon widget-icon-naranja" aria-hidden="true"><i class="bi bi-box-seam"></i></div>
            <div class="widget-cuerpo">
                <div class="widget-label">Unidades vendidas</div>
                <div class="widget-value" id="kpi-unidades"><?php echo $folio($datos['unidades'] ?? 0); ?></div>
                <div class="widget-detalle" id="kpi-ticket">Ticket promedio <?php echo format_bs((float)($datos['ticket'] ?? 0), $tasa_bcv ?? null); ?></div>
                <div class="cmp-wrap"><?php echo jv_sello(isset($datos['pct_unidades']) ? $datos['pct_unidades'] : null); ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-12">
        <div class="widget-card">
            <div class="widget-icon widget-icon-verde" aria-hidden="true"><i class="bi bi-truck"></i></div>
            <div class="widget-cuerpo">
                <div class="widget-label">Compras</div>
                <div class="widget-value" id="kpi-compras"><?php echo format_bs((float)(isset($datos['compras']) ? $datos['compras'] : 0), $tasa_bcv ?? null); ?></div>
                <div class="widget-detalle">Costo de reposición</div>
                <div class="cmp-wrap"><?php echo jv_sello(isset($datos['pct_compras']) ? $datos['pct_compras'] : null); ?></div>
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
                ventas <?php echo format_bs($ventas_anteriores, $tasa_bcv ?? null); ?>,
                compras <?php echo format_bs($compras_anteriores, $tasa_bcv ?? null); ?>.
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
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="chart-card h-100">
            <h2><i class="bi bi-graph-up me-2" aria-hidden="true"></i>Ventas, compras y margen</h2>
            <div class="chart-canvas-wrap">
                <canvas id="chartFlujo" role="img" aria-label="Gráfico de ventas, compras y margen por fecha"></canvas>
                <div class="chart-empty d-none" id="empty-flujo">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <span>Sin ventas ni compras en este periodo</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h2><i class="bi bi-pie-chart-fill me-2" aria-hidden="true"></i>Ventas por categoría</h2>
            <div class="chart-canvas-wrap chart-canvas-wrap--dona">
                <canvas id="chartCategoria" role="img" aria-label="Gráfico de participación de ventas por categoría"></canvas>
                <div class="chart-empty d-none" id="empty-categoria">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <span>Sin ventas por categoría en este periodo</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- RANKING Y RENTABILIDAD -->
<div class="row g-4">
    <div class="col-lg-5">
        <div class="chart-card tabla-card h-100">
            <h2><i class="bi bi-trophy me-2" aria-hidden="true"></i>Top 10 productos vendidos</h2>
            <div class="tabla-scroll">
                <table class="tabla-stats" id="tablaTop">
                    <caption class="visually-hidden">Productos ordenados por unidades vendidas en el periodo filtrado</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="col-puesto">#</th>
                            <th scope="col">Producto</th>
                            <th scope="col" class="num">Unid.</th>
                            <th scope="col" class="col-barra">Participación</th>
                        </tr>
                    </thead>
                    <tbody id="tablaTopCuerpo">
                        <?php foreach ($top_rows as $indice => $fila_top): ?>
                            <?php
                            $unidades_top = (float)$fila_top['unidades'];
                            $share_top = $top_total > 0 ? ($unidades_top / $top_total) * 100 : 0;
                            $ancho_top = $top_max > 0 ? ($unidades_top / $top_max) * 100 : 0;
                            ?>
                            <tr>
                                <td class="col-puesto"><?php echo $indice + 1; ?></td>
                                <td class="celda-producto">
                                    <span class="prod-nombre"><?php echo htmlspecialchars($fila_top['nombre_producto']); ?></span>
                                    <span class="prod-sku"><?php echo htmlspecialchars($fila_top['sku']); ?></span>
                                </td>
                                <td class="num"><?php echo $folio($unidades_top); ?></td>
                                <td class="col-barra">
                                    <div class="rank-linea">
                                        <div class="rank-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)round($share_top); ?>" aria-label="Participación de <?php echo htmlspecialchars($fila_top['nombre_producto']); ?>">
                                            <span style="width:<?php echo max(2, min(100, $ancho_top)); ?>%"></span>
                                        </div>
                                        <span class="rank-pct"><?php echo number_format($share_top, 1, ',', '.'); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tabla-vacia <?php echo $top_rows === [] ? '' : 'd-none'; ?>" id="vacio-top">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                <span>Sin ventas registradas en este periodo</span>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="chart-card tabla-card h-100">
            <h2><i class="bi bi-cash-stack me-2" aria-hidden="true"></i>Rentabilidad por producto</h2>
            <div class="tabla-scroll">
                <table class="tabla-stats tabla-rentabilidad" id="tablaRentabilidad">
                    <caption class="visually-hidden">Margen real por producto: precio de venta menos costo del lote vendido</caption>
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col" class="num">Unid.</th>
                            <th scope="col" class="num col-ingreso">Ingreso</th>
                            <th scope="col" class="num">Margen</th>
                            <th scope="col" class="col-barra">% margen</th>
                        </tr>
                    </thead>
                    <tbody id="tablaRentabilidadCuerpo">
                        <?php foreach ($rent_rows as $fila_rent): ?>
                            <?php
                            $margen_pct = isset($fila_rent['margen_pct']) ? (float)$fila_rent['margen_pct'] : 0;
                            $clase_margen = $margen_pct >= 30 ? 'margen-alto' : ($margen_pct >= 15 ? 'margen-medio' : 'margen-bajo');
                            $ancho_margen = max(2, min(100, $margen_pct));
                            ?>
                            <tr>
                                <td class="celda-producto">
                                    <span class="prod-nombre"><?php echo htmlspecialchars($fila_rent['nombre_producto']); ?></span>
                                    <span class="prod-sku"><?php echo htmlspecialchars($fila_rent['sku']); ?></span>
                                </td>
                                <td class="num"><?php echo $folio($fila_rent['unidades']); ?></td>
                                <td class="num col-ingreso"><?php echo format_bs((float)$fila_rent['ingreso'], $tasa_bcv ?? null); ?></td>
                                <td class="num importe-margen"><?php echo format_bs((float)$fila_rent['margen'], $tasa_bcv ?? null); ?></td>
                                <td class="col-barra">
                                    <div class="rank-linea">
                                        <div class="rank-bar rank-bar--margen <?php echo $clase_margen; ?>" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)round($margen_pct); ?>" aria-label="Margen de <?php echo htmlspecialchars($fila_rent['nombre_producto']); ?>">
                                            <span style="width:<?php echo $ancho_margen; ?>%"></span>
                                        </div>
                                        <span class="rank-pct"><?php echo number_format($margen_pct, 1, ',', '.'); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tabla-vacia <?php echo $rent_rows === [] ? '' : 'd-none'; ?>" id="vacio-rentabilidad">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                <span>Sin ventas para calcular margen en este periodo</span>
            </div>
            <div class="tabla-pie">
                <span class="tabla-resumen" id="rentResumen"><?php echo $rent_rows === [] ? '0 productos' : 'Mostrando ' . $rent_desde . '–' . $rent_hasta . ' de ' . (int)$datos['rent_total']; ?></span>
                <nav class="tabla-paginacion" id="paginacionRentabilidad" aria-label="Paginación de rentabilidad" data-pagina="<?php echo (int)$datos['rent_pagina']; ?>" data-paginas="<?php echo (int)$datos['rent_paginas']; ?>">
                    <button type="button" class="btn-pagina" data-pagina="<?php echo max(1, (int)$datos['rent_pagina'] - 1); ?>" <?php echo (int)$datos['rent_pagina'] <= 1 ? 'disabled' : ''; ?>>Anterior</button>
                    <span class="pagina-indicador" id="rentIndicador"><?php echo (int)$datos['rent_pagina']; ?> / <?php echo (int)$datos['rent_paginas']; ?></span>
                    <button type="button" class="btn-pagina" data-pagina="<?php echo min((int)$datos['rent_paginas'], (int)$datos['rent_pagina'] + 1); ?>" <?php echo (int)$datos['rent_pagina'] >= (int)$datos['rent_paginas'] ? 'disabled' : ''; ?>>Siguiente</button>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- INVENTARIO CRÍTICO -->
<h2 class="seccion-titulo" id="inventario-critico"><i class="bi bi-box-seam" aria-hidden="true"></i>Inventario crítico</h2>
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="chart-card tabla-card h-100">
            <h2><i class="bi bi-cart-plus me-2" aria-hidden="true"></i>Comprar ya <span class="seccion-nota">stock ≤ mínimo</span></h2>
            <div class="tabla-scroll">
                <table class="tabla-stats" id="tablaCriticos">
                    <caption class="visually-hidden">Productos con stock igual o inferior al mínimo configurado</caption>
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col" class="num">Stock</th>
                            <th scope="col" class="num">Mín.</th>
                            <th scope="col" class="num d-none d-md-table-cell">Cobertura</th>
                            <th scope="col" class="num">Sugerido</th>
                            <th scope="col" class="num"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody id="tablaCriticosCuerpo">
                        <?php foreach ($criticos as $fila_critico): ?>
                            <tr>
                                <td class="celda-producto">
                                    <a class="enlace-producto" href="<?php echo htmlspecialchars($link_producto($fila_critico['sku'])); ?>"><?php echo htmlspecialchars($fila_critico['nombre_producto']); ?></a>
                                    <span class="prod-sku"><?php echo htmlspecialchars($fila_critico['sku']); ?></span>
                                </td>
                                <td class="num stock-critico"><?php echo $folio($fila_critico['stock_actual']); ?></td>
                                <td class="num"><?php echo $folio($fila_critico['stock_minimo']); ?></td>
                                <td class="num d-none d-md-table-cell">
                                    <?php echo $fila_critico['cobertura_dias'] !== null ? number_format((float)$fila_critico['cobertura_dias'], 1, ',', '.') . ' d' : '—'; ?>
                                </td>
                                <td class="num"><?php echo $folio($fila_critico['sugerido_compra']); ?></td>
                                <td class="num">
                                    <a class="btn-reponer" href="<?php echo htmlspecialchars($link_reponer($fila_critico)); ?>" title="Reponer <?php echo htmlspecialchars($fila_critico['sku']); ?> en Compras">
                                        <i class="bi bi-plus-lg" aria-hidden="true"></i><span class="visually-hidden">Reponer <?php echo htmlspecialchars($fila_critico['sku']); ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tabla-vacia <?php echo $criticos === [] ? '' : 'd-none'; ?>" id="vacio-criticos">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                <span>Todo el inventario está por encima del mínimo</span>
            </div>
            <div class="tabla-pie">
                <span class="tabla-resumen" id="criticosResumen"><?php echo $criticos === [] ? '0 productos' : 'Mostrando ' . count($criticos) . ' de ' . (int)$inventario_critico['total']; ?></span>
                <nav class="tabla-paginacion" id="paginacionCriticos" aria-label="Paginación de productos por comprar" data-vista="critico" data-pagina="<?php echo (int)$inventario_critico['pagina']; ?>" data-paginas="<?php echo (int)$inventario_critico['paginas']; ?>">
                    <button type="button" class="btn-pagina" data-pagina="<?php echo max(1, (int)$inventario_critico['pagina'] - 1); ?>" <?php echo (int)$inventario_critico['pagina'] <= 1 ? 'disabled' : ''; ?>>Anterior</button>
                    <span class="pagina-indicador" id="criticosIndicador"><?php echo (int)$inventario_critico['pagina']; ?> / <?php echo (int)$inventario_critico['paginas']; ?></span>
                    <button type="button" class="btn-pagina" data-pagina="<?php echo min((int)$inventario_critico['paginas'], (int)$inventario_critico['pagina'] + 1); ?>" <?php echo (int)$inventario_critico['pagina'] >= (int)$inventario_critico['paginas'] ? 'disabled' : ''; ?>>Siguiente</button>
                </nav>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="chart-card tabla-card h-100">
            <h2><i class="bi bi-hourglass-split me-2" aria-hidden="true"></i>Rotación baja <span class="seccion-nota">0 ventas en el periodo</span></h2>
            <div class="tabla-scroll">
                <table class="tabla-stats" id="tablaRotacion">
                    <caption class="visually-hidden">Productos con existencias que no vendieron en el periodo filtrado</caption>
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col" class="num">Stock</th>
                            <th scope="col" class="num">Última venta</th>
                            <th scope="col" class="num">Sin vender</th>
                            <th scope="col" class="num d-none d-md-table-cell">Valor</th>
                        </tr>
                    </thead>
                    <tbody id="tablaRotacionCuerpo">
                        <?php foreach ($rotacion_filas as $fila_rotacion): ?>
                            <tr>
                                <td class="celda-producto">
                                    <a class="enlace-producto" href="<?php echo htmlspecialchars($link_producto($fila_rotacion['sku'])); ?>"><?php echo htmlspecialchars($fila_rotacion['nombre_producto']); ?></a>
                                    <span class="prod-sku"><?php echo htmlspecialchars($fila_rotacion['sku']); ?></span>
                                </td>
                                <td class="num"><?php echo $folio($fila_rotacion['stock_actual']); ?></td>
                                <td class="num"><?php echo $fila_rotacion['ultima_venta'] !== null ? date('d/m/Y', strtotime((string)$fila_rotacion['ultima_venta'])) : 'Nunca'; ?></td>
                                <td class="num"><?php echo $fila_rotacion['dias_sin_vender'] !== null ? $folio($fila_rotacion['dias_sin_vender']) . ' d' : '—'; ?></td>
                                <td class="num d-none d-md-table-cell"><?php echo format_bs((float)$fila_rotacion['valor_inmovilizado'], $tasa_bcv ?? null); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="tabla-vacia <?php echo $rotacion_filas === [] ? '' : 'd-none'; ?>" id="vacio-rotacion">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                <span>Todo el inventario rotó en este periodo</span>
            </div>
            <div class="tabla-pie">
                <span class="tabla-resumen" id="rotacionResumen"><?php echo $rotacion_filas === [] ? '0 productos' : 'Mostrando ' . count($rotacion_filas) . ' de ' . (int)$rotacion['total']; ?></span>
                <nav class="tabla-paginacion" id="paginacionRotacion" aria-label="Paginación de rotación baja" data-vista="rotacion" data-pagina="<?php echo (int)$rotacion['pagina']; ?>" data-paginas="<?php echo (int)$rotacion['paginas']; ?>">
                    <button type="button" class="btn-pagina" data-pagina="<?php echo max(1, (int)$rotacion['pagina'] - 1); ?>" <?php echo (int)$rotacion['pagina'] <= 1 ? 'disabled' : ''; ?>>Anterior</button>
                    <span class="pagina-indicador" id="rotacionIndicador"><?php echo (int)$rotacion['pagina']; ?> / <?php echo (int)$rotacion['paginas']; ?></span>
                    <button type="button" class="btn-pagina" data-pagina="<?php echo min((int)$rotacion['paginas'], (int)$rotacion['pagina'] + 1); ?>" <?php echo (int)$rotacion['pagina'] >= (int)$rotacion['paginas'] ? 'disabled' : ''; ?>>Siguiente</button>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- VENCIMIENTOS -->
<h2 class="seccion-titulo" id="vencimientos"><i class="bi bi-calendar-x" aria-hidden="true"></i>Vencimientos <span class="seccion-nota">a hoy · próximos 90 días</span></h2>
<div class="venc-contadores" id="vencContadores" role="group" aria-label="Filtrar vencimientos por tramo">
    <button type="button" class="venc-contador <?php echo $venc_conteos['vencido'] > 0 ? 'venc-contador--alerta' : ''; ?>" data-tramo="vencido" aria-pressed="false">
        <span class="venc-contador-num" id="venc-vencido"><?php echo $folio($venc_conteos['vencido']); ?></span>
        <span class="venc-contador-label">Vencidos</span>
    </button>
    <button type="button" class="venc-contador" data-tramo="d30" aria-pressed="false">
        <span class="venc-contador-num" id="venc-d30"><?php echo $folio($venc_conteos['d30']); ?></span>
        <span class="venc-contador-label">≤ 30 días</span>
    </button>
    <button type="button" class="venc-contador" data-tramo="d60" aria-pressed="false">
        <span class="venc-contador-num" id="venc-d60"><?php echo $folio($venc_conteos['d60']); ?></span>
        <span class="venc-contador-label">31–60 días</span>
    </button>
    <button type="button" class="venc-contador" data-tramo="d90" aria-pressed="false">
        <span class="venc-contador-num" id="venc-d90"><?php echo $folio($venc_conteos['d90']); ?></span>
        <span class="venc-contador-label">61–90 días</span>
    </button>
</div>

<div class="chart-card tabla-card mb-4">
    <h2><i class="bi bi-calendar-x me-2" aria-hidden="true"></i>Lotes por vencer <span class="seccion-nota" id="vencTramoEtiqueta">todos los tramos</span></h2>
    <div class="tabla-scroll">
        <table class="tabla-stats" id="tablaVencimientos">
            <caption class="visually-hidden">Lotes con existencia que vencen en los próximos 90 días o ya vencieron</caption>
            <thead>
                <tr>
                    <th scope="col">Producto</th>
                    <th scope="col" class="num">Lote</th>
                    <th scope="col" class="num">Cant.</th>
                    <th scope="col" class="num">Vence</th>
                    <th scope="col" class="num">Días</th>
                    <th scope="col" class="d-none d-lg-table-cell">Proveedor</th>
                    <th scope="col" class="num">Valor</th>
                </tr>
            </thead>
            <tbody id="tablaVencimientosCuerpo">
                <?php foreach ($venc_filas as $fila_venc): ?>
                    <tr>
                        <td class="celda-producto">
                            <a class="enlace-producto" href="<?php echo htmlspecialchars($link_producto($fila_venc['sku'])); ?>"><?php echo htmlspecialchars($fila_venc['nombre_producto']); ?></a>
                            <span class="prod-sku"><?php echo htmlspecialchars($fila_venc['sku']); ?></span>
                        </td>
                        <td class="num">#<?php echo (int)$fila_venc['id_lote']; ?></td>
                        <td class="num"><?php echo $folio($fila_venc['cantidad_restante']); ?></td>
                        <td class="num"><?php echo date('d/m/Y', strtotime((string)$fila_venc['fecha_vencimiento'])); ?></td>
                        <td class="num">
                            <span class="venc-dias <?php echo (int)$fila_venc['dias_restantes'] < 0 ? 'venc-dias--negativo' : ((int)$fila_venc['dias_restantes'] <= 30 ? 'venc-dias--alerta' : '') ; ?>">
                                <?php echo (int)$fila_venc['dias_restantes']; ?> d
                            </span>
                        </td>
                        <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($fila_venc['proveedor'] ?? '—'); ?></td>
                        <td class="num"><?php echo format_bs((float)$fila_venc['valor_riesgo'], $tasa_bcv ?? null); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="tabla-vacia <?php echo $venc_filas === [] ? '' : 'd-none'; ?>" id="vacio-vencimientos">
        <i class="bi bi-check2-circle" aria-hidden="true"></i>
        <span>No hay lotes vencidos ni próximos a vencer en 90 días</span>
    </div>
    <div class="tabla-pie">
        <span class="tabla-resumen" id="vencResumen"><?php echo $venc_filas === [] ? '0 lotes' : 'Mostrando ' . count($venc_filas) . ' de ' . (int)$vencimientos['total']; ?></span>
        <nav class="tabla-paginacion" id="paginacionVencimientos" aria-label="Paginación de vencimientos" data-pagina="<?php echo (int)$vencimientos['pagina']; ?>" data-paginas="<?php echo (int)$vencimientos['paginas']; ?>" data-tramo="todas">
            <button type="button" class="btn-pagina" data-pagina="<?php echo max(1, (int)$vencimientos['pagina'] - 1); ?>" <?php echo (int)$vencimientos['pagina'] <= 1 ? 'disabled' : ''; ?>>Anterior</button>
            <span class="pagina-indicador" id="vencIndicador"><?php echo (int)$vencimientos['pagina']; ?> / <?php echo (int)$vencimientos['paginas']; ?></span>
            <button type="button" class="btn-pagina" data-pagina="<?php echo min((int)$vencimientos['paginas'], (int)$vencimientos['pagina'] + 1); ?>" <?php echo (int)$vencimientos['pagina'] >= (int)$vencimientos['paginas'] ? 'disabled' : ''; ?>>Siguiente</button>
        </nav>
    </div>
</div>

<!-- PROVEEDORES -->
<?php $prov_filas = is_array($proveedores_tabla['filas'] ?? null) ? $proveedores_tabla['filas'] : []; ?>
<h2 class="seccion-titulo" id="proveedores"><i class="bi bi-truck" aria-hidden="true"></i>Proveedores <span class="seccion-nota">volumen, margen y productos que no rotan</span></h2>
<div class="chart-card tabla-card mb-4">
    <div class="tabla-scroll">
        <table class="tabla-stats tabla-proveedores" id="tablaProveedores">
            <caption class="visually-hidden">Consolidado por proveedor: compras, margen generado y productos sin ventas en el periodo</caption>
            <thead>
                <tr>
                    <th scope="col">Proveedor</th>
                    <th scope="col" class="num">Comprado</th>
                    <th scope="col" class="num">Margen</th>
                    <th scope="col" class="num d-none d-md-table-cell">% margen</th>
                    <th scope="col" class="num">No rotan</th>
                    <th scope="col" class="num"><span class="visually-hidden">Detalle</span></th>
                </tr>
            </thead>
            <tbody id="tablaProveedoresCuerpo">
                <?php foreach ($prov_filas as $fila_prov): ?>
                    <?php
                    $pct_no_rotacion = (float)$fila_prov['pct_no_rotacion'];
                    $clase_no_rotacion = $pct_no_rotacion >= 50 ? 'margen-bajo' : ($pct_no_rotacion >= 20 ? 'margen-medio' : 'margen-alto');
                    $margen_pct_prov = $fila_prov['margen_pct'];
                    ?>
                    <tr data-proveedor="<?php echo (int)$fila_prov['id_proveedor']; ?>">
                        <td class="celda-producto">
                            <span class="prod-nombre"><?php echo htmlspecialchars($fila_prov['nombre_empresa']); ?></span>
                            <span class="prod-sku">
                                <?php echo (int)$fila_prov['productos_asignados']; ?> productos activos
                                <?php echo !empty($fila_prov['lead_time']) ? ' · ' . (int)$fila_prov['lead_time'] . ' días de entrega' : ''; ?>
                            </span>
                        </td>
                        <td class="num">
                            <?php echo format_bs((float)$fila_prov['monto_comprado'], $tasa_bcv ?? null); ?>
                            <span class="prod-sku"><?php echo $folio($fila_prov['unidades_compradas']); ?> uds</span>
                        </td>
                        <td class="num importe-margen">
                            <?php echo format_bs((float)$fila_prov['margen'], $tasa_bcv ?? null); ?>
                            <span class="prod-sku"><?php echo $folio($fila_prov['unidades_vendidas']); ?> uds vendidas</span>
                        </td>
                        <td class="num d-none d-md-table-cell">
                            <?php echo $margen_pct_prov !== null ? number_format((float)$margen_pct_prov, 1, ',', '.') . ' %' : '—'; ?>
                        </td>
                        <td class="num">
                            <span class="no-rotacion <?php echo $clase_no_rotacion; ?>"><?php echo number_format($pct_no_rotacion, 1, ',', '.'); ?> %</span>
                            <span class="prod-sku"><?php echo (int)$fila_prov['productos_sin_rotar']; ?> de <?php echo (int)$fila_prov['productos_asignados']; ?></span>
                        </td>
                        <td class="num">
                            <button type="button" class="btn-detalle-prov" data-proveedor="<?php echo (int)$fila_prov['id_proveedor']; ?>" aria-expanded="false" aria-controls="detalleProv-<?php echo (int)$fila_prov['id_proveedor']; ?>" title="Ver productos sin ventas de <?php echo htmlspecialchars($fila_prov['nombre_empresa']); ?>">
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                <span class="visually-hidden">Ver productos sin ventas</span>
                            </button>
                        </td>
                    </tr>
                    <tr class="detalle-prov d-none" id="detalleProv-<?php echo (int)$fila_prov['id_proveedor']; ?>" data-cargado="0">
                        <td colspan="6">
                            <div class="detalle-prov-cuerpo">
                                <p class="detalle-prov-titulo">Productos sin ventas en el periodo</p>
                                <div class="detalle-prov-lista" data-lista></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="tabla-vacia <?php echo $prov_filas === [] ? '' : 'd-none'; ?>" id="vacio-proveedores">
        <i class="bi bi-inbox" aria-hidden="true"></i>
        <span>No hay proveedores activos para los filtros seleccionados</span>
    </div>
    <div class="tabla-pie">
        <span class="tabla-resumen" id="provResumen"><?php echo $prov_filas === [] ? '0 proveedores' : 'Mostrando ' . count($prov_filas) . ' de ' . (int)$proveedores_tabla['total'] . ' proveedores'; ?></span>
        <nav class="tabla-paginacion" id="paginacionProveedores" aria-label="Paginación de proveedores" data-pagina="<?php echo (int)$proveedores_tabla['pagina']; ?>" data-paginas="<?php echo (int)$proveedores_tabla['paginas']; ?>">
            <button type="button" class="btn-pagina" data-pagina="<?php echo max(1, (int)$proveedores_tabla['pagina'] - 1); ?>" <?php echo (int)$proveedores_tabla['pagina'] <= 1 ? 'disabled' : ''; ?>>Anterior</button>
            <span class="pagina-indicador" id="provIndicador"><?php echo (int)$proveedores_tabla['pagina']; ?> / <?php echo (int)$proveedores_tabla['paginas']; ?></span>
            <button type="button" class="btn-pagina" data-pagina="<?php echo min((int)$proveedores_tabla['paginas'], (int)$proveedores_tabla['pagina'] + 1); ?>" <?php echo (int)$proveedores_tabla['pagina'] >= (int)$proveedores_tabla['paginas'] ? 'disabled' : ''; ?>>Siguiente</button>
        </nav>
    </div>
</div>
