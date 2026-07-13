<?php
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();

$esAdmin = Security::esAdmin();
$csrf_token = Security::generateToken();

// --- AJAX: Create product inline from compra modal ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_producto']) && $_POST['accion_producto'] === 'crear_ajax') {
    header('Content-Type: application/json');
    $nombre = mb_strtoupper(trim($_POST['nombre_producto'] ?? ''));
    $id_cat = intval($_POST['id_categoria'] ?? 0);
    $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
    $status = $_POST['status'] ?? 'Activo';
    $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

    if (empty($nombre) || $id_cat <= 0) {
        echo json_encode(['success' => false, 'error' => 'Nombre y categoría requeridos']);
        exit;
    }

    $dup = $db->fetchOne("SELECT id_producto FROM productos WHERE LOWER(nombre_producto) = LOWER(?)", [$nombre]);
    if ($dup) {
        echo json_encode(['success' => false, 'error' => 'Ya existe un producto con ese nombre']);
        exit;
    }

    $db->execute("INSERT IGNORE INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('PROD', 0)");

    $db->begin();
    try {
        $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='PROD' FOR UPDATE");
        $prox = intval($cnt['ultimo_numero'] ?? 0) + 1;
        $sku = 'PROD-' . str_pad($prox, 3, '0', STR_PAD_LEFT);
        $db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='PROD'", [$prox]);

        $id = $db->insert('productos', [
            'sku'               => $sku,
            'nombre_producto'   => $nombre,
            'precio_venta'      => 0,
            'precio_costo'      => 0,
            'stock_actual'      => 0,
            'stock_minimo'      => $stock_minimo,
            'status'            => $status,
            'id_categoria'      => $id_cat,
            'fecha_vencimiento' => $fecha_vencimiento,
        ]);

        $db->commit();

        echo json_encode(['success' => true, 'id' => $id, 'nombre' => $nombre, 'sku' => $sku]);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
    }
    exit;
}

// --- Compra POST handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_compra'])) {
    $tipo_entrada = in_array(trim($_POST['tipo_entrada'] ?? ''), ['Compra a proveedor', 'Ajuste', 'Donación']) ? trim($_POST['tipo_entrada']) : 'Compra a proveedor';
    $es_proveedor = $tipo_entrada === 'Compra a proveedor';
    $es_ajuste = $tipo_entrada === 'Ajuste';
    $es_donacion = $tipo_entrada === 'Donación';

    $id_proveedor = $es_proveedor ? intval($_POST['id_proveedor'] ?? 0) : null;
    if ($es_proveedor && $id_proveedor <= 0) {
        $_SESSION['error'] = 'SELECCIONE UN PROVEEDOR PARA COMPRA A PROVEEDOR.';
        header('Location: compras.php');
        exit;
    }
    $nro_factura = trim($_POST['nro_factura'] ?? '');
    if ($es_proveedor && empty($nro_factura)) {
        $_SESSION['error'] = 'EL NÚMERO DE FACTURA ES OBLIGATORIO.';
        header('Location: compras.php');
        exit;
    }
    if ($es_proveedor && $db->fetchOne("SELECT id_compra FROM compras WHERE nro_factura = ? AND status = 'Activa'", [$nro_factura])) {
        $_SESSION['error'] = 'EL NÚMERO DE FACTURA YA EXISTE EN EL SISTEMA.';
        header('Location: compras.php');
        exit;
    }
    $nro_control = $es_proveedor ? trim($_POST['nro_control'] ?? '') : null;
    if ($es_proveedor && !preg_match('/^\d{2}-\d{8}$/', $nro_control)) {
        $_SESSION['error'] = 'NRO. CONTROL INVÁLIDO. Formato: 00-00000000';
        header('Location: compras.php');
        exit;
    }
    $fecha_compra = $_POST['fecha_compra'] ?? date('Y-m-d');
    $motivo = $es_proveedor ? '' : ($_POST['motivo'] ?? '');
    $observaciones = $motivo ?: ($_POST['observaciones'] ?? '');

    $condiciones_pago = 'Contado';
    $dias_credito = 0;
    $fecha_vencimiento = null;

    if ($es_proveedor && $id_proveedor > 0) {
        $prov = $db->fetchOne("SELECT condiciones_pago, dias_credito FROM proveedores WHERE id_proveedor = ?", [$id_proveedor]);
        $condiciones_pago = $prov['condiciones_pago'] ?? 'Contado';
        $dias_credito = intval($prov['dias_credito'] ?? 0);
        if ($condiciones_pago === 'Credito' && $dias_credito > 0) {
            $fecha_vencimiento = date('Y-m-d', strtotime("+$dias_credito days", strtotime($fecha_compra)));
        }
    }

    $productos_raw = json_decode($_POST['productos_data'] ?? '[]', true);
    $productos = is_array($productos_raw) ? $productos_raw : [];
    $exitos = 0;

    if (!empty($productos)) {
        $db->begin();
        try {
            $id_usuario_sesion = intval($_SESSION['id_usuario'] ?? 0);

            foreach ($productos as $i => $prod) {
                $id_producto = intval($prod['id'] ?? 0);
                $cantidad = intval($prod['cantidad'] ?? 0);
                $precio_costo = $es_donacion ? 0 : floatval($prod['precio'] ?? 0);

                if ($id_producto > 0 && $cantidad > 0 && ($es_donacion || $precio_costo > 0)) {
                    $total = $cantidad * $precio_costo;
                    $nro_ctrl_row = $nro_control;
                    if ($es_proveedor && $i > 0 && $nro_control) {
                        $nro_ctrl_row = $nro_control . '-' . $i;
                    }

                    $db->insert('compras', [
                        'id_proveedor'     => $id_proveedor,
                        'id_producto'      => $id_producto,
                        'nro_factura'      => $nro_factura,
                        'nro_control'      => $nro_ctrl_row,
                        'tipo_entrada'     => $tipo_entrada,
                        'cantidad'         => $cantidad,
                        'precio_costo'     => $precio_costo,
                        'total'            => $total,
                        'condiciones_pago' => $condiciones_pago,
                        'dias_plazo'       => $dias_credito,
                        'fecha_compra'     => $fecha_compra,
                        'fecha_vencimiento'=> $fecha_vencimiento,
                        'observaciones'    => $observaciones,
                        'id_usuario'       => $id_usuario_sesion,
                    ]);

                    $prod_row = $db->fetchOne("SELECT stock_actual, precio_costo, precio_venta FROM productos WHERE id_producto = ?", [$id_producto]);
                    $old_stock = (int)($prod_row['stock_actual'] ?? 0);
                    $old_cost = (float)($prod_row['precio_costo'] ?? 0);
                    $old_pv = (float)($prod_row['precio_venta'] ?? 0);
                    $new_stock = $old_stock + $cantidad;
                    if ($es_donacion) {
                        $new_avg = $old_cost;
                        $new_pv = $old_pv;
                    } else {
                        $new_avg = $new_stock > 0 ? (($old_stock * $old_cost) + ($cantidad * $precio_costo)) / $new_stock : $precio_costo;
                        $new_pv = $old_pv > 0 ? $old_pv : round($new_avg * 1.3, 2);
                    }
                    $db->execute("UPDATE productos SET stock_actual = ?, precio_costo = ?, precio_venta = ? WHERE id_producto = ?", [$new_stock, $new_avg, $new_pv, $id_producto]);

                    $exitos++;
                }
            }
            if ($exitos > 0) {
                registrarAuditoria('crear', "Entrada registrada ($tipo_entrada, $exitos producto(s))");
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'ERROR AL PROCESAR LA ENTRADA. VERIFICA LOS DATOS E INTENTA DE NUEVO.'];
            header('Location: compras.php');
            exit;
        }
    }

    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => $exitos > 0 ? "ENTRADA REGISTRADA: $exitos producto(s)." : 'Error al guardar: verifica los datos e intenta de nuevo.'];
    header('Location: compras.php');
    exit;
}

// --- Delete handler ---
if (isset($_GET['eliminar']) && $esAdmin) {
    $id_compra = intval($_GET['eliminar']);
    $compra = $db->fetchOne("SELECT id_producto, cantidad FROM compras WHERE id_compra = ?", [$id_compra]);
    if ($compra) {
        $db->begin();
        try {
            $db->execute("UPDATE productos SET stock_actual = stock_actual - ? WHERE id_producto = ?", [(int)$compra['cantidad'], (int)$compra['id_producto']]);
            $db->execute("UPDATE compras SET status = 'Anulada' WHERE id_compra = ?", [$id_compra]);
            $db->commit();
            registrarAuditoria('anular', 'Entrada de compra anulada - stock revertido');
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'ENTRADA ANULADA. STOCK REVERTIDO.'];
        } catch (Exception $e) {
            $db->rollback();
        }
    }
    header('Location: compras.php');
    exit;
}

// --- Queries ---
$filtro_proveedor = intval($_GET['filtro_proveedor'] ?? 0);
$sql_compras = "
    SELECT c.*, p.nombre_producto, pr.nombre_empresa as proveedor
    FROM compras c
    LEFT JOIN productos p ON c.id_producto = p.id_producto
    LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
    WHERE c.status = 'Activa'
";
$sql_compras .= " ORDER BY c.fecha_compra DESC, c.id_compra DESC LIMIT 100";
$compras = $filtro_proveedor > 0
    ? $db->fetchAll($sql_compras . " AND c.id_proveedor = ?", [$filtro_proveedor])
    : $db->fetchAll($sql_compras);

$productos = $db->fetchAll("SELECT id_producto, nombre_producto, stock_actual, precio_costo FROM productos WHERE status = 'Activo' ORDER BY nombre_producto");
$proveedores = $db->fetchAll("SELECT id_proveedor, nombre_empresa, rif, condiciones_pago, dias_credito FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa");
$categorias = $db->fetchAll("SELECT id_categoria, nombre FROM categorias WHERE status = 'Activo' ORDER BY nombre");

$total_entradas = (int)$db->fetchOne("SELECT COUNT(*) as t FROM compras WHERE status = 'Activa'")['t'];
$entradas_hoy = (int)$db->fetchOne("SELECT COALESCE(SUM(cantidad),0) as t FROM compras WHERE fecha_compra >= CURDATE() AND fecha_compra < CURDATE() + INTERVAL 1 DAY AND status = 'Activa'")['t'];
$inv_mes_row = $db->fetchOne("SELECT COALESCE(SUM(total),0) as t FROM compras WHERE fecha_compra >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND fecha_compra < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH,'%Y-%m-01') AND status = 'Activa'");
$invertido_mes = $inv_mes_row['t'] ?? 0;




$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>
    <style>
    /* === THEME: COMPRAS (Green / Emerald) ================ */

    /* ── Header icon ── */
    .com-header-icon {
        width:52px;height:52px;border-radius:14px;
        background:linear-gradient(135deg,#059669,#065f46);
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-size:1.5rem;flex-shrink:0;
        box-shadow:0 0 30px rgba(5,150,105,0.3);
    }

    /* ── Codigo badge (green-tinted) ── */
    .codigo-badge {
        background:rgba(5,150,105,0.12);color:#6ee7b7;
        font-size:.7rem;font-weight:800;padding:3px 10px;
        border-radius:20px;display:inline-block;
        letter-spacing:.5px;
    }

    /* ── Action button (40px) ── */
    .btn-action {
        width:40px;height:40px;border-radius:12px;
        display:inline-flex;align-items:center;justify-content:center;
        border:1px solid var(--jv-border);background:var(--jv-bg-primary);
        color:var(--jv-text);transition:.15s;
    }
    .btn-action:hover {
        background:var(--jv-bg-hover);border-color:#10b981;
        color:#10b981;
    }

    /* ── Empty state ── */
    .estado-vacio {
        padding:60px 20px;text-align:center;
    }
    .estado-vacio i {
        font-size:3.5rem;color:rgba(16,185,129,0.2);display:block;margin-bottom:16px;
    }
    .estado-vacio span {
        font-size:.85rem;font-weight:700;text-transform:uppercase;
        letter-spacing:1px;color:rgba(148,163,184,0.5);
    }

    /* ── Scoped module styles ── */
    .pagina-compras .card-jv {
        border-color:rgba(16,185,129,0.25);
        box-shadow:0 20px 50px -12px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(16,185,129,0.06);
    }
    .pagina-compras .card-jv:hover {
        border-color:rgba(16,185,129,0.45);
    }
    .pagina-compras .card-jv-table {
        border-top:4px solid #10b981;
        border-radius:var(--jv-radius) !important;overflow:hidden;
    }
    .pagina-compras .table-jv thead th {
        background:linear-gradient(135deg,#065f46,#047857);
        color:#a7f3d0;
        border-bottom:2px solid rgba(16,185,129,0.3);
        font-size:1rem;
        padding:18px 24px;
    }
    .pagina-compras .table-jv tbody td {
        border-bottom:1px solid rgba(16,185,129,0.07);
        padding:18px 24px;
        font-size:1rem;
    }
    .pagina-compras .table-jv tbody tr:hover {
        background:rgba(16,185,129,0.03);
    }
    .pagina-compras .btn-jv-primary {
        background:linear-gradient(135deg,#059669,#065f46);
    }
    .pagina-compras .btn-jv-primary:hover {
        box-shadow:0 8px 25px -5px rgba(5,150,105,0.4);
        transform:translateY(-2px);
    }
    .pagina-compras .input-jv:focus {
        border-color:#10b981;
        box-shadow:0 0 0 3px rgba(16,185,129,0.15);
    }
    .pagina-compras .buscador-wrapper {
        border-bottom:1px solid rgba(16,185,129,0.15);
        background:rgba(2,6,23,0.5);
    }
    .pagina-compras .buscador-wrapper i {
        color:#10b981;
    }
    .pagina-compras .btn-jv-success {
        border:1px solid rgba(255,255,255,0.12);
        box-shadow:0 0 24px rgba(5,150,105,0.3), inset 0 1px 0 rgba(255,255,255,0.1);
    }
    .pagina-compras .btn-jv-success:hover {
        box-shadow:0 8px 30px -5px rgba(5,150,105,0.5);
        transform:translateY(-2px);
    }
    .pagina-compras .header-card {
        padding:18px 24px;
        border-left:4px solid #10b981;
    }
    .pagina-compras .widget-card {
        border-left:3px solid #10b981;
    }
    .pagina-compras .widget-card:hover {
        border-color:#34d399;
    }

    /* ── Widget cards bigger & bolder ── */
    .pagina-compras .widget-card {
        border-radius:var(--jv-radius-lg);
        background:var(--jv-bg-card);
        backdrop-filter:blur(20px);
        border:1px solid var(--jv-border);
        padding:20px 22px;
        display:flex;
        align-items:center;
        gap:18px;
        transition:all .25s ease;
        min-height:90px;
    }
    .pagina-compras .widget-card:hover {
        border-color:var(--jv-border-hover);
        transform:translateY(-3px);
        box-shadow:0 12px 40px -8px rgba(0,0,0,0.4);
    }
    .widget-card .widget-icon {
        width:46px;height:46px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        font-size:1.3rem;flex-shrink:0;
    }
    .widget-card .widget-label {
        font-size:.6rem;text-transform:uppercase;
        letter-spacing:1px;font-weight:700;
        color:rgba(148,163,184,0.7);
        margin-bottom:4px;
    }
    .widget-card .widget-value {
        font-size:1.4rem;font-weight:800;color:#fff;
        line-height:1.2;
    }

    /* ── Cant badge ── */
    .cant-badge {
        background:rgba(16,185,129,0.15);color:#34d399;
        padding:2px 10px;border-radius:4px;
        font-weight:700;font-size:.8rem;
    }

    /* ── Alert overrides ── */
    .alert-jv { border-left:4px solid; border-radius:8px; padding:14px 20px !important; font-size:.9rem; }
    .alert-jv-success { border-left-color:#22c55e; background:rgba(34,197,94,0.1); }
    .alert-jv-danger { border-left-color:#ef4444; background:rgba(239,68,68,0.1); }

    /* ── Modal section groups ── */
    .section-bg {
        background:rgba(2,6,23,0.3);
        border:1px solid rgba(16,185,129,0.08);
        border-radius:var(--jv-radius);
        padding:12px 14px;
        margin-bottom:10px;
    }
    .section-label {
        font-size:.65rem;font-weight:800;text-transform:uppercase;
        letter-spacing:1px;color:#34d399;
        margin-bottom:6px;padding-bottom:4px;
        border-bottom:1px solid rgba(16,185,129,0.15);
        display:flex;align-items:center;gap:4px;
    }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4 pagina-compras">

            <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 header-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="com-header-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <h1 class="font-brand fw-bold m-0 text-white" style="font-size:1.6rem;letter-spacing:-1px;">COMPRAS</h1>
                        <p class="text-white opacity-75 small fw-bold text-uppercase m-0">Entradas de Inventario</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-jv-success pulse-jv" data-bs-toggle="modal" data-bs-target="#modalCompra" style="padding:10px 28px;font-size:.9rem;">
                        <i class="bi bi-plus-lg me-1"></i>NUEVA ENTRADA
                    </button>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3 px-3 py-2">
                    <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($flash['texto']); ?>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="widget-card" style="border-left:4px solid #22c55e;">
                        <div class="widget-icon" style="background:linear-gradient(135deg,rgba(34,197,94,0.2),rgba(34,197,94,0.05));color:#4ade80;">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <div class="widget-label">Total Entradas</div>
                            <div class="widget-value"><?php echo $total_entradas; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="widget-card" style="border-left:4px solid #f59e0b;">
                        <div class="widget-icon" style="background:linear-gradient(135deg,rgba(245,158,11,0.2),rgba(245,158,11,0.05));color:#fbbf24;">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div>
                            <div class="widget-label">Invertido (Mes)</div>
                            <div class="widget-value">$<?php echo number_format($invertido_mes, 0); ?></div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card-jv card-jv-table p-0">
                <div class="d-flex align-items-center px-3 py-2 buscador-wrapper">
                    <i class="bi bi-search me-2" style="font-size:1rem;"></i>
                    <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar entradas..." id="buscar" onkeyup="filtrar()" style="box-shadow:none;font-size:.85rem;padding:8px 6px;">
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Control</th>
                                <th>Tipo</th>
                                <th>Proveedor</th>
                                <th class="text-center">Cant</th>
                                <th>Total</th>
                                <th class="text-center">Condiciones</th>
                                <th>Fecha</th>
                                <th class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="tablaEntradas">
                            <?php if (count($compras) > 0): foreach ($compras as $row): ?>
                                <tr>
                                    <td><span class="codigo-badge"><?php echo htmlspecialchars($row['nro_factura'] ?: '-'); ?></span></td>
                                    <td style="color:#94a3b8;"><?php echo htmlspecialchars($row['nro_control'] ?: '-'); ?></td>
                                    <td><span class="badge-jv <?php echo ($row['tipo_entrada'] ?? '') == 'Compra a proveedor' ? 'badge-success' : 'badge-warning'; ?>"><?php echo htmlspecialchars($row['tipo_entrada']); ?></span></td>
                                    <td class="text-uppercase fw-bold"><?php echo htmlspecialchars($row['proveedor'] ?? 'S/P'); ?></td>
                                    <td class="text-center"><span class="cant-badge">+<?php echo $row['cantidad']; ?></span></td>
                                    <td class="fw-bold" style="color:#34d399;">$<?php echo number_format($row['total'], 2); ?></td>
                                    <td class="text-center"><span class="badge-jv <?php echo ($row['condiciones_pago'] ?? 'Contado') === 'Contado' ? 'badge-success' : 'badge-warning'; ?>"><?php echo $row['condiciones_pago'] ?? 'Contado'; ?></span></td>
                                    <td style="color:#e2e8f0;font-weight:600;font-size:.82rem;"><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></td>
                                    <td class="text-center">
                                        <?php if ($esAdmin): ?>
                                            <button type="button" class="btn-action" onclick="confirmarEliminar(<?php echo $row['id_compra']; ?>)"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="10">
                                    <div class="estado-vacio">
                                        <i class="bi bi-cart-x"></i>
                                        <span>No hay entradas registradas</span>
                                    </div>
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Compra -->
    <div class="modal fade" id="modalCompra" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <form method="POST" id="formCompra">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_compra" value="registrar">
                    <input type="hidden" name="productos_data" id="productosData">

                    <div class="p-3" style="border-bottom:1px solid rgba(16,185,129,0.12);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 font-brand" style="color:#34d399;font-size:1rem;letter-spacing:-.5px;"><i class="bi bi-cart-plus me-2"></i>REGISTRAR ENTRADA</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                    </div>

                    <div class="p-3" style="padding:16px 20px;">
                        <div class="comp-proveedor-section section-bg">
                            <div class="section-label"><i class="bi bi-building me-1"></i>Proveedor</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="small fw-bold text-secondary mb-1">PROVEEDOR *</label>
                                    <select name="id_proveedor" class="input-jv" id="selProveedor">
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($proveedores as $p): ?>
                                            <option value="<?php echo $p['id_proveedor']; ?>" data-condicion="<?php echo $p['condiciones_pago']; ?>" data-dias="<?php echo $p['dias_credito']; ?>">
                                                <?php echo htmlspecialchars($p['nombre_empresa']); ?> (<?php echo $p['rif']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">CONDICIÓN</label>
                                    <input type="text" class="input-jv" id="displayCondicion" value="-" readonly disabled style="color:#94a3b8;">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">DÍAS</label>
                                    <input type="text" class="input-jv" id="displayDias" value="-" readonly disabled style="color:#94a3b8;">
                                </div>
                            </div>
                        </div>

                        <!-- MOTIVO (solo para Ajuste / Donación) -->
                        <div class="comp-motivo-section section-bg" style="display:none;">
                            <div class="section-label"><i class="bi bi-chat-dots me-1"></i>Motivo</div>
                            <div class="row g-2">
                                <div class="col-md-12">
                                    <label class="small fw-bold text-secondary mb-1">DESCRIPCIÓN / MOTIVO *</label>
                                    <textarea name="motivo" class="input-jv" rows="2" placeholder="Ej: Ajuste por inventario, Donación recibida..." style="resize:vertical;"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-receipt me-1"></i>Comprobante</div>
                            <div class="row g-2">
                                <div class="comp-factura-section col-md-4">
                                    <label class="small fw-bold text-secondary mb-1">NRO. FACTURA *</label>
                                    <input type="text" name="nro_factura" class="input-jv" value="FAC-001" oninput="var n=this.value.replace(/^FAC-/i,'').replace(/[^0-9]/g,'');if(n>999)n='999';if(n<1||n=='')n='1';this.value='FAC-'+n.padStart(3,'0')">
                                </div>
                                <div class="comp-factura-section col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">NRO. CONTROL *</label>
                                    <input type="text" name="nro_control" class="input-jv" value="" placeholder="00-00000000" oninput="var v=this.value.replace(/[^0-9]/g,'');if(v.length>10)v=v.slice(0,10);if(v.length>2)v=v.slice(0,2)+'-'+v.slice(2);this.value=v" maxlength="11">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-1">TIPO</label>
                                    <select name="tipo_entrada" class="input-jv" onchange="toggleCamposCompras(this)">
                                        <option>Compra a proveedor</option>
                                        <option>Ajuste</option>
                                        <option>Donación</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small fw-bold text-secondary mb-1">FECHA</label>
                                    <input type="date" name="fecha_compra" class="input-jv" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-box-seam me-1"></i>Productos</div>

                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="small fw-bold text-secondary mb-1">Producto</label>
                                <div class="d-flex gap-2">
                                    <select class="input-jv" id="selProducto" style="flex:1;min-width:0;">
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($productos as $prod): ?>
                                            <option value="<?php echo $prod['id_producto']; ?>" data-precio="<?php echo $prod['precio_costo']; ?>">
                                                <?php echo htmlspecialchars($prod['nombre_producto']); ?> (Stock: <?php echo $prod['stock_actual']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn-jv-primary pagina-compras" style="flex-shrink:0;padding:8px 14px;font-size:.75rem;white-space:nowrap;font-weight:700;" onclick="abrirNuevoProducto()" title="Crear producto nuevo">
                                        <i class="bi bi-lightning-fill me-1"></i>CREAR
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-secondary mb-1">Cant</label>
                                <input type="number" class="input-jv" id="inputCant" value="1" min="1">
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-secondary mb-1">Precio $</label>
                                <input type="number" class="input-jv" id="inputPrecio" step="0.01" min="0" max="1000000" placeholder="0.00" oninput="if(this.value>1000000)this.value=1000000;if(this.value<0)this.value=0">
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn-jv-primary w-100" style="padding:12px;" onclick="agregarProducto()">
                                    <i class="bi bi-plus-lg"></i> Agregar
                                </button>
                            </div>
                        </div>

                        <div style="border:1px solid rgba(16,185,129,0.12);border-radius:8px;overflow:hidden;margin-top:10px;">
                            <table style="width:100%;border-collapse:collapse;background:var(--jv-bg-primary);">
                                <thead>
                                    <tr style="background:linear-gradient(135deg,#065f46,#047857);">
                                        <th style="padding:6px 8px;width:28px;text-align:center;color:#a7f3d0;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">#</th>
                                        <th style="padding:6px 8px;color:#a7f3d0;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Producto</th>
                                        <th style="padding:6px 8px;width:55px;text-align:center;color:#a7f3d0;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Cant</th>
                                        <th style="padding:6px 8px;width:90px;text-align:right;color:#a7f3d0;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Precio</th>
                                        <th style="padding:6px 8px;width:90px;text-align:right;color:#a7f3d0;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Total</th>
                                        <th style="width:28px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="productosBody">
                                    <tr id="filaVacia"><td colspan="6" style="padding:24px 12px;text-align:center;color:#64748b;font-size:.85rem;border-bottom:1px solid rgba(16,185,129,0.07);">⬆ Use los controles de arriba para agregar productos</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;margin-top:8px;background:rgba(16,185,129,0.04);border:1px solid rgba(16,185,129,0.15);border-radius:8px;">
                            <div>
                                <span class="text-secondary" style="font-weight:600;font-size:.9rem;">Productos</span>
                                <span class="fw-bold ms-2" id="totalItems" style="color:#34d399;font-size:1.1rem;">0</span>
                            </div>
                            <div>
                                <span class="text-secondary" style="font-weight:600;font-size:.9rem;">Total Costo</span>
                                <span class="fw-bold ms-2" id="totalCosto" style="color:#f59e0b;font-size:1.15rem;">$0.00</span>
                            </div>
                        </div>
                        </div>

                        <div class="section-bg" style="margin-bottom:0;">
                            <div class="section-label"><i class="bi bi-chat-text me-1"></i>Observaciones</div>
                            <input type="text" name="observaciones" class="input-jv" placeholder="Notas opcionales...">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid rgba(16,185,129,0.1);">
                        <button type="button" class="btn btn-jv-danger" style="padding:10px 24px;font-size:.85rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="submit" class="btn btn-jv-success" id="btnGuardar" disabled style="padding:10px 24px;font-size:.85rem;" onclick="this.disabled=true;this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span> GUARDANDO...';this.form.submit()"><i class="bi bi-check-lg me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mini Modal: Nuevo Producto -->
    <div class="modal fade" id="modalNuevoProducto" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <div class="p-3" style="border-bottom:1px solid rgba(16,185,129,0.12);">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 font-brand" style="color:#34d399;font-size:.95rem;letter-spacing:-.5px;"><i class="bi bi-box-seam-fill me-2"></i>CREAR PRODUCTO</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="p-3">
                    <input type="hidden" id="np_csrf" value="<?php echo $csrf_token; ?>">
                    <div class="mb-2">
                        <label class="small fw-bold text-secondary mb-1">NOMBRE *</label>
                        <input type="text" class="input-jv" id="np_nombre" required placeholder="Ej: ACEITE DE MOTOR 20W-50" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold text-secondary mb-1">CATEGORÍA *</label>
                        <select class="input-jv" id="np_categoria" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small fw-bold text-secondary mb-1">STOCK MÍNIMO</label>
                            <input type="number" class="input-jv" id="np_stock_minimo" min="0" max="99999" value="5">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-secondary mb-1">ESTADO</label>
                            <select class="input-jv" id="np_status">
                                <option value="Activo" selected>Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold text-secondary mb-1">FECHA VENCIMIENTO <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="date" class="input-jv" id="np_fecha_vencimiento">
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-jv-danger" style="padding:10px 24px;font-size:.85rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="button" class="btn btn-jv-success" id="btnCrearProducto" style="padding:10px 24px;font-size:.85rem;" onclick="crearProducto()"><i class="bi bi-check-lg me-1"></i> Crear</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    let productos = [];
    const modalNP = new bootstrap.Modal(document.getElementById('modalNuevoProducto'));

    function abrirNuevoProducto() {
        document.getElementById('np_nombre').value = '';
        document.getElementById('np_categoria').value = '';
        document.getElementById('np_stock_minimo').value = 5;
        document.getElementById('np_status').value = 'Activo';
        document.getElementById('np_fecha_vencimiento').value = '';
        document.getElementById('np_nombre').focus();
        modalNP.show();
    }

    function crearProducto() {
        const nombre = document.getElementById('np_nombre').value.trim();
        const cat = document.getElementById('np_categoria').value;
        const stockMin = parseInt(document.getElementById('np_stock_minimo').value) || 5;
        const statusVal = document.getElementById('np_status').value;
        const fechaVenc = document.getElementById('np_fecha_vencimiento').value;
        const btn = document.getElementById('btnCrearProducto');

        if (!nombre || !cat) {
            Swal.fire({ title: 'Campos requeridos', text: 'Completa nombre y categoría', icon: 'warning', background: '#0f172a', color: '#fff', confirmButtonColor: '#06b6d4' });
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creando...';

        const formData = new FormData();
        formData.append('csrf_token', document.getElementById('np_csrf').value);
        formData.append('accion_producto', 'crear_ajax');
        formData.append('nombre_producto', nombre);
        formData.append('id_categoria', cat);
        formData.append('stock_minimo', stockMin);
        formData.append('status', statusVal);
        formData.append('fecha_vencimiento', fechaVenc);

        fetch('compras.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const sel = document.getElementById('selProducto');
                    const opt = document.createElement('option');
                    opt.value = res.id;
                    opt.dataset.precio = '0';
                    opt.textContent = res.nombre + ' (Stock: 0)';
                    sel.appendChild(opt);
                    sel.value = res.id;
                    document.getElementById('inputPrecio').value = '';
                    document.getElementById('inputPrecio').focus();
                    modalNP.hide();
                } else {
                    Swal.fire({ title: 'Error', text: res.error || 'No se pudo crear', icon: 'error', background: '#0f172a', color: '#fff', confirmButtonColor: '#06b6d4' });
                }
            })
            .catch(function(err) {
                Swal.fire({ title: 'Error', text: 'Error de conexión: ' + (err.message || 'desconocido'), icon: 'error', background: '#0f172a', color: '#fff', confirmButtonColor: '#06b6d4' });
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Crear';
            });
    }

    document.getElementById('selProveedor').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt && opt.value) {
            const cond = opt.dataset.condicion || 'Contado';
            const dias = opt.dataset.dias || '0';
            document.getElementById('displayCondicion').value = cond;
            document.getElementById('displayDias').value = dias;
        } else {
            document.getElementById('displayCondicion').value = '-';
            document.getElementById('displayDias').value = '-';
        }
    });

    document.getElementById('selProducto').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt && opt.dataset.precio) {
            document.getElementById('inputPrecio').value = parseFloat(opt.dataset.precio).toFixed(2);
        }
    });

    function agregarProducto() {
        const sel = document.getElementById('selProducto');
        const cant = parseInt(document.getElementById('inputCant').value) || 0;
        const precio = parseFloat(document.getElementById('inputPrecio').value) || 0;
        const tipoSel = document.querySelector('select[name="tipo_entrada"]');
        const esDonacion = tipoSel && tipoSel.value === 'Donación';

        if (!sel.value || cant <= 0 || (!esDonacion && precio <= 0)) {
            alert('Seleccione producto, cantidad y precio válidos');
            return;
        }

        const nombre = sel.options[sel.selectedIndex].text.split(' (')[0];

        productos.push({ id: sel.value, nombre: nombre, cantidad: cant, precio: precio, total: cant * precio });
        actualizarTabla();

        sel.value = '';
        document.getElementById('inputCant').value = 1;
        document.getElementById('inputPrecio').value = '';
    }

    function quitarProducto(idx) {
        productos.splice(idx, 1);
        actualizarTabla();
    }

    function actualizarTabla() {
        const body = document.getElementById('productosBody');
        if (productos.length === 0) {
            body.innerHTML = '<tr id="filaVacia"><td colspan="6" style="padding:24px 12px;text-align:center;color:#64748b;font-size:.85rem;border-bottom:1px solid rgba(16,185,129,0.07);">⬆ Use los controles de arriba para agregar productos</td></tr>';
        } else {
            body.innerHTML = '';
            productos.forEach((p, i) => {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td style="padding:8px 10px;color:#64748b;text-align:center;font-size:.85rem;border-bottom:1px solid rgba(16,185,129,0.07);">' + (i + 1) + '</td>' +
                    '<td style="padding:8px 10px;font-size:.85rem;border-bottom:1px solid rgba(16,185,129,0.07);">' + p.nombre + '</td>' +
                    '<td style="padding:8px 10px;font-size:.85rem;text-align:center;border-bottom:1px solid rgba(16,185,129,0.07);">' + p.cantidad + '</td>' +
                    '<td style="padding:8px 10px;font-size:.85rem;text-align:right;color:#94a3b8;border-bottom:1px solid rgba(16,185,129,0.07);">$' + p.precio.toFixed(2) + '</td>' +
                    '<td style="padding:8px 10px;font-size:.85rem;text-align:right;color:#06b6d4;font-weight:700;border-bottom:1px solid rgba(16,185,129,0.07);">$' + p.total.toFixed(2) + '</td>' +
                    '<td style="padding:8px 10px;border-bottom:1px solid rgba(16,185,129,0.07);"><button type="button" class="btn btn-sm border-0" style="padding:0;color:#ef4444;font-size:.8rem;line-height:1;" onclick="quitarProducto(' + i + ')"><i class="bi bi-x-circle"></i></button></td>';
                body.appendChild(tr);
            });
        }
        document.getElementById('totalItems').textContent = productos.length;
        const suma = productos.reduce(function(s, p) { return s + p.total; }, 0);
        document.getElementById('totalCosto').textContent = '$' + suma.toFixed(2);
        document.getElementById('btnGuardar').disabled = productos.length === 0;
        document.getElementById('productosData').value = JSON.stringify(productos);
    }

    function filtrar() {
        const input = document.getElementById('buscar');
        const filter = input.value.toLowerCase();
        const rows = document.getElementById('tablaEntradas').getElementsByTagName('tr');
        for (let i = 0; i < rows.length; i++) {
            rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
        }
    }

    function toggleCamposCompras(sel) {
        const tipo = sel.value;
        const esProv = tipo === 'Compra a proveedor';
        const esDonacion = tipo === 'Donación';
        document.querySelectorAll('.comp-proveedor-section').forEach(el => el.style.display = esProv ? '' : 'none');
        document.querySelectorAll('.comp-factura-section').forEach(el => el.style.display = esProv ? '' : 'none');
        document.querySelectorAll('.comp-motivo-section').forEach(el => el.style.display = esProv ? 'none' : '');
        const provSel = document.getElementById('selProveedor');
        if (!esProv && provSel) provSel.removeAttribute('required');
        if (esProv && provSel) provSel.setAttribute('required', '');
        const facInput = document.querySelector('input[name="nro_factura"]');
        if (facInput) {
            if (esProv) facInput.setAttribute('required', '');
            else facInput.removeAttribute('required');
        }
        const precioInput = document.getElementById('inputPrecio');
        if (precioInput) {
            if (esDonacion) {
                precioInput.value = '0';
                precioInput.readOnly = true;
            } else {
                precioInput.readOnly = false;
            }
        }
    }

    function confirmarEliminar(id) {
        Swal.fire({title:'¿ANULAR?',text:'El stock será revertido del inventario.',icon:'warning',showCancelButton:true,background:'#0f172a',color:'#fff',confirmButtonColor:'#ef4444',cancelButtonColor:'#1e293b',confirmButtonText:'SÍ, ANULAR',cancelButtonText:'CANCELAR'}).then(r => {
            if (r.isConfirmed) window.location.href = 'compras.php?eliminar=' + id;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.flash-auto').forEach(el => {
            setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
        });
        const tipoSel = document.querySelector('select[name="tipo_entrada"]');
        if (tipoSel) toggleCamposCompras(tipoSel);
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
