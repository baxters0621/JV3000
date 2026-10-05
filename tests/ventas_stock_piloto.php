<?php

/**
 * Pruebas de integración aisladas para el piloto de ventas y existencias.
 *
 * Ejecutar con JV_PILOT_TEST_DB_NAME, JV_PILOT_TEST_HOST, JV_PILOT_TEST_PORT
 * y JV_PILOT_TEST_DATADIR apuntando a una instancia temporal exclusiva. El
 * script valida su identidad antes de escribir; no carga config/.env ni init.php.
 * Usa y elimina triggers temporales para simular fallos tardíos de auditoría.
 */

$root = dirname(__DIR__);
ini_set('error_log', 'php://stderr');
define('APP_ROOT', $root);
define('APP_VIEWS', $root . '/views');
define('APP_URL_BASE', '');
define('BASE_PATH', '');

require_once $root . '/includes/Database.php';
require_once $root . '/includes/Security.php';
require_once $root . '/includes/helpers.php';
require_once $root . '/core/Model.php';
require_once $root . '/core/Controller.php';
require_once $root . '/models/Salida.php';

function pilotoAfirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function pilotoNormalizarRuta(string $ruta): string
{
    $real = realpath($ruta);
    return strtolower(rtrim(str_replace('\\', '/', $real !== false ? $real : $ruta), '/'));
}

function pilotoConectar(): Database
{
    $host = (string)getenv('JV_PILOT_TEST_HOST');
    $nombre = (string)getenv('JV_PILOT_TEST_DB_NAME');
    $puerto = filter_var(getenv('JV_PILOT_TEST_PORT'), FILTER_VALIDATE_INT);
    $datadir = (string)getenv('JV_PILOT_TEST_DATADIR');

    if (
        $host !== '127.0.0.1'
        || !preg_match('/^jv3000_pilot_[a-zA-Z0-9_]+$/', $nombre)
        || $puerto !== 33316
        || $datadir === ''
        || !is_dir($datadir)
    ) {
        throw new RuntimeException('Destino de pruebas ausente o fuera de la instancia temporal aprobada.');
    }

    $db = Database::getInstance();
    $db->connect($host, 'root', '', $nombre, $puerto);
    $identidad = $db->fetchOne(
        'SELECT DATABASE() AS nombre, @@datadir AS datadir, @@port AS puerto, @@bind_address AS bind_address'
    );
    pilotoAfirmar(
        $identidad !== null
        && $identidad['nombre'] === $nombre
        && (int)$identidad['puerto'] === $puerto
        && $identidad['bind_address'] === '127.0.0.1'
        && pilotoNormalizarRuta((string)$identidad['datadir']) === pilotoNormalizarRuta($datadir),
        'La conexión no coincide con la base, puerto o datadir desechable esperados.'
    );

    return $db;
}

function pilotoContar(Database $db, string $sql, array $parametros = []): int
{
    $fila = $db->fetchOne($sql, $parametros);
    return (int)($fila['total'] ?? 0);
}

function pilotoCrearProducto(Database $db, string $sku, int $stock, array $lotes = []): int
{
    static $idCategoria = null;
    if ($idCategoria === null) {
        $db->execute("INSERT INTO categorias (nombre) VALUES ('PILOTO SINTETICO')");
        $idCategoria = $db->lastInsertId();
    }

    $idProducto = $db->insert('productos', [
        'sku' => $sku,
        'nombre_producto' => 'Producto sintético ' . $sku,
        'precio_venta' => 10.00,
        'precio_costo' => 2.00,
        'stock_actual' => $stock,
        'id_categoria' => $idCategoria,
    ]);

    foreach ($lotes as [$cantidad, $dias]) {
        $db->insert('lotes', [
            'id_producto' => $idProducto,
            'cantidad' => $cantidad,
            'cantidad_restante' => $cantidad,
            'precio_costo' => 2.00,
            'fecha_vencimiento' => date('Y-m-d', strtotime("+$dias days")),
        ]);
    }

    return $idProducto;
}

function pilotoConfirmar(int $idProducto, int $cantidad): array
{
    return (new Salida())->confirmar([
        'grupo' => 'venta',
        'productos_data' => json_encode([[
            'id_producto' => $idProducto,
            'cantidad' => $cantidad,
            'precio' => 10.00,
        ]], JSON_THROW_ON_ERROR),
        'id_tipo_mov' => 1,
        'id_usuario' => 1,
        'cliente' => 'Cliente sintético',
        'rif_cliente' => 'V-12345678',
        'nro_control' => '',
        'observaciones' => 'Prueba sintética del piloto',
    ]);
}

function pilotoPrepararSesion(): void
{
    $_SESSION['id_usuario'] = 1;
    $_SESSION['usuario'] = 'Administrador';
    $_SESSION['id_rol'] = 1;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = '/tests/ventas_stock_piloto.php';
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

function pilotoTrabajador(string $modo, array $argumentos): never
{
    if ($modo === 'csrf-invalido') {
        session_save_path(sys_get_temp_dir());
        session_id('jvPilotCsrf' . bin2hex(random_bytes(5)));
        session_start();
        register_shutdown_function(static function (): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_abort();
            }
        });
        $_SESSION['csrf_token'] = 'token-esperado';
        $_POST['csrf_token'] = 'token-invalido';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        Security::validateCSRF();
        exit(3);
    }

    if ($modo === 'cancelacion-no-admin') {
        require_once APP_ROOT . '/controllers/SalidasController.php';
        $_SESSION['id_rol'] = 3;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['eliminar'] = (string)$argumentos[0];
        (new SalidasController())->index();
        exit(3);
    }

    $db = pilotoConectar();
    pilotoPrepararSesion();
    $id = (int)$argumentos[0];

    if ($modo === 'confirmar') {
        file_put_contents((string)$argumentos[1], 'listo', LOCK_EX);
        while (!is_file((string)$argumentos[2])) {
            usleep(10000);
        }
        echo json_encode(pilotoConfirmar($id, 1), JSON_THROW_ON_ERROR);
        exit(0);
    }

    if ($modo === 'anular') {
        file_put_contents((string)$argumentos[1], 'listo', LOCK_EX);
        while (!is_file((string)$argumentos[2])) {
            usleep(10000);
        }
        echo json_encode((new Salida())->anular($id), JSON_THROW_ON_ERROR);
        exit(0);
    }

    $db->rollback();
    exit(2);
}

function pilotoEjecutarTrabajador(string $modo, array $argumentos = []): string
{
    $comando = array_merge([PHP_BINARY, __FILE__, $modo], array_map('strval', $argumentos));
    $tuberias = [];
    $proceso = proc_open($comando, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $tuberias, $GLOBALS['root'], getenv());
    pilotoAfirmar(is_resource($proceso), 'No se pudo iniciar el trabajador ' . $modo . '.');
    fclose($tuberias[0]);
    $salida = stream_get_contents($tuberias[1]);
    $error = stream_get_contents($tuberias[2]);
    fclose($tuberias[1]);
    fclose($tuberias[2]);
    $codigo = proc_close($proceso);
    pilotoAfirmar($codigo === 0, 'Falló el trabajador ' . $modo . ': ' . trim($error));
    return $salida;
}

function pilotoEjecutarConcurrencia(string $modo, int $id): array
{
    $directorio = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jv3000_piloto_' . bin2hex(random_bytes(6));
    if (!mkdir($directorio)) {
        throw new RuntimeException('No se pudo crear el directorio temporal de coordinación.');
    }

    $listos = [$directorio . DIRECTORY_SEPARATOR . 'listo-1', $directorio . DIRECTORY_SEPARATOR . 'listo-2'];
    $inicio = $directorio . DIRECTORY_SEPARATOR . 'inicio';
    $procesos = [];
    $salidas = [];

    try {
        foreach ($listos as $listo) {
            $argumentos = [PHP_BINARY, __FILE__, $modo, (string)$id, $listo, $inicio];
            $tuberias = [];
            $proceso = proc_open($argumentos, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $tuberias, $GLOBALS['root'], getenv());
            if (!is_resource($proceso)) {
                throw new RuntimeException('No se pudo iniciar un trabajador de concurrencia.');
            }
            fclose($tuberias[0]);
            $procesos[] = [$proceso, $tuberias];
        }

        $limite = microtime(true) + 15;
        while ((!is_file($listos[0]) || !is_file($listos[1])) && microtime(true) < $limite) {
            usleep(10000);
        }
        pilotoAfirmar(is_file($listos[0]) && is_file($listos[1]), 'Los trabajadores no alcanzaron la barrera de concurrencia.');
        file_put_contents($inicio, 'go', LOCK_EX);

        foreach ($procesos as [$proceso, $tuberias]) {
            $salida = stream_get_contents($tuberias[1]);
            $error = stream_get_contents($tuberias[2]);
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            $codigo = proc_close($proceso);
            pilotoAfirmar($codigo === 0, 'Falló un trabajador: ' . trim($error));
            $resultado = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
            $salidas[] = $resultado;
        }
    } finally {
        foreach ($procesos as [$proceso, $tuberias]) {
            if (is_resource($proceso)) {
                foreach ([1, 2] as $indice) {
                    if (isset($tuberias[$indice]) && is_resource($tuberias[$indice])) {
                        fclose($tuberias[$indice]);
                    }
                }
                proc_terminate($proceso);
                proc_close($proceso);
            }
        }
        foreach (glob($directorio . DIRECTORY_SEPARATOR . '*') ?: [] as $archivo) {
            unlink($archivo);
        }
        rmdir($directorio);
    }

    return $salidas;
}

$modo = $argv[1] ?? 'suite';
if ($modo !== 'suite') {
    pilotoTrabajador($modo, array_slice($argv, 2));
}

session_save_path(sys_get_temp_dir());
session_id('jvPilotSuite' . bin2hex(random_bytes(5)));
session_start();
register_shutdown_function(static function (): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_abort();
    }
});
$db = pilotoConectar();
pilotoPrepararSesion();
$idProductoFefo = pilotoCrearProducto($db, 'PILOT-FEFO-' . bin2hex(random_bytes(3)), 9, [
    [2, 10],
    [3, 30],
    [4, 60],
]);
$auditoriasAntesVenta = pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'crear'");
$resultadoVenta = pilotoConfirmar($idProductoFefo, 4);
pilotoAfirmar($resultadoVenta['ok'] === true, 'La venta FEFO válida no se confirmó: ' . ($resultadoVenta['error'] ?? json_encode($resultadoVenta)));
$idSalidaFefo = (int)$resultadoVenta['id_salida'];
$stock = $db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoFefo]);
$cantidadesLote = $db->fetchAll(
    'SELECT cantidad_restante FROM lotes WHERE id_producto = ? ORDER BY fecha_vencimiento, id_lote',
    [$idProductoFefo]
);
$detalleVenta = $db->fetchAll('SELECT id_lote, cantidad FROM detalle_salidas WHERE id_salida = ? ORDER BY id_detalle', [$idSalidaFefo]);
$movimiento = $db->fetchOne("SELECT id_movimiento, status FROM movimientos WHERE id_referencia = ? AND tipo_referencia = 'venta'", [$idSalidaFefo]);
pilotoAfirmar((int)$stock['stock_actual'] === 5, 'El stock agregado no refleja la venta.');
pilotoAfirmar(array_map(static fn(array $lote): int => (int)$lote['cantidad_restante'], $cantidadesLote) === [0, 1, 4], 'La venta no consumió los lotes en orden FEFO.');
pilotoAfirmar(count($detalleVenta) === 2 && (int)$detalleVenta[0]['cantidad'] === 2 && (int)$detalleVenta[1]['cantidad'] === 2, 'El detalle no refleja el consumo por lote.');
pilotoAfirmar($movimiento !== null && $movimiento['status'] === 'Activo', 'No se creó el movimiento activo de venta.');
pilotoAfirmar(pilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_movimientos WHERE id_movimiento = ?', [(int)$movimiento['id_movimiento']]) === 1, 'Falta el detalle del movimiento.');
pilotoAfirmar(pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'crear'") === $auditoriasAntesVenta + 1, 'La auditoría de venta no se registró exactamente una vez.');
echo "OK venta múltiple FEFO y auditoría atómica\n";

$idProductoInsuficiente = pilotoCrearProducto($db, 'PILOT-STOCK-' . bin2hex(random_bytes(3)), 3, [[3, 20]]);
$salidasAntes = pilotoContar($db, 'SELECT COUNT(*) AS total FROM salidas');
$movimientosAntes = pilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'venta'");
$auditoriasAntes = pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'crear'");
$resultadoInsuficiente = pilotoConfirmar($idProductoInsuficiente, 4);
pilotoAfirmar($resultadoInsuficiente['ok'] === false, 'Se aceptó una venta con stock insuficiente.');
pilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoInsuficiente])['stock_actual'] === 3, 'Cambió el stock tras rechazar la venta.');
pilotoAfirmar((int)$db->fetchOne('SELECT cantidad_restante FROM lotes WHERE id_producto = ?', [$idProductoInsuficiente])['cantidad_restante'] === 3, 'Cambió el lote tras rechazar la venta.');
pilotoAfirmar(pilotoContar($db, 'SELECT COUNT(*) AS total FROM salidas') === $salidasAntes, 'Se persistió una cabecera de una venta rechazada.');
pilotoAfirmar(pilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'venta'") === $movimientosAntes, 'Se persistió un movimiento de una venta rechazada.');
pilotoAfirmar(pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'crear'") === $auditoriasAntes, 'Se auditó una venta rechazada como exitosa.');
echo "OK rechazo por stock insuficiente sin escrituras parciales\n";

$idProductoRollback = pilotoCrearProducto($db, 'PILOT-ROLLBACK-' . bin2hex(random_bytes(3)), 5, [[5, 25]]);
$contadorAntes = (int)$db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix = 'NDE'")['ultimo_numero'];
$salidasAntes = pilotoContar($db, 'SELECT COUNT(*) AS total FROM salidas');
$movimientosAntes = pilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'venta'");
$auditoriasAntes = pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria");
$db->getConnection()->query("CREATE TRIGGER piloto_falla_auditoria BEFORE INSERT ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo sintético de auditoría'");
try {
    $resultadoRollback = pilotoConfirmar($idProductoRollback, 2);
} finally {
    $db->getConnection()->query('DROP TRIGGER IF EXISTS piloto_falla_auditoria');
}
pilotoAfirmar($resultadoRollback['ok'] === false, 'La venta no notificó el fallo tardío de auditoría.');
pilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoRollback])['stock_actual'] === 5, 'El rollback no restauró el stock agregado.');
pilotoAfirmar((int)$db->fetchOne('SELECT cantidad_restante FROM lotes WHERE id_producto = ?', [$idProductoRollback])['cantidad_restante'] === 5, 'El rollback no restauró el lote.');
pilotoAfirmar((int)$db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix = 'NDE'")['ultimo_numero'] === $contadorAntes, 'El rollback no revirtió el contador NDE.');
pilotoAfirmar(pilotoContar($db, 'SELECT COUNT(*) AS total FROM salidas') === $salidasAntes, 'El rollback dejó una cabecera de venta.');
pilotoAfirmar(pilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'venta'") === $movimientosAntes, 'El rollback dejó un movimiento de venta.');
pilotoAfirmar(pilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntes, 'El rollback dejó una auditoría parcial.');
echo "OK rollback completo ante fallo tardío de auditoría\n";

$idProductoConcurrente = pilotoCrearProducto($db, 'PILOT-RACE-' . bin2hex(random_bytes(3)), 1);
$ventasAntes = pilotoContar($db, 'SELECT COUNT(*) AS total FROM salidas');
$movimientosAntes = pilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'venta'");
$auditoriasAntes = pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'crear'");
$resultadosConcurrentes = pilotoEjecutarConcurrencia('confirmar', $idProductoConcurrente);
pilotoAfirmar(count(array_filter($resultadosConcurrentes, static fn(array $r): bool => ($r['ok'] ?? false) === true)) === 1, 'La carrera de ventas no produjo exactamente una confirmación.');
pilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoConcurrente])['stock_actual'] === 0, 'La carrera dejó stock negativo o unidades sin descontar.');
pilotoAfirmar(pilotoContar($db, 'SELECT COUNT(*) AS total FROM salidas') === $ventasAntes + 1, 'La carrera persistió una cantidad incorrecta de ventas.');
pilotoAfirmar(pilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'venta'") === $movimientosAntes + 1, 'La carrera persistió movimientos inconsistentes.');
pilotoAfirmar(pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'crear'") === $auditoriasAntes + 1, 'La carrera produjo auditorías duplicadas.');
echo "OK concurrencia de ventas sin sobreventa\n";

$salidaActiva = $db->fetchOne('SELECT id_salida FROM salidas WHERE id_salida = ?', [$idSalidaFefo]);
$stockAntesAnular = (int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoFefo])['stock_actual'];
$lotesAntesAnular = $db->fetchAll('SELECT cantidad_restante FROM lotes WHERE id_producto = ? ORDER BY fecha_vencimiento, id_lote', [$idProductoFefo]);
$auditoriasAntesAnular = pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'anular'");
$db->getConnection()->query("CREATE TRIGGER piloto_falla_anulacion BEFORE INSERT ON auditoria FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo sintético de auditoría'");
try {
    $resultadoAnulacionRollback = (new Salida())->anular($idSalidaFefo);
} finally {
    $db->getConnection()->query('DROP TRIGGER IF EXISTS piloto_falla_anulacion');
}
pilotoAfirmar($resultadoAnulacionRollback['ok'] === false, 'La anulación no notificó el fallo tardío de auditoría.');
pilotoAfirmar($db->fetchOne('SELECT status FROM salidas WHERE id_salida = ?', [$idSalidaFefo])['status'] === 'Activa', 'El rollback dejó la salida anulada.');
pilotoAfirmar($db->fetchOne('SELECT status FROM movimientos WHERE id_referencia = ? AND tipo_referencia = \'venta\'', [$idSalidaFefo])['status'] === 'Activo', 'El rollback dejó anulado el movimiento.');
pilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoFefo])['stock_actual'] === $stockAntesAnular, 'El rollback de anulación alteró el stock.');
pilotoAfirmar($db->fetchAll('SELECT cantidad_restante FROM lotes WHERE id_producto = ? ORDER BY fecha_vencimiento, id_lote', [$idProductoFefo]) === $lotesAntesAnular, 'El rollback de anulación alteró los lotes.');
pilotoAfirmar(pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'anular'") === $auditoriasAntesAnular, 'El rollback dejó una auditoría de anulación.');
echo "OK rollback completo de anulación ante fallo de auditoría\n";

$resultadosAnulacion = pilotoEjecutarConcurrencia('anular', $idSalidaFefo);
pilotoAfirmar(
    count(array_filter($resultadosAnulacion, static fn(array $r): bool => ($r['ok'] ?? false) === true)) === 1,
    'La carrera de anulaciones no fue idempotente: ' . json_encode($resultadosAnulacion)
);
pilotoAfirmar($db->fetchOne('SELECT status FROM salidas WHERE id_salida = ?', [$idSalidaFefo])['status'] === 'Anulada', 'La anulación concurrente no actualizó la cabecera.');
pilotoAfirmar($db->fetchOne('SELECT status FROM movimientos WHERE id_referencia = ? AND tipo_referencia = \'venta\'', [$idSalidaFefo])['status'] === 'Anulado', 'La anulación concurrente no actualizó el movimiento.');
pilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoFefo])['stock_actual'] === $stockAntesAnular + 4, 'La carrera de anulaciones restituyó stock más de una vez.');
$lotesDespuesAnular = $db->fetchAll('SELECT cantidad_restante FROM lotes WHERE id_producto = ? ORDER BY fecha_vencimiento, id_lote', [$idProductoFefo]);
pilotoAfirmar(array_map(static fn(array $lote): int => (int)$lote['cantidad_restante'], $lotesDespuesAnular) === [2, 3, 4], 'La carrera de anulaciones restituyó mal los lotes.');
pilotoAfirmar(pilotoContar($db, "SELECT COUNT(*) AS total FROM auditoria WHERE accion = 'anular'") === $auditoriasAntesAnular + 1, 'La carrera generó auditorías de anulación duplicadas.');
echo "OK anulación concurrente idempotente: stock, lotes, movimiento y auditoría\n";

$salidaParaPermiso = $db->fetchOne(
    "SELECT s.id_salida FROM salidas s JOIN detalle_salidas ds ON ds.id_salida = s.id_salida
     WHERE ds.id_producto = ? AND s.status = 'Activa' LIMIT 1",
    [$idProductoConcurrente]
);
pilotoAfirmar($salidaParaPermiso !== null, 'Falta una venta activa para comprobar permisos.');
$idSalidaPermiso = (int)$salidaParaPermiso['id_salida'];
$salidasAntesPermiso = pilotoContar($db, 'SELECT COUNT(*) AS total FROM salidas');
$stockAntesPermiso = (int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoConcurrente])['stock_actual'];
pilotoEjecutarTrabajador('cancelacion-no-admin', [$idSalidaPermiso]);
pilotoAfirmar($db->fetchOne('SELECT status FROM salidas WHERE id_salida = ?', [$idSalidaPermiso])['status'] === 'Activa', 'Un operador de ventas pudo anular una salida activa.');
pilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$idProductoConcurrente])['stock_actual'] === $stockAntesPermiso, 'El intento no autorizado alteró el stock.');
pilotoAfirmar(pilotoContar($db, 'SELECT COUNT(*) AS total FROM salidas') === $salidasAntesPermiso, 'El intento no autorizado creó una salida.');
echo "OK anulación restringida a administrador en el controlador\n";

$csrfRespuesta = pilotoEjecutarTrabajador('csrf-invalido');
pilotoAfirmar(str_contains($csrfRespuesta, '"success":false'), 'Un token CSRF inválido no fue rechazado.');
echo "OK rechazo CSRF\n";

require_once $root . '/controllers/SalidasController.php';
pilotoPrepararSesion();
$_SESSION['id_rol'] = 3;
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
(new SalidasController())->index();
$html = (string)ob_get_clean();
pilotoAfirmar(str_contains($html, 'SALIDAS / VENTAS'), 'La pantalla de ventas no se renderizó.');
pilotoAfirmar(str_contains($html, 'NUEVA VENTA') && str_contains($html, 'id="tablaSalidas"'), 'Faltan controles esenciales de la interfaz de ventas.');
pilotoAfirmar(str_contains($html, 'csrfToken') && str_contains($html, 'modules/salidas/salidas.js'), 'La interfaz omitió su token o el módulo JavaScript.');
pilotoAfirmar(!str_contains($html, 'CLIENTES</button>'), 'La interfaz expuso controles administrativos al operador de ventas.');
echo "OK render UI MVC para operador de ventas\n";
echo "Todas las pruebas del piloto pasaron en la base temporal identificada.\n";
