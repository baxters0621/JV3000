<?php

// ==========================================
// CONSTANTES GLOBALES
// ==========================================
$jv_env = [];
$jv_env_file = dirname(__DIR__) . '/config/.env';
$jv_env_valido = true;
if (is_file($jv_env_file)) {
    try {
        $jv_env = parse_ini_file($jv_env_file, false, INI_SCANNER_RAW);
        if (!is_array($jv_env)) {
            $jv_env = [];
            $jv_env_valido = false;
        }
    } catch (Throwable) {
        $jv_env = [];
        $jv_env_valido = false;
    }
}
$jv_config_value = static function (string $key, string $fallback) use ($jv_env): string {
    $environmentValue = getenv($key);
    return $environmentValue !== false ? (string)$environmentValue : (string)($jv_env[$key] ?? $fallback);
};

define('DB_HOST', $jv_config_value('JV_DB_HOST', 'localhost'));
define('DB_USER', $jv_config_value('JV_DB_USER', 'root'));
$jv_db_pass = getenv('JV_DB_PASS');
$jv_password_valido = true;
if ($jv_db_pass === false) {
    if (array_key_exists('JV_DB_PASS', $jv_env)) {
        $jv_db_pass = (string)$jv_env['JV_DB_PASS'];
    } elseif (isset($jv_env['JV_DB_PASS_B64'])) {
        $jv_db_pass = base64_decode((string)$jv_env['JV_DB_PASS_B64'], true);
        if ($jv_db_pass === false) {
            $jv_db_pass = '';
            $jv_password_valido = false;
        }
    } else {
        $jv_db_pass = '';
    }
}
define('DB_PASS', (string)$jv_db_pass);
define('DB_NAME', $jv_config_value('JV_DB_NAME', 'jv3000_db'));
define('DB_PORT', (int)$jv_config_value('JV_DB_PORT', '3306'));
$jv_configuracion_invalida = false;
$jv_valores_predeterminados = [
    'JV_DB_HOST' => 'localhost',
    'JV_DB_USER' => 'root',
    'JV_DB_NAME' => 'jv3000_db',
    'JV_DB_PORT' => '3306',
];
foreach ($jv_valores_predeterminados as $jv_clave_configuracion => $jv_valor_predeterminado) {
    $jv_valor_configuracion = trim($jv_config_value($jv_clave_configuracion, $jv_valor_predeterminado));
    if (
        $jv_valor_configuracion === ''
        || str_contains(strtoupper($jv_valor_configuracion), 'CAMBIAR_POR_')
    ) {
        $jv_configuracion_invalida = true;
    }
}
$jv_puerto_configurado = getenv('JV_DB_PORT');
$jv_puerto_configurado = $jv_puerto_configurado !== false
    ? $jv_puerto_configurado
    : ($jv_env['JV_DB_PORT'] ?? '3306');
if (
    filter_var($jv_puerto_configurado, FILTER_VALIDATE_INT) === false
    || (int)$jv_puerto_configurado < 1
    || (int)$jv_puerto_configurado > 65535
) {
    $jv_configuracion_invalida = true;
}
define(
    'DB_CONFIG_STATUS',
    !$jv_env_valido || !$jv_password_valido || $jv_configuracion_invalida
        ? 'invalid'
        : 'ready'
);
define('DB_CONFIG_MISSING', []);
define('APP_NAME', 'JV3000 C.A.');
define('VERSION', '3.0.0');
defined('APP_ROOT') || define('APP_ROOT', dirname(__DIR__));
define('AUDIT_RETENCION_MESES', 6);

// ==========================================
// CORREO SALIENTE (SMTP) — recuperación de contraseña
// ==========================================
// Llegan de config/.env con prefijo JV_SMTP_. Si faltan, Mail::hayInternet()
// devuelve false y la aplicación deja de ofrecer el canal de correo sin romper
// nada: el usuario recurre al PIN de emergencia, que no necesita red.
define('SMTP_HOST', $jv_config_value('JV_SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int)$jv_config_value('JV_SMTP_PORT', '587'));
define('SMTP_USER', $jv_config_value('JV_SMTP_USER', ''));
define('SMTP_PASS', $jv_config_value('JV_SMTP_PASS', ''));
define('SMTP_FROM', $jv_config_value('JV_SMTP_FROM', ''));
define('SMTP_FROM_NOMBRE', $jv_config_value('JV_SMTP_FROM_NAME', 'JV3000 C.A.'));
// Destinatario del aviso proactivo de errores del diario (observabilidad).
// Vacío = sin correos automáticos; los errores quedan solo en el panel.
define('JV_MAIL_ADMIN', $jv_config_value('JV_MAIL_ADMIN', ''));

// Ruta base relativa desde el script actual hasta la raíz de la app
// ('', '../', '../../' según la profundidad: raíz, login/dashboard/modules, ...)
$jv_app_fs = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$jv_cur_fs = str_replace('\\', '/', dirname((string)realpath($_SERVER['SCRIPT_FILENAME'] ?? '')));
$jv_rest = $jv_app_fs !== false && strncmp($jv_cur_fs, $jv_app_fs, strlen($jv_app_fs)) === 0
    ? substr($jv_cur_fs, strlen($jv_app_fs)) : '';
$jv_rest = trim((string)$jv_rest, '/');
$jv_depth = ($jv_rest === '') ? 0 : substr_count($jv_rest, '/') + 1;
define('BASE_PATH', $jv_depth > 0 ? str_repeat('../', $jv_depth) : '');
define('BASE_ASSETS', BASE_PATH . 'assets/');
