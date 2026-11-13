<?php

/**
 * Verifica que el alta de compras y el registro de pagos no aniden formularios.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$modelo = file_get_contents($raiz . '/models/Compra.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$estilos = file_get_contents($raiz . '/assets/modules/compras/compras.css');
$diseno = file_get_contents($raiz . '/assets/js/diseno.js');

function afirmarComprasFormulario(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$inicio_compra = strpos($vista, '<form method="POST" id="formCompra"');
$fin_compra = $inicio_compra === false ? false : strpos($vista, '</form>', $inicio_compra);
$modal_pago = strpos($vista, 'id="modalPago"');
$carrito = strpos($vista, 'id="productosBody"');

afirmarComprasFormulario($inicio_compra !== false, 'No se encontró el formulario de compra.');
afirmarComprasFormulario($fin_compra !== false && $carrito !== false && $fin_compra > $carrito, 'El carrito quedó fuera del formulario de compra.');
afirmarComprasFormulario($modal_pago !== false && $modal_pago > $fin_compra, 'El modal de pago quedó anidado dentro del formulario de compra.');
afirmarComprasFormulario(strpos($vista, '<!-- PASO 3: Pago -->') === false, 'El alta de compra todavía solicita datos de pago.');
afirmarComprasFormulario(strpos($javascript, 'const form = btn.form;') !== false, 'El guardado no valida la asociación entre botón y formulario.');
afirmarComprasFormulario(strpos($vista, 'data-borrador-restaurar-automatico') !== false, 'La recuperación silenciosa del borrador no está habilitada para Compras.');
afirmarComprasFormulario(strpos($modelo, 'if ($monto_pago > 0) {') !== false, 'La validación de pago no está condicionada a un monto positivo.');
afirmarComprasFormulario(strpos($diseno, "form.hasAttribute('data-borrador-restaurar-automatico')") !== false, 'El sistema de borradores ignora la restauración automática por formulario.');
afirmarComprasFormulario(strpos($javascript, "jvBorradorLimpiar('compras_alta')") !== false, 'El guardado exitoso podría restaurar un carrito ya registrado.');
afirmarComprasFormulario(substr_count($vista, 'name="observaciones"') === 1, 'Observaciones debe conservar un único campo en el formulario.');
afirmarComprasFormulario(strpos($vista, 'id="compObservaciones"') < strpos($vista, '<!-- PASO 3: Productos -->'), 'Observaciones debe aparecer en los datos de factura antes del paso de productos.');
afirmarComprasFormulario(strpos($vista, 'row g-3 align-items-end comp-add-grid') !== false, 'La fila para agregar productos debe usar el grid de Bootstrap.');
foreach (['col-md-4 comp-add-product', 'col-md-1 comp-add-field', 'col-md-3 comp-add-field', 'col-md-2 comp-add-field', 'col-md-2 comp-add-action'] as $columna) {
    afirmarComprasFormulario(strpos($vista, $columna) !== false, 'Falta la columna esperada del grid: ' . $columna);
}
afirmarComprasFormulario(strpos($vista, 'class="comp-add-btn"') !== false && strpos($estilos, 'width: 100%;' . "\n            height: 52px;") !== false, 'El botón de agregar debe ocupar su columna y medir lo mismo que los controles.');
afirmarComprasFormulario(strpos($vista, 'comp-summary-item text-end') !== false && strpos($vista, 'comp-total-big fs-5') !== false, 'El resumen debe estar alineado a la derecha y destacar el total.');
$iva_oculto = strpos($vista, 'comp-summary-item text-end d-none');
$iva_nodo = strpos($vista, 'id="totalIva"');
afirmarComprasFormulario($iva_oculto !== false && $iva_nodo !== false && $iva_nodo > $iva_oculto && ($iva_nodo - $iva_oculto) < 260, 'El IVA del resumen debe ocultarse solo visualmente y conservar #totalIva para el cálculo.');
afirmarComprasFormulario(strpos($estilos, 'padding: 12px 16px !important;') !== false && strpos($estilos, 'padding: 1rem 16px !important;') !== false, 'El carrito debe conservar el espaciado solicitado y el estado vacío compacto.');

echo "OK formulario de compras separado del pago y recuperación automática verificada\n";
