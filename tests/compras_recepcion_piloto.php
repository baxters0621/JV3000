<?php

/**
 * Pruebas de integración aisladas para el piloto de compras y recepción.
 *
 * Solo conecta a la instancia desechable aprobada; no carga config/.env ni
 * init.php. Requiere las variables JV_PURCHASES_TEST_* y un usuario limitado
 * a la base temporal de esta ejecución.
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
require_once $root . '/models/Compra.php';
require_once $root . '/models/Recepcion.php';

/**
 * Falla la suite al incumplirse una invariante.
 */
function comprasPilotoAfirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

/**
 * Normaliza rutas para comparar la identidad física del servidor.
 */
function comprasPilotoRuta(string $ruta): string
{
    $real = realpath($ruta);
    return strtolower(rtrim(str_replace('\\', '/', $real !== false ? $real : $ruta), '/'));
}

/**
 * Abre la única base permitida y verifica todos los datos de aislamiento.
 */
function comprasPilotoConectar(): Database
{
    $host = (string)getenv('JV_PURCHASES_TEST_HOST');
    $nombre = (string)getenv('JV_PURCHASES_TEST_DB_NAME');
    $puerto = filter_var(getenv('JV_PURCHASES_TEST_PORT'), FILTER_VALIDATE_INT);
    $datadir = (string)getenv('JV_PURCHASES_TEST_DATADIR');
    $usuario = (string)getenv('JV_PURCHASES_TEST_USER');
    $contrasena = (string)getenv('JV_PURCHASES_TEST_PASS');

    if (
        $host !== '127.0.0.1'
        || !preg_match('/^jv3000_compras_piloto_[a-zA-Z0-9_]+$/', $nombre)
        || $puerto === false
        || $puerto < 1
        || $puerto > 65535
        || $datadir === ''
        || !is_dir($datadir)
        || $usuario !== 'jv_purchase_test'
        || $contrasena === ''
    ) {
        throw new RuntimeException('Destino ausente o fuera de la base temporal de compras autorizada.');
    }

    $db = Database::getInstance();
    $db->connect($host, $usuario, $contrasena, $nombre, $puerto);
    $identidad = $db->fetchOne(
        'SELECT DATABASE() AS nombre, @@datadir AS datadir, @@port AS puerto, @@bind_address AS bind_address'
    );
    comprasPilotoAfirmar(
        $identidad !== null
        && $identidad['nombre'] === $nombre
        && (int)$identidad['puerto'] === $puerto
        && $identidad['bind_address'] === '127.0.0.1'
        && comprasPilotoRuta((string)$identidad['datadir']) === comprasPilotoRuta($datadir),
        'La conexión no coincide con la base, puerto o datadir desechable aprobados.'
    );

    return $db;
}

/**
 * Cuenta filas con una consulta de verificación sintética.
 */
function comprasPilotoContar(Database $db, string $sql, array $parametros = []): int
{
    $fila = $db->fetchOne($sql, $parametros);
    return (int)($fila['total'] ?? 0);
}

/**
 * Crea los datos mínimos sintéticos para compras de prueba.
 */
function comprasPilotoFixtures(Database $db): array
{
    static $categoriaId = null;
    static $proveedorId = null;

    if ($categoriaId === null) {
        $db->execute("INSERT INTO categorias (nombre) VALUES ('PILOTO COMPRAS SINTETICO')");
        $categoriaId = $db->lastInsertId();
    }
    if ($proveedorId === null) {
        $proveedorId = $db->insert('proveedores', [
            'rif' => 'J-12345678-9',
            'nombre_empresa' => 'Proveedor sintético piloto',
            'status' => 'Activo',
        ]);
    }

    return [$categoriaId, $proveedorId];
}

/**
 * Crea un producto con stock y costo base controlados.
 */
function comprasPilotoProducto(Database $db, int $categoriaId, string $sufijo, int $stock = 0, float $costo = 0): int
{
    return $db->insert('productos', [
        'sku' => 'CP-' . strtoupper($sufijo) . '-' . bin2hex(random_bytes(3)),
        'nombre_producto' => 'Producto sintético ' . $sufijo,
        'precio_venta' => 10.00,
        'precio_costo' => $costo,
        'stock_actual' => $stock,
        'id_categoria' => $categoriaId,
    ]);
}

/**
 * Construye una factura sintética conservando el contrato de Compra::registrar.
 */
function comprasPilotoFormulario(int $proveedorId, array $productos, string $factura, int $idSolicitud = 0, float $pagoInicial = 0): array
{
    $items = [];
    foreach ($productos as [$idProducto, $cantidad, $precio]) {
        $items[] = [
            'id' => $idProducto,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
        ];
    }

    return [
        'id_proveedor' => $proveedorId,
        'id_solicitud' => $idSolicitud,
        'nro_factura' => $factura,
        'nro_control' => '00-12345678',
        'observaciones' => 'Fixture sintético del piloto',
        'productos_data' => json_encode($items, JSON_THROW_ON_ERROR),
        'monto_pago' => $pagoInicial,
        'metodo_pago' => 'Efectivo',
        'efectivo_tipo' => 'Bolivares',
    ];
}

/**
 * Registra una compra sintética y comprueba el resultado del modelo.
 */
function comprasPilotoRegistrar(int $proveedorId, array $productos, string $factura, int $idSolicitud = 0, float $pagoInicial = 0): array
{
    return (new Compra())->registrar(
        comprasPilotoFormulario($proveedorId, $productos, $factura, $idSolicitud, $pagoInicial),
        1
    );
}

/**
 * Recibe cantidades sintéticas en el modelo de recepción.
 */
function comprasPilotoRecibir(
    int $idCompra,
    array $items,
    ?string $claveIdempotencia = null,
    string $documento = 'GUIA-PILOTO'
): array
{
    $claveIdempotencia ??= bin2hex(random_bytes(32));
    return (new Recepcion())->registrar([
        'id_compra' => $idCompra,
        'items_data' => json_encode($items, JSON_THROW_ON_ERROR),
        'documento_recepcion' => $documento,
        'clave_idempotencia' => $claveIdempotencia,
    ]);
}

/**
 * Inicia el contexto sintético que usa la auditoría.
 */
function comprasPilotoSesion(): void
{
    $_SESSION['id_usuario'] = 1;
    $_SESSION['usuario'] = 'Administrador';
    $_SESSION['id_rol'] = 1;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_URI'] = '/tests/compras_recepcion_piloto.php';
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

/**
 * Ejecuta un trabajador de carrera tras conectarse a la base aislada.
 */
function comprasPilotoTrabajador(string $modo, array $argumentos): never
{
    if ($modo === 'permiso') {
        require_once APP_ROOT . '/controllers/ComprasController.php';
        $_SESSION['id_rol'] = 3;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['accion_compra' => 'no debe procesarse'];
        register_shutdown_function(static function (): void {
            if (error_get_last() === null) {
                echo 'PERMISO_DENEGADO';
            }
        });
        (new ComprasController())->index();
        echo 'ACCESO_INDEBIDO';
        exit(2);
    }

    $db = comprasPilotoConectar();
    comprasPilotoSesion();
    $rutaListo = (string)$argumentos[count($argumentos) - 2];
    $rutaInicio = (string)$argumentos[count($argumentos) - 1];
    file_put_contents($rutaListo, 'listo', LOCK_EX);
    $limite = microtime(true) + 15;
    while (!is_file($rutaInicio) && microtime(true) < $limite) {
        usleep(10000);
    }
    comprasPilotoAfirmar(is_file($rutaInicio), 'Se agotó el tiempo de espera de la barrera concurrente.');

    if ($modo === 'compra') {
        $resultado = comprasPilotoRegistrar(
            (int)$argumentos[0],
            [[(int)$argumentos[1], 1, 2.00]],
            (string)$argumentos[2]
        );
    } elseif ($modo === 'compra-solicitud') {
        $resultado = comprasPilotoRegistrar(
            (int)$argumentos[0],
            [[(int)$argumentos[1], 1, 2.00]],
            (string)$argumentos[2],
            (int)$argumentos[3],
            (float)$argumentos[4]
        );
    } elseif ($modo === 'recepcion') {
        $resultado = comprasPilotoRecibir((int)$argumentos[0], [[
            'id_detalle' => (int)$argumentos[1],
            'cantidad' => (int)$argumentos[2],
            'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
        ]]);
    } elseif ($modo === 'recepcion-idempotente') {
        $resultado = comprasPilotoRecibir(
            (int)$argumentos[0],
            [[
                'id_detalle' => (int)$argumentos[1],
                'cantidad' => (int)$argumentos[2],
                'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
            ]],
            (string)$argumentos[3],
            (string)$argumentos[4]
        );
    } elseif ($modo === 'anular-compra') {
        $resultado = (new Compra())->anular((int)$argumentos[0]);
    } else {
        throw new RuntimeException('Modo de trabajador desconocido.');
    }
    echo json_encode($resultado, JSON_THROW_ON_ERROR);
    exit(0);
}

/**
 * Arranca varios procesos PHP y libera sus operaciones con una barrera común.
 */
function comprasPilotoCarrera(array $operaciones): array
{
    $cantidad = count($operaciones);
    comprasPilotoAfirmar($cantidad > 1, 'La carrera requiere al menos dos operaciones.');
    $directorio = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jv3000_compras_' . bin2hex(random_bytes(6));
    comprasPilotoAfirmar(mkdir($directorio), 'No se pudo crear el directorio temporal de coordinación.');
    $inicio = $directorio . DIRECTORY_SEPARATOR . 'inicio';
    $procesos = [];
    $resultados = [];

    try {
        for ($indice = 0; $indice < $cantidad; $indice++) {
            $rutaListo = $directorio . DIRECTORY_SEPARATOR . 'listo-' . $indice;
            $operacion = $operaciones[$indice];
            $comando = array_merge(
                [PHP_BINARY, __FILE__, $operacion['modo']],
                array_map('strval', $operacion['argumentos']),
                [$rutaListo, $inicio]
            );
            $tuberias = [];
            $proceso = proc_open($comando, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $tuberias, $GLOBALS['root'], getenv());
            comprasPilotoAfirmar(is_resource($proceso), 'No se pudo iniciar un trabajador concurrente.');
            fclose($tuberias[0]);
            $procesos[] = [$proceso, $tuberias, $rutaListo];
        }

        $limite = microtime(true) + 20;
        do {
            $listos = count(array_filter($procesos, static fn(array $p): bool => is_file($p[2])));
            if ($listos === $cantidad) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $limite);
        comprasPilotoAfirmar($listos === $cantidad, 'Los procesos no alcanzaron la barrera de concurrencia.');
        file_put_contents($inicio, 'iniciar', LOCK_EX);

        foreach ($procesos as [$proceso, $tuberias]) {
            $salida = stream_get_contents($tuberias[1]);
            $error = stream_get_contents($tuberias[2]);
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            $codigo = proc_close($proceso);
            comprasPilotoAfirmar($codigo === 0, 'Falló un trabajador concurrente: ' . trim($error));
            $resultados[] = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
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

    return $resultados;
}

$modo = $argv[1] ?? 'suite';
if ($modo !== 'suite') {
    comprasPilotoTrabajador($modo, array_slice($argv, 2));
}

session_save_path(sys_get_temp_dir());
session_id('jvComprasPiloto' . bin2hex(random_bytes(5)));
session_start();
register_shutdown_function(static function (): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_abort();
    }
});

try {
    $db = comprasPilotoConectar();
    $conexion = $db->getConnection();
    $conexion->query(
        'CREATE TEMPORARY TABLE compras_piloto_affected_rows (
            id INT NOT NULL PRIMARY KEY,
            valor INT NOT NULL
        )'
    );
    $conexion->query('INSERT INTO compras_piloto_affected_rows (id, valor) VALUES (1, 1)');
    $db->execute('UPDATE compras_piloto_affected_rows SET valor = 2 WHERE id = 1');
    comprasPilotoAfirmar(
        $db->affectedRows() === 1,
        'affectedRows() no conservó el resultado del UPDATE después de liberar el statement.'
    );
    $conexion->query('DROP TEMPORARY TABLE compras_piloto_affected_rows');
    echo "OK affectedRows conserva el resultado del último statement\n";

    comprasPilotoSesion();
    [$categoriaId, $proveedorId] = comprasPilotoFixtures($db);

    $productoA = comprasPilotoProducto($db, $categoriaId, 'MULTI-A', 7, 1.25);
    $productoB = comprasPilotoProducto($db, $categoriaId, 'MULTI-B');
    $solicitudId = $db->insert('solicitudes_compra', [
        'id_usuario_solicitante' => 1,
        'motivo' => 'Solicitud sintética vinculada',
        'estado' => 'Pendiente',
    ]);
    $db->insert('detalle_solicitud_compra', [
        'id_solicitud' => $solicitudId,
        'id_producto' => $productoA,
        'cantidad_solicitada' => 2,
    ]);
    $auditoriasAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $compraResultado = comprasPilotoRegistrar($proveedorId, [
        [$productoA, 2, 12.50],
        [$productoB, 3, 2.00],
    ], '700001', $solicitudId, 5.00);
    comprasPilotoAfirmar($compraResultado['ok'], 'Falló el alta sintética de compra: ' . $compraResultado['mensaje']);
    $compraId = (int)$db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = '700001'", [$proveedorId])['id_compra'];
    $compra = $db->fetchOne('SELECT subtotal, iva, total, status_pago, monto_pago, estado_recepcion FROM compras WHERE id_compra = ?', [$compraId]);
    comprasPilotoAfirmar(
        (float)$compra['subtotal'] === 31.00
        && (float)$compra['iva'] === 4.96
        && (float)$compra['total'] === 35.96
        && $compra['status_pago'] === 'Parcial'
        && (float)$compra['monto_pago'] === 5.00
        && $compra['estado_recepcion'] === 'Pendiente',
        'El alta no preservó los importes, anticipo o estado actuales.'
    );
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoA])['stock_actual'] === 7, 'El alta de compra alteró el stock antes de recibir.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$compraId]) === 0, 'El alta de compra creó lotes antes de la recepción.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = \'compra\' AND id_referencia = ?', [$compraId]) === 0, 'El alta de compra creó un movimiento antes de la recepción.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra WHERE id_compra = ?', [$compraId]) === 1, 'No se conservó el comprobante de pago inicial.');
    comprasPilotoAfirmar(
        $db->fetchOne('SELECT estado, id_compra FROM solicitudes_compra WHERE id_solicitud = ?', [$solicitudId])['estado'] === 'Atendida'
        && (int)$db->fetchOne('SELECT id_compra FROM solicitudes_compra WHERE id_solicitud = ?', [$solicitudId])['id_compra'] === $compraId,
        'No se conservó la asociación de la solicitud atendida.'
    );
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntes + 1, 'El alta de compra no generó exactamente una auditoría.');
    echo "OK alta multiítem preserva anticipo, solicitud e inventario\n";

    $comprasAntesValidacion = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM compras');
    $detallesAntesValidacion = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_compras');
    $pagosAntesValidacion = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra');
    $auditoriasAntesValidacion = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $resultadoIdInvalido = comprasPilotoRegistrar(
        $proveedorId,
        [[$productoA, 1, 1.00], [0, 1, 1.00]],
        '700009'
    );
    comprasPilotoAfirmar(!$resultadoIdInvalido['ok'], 'Se descartó una línea con ID inválido y se aceptó el resto de la compra.');
    $resultadoProductoInexistente = comprasPilotoRegistrar(
        $proveedorId,
        [[$productoA, 1, 1.00], [2147483647, 1, 1.00]],
        '700010'
    );
    comprasPilotoAfirmar(!$resultadoProductoInexistente['ok'], 'Se descartó una línea con producto inexistente y se aceptó el resto de la compra.');
    $resultadoCantidadFraccionaria = comprasPilotoRegistrar(
        $proveedorId,
        [[$productoA, 1.5, 1.00]],
        '799991'
    );
    comprasPilotoAfirmar(
        !$resultadoCantidadFraccionaria['ok']
        && str_contains($resultadoCantidadFraccionaria['mensaje'] ?? '', 'CANTIDAD INVÁLIDA'),
        'El alta de compra aceptó o truncó una cantidad fraccionaria.'
    );
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM compras') === $comprasAntesValidacion, 'La validación de líneas dejó una cabecera de compra.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_compras') === $detallesAntesValidacion, 'La validación de líneas dejó detalles parciales.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra') === $pagosAntesValidacion, 'La validación de líneas dejó un pago inicial.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntesValidacion, 'La validación de líneas dejó auditoría parcial.');
    echo "OK líneas inválidas mezcladas con válidas rechazan la compra completa\n";

    $productoSolicitudCarreraA = comprasPilotoProducto($db, $categoriaId, 'SOLICITUD-CARRERA-A');
    $productoSolicitudCarreraB = comprasPilotoProducto($db, $categoriaId, 'SOLICITUD-CARRERA-B');
    $solicitudCarreraId = $db->insert('solicitudes_compra', [
        'id_usuario_solicitante' => 1,
        'motivo' => 'Solicitud sintética para carrera de asociación',
        'estado' => 'Pendiente',
    ]);
    foreach ([$productoSolicitudCarreraA, $productoSolicitudCarreraB] as $productoSolicitudCarrera) {
        $db->insert('detalle_solicitud_compra', [
            'id_solicitud' => $solicitudCarreraId,
            'id_producto' => $productoSolicitudCarrera,
            'cantidad_solicitada' => 1,
        ]);
    }
    $comprasAntesCarreraSolicitud = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM compras');
    $detallesAntesCarreraSolicitud = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_compras');
    $pagosAntesCarreraSolicitud = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra');
    $auditoriasAntesCarreraSolicitud = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $resultadosCarreraSolicitud = comprasPilotoCarrera([
        [
            'modo' => 'compra-solicitud',
            'argumentos' => [$proveedorId, $productoSolicitudCarreraA, '700013', $solicitudCarreraId, 1.00],
        ],
        [
            'modo' => 'compra-solicitud',
            'argumentos' => [$proveedorId, $productoSolicitudCarreraB, '700014', $solicitudCarreraId, 1.00],
        ],
    ]);
    $comprasGanadorasSolicitud = array_values(array_filter(
        $resultadosCarreraSolicitud,
        static fn(array $resultado): bool => $resultado['ok'] === true
    ));
    $comprasPerdedorasSolicitud = array_values(array_filter(
        $resultadosCarreraSolicitud,
        static fn(array $resultado): bool => $resultado['ok'] === false
    ));
    comprasPilotoAfirmar(count($comprasGanadorasSolicitud) === 1, 'La carrera no produjo exactamente una compra ganadora.');
    comprasPilotoAfirmar(
        count($comprasPerdedorasSolicitud) === 1
        && str_contains($comprasPerdedorasSolicitud[0]['mensaje'], 'LA SOLICITUD YA FUE ATENDIDA'),
        'La compra que perdió la carrera no informó explícitamente el conflicto.'
    );
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM compras') === $comprasAntesCarreraSolicitud + 1, 'La carrera dejó persistir más de una cabecera de compra.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_compras') === $detallesAntesCarreraSolicitud + 1, 'La compra perdedora dejó detalles parciales.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra') === $pagosAntesCarreraSolicitud + 1, 'La compra perdedora dejó un pago inicial parcial.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntesCarreraSolicitud + 1, 'La compra perdedora dejó auditoría parcial.');
    $solicitudGanadora = $db->fetchOne(
        'SELECT estado, id_compra FROM solicitudes_compra WHERE id_solicitud = ?',
        [$solicitudCarreraId]
    );
    $compraGanadora = $db->fetchOne(
        "SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura IN ('700013', '700014')",
        [$proveedorId]
    );
    comprasPilotoAfirmar(
        $compraGanadora !== null
        && $solicitudGanadora['estado'] === 'Atendida'
        && (int)$solicitudGanadora['id_compra'] === (int)$compraGanadora['id_compra'],
        'La solicitud no quedó vinculada exclusivamente a la compra ganadora.'
    );
    echo "OK carrera de solicitud revierte íntegramente la compra perdedora\n";

    $productoRollback = comprasPilotoProducto($db, $categoriaId, 'ROLLBACK');
    $solicitudRollback = $db->insert('solicitudes_compra', [
        'id_usuario_solicitante' => 1,
        'motivo' => 'Solicitud rollback sintética',
        'estado' => 'Pendiente',
    ]);
    $comprasAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM compras');
    $detallesAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_compras');
    $pagosAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra');
    $auditoriasAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $db->getConnection()->query(
        "CREATE TRIGGER compras_piloto_falla_solicitud BEFORE UPDATE ON solicitudes_compra
         FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo sintético de solicitud'"
    );
    try {
        $resultadoRollbackCompra = comprasPilotoRegistrar(
            $proveedorId,
            [[$productoRollback, 2, 3.00]],
            '700002',
            $solicitudRollback,
            1.00
        );
    } finally {
        $db->getConnection()->query('DROP TRIGGER IF EXISTS compras_piloto_falla_solicitud');
    }
    comprasPilotoAfirmar(!$resultadoRollbackCompra['ok'], 'La compra no reportó el fallo tardío de solicitud.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM compras') === $comprasAntes, 'El rollback dejó la cabecera de compra.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_compras') === $detallesAntes, 'El rollback dejó detalles de compra.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra') === $pagosAntes, 'El rollback dejó el pago inicial.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntes, 'El rollback dejó auditoría parcial.');
    comprasPilotoAfirmar(
        $db->fetchOne('SELECT estado, id_compra FROM solicitudes_compra WHERE id_solicitud = ?', [$solicitudRollback])['estado'] === 'Pendiente'
        && $db->fetchOne('SELECT id_compra FROM solicitudes_compra WHERE id_solicitud = ?', [$solicitudRollback])['id_compra'] === null,
        'El rollback modificó la solicitud pendiente.'
    );
    echo "OK rollback del alta revierte compra, detalles, pago, auditoría y solicitud\n";

    $detalleA = (int)$db->fetchOne('SELECT id_detalle FROM detalle_compras WHERE id_compra = ? AND id_producto = ?', [$compraId, $productoA])['id_detalle'];
    $detalleB = (int)$db->fetchOne('SELECT id_detalle FROM detalle_compras WHERE id_compra = ? AND id_producto = ?', [$compraId, $productoB])['id_detalle'];
    $auditoriasAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $recepcionParcial = comprasPilotoRecibir($compraId, [[
        'id_detalle' => $detalleA,
        'cantidad' => 2,
        'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
    ], [
        'id_detalle' => $detalleB,
        'cantidad' => 0,
        'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
    ]]);
    comprasPilotoAfirmar($recepcionParcial['ok'], 'Falló la recepción parcial de una línea.');
    comprasPilotoAfirmar($db->fetchOne('SELECT estado_recepcion FROM compras WHERE id_compra = ?', [$compraId])['estado_recepcion'] === 'Parcial', 'Una línea completa ocultó el saldo pendiente de otra línea.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoA])['stock_actual'] === 9, 'La recepción parcial no actualizó el stock agregado.');
    comprasPilotoAfirmar((float)$db->fetchOne('SELECT precio_costo FROM productos WHERE id_producto = ?', [$productoA])['precio_costo'] === 3.75, 'La recepción parcial no preservó el costo promedio ponderado actual.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoB])['stock_actual'] === 0, 'La recepción parcial alteró un producto omitido.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$compraId]) === 1, 'La recepción parcial no creó exactamente el lote esperado.');
    $movimientoParcial = $db->fetchOne("SELECT id_movimiento, status FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?", [$compraId]);
    comprasPilotoAfirmar($movimientoParcial !== null && $movimientoParcial['status'] === 'Activo', 'La recepción parcial no creó su movimiento activo.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_movimientos WHERE id_movimiento = ?', [(int)$movimientoParcial['id_movimiento']]) === 1, 'El movimiento parcial no coincide con la línea recibida.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntes + 1, 'La recepción parcial no dejó exactamente una auditoría.');
    $pendientesTablero = (new Recepcion())->comprasPendientes();
    $compraEnTablero = array_values(array_filter(
        $pendientesTablero,
        static fn(array $fila): bool => (int)$fila['id_compra'] === $compraId
    ));
    comprasPilotoAfirmar(
        count($compraEnTablero) === 1
        && $compraEnTablero[0]['estado_recepcion'] === 'Parcial'
        && (int)$compraEnTablero[0]['unidades_pend'] === 3,
        'El tablero de recepción no expuso la compra parcial ni sus unidades pendientes.'
    );
    echo "OK recepción parcial mantiene la compra pendiente hasta completar todas sus líneas\n";

    $stockAntesCero = (int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoB])['stock_actual'];
    $lotesAntesCero = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$compraId]);
    $movimientosAntesCero = comprasPilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?", [$compraId]);
    $auditoriasAntesCero = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $recepcionCero = comprasPilotoRecibir($compraId, [[
        'id_detalle' => $detalleB,
        'cantidad' => 0,
        'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
    ]]);
    comprasPilotoAfirmar(!$recepcionCero['ok'], 'Se aceptó una recepción con todas las cantidades en cero.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoB])['stock_actual'] === $stockAntesCero, 'El rechazo de cantidades cero alteró el stock.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$compraId]) === $lotesAntesCero, 'El rechazo de cantidades cero creó un lote.');
    comprasPilotoAfirmar(comprasPilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?", [$compraId]) === $movimientosAntesCero, 'El rechazo de cantidades cero creó un movimiento.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntesCero, 'El rechazo de cantidades cero creó auditoría.');
    echo "OK recepción con cantidades cero en todas las líneas se rechaza sin escrituras\n";

    $recepcionCompleta = comprasPilotoRecibir($compraId, [[
        'id_detalle' => $detalleB,
        'cantidad' => 3,
        'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
    ]]);
    comprasPilotoAfirmar($recepcionCompleta['ok'], 'Falló la recepción final de la compra.');
    comprasPilotoAfirmar($db->fetchOne('SELECT estado_recepcion FROM compras WHERE id_compra = ?', [$compraId])['estado_recepcion'] === 'Completa', 'La recepción total no marcó la compra completa.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT cantidad_recibida FROM detalle_compras WHERE id_detalle = ?', [$detalleB])['cantidad_recibida'] === 3, 'No se acumuló la cantidad recibida final.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoB])['stock_actual'] === 3, 'El stock final no refleja la recepción completa.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$compraId]) === 2, 'La recepción completa no creó un lote por recepción.');
    $anulacionRecibida = (new Compra())->anular($compraId);
    comprasPilotoAfirmar(!$anulacionRecibida['ok'], 'Se permitió anular una compra con mercancía recibida.');
    comprasPilotoAfirmar($db->fetchOne('SELECT status FROM compras WHERE id_compra = ?', [$compraId])['status'] === 'Activa', 'El intento de anular una compra recibida alteró su estado.');
    echo "OK recepción de saldo completa la compra y stock\n";
    echo "OK anulación de compra recibida sigue rechazada\n";

    $productoRecepcionRollback = comprasPilotoProducto($db, $categoriaId, 'RECEPCION-ROLLBACK');
    $compraRollbackRecepcion = comprasPilotoRegistrar($proveedorId, [[$productoRecepcionRollback, 2, 4.00]], '700003');
    comprasPilotoAfirmar($compraRollbackRecepcion['ok'], 'No se pudo crear la compra de prueba para rollback de recepción.');
    $idCompraRollbackRecepcion = (int)$db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = '700003'", [$proveedorId])['id_compra'];
    $detalleRollbackRecepcion = (int)$db->fetchOne('SELECT id_detalle FROM detalle_compras WHERE id_compra = ?', [$idCompraRollbackRecepcion])['id_detalle'];
    $lotesAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$idCompraRollbackRecepcion]);
    $movimientosAntes = comprasPilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?", [$idCompraRollbackRecepcion]);
    $auditoriasAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $claveRollbackRecepcion = bin2hex(random_bytes(32));
    $documentoRollbackRecepcion = 'GUIA-ROLLBACK-PILOTO';
    $db->getConnection()->query(
        "CREATE TRIGGER compras_piloto_falla_movimiento BEFORE INSERT ON detalle_movimientos
         FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo sintético de movimiento'"
    );
    try {
        $resultadoRollbackRecepcion = comprasPilotoRecibir($idCompraRollbackRecepcion, [[
            'id_detalle' => $detalleRollbackRecepcion,
            'cantidad' => 2,
            'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
        ]], $claveRollbackRecepcion, $documentoRollbackRecepcion);
    } finally {
        $db->getConnection()->query('DROP TRIGGER IF EXISTS compras_piloto_falla_movimiento');
    }
    comprasPilotoAfirmar(!$resultadoRollbackRecepcion['ok'], 'La recepción no informó el fallo tardío del movimiento.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoRecepcionRollback])['stock_actual'] === 0, 'El rollback no restauró el stock agregado.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT cantidad_recibida FROM detalle_compras WHERE id_detalle = ?', [$detalleRollbackRecepcion])['cantidad_recibida'] === 0, 'El rollback no restauró la cantidad recibida.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$idCompraRollbackRecepcion]) === $lotesAntes, 'El rollback dejó un lote parcial.');
    comprasPilotoAfirmar(comprasPilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?", [$idCompraRollbackRecepcion]) === $movimientosAntes, 'El rollback dejó un movimiento parcial.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntes, 'El rollback dejó auditoría parcial de recepción.');
    comprasPilotoAfirmar($db->fetchOne('SELECT estado_recepcion FROM compras WHERE id_compra = ?', [$idCompraRollbackRecepcion])['estado_recepcion'] === 'Pendiente', 'El rollback no restauró el estado de recepción.');
    $reintentoRollback = comprasPilotoRecibir(
        $idCompraRollbackRecepcion,
        [[
            'id_detalle' => $detalleRollbackRecepcion,
            'cantidad' => 2,
            'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
        ]],
        $claveRollbackRecepcion,
        $documentoRollbackRecepcion
    );
    comprasPilotoAfirmar($reintentoRollback['ok'], 'La clave idempotente quedó consumida pese al rollback previo.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$idCompraRollbackRecepcion]) === 1, 'El reintento tras rollback no creó exactamente un lote.');
    comprasPilotoAfirmar(comprasPilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?", [$idCompraRollbackRecepcion]) === 1, 'El reintento tras rollback no creó exactamente un movimiento.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntes + 1, 'El reintento tras rollback no registró exactamente una auditoría.');
    echo "OK rollback de recepción revierte el intento y permite reintentarlo con la misma clave\n";

    $productoExceso = comprasPilotoProducto($db, $categoriaId, 'SOBRERECEPCION');
    $compraExceso = comprasPilotoRegistrar($proveedorId, [[$productoExceso, 2, 1.00]], '700004');
    comprasPilotoAfirmar($compraExceso['ok'], 'No se pudo crear la compra de prueba de sobre-recepción.');
    $idCompraExceso = (int)$db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = '700004'", [$proveedorId])['id_compra'];
    $detalleExceso = (int)$db->fetchOne('SELECT id_detalle FROM detalle_compras WHERE id_compra = ?', [$idCompraExceso])['id_detalle'];
    $resultadoExceso = comprasPilotoRecibir($idCompraExceso, [[
        'id_detalle' => $detalleExceso,
        'cantidad' => 3,
        'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days')),
    ]]);
    comprasPilotoAfirmar(!$resultadoExceso['ok'], 'Se permitió recibir más unidades que las ordenadas.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoExceso])['stock_actual'] === 0, 'La sobre-recepción alteró el stock.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$idCompraExceso]) === 0, 'La sobre-recepción persistió un lote.');
    echo "OK sobre-recepción rechazada sin escrituras\n";

    $productoReintento = comprasPilotoProducto($db, $categoriaId, 'RECEPCION-IDEMPOTENTE');
    $compraReintento = comprasPilotoRegistrar($proveedorId, [[$productoReintento, 8, 2.00]], '700016');
    comprasPilotoAfirmar($compraReintento['ok'], 'No se pudo crear la compra para reintentos de recepción.');
    $idCompraReintento = (int)$db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = '700016'", [$proveedorId])['id_compra'];
    $detalleReintento = (int)$db->fetchOne('SELECT id_detalle FROM detalle_compras WHERE id_compra = ?', [$idCompraReintento])['id_detalle'];
    $documentoReintento = 'GUIA-REUTILIZABLE';
    $clavePrimerIntento = bin2hex(random_bytes(32));
    $claveSegundoIntento = bin2hex(random_bytes(32));
    $claveIntentoConcurrente = bin2hex(random_bytes(32));
    $auditoriasAntesReintento = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');

    $primeraRecepcion = comprasPilotoRecibir(
        $idCompraReintento,
        [['id_detalle' => $detalleReintento, 'cantidad' => 2, 'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days'))]],
        $clavePrimerIntento,
        $documentoReintento
    );
    $segundaRecepcionDistinta = comprasPilotoRecibir(
        $idCompraReintento,
        [['id_detalle' => $detalleReintento, 'cantidad' => 1, 'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days'))]],
        $claveSegundoIntento,
        $documentoReintento
    );
    comprasPilotoAfirmar($primeraRecepcion['ok'] && $segundaRecepcionDistinta['ok'], 'Claves distintas con el mismo documento no permitieron recepciones intencionales separadas.');

    $resultadosReintentoConcurrente = comprasPilotoCarrera([
        [
            'modo' => 'recepcion-idempotente',
            'argumentos' => [$idCompraReintento, $detalleReintento, 2, $claveIntentoConcurrente, $documentoReintento],
        ],
        [
            'modo' => 'recepcion-idempotente',
            'argumentos' => [$idCompraReintento, $detalleReintento, 2, $claveIntentoConcurrente, $documentoReintento],
        ],
    ]);
    comprasPilotoAfirmar(
        count(array_filter($resultadosReintentoConcurrente, static fn(array $resultado): bool => $resultado['ok'] === true)) === 2
        && count(array_filter($resultadosReintentoConcurrente, static fn(array $resultado): bool => str_contains($resultado['mensaje'], 'NO SE REPITIERON CAMBIOS'))) === 1,
        'El reenvío concurrente no devolvió un éxito original y un replay idempotente.'
    );
    $resultadoReintentoSecuencial = comprasPilotoRecibir(
        $idCompraReintento,
        [['id_detalle' => $detalleReintento, 'cantidad' => 2, 'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days'))]],
        $claveIntentoConcurrente,
        $documentoReintento
    );
    comprasPilotoAfirmar(
        $resultadoReintentoSecuencial['ok']
        && str_contains($resultadoReintentoSecuencial['mensaje'], 'NO SE REPITIERON CAMBIOS'),
        'El replay secuencial no informó que la recepción ya se había aplicado.'
    );
    $resultadoClaveReutilizada = comprasPilotoRecibir(
        $idCompraReintento,
        [['id_detalle' => $detalleReintento, 'cantidad' => 1, 'fecha_vencimiento' => date('Y-m-d', strtotime('+90 days'))]],
        $claveIntentoConcurrente,
        $documentoReintento
    );
    comprasPilotoAfirmar(!$resultadoClaveReutilizada['ok'], 'Se aceptó reutilizar la clave idempotente con contenido distinto.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT cantidad_recibida FROM detalle_compras WHERE id_detalle = ?', [$detalleReintento])['cantidad_recibida'] === 5, 'Los reenvíos incrementaron dos veces la cantidad recibida.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoReintento])['stock_actual'] === 5, 'Los reenvíos duplicaron el incremento de stock.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$idCompraReintento]) === 3, 'Los reenvíos duplicaron lotes o impidieron operaciones distintas.');
    comprasPilotoAfirmar(comprasPilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?", [$idCompraReintento]) === 3, 'Los reenvíos duplicaron movimientos o impidieron operaciones distintas.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntesReintento + 3, 'Un replay duplicó auditoría o una recepción distinta no la registró.');
    echo "OK replay idempotente secuencial/concurrente y claves distintas con documento repetido\n";

    $productoCompraCarrera = comprasPilotoProducto($db, $categoriaId, 'COMPRA-CARRERA');
    $comprasAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM compras');
    $detallesAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_compras');
    $pagosAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra');
    $auditoriasAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $db->getConnection()->query(
        "CREATE TRIGGER compras_piloto_retardo_compra BEFORE INSERT ON compras
         FOR EACH ROW BEGIN DO SLEEP(0.5); END"
    );
    try {
        $resultadosCompraConcurrentes = comprasPilotoCarrera(array_fill(0, 2, [
            'modo' => 'compra',
            'argumentos' => [
                (string)$proveedorId,
                (string)$productoCompraCarrera,
                '700005',
            ],
        ]));
    } finally {
        $db->getConnection()->query('DROP TRIGGER IF EXISTS compras_piloto_retardo_compra');
    }
    comprasPilotoAfirmar(
        count(array_filter($resultadosCompraConcurrentes, static fn(array $r): bool => ($r['ok'] ?? false) === true)) === 1,
        'La carrera de facturas duplicadas no produjo exactamente un alta.'
    );
    comprasPilotoAfirmar(comprasPilotoContar($db, "SELECT COUNT(*) AS total FROM compras WHERE id_proveedor = ? AND nro_factura = '700005'", [$proveedorId]) === 1, 'La carrera persistió facturas duplicadas.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM detalle_compras') === $detallesAntes + 1, 'La carrera dejó detalle parcial o duplicado.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM pagos_compra') === $pagosAntes, 'La carrera creó pagos inesperados.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM compras') === $comprasAntes + 1, 'La carrera dejó una cantidad incorrecta de cabeceras.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntes + 1, 'La carrera duplicó o dejó auditoría parcial.');
    echo "OK concurrencia de alta con factura duplicada deja una compra íntegra\n";

    $productoRecepcionCarrera = comprasPilotoProducto($db, $categoriaId, 'RECEPCION-CARRERA');
    $compraCarreraRecepcion = comprasPilotoRegistrar($proveedorId, [[$productoRecepcionCarrera, 3, 2.00]], '700006');
    comprasPilotoAfirmar($compraCarreraRecepcion['ok'], 'No se pudo crear la compra para carrera de recepción.');
    $idCompraCarreraRecepcion = (int)$db->fetchOne("SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = '700006'", [$proveedorId])['id_compra'];
    $detalleCarreraRecepcion = (int)$db->fetchOne('SELECT id_detalle FROM detalle_compras WHERE id_compra = ?', [$idCompraCarreraRecepcion])['id_detalle'];
    $auditoriasAntes = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $resultadosRecepcionConcurrentes = comprasPilotoCarrera(array_fill(0, 2, [
        'modo' => 'recepcion',
        'argumentos' => [
            (string)$idCompraCarreraRecepcion,
            (string)$detalleCarreraRecepcion,
            '2',
        ],
    ]));
    comprasPilotoAfirmar(
        count(array_filter($resultadosRecepcionConcurrentes, static fn(array $r): bool => ($r['ok'] ?? false) === true)) === 1,
        'La carrera de recepciones no produjo exactamente una recepción aceptada.'
    );
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT cantidad_recibida FROM detalle_compras WHERE id_detalle = ?', [$detalleCarreraRecepcion])['cantidad_recibida'] === 2, 'La carrera excedió o perdió la cantidad recibida.');
    comprasPilotoAfirmar((int)$db->fetchOne('SELECT stock_actual FROM productos WHERE id_producto = ?', [$productoRecepcionCarrera])['stock_actual'] === 2, 'La carrera dejó stock agregado inconsistente.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?', [$idCompraCarreraRecepcion]) === 1, 'La carrera duplicó o perdió lotes.');
    comprasPilotoAfirmar(comprasPilotoContar($db, "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?", [$idCompraCarreraRecepcion]) === 1, 'La carrera duplicó movimientos.');
    comprasPilotoAfirmar(comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntes + 1, 'La carrera generó auditoría duplicada o parcial.');
    echo "OK concurrencia de recepción serializa contra el saldo pendiente\n";

    $productoCompraAnulacion = comprasPilotoProducto($db, $categoriaId, 'ANULACION-CARRERA');
    $compraAnulacionCarrera = comprasPilotoRegistrar(
        $proveedorId,
        [[$productoCompraAnulacion, 3, 2.00]],
        '700007'
    );
    comprasPilotoAfirmar($compraAnulacionCarrera['ok'], 'No se pudo crear la compra para carrera de anulación/recepción.');
    $idCompraAnulacionCarrera = (int)$db->fetchOne(
        "SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = '700007'",
        [$proveedorId]
    )['id_compra'];
    $detalleAnulacionCarrera = (int)$db->fetchOne(
        'SELECT id_detalle FROM detalle_compras WHERE id_compra = ?',
        [$idCompraAnulacionCarrera]
    )['id_detalle'];
    $stockAntesCarreraAnulacion = (int)$db->fetchOne(
        'SELECT stock_actual FROM productos WHERE id_producto = ?',
        [$productoCompraAnulacion]
    )['stock_actual'];
    $auditoriasAntesCarreraAnulacion = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');

    [$resultadoCarreraAnulacion, $resultadoCarreraRecepcion] = comprasPilotoCarrera([
        [
            'modo' => 'anular-compra',
            'argumentos' => [(string)$idCompraAnulacionCarrera],
        ],
        [
            'modo' => 'recepcion',
            'argumentos' => [
                (string)$idCompraAnulacionCarrera,
                (string)$detalleAnulacionCarrera,
                '2',
            ],
        ],
    ]);

    $anulacionAceptada = ($resultadoCarreraAnulacion['ok'] ?? false) === true;
    $recepcionAceptada = ($resultadoCarreraRecepcion['ok'] ?? false) === true;
    comprasPilotoAfirmar(
        $anulacionAceptada !== $recepcionAceptada,
        'La carrera anulación/recepción no produjo exactamente una operación aceptada.'
    );
    $compraCarreraFinal = $db->fetchOne(
        'SELECT status, estado_recepcion FROM compras WHERE id_compra = ?',
        [$idCompraAnulacionCarrera]
    );
    $lotesCarreraAnulacion = comprasPilotoContar(
        $db,
        'SELECT COUNT(*) AS total FROM lotes WHERE id_compra = ?',
        [$idCompraAnulacionCarrera]
    );
    $stockCarreraAnulacion = (int)$db->fetchOne(
        'SELECT stock_actual FROM productos WHERE id_producto = ?',
        [$productoCompraAnulacion]
    )['stock_actual'];
    $detallesRecibidosCarrera = (int)$db->fetchOne(
        'SELECT cantidad_recibida FROM detalle_compras WHERE id_detalle = ?',
        [$detalleAnulacionCarrera]
    )['cantidad_recibida'];
    $movimientosCarreraAnulacion = comprasPilotoContar(
        $db,
        "SELECT COUNT(*) AS total FROM movimientos WHERE tipo_referencia = 'compra' AND id_referencia = ?",
        [$idCompraAnulacionCarrera]
    );
    comprasPilotoAfirmar(
        $anulacionAceptada
        ? $compraCarreraFinal['status'] === 'Anulada'
            && $compraCarreraFinal['estado_recepcion'] === 'Pendiente'
            && $lotesCarreraAnulacion === 0
            && $stockCarreraAnulacion === $stockAntesCarreraAnulacion
            && $detallesRecibidosCarrera === 0
            && $movimientosCarreraAnulacion === 0
        : $recepcionAceptada
            && $compraCarreraFinal['status'] === 'Activa'
            && $compraCarreraFinal['estado_recepcion'] === 'Parcial'
            && $lotesCarreraAnulacion === 1
            && $stockCarreraAnulacion === $stockAntesCarreraAnulacion + 2
            && $detallesRecibidosCarrera === 2
            && $movimientosCarreraAnulacion === 1,
        'La carrera dejó estado, lotes o stock incompatibles entre compra y recepción.'
    );
    comprasPilotoAfirmar(
        comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntesCarreraAnulacion + 1,
        'La carrera no registró exactamente una auditoría para la operación aceptada.'
    );
    echo "OK concurrencia de anulación y recepción conserva estados compatibles\n";

    $productoRollbackAnulacion = comprasPilotoProducto($db, $categoriaId, 'ANULACION-ROLLBACK');
    $compraRollbackAnulacion = comprasPilotoRegistrar(
        $proveedorId,
        [[$productoRollbackAnulacion, 1, 2.00]],
        '700008'
    );
    comprasPilotoAfirmar($compraRollbackAnulacion['ok'], 'No se pudo crear la compra para rollback de anulación.');
    $idCompraRollbackAnulacion = (int)$db->fetchOne(
        "SELECT id_compra FROM compras WHERE id_proveedor = ? AND nro_factura = '700008'",
        [$proveedorId]
    )['id_compra'];
    $auditoriasAntesRollbackAnulacion = comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria');
    $db->getConnection()->query(
        "CREATE TRIGGER compras_piloto_falla_auditoria_anulacion BEFORE INSERT ON auditoria
         FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo sintético de auditoría de anulación'"
    );
    try {
        $resultadoRollbackAnulacion = (new Compra())->anular($idCompraRollbackAnulacion);
    } finally {
        $db->getConnection()->query('DROP TRIGGER IF EXISTS compras_piloto_falla_auditoria_anulacion');
    }
    comprasPilotoAfirmar(!$resultadoRollbackAnulacion['ok'], 'La anulación no reportó el fallo tardío de auditoría.');
    comprasPilotoAfirmar(
        $db->fetchOne('SELECT status FROM compras WHERE id_compra = ?', [$idCompraRollbackAnulacion])['status'] === 'Activa',
        'El rollback dejó anulada una compra sin auditoría.'
    );
    comprasPilotoAfirmar(
        comprasPilotoContar($db, 'SELECT COUNT(*) AS total FROM auditoria') === $auditoriasAntesRollbackAnulacion,
        'El rollback dejó una auditoría parcial de anulación.'
    );
    echo "OK rollback de anulación revierte el estado si falla la auditoría\n";

    $salidaPermiso = shell_exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' permiso 2>&1'
    );
    comprasPilotoAfirmar(trim((string)$salidaPermiso) === 'PERMISO_DENEGADO', 'El controlador de compras no bloqueó al operador de ventas.');
    echo "OK permiso del controlador rechaza al rol sin acceso a Compras\n";
    echo "SUITE COMPRAS/RECEPCION COMPLETA\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FALLO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
