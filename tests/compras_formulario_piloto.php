<?php

/**
 * Verifica que el alta de compras y el registro de pagos no aniden formularios.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$modelo = file_get_contents($raiz . '/models/Compra.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');
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

echo "OK formulario de compras separado del pago y recuperación automática verificada\n";
