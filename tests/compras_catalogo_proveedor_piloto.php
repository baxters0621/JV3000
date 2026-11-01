<?php

/**
 * Verifica que el alta de Compras use solo el catálogo del proveedor elegido.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$controlador = file_get_contents($raiz . '/controllers/ComprasController.php');
$modelo = file_get_contents($raiz . '/models/Compra.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');

function afirmarCatalogoCompras(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarCatalogoCompras(strpos($vista, 'id="selProductoProveedor"') !== false, 'Falta el selector de productos del proveedor.');
afirmarCatalogoCompras(strpos($vista, 'window.JV_CATALOGO = <?php echo $catalogo_productos; ?>;') !== false, 'La vista no recibe el catálogo por proveedor.');
afirmarCatalogoCompras(strpos($vista, 'id="buscarProducto"') === false, 'La búsqueda general de productos sigue habilitada.');
afirmarCatalogoCompras(strpos($controlador, 'catalogoProductosPorProveedor()') !== false, 'El controlador no carga el catálogo asociado a cada proveedor.');
afirmarCatalogoCompras(strpos($modelo, "WHERE p.status = 'Activo'") !== false, 'El catálogo incluye productos inactivos.');
afirmarCatalogoCompras(strpos($javascript, 'catalogo.forEach(function(producto)') !== false, 'El selector no se llena desde el catálogo del proveedor.');
afirmarCatalogoCompras(strpos($javascript, 'jvBuscarProductos') === false, 'La compra todavía invoca el buscador que causaba ReferenceError.');
afirmarCatalogoCompras(strpos($modelo, 'cc.id_proveedor = ?') !== false, 'El servidor no comprueba que el producto pertenezca al proveedor.');
afirmarCatalogoCompras(strpos($modelo, 'NO ESTÁ EN EL CATÁLOGO DEL PROVEEDOR') !== false, 'El servidor no rechaza productos ajenos al catálogo.');

echo "OK selector de Compras limitado al catálogo activo del proveedor\n";
