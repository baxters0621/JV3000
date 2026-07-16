<?php
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoVenta();
$csrf_token = Security::generateToken();

$iva_pct = getConfig('iva_porcentaje', '16');

// ── MODO: STORE (AJAX) ──
if (isset($_GET['store'])) {
    header('Content-Type: application/json');

    $id_producto  = intval($_POST['id_producto'] ?? 0);
    $cantidad     = intval($_POST['cantidad'] ?? 0);
    $id_tipo_mov  = intval($_POST['id_tipo_mov'] ?? 0);
    $precio_venta = floatval($_POST['precio_venta'] ?? 0);
    $cliente      = mb_strtoupper(trim($_POST['cliente'] ?? 'VENTA GENERAL'));
    $rif_cliente  = mb_strtoupper(trim($_POST['rif_cliente'] ?? ''));
    $fecha_salida = $_POST['fecha_salida'] ?? date('Y-m-d');
    $nro_control  = generarControlNumero();
    $observaciones = trim(($_POST['descripcion_motivo'] ?? '') . ' | ' . ($_POST['observaciones'] ?? ''));
    $observaciones = trim(preg_replace('/^\s*\|\s*$/', '', $observaciones));
    $id_usuario   = $_SESSION['id_usuario'];

    $tn_row = $db->fetchOne("SELECT nombre FROM tipos_movimientos WHERE id_tipo_mov = ?", [$id_tipo_mov]);
    $tipo_nombre = $tn_row['nombre'] ?? '';
    if (mb_strtoupper(trim($tipo_nombre)) === 'REGALIAS') {
        $precio_venta = 0;
    }

    if (!$id_producto || $cantidad <= 0 || !$id_tipo_mov) {
        echo json_encode(['ok'=>false,'error'=>'DATOS INCOMPLETOS.']);
        exit();
    }

    $prod_check = $db->fetchOne("SELECT fecha_vencimiento FROM productos WHERE id_producto = ?", [$id_producto]);
    if ($prod_check && $prod_check['fecha_vencimiento'] && $prod_check['fecha_vencimiento'] <= date('Y-m-d')) {
        echo json_encode(['ok'=>false,'error'=>'PRODUCTO VENCIDO. NO SE PUEDE VENDER.']);
        exit();
    }

    $_SESSION['preview_data'] = [
        'id_producto'        => $id_producto,
        'cantidad'           => $cantidad,
        'precio_venta'       => $precio_venta,
        'cliente'            => $cliente,
        'rif_cliente'        => $rif_cliente ?: 'N/A',
        'nro_factura_manual' => 'PENDIENTE',
        'nro_control'        => $nro_control,
        'fecha_salida'       => $fecha_salida,
        'id_tipo_mov'        => $id_tipo_mov,
        'observaciones'      => $observaciones,
        'id_usuario'         => $id_usuario,
    ];

    echo json_encode(['ok'=>true]);
    exit();
}

// ── MODO: REIMPRIMIR desde DB ──
$data = null;

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $data = $db->fetchOne("
        SELECT s.*, p.nombre_producto, p.sku, p.precio_venta as p_precio, tm.nombre as tipo_nombre
        FROM salidas s
        JOIN productos p ON s.id_producto = p.id_producto
        LEFT JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
        WHERE s.id_salida = ?
    ", [$id]);
    if (!$data) { echo "<h2>NOTA DE ENTREGA NO ENCONTRADA</h2>"; exit(); }
} else {
    $data = $_SESSION['preview_data'] ?? null;
    if (!$data) { echo "<h2>NO HAY DATOS DE PREVIEW</h2>"; exit(); }

    $prod = $db->fetchOne("SELECT nombre_producto, sku, fecha_vencimiento FROM productos WHERE id_producto = ?", [(int)$data['id_producto']]);
    if ($prod) {
        $data['nombre_producto'] = $prod['nombre_producto'];
        $data['sku'] = $prod['sku'];
        $data['fecha_vencimiento'] = $prod['fecha_vencimiento'];
    }
    $tn_row = $db->fetchOne("SELECT nombre FROM tipos_movimientos WHERE id_tipo_mov = ?", [(int)$data['id_tipo_mov']]);
    if ($tn_row) {
        $data['tipo_nombre'] = $tn_row['nombre'];
    }
}

// ── Alerta de vencimiento ──
$alerta_venc = '';
$venc_fecha = $data['fecha_vencimiento'] ?? null;
if ($venc_fecha && $venc_fecha <= date('Y-m-d')) {
    $alerta_venc = 'vencido';
} elseif ($venc_fecha && $venc_fecha <= date('Y-m-d', strtotime('+7 days'))) {
    $alerta_venc = 'proximo';
}

// ── Determinar tipo para layout adaptativo ──
$tipo_mov = strtoupper(trim($data['tipo_nombre'] ?? 'VENTA'));
$es_venta = $tipo_mov === 'VENTA';
$es_regalias = $tipo_mov === 'REGALIAS';
$es_merma = in_array($tipo_mov, ['MERMAS', 'DAÑOS']);

$total_fila = $data['cantidad'] * $data['precio_venta'];
$subtotal = $es_merma ? 0 : $total_fila;
$iva = $es_venta ? ($subtotal * ($iva_pct / 100)) : 0;
$total_neto = $subtotal + $iva;

// Config empresa (key-value table)
$empresa = getConfig('empresa_nombre', 'JV3000');
$rif_emp  = getConfig('empresa_rif', 'J-00000000-0');
$tel_emp  = getConfig('empresa_telefono', '');
$dir_emp  = getConfig('empresa_direccion', '');
$email_emp = getConfig('empresa_email', '');

$badge_color = '#dc2626';
$badge_label = $tipo_mov;
if ($es_regalias) { $badge_color = '#f59e0b'; $badge_label = 'REGALÍA'; }
if ($es_merma) $badge_color = '#64748b';

// Hora actual para el sello fiscal
$hora_actual = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Factura #<?php echo $data['id_salida'] ?? 'PREVIEW'; ?> | <?php echo $empresa; ?></title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/bootstrap-icons.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family:system-ui,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    background:#f1f5f9; padding:30px;
}
.page {
    max-width:800px; margin:0 auto; background:#fff;
    border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.08);
    padding:32px 36px;
}
/* ── Encabezado ── */
.doc-header {
    display:flex; justify-content:space-between; align-items:flex-start;
    border-bottom:2px solid #0f172a; padding-bottom:16px; margin-bottom:16px;
}
.doc-issuer h2 {
    font-family:system-ui,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    font-size:1.3rem; font-weight:800; color:#0f172a; margin:0; letter-spacing:-.5px;
}
.doc-issuer p { margin:1px 0; font-size:.75rem; color:#475569; line-height:1.4; }
.doc-issuer .issuer-name { font-size:.95rem; font-weight:700; color:#0f172a; }
.doc-type {
    text-align:right;
}
.doc-type h1 {
    font-family:system-ui,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    font-size:2rem; font-weight:900; color:#0f172a; margin:0; letter-spacing:2px;
    text-transform:uppercase;
}
.doc-type .type-sub {
    font-size:.7rem; color:#64748b; font-weight:600;
    text-transform:uppercase; letter-spacing:1px; margin-top:2px;
}
/* ── Numeración ── */
.num-row {
    display:flex; justify-content:space-between; align-items:center;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
    padding:10px 16px; margin-bottom:16px; flex-wrap:wrap; gap:6px;
}
.num-item { font-size:.75rem; color:#475569; }
.num-item strong { color:#0f172a; font-size:.85rem; }
/* ── Info boxes ── */
.info-grid {
    display:grid; grid-template-columns:1fr 1fr;
    gap:10px; margin-bottom:16px;
}
.info-box {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px;
}
.info-box label {
    font-size:.6rem; font-weight:700; color:#94a3b8;
    text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:2px;
}
.info-box .value { font-size:.82rem; font-weight:600; color:#0f172a; }
/* ── Tabla ── */
table { width:100%; border-collapse:collapse; margin:12px 0 16px; }
table th {
    background:#0f172a; color:#fff; font-size:.65rem;
    text-transform:uppercase; letter-spacing:1px;
    padding:10px 8px; text-align:left; border:none;
}
table th:last-child { text-align:right; }
table th:nth-child(2) { text-align:center; }
table th:nth-child(3) { text-align:center; }
table td {
    padding:10px 8px; border-bottom:1px solid #e2e8f0;
    font-size:.82rem; color:#0f172a;
}
table td:last-child { text-align:right; font-weight:700; }
table td:nth-child(2) { text-align:center; }
table td:nth-child(3) { text-align:center; }
/* ── Totales ── */
.totals { margin-left:auto; width:280px; }
.totals .row { display:flex; justify-content:space-between; padding:5px 0; font-size:.82rem; }
.totals .row.iva { color:#0891b2; }
.totals .row.total {
    border-top:2px solid #0f172a; margin-top:5px; padding-top:8px;
    font-weight:800; font-size:1rem; color:#dc2626;
}
.totals .row .label { font-weight:600; color:#475569; }
/* ── Base legal ── */
.legal-box {
    margin-top:16px; padding:10px 14px;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
    font-size:.6rem; color:#94a3b8; text-align:center; line-height:1.5;
}
.obs-box {
    margin:12px 0; padding:10px 14px;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;
}
.obs-box label {
    font-size:.6rem; font-weight:700; color:#94a3b8;
    text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:2px;
}
.obs-box p { font-size:.8rem; color:#475569; margin:0; }
/* ── Firmas ── */
.signatures {
    display:flex; justify-content:space-between; margin-top:20px;
    padding-top:16px; border-top:1px solid #e2e8f0;
}
.signatures .sig { width:220px; text-align:center; }
.signatures .sig .line {
    border-top:1px solid #94a3b8; padding-top:6px;
    margin-top:36px; font-size:.7rem; color:#64748b;
}
.signatures .sig p { font-size:.75rem; color:#64748b; margin:0 0 4px; font-weight:600; }
/* ── Botones ── */
.buttons { display:flex; gap:12px; justify-content:center; margin-top:24px; }
.buttons form { margin:0; }
.btn { padding:12px 32px; border-radius:10px; font-weight:700; font-size:.85rem; border:none; cursor:pointer; transition:.2s; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
.btn-primary { background:#dc2626; color:#fff; }
.btn-primary:hover { background:#b91c1c; }
.btn-primary:disabled { opacity:.5; cursor:not-allowed; }
.btn-outline { background:transparent; color:#475569; border:1px solid #cbd5e1; }
.btn-outline:hover { background:#f1f5f9; }
/* ── Print ── */
@media print {
    @page { margin:10mm 8mm; }
    body { background:#fff; padding:0; }
    .page { box-shadow:none; border-radius:0; padding:20px 22px; max-width:100%; }
    .buttons { display:none !important; }
    .doc-header { padding-bottom:10px; margin-bottom:10px; }
    .doc-issuer h2 { font-size:1.1rem; }
    .doc-issuer p { font-size:.65rem; }
    .doc-issuer .issuer-name { font-size:.8rem; }
    .doc-type h1 { font-size:1.6rem; }
    .num-row { padding:6px 10px; margin-bottom:10px; }
    .num-item { font-size:.65rem; }
    .num-item strong { font-size:.75rem; }
    .info-grid { gap:6px; margin-bottom:10px; }
    .info-box { padding:6px 10px; }
    .info-box label { font-size:.5rem; }
    .info-box .value { font-size:.7rem; }
    table { margin:8px 0 10px; }
    table th { padding:6px 6px; font-size:.55rem; }
    table td { padding:6px 6px; font-size:.7rem; }
    .totals { width:220px; }
    .totals .row { padding:3px 0; font-size:.7rem; }
    .totals .row.total { font-size:.85rem; padding-top:5px; }
    .obs-box { padding:6px 10px; margin:8px 0; }
    .obs-box p { font-size:.7rem; }
    .legal-box { font-size:.5rem; padding:6px 10px; }
    .signatures { margin-top:12px; padding-top:10px; }
    .signatures .sig .line { margin-top:24px; font-size:.6rem; }
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
        <span class="num-item"><strong>N° FACTURA:</strong> <?php echo htmlspecialchars($data['nro_factura_manual'] ?? 'PENDIENTE'); ?></span>
        <span class="num-item"><strong>N° CONTROL:</strong> <?php echo htmlspecialchars($data['nro_control'] ?? '—'); ?></span>
        <span class="num-item"><strong>FECHA:</strong> <?php echo date('d/m/Y', strtotime($data['fecha_salida'])); ?></span>
        <span class="num-item"><strong>HORA:</strong> <?php echo $hora_actual; ?></span>
    </div>

    <?php if ($alerta_venc): ?>
    <div style="padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:.8rem;font-weight:600;text-align:center;<?php echo $alerta_venc === 'vencido' ? 'background:#fef2f2;color:#dc2626;border:1px solid #fecaca;' : 'background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;'; ?>">
        <?php if ($alerta_venc === 'vencido'): ?>
        ⚠ PRODUCTO VENCIDO (<?php echo date('d/m/Y', strtotime($venc_fecha)); ?>)
        <?php else: ?>
        ⚠ PRODUCTO PRÓXIMO A VENCER (<?php echo date('d/m/Y', strtotime($venc_fecha)); ?>)
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
            <tr>
                <td><?php echo $data['cantidad']; ?></td>
                <td><strong><?php echo htmlspecialchars($data['nombre_producto'] ?? ''); ?></strong><br><span style="font-size:.7rem;color:#94a3b8;">SKU: <?php echo htmlspecialchars($data['sku'] ?? ''); ?></span></td>
                <td>$ <?php echo number_format($data['precio_venta'], 2); ?></td>
                <td>$ <?php echo number_format($total_fila, 2); ?></td>
            </tr>
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
    btn.disabled = true; btn.textContent = '⏳ REGISTRANDO...';
    document.querySelector('.buttons form').submit();
}
</script>
</body>
</html>
