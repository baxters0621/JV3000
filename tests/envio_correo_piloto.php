<?php

/**
 * Prueba en vivo del canal de correo SMTP.
 *
 * Uso (CLI):  php tests/envio_correo_piloto.php destino@correo.com
 *
 * Verifica la ruta completa que usará la recuperación de contraseña: lectura de
 * config/.env, sondeo de conexión y envío real vía PHPMailer. No toca la base
 * de datos. Solo CLI: un GET sobre este archivo no debe ser capaz de enviar nada.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Mail.php';

function comprobar(bool $condicion, string $mensaje): void
{
    echo ($condicion ? '  [OK] ' : '  [FALLO] ') . $mensaje . PHP_EOL;
    if (!$condicion) {
        exit(1);
    }
}

$destino = trim($argv[1] ?? '');
if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
    echo "Uso: php tests/envio_correo_piloto.php destino@correo.com\n";
    exit(1);
}

echo "== Configuración leída de config/.env ==\n";
echo '  SMTP_HOST  = ' . (SMTP_HOST !== '' ? SMTP_HOST : '(vacío)') . "\n";
echo '  SMTP_PORT  = ' . SMTP_PORT . "\n";
echo '  SMTP_USER  = ' . (SMTP_USER !== '' ? SMTP_USER : '(vacío)') . "\n";
echo '  SMTP_PASS  = ' . (SMTP_PASS !== '' ? str_repeat('•', 8) . ' (' . strlen(SMTP_PASS) . ' caracteres)' : '(vacío)') . "\n";
echo '  SMTP_FROM  = ' . (SMTP_FROM !== '' ? SMTP_FROM : '(vacío)') . "\n";
echo '  Destino    = ' . $destino . "\n\n";

echo "== 1. Conexión ==\n";
$hayInternet = Mail::hayInternet();
comprobar($hayInternet, 'Mail::hayInternet() conecta con ' . SMTP_HOST . ':' . SMTP_PORT);

echo "\n== 2. Envío real ==\n";
$asunto = 'JV3000 — Prueba de canal de correo ' . date('Y-m-d H:i:s');
$cuerpo = "Este es un mensaje de prueba del sistema JV3000 C.A.\n\n"
    . "Si lo estás leyendo, el canal de recuperación de contraseña por correo\n"
    . "está operativo desde este equipo.\n\n"
    . 'Enviado: ' . date('Y-m-d H:i:s');

$resultado = Mail::enviar($destino, $asunto, $cuerpo);
echo '  Respuesta: ' . $resultado['mensaje'] . "\n";
comprobar($resultado['ok'], 'El correo se envió sin error de SMTP');

echo "\nRESULTADO: canal de correo operativo.\n";
