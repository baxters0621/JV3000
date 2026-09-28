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

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    die('JV3000 requiere PHP 8.2 o superior. Selecciona PHP 8.2+ en la configuración de Apache/XAMPP.');
}
if (!extension_loaded('mysqli')) {
    http_response_code(500);
    die('JV3000 requiere la extensión mysqli. Actívala en el php.ini que utiliza Apache y reinicia Apache.');
}

// --- 5. Cargar clases core ---
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/helpers.php';

// ==========================================
// BASE DE DATOS
// ==========================================

// --- 6/7. Conectar BD (auto-restaurar backup si la BD no existe) ---
function jv_db_error_page(?Throwable $error = null): void
{
    $errorCode = $error ? (int)$error->getCode() : 0;
    $diagnostic = match ($errorCode) {
        1044, 1045 => 'MySQL rechazó el usuario o la contraseña. Usa el asistente de conexión para configurar los datos de este equipo.',
        1049 => 'La base de datos configurada no existe. El asistente de instalación puede crearla si el usuario tiene permiso CREATE DATABASE.',
        2002, 2003 => 'No se pudo alcanzar el servidor MySQL. Verifica que MySQL esté iniciado y usa el asistente para indicar el host y puerto correctos.',
        default => 'Revisa que MySQL esté iniciado y usa el asistente para comprobar la configuración. El registro de Apache contiene el diagnóstico técnico.',
    };
    if ($error) {
        error_log("[JV3000] Error de conexión a la base de datos (código {$errorCode}): " . $error->getMessage());
    }

    http_response_code(500);
    die("<div style='background:#020617;color:#f87171;font-family:sans-serif;text-align:center;padding:100px;min-height:100vh;box-sizing:border-box;'>
            <div style='max-width:650px;margin:auto;border:1px solid rgba(248,113,113,0.3);padding:40px;border-radius:20px;background:#0f172a;'>
                <h2 style='color:#ef4444;text-transform:uppercase;letter-spacing:2px;'>Error de Conexión</h2>
                <p style='color:#94a3b8;margin-top:20px;'>No se pudo conectar a la base de datos.</p>
                <p style='color:#fbbf24;margin-top:20px;'>" . htmlspecialchars($diagnostic, ENT_QUOTES, 'UTF-8') . "</p>
                <p style='color:#94a3b8;margin-top:20px;'>Recarga la página para abrir el asistente de conexión y guardar la configuración local de este equipo.</p>
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
    if (!is_file($sql_path) || !is_readable($sql_path)) {
        error_log("[JV3000] Archivo SQL no encontrado o no legible: " . $sql_path);
        return false;
    }
    $sql_content = file_get_contents($sql_path);
    if ($sql_content === false || trim($sql_content) === '') {
        error_log("[JV3000] Archivo SQL vacío: " . $sql_path);
        return false;
    }
    try {
        if (!mysqli_multi_query($conn, $sql_content)) {
            error_log("[JV3000] No se pudo iniciar la importación SQL: " . mysqli_error($conn));
            return false;
        }
        do {
            if ($res = mysqli_store_result($conn)) {
                mysqli_free_result($res);
            }
            if (mysqli_errno($conn) !== 0) {
                error_log("[JV3000] Error durante la importación SQL: " . mysqli_error($conn));
                return false;
            }
        } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    } catch (mysqli_sql_exception $e) {
        error_log("[JV3000] Importación SQL fallida: " . $e->getMessage());
        return false;
    }
    $check = @mysqli_query($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
    $row = $check ? @mysqli_fetch_assoc($check) : null;
    if (!$row || (int)$row['total'] < 20) {
        error_log("[JV3000] Importación incompleta (menos de 20 tablas): " . $sql_path);
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
        $conn_demo = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
        if ($conn_demo && jv_importar_sql($conn_demo, __DIR__ . '/db/jv3000_portable_v6.sql')) {
            mysqli_close($conn_demo);
            header('Location: ' . BASE_PATH . 'dashboard/index.php');
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
        ? "<p style='color:#fbbf24;margin-top:20px;'>La instalación falló. Revisa config/.env, que MySQL permita crear la base y sus tablas, que db/jv3000_portable_v6.sql exista y que el registro de Apache no muestre errores SQL.</p>"
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

/**
 * Prueba y guarda las credenciales locales de MySQL mediante un formulario protegido por CSRF.
 */
function jv_db_setup_page(?Throwable $initialError = null): never
{
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
        http_response_code(403);
        die('Por seguridad, el asistente de base de datos solo está disponible desde el mismo equipo. Configura config/.env localmente.');
    }

    $errorMessage = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_conexion'])) {
        Security::validateCSRF();
        $host = trim((string)($_POST['db_host'] ?? ''));
        $user = trim((string)($_POST['db_user'] ?? ''));
        $password = (string)($_POST['db_pass'] ?? '');
        $database = trim((string)($_POST['db_name'] ?? ''));
        $port = filter_var($_POST['db_port'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);

        if (filter_var($host, FILTER_VALIDATE_IP) === false && !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]{0,252}\z/', $host)) {
            $errorMessage = 'El host debe ser un nombre o dirección válida, por ejemplo localhost o 127.0.0.1.';
        } elseif (!preg_match('/\A[A-Za-z0-9_][A-Za-z0-9_.@-]{0,127}\z/', $user)) {
            $errorMessage = 'El usuario MySQL contiene caracteres no permitidos.';
        } elseif (!preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $database)) {
            $errorMessage = 'El nombre de la base solo puede contener letras, números y guion bajo.';
        } elseif ($port === false) {
            $errorMessage = 'El puerto debe ser un número entre 1 y 65535.';
        } elseif (strlen($password) > 1024 || str_contains($password, "\0")) {
            $errorMessage = 'La contraseña no es válida.';
        } else {
            $probe = null;
            try {
                $probe = @mysqli_connect($host, $user, $password, '', (int)$port);
                if (!$probe) {
                    throw new RuntimeException('No se pudo conectar al servidor MySQL.', mysqli_connect_errno());
                }
                mysqli_set_charset($probe, 'utf8mb4');
                try {
                    mysqli_select_db($probe, $database);
                } catch (mysqli_sql_exception $e) {
                    if ((int)$e->getCode() !== 1049) {
                        throw $e;
                    }
                }
                mysqli_close($probe);
                $probe = null;

                $envPath = __DIR__ . '/config/.env';
                $envContent = "JV_DB_HOST={$host}\n"
                    . "JV_DB_USER={$user}\n"
                    . "JV_DB_PASS_B64=" . base64_encode($password) . "\n"
                    . "JV_DB_NAME={$database}\n"
                    . "JV_DB_PORT={$port}\n";
                if (file_put_contents($envPath, $envContent, LOCK_EX) === false) {
                    throw new RuntimeException('No se pudo guardar config/.env. Verifica permisos de escritura en la carpeta config.');
                }
                if (DIRECTORY_SEPARATOR !== '\\' && !chmod($envPath, 0600)) {
                    throw new RuntimeException('No se pudieron restringir los permisos del archivo config/.env.');
                }
                error_log('[JV3000] Configuración local de MySQL guardada desde el asistente.');
                header('Location: ' . BASE_PATH . 'index.php');
                exit;
            } catch (Throwable $e) {
                if ($probe instanceof mysqli) {
                    mysqli_close($probe);
                }
                $errorMessage = match ((int)$e->getCode()) {
                    1044, 1045 => 'MySQL rechazó el usuario o la contraseña. Verifica los datos e inténtalo de nuevo.',
                    1049 => 'La base aún no existe; se instalará después de guardar. El usuario necesita permiso para crearla.',
                    2002, 2003 => 'No se encontró MySQL en ese host y puerto. Confirma que el servicio esté iniciado.',
                    default => $e instanceof mysqli_sql_exception
                        ? 'MySQL rechazó la conexión o el acceso a esa base. Verifica credenciales y permisos.'
                        : $e->getMessage(),
                };
                error_log('[JV3000] Asistente de conexión MySQL: ' . $e->getMessage());
            }
        }
    } elseif ($initialError) {
        $errorCode = (int)$initialError->getCode();
        $errorMessage = match ($errorCode) {
            1044, 1045 => 'Los valores XAMPP habituales no conectaron: MySQL rechazó el usuario o la contraseña. Configura aquí los datos de este equipo.',
            2002, 2003 => 'No se pudo alcanzar MySQL con la configuración habitual. Confirma que esté iniciado e indica aquí el host y puerto.',
            default => 'No se pudo conectar con la configuración actual. Comprueba los datos de MySQL para este equipo.',
        };
    }

    $token = htmlspecialchars(Security::generateToken(), ENT_QUOTES, 'UTF-8');
    $host = htmlspecialchars((string)($_POST['db_host'] ?? DB_HOST), ENT_QUOTES, 'UTF-8');
    $user = htmlspecialchars((string)($_POST['db_user'] ?? DB_USER), ENT_QUOTES, 'UTF-8');
    $database = htmlspecialchars((string)($_POST['db_name'] ?? DB_NAME), ENT_QUOTES, 'UTF-8');
    $port = htmlspecialchars((string)($_POST['db_port'] ?? DB_PORT), ENT_QUOTES, 'UTF-8');
    $errorHtml = $errorMessage !== ''
        ? "<p role='alert' style='color:#fca5a5;margin:20px 0;'>" . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . "</p>"
        : '';

    http_response_code(200);
    die("<main style='background:#020617;color:#e2e8f0;font-family:sans-serif;min-height:100vh;padding:40px 16px;box-sizing:border-box;'>
        <section style='max-width:560px;margin:auto;border:1px solid #334155;padding:32px;border-radius:16px;background:#0f172a;'>
            <h1 style='color:#fbbf24;font-size:1.5rem;'>Configurar conexión de JV3000</h1>
            <p style='color:#cbd5e1;'>Se probaron los valores habituales de XAMPP. Ingresa los datos de MySQL de este equipo una sola vez; se guardarán localmente y no se subirán al repositorio. La contraseña se codifica para guardarla, pero Base64 no es cifrado.</p>
            {$errorHtml}
            <form method='post' autocomplete='off'>
                <input type='hidden' name='csrf_token' value='{$token}'>
                <label style='display:block;margin-top:14px;'>Servidor MySQL
                    <input required maxlength='253' name='db_host' value='{$host}' style='display:block;width:100%;padding:10px;margin-top:5px;box-sizing:border-box;'>
                </label>
                <label style='display:block;margin-top:14px;'>Puerto
                    <input required type='number' min='1' max='65535' name='db_port' value='{$port}' style='display:block;width:100%;padding:10px;margin-top:5px;box-sizing:border-box;'>
                </label>
                <label style='display:block;margin-top:14px;'>Usuario MySQL
                    <input required maxlength='128' name='db_user' value='{$user}' autocomplete='username' style='display:block;width:100%;padding:10px;margin-top:5px;box-sizing:border-box;'>
                </label>
                <label style='display:block;margin-top:14px;'>Contraseña MySQL
                    <input type='password' maxlength='1024' name='db_pass' autocomplete='new-password' style='display:block;width:100%;padding:10px;margin-top:5px;box-sizing:border-box;'>
                </label>
                <label style='display:block;margin-top:14px;'>Base de datos
                    <input required maxlength='64' name='db_name' value='{$database}' style='display:block;width:100%;padding:10px;margin-top:5px;box-sizing:border-box;'>
                </label>
                <button type='submit' name='guardar_conexion' value='1' style='margin-top:22px;background:#2563eb;color:white;border:0;padding:12px 18px;border-radius:8px;font-weight:600;cursor:pointer;'>Probar y guardar conexión</button>
            </form>
            <p style='color:#94a3b8;margin-top:20px;font-size:.9rem;'>Si la base es nueva, el usuario MySQL necesitará permiso para crear la base y sus tablas. Este asistente no borra bases existentes.</p>
        </section>
    </main>");
}

try {
    Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
} catch (Throwable $e) {
    $installed = false;
    $noBackup = false;
    $restoreFailed = false;
    try {
        $connectionWithoutDatabase = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    } catch (Throwable $connectionError) {
        $connectionWithoutDatabase = false;
    }
    if (!$connectionWithoutDatabase) {
        jv_db_setup_page($e);
    }
    if ($connectionWithoutDatabase) {
        $databaseExists = true;
        try {
            if (!mysqli_select_db($connectionWithoutDatabase, DB_NAME)) {
                throw new RuntimeException('No se pudo seleccionar la base de datos configurada.', mysqli_errno($connectionWithoutDatabase));
            }
        } catch (Throwable $databaseCheckError) {
            if ((int)$databaseCheckError->getCode() !== 1049) {
                mysqli_close($connectionWithoutDatabase);
                jv_db_setup_page($databaseCheckError);
            }
            $databaseExists = false;
        }
        if (!$databaseExists) {
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
            Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        } catch (Throwable $e2) {
            error_log("[JV3000] DB connection failed after auto-install: " . $e2->getMessage());
            jv_db_error_page($e2);
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
        jv_db_setup_page($e);
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

// --- 7e.1. Estado de pago parcial (idempotente) ---
$migracion_estado_pago = $jv_db->fetchOne(
    "SELECT valor FROM configuracion WHERE clave = 'status_pago_parcial_v6'"
);
if (!$migracion_estado_pago) {
    $columna_estado_pago = $jv_db->fetchOne(
        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'status_pago'",
        [DB_NAME]
    );
    if ($columna_estado_pago && strpos($columna_estado_pago['COLUMN_TYPE'], "'Parcial'") === false) {
        $jv_db->execute(
            "ALTER TABLE compras MODIFY status_pago ENUM('Pendiente','Parcial','Pagada') NOT NULL DEFAULT 'Pendiente'"
        );
    }
    $jv_db->execute(
        "UPDATE compras
         SET status_pago = CASE
             WHEN COALESCE(monto_pago, 0) <= 0 THEN 'Pendiente'
             WHEN monto_pago >= total THEN 'Pagada'
             ELSE 'Parcial'
         END"
    );
    $jv_db->execute(
        "INSERT INTO configuracion (clave, valor, descripcion, fecha_actualizado)
         VALUES ('status_pago_parcial_v6', '1', 'Estado de pago parcial habilitado', NOW())
         ON DUPLICATE KEY UPDATE valor = '1'"
    );
    error_log("[JV3000] Migración de estados de pago parciales aplicada.");
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
