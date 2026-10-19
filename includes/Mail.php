<?php

// ==========================================
// MAIL — Envío por SMTP y detección de conexión
// ==========================================
// Es el único punto del sistema que habla con un servidor de correo. No conoce
// el flujo de recuperación: solo sabe enviar un mensaje y decir si hay red.
// Toda su configuración llega desde config/.env (claves JV_SMTP_*).

require_once __DIR__ . '/../assets/lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../assets/lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../assets/lib/PHPMailer/src/SMTP.php';

/**
 * Mail: envío de correos electrónicos por SMTP.
 *
 * Pensado para un entorno local (XAMPP) donde la empresa a veces no tiene
 * internet: `hayInternet()` es lo que permite ocultar la opción de correo en
 * la recuperación en lugar de dejar un envío que va a fallar igualmente.
 */
final class Mail
{
    /** Timeout del sondeo de conexión, en segundos. */
    private const TIMEOUT_CONEXION = 3;

    /** Resultado del sondeo para no repetirlo dentro de la misma petición. */
    private static ?bool $hayInternet = null;

    /**
     * ¿Existe ruta de red hasta el servidor SMTP configurado?
     *
     * Abre un socket directo con un tope corto para no frenar la petición. Si
     * no conecta, el sistema no ofrece el canal de correo: el usuario cae en el
     * PIN de emergencia, que no depende de red. El resultado se guarda solo
     * durante la petición en curso: recuperar.php lo consulta al pintar la
     * pantalla y otra vez al enviar, y dos sondeos seguidos costarían el doble
     * de espera cuando no hay red.
     *
     * @return bool true si el servidor SMTP responde.
     */
    public static function hayInternet(): bool
    {
        if (self::$hayInternet !== null) {
            return self::$hayInternet;
        }
        if (SMTP_HOST === '' || SMTP_USER === '' || SMTP_PASS === '') {
            return self::$hayInternet = false; // Sin credenciales no hay canal de correo.
        }

        $socket = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, self::TIMEOUT_CONEXION);
        if (!is_resource($socket)) {
            return self::$hayInternet = false;
        }
        fclose($socket);
        return self::$hayInternet = true;
    }

    /**
     * Envía un correo en texto plano por SMTP con TLS implícito/STARTTLS.
     *
     * Nunca lanza excepciones: un fallo de red o de credenciales vuelve como
     * `['ok' => false]` para que quien llama decida qué mostrar.
     *
     * @param string $para     Correo del destinatario.
     * @param string $asunto   Asunto del mensaje.
     * @param string $cuerpo   Cuerpo del mensaje (texto plano).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public static function enviar(string $para, string $asunto, string $cuerpo): array
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'CORREO DEL DESTINATARIO INVÁLIDO.'];
        }
        if (!self::hayInternet()) {
            return ['ok' => false, 'mensaje' => 'SIN CONEXIÓN CON EL SERVIDOR DE CORREO.'];
        }

        $correo = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $correo->isSMTP();
            $correo->Host       = SMTP_HOST;
            $correo->Port       = SMTP_PORT;
            $correo->SMTPAuth   = true;
            $correo->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $correo->Username   = SMTP_USER;
            $correo->Password   = SMTP_PASS;
            $correo->CharSet    = 'UTF-8';

            $correo->setFrom(SMTP_FROM, SMTP_FROM_NOMBRE);
            $correo->addAddress($para);
            $correo->Subject = $asunto;
            $correo->Body    = $cuerpo;
            $correo->isHTML(false);

            $correo->send();
            return ['ok' => true, 'mensaje' => 'CORREO ENVIADO.'];
        } catch (Throwable $e) {
            // El detalle técnico se queda en el log; al usuario solo le llega
            // un mensaje claro para que pueda recurrir al PIN de emergencia.
            error_log('[Mail] Fallo al enviar a ' . $para . ': ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'NO SE PUDO ENVIAR EL CORREO. USE SU PIN DE EMERGENCIA.'];
        }
    }
}
