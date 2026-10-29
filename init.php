<?php

// ==========================================
// CONFIGURACIÃ“N INICIAL
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

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    die('Componente: PHP del servidor web. Evidencia: JV3000 requiere PHP 8.2 o superior y el proceso web estÃ¡ usando PHP '
        . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8')
        . '. Revisa con la persona administradora quÃ© versiÃ³n usa Apache; si no puede corregirse mediante el procedimiento local autorizado, detente y contacta soporte. No se modificÃ³ el servidor.');
}
if (!extension_loaded('mysqli')) {
    http_response_code(500);
    die('Componente: requisitos de PHP del servidor web. Evidencia: la extensiÃ³n mysqli no estÃ¡ disponible para el proceso web. Pide al administrador del PC servidor que revise el php.ini que utiliza Apache; no compartas configuraciones privadas ni actives extensiones desde esta pÃ¡gina. Si persiste, detente y contacta soporte.');
}

// --- 4. Cargar constantes ---
require_once __DIR__ . '/includes/config.php';

// --- 5. Cargar clases core ---
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/StartupDiagnostics.php';
require_once __DIR__ . '/includes/InstaladorInicial.php';
require_once __DIR__ . '/includes/EstadoInstalacion.php';

/**
 * Presenta orientaciÃ³n segura y consistente para cada estado bloqueado.
 *
 * @param array $diagnostic Resultado fijo de StartupDiagnostics.
 * @return string HTML escapado del diagnÃ³stico.
 */
function jv_render_diagnostico(array $diagnostic): string
{
    $checks = $diagnostic['checks'] ?? [];
    $checksHtml = $checks !== []
        ? '<ol>' . implode('', array_map(
            static fn(string $check): string => '<li>' . htmlspecialchars($check, ENT_QUOTES, 'UTF-8') . '</li>',
            $checks
        )) . '</ol>'
        : '';
    $support = $diagnostic['support_data'] ?? [];
    $supportHtml = $support !== []
        ? '<ul>' . implode('', array_map(
            static fn(string $item): string => '<li>' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</li>',
            $support
        )) . '</ul>'
        : '';
    $optionsHtml = !empty($diagnostic['options'])
        ? '<ul>' . implode('', array_map(
            static fn(string $option): string => '<li>' . htmlspecialchars($option, ENT_QUOTES, 'UTF-8') . '</li>',
            $diagnostic['options']
        )) . '</ul>'
        : '';

    return '<p><strong>Componente:</strong> ' . htmlspecialchars((string)($diagnostic['component'] ?? 'JV3000'), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>QuÃ© se observÃ³:</strong> ' . htmlspecialchars((string)($diagnostic['evidence'] ?? $diagnostic['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>' . (($diagnostic['cause_status'] ?? 'posible') === 'confirmada' ? 'Causa confirmada' : 'Causa posible') . ':</strong> '
        . htmlspecialchars((string)($diagnostic['cause'] ?? $diagnostic['message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
        . $checksHtml
        . $optionsHtml
        . '<p><strong>CuÃ¡ndo detenerse:</strong> ' . htmlspecialchars((string)($diagnostic['escalate'] ?? $diagnostic['next_step'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>'
        . ($supportHtml !== '' ? '<p><strong>InformaciÃ³n no sensible para soporte:</strong></p>' . $supportHtml : '');
}

/**
 * Crea el lector del marcador con configuraciÃ³n local y raÃ­z web canÃ³nica.
 *
 * @return EstadoInstalacion Lector del estado privado de instalaciÃ³n.
 */
function jv_crear_estado_instalacion(): EstadoInstalacion
{
    $directorio = getenv('JV_INSTALL_STATE_DIR');

    return new EstadoInstalacion(
        $directorio === false ? null : $directorio,
        (string)($_SERVER['DOCUMENT_ROOT'] ?? ''),
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
}

if (DB_CONFIG_STATUS !== 'ready') {
    $configurationDiagnostic = StartupDiagnostics::fromConfigurationProblem(
        DB_CONFIG_STATUS,
        DB_CONFIG_MISSING
    );
    if (DB_CONFIG_STATUS === 'invalid') {
        $configurationDiagnostic = StartupDiagnostics::fromConfigurationProblem('invalid');
    }
    http_response_code(503);
    die("<main style='background:#020617;color:#e2e8f0;font-family:sans-serif;min-height:100vh;padding:40px 16px;box-sizing:border-box;'>
        <section style='max-width:720px;margin:auto;border:1px solid #334155;padding:32px;border-radius:16px;background:#0f172a;'>
            <h1 style='color:#fbbf24;'>" . htmlspecialchars($configurationDiagnostic['title'], ENT_QUOTES, 'UTF-8') . '</h1>'
            . jv_render_diagnostico($configurationDiagnostic) .
        '</section>
    </main>');
}

// ==========================================
// BASE DE DATOS
// ==========================================

// --- 6. Conectar a la base configurada o detenerse en diagnÃ³stico seguro ---
function jv_db_error_page(?Throwable $error = null): void
{
    $errorCode = $error ? (int)$error->getCode() : 0;
    $diagnostic = StartupDiagnostics::fromConnectionError(
        $error ?? new RuntimeException('No se proporcionÃ³ detalle de conexiÃ³n.')
    );
    if ($diagnostic['category'] === StartupDiagnostics::DATABASE_MISSING) {
        jv_installation_page('ausente');
    }
    if ($error) {
        error_log("[JV3000] DiagnÃ³stico de conexiÃ³n: {$diagnostic['category']} (cÃ³digo {$errorCode}).");
    }

    http_response_code(500);
    die("<div style='background:#020617;color:#f87171;font-family:sans-serif;text-align:center;padding:100px;min-height:100vh;box-sizing:border-box;'>
            <div style='max-width:650px;margin:auto;border:1px solid rgba(248,113,113,0.3);padding:40px;border-radius:20px;background:#0f172a;'>
                <h2 style='color:#ef4444;text-transform:uppercase;letter-spacing:2px;'>" . htmlspecialchars($diagnostic['title'], ENT_QUOTES, 'UTF-8') . "</h2>
                <div style='color:#cbd5e1;text-align:left;margin:20px auto;max-width:560px;'>" . jv_render_diagnostico($diagnostic) . "</div>
            </div>
         </div>");
}

/**
 * Detiene el arranque funcional ante un esquema no compatible o no verificable.
 *
 * @param array $diagnostic Mensaje seguro producido por StartupDiagnostics.
 */
function jv_schema_error_page(array $diagnostic): never
{
    http_response_code(503);
    die("<main style='background:#020617;color:#e2e8f0;font-family:sans-serif;min-height:100vh;padding:40px 16px;box-sizing:border-box;'>
        <section style='max-width:720px;margin:auto;border:1px solid #7f1d1d;padding:32px;border-radius:16px;background:#0f172a;'>
            <h1 style='color:#f87171;font-size:1.5rem;'>" . htmlspecialchars($diagnostic['title'], ENT_QUOTES, 'UTF-8') . "</h1>
            <div style='color:#cbd5e1;margin-top:20px;'>" . jv_render_diagnostico($diagnostic) . "</div>
        </section>
    </main>");
}

/**
 * Cuenta tablas de la base seleccionada usando solo metadatos.
 *
 * @param mysqli $connection ConexiÃ³n a la base configurada.
 * @return int Cantidad de tablas visibles.
 */
function jv_contar_tablas_destino(mysqli $connection): int
{
    $statement = $connection->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"
    );
    if (!$statement) {
        throw new RuntimeException('No se pudieron consultar los metadatos del destino.');
    }
    $databaseName = DB_NAME;
    $statement->bind_param('s', $databaseName);
    $statement->execute();
    $statement->bind_result($tableCount);
    $statement->fetch();
    $statement->close();

    return (int)$tableCount;
}

/**
 * Selecciona la base sin dejar que la excepción de mysqli decida el resultado.
 *
 * PHP 8.1+ activa MYSQLI_REPORT_STRICT por defecto: una base ausente lanza
 * mysqli_sql_exception en lugar de devolver false, y eso convertía el estado
 * legítimo "ausente" en "desconocido", bloqueando la instalación limpia.
 *
 * @param mysqli $connection Conexión ya abierta al servidor.
 * @param string $base Nombre lógico de la base a seleccionar.
 * @return bool True si la selección terminó sin error.
 */
function seleccionarBaseSinExcepcion(mysqli $connection, string $base): bool
{
    try {
        return @mysqli_select_db($connection, $base);
    } catch (mysqli_sql_exception) {
        return false;
    }
}

/**
 * Comprueba el destino sin crearlo ni modificarlo.
 *
 * @return string Estado observable: ausente, vacio, existente o desconocido.
 */
function jv_estado_destino_instalacion(): string
{
    $connection = null;
    try {
        $connection = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
        if (!$connection) {
            return 'desconocido';
        }
        if (!seleccionarBaseSinExcepcion($connection, DB_NAME)) {
            return mysqli_errno($connection) === 1049 ? 'ausente' : 'desconocido';
        }

        return jv_contar_tablas_destino($connection) === 0 ? 'vacio' : 'existente';
    } catch (Throwable) {
        return 'desconocido';
    } finally {
        if ($connection instanceof mysqli) {
            mysqli_close($connection);
        }
    }
}

/**
 * Importa v6 Ãºnicamente a un destino que vuelve a comprobarse como ausente o vacÃ­o.
 *
 * @return bool True si el SQL terminÃ³ sin errores.
 */
function jv_importar_esquema_inicial(): bool
{
    $connection = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    if (!$connection) {
        return false;
    }

    try {
        $destinationState = jv_estado_destino_instalacion();
        if (!in_array($destinationState, ['ausente', 'vacio'], true)) {
            return false;
        }

        if ($destinationState === 'ausente') {
            $databaseIdentifier = '`' . str_replace('`', '``', DB_NAME) . '`';
            if (!$connection->query(
                "CREATE DATABASE {$databaseIdentifier} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
            )) {
                return false;
            }
        }
        if (!mysqli_select_db($connection, DB_NAME) || jv_contar_tablas_destino($connection) !== 0) {
            return false;
        }

        $sqlContent = file_get_contents(__DIR__ . '/db/jv3000_portable_v6.sql');
        if ($sqlContent === false || trim($sqlContent) === '' || !mysqli_multi_query($connection, $sqlContent)) {
            return false;
        }
        do {
            $result = mysqli_store_result($connection);
            if ($result instanceof mysqli_result) {
                mysqli_free_result($result);
            }
            if (mysqli_errno($connection) !== 0) {
                return false;
            }
        } while (mysqli_more_results($connection) && mysqli_next_result($connection));

        return mysqli_errno($connection) === 0;
    } catch (Throwable) {
        return false;
    } finally {
        mysqli_close($connection);
    }
}

/**
 * Presenta la confirmaciÃ³n separada de instalaciÃ³n inicial y detiene el arranque.
 *
 * @param string $estadoInicial Estado observado antes de mostrar la ruta.
 */
function jv_installation_page(string $estadoInicial): never
{
    $resultado = null;
    $marcador = jv_crear_estado_instalacion();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['confirmar_instalacion'])) {
        $csrfRecibido = $_POST['csrf_token'] ?? '';
        $csrfValido = is_string($csrfRecibido)
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $csrfRecibido);
        $estadoDestino = jv_estado_destino_instalacion();
        try {
            $schemaSql = file_get_contents(__DIR__ . '/db/jv3000_portable_v6.sql');
            $perfilActual = $schemaSql === false
                ? []
                : SchemaCompatibility::profileFromSql($schemaSql, 'v6');
        } catch (Throwable) {
            $perfilActual = [];
        }
        $resultado = InstaladorInicial::procesar(
            'POST',
            ($_POST['confirmacion_instalacion_nueva'] ?? '') === '1',
            $csrfValido,
            $estadoDestino,
            'jv_importar_esquema_inicial',
            static function () use ($perfilActual): array {
                $connection = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
                if (!$connection) {
                    return ['status' => SchemaCompatibility::UNKNOWN];
                }
                try {
                    $perfilObservado = jv_read_schema_profile($connection, DB_NAME);
                    return SchemaCompatibility::compare($perfilObservado, $perfilActual);
                } finally {
                    mysqli_close($connection);
                }
            },
            static function (): void {
                header('Location: ' . BASE_PATH . 'index.php');
                exit;
            },
            $marcador
        );
    }

    $fallo = match ($resultado['estado'] ?? null) {
        InstaladorInicial::CSRF_INVALIDO => 'La confirmaciÃ³n de seguridad no es vÃ¡lida o venciÃ³. No se modificÃ³ la base; vuelve a cargar la pÃ¡gina y confirma de nuevo.',
        InstaladorInicial::REQUIERE_CONFIRMACION => 'La instalaciÃ³n no comenzÃ³ porque falta la confirmaciÃ³n humana explÃ­cita.',
        InstaladorInicial::DESTINO_EXISTENTE => 'El destino contiene tablas o no se pudo confirmar como ausente/vacÃ­o. La importaciÃ³n estÃ¡ bloqueada.',
        InstaladorInicial::INCOMPLETA => 'La importaciÃ³n no terminÃ³ o no pudo verificarse. El marcador bloquea nuevos intentos; solicita revisiÃ³n manual.',
        InstaladorInicial::ESQUEMA_INCOMPATIBLE => 'El esquema importado no pudo verificarse como compatible. El marcador bloquea nuevos intentos; no borres tablas ni el marcador.',
        default => '',
    };
    $estadoPantalla = in_array($resultado['estado'] ?? null, [
        InstaladorInicial::INCOMPLETA,
        InstaladorInicial::ESQUEMA_INCOMPATIBLE,
    ], true)
        ? $resultado['estado']
        : $estadoInicial;
    if (($resultado['estado'] ?? null) === InstaladorInicial::DESTINO_EXISTENTE) {
        $estadoPantalla = 'existente';
    }
    if ($estadoPantalla === 'desconocido') {
        $fallo = 'No fue posible confirmar el estado del destino. No se realizÃ³ ninguna instalaciÃ³n.';
    } elseif ($estadoPantalla === 'existente') {
        $fallo = 'El destino ya contiene tablas. La instalaciÃ³n nueva estÃ¡ bloqueada y no se modificÃ³.';
    }
    $estadoMarcador = $marcador->diagnosticar();
    if (($resultado['estado'] ?? null) === InstaladorInicial::MARCADOR_NO_VALIDO) {
        $estadoMarcador = (string)($resultado['motivo'] ?? $estadoMarcador);
    } elseif (in_array($resultado['estado'] ?? null, [
        EstadoInstalacion::EN_PROGRESO,
        EstadoInstalacion::INCOMPLETA,
        EstadoInstalacion::COMPLETADA,
    ], true)) {
        $estadoMarcador = $resultado['estado'];
    }

    $token = htmlspecialchars(Security::generateToken(), ENT_QUOTES, 'UTF-8');
    $errorHtml = $fallo !== ''
        ? "<p role='alert' style='color:#fca5a5;margin:20px 0;'>" . htmlspecialchars($fallo, ENT_QUOTES, 'UTF-8') . '</p>'
        : '';
    $installationForm = in_array($estadoPantalla, ['ausente', 'vacio'], true)
        && $estadoMarcador === EstadoInstalacion::LISTA
        && !in_array($resultado['estado'] ?? null, [
            InstaladorInicial::INCOMPLETA,
            InstaladorInicial::ESQUEMA_INCOMPATIBLE,
            InstaladorInicial::LISTA,
            InstaladorInicial::DESTINO_EXISTENTE,
            InstaladorInicial::MARCADOR_NO_VALIDO,
        ], true)
        ? "<form method='post' autocomplete='off' style='text-align:left;margin-top:24px;'>
                <input type='hidden' name='csrf_token' value='{$token}'>
                <label style='display:flex;gap:10px;align-items:flex-start;color:#e2e8f0;'>
                    <input type='checkbox' name='confirmacion_instalacion_nueva' value='1' required>
                    <span>Confirmo que este destino es para una instalaciÃ³n nueva y entiendo que JV3000 crearÃ¡/importarÃ¡ el esquema limpio v6, sin recuperar datos anteriores.</span>
                </label>
                <button type='submit' name='confirmar_instalacion' value='1' style='margin-top:18px;background:#b45309;color:white;border:0;padding:12px 18px;border-radius:8px;font-weight:600;cursor:pointer;'>Confirmar instalaciÃ³n limpia</button>
            </form>"
        : '';

    http_response_code(($resultado['estado'] ?? null) === InstaladorInicial::CSRF_INVALIDO
        ? 403
        : ($fallo !== '' || $estadoMarcador !== EstadoInstalacion::LISTA ? 409 : 200));
    $titulo = match ($estadoPantalla) {
        'vacio' => 'Base de datos vacÃ­a',
        InstaladorInicial::INCOMPLETA => 'InstalaciÃ³n incompleta',
        InstaladorInicial::ESQUEMA_INCOMPATIBLE => 'Esquema incompatible',
        InstaladorInicial::DESTINO_EXISTENTE, 'existente' => 'InstalaciÃ³n bloqueada',
        default => 'Base de datos no encontrada',
    };
    $observedMessage = $estadoPantalla === 'vacio'
        ? 'El servidor respondiÃ³ y la base configurada existe, pero no contiene tablas visibles. Esto no demuestra por sÃ­ solo que sea correcto usarla.'
        : ($estadoPantalla === 'ausente'
            ? 'El servidor respondiÃ³ y no encontrÃ³ la base configurada. Esto no demuestra por sÃ­ solo que el nombre sea correcto ni que se autorice crearla.'
            : 'No se pudo confirmar de forma segura el estado del destino.');
    $diagnosticoBase = StartupDiagnostics::fromConnectionError(
        new RuntimeException(
            'Estado de destino para la orientaciÃ³n de instalaciÃ³n.',
            $estadoPantalla === 'ausente' ? 1049 : 9999
        )
    );
    $diagnosticoBase['evidence'] = $observedMessage;
    $diagnosticoBase['message'] = $observedMessage;
    if ($estadoPantalla === 'vacio') {
        $diagnosticoBase['component'] = 'Existencia y contenido estructural de la base.';
        $diagnosticoBase['cause'] = 'La base existe sin tablas visibles; el sistema no infiere que estÃ© autorizada para importar datos.';
        $diagnosticoBase['checks'] = [
            'Confirma con la persona administradora que el nombre corresponde al destino previsto.',
            'Si se esperaban datos, detente y localiza primero el procedimiento de respaldo institucional.',
            'La instalaciÃ³n limpia solo estÃ¡ disponible despuÃ©s de revisar el marcador y confirmar deliberadamente.',
        ];
    } elseif ($estadoPantalla === 'existente') {
        $diagnosticoBase['component'] = 'Destino de base existente.';
        $diagnosticoBase['evidence'] = 'La comprobaciÃ³n del POST observÃ³ tablas o no pudo confirmar un destino ausente/vacÃ­o.';
        $diagnosticoBase['message'] = $diagnosticoBase['evidence'];
        $diagnosticoBase['cause_status'] = 'confirmada';
        $diagnosticoBase['cause'] = 'El destino no cumple las condiciones para una instalaciÃ³n limpia.';
        $diagnosticoBase['checks'] = [
            'No se iniciÃ³ la importaciÃ³n.',
            'Conserva la base y solicita una revisiÃ³n del esquema existente.',
        ];
    }
    $diagnosticoMarcador = StartupDiagnostics::fromInstallationState($estadoMarcador);
    $installationWarning = in_array($estadoPantalla, ['ausente', 'vacio'], true)
        ? '<p style="color:#fca5a5;margin-top:16px;">La opciÃ³n siguiente crearÃ¡ una instalaciÃ³n limpia sin recuperar datos anteriores. No continÃºes si esperas encontrar informaciÃ³n previa.</p>'
        : '';
    die("<main style='background:#020617;color:#e2e8f0;font-family:sans-serif;min-height:100vh;padding:40px 16px;box-sizing:border-box;'>
        <section style='max-width:760px;margin:auto;border:1px solid #334155;padding:32px;border-radius:16px;background:#0f172a;'>
            <h1 style='color:#fbbf24;font-size:1.5rem;'>{$titulo}</h1>
            <div style='color:#cbd5e1;margin-top:20px;'>" . jv_render_diagnostico($diagnosticoBase) . '</div>'
            . $errorHtml
            . ($estadoMarcador !== EstadoInstalacion::LISTA
                ? "<h2 style='color:#f87171;margin-top:24px;'>PreparaciÃ³n privada de instalaciÃ³n</h2><div style='color:#cbd5e1;'>" . jv_render_diagnostico($diagnosticoMarcador) . '</div>'
                : $installationWarning)
            . $installationForm
            . '
        </section>
    </main>');
}

/**
 * Lee solo metadatos estructurales de las tablas de la base seleccionada.
 *
 * @param mysqli $connection ConexiÃ³n ya establecida.
 * @param string $database Nombre de la base configurada.
 * @return array Perfil estructural observado.
 */
function jv_read_schema_profile(?mysqli $connection, string $database): array
{
    if (!$connection) {
        throw new RuntimeException('La conexiÃ³n no estÃ¡ disponible para leer metadatos.');
    }

    $statement = $connection->prepare(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'"
    );
    if (!$statement) {
        throw new RuntimeException('No se pudieron consultar los metadatos del esquema.', $connection->errno);
    }
    $statement->bind_param('s', $database);
    $statement->execute();
    $result = $statement->get_result();
    $tableNames = $result->fetch_all(MYSQLI_NUM);
    $result->free();
    $statement->close();

    $definitions = [];
    foreach ($tableNames as $tableName) {
        $escapedName = str_replace('`', '``', (string)$tableName[0]);
        $definitionResult = $connection->query("SHOW CREATE TABLE `{$escapedName}`");
        if (!$definitionResult) {
            throw new RuntimeException('No se pudo leer la definiciÃ³n de una tabla.', $connection->errno);
        }
        $definition = $definitionResult->fetch_row();
        $definitionResult->free();
        if (!isset($definition[1])) {
            throw new RuntimeException('La definiciÃ³n estructural de una tabla no estÃ¡ disponible.');
        }
        $definitions[] = $definition[1] . ';';
    }

    if ($definitions === []) {
        return ['version' => 'observed', 'tables' => []];
    }

    return SchemaCompatibility::profileFromSql(implode("\n", $definitions), 'observed');
}

try {
    Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
} catch (Throwable $e) {
    $connectionDiagnostic = StartupDiagnostics::fromConnectionError($e);
    error_log(
        "[JV3000] DiagnÃ³stico de conexiÃ³n de arranque: {$connectionDiagnostic['category']} "
        . '(cÃ³digo ' . (int)$e->getCode() . ').'
    );
    try {
        $connectionWithoutDatabase = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    } catch (Throwable) {
        $connectionWithoutDatabase = false;
    }
    if (!$connectionWithoutDatabase) {
        jv_db_error_page($e);
    }

    try {
        if (!mysqli_select_db($connectionWithoutDatabase, DB_NAME)) {
            throw new RuntimeException('No se pudo seleccionar la base configurada.', mysqli_errno($connectionWithoutDatabase));
        }
    } catch (Throwable $databaseCheckError) {
        mysqli_close($connectionWithoutDatabase);
        if ((int)$databaseCheckError->getCode() === 1049) {
            $databaseDiagnostic = StartupDiagnostics::fromConnectionError($databaseCheckError);
            error_log("[JV3000] DiagnÃ³stico de base de datos: {$databaseDiagnostic['category']}.");
            jv_db_error_page($databaseCheckError);
        }
        jv_db_error_page($databaseCheckError);
    }
    mysqli_close($connectionWithoutDatabase);
    error_log("[JV3000] La base existe, pero la conexiÃ³n normal fallÃ³; categorÃ­a {$connectionDiagnostic['category']}.");
    jv_db_error_page($e);
}

$activeConnection = Database::getInstance()->getConnection();
if ($activeConnection instanceof mysqli) {
    try {
        if (jv_contar_tablas_destino($activeConnection) === 0) {
            jv_installation_page('vacio');
        }
    } catch (Throwable) {
        jv_installation_page('desconocido');
    }
}

try {
    $schemaSql = file_get_contents(__DIR__ . '/db/jv3000_portable_v6.sql');
    if ($schemaSql === false) {
        throw new RuntimeException('No se pudo leer el perfil de esquema versionado.');
    }
    $currentSchemaProfile = SchemaCompatibility::profileFromSql($schemaSql, 'v6');
    $observedSchemaProfile = jv_read_schema_profile(
        Database::getInstance()->getConnection(),
        DB_NAME
    );
    $schemaComparison = SchemaCompatibility::compare($observedSchemaProfile, $currentSchemaProfile);
    $schemaDiagnostic = StartupDiagnostics::fromSchemaComparison($schemaComparison);
} catch (Throwable $schemaError) {
    $schemaComparison = ['status' => SchemaCompatibility::UNKNOWN];
    $schemaDiagnostic = StartupDiagnostics::fromConnectionError($schemaError);
    if ($schemaDiagnostic['category'] === StartupDiagnostics::CONNECTION_UNKNOWN) {
        $schemaDiagnostic = StartupDiagnostics::fromSchemaComparison($schemaComparison);
    }
}

$_SESSION['_jv_schema_diagnostic'] = $schemaDiagnostic['category'];
error_log("[JV3000] DiagnÃ³stico estructural de arranque: {$schemaDiagnostic['category']}.");
if (!StartupDiagnostics::allowsNormalStartup($schemaComparison)) {
    jv_schema_error_page($schemaDiagnostic);
}

// ==========================================
// SEGURIDAD Y SESIÃ“N
// ==========================================

// --- 8. ValidaciÃ³n de sesiÃ³n (salvo pÃ¡ginas pÃºblicas) ---
$publicPages = ['login.php', 'recuperar.php', 'logout.php'];
$currentScript = basename($_SERVER['SCRIPT_NAME']);

if (!in_array($currentScript, $publicPages)) {
    Security::validateSession();
}

// --- 9. Sanitización global de inputs ---Ã³n global de inputs ---
Security::sanitizeGlobals();

// --- 10. ValidaciÃ³n CSRF en peticiones POST ---
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

    // Observabilidad: última red del sistema. Todo error que no pasó por el
    // Router queda en el diario (tabla errores) como los del boundary.
    try {
        require_once __DIR__ . '/models/ErrorLog.php';
        (new ErrorLog())->registrar($e);
    } catch (Throwable) {
        // El diagnóstico nunca interrumpe la respuesta al usuario.
    }

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['ok' => false, 'success' => false, 'error' => 'No se completó la operación. Intenta de nuevo.']);
        exit;
    }

    $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'No se completó la operación. Intenta de nuevo; si vuelve a fallar, avisa al administrador.'];
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

