<?php
require_once __DIR__ . '/../init.php';
$db = Database::getInstance();
Security::verificarPermisoVenta();

$productos = $db->fetchAll("
    SELECT p.*, c.nombre as nombre_cat,
        COALESCE(pr.nombre_empresa, (
            SELECT pr2.nombre_empresa FROM detalle_compras dc JOIN compras co ON dc.id_compra = co.id_compra LEFT JOIN proveedores pr2 ON co.id_proveedor = pr2.id_proveedor WHERE dc.id_producto = p.id_producto AND co.status = 'Activa' ORDER BY co.fecha_compra DESC LIMIT 1
        )) as ultimo_proveedor,
        (p.stock_actual * p.precio_costo) as valor_costo,
        (p.stock_actual * p.precio_venta) as valor_venta
    FROM productos p
    LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
    LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
    WHERE p.status = 'Activo'
    ORDER BY p.nombre_producto ASC
");

$gran_total_stock = 0;
$valor_costo_total = 0;
$valor_venta_total = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario | JV3000 C.A.</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/bootstrap-icons.css">
    <style>
        body { background-color: white !important; color: black !important; font-family: 'Segoe UI', sans-serif; }
        .header-report { border-bottom: 2px solid #334155; margin-bottom: 30px; padding-bottom: 15px; }
        .table thead { background-color: #f8fafc !important; color: #1e293b !important; border-bottom: 2px solid #cbd5e1; }
        .footer-total { background-color: #f1f5f9 !important; font-weight: bold; border-top: 2px solid #334155 !important; }

        @media print {
            .no-print { display: none !important; }
            body { margin: 0 !important; padding: 15mm !important; }
            .container-fluid { max-width: 100% !important; padding: 0 !important; }
            @page { margin: 0; size: letter; }
            .table { font-size: 8px; }
            .table th, .table td { padding: 2px 3px !important; }
            .header-report { margin-bottom: 8px; padding-bottom: 4px; }
        }
    </style>
</head>
<body class="p-4 p-md-5">
    <div class="container-fluid">
        <div class="header-report d-flex justify-content-between align-items-center">
            <div class="text-start">
                <h2 class="fw-bold m-0 text-dark">JV3000 C.A.</h2>
                <p class="m-0 text-muted small fw-bold">RIF: J-502873090 | CONTROL DE EXISTENCIAS</p>
                <p class="m-0 small">Sede Principal: Valencia, Edo. Carabobo</p>
            </div>
            <div class="text-end">
                <h3 class="m-0 text-uppercase fw-bold text-primary">Estado de Inventario</h3>
                <p class="m-0 small text-muted">Fecha: <strong><?php echo date('d/m/Y'); ?></strong> | Hora: <strong><?php echo date('h:i A'); ?></strong></p>
                <p class="m-0 small">Generado por: <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($_SESSION['usuario'] ?? ''); ?></span></p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mt-2">
                <thead class="text-center">
                    <tr class="text-uppercase small fw-bold">
                        <th width="10%">SKU</th>
                        <th width="26%">Producto</th>
                        <th width="14%">Categoría</th>
                        <th width="16%">Proveedor</th>
                        <th width="8%">Stock</th>
                        <th width="10%">P. Costo</th>
                        <th width="10%">P. Venta</th>
                        <th width="12%">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $r):
                        $gran_total_stock += $r['stock_actual'];
                        $valor_costo_total += $r['valor_costo'];
                        $valor_venta_total += $r['valor_venta'];
                    ?>
                        <tr>
                            <td class="text-center font-monospace small"><?php echo htmlspecialchars($r['sku']); ?></td>
                            <td class="text-start ps-2 fw-semibold small"><?php echo htmlspecialchars($r['nombre_producto']); ?></td>
                            <td class="text-center small text-muted"><?php echo htmlspecialchars($r['nombre_cat'] ?? '-'); ?></td>
                            <td class="text-center small text-muted"><?php echo htmlspecialchars($r['ultimo_proveedor'] ?? '-'); ?></td>
                            <td class="text-center <?php echo ($r['stock_actual'] <= 5) ? 'text-danger fw-bold' : ''; ?>"><?php echo number_format($r['stock_actual'], 0); ?></td>
                            <td class="text-end pe-2 small">$<?php echo number_format($r['precio_costo'], 2); ?></td>
                            <td class="text-end pe-2 small">$<?php echo number_format($r['precio_venta'], 2); ?></td>
                            <td class="text-end pe-2 fw-bold">$<?php echo number_format($r['valor_venta'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="footer-total">
                        <td colspan="4" class="text-end text-uppercase py-2 pe-3 small">Totales Consolidados:</td>
                        <td class="text-center py-2"><?php echo number_format($gran_total_stock, 0); ?> Unds.</td>
                        <td class="text-end pe-2 py-2 text-primary fw-bold">$<?php echo number_format($valor_costo_total, 2); ?></td>
                        <td class="text-end pe-2 py-2 text-success fw-bold">$<?php echo number_format($valor_venta_total, 2); ?></td>
                        <td class="text-end pe-2 py-2 text-success fw-bold">$<?php echo number_format($valor_venta_total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row mt-4 pt-3 text-center d-none d-print-flex">
            <div class="col-4"><div style="width:70%;border-top:1.5px solid #000;margin:0 auto;"></div><p class="small mt-2"><strong>Elaborado por</strong></p></div>
            <div class="col-4"><div style="width:70%;border-top:1.5px solid #000;margin:0 auto;"></div><p class="small mt-2"><strong>Almacenista</strong></p></div>
            <div class="col-4"><div style="width:70%;border-top:1.5px solid #000;margin:0 auto;"></div><p class="small mt-2"><strong>Gerencia</strong></p></div>
        </div>

        <div class="mt-4 no-print d-flex justify-content-center gap-3">
            <button onclick="window.print()" class="btn btn-dark btn-lg rounded-pill px-5 shadow"><i class="bi bi-printer-fill me-2"></i>Imprimir Reporte</button>
            <a href="../index.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">Volver al Panel de Inicio</a>
        </div>
    </div>
</body>
</html>
