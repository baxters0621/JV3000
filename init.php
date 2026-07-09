<?php

// ==========================================================
// init.php — Middleware Central de Seguridad
// TODO entry point DEBE requerir este archivo primero.
// ==========================================================

// --- 1. Session (strict mode) ---
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// --- 2. Error reporting OFF + handler global ---
error_reporting(0);
ini_set('display_errors', '0');

set_error_handler(function ($severity, $msg, $file, $line) {
    throw new ErrorException($msg, 0, $severity, $file, $line);
});

// --- 3. Marcar que venimos de init.php (config.php salta lógica legacy) ---
define('INIT_LOADED', true);

// --- 4. Cargar constantes ---
require_once __DIR__ . '/includes/config.php';

// --- 5. Cargar clases core ---
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/helpers.php';

// --- 6. Conectar base de datos ---
try {
    Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (Throwable $e) {
    error_log("[JV3000] DB connection failed: " . $e->getMessage());
    die("<div style='background:#020617;color:#f87171;font-family:sans-serif;text-align:center;padding:100px;height:100vh;'>
            <div style='max-width:600px;margin:auto;border:1px solid rgba(248,113,113,0.3);padding:40px;border-radius:20px;background:#0f172a;'>
                <h2 style='color:#ef4444;text-transform:uppercase;letter-spacing:2px;'>Error de Conexión</h2>
                <p style='color:#94a3b8;margin-top:20px;'>No se pudo conectar a la base de datos.</p>
            </div>
         </div>");
}

// --- 6. Validación de sesión (salvo páginas públicas) ---
$publicPages = ['login.php', 'recuperar.php', 'logout.php'];
$currentScript = basename($_SERVER['SCRIPT_NAME']);

if (!in_array($currentScript, $publicPages)) {
    Security::validateSession();
}

// --- 6b. Tab session marker (prevents reused session after tab close) ---
if (isset($_SESSION['id_usuario'])) {
    if (!isset($_SESSION['tab_marker'])) {
        $_SESSION['tab_marker'] = bin2hex(random_bytes(16));
    }
    $freshLogin = !empty($_SESSION['fresh_login']);
    define('_TAB_FRESH_LOGIN', $freshLogin);
    if ($freshLogin) {
        $_SESSION['fresh_login'] = false;
    }
}

// --- 7. Sanitización global de inputs ---
Security::sanitizeGlobals();

// --- 8. Validación CSRF en peticiones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::validateCSRF();
}

// --- 9. Manejador global de excepciones ---
set_exception_handler(function (Throwable $e) {
    $db = Database::getInstance();
    if ($db->inTransaction()) {
        $db->rollback();
    }

    error_log("[JV3000] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
        exit;
    }

    $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'Error interno del servidor.'];
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $referer");
    exit;
});

// --- 10. Variable global $db para compatibilidad con helpers legacy ---
$db = Database::getInstance();
