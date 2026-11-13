<?php

/**
 * Piloto del alta rápida de producto desde el catálogo de costos (POP-UP 3).
 *
 * Usa una base de datos simulada: no carga configuración local ni abre
 * conexiones. Comprueba la transacción del alta, los rechazos y el contrato
 * estático entre vista, controlador, JavaScript y CSS.
 */

$root = dirname(__DIR__);

final class Database
{
    private static ?self $instance = null;
    public array $inserts = [];
    public array $updates = [];
    public bool $enTransaccion = false;
    public int $rollbacks = 0;
    public int $commits = 0;
    public bool $skuDuplicado = false;
    public bool $fallarCatalogo = false;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        if (str_contains($sql, 'FROM proveedores')) {
            return ['id_proveedor' => 2];
        }
        if (str_contains($sql, 'FROM categorias')) {
            return ((int)($params[0] ?? 0)) > 0 ? ['id_categoria' => 1] : null;
        }
        if (str_contains($sql, 'FROM productos WHERE id_producto')) {
            return ['id_producto' => 4];
        }
        if (str_contains($sql, 'FROM productos WHERE sku')) {
            return $this->skuDuplicado ? ['id_producto' => 7] : null;
        }
        return null;
    }

    public function insert(string $tabla, array $datos): int
    {
        if ($tabla === 'catalogo_costos' && $this->fallarCatalogo) {
            throw new RuntimeException('Fallo simulado al insertar el catálogo.');
        }
        $this->inserts[] = [$tabla, $datos];
        return $tabla === 'productos' ? 99 : 55;
    }

    public function execute(string $sql, array $params = []): void
    {
        $this->updates[] = [$sql, $params];
    }

    public function begin(): void
    {
        $this->enTransaccion = true;
    }

    public function commit(): void
    {
        $this->enTransaccion = false;
        $this->commits++;
    }

    public function rollback(): void
    {
        $this->enTransaccion = false;
        $this->rollbacks++;
    }

    public function inTransaction(): bool
    {
        return $this->enTransaccion;
    }

    public function affectedRows(): int
    {
        return 1;
    }
}

function registrarAuditoria(string $accion, string $detalle): void
{
}

require_once $root . '/core/Model.php';
require_once $root . '/models/Proveedor.php';

$fallos = 0;
$total = 0;
$afirmar = static function (bool $condicion, string $mensaje) use (&$fallos, &$total): void {
    $total++;
    if (!$condicion) {
        $fallos++;
        fwrite(STDERR, "FALLO: {$mensaje}\n");
    }
};

$db = Database::getInstance();
$modelo = new Proveedor();

$datos = [
    'accion' => 'registrar_con_producto',
    'id_proveedor' => 2,
    'costo' => '9.99',
    'stock_disponible' => '12',
    'codigo_proveedor' => 'PROV-01',
    'nombre_producto' => 'Batería de prueba',
    'sku' => ' bat-test-01 ',
    'id_categoria' => 1,
    'precio_venta' => '15.50',
];

// Alta válida: producto + catálogo en la misma transacción.
$resultado = $modelo->procesarCatalogo($datos);
$afirmar($resultado['ok'] === true, 'El alta válida debe completarse.');
$afirmar($resultado['mensaje'] === 'PRODUCTO CREADO Y AGREGADO AL CATÁLOGO DEL PROVEEDOR.', 'El mensaje debe confirmar el alta conjunta.');
$afirmar($db->commits === 1 && $db->rollbacks === 0, 'El alta válida debe confirmar una transacción.');
$afirmar(count($db->inserts) === 2, 'El alta debe insertar el producto y su entrada de catálogo.');
$afirmar($db->inserts[0][0] === 'productos', 'Primero se inserta el producto.');
$producto = $db->inserts[0][1];
$afirmar($producto['sku'] === 'BAT-TEST-01', 'El SKU debe normalizarse a mayúsculas y sin espacios.');
$afirmar((int)$producto['stock_actual'] === 0, 'El producto nuevo debe nacer con stock 0.');
$afirmar((float)$producto['precio_costo'] === 9.99, 'El costo del producto debe ser el del catálogo.');
$afirmar((float)$producto['precio_venta'] === 15.50, 'El precio de venta debe respetarse.');
$afirmar($producto['requiere_vencimiento'] === 0 && $producto['fecha_vencimiento'] === null, 'El producto nuevo no debe exigir vencimiento.');
$afirmar($db->inserts[1][0] === 'catalogo_costos' && (int)$db->inserts[1][1]['id_producto'] === 99, 'El catálogo debe apuntar al producto recién creado.');

// SKU duplicado: revierte y no inserta nada.
$db->inserts = [];
$db->commits = 0;
$db->rollbacks = 0;
$db->skuDuplicado = true;
$resultado = $modelo->procesarCatalogo($datos);
$afirmar($resultado['ok'] === false, 'Un SKU existente debe rechazarse.');
$afirmar($db->rollbacks === 1 && $db->commits === 0, 'El SKU duplicado debe revertir la transacción.');
$afirmar($db->inserts === [], 'El SKU duplicado no debe insertar producto ni catálogo.');

// Fallo al insertar el catálogo: revierte todo.
$db->skuDuplicado = false;
$db->fallarCatalogo = true;
$db->rollbacks = 0;
$db->commits = 0;
$db->inserts = [];
$resultado = $modelo->procesarCatalogo($datos);
$afirmar($resultado['ok'] === false, 'Un fallo del catálogo debe informarse.');
$afirmar($db->rollbacks === 1 && $db->commits === 0, 'El fallo del catálogo debe revertir la transacción completa.');
$afirmar($db->enTransaccion === false, 'La transacción debe quedar cerrada tras el fallo.');

// Validaciones previas del producto nuevo.
$db->fallarCatalogo = false;
$casos = [
    ['nombre_producto' => '', 'mensaje' => 'EL NOMBRE DEL PRODUCTO ES OBLIGATORIO'],
    ['sku' => 'CON ESPACIO', 'mensaje' => 'SKU INVÁLIDO'],
    ['precio_venta' => '0', 'mensaje' => 'PRECIO DE VENTA INVÁLIDO'],
    ['id_categoria' => 0, 'mensaje' => 'SELECCIONA UNA CATEGORÍA ACTIVA'],
];
foreach ($casos as $caso) {
    $entrada = array_merge($datos, $caso);
    unset($entrada['mensaje']);
    $resultado = $modelo->procesarCatalogo($entrada);
    $afirmar($resultado['ok'] === false && str_contains($resultado['mensaje'], $caso['mensaje']), 'Debe rechazarse: ' . $caso['mensaje'] . '.');
}

// Contrato estático de vista, controlador, JS y CSS.
$vista = (string)file_get_contents($root . '/views/compras/index.php');
$controlador = (string)file_get_contents($root . '/controllers/ComprasController.php');
$js = (string)file_get_contents($root . '/assets/modules/compras/compras.js');
$css = (string)file_get_contents($root . '/assets/modules/compras/compras.css');

$afirmar(str_contains($vista, 'id="btnCatProductoNuevo"'), 'La vista debe ofrecer el botón Producto nuevo.');
$afirmar(str_contains($vista, 'id="catPanelProductoNuevo"'), 'La vista debe incluir el panel de alta.');
foreach (['nombre_producto', 'sku', 'id_categoria', 'precio_venta'] as $campo) {
    $afirmar(str_contains($vista, 'name="' . $campo . '"'), 'La vista debe enviar el campo ' . $campo . '.');
}
$afirmar(str_contains($controlador, "'nombre_producto'") && str_contains($controlador, "'precio_venta'"), 'El controlador debe reenviar los datos del producto nuevo.');
$afirmar(str_contains($controlador, 'categorias_activas'), 'El controlador debe entregar las categorías activas.');
$afirmar(str_contains($js, 'function alternarProductoNuevo'), 'El JS debe alternar entre producto existente y nuevo.');
$afirmar(str_contains($js, "registrar_con_producto"), 'El JS debe activar la acción registrar_con_producto.');
$afirmar(str_contains($css, '.cat-panel-nuevo') && str_contains($css, '.btn-cat-nuevo'), 'El CSS debe estilizar el botón y el panel del alta.');

if ($fallos > 0) {
    fwrite(STDERR, "Piloto de alta de producto: {$fallos} fallo(s) de {$total}.\n");
    exit(1);
}

echo "OK compras_producto_nuevo_piloto: {$total} afirmaciones en verde.\n";
exit(0);
