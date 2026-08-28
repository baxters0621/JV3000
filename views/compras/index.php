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

// ==========================================
// VISTA: Compras (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
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
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3 px-3 py-2" id="flashMsg" data-texto="<?php echo htmlspecialchars($flash['texto']); ?>">
        <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
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
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div>
                <div class="widget-label">Invertido (Mes)</div>
                <div class="widget-value">$<?php echo number_format((float)$kpis['invertido_mes'], 0); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de compras -->
<div class="card-jv card-jv-table p-0">
    <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper flex-wrap">
        <div class="compras-search">
            <i class="bi bi-search"></i>
            <input type="text" class="input-jv" placeholder="Buscar por factura, proveedor, productos..." id="buscar" aria-label="Buscar compra" onkeyup="filtrar()">
        </div>
        <select class="input-jv ms-auto" id="filtroPago" aria-label="Filtrar por estado de pago" onchange="window.location='<?php echo $purchaseListUrl; ?>&filtro_pago='+this.value">
            <option value="">Todos los pagos</option>
            <option value="Pendiente" <?php echo $filtro_pago === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
            <option value="Pagada" <?php echo $filtro_pago === 'Pagada' ? 'selected' : ''; ?>>Pagada</option>
        </select>
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
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
                    <th>Pago</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaEntradas">
                <?php if (count($compras) > 0): foreach ($compras as $compra): ?>
                        <tr>
                            <td class="td-factura">
                                <span class="codigo-badge" data-tooltip="<?php echo htmlspecialchars($compra['nro_control'] ?: 'Sin control'); ?>"><?php echo htmlspecialchars($compra['nro_factura'] ?: '-'); ?></span>
                            </td>
                            <td class="td-proveedor" data-tooltip="<?php echo htmlspecialchars($compra['proveedor'] ?? 'S/P'); ?>">
                                <span class="proveedor-nombre"><?php echo htmlspecialchars($compra['proveedor'] ?? 'S/P'); ?></span>
                            </td>
                            <td class="td-detalle" data-tooltip="<?php echo htmlspecialchars($compra['productos_list'] ?? ''); ?>">
                                <span class="cant-pill">+<?php echo (int)$compra['total_cantidad']; ?></span>
                                <span class="detalle-nombres"><?php echo htmlspecialchars($compra['productos_list'] ?? '-'); ?></span>
                            </td>
                            <td class="td-total text-end">
                                <span class="total-monto">$<?php echo number_format($compra['total'], 2); ?></span>
                            </td>
                            <td class="text-center">
                                <?php $status_pago = $compra['status_pago'] ?? 'Pendiente'; ?>
                                <span class="badge-jv <?php echo $status_pago === 'Pagada' ? 'badge-success' : 'badge-warning'; ?>"><i class="bi <?php echo $status_pago === 'Pagada' ? 'bi-check-circle' : 'bi-hourglass-split'; ?> me-1"></i><?php echo $status_pago; ?></span>
                            </td>
                            <td class="td-fecha"><?php echo date('d/m/Y', strtotime($compra['fecha_compra'])); ?></td>
                            <td class="text-center">
                                <?php if ($es_admin): ?>
                                    <button type="button" class="btn-action" onclick="confirmarEliminar(<?php echo (int)$compra['id_compra']; ?>)" data-tooltip="Anular compra"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
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

<!-- Modal: Registrar compra -->
<!-- Mapa de costos del catálogo [id_proveedor][id_producto] = costo:
     compras.js lo usa para autocompletar el costo al elegir proveedor. -->
<script>window.JV_CATALOGO = <?php echo $catalogo_costos; ?>;</script>
<div class="modal fade" id="modalCompra" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form method="POST" id="formCompra">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_compra" value="registrar">
                <input type="hidden" name="productos_data" id="productosData">
                <?php if (!empty($solicitud_prefill)): ?>
                    <input type="hidden" name="id_solicitud" id="idSolicitud" value="<?php echo (int)$solicitud_prefill['id_solicitud']; ?>">
                    <div class="alert-jv alert-jv-info mb-3 px-3 py-2" style="margin:16px 20px 0;">
                        <i class="bi bi-cart-check me-2"></i>
                        <strong>SOLICITUD #<?php echo (int)$solicitud_prefill['id_solicitud']; ?></strong> — <?php echo htmlspecialchars($solicitud_prefill['motivo'] ?? 'Solicitud de reposición'); ?>. Los productos ya fueron precargados.
                    </div>
                <?php endif; ?>

                <div class="p-3" style="border-bottom:1px solid var(--jv-border);">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 font-brand" style="color:var(--jv-navy);font-size:1.3rem;letter-spacing:-.5px;"><i class="bi bi-cart-plus me-2"></i>REGISTRAR COMPRA</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <div class="p-3" style="padding:16px 20px;">
                    <div class="comp-proveedor-section section-bg">
                        <div class="section-label"><i class="bi bi-building me-1"></i>Proveedor</div>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="small fw-bold text-secondary mb-1">PROVEEDOR *</label>
                                <select name="id_proveedor" class="input-jv" id="selProveedor" aria-label="Proveedor de la compra" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($proveedores as $proveedor): ?>
                                        <option value="<?php echo (int)$proveedor['id_proveedor']; ?>" data-rif="<?php echo htmlspecialchars($proveedor['rif']); ?>">
                                            <?php echo htmlspecialchars($proveedor['nombre_empresa']); ?> (<?php echo htmlspecialchars($proveedor['rif']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-secondary mb-1">RIF</label>
                                <input type="text" class="input-jv" id="displayRif" aria-label="RIF del proveedor" value="-" readonly disabled style="color:var(--jv-text-muted);">
                            </div>
                        </div>
                    </div>

                    <div class="section-bg">
                        <div class="section-label"><i class="bi bi-receipt me-1"></i>Factura del Proveedor</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="small fw-bold text-secondary mb-1">NRO. FACTURA (PROVEEDOR) *</label>
                                <input type="text" name="nro_factura" aria-label="Numero de factura" class="input-jv" placeholder="Ej: 001254" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-secondary mb-1">NRO. CONTROL <span class="fw-normal">(opcional)</span></label>
                                <input type="text" name="nro_control" aria-label="Numero de control" class="input-jv" value="" placeholder="00-00000000" oninput="var v=this.value.replace(/[^0-9]/g,'');if(v.length>10)v=v.slice(0,10);if(v.length>2)v=v.slice(0,2)+'-'+v.slice(2);this.value=v" maxlength="11">
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-secondary mb-1">FECHA</label>
                                <input type="date" class="input-jv" value="<?php echo date('Y-m-d'); ?>" aria-label="Fecha de la compra" disabled>
                                <input type="hidden" name="fecha_compra" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="section-bg">
                        <div class="section-label"><i class="bi bi-cash-coin me-1"></i>Comprobante de Pago</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="small fw-bold text-secondary mb-1">MÉTODO *</label>
                                <select name="metodo_pago" class="input-jv" id="selMetodo" aria-label="Metodo de pago">
                                    <option value="">Seleccionar...</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-secondary mb-1">MONTO PAGADO $</label>
                                <input type="text" inputmode="decimal" name="monto_pago" class="input-jv" id="montoPago" aria-label="Monto pagado" value="0.00" oninput="marcarMontoEditado();formatearPrecioCompra(this)">
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-secondary mb-1">FECHA PAGO</label>
                                <input type="date" class="input-jv" value="<?php echo date('Y-m-d'); ?>" aria-label="Fecha del pago" disabled>
                                <small class="text-muted d-block mt-1" style="font-size:.68rem;">Si el monto es menor al total, la factura queda PENDIENTE.</small>
                            </div>
                        </div>
                    </div>

                    <div class="section-bg">
                        <div class="section-label"><i class="bi bi-box-seam me-1"></i>Productos</div>

                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="small fw-bold text-secondary mb-1">Producto</label>
                                <div class="com-toolbox">
                                    <input type="text" class="input-jv w-100" id="buscarProducto" aria-label="Buscar producto para agregar" placeholder="Buscar por nombre o SKU..." autocomplete="off">
                                    <input type="hidden" id="selProductoId">
                                    <input type="hidden" id="selProductoNombre">
                                    <div class="com-resultados" id="resultadosBusqueda"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-secondary mb-1">Cant</label>
                                <input type="number" class="input-jv" id="inputCant" aria-label="Cantidad del producto" value="1" min="1" max="999999" oninput="if(this.value>999999)this.value=999999;if(this.value<1)this.value=1">
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-secondary mb-1">Precio $</label>
                                <input type="text" inputmode="decimal" class="input-jv" id="inputPrecio" aria-label="Precio unitario del producto" placeholder="0.00" oninput="formatearPrecioCompra(this)">
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-secondary mb-1">Vence <span class="text-danger">*</span></label>
                                <input type="date" class="input-jv" id="inputVencimiento" aria-label="Fecha de vencimiento del lote" required>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn-jv-primary w-100" style="padding:14px;" onclick="agregarProducto()">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div style="border:1px solid var(--jv-border);border-radius:8px;overflow:hidden;margin-top:10px;">
                            <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-card);">
                                <thead>
                                    <tr style="background:var(--jv-navy);">
                                        <th style="padding:10px 8px;width:28px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">#</th>
                                        <th style="padding:10px 8px;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Producto</th>
                                        <th style="padding:10px 8px;width:55px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Cant</th>
                                        <th style="padding:10px 8px;width:90px;text-align:right;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Precio</th>
                                        <th style="padding:10px 8px;width:110px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Vence</th>
                                        <th style="padding:10px 8px;width:90px;text-align:right;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Total</th>
                                        <th style="width:28px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="productosBody">
                                    <tr id="filaVacia">
                                        <td colspan="7" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.95rem;border-bottom:1px solid var(--jv-border);">⬆ Busque un producto y presione + para agregarlo</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:24px;align-items:center;padding:12px 16px;margin-top:8px;background:var(--jv-bg-card);border:1px solid var(--jv-border);border-radius:8px;flex-wrap:wrap;">
                            <div>
                                <span class="text-secondary" style="font-weight:700;font-size:1rem;">Productos</span>
                                <span class="fw-bold ms-2" id="totalItems" style="color:var(--jv-navy);font-size:1.3rem;">0</span>
                            </div>
                            <div>
                                <span class="text-secondary" style="font-weight:700;font-size:1rem;">Subtotal</span>
                                <span class="fw-bold ms-2" id="totalSubtotal" style="color:var(--jv-text-primary);font-size:1.3rem;">$0.00</span>
                            </div>
                            <div>
                                <span class="text-secondary" style="font-weight:700;font-size:1rem;">IVA (<?php echo $iva_pct; ?>%)</span>
                                <span class="fw-bold ms-2" id="totalIva" style="color:var(--jv-text-primary);font-size:1.3rem;">$0.00</span>
                            </div>
                            <div>
                                <span class="text-secondary" style="font-weight:700;font-size:1rem;">Total</span>
                                <span class="fw-bold ms-2" id="totalCosto" style="color:var(--jv-warning);font-size:1.4rem;">$0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="section-bg" style="margin-bottom:0;">
                        <div class="section-label"><i class="bi bi-chat-text me-1"></i>Observaciones</div>
                        <input type="text" name="observaciones" aria-label="Observaciones de la compra" class="input-jv" placeholder="Notas opcionales...">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
                    <button type="button" class="btn btn-jv-danger" style="padding:12px 28px;font-size:1rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-jv-success module-action-btn" id="btnGuardar" disabled onclick="return validarFormulario(this)"><i class="bi bi-check-lg me-1"></i> Guardar</button>
            </form>
                </div>
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
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-jv">
            <div class="modal-header-jv">
                <div>
                    <h5 class="font-brand"><i class="bi bi-building me-2"></i>Proveedores Registrados</h5>
                    <small>Administra tus proveedores, sus datos de contacto y cat&aacute;logos de precios · Total <strong><?php echo count($prov_gestion); ?></strong> · Activos <strong><?php echo $prov_activos; ?></strong></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

                <div style="border:1px solid var(--jv-border);border-radius:10px;overflow:hidden;">
                    <table class="table-jv mb-0" style="--jv-min-w:0;">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>RIF</th>
                                <th>Tel&eacute;fono</th>
                                <th class="text-center">Productos</th>
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
                                        <td class="text-secondary"><?php echo !empty($gestion_prov['telefono']) ? htmlspecialchars(formatearTelefono($gestion_prov['telefono'])) : '&mdash;'; ?></td>
                                        <td class="text-center">
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
                                                            <?php if (!empty($entrada_cat['descripcion'])): ?>
                                                                <small class="cat-item-desc"><?php echo htmlspecialchars($entrada_cat['descripcion']); ?></small>
                                                            <?php endif; ?>
                                                            <small class="cat-item-meta"><?php echo htmlspecialchars($entrada_cat['sku']); ?><?php echo !empty($entrada_cat['codigo_proveedor']) ? ' · C&oacute;d. prov: ' . htmlspecialchars($entrada_cat['codigo_proveedor']) : ''; ?></small>
                                                        </div>
                                                        <span class="cat-item-costo">$<?php echo number_format((float)$entrada_cat['costo'], 2); ?></span>
                                                        <button type="button" class="btn-cat-icon" onclick='editarProductoCatalogo(<?php echo htmlspecialchars(json_encode($entrada_cat, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8"); ?>)' data-tooltip="Editar costo"><i class="bi bi-pencil-square"></i></button>
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-jv">
            <div class="modal-body p-4">
            <form action="" method="POST" id="formProveedor">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_proveedor" id="p_accion" value="registrar">
                <input type="hidden" name="id_proveedor" id="p_id_edit">
                    <div class="modal-header-jv">
                        <div>
                            <h5 class="font-brand" id="modalTitle"><i class="bi bi-building me-2"></i>Registrar Nuevo Proveedor</h5>
                            <small>Completa los datos del proveedor para asociarlo a tus compras</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="section-bg sec-fiscal">
                        <div class="section-label">
                            <span class="section-chip chip-fiscal"><i class="bi bi-file-earmark-text"></i></span>
                            Informaci&oacute;n Fiscal
                            <span class="section-ayuda">Datos legales de la empresa</span>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-md-4">
                                <label for="p_rif" class="small fw-bold text-secondary mb-2">RIF *</label>
                                <input type="text" name="rif" id="p_rif" class="input-jv" required placeholder="Ej: J-12345678-0" maxlength="13">
                                <small style="color:var(--jv-text-secondary);font-size:.85rem;display:block;margin-top:6px;">Formato: J-12345678-0</small>
                            </div>
                            <div class="col-md-8">
                                <label for="p_empresa" class="small fw-bold text-secondary mb-2">NOMBRE EMPRESA *</label>
                                <input type="text" name="nombre_empresa" id="p_empresa" class="input-jv text-uppercase" required placeholder="Nombre legal de la empresa" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="mt-3 mb-0">
                            <label for="p_direccion" class="small fw-bold text-secondary mb-2">DIRECCI&Oacute;N FISCAL</label>
                            <textarea name="direccion" id="p_direccion" class="input-jv" rows="2" placeholder="Direcci&oacute;n fiscal"></textarea>
                        </div>
                    </div>

                    <div class="section-bg sec-contacto">
                        <div class="section-label">
                            <span class="section-chip chip-contacto"><i class="bi bi-person-lines-fill"></i></span>
                            Contacto
                            <span class="section-ayuda">&iquest;Con qui&eacute;n hablamos?</span>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-md-4">
                                <label for="p_tel" class="small fw-bold text-secondary mb-2">TEL&Eacute;FONO *</label>
                                <input type="tel" name="telefono" id="p_tel" class="input-jv" required>
                                <input type="hidden" name="telefono_completo" id="p_tel_full">
                            </div>
                            <div class="col-md-4">
                                <label for="p_contacto_nombre" class="small fw-bold text-secondary mb-2">PERSONA DE CONTACTO</label>
                                <input type="text" name="contacto_nombre" id="p_contacto_nombre" class="input-jv" placeholder="Nombre del contacto">
                            </div>
                            <div class="col-md-4">
                                <label for="p_email" class="small fw-bold text-secondary mb-2">CORREO ELECTR&Oacute;NICO</label>
                                <input type="email" name="email" id="p_email" class="input-jv" placeholder="correo@ejemplo.com">
                            </div>
                        </div>
                    </div>

                    <div class="section-bg sec-comercial mb-4">
                        <div class="section-label">
                            <span class="section-chip chip-comercial"><i class="bi bi-gear"></i></span>
                            Condiciones Comerciales
                            <span class="section-ayuda">Entregas y moneda de trabajo</span>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-md-4">
                                <label for="p_lead_time" class="small fw-bold text-secondary mb-2">PLAZO DE ENTREGA (D&Iacute;AS)</label>
                                <input type="number" name="lead_time" id="p_lead_time" class="input-jv" placeholder="Ej: 7" min="0" max="365">
                            </div>
                            <div class="col-md-4">
                                <label for="p_moneda" class="small fw-bold text-secondary mb-2">MONEDA</label>
                                <select name="moneda" id="p_moneda" class="input-jv">
                                    <option value="USD">USD - D&oacute;lar</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="VES">VES - Bol&iacute;var</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="p_status" class="small fw-bold text-secondary mb-2">ESTADO</label>
                                <select name="status" id="p_status" class="input-jv">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="btn-prov-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase">
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
            <form action="" method="POST" id="formCatalogo">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_catalogo" id="cat_accion" value="registrar">
                <input type="hidden" name="id_catalogo" id="cat_id_edit">
                <div class="modal-body p-4">
                    <div class="modal-header-jv">
                        <div>
                            <h5 class="font-brand"><i class="bi bi-box-seam me-2"></i><span id="catTitulo">AGREGAR PRODUCTO</span></h5>
                            <small id="catSubtitulo"></small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="section-bg sec-comercial mb-4">
                        <div class="section-label">
                            <span class="section-chip chip-comercial"><i class="bi bi-tag"></i></span>
                            Datos del producto
                            <span class="section-ayuda">Costo con el que este proveedor te lo vende</span>
                        </div>
                        <div class="row g-3 mb-0">
                            <div class="col-12">
                                <label for="cat_proveedor_nombre" class="small fw-bold text-secondary mb-2">PROVEEDOR</label>
                                <input type="text" id="cat_proveedor_nombre" class="input-jv" readonly style="background:rgba(15,26,46,0.04);">
                                <input type="hidden" name="id_proveedor" id="cat_id_prov">
                            </div>
                            <div class="col-12">
                                <label for="cat_producto" class="small fw-bold text-secondary mb-2">PRODUCTO *</label>
                                <select name="id_producto" id="cat_producto" class="input-jv" required>
                                    <option value="">&mdash; Selecciona un producto &mdash;</option>
                                    <?php foreach ($productos_activos as $prod_activo): ?>
                                        <option value="<?php echo (int)$prod_activo['id_producto']; ?>">
                                            <?php echo htmlspecialchars($prod_activo['nombre_producto'] . ' (' . $prod_activo['sku'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cat_costo" class="small fw-bold text-secondary mb-2">COSTO DE COMPRA ($) *</label>
                                <input type="text" name="costo" id="cat_costo" class="input-jv" required placeholder="0.00" maxlength="12" inputmode="decimal">
                            </div>
                            <div class="col-md-6">
                                <label for="cat_codigo_prov" class="small fw-bold text-secondary mb-2">C&Oacute;DIGO INTERNO DEL PROVEEDOR</label>
                                <input type="text" name="codigo_proveedor" id="cat_codigo_prov" class="input-jv" placeholder="Opcional" maxlength="50">
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