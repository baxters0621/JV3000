<?php

// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================

// --- 1. Session (strict mode) ---
date_default_timezone_set('America/Caracas');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '1800');
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
// Sin cache de paginas internas: el navegador SIEMPRE revalida y los cambios
// de codigo/CSS se ven de inmediato sin depender de Ctrl+F5
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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
function jv_db_error_page()
{
    die("<div style='background:#020617;color:#f87171;font-family:sans-serif;text-align:center;padding:100px;height:100vh;'>
            <div style='max-width:600px;margin:auto;border:1px solid rgba(248,113,113,0.3);padding:40px;border-radius:20px;background:#0f172a;'>
                <h2 style='color:#ef4444;text-transform:uppercase;letter-spacing:2px;'>Error de Conexión</h2>
                <p style='color:#94a3b8;margin-top:20px;'>No se pudo conectar a la base de datos.</p>
            </div>
         </div>");
}

function jv_importar_sql(mysqli $conn, string $sql_path): bool
{
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
        if ($res = @mysqli_store_result($conn)) {
            @mysqli_free_result($res);
        }
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

function jv_boot_page(string $titulo, string $mensaje, bool $showDemoForm)
{
    $demo_error = false;
    if ($showDemoForm && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['instalar_demo'])) {
        Security::validateCSRF();
        $conn_demo = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);
        if ($conn_demo && jv_importar_sql($conn_demo, __DIR__ . '/db/jv3000_portable_v5.sql')) {
            mysqli_close($conn_demo);
            header('Location: dashboard/index.php');
            exit;
        }
        if ($conn_demo) {
            mysqli_close($conn_demo);
        }
        error_log("[JV3000] Fallo al instalar el sistema.");
        $demo_error = true;
    }

    $csrfToken = Security::generateToken();
    $additionalMessage = $demo_error
        ? "<p style='color:#fbbf24;margin-top:20px;'>La instalación falló. Revisa que exista db/jv3000_portable_v5.sql o coloca un respaldo en backups/.</p>"
        : '';
    $installationForm = '';
    if ($showDemoForm) {
        $installationForm = "<hr style='border-color:rgba(148,163,184,0.2);margin:28px 0;'>
                 <p style='color:#94a3b8;'>¿Es una instalación nueva? Puedes instalar el sistema en limpio (sin datos de ejemplo):</p>
                 <form method='POST' action='" . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'login.php') . "' style='margin-top:16px;'>
                     <input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrfToken) . "'>
                     <button type='submit' name='instalar_demo' value='1' style='background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;border:none;padding:12px 24px;border-radius:10px;font-size:0.95rem;font-weight:600;cursor:pointer;text-transform:uppercase;letter-spacing:1px;'>Instalar sistema</button>
                 </form>";
    }
    die("<div style='background:#020617;color:#f87171;font-family:sans-serif;text-align:center;padding:100px;height:100vh;'>
            <div style='max-width:620px;margin:auto;border:1px solid rgba(251,191,36,0.3);padding:40px;border-radius:20px;background:#0f172a;'>
                <h2 style='color:#fbbf24;text-transform:uppercase;letter-spacing:2px;'>" . htmlspecialchars($titulo) . "</h2>
                <p style='color:#94a3b8;margin-top:20px;'>" . $mensaje . "</p>
                " . $additionalMessage . $installationForm . "
            </div>
         </div>");
}

try {
    Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (Throwable $e) {
    $installed = false;
    $noBackup = false;
    $restoreFailed = false;
    $connectionWithoutDatabase = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    if ($connectionWithoutDatabase) {
        $databaseCheck = @mysqli_query($connectionWithoutDatabase, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        if ($databaseCheck && mysqli_num_rows($databaseCheck) == 0) {
            $backup_files = glob(__DIR__ . '/backups/jv3000_db_*.sql');
            if (!$backup_files) {
                $noBackup = true;
            } else {
                usort($backup_files, fn($a, $b) => filemtime($b) <=> filemtime($a));
                foreach ($backup_files as $candidate) {
                    $installed = jv_importar_sql($connectionWithoutDatabase, $candidate);
                    if ($installed) {
                        break;
                    }
                }
                $restoreFailed = !$installed;
            }
        }
        mysqli_close($connectionWithoutDatabase);
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
// MIGRACIÓN AUTOMÁTICA DE DOCUMENTOS (una sola vez)
// ==========================================

// --- 7b. Normalización de documento fiscal (cédula/RIF) ---
// Se ejecuta una única vez (flag en configuracion). Idempotente.
$jv_db = Database::getInstance();

// -- Compatibilidad de auditoría: columnas de trazabilidad del request
foreach (
    [
        ['auditoria', 'ip_origen', 'VARCHAR(45) NULL'],
        ['auditoria', 'ruta', 'VARCHAR(255) NULL'],
        ['auditoria', 'metodo', 'VARCHAR(10) NULL'],
        ['login_intentos', 'ultimo_intento', 'TIMESTAMP NOT NULL DEFAULT current_timestamp()'],
    ] as [$tabla, $columna, $tipo]
) {
    $exists = $jv_db->fetchOne(
        "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [DB_NAME, $tabla, $columna]
    );
    if (!$exists || (int)($exists['n'] ?? 0) === 0) {
        $jv_db->execute("ALTER TABLE {$tabla} ADD COLUMN {$columna} {$tipo}");
    }
}

$loginUniqueIndex = $jv_db->fetchOne(
    "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'login_intentos' AND INDEX_NAME = 'idx_ip_unique'",
    [DB_NAME]
);
if (!$loginUniqueIndex || (int)($loginUniqueIndex['n'] ?? 0) === 0) {
    $jv_db->execute("DELETE FROM login_intentos WHERE id NOT IN (SELECT id FROM (SELECT MIN(id) AS id FROM login_intentos GROUP BY ip_address) AS intentos_unicos)");
    $jv_db->execute("ALTER TABLE login_intentos ADD UNIQUE INDEX idx_ip_unique (ip_address)");
}

$normalizationFlag = $jv_db->fetchOne("SELECT valor FROM configuracion WHERE clave = 'documentos_normalizados'");
if (!$normalizationFlag) {
    $migrationFile = __DIR__ . '/db/migrar_documentos.php';
    if (is_file($migrationFile)) {
        require_once $migrationFile;
        if (function_exists('migrar_documentos')) {
            ob_start();
            migrar_documentos($jv_db->getConnection(), DB_NAME);
            ob_end_clean();
        }
    }
    @mysqli_query($jv_db->getConnection(), "INSERT INTO configuracion (clave, valor, descripcion, fecha_actualizado) VALUES ('documentos_normalizados', '1', 'Migración de formato de documento fiscal aplicada (v1)', NOW()) ON DUPLICATE KEY UPDATE valor = '1'");
    error_log("[JV3000] Migración de documentos fiscales ejecutada.");
}

// --- 7c. Esquema: solicitudes de compra + documento de recepción (idempotente) ---
$jv_db->execute("CREATE TABLE IF NOT EXISTS solicitudes_compra (
    id_solicitud int(11) NOT NULL AUTO_INCREMENT,
    id_usuario_solicitante int(11) NOT NULL,
    fecha_solicitud timestamp NOT NULL DEFAULT current_timestamp(),
    motivo varchar(150) DEFAULT NULL,
    estado enum('Pendiente','Atendida','Cancelada') NOT NULL DEFAULT 'Pendiente',
    id_compra int(11) DEFAULT NULL,
    fecha_atendida datetime DEFAULT NULL,
    PRIMARY KEY (id_solicitud),
    KEY fk_sol_user (id_usuario_solicitante),
    KEY fk_sol_compra (id_compra),
    KEY idx_sol_estado (estado),
    CONSTRAINT fk_sol_user FOREIGN KEY (id_usuario_solicitante) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_sol_compra FOREIGN KEY (id_compra) REFERENCES compras (id_compra) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$jv_db->execute("CREATE TABLE IF NOT EXISTS detalle_solicitud_compra (
    id_detalle int(11) NOT NULL AUTO_INCREMENT,
    id_solicitud int(11) NOT NULL,
    id_producto int(11) NOT NULL,
    cantidad_solicitada int(11) NOT NULL,
    PRIMARY KEY (id_detalle),
    KEY fk_dsc_solicitud (id_solicitud),
    KEY fk_dsc_producto (id_producto),
    CONSTRAINT fk_dsc_solicitud FOREIGN KEY (id_solicitud) REFERENCES solicitudes_compra (id_solicitud) ON DELETE CASCADE,
    CONSTRAINT fk_dsc_producto FOREIGN KEY (id_producto) REFERENCES productos (id_producto)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$receptionDocumentColumn = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'movimientos' AND COLUMN_NAME = 'documento_recepcion'");
if (!$receptionDocumentColumn || (int)$receptionDocumentColumn['n'] === 0) {
    $jv_db->execute("ALTER TABLE movimientos ADD COLUMN documento_recepcion VARCHAR(100) DEFAULT NULL AFTER status");
}
$pinEmergenciaColumn = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'pin_emergencia'");
if (!$pinEmergenciaColumn || (int)$pinEmergenciaColumn['n'] === 0) {
    $jv_db->execute("ALTER TABLE usuarios ADD COLUMN pin_emergencia VARCHAR(60) DEFAULT NULL COMMENT 'bcrypt hash de PIN 6 digitos de emergencia' AFTER respuesta_seguridad");
}

// --- 7d. Login intentos: columna usuario (doble bloqueo IP + usuario) ---
$loginUsuarioColumn = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'login_intentos' AND COLUMN_NAME = 'usuario'");
if (!$loginUsuarioColumn || (int)$loginUsuarioColumn['n'] === 0) {
    $jv_db->execute("ALTER TABLE login_intentos ADD COLUMN usuario VARCHAR(100) DEFAULT NULL AFTER ip_address");
    $jv_db->execute("ALTER TABLE login_intentos DROP INDEX idx_ip_unique");
    $jv_db->execute("ALTER TABLE login_intentos ADD UNIQUE INDEX idx_ip_user_unique (ip_address, usuario)");
    error_log("[JV3000] Migración login_intentos: columna usuario agregada.");
}

// --- 7e. Cuentas por pagar: tabla pagos_compra (historial de pagos parciales) ---
$pagosTable = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'pagos_compra'");
if (!$pagosTable || (int)$pagosTable['n'] === 0) {
    $jv_db->execute("CREATE TABLE IF NOT EXISTS pagos_compra (
        id_pago int(11) NOT NULL AUTO_INCREMENT,
        id_compra int(11) NOT NULL,
        id_usuario int(11) NOT NULL,
        monto decimal(10,2) NOT NULL,
        metodo_pago enum('Efectivo','Transferencia','Cheque','Otro') NOT NULL,
        detalle_pago json DEFAULT NULL,
        fecha_pago timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id_pago),
        KEY fk_pago_compra (id_compra),
        KEY fk_pago_usuario (id_usuario),
        CONSTRAINT fk_pago_compra FOREIGN KEY (id_compra) REFERENCES compras (id_compra) ON DELETE CASCADE,
        CONSTRAINT fk_pago_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    error_log("[JV3000] Tabla pagos_compra creada.");
}

// --- 7f. Optimistic locking: columna updated_at en tablas clave ---
foreach (['productos', 'compras', 'proveedores', 'clientes'] as $tablaLock) {
    $colCheck = $jv_db->fetchOne("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '$tablaLock' AND COLUMN_NAME = 'updated_at'");
    if (!$colCheck || (int)$colCheck['n'] === 0) {
        $jv_db->execute("ALTER TABLE $tablaLock ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        error_log("[JV3000] Migración optimistic locking: updated_at agregada a $tablaLock.");
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
    header("Location: " . ($ref !== '' ? $ref : 'dashboard/index.php'));
    exit;
});

// ==========================================
// COMPATIBILIDAD
// ==========================================

// --- 12. Variable global $db para compatibilidad con helpers legacy ---
$db = Database::getInstance();
