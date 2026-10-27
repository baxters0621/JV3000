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

<section class="historial-header" aria-labelledby="historial-header-title">
    <div class="historial-header-wrapper">
        <div class="historial-header-brand">
            <i class="bi bi-clock-historial"></i>
        </div>
        <div class="historial-header-info">
            <h1 id="historial-header-title">Historial de Operaciones</h1>
            <p class="historial-header-subtitle">Compras y ventas registradas</p>
        </div>
    </div>
    <div class="historial-header-stats">
        <div class="historial-stat-item">
            <div class="historial-stat-number"><?php echo number_format($total_registros); ?></div>
            <div class="historial-stat-label">Operaciones</div>
        </div>
    </div>
</section>

<form class="historial-filtros" method="GET" action="<?php echo APP_URL_BASE; ?>index.php">
    <input type="hidden" name="url" value="historial">
    <div class="historial-filtro-busqueda">
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
    <div class="historial-filtros-secundarios">
        <div>
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
        <div>
            <label for="estadoOperacion">Estado</label>
            <select id="estadoOperacion" name="estado" class="input-jv historial-select">
                <option value="">Todos</option>
                <option value="Activa" <?php echo $filtro_estado === 'Activa' ? 'selected' : ''; ?>>Activas</option>
                <option value="Anulada" <?php echo $filtro_estado === 'Anulada' ? 'selected' : ''; ?>>Anuladas</option>
            </select>
        </div>
        <div>
            <label for="desdeOperacion">Desde</label>
            <input type="date" id="desdeOperacion" name="desde" class="input-jv historial-fecha" value="<?php echo htmlspecialchars($filtro_desde, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div>
            <label for="hastaOperacion">Hasta</label>
            <input type="date" id="hastaOperacion" name="hasta" class="input-jv historial-fecha" value="<?php echo htmlspecialchars($filtro_hasta, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="historial-acciones-filtro">
            <button type="submit" class="btn-jv-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrar</button>
            <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial" class="historial-limpiar">Limpiar</a>
        </div>
    </div>
    <?php if ($error_filtros !== ''): ?>
        <p class="historial-error-filtro" role="alert"><?php echo htmlspecialchars($error_filtros, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
</form>

<section class="historial-resultados" aria-label="Operaciones encontradas">
    <div class="historial-tabla-scroll">
        <table class="historial-tabla">
            <thead>
                <tr>
                    <th scope="col">Operación</th>
                    <th scope="col">Proveedor o cliente</th>
                    <th scope="col">Productos</th>
                    <th scope="col">Registró</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="historial-th-total">Total</th>
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
                        <td data-label="Operación">
                            <span class="historial-tipo"><i class="bi <?php echo $es_compra ? 'bi-box-arrow-in-down' : 'bi-bag-check'; ?>" aria-hidden="true"></i><?php echo $nombre_tipo; ?></span>
                            <strong class="historial-referencia">
                                <?php echo $es_compra ? 'Factura ' : ''; ?><?php echo htmlspecialchars((string)$operacion['referencia'], ENT_QUOTES, 'UTF-8'); ?>
                            </strong>
                        </td>
                        <td data-label="<?php echo $es_compra ? 'Proveedor' : 'Cliente'; ?>">
                            <span class="historial-celda-principal"><?php echo htmlspecialchars((string)$operacion['contraparte'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Productos">
                            <span class="historial-productos"><?php echo htmlspecialchars((string)($operacion['productos'] ?? 'Sin productos detallados'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="historial-unidades"><?php echo number_format((int)$operacion['cantidad_total']); ?> unidades</span>
                        </td>
                        <td data-label="Registró">
                            <span class="historial-celda-principal"><?php echo htmlspecialchars((string)$operacion['usuario'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <time class="historial-fecha" datetime="<?php echo $fecha_operacion ? date('Y-m-d\TH:i:s', $fecha_operacion) : ''; ?>">
                                <?php echo $fecha_operacion ? date('d/m/Y, H:i', $fecha_operacion) : 'Fecha no disponible'; ?>
                            </time>
                        </td>
                        <td data-label="Estado">
                            <span class="historial-estado <?php echo $operacion['estado'] === 'Anulada' ? 'historial-estado-anulada' : 'historial-estado-activa'; ?>">
                                <?php echo htmlspecialchars((string)$operacion['estado'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ($es_compra): ?>
                                <span class="historial-estado-secundario">
                                    Pago: <?php echo htmlspecialchars((string)($operacion['estado_pago'] ?? 'Pendiente'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="historial-estado-secundario">
                                    Recepción: <?php echo htmlspecialchars((string)($operacion['estado_recepcion'] ?? 'Pendiente'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Total" class="historial-total">
                            $<?php echo number_format((float)$operacion['monto_total'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($registros === []): ?>
                    <tr>
                        <td colspan="6" class="historial-vacio">
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