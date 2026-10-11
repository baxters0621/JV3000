<?php

require_once dirname(__DIR__) . '/includes/helpers.php';

$productosCompra = [
    ['id' => 8, 'nombre' => 'Producto de prueba', 'cantidad' => 2, 'precio' => 12.5, 'fecha_vencimiento' => '2027-01-31'],
    ['id' => 8, 'nombre' => 'Producto de prueba', 'cantidad' => 3, 'precio' => 11, 'fecha_vencimiento' => '2027-02-28'],
];
$compra = prepararRecuperacionCompra([
    'csrf_token' => 'csrf-sintetico',
    'id_proveedor' => '4',
    'nro_factura' => '00001234',
    'nro_control' => '00-00000001',
    'productos_data' => json_encode($productosCompra),
    'monto_pago' => '150.00',
    'metodo_pago' => 'Transferencia',
    'pm_cedula' => 'V-12345678',
    'pm_telefono' => '04120000000',
    'pm_referencia' => 'REF-PRUEBA',
]);

if (
    $compra === null
    || $compra['id_proveedor'] !== 4
    || $compra['nro_factura'] !== '00001234'
    || count($compra['productos']) !== 2
    || $compra['productos'][0]['id'] !== $compra['productos'][1]['id']
    || $compra['productos'][1]['cantidad'] !== '3'
) {
    throw new RuntimeException('No se restauraron las líneas seguras de compra sin cambiar líneas repetidas.');
}

foreach (['csrf_token', 'monto_pago', 'metodo_pago', 'pm_cedula', 'pm_telefono', 'pm_referencia'] as $campo_sensible) {
    if (array_key_exists($campo_sensible, $compra)) {
        throw new RuntimeException('Se retuvo un campo sensible o de seguridad del formulario de compra.');
    }
}

$claveRecepcion = str_repeat('a', 64);
$recepcion = prepararRecuperacionRecepcion([
    'id_compra' => '12',
    'clave_idempotencia' => $claveRecepcion,
    'documento_recepcion' => 'GUIA-SINTETICA',
    'items_data' => json_encode([
        ['id_detalle' => 31, 'cantidad' => 2, 'fecha_vencimiento' => '2027-03-31'],
    ]),
    'csrf_token' => 'csrf-sintetico',
]);

if (
    $recepcion === null
    || $recepcion['id_compra'] !== 12
    || $recepcion['clave_idempotencia'] !== $claveRecepcion
    || $recepcion['items'] !== [
        ['id_detalle' => 31, 'cantidad' => 2, 'fecha_vencimiento' => '2027-03-31'],
    ]
    || array_key_exists('csrf_token', $recepcion)
) {
    throw new RuntimeException('No se recuperó la recepción segura con la misma clave de reintento.');
}

$recepcionAmbigua = prepararRecuperacionRecepcion([
    'id_compra' => '12',
    'clave_idempotencia' => $claveRecepcion,
    'items_data' => json_encode([
        ['id_detalle' => 31, 'cantidad' => 1, 'fecha_vencimiento' => '2027-03-31'],
        ['id_detalle' => 31, 'cantidad' => 1, 'fecha_vencimiento' => '2027-03-31'],
    ]),
]);

if ($recepcionAmbigua === null || $recepcionAmbigua['items'] !== null) {
    throw new RuntimeException('La recuperación no debe interpretar un id_detalle duplicado.');
}

if (prepararRecuperacionRecepcion([
    'id_compra' => '12',
    'clave_idempotencia' => 'clave-invalida',
    'items_data' => '[]',
]) !== null) {
    throw new RuntimeException('Se aceptó una clave de idempotencia inválida en la recuperación.');
}

echo "OK recuperación de formularios: whitelist, exclusión de pagos y límites de recepción\n";
