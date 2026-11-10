<?php

/**
 * Verifica la cuadrícula visual del formulario de proveedor en Compras.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$estilos = file_get_contents($raiz . '/assets/modules/compras/compras.css');
$modelo = file_get_contents($raiz . '/models/Proveedor.php');
$compra = file_get_contents($raiz . '/models/Compra.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');
require_once $raiz . '/includes/helpers.php';
$inicio_modal = strpos($vista, '<div class="modal fade" id="modalProveedor"');
$fin_modal = strpos($vista, '<!-- POP-UP 3:', $inicio_modal);
$modal = $inicio_modal === false || $fin_modal === false ? '' : substr($vista, $inicio_modal, $fin_modal - $inicio_modal);
$inicio_contacto = strpos($modal, '<div class="comp-section sec-contacto">');
$fin_contacto = strpos($modal, '<div class="comp-section sec-comercial">', $inicio_contacto);
$contacto = $inicio_contacto === false || $fin_contacto === false ? '' : substr($modal, $inicio_contacto, $fin_contacto - $inicio_contacto);

function afirmarModalProveedor(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarModalProveedor($modal !== '' && $contacto !== '', 'No se encontró la estructura completa del modal de proveedor.');
afirmarModalProveedor(preg_match('/<div class="col-md-4">\s*<label class="comp-label">RIF/is', $modal) === 1, 'El RIF debe ocupar cuatro columnas.');
afirmarModalProveedor(preg_match('/<div class="col-md-8">\s*<label class="comp-label">Nombre de la Empresa/is', $modal) === 1, 'El nombre de empresa debe ocupar ocho columnas.');
afirmarModalProveedor(preg_match('/<div class="col-12">\s*<label class="comp-label">Direcci&oacute;n Fiscal/is', $modal) === 1 && strpos($modal, 'id="p_direccion" class="input-jv comp-input" rows="2"') !== false, 'La dirección fiscal debe ocupar todo el ancho y tener dos filas.');
afirmarModalProveedor(strpos($modal, 'class="comp-rif-grupo"') !== false, 'El RIF debe usar el grupo flex propio.');
afirmarModalProveedor(strpos($modal, 'V/E: 8 dígitos') === false, 'La ayuda del RIF debe haberse retirado.');
afirmarModalProveedor(strpos($modal, 'placeholder="12345678-9"') !== false && strpos($modal, 'maxlength="10"') !== false, 'El RIF jurídico debe mostrar el formato con verificador.');
afirmarModalProveedor(substr_count($contacto, 'class="col-md-4"') === 3, 'Teléfono, persona de contacto y correo deben ocupar cuatro columnas cada uno.');
afirmarModalProveedor(strpos($modal, 'id="p_tel" class="input-jv comp-input w-100"') !== false, 'El campo telefónico debe ocupar todo el ancho de la columna.');
afirmarModalProveedor(strpos($estilos, '.comp-rif-grupo') !== false && strpos($estilos, 'width: 64px !important;') !== false, 'El RIF debe integrar selector e input sin margen adicional.');
afirmarModalProveedor(strpos($estilos, '#modalProveedor .iti {') !== false && strpos($estilos, '#modalProveedor .iti input {') !== false, 'El selector internacional debe conservar el ancho y altura del teléfono.');
afirmarModalProveedor(strpos($estilos, '#modalProveedor .comp-input,') !== false && strpos($estilos, 'height: 42px;') !== false, 'Los controles del modal deben tener altura uniforme.');
afirmarModalProveedor(strpos($modal, 'id="p_lead_time"') !== false && strpos($modal, 'id="p_moneda"') !== false && strpos($modal, 'id="p_status"') !== false, 'Faltan campos de condiciones comerciales.');
afirmarModalProveedor(preg_match('/<div class="col-lg-4">\s*<label class="comp-label">Plazo/is', $modal) === 1, 'El plazo debe ocupar un tercio en escritorio para que su etiqueta no se parta.');
afirmarModalProveedor(preg_match('/<div class="col-lg-4">\s*<label class="comp-label">Moneda/is', $modal) === 1, 'La moneda debe ocupar un tercio en escritorio.');
afirmarModalProveedor(preg_match('/<div class="col-lg-4">\s*<label class="comp-label">Estado/is', $modal) === 1, 'El estado debe ocupar un tercio en escritorio.');
afirmarModalProveedor(substr_count($modal, 'class="col-lg-4"') === 3, 'Las tres condiciones comerciales deben ser simétricas.');
afirmarModalProveedor(strpos($modal, 'id="btn-prov-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase mt-4"') !== false, 'Guardar debe ocupar el ancho completo, separado con mt-4.');
afirmarModalProveedor(strpos($modal, 'maxlength="15"') !== false, 'El teléfono debe limitar la cantidad de dígitos visibles.');
afirmarModalProveedor(strpos($modal, 'autocomplete="tel-national"') !== false, 'El teléfono debe declarar que se captura sin el código de país.');
afirmarModalProveedor(strpos($modal, 'placeholder="412-486-2167"') !== false, 'El teléfono debe mostrar el formato nacional esperado.');
afirmarModalProveedor(strpos($modal, 'id="p_tel_full"') !== false, 'El formulario debe conservar el teléfono completo para el servidor.');
afirmarModalProveedor(strpos($javascript, 'telRecortar') !== false && strpos($javascript, 'telFormatear') !== false && strpos($javascript, 'telE164') !== false, 'El JS debe recortar, formatear y construir el E.164 del teléfono.');
afirmarModalProveedor(strpos($javascript, 'window.jvTelProvCargar') !== false && strpos($javascript, 'window.jvTelProvNumero') !== false, 'El JS debe exponer la carga y lectura del teléfono del proveedor.');
afirmarModalProveedor(strpos($javascript, 'telMaximosPorPais') !== false, 'El JS debe limitar los dígitos según el país.');
afirmarModalProveedor(strpos($modelo, 'validarTelefonoProveedor($telefono)') !== false, 'El modelo debe validar el teléfono también en el servidor.');
afirmarModalProveedor(strpos($modelo, 'validarRifProveedor($rif)') !== false, 'El modelo debe validar el RIF por tipo de documento.');
afirmarModalProveedor(strpos($javascript, 'validarRifProv') !== false && strpos($javascript, 'telVenezuelaValido') !== false, 'El JS debe validar RIF y prefijos venezolanos.');
afirmarModalProveedor(strpos($javascript, '4(12|14|16|24|26|10|11|18|19)') !== false, 'El JS debe exigir los prefijos móviles venezolanos.');
afirmarModalProveedor(strpos($compra, 'validarRifProveedor(normalizarDocumento($prov[\'rif\'] ?? \'\'))') !== false, 'El alta de compras debe aceptar cédulas V/E de 8 dígitos.');
afirmarModalProveedor(formatearTelefono('+58-412-5550101') === '+58 412-555-0101', 'El teléfono venezolano debe mostrarse como +58 412-555-0101.');
afirmarModalProveedor(formatearTelefono('+14155550123') === '+1 415-555-0123', 'El teléfono de EE. UU. debe conservar su código de país de un dígito.');

echo "OK estructura visual y teléfono del modal de proveedor verificados\n";
