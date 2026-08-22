<?php

// ==========================================
// CONSTANTES GLOBALES
// ==========================================
$jv_env = [];
$jv_env_file = dirname(__DIR__) . '/config/.env';
if (is_file($jv_env_file)) {
    $jv_env = parse_ini_file($jv_env_file, false, INI_SCANNER_RAW) ?: [];
}
$jv_config_value = static function (string $key, string $fallback) use ($jv_env): string {
    $environmentValue = getenv($key);
    return $environmentValue !== false ? (string)$environmentValue : (string)($jv_env[$key] ?? $fallback);
};

define('DB_HOST', $jv_config_value('JV_DB_HOST', 'localhost'));
define('DB_USER', $jv_config_value('JV_DB_USER', 'root'));
define('DB_PASS', $jv_config_value('JV_DB_PASS', ''));
define('DB_NAME', $jv_config_value('JV_DB_NAME', 'jv3000_db_test'));
define('APP_NAME', 'JV3000 C.A.');
define('VERSION', '3.0.0');
define('AUDIT_RETENCION_MESES', 6);

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
