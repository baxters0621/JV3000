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
            <p class="module-subtitle">Facturas de Proveedores</p>
        </div>
    </div>
    <div class="d-flex gap-2">
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

<!-- Mensajes flash -->
<?php if (!empty($flash)): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3 px-3 py-2">
        <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Estadísticas / Widgets -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-success);">
            <div class="widget-icon" style="background:rgba(22,163,74,0.12);color:var(--jv-success);">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="widget-label">Total Compras</div>
                <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo (int)$kpis['total_compras']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-warning);">
            <div class="widget-icon" style="background:rgba(245,158,11,0.12);color:var(--jv-warning);">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="widget-label">Por Pagar</div>
                <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo (int)$kpis['por_pagar']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-info);">
            <div class="widget-icon" style="background:rgba(14,165,233,0.12);color:var(--jv-info);">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div>
                <div class="widget-label">Invertido (Mes)</div>
                <div class="widget-value" style="color: var(--jv-text-primary);">$<?php echo number_format((float)$kpis['invertido_mes'], 0); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de compras -->
<div class="card-jv card-jv-table p-0">
    <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper flex-wrap">
        <i class="bi bi-search me-1" style="font-size:1.1rem;color:var(--jv-orange);"></i>
        <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por factura, control, proveedor, productos, estado..." id="buscar" aria-label="Buscar compra" onkeyup="filtrar()" style="box-shadow:none;font-size:1rem;padding:8px 6px;max-width:340px;">
        <select class="input-jv ms-auto" id="filtroPago" aria-label="Filtrar por estado de pago" onchange="window.location='<?php echo $purchaseListUrl; ?>&filtro_pago='+this.value" style="width:auto;padding:6px 10px;font-size:.95rem;">
            <option value="">Todos los pagos</option>
            <option value="Pendiente" <?php echo $filtro_pago === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
            <option value="Pagada" <?php echo $filtro_pago === 'Pagada' ? 'selected' : ''; ?>>Pagada</option>
        </select>
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th style="width:8%;">Factura</th>
                    <th style="width:10%;">N° Control</th>
                    <th style="width:12%;">Proveedor</th>
                    <th style="width:14%;">Detalle</th>
                    <th class="text-center" style="width:5%;">Cant</th>
                    <th style="width:8%;">Subtotal</th>
                    <th style="width:7%;">IVA</th>
                    <th style="width:9%;">Total</th>
                    <th class="text-center" style="width:8%;">Pago</th>
                    <th style="width:7%;">Fecha</th>
                    <th class="text-center" style="width:120px;">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaEntradas">
                <?php if (count($compras) > 0): foreach ($compras as $compra): ?>
                        <tr>
                            <td class="text-center"><span class="codigo-badge"><?php echo htmlspecialchars($compra['nro_factura'] ?: '-'); ?></span></td>
                            <td class="nro-control-cell" data-tooltip="<?php echo htmlspecialchars($compra['nro_control'] ?: '-'); ?>"><?php echo htmlspecialchars($compra['nro_control'] ?: '-'); ?></td>
                            <td class="text-uppercase fw-bold proveedor-cell" data-tooltip="<?php echo htmlspecialchars($compra['proveedor'] ?? 'S/P'); ?>"><?php echo htmlspecialchars($compra['proveedor'] ?? 'S/P'); ?></td>
                            <td class="td-prod" data-tooltip="<?php echo htmlspecialchars($compra['productos_list'] ?? ''); ?>"><?php echo htmlspecialchars($compra['productos_list'] ?? '-'); ?></td>
                            <td class="text-center"><span class="cant-badge">+<?php echo (int)$compra['total_cantidad']; ?></span></td>
                            <td style="font-weight:600;">$<?php echo number_format($compra['subtotal'] ?? 0, 2); ?></td>
                            <td style="font-weight:600;">$<?php echo number_format($compra['iva'] ?? 0, 2); ?></td>
                            <td class="fw-bold text-success">$<?php echo number_format($compra['total'], 2); ?></td>
                            <td class="text-center">
                                <?php // Estado de pago de la compra: 'Pagada' o 'Pendiente'
                                $status_pago = $compra['status_pago'] ?? 'Pendiente'; ?>
                                <span class="badge-jv <?php echo $status_pago === 'Pagada' ? 'badge-success' : 'badge-warning'; ?>"><i class="bi <?php echo $status_pago === 'Pagada' ? 'bi-check-circle' : 'bi-hourglass-split'; ?> me-1"></i><?php echo $status_pago; ?></span>
                            </td>
                            <td class="fecha-cell"><?php echo date('d/m/Y', strtotime($compra['fecha_compra'])); ?></td>
                            <td class="text-center">
                                <?php if ($es_admin): ?>
                                    <button type="button" class="btn-action" onclick="confirmarEliminar(<?php echo (int)$compra['id_compra']; ?>)"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
                        <td colspan="11">
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
                                <label class="small fw-bold text-secondary mb-1">Vence <span class="text-muted fw-normal">(opcional)</span></label>
                                <input type="date" class="input-jv" id="inputVencimiento" aria-label="Fecha de vencimiento del lote">
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
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Prefill de solicitud para compras.js (se ejecuta antes que los scripts js_extra) -->
<script>
    window.COMPRAS_SOLICITUD = <?php echo !empty($solicitud_prefill) ? json_encode($solicitud_prefill, JSON_UNESCAPED_UNICODE) : 'null'; ?>;
</script>