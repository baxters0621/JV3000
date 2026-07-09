<?php
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoVenta();

$productos = $db->fetchAll("SELECT p.*, (p.stock_actual * p.precio_venta) as valor_total FROM productos p JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.status = 'Activo' AND c.status = 'Activo' ORDER BY p.nombre_producto ASC");

$gran_total_stock = 0;
$valor_inventario = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario | JV3000 C.A.</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/bootstrap-icons.css">
    <style>
        body {
            background-color: white !important;
            color: black !important;
            font-family: 'Segoe UI', sans-serif;
        }
        .header-report {
            border-bottom: 2px solid #334155;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }
        .table thead {
            background-color: #f8fafc !important;
            color: #1e293b !important;
            border-bottom: 2px solid #cbd5e1;
        }
        .footer-total {
            background-color: #f1f5f9 !important;
            font-weight: bold;
            border-top: 2px solid #334155 !important;
        }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0 !important; padding: 15mm !important; }
            .container-fluid { max-width: 100% !important; padding: 0 !important; }
            .badge { border: 1px solid #000 !important; color: #000 !important; }
            @page { margin: 0; size: letter; }
            .table { font-size: 9px; }
            .table th, .table td { padding: 3px 4px !important; }
            .header-report { margin-bottom: 10px; padding-bottom: 6px; }
            .mt-5 { margin-top: 0 !important; }
            .pt-5 { padding-top: 0 !important; }
        }
    </style>
</head>
<body class="p-4 p-md-5">
    <div class="container-fluid">
        <div class="header-report d-flex justify-content-between align-items-center">
            <div class="text-start">
                <h2 class="fw-bold m-0 text-dark">JV3000 C.A. SYSTEM, C.A.</h2>
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
                        <th width="12%">SKU</th>
                        <th width="38%">Descripción del Producto</th>
                        <th width="15%">Existencia</th>
                        <th width="15%">P. Unitario</th>
                        <th width="20%">Valorización</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($productos)): ?>
                        <?php foreach ($productos as $r):
                            $gran_total_stock += $r['stock_actual'];
                            $valor_inventario += $r['valor_total'];
                        ?>
                            <tr>
                                <td class="text-center font-monospace small"><?php echo htmlspecialchars($r['sku']); ?></td>
                                <td class="text-start ps-3 fw-semibold"><?php echo htmlspecialchars($r['nombre_producto']); ?></td>
                                <td class="text-center">
                                    <span class="<?php echo ($r['stock_actual'] <= 5) ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo number_format($r['stock_actual'], 0); ?>
                                    </span>
                                    <?php if ($r['stock_actual'] <= 5): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-danger ms-1 small"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">$<?php echo number_format($r['precio_venta'], 2); ?></td>
                                <td class="text-end pe-3 fw-bold">$<?php echo number_format($r['valor_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No se encontraron productos activos en el sistema.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="footer-total">
                        <td colspan="2" class="text-end text-uppercase py-3 pe-3 small">Totales Consolidados:</td>
                        <td class="text-center py-3"><?php echo number_format($gran_total_stock, 0); ?> Unds.</td>
                        <td></td>
                        <td class="text-end pe-3 py-3 fs-5 text-primary">$<?php echo number_format($valor_inventario, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row mt-5 pt-5 text-center d-none d-print-flex">
            <div class="col-6">
                <div style="width: 70%; border-top: 1.5px solid #000; margin: 0 auto;"></div>
                <p class="small mt-2"><strong>Firma Almacenista</strong><br>Control de Inventario</p>
            </div>
            <div class="col-6">
                <div style="width: 70%; border-top: 1.5px solid #000; margin: 0 auto;"></div>
                <p class="small mt-2"><strong>Sello Gerencia</strong><br>JV3000 C.A. SYSTEM</p>
            </div>
        </div>

        <div class="mt-5 no-print d-flex justify-content-center gap-3">
            <button onclick="window.print()" class="btn btn-dark btn-lg rounded-pill px-5 shadow">
                <i class="bi bi-printer-fill me-2"></i>Imprimir Reporte
            </button>
            <a href="../index.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">
                Volver al Dashboard
            </a>
        </div>
    </div>
</body>
</html>
