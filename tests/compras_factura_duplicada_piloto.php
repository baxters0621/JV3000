<?php

/**
 * Verifica la generación automática, el bloqueo de duplicados y la sugerencia
 * de consecutivo en el alta de compras (modelo, controlador, vista, JS y CSS).
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
afirmarFacturaDuplicada(strpos($vista, 'readonly aria-readonly="true"') !== false, 'La factura debe generarse bloqueada para los operadores.');
afirmarFacturaDuplicada(strpos($vista, 'id="btnEditarFactura"') !== false, 'Falta el botón de excepción manual.');
afirmarFacturaDuplicada(strpos($vista, 'Security::esAdmin()') !== false, 'El botón manual debe reservarse al administrador.');

afirmarFacturaDuplicada(strpos($modelo, 'public function estadoFactura') !== false, 'El modelo debe exponer estadoFactura().');
afirmarFacturaDuplicada(strpos($modelo, 'MAX(CAST(nro_factura AS UNSIGNED))') !== false, 'La secuencia debe usar el máximo histórico.');
afirmarFacturaDuplicada(strpos($modelo, 'ORDER BY (c.id_proveedor = ?) DESC') !== false, 'El proveedor actual debe tener prioridad al detectar duplicados.');
afirmarFacturaDuplicada(strpos($modelo, "REGEXP '^[0-9]{6,8}$'") !== false, 'La secuencia debe ignorar números con formato inválido.');
afirmarFacturaDuplicada(strpos($modelo, "'00000001'") !== false, 'El primer número debe ser 00000001.');
afirmarFacturaDuplicada(strpos($modelo, "nro_factura = ? AND status = 'Activa'") === false, 'El duplicado debe considerar también las compras anuladas.');
afirmarFacturaDuplicada(strpos($modelo, 'WHERE id_proveedor = ? AND nro_factura = ? LIMIT 1') !== false, 'El guardado debe bloquear el duplicado por proveedor.');
afirmarFacturaDuplicada(strpos($modelo, 'ESTA FACTURA YA FUE REGISTRADA ANTERIORMENTE PARA ESTE PROVEEDOR.') !== false, 'Falta el mensaje de duplicado en el guardado.');

afirmarFacturaDuplicada(strpos($controlador, 'public function factura_estado') !== false, 'El controlador debe exponer la acción factura_estado().');
afirmarFacturaDuplicada(strpos($controlador, 'verificarPermisoCarga') !== false, 'El endpoint debe exigir permisos de carga.');
afirmarFacturaDuplicada(strpos($controlador, '$this->json(array_merge') !== false, 'El endpoint debe responder con Controller::json().');

afirmarFacturaDuplicada(strpos($javascript, 'compras/factura_estado') !== false, 'El JS debe consultar el endpoint de facturas.');
afirmarFacturaDuplicada(strpos($javascript, 'generarFacturaAutomatica') !== false, 'El JS debe generar la factura al elegir proveedor.');
afirmarFacturaDuplicada(strpos($javascript, "facturaModo = 'manual'") !== false, 'El JS debe distinguir la edición manual del administrador.');
afirmarFacturaDuplicada(strpos($javascript, 'asegurarFacturaUnica') !== false, 'El guardado debe verificar la factura antes de enviar.');
afirmarFacturaDuplicada(strpos($javascript, 'nroFacturaAyuda') !== false, 'El JS debe actualizar el texto de ayuda.');
afirmarFacturaDuplicada(strpos($javascript, "classList.add('input-error')") !== false, 'El duplicado debe pintar el campo de rojo.');
afirmarFacturaDuplicada(strpos($javascript, "classList.add('input-ok')") !== false, 'La factura libre debe pintar el campo de verde.');

afirmarFacturaDuplicada(strpos($estilos, '.comp-input.input-ok') !== false, 'Falta el estilo verde del campo verificado.');
afirmarFacturaDuplicada(strpos($estilos, '.comp-input.input-warn') !== false, 'Falta el estilo ámbar del aviso cruzado.');
afirmarFacturaDuplicada(strpos($estilos, '.comp-input[readonly]') !== false, 'Falta el estilo del campo de factura bloqueado.');
afirmarFacturaDuplicada(strpos($estilos, '.comp-editar-factura') !== false, 'Falta el estilo del botón de edición manual.');

echo "OK factura automática, duplicados y consecutivo sugerido\n";
