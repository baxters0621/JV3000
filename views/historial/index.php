<?php

/** @var array<string, string>|null $flash */
/** @var int $total_registros */
/** @var string $filtro_tipo */
/** @var string $filtro_estado */
/** @var string $filtro_desde */
/** @var string $filtro_hasta */
/** @var string $filtro_buscar */
/** @var string $error_filtros */
/** @var array<string, string> $tipos_disponibles */
/** @var array<int, array<string, mixed>> $registros */
/** @var int $total_paginas */
/** @var string $query_string */
/** @var int $page */
?>
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3">
        <?php echo htmlspecialchars($flash['texto'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="module-header historial-cabecera">
    <div class="module-header-icon historial-header-icono" aria-hidden="true">
        <i class="bi bi-clock-history"></i>
    </div>
    <div>
        <h1 id="historial-header-title" class="module-title">Historial de Operaciones</h1>
        <p class="module-subtitle">Compras y ventas registradas</p>
    </div>
    <div class="historial-conteo">
        <span class="historial-conteo-numero"><?php echo number_format($total_registros); ?></span>
        <span class="historial-conteo-label">operaciones</span>
    </div>
</div>

<form id="formFiltrosHistorial" class="historial-filtros card-jv p-4" method="GET" action="<?php echo APP_URL_BASE; ?>index.php">
    <input type="hidden" name="url" value="historial">
    <div class="row g-3 align-items-end">
        <div class="col-12 historial-filtro-busqueda">
            <label for="buscarOperaciones">Buscar operaciones</label>
            <div class="historial-busqueda-control">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    type="search"
                    id="buscarOperaciones"
                    name="buscar"
                    class="input-jv"
                    placeholder="Factura, proveedor, cliente, producto o usuario…"
                    value="<?php echo htmlspecialchars($filtro_buscar, ENT_QUOTES, 'UTF-8'); ?>"
                    maxlength="100"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="col-12 col-md-3">
            <label for="tipoOperacion">Tipo</label>
            <select id="tipoOperacion" name="tipo" class="input-jv historial-select">
                <option value="">Todas las operaciones</option>
                <?php foreach ($tipos_disponibles as $tipo_key => $tipo_nombre): ?>
                    <option value="<?php echo htmlspecialchars($tipo_key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtro_tipo === $tipo_key ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tipo_nombre, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="estadoOperacion">Estado</label>
            <select id="estadoOperacion" name="estado" class="input-jv historial-select">
                <option value="">Todos</option>
                <option value="Activa" <?php echo $filtro_estado === 'Activa' ? 'selected' : ''; ?>>Activas</option>
                <option value="Anulada" <?php echo $filtro_estado === 'Anulada' ? 'selected' : ''; ?>>Anuladas</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="desdeOperacion">Desde</label>
            <input type="date" id="desdeOperacion" name="desde" class="input-jv historial-fecha" value="<?php echo htmlspecialchars($filtro_desde, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="hastaOperacion">Hasta</label>
            <input type="date" id="hastaOperacion" name="hasta" class="input-jv historial-fecha" value="<?php echo htmlspecialchars($filtro_hasta, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12 col-md-2 historial-acciones-filtro">
            <button type="submit" class="btn-jv-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrar</button>
            <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial" class="btn btn-outline-secondary historial-limpiar">Limpiar</a>
        </div>
        <?php if ($error_filtros !== ''): ?>
            <div class="col-12">
                <p class="historial-error-filtro" role="alert"><?php echo htmlspecialchars($error_filtros, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</form>

<section class="historial-resultados" aria-label="Operaciones encontradas">
    <div class="table-responsive historial-tabla-scroll">
        <table class="historial-tabla">
            <colgroup>
                <col style="width:160px;">
                <col style="width:190px;">
                <col style="width:210px;">
                <col style="width:170px;">
                <col style="width:190px;">
                <col style="width:200px;">
                <col style="width:80px;">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">Operación</th>
                    <th scope="col">Proveedor o cliente</th>
                    <th scope="col">Productos</th>
                    <th scope="col">Registró</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="historial-th-total">Total</th>
                    <th class="historial-accion" scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $operacion): ?>
                    <?php
                        $es_compra = $operacion['tipo'] === 'compra';
                        $clase_tipo = $es_compra ? 'historial-tipo-compra' : 'historial-tipo-venta';
                        $nombre_tipo = $es_compra ? 'Compra' : 'Venta';
                        $fecha_operacion = strtotime((string)$operacion['fecha']);
                    ?>
                    <tr class="historial-fila <?php echo $clase_tipo; ?>">
                        <td class="historial-operacion-cell" data-label="Operación">
                            <span class="historial-tipo"><i class="bi <?php echo $es_compra ? 'bi-box-arrow-in-down' : 'bi-bag-check'; ?>" aria-hidden="true"></i><?php echo $nombre_tipo; ?></span>
                            <strong class="historial-referencia">
                                <?php echo $es_compra ? 'Factura ' : ''; ?><?php echo htmlspecialchars((string)$operacion['referencia'], ENT_QUOTES, 'UTF-8'); ?>
                            </strong>
                        </td>
                        <td class="historial-contraparte-cell" data-label="<?php echo $es_compra ? 'Proveedor' : 'Cliente'; ?>">
                            <span class="historial-celda-principal historial-contraparte" title="<?php echo htmlspecialchars((string)$operacion['contraparte'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$operacion['contraparte'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td class="historial-productos-cell" data-label="Productos">
                            <span class="historial-productos"><?php echo htmlspecialchars((string)($operacion['productos'] ?? 'Sin productos detallados'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="historial-unidades badge"><?php echo number_format((int)$operacion['cantidad_total']); ?> unidades</span>
                        </td>
                        <td class="historial-registrado-cell" data-label="Registró">
                            <span class="historial-celda-principal historial-usuario" title="<?php echo htmlspecialchars((string)$operacion['usuario'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$operacion['usuario'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <time class="historial-fecha" datetime="<?php echo $fecha_operacion ? date('Y-m-d\TH:i:s', $fecha_operacion) : ''; ?>">
                                <?php echo $fecha_operacion ? date('d/m/Y, H:i', $fecha_operacion) : 'Fecha no disponible'; ?>
                            </time>
                        </td>
                        <td class="historial-estado-cell" data-label="Estado">
                            <div class="historial-estado-grupo">
                                <span class="historial-estado <?php echo $operacion['estado'] === 'Anulada' ? 'historial-estado-anulada' : 'historial-estado-activa'; ?>">
                                    <?php echo htmlspecialchars((string)$operacion['estado'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php if ($es_compra): ?>
                                    <span class="historial-estado-secundario <?php echo ($operacion['estado_pago'] ?? '') === 'Pagada' ? 'historial-badge-ok' : 'historial-badge-pendiente'; ?>">
                                        Pago: <?php echo htmlspecialchars((string)($operacion['estado_pago'] ?? 'Pendiente'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="historial-estado-secundario <?php echo ($operacion['estado_recepcion'] ?? '') === 'Completa' ? 'historial-badge-ok' : 'historial-badge-pendiente'; ?>">
                                        Recepción: <?php echo htmlspecialchars((string)($operacion['estado_recepcion'] ?? 'Pendiente'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Total" class="historial-total <?php echo $es_compra ? 'historial-total-compra' : 'historial-total-venta'; ?>">
                            $<?php echo number_format((float)$operacion['monto_total'], 2); ?>
                        </td>
                        <td data-label="Acciones" class="historial-accion-cell">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetalleOperacion" data-operacion="<?php echo htmlspecialchars((string)$operacion['id_operacion'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ver detalle de <?php echo htmlspecialchars((string)$operacion['referencia'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($registros === []): ?>
                    <tr>
                        <td colspan="7" class="historial-vacio">
                            <i class="bi bi-receipt" aria-hidden="true"></i>
                            <strong>No hay operaciones para mostrar</strong>
                            <span>Prueba con otro tipo, estado o rango de fechas.</span>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($total_paginas > 1): ?>
    <nav class="historial-paginacion" aria-label="Paginación del historial">
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial&page=<?php echo max(1, $page - 1); ?><?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" aria-label="Página anterior">&lsaquo;</a>
        <?php for ($numero_pagina = max(1, $page - 2); $numero_pagina <= min($total_paginas, $page + 2); $numero_pagina++): ?>
            <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial&page=<?php echo $numero_pagina; ?><?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $numero_pagina === $page ? 'active' : ''; ?>" <?php echo $numero_pagina === $page ? 'aria-current="page"' : ''; ?>>
                <?php echo $numero_pagina; ?>
            </a>
        <?php endfor; ?>
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial&page=<?php echo min($total_paginas, $page + 1); ?><?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $page >= $total_paginas ? 'disabled' : ''; ?>" aria-label="Página siguiente">&rsaquo;</a>
    </nav>
<?php endif; ?>