<?php
/** @var array $ventas */
/** @var array $devoluciones */
/** @var string $csrf */
/** @var array|null $flash */

$devolucionesUrl = APP_URL_BASE . 'index.php?url=devoluciones';
?>
<!-- Encabezado -->
<div class="card-jv d-flex justify-content-between align-content-center flex-wrap gap-2 mb-3 header-card">
    <div class="d-flex align-items-center gap-3">
        <div class="dev-header-icon">
            <i class="bi bi-arrow-return-left"></i>
        </div>
        <div>
            <h1 class="module-title">DEVOLUCIONES</h1>
            <p class="module-subtitle">Devoluciones de Clientes con Control FEFO</p>
        </div>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo'] ?? 'info'; ?> mb-3 px-3 py-2 d-flex align-items-center gap-2">
        <i class="bi <?php echo ($flash['tipo'] ?? '') === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
        <?php echo htmlspecialchars($flash['texto'] ?? ''); ?>
    </div>
<?php endif; ?>

<!-- Buscar venta -->
<div class="card-jv mb-3">
    <div class="card-header-jv" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
        <i class="bi bi-search me-1"></i> Buscar Venta a Devolver
    </div>
    <div class="card-body-jv p-3">
        <form method="GET" action="<?php echo $devolucionesUrl; ?>" class="d-flex gap-2">
            <input type="text" class="form-control input-jv" name="q" placeholder="Nro factura, cliente, RIF..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" style="max-width:400px;">
            <button type="submit" class="btn btn-jv-primary"><i class="bi bi-search me-1"></i>Buscar</button>
        </form>
    </div>
</div>

<!-- Listado de ventas -->
<div class="card-jv mb-3">
    <div class="card-header-jv" style="background:linear-gradient(135deg,#2563eb,#7c3aed);">
        <i class="bi bi-receipt me-1"></i> Ventas Disponibles
    </div>
    <div class="card-body-jv p-0">
        <div class="table-responsive">
            <table class="table-jv table-hover mb-0" id="tablaVentas">
                <thead>
                    <tr>
                        <th>Nro Factura</th>
                        <th>Cliente</th>
                        <th>RIF/Cédula</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ventas)): ?>
                        <?php foreach ($ventas as $v): ?>
                            <tr>
                                <td><span class="codigo-badge"><?php echo htmlspecialchars($v['nro_factura_manual'] ?? '-'); ?></span></td>
                                <td><?php echo htmlspecialchars($v['cliente'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($v['rif_cliente'] ?? '-'); ?></td>
                                <td><span class="badge-jv badge-info"><?php echo htmlspecialchars($v['tipo_nombre'] ?? ''); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($v['fecha_salida'])); ?></td>
                                <td class="fw-bold">$<?php echo number_format($v['total_monto'], 2); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn-action" style="color:var(--jv-purple);border-color:var(--jv-purple);" onclick="seleccionarVenta(<?php echo (int)$v['id_salida']; ?>)" data-tooltip="Seleccionar para devolver">
                                        <i class="bi bi-arrow-return-left"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="estado-vacio">
                                    <i class="bi bi-inbox"></i>
                                    <span>No se encontraron ventas</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Panel de devolución (se muestra al seleccionar venta) -->
<div class="card-jv mb-3" id="panelDevolucion" style="display:none;">
    <div class="card-header-jv" style="background:linear-gradient(135deg,#059669,#10b981);">
        <i class="bi bi-arrow-return-left me-1"></i> <span id="devVentaTitulo">Registrar Devolución</span>
    </div>
    <div class="card-body-jv p-3">
        <form method="POST" action="<?php echo $devolucionesUrl; ?>" id="formDevolucion">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="accion_devolucion" value="1">
            <input type="hidden" name="id_salida" id="dev_id_salida">
            <input type="hidden" name="productos_data" id="dev_productos_data">

            <div class="row g-3 mb-3">
                <div class="col-md-12">
                    <label class="jv-modal-label">MOTIVO DE LA DEVOLUCIÓN <span style="color:var(--jv-red);">*</span></label>
                    <textarea class="form-control input-jv" name="motivo" id="dev_motivo" rows="2" required placeholder="Describa el motivo de la devolución..."></textarea>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-jv" id="tablaProductosDevolucion">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Comprado</th>
                            <th>Stock Actual</th>
                            <th>Cant. Devolver</th>
                            <th>Lote (FEFO)</th>
                            <th>Precio Venta</th>
                        </tr>
                    </thead>
                    <tbody id="devProductosBody">
                        <tr>
                            <td colspan="6" style="text-align:center;color:var(--jv-text-muted);padding:24px;">Cargando productos...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3" style="border-top:1px solid var(--jv-border);padding-top:16px;">
                <span id="devResumen" style="font-size:.9rem;color:var(--jv-text-muted);"></span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-jv-danger" onclick="cancelarDevolucion()" style="padding:12px 28px;font-size:1rem;">
                        <i class="bi bi-x-lg me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-jv-success module-action-btn" id="btnDevolver">
                        <i class="bi bi-check-lg me-1"></i>Registrar Devolución
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Últimas devoluciones -->
<div class="card-jv">
    <div class="card-header-jv" style="background:linear-gradient(135deg,#475569,#64748b);">
        <i class="bi bi-clock-history me-1"></i> Últimas Devoluciones
    </div>
    <div class="card-body-jv p-0">
        <div class="table-responsive">
            <table class="table-jv table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Venta</th>
                        <th>Cliente</th>
                        <th>Productos</th>
                        <th>Unidades</th>
                        <th>Registrado por</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($devoluciones)): ?>
                        <?php foreach ($devoluciones as $d): ?>
                            <tr>
                                <td><span class="codigo-badge">#<?php echo (int)$d['id_movimiento']; ?></span></td>
                                <td><?php echo htmlspecialchars($d['nro_factura_manual'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($d['cliente'] ?? '-'); ?></td>
                                <td style="max-width:250px;"><?php echo htmlspecialchars($d['productos_resumen'] ?? '-'); ?></td>
                                <td class="fw-bold"><?php echo (int)$d['total_unidades']; ?></td>
                                <td><?php echo htmlspecialchars($d['registrado_por'] ?? '-'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($d['fecha_movimiento'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="estado-vacio">
                                    <i class="bi bi-inbox"></i>
                                    <span>No hay devoluciones registradas</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
