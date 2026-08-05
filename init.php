<?php

// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================

// --- 1. Session (strict mode) ---
date_default_timezone_set('America/Caracas');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// --- 1b. Headers de seguridad ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");

// --- 2. Error reporting OFF + handler global ---
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

set_error_handler(function ($severity, $msg, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($msg, 0, $severity, $file, $line);
});

// --- 3. Marcar que venimos de init.php ---
define('INIT_LOADED', true);

// --- 4. Cargar constantes ---
require_once __DIR__ . '/includes/config.php';

// --- 5. Cargar clases core ---
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/helpers.php';

// ==========================================
// BASE DE DATOS
// ==========================================

// --- 6/7. Conectar BD (auto-restaurar backup si la BD no existe) ---
function jv_db_error_page() {
    die("<div style='background:#020617;color:#f87171;font-family:sans-serif;text-align:center;padding:100px;height:100vh;'>
            <div style='max-width:600px;margin:auto;border:1px solid rgba(248,113,113,0.3);padding:40px;border-radius:20px;background:#0f172a;'>
                <h2 style='color:#ef4444;text-transform:uppercase;letter-spacing:2px;'>Error de Conexión</h2>
                <p style='color:#94a3b8;margin-top:20px;'>No se pudo conectar a la base de datos.</p>
            </div>
         </div>");
}

function jv_importar_sql($conn, $sql_path) {
    if (!@mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        error_log("[JV3000] No se pudo crear la BD: " . mysqli_error($conn));
        return false;
    }
    if (!@mysqli_select_db($conn, DB_NAME)) {
        error_log("[JV3000] No se pudo seleccionar la BD: " . mysqli_error($conn));
        return false;
    }
    @mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    $sql_content = file_get_contents($sql_path);
    if (empty($sql_content)) {
        error_log("[JV3000] Archivo SQL vacío: " . $sql_path);
        return false;
    }
    @mysqli_multi_query($conn, $sql_content);
    do {
        if ($res = @mysqli_store_result($conn)) { @mysqli_free_result($res); }
    } while (@mysqli_next_result($conn));
    $check = @mysqli_query($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
    $row = $check ? @mysqli_fetch_assoc($check) : null;
    if (!$row || (int)$row['total'] === 0) {
        error_log("[JV3000] Import fallido (sin tablas): " . $sql_path);
        return false;
    }
    error_log("[JV3000] BD auto-instalada desde: " . basename($sql_path));
    return true;
}

function jv_boot_page(string $titulo, string $mensaje, bool $showDemoForm) {
    $demo_error = false;
    if ($showDemoForm && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['instalar_demo'])) {
        Security::validateCSRF();
        $conn_demo = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);
        if ($conn_demo && jv_importar_sql($conn_demo, __DIR__ . '/db/jv3000_portable_v3.sql')) {
            mysqli_close($conn_demo);
            header('Location: index.php');
            exit;
        }
        if ($conn_demo) { mysqli_close($conn_demo); }
        error_log("[JV3000] Fallo al instalar los datos de ejemplo.");
        $demo_error = true;
    }

    $csrf = Security::generateToken();
    $extra = $demo_error
        ? "<p style='color:#fbbf24;margin-top:20px;'>La instalación de los datos de ejemplo falló. Revisa que exista db/jv3000_portable_v3.sql o coloca un respaldo en backups/.</p>"
        : '';
    $form = '';
    if ($showDemoForm) {
        $form = "<hr style='border-color:rgba(148,163,184,0.2);margin:28px 0;'>
                 <p style='color:#94a3b8;'>¿Es una instalación nueva? Puedes iniciar con datos de ejemplo (no es tu información real):</p>
                 <form method='POST' action='" . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'login.php') . "' style='margin-top:16px;'>
                     <input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf) . "'>
                     <button type='submit' name='instalar_demo' value='1' style='background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;border:none;padding:12px 24px;border-radius:10px;font-size:0.95rem;font-weight:600;cursor:pointer;text-transform:uppercase;letter-spacing:1px;'>Instalar con datos de ejemplo</button>
                 </form>";
    }
    die("<div style='background:#020617;color:#f87171;font-family:sans-serif;text-align:center;padding:100px;height:100vh;'>
            <div style='max-width:620px;margin:auto;border:1px solid rgba(251,191,36,0.3);padding:40px;border-radius:20px;background:#0f172a;'>
                <h2 style='color:#fbbf24;text-transform:uppercase;letter-spacing:2px;'>" . htmlspecialchars($titulo) . "</h2>
                <p style='color:#94a3b8;margin-top:20px;'>" . $mensaje . "</p>
                " . $extra . $form . "
            </div>
         </div>");
}

try {
    Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (Throwable $e) {
    $installed = false;
    $noBackup = false;
    $restoreFailed = false;
    $conn_no_db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if ($conn_no_db) {
        $db_check = @mysqli_query($conn_no_db, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        if ($db_check && mysqli_num_rows($db_check) == 0) {
            $backup_files = glob(__DIR__ . '/backups/jv3000_db_*.sql');
            if (!$backup_files) {
                $noBackup = true;
            } else {
                usort($backup_files, fn($a, $b) => filemtime($b) <=> filemtime($a));
                foreach ($backup_files as $candidate) {
                    $installed = jv_importar_sql($conn_no_db, $candidate);
                    if ($installed) { break; }
                }
                $restoreFailed = !$installed;
            }
        }
        mysqli_close($conn_no_db);
    }

    if ($installed) {
        try {
            Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        } catch (Throwable $e2) {
            error_log("[JV3000] DB connection failed after auto-install: " . $e2->getMessage());
            jv_db_error_page();
        }
    } elseif ($noBackup) {
        error_log("[JV3000] No hay backups en backups/ — instalación detenida.");
        jv_boot_page(
            'Base de Datos no encontrada',
            'No se encontró ningún respaldo en <b>backups/</b> para restaurar.<br>
             Para restaurar tu información, coloca un archivo <b>jv3000_db_*.sql</b> (genéralo con <b>backups/backup.bat</b> en el equipo donde trabajas) y vuelve a cargar la página.',
            true
        );
    } elseif ($restoreFailed) {
        error_log("[JV3000] No se pudo restaurar ningún backup de backups/.");
        jv_boot_page(
            'Restauración fallida',
            'No se pudo restaurar ninguno de los respaldos en <b>backups/</b>. Revisa los archivos <b>jv3000_db_*.sql</b> y el log del servidor.',
            false
        );
    } else {
        error_log("[JV3000] DB connection failed: " . $e->getMessage());
        jv_db_error_page();
    }
}

// ==========================================
// SEGURIDAD Y SESIÓN
// ==========================================

// --- 8. Validación de sesión (salvo páginas públicas) ---
$publicPages = ['login.php', 'recuperar.php', 'logout.php'];
$currentScript = basename($_SERVER['SCRIPT_NAME']);

if (!in_array($currentScript, $publicPages)) {
    Security::validateSession();
}

// --- 8b. Tab session marker (prevents reused session after tab close) ---
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

// --- 9. Sanitización global de inputs ---
Security::sanitizeGlobals();

// --- 10. Validación CSRF en peticiones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::validateCSRF();
}

// ==========================================
// MANEJADOR DE ERRORES GLOBAL
// ==========================================

// --- 11. Manejador global de excepciones ---
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
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '') {
        $rp = parse_url($ref);
        if (($rp['host'] ?? '') !== ($_SERVER['HTTP_HOST'] ?? '')) $ref = '';
    }
    header("Location: " . ($ref !== '' ? $ref : 'index.php'));
    exit;
});

// ==========================================
// COMPATIBILIDAD
// ==========================================

// --- 12. Variable global $db para compatibilidad con helpers legacy ---
$db = Database::getInstance();
