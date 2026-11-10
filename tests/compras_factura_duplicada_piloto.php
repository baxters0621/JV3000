<?php

/**
 * Verifica la validación de facturas duplicadas y la sugerencia de consecutivo
 * en el alta de compras (modelo, controlador, vista, JS y estilos).
 */
$raiz = dirname(__DIR__);
$vista = file_get_contents($raiz . '/views/compras/index.php');
$modelo = file_get_contents($raiz . '/models/Compra.php');
$controlador = file_get_contents($raiz . '/controllers/ComprasController.php');
$javascript = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$estilos = file_get_contents($raiz . '/assets/modules/compras/compras.css');

function afirmarFacturaDuplicada(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarFacturaDuplicada(strpos($vista, 'id="nroFactura"') !== false, 'El campo de factura debe tener id para el feedback en vivo.');
afirmarFacturaDuplicada(strpos($vista, 'aria-describedby="nroFacturaAyuda"') !== false, 'El campo de factura debe anunciar su texto de ayuda.');
afirmarFacturaDuplicada(strpos($vista, 'id="nroFacturaAyuda"') !== false, 'Falta el texto de ayuda del último número registrado.');

afirmarFacturaDuplicada(strpos($modelo, 'public function estadoFactura') !== false, 'El modelo debe exponer estadoFactura().');
afirmarFacturaDuplicada(strpos($modelo, "WHERE c.nro_factura = ? AND c.status = 'Activa'") !== false, 'La verificación debe ignorar compras anuladas.');
afirmarFacturaDuplicada(strpos($modelo, "WHERE status = 'Activa'") !== false, 'La sugerencia debe tomar solo compras activas.');
afirmarFacturaDuplicada(strpos($modelo, 'ESTA FACTURA YA FUE REGISTRADA ANTERIORMENTE PARA ESTE PROVEEDOR.') !== false, 'Falta el mensaje de duplicado en el guardado.');

afirmarFacturaDuplicada(strpos($controlador, 'public function factura_estado') !== false, 'El controlador debe exponer la acción factura_estado().');
afirmarFacturaDuplicada(strpos($controlador, 'verificarPermisoCarga') !== false, 'El endpoint debe exigir permisos de carga.');
afirmarFacturaDuplicada(strpos($controlador, '$this->json(array_merge') !== false, 'El endpoint debe responder con Controller::json().');

afirmarFacturaDuplicada(strpos($javascript, 'compras/factura_estado') !== false, 'El JS debe consultar el endpoint de facturas.');
afirmarFacturaDuplicada(strpos($javascript, 'asegurarFacturaUnica') !== false, 'El guardado debe verificar la factura antes de enviar.');
afirmarFacturaDuplicada(strpos($javascript, 'nroFacturaAyuda') !== false, 'El JS debe actualizar el texto de ayuda.');
afirmarFacturaDuplicada(strpos($javascript, "classList.add('input-error')") !== false, 'El duplicado debe pintar el campo de rojo.');
afirmarFacturaDuplicada(strpos($javascript, "classList.add('input-ok')") !== false, 'La factura libre debe pintar el campo de verde.');

afirmarFacturaDuplicada(strpos($estilos, '.comp-input.input-ok') !== false, 'Falta el estilo verde del campo verificado.');
afirmarFacturaDuplicada(strpos($estilos, '.comp-input.input-warn') !== false, 'Falta el estilo ámbar del aviso cruzado.');

echo "OK validacion de facturas duplicadas y consecutivo sugerido\n";
