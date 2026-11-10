<?php

/**
 * Valida las reglas estrictas de RIF y teléfono del proveedor.
 */
$raiz = dirname(__DIR__);
require_once $raiz . '/includes/helpers.php';

function afirmarValidacionProveedor(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarValidacionProveedor(validarRifProveedor('V-12345678'), 'La cédula V debe aceptar 8 dígitos.');
afirmarValidacionProveedor(validarRifProveedor('E-12345678'), 'La cédula E debe aceptar 8 dígitos.');
afirmarValidacionProveedor(validarRifProveedor('J-12345678-9'), 'El RIF J debe aceptar 8 dígitos y verificador.');
afirmarValidacionProveedor(validarRifProveedor('G-12345678-9'), 'El RIF G debe aceptar 8 dígitos y verificador.');
afirmarValidacionProveedor(validarRifProveedor('P-12345678-9'), 'El RIF P debe aceptar 8 dígitos y verificador.');
afirmarValidacionProveedor(validarRifProveedor('C-12345678-9'), 'El RIF C debe aceptar 8 dígitos y verificador.');
afirmarValidacionProveedor(!validarRifProveedor('J-12345678'), 'El RIF jurídico no debe aceptar 8 dígitos sin verificador.');
afirmarValidacionProveedor(!validarRifProveedor('V-12345678-9'), 'La cédula no debe aceptar verificador.');
afirmarValidacionProveedor(!validarRifProveedor('V-1234567'), 'La cédula no debe aceptar 7 dígitos.');
afirmarValidacionProveedor(!validarRifProveedor('X-12345678'), 'El tipo de documento debe ser V/E/J/G/P/C.');

afirmarValidacionProveedor(validarTelefonoProveedor('+584121234567'), 'El prefijo 412 debe ser válido.');
afirmarValidacionProveedor(validarTelefonoProveedor('+584141234567'), 'El prefijo 414 debe ser válido.');
afirmarValidacionProveedor(validarTelefonoProveedor('+584161234567'), 'El prefijo 416 debe ser válido.');
afirmarValidacionProveedor(validarTelefonoProveedor('+584241234567'), 'El prefijo 424 debe ser válido.');
afirmarValidacionProveedor(validarTelefonoProveedor('+584261234567'), 'El prefijo 426 debe ser válido.');
afirmarValidacionProveedor(validarTelefonoProveedor('+584101234567'), 'El prefijo 410 debe ser válido.');
afirmarValidacionProveedor(validarTelefonoProveedor('+584111234567'), 'El prefijo 411 debe ser válido.');
afirmarValidacionProveedor(validarTelefonoProveedor('+584181234567'), 'El prefijo 418 debe ser válido.');
afirmarValidacionProveedor(validarTelefonoProveedor('+584191234567'), 'El prefijo 419 debe ser válido.');
afirmarValidacionProveedor(!validarTelefonoProveedor('+580909090909'), 'Venezuela no debe aceptar el prefijo 090.');
afirmarValidacionProveedor(!validarTelefonoProveedor('+58412123456'), 'Venezuela debe exigir 10 dígitos nacionales.');
afirmarValidacionProveedor(!validarTelefonoProveedor('+5841212345678'), 'Venezuela no debe aceptar 11 dígitos nacionales.');
afirmarValidacionProveedor(validarTelefonoProveedor('+14155550123'), 'Un teléfono internacional válido debe aceptarse.');
afirmarValidacionProveedor(validarTelefonoProveedor('+34912345678'), 'Un teléfono español válido debe aceptarse.');

echo "OK validación estricta de RIF y teléfono del proveedor\n";
