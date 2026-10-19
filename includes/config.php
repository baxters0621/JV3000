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
$jv_password_configurado = $jv_db_pass !== false
    || array_key_exists('JV_DB_PASS', $jv_env)
    || array_key_exists('JV_DB_PASS_B64', $jv_env);
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
$jv_configuracion_faltante = [];
$jv_configuracion_invalida = false;
foreach (['JV_DB_HOST', 'JV_DB_USER', 'JV_DB_NAME', 'JV_DB_PORT'] as $jv_clave_configuracion) {
    $jv_valor_entorno = getenv($jv_clave_configuracion);
    $jv_configuracion_explicita = $jv_valor_entorno !== false
        || array_key_exists($jv_clave_configuracion, $jv_env);
    if (!$jv_configuracion_explicita) {
        $jv_configuracion_faltante[] = $jv_clave_configuracion;
        continue;
    }

    $jv_valor_configuracion = trim((string)($jv_valor_entorno !== false
        ? $jv_valor_entorno
        : $jv_env[$jv_clave_configuracion]));
    if (
        $jv_valor_configuracion === ''
        || str_contains(strtoupper($jv_valor_configuracion), 'CAMBIAR_POR_')
    ) {
        $jv_configuracion_invalida = true;
    }
}
if (!$jv_password_configurado) {
    $jv_configuracion_faltante[] = 'JV_DB_PASS';
}
$jv_puerto_configurado = getenv('JV_DB_PORT');
$jv_puerto_configurado = $jv_puerto_configurado !== false
    ? $jv_puerto_configurado
    : ($jv_env['JV_DB_PORT'] ?? '');
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
        : ($jv_configuracion_faltante === [] ? 'ready' : 'missing')
);
define('DB_CONFIG_MISSING', $jv_configuracion_faltante);
define('APP_NAME', 'JV3000 C.A.');
define('VERSION', '3.0.0');
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
