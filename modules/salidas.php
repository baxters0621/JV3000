<?php
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();

Security::verificarPermisoVenta();

$csrf_token = Security::generateToken();

function getGrupoTipo(string $nombre) {
    $n = mb_strtoupper(trim($nombre));
    if ($n === 'VENTA') return 'venta';
    if ($n === 'REGALIAS') return 'regalias';
    return 'merma';
}

// ── CONFIRMAR desde preview_factura.php ──
if (isset($_GET['confirm'])) {
    $data = $_SESSION['preview_data'] ?? null;
    if (!$data) {
        header("Location: salidas.php"); exit();
    }

    $id_tipo_mov = intval($data['id_tipo_mov']);
    $id_producto = intval($data['id_producto']);
    $cantidad = intval($data['cantidad']);
    $precio_venta = floatval($data['precio_venta']);

    $prod_st = $db->fetchOne("SELECT stock_actual FROM productos WHERE id_producto = ?", [$id_producto]);

    if (!$prod_st || (int)$prod_st['stock_actual'] < $cantidad) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'STOCK INSUFICIENTE.'];
        unset($_SESSION['preview_data']);
        header("Location: salidas.php"); exit();
    }

    $db->begin();
    try {
        $factura_num = generarFacturaNumero();
        $nro_ctrl = $data['nro_control'] ?? '';

        $inserted_id = $db->insert('salidas', [
            'nro_factura_manual' => $factura_num,
            'nro_control'        => $nro_ctrl,
            'id_producto'        => $id_producto,
            'cantidad'           => $cantidad,
            'precio_venta'       => $precio_venta,
            'cliente'            => $data['cliente'],
            'rif_cliente'        => $data['rif_cliente'],
            'id_usuario'         => $data['id_usuario'],
            'fecha_salida'       => $data['fecha_salida'],
            'id_tipo_mov'        => $id_tipo_mov,
            'observaciones'      => $data['observaciones'],
        ]);

        $db->execute("UPDATE productos SET stock_actual = stock_actual - ? WHERE id_producto = ?", [$cantidad, $id_producto]);

        $db->commit();
        registrarAuditoria('crear', 'Movimiento registrado');
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'MOVIMIENTO REGISTRADO CON ÉXITO.'];
        unset($_SESSION['preview_data']);
        header("Location: salidas.php#salida-$inserted_id");
        exit();
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR EN LA BASE DE DATOS.'];
        unset($_SESSION['preview_data']);
        header("Location: salidas.php"); exit();
    }
}

// ── POST desde modal ──
if (isset($_POST['accion_salida'])) {
    $accion = in_array($_POST['accion_salida'] ?? '', ['registrar', 'editar']) ? $_POST['accion_salida'] : '';
    $id_producto = intval($_POST['id_producto']);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $id_tipo_mov = intval($_POST['id_tipo_mov']);

    if (!$accion) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ACCIÓN INVÁLIDA.'];
        header("Location: salidas.php"); exit();
    }
    if ($id_producto <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'SELECCIONE UN PRODUCTO.'];
        header("Location: salidas.php"); exit();
    }
    if ($cantidad <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'LA CANTIDAD DEBE SER MAYOR A CERO.'];
        header("Location: salidas.php"); exit();
    }

    $tipo_nombre = '';
    $tn_row = $db->fetchOne("SELECT nombre FROM tipos_movimientos WHERE id_tipo_mov = ?", [$id_tipo_mov]);
    if ($tn_row) $tipo_nombre = $tn_row['nombre'];
    $grupo = getGrupoTipo($tipo_nombre);

    $precio_venta = 0;
    if ($grupo === 'venta') {
        $precio_venta = floatval($_POST['precio_venta'] ?? 0);
        if ($precio_venta < 0 || $precio_venta > 99999999.99) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'PRECIO INVÁLIDO.'];
            header("Location: salidas.php"); exit();
        }
    }

    $nro_fac_man = 'PENDIENTE';
    $nro_control = generarControlNumero();
    $rif_cliente = mb_strtoupper(trim($_POST['rif_cliente'] ?? ''));
    $cliente = mb_strtoupper(trim($_POST['cliente'] ?? 'VENTA GENERAL'));
    $fecha_salida = $_POST['fecha_salida'] ?? date('Y-m-d');
    $observaciones = trim(($_POST['descripcion_motivo'] ?? '') . ' | ' . ($_POST['observaciones'] ?? ''));
    $observaciones = trim(preg_replace('/^\s*\|\s*$/', '', $observaciones));
    $id_usuario = $_SESSION['id_usuario'];

    if ($rif_cliente !== '' && $rif_cliente !== 'N/A' && !validarRIF($rif_cliente)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'RIF INVÁLIDO.'];
        header("Location: salidas.php"); exit();
    }

    if ($accion === 'registrar') {
        $prod_st = $db->fetchOne("SELECT stock_actual FROM productos WHERE id_producto = ?", [$id_producto]);

        if (!$prod_st || (int)$prod_st['stock_actual'] < $cantidad) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'STOCK INSUFICIENTE.'];
            header("Location: salidas.php"); exit();
        }

        $_SESSION['preview_data'] = [
            'id_producto'         => $id_producto,
            'cantidad'            => $cantidad,
            'precio_venta'        => $precio_venta,
            'cliente'             => $cliente,
            'rif_cliente'         => $rif_cliente ?: 'N/A',
            'nro_factura_manual'  => $nro_fac_man,
            'nro_control'         => $nro_control,
            'fecha_salida'        => $fecha_salida,
            'id_tipo_mov'         => $id_tipo_mov,
            'observaciones'       => $observaciones,
            'id_usuario'          => $id_usuario,
        ];
        header("Location: preview_factura.php");
        exit();
    }

    if ($accion === 'editar') {
        $id_salida = intval($_POST['id_salida']);
        $ant = $db->fetchOne("SELECT cantidad, id_producto, nro_factura_manual FROM salidas WHERE id_salida = ?", [$id_salida]);

        if (!$ant) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'REGISTRO NO ENCONTRADO.'];
            header("Location: salidas.php"); exit();
        }

        $db->begin();
        try {
            $db->execute(
                "UPDATE salidas SET nro_factura_manual=?, nro_control=?, id_producto=?, cantidad=?, precio_venta=?, cliente=?, rif_cliente=?, fecha_salida=?, id_tipo_mov=?, observaciones=? WHERE id_salida=?",
                [$ant['nro_factura_manual'], $nro_control, $id_producto, $cantidad, $precio_venta, $cliente, $rif_cliente, $fecha_salida, $id_tipo_mov, $observaciones, $id_salida]
            );
            $db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [(int)$ant['cantidad'], (int)$ant['id_producto']]);
            $db->execute("UPDATE productos SET stock_actual = stock_actual - ? WHERE id_producto = ?", [$cantidad, $id_producto]);
            $db->commit();
            registrarAuditoria('editar', 'Movimiento modificado');
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'SALIDA ACTUALIZADA CORRECTAMENTE.'];
            header("Location: salidas.php");
            exit();
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR EN LA BASE DE DATOS.'];
            header("Location: salidas.php");
            exit();
        }
    }
}

// ── ELIMINAR ──
if (isset($_GET['eliminar'])) {
    Security::soloAdmin();
    $id_del = intval($_GET['eliminar']);
    $inf = $db->fetchOne("SELECT cantidad, id_producto FROM salidas WHERE id_salida = ?", [$id_del]);
    if ($inf) {
        $db->begin();
        try {
            $db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [(int)$inf['cantidad'], (int)$inf['id_producto']]);
            $db->execute("UPDATE salidas SET status = 'Anulada' WHERE id_salida = ?", [$id_del]);
            $db->commit();
            registrarAuditoria('anular', 'Movimiento anulado');
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'SALIDA ANULADA. STOCK RESTAURADO.'];
            header("Location: salidas.php"); exit();
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR EN LA BASE DE DATOS.'];
            header("Location: salidas.php"); exit();
        }
    }
}

// ── DATOS PARA LA VISTA ──
$salidas = $db->fetchAll("SELECT s.*, p.nombre_producto, p.sku FROM salidas s INNER JOIN productos p ON s.id_producto = p.id_producto WHERE s.status = 'Activa' ORDER BY s.id_salida DESC");
$productos = $db->fetchAll("SELECT id_producto, nombre_producto, sku, precio_venta FROM productos WHERE status = 'Activo' ORDER BY nombre_producto ASC");
$tipos_mov = $db->fetchAll("SELECT id_tipo_mov, nombre FROM tipos_movimientos WHERE tipo_movimiento = 'Salida' ORDER BY id_tipo_mov");
$clientes_previos = $db->fetchAll("SELECT DISTINCT cliente, rif_cliente FROM salidas WHERE cliente IS NOT NULL AND cliente != 'Venta General' AND status = 'Activa' ORDER BY cliente ASC");

// Mapa id_tipo_mov → grupo para JS
$tipos_mov_map = [];
foreach ($tipos_mov as $tm) {
    $tipos_mov_map[$tm['id_tipo_mov']] = getGrupoTipo($tm['nombre']);
}

$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/diseno.php'; ?>
    <title>Salidas / Ventas | JV3000 C.A.</title>
    <style>
    /* === HEADER ICON === */
    .sal-header-icon {
        width:48px;height:48px;border-radius:14px;
        background:linear-gradient(135deg,#dc2626,#b91c1c);
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-size:1.5rem;flex-shrink:0;
        box-shadow:0 0 30px rgba(220,38,38,0.25);
    }
    /* === CODIGO BADGE (Red-tinted) === */
    .codigo-badge {
        background:rgba(220,38,38,0.1);color:#fca5a5;
        font-size:.7rem;font-weight:800;padding:3px 10px;
        border-radius:20px;display:inline-block;
        letter-spacing:.5px;
    }
    /* === ACTION BUTTON (40px) === */
    .btn-action {
        width:40px;height:40px;border-radius:12px;
        display:inline-flex;align-items:center;justify-content:center;
        border:1px solid var(--jv-border);background:var(--jv-bg-primary);
        color:var(--jv-text);transition:.15s;
    }
    .btn-action:hover {
        background:var(--jv-bg-hover);border-color:#dc2626;
        color:#dc2626;
    }
    /* === EMPTY STATE === */
    .estado-vacio {
        padding:60px 20px;text-align:center;
    }
    .estado-vacio i {
        font-size:3.5rem;color:rgba(220,38,38,0.2);display:block;margin-bottom:16px;
    }
    .estado-vacio span {
        font-size:.85rem;font-weight:700;text-transform:uppercase;
        letter-spacing:1px;color:rgba(148,163,184,0.5);
    }
    /* === DISTINCTIVE VENTAS MODULE ====================== */
    .pagina-salidas .card-jv {
        border-color:rgba(220,38,38,0.25);
        box-shadow:0 20px 50px -12px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(220,38,38,0.06);
    }
    .pagina-salidas .card-jv:hover {
        border-color:rgba(220,38,38,0.45);
    }
    .pagina-salidas .table-jv thead th {
        background:linear-gradient(135deg,#7f1d1d,#991b1b);
        color:#fecaca;
        border-bottom:2px solid rgba(220,38,38,0.3);
    }
    .pagina-salidas .table-jv tbody td {
        border-bottom:1px solid rgba(220,38,38,0.07);
    }
    .pagina-salidas .table-jv tbody tr:hover {
        background:rgba(220,38,38,0.03);
    }
    .pagina-salidas .btn-jv-primary {
        background:linear-gradient(135deg,#dc2626,#b91c1c);
    }
    .pagina-salidas .btn-jv-primary:hover {
        box-shadow:0 8px 25px -5px rgba(220,38,38,0.4);
        transform:translateY(-2px);
    }
    .pagina-salidas .input-jv:focus {
        border-color:#dc2626;
        box-shadow:0 0 0 3px rgba(220,38,38,0.15);
    }

    /* === DYNAMIC FIELD GROUPS === */
    .sal-field-group { display:none; }
    .sal-field-group.active { display:block; }
    .sal-avanzado summary {
        cursor:pointer; font-size:.75rem; font-weight:700;
        text-transform:uppercase; letter-spacing:1px;
        color:rgba(148,163,184,0.6); padding:8px 0;
        list-style:none;
    }
    .sal-avanzado summary:hover { color:var(--jv-text-secondary); }
    .sal-avanzado[open] summary { color:var(--jv-text-secondary); }
    .sal-avanzado .section-bg { margin-top:10px; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
    <div class="container-fluid px-4 py-4 pagina-salidas">

        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="sal-header-icon"><i class="bi bi-cart-x-fill"></i></div>
            <div>
                <h1 class="font-brand m-0" style="font-size:1.6rem;letter-spacing:-1px;">SALIDAS / VENTAS</h1>
                <p class="text-white opacity-75 small fw-bold text-uppercase m-0">Notas de Entrega y Despacho</p>
            </div>
            <div class="ms-auto">
                <button class="btn btn-jv-primary" onclick="nuevaSalida()">
                    <i class="bi bi-cart-plus-fill me-2"></i>NUEVA VENTA
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
        <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4">
            <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($flash['texto']); ?>
        </div>
        <?php endif; ?>

        <div class="card-jv p-0">
            <div class="table-responsive">
                <table class="table-jv mb-0">
                    <thead>
                        <tr>
                            <th>CÓDIGO</th>
                            <th>PRODUCTO</th>
                            <th>CLIENTE / DESTINO</th>
                            <th class="text-center">CANT.</th>
                            <th>P.UNIT.</th>
                            <th>TOTAL</th>
                            <th>FECHA</th>
                            <th class="text-center">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($salidas) > 0): ?>
                            <?php foreach ($salidas as $row):
                                $total_fila = $row['cantidad'] * $row['precio_venta'];
                            ?>
                                <tr>
                                    <td><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura_manual'] ?: '#' . $row['id_salida']); ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['nombre_producto']); ?></div>
                                        <div class="text-jv-cyan small fw-bold"><?php echo htmlspecialchars($row['sku']); ?></div>
                                    </td>
                                    <td class="text-uppercase">
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['cliente'] ?? 'Venta General'); ?></div>
                                        <div class="text-secondary small"><?php echo htmlspecialchars($row['rif_cliente'] ?? 'S/RIF'); ?></div>
                                    </td>
                                    <td class="text-center"><span class="badge-jv badge-danger">-<?php echo $row['cantidad']; ?></span></td>
                                    <td class="fw-bold">$<?php echo number_format($row['precio_venta'], 2); ?></td>
                                    <td class="fw-bold text-jv-cyan">$<?php echo number_format($total_fila, 2); ?></td>
                                    <td style="color:#e2e8f0;font-weight:600;font-size:.82rem;"><?php echo date('d/m/Y', strtotime($row['fecha_salida'])); ?></td>
                                    <td class="text-center">
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
                                <td colspan="8">
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

    <!-- ═══════════════ MODAL POS DINÁMICO ═══════════════ -->
    <div class="modal fade" id="modalSalida" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <form action="" method="POST" id="formSalida">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_salida" id="s_accion" value="registrar">
                    <input type="hidden" name="id_salida" id="s_id_edit">
                    <div class="modal-body p-4">
                        <h5 class="fw-bolder mb-4 font-brand text-uppercase" id="modalTitle" style="color:#fca5a5;">REGISTRAR MOVIMIENTO</h5>

                        <!-- TIPO (siempre visible) -->
                        <div class="section-bg">
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

                        <!-- PRODUCTO (todos los grupos) -->
                        <div class="section-bg">
                            <label class="small fw-bold text-secondary mb-2">PRODUCTO</label>
                            <select name="id_producto" id="s_prod" class="input-jv" required onchange="cargarPrecio()">
                                <option value="">Seleccione un producto...</option>
                                <?php foreach ($productos as $pr): ?>
                                    <option value="<?php echo $pr['id_producto']; ?>" data-precio="<?php echo $pr['precio_venta']; ?>"><?php echo $pr['sku'] . " - " . $pr['nombre_producto']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- FECHA (todos los grupos) -->
                        <div class="section-bg">
                            <label class="small fw-bold text-secondary mb-2">FECHA</label>
                            <input type="date" name="fecha_salida" id="s_fecha" class="input-jv" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- CANTIDAD (todos los grupos) -->
                        <div class="section-bg">
                            <label class="small fw-bold text-secondary mb-2">CANTIDAD</label>
                            <input type="number" name="cantidad" id="s_cant" class="input-jv" required min="1" placeholder="1">
                        </div>

                        <!-- GRUPO: VENTA -->
                        <div class="sal-field-group" data-grupo="venta">
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">PRECIO UNITARIO ($)</label>
                                <input type="number" step="0.01" name="precio_venta" id="s_precio" class="input-jv" placeholder="0.00" min="0">
                            </div>
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">CLIENTE <span class="text-secondary fw-normal">(opcional)</span></label>
                                <input type="text" name="cliente" id="s_cliente" class="input-jv" placeholder="Nombre o Razón Social">
                            </div>
                        </div>

                        <!-- GRUPO: REGALIAS (solo cliente, precio $0) -->
                        <div class="sal-field-group" data-grupo="regalias">
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">CLIENTE <span class="text-secondary fw-normal">(opcional)</span></label>
                                <input type="text" name="cliente" id="s_cliente_reg" class="input-jv" placeholder="Nombre o Razón Social" oninput="document.getElementById('s_cliente').value=this.value">
                            </div>
                        </div>

                        <!-- GRUPO: MERMA (Mermas + Daños) -->
                        <div class="sal-field-group" data-grupo="merma">
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">DESCRIPCIÓN / MOTIVO</label>
                                <textarea name="descripcion_motivo" id="s_desc_motivo" class="input-jv" rows="2" placeholder="Ej: Producto vencido, dañado durante transporte..."></textarea>
                            </div>
                        </div>

                        <!-- AVANZADO (colapsable, todos los grupos) -->
                        <details class="sal-avanzado">
                            <summary>📋 OPCIONES AVANZADAS</summary>
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">RIF / CÉDULA</label>
                                <input type="text" name="rif_cliente" id="s_rif" class="input-jv" maxlength="13" placeholder="Ej: V-12345678 o J-12345678-0" oninput="validarRIFInput(this)">
                                <div id="s-rif-msg" class="small mt-1" style="min-height:18px;"></div>
                            </div>
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">NRO. CONTROL</label>
                                <input type="text" class="input-jv" value="Generado automáticamente" disabled style="color:#94a3b8;">
                            </div>
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">OBSERVACIONES</label>
                                <textarea name="observaciones" id="s_obs" class="input-jv" rows="2" placeholder="Notas adicionales..."></textarea>
                            </div>
                        </details>

                        <button type="button" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase" id="btnPreview" onclick="enviarPreview()">
                            📄 VISTA PREVIA NOTA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sweetalert2.all.min.js"></script>
    <script>
        const modalS = new bootstrap.Modal(document.getElementById('modalSalida'));
        const TIPO_MAP = <?php echo json_encode($tipos_mov_map); ?>;

        function toggleCampos() {
            const sel = document.getElementById('s_tipo');
            const tipoId = sel.value;
            const grupo = TIPO_MAP[tipoId] || '';
            document.querySelectorAll('.sal-field-group').forEach(el => {
                el.classList.toggle('active', el.dataset.grupo === grupo);
            });
            const nombres = {venta:'REGISTRAR VENTA', regalias:'REGISTRAR REGALÍA', merma:'REGISTRAR AJUSTE'};
            document.getElementById('modalTitle').innerText = nombres[grupo] || 'REGISTRAR MOVIMIENTO';
        }

        function nuevaSalida() {
            document.getElementById('s_accion').value = 'registrar';
            document.getElementById('s_id_edit').value = '';
            document.getElementById('modalTitle').innerText = 'REGISTRAR MOVIMIENTO';
            document.getElementById('s_prod').value = '';
            document.getElementById('s_cant').value = '';
            document.getElementById('s_precio').value = '';
            document.getElementById('s_cliente').value = '';
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = '');
            document.getElementById('s_rif').value = '';
            var m = document.getElementById('s-rif-msg'); if (m) m.innerHTML = '';
            var ri = document.getElementById('s_rif'); if (ri) ri.style.borderColor = '';
            // nro_control se genera automáticamente
            document.getElementById('s_obs').value = '';
            document.getElementById('s_fecha').value = new Date().toISOString().slice(0,10);
            document.getElementById('s_desc_motivo') && (document.getElementById('s_desc_motivo').value = '');
            document.getElementById('s_tipo').value = '';
            document.querySelectorAll('.sal-field-group').forEach(el => el.classList.remove('active'));
            modalS.show();
        }

        function editarSalida(data) {
            document.getElementById('s_accion').value = 'editar';
            document.getElementById('s_id_edit').value = data.id_salida;
            document.getElementById('modalTitle').innerText = 'EDITAR SALIDA';
            document.getElementById('s_fecha').value = data.fecha_salida;
            document.getElementById('s_prod').value = data.id_producto;
            document.getElementById('s_cliente').value = data.cliente;
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = data.cliente);
            document.getElementById('s_rif').value = data.rif_cliente;
            validarRIFInput(document.getElementById('s_rif'));
            document.getElementById('s_cant').value = data.cantidad;
            document.getElementById('s_precio').value = parseFloat(data.precio_venta).toFixed(2);
            document.getElementById('s_tipo').value = data.id_tipo_mov;
            // nro_control se genera automáticamente al registrar
            document.getElementById('s_obs').value = data.observaciones;
            toggleCampos();
            modalS.show();
        }

        function validarRIFInput(el) {
            var v = el.value.toUpperCase().replace(/[^VJEPG\d-]/g, '');
            var msg = document.getElementById('s-rif-msg');
            if (v === '') { msg.innerHTML = ''; el.style.borderColor = ''; el.value = v; return; }
            var valido = /^[VJGPE]-\d{7,9}(?:-\d)?$/.test(v);
            if (valido) {
                msg.innerHTML = '<span style="color:#22c55e;">✓ Válido</span>';
                el.style.borderColor = '#22c55e';
            } else {
                msg.innerHTML = '<span style="color:#ef4444;">Formato: V-12345678 o J-12345678-0</span>';
                el.style.borderColor = '#ef4444';
            }
            el.value = v;
        }
            const btn = document.getElementById('btnPreview');
            btn.disabled = true; btn.innerHTML = '⏳ PROCESANDO...';

            const tipo = document.getElementById('s_tipo');
            if (!tipo.value) {
                Swal.fire({icon:'warning',title:'SELECCIONE TIPO',text:'Debe elegir un tipo de movimiento.',background:'#0f172a',color:'#fff',confirmButtonColor:'#dc2626'});
                btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA'; return;
            }
            const prod = document.getElementById('s_prod');
            if (!prod.value) {
                Swal.fire({icon:'warning',title:'SELECCIONE PRODUCTO',text:'Debe elegir un producto.',background:'#0f172a',color:'#fff',confirmButtonColor:'#dc2626'});
                btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA'; return;
            }
            const cant = parseInt(document.getElementById('s_cant').value);
            if (!cant || cant <= 0) {
                Swal.fire({icon:'warning',title:'CANTIDAD INVÁLIDA',text:'Ingrese una cantidad mayor a cero.',background:'#0f172a',color:'#fff',confirmButtonColor:'#dc2626'});
                btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA'; return;
            }

            const grupo = TIPO_MAP[tipo.value] || '';
            if (grupo === 'regalias') {
                document.getElementById('s_precio').value = '0';
                document.getElementById('s_cliente').value = document.getElementById('s_cliente_reg').value;
            }
            const fd = new FormData(document.getElementById('formSalida'));

            fetch('preview_factura.php?store=1', { method:'POST', body:fd })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                    if (d.ok) {
                        window.open('preview_factura.php', '_blank');
                        modalS.hide();
                    } else {
                        Swal.fire({icon:'error',title:'ERROR',text:d.error||'Error al generar preview.',background:'#0f172a',color:'#fff',confirmButtonColor:'#dc2626'});
                    }
                })
                .catch(e => {
                    btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                    Swal.fire({icon:'error',title:'ERROR DE CONEXIÓN',text:e.message,background:'#0f172a',color:'#fff',confirmButtonColor:'#dc2626'});
                });
        }

        function verFactura(id) {
            window.open('preview_factura.php?id=' + id, '_blank');
        }

        function confirmarEliminar(id) {
            Swal.fire({title:'¿ANULAR?',text:'El stock volverá al inventario.',icon:'warning',showCancelButton:true,background:'#0f172a',color:'#fff',confirmButtonColor:'#ef4444',cancelButtonColor:'#1e293b',confirmButtonText:'SÍ, ANULAR',cancelButtonText:'CANCELAR'}).then(r => {
                if (r.isConfirmed) window.location.href = 'salidas.php?eliminar=' + id;
            });
        }

        function cargarPrecio() {
            const sel = document.getElementById('s_prod');
            const opt = sel.options[sel.selectedIndex];
            const precio = document.getElementById('s_precio');
            if (opt && opt.dataset.precio) precio.value = opt.dataset.precio;
        }
    </script>
    <script>
    document.querySelectorAll('.flash-auto').forEach(el => {
        setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
    });
    </script>
    <script>
        const mainWrapper = document.getElementById('mainWrapper');
        const observer = new MutationObserver(() => {
            if (document.body.classList.contains('sidebar-open')) {
                mainWrapper.classList.add('sidebar-open');
            } else {
                mainWrapper.classList.remove('sidebar-open');
            }
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    </script>
</body>
</html>