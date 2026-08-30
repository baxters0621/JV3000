<?php

/** @var array<string, mixed> $kpis */
/** @var array<int, array<string, mixed>> $salidas */
/** @var string $csrf */
/** @var array<int, array<string, mixed>> $tipos_mov */
/** @var array<string, string> $tipos_mov_map */
/** @var array<int, array<string, mixed>> $cli_gestion Clientes para gestión (admin) */
/** @var int $cli_activos Total de clientes activos */
/** @var bool $es_admin Es administrador */

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
    <div class="ms-auto d-flex align-items-center gap-2">
        <?php if ($es_admin): ?>
            <button class="btn btn-outline-secondary module-action-btn d-flex align-items-center gap-2" onclick="abrirGestorCli()" style="border-color:#059669;color:#059669;font-weight:700;font-size:.88rem;padding:10px 18px;border-radius:10px;">
                <i class="bi bi-people-fill"></i>CLIENTES
                <span class="badge rounded-pill" style="background:#059669;color:#fff;font-size:.78rem;padding:4px 10px;margin-left:2px;"><?php echo $cli_activos; ?></span>
            </button>
        <?php endif; ?>
        <button class="btn btn-jv-primary module-action-btn" onclick="nuevaSalida()">
            <i class="bi bi-cart-plus-fill me-2"></i>NUEVA VENTA
        </button>
    </div>
</div>

<!-- Mensajes flash -->
<?php if (!empty($flash)): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4" id="flash<?php echo ucfirst($flash['tipo']); ?>" data-texto="<?php echo htmlspecialchars($flash['texto']); ?>">
        <i class="bi bi-shield-check"></i><?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<!-- Script: datos de clientes para gestión (JSON) -->
<?php if ($es_admin): ?>
<script>
window.JV_CLIENTES = <?php echo json_encode(array_map(function($c) {
    return [
        'id_cliente' => (int)$c['id_cliente'],
        'nombre'     => (string)$c['nombre'],
        'documento'  => (string)($c['documento'] ?? ''),
        'telefono'   => (string)($c['telefono'] ?? ''),
        'direccion'  => (string)($c['direccion'] ?? ''),
        'status'     => (string)$c['status'],
    ];
}, $cli_gestion), JSON_UNESCAPED_UNICODE); ?>;
</script>
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
        <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por nota, control, cliente, RIF, productos, tipo..." id="buscarSal" aria-label="Buscar salida" onkeyup="filtrarSalidas()" style="box-shadow:none;font-size:.95rem;padding:8px 6px;max-width:340px;">
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
                    <?php foreach ($salidas as $outgoingRecord): ?>
                        <?php
                        $productos_lista = $outgoingRecord['productos_list'] ?? '';
                        // Grupo del tipo de movimiento: venta / regalias / merma.
                        $grupo_mov = Salida::grupoDeTipo($outgoingRecord['tipo_mov_nombre'] ?? '');
                        ?>
                        <tr>
                            <td style="vertical-align:middle;text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($outgoingRecord['nro_factura_manual'] ?: '#' . $outgoingRecord['id_salida']); ?></span></td>
                            <td class="td-control" data-tooltip="<?php echo htmlspecialchars($outgoingRecord['nro_control']); ?>"><?php echo htmlspecialchars($outgoingRecord['nro_control']); ?></td>
                            <td class="text-uppercase td-cliente" data-tooltip="<?php echo htmlspecialchars(($outgoingRecord['cliente'] ?? 'S/Cliente') . ' - ' . ($outgoingRecord['rif_cliente'] ?? 'S/RIF')); ?>">
                                <div class="cli-nombre"><?php echo htmlspecialchars($outgoingRecord['cliente'] ?? 'S/Cliente'); ?></div>
                                <div class="cli-rif"><?php echo htmlspecialchars($outgoingRecord['rif_cliente'] ?? 'S/RIF'); ?></div>
                            </td>
                            <td class="td-productos" data-tooltip="<?php echo htmlspecialchars($productos_lista); ?>"><?php echo htmlspecialchars($productos_lista); ?></td>
                            <td class="text-center"><span class="badge-jv badge-danger" style="padding:6px 14px;">-<?php echo $outgoingRecord['total_cantidad']; ?></span></td>
                            <td class="text-center"><?php
                                                    $nombre_tipo = $outgoingRecord['tipo_mov_nombre'] ?? '';
                                                    $observaciones = $outgoingRecord['observaciones'] ?? '';
                                                    $causa_ajuste = '';
                                                    if (preg_match('/^Causa:\s*(.+?)(?:\s*\||$)/', $observaciones, $causeMatch)) $causa_ajuste = trim($causeMatch[1]);
                                                    if ($grupo_mov === 'venta') echo '<span class="badge-jv badge-success"><i class="bi bi-cart me-1"></i>Venta</span>';
                                                    elseif ($grupo_mov === 'regalias') echo '<span class="badge-jv badge-info"><i class="bi bi-gift me-1"></i>Regalía</span>';
                                                    else echo '<span class="badge-jv badge-warning" style="cursor:pointer;" data-tooltip="' . htmlspecialchars($nombre_tipo . ($causa_ajuste ? ': ' . $causa_ajuste : ''), ENT_QUOTES, 'UTF-8') . '" onclick="verDetalleDano(' . htmlspecialchars(json_encode($nombre_tipo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars(json_encode($causa_ajuste, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') . ')"><i class="bi bi-exclamation-triangle me-1"></i>' . htmlspecialchars($nombre_tipo, ENT_QUOTES, 'UTF-8') . '</span>';
                                                    ?></td>
                            <td class="td-total" style="<?php echo $grupo_mov === 'merma' ? 'color:var(--jv-danger);' : 'color:var(--jv-success);'; ?>">$<?php echo number_format($outgoingRecord['total_monto'] ?? 0, 2); ?></td>
                            <td class="text-center fecha-cell"><?php echo date('d/m/Y', strtotime($outgoingRecord['fecha_salida'])); ?></td>
                            <td class="text-center" style="white-space:nowrap;">
                                <button class="btn-action" onclick="verFactura(<?php echo $outgoingRecord['id_salida']; ?>)" data-tooltip="Ver Nota">
                                    <i class="bi bi-receipt"></i>
                                </button>
                                <button class="btn-action" onclick='editarSalida(<?php echo json_encode($outgoingRecord); ?>)' data-tooltip="Editar">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <?php if (Security::esAdmin()): ?>
                                    <button class="btn-action" onclick="confirmarEliminar(<?php echo $outgoingRecord['id_salida']; ?>)" data-tooltip="Anular">
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
                                <select name="id_tipo_mov" id="s_tipo" aria-label="Tipo de movimiento" class="input-jv" required onchange="toggleCampos()">
                                    <option value="">Seleccione tipo...</option>
                                    <?php foreach ($tipos_mov as $tipo_mov):
                                        $grupo = $tipos_mov_map[$tipo_mov['id_tipo_mov']];
                                    ?>
                                        <option value="<?php echo $tipo_mov['id_tipo_mov']; ?>" data-grupo="<?php echo $grupo; ?>"><?php echo $tipo_mov['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-secondary mb-2">FECHA</label>
                                <input type="date" id="s_fecha" aria-label="Fecha de la salida" class="input-jv" value="<?php echo date('Y-m-d'); ?>" disabled>
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
                                        <input type="text" class="input-jv w-100" id="buscarClienteSal" aria-label="Buscar cliente" placeholder="Buscar o escribir cliente..." autocomplete="off">
                                        <input type="hidden" name="id_cliente" id="s_id_cliente">
                                        <input type="hidden" name="cliente" id="s_cliente">
                                        <div class="com-resultados" id="resultadosBusquedaCli"></div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: GESTIÓN DE CLIENTES (listado ABC) -->
<!-- ========================================== -->
<?php if ($es_admin): ?>
<div class="modal fade" id="modalCliList" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <div class="cli-modal-header-jv">
                <h5 class="fw-bolder font-brand text-uppercase m-0"><i class="bi bi-people-fill me-2"></i>Gestión de Clientes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Filtros -->
                <div class="section-bg">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-search" style="font-size:1rem;color:#059669;"></i>
                                <input type="text" class="input-jv border-0 bg-transparent" id="cliBuscar" placeholder="Buscar por nombre o documento..." style="box-shadow:none;font-size:.95rem;padding:8px 6px;flex:1;" oninput="cliFiltrar()">
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-action-sm" onclick="cliSetFiltro('todos')" id="cliFiltTodos" style="background:#059669;color:#fff;">Todos</button>
                            <button class="btn btn-action-sm" onclick="cliSetFiltro('Activo')" id="cliFiltAct" style="background:#d1fae5;color:#065f46;">Activos</button>
                            <button class="btn btn-action-sm" onclick="cliSetFiltro('Inactivo')" id="cliFiltInact" style="background:#fee2e2;color:#991b1b;">Inactivos</button>
                        </div>
                    </div>
                </div>

                <div class="section-bg" style="margin-top:10px;">
                    <div class="section-label"><i class="bi bi-list-ul me-1"></i>Clientes Registrados <span class="badge rounded-pill ms-2" style="background:#059669;color:#fff;font-size:.75rem;" id="cliTotalBadge"><?php echo count($cli_gestion); ?></span></div>
                    <div style="border:1px solid var(--jv-border);border-radius:8px;overflow:hidden;">
                        <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-card);">
                            <thead>
                                <tr style="background:#065f46;">
                                    <th style="padding:10px 12px;color:#fff;font-size:.85rem;text-transform:uppercase;width:30px;text-align:center;">#</th>
                                    <th style="padding:10px 12px;color:#fff;font-size:.85rem;text-transform:uppercase;">Cliente</th>
                                    <th style="padding:10px 12px;color:#fff;font-size:.85rem;text-transform:uppercase;width:140px;">Documento</th>
                                    <th style="padding:10px 12px;color:#fff;font-size:.85rem;text-transform:uppercase;width:120px;">Teléfono</th>
                                    <th style="padding:10px 12px;color:#fff;font-size:.85rem;text-transform:uppercase;text-align:center;width:90px;">Estado</th>
                                    <th style="padding:10px 12px;color:#fff;font-size:.85rem;text-transform:uppercase;text-align:center;width:80px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cliTablaBody">
                                <?php if (count($cli_gestion) > 0): ?>
                                    <?php foreach ($cli_gestion as $idx => $cli): ?>
                                        <tr class="cli-fila" data-status="<?php echo $cli['status']; ?>" data-nombre="<?php echo htmlspecialchars(strtolower($cli['nombre'])); ?>" data-documento="<?php echo htmlspecialchars(strtolower($cli['documento'] ?? '')); ?>">
                                            <td style="text-align:center;color:var(--jv-text-muted);padding:10px 12px;font-size:.85rem;"><?php echo $idx + 1; ?></td>
                                            <td style="padding:10px 12px;">
                                                <div style="font-weight:700;font-size:1rem;color:var(--jv-text-primary);"><?php echo htmlspecialchars($cli['nombre']); ?></div>
                                                <?php if (!empty($cli['direccion'])): ?>
                                                    <div style="font-size:.85rem;color:var(--jv-text-secondary);margin-top:2px;"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($cli['direccion']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:10px 12px;font-size:.95rem;color:var(--jv-text-primary);white-space:nowrap;"><?php echo htmlspecialchars($cli['documento'] ?: '—'); ?></td>
                                            <td style="padding:10px 12px;font-size:.95rem;color:var(--jv-text-primary);white-space:nowrap;"><?php echo htmlspecialchars($cli['telefono'] ?: '—'); ?></td>
                                            <td style="text-align:center;padding:10px 12px;">
                                                <?php if ($cli['status'] === 'Activo'): ?>
                                                    <span class="abc-badge abc-abc">Activo</span>
                                                <?php else: ?>
                                                    <span class="manejo-badge manejo-directa" style="background:#fee2e2;color:#991b1b;">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:center;padding:10px 12px;white-space:nowrap;">
                                                <button class="btn-action" onclick="cliEditar(<?php echo $cli['id_cliente']; ?>)" data-tooltip="Editar"><i class="bi bi-pencil-square"></i></button>
                                                <button class="btn-action" onclick="cliToggleStatus(<?php echo $cli['id_cliente']; ?>, '<?php echo $cli['status']; ?>')" data-tooltip="<?php echo $cli['status'] === 'Activo' ? 'Desactivar' : 'Activar'; ?>" style="<?php echo $cli['status'] === 'Activo' ? 'color:var(--jv-danger);' : 'color:var(--jv-success);'; ?>">
                                                    <i class="bi bi-<?php echo $cli['status'] === 'Activo' ? 'x-circle' : 'check-circle'; ?>"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr id="cliVacio">
                                        <td colspan="6" style="text-align:center;padding:30px;color:var(--jv-text-muted);">
                                            <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>
                                            No hay clientes registrados
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0 pb-3 px-3">
                <button class="btn btn-action-sm fw-bold" onclick="nuevaCli()" style="background:#059669;color:#fff;padding:10px 20px;border-radius:8px;font-size:.9rem;">
                    <i class="bi bi-plus-lg me-1"></i>NUEVO CLIENTE
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: FORMULARIO CLIENTE (crear/editar) -->
<div class="modal fade" id="modalCli" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form id="formCli" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion_cliente" id="cliAccion" value="registrar">
                <input type="hidden" name="id_cliente" id="cliIdEdit">
                <div class="modal-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bolder font-brand text-uppercase m-0 modal-title-jv" id="cliFormTitle">REGISTRAR CLIENTE</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" id="btnCerrarCliForm"></button>
                    </div>

                    <div class="section-bg">
                        <div class="section-label"><i class="bi bi-person-badge me-1"></i>Datos del Cliente</div>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="small fw-bold text-secondary mb-2">NOMBRE / RAZÓN SOCIAL <span style="color:var(--jv-danger);">*</span></label>
                                <input type="text" name="nombre" id="cliNombre" class="input-jv" placeholder="Nombre completo o razón social" maxlength="150" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-secondary mb-2">ESTADO</label>
                                <select name="status" id="cliStatus" class="input-jv">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-md-5">
                                <label class="small fw-bold text-secondary mb-2">DOCUMENTO (RIF/Cédula)</label>
                                <div class="d-flex gap-2">
                                    <select id="cliDocTipo" aria-label="Tipo de documento" class="input-jv" style="max-width:70px;flex-shrink:0;" onchange="cliValidarDoc()">
                                        <option value="V">V-</option>
                                        <option value="J">J-</option>
                                        <option value="E">E-</option>
                                        <option value="P">P-</option>
                                        <option value="G">G-</option>
                                        <option value="C">C-</option>
                                    </select>
                                    <input type="text" name="documento" id="cliDocNum" class="input-jv" placeholder="Número" oninput="cliValidarDoc()" style="flex:1;" inputmode="numeric" maxlength="12">
                                </div>
                                <div id="cliDocMsg" class="small mt-1" style="min-height:18px;"></div>
                            </div>
                            <div class="col-md-7">
                                <label class="small fw-bold text-secondary mb-2">TELÉFONO</label>
                                <input type="text" name="telefono" id="cliTelefono" class="input-jv" placeholder="0414-1234567" maxlength="20">
                            </div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col-12">
                                <label class="small fw-bold text-secondary mb-2">DIRECCIÓN</label>
                                <input type="text" name="direccion" id="cliDireccion" class="input-jv" placeholder="Dirección completa" maxlength="255">
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-jv-primary w-100 py-2 fw-bolder text-uppercase mt-2" id="btnGuardarCli" onclick="cliEnviarForm()">
                        <i class="bi bi-check-circle me-1"></i>GUARDAR
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary mb-2">RIF / CÉDULA <span style="color:var(--jv-danger);">*</span></label>
                                    <div class="d-flex gap-2">
                                        <select id="s_rif_tipo" aria-label="Tipo de RIF o cedula" class="input-jv" style="max-width:70px;flex-shrink:0;" onchange="validarRIFInput()">
                                            <option value="V">V-</option>
                                            <option value="J">J-</option>
                                            <option value="E">E-</option>
                                            <option value="P">P-</option>
                                            <option value="G">G-</option>
                                            <option value="C">C-</option>
                                        </select>
                                        <input type="text" id="s_rif_num" aria-label="Numero de RIF o cedula" class="input-jv" placeholder="Número de identificación" oninput="validarRIFInput()" style="flex:1;" inputmode="numeric">
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
                                    <select name="motivo_regalia" id="s_motivo_reg" aria-label="Motivo de la regalia" class="input-jv">
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
                                    <input type="text" id="s_cliente_reg" aria-label="Cliente que recibe la regalia" class="input-jv" placeholder="Nombre o Razón Social" oninput="document.getElementById('s_cliente').value=this.value">
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
                                    <select name="causa_ajuste" id="s_causa" aria-label="Causa del ajuste" class="input-jv">
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
                                    <textarea name="descripcion_motivo" id="s_desc_motivo" aria-label="Descripcion del motivo" class="input-jv" rows="2" placeholder="Detalle adicional..."></textarea>
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
                                    <input type="text" class="input-jv w-100" id="buscarProductoSal" aria-label="Buscar producto para agregar" placeholder="Buscar por nombre o SKU..." autocomplete="off">
                                    <input type="hidden" id="selProductoSalId">
                                    <input type="hidden" id="selProductoSalNombre">
                                    <div class="com-resultados" id="resultadosBusquedaSal"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-secondary mb-1">Cant</label>
                                <input type="number" id="s_cant" aria-label="Cantidad del producto" class="input-jv" value="1" min="1" max="999999" oninput="if(this.value>999999)this.value=999999">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-secondary mb-1">Precio $</label>
                                <input type="text" inputmode="decimal" id="s_precio" aria-label="Precio unitario del producto" class="input-jv" placeholder="0.00" readonly style="background:var(--jv-bg-primary);cursor:not-allowed;color:var(--jv-text-muted);">
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
                                <tr id="s_fila_vacia">
                                    <td colspan="6" class="sal-fila-vacia">⬆ Agregue productos con los controles de arriba</td>
                                </tr>
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
                        <textarea name="observaciones" id="s_obs" aria-label="Observaciones de la salida" class="input-jv" rows="1" placeholder="Notas adicionales..."></textarea>
                    </div>

                    <button type="button" class="btn btn-jv-primary w-100 py-2 fw-bolder text-uppercase mt-1" id="btnPreview" onclick="enviarPreview()">
                        📄 VISTA PREVIA NOTA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
