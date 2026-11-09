<?php

/**
 * Verifica la estructura del modal "Ver detalle" de Compras.
 *
 * Cubre el endpoint del modelo/controlador, los anclajes de la vista y la
 * función fetch del módulo. No consulta la base de datos.
 */
$raiz = dirname(__DIR__);
$modelo = file_get_contents($raiz . '/models/Compra.php');
$controlador = file_get_contents($raiz . '/controllers/ComprasController.php');
$vista = file_get_contents($raiz . '/views/compras/index.php');
$js = file_get_contents($raiz . '/assets/modules/compras/compras.js');

function afirmarDetalleCompra(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarDetalleCompra(strpos($modelo, 'public function obtenerDetalle(int $id_compra): ?array') !== false, 'Falta el método obtenerDetalle en el modelo Compra.');
afirmarDetalleCompra(strpos($modelo, "WHERE c.id_compra = ? AND c.status = 'Activa'") !== false, 'El detalle debe limitarse a compras activas.');
afirmarDetalleCompra(
    strpos($modelo, 'pr.rif') !== false
        && strpos($modelo, 'pr.direccion') !== false
        && strpos($modelo, 'pr.telefono') !== false,
    'La cabecera debe traer RIF, dirección y teléfono del proveedor.'
);
afirmarDetalleCompra(strpos($controlador, 'public function detalle(): void') !== false, 'Falta la acción detalle en ComprasController.');
afirmarDetalleCompra(strpos($controlador, 'Security::verificarPermisoCarga();') !== false, 'La acción detalle debe exigir permiso de carga.');

afirmarDetalleCompra(strpos($vista, 'btn btn-sm btn-outline-primary btn-detalle-compra') !== false && strpos($vista, 'data-id-compra=') !== false, 'La celda Detalle debe exponer un botón explícito con data-id-compra.');
afirmarDetalleCompra(strpos($vista, 'class="detalle-celda d-flex justify-content-between align-items-center gap-2"') !== false, 'La celda Detalle debe alinear nombre y botón en una sola línea.');
afirmarDetalleCompra(strpos($vista, 'class="cant-pill"') === false, 'La celda Detalle no debe mostrar el badge de unidades.');
afirmarDetalleCompra(strpos($vista, 'id="modalDetalleCompra"') !== false, 'Falta el modal #modalDetalleCompra.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraProveedor"') !== false, 'Falta el proveedor en la cabecera del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraRif"') !== false, 'Falta el RIF del proveedor en la cabecera del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraDireccion"') !== false, 'Falta la dirección del proveedor en la cabecera del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraTelefono"') !== false, 'Falta el teléfono del proveedor en la cabecera del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraFactura"') !== false, 'Falta la factura en la cabecera del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraFecha"') !== false, 'Falta la fecha en la cabecera del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraItems"') !== false, 'Falta el cuerpo de productos del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraSubtotal"') !== false, 'Falta el subtotal del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraIva"') !== false, 'Falta el IVA del modal.');
afirmarDetalleCompra(strpos($vista, 'id="detalleCompraTotal"') !== false, 'Falta el total del modal.');
afirmarDetalleCompra(strpos($vista, 'class="text-end fw-bold fs-5" id="detalleCompraTotal"') !== false, 'El total debe estar alineado a la derecha y resaltado.');

afirmarDetalleCompra(strpos($js, 'function abrirDetalleCompra(idCompra)') !== false, 'Falta la función abrirDetalleCompra en compras.js.');
afirmarDetalleCompra(strpos($js, "index.php?url=compras/detalle&id_compra=") !== false, 'La función debe consultar el endpoint compras/detalle.');
afirmarDetalleCompra(
    strpos($js, 'detalleCompraRif') !== false
        && strpos($js, 'detalleCompraDireccion') !== false
        && strpos($js, 'detalleCompraTelefono') !== false,
    'La función debe rellenar RIF, dirección y teléfono del proveedor.'
);
afirmarDetalleCompra(strpos($js, "addEventListener('click'") !== false && strpos($js, "closest('.btn-detalle-compra')") !== false, 'Falta el listener delegado para abrir el detalle.');

$inicio_tabla = strpos($vista, '<!-- Tabla de compras -->');
$fin_tabla = strpos($vista, '<!-- Compras pendientes de recepción', $inicio_tabla);
$tabla = $inicio_tabla === false || $fin_tabla === false ? '' : substr($vista, $inicio_tabla, $fin_tabla - $inicio_tabla);
afirmarDetalleCompra($tabla !== '', 'No se encontró la tabla principal de Compras.');
afirmarDetalleCompra(preg_match_all('/<th\b/', $tabla) === 7, 'La tabla principal debe conservar sus siete columnas.');

echo "OK modal de detalle de Compras verificado\n";
