<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoVenta();
$csrf_token = Security::generateToken();

$iva_pct = getConfig('iva_porcentaje', '16');

// ==========================================
// MODO ALMACENAR (AJAX)
// ==========================================
if (isset($_GET['store'])) {
    header('Content-Type: application/json');

    $productos_data = $_POST['productos_data'] ?? '';
    $id_tipo_mov  = intval($_POST['id_tipo_mov'] ?? 0);
    $cliente      = mb_strtoupper(trim($_POST['cliente'] ?? ''));
    $rif_cliente  = mb_strtoupper(trim($_POST['rif_cliente'] ?? ''));
    $fecha_salida = $_POST['fecha_salida'] ?? date('Y-m-d');
    $nro_control  = generarControlNumero();
    $tn_row2 = $db->fetchOne("SELECT nombre FROM tipos_movimientos WHERE id_tipo_mov = ?", [$id_tipo_mov]);
    $tipo_nombre2 = $tn_row2['nombre'] ?? '';
    $n2 = mb_strtoupper(trim($tipo_nombre2));
    $grupo = $n2 === 'VENTA' ? 'venta' : ($n2 === 'REGALIAS' ? 'regalias' : 'merma');
    $causa_ajuste = $grupo === 'merma' ? trim($_POST['causa_ajuste'] ?? '') : '';
    $motivo_merma = trim($_POST['descripcion_motivo'] ?? '');
    $motivo_reg = trim($_POST['motivo_regalia'] ?? '');
    $obs_extra = trim($_POST['observaciones'] ?? '');
    $partes = [];
    if ($causa_ajuste) $partes[] = "Causa: $causa_ajuste";
    if ($motivo_merma) $partes[] = "Motivo: $motivo_merma";
    if ($motivo_reg) $partes[] = "Regalía: $motivo_reg";
    if ($obs_extra) $partes[] = $obs_extra;
    $observaciones = implode(' | ', $partes);
    $id_usuario   = $_SESSION['id_usuario'];
    $accion_salida = in_array($_POST['accion_salida'] ?? '', ['registrar', 'editar']) ? $_POST['accion_salida'] : 'registrar';
    $id_salida     = intval($_POST['id_salida'] ?? 0);

    // Parse productos: from JSON string or individual fields
    $productos = [];
    if (!empty($productos_data)) {
        $parsed = json_decode($productos_data, true);
        if (is_array($parsed)) $productos = $parsed;
    } else {
        $productos[] = [
            'id_producto' => intval($_POST['id_producto'] ?? 0),
            'cantidad'    => intval($_POST['cantidad'] ?? 0),
            'precio'      => floatval($_POST['precio_venta'] ?? 0),
        ];
    }

    if (empty($productos) || !$id_tipo_mov) {
        echo json_encode(['ok' => false, 'error' => 'DATOS INCOMPLETOS (FALTAN PRODUCTOS O TIPO).']);
        exit();
    }
    if ($grupo === 'venta') {
        if (empty($cliente)) {
            echo json_encode(['ok' => false, 'error' => 'CLIENTE OBLIGATORIO PARA VENTAS.']);
            exit();
        }
        if (empty($rif_cliente)) {
            echo json_encode(['ok' => false, 'error' => 'RIF OBLIGATORIO PARA VENTAS.']);
            exit();
        }
    }
    if ($grupo === 'regalias') {
        if (empty($cliente)) {
            echo json_encode(['ok' => false, 'error' => 'CLIENTE OBLIGATORIO PARA REGALÍAS.']);
            exit();
        }
        if (empty($motivo_reg)) {
            echo json_encode(['ok' => false, 'error' => 'MOTIVO OBLIGATORIO PARA REGALÍAS.']);
            exit();
        }
    }
    if ($grupo === 'merma') {
        if (empty($causa_ajuste)) {
            echo json_encode(['ok' => false, 'error' => 'CAUSA OBLIGATORIA PARA AJUSTES/MERMAS.']);
            exit();
        }
    }

    // Check for expired and stock availability
    foreach ($productos as $p) {
        $pid = intval($p['id_producto'] ?? 0);
        $cant = intval($p['cantidad'] ?? 0);
        if ($pid) {
            $pc = $db->fetchOne("SELECT stock_actual, fecha_vencimiento FROM productos WHERE id_producto = ?", [$pid]);
            if ($pc && $pc['fecha_vencimiento'] && $pc['fecha_vencimiento'] <= date('Y-m-d')) {
                echo json_encode(['ok' => false, 'error' => 'PRODUCTO VENCIDO. NO SE PUEDE VENDER.']);
                exit();
            }
            if ($pc && (int)$pc['stock_actual'] < $cant) {
                echo json_encode(['ok' => false, 'error' => "STOCK INSUFICIENTE. Disponible: {$pc['stock_actual']}, solicitado: $cant."]);
                exit();
            }
        }
    }

    // Handle REGALIAS (force price to 0 for all products)
    $tn_row = $db->fetchOne("SELECT nombre FROM tipos_movimientos WHERE id_tipo_mov = ?", [$id_tipo_mov]);
    $tipo_nombre = $tn_row['nombre'] ?? '';
    if (mb_strtoupper(trim($tipo_nombre)) === 'REGALIAS') {
        foreach ($productos as &$p) $p['precio'] = 0;
        unset($p);
    }

    $_SESSION['preview_data'] = [
        'productos_data'     => json_encode($productos),
        'cliente'            => $cliente,
        'rif_cliente'        => $rif_cliente ?: 'N/A',
        'nro_factura_manual' => 'PENDIENTE',
        'nro_control'        => $nro_control,
        'fecha_salida'       => $fecha_salida,
        'id_tipo_mov'        => $id_tipo_mov,
        'grupo'              => $grupo,
        'causa_ajuste'       => $causa_ajuste,
        'observaciones'      => $observaciones,
        'id_usuario'         => $id_usuario,
        'accion_salida'      => $accion_salida,
        'id_salida'          => $id_salida,
    ];

    echo json_encode(['ok' => true]);
    exit();
}

// ==========================================
// MODO REIMPRESIÓN (DESDE BD)
// ==========================================
$data = null;
$detalles = [];

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $data = $db->fetchOne("
        SELECT s.*, tm.nombre as tipo_nombre
        FROM salidas s
        LEFT JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
        WHERE s.id_salida = ?
    ", [$id]);
    if (!$data) {
        echo "<h2>NOTA DE ENTREGA NO ENCONTRADA</h2>";
        exit();
    }

    $detalles = $db->fetchAll("
        SELECT ds.*, p.nombre_producto, p.sku, p.precio_venta as precio_original, p.fecha_vencimiento
        FROM detalle_salidas ds
        JOIN productos p ON ds.id_producto = p.id_producto
        WHERE ds.id_salida = ?
    ", [$id]);
} else {
    $data = $_SESSION['preview_data'] ?? null;
    if (!$data) {
        echo "<h2>NO HAY DATOS DE PREVIEW</h2>";
        exit();
    }

    $productos_raw = [];
    if (isset($data['productos_data'])) {
        $productos_raw = json_decode($data['productos_data'], true) ?: [];
    } else {
        // Fallback for old single-product preview
        $productos_raw[] = [
            'id_producto' => intval($data['id_producto'] ?? 0),
            'cantidad'    => intval($data['cantidad'] ?? 0),
            'precio'      => floatval($data['precio_venta'] ?? 0),
        ];
    }

    foreach ($productos_raw as $p) {
        $pid = intval($p['id_producto'] ?? 0);
        $prod = $db->fetchOne("SELECT nombre_producto, sku, precio_venta, fecha_vencimiento FROM productos WHERE id_producto = ?", [$pid]);
        $detalles[] = [
            'id_producto'     => $pid,
            'cantidad'        => intval($p['cantidad'] ?? 0),
            'precio_venta'    => floatval($p['precio'] ?? 0),
            'precio_original' => floatval($prod['precio_venta'] ?? 0),
            'nombre_producto' => $prod['nombre_producto'] ?? '—',
            'sku'             => $prod['sku'] ?? '—',
            'fecha_vencimiento' => $prod['fecha_vencimiento'] ?? null,
        ];
    }

    $tn_row = $db->fetchOne("SELECT nombre FROM tipos_movimientos WHERE id_tipo_mov = ?", [(int)$data['id_tipo_mov']]);
    if ($tn_row) {
        $data['tipo_nombre'] = $tn_row['nombre'];
    }
}

// ==========================================
// ALERTA DE VENCIMIENTO (por cada producto)
// ==========================================
$alertas_venc = [];
foreach ($detalles as $det) {
    $vf = $det['fecha_vencimiento'] ?? null;
    if ($vf && $vf <= date('Y-m-d')) {
        $alertas_venc[] = ['tipo' => 'vencido', 'producto' => $det['nombre_producto'], 'fecha' => $vf];
    } elseif ($vf && $vf <= date('Y-m-d', strtotime('+7 days'))) {
        $alertas_venc[] = ['tipo' => 'proximo', 'producto' => $det['nombre_producto'], 'fecha' => $vf];
    }
}

// ==========================================
// LÓGICA DE DISEÑO
// ==========================================
$tipo_mov = strtoupper(trim($data['tipo_nombre'] ?? 'VENTA'));
$es_venta = $tipo_mov === 'VENTA';
$es_regalias = $tipo_mov === 'REGALIAS';
$es_merma = in_array($tipo_mov, ['MERMAS', 'DAÑOS']);

// Calcular totales desde detalles
$subtotal = 0;
foreach ($detalles as $det) {
    $subtotal += $det['cantidad'] * $det['precio_venta'];
}
$iva = $es_venta ? ($subtotal * ($iva_pct / 100)) : 0;
$total_neto = $subtotal + $iva;

// Datos de la empresa
$empresa = getConfig('empresa_nombre', 'JV3000');
$rif_emp  = getConfig('empresa_rif', 'J-00000000-0');
$tel_emp  = getConfig('empresa_telefono', '');
$dir_emp  = getConfig('empresa_direccion', '');
$email_emp = getConfig('empresa_email', '');

$badge_color = '#dc2626';
$badge_label = $tipo_mov;
if ($es_regalias) {
    $badge_color = '#f59e0b';
    $badge_label = 'REGALÍA';
}
if ($es_merma) $badge_color = '#64748b';

// Hora actual para el sello fiscal
$hora_actual = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Entrega #<?php echo $data['id_salida'] ?? 'PREVIEW'; ?> | <?php echo $empresa; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-icons.css">
    <style>
        /* === PRINT STYLES === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f1f5f9;
            padding: 30px;
        }

        .page {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            padding: 32px 36px;
        }

        /* ── Encabezado ── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 16px;
        }

        .doc-issuer h2 {
            font-family: system-ui, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -.5px;
        }

        .doc-issuer p {
            margin: 1px 0;
            font-size: .75rem;
            color: #475569;
            line-height: 1.4;
        }

        .doc-issuer .issuer-name {
            font-size: .95rem;
            font-weight: 700;
            color: #0f172a;
        }

        .doc-type {
            text-align: right;
        }

        .doc-type h1 {
            font-family: system-ui, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 2rem;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .doc-type .type-sub {
            font-size: .7rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        /* ── Numeración ── */
        .num-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 6px;
        }

        .num-item {
            font-size: .75rem;
            color: #475569;
        }

        .num-item strong {
            color: #0f172a;
            font-size: .85rem;
        }

        /* ── Info boxes ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
        }

        .info-box label {
            font-size: .6rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: block;
            margin-bottom: 2px;
        }

        .info-box .value {
            font-size: .82rem;
            font-weight: 600;
            color: #0f172a;
        }

        /* ── Tabla ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 16px;
        }

        table th {
            background: #0f172a;
            color: #fff;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 8px;
            text-align: left;
            border: none;
        }

        table th:last-child {
            text-align: right;
        }

        table th:nth-child(2) {
            text-align: center;
        }

        table th:nth-child(3) {
            text-align: center;
        }

        table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: .82rem;
            color: #0f172a;
        }

        table td:last-child {
            text-align: right;
            font-weight: 700;
        }

        table td:nth-child(2) {
            text-align: center;
        }

        table td:nth-child(3) {
            text-align: center;
        }

        /* ── Totales ── */
        .totals {
            margin-left: auto;
            width: 280px;
        }

        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: .82rem;
        }

        .totals .row.iva {
            color: #0891b2;
        }

        .totals .row.total {
            border-top: 2px solid #0f172a;
            margin-top: 5px;
            padding-top: 8px;
            font-weight: 800;
            font-size: 1rem;
            color: #dc2626;
        }

        .totals .row .label {
            font-weight: 600;
            color: #475569;
        }

        /* ── Base legal ── */
        .legal-box {
            margin-top: 16px;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: .6rem;
            color: #94a3b8;
            text-align: center;
            line-height: 1.5;
        }

        .obs-box {
            margin: 12px 0;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .obs-box label {
            font-size: .6rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: block;
            margin-bottom: 2px;
        }

        .obs-box p {
            font-size: .8rem;
            color: #475569;
            margin: 0;
        }

        /* ── Firmas ── */
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        .signatures .sig {
            width: 220px;
            text-align: center;
        }

        .signatures .sig .line {
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            margin-top: 36px;
            font-size: .7rem;
            color: #64748b;
        }

        .signatures .sig p {
            font-size: .75rem;
            color: #64748b;
            margin: 0 0 4px;
            font-weight: 600;
        }

        /* ── Botones ── */
        .buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
        }

        .buttons form {
            margin: 0;
        }

        .btn {
            padding: 12px 32px;
            border-radius: 10px;
            font-weight: 700;
            font-size: .85rem;
            border: none;
            cursor: pointer;
            transition: .2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #dc2626;
            color: #fff;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        .btn-primary:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .btn-outline {
            background: transparent;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-outline:hover {
            background: #f1f5f9;
        }

        /* ── Print ── */
        @media print {
            @page {
                margin: 10mm 8mm;
            }

            body {
                background: #fff;
                padding: 0;
            }

            .page {
                box-shadow: none;
                border-radius: 0;
                padding: 20px 22px;
                max-width: 100%;
            }

            .buttons {
                display: none !important;
            }

            .doc-header {
                padding-bottom: 10px;
                margin-bottom: 10px;
            }

            .doc-issuer h2 {
                font-size: 1.1rem;
            }

            .doc-issuer p {
                font-size: .65rem;
            }

            .doc-issuer .issuer-name {
                font-size: .8rem;
            }

            .doc-type h1 {
                font-size: 1.6rem;
            }

            .num-row {
                padding: 6px 10px;
                margin-bottom: 10px;
            }

            .num-item {
                font-size: .65rem;
            }

            .num-item strong {
                font-size: .75rem;
            }

            .info-grid {
                gap: 6px;
                margin-bottom: 10px;
            }

            .info-box {
                padding: 6px 10px;
            }

            .info-box label {
                font-size: .5rem;
            }

            .info-box .value {
                font-size: .7rem;
            }

            table {
                margin: 8px 0 10px;
            }

            table th {
                padding: 6px 6px;
                font-size: .55rem;
            }

            table td {
                padding: 6px 6px;
                font-size: .7rem;
            }

            .totals {
                width: 220px;
            }

            .totals .row {
                padding: 3px 0;
                font-size: .7rem;
            }

            .totals .row.total {
                font-size: .85rem;
                padding-top: 5px;
            }

            .obs-box {
                padding: 6px 10px;
                margin: 8px 0;
            }

            .obs-box p {
                font-size: .7rem;
            }

            .legal-box {
                font-size: .5rem;
                padding: 6px 10px;
            }

            .signatures {
                margin-top: 12px;
                padding-top: 10px;
            }

            .signatures .sig .line {
                margin-top: 24px;
                font-size: .6rem;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="doc-header">
            <div class="doc-issuer">
                <div class="issuer-name"><?php echo htmlspecialchars($empresa); ?></div>
                <p>RIF: <?php echo htmlspecialchars($rif_emp); ?></p>
                <p><?php echo htmlspecialchars($dir_emp ?: ' '); ?></p>
                <p>TLF: <?php echo htmlspecialchars($tel_emp ?: ' '); ?></p>
                <p>Correo: <?php echo htmlspecialchars($email_emp ?: ' '); ?></p>
            </div>
            <div class="doc-type">
                <h1>NOTA DE ENTREGA</h1>
                <div class="type-sub">Oficial</div>
            </div>
        </div>

        <div class="num-row">
            <span class="num-item"><strong>N° N/ENTREGA:</strong> <?php echo htmlspecialchars($data['nro_factura_manual'] ?? 'PENDIENTE'); ?></span>
            <span class="num-item"><strong>N° CONTROL:</strong> <?php echo htmlspecialchars($data['nro_control'] ?? '—'); ?></span>
            <span class="num-item"><strong>FECHA:</strong> <?php echo date('d/m/Y', strtotime($data['fecha_salida'])); ?></span>
            <span class="num-item"><strong>HORA:</strong> <?php echo $hora_actual; ?></span>
        </div>

        <?php foreach ($alertas_venc as $av): ?>
            <div style="padding:10px 14px;border-radius:8px;margin-bottom:6px;font-size:.75rem;font-weight:600;text-align:center;<?php echo $av['tipo'] === 'vencido' ? 'background:#fef2f2;color:#dc2626;border:1px solid #fecaca;' : 'background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;'; ?>">
                <?php echo $av['tipo'] === 'vencido' ? '⚠ VENCIDO' : '⚠ PRÓXIMO A VENCER'; ?>
                — <?php echo htmlspecialchars($av['producto']); ?> (<?php echo date('d/m/Y', strtotime($av['fecha'])); ?>)
            </div>
        <?php endforeach; ?>

        <?php if (!$es_merma): ?>
            <div class="info-grid">
                <div class="info-box">
                    <label>Cliente</label>
                    <div class="value"><?php echo htmlspecialchars($data['cliente'] ?? 'CONSUMIDOR FINAL'); ?></div>
                </div>
                <div class="info-box">
                    <label>RIF / Cédula</label>
                    <div class="value"><?php echo htmlspecialchars($data['rif_cliente'] ?? 'SIN IDENTIFICACIÓN'); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th style="width:8%;">Cant.</th>
                    <th style="width:52%;">Descripción</th>
                    <th style="width:18%;">P. Unit.</th>
                    <th style="width:22%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $det): ?>
                    <?php $fila_total = $det['cantidad'] * $det['precio_venta']; ?>
                    <tr>
                        <td><?php echo $det['cantidad']; ?></td>
                        <td><strong><?php echo htmlspecialchars($det['nombre_producto'] ?? ''); ?></strong><br><span style="font-size:.7rem;color:#94a3b8;">SKU: <?php echo htmlspecialchars($det['sku'] ?? ''); ?></span></td>
                        <td><?php if ($es_regalias && $det['precio_venta'] == 0 && ($det['precio_original'] ?? 0) > 0): ?><span style="text-decoration:line-through;color:#94a3b8;">$ <?php echo number_format($det['precio_original'], 2); ?></span> <span style="color:#22c55e;font-weight:700;">GRATIS</span><?php else: ?>$ <?php echo number_format($det['precio_venta'], 2); ?><?php endif; ?></td>
                        <td><?php if ($es_regalias && $det['precio_venta'] == 0 && ($det['precio_original'] ?? 0) > 0): ?><span style="text-decoration:line-through;color:#94a3b8;">$ <?php echo number_format($det['cantidad'] * $det['precio_original'], 2); ?></span> <span style="color:#22c55e;font-weight:700;">$ 0.00</span><?php else: ?>$ <?php echo number_format($fila_total, 2); ?><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!$es_merma): ?>
            <div class="totals">
                <div class="row"><span class="label">Base Imponible</span><span>$ <?php echo number_format($subtotal, 2); ?></span></div>
                <?php if ($es_venta): ?>
                    <div class="row iva"><span class="label">I.V.A. (<?php echo $iva_pct; ?>%)</span><span>$ <?php echo number_format($iva, 2); ?></span></div>
                <?php else: ?>
                    <div class="row iva"><span class="label">I.V.A.</span><span>EXENTO</span></div>
                <?php endif; ?>
                <div class="row total"><span>MONTO TOTAL</span><span>$ <?php echo number_format($total_neto, 2); ?></span></div>
            </div>
        <?php else: ?>
            <div class="totals">
                <div class="row total" style="border:none;color:#64748b;"><span>VALOR</span><span>$ 0.00</span></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($data['observaciones'])): ?>
            <div class="obs-box">
                <label>Observaciones</label>
                <p><?php echo nl2br(htmlspecialchars($data['observaciones'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$es_merma): ?>
            <div class="signatures">
                <div class="sig">
                    <p>Entregado por</p>
                    <div class="line">Firma del Vendedor</div>
                </div>
                <div class="sig">
                    <p>Recibido por</p>
                    <div class="line">Firma del Cliente</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="buttons">
            <button class="btn btn-outline" onclick="window.close()">← VOLVER</button>
            <?php if (!isset($_GET['id'])): ?>
                <form action="salidas.php?confirm=1" method="POST" onsubmit="return confirmarRegistro(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" class="btn btn-primary" id="btnConfirmar">✓ CONFIRMAR Y REGISTRAR</button>
                </form>
            <?php else: ?>
                <button class="btn btn-primary" onclick="window.print()">🖨 IMPRIMIR</button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmarRegistro(e) {
            e.preventDefault();
            const btn = document.getElementById('btnConfirmar');
            btn.disabled = true;
            btn.textContent = '⏳ REGISTRANDO...';
            document.querySelector('.buttons form').submit();
        }
    </script>
</body>

</html>