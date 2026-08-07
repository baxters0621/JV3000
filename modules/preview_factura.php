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
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_GET['store'])) {
    header('Content-Type: application/json');

    $productos_data = $_POST['productos_data'] ?? '';
    $id_tipo_mov  = intval($_POST['id_tipo_mov'] ?? 0);
    $cliente      = mb_strtoupper(trim($_POST['cliente'] ?? ''));
    $rif_cliente  = mb_strtoupper(trim($_POST['rif_cliente'] ?? ''));
    $id_cliente   = intval($_POST['id_cliente'] ?? 0) ?: null;
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
        if (is_array($parsed)) {
            if (count($parsed) > 200) {
                echo json_encode(['ok' => false, 'error' => 'MÁXIMO 200 PRODUCTOS POR VENTA.']); exit();
            }
            $productos = $parsed;
        }
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
        $rif_cliente = normalizarDocumento($rif_cliente);
        if (!validarDocumentoFiscal($rif_cliente)) {
            echo json_encode(['ok' => false, 'error' => 'DOCUMENTO FISCAL INVÁLIDO (CÉDULA O RIF).']);
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

    // Check for expired and stock availability (según grupo y lotes FEFO)
    foreach ($productos as $p) {
        $pid = intval($p['id_producto'] ?? 0);
        $cant = intval($p['cantidad'] ?? 0);
        if ($cant < 1 || $cant > 999999) {
            echo json_encode(['ok' => false, 'error' => 'CANTIDAD INVÁLIDA POR PRODUCTO. RANGO: 1 A 999,999.']);
            exit();
        }
        $precio_entrante = floatval($p['precio'] ?? 0);
        if ($grupo === 'venta' && ($precio_entrante <= 0 || $precio_entrante > 99999999.99)) {
            echo json_encode(['ok' => false, 'error' => "PRECIO DE VENTA INVÁLIDO PARA PRODUCTO #$pid."]);
            exit();
        }
        if ($grupo === 'merma' && ($precio_entrante < 0 || $precio_entrante > 99999999.99)) {
            echo json_encode(['ok' => false, 'error' => "PRECIO DE AJUSTE INVÁLIDO PARA PRODUCTO #$pid."]);
            exit();
        }
        if ($pid) {
            $pc = $db->fetchOne("SELECT stock_actual, fecha_vencimiento FROM productos WHERE id_producto = ?", [$pid]);
            if (!$pc) {
                echo json_encode(['ok' => false, 'error' => "PRODUCTO #$pid NO EXISTE."]);
                exit();
            }
            $tiene_lotes = (int)$db->fetchOne("SELECT COUNT(*) as n FROM lotes WHERE id_producto = ?", [$pid])['n'];
            if ($tiene_lotes > 0) {
                $solo_venc = $grupo === 'merma';
                $disp = stockLoteDisponible($db, $pid, $solo_venc);
                if ($disp < $cant) {
                    $modo = $solo_venc ? 'VENCIDO' : 'VIGENTE';
                    echo json_encode(['ok' => false, 'error' => "STOCK $modo INSUFICIENTE. Disponible: $disp, solicitado: $cant."]);
                    exit();
                }
            } else {
                if ($grupo === 'merma') {
                    if (empty($pc['fecha_vencimiento']) || $pc['fecha_vencimiento'] > date('Y-m-d')) {
                        echo json_encode(['ok' => false, 'error' => 'EN EL MODO AJUSTE SOLO SE PUEDEN SELECCIONAR PRODUCTOS VENCIDOS.']);
                        exit();
                    }
                } elseif ($pc['fecha_vencimiento'] && $pc['fecha_vencimiento'] <= date('Y-m-d')) {
                    echo json_encode(['ok' => false, 'error' => 'PRODUCTO VENCIDO. NO SE PUEDE VENDER.']);
                    exit();
                }
                if ((int)$pc['stock_actual'] < $cant) {
                    echo json_encode(['ok' => false, 'error' => "STOCK INSUFICIENTE. Disponible: {$pc['stock_actual']}, solicitado: $cant."]);
                    exit();
                }
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

    purgarPreviewsSesion();
    $preview_token = bin2hex(random_bytes(16));
    $_SESSION['preview_data_' . $preview_token] = [
        'productos_data'     => json_encode($productos),
        'cliente'            => $cliente,
        'rif_cliente'        => $rif_cliente ?: 'N/A',
        'id_cliente'         => $id_cliente,
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

    echo json_encode(['ok' => true, 'token' => $preview_token]);
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
        SELECT ds.*, p.nombre_producto, p.sku, p.precio_venta as precio_original, p.fecha_vencimiento, l.fecha_vencimiento as lote_vencimiento
        FROM detalle_salidas ds
        JOIN productos p ON ds.id_producto = p.id_producto
        LEFT JOIN lotes l ON ds.id_lote = l.id_lote
        WHERE ds.id_salida = ?
    ", [$id]);
} else {
    $preview_token = $_GET['token'] ?? '';
    $data = $preview_token !== '' ? ($_SESSION['preview_data_' . $preview_token] ?? null) : ($_SESSION['preview_data'] ?? null);
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
    $vf = $det['lote_vencimiento'] ?? ($det['fecha_vencimiento'] ?? null);
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

$badge_color = '#DC2626';
$badge_label = $tipo_mov;
if ($es_regalias) {
    $badge_color = '#D97706';
    $badge_label = 'REGALÍA';
}
if ($es_merma) $badge_color = '#6C757D';

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
        <link rel="stylesheet" href="../assets/modules/preview_factura/preview_factura.css?v=3">
</head>

<body>
    <div class="page">
        <div class="doc-header">
            <div class="doc-left">
                <img class="doc-logo" src="../assets/img/logo-mark.svg?v=1" alt="JV3000">
                <div class="doc-issuer">
                    <div class="issuer-name"><?php echo htmlspecialchars($empresa); ?></div>
                    <p>RIF: <?php echo htmlspecialchars($rif_emp); ?></p>
                    <p><?php echo htmlspecialchars($dir_emp ?: ' '); ?></p>
                    <p>TLF: <?php echo htmlspecialchars($tel_emp ?: ' '); ?></p>
                    <p>Correo: <?php echo htmlspecialchars($email_emp ?: ' '); ?></p>
                </div>
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
            <div style="padding:10px 14px;border-radius:8px;margin-bottom:6px;font-size:.75rem;font-weight:600;text-align:center;<?php echo $av['tipo'] === 'vencido' ? 'background:rgba(220,38,38,0.1);color:#DC2626;border:1px solid rgba(220,38,38,0.3);' : 'background:rgba(217,119,6,0.1);color:var(--jv-warning);border:1px solid rgba(217,119,6,0.3);'; ?>">
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
                        <td><strong class="pv-nombre" data-tooltip="<?php echo htmlspecialchars($det['nombre_producto'] ?? ''); ?>"><?php echo htmlspecialchars($det['nombre_producto'] ?? ''); ?></strong><br><span style="font-size:.85rem;color:#6C757D;">SKU: <?php echo htmlspecialchars($det['sku'] ?? ''); ?></span></td>
                        <td><?php if ($es_regalias && $det['precio_venta'] == 0 && ($det['precio_original'] ?? 0) > 0): ?><span style="text-decoration:line-through;color:#6C757D;">$ <?php echo number_format($det['precio_original'], 2); ?></span> <span style="color:#198754;font-weight:700;">GRATIS</span><?php else: ?>$ <?php echo number_format($det['precio_venta'], 2); ?><?php endif; ?></td>
                        <td><?php if ($es_regalias && $det['precio_venta'] == 0 && ($det['precio_original'] ?? 0) > 0): ?><span style="text-decoration:line-through;color:#6C757D;">$ <?php echo number_format($det['cantidad'] * $det['precio_original'], 2); ?></span> <span style="color:#198754;font-weight:700;">$ 0.00</span><?php else: ?>$ <?php echo number_format($fila_total, 2); ?><?php endif; ?></td>
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
                <div class="row total" style="border:none;color:#6C757D;"><span>VALOR</span><span>$ 0.00</span></div>
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
                <form action="salidas.php?confirm=1&token=<?php echo urlencode($preview_token); ?>" method="POST" onsubmit="return confirmarRegistro(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" class="btn btn-primary" id="btnConfirmar">✓ CONFIRMAR Y REGISTRAR</button>
                </form>
            <?php else: ?>
                <button class="btn btn-primary" onclick="window.print()">🖨 IMPRIMIR</button>
            <?php endif; ?>
        </div>
    </div>

        <script src="../assets/modules/preview_factura/preview_factura.js"></script>
</body>

</html>