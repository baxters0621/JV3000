<?php
// ==========================================
// VISTA: Salidas / Ventas (index)
// ==========================================
// Solo muestra los datos. No hace consultas.
// El layout principal inyecta sidebar, bootstrap,
// sweetalert, JV_CONFIG (c0=mapa tipo→grupo, c1=CSRF) y salidas.js.
?>
<!-- Encabezado -->
<div class="d-flex align-items-center gap-3 mb-4">
    <div class="sal-header-icon"><i class="bi bi-cart-x-fill"></i></div>
    <div>
        <h1 class="module-title">SALIDAS / VENTAS</h1>
        <p class="module-subtitle">Notas de Entrega y Despacho</p>
    </div>
    <div class="ms-auto">
        <button class="btn btn-jv-primary module-action-btn" onclick="nuevaSalida()">
            <i class="bi bi-cart-plus-fill me-2"></i>NUEVA VENTA
        </button>
    </div>
</div>

<!-- Mensajes flash -->
<?php if (!empty($flash)): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4">
        <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Estadísticas / Widgets -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-success);">
            <div class="widget-icon" style="background:rgba(22,163,74,0.12);color:var(--jv-success);">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div>
                <div class="widget-label">Ventas del Mes</div>
                <div class="widget-value" style="color: var(--jv-text-primary);">$<?php echo number_format($kpis['ventas_mes'], 2); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-info);">
            <div class="widget-icon" style="background:rgba(14,165,233,0.12);color:var(--jv-info);">
                <i class="bi bi-boxes"></i>
            </div>
            <div>
                <div class="widget-label">Unidades Vendidas (Mes)</div>
                <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo number_format($kpis['und_mes'], 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="widget-card" style="border-left:4px solid var(--jv-warning);">
            <div class="widget-icon" style="background:rgba(245,158,11,0.12);color:var(--jv-warning);">
                <i class="bi bi-receipt"></i>
            </div>
            <div>
                <div class="widget-label">Ventas de Hoy</div>
                <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo (int)$kpis['ventas_hoy']; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de ventas -->
<div class="card-jv p-0">
    <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper flex-wrap">
        <i class="bi bi-search me-1" style="font-size:1rem;color:var(--jv-orange);"></i>
        <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por nota, control, cliente, RIF, productos, tipo..." id="buscarSal" onkeyup="filtrarSalidas()" style="box-shadow:none;font-size:.95rem;padding:8px 6px;max-width:340px;">
    </div>
    <div class="table-responsive">
        <table class="table-jv mb-0">
            <thead>
                <tr>
                    <th style="width:10%;">Nota</th>
                    <th style="width:10%;">N° Control</th>
                    <th style="width:15%;">Cliente</th>
                    <th style="width:20%;">Productos</th>
                    <th class="text-center" style="width:6%;">Cant</th>
                    <th class="text-center" style="width:12%;">Tipo</th>
                    <th style="width:9%;">Total</th>
                    <th class="text-center" style="width:8%;">Fecha</th>
                    <th class="text-center" style="width:10%;">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaSalidas">
                <?php if (count($salidas) > 0): ?>
                    <?php foreach ($salidas as $row): ?>
                        <?php
                            $productos_full = $row['productos_list'] ?? '';
                            $g = Salida::grupoDeTipo($row['tipo_mov_nombre'] ?? '');
                        ?>
                        <tr>
                            <td style="vertical-align:middle;text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura_manual'] ?: '#' . $row['id_salida']); ?></span></td>
                            <td class="td-control" data-tooltip="<?php echo htmlspecialchars($row['nro_control']); ?>"><?php echo htmlspecialchars($row['nro_control']); ?></td>
                            <td class="text-uppercase td-cliente" data-tooltip="<?php echo htmlspecialchars(($row['cliente'] ?? 'S/Cliente') . ' - ' . ($row['rif_cliente'] ?? 'S/RIF')); ?>">
                                <div class="cli-nombre"><?php echo htmlspecialchars($row['cliente'] ?? 'S/Cliente'); ?></div>
                                <div class="cli-rif"><?php echo htmlspecialchars($row['rif_cliente'] ?? 'S/RIF'); ?></div>
                            </td>
                            <td class="td-productos" data-tooltip="<?php echo htmlspecialchars($productos_full); ?>"><?php echo htmlspecialchars($productos_full); ?></td>
                            <td class="text-center"><span class="badge-jv badge-danger" style="padding:6px 14px;">-<?php echo $row['total_cantidad']; ?></span></td>
                            <td class="text-center"><?php
                                $tn = $row['tipo_mov_nombre'] ?? '';
                                $obs = $row['observaciones'] ?? '';
                                $causa = '';
                                if (preg_match('/^Causa:\s*(.+?)(?:\s*\||$)/', $obs, $m)) $causa = trim($m[1]);
                                if ($g === 'venta') echo '<span class="badge-jv badge-success"><i class="bi bi-cart me-1"></i>Venta</span>';
                                elseif ($g === 'regalias') echo '<span class="badge-jv badge-info"><i class="bi bi-gift me-1"></i>Regalía</span>';
                                else echo '<span class="badge-jv badge-warning" style="cursor:pointer;" title="' . htmlspecialchars($tn) . ($causa ? ': ' . htmlspecialchars($causa) : '') . '" onclick="verDetalleDano(\'' . htmlspecialchars($tn, ENT_QUOTES) . '\', \'' . htmlspecialchars($causa, ENT_QUOTES) . '\')"><i class="bi bi-exclamation-triangle me-1"></i>' . htmlspecialchars($tn) . '</span>';
                            ?></td>
                            <td class="td-total" style="<?php echo $g === 'merma' ? 'color:var(--jv-danger);' : 'color:var(--jv-success);'; ?>">$<?php echo number_format($row['total_monto'] ?? 0, 2); ?></td>
                            <td class="text-center fecha-cell"><?php echo date('d/m/Y', strtotime($row['fecha_salida'])); ?></td>
                            <td class="text-center" style="white-space:nowrap;">
                                <button class="btn-action" onclick="verFactura(<?php echo $row['id_salida']; ?>)" title="Ver Nota">
                                    <i class="bi bi-receipt"></i>
                                </button>
                                <button class="btn-action" onclick='editarSalida(<?php echo json_encode($row); ?>)' title="Editar">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php if (Security::esAdmin()): ?>
                                    <button class="btn-action" onclick="confirmarEliminar(<?php echo $row['id_salida']; ?>)" title="Anular">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="estado-vacio">
                                <i class="bi bi-clipboard-x"></i>
                                <span>No hay registros de ventas</span>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Nueva / Editar salida -->
<div class="modal fade" id="modalSalida" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form action="" method="POST" id="formSalida" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_salida" id="s_accion" value="registrar">
                <input type="hidden" name="id_salida" id="s_id_edit">
                <div class="modal-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bolder font-brand text-uppercase m-0 modal-title-jv" id="modalTitle">REGISTRAR MOVIMIENTO</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="section-bg">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2">TIPO DE MOVIMIENTO</label>
                                <select name="id_tipo_mov" id="s_tipo" class="input-jv" required onchange="toggleCampos()">
                                    <option value="">Seleccione tipo...</option>
                                    <?php foreach ($tipos_mov as $tm):
                                        $grupo = $tipos_mov_map[$tm['id_tipo_mov']];
                                    ?>
                                        <option value="<?php echo $tm['id_tipo_mov']; ?>" data-grupo="<?php echo $grupo; ?>"><?php echo $tm['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2">FECHA</label>
                                <input type="date" id="s_fecha" class="input-jv" value="<?php echo date('Y-m-d'); ?>" disabled>
                                <input type="hidden" name="fecha_salida" id="s_fecha_hidden" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- GRUPO: VENTA (Cliente + RIF) -->
                    <div class="sal-field-group" data-grupo="venta">
                        <div class="section-bg">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary mb-2">CLIENTE</label>
                                    <div class="com-toolbox">
                                        <input type="text" class="input-jv w-100" id="buscarClienteSal" placeholder="Buscar o escribir cliente..." autocomplete="off">
                                        <input type="hidden" name="id_cliente" id="s_id_cliente">
                                        <input type="hidden" name="cliente" id="s_cliente">
                                        <div class="com-resultados" id="resultadosBusquedaCli"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary mb-2">RIF / CÉDULA <span style="color:var(--jv-danger);">*</span></label>
                                    <div class="d-flex gap-2">
                                        <select id="s_rif_tipo" class="input-jv" style="max-width:70px;flex-shrink:0;" onchange="validarRIFInput()">
                                            <option value="V">V-</option>
                                            <option value="J">J-</option>
                                            <option value="E">E-</option>
                                            <option value="P">P-</option>
                                            <option value="G">G-</option>
                                            <option value="C">C-</option>
                                        </select>
                                        <input type="text" id="s_rif_num" class="input-jv" placeholder="Número de identificación" oninput="validarRIFInput()" style="flex:1;" inputmode="numeric">
                                        <input type="hidden" name="rif_cliente" id="s_rif">
                                    </div>
                                    <div id="s-rif-msg" class="small mt-1" style="min-height:18px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- GRUPO: REGALIAS (solo Cliente) -->
                    <div class="sal-field-group" data-grupo="regalias">
                        <div class="section-bg">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="small fw-bold text-secondary mb-1">MOTIVO *</label>
                                    <select name="motivo_regalia" id="s_motivo_reg" class="input-jv">
                                        <option value="">Seleccionar...</option>
                                        <option>Promoción</option>
                                        <option>Cortesía / Fidelización</option>
                                        <option>Garantía</option>
                                        <option>Producto Dañado</option>
                                        <option>Muestra</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="small fw-bold text-secondary mb-2">CLIENTE</label>
                                    <input type="text" id="s_cliente_reg" class="input-jv" placeholder="Nombre o Razón Social" oninput="document.getElementById('s_cliente').value=this.value">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- GRUPO: MERMA (Causa + Motivo) -->
                    <div class="sal-field-group" data-grupo="merma">
                        <div class="section-bg">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="small fw-bold text-secondary mb-1">CAUSA *</label>
                                    <select name="causa_ajuste" id="s_causa" class="input-jv">
                                        <option value="">Seleccionar...</option>
                                        <option>Producto vencido</option>
                                        <option>Dañado/Averiado</option>
                                        <option>Robo hormiga</option>
                                        <option>Error de inventario</option>
                                        <option>Merma operativa</option>
                                        <option>Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="small fw-bold text-secondary mb-1">MOTIVO <span class="fw-normal">(opcional)</span></label>
                                    <textarea name="descripcion_motivo" id="s_desc_motivo" class="input-jv" rows="2" placeholder="Detalle adicional..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PRODUCTOS: Controles + Tabla (siempre visible) -->
                    <div class="section-bg">
                        <div class="section-label"><i class="bi bi-box-seam me-1"></i>Agregar productos</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="small fw-bold text-secondary mb-1">Producto</label>
                                <div class="com-toolbox">
                                    <input type="text" class="input-jv w-100" id="buscarProductoSal" placeholder="Buscar por nombre o SKU..." autocomplete="off">
                                    <input type="hidden" id="selProductoSalId">
                                    <input type="hidden" id="selProductoSalNombre">
                                    <div class="com-resultados" id="resultadosBusquedaSal"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-secondary mb-1">Cant</label>
                                <input type="number" id="s_cant" class="input-jv" value="1" min="1" max="999999" oninput="if(this.value>999999)this.value=999999">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-secondary mb-1">Precio $</label>
                                <input type="text" inputmode="decimal" id="s_precio" class="input-jv" placeholder="0.00" readonly style="background:var(--jv-bg-primary);cursor:not-allowed;color:var(--jv-text-muted);">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn-jv-primary w-100" style="margin-top:22px;padding:12px 8px;" onclick="agregarProductoSalida()">
                                    <i class="bi bi-plus-lg"></i> Agregar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA DE PRODUCTOS -->
                    <div class="sal-prod-table">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:26px;">#</th>
                                    <th>Producto</th>
                                    <th style="width:50px;">Cant</th>
                                    <th style="width:85px;">Precio</th>
                                    <th style="width:85px;">Total</th>
                                    <th style="width:26px;"></th>
                                </tr>
                            </thead>
                            <tbody id="s_productos_body">
                                <tr id="s_fila_vacia"><td colspan="6" class="sal-fila-vacia">⬆ Agregue productos con los controles de arriba</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sal-totales-bar">
                        <span class="tt-label">Productos <span class="tt-valor" id="s_total_items">0</span></span>
                        <span class="tt-label ms-auto">Total Venta <span class="tt-valor" id="s_total_monto">$0.00</span></span>
                    </div>

                    <!-- SOLICITUD A COMPRAS (venta sin stock) -->
                    <div id="solicitud_compras_box" class="section-bg" style="display:none;border-left:4px solid var(--jv-warning);">
                        <div class="section-label"><i class="bi bi-cart-plus me-1"></i>PRODUCTOS SOLICITADOS A COMPRAS</div>
                        <p class="small text-secondary fw-bold mb-2">Productos sin stock que se pedirán a Compras en una sola solicitud.</p>
                        <div style="border:1px solid var(--jv-border);border-radius:8px;overflow:hidden;">
                            <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-card);">
                                <thead>
                                    <tr style="background:var(--jv-navy);">
                                        <th style="padding:8px;width:30px;text-align:center;color:#fff;font-size:.8rem;text-transform:uppercase;">#</th>
                                        <th style="padding:8px;color:#fff;font-size:.8rem;text-transform:uppercase;">Producto</th>
                                        <th style="padding:8px;width:70px;text-align:center;color:#fff;font-size:.8rem;text-transform:uppercase;">Cant</th>
                                        <th style="padding:8px;width:30px;text-align:center;color:#fff;font-size:.8rem;text-transform:uppercase;"></th>
                                    </tr>
                                </thead>
                                <tbody id="s_solicitud_body"></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" class="btn-jv-primary" id="btnEnviarSolicitud" style="padding:10px 20px;font-size:.9rem;font-weight:700;border:none;border-radius:8px;color:#fff;" onclick="enviarSolicitudCompras()">
                                <i class="bi bi-truck me-1"></i> ENVIAR SOLICITUD A COMPRAS
                            </button>
                        </div>
                    </div>

                    <!-- OBSERVACIONES -->
                    <div class="section-bg">
                        <label class="small fw-bold text-secondary mb-2">OBSERVACIONES</label>
                        <textarea name="observaciones" id="s_obs" class="input-jv" rows="1" placeholder="Notas adicionales..."></textarea>
                    </div>

                    <button type="button" class="btn btn-jv-primary w-100 py-2 fw-bolder text-uppercase mt-1" id="btnPreview" onclick="enviarPreview()">
                        📄 VISTA PREVIA NOTA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
