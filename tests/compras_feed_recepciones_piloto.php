<?php

/**
 * Verifica que el feed de recepciones sea compacto, reciente y adaptable.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$modelo = file_get_contents($raiz . '/models/Recepcion.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$estilos = file_get_contents($raiz . '/assets/modules/compras/compras.css');
$inicio_feed = strpos($vista, '<!-- Actividad reciente de recepciones -->');
$fin_feed = strpos($vista, '<!-- Modal: Registrar compra -->', $inicio_feed);
$feed = $inicio_feed === false || $fin_feed === false ? '' : substr($vista, $inicio_feed, $fin_feed - $inicio_feed);

function afirmarFeedRecepciones(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarFeedRecepciones(strpos($vista, '<ul class="list-group list-group-flush comp-rec-feed-list">') !== false, 'El feed debe usar una lista compacta.');
afirmarFeedRecepciones($feed !== '' && strpos($feed, '<table') === false, 'La tabla de recepciones debe reemplazarse por el feed.');
afirmarFeedRecepciones(strpos($feed, 'Se recibieron <strong>+') !== false && strpos($feed, 'unidades</strong> de') !== false, 'Cada evento debe resumir las unidades y el proveedor.');
afirmarFeedRecepciones(strpos($feed, 'Factura <?php echo htmlspecialchars($recepcion[\'nro_factura\']') !== false, 'La factura debe mostrarse como texto secundario.');
afirmarFeedRecepciones(strpos($feed, "'Hoy, ' . date('H:i'") !== false && strpos($feed, "date('d/m', \$marca_tiempo)") !== false, 'La fecha debe usar un formato breve.');
afirmarFeedRecepciones(strpos($feed, 'url=historial') !== false && strpos($feed, 'Ver todo el historial de operaciones') !== false, 'El feed debe enlazar al historial de operaciones.');
afirmarFeedRecepciones(strpos($modelo, 'ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC') !== false && strpos($modelo, 'LIMIT 4') !== false, 'La consulta debe limitar las recepciones más recientes a cuatro.');
afirmarFeedRecepciones(strpos($javascript, 'function filtrarRecepciones()') === false, 'No debe quedar lógica de búsqueda para la tabla eliminada.');
afirmarFeedRecepciones(strpos($estilos, 'overflow-wrap: anywhere;') !== false && strpos($estilos, 'grid-template-columns: 36px minmax(0, 1fr);') !== false, 'El feed debe permitir nombres largos y adaptarse a móvil.');

echo "OK feed de recepciones limitado a cuatro y adaptable verificado\n";
