<?php

/** @var array<string, mixed>|null $solicitud_prefill */
/** @var array<string, mixed>|null $flash */
/** @var array<string, mixed> $kpis */
/** @var string $filtro_pago */
/** @var array<int, array<string, mixed>> $compras */
/** @var bool $es_admin */
/** @var string $csrf */
/** @var array<int, array<string, mixed>> $proveedores */
/** @var float|int $iva_pct */
/** @var array<int, array<string, mixed>> $prov_gestion Listado completo para el gestor integrado */
/** @var int $prov_activos */
/** @var array<int, array<int, array<string, mixed>>> $prov_catalogo Mapa id_proveedor => entradas */
/** @var array<int, array<string, mixed>> $productos_activos */
/** @var array<int, array<string, mixed>> $solicitudes_pendientes Solicitudes de reposición por atender */
/** @var array<int, array<string, mixed>> $compras_pendientes Compras pendientes de recepción */
/** @var int $unidades_por_recibir Unidades pendientes por recibir */
/** @var array<int, array<string, mixed>> $recepciones Últimas recepciones registradas */
/** @var float|null $tasa_bcv Tasa oficial USD→VES del día */
/** @var string|null $tasa_bcv_fecha Fecha de la tasa BCV (Y-m-d) */
/** @var bool $tasa_bcv_manual Indica si la tasa mostrada fue fijada a mano */
/** @var array{usd:?float,fecha:?string} $tasa_bcv_manual_guardada Tasa manual almacenada */
/** @var float|null $tasa_bcv_referencia Última tasa conocida para validar la manual */

// ==========================================
// VISTA: Compras (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
$tasa_bcv_vis = isset($tasa_bcv) && (float)$tasa_bcv > 0 ? (float)$tasa_bcv : 0.0;
$tasa_bcv_es_manual = !empty($tasa_bcv_manual);
$tasa_manual_guardada_vis = isset($tasa_bcv_manual_guardada['usd']) && (float)$tasa_bcv_manual_guardada['usd'] > 0
    ? (float)$tasa_bcv_manual_guardada['usd']
    : 0.0;
$tasa_bcv_ref_vis = isset($tasa_bcv_referencia) && (float)$tasa_bcv_referencia > 0
    ? (float)$tasa_bcv_referencia
    : 0.0;
$purchaseListUrl = APP_URL_BASE . 'index.php?url=compras';
?>
<!-- Encabezado -->
<div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 header-card">
    <div class="d-flex align-items-center gap-3">
        <div class="com-header-icon">
            <i class="bi bi-truck"></i>
        </div>
        <div>
            <h1 class="module-title">COMPRAS</h1>
            <p class="module-subtitle">&Oacute;rdenes de Compra y Reposici&oacute;n</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-jv-primary module-action-btn" onclick="abrirGestorProv()" data-tooltip="Gestionar proveedores registrados y sus catálogos de costos">
            <i class="bi bi-building me-1"></i>PROVEEDORES
        </button>
        <button class="btn btn-jv-success pulse-jv module-action-btn" data-bs-toggle="modal" data-bs-target="#modalCompra">
            <i class="bi bi-plus-lg me-1"></i>NUEVA COMPRA
        </button>
    </div>
</div>

<!-- Banner: atendiendo solicitud -->
<?php if (!empty($solicitud_prefill)): ?>
    <div class="alert-jv alert-jv-info mb-3 px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2" id="bannerSolicitud">
        <span>
            <i class="bi bi-cart-check me-2"></i>
            <strong>ATENDIENDO SOLICITUD #<?php echo (int)$solicitud_prefill['id_solicitud']; ?></strong>
            — <?php echo htmlspecialchars($solicitud_prefill['motivo'] ?? 'Solicitud de reposición'); ?>
            <small class="d-block mt-1" style="opacity:.8;">Los productos ya fueron precargados en el formulario.</small>
        </span>
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=compras/cancelar_solicitud" class="btn btn-sm btn-jv-danger" style="padding:7px 16px;font-size:.8rem;">
            <i class="bi bi-x-lg me-1"></i>CANCELAR
        </a>
    </div>
<?php endif; ?>

<!-- Mensajes flash (id/data-texto permiten al JS de proveedores marcar el campo con error) -->
<?php if (!empty($flash)): ?>
    <?php $actualiza_dashboard = ($flash['tipo'] ?? '') === 'success' && in_array($flash['origen'] ?? '', ['compra_crear', 'compra_anular', 'compra_pago', 'compra_recepcion'], true); ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3" id="flashMsg" data-texto="<?php echo htmlspecialchars($flash['texto']); ?>"<?php echo $actualiza_dashboard ? ' data-actualiza-dashboard="1"' : ''; ?>>
        <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Estadísticas / Widgets -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="widget-card widget-green">
            <div class="widget-icon widget-icon-green">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="widget-label">Total Compras</div>
                <div class="widget-value"><?php echo (int)$kpis['total_compras']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="widget-card widget-amber">
            <div class="widget-icon widget-icon-amber">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="widget-label">Por Pagar</div>
                <div class="widget-value"><?php echo (int)$kpis['por_pagar']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="widget-card widget-blue">
            <div class="widget-icon widget-icon-blue">
                <i class="bi bi-<?php echo $tasa_bcv_vis > 0 ? 'cash-stack' : 'currency-dollar'; ?>"></i>
            </div>
            <div>
                <div class="widget-label">Invertido (Mes)</div>
                <div class="widget-value" id="widgetInvertidoMes" data-usd="<?php echo htmlspecialchars((string)(float)$kpis['invertido_mes'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo format_bs((float)$kpis['invertido_mes'], $tasa_bcv_vis ?: null); ?></div>
            </div>
        </div>
    </div>
</div>

<?php
// Etiqueta de tasa BCV: obligatoria por normativa, pero discreta.
$tasa_bcv_fecha_legible = '';
if (!empty($tasa_bcv_fecha)) {
    $marca_tasa = strtotime((string)$tasa_bcv_fecha);
    $tasa_bcv_fecha_legible = $marca_tasa ? date('d/m/Y', $marca_tasa) : '';
}
?>
<div class="comp-tasa-bcv-wrap text-end">
    <span class="comp-tasa-bcv<?php echo $tasa_bcv_es_manual ? ' es-manual' : ''; ?>" id="tasaBcvChip">
        <i class="bi bi-bank"></i>
        <span id="tasaBcvLectura">
            <?php if ($tasa_bcv_vis > 0): ?>
                Tasa BCV<?php echo $tasa_bcv_es_manual ? ' (manual)' : ''; ?>:
                <strong><?php echo jv_formatear_bolivares($tasa_bcv_vis, 2); ?></strong><?php echo $tasa_bcv_fecha_legible !== '' ? ' &middot; ' . $tasa_bcv_fecha_legible : ''; ?>
            <?php else: ?>
                Tasa BCV no disponible
            <?php endif; ?>
        </span>
        <button type="button" class="comp-tasa-bcv-edit" data-bs-toggle="modal" data-bs-target="#modalTasaBcv" aria-label="Fijar tasa BCV" data-tooltip="Fijar tasa BCV"><i class="bi bi-pencil"></i></button>
    </span>
</div>

<!-- Solicitudes de reposición pendientes (integradas en Compras) -->
<?php if (!empty($solicitudes_pendientes)): ?>
    <div class="card-jv card-jv-table p-0 mb-4 comp-sol-card">
        <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper flex-wrap">
            <i class="bi bi-clipboard-check me-1" style="font-size:1rem;color:#7C3AED;"></i>
            <span class="fw-bold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--jv-navy);">
                Solicitudes de Reposición Pendientes
            </span>
            <span class="badge-jv badge-primary ms-1"><?php echo count($solicitudes_pendientes); ?></span>
            <span class="ms-auto"></span>
            <span class="small text-secondary fw-semibold"><i class="bi bi-info-circle me-1"></i>Se generan desde Ventas cuando un producto queda sin stock</span>
        </div>
        <div class="table-responsive">
            <table class="table-jv comp-sol-table mb-0">
                <colgroup>
                    <col class="comp-sol-col-numero">
                    <col class="comp-sol-col-solicitante">
                    <col class="comp-sol-col-motivo">
                    <col class="comp-sol-col-productos">
                    <col class="comp-sol-col-unidades">
                    <col class="comp-sol-col-fecha">
                    <col class="comp-sol-col-acciones">
                </colgroup>
                <thead>
                    <tr>
                        <th>Solicitud</th>
                        <th>Solicitante</th>
                        <th>Motivo</th>
                        <th class="text-center comp-sol-cantidad">Productos</th>
                        <th class="text-center comp-sol-cantidad">Unidades</th>
                        <th>Fecha</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody id="tablaSolicitudes">
                    <?php foreach ($solicitudes_pendientes as $sol_pend): ?>
                        <tr>
                            <td class="sol-celda-numero" data-label="Solicitud" style="vertical-align:middle;text-align:center;"><span class="codigo-badge sol-num-badge"><?php echo '#' . (int)$sol_pend['id_solicitud']; ?></span></td>
                            <td class="sol-celda-solicitante text-uppercase fw-bold small" data-label="Solicitante"><?php echo htmlspecialchars($sol_pend['solicitante']); ?></td>
                            <td class="sol-celda-motivo" data-label="Motivo" style="color:var(--jv-text-secondary);"><?php echo htmlspecialchars($sol_pend['motivo'] ?? 'Solicitud de reposición'); ?></td>
                            <td class="sol-celda-productos text-center" data-label="Productos"><span class="cant-badge"><?php echo (int)$sol_pend['num_productos']; ?></span></td>
                            <td class="sol-celda-unidades text-center fw-bold" data-label="Unidades"><?php echo (int)$sol_pend['total_unidades']; ?></td>
                            <td class="sol-celda-fecha small" data-label="Fecha"><?php echo date('d/m/Y H:i', strtotime($sol_pend['fecha_solicitud'])); ?></td>
                            <td class="sol-celda-acciones text-center" data-label="Acción">
                                <div class="sol-acciones-grupo">
                                    <a href="<?php echo APP_URL_BASE; ?>index.php?url=compras&atender_solicitud=<?php echo (int)$sol_pend['id_solicitud']; ?>" class="btn btn-sm btn-jv-success comp-sol-atender" data-tooltip="Atender esta solicitud creando la compra">
                                        <i class="bi bi-cart-check"></i><span>Atender</span>
                                    </a>
                                    <button type="button" class="btn-action comp-sol-cancelar" onclick="confirmarCancelarSolicitud(<?php echo (int)$sol_pend['id_solicitud']; ?>)" data-tooltip="Cancelar solicitud" aria-label="Cancelar solicitud"><i class="bi bi-x-circle"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Tabla de compras -->
<div class="card-jv card-jv-table p-0">
    <div class="d-flex align-items-center gap-3 px-3 py-2 buscador-wrapper compras-list-toolbar">
        <div class="compras-search">
            <i class="bi bi-search"></i>
            <input type="text" class="input-jv" placeholder="Buscar por factura, proveedor, productos..." id="buscar" aria-label="Buscar compra" onkeyup="filtrar()">
        </div>
        <select class="input-jv" id="filtroPago" aria-label="Filtrar por estado de pago" onchange="window.location='<?php echo $purchaseListUrl; ?>&filtro_pago='+this.value">
            <option value="">Todos los pagos</option>
            <option value="Pendiente" <?php echo $filtro_pago === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
            <option value="Parcial" <?php echo $filtro_pago === 'Parcial' ? 'selected' : ''; ?>>Parcial</option>
            <option value="Pagada" <?php echo $filtro_pago === 'Pagada' ? 'selected' : ''; ?>>Pagada</option>
        </select>
    </div>
    <div class="table-responsive compras-list-responsive">
        <table class="table-jv mb-0 compras-list-table">
            <colgroup>
                <col class="col-factura">
                <col class="col-proveedor">
                <col class="col-detalle">
                <col class="col-total">
                <col class="col-pago">
                <col class="col-fecha">
                <col class="col-acciones">
            </colgroup>
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Proveedor</th>
                    <th>Detalle</th>
                    <th>Total</th>
                    <th class="d-none d-xl-table-cell">Pago</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaEntradas">
                <?php if (count($compras) > 0): foreach ($compras as $compra): ?>
                        <tr>
                            <td class="td-factura" data-label="Factura">
                                <span class="factura-texto text-truncate" data-tooltip="<?php echo htmlspecialchars('Factura: ' . ($compra['nro_factura'] ?: '-') . ' · Control: ' . ($compra['nro_control'] ?: 'Sin control'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($compra['nro_factura'] ?: '-'); ?></span>
                            </td>
                            <td class="td-proveedor" data-label="Proveedor">
                                <span class="proveedor-nombre text-truncate" data-tooltip="<?php echo htmlspecialchars($compra['proveedor'] ?? 'S/P', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($compra['proveedor'] ?? 'S/P'); ?></span>
                            </td>
                            <td class="td-detalle" data-label="Detalle">
                                <div class="detalle-celda d-flex justify-content-between align-items-center gap-2">
                                    <span class="detalle-nombres text-truncate" data-tooltip="<?php echo htmlspecialchars($compra['productos_list'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($compra['productos_list'] ?? '-'); ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-detalle-compra flex-shrink-0" data-id-compra="<?php echo (int)$compra['id_compra']; ?>" data-tooltip="Ver detalle de la factura" aria-label="Ver detalle de la factura N° <?php echo htmlspecialchars($compra['nro_factura'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-eye me-1" aria-hidden="true"></i>Ver Detalle</button>
                                </div>
                            </td>
                            <td class="td-total text-end" data-label="Total">
                                <span class="total-monto" data-usd="<?php echo htmlspecialchars((string)(float)$compra['total'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo format_bs((float)$compra['total'], $tasa_bcv_vis ?: null); ?></span>
                            </td>
                            <td class="text-center d-none d-xl-table-cell" data-label="Pago">
                                <?php $status_pago = $compra['status_pago'] ?? 'Pendiente'; ?>
                                <span class="badge-jv <?php echo $status_pago === 'Pagada' ? 'badge-success' : 'badge-warning'; ?>"><i class="bi <?php echo $status_pago === 'Pagada' ? 'bi-check-circle' : 'bi-hourglass-split'; ?> me-1"></i><?php echo $status_pago; ?></span>
                                <?php if ($status_pago !== 'Pagada' && (float)($compra['saldo_pendiente'] ?? 0) > 0): ?>
                                    <div class="mt-1" style="font-size:.75rem;color:var(--jv-red);">Saldo: <?php echo format_bs((float)$compra['saldo_pendiente'], $tasa_bcv_vis ?: null); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="td-fecha" data-label="Fecha"><?php echo date('d/m/Y', strtotime($compra['fecha_compra'])); ?></td>
                            <td class="text-center" data-label="Acciones">
                                <div class="compra-actions">
                                    <?php if ($status_pago !== 'Pagada' && (float)($compra['saldo_pendiente'] ?? 0) > 0): ?>
                                        <button type="button" class="btn-action" style="color:var(--jv-green);border-color:var(--jv-green);" onclick="abrirModalPago(<?php echo (int)$compra['id_compra']; ?>, '<?php echo htmlspecialchars(addslashes($compra['nro_factura'] ?? ''), ENT_QUOTES); ?>', <?php echo (float)$compra['total']; ?>, <?php echo (float)($compra['saldo_pendiente'] ?? 0); ?>)" data-tooltip="Registrar pago" aria-label="Registrar pago"><i class="bi bi-cash-coin"></i></button>
                                    <?php endif; ?>
                                    <?php if ($es_admin): ?>
                                        <?php $compra_recibida = in_array($compra['estado_recepcion'] ?? 'Pendiente', ['Parcial', 'Completa'], true); ?>
                                        <button type="button" class="btn-action btn-anular-compra" onclick="confirmarEliminar(<?php echo (int)$compra['id_compra']; ?>)" data-tooltip="<?php echo $compra_recibida ? 'No se puede anular: ya se recibieron productos.' : 'Anular compra'; ?>" aria-label="<?php echo $compra_recibida ? 'Anulación no disponible: mercancía recibida' : 'Anular compra'; ?>" <?php echo $compra_recibida ? 'disabled' : ''; ?>><i class="bi bi-slash-circle"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr class="compras-empty-row">
                        <td colspan="7">
                            <div class="estado-vacio">
                                <i class="bi bi-cart-x"></i>
                                <span>No hay compras registradas</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Compras pendientes de recepción (integradas en Compras) -->
<?php if (!empty($compras_pendientes)): ?>
    <div class="card-jv card-jv-table p-0 mb-4 comp-rec-card">
        <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper flex-wrap">
            <i class="bi bi-box-arrow-in-down me-1" style="font-size:1rem;color:#D97706;"></i>
            <span class="fw-bold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--jv-navy);">Compras Pendientes de Recepción</span>
            <span class="badge-jv badge-warning ms-1"><?php echo count($compras_pendientes); ?> compra(s)</span>
            <span class="small text-secondary fw-semibold ms-1"><i class="bi bi-boxes me-1"></i><?php echo number_format($unidades_por_recibir, 0); ?> unds.</span>
            <span class="ms-auto"></span>
            <i class="bi bi-search me-1" style="font-size:1rem;color:#D97706;"></i>
            <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar pendientes..." id="buscarPendientes" onkeyup="filtrarPendientes()" style="box-shadow:none;font-size:.95rem;padding:8px 6px;max-width:240px;">
        </div>
        <div class="table-responsive">
            <table class="table-jv rec-table mb-0">
                <thead>
                    <tr>
                        <th style="width:12%;">Factura</th>
                        <th class="d-none d-xl-table-cell" style="width:10%;">Nro. Control</th>
                        <th style="width:17%;">Proveedor</th>
                        <th class="text-center d-none d-xl-table-cell" style="width:7%;">Items</th>
                        <th class="text-center d-none d-xl-table-cell" style="width:8%;">Unds</th>
                        <th class="text-center" style="width:11%;">Estado</th>
                        <th style="width:8%;">Fecha</th>
                        <th class="text-center" style="width:130px;">Acción</th>
                    </tr>
                </thead>
                <tbody id="tablaPendientes">
                    <?php foreach ($compras_pendientes as $compra_pendiente): ?>
                        <tr>
                            <td style="vertical-align:middle;text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($compra_pendiente['nro_factura']); ?></span></td>
                            <td class="d-none d-xl-table-cell" style="color:var(--jv-text-muted);font-weight:600;"><?php echo htmlspecialchars($compra_pendiente['nro_control'] ?: '-'); ?></td>
                            <td class="td-proveedor text-uppercase fw-bold" data-tooltip="<?php echo htmlspecialchars($compra_pendiente['proveedor'] ?? 'S/P'); ?>"><?php echo htmlspecialchars($compra_pendiente['proveedor'] ?? 'S/P'); ?></td>
                            <td class="text-center d-none d-xl-table-cell"><span class="cant-badge"><?php echo (int)$compra_pendiente['items_pend']; ?></span></td>
                            <td class="text-center fw-bold d-none d-xl-table-cell"><?php echo (int)$compra_pendiente['unidades_pend']; ?></td>
                            <td class="text-center">
                                <?php $estado_recepcion = $compra_pendiente['estado_recepcion']; ?>
                                <span class="badge-jv <?php echo $estado_recepcion === 'Parcial' ? 'badge-info' : 'badge-warning'; ?>"><i class="bi <?php echo $estado_recepcion === 'Parcial' ? 'bi-arrow-repeat' : 'bi-hourglass-split'; ?> me-1"></i><?php echo $estado_recepcion; ?></span>
                            </td>
                            <td class="fecha-cell"><?php echo date('d/m/Y', strtotime($compra_pendiente['fecha_compra'])); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn comp-rec-btn w-100" onclick="abrirRecepcion(<?php echo $compra_pendiente['id_compra']; ?>)" data-tooltip="Registrar la recepción de esta mercancía">
                                    <i class="bi bi-box-arrow-in-down me-1"></i>RECIBIR
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Actividad reciente de recepciones -->
<section class="card-jv p-0 mt-4 mb-4 comp-rec-feed" aria-labelledby="compRecFeedTitle">
    <div class="comp-rec-feed-header">
        <span class="comp-rec-feed-heading-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
        <h2 class="comp-rec-feed-title" id="compRecFeedTitle">Actividad reciente</h2>
    </div>
    <?php if (!empty($recepciones)): ?>
        <ul class="list-group list-group-flush comp-rec-feed-list">
            <?php foreach ($recepciones as $recepcion): ?>
                <?php
                $marca_tiempo = strtotime($recepcion['fecha_movimiento'] ?? '');
                $fecha_corta = $marca_tiempo !== false && date('Y-m-d', $marca_tiempo) === date('Y-m-d')
                    ? 'Hoy, ' . date('H:i', $marca_tiempo)
                    : ($marca_tiempo !== false ? date('d/m', $marca_tiempo) : '—');
                ?>
                <li class="list-group-item comp-rec-feed-item d-flex align-items-center">
                    <span class="comp-rec-feed-icon me-3" aria-hidden="true"><i class="bi bi-check2"></i></span>
                    <div class="comp-rec-feed-content">
                        <p class="comp-rec-feed-summary">
                            Se recibieron <strong>+<?php echo (int)$recepcion['unidades']; ?> unidades</strong> de
                            <strong class="comp-rec-feed-supplier"><?php echo htmlspecialchars($recepcion['proveedor'] ?? 'Proveedor sin nombre'); ?></strong>
                        </p>
                        <p class="comp-rec-feed-invoice">Factura <?php echo htmlspecialchars($recepcion['nro_factura'] ?? '-'); ?></p>
                    </div>
                    <time class="comp-rec-feed-date"<?php if ($marca_tiempo !== false): ?> datetime="<?php echo htmlspecialchars(date('c', $marca_tiempo)); ?>"<?php endif; ?>>
                        <?php echo htmlspecialchars($fecha_corta); ?>
                    </time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="comp-rec-feed-empty">Las recepciones completadas aparecerán aquí.</p>
    <?php endif; ?>
    <div class="comp-rec-feed-footer">
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=historial">Ver todo el historial de operaciones <span aria-hidden="true">→</span></a>
    </div>
</section>

<!-- Modal: Registrar compra -->
<script>
    window.JV_CATALOGO = <?php echo $catalogo_productos; ?>;
</script>
<div class="modal fade" id="modalCompra" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form method="POST" id="formCompra" data-borrador="compras_alta" data-borrador-restaurar-automatico data-borrador-campos-relevantes="id_proveedor,nro_factura,nro_control,observaciones">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_compra" value="registrar">
                <input type="hidden" name="productos_data" id="productosData">
                <?php if (!empty($solicitud_prefill)): ?>
                    <input type="hidden" name="id_solicitud" id="idSolicitud" value="<?php echo (int)$solicitud_prefill['id_solicitud']; ?>">
                <?php endif; ?>

                <!-- Header verdegradado -->
                <div class="comp-modal-header">
                    <div class="comp-modal-header-content">
                        <div class="comp-modal-icon"><i class="bi bi-cart-plus"></i></div>
                        <div>
                            <h5 class="comp-modal-title">Nueva Compra</h5>
                            <p class="comp-modal-subtitle">Registra una compra indicando el proveedor, factura y productos</p>
                        </div>
                    </div>
                    <button type="button" class="prov-close-btn" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>

                <?php if (!empty($solicitud_prefill)): ?>
                    <div class="comp-alert-solicitud">
                        <i class="bi bi-cart-check me-2"></i>
                        <strong>Solicitud #<?php echo (int)$solicitud_prefill['id_solicitud']; ?></strong>
                        — <?php echo htmlspecialchars($solicitud_prefill['motivo'] ?? 'Solicitud de reposición'); ?>. Los productos ya est&aacute;n precargados en el formulario.
                    </div>
                <?php endif; ?>

                <div class="comp-modal-body">

                    <!-- PASO 1: Proveedor -->
                    <div class="comp-step">
                        <div class="comp-step-header">
                            <div class="comp-step-chip chip-green">1</div>
                            <div class="comp-step-info">
                                <div class="comp-step-title">Seleccionar Proveedor</div>
                                <div class="comp-step-desc">Indica qui&eacute;n te est&aacute; suministrando la mercanc&iacute;a</div>
                            </div>
                            <i class="bi bi-question-circle comp-step-help" data-tooltip="El proveedor debe estar registrado y activo. El RIF se completa autom&aacute;ticamente al seleccionarlo."></i>
                        </div>
                        <div class="comp-step-body">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="comp-label">Proveedor <span class="text-danger">*</span></label>
                                    <select name="id_proveedor" class="input-jv comp-input" id="selProveedor" aria-label="Proveedor de la compra" required>
                                        <option value="">Selecciona un proveedor...</option>
                                        <?php foreach ($proveedores as $proveedor): ?>
                                            <option value="<?php echo (int)$proveedor['id_proveedor']; ?>" data-rif="<?php echo htmlspecialchars($proveedor['rif']); ?>">
                                                <?php echo htmlspecialchars($proveedor['nombre_empresa']); ?> (<?php echo htmlspecialchars($proveedor['rif']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="comp-label">RIF del Proveedor</label>
                                    <input type="text" class="input-jv comp-input" id="displayRif" aria-label="RIF del proveedor" value="—" readonly disabled>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 2: Factura -->
                    <div class="comp-step">
                        <div class="comp-step-header">
                            <div class="comp-step-chip chip-blue">2</div>
                            <div class="comp-step-info">
                                <div class="comp-step-title">Datos de la Factura</div>
                                <div class="comp-step-desc">Informaci&oacute;n fiscal del comprobante recibido</div>
                            </div>
                            <i class="bi bi-question-circle comp-step-help" data-tooltip="Ambos campos son obligatorios. La factura y el control deben coincidir con los datos impressos en el comprobante fiscal."></i>
                        </div>
                        <div class="comp-step-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="comp-label">N&uacute;mero de Factura <span class="text-danger">*</span></label>
                                    <input type="text" inputmode="numeric" name="nro_factura" aria-label="Numero de factura" class="input-jv comp-input" placeholder="Ej: 00001234 (6 a 8 d&iacute;gitos)" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,8)" maxlength="8" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="comp-label">N&uacute;mero de Control <span class="text-danger">*</span></label>
                                    <input type="text" name="nro_control" aria-label="Numero de control" class="input-jv comp-input" value="" placeholder="00-00000000" oninput="var v=this.value.replace(/[^0-9]/g,'');if(v.length>10)v=v.slice(0,10);if(v.length>2)v=v.slice(0,2)+'-'+v.slice(2);this.value=v" maxlength="11" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="comp-label">Fecha de Compra</label>
                                    <input type="date" name="fecha_compra" id="fechaCompra" class="input-jv comp-input" value="<?php echo date('Y-m-d'); ?>" min="1970-01-01" max="<?php echo date('Y-m-d'); ?>" aria-label="Fecha de la compra" required>
                                </div>
                                <div class="col-12">
                                    <label class="comp-label" for="compObservaciones">Observaciones</label>
                                    <div class="comp-obs-row">
                                        <i class="bi bi-chat-text" aria-hidden="true"></i>
                                        <input type="text" name="observaciones" id="compObservaciones" aria-label="Observaciones de la compra" class="input-jv" placeholder="Observaciones opcionales...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 3: Productos -->
                    <div class="comp-step comp-step-main">
                        <div class="comp-step-header">
                            <div class="comp-step-chip chip-orange">3</div>
                            <div class="comp-step-info">
                                <div class="comp-step-title">Agregar Productos</div>
                                <div class="comp-step-desc">Elige un producto del cat&aacute;logo del proveedor, indica sus datos y agr&eacute;galo al carrito</div>
                            </div>
                            <i class="bi bi-question-circle comp-step-help" data-tooltip="La lista contiene solo productos asociados al proveedor seleccionado. Si falta uno, agr&eacute;galo a su cat&aacute;logo en Gesti&oacute;n de proveedores."></i>
                        </div>
                        <div class="comp-step-body">

                            <!-- Toolbar de agregar -->
                            <div class="comp-add-row">
                                <div class="row g-3 align-items-end comp-add-grid">
                                    <div class="col-md-4 comp-add-product">
                                        <label class="comp-label-sm" for="selProductoProveedor">Producto del proveedor</label>
                                        <div class="comp-catalog-status" id="catalogoProveedorEstado" role="status" aria-live="polite">Selecciona un proveedor para ver sus productos.</div>
                                        <select class="input-jv" id="selProductoProveedor" aria-label="Producto del catálogo del proveedor" disabled>
                                            <option value="">Selecciona primero un proveedor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1 comp-add-field">
                                        <label class="comp-label-sm" for="inputCant">Cantidad</label>
                                        <input type="number" class="input-jv" id="inputCant" aria-label="Cantidad del producto" value="1" min="1" max="999999" step="1">
                                    </div>
                                    <div class="col-md-3 comp-add-field">
                                        <label class="comp-label-sm" for="inputPrecio">Precio Unitario del Catálogo (Bs.)</label>
                                        <input type="text" class="input-jv" id="inputPrecio" aria-label="Precio unitario establecido por el proveedor" placeholder="Selecciona un producto" readonly aria-readonly="true">
                                    </div>
                                    <div class="col-md-2 comp-add-field">
                                        <label class="comp-label-sm" for="inputVencimiento">Fecha de Vencimiento <span class="text-danger">*</span></label>
                                        <input type="date" class="input-jv" id="inputVencimiento" aria-label="Fecha de vencimiento del lote" required>
                                    </div>
                                    <div class="col-md-2 comp-add-action">
                                        <button type="button" class="comp-add-btn" onclick="agregarProducto()" aria-label="Agregar producto al carrito" data-tooltip="Agregar producto al carrito">
                                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de productos -->
                            <div class="comp-cart-table table-responsive">
                                <table class="table-jv mb-0">
                                    <colgroup>
                                        <col style="width:6%;">
                                        <col style="width:32%;">
                                        <col style="width:10%;">
                                        <col style="width:14%;">
                                        <col style="width:16%;">
                                        <col style="width:16%;">
                                        <col style="width:6%;">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Producto</th>
                                            <th class="text-center">Cant</th>
                                            <th class="text-end">Precio</th>
                                            <th class="text-center">Vence</th>
                                            <th class="text-end">Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productosBody">
                                        <tr id="filaVacia">
                                            <td colspan="7" class="comp-empty-cart">
                                                <i class="bi bi-cart-x"></i>
                                                <span>Busque un producto y presione <strong>+</strong> para agregarlo</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Resumen -->
                            <div class="comp-cart-summary">
                                <div class="comp-summary-item text-end">
                                    <span class="comp-summary-label">Productos</span>
                                    <span class="comp-summary-value" id="totalItems">0</span>
                                </div>
                                <div class="comp-summary-item text-end">
                                    <span class="comp-summary-label">Subtotal</span>
                                    <span class="comp-summary-value" id="totalSubtotal">Bs. 0</span>
                                </div>
                                <div class="comp-summary-item text-end">
                                    <span class="comp-summary-label">IVA (<?php echo $iva_pct; ?>%)</span>
                                    <span class="comp-summary-value" id="totalIva">Bs. 0</span>
                                </div>
                                <div class="comp-summary-item comp-summary-total text-end">
                                    <span class="comp-summary-label">Total</span>
                                    <span class="comp-summary-value comp-total-big fs-5" id="totalCosto">Bs. 0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="comp-modal-footer">
                    <button type="button" class="btn btn-jv-danger comp-btn-footer" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                    <button type="button" class="btn btn-jv-success comp-btn-footer comp-btn-save" id="btnGuardar" disabled onclick="validarFormulario(this)"><i class="bi bi-check-lg me-1"></i> Guardar Compra</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Registrar Pago -->
<div class="modal fade" id="modalPago" tabindex="-1" aria-labelledby="modalPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width:520px;">
        <div class="modal-content jv-modal-content">
            <div class="jv-modal-header" style="background:linear-gradient(135deg,#059669,#10b981);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cash-coin" style="font-size:1.3rem;"></i>
                    <h5 class="jv-modal-title" id="modalPagoLabel">REGISTRAR PAGO</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" action="<?php echo $purchaseListUrl; ?>" id="formPago" data-borrador="compras_pago">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="accion_pago" value="1">
                <input type="hidden" name="id_compra" id="pago_id_compra">

                <div class="jv-modal-body" style="padding:20px;">
                    <div class="jv-modal-section-sub mb-3">
                        <span style="font-size:.85rem;color:var(--jv-text-muted);">Factura:</span>
                        <span id="pago_factura_num" style="font-weight:700;margin-left:4px;"></span>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="jv-modal-label">TOTAL (Bs.)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="pago_total" readonly style="background:var(--jv-bg);font-weight:700;">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="jv-modal-label">SALDO PENDIENTE (Bs.)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="pago_saldo" readonly style="background:var(--jv-bg);font-weight:700;color:var(--jv-red);">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="jv-modal-label">MONTO A PAGAR (Bs.) <span style="color:var(--jv-red);">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="font-size:.85rem;">Bs.</span>
                            <input type="number" step="0.01" min="0.01" class="form-control input-jv" id="pago_monto" required placeholder="0.00" style="font-weight:700;font-size:1.1rem;">
                        </div>
                        <input type="hidden" name="monto_usd" id="pago_monto_usd" value="">
                        <input type="hidden" name="monto_pago" id="pago_monto_pago" value="">
                        <small id="pago_equivalente_usd" class="d-block mt-1 text-secondary" style="font-size:.78rem;">Equivalente: $0.00</small>
                    </div>

                    <div class="mb-3">
                        <label class="jv-modal-label">MÉTODO DE PAGO <span style="color:var(--jv-red);">*</span></label>
                        <select name="metodo_pago" class="form-select input-jv" id="pago_metodo" required>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <!-- Detalle según método -->
                    <div id="pago_detalle_transferencia" style="display:none;">
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="jv-modal-label">Teléfono (Pago Móvil)</label>
                                <input type="text" class="form-control input-jv" name="pago_telefono" placeholder="0412-1234567">
                            </div>
                            <div class="col-6">
                                <label class="jv-modal-label">Banco</label>
                                <input type="text" class="form-control input-jv" name="pago_banco" placeholder="Banco">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="jv-modal-label">Referencia</label>
                            <input type="text" class="form-control input-jv" name="pago_referencia" placeholder="Nro. referencia">
                        </div>
                    </div>

                    <div id="pago_detalle_cheque" style="display:none;">
                        <div class="mb-3">
                            <label class="jv-modal-label">Nro. de Cheque</label>
                            <input type="text" class="form-control input-jv" name="pago_referencia" placeholder="Nro. cheque">
                        </div>
                    </div>

                    <div id="pago_detalle_otro" style="display:none;">
                        <div class="mb-3">
                            <label class="jv-modal-label">Descripción</label>
                            <input type="text" class="form-control input-jv" name="pago_descripcion" placeholder="Detalle del pago">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="jv-modal-label">Pago rápido</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-jv-outline" onclick="setPagoMonto(25)">25%</button>
                            <button type="button" class="btn btn-sm btn-jv-outline" onclick="setPagoMonto(50)">50%</button>
                            <button type="button" class="btn btn-sm btn-jv-outline" onclick="setPagoMonto(75)">75%</button>
                            <button type="button" class="btn btn-sm btn-jv-success" onclick="setPagoMonto(100)">100%</button>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
                    <button type="button" class="btn btn-jv-danger" style="padding:12px 28px;font-size:1rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-jv-success module-action-btn"><i class="bi bi-check-lg me-1"></i>Registrar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Ver Detalle de Factura -->
<div class="modal fade" id="modalDetalleCompra" tabindex="-1" aria-labelledby="modalDetalleCompraLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content jv-modal-content">
            <div class="jv-modal-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt fs-4"></i>
                    <h5 class="jv-modal-title" id="modalDetalleCompraLabel">DETALLE DE FACTURA</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body jv-modal-body detalle-compra-body">
                <div class="row g-3 g-md-4 detalle-compra-cabecera mb-4 pb-3 border-bottom">
                    <div class="col-md-6">
                        <span class="jv-modal-label">PROVEEDOR</span>
                        <div class="detalle-compra-dato" id="detalleCompraProveedor">&mdash;</div>
                    </div>
                    <div class="col-md-3">
                        <span class="jv-modal-label">RIF</span>
                        <div class="detalle-compra-dato" id="detalleCompraRif">&mdash;</div>
                    </div>
                    <div class="col-md-3">
                        <span class="jv-modal-label">FACTURA</span>
                        <div class="detalle-compra-dato" id="detalleCompraFactura">&mdash;</div>
                    </div>
                    <div class="col-md-6">
                        <span class="jv-modal-label">DIRECCIÓN</span>
                        <div class="detalle-compra-dato" id="detalleCompraDireccion">&mdash;</div>
                    </div>
                    <div class="col-md-3">
                        <span class="jv-modal-label">TELÉFONO</span>
                        <div class="detalle-compra-dato" id="detalleCompraTelefono">&mdash;</div>
                    </div>
                    <div class="col-md-3">
                        <span class="jv-modal-label">FECHA</span>
                        <div class="detalle-compra-dato" id="detalleCompraFecha">&mdash;</div>
                    </div>
                </div>

                <div class="table-responsive detalle-compra-tabla-wrap">
                    <table class="table align-middle mb-0 detalle-compra-tabla">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="detalleCompraItems">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Cargando detalle...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="detalle-compra-totales d-flex flex-column gap-2">
                    <div class="detalle-compra-total-row">
                        <span>Subtotal</span>
                        <span class="text-end" id="detalleCompraSubtotal">Bs. 0</span>
                    </div>
                    <div class="detalle-compra-total-row d-none">
                        <span>IVA</span>
                        <span class="text-end" id="detalleCompraIva">Bs. 0</span>
                    </div>
                    <div class="detalle-compra-total-row detalle-compra-total-final">
                        <span>TOTAL</span>
                        <span class="text-end fw-bold fs-5" id="detalleCompraTotal">Bs. 0</span>
                    </div>
                    <div class="detalle-compra-tasa<?php echo $tasa_bcv_vis > 0 ? '' : ' d-none'; ?>" id="detalleCompraTasa">
                        <?php if ($tasa_bcv_vis > 0): ?>
                            Montos convertidos a Bs. con la tasa BCV de <?php echo jv_formatear_bolivares($tasa_bcv_vis, 2); ?><?php echo $tasa_bcv_fecha_legible !== '' ? ' del ' . $tasa_bcv_fecha_legible : ''; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="jv-modal-footer">
                <button type="button" class="btn btn-jv-danger" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     TASA BCV MANUAL (prioridad sobre la consulta automática)
     ============================================================ -->
<div class="modal fade" id="modalTasaBcv" tabindex="-1" aria-labelledby="modalTasaBcvTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form method="post" action="<?php echo APP_URL_BASE; ?>index.php?url=compras/tasa_manual" class="m-0">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>">

                <div class="jv-modal-header">
                    <h5 id="modalTasaBcvTitulo"><i class="bi bi-bank me-2"></i>Tasa BCV</h5>
                </div>

                <div class="modal-body jv-modal-body">
                    <p class="small text-secondary mb-3">
                        La tasa manual tiene prioridad sobre la consulta automática al BCV.
                        Al guardarla se aplica de inmediato a todos los montos en bolívares;
                        usa «Volver a automática» para retomar la tasa oficial.
                    </p>

                    <label for="tasa_manual" class="form-label small fw-bold">Tasa USD &rarr; VES</label>
                    <div class="input-group mb-2">
                        <span class="input-group-text">Bs.</span>
                        <input type="text" class="form-control" id="tasa_manual" name="tasa_manual"
                               inputmode="decimal" autocomplete="off" required
                               placeholder="<?php echo $tasa_bcv_vis > 0 ? htmlspecialchars(number_format($tasa_bcv_vis, 2, ',', '.'), ENT_QUOTES) : '897,54'; ?>"
                               value="<?php echo $tasa_manual_guardada_vis > 0 ? htmlspecialchars(number_format($tasa_manual_guardada_vis, 2, ',', '.'), ENT_QUOTES) : ''; ?>">
                    </div>

                    <div class="tasa-manual-referencia">
                        <?php if ($tasa_bcv_ref_vis > 0): ?>
                            <i class="bi bi-info-circle me-1"></i>Última referencia: <strong id="tasaBcvReferencia"><?php echo jv_formatear_bolivares($tasa_bcv_ref_vis, 2); ?></strong>.
                            Solo se aceptan valores dentro del &plusmn;20% de esa referencia.
                        <?php else: ?>
                            <i class="bi bi-info-circle me-1"></i>No hay tasa de referencia: se guardará sin comparación.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="jv-modal-footer justify-content-between">
                    <div>
                        <?php if ($tasa_manual_guardada_vis > 0): ?>
                            <button type="submit" name="accion_tasa" value="automatica" formnovalidate class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Volver a automática
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-jv-danger" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="submit" name="accion_tasa" value="guardar" class="btn btn-jv-success"><i class="bi bi-check-lg me-1"></i>Guardar tasa</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================
     GESTIÓN INTEGRADA DE PROVEEDORES (pop-ups dentro de Compras)
     Datos para compras.js: listado completo en JV_PROVS.
     ============================================================ -->
<script>
    window.JV_PROVS = <?php echo json_encode(array_values($prov_gestion), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<!-- POP-UP 1: Listado de proveedores -->
<div class="modal fade" id="modalProvList" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content modal-content-jv">
            <div class="modal-header-jv">
                <div>
                    <h5 class="font-brand" style="font-size:1.1rem;"><i class="bi bi-building me-2"></i>Proveedores Registrados</h5>
                    <small>Administra tus proveedores, sus datos de contacto y cat&aacute;logos de precios · Total <strong><?php echo count($prov_gestion); ?></strong> · Activos <strong><?php echo $prov_activos; ?></strong></small>
                </div>
                <button type="button" class="prov-close-btn" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <div class="prov-search">
                        <i class="bi bi-search"></i>
                        <input type="text" class="input-jv" id="buscarProv" placeholder="Buscar por empresa, RIF o teléfono..." oninput="provFiltrar()" aria-label="Buscar proveedor">
                    </div>
                    <div class="filter-group ms-auto">
                        <button type="button" class="btn-filter active" onclick="provSetFiltro('todos', this)">Todos</button>
                        <button type="button" class="btn-filter" onclick="provSetFiltro('Activo', this)">Activos</button>
                        <button type="button" class="btn-filter" onclick="provSetFiltro('Inactivo', this)">Inactivos</button>
                    </div>
                    <button type="button" class="btn btn-jv-primary module-action-btn" onclick="nuevoProv()">
                        <i class="bi bi-plus-lg me-1"></i>NUEVO
                    </button>
                </div>

                <div class="table-responsive" style="border:1px solid var(--jv-border);border-radius:10px;overflow-x:auto;overflow-y:hidden;">
                    <table class="table-jv mb-0" style="--jv-min-w:0;">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>RIF</th>
                                <th class="d-none d-xl-table-cell">Tel&eacute;fono</th>
                                <th class="text-center d-none d-xl-table-cell">Productos</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="provTbody">
                            <?php if (!empty($prov_gestion)): ?>
                                <?php foreach ($prov_gestion as $gestion_prov): ?>
                                    <?php
                                    $entradas_cat = $prov_catalogo[$gestion_prov['id_proveedor']] ?? [];
                                    $prov_json = htmlspecialchars(json_encode($gestion_prov, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="prov-fila" data-status="<?php echo $gestion_prov['status']; ?>" data-texto="<?php echo strtolower(htmlspecialchars($gestion_prov['nombre_empresa'] . ' ' . $gestion_prov['rif'] . ' ' . ($gestion_prov['telefono'] ?? ''))); ?>">
                                        <td class="fw-bold text-uppercase"><?php echo htmlspecialchars($gestion_prov['nombre_empresa']); ?></td>
                                        <td><span class="codigo-badge"><?php echo htmlspecialchars($gestion_prov['rif']); ?></span></td>
                                        <td class="text-secondary d-none d-xl-table-cell"><?php echo !empty($gestion_prov['telefono']) ? htmlspecialchars(formatearTelefono($gestion_prov['telefono'])) : '&mdash;'; ?></td>
                                        <td class="text-center d-none d-xl-table-cell">
                                            <button type="button" class="cant-badge border-0" style="cursor:pointer;" onclick="provToggleDetalle(<?php echo (int)$gestion_prov['id_proveedor']; ?>)" data-tooltip="Ver productos que suministra">
                                                <i class="bi bi-box-seam me-1"></i><?php echo count($entradas_cat); ?>
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-jv <?php echo $gestion_prov['status'] === 'Activo' ? 'badge-success' : 'badge-danger'; ?>"><?php echo $gestion_prov['status']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn-action" onclick='editarProv(<?php echo $prov_json; ?>)' data-tooltip="Editar"><i class="bi bi-pencil-square"></i></button>
                                                <?php if ($es_admin): ?>
                                                    <button type="button" class="btn-action" onclick="provToggleStatus(<?php echo (int)$gestion_prov['id_proveedor']; ?>, '<?php echo htmlspecialchars(addslashes($gestion_prov['nombre_empresa'])); ?>', '<?php echo $gestion_prov['status']; ?>')" data-tooltip="<?php echo $gestion_prov['status'] === 'Activo' ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="bi <?php echo $gestion_prov['status'] === 'Activo' ? 'bi-pause-circle' : 'bi-play-circle'; ?>" style="color:<?php echo $gestion_prov['status'] === 'Activo' ? 'var(--jv-danger)' : 'var(--jv-success)'; ?>"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="prov-detalle-row" id="prov-detalle-<?php echo (int)$gestion_prov['id_proveedor']; ?>" style="display:none;">
                                        <td colspan="6" style="background:var(--jv-bg-primary);padding:12px 18px;">
                                            <div class="prov-catalogo-head">
                                                <span><i class="bi bi-box-seam me-1"></i>PRODUCTOS QUE SUMINISTRA</span>
                                                <button type="button" class="btn-cat-add" onclick="provAgregarCat(<?php echo (int)$gestion_prov['id_proveedor']; ?>, '<?php echo htmlspecialchars(addslashes($gestion_prov['nombre_empresa'])); ?>')">
                                                    <i class="bi bi-plus-lg"></i> Agregar
                                                </button>
                                            </div>
                                            <?php if (!empty($entradas_cat)): ?>
                                                <?php foreach ($entradas_cat as $entrada_cat): ?>
                                                    <div class="prov-cat-item">
                                                        <div class="cat-item-info">
                                                            <span class="cat-item-nombre" data-tooltip="<?php echo htmlspecialchars($entrada_cat['nombre_producto']); ?>"><?php echo htmlspecialchars($entrada_cat['nombre_producto']); ?></span>
                                                            <small class="cat-item-meta"><?php echo htmlspecialchars($entrada_cat['sku']); ?><?php echo !empty($entrada_cat['codigo_proveedor']) ? ' · C&oacute;d. prov: ' . htmlspecialchars($entrada_cat['codigo_proveedor']) : ''; ?></small>
                                                        </div>
                                                        <span class="cat-item-costo"><?php echo format_bs((float)$entrada_cat['costo'], $tasa_bcv_vis ?: null); ?></span>
                                                        <span class="cat-item-stock">Disponible: <?php echo number_format((int)$entrada_cat['stock_disponible']); ?></span>
                                                        <button type="button" class="btn-cat-icon" onclick='editarProductoCatalogo(<?php echo htmlspecialchars(json_encode($entrada_cat, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8"); ?>)' data-tooltip="Editar costo y disponible"><i class="bi bi-pencil-square"></i></button>
                                                        <button type="button" class="btn-cat-icon btn-cat-del" onclick="provEliminarCat(<?php echo (int)$entrada_cat['id_catalogo']; ?>, '<?php echo htmlspecialchars(addslashes($entrada_cat['nombre_producto'])); ?>')" data-tooltip="Quitar del cat&aacute;logo"><i class="bi bi-trash3"></i></button>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="prov-catalogo-vacio">
                                                    <i class="bi bi-inbox"></i>A&uacute;n no tiene productos en su cat&aacute;logo.
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="estado-vacio">
                                            <i class="bi bi-building"></i>
                                            <span>No hay proveedores registrados</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- POP-UP 2: Formulario de proveedor (registrar / editar) -->
<div class="modal fade" id="modalProveedor" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content modal-content-jv">
            <div class="modal-body p-4">
                <form action="" method="POST" id="formProveedor" data-borrador="compras_proveedor">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="accion_proveedor" id="p_accion" value="registrar">
                    <input type="hidden" name="id_proveedor" id="p_id_edit">
                    <div class="modal-header-jv">
                        <div>
                            <h5 class="font-brand" id="modalTitle" style="font-size:1.1rem;"><i class="bi bi-building me-2"></i>Registrar Nuevo Proveedor</h5>
                            <small>Completa los datos del proveedor para asociarlo a tus compras</small>
                        </div>
                        <button type="button" class="prov-close-btn" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <div class="comp-section sec-fiscal">
                        <div class="comp-section-header">
                            <span class="comp-section-chip chip-fiscal"><i class="bi bi-file-earmark-text"></i></span>
                            <div>
                                <div class="comp-section-title">Informaci&oacute;n Fiscal</div>
                                <div class="comp-section-sub">Datos legales de la empresa</div>
                            </div>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-md-4">
                                <label class="comp-label">RIF / C&eacute;dula <span class="text-danger">*</span></label>
                                <input type="hidden" name="rif" id="p_rif">
                                <div class="input-group">
                                    <select id="p_rif_tipo" class="input-jv comp-input comp-rif-tipo" required>
                                        <option value="V">V</option>
                                        <option value="E">E</option>
                                        <option value="J" selected>J</option>
                                        <option value="G">G</option>
                                        <option value="P">P</option>
                                        <option value="C">C</option>
                                    </select>
                                    <input type="text" id="p_rif_numero" class="input-jv comp-input" required placeholder="12345678-0" maxlength="10" inputmode="numeric">
                                </div>
                                <small class="comp-hint">V/E: 8 dígitos · J/G/P/C: 8 dígitos + 1 verificador</small>
                            </div>
                            <div class="col-md-8">
                                <label class="comp-label">Nombre de la Empresa <span class="text-danger">*</span></label>
                                <input type="text" name="nombre_empresa" id="p_empresa" class="input-jv comp-input text-uppercase" required placeholder="NOMBRE LEGAL DE LA EMPRESA" maxlength="100" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-12">
                                <label class="comp-label">Direcci&oacute;n Fiscal</label>
                                <textarea name="direccion" id="p_direccion" class="input-jv comp-input" rows="2" placeholder="Direcci&oacute;n fiscal completa"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="comp-section sec-contacto">
                        <div class="comp-section-header">
                            <span class="comp-section-chip chip-contacto"><i class="bi bi-person-lines-fill"></i></span>
                            <div>
                                <div class="comp-section-title">Contacto</div>
                                <div class="comp-section-sub">&iquest;Con qui&eacute;n hablamos?</div>
                            </div>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-md-4">
                                <label class="comp-label">Tel&eacute;fono <span class="text-danger">*</span></label>
                                <input type="tel" name="telefono" id="p_tel" class="input-jv comp-input w-100" required maxlength="20" inputmode="tel" placeholder="0412-1234567">
                                <input type="hidden" name="telefono_completo" id="p_tel_full">
                            </div>
                            <div class="col-md-4">
                                <label class="comp-label">Persona de Contacto</label>
                                <input type="text" name="contacto_nombre" id="p_contacto_nombre" class="input-jv comp-input" placeholder="Nombre del contacto">
                            </div>
                            <div class="col-md-4">
                                <label class="comp-label">Correo Electr&oacute;nico</label>
                                <input type="email" name="email" id="p_email" class="input-jv comp-input" placeholder="correo@ejemplo.com">
                            </div>
                        </div>
                    </div>

                    <div class="comp-section sec-comercial">
                        <div class="comp-section-header">
                            <span class="comp-section-chip chip-comercial"><i class="bi bi-gear"></i></span>
                            <div>
                                <div class="comp-section-title">Condiciones Comerciales</div>
                                <div class="comp-section-sub">Entregas y moneda de trabajo</div>
                            </div>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-md-3">
                                <label class="comp-label">Plazo de Entrega (D&iacute;as)</label>
                                <input type="number" name="lead_time" id="p_lead_time" class="input-jv comp-input" placeholder="Ej: 7" min="0" max="365" maxlength="3" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,3)">
                            </div>
                            <div class="col-md-4">
                                <label class="comp-label">Moneda</label>
                                <select name="moneda" id="p_moneda" class="input-jv comp-input">
                                    <option value="USD">USD - D&oacute;lar</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="VES">VES - Bol&iacute;var</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="comp-label">Estado</label>
                                <select name="status" id="p_status" class="input-jv comp-input">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="btn-prov-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase mt-4">
                        <i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- POP-UP 3: Catálogo de costos (asociar producto a proveedor) -->
<div class="modal fade" id="modalCatalogo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form action="" method="POST" id="formCatalogo" data-borrador="compras_catalogo" data-borrador-contexto="id_catalogo">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_catalogo" id="cat_accion" value="registrar">
                <input type="hidden" name="id_catalogo" id="cat_id_edit">
                <div class="modal-body p-4">
                    <div class="modal-header-jv">
                        <div>
                            <h5 class="font-brand" style="font-size:1.1rem;"><i class="bi bi-box-seam me-2"></i><span id="catTitulo">AGREGAR PRODUCTO</span></h5>
                            <small id="catSubtitulo" style="color:#065f46;"></small>
                        </div>
                        <button type="button" class="prov-close-btn" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <div class="comp-section sec-comercial mb-4">
                        <div class="comp-section-header">
                            <span class="comp-section-chip chip-comercial"><i class="bi bi-tag"></i></span>
                            <div>
                                <div class="comp-section-title">Datos del Producto</div>
                                <div class="comp-section-sub">Costo con el que este proveedor te lo vende</div>
                            </div>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-12">
                                <label class="comp-label">Proveedor</label>
                                <input type="text" id="cat_proveedor_nombre" class="input-jv comp-input" readonly style="background:rgba(15,26,46,0.04);">
                                <input type="hidden" name="id_proveedor" id="cat_id_prov">
                            </div>
                            <div class="col-12">
                                <label class="comp-label">Producto <span class="text-danger">*</span></label>
                                <select name="id_producto" id="cat_producto" class="input-jv comp-input" required>
                                    <option value="">&mdash; Selecciona un producto &mdash;</option>
                                    <?php foreach ($productos_activos as $prod_activo): ?>
                                        <option value="<?php echo (int)$prod_activo['id_producto']; ?>">
                                            <?php echo htmlspecialchars($prod_activo['nombre_producto'] . ' (' . $prod_activo['sku'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="comp-label">Costo de Compra ($) <span class="text-danger">*</span></label>
                                <input type="text" name="costo" id="cat_costo" class="input-jv comp-input" required placeholder="0.00" maxlength="12" inputmode="decimal">
                            </div>
                            <div class="col-md-6">
                                <label class="comp-label">Disponible en el proveedor <span class="text-danger">*</span></label>
                                <input type="number" name="stock_disponible" id="cat_stock_disponible" class="input-jv comp-input" min="0" max="4294967295" step="1" value="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="comp-label">C&oacute;digo Interno del Proveedor</label>
                                <input type="text" name="codigo_proveedor" id="cat_codigo_prov" class="input-jv comp-input" placeholder="Opcional" maxlength="50">
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="btn-cat-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase">
                        <i class="bi bi-check-lg me-2"></i>GUARDAR EN CAT&Aacute;LOGO
                    </button>
            </form>
        </div>
    </div>
</div>
</div>

<!-- Prefill de solicitud para compras.js (se ejecuta antes que los scripts js_extra) -->
<script>
    window.COMPRAS_SOLICITUD = <?php echo !empty($solicitud_prefill) ? json_encode($solicitud_prefill, JSON_UNESCAPED_UNICODE) : 'null'; ?>;
</script>

<!-- ============================================================
     RECEPCIÓN DE MERCADERÍA (modal integrado en Compras)
     Los datos de cada compra pendiente llegan vía
     window.JV_CONFIG.recepcionDatos (inyectado por el layout).
     ============================================================ -->
<div class="modal fade" id="modalRecepcion" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form method="POST" id="formRecepcion" data-borrador="compras_recepcion">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_recepcion" value="recibir">
                <input type="hidden" name="id_compra" id="recIdCompra">
                <input type="hidden" name="clave_idempotencia" id="recClaveIdempotencia">
                <input type="hidden" name="items_data" id="recItemsData">

                <!-- Header receptivo -->
                <div class="rec-modal-header">
                    <div class="rec-modal-icon"><i class="bi bi-box-arrow-in-down"></i></div>
                    <div>
                        <h5 class="rec-modal-titulo">RECIBIR MERCADERÍA</h5>
                        <p class="rec-modal-sub">Ingreso al inventario: lotes, vencimiento y costo promedio</p>
                    </div>
                    <button type="button" class="prov-close-btn ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
                </div>

                <div class="p-3">
                    <div id="recuperacionRecepcionAviso" class="alert alert-warning" role="alert" style="display:none;"></div>
                    <div class="rec-section">
                        <div class="rec-section-label"><i class="bi bi-receipt me-1"></i>Compra</div>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="small fw-bold text-secondary mb-1">FACTURA</label>
                                <input type="text" class="input-jv" id="recFactura" readonly disabled style="color:var(--jv-text-muted);font-weight:700;">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-secondary mb-1">PROVEEDOR</label>
                                <input type="text" class="input-jv" id="recProveedor" readonly disabled style="color:var(--jv-text-muted);">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-1">N&deg; GU&Iacute;A / RECIBO <span class="fw-normal">(opcional)</span></label>
                                <input type="text" name="documento_recepcion" class="input-jv" id="recDocumento" maxlength="100" placeholder="Documento físico de entrega...">
                            </div>
                        </div>
                    </div>

                    <div class="rec-section" style="margin-bottom:0;">
                        <div class="rec-section-label"><i class="bi bi-box-seam me-1"></i>Productos a recibir <span class="fw-normal text-secondary">(ajuste la cantidad, m&aacute;x. lo pendiente)</span></div>
                        <div class="table-responsive" style="border:1px solid var(--jv-border);border-radius:8px;overflow-x:auto;overflow-y:hidden;">
                            <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-card);">
                                <thead>
                                    <tr style="background:linear-gradient(135deg,#B45309,#D97706);">
                                        <th style="padding:10px 8px;width:28px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">#</th>
                                        <th style="padding:10px 8px;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Producto</th>
                                        <th style="padding:10px 8px;width:70px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Orden</th>
                                        <th style="padding:10px 8px;width:70px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Recibido</th>
                                        <th style="padding:10px 8px;width:100px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">A Recibir</th>
                                        <th style="padding:10px 8px;width:130px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Vence</th>
                                        <th style="padding:10px 8px;width:80px;text-align:right;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">P. Costo</th>
                                    </tr>
                                </thead>
                                <tbody id="recItemsBody">
                                    <tr>
                                        <td colspan="7" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.85rem;">Seleccione una compra para recibir</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
                    <button type="button" class="btn btn-jv-danger" style="padding:12px 28px;font-size:1rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                    <button type="button" class="btn btn-jv-success module-action-btn" id="btnRecibir" onclick="return confirmarRecepcion(this)"><i class="bi bi-check-lg me-1"></i> Registrar Recepci&oacute;n</button>
                </div>
            </form>
        </div>
    </div>
</div>