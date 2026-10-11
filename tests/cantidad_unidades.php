<?php

require_once dirname(__DIR__) . '/includes/helpers.php';

$casosValidos = [
    [1, 1],
    ['25', 25],
    ['25.0', 25],
    [999999, 999999],
];

foreach ($casosValidos as [$entrada, $esperado]) {
    if (normalizarCantidadUnidades($entrada) !== $esperado) {
        throw new RuntimeException('No se normalizó correctamente una cantidad entera válida.');
    }
}

$casosInvalidos = [
    null,
    true,
    '',
    'texto',
    0,
    -1,
    1.5,
    1.0,
    '1.5',
    '1.0000000000000001',
    json_decode('{"cantidad":1.0000000000000001}', true)['cantidad'],
    '1e2',
    1000000,
];

foreach ($casosInvalidos as $entrada) {
    if (normalizarCantidadUnidades($entrada) !== null) {
        throw new RuntimeException('Se aceptó una cantidad inválida o fraccionaria.');
    }
}

echo "OK cantidades enteras normalizadas y fracciones rechazadas\n";
