<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();
$csrf_token = Security::generateToken();

$registros_por_pagina = 30;
if (isset($_GET['producto']) || isset($_GET['alerta'])) $registros_por_pagina = 1000;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// ==========================================
// PROCESAR ACCIONES POST
// ==========================================
$esAdmin = Security::esAdmin();
$id_toggle = intval($_POST['toggle'] ?? 0);
$id_baja_vencido = intval($_POST['baja_vencido'] ?? 0);

if ($id_toggle && $esAdmin) {
    $p = $db->fetchOne("SELECT status FROM productos WHERE id_producto = ?", [$id_toggle]);
    if ($p) {
        $nuevo = $p['status'] === 'Activo' ? 'Inactivo' : 'Activo';
        $db->execute("UPDATE productos SET status = ? WHERE id_producto = ?", [$nuevo, $id_toggle]);
        $accion = $nuevo === 'Activo' ? 'REACTIVADO' : 'DESACTIVADO';
        registrarAuditoria(strtolower($accion), "Producto $accion");
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => "PRODUCTO $accion."];
    }
    header('Location: productos.php');
    exit;
}

if ($id_baja_vencido && $esAdmin) {
    $db->execute("UPDATE lotes SET cantidad_restante = 0 WHERE id_producto = ? AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= CURDATE()", [$id_baja_vencido]);
    $db->execute(
        "UPDATE productos p SET p.status = 'Inactivo', p.stock_actual = (
            SELECT COALESCE(SUM(l.cantidad_restante), 0) FROM lotes l WHERE l.id_producto = p.id_producto
         ) WHERE p.id_producto = ?",
        [$id_baja_vencido]
    );
    registrarAuditoria('baja_vencido', 'Producto dado de baja por vencimiento');
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PRODUCTO DADO DE BAJA POR VENCIMIENTO. LOTES VENCIDOS PUESTOS EN CERO.'];
    header('Location: productos.php');
    exit;
}

// ==========================================
// PROCESAR EDICIÓN DE PRODUCTO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_producto' && $esAdmin) {
    $id_prod = intval($_POST['id_producto'] ?? 0);
    $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
    $stock_maximo = intval($_POST['stock_maximo'] ?? 0);
    $precio_venta = floatval($_POST['precio_venta'] ?? 0);
    $precio_costo = floatval($_POST['precio_costo'] ?? 0);
    $status = $_POST['status'] ?? 'Activo';
    $fecha_venc = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

    if ($id_prod <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'PRODUCTO INVÁLIDO.'];
        header('Location: productos.php');
        exit;
    }
    if ($stock_minimo <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'STOCK MÍNIMO DEBE SER MAYOR A 0.'];
        header('Location: productos.php');
        exit;
    }
    if ($stock_maximo < 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'CAPACIDAD MÁXIMA NO PUEDE SER NEGATIVA.'];
        header('Location: productos.php');
        exit;
    }
    if ($stock_maximo > 0 && $stock_maximo < $stock_minimo) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'LA CAPACIDAD MÁXIMA DEBE SER MAYOR O IGUAL AL STOCK MÍNIMO (O 0 PARA HEREDAR LA DE LA CATEGORÍA).'];
        header('Location: productos.php');
        exit;
    }
    if ($precio_venta <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'PRECIO VENTA DEBE SER MAYOR A 0.'];
        header('Location: productos.php');
        exit;
    }
    if ($precio_costo <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'PRECIO COSTO DEBE SER MAYOR A 0.'];
        header('Location: productos.php');
        exit;
    }
    if (!in_array($status, ['Activo', 'Inactivo'])) $status = 'Activo';
    if ($fecha_venc && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_venc)) $fecha_venc = null;

    $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
    if ($id_proveedor <= 0) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'DEBE SELECCIONAR UN PROVEEDOR.'];
        header('Location: productos.php');
        exit;
    }

    $db->execute(
        "UPDATE productos SET stock_minimo=?, stock_maximo=?, precio_venta=?, precio_costo=?, status=?, fecha_vencimiento=?, id_proveedor=? WHERE id_producto=?",
        [$stock_minimo, $stock_maximo, $precio_venta, $precio_costo, $status, $fecha_venc, $id_proveedor, $id_prod]
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
$total_activos = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE status = 'Activo'")['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $registros_por_pagina));
$productos = $db->fetchAll(
    "SELECT p.*, c.nombre as nombre_cat, COALESCE(NULLIF(p.stock_maximo,0), c.stock_maximo, 100) as capacidad,
        COALESCE(pr.nombre_empresa, (
            SELECT pr2.nombre_empresa FROM detalle_compras dc JOIN compras co ON dc.id_compra = co.id_compra LEFT JOIN proveedores pr2 ON co.id_proveedor = pr2.id_proveedor WHERE dc.id_producto = p.id_producto AND co.status = 'Activa' ORDER BY co.fecha_compra DESC LIMIT 1
        )) as ultimo_proveedor
    FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor ORDER BY p.status DESC, p.nombre_producto ASC LIMIT ? OFFSET ?",
    [$registros_por_pagina, $offset]
);

$vencidos_count = (int)$db->fetchOne("SELECT COUNT(*) as t FROM productos WHERE fecha_vencimiento <= CURDATE() AND fecha_vencimiento IS NOT NULL AND status = 'Activo'")['t'];
$proximos_count = (int)$db->fetchOne("SELECT COUNT(*) as t FROM productos WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND fecha_vencimiento IS NOT NULL AND status = 'Activo'")['t'];
$proveedores_list = $db->fetchAll("SELECT id_proveedor, nombre_empresa FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa ASC");
?>
<!-- HEAD Y ESTILOS HTML -->
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include '../includes/diseno.php'; ?>
    <title>Inventario | JV3000 C.A.</title>
        <link rel="stylesheet" href="../assets/modules/productos/productos.css?v=7">
</head>

<!-- BODY HTML -->

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">

            <!-- Encabezado -->
            <div class="card-jv d-flex align-items-center gap-3 mb-3" style="padding: 18px 24px; border-left: 4px solid var(--jv-orange);">
                <div style="width: 56px; height: 56px; border-radius: 12px; background: var(--jv-navy); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(15, 26, 46, 0.25);">
                    <i class="bi bi-box-seam text-white" style="font-size: 1.5rem;"></i>
                </div>
                <div>
                    <h1 class="font-brand fw-bold m-0" style="font-size: 2rem; color: var(--jv-text-primary);">INVENTARIO</h1>
                    <p class="m-0 text-secondary" style="font-size: 1rem;">Control Maestro de Existencias</p>
                </div>
            </div>

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
                    <i class="bi bi-search me-1" style="color: var(--jv-orange);"></i>
                    <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por nombre, código, proveedor, categoría, estado..." id="buscar" onkeyup="filtrar()" style="box-shadow: none; max-width: 340px;">
                    <span class="actions-divider mx-1"></span>
                    <span class="small fw-bold text-uppercase" style="color:var(--jv-text-muted);font-size:.8rem;letter-spacing:1px;">Estado:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn-filter-prod active" data-status="todas" onclick="filtrarStatus(this)">Todos</button>
                        <button type="button" class="btn-filter-prod" data-status="Activo" onclick="filtrarStatus(this)">Activos</button>
                        <button type="button" class="btn-filter-prod" data-status="Inactivo" onclick="filtrarStatus(this)">Inactivos</button>
                    </div>
                    <span class="actions-divider mx-1"></span>
                    <span class="small fw-bold text-uppercase" style="color:var(--jv-text-muted);font-size:.8rem;letter-spacing:1px;">Vence:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-sm btn-filtro-venc active" data-venc="todas" onclick="filtrarVenc(this)" style="border-radius:6px 0 0 6px;background:rgba(234,88,12,0.15);color:var(--jv-orange);border:1px solid rgba(234,88,12,0.3);">Todas</button>
                        <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="vencido" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-danger);border:1px solid rgba(220,38,38,0.3);">Vencidos</button>
                        <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="proximo" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-warning);border:1px solid rgba(217,119,6,0.3);">Próximo</button>
                        <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="pronto" onclick="filtrarVenc(this)" style="border-radius:0;background:transparent;color:var(--jv-warning);border:1px solid rgba(217,119,6,0.3);">Pronto</button>
                        <button type="button" class="btn btn-sm btn-filtro-venc" data-venc="vigente" onclick="filtrarVenc(this)" style="border-radius:0 6px 6px 0;background:transparent;color:var(--jv-success);border:1px solid rgba(22,163,74,0.3);">Vigente</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:9%;">CÓDIGO</th>
                                <th style="width:22%;">PRODUCTO</th>
                                <th style="width:13%;">CATEGORÍA</th>
                                <th style="width:14%;">PROVEEDOR</th>
                                <th class="text-center" style="width:8%;">STOCK</th>
                                <th style="width:9%;">PRECIO</th>
                                <th class="text-center" style="width:7%;">VENCE</th>
                                <th class="text-center" style="width:8%;">ESTADO</th>
                                <?php if ($esAdmin): ?>
                                    <th class="text-center" style="width:10%;">ACCIONES</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tablaProductos">
                            <?php if (!empty($productos)): ?>
                                <?php foreach ($productos as $row):
                                    $stk = intval($row['stock_actual']);
                                    $min = intval($row['stock_minimo']);
                                    $max = max(1, intval($row['capacidad'] ?? 100));
                                    if ($stk == 0) {
                                        $stk_cls = 'danger';
                                        $stk_lbl = 'AGOTADO';
                                        $stk_pct = 0;
                                    } elseif ($stk <= $min) {
                                        $stk_cls = 'danger';
                                        $stk_lbl = 'BAJO';
                                        $stk_pct = max(5, ($stk / $max) * 100);
                                    } elseif ($stk >= $max) {
                                        $stk_cls = 'info';
                                        $stk_lbl = 'COMPLETO';
                                        $stk_pct = 100;
                                    } else {
                                        $pct = ($stk / $max) * 100;
                                        $stk_cls = 'success';
                                        $stk_lbl = 'OK';
                                        $stk_pct = $pct;
                                    }
                                    $bar_color = $stk_cls == 'danger' ? '#DC2626' : ($stk_cls == 'info' ? '#2563EB' : '#16A34A');
                                ?>
                                    <?php
                                    $venc = $row['fecha_vencimiento'] ?? '';
                                    $venc_cls = '';
                                    $vc = 'badge-secondary';
                                    $vi = 'dash-circle';
                                    $vd = '';
                                    if ($venc) {
                                        $dias_v = floor((strtotime($venc) - time()) / 86400);
                                        $vd = date('d/m/Y', strtotime($venc));
                                        if ($dias_v < 0) {
                                            $venc_cls = 'vencido';
                                            $vc = 'badge-danger';
                                            $vi = 'exclamation-triangle';
                                        } elseif ($dias_v <= 7) {
                                            $venc_cls = 'proximo';
                                            $vc = 'badge-danger';
                                            $vi = 'clock';
                                        } elseif ($dias_v <= 30) {
                                            $venc_cls = 'pronto';
                                            $vc = 'badge-warning';
                                            $vi = 'clock';
                                        } else {
                                            $venc_cls = 'vigente';
                                            $vc = 'badge-success';
                                            $vi = 'check-circle';
                                        }
                                    }
                                    ?>
                                    <tr data-id="<?php echo $row['id_producto']; ?>" data-sku="<?php echo strtolower(htmlspecialchars($row['sku'])); ?>" data-nombre="<?php echo strtolower(htmlspecialchars($row['nombre_producto'])); ?>" data-prov="<?php echo strtolower(htmlspecialchars($row['ultimo_proveedor'] ?? '')); ?>" data-prov-id="<?php echo intval($row['id_proveedor'] ?? 0); ?>" data-stock="<?php echo $row['stock_actual']; ?>" data-minimo="<?php echo $row['stock_minimo']; ?>" data-max="<?php echo $max; ?>" data-maximo="<?php echo intval($row['stock_maximo'] ?? 0); ?>" data-pvp="<?php echo $row['precio_venta']; ?>" data-costo="<?php echo $row['precio_costo']; ?>" data-status="<?php echo $row['status']; ?>" data-venc="<?php echo $row['fecha_vencimiento'] ?? ''; ?>" data-venc-cls="<?php echo $venc_cls; ?>">
                                        <td class="td-prod-sku">
                                            <span class="codigo-badge"><?php echo htmlspecialchars($row['sku']); ?></span>
                                        </td>
                                        <td class="td-prod-nombre" data-tooltip="<?php echo htmlspecialchars($row['nombre_producto']); ?>">
                                            <span class="prod-nombre text-uppercase"><?php echo htmlspecialchars($row['nombre_producto']); ?></span>
                                        </td>
                                        <td class="td-prod-cat" data-tooltip="<?php echo htmlspecialchars($row['nombre_cat'] ?? 'Sin categoría'); ?>">
                                            <span class="prod-cat"><?php echo htmlspecialchars($row['nombre_cat'] ?? 'Sin categoría'); ?></span>
                                        </td>
                                        <td class="td-prod-prov" data-tooltip="<?php echo htmlspecialchars($row['ultimo_proveedor'] ?? '—'); ?>">
                                            <span class="prod-prov"><?php echo htmlspecialchars($row['ultimo_proveedor'] ?? '—'); ?></span>
                                        </td>
                                        <td class="td-stock text-center">
                                            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                                <span class="stk-num"><?php echo $stk; ?></span>
                                                <span class="badge-jv badge-<?php echo $stk_cls; ?>" style="font-size:0.75rem;padding:3px 10px;"><?php echo $stk_lbl; ?></span>
                                            </div>
                                            <div style="height:6px;background:rgba(15,26,46,0.08);border-radius:3px;overflow:hidden;margin:0 auto;max-width:100px;">
                                                <div style="height:100%;width:<?php echo $stk_pct; ?>%;background:<?php echo $bar_color; ?>;border-radius:3px;transition:width 0.3s;"></div>
                                            </div>
                                            <div class="stk-meta">
                                                Mín: <?php echo $min; ?> · Máx: <?php echo $max; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="prod-precio">$<?php echo number_format($row['precio_venta'], 2); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-jv <?php echo $vc; ?>" style="white-space:nowrap;font-size:.85rem;">
                                                <i class="bi bi-<?php echo $vi; ?>"></i> <?php echo $vd ?: '—'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-jv <?php echo ($row['status'] == 'Activo') ? 'badge-success' : 'badge-danger'; ?>" style="font-size:.85rem;">
                                                <i class="bi bi-<?php echo ($row['status'] == 'Activo') ? 'eye' : 'eye-off'; ?>"></i>
                                                <?php echo strtoupper($row['status']); ?>
                                            </span>
                                        </td>
                                        <?php if ($esAdmin): ?>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button type="button" class="btn btn-sm p-0" style="width:38px;height:38px;border-radius:8px;background:rgba(234,88,12,0.12);color:var(--jv-orange);border:1px solid rgba(234,88,12,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="editarProducto(<?php echo $row['id_producto']; ?>)" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($row['status'] === 'Activo'): ?>
                                                        <button type="button" class="btn btn-sm p-0" style="width:38px;height:38px;border-radius:8px;background:rgba(220,38,38,0.12);color:var(--jv-danger);border:1px solid rgba(220,38,38,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="toggleProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre_producto']); ?>', 'desactivar')" title="Desactivar">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                        <?php if ($venc_cls === 'vencido'): ?>
                                                            <button type="button" class="btn btn-sm p-0 ms-1" style="width:38px;height:38px;border-radius:8px;background:rgba(100,116,139,0.12);color:var(--jv-text-muted);border:1px solid rgba(100,116,139,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="bajaVencido(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre_producto']); ?>')" title="Dar de baja por vencimiento">
                                                                <i class="bi bi-archive"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm p-0" style="width:38px;height:38px;border-radius:8px;background:rgba(22,163,74,0.12);color:var(--jv-success);border:1px solid rgba(22,163,74,0.25);display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;transition:.15s;" onclick="toggleProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre_producto']); ?>', 'activar')" title="Reactivar">
                                                            <i class="bi bi-play-circle"></i>
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
                                        <i class="bi bi-box-seam d-block mb-3 mx-auto" style="font-size: 3.5rem; color: var(--jv-text-muted);"></i>
                                        <span class="text-uppercase" style="color: var(--jv-text-primary); font-weight: 700; font-size: 1.1rem;">Inventario vacío</span>
                                        <p class="mt-2" style="color: var(--jv-text-muted); font-size: 1rem;">Registra entradas desde <strong style="color: var(--jv-orange);">Compras</strong> para ver productos aquí</p>
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
                                        <a class="page-link" style="<?php echo ($i == $pagina_actual) ? 'background:var(--jv-orange); border-color:var(--jv-orange); color:#fff;' : 'background:var(--jv-bg-primary); border:1px solid var(--jv-border); color:var(--jv-text-primary);'; ?>" href="?p=<?php echo $i; ?>"><?php echo $i; ?></a>
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
                        <div class="p-3" style="border-bottom:1px solid var(--jv-border);">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0 font-brand" style="color:var(--jv-navy);font-size:.95rem;letter-spacing:-.5px;">
                                    <i class="bi bi-pencil-square me-2"></i>EDITAR PRODUCTO
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                        </div>
                        <div class="p-3">
                            <div class="mb-2">
                                <label class="small fw-bold text-secondary mb-1">PRODUCTO</label>
                                <input type="text" class="input-jv" id="edit_nombre" readonly disabled style="color:var(--jv-text-muted);">
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold text-secondary mb-1">Código</label>
                                <input type="text" class="input-jv" id="edit_sku" readonly disabled style="color:var(--jv-text-muted);">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-4">
                                    <label class="small fw-bold text-secondary mb-1">STOCK ACTUAL</label>
                                    <input type="text" class="input-jv" id="edit_stock" readonly disabled style="color:var(--jv-text-muted);">
                                </div>
                                <div class="col-4">
                                    <label class="small fw-bold text-secondary mb-1">STOCK MÍNIMO</label>
                                    <input type="number" class="input-jv" id="edit_minimo" name="stock_minimo" min="0" max="99999">
                                </div>
                                <div class="col-4">
                                    <label class="small fw-bold text-secondary mb-1">CAPACIDAD MÁX. <span class="text-jv-muted" style="font-weight:400;">(0 = categoría)</span></label>
                                    <input type="number" class="input-jv" id="edit_maximo" name="stock_maximo" min="0" max="999999">
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
                                <div class="col-4">
                                    <label class="small fw-bold text-secondary mb-1">ESTADO</label>
                                    <select class="input-jv" id="edit_status" name="status">
                                        <option value="Activo">Activo</option>
                                        <option value="Inactivo">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="small fw-bold text-secondary mb-1">VENCIMIENTO</label>
                                    <input type="date" class="input-jv" id="edit_vencimiento" name="fecha_vencimiento">
                                </div>
                                <div class="col-4">
                                    <label class="small fw-bold text-secondary mb-1">PROVEEDOR <span style="color:var(--jv-danger);">*</span></label>
                                    <select class="input-jv" id="edit_proveedor" name="id_proveedor" required>
                                        <option value="">SELECCIONE...</option>
                                        <?php foreach ($proveedores_list as $prov): ?>
                                            <option value="<?php echo $prov['id_proveedor']; ?>"><?php echo htmlspecialchars($prov['nombre_empresa']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid var(--jv-border);">
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
    window.JV_CONFIG = { c0: '<?php echo $csrf_token; ?>', c1: '<?php echo $csrf_token; ?>' };
</script>
    <script src="../assets/modules/productos/productos.js?v=3"></script>
</body>

</html>