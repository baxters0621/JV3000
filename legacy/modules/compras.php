<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();

$esAdmin = Security::esAdmin();
$csrf_token = Security::generateToken();
$iva_pct = (float)getConfig('iva_porcentaje', '16');

// ==========================================
// PROCESAR ACCIONES POST
// ==========================================

// Registrar compra (factura del proveedor + comprobante de pago). No mueve stock.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_compra'])) {
    $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
    if ($id_proveedor <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'SELECCIONE UN PROVEEDOR.'];
        header('Location: compras.php');
        exit;
    }
    $id_solicitud = intval($_POST['id_solicitud'] ?? 0);

    // Número de factura: manual (la factura es del proveedor), única por proveedor
    $nro_factura = trim($_POST['nro_factura'] ?? '');
    if ($nro_factura === '') {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL NÚMERO DE FACTURA ES OBLIGATORIO.'];
        header('Location: compras.php');
        exit;
    }
    if ($db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = ? AND status = 'Activa'", [$id_proveedor, $nro_factura])) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ESA FACTURA YA ESTÁ REGISTRADA PARA EL PROVEEDOR.'];
        header('Location: compras.php');
        exit;
    }

    // Número de control: manual y opcional (solo se valida el formato si se llena)
    $nro_control = trim($_POST['nro_control'] ?? '');
    if ($nro_control !== '' && !preg_match('/^\d{2}-\d{8}$/', $nro_control)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'NRO. CONTROL INVÁLIDO. Formato: 00-00000000'];
        header('Location: compras.php');
        exit;
    }

    $fecha_compra = date('Y-m-d H:i:s');
    $observaciones = trim($_POST['observaciones'] ?? '');

    $prov = $db->fetchOne("SELECT condiciones_pago, dias_credito, limite_credito, rif FROM proveedores WHERE id_proveedor = ?", [$id_proveedor]);
    if (!$prov) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'PROVEEDOR INVÁLIDO.'];
        header('Location: compras.php');
        exit;
    }
    if (!validarRIF(normalizarDocumento($prov['rif'] ?? ''))) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL PROVEEDOR SELECCIONADO NO TIENE UN RIF VÁLIDO. CORRÍJALO EN PROVEEDORES.'];
        header('Location: compras.php');
        exit;
    }
    $condiciones_pago = $prov['condiciones_pago'] ?? 'Contado';
    $dias_credito = intval($prov['dias_credito'] ?? 0);

    // Ítems de la factura
    $productos_raw = json_decode($_POST['productos_data'] ?? '[]', true);
    $productos = is_array($productos_raw) ? $productos_raw : [];
    if (count($productos) > 200) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'MÁXIMO 200 PRODUCTOS POR FACTURA.'];
        header('Location: compras.php');
        exit;
    }

    $items_validos = [];
    $subtotal = 0;
    foreach ($productos as $prod) {
        $id_producto = intval($prod['id'] ?? 0);
        $cantidad = intval($prod['cantidad'] ?? 0);
        $precio_costo = round((float)($prod['precio'] ?? 0), 2);
        if ($cantidad < 1 || $cantidad > 999999) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'CANTIDAD INVÁLIDA POR PRODUCTO. RANGO: 1 A 999,999.'];
            header('Location: compras.php');
            exit;
        }
        if ($precio_costo < 0 || $precio_costo > 99999999.99) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => "PRECIO DE COSTO INVÁLIDO PARA PRODUCTO #$id_producto. RANGO: 0 A 99,999,999.99."];
            header('Location: compras.php');
            exit;
        }
        if ($id_producto <= 0) continue;
        if (!$db->fetchOne("SELECT id_producto FROM productos WHERE id_producto = ?", [$id_producto])) continue;
        $lote_venc = null;
        if (!empty($prod['fecha_vencimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($prod['fecha_vencimiento']))) {
            $lote_venc = trim($prod['fecha_vencimiento']);
        }
        $items_validos[] = ['id' => $id_producto, 'cantidad' => $cantidad, 'precio' => $precio_costo, 'fecha_vencimiento' => $lote_venc];
        $subtotal += $cantidad * $precio_costo;
    }
    if (empty($items_validos)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'DEBE AGREGAR AL MENOS UN PRODUCTO VÁLIDO.'];
        header('Location: compras.php');
        exit;
    }

    $subtotal = round($subtotal, 2);
    $iva = round($subtotal * $iva_pct / 100, 2);
    $total = round($subtotal + $iva, 2);

    // Validar límite de crédito
    if ($condiciones_pago === 'Credito') {
        $limite = (float)($prov['limite_credito'] ?? 0);
        if ($limite > 0) {
            $usado = (float)$db->fetchOne("SELECT COALESCE(SUM(total),0) as t FROM compras WHERE id_proveedor = ? AND status = 'Activa' AND condiciones_pago = 'Credito'", [$id_proveedor])['t'];
            if (($usado + $total) > $limite) {
                $disponible = $limite - $usado;
                $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => "CRÉDITO INSUFICIENTE. Límite: \$" . number_format($limite, 2) . ", usado: \$" . number_format($usado, 2) . ", disponible: \$" . number_format(max(0, $disponible), 2) . "."];
                header('Location: compras.php');
                exit;
            }
        }
    }

    // Comprobante de pago
    $metodo_pago = in_array(trim($_POST['metodo_pago'] ?? ''), ['Efectivo', 'Transferencia', 'Cheque', 'Otro']) ? trim($_POST['metodo_pago']) : 'Efectivo';
    $monto_pago = round((float)($_POST['monto_pago'] ?? 0), 2);
    if ($monto_pago < 0) $monto_pago = 0;
    if ($monto_pago > 99999999.99) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'MONTO DE PAGO INVÁLIDO. MÁXIMO 99,999,999.99.'];
        header('Location: compras.php');
        exit;
    }
    $status_pago = $monto_pago >= $total ? 'Pagada' : 'Pendiente';
    $fecha_pago = date('Y-m-d H:i:s');

    $db->begin();
    try {
        $id_usuario_sesion = intval($_SESSION['id_usuario'] ?? 0);

        $compra_id = $db->insert('compras', [
            'nro_factura'       => $nro_factura,
            'id_proveedor'      => $id_proveedor,
            'id_usuario'        => $id_usuario_sesion,
            'fecha_compra'      => $fecha_compra,
            'nro_control'       => $nro_control !== '' ? $nro_control : null,
            'condiciones_pago'  => $condiciones_pago,
            'dias_plazo'        => $dias_credito,
            'subtotal'          => $subtotal,
            'iva'               => $iva,
            'total'             => $total,
            'status'            => 'Activa',
            'tipo_entrada'      => 'Compra a proveedor',
            'observaciones'     => $observaciones,
            'status_pago'       => $status_pago,
            'monto_pago'        => $monto_pago,
            'fecha_pago'        => $fecha_pago,
            'metodo_pago'       => $metodo_pago,
            'estado_recepcion'  => 'Pendiente',
        ]);

        foreach ($items_validos as $it) {
            $db->insert('detalle_compras', [
                'id_compra'         => $compra_id,
                'id_producto'       => $it['id'],
                'cantidad'          => $it['cantidad'],
                'precio_costo'      => $it['precio'],
                'cantidad_recibida' => 0,
                'fecha_vencimiento' => $it['fecha_vencimiento'],
            ]);
        }

        registrarAuditoria('crear', "Compra registrada (factura $nro_factura, " . count($items_validos) . " producto(s))");

        if ($id_solicitud > 0) {
            $db->execute("UPDATE solicitudes_compra SET estado = 'Atendida', id_compra = ?, fecha_atendida = NOW() WHERE id_solicitud = ? AND estado = 'Pendiente'", [$compra_id, $id_solicitud]);
        }

        $db->commit();

        $msg = "COMPRA REGISTRADA: factura $nro_factura, " . count($items_validos) . " producto(s). ";
        $msg .= $status_pago === 'Pagada' ? 'COMPROBANTE DE PAGO REGISTRADO.' : 'PAGO PENDIENTE (' . number_format($monto_pago, 2) . ' DE ' . number_format($total, 2) . ').';
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => $msg];
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR AL REGISTRAR LA COMPRA. VERIFICA LOS DATOS E INTENTA DE NUEVO.'];
    }
    header('Location: compras.php');
    exit;
}

// Anular compra (solo si no hay mercancía recibida en inventario)
if (isset($_POST['eliminar']) && $esAdmin) {
    $id_compra = intval($_POST['eliminar']);
    $compra = $db->fetchOne("SELECT nro_factura, status FROM compras WHERE id_compra = ?", [$id_compra]);
    if (!$compra || $compra['status'] === 'Anulada') {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'LA COMPRA NO EXISTE O YA FUE ANULADA.'];
        header('Location: compras.php');
        exit;
    }
    $recibida = (int)($db->fetchOne("SELECT COUNT(*) AS n FROM lotes WHERE id_compra = ?", [$id_compra])['n'] ?? 0);
    if ($recibida > 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'NO SE PUEDE ANULAR: la compra ya tiene mercancía recibida en inventario.'];
        header('Location: compras.php');
        exit;
    }
    $db->execute("UPDATE compras SET status = 'Anulada' WHERE id_compra = ?", [$id_compra]);
    registrarAuditoria('anular', "Compra #$id_compra (factura {$compra['nro_factura']}) anulada");
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'COMPRA ANULADA.'];
    header('Location: compras.php');
    exit;
}

// ==========================================
// OBTENER DATOS
// ==========================================
$filtro_proveedor = intval($_GET['filtro_proveedor'] ?? 0);
$filtro_pago = in_array($_GET['filtro_pago'] ?? '', ['Pendiente', 'Pagada']) ? $_GET['filtro_pago'] : '';

// Solicitud de compra a atender (prefill del formulario)
$atender_solicitud = intval($_GET['atender_solicitud'] ?? 0);
$solicitud_prefill = null;
if ($atender_solicitud > 0) {
    $sol = $db->fetchOne("SELECT id_solicitud, motivo, estado FROM solicitudes_compra WHERE id_solicitud = ?", [$atender_solicitud]);
    if ($sol && $sol['estado'] === 'Pendiente') {
        $det = $db->fetchAll("SELECT d.id_producto, d.cantidad_solicitada, p.sku, p.nombre_producto, p.precio_costo
                              FROM detalle_solicitud_compra d
                              JOIN productos p ON d.id_producto = p.id_producto
                              WHERE d.id_solicitud = ?", [$atender_solicitud]);
        if (!empty($det)) {
            $solicitud_prefill = [
                'id_solicitud' => (int)$sol['id_solicitud'],
                'motivo'       => $sol['motivo'],
                'items'        => array_map(function ($d) {
                    return [
                        'id'          => (int)$d['id_producto'],
                        'nombre'      => $d['nombre_producto'],
                        'cantidad'    => (int)$d['cantidad_solicitada'],
                        'precio'      => (float)$d['precio_costo'],
                    ];
                }, $det),
            ];
        }
    }
}

$sql_compras = "
    SELECT c.*,
           GROUP_CONCAT(DISTINCT p.nombre_producto SEPARATOR ', ') as productos_list,
           SUM(dc.cantidad) as total_cantidad,
           COUNT(dc.id_detalle) as num_productos,
           pr.nombre_empresa as proveedor
    FROM compras c
    LEFT JOIN detalle_compras dc ON c.id_compra = dc.id_compra
    LEFT JOIN productos p ON dc.id_producto = p.id_producto
    LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
    WHERE c.status = 'Activa'
";
$params = [];
if ($filtro_proveedor > 0) {
    $sql_compras .= " AND c.id_proveedor = ?";
    $params[] = $filtro_proveedor;
}
if ($filtro_pago !== '') {
    $sql_compras .= " AND c.status_pago = ?";
    $params[] = $filtro_pago;
}
$sql_compras .= " GROUP BY c.id_compra ORDER BY c.fecha_compra DESC, c.id_compra DESC LIMIT 100";
$compras = $db->fetchAll($sql_compras, $params);

$proveedores = $db->fetchAll("SELECT id_proveedor, nombre_empresa, rif, condiciones_pago, dias_credito, limite_credito FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa");

$credito_usado = [];
$rows_used = $db->fetchAll("SELECT id_proveedor, COALESCE(SUM(total),0) as usado FROM compras WHERE status = 'Activa' AND condiciones_pago = 'Credito' AND id_proveedor IS NOT NULL GROUP BY id_proveedor");
foreach ($rows_used as $r) {
    $credito_usado[(int)$r['id_proveedor']] = (float)$r['usado'];
}

$total_compras = (int)$db->fetchOne("SELECT COUNT(*) as t FROM compras WHERE status = 'Activa'")['t'];
$por_pagar = (int)$db->fetchOne("SELECT COUNT(*) as t FROM compras WHERE status = 'Activa' AND status_pago = 'Pendiente'")['t'];
$inv_mes_row = $db->fetchOne("SELECT COALESCE(SUM(c.total),0) as t FROM compras c WHERE c.fecha_compra >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND c.fecha_compra < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH,'%Y-%m-01') AND c.status = 'Activa'");
$invertido_mes = $inv_mes_row['t'] ?? 0;

$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!-- HEAD Y ESTILOS HTML -->
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>
        <link rel="stylesheet" href="../assets/modules/compras/compras.css?v=8">
</head>
<!-- BODY HTML -->

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4 pagina-compras">

            <!-- Encabezado -->
            <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 header-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="com-header-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <h1 class="module-title">COMPRAS</h1>
                        <p class="module-subtitle">Facturas de Proveedores</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-jv-success pulse-jv module-action-btn" data-bs-toggle="modal" data-bs-target="#modalCompra">
                        <i class="bi bi-plus-lg me-1"></i>NUEVA COMPRA
                    </button>
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
                    <div class="widget-card" style="border-left:4px solid var(--jv-success);">
                        <div class="widget-icon" style="background:rgba(22,163,74,0.12);color:var(--jv-success);">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <div class="widget-label">Total Compras</div>
                            <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo $total_compras; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="widget-card" style="border-left:4px solid var(--jv-warning);">
                        <div class="widget-icon" style="background:rgba(245,158,11,0.12);color:var(--jv-warning);">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <div class="widget-label">Por Pagar</div>
                            <div class="widget-value" style="color: var(--jv-text-primary);"><?php echo $por_pagar; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="widget-card" style="border-left:4px solid var(--jv-info);">
                        <div class="widget-icon" style="background:rgba(14,165,233,0.12);color:var(--jv-info);">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div>
                            <div class="widget-label">Invertido (Mes)</div>
                            <div class="widget-value" style="color: var(--jv-text-primary);">$<?php echo number_format($invertido_mes, 0); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de compras -->
            <div class="card-jv card-jv-table p-0">
                <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper flex-wrap">
                    <i class="bi bi-search me-1" style="font-size:1.1rem;color:var(--jv-orange);"></i>
                    <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por factura, control, proveedor, productos, estado..." id="buscar" onkeyup="filtrar()" style="box-shadow:none;font-size:1rem;padding:8px 6px;max-width:340px;">
                    <select class="input-jv ms-auto" id="filtroPago" onchange="window.location='compras.php?filtro_pago='+this.value" style="width:auto;padding:6px 10px;font-size:.95rem;">
                        <option value="">Todos los pagos</option>
                        <option value="Pendiente" <?php echo $filtro_pago === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="Pagada" <?php echo $filtro_pago === 'Pagada' ? 'selected' : ''; ?>>Pagada</option>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th style="width:8%;">Factura</th>
                                <th style="width:10%;">N° Control</th>
                                <th style="width:12%;">Proveedor</th>
                                <th style="width:14%;">Detalle</th>
                                <th class="text-center" style="width:5%;">Cant</th>
                                <th style="width:8%;">Subtotal</th>
                                <th style="width:7%;">IVA</th>
                                <th style="width:9%;">Total</th>
                                <th class="text-center" style="width:9%;">Condición</th>
                                <th class="text-center" style="width:8%;">Pago</th>
                                <th style="width:7%;">Fecha</th>
                                <th class="text-center" style="width:120px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaEntradas">
                            <?php if (count($compras) > 0): foreach ($compras as $row): ?>
                                    <tr>
                                        <td class="text-center"><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura'] ?: '-'); ?></span></td>
                                        <td class="nro-control-cell" data-tooltip="<?php echo htmlspecialchars($row['nro_control'] ?: '-'); ?>"><?php echo htmlspecialchars($row['nro_control'] ?: '-'); ?></td>
                                        <td class="text-uppercase fw-bold proveedor-cell" data-tooltip="<?php echo htmlspecialchars($row['proveedor'] ?? 'S/P'); ?>"><?php echo htmlspecialchars($row['proveedor'] ?? 'S/P'); ?></td>
                                        <td class="td-prod" data-tooltip="<?php echo htmlspecialchars($row['productos_list'] ?? ''); ?>"><?php echo htmlspecialchars($row['productos_list'] ?? '-'); ?></td>
                                        <td class="text-center"><span class="cant-badge">+<?php echo $row['total_cantidad']; ?></span></td>
                                        <td style="font-weight:600;">$<?php echo number_format($row['subtotal'] ?? 0, 2); ?></td>
                                        <td style="font-weight:600;">$<?php echo number_format($row['iva'] ?? 0, 2); ?></td>
                                        <td class="fw-bold text-success">$<?php echo number_format($row['total'], 2); ?></td>
                                        <td class="text-center"><span class="badge-jv <?php echo ($row['condiciones_pago'] ?? 'Contado') === 'Contado' ? 'badge-success' : 'badge-warning'; ?>"><i class="<?php echo ($row['condiciones_pago'] ?? 'Contado') === 'Contado' ? 'bi bi-cash-stack' : 'bi bi-calendar-check'; ?> me-1"></i><?php echo $row['condiciones_pago'] ?? 'Contado'; ?></span></td>
                                        <td class="text-center">
                                            <?php $st = $row['status_pago'] ?? 'Pendiente'; ?>
                                            <span class="badge-jv <?php echo $st === 'Pagada' ? 'badge-success' : 'badge-warning'; ?>"><i class="bi <?php echo $st === 'Pagada' ? 'bi-check-circle' : 'bi-hourglass-split'; ?> me-1"></i><?php echo $st; ?></span>
                                        </td>
                                        <td class="fecha-cell"><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></td>
                                        <td class="text-center">
                                            <?php if ($esAdmin): ?>
                                                <button type="button" class="btn-action" onclick="confirmarEliminar(<?php echo $row['id_compra']; ?>)"><i class="bi bi-trash"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="12">
                                        <div class="estado-vacio">
                                            <i class="bi bi-cart-x"></i>
                                            <span>No hay compras registradas</span>
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

    <!-- Modal: Registrar compra -->
    <div class="modal fade" id="modalCompra" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <form method="POST" id="formCompra">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_compra" value="registrar">
                    <input type="hidden" name="productos_data" id="productosData">
                    <?php if ($solicitud_prefill): ?>
                        <input type="hidden" name="id_solicitud" id="idSolicitud" value="<?php echo (int)$solicitud_prefill['id_solicitud']; ?>">
                        <div class="alert-jv alert-jv-info mb-3 px-3 py-2" style="margin:16px 20px 0;">
                            <i class="bi bi-cart-check me-2"></i>
                            <strong>SOLICITUD #<?php echo (int)$solicitud_prefill['id_solicitud']; ?></strong> — <?php echo htmlspecialchars($solicitud_prefill['motivo'] ?? 'Solicitud de reposición'); ?>. Los productos ya fueron precargados.
                        </div>
                    <?php endif; ?>

                    <div class="p-3" style="border-bottom:1px solid var(--jv-border);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 font-brand" style="color:var(--jv-navy);font-size:1.3rem;letter-spacing:-.5px;"><i class="bi bi-cart-plus me-2"></i>REGISTRAR COMPRA</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    </div>

                    <div class="p-3" style="padding:16px 20px;">
                        <div class="comp-proveedor-section section-bg">
                            <div class="section-label"><i class="bi bi-building me-1"></i>Proveedor</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary mb-1">PROVEEDOR *</label>
                                    <select name="id_proveedor" class="input-jv" id="selProveedor" required>
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($proveedores as $p): ?>
                                            <option value="<?php echo $p['id_proveedor']; ?>" data-condicion="<?php echo $p['condiciones_pago']; ?>" data-dias="<?php echo $p['dias_credito']; ?>" data-limite="<?php echo (float)($p['limite_credito'] ?? 0); ?>" data-usado="<?php echo $credito_usado[(int)$p['id_proveedor']] ?? 0; ?>" data-rif="<?php echo htmlspecialchars($p['rif']); ?>">
                                                <?php echo htmlspecialchars($p['nombre_empresa']); ?> (<?php echo $p['rif']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">RIF</label>
                                    <input type="text" class="input-jv" id="displayRif" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">CONDICIÓN</label>
                                    <input type="text" class="input-jv" id="displayCondicion" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">DÍAS</label>
                                    <input type="text" class="input-jv" id="displayDias" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                            </div>
                            <div class="row g-2 mt-1" id="rowCredito" style="display:none;">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">LÍMITE CRÉDITO</label>
                                    <input type="text" class="input-jv" id="displayLimite" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">CRÉDITO USADO</label>
                                    <input type="text" class="input-jv" id="displayUsado" value="-" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">DISPONIBLE</label>
                                    <input type="text" class="input-jv" id="displayDisponible" value="-" readonly disabled style="color:var(--jv-text-muted);font-weight:700;">
                                </div>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-receipt me-1"></i>Factura del Proveedor</div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">NRO. FACTURA (PROVEEDOR) *</label>
                                    <input type="text" name="nro_factura" class="input-jv" placeholder="Ej: 001254" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">NRO. CONTROL <span class="fw-normal">(opcional)</span></label>
                                    <input type="text" name="nro_control" class="input-jv" value="" placeholder="00-00000000" oninput="var v=this.value.replace(/[^0-9]/g,'');if(v.length>10)v=v.slice(0,10);if(v.length>2)v=v.slice(0,2)+'-'+v.slice(2);this.value=v" maxlength="11">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">FECHA</label>
                                    <input type="date" class="input-jv" value="<?php echo date('Y-m-d'); ?>" disabled>
                                    <input type="hidden" name="fecha_compra" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-cash-coin me-1"></i>Comprobante de Pago</div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">MÉTODO *</label>
                                    <select name="metodo_pago" class="input-jv" id="selMetodo">
                                        <option value="">Seleccionar...</option>
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Transferencia">Transferencia</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">MONTO PAGADO $</label>
                                    <input type="text" inputmode="decimal" name="monto_pago" class="input-jv" id="montoPago" value="0.00" oninput="marcarMontoEditado();formatearPrecioCompra(this)">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">FECHA PAGO</label>
                                    <input type="date" class="input-jv" value="<?php echo date('Y-m-d'); ?>" disabled>
                                    <small class="text-muted d-block mt-1" style="font-size:.68rem;">Si el monto es menor al total, la factura queda PENDIENTE.</small>
                                </div>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-box-seam me-1"></i>Productos</div>

                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="small fw-bold text-secondary mb-1">Producto</label>
                                    <div class="com-toolbox">
                                        <input type="text" class="input-jv w-100" id="buscarProducto" placeholder="Buscar por nombre o SKU..." autocomplete="off">
                                        <input type="hidden" id="selProductoId">
                                        <input type="hidden" id="selProductoNombre">
                                        <div class="com-resultados" id="resultadosBusqueda"></div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-secondary mb-1">Cant</label>
                                    <input type="number" class="input-jv" id="inputCant" value="1" min="1" max="999999" oninput="if(this.value>999999)this.value=999999;if(this.value<1)this.value=1">
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-secondary mb-1">Precio $</label>
                                    <input type="text" inputmode="decimal" class="input-jv" id="inputPrecio" placeholder="0.00" oninput="formatearPrecioCompra(this)">
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-secondary mb-1">Vence <span class="text-muted fw-normal">(opcional)</span></label>
                                    <input type="date" class="input-jv" id="inputVencimiento">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn-jv-primary w-100" style="padding:14px;" onclick="agregarProducto()">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <div style="border:1px solid var(--jv-border);border-radius:8px;overflow:hidden;margin-top:10px;">
                                <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-card);">
                                    <thead>
                                        <tr style="background:var(--jv-navy);">
                                            <th style="padding:10px 8px;width:28px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">#</th>
                                            <th style="padding:10px 8px;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Producto</th>
                                            <th style="padding:10px 8px;width:55px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Cant</th>
                                            <th style="padding:10px 8px;width:90px;text-align:right;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Precio</th>
                                            <th style="padding:10px 8px;width:110px;text-align:center;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Vence</th>
                                            <th style="padding:10px 8px;width:90px;text-align:right;color:#fff;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Total</th>
                                            <th style="width:28px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productosBody">
                                        <tr id="filaVacia">
                                            <td colspan="7" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.95rem;border-bottom:1px solid var(--jv-border);">⬆ Busque un producto y presione + para agregarlo</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div style="display:flex;justify-content:flex-end;gap:24px;align-items:center;padding:12px 16px;margin-top:8px;background:var(--jv-bg-card);border:1px solid var(--jv-border);border-radius:8px;flex-wrap:wrap;">
                                <div>
                                    <span class="text-secondary" style="font-weight:700;font-size:1rem;">Productos</span>
                                    <span class="fw-bold ms-2" id="totalItems" style="color:var(--jv-navy);font-size:1.3rem;">0</span>
                                </div>
                                <div>
                                    <span class="text-secondary" style="font-weight:700;font-size:1rem;">Subtotal</span>
                                    <span class="fw-bold ms-2" id="totalSubtotal" style="color:var(--jv-text-primary);font-size:1.3rem;">$0.00</span>
                                </div>
                                <div>
                                    <span class="text-secondary" style="font-weight:700;font-size:1rem;">IVA (<?php echo $iva_pct; ?>%)</span>
                                    <span class="fw-bold ms-2" id="totalIva" style="color:var(--jv-text-primary);font-size:1.3rem;">$0.00</span>
                                </div>
                                <div>
                                    <span class="text-secondary" style="font-weight:700;font-size:1rem;">Total</span>
                                    <span class="fw-bold ms-2" id="totalCosto" style="color:var(--jv-warning);font-size:1.4rem;">$0.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="section-bg" style="margin-bottom:0;">
                            <div class="section-label"><i class="bi bi-chat-text me-1"></i>Observaciones</div>
                            <input type="text" name="observaciones" class="input-jv" placeholder="Notas opcionales...">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
                        <button type="button" class="btn btn-jv-danger" style="padding:12px 28px;font-size:1rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="submit" class="btn btn-jv-success module-action-btn" id="btnGuardar" disabled onclick="return validarFormulario(this)"><i class="bi bi-check-lg me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    window.JV_CONFIG = { c0: '<?php echo $csrf_token; ?>', c1: <?php echo (float)$iva_pct; ?> };
    window.COMPRAS_SOLICITUD = <?php echo $solicitud_prefill ? json_encode($solicitud_prefill) : 'null'; ?>;
</script>
    <script src="../assets/modules/compras/compras.js?v=3"></script>
    
</body>

</html>
