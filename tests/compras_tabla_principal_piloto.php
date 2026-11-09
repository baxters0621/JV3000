<?php

/**
 * Verifica la estructura y legibilidad de la tabla principal de Compras.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$estilos = file_get_contents($raiz . '/assets/modules/compras/compras.css');
$inicio_tabla = strpos($vista, '<!-- Tabla de compras -->');
$fin_tabla = strpos($vista, '<!-- Compras pendientes de recepción', $inicio_tabla);
$tabla = $inicio_tabla === false || $fin_tabla === false ? '' : substr($vista, $inicio_tabla, $fin_tabla - $inicio_tabla);

function afirmarTablaCompras(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarTablaCompras($tabla !== '', 'No se encontró la tabla principal de Compras.');
afirmarTablaCompras(strpos($tabla, 'd-flex align-items-center gap-3') !== false && strpos($tabla, 'compras-list-toolbar') !== false, 'El buscador y el filtro de pago deben compartir la fila flex.');
afirmarTablaCompras(strpos($tabla, 'id="buscar"') < strpos($tabla, 'id="filtroPago"'), 'El buscador y el filtro deben estar en la misma barra.');
afirmarTablaCompras(strpos($tabla, 'class="factura-texto text-truncate"') !== false, 'La factura debe ser texto limpio truncable.');
afirmarTablaCompras(strpos($tabla, 'class="codigo-badge" data-tooltip=') === false, 'La factura no debe usar el badge blanco.');
afirmarTablaCompras(strpos($tabla, 'class="proveedor-nombre text-truncate" data-tooltip=') !== false, 'El proveedor debe truncarse y conservar acceso al nombre completo.');
afirmarTablaCompras(strpos($tabla, 'class="detalle-nombres text-truncate" data-tooltip=') !== false, 'El detalle debe truncarse y conservar acceso a la lista completa.');
afirmarTablaCompras(preg_match_all('/<th\b/', $tabla) === 7, 'La tabla debe conservar sus siete columnas.');
foreach (['col-factura', 'col-proveedor', 'col-detalle', 'col-total', 'col-pago', 'col-fecha', 'col-acciones'] as $columna) {
    afirmarTablaCompras(strpos($tabla, 'class="' . $columna . '"') !== false, 'Falta la columna ' . $columna . '.');
}
afirmarTablaCompras(strpos($estilos, '.pagina-compras .compras-list-table thead th') !== false && strpos($estilos, 'white-space: nowrap;') !== false, 'Los encabezados deben permanecer en una sola línea.');
afirmarTablaCompras(strpos($estilos, '.pagina-compras .compras-list-table tbody#tablaEntradas > tr > td:first-child') !== false && strpos($estilos, 'border-left: 0;') !== false, 'Las filas de la tabla principal no deben mostrar el borde verde lateral.');
afirmarTablaCompras(strpos($estilos, '.pagina-compras .compras-list-table {') !== false && strpos($estilos, 'min-width: 1040px;') !== false, 'La tabla debe reservar ancho suficiente y permitir desplazamiento horizontal.');

echo "OK tabla principal de Compras verificada\n";
