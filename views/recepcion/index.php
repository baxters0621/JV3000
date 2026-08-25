<?php

/** @var array<string, string>|null $flash */
/** @var int $total_por_recibir */
/** @var int $unidades_por_recibir */
/** @var int $recepciones_hoy */
/** @var array<int, array<string, mixed>> $compras_pendientes */
/** @var array<int, array<string, mixed>> $recepciones */
/** @var string $csrf */

// ==========================================
// VISTA: Recepción de Mercancía (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
// Los datos del modal llegan vía window.JV_CONFIG.recepcionDatos
// (inyectados por el layout desde js_config).
?>
<!-- Encabezado -->
<div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 header-card">
    <div class="d-flex align-items-center gap-3">
        <div class="rec-header-icon">
            <i class="bi bi-box-arrow-in-down"></i>
        </div>
        <div>
            <h1 class="module-title">RECEPCIÓN</h1>
            <p class="module-subtitle">Ingreso de Mercancía al Inventario</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <span class="badge-jv badge-primary" style="font-size:.75rem;padding:8px 16px;"><i class="bi bi-box-arrow-in-down me-1"></i>RECEPCIÓN DE MERCADERÍA</span>
    </div>
</div>

<!-- Mensajes flash -->
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3 px-3 py-2">
        <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Estadísticas / Widgets -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-warning);">
            <div class="widget-icon" style="background:rgba(245,158,11,0.12);color:var(--jv-warning);">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
                <div class="widget-label">Compras por Recibir</div>
                <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo $total_por_recibir; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-info);">
            <div class="widget-icon" style="background:rgba(14,165,233,0.12);color:var(--jv-info);">
                <i class="bi bi-boxes"></i>
            </div>
            <div>
                <div class="widget-label">Unidades por Recibir</div>
                <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo number_format($unidades_por_recibir, 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-success);">
            <div class="widget-icon" style="background:rgba(22,163,74,0.12);color:var(--jv-success);">
                <i class="bi bi-box-arrow-in-down"></i>
            </div>
            <div>
                <div class="widget-label">Recepciones Hoy</div>
                <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo $recepciones_hoy; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Compras pendientes de recepción -->
<div class="card-jv card-jv-table p-0 mb-4">
    <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper">
        <i class="bi bi-inbox me-1" style="font-size:1rem;"></i>
        <span class="fw-bold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--jv-navy);">Compras Pendientes de Recepción</span>
        <span class="ms-auto"></span>
        <i class="bi bi-search me-1" style="font-size:1rem;color:var(--jv-orange);"></i>
        <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar pendientes..." id="buscarPendientes" onkeyup="filtrarPendientes()" style="box-shadow:none;font-size:.95rem;padding:8px 6px;max-width:260px;">
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th style="width:12%;">Factura</th>
                    <th style="width:10%;">Nro. Control</th>
                    <th style="width:17%;">Proveedor</th>
                    <th class="text-center" style="width:7%;">Items</th>
                    <th class="text-center" style="width:8%;">Unds</th>
                    <th class="text-center" style="width:11%;">Estado</th>
                    <th style="width:8%;">Fecha</th>
                    <th class="text-center" style="width:150px;">Acción</th>
                </tr>
            </thead>
            <tbody id="tablaPendientes">
                <?php if (count($compras_pendientes) > 0): foreach ($compras_pendientes as $compra_pendiente): ?>
                        <tr>
                            <td style="vertical-align:middle;text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($compra_pendiente['nro_factura']); ?></span></td>
                            <td style="color:var(--jv-text-muted);font-weight:600;"><?php echo htmlspecialchars($compra_pendiente['nro_control'] ?: '-'); ?></td>
                            <td class="td-proveedor text-uppercase fw-bold" data-tooltip="<?php echo htmlspecialchars($compra_pendiente['proveedor'] ?? 'S/P'); ?>"><?php echo htmlspecialchars($compra_pendiente['proveedor'] ?? 'S/P'); ?></td>
                            <td class="text-center"><span class="cant-badge"><?php echo (int)$compra_pendiente['items_pend']; ?></span></td>
                            <td class="text-center fw-bold"><?php echo (int)$compra_pendiente['unidades_pend']; ?></td>
                            <td class="text-center">
                                <?php // Estado de recepción: 'Pendiente' (sin recibir) o 'Parcial' (recibida en parte)
                                $estado_recepcion = $compra_pendiente['estado_recepcion']; ?>
                                <span class="badge-jv <?php echo $estado_recepcion === 'Parcial' ? 'badge-info' : 'badge-warning'; ?>"><i class="bi <?php echo $estado_recepcion === 'Parcial' ? 'bi-arrow-repeat' : 'bi-hourglass-split'; ?> me-1"></i><?php echo $estado_recepcion; ?></span>
                            </td>
                            <td class="fecha-cell"><?php echo date('d/m/Y', strtotime($compra_pendiente['fecha_compra'])); ?></td>
                            <td class="text-center">
                                <button type="button" class="btn-jv-primary btn-recibir w-100" style="border:none;padding:10px 12px;font-size:.9rem;font-weight:700;border-radius:8px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" onclick="abrirRecepcion(<?php echo $compra_pendiente['id_compra']; ?>)">
                                    <i class="bi bi-box-arrow-in-down me-1"></i>RECIBIR
                                </button>
                            </td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="estado-vacio">
                                <i class="bi bi-check2-circle"></i>
                                <span>No hay compras pendientes de recepción</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Últimas recepciones -->
<div class="card-jv card-jv-table p-0">
    <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper">
        <i class="bi bi-clock-history me-1" style="font-size:1rem;"></i>
        <span class="fw-bold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--jv-navy);">Últimas Recepciones</span>
        <span class="ms-auto"></span>
        <i class="bi bi-search me-1" style="font-size:1rem;color:var(--jv-orange);"></i>
        <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar recepciones..." id="buscarRecepciones" onkeyup="filtrarRecepciones()" style="box-shadow:none;font-size:.95rem;padding:8px 6px;max-width:260px;">
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th style="width:14%;">Fecha</th>
                    <th style="width:14%;">Factura</th>
                    <th style="width:22%;">Proveedor</th>
                    <th class="text-center" style="width:11%;">Productos</th>
                    <th class="text-center" style="width:11%;">Unidades</th>
                    <th style="width:16%;">Guía/Recibo</th>
                    <th style="width:12%;">Operador</th>
                </tr>
            </thead>
            <tbody id="tablaRecepciones">
                <?php if (count($recepciones) > 0): foreach ($recepciones as $recepcion): ?>
                        <tr>
                            <td class="fecha-cell"><?php echo date('d/m/Y H:i', strtotime($recepcion['fecha_movimiento'])); ?></td>
                            <td style="text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($recepcion['nro_factura'] ?? '-'); ?></span></td>
                            <td class="td-proveedor text-uppercase fw-bold" data-tooltip="<?php echo htmlspecialchars($recepcion['proveedor'] ?? 'S/P'); ?>"><?php echo htmlspecialchars($recepcion['proveedor'] ?? 'S/P'); ?></td>
                            <td class="text-center"><span class="cant-badge">+<?php echo (int)$recepcion['num_items']; ?></span></td>
                            <td class="text-center fw-bold text-success">+<?php echo (int)$recepcion['unidades']; ?></td>
                            <td style="color:var(--jv-text-muted);"><?php echo htmlspecialchars($recepcion['documento_recepcion'] ?: '-'); ?></td>
                            <td style="color:var(--jv-text-muted);"><?php echo htmlspecialchars($recepcion['operador'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="estado-vacio">
                                <i class="bi bi-inbox"></i>
                                <span>Aún no hay recepciones registradas</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Recibir compra -->
<div class="modal fade" id="modalRecepcion" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form method="POST" id="formRecepcion">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_recepcion" value="recibir">
                <input type="hidden" name="id_compra" id="recIdCompra">
                <input type="hidden" name="items_data" id="recItemsData">

                <div class="p-3" style="border-bottom:1px solid var(--jv-border);">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 font-brand" style="color:var(--jv-navy);font-size:1.3rem;letter-spacing:-.5px;"><i class="bi bi-box-arrow-in-down me-2"></i>RECIBIR MERCADERÍA</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <div class="p-3">
                    <div class="section-bg">
                        <div class="section-label"><i class="bi bi-receipt me-1"></i>Compra</div>
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
                                <label class="small fw-bold text-secondary mb-1">N° GUÍA / RECIBO <span class="fw-normal">(opcional)</span></label>
                                <input type="text" name="documento_recepcion" class="input-jv" id="recDocumento" maxlength="100" placeholder="Documento físico de entrega...">
                            </div>
                        </div>
                    </div>

                    <div class="section-bg" style="margin-bottom:0;">
                        <div class="section-label"><i class="bi bi-box-seam me-1"></i>Productos a recibir <span class="fw-normal text-secondary">(ajuste la cantidad a recibir, máx. lo pendiente)</span></div>
                        <div style="border:1px solid var(--jv-border);border-radius:8px;overflow:hidden;">
                            <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-card);">
                                <thead>
                                    <tr style="background:var(--jv-navy);">
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
                    <button type="button" class="btn btn-jv-success module-action-btn" id="btnRecibir" onclick="return confirmarRecepcion(this)"><i class="bi bi-check-lg me-1"></i> Registrar Recepción</button>
                </div>
            </form>
        </div>
    </div>
</div>