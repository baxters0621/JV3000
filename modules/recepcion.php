<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();

$csrf_token = Security::generateToken();

// ==========================================
// PROCESAR ACCIONES POST
// ==========================================

// Registrar recepción de mercancía (crea lotes, movimientos y sube stock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_recepcion'])) {
    $id_compra = intval($_POST['id_compra'] ?? 0);
    $compra = $db->fetchOne("SELECT id_compra, nro_factura, id_proveedor, status, estado_recepcion FROM compras WHERE id_compra = ? AND status = 'Activa'", [$id_compra]);
    if (!$compra || $compra['estado_recepcion'] === 'Completa') {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'LA COMPRA NO EXISTE O YA FUE RECIBIDA POR COMPLETO.'];
        header('Location: recepcion.php');
        exit;
    }

    $items_raw = json_decode($_POST['items_data'] ?? '[]', true);
    $items = is_array($items_raw) ? $items_raw : [];
    if (empty($items)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'DEBE INDICAR AL MENOS UN PRODUCTO PARA RECIBIR.'];
        header('Location: recepcion.php');
        exit;
    }

    $solicitado = [];
    foreach ($items as $it) {
        $id_detalle = intval($it['id_detalle'] ?? 0);
        $cantidad = intval($it['cantidad'] ?? 0);
        if ($id_detalle <= 0 || $cantidad <= 0) continue;
        $venc = null;
        if (!empty($it['fecha_vencimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($it['fecha_vencimiento']))) {
            $venc = trim($it['fecha_vencimiento']);
        }
        $solicitado[$id_detalle] = ['cantidad' => $cantidad, 'fecha_vencimiento' => $venc];
    }
    if (empty($solicitado)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'DEBE INDICAR CANTIDADES VÁLIDAS PARA RECIBIR.'];
        header('Location: recepcion.php');
        exit;
    }

    $ids = array_keys($solicitado);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $filas = $db->fetchAll(
        "SELECT d.id_detalle, d.id_producto, d.cantidad, d.cantidad_recibida, d.precio_costo, d.fecha_vencimiento, p.nombre_producto
         FROM detalle_compras d JOIN productos p ON d.id_producto = p.id_producto
         WHERE d.id_compra = ? AND d.id_detalle IN ($placeholders)",
        array_merge([$id_compra], $ids)
    );
    if (count($filas) !== count($ids)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ALGUNO DE LOS PRODUCTOS NO PERTENECE A LA COMPRA.'];
        header('Location: recepcion.php');
        exit;
    }

    foreach ($filas as $f) {
        $restante = (int)$f['cantidad'] - (int)$f['cantidad_recibida'];
        if ($solicitado[(int)$f['id_detalle']]['cantidad'] > $restante) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => "CANTIDAD EXCEDE LO PENDIENTE PARA: {$f['nombre_producto']} (pendiente $restante)."];
            header('Location: recepcion.php');
            exit;
        }
    }

    $id_usuario_sesion = intval($_SESSION['id_usuario'] ?? 0);
    $id_proveedor = $compra['id_proveedor'] ? intval($compra['id_proveedor']) : null;

    $db->begin();
    try {
        $mov_id = $db->insert('movimientos', [
            'id_referencia'   => $id_compra,
            'tipo_referencia' => 'compra',
            'tipo'            => 'Entrada',
            'id_usuario'      => $id_usuario_sesion,
            'status'          => 'Activo',
        ]);

        $total_productos = 0;
        $total_unidades = 0;
        $faltante = false;

        foreach ($filas as $f) {
            $id_detalle = (int)$f['id_detalle'];
            $recibir = $solicitado[$id_detalle]['cantidad'];
            $venc = $solicitado[$id_detalle]['fecha_vencimiento'] ?? $f['fecha_vencimiento'];

            $db->insert('lotes', [
                'id_producto'       => (int)$f['id_producto'],
                'id_proveedor'      => $id_proveedor,
                'id_compra'         => $id_compra,
                'cantidad'          => $recibir,
                'cantidad_restante' => $recibir,
                'precio_costo'      => (float)$f['precio_costo'],
                'fecha_vencimiento' => $venc,
            ]);

            $db->execute("UPDATE detalle_compras SET cantidad_recibida = cantidad_recibida + ? WHERE id_detalle = ?", [$recibir, $id_detalle]);
            $db->execute("UPDATE productos SET stock_actual = stock_actual + ? WHERE id_producto = ?", [$recibir, (int)$f['id_producto']]);
            if ($id_proveedor) {
                $db->execute("UPDATE productos SET id_proveedor = ? WHERE id_producto = ? AND (id_proveedor IS NULL OR id_proveedor = 0)", [$id_proveedor, (int)$f['id_producto']]);
            }

            $db->insert('detalle_movimientos', [
                'id_movimiento'   => $mov_id,
                'id_producto'     => (int)$f['id_producto'],
                'cantidad'        => $recibir,
                'precio_unitario' => (float)$f['precio_costo'],
            ]);

            if (((int)$f['cantidad_recibida'] + $recibir) < (int)$f['cantidad']) {
                $faltante = true;
            }
            $total_productos++;
            $total_unidades += $recibir;
        }

        $db->execute("UPDATE compras SET estado_recepcion = ? WHERE id_compra = ?", [$faltante ? 'Parcial' : 'Completa', $id_compra]);
        registrarAuditoria('crear', "Recepción de mercancía (factura {$compra['nro_factura']}, $total_productos producto(s), $total_unidades und(s))");
        $db->commit();

        $msg = "RECEPCIÓN REGISTRADA: factura {$compra['nro_factura']}, $total_productos producto(s), $total_unidades und(s). ";
        $msg .= $faltante ? 'RECEPCIÓN PARCIAL.' : 'MERCADERÍA RECIBIDA POR COMPLETO.';
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => $msg];
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR AL REGISTRAR LA RECEPCIÓN. VERIFICA LOS DATOS E INTENTA DE NUEVO.'];
    }
    header('Location: recepcion.php');
    exit;
}

// ==========================================
// OBTENER DATOS
// ==========================================

// Compras pendientes de recepción (Pendiente o Parcial)
$compras_pendientes = $db->fetchAll("
    SELECT c.id_compra, c.nro_factura, c.nro_control, c.fecha_compra, c.condiciones_pago, c.estado_recepcion, c.total,
           pr.nombre_empresa AS proveedor,
           COUNT(dc.id_detalle) AS num_items,
           SUM(dc.cantidad - dc.cantidad_recibida) AS unidades_pend,
           SUM(CASE WHEN (dc.cantidad - dc.cantidad_recibida) > 0 THEN 1 ELSE 0 END) AS items_pend
    FROM compras c
    LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
    LEFT JOIN detalle_compras dc ON c.id_compra = dc.id_compra
    WHERE c.status = 'Activa' AND c.estado_recepcion != 'Completa'
    GROUP BY c.id_compra
    ORDER BY c.fecha_compra ASC, c.id_compra ASC
");

// Ítems pendientes por compra (para el modal de recepción)
$items_pendientes = $db->fetchAll("
    SELECT dc.id_compra, dc.id_detalle, dc.id_producto, dc.cantidad, dc.cantidad_recibida,
           dc.precio_costo, dc.fecha_vencimiento, p.sku, p.nombre_producto
    FROM detalle_compras dc
    JOIN productos p ON dc.id_producto = p.id_producto
    JOIN compras c ON dc.id_compra = c.id_compra
    WHERE c.status = 'Activa' AND c.estado_recepcion != 'Completa' AND (dc.cantidad - dc.cantidad_recibida) > 0
    ORDER BY c.id_compra, dc.id_detalle
");

$datos_recepcion = [];
foreach ($compras_pendientes as $cp) {
    $datos_recepcion[$cp['id_compra']] = [
        'nro_factura'  => $cp['nro_factura'],
        'proveedor'    => $cp['proveedor'] ?? 'S/P',
        'condiciones'  => $cp['condiciones_pago'] ?? 'Contado',
        'items'        => [],
    ];
}
foreach ($items_pendientes as $it) {
    $cid = (int)$it['id_compra'];
    if (!isset($datos_recepcion[$cid])) continue;
    $datos_recepcion[$cid]['items'][] = [
        'id_detalle' => (int)$it['id_detalle'],
        'id_producto' => (int)$it['id_producto'],
        'nombre'     => $it['nombre_producto'],
        'sku'        => $it['sku'],
        'cantidad'   => (int)$it['cantidad'],
        'recibida'   => (int)$it['cantidad_recibida'],
        'restante'   => (int)$it['cantidad'] - (int)$it['cantidad_recibida'],
        'precio'     => (float)$it['precio_costo'],
        'vence'      => $it['fecha_vencimiento'],
    ];
}

// Últimas recepciones registradas
$recepciones = $db->fetchAll("
    SELECT m.id_movimiento, m.fecha_movimiento, u.usuario AS operador,
           c.nro_factura, pr.nombre_empresa AS proveedor,
           (SELECT COUNT(*) FROM detalle_movimientos dm WHERE dm.id_movimiento = m.id_movimiento) AS num_items,
           (SELECT COALESCE(SUM(dm.cantidad),0) FROM detalle_movimientos dm WHERE dm.id_movimiento = m.id_movimiento) AS unidades
    FROM movimientos m
    JOIN usuarios u ON m.id_usuario = u.id_usuario
    LEFT JOIN compras c ON m.tipo_referencia = 'compra' AND m.id_referencia = c.id_compra
    LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
    WHERE m.tipo_referencia = 'compra' AND m.tipo = 'Entrada' AND m.status = 'Activo'
    ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC
    LIMIT 20
");

$total_por_recibir = count($compras_pendientes);
$unidades_por_recibir = 0;
foreach ($compras_pendientes as $cp) {
    $unidades_por_recibir += (int)$cp['unidades_pend'];
}
$recepciones_hoy = (int)($db->fetchOne("SELECT COUNT(*) AS n FROM movimientos WHERE tipo_referencia = 'compra' AND tipo = 'Entrada' AND status = 'Activo' AND fecha_movimiento >= CURDATE()")['n'] ?? 0);

$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recepción de Mercancía | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>
        <link rel="stylesheet" href="../assets/modules/recepcion/recepcion.css?v=7">
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4 pagina-recepcion">

            <!-- Encabezado -->
            <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 header-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="rec-header-icon">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                    <div>
                        <h1 class="font-brand fw-bold m-0" style="font-size:2rem;letter-spacing:-1px; color: var(--jv-text-primary);">RECEPCIÓN</h1>
                        <p class="text-secondary fw-bold text-uppercase m-0" style="font-size:.95rem;">Ingreso de Mercancía al Inventario</p>
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
                                <th class="text-center" style="width:12%;">Condiciones</th>
                                <th class="text-center" style="width:7%;">Items</th>
                                <th class="text-center" style="width:8%;">Unds</th>
                                <th class="text-center" style="width:11%;">Estado</th>
                                <th style="width:8%;">Fecha</th>
                                <th class="text-center" style="width:150px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPendientes">
                            <?php if (count($compras_pendientes) > 0): foreach ($compras_pendientes as $row): ?>
                                    <tr>
                                        <td style="vertical-align:middle;text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura']); ?></span></td>
                                        <td style="color:var(--jv-text-muted);font-weight:600;"><?php echo htmlspecialchars($row['nro_control'] ?: '-'); ?></td>
                                        <td class="td-proveedor text-uppercase fw-bold" data-tooltip="<?php echo htmlspecialchars($row['proveedor'] ?? 'S/P'); ?>"><?php echo htmlspecialchars($row['proveedor'] ?? 'S/P'); ?></td>
                                        <td class="text-center"><span class="badge-jv <?php echo ($row['condiciones_pago'] ?? 'Contado') === 'Contado' ? 'badge-success' : 'badge-warning'; ?>"><i class="<?php echo ($row['condiciones_pago'] ?? 'Contado') === 'Contado' ? 'bi bi-cash-stack' : 'bi bi-calendar-check'; ?> me-1"></i><?php echo $row['condiciones_pago'] ?? 'Contado'; ?></span></td>
                                        <td class="text-center"><span class="cant-badge"><?php echo (int)$row['items_pend']; ?></span></td>
                                        <td class="text-center fw-bold"><?php echo (int)$row['unidades_pend']; ?></td>
                                        <td class="text-center">
                                            <?php $est = $row['estado_recepcion']; ?>
                                            <span class="badge-jv <?php echo $est === 'Parcial' ? 'badge-info' : 'badge-warning'; ?>"><i class="bi <?php echo $est === 'Parcial' ? 'bi-arrow-repeat' : 'bi-hourglass-split'; ?> me-1"></i><?php echo $est; ?></span>
                                        </td>
                                        <td class="fecha-cell"><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn-jv-primary btn-recibir w-100" style="border:none;padding:10px 12px;font-size:.9rem;font-weight:700;border-radius:8px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" onclick="abrirRecepcion(<?php echo $row['id_compra']; ?>)">
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
                                <th style="width:32%;">Proveedor</th>
                                <th class="text-center" style="width:12%;">Productos</th>
                                <th class="text-center" style="width:12%;">Unidades</th>
                                <th style="width:16%;">Operador</th>
                            </tr>
                        </thead>
                        <tbody id="tablaRecepciones">
                            <?php if (count($recepciones) > 0): foreach ($recepciones as $r): ?>
                                    <tr>
                                        <td class="fecha-cell"><?php echo date('d/m/Y H:i', strtotime($r['fecha_movimiento'])); ?></td>
                                        <td style="text-align:center;"><span class="codigo-badge"><?php echo htmlspecialchars($r['nro_factura'] ?? '-'); ?></span></td>
                                        <td class="td-proveedor text-uppercase fw-bold" data-tooltip="<?php echo htmlspecialchars($r['proveedor'] ?? 'S/P'); ?>"><?php echo htmlspecialchars($r['proveedor'] ?? 'S/P'); ?></td>
                                        <td class="text-center"><span class="cant-badge">+<?php echo (int)$r['num_items']; ?></span></td>
                                        <td class="text-center fw-bold text-success">+<?php echo (int)$r['unidades']; ?></td>
                                        <td style="color:var(--jv-text-muted);"><?php echo htmlspecialchars($r['operador'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="6">
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
        </div>
    </div>

    <!-- Modal: Recibir compra -->
    <div class="modal fade" id="modalRecepcion" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <form method="POST" id="formRecepcion">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
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
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">FACTURA</label>
                                    <input type="text" class="input-jv" id="recFactura" readonly disabled style="color:var(--jv-text-muted);font-weight:700;">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">PROVEEDOR</label>
                                    <input type="text" class="input-jv" id="recProveedor" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">CONDICIONES</label>
                                    <input type="text" class="input-jv" id="recCondiciones" readonly disabled style="color:var(--jv-text-muted);">
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
                                        <tr><td colspan="7" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.85rem;">Seleccione una compra para recibir</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
                        <button type="button" class="btn btn-jv-danger" style="padding:12px 28px;font-size:1rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="button" class="btn btn-jv-success" id="btnRecibir" style="padding:12px 28px;font-size:1rem;" onclick="return confirmarRecepcion(this)"><i class="bi bi-check-lg me-1"></i> Registrar Recepción</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        window.JV_CONFIG = { c0: '<?php echo $csrf_token; ?>' };
        window.RECEPCION_DATOS = <?php echo json_encode($datos_recepcion); ?>;
    </script>
    <script src="../assets/modules/recepcion/recepcion.js?v=2"></script>
</body>

</html>
