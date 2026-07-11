<?php
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
    "SELECT p.*, c.nombre as nombre_cat FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.status = 'Activo' ORDER BY p.nombre_producto ASC LIMIT ? OFFSET ?",
    [$registros_por_pagina, $offset]
);

$esAdmin = Security::esAdmin();
$id_eliminar = intval($_GET['eliminar'] ?? 0);

if ($id_eliminar) {
    if (!$esAdmin) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'SOLO EL ADMINISTRADOR PUEDE DESACTIVAR PRODUCTOS.'];
        header('Location: productos.php');
        exit;
    }
    $db->execute("UPDATE productos SET status = 'Inactivo' WHERE id_producto = ?", [$id_eliminar]);
    registrarAuditoria('eliminar', 'Producto desactivado del inventario');
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PRODUCTO DESACTIVADO DEL INVENTARIO.'];
    header('Location: productos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_producto' && $esAdmin) {
    $id_prod = intval($_POST['id_producto'] ?? 0);
    $stock_minimo = intval($_POST['stock_minimo'] ?? 5);
    $precio_venta = floatval($_POST['precio_venta'] ?? 0);
    $precio_costo = floatval($_POST['precio_costo'] ?? 0);
    $status = $_POST['status'] ?? 'Activo';
    $fecha_venc = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;

    $db->execute(
        "UPDATE productos SET stock_minimo=?, precio_venta=?, precio_costo=?, status=?, fecha_vencimiento=? WHERE id_producto=?",
        [$stock_minimo, $precio_venta, $precio_costo, $status, $fecha_venc, $id_prod]
    );
    registrarAuditoria('editar', 'Producto modificado');
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PRODUCTO ACTUALIZADO EN EL INVENTARIO.'];
    header('Location: productos.php');
    exit;
}

// Re-query after potential edit/delete
$total_registros = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE status = 'Activo'")['total'] ?? 0;
$total_paginas = max(1, ceil($total_registros / $registros_por_pagina));
$productos = $db->fetchAll(
    "SELECT p.*, c.nombre as nombre_cat FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.status = 'Activo' ORDER BY p.nombre_producto ASC LIMIT ? OFFSET ?",
    [$registros_por_pagina, $offset]
);
?>
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
        .prod-cat { font-size: 0.75rem; color: #94a3b8; font-weight: 600; }
        .prod-precio { font-weight: 800; color: #22d3ee; font-size: 0.9rem; }
        .badge-jv { padding: 6px 16px; border-radius: 20px; font-weight: 800; font-size: 0.75rem; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 6px; }
        .badge-success { background: rgba(34,197,94,0.18); color: #4ade80; border: 1px solid rgba(34,197,94,0.4); }
        .badge-danger { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid rgba(239,68,68,0.4); }
        .alert-jv { border-left: 4px solid; border-radius: 8px; padding: 14px 20px !important; font-size: 0.9rem; }
        .alert-jv-success { border-left-color: #22c55e; background: rgba(34,197,94,0.1); }
        .alert-jv-danger { border-left-color: #ef4444; background: rgba(239,68,68,0.1); }
        .buscador-wrapper { border-bottom: 1px solid rgba(56, 189, 248, 0.12); background: rgba(2, 6, 23, 0.5); }
        .buscador-wrapper input { font-size: 0.95rem !important; padding: 10px 8px !important; }
        .buscador-wrapper i { font-size: 1.15rem !important; }
        .card-jv-table { border-top: 4px solid #22d3ee; border-radius: var(--jv-radius) !important; overflow: hidden; }
        .codigo-badge { background: rgba(6,182,212,0.1); border: 1px solid rgba(6,182,212,0.25); border-radius: 6px; padding: 3px 10px; font-size: 0.8rem; font-weight: 700; color: #22d3ee; font-family: 'Courier New', monospace; display: inline-block; }
        .stock-badge { min-width: 44px; text-align: center; font-weight: 900; font-size: 0.85rem; }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">

            <div class="card-jv d-flex align-items-center gap-3 mb-3" style="padding: 18px 24px; border-left: 4px solid #22d3ee;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #0e7490, #155e75); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(6, 182, 212, 0.35);">
                    <i class="bi bi-box-seam text-white" style="font-size: 1.3rem;"></i>
                </div>
                <div>
                    <h1 class="font-brand fw-bold m-0 text-white" style="font-size: 1.4rem;">INVENTARIO</h1>
                    <p class="m-0 text-white opacity-75" style="font-size: 0.85rem;">Control Maestro de Existencias</p>
                </div>
            </div>

            <?php if (isset($_SESSION['flash_msg'])): ?>
                <div class="alert-jv alert-jv-<?php echo $_SESSION['flash_msg']['tipo']; ?> mb-3 px-3 py-2">
                    <i class="bi bi-<?php echo $_SESSION['flash_msg']['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $_SESSION['flash_msg']['texto']; ?>
                </div>
                <?php unset($_SESSION['flash_msg']); ?>
            <?php endif; ?>

            <div class="card-jv card-jv-table p-0">
                <div class="buscador-wrapper d-flex align-items-center px-3 py-2">
                    <i class="bi bi-search me-2" style="color: #22d3ee; font-size: 1rem;"></i>
                    <input type="text" class="input-jv border-0 bg-transparent py-1" placeholder="Buscar por SKU o nombre..." id="buscar" onkeyup="filtrar()" style="box-shadow: none; font-size: 0.85rem; padding: 8px 6px;">
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th style="width: 16%;">SKU</th>
                                <th style="width: 26%;">PRODUCTO</th>
                                <th style="width: 15%;">CATEGORÍA</th>
                                <th style="width: 12%;" class="text-center">STOCK</th>
                                <th style="width: 12%;">P. VENTA</th>
                                <th style="width: 10%;">VENCE</th>
                                <th style="width: 9%;" class="text-center">ESTADO</th>
                                <?php if ($esAdmin): ?>
                                <th style="width: 10%;" class="text-center">ACCIONES</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tablaProductos">
                            <?php if (!empty($productos)): ?>
                                <?php foreach ($productos as $row):
                                    $is_low = ($row['stock_actual'] <= $row['stock_minimo']);
                                ?>
                                    <tr data-id="<?php echo $row['id_producto']; ?>" data-sku="<?php echo strtolower(htmlspecialchars($row['sku'])); ?>" data-nombre="<?php echo strtolower(htmlspecialchars($row['nombre_producto'])); ?>" data-stock="<?php echo $row['stock_actual']; ?>" data-minimo="<?php echo $row['stock_minimo']; ?>" data-pvp="<?php echo $row['precio_venta']; ?>" data-costo="<?php echo $row['precio_costo']; ?>" data-status="<?php echo $row['status']; ?>" data-venc="<?php echo $row['fecha_vencimiento'] ?? ''; ?>">
                                        <td>
                                            <span class="codigo-badge"><?php echo htmlspecialchars($row['sku']); ?></span>
                                        </td>
                                        <td>
                                            <span class="prod-nombre text-uppercase"><?php echo htmlspecialchars($row['nombre_producto']); ?></span>
                                        </td>
                                        <td>
                                            <span class="prod-cat"><?php echo htmlspecialchars($row['nombre_cat'] ?? 'Sin categoría'); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="stock-badge badge-jv <?php echo $is_low ? 'badge-danger' : 'badge-success'; ?>">
                                                <?php echo $row['stock_actual']; ?>
                                            </span>
                                            <?php if ($is_low): ?>
                                                <span class="d-block mt-1" style="font-size: 0.6rem; color: #f87171; font-weight: 700;">MÍN</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="prod-precio">$<?php echo number_format($row['precio_venta'], 2); ?></span>
                                        </td>
                                        <td class="prod-cat">
                                            <?php
                                            $venc = $row['fecha_vencimiento'] ?? '';
                                            if ($venc) {
                                                $dias = floor((strtotime($venc) - time()) / 86400);
                                                if ($dias < 0) {
                                                    $vc = 'badge-danger'; $vt = 'VENCIDO'; $vi = 'exclamation-triangle';
                                                } elseif ($dias <= 15) {
                                                    $vc = 'badge-danger'; $vt = 'PRÓXIMO'; $vi = 'clock';
                                                } elseif ($dias <= 30) {
                                                    $vc = 'badge-warning'; $vt = 'PRONTO'; $vi = 'clock';
                                                } else {
                                                    $vc = 'badge-success'; $vt = 'VIGENTE'; $vi = 'check-circle';
                                                }
                                                $vd = date('d/m/Y', strtotime($venc));
                                            } else {
                                                $vc = 'badge-secondary'; $vt = 'S/V'; $vi = 'dash-circle'; $vd = '';
                                            }
                                            ?>
                                            <span class="badge-jv <?php echo $vc; ?>">
                                                <i class="bi bi-<?php echo $vi; ?>"></i> <?php echo $vt; ?>
                                            </span>
                                            <?php if ($vd): ?>
                                            <span class="d-block mt-1" style="font-size:.72rem;color:#cbd5e1;font-weight:600;"><?php echo $vd; ?></span>
                                            <?php endif; ?>
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
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $esAdmin ? 8 : 7; ?>" class="text-center py-5">
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
    <!-- Modal Editar Producto -->
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
                                <label class="small fw-bold text-secondary mb-1">FECHA VENCIMIENTO</label>
                                <input type="date" class="input-jv" id="edit_vencimiento" name="fecha_vencimiento">
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 p-3" style="border-top:1px solid rgba(6,182,212,0.1);">
                        <button type="button" class="btn btn-jv-danger" style="padding:8px 20px;font-size:.8rem;" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                        <button type="submit" class="btn btn-jv-success" style="padding:8px 20px;font-size:.8rem;" onclick="this.disabled=true;this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>GUARDANDO...';this.form.submit()"><i class="bi bi-check-lg me-1"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        function filtrar() {
            const input = document.getElementById('buscar');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('tablaProductos').getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                const sku = rows[i].getAttribute('data-sku') || '';
                const nombre = rows[i].getAttribute('data-nombre') || '';
                rows[i].style.display = (sku.includes(filter) || nombre.includes(filter)) ? '' : 'none';
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
        });

        var modalEditar = null;
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('modalEditar');
            if (el) modalEditar = new bootstrap.Modal(el);
        });

        function editarProducto(id) {
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
