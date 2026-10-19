<?php

/**
 * Regresión: código de recuperación por correo (6 dígitos, en sesión).
 *
 * Cubre generación hasheada, vigencia de 15 min, 3 intentos máximos y el
 * límite de 3 envíos por ventana. Ejecutar con PHP CLI; sin base de datos.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$root = dirname(__DIR__);
require_once $root . '/includes/helpers.php';

function afirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function reiniciarSesion(): void
{
    $_SESSION = [];
}

// -----------------------------------------------
// 1) Generación: 6 dígitos y hash, nunca el código en claro
// -----------------------------------------------
reiniciarSesion();
$solicitud = solicitarCodigoRecuperacion();
afirmar($solicitud['ok'] === true, 'La primera solicitud debe permitirse.');
afirmar(preg_match('/^\d{6}$/', (string)$solicitud['codigo']) === 1, 'El código debe tener 6 dígitos.');
afirmar(!isset($_SESSION['rec_codigo_hash']) || $_SESSION['rec_codigo_hash'] !== (string)$solicitud['codigo'],
    'El código no debe guardarse en claro en la sesión.');
afirmar(
    $_SESSION['rec_codigo_hash'] === hash('sha256', (string)$solicitud['codigo']),
    'La sesión debe guardar el SHA-256 del código.'
);

// -----------------------------------------------
// 2) El código correcto verifica y queda de un solo uso
// -----------------------------------------------
$resultado = verificarCodigoRecuperacion((string)$solicitud['codigo']);
afirmar($resultado['ok'] === true, 'El código correcto debe verificar: ' . $resultado['mensaje']);
afirmar(!isset($_SESSION['rec_codigo_hash']), 'Verificado el código debe descartarse (un solo uso).');

// -----------------------------------------------
// 3) Un código distinto falla y consume un intento
// -----------------------------------------------
$solicitud = solicitarCodigoRecuperacion();
afirmar($solicitud['ok'] === true, 'Debe poder solicitarse un código nuevo.');
$otro = $solicitud['codigo'] === 123456 ? 654321 : 123456;
$resultado = verificarCodigoRecuperacion((string)$otro);
afirmar($resultado['ok'] === false, 'Un código distinto debe fallar.');
afirmar($_SESSION['rec_codigo_intentos'] === 1, 'Debe quedar registrado 1 intento fallido.');
afirmar(str_contains($resultado['mensaje'], '2'), 'Debe indicar los intentos restantes: ' . $resultado['mensaje']);

// -----------------------------------------------
// 4) Al tercer intento fallido se bloquea y se descarta
// -----------------------------------------------
verificarCodigoRecuperacion((string)$otro);
$resultado = verificarCodigoRecuperacion((string)$otro);
afirmar($resultado['ok'] === false, 'Al tercer fallo sigue sin verificar.');
afirmar(!isset($_SESSION['rec_codigo_hash']), 'Tras agotar intentos el código debe descartarse.');
afirmar(
    str_contains($resultado['mensaje'], 'DEMASIADOS'),
    'Debe avisar de intentos agotados: ' . $resultado['mensaje']
);

// -----------------------------------------------
// 5) Sin código pendiente no se puede verificar
// -----------------------------------------------
$resultado = verificarCodigoRecuperacion('123456');
afirmar($resultado['ok'] === false, 'Sin código pendiente debe rechazar.');

// -----------------------------------------------
// 6) Un código vencido se descarta
// -----------------------------------------------
reiniciarSesion();
$solicitud = solicitarCodigoRecuperacion();
$_SESSION['rec_codigo_expira'] = time() - 1;
$resultado = verificarCodigoRecuperacion((string)$solicitud['codigo']);
afirmar($resultado['ok'] === false, 'El código vencido debe rechazarse.');
afirmar(str_contains($resultado['mensaje'], 'EXPIR'), 'Debe avisar de caducidad: ' . $resultado['mensaje']);
afirmar(!isset($_SESSION['rec_codigo_hash']), 'El código vencido debe borrarse.');

// -----------------------------------------------
// 7) Límite de envíos: 3 permitidos, el 4º rechazado
// -----------------------------------------------
reiniciarSesion();
for ($intento = 1; $intento <= 3; $intento++) {
    $solicitud = solicitarCodigoRecuperacion();
    afirmar($solicitud['ok'] === true, "El envío $intento debe permitirse.");
}
$solicitud = solicitarCodigoRecuperacion();
afirmar($solicitud['ok'] === false, 'El cuarto envío debe rechazarse.');
afirmar($solicitud['codigo'] === null, 'El envío rechazado no debe entregar código.');
afirmar(
    str_contains($solicitud['mensaje'], 'DEMASIADOS ENVÍOS'),
    'Debe avisar del límite de envíos: ' . $solicitud['mensaje']
);

// -----------------------------------------------
// 8) La ventana de envíos caduca a los 15 minutos
// -----------------------------------------------
$_SESSION['rec_envios'] = ['total' => 3, 'inicio' => time() - 901];
$solicitud = solicitarCodigoRecuperacion();
afirmar($solicitud['ok'] === true, 'Caducada la ventana debe volver a permitir envíos.');

// -----------------------------------------------
// 9) limpiarCodigoRecuperacion() no borra el contador de envíos
// -----------------------------------------------
limpiarCodigoRecuperacion();
afirmar(!isset($_SESSION['rec_codigo_hash']), 'limpiar debe borrar el código.');
afirmar(isset($_SESSION['rec_envios']), 'limpiar debe conservar el contador de envíos.');

echo "OK - recuperacion_codigo_piloto: 24 aserciones superadas.\n";
