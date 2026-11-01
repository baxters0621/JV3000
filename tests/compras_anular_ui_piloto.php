<?php

/**
 * Verifica la disponibilidad visual y el mensaje de anulación de Compras.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$estilos = file_get_contents($raiz . '/assets/modules/compras/compras.css');
$inicio_tabla = strpos($vista, '<!-- Tabla de compras -->');
$fin_tabla = strpos($vista, '<!-- Actividad reciente de recepciones -->', $inicio_tabla);
$tabla = $inicio_tabla === false || $fin_tabla === false ? '' : substr($vista, $inicio_tabla, $fin_tabla - $inicio_tabla);

function afirmarAnulacionCompras(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarAnulacionCompras($tabla !== '', 'No se encontró la tabla principal de Compras.');
afirmarAnulacionCompras(
    strpos($tabla, "in_array(\$compra['estado_recepcion'] ?? 'Pendiente', ['Parcial', 'Completa'], true)") !== false,
    'La anulación debe bloquearse cuando la recepción sea parcial o completa.'
);
afirmarAnulacionCompras(
    strpos($tabla, "<?php echo \$compra_recibida ? 'disabled' : ''; ?>") !== false,
    'El botón de anulación debe deshabilitarse cuando ya existe mercancía recibida.'
);
afirmarAnulacionCompras(
    strpos($tabla, 'Anulación no disponible: mercancía recibida') !== false,
    'El botón deshabilitado debe indicar por qué no está disponible.'
);
afirmarAnulacionCompras(
    strpos($javascript, '¿Estás seguro de anular esta factura? Esta acción eliminará la compra permanentemente. Solo puedes anular compras que NO hayan sido recibidas en inventario.') !== false,
    'La confirmación debe explicar el efecto y la restricción de recepción.'
);
afirmarAnulacionCompras(
    strpos($estilos, '.pagina-compras .btn-anular-compra:disabled') !== false
    && strpos($estilos, 'color: #94A3B8;') !== false
    && strpos($estilos, 'cursor: not-allowed;') !== false,
    'El botón deshabilitado debe tener estilo gris y cursor de no disponible.'
);

echo "OK disponibilidad y confirmación de anulación verificadas\n";
