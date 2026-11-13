<?php

/**
 * Verifica que el alta de Compras use solo el catálogo del proveedor elegido.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$controlador = file_get_contents($raiz . '/controllers/ComprasController.php');
$modelo = file_get_contents($raiz . '/models/Compra.php');
$modeloProveedor = file_get_contents($raiz . '/models/Proveedor.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$esquema = file_get_contents($raiz . '/db/jv3000_portable_v6.sql');

function afirmarCatalogoCompras(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarCatalogoCompras(strpos($vista, 'id="selProductoProveedor"') !== false, 'Falta el selector de productos del proveedor.');
afirmarCatalogoCompras(
    preg_match('/<input\b(?=[^>]*id="inputPrecio")(?=[^>]*\breadonly\b)[^>]*>/i', $vista) === 1,
    'El precio unitario del proveedor todavía se puede editar.'
);
afirmarCatalogoCompras(strpos($vista, 'window.JV_CATALOGO = <?php echo $catalogo_productos; ?>;') !== false, 'La vista no recibe el catálogo por proveedor.');
afirmarCatalogoCompras(strpos($vista, 'id="buscarProducto"') === false, 'La búsqueda general de productos sigue habilitada.');
afirmarCatalogoCompras(strpos($controlador, 'catalogoProductosPorProveedor()') !== false, 'El controlador no carga el catálogo asociado a cada proveedor.');
afirmarCatalogoCompras(strpos($modelo, "WHERE p.status = 'Activo'") === false, 'El catálogo de compras sigue ocultando productos inactivos que deben poder reponerse.');
afirmarCatalogoCompras(strpos($modelo, "'estado'") !== false, 'El catálogo no informa el estado del producto (activo, agotado, vencido o inactivo).');
afirmarCatalogoCompras(strpos($javascript, 'catalogo.forEach(function(producto)') !== false, 'El selector no se llena desde el catálogo del proveedor.');
afirmarCatalogoCompras(strpos($javascript, 'jvBuscarProductos') === false, 'La compra todavía invoca el buscador que causaba ReferenceError.');
afirmarCatalogoCompras(strpos($modelo, 'cc.id_proveedor = ?') !== false, 'El servidor no comprueba que el producto pertenezca al proveedor.');
afirmarCatalogoCompras(strpos($modelo, 'cc.costo') !== false, 'El modelo no consulta el costo registrado en el catálogo del proveedor.');
afirmarCatalogoCompras(strpos($modelo, "\$precio_costo = round((float)\$prod_fila['costo'], 2);") !== false, 'El modelo todavía confía en el precio enviado desde el navegador.');
afirmarCatalogoCompras(strpos($modelo, "\$prod['precio']") === false, 'El detalle enviado desde el navegador todavía determina el costo de compra.');
afirmarCatalogoCompras(strpos($modelo, 'NO ESTÁ EN EL CATÁLOGO DEL PROVEEDOR') !== false, 'El servidor no rechaza productos ajenos al catálogo.');
afirmarCatalogoCompras(strpos($modelo, 'cc.stock_disponible') !== false, 'El catálogo no envía las existencias del proveedor.');
afirmarCatalogoCompras(strpos($javascript, 'Disponible: ') !== false, 'El selector no muestra las existencias por producto.');
afirmarCatalogoCompras(strpos($javascript, 'Number(productoCatalogo.costo)') !== false, 'El carrito no usa el costo del catálogo del proveedor.');
afirmarCatalogoCompras(strpos($javascript, 'cantidadEnCarrito') !== false, 'El selector no descuenta del disponible lo ya agregado al carrito.');
afirmarCatalogoCompras(strpos($modelo, 'stock_disponible = stock_disponible - ?') !== false, 'La compra no descuenta el disponible del proveedor de forma atómica.');
afirmarCatalogoCompras(strpos($modelo, 'stock_proveedor_reservado') !== false, 'La compra no distingue las reservas nuevas de las compras anteriores a la migración.');
afirmarCatalogoCompras(strpos($modelo, 'stock_disponible = stock_disponible + ?') !== false, 'La anulación no devuelve el disponible reservado al proveedor.');
afirmarCatalogoCompras(strpos($modeloProveedor, 'COMPRA PENDIENTE DE RECEPCIÓN') !== false, 'El catálogo permite retirar asociaciones necesarias para anular compras pendientes.');
afirmarCatalogoCompras(strpos($vista, 'name="stock_disponible"') !== false, 'El catálogo no permite registrar existencias disponibles del proveedor.');
afirmarCatalogoCompras(strpos($vista, 'name="fecha_compra"') !== false && strpos($vista, 'name="fecha_compra" value=') === false, 'La fecha de compra no es editable.');
afirmarCatalogoCompras(strpos($vista, 'id="inputCant"') !== false && strpos($vista, 'oninput="if(this.value>999999)') === false, 'La cantidad sigue forzándose durante la edición.');
afirmarCatalogoCompras(strpos($modelo, 'DateTimeImmutable::createFromFormat') !== false, 'El servidor no valida la fecha de compra seleccionada.');
afirmarCatalogoCompras(strpos($modelo, "\$fecha_compra = date('Y-m-d H:i:s');") === false, 'El modelo sigue sustituyendo la fecha seleccionada por la fecha actual.');
afirmarCatalogoCompras(strpos($esquema, '`stock_disponible` int(10) unsigned NOT NULL DEFAULT 0') !== false, 'El esquema no incluye el disponible del proveedor con valor inicial cero.');
afirmarCatalogoCompras(strpos($esquema, '`stock_proveedor_reservado` tinyint(1) NOT NULL DEFAULT 0') !== false, 'El esquema no protege compras existentes contra devoluciones de stock no reservado.');
afirmarCatalogoCompras(strpos($modeloProveedor, "'registrar_con_producto'") !== false, 'El catálogo no permite dar de alta un producto nuevo.');
afirmarCatalogoCompras(strpos($vista, 'id="btnCatProductoNuevo"') !== false, 'Falta el botón para crear un producto desde el catálogo.');
afirmarCatalogoCompras(strpos($vista, 'id="catPanelProductoNuevo"') !== false, 'Falta el panel de alta rápida de producto.');
afirmarCatalogoCompras(strpos($javascript, 'alternarProductoNuevo') !== false, 'El JS no alterna el modo producto nuevo.');
afirmarCatalogoCompras(strpos($modeloProveedor, "ORDER BY (status = 'Activo') DESC") !== false, 'El selector debe listar primero los productos activos y luego los inactivos.');

echo "OK selector de Compras con catálogo del proveedor y estados para reponer\n";
