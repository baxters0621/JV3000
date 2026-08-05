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
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_GET['confirm'])) {
    $preview_token = $_GET['token'] ?? '';
    $data = $preview_token !== '' ? ($_SESSION['preview_data_' . $preview_token] ?? null) : ($_SESSION['preview_data'] ?? null);
    if (!$data) {
        header("Location: salidas.php"); exit();
    }

    // Procesar producto(s) desde preview_data
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

    $es_edicion = ($data['accion_salida'] ?? '') === 'editar';
    $id_editar = intval($data['id_salida'] ?? 0);
    $grupo_data = $data['grupo'] ?? 'venta';

    $db->begin();
    try {
        if (count($productos_raw) > 200) { throw new Exception("MÁXIMO 200 PRODUCTOS POR VENTA."); }

        // 0. Si es edición: restaurar el stock y los lotes de los detalles actuales
        if ($es_edicion) {
            $salida_vieja = $db->fetchOne("SELECT id_salida FROM salidas WHERE id_salida = ? AND status = 'Activa'", [$id_editar]);
            if (!$salida_vieja) throw new Exception("LA SALIDA A EDITAR NO EXISTE O YA FUE ANULADA.");
            $ant_detalles = $db->fetchAll("SELECT id_producto, id_lote, cantidad FROM detalle_salidas WHERE id_salida = ?", [$id_editar]);
            foreach ($ant_detalles as $det) {
                $db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [(int)$det['cantidad'], (int)$det['id_producto']]);
                if (!empty($det['id_lote'])) devolverLote($db, (int)$det['id_lote'], (int)$det['cantidad']);
            }
        }

        // Validar stock de todos los productos (después de restaurar, en caso de edición)
        $solo_vencidos = $grupo_data === 'merma';
        foreach ($productos_raw as $prod) {
            $id_producto = intval($prod['id_producto'] ?? 0);
            $cantidad = intval($prod['cantidad'] ?? 0);
            if ($id_producto <= 0 || $cantidad <= 0) continue;
            $pi = $db->fetchOne("SELECT stock_actual FROM productos WHERE id_producto = ?", [$id_producto]);
            if (!$pi) throw new Exception("Producto #$id_producto no encontrado");
            $tiene_lotes = (int)$db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE id_producto = ?", [$id_producto])['n'];
            if ($tiene_lotes > 0) {
                $disp = stockLoteDisponible($db, $id_producto, $solo_vencidos);
                if ($disp < $cantidad) {
                    $modo = $solo_vencidos ? 'VENCIDO' : 'VIGENTE';
                    throw new Exception("STOCK $modo INSUFICIENTE para producto (ID:$id_producto). Disponible: $disp, solicitado: $cantidad");
                }
            } elseif ((int)$pi['stock_actual'] < $cantidad) {
                throw new Exception("Stock insuficiente para producto (ID:$id_producto). Disponible:{$pi['stock_actual']}, solicitado:$cantidad");
            }
        }

        // 1. Cabecera: actualizar o insertar
        if ($es_edicion) {
            $db->execute(
                "UPDATE salidas SET nro_control=?, cliente=?, rif_cliente=?, fecha_salida=?, id_tipo_mov=?, observaciones=? WHERE id_salida=?",
                [$data['nro_control'] ?? '', $data['cliente'] ?? '', $data['rif_cliente'] ?? 'N/A', $data['fecha_salida'] ?? date('Y-m-d H:i:s'), intval($data['id_tipo_mov']), $data['observaciones'] ?? '', $id_editar]
            );
            $db->execute("DELETE FROM detalle_salidas WHERE id_salida = ?", [$id_editar]);
            $salida_id = $id_editar;
        } else {
            $salida_id = $db->insert('salidas', [
                'nro_factura_manual' => generarFacturaNumero(),
                'nro_control'        => $data['nro_control'] ?? '',
                'cliente'            => $data['cliente'] ?? '',
                'rif_cliente'        => $data['rif_cliente'] ?? 'N/A',
                'id_tipo_mov'        => intval($data['id_tipo_mov']),
                'id_usuario'         => $data['id_usuario'],
                'fecha_salida'       => $data['fecha_salida'] ?? date('Y-m-d H:i:s'),
                'status'             => 'Activa',
                'observaciones'      => $data['observaciones'] ?? '',
            ]);
        }

        // 2. Insertar detalles en lote y descontar stock (consumo FEFO por lote)
        foreach ($productos_raw as $prod) {
            $id_producto = intval($prod['id_producto'] ?? 0);
            $cantidad = intval($prod['cantidad'] ?? 0);
            $precio_venta = floatval($prod['precio'] ?? 0);
            if ($id_producto <= 0 || $cantidad <= 0) continue;

            $tiene_lotes = (int)$db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE id_producto = ?", [$id_producto])['n'];
            if ($tiene_lotes > 0) {
                $usados = consumirLotes($db, $id_producto, $cantidad, $solo_vencidos);
                foreach ($usados as $u) {
                    $db->insert('detalle_salidas', [
                        'id_salida'    => $salida_id,
                        'id_producto'  => $id_producto,
                        'id_lote'      => $u['id_lote'],
                        'cantidad'     => $u['cantidad'],
                        'precio_venta' => $precio_venta,
                    ]);
                    $db->execute("UPDATE lotes SET cantidad_restante = cantidad_restante - ? WHERE id_lote = ?", [$u['cantidad'], $u['id_lote']]);
                }
            } else {
                $db->insert('detalle_salidas', [
                    'id_salida'    => $salida_id,
                    'id_producto'  => $id_producto,
                    'id_lote'      => null,
                    'cantidad'     => $cantidad,
                    'precio_venta' => $precio_venta,
                ]);
            }

            $db->execute("UPDATE productos SET stock_actual = stock_actual - ? WHERE id_producto = ?", [$cantidad, $id_producto]);
        }

        // 3. Movimiento de inventario
        if ($es_edicion) {
            $mov = $db->fetchOne("SELECT id_movimiento FROM movimientos WHERE id_referencia = ? AND tipo_referencia = 'venta'", [$id_editar]);
            if ($mov) {
                $db->execute("DELETE FROM detalle_movimientos WHERE id_movimiento = ?", [$mov['id_movimiento']]);
                $mov_id = $mov['id_movimiento'];
            } else {
                $mov_id = $db->insert('movimientos', [
                    'id_referencia'   => $id_editar,
                    'tipo_referencia' => 'venta',
                    'tipo'            => 'Salida',
                    'id_usuario'      => $data['id_usuario'],
                    'status'          => 'Activo',
                ]);
            }
        } else {
            $mov_id = $db->insert('movimientos', [
                'id_referencia'   => $salida_id,
                'tipo_referencia' => 'venta',
                'tipo'            => 'Salida',
                'id_usuario'      => $data['id_usuario'],
                'status'          => 'Activo',
            ]);
        }

        // 4. Insertar detalle de movimiento (re-iterate productos)
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
        if ($es_edicion) {
            $det_auditoria = $grupo_data === 'merma'
                ? "Ajuste (-) editado: Causa: $causa_data, " . count($productos_raw) . " producto(s)"
                : "Venta editada, " . count($productos_raw) . " producto(s)";
            registrarAuditoria('editar', $det_auditoria);
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'SALIDA ACTUALIZADA CORRECTAMENTE.'];
        } else {
            $det_auditoria = $grupo_data === 'merma'
                ? "Ajuste (-): Causa: $causa_data, " . count($productos_raw) . " producto(s)"
                : "Venta registrada, " . count($productos_raw) . " producto(s)";
            registrarAuditoria('crear', $det_auditoria);
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'VENTA REGISTRADA EXITOSAMENTE.'];
        }
        if ($preview_token !== '') { unset($_SESSION['preview_data_' . $preview_token]); } else { unset($_SESSION['preview_data']); }
        header("Location: salidas.php#salida-$salida_id");
        exit();
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
        if ($preview_token !== '') { unset($_SESSION['preview_data_' . $preview_token]); } else { unset($_SESSION['preview_data']); }
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
    if ($cantidad > 999999) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'CANTIDAD MÁXIMA PERMITIDA: 999,999.'];
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

        purgarPreviewsSesion();
        $preview_token = bin2hex(random_bytes(16));
        $_SESSION['preview_data_' . $preview_token] = [
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
        header("Location: preview_factura.php?token=" . $preview_token);
        exit();
    }

}

// Eliminar / anular salida
if (isset($_POST['eliminar'])) {
    Security::soloAdmin();
    $id_salida = intval($_POST['eliminar']);
    $detalles = $db->fetchAll("SELECT id_producto, id_lote, cantidad FROM detalle_salidas WHERE id_salida = ?", [$id_salida]);
    if (!empty($detalles)) {
        $db->begin();
        try {
            foreach ($detalles as $det) {
                $db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [(int)$det['cantidad'], (int)$det['id_producto']]);
                if (!empty($det['id_lote'])) devolverLote($db, (int)$det['id_lote'], (int)$det['cantidad']);
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

// Adjuntar productos por salida en JSON para el modal de edición
if ($salidas) {
    $ids_sal = implode(',', array_map(fn($r) => (int)$r['id_salida'], $salidas));
    $detalle_all = $db->fetchAll("SELECT ds.id_salida, ds.id_producto, ds.cantidad, ds.precio_venta, p.nombre_producto FROM detalle_salidas ds JOIN productos p ON ds.id_producto = p.id_producto WHERE ds.id_salida IN ($ids_sal)");
    $mapa_det = [];
    foreach ($detalle_all as $d) {
        $mapa_det[$d['id_salida']][] = [
            'id_producto'   => (int)$d['id_producto'],
            'nombre_producto' => $d['nombre_producto'],
            'cantidad'      => (int)$d['cantidad'],
            'precio_venta'  => (float)$d['precio_venta'],
        ];
    }
    foreach ($salidas as &$s) { $s['productos_json'] = json_encode($mapa_det[$s['id_salida']] ?? []); }
    unset($s);
}
$productos = $db->fetchAll("SELECT id_producto, nombre_producto, sku, precio_venta, precio_costo, stock_actual, fecha_vencimiento FROM productos WHERE status = 'Activo' ORDER BY nombre_producto ASC");
$mapa_lotes = [];
foreach ($db->fetchAll("SELECT id_producto,
        SUM(CASE WHEN fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= CURDATE() THEN cantidad_restante ELSE 0 END) as vencido,
        SUM(CASE WHEN fecha_vencimiento IS NULL OR fecha_vencimiento > CURDATE() THEN cantidad_restante ELSE 0 END) as vigente
      FROM lotes GROUP BY id_producto") as $lt) {
    $mapa_lotes[(int)$lt['id_producto']] = $lt;
}
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
        <link rel="stylesheet" href="../assets/modules/salidas/salidas.css">
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
                <h1 class="font-brand m-0" style="font-size:1.6rem;letter-spacing:-1px; color: var(--jv-text-primary);">SALIDAS / VENTAS</h1>
                <p class="text-secondary small fw-bold text-uppercase m-0">Notas de Entrega y Despacho</p>
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
                                    <td style="vertical-align:middle;text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura_manual'] ?: '#' . $row['id_salida']); ?></span></td>
                                    <td style="font-size:.82rem;color:var(--jv-text-secondary);"><?php echo htmlspecialchars($row['nro_control']); ?></td>
                                    <td class="text-uppercase">
                                        <div class="fw-bold" style="font-size:.85rem;"><?php echo htmlspecialchars($row['cliente'] ?? 'S/Cliente'); ?></div>
                                        <div class="text-secondary small" style="font-size:.7rem;"><?php echo htmlspecialchars($row['rif_cliente'] ?? 'S/RIF'); ?></div>
                                    </td>
                                    <td style="font-size:.82rem;color:var(--jv-text-secondary);"><?php echo htmlspecialchars(mb_substr($row['productos_list'] ?? '', 0, 60)) . (mb_strlen($row['productos_list'] ?? '') > 60 ? '...' : ''); ?></td>
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
                                    <td class="text-end fw-bold" style="font-size:.9rem;<?php echo $g === 'merma' ? 'color:var(--jv-danger);' : 'color:var(--jv-success);'; ?>">$<?php echo number_format($row['total_monto'] ?? 0, 2); ?></td>
                                    <td class="text-center" style="font-weight:600;font-size:.82rem;color:var(--jv-text-primary);"><?php echo date('d/m/Y', strtotime($row['fecha_salida'])); ?></td>
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
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_salida" id="s_accion" value="registrar">
                    <input type="hidden" name="id_salida" id="s_id_edit">
                    <div class="modal-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bolder font-brand text-uppercase m-0" id="modalTitle" style="color:var(--jv-navy);">REGISTRAR MOVIMIENTO</h5>
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
                                        <input type="text" name="cliente" id="s_cliente" class="input-jv" placeholder="Nombre o Razón Social">
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
                                                $stock = (int)$pr['stock_actual'];
                                                $es_vencido = $pr['fecha_vencimiento'] && $pr['fecha_vencimiento'] <= date('Y-m-d');
                                                if ($es_vencido) {
                                                    $alerta = '«VENCIDO» ';
                                                } elseif ($pr['fecha_vencimiento'] && $pr['fecha_vencimiento'] <= date('Y-m-d', strtotime('+7 days'))) {
                                                    $alerta = '«PRÓX» ';
                                                }
                                                $disabled = $stock <= 0 ? 'disabled' : '';
                                                $venc_lot = isset($mapa_lotes[(int)$pr['id_producto']]) ? (int)$mapa_lotes[(int)$pr['id_producto']]['vencido'] : ($es_vencido ? $stock : 0);
                                                $vig_lot = isset($mapa_lotes[(int)$pr['id_producto']]) ? (int)$mapa_lotes[(int)$pr['id_producto']]['vigente'] : ($es_vencido ? 0 : $stock);
                                                $agotado = ($venc_lot + $vig_lot) <= 0;
                                                $disabled = $agotado ? 'disabled' : '';
                                                $label = $alerta . $pr['sku'] . ' - ' . $pr['nombre_producto'];
                                                if ($agotado) {
                                                    $label .= ' (AGOTADO)';
                                                } else {
                                                    $label .= " (Stock: $stock)";
                                                }
                                            ?>
                                                <option value="<?php echo $pr['id_producto']; ?>" data-precio="<?php echo $pr['precio_venta']; ?>" data-costo="<?php echo $pr['precio_costo']; ?>" data-stock="<?php echo $stock; ?>" data-stock-vigente="<?php echo $vig_lot; ?>" data-stock-vencido="<?php echo $venc_lot; ?>" data-vencido="<?php echo $es_vencido ? '1' : '0'; ?>" <?php echo $disabled; ?>><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                            <?php endforeach; ?>
                                    </select>
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
                        <div style="border:1px solid var(--jv-border);border-radius:8px;overflow:hidden;margin-top:8px;">
                            <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-card);">
                                <thead>
                                    <tr style="background:var(--jv-navy);">
                                        <th style="padding:4px 6px;width:26px;text-align:center;color:#fff;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">#</th>
                                        <th style="padding:4px 6px;color:#fff;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Producto</th>
                                        <th style="padding:4px 6px;width:50px;text-align:center;color:#fff;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Cant</th>
                                        <th style="padding:4px 6px;width:85px;text-align:right;color:#fff;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Precio</th>
                                        <th style="padding:4px 6px;width:85px;text-align:right;color:#fff;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Total</th>
                                        <th style="width:26px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="s_productos_body">
                                    <tr id="s_fila_vacia"><td colspan="6" style="padding:18px 10px;text-align:center;color:var(--jv-text-muted);font-size:.8rem;border-bottom:1px solid var(--jv-border);">⬆ Agregue productos con los controles de arriba</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;margin-top:6px;background:var(--jv-bg-card);border:1px solid var(--jv-border);border-radius:8px;">
                            <span class="text-secondary small">Productos</span>
                            <span class="fw-bold ms-2" id="s_total_items" style="color:var(--jv-navy);">0</span>
                            <span class="text-secondary small ms-auto">Total Venta</span>
                            <span class="fw-bold ms-2" id="s_total_monto" style="color:var(--jv-navy);">$0.00</span>
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
    window.JV_CONFIG = { c0: <?php echo json_encode($tipos_mov_map); ?>, c1: '<?php echo $csrf_token; ?>' };
</script>
    <script src="../assets/modules/salidas/salidas.js"></script>
    
    
</body>
</html>