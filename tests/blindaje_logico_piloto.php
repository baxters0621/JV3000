<?php

/**
 * Piloto de blindaje lógico.
 *
 * Verifica que el parseo estricto de POST rechace arreglos y tipos inválidos,
 * que el preview ignore el precio del vendedor y rechace tipos de movimiento
 * que no son salidas, y que los guards críticos sigan presentes en el código.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../models/NotaEntrega.php';

Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

$afirmaciones = 0;
$fallos = [];

function afirmar(bool $condicion, string $descripcion): void
{
    global $afirmaciones, $fallos;
    $afirmaciones++;
    if (!$condicion) {
        $fallos[] = $descripcion;
    }
}

/** Controlador mínimo para exponer los helpers protegidos. */
class ControladorPiloto extends Controller
{
    public function int(string $campo, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
    {
        return $this->postInt($campo, $min, $max);
    }

    public function float(string $campo, float $min = -1.0e15, float $max = 1.0e15): ?float
    {
        return $this->postFloat($campo, $min, $max);
    }

    public function texto(string $campo, int $max = 255): ?string
    {
        return $this->postTexto($campo, $max);
    }
}

$controlador = new ControladorPiloto();

$_POST = ['id_compra' => ['1'], 'monto_pago' => 'abc', 'metodo_pago' => ['Efectivo'], 'nota' => ['x']];
afirmar($controlador->int('id_compra', 1) === null, 'postInt rechaza arreglos.');
afirmar($controlador->float('monto_pago', 0.01) === null, 'postFloat rechaza letras.');
afirmar($controlador->texto('metodo_pago') === null, 'postTexto rechaza arreglos.');
afirmar($controlador->int('inexistente') === null, 'postInt rechaza campos ausentes.');

$_POST = ['cantidad' => '-5', 'precio' => '12.50', 'texto' => '  hola  '];
afirmar($controlador->int('cantidad', 1) === null, 'postInt rechaza negativos fuera de rango.');
afirmar($controlador->float('precio', 0.01) === 12.5, 'postFloat acepta decimales válidos.');
afirmar($controlador->texto('texto', 20) === 'hola', 'postTexto recorta espacios.');

// Precio: el vendedor no puede imponer 0,01; se usa el precio de la BD.
$producto = Database::getInstance()->fetchOne(
    "SELECT id_producto, precio_venta FROM productos WHERE status = 'Activo' ORDER BY id_producto LIMIT 1"
);
afirmar($producto !== null, 'Hay un producto activo para la prueba de precio.');
if ($producto) {
    $preview = (new NotaEntrega())->construirPreview([
        'id_tipo_mov'    => 1,
        'productos_data' => json_encode([[
            'id_producto' => (int)$producto['id_producto'],
            'cantidad'    => 1,
            'precio'      => 0.01,
        ]]),
        'cliente'        => 'CLIENTE DE PRUEBA',
        'rif_cliente'    => 'V-11222333',
    ], 1, false);
    $precio_guardado = 0.0;
    if (!empty($preview['ok'])) {
        $items = json_decode($preview['data']['productos_data'], true);
        $precio_guardado = (float)($items[0]['precio'] ?? 0);
    }
    afirmar(!empty($preview['ok']), 'El preview válido de venta se construye.');
    afirmar($precio_guardado === (float)$producto['precio_venta'], 'El precio del vendedor se sustituye por el de la BD.');
    afirmar($precio_guardado !== 0.01, 'El precio manipulado 0,01 no se conserva.');
}

// Tipo de movimiento de entrada rechazado en salidas.
$preview_tipo = (new NotaEntrega())->construirPreview([
    'id_tipo_mov'    => 7,
    'productos_data' => json_encode([['id_producto' => 1, 'cantidad' => 1, 'precio' => 1]]),
    'cliente'        => 'CLIENTE DE PRUEBA',
    'rif_cliente'    => 'V-11222333',
], 1, false);
afirmar(empty($preview_tipo['ok']), 'Un tipo de movimiento de entrada no se acepta como salida.');

// Administrador: el precio manual exige motivo y se conserva.
$preview_admin = (new NotaEntrega())->construirPreview([
    'id_tipo_mov'       => 1,
    'precio_autorizado' => 1,
    'motivo_precio'     => 'Ajuste comercial autorizado',
    'productos_data'    => json_encode([['id_producto' => 1, 'cantidad' => 1, 'precio' => 0.01]]),
    'cliente'           => 'CLIENTE DE PRUEBA',
    'rif_cliente'       => 'V-11222333',
], 1, true);
afirmar(!empty($preview_admin['ok']), 'El administrador puede autorizar un precio manual con motivo.');
if (!empty($preview_admin['ok'])) {
    $items_admin = json_decode($preview_admin['data']['productos_data'], true);
    afirmar((float)($items_admin[0]['precio'] ?? 0) === 0.01, 'El precio autorizado se conserva para el administrador.');
    afirmar(strpos((string)$preview_admin['data']['observaciones'], 'Ajuste comercial autorizado') !== false, 'El motivo queda en observaciones.');
}
$preview_admin_sin_motivo = (new NotaEntrega())->construirPreview([
    'id_tipo_mov'       => 1,
    'precio_autorizado' => 1,
    'motivo_precio'     => '',
    'productos_data'    => json_encode([['id_producto' => 1, 'cantidad' => 1, 'precio' => 0.01]]),
    'cliente'           => 'CLIENTE DE PRUEBA',
    'rif_cliente'       => 'V-11222333',
], 1, true);
afirmar(empty($preview_admin_sin_motivo['ok']), 'El precio manual sin motivo se rechaza.');

// Edición sin admin rechazada en preview.
$preview_edicion = (new NotaEntrega())->construirPreview([
    'id_tipo_mov'    => 1,
    'accion_salida'  => 'editar',
    'id_salida'      => 1,
    'productos_data' => json_encode([['id_producto' => 1, 'cantidad' => 1, 'precio' => 1]]),
    'cliente'        => 'CLIENTE DE PRUEBA',
    'rif_cliente'    => 'V-11222333',
], 1, false);
afirmar(empty($preview_edicion['ok']), 'Un vendedor no puede preparar la edición de una venta.');

// Guards estáticos en el código.
$nota = file_get_contents(__DIR__ . '/../models/NotaEntrega.php');
$salida = file_get_contents(__DIR__ . '/../models/Salida.php');
$estadisticas = file_get_contents(__DIR__ . '/../controllers/EstadisticasController.php');
$compras = file_get_contents(__DIR__ . '/../controllers/ComprasController.php');

afirmar(strpos($nota, 'precio_autorizado') !== false, 'NotaEntrega contempla el precio autorizado.');
afirmar(strpos($nota, 'SOLO UN ADMINISTRADOR PUEDE EDITAR') !== false, 'NotaEntrega bloquea la edición sin admin.');
afirmar(strpos($salida, 'TIPO DE MOVIMIENTO INVÁLIDO') !== false, 'Salida revalida el tipo de movimiento.');
afirmar(strpos($salida, 'SOLO UN ADMINISTRADOR PUEDE EDITAR') !== false, 'Salida bloquea la edición sin admin.');
afirmar(substr_count($estadisticas, '$this->exigirAjaxVentas();') === 6, 'Los seis endpoints de estadísticas usan el guard central.');
afirmar(strpos($estadisticas, 'private function exigirAjaxVentas') !== false, 'Existe el guard central de estadísticas.');
afirmar(strpos($compras, "postInt('id_compra'") !== false, 'Compras valida el id de pago como entero estricto.');

if ($fallos !== []) {
    echo "FALLOS (" . count($fallos) . "):\n - " . implode("\n - ", $fallos) . "\n";
    exit(1);
}

echo "OK blindaje_logico_piloto: $afirmaciones afirmaciones en verde.\n";
