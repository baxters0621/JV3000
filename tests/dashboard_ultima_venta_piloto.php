<?php

/**
 * Verifica que el KPI "Última Venta" sume la venta completa y que la columna
 * de precio de Inventario tenga ancho suficiente para el monto en bolívares.
 */
$raiz = dirname(__DIR__);
$dashboard = file_get_contents($raiz . '/dashboard/index.php');
$vista_productos = file_get_contents($raiz . '/views/productos/index.php');
$css_productos = file_get_contents($raiz . '/assets/modules/productos/productos.css');

function afirmarDashboardPrecio(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarDashboardPrecio(
    strpos($dashboard, 'SUM(ds.cantidad * ds.precio_venta)') !== false,
    'El KPI Última Venta debe sumar todas las líneas de la venta.'
);
afirmarDashboardPrecio(
    strpos($dashboard, 'SELECT COALESCE(ds.cantidad * ds.precio_venta, 0)') === false,
    'El KPI Última Venta no debe devolver una sola línea de detalle.'
);
afirmarDashboardPrecio(
    strpos($dashboard, 's.id_salida = (') !== false
    && strpos($dashboard, 'ORDER BY s2.fecha_salida DESC, s2.id_salida DESC') !== false,
    'El KPI debe seleccionar la última venta activa por fecha e id.'
);

afirmarDashboardPrecio(
    strpos($vista_productos, '<th class="text-end">PRECIO (Bs.)</th>') !== false,
    'El encabezado de precio debe alinearse a la derecha.'
);
afirmarDashboardPrecio(
    preg_match('/<td class="text-end">\s*<span class="prod-precio">/', $vista_productos) === 1,
    'El importe de precio debe alinearse a la derecha.'
);
afirmarDashboardPrecio(
    preg_match('/\.table-jv \.col-precio\s*\{\s*width:\s*12%;\s*\}/', $css_productos) === 1,
    'La columna de precio debe ocupar 12% en escritorio.'
);
afirmarDashboardPrecio(
    strpos($css_productos, '.pagina-productos .table-jv .col-precio { width: 14%; }') !== false,
    'La columna de precio debe ocupar 14% en pantallas menores a 1200 px.'
);
afirmarDashboardPrecio(
    strpos($css_productos, 'min-width: 820px;') !== false,
    'La tabla debe conservar espacio suficiente en pantallas medianas.'
);
afirmarDashboardPrecio(
    strpos($css_productos, 'white-space: nowrap;') !== false
    && strpos($css_productos, 'font-variant-numeric: tabular-nums;') !== false,
    'El precio debe mostrarse completo y con dígitos alineados.'
);

echo "OK KPI Última Venta y precio de Inventario verificados\n";
