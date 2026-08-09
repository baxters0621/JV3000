<?php
// ==========================================
// VISTA: Inventario / Productos (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
?>
<!-- Encabezado -->
<div class="card-jv d-flex align-items-center gap-3 mb-3" style="padding: 18px 24px; border-left: 4px solid var(--jv-orange);">
    <div class="module-header-icon" style="background: var(--jv-orange); box-shadow: 0 4px 16px rgba(234,88,12,0.25);">
        <i class="bi bi-box-seam text-white" style="font-size:1.5rem;"></i>
    </div>
    <div>
        <h1 class="module-title">INVENTARIO</h1>
        <p class="module-subtitle">Control Maestro de Existencias</p>
    </div>
</div>

<!-- Mensajes flash -->
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> mb-3 px-3 py-2">
        <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Tabla de productos -->
<div class="card-jv card-jv-table p-0">
    <div class="buscador-wrapper d-flex align-items-center flex-wrap gap-2 px-3 py-2">
        <i class="bi bi-search me-1" style="color: var(--jv-orange);"></i>
        <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por nombre, código, proveedor, categoría, estado..." id="buscar" onkeyup="filtrar()" style="box-shadow: none; max-width: 340px;">
        <span class="actions-divider mx-1"></span>
        <span class="small fw-bold text-uppercase" style="color:var(--jv-text-muted);font-size:.8rem;letter-spacing:1px;">Estado:</span>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn-filter-prod active" data-status="todas" onclick="filtrarStatus(this)">Todos</button>
            <button type="button" class="btn-filter-prod" data-status="Activo" onclick="filtrarStatus(this)">Activos</button>
            <button type="button" class="btn-filter-prod" data-status="Inactivo" onclick="filtrarStatus(this)">Inactivos</button>
        </div>
        <span class="actions-divider mx-1"></span>
        <span class="small fw-bold text-uppercase" style="color:var(--jv-text-muted);font-size:.8rem;letter-spacing:1px;">Vence:</span>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-sm btn-filtro-venc active" data-venc="todas" onclick="filtrarVenc(this)" style="border-radius:6px 0 0 6px;background:rgba(234,88,12,0.15);color:var(--jv-orange);border:1px solid rgba(234,88,12,0.3);">Todas</button>
            <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="vencido" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-danger);border:1px solid rgba(220,38,38,0.3);">Vencidos</button>
            <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="proximo" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-warning);border:1px solid rgba(217,119,6,0.3);">Próximo</button>
            <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="pronto" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-warning);border:1px solid rgba(217,119,6,0.3);">Pronto</button>
            <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="vigente" onclick="filtrarVenc(this)" style="border-radius:0 6px 6px 0;background:transparent;color:var(--jv-success);border:1px solid rgba(22,163,74,0.3);">Vigente</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:9%;">CÓDIGO</th>
                    <th style="width:22%;">PRODUCTO</th>
                    <th style="width:13%;">CATEGORÍA</th>
                    <th style="width:14%;">PROVEEDOR</th>
                    <th class="text-center" style="width:8%;">STOCK</th>
                    <th style="width:9%;">PRECIO</th>
                    <th class="text-center" style="width:7%;">VENCE</th>
                    <th class="text-center" style="width:8%;">ESTADO</th>
                    <?php if ($esAdmin): ?>
                        <th class="text-center" style="width:10%;">ACCIONES</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="tablaProductos">
                <?php if (!empty($productos)): ?>
                    <?php foreach ($productos as $row):
                        $stk = intval($row['stock_actual']);
                        $min = intval($row['stock_minimo']);
                        $max = max(1, intval($row['capacidad'] ?? 100));
                        if ($stk == 0) {
                            $stk_cls = 'danger';
                            $stk_lbl = 'AGOTADO';
                            $stk_pct = 0;
                        } elseif ($stk <= $min) {
                            $stk_cls = 'danger';
                            $stk_lbl = 'BAJO';
                            $stk_pct = max(5, ($stk / $max) * 100);
                        } elseif ($stk >= $max) {
                            $stk_cls = 'info';
                            $stk_lbl = 'COMPLETO';
                            $stk_pct = 100;
                        } else {
                            $pct = ($stk / $max) * 100;
                            $stk_cls = 'success';
                            $stk_lbl = 'OK';
                            $stk_pct = $pct;
                        }
                        $bar_color = $stk_cls == 'danger' ? '#DC2626' : ($stk_cls == 'info' ? '#2563EB' : '#16A34A');
                    ?>
                        <?php
                        $venc = $row['fecha_vencimiento'] ?? '';
                        $venc_cls = '';
                        $vc = 'badge-secondary';
                        $vi = 'dash-circle';
                        $vd = '';
                        if ($venc) {
                            $dias_v = floor((strtotime($venc) - time()) / 86400);
                            $vd = date('d/m/Y', strtotime($venc));
                            if ($dias_v < 0) {
                                $venc_cls = 'vencido';
                                $vc = 'badge-danger';
                                $vi = 'exclamation-triangle';
                            } elseif ($dias_v <= 7) {
                                $venc_cls = 'proximo';
                                $vc = 'badge-danger';
                                $vi = 'clock';
                            } elseif ($dias_v <= 30) {
                                $venc_cls = 'pronto';
                                $vc = 'badge-warning';
                                $vi = 'clock';
                            } else {
                                $venc_cls = 'vigente';
                                $vc = 'badge-success';
                                $vi = 'check-circle';
                            }
                        }
                        ?>
                        <tr data-id="<?php echo $row['id_producto']; ?>" data-sku="<?php echo strtolower(htmlspecialchars($row['sku'])); ?>" data-nombre="<?php echo strtolower(htmlspecialchars($row['nombre_producto'])); ?>" data-prov="<?php echo strtolower(htmlspecialchars($row['ultimo_proveedor'] ?? '')); ?>" data-prov-id="<?php echo intval($row['id_proveedor'] ?? 0); ?>" data-stock="<?php echo $row['stock_actual']; ?>" data-minimo="<?php echo $row['stock_minimo']; ?>" data-max="<?php echo $max; ?>" data-maximo="<?php echo intval($row['stock_maximo'] ?? 0); ?>" data-pvp="<?php echo $row['precio_venta']; ?>" data-costo="<?php echo $row['precio_costo']; ?>" data-status="<?php echo $row['status']; ?>" data-venc="<?php echo $row['fecha_vencimiento'] ?? ''; ?>" data-venc-cls="<?php echo $venc_cls; ?>">
                            <td class="td-prod-sku">
                                <span class="codigo-badge"><?php echo htmlspecialchars($row['sku']); ?></span>
                            </td>
                            <td class="td-prod-nombre" data-tooltip="<?php echo htmlspecialchars($row['nombre_producto']); ?>">
                                <span class="prod-nombre text-uppercase"><?php echo htmlspecialchars($row['nombre_producto']); ?></span>
                            </td>
                            <td class="td-prod-cat" data-tooltip="<?php echo htmlspecialchars($row['nombre_cat'] ?? 'Sin categoría'); ?>">
                                <span class="prod-cat"><?php echo htmlspecialchars($row['nombre_cat'] ?? 'Sin categoría'); ?></span>
                            </td>
                            <td class="td-prod-prov" data-tooltip="<?php echo htmlspecialchars($row['ultimo_proveedor'] ?? '—'); ?>">
                                <span class="prod-prov"><?php echo htmlspecialchars($row['ultimo_proveedor'] ?? '—'); ?></span>
                            </td>
                            <td class="td-stock text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                    <span class="stk-num"><?php echo $stk; ?></span>
                                    <span class="badge-jv badge-<?php echo $stk_cls; ?>" style="font-size:0.75rem;padding:3px 10px;"><?php echo $stk_lbl; ?></span>
                                </div>
                                <div style="height:6px;background:rgba(15,26,46,0.08);border-radius:3px;overflow:hidden;margin:0 auto;max-width:100px;">
                                    <div style="height:100%;width:<?php echo $stk_pct; ?>%;background:<?php echo $bar_color; ?>;border-radius:3px;transition:width 0.3s;"></div>
                                </div>
                                <div class="stk-meta">
                                    Mín: <?php echo $min; ?> · Máx: <?php echo $max; ?>
                                </div>
                            </td>
                            <td>
                                <span class="prod-precio">$<?php echo number_format($row['precio_venta'], 2); ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge-jv <?php echo $vc; ?>" style="white-space:nowrap;font-size:.85rem;">
                                    <i class="bi bi-<?php echo $vi; ?>"></i> <?php echo $vd ?: '—'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge-jv <?php echo ($row['status'] == 'Activo') ? 'badge-success' : 'badge-danger'; ?>" style="font-size:.85rem;">
                                    <i class="bi bi-<?php echo ($row['status'] == 'Activo') ? 'eye' : 'eye-off'; ?>"></i>
                                    <?php echo strtoupper($row['status']); ?>
                                </span>
                            </td>
                            <?php if ($esAdmin): ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button type="button" class="btn btn-sm p-0" style="width:38px;height:38px;border-radius:8px;background:rgba(234,88,12,0.12);color:var(--jv-orange);border:1px solid rgba(234,88,12,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="editarProducto(<?php echo $row['id_producto']; ?>)" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($row['status'] === 'Activo'): ?>
                                            <button type="button" class="btn btn-sm p-0" style="width:38px;height:38px;border-radius:8px;background:rgba(220,38,38,0.12);color:var(--jv-danger);border:1px solid rgba(220,38,38,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="toggleProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre_producto']); ?>', 'desactivar')" title="Desactivar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php if ($venc_cls === 'vencido'): ?>
                                                <button type="button" class="btn btn-sm p-0 ms-1" style="width:38px;height:38px;border-radius:8px;background:rgba(100,116,139,0.12);color:var(--jv-text-muted);border:1px solid rgba(100,116,139,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="bajaVencido(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre_producto']); ?>')" title="Dar de baja por vencimiento">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm p-0" style="width:38px;height:38px;border-radius:8px;background:rgba(22,163,74,0.12);color:var(--jv-success);border:1px solid rgba(22,163,74,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="toggleProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre_producto']); ?>', 'activar')" title="Reactivar">
                                                <i class="bi bi-play-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $esAdmin ? 9 : 8; ?>" class="text-center py-5">
                            <i class="bi bi-box-seam d-block mb-3 mx-auto" style="font-size: 3.5rem; color: var(--jv-text-muted);"></i>
                            <span class="text-uppercase" style="color: var(--jv-text-primary); font-weight: 700; font-size: 1.1rem;">Inventario vacío</span>
                            <p class="mt-2" style="color: var(--jv-text-muted); font-size: 1rem;">Registra entradas desde <strong style="color: var(--jv-orange);">Compras</strong> para ver productos aquí</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-4" style="border-top: 1px solid var(--jv-border);">
            <div class="small text-secondary">
                Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $registros_por_pagina, $total_registros); ?> de <?php echo $total_registros; ?> productos
            </div>
            <nav>
                <ul class="pagination pagination-sm m-0">
                    <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" style="background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $pagina_actual - 1; ?>">Anterior</a>
                    </li>
                    <?php
                    $inicio_p = max(1, $pagina_actual - 2);
                    $fin_p = min($total_paginas, $pagina_actual + 2);
                    for ($i = $inicio_p; $i <= $fin_p; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                            <a class="page-link" style="<?php echo ($i == $pagina_actual) ? 'background:var(--jv-orange); border-color:var(--jv-orange); color:#fff;' : 'background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);'; ?>" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" style="background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);" href="<?php echo APP_URL_BASE; ?>index.php?url=productos&p=<?php echo $pagina_actual + 1; ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php if ($esAdmin): ?>
    <!-- Modal: Editar producto -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <form method="POST" id="formEditar">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="accion" value="editar_producto">
                    <input type="hidden" name="id_producto" id="edit_id">
                    <div class="p-3" style="border-bottom:1px solid var(--jv-border);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 font-brand" style="color:var(--jv-navy);font-size:.95rem;letter-spacing:-.5px;">
                                <i class="bi bi-pencil-square me-2"></i>EDITAR PRODUCTO
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="mb-2">
                            <label class="small fw-bold text-secondary mb-1">PRODUCTO</label>
                            <input type="text" class="input-jv" id="edit_nombre" readonly disabled style="color:var(--jv-text-muted);">
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-secondary mb-1">Código</label>
                            <input type="text" class="input-jv" id="edit_sku" readonly disabled style="color:var(--jv-text-muted);">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">STOCK ACTUAL</label>
                                <input type="text" class="input-jv" id="edit_stock" readonly disabled style="color:var(--jv-text-muted);">
                            </div>
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">STOCK MÍNIMO</label>
                                <input type="number" class="input-jv" id="edit_minimo" name="stock_minimo" min="0" max="99999">
                            </div>
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">CAPACIDAD MÁX. <span class="text-jv-muted" style="font-weight:400;">(0 = categoría)</span></label>
                                <input type="number" class="input-jv" id="edit_maximo" name="stock_maximo" min="0" max="999999">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">PRECIO VENTA ($)</label>
                                <input type="number" class="input-jv" id="edit_pvp" name="precio_venta" step="0.01" min="0" max="999999">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">PRECIO COSTO ($)</label>
                                <input type="number" class="input-jv" id="edit_costo" name="precio_costo" step="0.01" min="0" max="999999">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">ESTADO</label>
                                <select class="input-jv" id="edit_status" name="status">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">VENCIMIENTO</label>
                                <input type="date" class="input-jv" id="edit_vencimiento" name="fecha_vencimiento">
                            </div>
                            <div class="col-4">
                                <label class="small fw-bold text-secondary mb-1">PROVEEDOR <span style="color:var(--jv-danger);">*</span></label>
                                <select class="input-jv" id="edit_proveedor" name="id_proveedor" required>
                                    <option value="">SELECCIONE...</option>
                                    <?php foreach ($proveedores_list as $prov): ?>
                                        <option value="<?php echo $prov['id_proveedor']; ?>"><?php echo htmlspecialchars($prov['nombre_empresa']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
                        <button type="button" class="btn btn-jv-danger" style="padding:8px 20px;font-size:.8rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="button" class="btn btn-jv-success" style="padding:8px 20px;font-size:.8rem;" onclick="return validarEditarProducto(this)"><i class="bi bi-check-lg me-1"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
