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
            'cliente'            => $data['cliente'] ?? '',
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
    $cliente = mb_strtoupper(trim($_POST['cliente'] ?? ''));
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
           SUM(ds.cantidad * ds.precio_venta) as total_monto,
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
$productos = $db->fetchAll("SELECT id_producto, nombre_producto, sku, precio_venta, precio_costo, fecha_vencimiento FROM productos WHERE status = 'Activo' ORDER BY nombre_producto ASC");
$tipos_mov = $db->fetchAll("SELECT id_tipo_mov, nombre FROM tipos_movimientos WHERE tipo_movimiento = 'Salida' ORDER BY id_tipo_mov");
$clientes_previos = $db->fetchAll("SELECT DISTINCT cliente, rif_cliente FROM salidas WHERE cliente IS NOT NULL AND cliente != '' AND status = 'Activa' ORDER BY cliente ASC");

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
        color:var(--jv-text-primary);transition:.15s;
    }
    .btn-action:hover {
        background:rgba(255,255,255,0.05);border-color:#dc2626;
        color:#dc2626;
    }
    .section-bg {
        background:rgba(2,6,23,0.3);
        border:1px solid rgba(6,182,212,0.08);
        border-radius:var(--jv-radius);
        padding:12px 14px;
        margin-bottom:10px;
    }
    .section-label {
        font-size:.65rem;
        text-transform:uppercase;
        letter-spacing:1px;
        font-weight:700;
        color:rgba(148,163,184,0.6);
        margin-bottom:8px;
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
                            <th style="width:145px;">Nota de Entrega</th>
                            <th style="width:140px;">Control</th>
                            <th>Cliente</th>
                            <th>Productos</th>
                            <th class="text-center" style="width:55px;">Cant</th>
                            <th class="text-center" style="width:180px;">Tipo</th>
                            <th class="text-end" style="width:110px;">Total</th>
                            <th class="text-center" style="width:85px;">Fecha</th>
                            <th class="text-center" style="width:120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($salidas) > 0): ?>
                            <?php foreach ($salidas as $row): ?>
                                <tr>
                                    <td><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura_manual'] ?: '#' . $row['id_salida']); ?></span></td>
                                    <td style="font-size:.82rem;color:#94a3b8;"><?php echo htmlspecialchars($row['nro_control']); ?></td>
                                    <td class="text-uppercase">
                                        <div class="fw-bold" style="font-size:.85rem;"><?php echo htmlspecialchars($row['cliente'] ?? 'S/Cliente'); ?></div>
                                        <div class="text-secondary small" style="font-size:.7rem;"><?php echo htmlspecialchars($row['rif_cliente'] ?? 'S/RIF'); ?></div>
                                    </td>
                                    <td style="font-size:.82rem;color:#cbd5e1;"><?php echo htmlspecialchars(mb_substr($row['productos_list'] ?? '', 0, 60)) . (mb_strlen($row['productos_list'] ?? '') > 60 ? '...' : ''); ?></td>
                                    <td class="text-center"><span class="badge-jv badge-danger" style="font-size:.7rem;padding:3px 12px;">-<?php echo $row['total_cantidad']; ?></span></td>
                                    <td class="text-center"><?php
                                        $tn = $row['tipo_mov_nombre'] ?? '';
                                        $obs = $row['observaciones'] ?? '';
                                        $causa = '';
                                        if (preg_match('/^Causa:\s*(.+?)(?:\s*\||$)/', $obs, $m)) $causa = trim($m[1]);
                                        $g = getGrupoTipo($tn);
                                        if ($g === 'venta') echo '<span class="badge-jv badge-success"><i class="bi bi-cart me-1"></i>Venta</span>';
                                        elseif ($g === 'regalias') echo '<span class="badge-jv badge-info"><i class="bi bi-gift me-1"></i>Regalía</span>';
                                        else echo '<span class="badge-jv badge-warning" style="cursor:pointer;" title="' . htmlspecialchars($tn) . ($causa ? ': ' . htmlspecialchars($causa) : '') . '" onclick="verDetalleDano(\'' . htmlspecialchars($tn, ENT_QUOTES) . '\', \'' . htmlspecialchars($causa, ENT_QUOTES) . '\')"><i class="bi bi-exclamation-triangle me-1"></i>' . htmlspecialchars($tn) . '</span>';
                                    ?></td>
                                    <td class="text-end fw-bold" style="font-size:.9rem;<?php echo $g === 'merma' ? 'color:#f87171;' : 'color:#34d399;'; ?>">$<?php echo number_format($row['total_monto'] ?? 0, 2); ?></td>
                                    <td class="text-center" style="font-weight:600;font-size:.82rem;color:#e2e8f0;"><?php echo date('d/m/Y', strtotime($row['fecha_salida'])); ?></td>
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
                <form action="" method="POST" id="formSalida">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_salida" id="s_accion" value="registrar">
                    <input type="hidden" name="id_salida" id="s_id_edit">
                    <div class="modal-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bolder font-brand text-uppercase m-0" id="modalTitle" style="color:#fca5a5;">REGISTRAR MOVIMIENTO</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                                        <input type="text" name="cliente" id="s_cliente" class="input-jv" placeholder="Nombre o Razón Social">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold text-secondary mb-2">RIF / CÉDULA <span style="color:#ef4444;">*</span></label>
                                        <div class="d-flex gap-2">
                                            <select id="s_rif_tipo" class="input-jv" style="max-width:70px;flex-shrink:0;" onchange="validarRIFInput()">
                                                <option value="V">V-</option>
                                                <option value="J">J-</option>
                                                <option value="E">E-</option>
                                                <option value="P">P-</option>
                                                <option value="G">G-</option>
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
                                        <input type="text" name="cliente" id="s_cliente_reg" class="input-jv" placeholder="Nombre o Razón Social" oninput="document.getElementById('s_cliente').value=this.value">
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
                                    <select id="s_prod" class="input-jv" onchange="cargarPrecio()">
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($productos as $pr):
                                            $alerta = '';
                                            if ($pr['fecha_vencimiento'] && $pr['fecha_vencimiento'] <= date('Y-m-d')) {
                                                $alerta = '«VENCIDO» ';
                                            } elseif ($pr['fecha_vencimiento'] && $pr['fecha_vencimiento'] <= date('Y-m-d', strtotime('+7 days'))) {
                                                $alerta = '«PRÓX» ';
                                            }
                                        ?>
                                            <option value="<?php echo $pr['id_producto']; ?>" data-precio="<?php echo $pr['precio_venta']; ?>" data-costo="<?php echo $pr['precio_costo']; ?>"><?php echo $alerta . $pr['sku'] . " - " . $pr['nombre_producto']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-secondary mb-1">Cant</label>
                                    <input type="number" id="s_cant" class="input-jv" value="1" min="1" max="999999">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">Precio $</label>
                                    <input type="text" inputmode="decimal" id="s_precio" class="input-jv" placeholder="0.00" readonly style="background:rgba(255,255,255,0.04);cursor:not-allowed;color:#94a3b8;">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn-jv-primary w-100" style="margin-top:22px;padding:12px 8px;" onclick="agregarProductoSalida()">
                                        <i class="bi bi-plus-lg"></i> Agregar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- TABLA DE PRODUCTOS -->
                        <div style="border:1px solid rgba(6,182,212,0.12);border-radius:8px;overflow:hidden;margin-top:8px;">
                            <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-primary);">
                                <thead>
                                    <tr style="background:linear-gradient(135deg,#0e7490,#0891b2);">
                                        <th style="padding:4px 6px;width:26px;text-align:center;color:#a5f3fc;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">#</th>
                                        <th style="padding:4px 6px;color:#a5f3fc;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Producto</th>
                                        <th style="padding:4px 6px;width:50px;text-align:center;color:#a5f3fc;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Cant</th>
                                        <th style="padding:4px 6px;width:85px;text-align:right;color:#a5f3fc;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Precio</th>
                                        <th style="padding:4px 6px;width:85px;text-align:right;color:#a5f3fc;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Total</th>
                                        <th style="width:26px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="s_productos_body">
                                    <tr id="s_fila_vacia"><td colspan="6" style="padding:18px 10px;text-align:center;color:#64748b;font-size:.8rem;border-bottom:1px solid rgba(6,182,212,0.07);">⬆ Agregue productos con los controles de arriba</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;margin-top:6px;background:rgba(6,182,212,0.04);border:1px solid rgba(6,182,212,0.15);border-radius:8px;">
                            <span class="text-secondary small">Productos</span>
                            <span class="fw-bold ms-2" id="s_total_items" style="color:var(--jv-cyan);">0</span>
                            <span class="text-secondary small ms-auto">Total Venta</span>
                            <span class="fw-bold ms-2" id="s_total_monto" style="color:var(--jv-cyan);">$0.00</span>
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

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sweetalert2.all.min.js"></script>
    <script>
        const modalS = new bootstrap.Modal(document.getElementById('modalSalida'));
        const TIPO_MAP = <?php echo json_encode($tipos_mov_map); ?>;
        let s_productos = [];

        function agregarProductoSalida() {
            const sel = document.getElementById('s_prod');
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) { alert('Seleccione un producto.'); sel.focus(); return; }
            const cant = parseInt(document.getElementById('s_cant').value) || 0;
            if (cant < 1) { alert('Cantidad debe ser mayor a 0.'); document.getElementById('s_cant').focus(); return; }
            const tipo = document.getElementById('s_tipo');
            const grupo = tipo && tipo.value ? (TIPO_MAP[tipo.value] || '') : '';
            const precio = parseFloat(document.getElementById('s_precio').value) || 0;
            if (grupo !== 'regalias' && grupo !== 'merma' && precio <= 0) { alert('El precio debe ser mayor a 0.'); document.getElementById('s_precio').focus(); return; }
            s_productos.push({
                id_producto: opt.value,
                sku: opt.text.split(' - ')[0].replace(/«.*?»\s*/g, ''),
                nombre_producto: opt.text.split(' - ').slice(1).join(' - ').replace(/«.*?»\s*/g, ''),
                cantidad: cant,
                precio_venta: precio
            });
            actualizarTablaSalida();
            sel.selectedIndex = 0;
            document.getElementById('s_cant').value = 1;
            document.getElementById('s_precio').value = '';
        }

        function quitarProductoSalida(idx) {
            s_productos.splice(idx, 1);
            actualizarTablaSalida();
        }

        function actualizarTablaSalida() {
            const tbody = document.getElementById('s_productos_body');
            if (!s_productos.length) {
                tbody.innerHTML = '<tr id="s_fila_vacia"><td colspan="6" style="padding:24px 12px;text-align:center;color:#64748b;font-size:.85rem;border-bottom:1px solid rgba(6,182,212,0.07);">⬆ Agregue productos con los controles de arriba</td></tr>';
                document.getElementById('s_total_items').textContent = '0';
                document.getElementById('s_total_monto').textContent = '$0.00';
                return;
            }
            let html = '';
            let totalItems = 0;
            let totalMonto = 0;
            s_productos.forEach((p, i) => {
                const subtotal = p.cantidad * p.precio_venta;
                totalItems += p.cantidad;
                totalMonto += subtotal;
                html += `<tr>
                    <td style="padding:6px 8px;text-align:center;color:#94a3b8;font-size:.8rem;border-bottom:1px solid rgba(6,182,212,0.07);">${i+1}</td>
                    <td style="padding:6px 8px;color:var(--jv-text-primary);font-size:.85rem;border-bottom:1px solid rgba(6,182,212,0.07);">${p.sku} - ${p.nombre_producto}</td>
                    <td style="padding:6px 8px;text-align:center;color:#e2e8f0;font-size:.85rem;border-bottom:1px solid rgba(6,182,212,0.07);">${p.cantidad}</td>
                    <td style="padding:6px 8px;text-align:right;color:#94a3b8;font-size:.85rem;border-bottom:1px solid rgba(6,182,212,0.07);">$${p.precio_venta.toFixed(2)}</td>
                    <td style="padding:6px 8px;text-align:right;color:var(--jv-cyan);font-weight:600;font-size:.85rem;border-bottom:1px solid rgba(6,182,212,0.07);">$${subtotal.toFixed(2)}</td>
                    <td style="padding:6px 8px;text-align:center;border-bottom:1px solid rgba(6,182,212,0.07);">
                        <button type="button" onclick="quitarProductoSalida(${i})" style="background:none;border:none;color:#ef4444;font-size:1rem;cursor:pointer;padding:2px 6px;" title="Quitar">&times;</button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
            document.getElementById('s_total_items').textContent = totalItems;
            document.getElementById('s_total_monto').textContent = '$' + totalMonto.toFixed(2);
        }

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
            // reset productos al cambiar tipo
            s_productos = [];
            actualizarTablaSalida();
            if (document.getElementById('s_prod').value) cargarPrecio();
        }

        function nuevaSalida() {
            limpiarErrores();
            document.getElementById('s_accion').value = 'registrar';
            document.getElementById('s_id_edit').value = '';
            document.getElementById('modalTitle').innerText = 'REGISTRAR MOVIMIENTO';
            s_productos = [];
            actualizarTablaSalida();
            document.getElementById('s_cliente').value = '';
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = '');
            document.getElementById('s_rif_tipo').value = 'V';
            document.getElementById('s_rif_num').value = '';
            document.getElementById('s_rif').value = '';
            var m = document.getElementById('s-rif-msg'); if (m) m.innerHTML = '';
            var ri = document.getElementById('s_rif_num'); if (ri) ri.style.borderColor = '';
            document.getElementById('s_motivo_reg') && (document.getElementById('s_motivo_reg').value = '');
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

        function validarRIFInput() {
            var tipo = document.getElementById('s_rif_tipo').value;
            var nums = document.getElementById('s_rif_num').value.replace(/\D/g, '');
            var msg = document.getElementById('s-rif-msg');
            var numInput = document.getElementById('s_rif_num');
            var hidden = document.getElementById('s_rif');
            var maxDig = (tipo === 'J' || tipo === 'G') ? 9 : 8;
            if (nums.length > maxDig) { nums = nums.slice(0, maxDig); }
            if (nums === '') {
                msg.innerHTML = ''; numInput.style.borderColor = ''; hidden.value = ''; numInput.value = '';
                return;
            }
            var formatted = nums.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            var valido = (tipo === 'V' || tipo === 'E') ? nums.length >= 7 : nums.length >= 8;
            hidden.value = tipo + '-' + nums;
            numInput.value = formatted;
            if (valido) {
                msg.innerHTML = '<span style="color:#22c55e;">✓ Válido</span>';
                numInput.style.borderColor = '#22c55e';
            } else {
                msg.innerHTML = '<span style="color:#ef4444;">RIF incompleto</span>';
                numInput.style.borderColor = '#ef4444';
            }
        }

        function editarSalida(data) {
            document.getElementById('s_accion').value = 'editar';
            document.getElementById('s_id_edit').value = data.id_salida;
            document.getElementById('modalTitle').innerText = 'EDITAR SALIDA';
            document.getElementById('s_fecha').value = data.fecha_salida;
            document.getElementById('s_fecha_hidden').value = data.fecha_salida;
            document.getElementById('s_cliente').value = data.cliente;
            document.getElementById('s_cliente_reg') && (document.getElementById('s_cliente_reg').value = data.cliente);
            var rifMatch = (data.rif_cliente || '').match(/^([VJGPE])-(\d+)/);
            if (rifMatch) {
                document.getElementById('s_rif_tipo').value = rifMatch[1];
                document.getElementById('s_rif_num').value = rifMatch[2];
            } else {
                document.getElementById('s_rif_tipo').value = 'V';
                document.getElementById('s_rif_num').value = '';
            }
            validarRIFInput();
            // cargar productos desde JSON si existe
            try {
                var prods = JSON.parse(data.productos_json || '[]');
                if (prods.length) { s_productos = prods; actualizarTablaSalida(); }
            } catch(e) {}
            document.getElementById('s_tipo').value = data.id_tipo_mov;
            document.getElementById('s_obs').value = data.observaciones;
            toggleCampos();
            modalS.show();
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

            if (!s_productos.length) { marcarError(document.getElementById('s_prod'), 'AGREGUE PRODUCTOS'); valido = false; if (!primerError) primerError = document.getElementById('s_prod'); }

            const grupo = TIPO_MAP[tipo.value] || '';
            if (grupo === 'merma') {
                const causa = document.getElementById('s_causa');
                if (!causa.value) { marcarError(causa, 'SELECCIONE CAUSA'); valido = false; if (!primerError) primerError = causa; }
            }
            if (grupo === 'regalias') {
                document.getElementById('s_precio').value = '0';
                document.getElementById('s_cliente').value = document.getElementById('s_cliente_reg').value;
                const motivo = document.getElementById('s_motivo_reg');
                if (!motivo.value) { marcarError(motivo, 'SELECCIONE MOTIVO'); valido = false; if (!primerError) primerError = motivo; }
                const cliReg = document.getElementById('s_cliente_reg');
                if (!cliReg.value.trim()) { marcarError(cliReg, 'CLIENTE OBLIGATORIO'); valido = false; if (!primerError) primerError = cliReg; }
            }
            if (grupo === 'venta') {
                const rifEl = document.getElementById('s_rif');
                const rifInput = document.getElementById('s_rif_num');
                const rifMsg = document.getElementById('s-rif-msg');
                const rifTipo = document.getElementById('s_rif_tipo');
                if (!rifEl.value) { marcarError(rifInput, 'RIF OBLIGATORIO'); valido = false; if (!primerError) primerError = rifInput; }
                else if (rifMsg && rifMsg.innerHTML.includes('incompleto')) { marcarError(rifInput, 'RIF INCOMPLETO'); valido = false; if (!primerError) primerError = rifInput; }
                const cli = document.getElementById('s_cliente');
                if (!cli.value.trim()) { marcarError(cli, 'CLIENTE OBLIGATORIO'); valido = false; if (!primerError) primerError = cli; }
            }

            if (!valido) {
                btn.disabled = false; btn.innerHTML = '📄 VISTA PREVIA NOTA';
                if (primerError) { primerError.focus(); var p = primerError.closest('.modal-body') || primerError; p.scrollIntoView({behavior:'smooth',block:'center'}); }
                return;
            }

            btn.disabled = true; btn.innerHTML = '⏳ PROCESANDO...';

    // inyectar productos como JSON en el campo que espera el backend
    let pj = document.getElementById('formSalida').querySelector('[name="productos_data"]');
    if (!pj) { pj = document.createElement('input'); pj.type = 'hidden'; pj.name = 'productos_data'; document.getElementById('formSalida').appendChild(pj); }
    // mapear a lo que espera el backend: id_producto, cantidad, precio
    const payload = s_productos.map(p => ({
        id_producto: parseInt(p.id_producto),
        cantidad: parseInt(p.cantidad),
        precio: parseFloat(p.precio_venta)
    }));
    pj.value = JSON.stringify(payload);

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

        function verDetalleDano(tipo, causa) {
            Swal.fire({
                icon: 'info',
                title: 'Detalle del Movimiento',
                html: '<div style="text-align:left;"><strong>Tipo:</strong> ' + tipo + '<br><strong>Causa:</strong> ' + (causa || 'No especificada') + '</div>',
                background: '#0f172a',
                color: '#fff',
                confirmButtonColor: '#06b6d4',
                confirmButtonText: 'OK'
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
            if (!opt || !opt.value) { precio.value = ''; return; }
            const tipo = document.getElementById('s_tipo');
            const grupo = tipo && tipo.value ? (TIPO_MAP[tipo.value] || '') : '';
            if (grupo === 'regalias') {
                precio.value = '0.00';
            } else if (grupo === 'merma' && opt.dataset.costo) {
                precio.value = parseFloat(opt.dataset.costo).toFixed(2);
            } else if (opt.dataset.precio) {
                precio.value = parseFloat(opt.dataset.precio).toFixed(2);
            }
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