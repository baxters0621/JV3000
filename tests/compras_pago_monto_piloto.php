<?php

/**
 * Verifica que el monto del pago en bolívares sea texto controlado, sin
 * spinners, con límite de dígitos y tope por el saldo pendiente.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$modeloPago = file_get_contents($raiz . '/models/PagoCompra.php');

function afirmarMontoPago(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarMontoPago(strpos($vista, 'id="pago_monto"') !== false, 'Falta el input del monto de pago.');
afirmarMontoPago(strpos($vista, 'type="number" step="0.01" min="0.01" class="form-control input-jv" id="pago_monto"') === false, 'El monto no debe ser type=number ni tener spinners.');
afirmarMontoPago(strpos($vista, 'type="text" inputmode="decimal" autocomplete="off" maxlength="13"') !== false, 'El monto debe ser texto decimal limitado a 13 caracteres.');
afirmarMontoPago(strpos($vista, 'id="pago_monto_max"') !== false, 'Falta la ayuda con el máximo permitido.');

afirmarMontoPago(strpos($javascript, 'function normalizarMontoBs') !== false, 'Falta el saneador del monto en bolívares.');
afirmarMontoPago(strpos($javascript, 'function limitarMontoPagoAlSaldo') !== false, 'Falta el tope por saldo pendiente.');
afirmarMontoPago(strpos($javascript, 'pagoSaldoBs') !== false, 'El JS debe conservar el saldo en bolívares.');
afirmarMontoPago(strpos($javascript, 'Se ajustó al saldo pendiente') !== false, 'Falta el aviso al recortar el monto.');
afirmarMontoPago(strpos($javascript, 'Monto excedido') !== false, 'Falta la validación de monto excedido al enviar.');
afirmarMontoPago(strpos($javascript, 'normalizarMontoBs(montoPago.value, true)') !== false, 'El input debe sanearse mientras se escribe.');

afirmarMontoPago(strpos($modeloPago, 'EL MONTO EXCEDE EL SALDO PENDIENTE') !== false, 'El backend debe conservar el rechazo de sobrepagos.');

echo "OK monto de pago limitado y sin spinners\n";
