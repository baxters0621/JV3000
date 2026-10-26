<?php

// ==========================================
// PILOTO: jerarquía y estado automático de categorías
// ==========================================
// Ejecutar desde la raíz: & "C:\xampp\php\php.exe" tests\categorias_jerarquia_piloto.php
// Usa una base de datos falsa (en memoria): verifica las reglas del modelo
// Categoria sin tocar la base de datos real.
//  - Registrar raíz y subcategoría (nivel, ruta, código CAT-XXX).
//  - Padre inexistente / subcategoría / inactivo: rechazados.
//  - Nombre duplicado entre hermanas: rechazado.
//  - Editar una raíz con hijos: arrastrar la ruta de los hijos y prohibir
//    que una raíz con subcategorías se vuelva subcategoría.
//  - Cambio a mano solo en categorías sin productos.
//  - Estado automático por stock (sincronizarPorStock).
//  - Árbol (listarArbol) con conteos acumulados de la rama.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

class Database
{
    private static ?self $instance = null;

    /** Filas configurables que devuelven las consultas de una sola fila. */
    public ?array $filaPadre = null;        // Padre del formulario (id/nombre/ruta/status/parent_id)
    public ?array $filaParentId = null;     // parent_id propio en edición
    public ?array $filaCompleta = null;     // SELECT * de la categoría editada
    public ?array $filaToggle = null;       // Fila del cambio a mano
    public ?array $duplicado = null;        // Hermana con el mismo nombre
    public ?array $tieneHijos = null;       // Guardia de raíz con subcategorías
    public int $contador = 42;              // último_numero del contador CAT
    public int $productosEnRama = 0;        // Productos de la rama (toggle)

    /** Listados configurables. */
    public array $hijosDeRuta = [];         // Hijos arrastrados al renombrar
    public array $filasListado = [];        // Listado plano para listar()
    public array $syncSubcategorias = [];   // Subcategorías en sincronizarPorStock
    public array $syncRaices = [];          // Raíces en sincronizarPorStock
    public array $filasJerarquia = [];      // Reparación de nivel/ruta

    /** Registro de lo sucedido. */
    public array $consultas = [];
    public array $escrituras = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->consultas[] = ['sql' => $sql, 'params' => $params];
        if (strpos($sql, 'sku_contadores') !== false) {
            return ['ultimo_numero' => $this->contador];
        }
        if (strpos($sql, 'SELECT id_categoria, nombre, ruta, status, parent_id') === 0) {
            return $this->filaPadre;
        }
        if (strpos($sql, 'SELECT parent_id FROM categorias') === 0) {
            return $this->filaParentId;
        }
        if (strpos($sql, 'SELECT * FROM categorias WHERE id_categoria') === 0) {
            return $this->filaCompleta;
        }
        if (strpos($sql, 'SELECT id_categoria, nombre, status, ruta') === 0) {
            return $this->filaToggle;
        }
        if (strpos($sql, 'LOWER(nombre) = LOWER(?)') !== false) {
            return $this->duplicado;
        }
        if (strpos($sql, 'COUNT(*) AS total') !== false) {
            return ['total' => $this->productosEnRama];
        }
        if (strpos($sql, 'WHERE parent_id = ? LIMIT 1') !== false) {
            return $this->tieneHijos;
        }
        return null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $this->consultas[] = ['sql' => $sql, 'params' => $params];
        if (strpos($sql, 'codigo IS NULL') !== false) {
            return [];
        }
        if (strpos($sql, 'ruta LIKE') !== false) {
            return $this->hijosDeRuta;
        }
        if (strpos($sql, 'COALESCE(c.parent_id') !== false) {
            return $this->filasListado;
        }
        if (strpos($sql, 'parent_id IS NOT NULL') !== false) {
            return $this->syncSubcategorias;
        }
        if (strpos($sql, 'parent_id IS NULL') !== false) {
            return $this->syncRaices;
        }
        if (strpos($sql, 'ORDER BY nivel ASC') !== false) {
            return $this->filasJerarquia;
        }
        return [];
    }

    public function execute(string $sql, array $params = []): bool
    {
        $this->escrituras[] = ['tipo' => 'execute', 'sql' => $sql, 'params' => $params];
        return true;
    }

    public function insert(string $tabla, array $datos): int
    {
        $this->escrituras[] = ['tipo' => 'insert', 'tabla' => $tabla, 'datos' => $datos];
        return 1;
    }

    public function update(string $tabla, array $datos, string $where, array $whereParams = []): int
    {
        $this->escrituras[] = ['tipo' => 'update', 'tabla' => $tabla, 'datos' => $datos, 'where' => $where, 'params' => $whereParams];
        return 1;
    }

    public function begin(): void {}
    public function commit(): void {}
    public function rollback(): void {}
}

$root = dirname(__DIR__);
require_once $root . '/includes/helpers.php';
require_once $root . '/core/Model.php';
require_once $root . '/models/Categoria.php';

$db = Database::getInstance();
$modelo = new Categoria();
$afirmaciones = 0;

$afirmar = static function (bool $condicion, string $mensaje) use (&$afirmaciones): void {
    $afirmaciones++;
    if (!$condicion) {
        throw new RuntimeException('FALLO: ' . $mensaje);
    }
};

$limpiar = static function () use ($db): void {
    $db->filaPadre = null;
    $db->filaParentId = null;
    $db->filaCompleta = null;
    $db->filaToggle = null;
    $db->duplicado = null;
    $db->tieneHijos = null;
    $db->contador = 42;
    $db->productosEnRama = 0;
    $db->hijosDeRuta = [];
    $db->filasListado = [];
    $db->syncSubcategorias = [];
    $db->syncRaices = [];
    $db->filasJerarquia = [];
    $db->consultas = [];
    $db->escrituras = [];
};

$buscarInsercion = static function () use ($db): array {
    // La primera escritura del alta es el contador CAT-XXX; la inserción de
    // la categoría va después (y la auditoría al final).
    foreach ($db->escrituras as $escritura) {
        if (($escritura['tipo'] ?? '') === 'insert' && ($escritura['tabla'] ?? '') === 'categorias') {
            return $escritura;
        }
    }
    return [];
};
$raizMotos = [
    'id_categoria' => 9, 'nombre' => 'REPUESTOS PARA MOTOS', 'codigo' => 'CAT-MOTO',
    'parent_id' => null, 'nivel' => 0, 'ruta' => 'REPUESTOS PARA MOTOS', 'status' => 'Activo',
    'productos_directos' => 2, 'productos_con_stock' => 1,
];
$raizAutos = [
    'id_categoria' => 10, 'nombre' => 'REPUESTOS PARA AUTOS', 'codigo' => 'CAT-AUTO',
    'parent_id' => null, 'nivel' => 0, 'ruta' => 'REPUESTOS PARA AUTOS', 'status' => 'Activo',
    'productos_directos' => 1, 'productos_con_stock' => 1,
];
$hijaRin13 = [
    'id_categoria' => 13, 'nombre' => 'RIN 13', 'codigo' => 'CAT-RIN13',
    'parent_id' => 9, 'nivel' => 1, 'ruta' => 'REPUESTOS PARA MOTOS/RIN 13', 'status' => 'Activo',
    'productos_directos' => 2, 'productos_con_stock' => 0,
];

// 1. Registrar una raíz: nivel 0, ruta = nombre, estado Activo y código nuevo.
$limpiar();
$resultado = $modelo->procesar(['accion' => 'registrar', 'nombre' => 'cauchos', 'id_padre' => 0]);
$afirmar($resultado['ok'], 'El alta de una raíz debe aceptarse.');
$insercion = $buscarInsercion();
$afirmar($insercion !== [], 'El alta debe insertar en categorias.');
$afirmar(($insercion['datos']['nivel'] ?? -1) === 0, 'Una raíz se registra en el nivel 0.');
$afirmar(($insercion['datos']['ruta'] ?? '') === 'CAUCHOS', 'La ruta de una raíz es su nombre en mayúsculas.');
$afirmar(($insercion['datos']['status'] ?? '') === 'Activo', 'Toda categoría nueva nace Activa.');
$afirmar(($insercion['datos']['codigo'] ?? '') === 'CAT-043', 'El código sale del contador (42 + 1 = CAT-043).');
$afirmar(array_key_exists('parent_id', $insercion['datos']) && $insercion['datos']['parent_id'] === null, 'Una raíz no lleva parent_id.');

// 2. Registrar una subcategoría con padre activo: nivel 1 y ruta compuesta.
$limpiar();
$db->filaPadre = $raizMotos;
$resultado = $modelo->procesar(['accion' => 'registrar', 'nombre' => 'rin 15', 'id_padre' => 9]);
$afirmar($resultado['ok'], 'El alta de una subcategoría con padre activo debe aceptarse.');
$insercion = $buscarInsercion();
$afirmar($insercion !== [], 'La subcategoría debe insertarse en categorias.');
$afirmar(($insercion['datos']['parent_id'] ?? 0) === 9, 'La subcategoría apunta a su raíz.');
$afirmar(($insercion['datos']['nivel'] ?? -1) === 1, 'La subcategoría se registra en el nivel 1.');
$afirmar(($insercion['datos']['ruta'] ?? '') === 'REPUESTOS PARA MOTOS/RIN 15', 'La ruta hereda el tramo del padre.');

// 3. Padre inexistente: rechazado sin escribir nada.
$limpiar();
$resultado = $modelo->procesar(['accion' => 'registrar', 'nombre' => 'rin 15', 'id_padre' => 999]);
$afirmar(!$resultado['ok'], 'Un padre inexistente debe rechazarse.');
$afirmar($db->escrituras === [], 'El rechazo no debe escribir nada.');

// 4. Padre subcategoría (tercer nivel): rechazado.
$limpiar();
$db->filaPadre = $hijaRin13;
$resultado = $modelo->procesar(['accion' => 'registrar', 'nombre' => 'llanta', 'id_padre' => 13]);
$afirmar(!$resultado['ok'], 'Solo las raíces pueden tener subcategorías (máximo 2 niveles).');

// 5. Padre inactivo: rechazado.
$limpiar();
$db->filaPadre = array_merge($raizMotos, ['status' => 'Inactivo']);
$resultado = $modelo->procesar(['accion' => 'registrar', 'nombre' => 'rin 15', 'id_padre' => 9]);
$afirmar(!$resultado['ok'], 'El padre debe estar activo.');

// 6. Nombre repetido entre hermanas: rechazado; en otro nivel, aceptado.
$limpiar();
$db->filaPadre = $raizMotos;
$db->duplicado = ['id_categoria' => 13];
$resultado = $modelo->procesar(['accion' => 'registrar', 'nombre' => 'rin 13', 'id_padre' => 9]);
$afirmar(!$resultado['ok'], 'No pueden existir dos hermanas con el mismo nombre.');
$limpiar();
$db->filaPadre = $raizAutos;
$db->duplicado = null;
$resultado = $modelo->procesar(['accion' => 'registrar', 'nombre' => 'rin 13', 'id_padre' => 10]);
$afirmar($resultado['ok'], 'El mismo nombre en OTRO nivel sí es válido.');

// 7. Editar una raíz con hijos: la ruta nueva se arrastra a las subcategorías.
$limpiar();
$db->filaCompleta = $raizMotos;
$db->filaParentId = ['parent_id' => null];
$db->hijosDeRuta = [['id_categoria' => 13, 'ruta' => 'REPUESTOS PARA MOTOS/RIN 13']];
$resultado = $modelo->procesar(['accion' => 'editar', 'nombre' => 'repuestos de motos', 'id_padre' => 0, 'id_categoria' => 9]);
$afirmar($resultado['ok'], 'La edición de la raíz debe aceptarse.');
$actualizacion = $db->escrituras[0] ?? [];
$afirmar(($actualizacion['tipo'] ?? '') === 'update' && ($actualizacion['datos']['ruta'] ?? '') === 'REPUESTOS DE MOTOS', 'El renombre actualiza la ruta propia.');
$afirmar(!array_key_exists('codigo', $actualizacion['datos']), 'El código no cambia al editar (no va en la actualización).');
$arrastrado = $db->escrituras[1] ?? [];
$afirmar(strpos($arrastrado['params'][0] ?? '', 'REPUESTOS DE MOTOS/RIN 13') !== false, 'La ruta de la subcategoría se arrastra al renombrar el padre.');

// 8. Una raíz con subcategorías no puede volverse subcategoría.
$limpiar();
$db->filaPadre = $raizAutos;
$db->filaParentId = ['parent_id' => null];
$db->tieneHijos = ['id_categoria' => 13];
$resultado = $modelo->procesar(['accion' => 'editar', 'nombre' => 'repuestos para motos', 'id_padre' => 10, 'id_categoria' => 9]);
$afirmar(!$resultado['ok'], 'Una categoría con subcategorías no puede volverse subcategoría.');
$afirmar($db->escrituras === [], 'El guardia actúa antes de escribir.');

// 9. Autofiliación: rechazada.
$limpiar();
$db->filaPadre = $raizMotos;
$resultado = $modelo->procesar(['accion' => 'editar', 'nombre' => 'repuestos para motos', 'id_padre' => 9, 'id_categoria' => 9]);
$afirmar(!$resultado['ok'], 'Una categoría no puede ser su propia categoría padre.');

// 10. Cambio a mano con productos: prohibido.
$limpiar();
$db->filaToggle = $raizMotos;
$db->productosEnRama = 3;
$resultado = $modelo->toggleStatus(9);
$afirmar(!$resultado['ok'], 'El estado de una categoría con productos lo maneja el stock.');
$afirmar($db->escrituras === [], 'El rechazo del cambio a mano no debe escribir nada.');
$consultaToggle = $db->consultas[1]['sql'] ?? '';
$afirmar(
    !str_contains($consultaToggle, "p.status = 'Activo'"),
    'La existencia de productos Inactivos también impide ocultar manualmente la categoría.'
);

// 11. Cambio a mano sin productos: permitido (ocultar).
$limpiar();
$db->filaToggle = array_merge($raizMotos, ['productos_directos' => 0]);
$db->productosEnRama = 0;
$resultado = $modelo->toggleStatus(9);
$afirmar($resultado['ok'] && strpos($resultado['mensaje'], 'OCULTADA') !== false, 'Una categoría vacía puede ocultarse a mano.');
$estado = $db->escrituras[0] ?? [];
$afirmar(($estado['params'][0] ?? '') === 'Inactivo', 'Ocultar pone el estado en Inactivo.');

// 12. Estado automático por stock: subcategoría llena sin stock se apaga.
$limpiar();
$db->syncSubcategorias = [[
    'id_categoria' => 13, 'nombre' => 'RIN 13', 'status' => 'Activo', 'total' => 2, 'con_stock' => 0,
]];
$db->syncRaices = [$raizMotos]; // Rama con stock (total>0, con_stock>0) → sigue Activa
$modelo->sincronizarPorStock();
$consultasStock = array_values(array_filter(
    $db->consultas,
    static fn(array $consulta): bool => str_contains($consulta['sql'], 'FROM categorias c')
));
$afirmar(count($consultasStock) === 2, 'La sincronización consulta subcategorías y raíces.');
foreach ($consultasStock as $consultaStock) {
    $afirmar(
        !str_contains($consultaStock['sql'], "p.status = 'Activo'"),
        'La regla de stock debe considerar productos Activos e Inactivos.'
    );
}
$apagadas = array_filter($db->escrituras, static fn($escritura) => ($escritura['params'][0] ?? '') === 'Inactivo');
$afirmar(count($apagadas) === 1, 'Solo la subcategoría sin stock debe apagarse.');
$afirmar((int)($apagadas[0]['params'][1] ?? 0) === 13, 'La subcategoría apagada es RIN 13.');
$consultaRaizStock = array_values(array_filter(
    $db->consultas,
    static fn(array $consulta): bool => str_contains($consulta['sql'], 'WHERE c.parent_id IS NULL')
))[0]['sql'] ?? '';
$afirmar(
    str_contains($consultaRaizStock, 'h.id_categoria = c.id_categoria OR h.parent_id = c.id_categoria'),
    'El conteo de una raíz incluye productos directos y los de todas sus subcategorías.'
);

// 13. Con algún stock (aunque el producto esté Inactivo), la subcategoría se reactiva.
$limpiar();
$db->syncSubcategorias = [[
    'id_categoria' => 13, 'nombre' => 'RIN 13', 'status' => 'Inactivo', 'total' => 2, 'con_stock' => 1,
]];
$db->syncRaices = [[
    'id_categoria' => 14, 'nombre' => 'VACIA', 'status' => 'Activo', 'total' => 0, 'con_stock' => 0,
]];
$modelo->sincronizarPorStock();
$encendidas = array_filter($db->escrituras, static fn($escritura) => ($escritura['params'][0] ?? '') === 'Activo');
$afirmar(count($encendidas) === 1, 'Entrar mercancía reactiva la categoría sola.');
$afirmar((int)($encendidas[0]['params'][1] ?? 0) === 13, 'La reactivada es RIN 13.');
$limpiar();
$db->syncSubcategorias = [
    ['id_categoria' => 13, 'nombre' => 'RIN 13', 'status' => 'Inactivo', 'total' => 1, 'con_stock' => 0],
    ['id_categoria' => 14, 'nombre' => 'RIN 14', 'status' => 'Inactivo', 'total' => 1, 'con_stock' => 0],
    ['id_categoria' => 15, 'nombre' => 'RIN 15', 'status' => 'Inactivo', 'total' => 1, 'con_stock' => 0],
];
$db->syncRaices = [[
    'id_categoria' => 9, 'nombre' => 'CAUCHOS', 'status' => 'Activo', 'total' => 3, 'con_stock' => 0,
]];
$modelo->sincronizarPorStock();
$raizApagada = array_filter(
    $db->escrituras,
    static fn(array $escritura): bool => ($escritura['params'][0] ?? '') === 'Inactivo'
        && (int)($escritura['params'][1] ?? 0) === 9
);
$afirmar(count($raizApagada) === 1, 'La raíz se desactiva cuando todos los productos de todas sus subcategorías están en cero.');
$hay_auditoria = false;
foreach ($db->escrituras as $escritura) {
    if (strpos($escritura['sql'] ?? '', 'INSERT INTO auditoria') !== false) {
        $hay_auditoria = strpos($escritura['params'][2] ?? '', 'sincronizar_stock') !== false;
    }
}
$afirmar($hay_auditoria, 'La sincronización registra una sola entrada en la auditoría.');

// 14. Árbol: raíces con hijos anidados y conteos de la rama acumulados.
$limpiar();
$db->filasListado = [$raizMotos, $hijaRin13, $raizAutos];
$arbol = $modelo->listarArbol();
$afirmar(count($arbol) === 2, 'El árbol devuelve solo las raíces.');
$afirmar(count($arbol[0]['hijos']) === 1 && $arbol[0]['hijos'][0]['nombre'] === 'RIN 13', 'La raíz lleva sus subcategorías anidadas.');
$afirmar((int)$arbol[0]['productos_total'] === 4, 'El total de la rama suma directos + subcategorías (2 + 2).');
$afirmar((int)$arbol[0]['productos_con_stock'] === 1, 'El conteo con stock de la rama también se acumula.');
$afirmar((int)$arbol[1]['productos_total'] === 1, 'Una raíz sin hijos mantiene su propio total.');
$consultaListado = $db->consultas[0]['sql'] ?? '';
$afirmar(
    !str_contains($consultaListado, "p.status = 'Activo'"),
    'Los conteos visibles deben incluir todos los productos, incluso los Inactivos.'
);

echo 'OK categorias_jerarquia: ' . $afirmaciones . ' afirmaciones en verde (jerarquía, unicidad, estado por stock y árbol).';
