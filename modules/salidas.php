<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();

Security::verificarPermisoVenta();

$csrf_token = Security::generateToken();

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================
function getGrupoTipo(string $nombre) {
    $n = mb_strtoupper(trim($nombre));
    if ($n === 'VENTA') return 'venta';
    if ($n === 'REGALIAS') return 'regalias';
    return 'merma';
}

// ==========================================
// PROCESAR ACCIONES GET
// ==========================================
// Confirmar desde preview_factura.php
if (isset($_GET['confirm'])) {
    $data = $_SESSION['preview_data'] ?? null;
    if (!$data) {
        header("Location: salidas.php"); exit();
    }

    $db->begin();
    try {
        // 1. Insertar cabecera
        $salida_id = $db->insert('salidas', [
            'nro_factura_manual' => $data['nro_factura_manual'] ?? generarFacturaNumero(),
            'nro_control'        => $data['nro_control'] ?? '',
            'cliente'            => $data['cliente'] ?? 'VENTA GENERAL',
            'rif_cliente'        => $data['rif_cliente'] ?? 'N/A',
            'id_tipo_mov'        => intval($data['id_tipo_mov']),
            'id_usuario'         => $data['id_usuario'],
            'fecha_salida'       => $data['fecha_salida'] ?? date('Y-m-d H:i:s'),
            'status'             => 'Activa',
            'observaciones'      => $data['observaciones'] ?? '',
        ]);

        // 2. Procesar producto(s) desde preview_data
        $productos_raw = [];
        if (isset($data['productos_data'])) {
            $productos_raw = json_decode($data['productos_data'], true) ?: [];
        } else {
            $productos_raw[] = [
                'id_producto' => intval($data['id_producto'] ?? 0),
                'cantidad'    => intval($data['cantidad'] ?? 0),
                'precio'      => floatval($data['precio_venta'] ?? 0),
            ];
        }

        // 3. Validar stock de todos los productos antes de procesar
        foreach ($productos_raw as $prod) {
            $id_producto = intval($prod['id_producto'] ?? 0);
            $cantidad = intval($prod['cantidad'] ?? 0);
            if ($id_producto <= 0 || $cantidad <= 0) continue;
            $pi = $db->fetchOne("SELECT stock_actual FROM productos WHERE id_producto = ?", [$id_producto]);
            if (!$pi) throw new Exception("Producto #$id_producto no encontrado");
            if ((int)$pi['stock_actual'] < $cantidad)
                throw new Exception("Stock insuficiente para producto (ID:$id_producto). Disponible:{$pi['stock_actual']}, solicitado:$cantidad");
        }

        // 4. Insertar detalles en lote y descontar stock
        foreach ($productos_raw as $prod) {
            $id_producto = intval($prod['id_producto'] ?? 0);
            $cantidad = intval($prod['cantidad'] ?? 0);
            $precio_venta = floatval($prod['precio'] ?? 0);
            if ($id_producto <= 0 || $cantidad <= 0) continue;

            $db->insert('detalle_salidas', [
                'id_salida'    => $salida_id,
                'id_producto'  => $id_producto,
                'cantidad'     => $cantidad,
                'precio_venta' => $precio_venta,
            ]);

            $db->execute("UPDATE productos SET stock_actual = stock_actual - ? WHERE id_producto = ?", [$cantidad, $id_producto]);
        }

        // 5. Insertar movimiento
        $mov_id = $db->insert('movimientos', [
            'id_referencia'   => $salida_id,
            'tipo_referencia' => 'venta',
            'tipo'            => 'Salida',
            'id_usuario'      => $data['id_usuario'],
            'status'          => 'Activo',
        ]);

        // 6. Insertar detalle de movimiento
        foreach ($productos_raw as $prod) {
            $id_producto = intval($prod['id_producto'] ?? 0);
            $cantidad = intval($prod['cantidad'] ?? 0);
            $precio_venta = floatval($prod['precio'] ?? 0);
            if ($id_producto <= 0 || $cantidad <= 0) continue;
            $db->insert('detalle_movimientos', [
                'id_movimiento'  => $mov_id,
                'id_producto'    => $id_producto,
                'cantidad'       => $cantidad,
                'precio_unitario'=> $precio_venta,
            ]);
        }

        $db->commit();
        $grupo_data = $data['grupo'] ?? 'venta';
        $causa_data = $data['causa_ajuste'] ?? '';
        $det_auditoria = $grupo_data === 'merma'
            ? "Ajuste (-): Causa: $causa_data, " . count($productos_raw) . " producto(s)"
            : "Venta registrada, " . count($productos_raw) . " producto(s)";
        registrarAuditoria('crear', $det_auditoria);
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'VENTA REGISTRADA EXITOSAMENTE.'];
        unset($_SESSION['preview_data']);
        header("Location: salidas.php#salida-$salida_id");
        exit();
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
        unset($_SESSION['preview_data']);
        header("Location: salidas.php");
        exit();
    }
}

// ==========================================
// PROCESAR ACCIONES POST
// ==========================================
if (isset($_POST['accion_salida'])) {
    $accion = in_array($_POST['accion_salida'] ?? '', ['registrar', 'editar']) ? $_POST['accion_salida'] : '';
    $id_producto = intval($_POST['id_producto'] ?? 0);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $id_tipo_mov = intval($_POST['id_tipo_mov'] ?? 0);

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
    // Validar causa si es ajuste (merma/daño)
    $causa_ajuste = '';
    $motivo_merma = '';
    if ($grupo === 'merma') {
        $causa_ajuste = trim($_POST['causa_ajuste'] ?? '');
        if (!$causa_ajuste) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'SELECCIONE UNA CAUSA DE AJUSTE.'];
            header("Location: salidas.php"); exit();
        }
        $motivo_merma = trim($_POST['descripcion_motivo'] ?? '');
    }
    $obs_extra = trim($_POST['observaciones'] ?? '');
    $partes = [];
    if ($causa_ajuste) $partes[] = "Causa: $causa_ajuste";
    if ($motivo_merma) $partes[] = "Motivo: $motivo_merma";
    if ($obs_extra) $partes[] = $obs_extra;
    $observaciones = implode(' | ', $partes);
    $id_usuario = $_SESSION['id_usuario'];

    if ($rif_cliente !== '' && $rif_cliente !== 'N/A' && !validarRIF($rif_cliente)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'RIF INVÁLIDO.'];
        header("Location: salidas.php"); exit();
    }

    if ($accion === 'registrar') {
        $prod_info = $db->fetchOne("SELECT stock_actual, fecha_vencimiento FROM productos WHERE id_producto = ?", [$id_producto]);

        if (!$prod_info) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'PRODUCTO NO ENCONTRADO.'];
            header("Location: salidas.php"); exit();
        }
        if ($prod_info['fecha_vencimiento'] && $prod_info['fecha_vencimiento'] <= date('Y-m-d')) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'PRODUCTO VENCIDO. NO SE PUEDE VENDER.'];
            header("Location: salidas.php"); exit();
        }
        if ((int)$prod_info['stock_actual'] < $cantidad) {
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
            'grupo'               => $grupo,
            'causa_ajuste'        => $causa_ajuste,
            'observaciones'       => $observaciones,
            'id_usuario'          => $id_usuario,
        ];
        header("Location: preview_factura.php");
        exit();
    }

    if ($accion === 'editar') {
        $id_salida = intval($_POST['id_salida'] ?? 0);
        $salida = $db->fetchOne("SELECT id_salida, nro_factura_manual FROM salidas WHERE id_salida = ?", [$id_salida]);

        if (!$salida) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'REGISTRO NO ENCONTRADO.'];
            header("Location: salidas.php"); exit();
        }

        // Obtener detalles anteriores para restaurar stock
        $ant_detalles = $db->fetchAll("SELECT id_producto, cantidad FROM detalle_salidas WHERE id_salida = ?", [$id_salida]);

        $db->begin();
        try {
            // Restaurar stock de productos anteriores
            foreach ($ant_detalles as $det) {
                $db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [(int)$det['cantidad'], (int)$det['id_producto']]);
            }

            // Validar stock antes de descontar
            $prod_check = $db->fetchOne("SELECT stock_actual FROM productos WHERE id_producto = ?", [$id_producto]);
            if (!$prod_check || (int)$prod_check['stock_actual'] < $cantidad)
                throw new Exception("STOCK INSUFICIENTE. Disponible: {$prod_check['stock_actual']}, solicitado: $cantidad.");

            // Actualizar cabecera
            $db->execute(
                "UPDATE salidas SET nro_control=?, cliente=?, rif_cliente=?, fecha_salida=?, id_tipo_mov=?, observaciones=? WHERE id_salida=?",
                [$nro_control, $cliente, $rif_cliente, $fecha_salida, $id_tipo_mov, $observaciones, $id_salida]
            );

            // Eliminar detalles viejos e insertar el nuevo
            $db->execute("DELETE FROM detalle_salidas WHERE id_salida = ?", [$id_salida]);
            $db->insert('detalle_salidas', [
                'id_salida'    => $id_salida,
                'id_producto'  => $id_producto,
                'cantidad'     => $cantidad,
                'precio_venta' => $precio_venta,
            ]);

            $db->execute("UPDATE productos SET stock_actual = stock_actual - ? WHERE id_producto = ?", [$cantidad, $id_producto]);

            $db->commit();
            $det_edit = $grupo === 'merma' ? "Ajuste (-) editado, Causa: $causa_ajuste" : 'Venta editada';
            registrarAuditoria('editar', $det_edit);
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'SALIDA ACTUALIZADA CORRECTAMENTE.'];
            header("Location: salidas.php");
            exit();
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
            header("Location: salidas.php");
            exit();
        }
    }
}

// Eliminar / anular salida
if (isset($_GET['eliminar'])) {
    Security::soloAdmin();
    $id_salida = intval($_GET['eliminar']);
    $detalles = $db->fetchAll("SELECT id_producto, cantidad FROM detalle_salidas WHERE id_salida = ?", [$id_salida]);
    if (!empty($detalles)) {
        $db->begin();
        try {
            foreach ($detalles as $det) {
                $db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [(int)$det['cantidad'], (int)$det['id_producto']]);
            }
            $db->execute("UPDATE salidas SET status = 'Anulada' WHERE id_salida = ?", [$id_salida]);
            $db->execute("UPDATE movimientos SET status = 'Anulado' WHERE id_referencia = ? AND tipo_referencia = 'venta'", [$id_salida]);
            $db->commit();
            registrarAuditoria('anular', "Salida #$id_salida anulada, " . count($detalles) . " producto(s)");
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'SALIDA ANULADA. STOCK RESTAURADO.'];
            header("Location: salidas.php"); exit();
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR EN LA BASE DE DATOS.'];
            header("Location: salidas.php"); exit();
        }
    }
}

// ==========================================
// OBTENER DATOS
// ==========================================
$sql = "
    SELECT s.*,
           GROUP_CONCAT(p.nombre_producto SEPARATOR ', ') as productos_list,
           SUM(ds.cantidad) as total_cantidad,
           COUNT(ds.id_detalle) as num_productos,
           tm.nombre as tipo_mov_nombre,
           (SELECT ds2.id_producto FROM detalle_salidas ds2 WHERE ds2.id_salida = s.id_salida ORDER BY ds2.id_detalle LIMIT 1) as first_id_producto,
           (SELECT ds2.cantidad FROM detalle_salidas ds2 WHERE ds2.id_salida = s.id_salida ORDER BY ds2.id_detalle LIMIT 1) as first_cantidad,
           (SELECT ds2.precio_venta FROM detalle_salidas ds2 WHERE ds2.id_salida = s.id_salida ORDER BY ds2.id_detalle LIMIT 1) as first_precio_venta
    FROM salidas s
    LEFT JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
    LEFT JOIN productos p ON ds.id_producto = p.id_producto
    LEFT JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
    WHERE s.status = 'Activa'
    GROUP BY s.id_salida
    ORDER BY s.fecha_salida DESC, s.id_salida DESC
";
$salidas = $db->fetchAll($sql);
$productos = $db->fetchAll("SELECT id_producto, nombre_producto, sku, precio_venta, fecha_vencimiento FROM productos WHERE status = 'Activo' ORDER BY nombre_producto ASC");
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
<!-- HEAD Y ESTILOS HTML -->
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
        border-color:#ef4444;
        box-shadow:0 0 0 3px rgba(239,68,68,0.15);
    }
    .input-error { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,0.15) !important; }

    /* === DYNAMIC FIELD GROUPS === */
    .sal-field-group { display:none; }
    .sal-field-group.active { display:block; }
    </style>
</head>
<!-- BODY HTML -->
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
    <div class="container-fluid px-4 py-4 pagina-salidas">

        <!-- Encabezado -->
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

        <!-- Mensajes flash -->
        <?php if ($flash): ?>
        <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4">
            <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($flash['texto']); ?>
        </div>
        <?php endif; ?>

        <!-- Tabla de ventas -->
        <div class="card-jv p-0">
            <div class="table-responsive">
                <table class="table-jv mb-0">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Nro. Control</th>
                            <th>Cliente</th>
                            <th>Productos</th>
                            <th class="text-center">Cant</th>
                            <th>Tipo</th>
                            <th class="text-center">Fecha</th>
                            <th class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($salidas) > 0): ?>
                            <?php foreach ($salidas as $row): ?>
                                <tr>
                                    <td><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura_manual'] ?: '#' . $row['id_salida']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['nro_control']); ?></td>
                                    <td class="text-uppercase">
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['cliente'] ?? 'Venta General'); ?></div>
                                        <div class="text-secondary small"><?php echo htmlspecialchars($row['rif_cliente'] ?? 'S/RIF'); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars(mb_substr($row['productos_list'] ?? '', 0, 60)) . (mb_strlen($row['productos_list'] ?? '') > 60 ? '...' : ''); ?></td>
                                    <td class="text-center"><span class="badge-jv badge-danger">-<?php echo $row['total_cantidad']; ?></span></td>
                                    <td><?php
                                        $tn = $row['tipo_mov_nombre'] ?? '';
                                        $obs = $row['observaciones'] ?? '';
                                        $causa = '';
                                        if (preg_match('/^Causa:\s*(.+?)(?:\s*\||$)/', $obs, $m)) $causa = trim($m[1]);
                                        $g = getGrupoTipo($tn);
                                        if ($g === 'venta') echo '<span class="badge-jv badge-success" style="font-size:.7rem;"><i class="bi bi-cart me-1"></i>Venta</span>';
                                        elseif ($g === 'regalias') echo '<span class="badge-jv badge-info" style="font-size:.7rem;"><i class="bi bi-gift me-1"></i>Regalía</span>';
                                        else echo '<span class="badge-jv badge-warning" style="font-size:.7rem;" title="' . htmlspecialchars($causa) . '"><i class="bi bi-exclamation-triangle me-1"></i>' . htmlspecialchars($tn) . ($causa ? ': ' . htmlspecialchars($causa) : '') . '</span>';
                                    ?></td>
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

    <!-- Modal: Nueva / Editar salida -->
    <div class="modal fade" id="modalSalida" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <form action="" method="POST" id="formSalida">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_salida" id="s_accion" value="registrar">
                    <input type="hidden" name="id_salida" id="s_id_edit">
                    <div class="modal-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bolder font-brand text-uppercase m-0" id="modalTitle" style="color:#fca5a5;">REGISTRAR MOVIMIENTO</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>

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
                                <?php foreach ($productos as $pr):
                                    $alerta = '';
                                    if ($pr['fecha_vencimiento'] && $pr['fecha_vencimiento'] <= date('Y-m-d')) {
                                        $alerta = '«VENCIDO» ';
                                    } elseif ($pr['fecha_vencimiento'] && $pr['fecha_vencimiento'] <= date('Y-m-d', strtotime('+7 days'))) {
                                        $alerta = '«PRÓX» ';
                                    }
                                ?>
                                    <option value="<?php echo $pr['id_producto']; ?>" data-precio="<?php echo $pr['precio_venta']; ?>"><?php echo $alerta . $pr['sku'] . " - " . $pr['nombre_producto']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- FECHA (todos los grupos) -->
                        <div class="section-bg">
                            <label class="small fw-bold text-secondary mb-2">FECHA</label>
                            <input type="date" id="s_fecha" class="input-jv" value="<?php echo date('Y-m-d'); ?>" disabled>
                            <input type="hidden" name="fecha_salida" id="s_fecha_hidden" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- CANTIDAD (todos los grupos) -->
                        <div class="section-bg">
                            <label class="small fw-bold text-secondary mb-2">CANTIDAD</label>
                            <input type="number" name="cantidad" id="s_cant" class="input-jv" required min="1" max="999999" oninput="if(this.value>999999)this.value=999999;if(this.value<1)this.value=1" placeholder="1">
                        </div>

                        <!-- GRUPO: VENTA -->
                        <div class="sal-field-group" data-grupo="venta">
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">PRECIO UNITARIO ($)</label>
                                <input type="text" inputmode="decimal" name="precio_venta" id="s_precio" class="input-jv" placeholder="0.00" oninput="formatearPrecio(this)">
                            </div>
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">CLIENTE</label>
                                <input type="text" name="cliente" id="s_cliente" class="input-jv" placeholder="Nombre o Razón Social">
                            </div>
                        </div>

                        <!-- GRUPO: REGALIAS (solo cliente, precio $0) -->
                        <div class="sal-field-group" data-grupo="regalias">
                            <div class="section-bg">
                                <label class="small fw-bold text-secondary mb-2">CLIENTE</label>
                                <input type="text" name="cliente" id="s_cliente_reg" class="input-jv" placeholder="Nombre o Razón Social" oninput="document.getElementById('s_cliente').value=this.value">
                            </div>
                        </div>

                        <!-- GRUPO: MERMA (Mermas + Daños) -->
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

                        <!-- DATOS ADICIONALES -->
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

                        <button type="button" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase" id="btnPreview" onclick="enviarPreview()">
                            📄 VISTA PREVIA NOTA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sweetalert2.all.min.js"></script>
    <script>
        const modalS = new bootstrap.Modal(document.getElementById('modalSalida'));
        const TIPO_MAP = <?php echo json_encode($tipos_mov_map); ?>;

        function toggleCampos() {
            limpiarErrores();
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
            limpiarErrores();
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
            var hoy = new Date().toISOString().slice(0,10);
            document.getElementById('s_fecha').value = hoy;
            document.getElementById('s_fecha_hidden').value = hoy;
            document.getElementById('s_desc_motivo') && (document.getElementById('s_desc_motivo').value = '');
            document.getElementById('s_causa') && (document.getElementById('s_causa').value = '');
            document.getElementById('s_tipo').value = '';
            document.querySelectorAll('.sal-field-group').forEach(el => el.classList.remove('active'));
            modalS.show();
        }

        function editarSalida(data) {
            document.getElementById('s_accion').value = 'editar';
            document.getElementById('s_id_edit').value = data.id_salida;
            document.getElementById('modalTitle').innerText = 'EDITAR SALIDA';
            document.getElementById('s_fecha').value = data.fecha_salida;
            document.getElementById('s_fecha_hidden').value = data.fecha_salida;
            document.getElementById('s_prod').value = data.first_id_producto;
            document.getElementById('s_cliente').value = data.cliente;
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = data.cliente);
            document.getElementById('s_rif').value = data.rif_cliente;
            validarRIFInput(document.getElementById('s_rif'));
            document.getElementById('s_cant').value = data.first_cantidad;
            var pv = document.getElementById('s_precio'); pv.value = parseFloat(data.first_precio_venta).toFixed(2); formatearPrecio(pv);
            document.getElementById('s_tipo').value = data.id_tipo_mov;
            document.getElementById('s_obs').value = data.observaciones;
            toggleCampos();
            modalS.show();
        }

        function formatearNumero(nums, tipo) {
            if (nums.length <= 8) return nums;
            return nums.slice(0,8) + '-' + nums.slice(8,9);
        }
        function formatearPrecio(el) {
            var raw = el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
            var parts = raw.split('.');
            var entero = parts[0].replace(/^0+/, '') || '0';
            var decimales = parts[1] ? parts[1].slice(0,2) : '';
            var formateado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (decimales) formateado += '.' + decimales;
            var num = parseFloat(entero + '.' + (decimales || '0'));
            if (num > 999999.99) { entero = '999999'; decimales = '99'; formateado = '999,999.99'; }
            el.value = formateado;
        }
        function validarRIFInput(el) {
            var raw = el.value.toUpperCase().replace(/[^VJEPG\d]/g, '');
            var msg = document.getElementById('s-rif-msg');
            if (raw === '') { msg.innerHTML = ''; el.style.borderColor = ''; el.value = ''; return; }
            var letter = raw.match(/^[VJEPG]/);
            var prefix = letter ? letter[0] + '-' : '';
            var nums = prefix ? raw.slice(1).replace(/\D/g, '') : raw.replace(/\D/g, '');
            var display, clean;
            if (prefix) {
                display = prefix + formatearNumero(nums, letter[0]);
                clean = prefix + nums;
            } else {
                display = formatearNumero(nums, 'V');
                clean = nums;
            }
            var valido = /^[VJGPE]-\d{7,9}(?:-\d)?$/.test(clean) || /^\d{7,9}$/.test(clean);
            if (valido) {
                msg.innerHTML = '<span style="color:#22c55e;">✓ Válido</span>';
                el.style.borderColor = '#22c55e';
            } else {
                msg.innerHTML = '<span style="color:#ef4444;">Anteponga V- o J- y escriba los números</span>';
                el.style.borderColor = '#ef4444';
            }
            el.value = display;
        }

        function limpiarErrores() {
            document.querySelectorAll('.input-error').forEach(function(el) { el.classList.remove('input-error'); });
            document.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
        }
        function marcarError(el, msg) {
            el.classList.add('input-error');
            if (msg && el.id) {
                var errEl = document.getElementById(el.id + '_err');
                if (!errEl) {
                    errEl = document.createElement('small');
                    errEl.id = el.id + '_err';
                    errEl.className = 'field-error';
                    errEl.style.cssText = 'color:#ef4444;font-size:.7rem;margin-top:2px;display:block;';
                    el.parentNode.appendChild(errEl);
                }
                errEl.textContent = msg;
            }
        }
        function enviarPreview() {
            limpiarErrores();
            const btn = document.getElementById('btnPreview');
            let valido = true;
            let primerError = null;

            const tipo = document.getElementById('s_tipo');
            if (!tipo.value) { marcarError(tipo, 'SELECCIONE TIPO'); valido = false; if (!primerError) primerError = tipo; }
            const prod = document.getElementById('s_prod');
            if (!prod.value) { marcarError(prod, 'SELECCIONE PRODUCTO'); valido = false; if (!primerError) primerError = prod; }
            const cant = parseInt(document.getElementById('s_cant').value);
            if (!cant || cant <= 0) { marcarError(document.getElementById('s_cant'), 'CANTIDAD > 0'); valido = false; if (!primerError) primerError = document.getElementById('s_cant'); }

            const grupo = TIPO_MAP[tipo.value] || '';
            if (grupo === 'merma') {
                const causa = document.getElementById('s_causa');
                if (!causa.value) { marcarError(causa, 'SELECCIONE CAUSA'); valido = false; if (!primerError) primerError = causa; }
            }
            if (grupo === 'regalias') {
                document.getElementById('s_precio').value = '0';
                document.getElementById('s_cliente').value = document.getElementById('s_cliente_reg').value;
            }
            if (grupo === 'venta') {
                var precEl = document.getElementById('s_precio');
                if (precEl) precEl.value = precEl.value.replace(/,/g, '');
                var pv = parseFloat(precEl.value);
                if (!pv || pv <= 0) { marcarError(precEl, 'PRECIO > 0'); valido = false; if (!primerError) primerError = precEl; }
            }
            var rifEl = document.getElementById('s_rif');
            var rifVal = rifEl.value.replace(/\./g, '');
            if (rifVal && !/^[VJGPE]-\d{7,9}(?:-\d)?$/.test(rifVal)) {
                marcarError(rifEl, 'RIF INVÁLIDO'); valido = false; if (!primerError) primerError = rifEl;
            }
            rifEl.value = rifVal;

            if (!valido) {
                btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                if (primerError) { primerError.focus(); var p = primerError.closest('.modal-body') || primerError; p.scrollIntoView({behavior:'smooth',block:'center'}); }
                return;
            }

            btn.disabled = true; btn.innerHTML = '⏳ PROCESANDO...';
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
    document.querySelectorAll('#formSalida input, #formSalida select, #formSalida textarea').forEach(function(el) {
        el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
        el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
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