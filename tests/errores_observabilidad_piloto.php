<?php

// ==========================================
// PILOTO: observabilidad (diario de errores)
// ==========================================
// Ejecutar desde la raíz: & "C:\xampp\php\php.exe" tests\errores_observabilidad_piloto.php
// Usa una base de datos falsa (en memoria): verifica las reglas del modelo
// ErrorLog sin tocar la base de datos real.
//  - Huella estable: mismo error → misma huella; otro archivo:línea → otra.
//  - El INSERT agrupa por huella (ON DUPLICATE KEY UPDATE ocurrencias).
//  - registrarCliente acota longitudes (mensaje 800, archivo 255, stack 4000)
//    y convierte vacíos en NULL; un mensaje vacío no se registra.
//  - purgar() emite el DELETE de retención; marcarResuelto actualiza y avisa.
//  - listar()/totalErrores() construyen los filtros y la paginación.
//  - Un fallo de la base de datos NO derriba la petición (caída silenciosa).
//  - AvisarCorreo no hace nada sin JV_MAIL_ADMIN y respeta el tope de 1 hora.
//  - Enganches presentes: Controller.php e init.php registran; diseno.js
//    reporta al endpoint con CSRF; el seed declara la tabla `errores`.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

class Database
{
    private static ?self $instance = null;

    /** Configuración de respuestas. */
    public ?array $filaDetalle = null;   // Detalle (SELECT * de un error)
    public array $filasListado = [];     // Filas del listado paginado
    public int $afectadas = 1;           // affectedRows del INSERT (1 = nuevo, 2 = repetido)
    public int $retornoUpdate = 1;       // Filas afectadas del UPDATE (marcarResuelto)
    public bool $falloEnExecute = false; // Simula la base de datos caída

    /** Registro de lo sucedido. */
    public array $consultas = [];
    public array $escrituras = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function execute(string $sql, array $params = []): ?object
    {
        $this->consultas[] = ['sql' => $sql, 'params' => $params];
        $this->escrituras[] = ['sql' => $sql, 'params' => $params];
        if ($this->falloEnExecute) {
            throw new RuntimeException('BD caída (simulada)');
        }
        return null;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->consultas[] = ['sql' => $sql, 'params' => $params];
        return $this->filaDetalle;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $this->consultas[] = ['sql' => $sql, 'params' => $params];
        return $this->filasListado;
    }

    public function update(string $tabla, array $datos, string $where, array $whereParams = []): int
    {
        $this->consultas[] = ['sql' => "UPDATE {$tabla}", 'params' => array_merge(array_values($datos), $whereParams)];
        $this->escrituras[] = ['tipo' => 'update', 'tabla' => $tabla, 'datos' => $datos, 'where' => $where, 'params' => $whereParams];
        return $this->retornoUpdate;
    }

    public function affectedRows(): int
    {
        return $this->afectadas;
    }
}

$root = dirname(__DIR__);
require_once $root . '/includes/helpers.php';
require_once $root . '/core/Model.php';
require_once $root . '/models/ErrorLog.php';

$db = Database::getInstance();
$modelo = new ErrorLog();
$afirmaciones = 0;

$afirmar = static function (bool $condicion, string $mensaje) use (&$afirmaciones): void {
    $afirmaciones++;
    if (!$condicion) {
        throw new RuntimeException('FALLO: ' . $mensaje);
    }
};

$limpiar = static function () use ($db): void {
    $db->filaDetalle = null;
    $db->filasListado = [];
    $db->afectadas = 1;
    $db->retornoUpdate = 1;
    $db->falloEnExecute = false;
    $db->consultas = [];
    $db->escrituras = [];
};

/** Devuelve los INSERT INTO errores registrados por el modelo falso. */
$insertsErrores = static function () use ($db): array {
    return array_values(array_filter($db->escrituras, static fn(array $e): bool => str_contains($e['sql'] ?? $e['tipo'] ?? '', 'INSERT INTO errores')));
};

/** Invoca calcularHuella (privado) para conocer la huella esperada. */
$huellaDe = static function (array $fila) use ($modelo): string {
    $metodo = new ReflectionMethod(ErrorLog::class, 'calcularHuella');
    $metodo->setAccessible(true);
    return $metodo->invoke($modelo, $fila);
};

// 1. Registrar una excepción: huella en el parámetro 1, agrupada por
//    archivo:línea y con contexto de petición vacío (CLI).
$limpiar();
$excepcion = new RuntimeException('Factura duplicada #123');
$modelo->registrar($excepcion);
$inserts = $insertsErrores();
$afirmar(count($inserts) === 1, 'registrar() emite un INSERT INTO errores.');
$huella1 = $inserts[0]['params'][0] ?? '';
$afirmar(strlen($huella1) === 32 && ctype_xdigit($huella1), 'La huella es un MD5 de 32 caracteres.');
$huellaEsperada = $huellaDe([
    'origen' => 'servidor',
    'clase' => RuntimeException::class,
    'mensaje' => 'Factura duplicada #123',
    'archivo' => $excepcion->getFile(),
    'linea' => $excepcion->getLine(),
]);
$afirmar($huella1 === $huellaEsperada, 'La huella sale de origen|clase|archivo:línea (estable pese a IDs en el mensaje).');
$afirmar(str_contains($inserts[0]['sql'], 'ON DUPLICATE KEY UPDATE ocurrencias = ocurrencias + 1, ultima_vez = NOW()'), 'La repetición suma ocurrencias y refresca la última vez (no duplica filas).');
$afirmar(($inserts[0]['params'][1] ?? '') === 'servidor', 'El origen de una excepción del servidor es servidor.');
$afirmar(($inserts[0]['params'][2] ?? null) === RuntimeException::class, 'La clase de la excepción queda registrada.');
$afirmar($inserts[0]['params'][6] === null, 'Sin petición HTTP, la URL queda NULL.');
$afirmar($inserts[0]['params'][9] === null, 'Sin sesión, el usuario queda NULL.');

// 2. Misma ubicación con mensajes distintos → misma huella (los IDs del
//    mensaje no separan); otra línea → otra huella.
$limpiar();
$modelo->registrarCliente(['mensaje' => 'Factura duplicada #123', 'archivo' => 'assets/js/compras.js', 'linea' => 55, 'stack' => '']);
$modelo->registrarCliente(['mensaje' => 'Factura duplicada #999', 'archivo' => 'assets/js/compras.js', 'linea' => 55, 'stack' => '']);
$inserts = $insertsErrores();
$afirmar($inserts[0]['params'][0] === $inserts[1]['params'][0], 'Dos fallos en el mismo archivo:línea comparten huella aunque el mensaje traiga IDs distintos.');
$limpiar();
$modelo->registrarCliente(['mensaje' => 'fallo x', 'archivo' => 'a.js', 'linea' => 1, 'stack' => '']);
$modelo->registrarCliente(['mensaje' => 'fallo x', 'archivo' => 'a.js', 'linea' => 2, 'stack' => '']);
$inserts = $insertsErrores();
$afirmar($inserts[0]['params'][0] !== $inserts[1]['params'][0], 'Errores en líneas distintas tienen huellas distintas.');

// 3. affectedRows = 2 (repetida): solo un INSERT y sin fila nueva.
$limpiar();
$db->afectadas = 2;
$modelo->registrar($excepcion);
$afirmar(count($insertsErrores()) === 1, 'Un error repetido no escribe una segunda fila.');

// 4. registrarCliente: acota longitudes y convierte vacíos en NULL.
$limpiar();
$modelo->registrarCliente([
    'mensaje' => str_repeat('m', 900),
    'archivo' => str_repeat('a', 300),
    'linea'   => 42,
    'stack'   => str_repeat('s', 5000),
]);
$inserts = $insertsErrores();
$afirmar(count($inserts) === 1, 'El reporte del navegador se registra.');
$afirmar(strlen($inserts[0]['params'][3] ?? '') === 800, 'El mensaje del navegador se recorta a 800.');
$afirmar(strlen($inserts[0]['params'][4] ?? '') === 255, 'El archivo se recorta a 255.');
$afirmar(strlen($inserts[0]['params'][12] ?? '') === 4000, 'El stack se recorta a 4000.');
$afirmar(($inserts[0]['params'][5] ?? 0) === 42, 'La línea del navegador se respeta.');
$afirmar(($inserts[0]['params'][1] ?? '') === 'navegador', 'El origen del reporte es navegador.');
$afirmar($inserts[0]['params'][2] === null, 'Un error del navegador no lleva clase PHP.');
$huellaNavegador = $huellaDe(['origen' => 'navegador', 'clase' => null, 'mensaje' => mb_substr(str_repeat('m', 900), 0, 800), 'archivo' => mb_substr(str_repeat('a', 300), 0, 255), 'linea' => 42]);
$afirmar($inserts[0]['params'][0] === $huellaNavegador, 'La huella del navegador agrupa por archivo:línea (con los valores ya recortados).');

// 5. Registrar cliente sin campos: linea 0 → NULL y archivo vacío → NULL.
$limpiar();
$modelo->registrarCliente(['mensaje' => 'Promesa sin manejar', 'archivo' => '', 'linea' => 0, 'stack' => '']);
$inserts = $insertsErrores();
$afirmar($inserts[0]['params'][4] === null, 'Sin archivo (rejection), la ubicación queda NULL.');
$afirmar($inserts[0]['params'][5] === null, 'Sin línea, la ubicación queda NULL.');
$huellaMensaje = $huellaDe(['origen' => 'navegador', 'clase' => null, 'mensaje' => 'Promesa sin manejar', 'archivo' => '', 'linea' => 0]);
$afirmar($inserts[0]['params'][0] === $huellaMensaje, 'Sin archivo, la huella cae al inicio del mensaje (120 primeros caracteres).');

// 6. Mensaje vacío del navegador: ignorado sin escribir nada.
$limpiar();
$modelo->registrarCliente(['mensaje' => '   ', 'archivo' => 'x.js', 'linea' => 1, 'stack' => '']);
$afirmar($db->escrituras === [], 'Sin detalle no hay nada que registrar (y no se escribe).');

// 7. Purga: DELETE con retención de 7 días a resueltos y 30 a los demás.
$limpiar();
$modelo->purgar();
$borrados = array_values(array_filter($db->escrituras, static fn(array $e): bool => str_contains($e['sql'] ?? '', 'DELETE FROM errores')));
$afirmar(count($borrados) === 1, 'purgar() emite un DELETE FROM errores.');
$afirmar(str_contains($borrados[0]['sql'] ?? '', 'resuelto = 1 AND ultima_vez < NOW() - INTERVAL 7 DAY'), 'Los resueltos se purgan a los 7 días.');
$afirmar(str_contains($borrados[0]['sql'] ?? '', 'INTERVAL 30 DAY'), 'Los activos se purgan a los 30 días.');

// 8. Marcar resuelto: UPDATE por id y resultado según filas afectadas.
$limpiar();
$afirmar($modelo->marcarResuelto(7) === true, 'marcarResuelto devuelve true cuando la fila existía.');
$updates = array_values(array_filter($db->escrituras, static fn(array $e): bool => ($e['tipo'] ?? '') === 'update'));
$afirmar(($updates[0]['datos']['resuelto'] ?? 0) === 1, 'El cierre pone resuelto = 1.');
$afirmar((int)($updates[0]['params'][0] ?? 0) === 7, 'El cierre apunta al error pedido (id 7).');
$limpiar();
$db->retornoUpdate = 0;
$afirmar($modelo->marcarResuelto(999) === false, 'marcarResuelto devuelve false si el error ya no existe.');

// 9. Listado con filtros y paginación.
$limpiar();
$db->filasListado = [['id_error' => 1, 'mensaje' => 'x']];
$filas = $modelo->listar(['origen' => 'servidor', 'estado' => 'activos', 'buscar' => 'factura'], 2, 50);
$consulta = $db->consultas[0]['sql'] ?? '';
$afirmar($filas === $db->filasListado, 'listar() devuelve las filas del listado.');
$afirmar(str_contains($consulta, 'WHERE origen = ?') && str_contains($consulta, 'resuelto = 0'), 'Los filtros de origen y estado entran al WHERE.');
$afirmar(str_contains($consulta, '(mensaje LIKE ? OR archivo LIKE ? OR clase LIKE ? OR url LIKE ?)'), 'La búsqueda libre cubre mensaje, archivo, clase y URL.');
$afirmar(str_contains($consulta, 'ORDER BY resuelto ASC, ultima_vez DESC'), 'Activos primero y por recencia.');
$afirmar(str_contains($consulta, 'LIMIT 50, 50'), 'La página 2 con 50 por página salta 50 filas.');
$afirmar(($db->consultas[0]['params'][0] ?? '') === 'servidor', 'El filtro de origen va como parámetro (sin SQL inyectado).');
$afirmar(($db->consultas[0]['params'][2] ?? '') === '%factura%', 'El texto de búsqueda va acolchado con %.');

// 10. Conteo para la paginación con los mismos filtros.
$limpiar();
$db->filaDetalle = ['total' => '13'];
$total = $modelo->totalErrores(['origen' => '', 'estado' => 'resueltos', 'buscar' => '']);
$afirmar($total === 13, 'totalErrores devuelve el conteo para la paginación.');
$afirmar(str_contains($db->consultas[0]['sql'] ?? '', 'WHERE resuelto = 1'), 'El filtro de estado solo (sin origen ni búsqueda) construye un WHERE mínimo.');

// 11. Conteo de activos para el pill del encabezado.
$limpiar();
$db->filaDetalle = ['total' => '4'];
$afirmar($modelo->contarActivos() === 4, 'contarActivos devuelve los errores sin resolver.');

// 12. Detalle por id.
$limpiar();
$db->filaDetalle = ['id_error' => 5, 'mensaje' => 'detalle', 'stack' => '#0 trace'];
$afirmar($modelo->detalle(5) === $db->filaDetalle, 'detalle() devuelve la fila completa.');
$afirmar(str_contains($db->consultas[0]['sql'] ?? '', 'WHERE id_error = ? LIMIT 1'), 'El detalle consulta por id con tope de una fila.');

// 13. La base de datos caída NO derriba la petición (captura silenciosa).
$limpiar();
$logTemporal = (string)tempnam(sys_get_temp_dir(), 'jvlog');
$logPrevio = ini_set('error_log', $logTemporal);
$db->falloEnExecute = true;
$modelo->registrar($excepcion); // No debe lanzar: el diario jamás interrumpe.
ini_set('error_log', (string)$logPrevio);
$db->falloEnExecute = false;
$evidencia = (string)file_get_contents($logTemporal);
@unlink($logTemporal);
$afirmar(str_contains($evidencia, 'BD caída'), 'La caída de la base de datos dejó evidencia en el error_log sin derribar la petición.');

// 14. AvisarCorreo sin JV_MAIL_ADMIN: no hace nada (ni bandera ni correo).
$limpiar();
if (defined('JV_MAIL_ADMIN')) {
    throw new RuntimeException('El piloto exige ejecutar sin JV_MAIL_ADMIN definido.');
}
$huellaAviso = $huellaDe(['origen' => 'servidor', 'clase' => 'RuntimeException', 'mensaje' => 'aviso', 'archivo' => 'init.php', 'linea' => 1]);
$modelo->avisarCorreo(['huella' => $huellaAviso, 'origen' => 'servidor', 'clase' => 'RuntimeException', 'mensaje' => 'aviso', 'archivo' => 'init.php', 'linea' => 1]);
$afirmar(class_exists('Mail', false) === false, 'Sin destinatario configurado, el aviso no carga el canal de correo.');
$afirmar(glob($root . '/cache/aviso_error_' . $huellaAviso . '.json') === [], 'Sin destinatario, no se escribe bandera de aviso.');

// 15. Tope de 1 hora: bandera fresca → regreso antes de tocar el correo.
define('JV_MAIL_ADMIN', 'admin@jv3000.test'); // Solo para verificar el tope (el envío real no ocurre).
$bandera = $root . '/cache/aviso_error_' . $huellaAviso . '.json';
file_put_contents($bandera, json_encode(['aviso' => time()]));
$modelo->avisarCorreo(['huella' => $huellaAviso, 'origen' => 'servidor', 'clase' => 'RuntimeException', 'mensaje' => 'aviso', 'archivo' => 'init.php', 'linea' => 1]);
$afirmar(class_exists('Mail', false) === false, 'La bandera fresca (menos de 1 hora) corta el aviso antes de sondear la red.');

// 16. Enganches de captura presentes en los límites del sistema.
$codigoController = (string)file_get_contents($root . '/core/Controller.php');
$codigoInit = (string)file_get_contents($root . '/init.php');
$codigoDiseno = (string)file_get_contents($root . '/assets/js/diseno.js');
$seed = (string)file_get_contents($root . '/db/jv3000_portable_v6.sql');
$afirmar(str_contains($codigoController, '(new ErrorLog())->registrar($e)'), 'El boundary de responderError (Controller) registra en el diario.');
$afirmar(str_contains($codigoInit, '(new ErrorLog())->registrar($e)'), 'El handler global de init.php registra en el diario.');
$afirmar(str_contains($codigoDiseno, 'diagnostico/cliente') && str_contains($codigoDiseno, 'csrf_token'), 'El reporte del navegador viaja con CSRF al endpoint del diario.');
$afirmar(str_contains($codigoDiseno, 'jvReportarErrorNavegador') && str_contains($codigoDiseno, 'keepalive'), 'El reporter es fire-and-forget con keepalive.');
$afirmar(str_contains($seed, 'CREATE TABLE `errores`'), 'El seed de instalación limpia declara la tabla `errores`.');
$afirmar(str_contains((string)file_get_contents($root . '/includes/config.php'), "define('JV_MAIL_ADMIN'"), 'La clave del destinatario del aviso se define en config.php.');

// Limpieza: las banderas creadas por el piloto son temporales.
@unlink($bandera);
foreach (glob($root . '/cache/aviso_error_*.json') ?: [] as $sobrante) {
    @unlink($sobrante);
}

echo 'OK errores_observabilidad: ' . $afirmaciones . ' afirmaciones en verde (huella, dedup, caps, purga, filtros, aviso y enganches).';
