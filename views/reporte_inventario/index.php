<?php

/** @var array<int, array<string, mixed>> $productos */
/** @var float|null $tasa_bcv Tasa BCV para mostrar montos en Bs. */
/** @var string|null $tasa_bcv_fecha Fecha de la tasa BCV (Y-m-d). */

// ==========================================
// VISTA: Reporte de Inventario (standalone imprimible)
// ==========================================
// HTML completo (doctype/head/body) — se renderiza
// con renderRaw, sin layout. Solo muestra los datos
// recibidos del controlador; no hace consultas.

$gran_total_stock = 0;
$valor_costo_total = 0;
$valor_venta_total = 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario | JV3000 C.A.</title>
    <link href="<?php echo APP_URL_BASE; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL_BASE; ?>assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL_BASE; ?>assets/modules/reporte_inventario/reporte_inventario.css">
</head>

<body class="p-4 p-md-5">
    <div class="container-fluid">
        <div class="header-report d-flex justify-content-between align-items-center">
            <div class="text-start">
                <h2 class="fw-bold m-0 text-dark">JV3000 C.A.</h2>
                <p class="m-0 text-muted small fw-bold">RIF: <?php echo htmlspecialchars(getConfig('empresa_rif', 'J-502873090')); ?> | CONTROL DE EXISTENCIAS</p>
                <p class="m-0 small">Sede Principal: <?php echo htmlspecialchars(getConfig('empresa_direccion', 'Valencia, Edo. Carabobo')); ?></p>
            </div>
            <div class="text-end">
                <h3 class="m-0 text-uppercase fw-bold text-primary">Estado de Inventario</h3>
                <p class="m-0 small text-muted">Fecha: <strong><?php echo date('d/m/Y'); ?></strong> | Hora: <strong><?php echo date('h:i A'); ?></strong></p>
                <p class="m-0 small">Generado por: <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($_SESSION['usuario'] ?? ''); ?></span></p>
                <?php if (!empty($tasa_bcv)): ?>
                    <p class="m-0 small text-muted">Tasa BCV: <strong><?php echo jv_formatear_bolivares((float)$tasa_bcv, 2); ?></strong><?php echo !empty($tasa_bcv_fecha) ? ' del ' . date('d/m/Y', strtotime((string)$tasa_bcv_fecha)) : ''; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mt-2">
                <thead class="text-center">
                    <tr class="text-uppercase small fw-bold">
                        <th width="8%">SKU</th>
                        <th width="24%">Producto</th>
                        <th width="12%">Categoría</th>
                        <th width="14%">Proveedor</th>
                        <th width="7%">Stock</th>
                        <th width="7%">Cap.</th>
                        <th width="9%">P. Costo (Bs.)</th>
                        <th width="9%">P. Venta (Bs.)</th>
                        <th width="10%">Valor Total (Bs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto):
                        $gran_total_stock += $producto['stock_actual'];
                        $valor_costo_total += $producto['valor_costo'];
                        $valor_venta_total += $producto['valor_venta'];
                    ?>
                        <tr>
                            <td class="text-center font-monospace small"><?php echo htmlspecialchars($producto['sku']); ?></td>
                            <td class="text-start ps-2 fw-semibold small"><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                            <td class="text-center small text-muted"><?php echo htmlspecialchars($producto['nombre_cat'] ?? '-'); ?></td>
                            <td class="text-center small text-muted"><?php echo htmlspecialchars($producto['ultimo_proveedor'] ?? '-'); ?></td>
                            <td class="text-center <?php echo ($producto['stock_actual'] <= 5) ? 'text-danger fw-bold' : ''; ?>"><?php echo number_format($producto['stock_actual'], 0); ?></td>
                            <td class="text-center small text-muted"><?php echo number_format((int)$producto['capacidad'], 0); ?></td>
                            <td class="text-end pe-2 small"><?php echo format_bs((float)$producto['precio_costo'], $tasa_bcv ?? null); ?></td>
                            <td class="text-end pe-2 small"><?php echo format_bs((float)$producto['precio_venta'], $tasa_bcv ?? null); ?></td>
                            <td class="text-end pe-2 fw-bold"><?php echo format_bs((float)$producto['valor_venta'], $tasa_bcv ?? null); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="footer-total">
                        <td colspan="4" class="text-end text-uppercase py-2 pe-3 small">Totales Consolidados:</td>
                        <td class="text-center py-2"><?php echo number_format($gran_total_stock, 0); ?> Unds.</td>
                        <td></td>
                        <td class="text-end pe-2 py-2 text-primary fw-bold"><?php echo format_bs((float)$valor_costo_total, $tasa_bcv ?? null); ?></td>
                        <td class="text-end pe-2 py-2 text-success fw-bold"><?php echo format_bs((float)$valor_venta_total, $tasa_bcv ?? null); ?></td>
                        <td class="text-end pe-2 py-2 text-success fw-bold"><?php echo format_bs((float)$valor_venta_total, $tasa_bcv ?? null); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row mt-4 pt-3 text-center d-none d-print-flex">
            <div class="col-4">
                <div style="width:70%;border-top:1.5px solid #000;margin:0 auto;"></div>
                <p class="small mt-2"><strong>Elaborado por</strong></p>
            </div>
            <div class="col-4">
                <div style="width:70%;border-top:1.5px solid #000;margin:0 auto;"></div>
                <p class="small mt-2"><strong>Almacenista</strong></p>
            </div>
            <div class="col-4">
                <div style="width:70%;border-top:1.5px solid #000;margin:0 auto;"></div>
                <p class="small mt-2"><strong>Gerencia</strong></p>
            </div>
        </div>

        <div class="mt-4 no-print d-flex justify-content-center gap-3">
            <button onclick="window.print()" class="btn btn-dark btn-lg rounded-pill px-5 shadow"><i class="bi bi-printer-fill me-2"></i>Imprimir Reporte</button>
            <a href="<?php echo APP_URL_BASE; ?>dashboard/index.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">Volver al Panel de Inicio</a>
        </div>
    </div>
</body>

</html>