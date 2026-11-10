<?php

/**
 * Verifica la etiqueta del KPI y el ancho/alineación de la tabla de Ventas.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/salidas/index.php');
$css = file_get_contents($raiz . '/assets/modules/salidas/salidas.css');
$js = file_get_contents($raiz . '/assets/modules/salidas/salidas.js');

function afirmarSalidasPrecio(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarSalidasPrecio(
    strpos($vista, 'title="Cantidad total de unidades vendidas en el mes">Artículos Vendidos (Mes)') !== false,
    'El KPI debe explicar que cuenta unidades vendidas en el mes.'
);
afirmarSalidasPrecio(
    strpos($vista, 'Unidades Vendidas (Mes)') === false,
    'El KPI no debe conservar la etiqueta ambigua anterior.'
);

afirmarSalidasPrecio(
    substr_count($vista, 'd-none d-xxl-table-cell') === 6,
    'Control, Cant y Tipo deben ocultarse hasta el breakpoint xxl (1400 px).'
);
afirmarSalidasPrecio(
    strpos($vista, 'sal-th-control d-none d-xxl-table-cell') !== false
    && strpos($vista, 'td-control d-none d-xxl-table-cell') !== false,
    'La columna Control debe usar el breakpoint xxl en cabecera y celdas.'
);
afirmarSalidasPrecio(
    strpos($vista, '<th class="text-end">Total</th>') !== false
    && preg_match('/class="td-total text-end /', $vista) === 1,
    'El total debe alinearse a la derecha en cabecera y celdas.'
);

foreach ([
    'nota' => '10.5',
    'control' => '9.5',
    'cliente' => '12',
    'productos' => '16',
    'cantidad' => '6',
    'tipo' => '8.5',
    'total' => '13',
    'fecha' => '9',
    'acciones' => '15.5',
] as $columna => $ancho) {
    afirmarSalidasPrecio(
        strpos($css, '.sal-col-' . $columna . ' { width: ' . $ancho . '%; }') !== false,
        'Falta el ancho de escritorio de la columna ' . $columna . '.'
    );
}
afirmarSalidasPrecio(
    strpos($css, '@media (max-width: 1399.98px)') !== false
    && strpos($css, '.sal-col-total { width: 17%; }') !== false
    && strpos($css, '.sal-col-acciones { width: 20%; }') !== false,
    'La tabla compacta debe activarse antes de 1400 px y reservar ancho para Total.'
);
afirmarSalidasPrecio(
    strpos($css, '.pagina-salidas .salidas-list-table tbody td.td-total') !== false
    && strpos($css, 'text-align: right;') !== false
    && strpos($css, 'padding-right: 20px !important;') !== false
    && strpos($css, 'font-variant-numeric: tabular-nums;') !== false,
    'El total debe tener alineación, separación y cifras tabulares.'
);
afirmarSalidasPrecio(
    strpos($css, 'min-width: 1100px;') !== false
    && strpos($css, 'min-width: 900px;') !== false,
    'La tabla debe conservar sus anchos mínimos de escritorio y compacto.'
);
afirmarSalidasPrecio(
    strpos($vista, 'id="modalDetalleVenta"') !== false
    && strpos($vista, 'onclick="verDetalleVenta(this)"') !== false
    && strpos($vista, 'class="prod-lista"') !== false
    && strpos($vista, 'class="td-productos" data-tooltip=') === false,
    'La celda de productos debe mostrar una lista y un botón de detalle sin tooltip.'
);
afirmarSalidasPrecio(
    strpos($js, 'function verDetalleVenta(boton)') !== false
    && strpos($js, 'detalleVentaProductos') !== false
    && strpos($js, 'formatBs(precio)') !== false,
    'El detalle de la venta debe construirse desde el JSON de productos.'
);
afirmarSalidasPrecio(
    strpos($css, '.pagina-salidas .prod-lista') !== false
    && strpos($css, '.pagina-salidas .btn-detalle-venta') !== false
    && strpos($css, '#modalDetalleVenta .det-venta-tabla') !== false,
    'La lista, el botón y el modal de detalle deben tener estilos propios.'
);

echo "OK KPI, tabla y detalle de Ventas verificados\n";
