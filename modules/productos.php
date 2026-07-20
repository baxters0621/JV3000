<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();
$csrf_token = Security::generateToken();

$registros_por_pagina = 30;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$total_registros = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE status = 'Activo'")['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $registros_por_pagina));

$productos = $db->fetchAll(
    "SELECT p.*, c.nombre as nombre_cat,
        (SELECT pr.nombre_empresa FROM detalle_compras dc JOIN compras co ON dc.id_compra = co.id_compra LEFT JOIN proveedores pr ON co.id_proveedor = pr.id_proveedor WHERE dc.id_producto = p.id_producto AND co.status = 'Activa' ORDER BY co.fecha_compra DESC LIMIT 1) as ultimo_proveedor
    FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.status = 'Activo' ORDER BY p.nombre_producto ASC LIMIT ? OFFSET ?",
    [$registros_por_pagina, $offset]
);

// ==========================================
// PROCESAR ACCIONES GET
// ==========================================
$esAdmin = Security::esAdmin();
$id_eliminar = intval($_GET['eliminar'] ?? 0);
$id_baja_vencido = intval($_GET['baja_vencido'] ?? 0);

if ($id_baja_vencido && $esAdmin) {
    $db->execute("UPDATE productos SET status = 'Inactivo', fecha_vencimiento = NULL WHERE id_producto = ?", [$id_baja_vencido]);
    registrarAuditoria('baja_vencido', 'Producto dado de baja por vencimiento');
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PRODUCTO DADO DE BAJA POR VENCIMIENTO.'];
    header('Location: productos.php');
    exit;
}

if ($id_eliminar && $esAdmin) {
    $db->execute("UPDATE productos SET status = 'Inactivo' WHERE id_producto = ?", [$id_eliminar]);
    registrarAuditoria('eliminar', 'Producto desactivado del inventario');
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PRODUCTO DESACTIVADO DEL INVENTARIO.'];
    header('Location: productos.php');
    exit;
}

// ==========================================
// PROCESAR EDICIÓN DE PRODUCTO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_producto' && $esAdmin) {
    $id_prod = intval($_POST['id_producto'] ?? 0);
    $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
    $precio_venta = floatval($_POST['precio_venta'] ?? 0);
    $precio_costo = floatval($_POST['precio_costo'] ?? 0);
    $status = $_POST['status'] ?? 'Activo';
    $fecha_venc = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

    if ($id_prod <= 0) { $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'PRODUCTO INVÁLIDO.']; header('Location: productos.php'); exit; }
    if ($stock_minimo < 0) { $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'STOCK MÍNIMO NO PUEDE SER NEGATIVO.']; header('Location: productos.php'); exit; }
    if ($precio_venta < 0) { $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'PRECIO VENTA NO PUEDE SER NEGATIVO.']; header('Location: productos.php'); exit; }
    if ($precio_costo < 0) { $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'PRECIO COSTO NO PUEDE SER NEGATIVO.']; header('Location: productos.php'); exit; }
    if (!in_array($status, ['Activo','Inactivo'])) $status = 'Activo';
    if ($fecha_venc && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_venc)) $fecha_venc = null;

    $db->execute(
        "UPDATE productos SET stock_minimo=?, precio_venta=?, precio_costo=?, status=?, fecha_vencimiento=? WHERE id_producto=?",
        [$stock_minimo, $precio_venta, $precio_costo, $status, $fecha_venc, $id_prod]
    );
    registrarAuditoria('editar', 'Producto modificado');
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PRODUCTO ACTUALIZADO EN EL INVENTARIO.'];
    header('Location: productos.php');
    exit;
}

// ==========================================
// OBTENER DATOS
// ==========================================
$total_registros = $db->fetchOne("SELECT COUNT(*) as total FROM productos")['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $registros_por_pagina));
$productos = $db->fetchAll(
    "SELECT p.*, c.nombre as nombre_cat,
        (SELECT pr.nombre_empresa FROM detalle_compras dc JOIN compras co ON dc.id_compra = co.id_compra LEFT JOIN proveedores pr ON co.id_proveedor = pr.id_proveedor WHERE dc.id_producto = p.id_producto AND co.status = 'Activa' ORDER BY co.fecha_compra DESC LIMIT 1) as ultimo_proveedor
    FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria ORDER BY p.nombre_producto ASC LIMIT ? OFFSET ?",
    [$registros_por_pagina, $offset]
);

$vencidos_count = (int)$db->fetchOne("SELECT COUNT(*) as t FROM productos WHERE fecha_vencimiento <= CURDATE() AND fecha_vencimiento IS NOT NULL AND status = 'Activo'")['t'];
$proximos_count = (int)$db->fetchOne("SELECT COUNT(*) as t FROM productos WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND fecha_vencimiento IS NOT NULL AND status = 'Activo'")['t'];
?>
<!-- HEAD Y ESTILOS HTML -->
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include '../includes/diseno.php'; ?>
    <title>Inventario | JV3000 C.A.</title>
    <style>
        .table-jv thead { background: #0e7490 !important; }
        .table-jv thead th { background: transparent !important; color: #ffffff !important; font-weight: 900 !important; letter-spacing: 1.2px !important; font-size: 0.8rem !important; padding: 14px 16px !important; border-bottom: 1px solid rgba(255,255,255,0.12) !important; }
        .table-jv tbody td { padding: 16px 16px !important; border-bottom: 1px dashed rgba(56, 189, 248, 0.15) !important; }
        .table-jv tbody tr:last-child td { border-bottom: none !important; }
        .table-jv tbody tr:hover { background: rgba(6, 182, 212, 0.12) !important; }
        .table-jv tbody tr:hover td:first-child { border-left-color: #22d3ee; }
        .table-jv tbody td:first-child { border-left: 3px solid transparent; transition: border-color 0.2s ease; }
        .prod-nombre { font-size: 1rem; font-weight: 800; color: #f1f5f9; }
        .prod-cat { font-size: 0.8rem; color: #22d3ee; font-weight: 700; }
        .prod-prov { font-size: 0.8rem; color: #fbbf24; font-weight: 700; }
        .prod-precio { font-weight: 800; color: #22d3ee; font-size: 0.9rem; }
        .badge-jv { padding: 6px 16px; border-radius: 20px; font-weight: 800; font-size: 0.75rem; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-success { background: rgba(34,197,94,0.18); color: #4ade80; border: 1px solid rgba(34,197,94,0.4); }
        .badge-danger { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid rgba(239,68,68,0.4); }
        .badge-info { background: rgba(34,211,238,0.18); color: #22d3ee; border: 1px solid rgba(34,211,238,0.4); }
        .alert-jv { border-left: 4px solid; border-radius: 8px; padding: 14px 20px !important; font-size: 0.9rem; }
        .alert-jv-success { border-left-color: #22c55e; background: rgba(34,197,94,0.1); }
        .alert-jv-danger { border-left-color: #ef4444; background: rgba(239,68,68,0.1); }
        .buscador-wrapper { border-bottom: 1px solid rgba(56, 189, 248, 0.12); background: rgba(2, 6, 23, 0.5); }
        .buscador-wrapper input { font-size: 0.95rem !important; padding: 10px 8px !important; }
        .buscador-wrapper i { font-size: 1.15rem !important; }
        .card-jv-table { border-top: 4px solid #22d3ee; border-radius: var(--jv-radius) !important; overflow: hidden; }
        .codigo-badge { background: rgba(6,182,212,0.1); border: 1px solid rgba(6,182,212,0.25); border-radius: 6px; padding: 3px 10px; font-size: 0.8rem; font-weight: 700; color: #22d3ee; font-family: 'Courier New', monospace; display: inline-block; }
        .alert-card { border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border: 1px solid; min-height: 64px; }
        .alert-card .alert-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .alert-card .alert-num { font-size: 1.6rem; font-weight: 900; line-height: 1; }
        .alert-card .alert-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .alert-card .alert-link { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; text-decoration: none; padding: 6px 14px; border-radius: 8px; transition: 0.15s; border: 1px solid; }
        .alert-card .alert-link:hover { transform: scale(1.05); }
        .alert-vencida { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.25); }
        .alert-vencida .alert-icon { background: rgba(239,68,68,0.2); color: #f87171; }
        .alert-vencida .alert-num { color: #f87171; }
        .alert-vencida .alert-label { color: rgba(248,113,113,0.8); }
        .alert-vencida .alert-link { background: rgba(239,68,68,0.12); color: #f87171; border-color: rgba(239,68,68,0.3); }
        .alert-vencida .alert-link:hover { background: rgba(239,68,68,0.25); }
        .alert-proxima { background: rgba(251,146,60,0.08); border-color: rgba(251,146,60,0.25); }
        .alert-proxima .alert-icon { background: rgba(251,146,60,0.2); color: #fb923c; }
        .alert-proxima .alert-num { color: #fb923c; }
        .alert-proxima .alert-label { color: rgba(251,146,60,0.8); }
        .alert-proxima .alert-link { background: rgba(251,146,60,0.12); color: #fb923c; border-color: rgba(251,146,60,0.3); }
        .alert-proxima .alert-link:hover { background: rgba(251,146,60,0.25); }
        .input-error { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,0.15) !important; }
    </style>
</head>

<!-- BODY HTML -->
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">

            <!-- Encabezado -->
            <div class="card-jv d-flex align-items-center gap-3 mb-3" style="padding: 18px 24px; border-left: 4px solid #22d3ee;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #0e7490, #155e75); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(6, 182, 212, 0.35);">
                    <i class="bi bi-box-seam text-white" style="font-size: 1.3rem;"></i>
                </div>
                <div>
                    <h1 class="font-brand fw-bold m-0 text-white" style="font-size: 1.4rem;">INVENTARIO</h1>
                    <p class="m-0 text-white opacity-75" style="font-size: 0.85rem;">Control Maestro de Existencias</p>
                </div>
            </div>

            <?php if ($vencidos_count > 0 || $proximos_count > 0): ?>
            <div class="row g-2 mb-3">
                <?php if ($vencidos_count > 0): ?>
                <div class="col-md-6">
                    <div class="alert-card alert-vencida">
                        <div class="d-flex align-items-center gap-3">
                            <div class="alert-icon"><i class="bi bi-x-circle-fill"></i></div>
                            <div>
                                <div class="alert-label">Productos Vencidos</div>
                                <div class="alert-num"><?php echo $vencidos_count; ?></div>
                            </div>
                        </div>
                        <button class="alert-link" onclick="filtrarPorAlerta('vencido')">Ver todos →</button>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($proximos_count > 0): ?>
                <div class="col-md-6">
                    <div class="alert-card alert-proxima">
                        <div class="d-flex align-items-center gap-3">
                            <div class="alert-icon"><i class="bi bi-clock-fill"></i></div>
                            <div>
                                <div class="alert-label">Próximos a Vencer (7 días)</div>
                                <div class="alert-num"><?php echo $proximos_count; ?></div>
                            </div>
                        </div>
                        <button class="alert-link" onclick="filtrarPorAlerta('proximo')">Ver todos →</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Mensajes flash -->
            <?php if (isset($_SESSION['flash_msg'])): ?>
                <div class="alert-jv alert-jv-<?php echo $_SESSION['flash_msg']['tipo']; ?> mb-3 px-3 py-2">
                    <i class="bi bi-<?php echo $_SESSION['flash_msg']['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['flash_msg']['texto']); ?>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <!-- Tabla de productos -->
            <div class="card-jv card-jv-table p-0">
                <div class="buscador-wrapper d-flex align-items-center flex-wrap gap-2 px-3 py-2">
                    <i class="bi bi-search me-1" style="color: #22d3ee; font-size: 1rem;"></i>
                    <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por SKU, nombre o proveedor..." id="buscar" onkeyup="filtrar()" style="box-shadow: none; font-size: 0.85rem; padding: 8px 6px; max-width: 260px;">
                    <span class="actions-divider mx-1"></span>
                    <span class="small fw-bold text-uppercase" style="color:#64748b;font-size:.65rem;letter-spacing:1px;">Vence:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-sm btn-filtro-venc active" data-venc="todas" onclick="filtrarVenc(this)" style="padding:4px 12px;font-size:.7rem;font-weight:700;border-radius:6px 0 0 6px;background:rgba(6,182,212,0.2);color:#22d3ee;border:1px solid rgba(6,182,212,0.3);">Todas</button>
                        <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="vencido" onclick="filtrarVenc(this)" style="padding:4px 12px;font-size:.7rem;font-weight:700;border-radius:0;background:transparent;color:#f87171;border:1px solid rgba(239,68,68,0.3);">Vencidos</button>
                        <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="proximo" onclick="filtrarVenc(this)" style="padding:4px 12px;font-size:.7rem;font-weight:700;border-radius:0;background:transparent;color:#fb923c;border:1px solid rgba(251,146,60,0.3);">Próximo</button>
                        <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="pronto" onclick="filtrarVenc(this)" style="padding:4px 12px;font-size:.7rem;font-weight:700;border-radius:0;background:transparent;color:#fbbf24;border:1px solid rgba(251,191,36,0.3);">Pronto</button>
                        <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="vigente" onclick="filtrarVenc(this)" style="padding:4px 12px;font-size:.7rem;font-weight:700;border-radius:0 6px 6px 0;background:transparent;color:#4ade80;border:1px solid rgba(74,222,128,0.3);">Vigente</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th style="width: 16%;">SKU</th>
                                <th style="width: 26%;">PRODUCTO</th>
                                <th style="width: 11%;">CATEGORÍA</th>
                                <th style="width: 16%;">PROVEEDOR</th>
                                <th style="width: 12%;" class="text-center">STOCK</th>
                                <th style="width: 12%;">PRECIO VENTA</th>
                                <th style="width: 10%;" class="text-center">VENCE</th>
                                <th style="width: 9%;" class="text-center">ESTADO</th>
                                <?php if ($esAdmin): ?>
                                <th style="width: 10%;" class="text-center">ACCIONES</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tablaProductos">
                            <?php if (!empty($productos)): ?>
                                <?php foreach ($productos as $row):
                                    $stk = intval($row['stock_actual']);
                                    $min = intval($row['stock_minimo']);
                                    $max = 100;
                                    if ($stk == 0) { $stk_cls = 'danger'; $stk_lbl = 'AGOTADO'; $stk_pct = 0; }
                                    elseif ($stk <= $min) { $stk_cls = 'danger'; $stk_lbl = 'BAJO'; $stk_pct = max(5, ($stk / $max) * 100); }
                                    elseif ($stk >= $max) { $stk_cls = 'info'; $stk_lbl = 'COMPLETO'; $stk_pct = 100; }
                                    else { $pct = ($stk / $max) * 100; $stk_cls = 'success'; $stk_lbl = 'OK'; $stk_pct = $pct; }
                                    $bar_color = $stk_cls == 'danger' ? '#ef4444' : ($stk_cls == 'info' ? '#22d3ee' : '#4ade80');
                                ?>
                                    <?php
                                        $venc = $row['fecha_vencimiento'] ?? '';
                                        $venc_cls = '';
                                        $vc = 'badge-secondary'; $vt = 'S/V'; $vi = 'dash-circle'; $vd = '';
                                        if ($venc) {
                                            $dias_v = floor((strtotime($venc) - time()) / 86400);
                                            $vd = date('d/m/Y', strtotime($venc));
                                            if ($dias_v < 0) {
                                                $venc_cls = 'vencido'; $vc = 'badge-danger'; $vt = 'VENCIDO'; $vi = 'exclamation-triangle';
                                            } elseif ($dias_v <= 7) {
                                                $venc_cls = 'proximo'; $vc = 'badge-danger'; $vt = 'PRÓXIMO'; $vi = 'clock';
                                            } elseif ($dias_v <= 30) {
                                                $venc_cls = 'pronto'; $vc = 'badge-warning'; $vt = 'PRONTO'; $vi = 'clock';
                                            } else {
                                                $venc_cls = 'vigente'; $vc = 'badge-success'; $vt = 'VIGENTE'; $vi = 'check-circle';
                                            }
                                        }
                                    ?>
                                    <tr data-id="<?php echo $row['id_producto']; ?>" data-sku="<?php echo strtolower(htmlspecialchars($row['sku'])); ?>" data-nombre="<?php echo strtolower(htmlspecialchars($row['nombre_producto'])); ?>" data-prov="<?php echo strtolower(htmlspecialchars($row['ultimo_proveedor'] ?? '')); ?>" data-stock="<?php echo $row['stock_actual']; ?>" data-minimo="<?php echo $row['stock_minimo']; ?>" data-max="<?php echo $max; ?>" data-pvp="<?php echo $row['precio_venta']; ?>" data-costo="<?php echo $row['precio_costo']; ?>" data-status="<?php echo $row['status']; ?>" data-venc="<?php echo $row['fecha_vencimiento'] ?? ''; ?>" data-venc-cls="<?php echo $venc_cls; ?>">
                                        <td>
                                            <span class="codigo-badge"><?php echo htmlspecialchars($row['sku']); ?></span>
                                        </td>
                                        <td>
                                            <span class="prod-nombre text-uppercase"><?php echo htmlspecialchars($row['nombre_producto']); ?></span>
                                        </td>
                                        <td>
                                            <span class="prod-cat"><?php echo htmlspecialchars($row['nombre_cat'] ?? 'Sin categoría'); ?></span>
                                        </td>
                                        <td>
                                            <span class="prod-prov"><?php echo htmlspecialchars($row['ultimo_proveedor'] ?? '—'); ?></span>
                                        </td>
                                        <td class="text-center" style="min-width:110px;">
                                            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                                <span style="font-size:1.2rem;font-weight:900;color:#f1f5f9;line-height:1;"><?php echo $stk; ?></span>
                                                <span class="badge-jv badge-<?php echo $stk_cls; ?>" style="font-size:0.6rem;padding:2px 8px;"><?php echo $stk_lbl; ?></span>
                                            </div>
                                            <div style="height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;margin:0 auto;max-width:100px;">
                                                <div style="height:100%;width:<?php echo $stk_pct; ?>%;background:<?php echo $bar_color; ?>;border-radius:3px;transition:width 0.3s;"></div>
                                            </div>
                                            <div style="font-size:0.6rem;color:#94a3b8;font-weight:600;margin-top:2px;">
                                                Mín: <?php echo $min; ?> · Máx: <?php echo $max; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="prod-precio">$<?php echo number_format($row['precio_venta'], 2); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-jv <?php echo $vc; ?>" style="white-space:nowrap;">
                                                <i class="bi bi-<?php echo $vi; ?>"></i> <?php echo $vd ? "$vd" : $vt; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-jv <?php echo ($row['status'] == 'Activo') ? 'badge-success' : 'badge-danger'; ?>">
                                                <i class="bi bi-<?php echo ($row['status'] == 'Activo') ? 'eye' : 'eye-off'; ?>"></i>
                                                <?php echo strtoupper($row['status']); ?>
                                            </span>
                                        </td>
                                        <?php if ($esAdmin): ?>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-sm p-0" style="width:32px;height:32px;border-radius:8px;background:rgba(6,182,212,0.12);color:#22d3ee;border:1px solid rgba(6,182,212,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:.15s;" onclick="editarProducto(<?php echo $row['id_producto']; ?>)" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm p-0" style="width:32px;height:32px;border-radius:8px;background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:.15s;" onclick="eliminarProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre_producto']); ?>')" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php if ($venc_cls === 'vencido'): ?>
                                                <button type="button" class="btn btn-sm p-0 ms-1" style="width:32px;height:32px;border-radius:8px;background:rgba(100,116,139,0.12);color:#94a3b8;border:1px solid rgba(100,116,139,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:.15s;" onclick="bajaVencido(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre_producto']); ?>')" title="Dar de baja por vencimiento">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $esAdmin ? 9 : 8; ?>" class="text-center py-5">
                                        <i class="bi bi-box-seam d-block mb-3 mx-auto" style="font-size: 3rem; color: rgba(6, 182, 212, 0.5);"></i>
                                        <span class="text-uppercase" style="color: #e2e8f0; font-weight: 700; font-size: 0.95rem;">Inventario vacío</span>
                                        <p class="mt-2" style="color: #94a3b8; font-size: 0.85rem;">Registra entradas desde <strong style="color: #22d3ee;">Compras</strong> para ver productos aquí</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_paginas > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-4" style="border-top: 1px solid var(--jv-border);">
                        <div class="small text-secondary">
                            Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $registros_por_pagina, $total_registros); ?> de <?php echo $total_registros; ?> productos
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm m-0">
                                <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" style="background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);" href="?p=<?php echo $pagina_actual - 1; ?>">Anterior</a>
                                </li>
                                <?php
                                $inicio_p = max(1, $pagina_actual - 2);
                                $fin_p = min($total_paginas, $pagina_actual + 2);
                                for ($i = $inicio_p; $i <= $fin_p; $i++):
                                ?>
                                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                                        <a class="page-link" style="<?php echo ($i == $pagina_actual) ? 'background:var(--jv-cyan); border-color:var(--jv-cyan); color:var(--jv-bg-primary);' : 'background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);'; ?>" href="?p=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                                    <a class="page-link" style="background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);" href="?p=<?php echo $pagina_actual + 1; ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($esAdmin): ?>
    <!-- Modal: Editar producto -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-jv">
                <form method="POST" id="formEditar">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion" value="editar_producto">
                    <input type="hidden" name="id_producto" id="edit_id">
                    <div class="p-3" style="border-bottom:1px solid rgba(6,182,212,0.12);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 font-brand" style="color:#22d3ee;font-size:.95rem;letter-spacing:-.5px;">
                                <i class="bi bi-pencil-square me-2"></i>EDITAR PRODUCTO
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="mb-2">
                            <label class="small fw-bold text-secondary mb-1">PRODUCTO</label>
                            <input type="text" class="input-jv" id="edit_nombre" readonly disabled style="color:#94a3b8;">
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold text-secondary mb-1">SKU</label>
                            <input type="text" class="input-jv" id="edit_sku" readonly disabled style="color:#94a3b8;">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">STOCK ACTUAL</label>
                                <input type="text" class="input-jv" id="edit_stock" readonly disabled style="color:#94a3b8;">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">STOCK MÍNIMO</label>
                                <input type="number" class="input-jv" id="edit_minimo" name="stock_minimo" min="0" max="99999">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">PRECIO VENTA ($)</label>
                                <input type="number" class="input-jv" id="edit_pvp" name="precio_venta" step="0.01" min="0" max="999999">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">PRECIO COSTO ($)</label>
                                <input type="number" class="input-jv" id="edit_costo" name="precio_costo" step="0.01" min="0" max="999999">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">ESTADO</label>
                                <select class="input-jv" id="edit_status" name="status">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-secondary mb-1">VENCIMIENTO</label>
                                <input type="date" class="input-jv" id="edit_vencimiento" name="fecha_vencimiento">
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid rgba(6,182,212,0.1);">
                        <button type="button" class="btn btn-jv-danger" style="padding:8px 20px;font-size:.8rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="button" class="btn btn-jv-success" style="padding:8px 20px;font-size:.8rem;" onclick="return validarEditarProducto(this)"><i class="bi bi-check-lg me-1"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function filtrarPorAlerta(clase) {
            var btn = document.querySelector('.btn-filtro-venc[data-venc="' + clase + '"]');
            if (btn) filtrarVenc(btn);
        }

        function filtrarVenc(btn) {
        document.querySelectorAll('.btn-filtro-venc').forEach(function(b) {
            b.style.background = 'transparent';
            b.style.color = b.dataset.venc === 'vencido' ? '#f87171' : b.dataset.venc === 'proximo' ? '#fb923c' : b.dataset.venc === 'pronto' ? '#fbbf24' : b.dataset.venc === 'vigente' ? '#4ade80' : '#22d3ee';
        });
        btn.style.background = 'rgba(6,182,212,0.2)';
        btn.style.color = '#22d3ee';
        var filtro = btn.getAttribute('data-venc');
        var rows = document.getElementById('tablaProductos').getElementsByTagName('tr');
        for (var i = 0; i < rows.length; i++) {
            var vc = rows[i].getAttribute('data-venc-cls') || '';
            if (filtro === 'todas') { rows[i].style.display = ''; }
            else { rows[i].style.display = vc === filtro ? '' : 'none'; }
        }
    }

    function bajaVencido(id, nombre) {
        Swal.fire({
            title: '¿DAR DE BAJA?',
            html: 'Se marcará como <strong>Inactivo</strong> por vencimiento: ' + nombre,
            icon: 'warning',
            showCancelButton: true,
            background: '#0f172a',
            color: '#fff',
            confirmButtonColor: '#64748b',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'SÍ, DAR DE BAJA',
            cancelButtonText: 'CANCELAR'
        }).then(function(r) {
            if (r.isConfirmed) window.location.href = 'productos.php?baja_vencido=' + id;
        });
    }

    function filtrar() {
            const input = document.getElementById('buscar');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('tablaProductos').getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const sku = rows[i].getAttribute('data-sku') || '';
                const nombre = rows[i].getAttribute('data-nombre') || '';
                const prov = rows[i].getAttribute('data-prov') || '';
                rows[i].style.display = (sku.includes(filter) || nombre.includes(filter) || prov.includes(filter)) ? '' : 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-jv');
            alerts.forEach(function(a) {
                setTimeout(function() {
                    a.style.transition = 'opacity 0.6s';
                    a.style.opacity = '0';
                    setTimeout(function() { a.remove(); }, 600);
                }, 4000);
            });
            document.querySelectorAll('#formEditar input, #formEditar select').forEach(function(el) {
                el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
                el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
            });
        });

        var modalEditar = null;
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('modalEditar');
            if (el) modalEditar = new bootstrap.Modal(el);
        });

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
        function validarEditarProducto(btn) {
            limpiarErrores();
            let primerError = null;
            const minimo = document.getElementById('edit_minimo');
            const pvp = document.getElementById('edit_pvp');
            const costo = document.getElementById('edit_costo');
            if (parseInt(minimo.value) < 0) { marcarError(minimo, '>= 0'); if (!primerError) primerError = minimo; }
            if (parseFloat(pvp.value) < 0) { marcarError(pvp, '>= 0'); if (!primerError) primerError = pvp; }
            if (parseFloat(costo.value) < 0) { marcarError(costo, '>= 0'); if (!primerError) primerError = costo; }
            if (primerError) { primerError.focus(); return false; }
            btn.disabled = true; btn.innerHTML = '<span class=\'spinner-border spinner-border-sm me-1\'></span>GUARDANDO...';
            btn.form.submit(); return false;
        }
        function editarProducto(id) {
            limpiarErrores();
            var row = document.querySelector('tr[data-id="' + id + '"]');
            if (!row) return;
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = row.getAttribute('data-nombre').toUpperCase();
            document.getElementById('edit_sku').value = row.getAttribute('data-sku');
            document.getElementById('edit_stock').value = row.getAttribute('data-stock');
            document.getElementById('edit_minimo').value = row.getAttribute('data-minimo');
            document.getElementById('edit_pvp').value = parseFloat(row.getAttribute('data-pvp')).toFixed(2);
            document.getElementById('edit_costo').value = parseFloat(row.getAttribute('data-costo')).toFixed(2);
            document.getElementById('edit_status').value = row.getAttribute('data-status');
            document.getElementById('edit_vencimiento').value = row.getAttribute('data-venc');
            if (modalEditar) modalEditar.show();
        }

        function eliminarProducto(id, nombre) {
            Swal.fire({
                title: '¿DESACTIVAR?',
                text: 'Se desactivará "' + nombre + '" del inventario.',
                icon: 'warning',
                showCancelButton: true,
                background: '#0f172a',
                color: '#fff',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#1e293b',
                confirmButtonText: 'SÍ, DESACTIVAR',
                cancelButtonText: 'CANCELAR'
            }).then(function(r) {
                if (r.isConfirmed) {
                    window.location.href = 'productos.php?eliminar=' + id;
                }
            });
        }
    </script>
</body>

</html>
