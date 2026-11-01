<?php

/**
 * Verifica la cuadrícula visual del formulario de proveedor en Compras.
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$estilos = file_get_contents($raiz . '/assets/modules/compras/compras.css');
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
afirmarModalProveedor(strpos($modal, 'class="comp-hint"') !== false && strpos($estilos, 'font-size: .7rem;') !== false, 'La ayuda del RIF debe usar texto pequeño y gris.');
afirmarModalProveedor(substr_count($contacto, 'class="col-md-4"') === 3, 'Teléfono, persona de contacto y correo deben ocupar cuatro columnas cada uno.');
afirmarModalProveedor(strpos($modal, 'id="p_tel" class="input-jv comp-input w-100"') !== false, 'El campo telefónico debe ocupar todo el ancho de la columna.');
afirmarModalProveedor(strpos($estilos, '#modalProveedor .iti {') !== false && strpos($estilos, '#modalProveedor .iti input {') !== false, 'El selector internacional debe conservar el ancho y altura del teléfono.');
afirmarModalProveedor(strpos($estilos, '#modalProveedor .comp-input,') !== false && strpos($estilos, 'height: 42px;') !== false, 'Los controles del modal deben tener altura uniforme.');
afirmarModalProveedor(strpos($modal, 'id="p_lead_time"') !== false && strpos($modal, 'id="p_moneda"') !== false && strpos($modal, 'id="p_status"') !== false, 'Faltan campos de condiciones comerciales.');
afirmarModalProveedor(preg_match('/<div class="col-md-3">\s*<label class="comp-label">Plazo/is', $modal) === 1, 'El plazo debe ocupar tres columnas.');
afirmarModalProveedor(preg_match('/<div class="col-md-4">\s*<label class="comp-label">Moneda/is', $modal) === 1, 'La moneda debe ocupar cuatro columnas.');
afirmarModalProveedor(preg_match('/<div class="col-md-5">\s*<label class="comp-label">Estado/is', $modal) === 1, 'El estado debe ocupar cinco columnas.');
afirmarModalProveedor(strpos($modal, 'id="btn-prov-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase mt-4"') !== false, 'Guardar debe ocupar el ancho completo, separado con mt-4.');

echo "OK estructura visual del modal de proveedor verificada\n";
