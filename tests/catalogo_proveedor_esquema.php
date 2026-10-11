<?php

/**
 * Regresión de contrato para el catálogo de productos por proveedor.
 *
 * Ejecutar con PHP CLI. Usa una base de datos simulada y lee el esquema v6;
 * no carga configuración local ni establece conexiones.
 */

$root = dirname(__DIR__);

final class Database
{
    private static ?self $instance = null;
    public string $lastQuery = '';

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $this->lastQuery = $sql;
        return [[
            'id_catalogo' => 7,
            'id_proveedor' => 3,
            'id_producto' => 11,
            'costo' => '2.50',
            'codigo_proveedor' => 'SKU-PROV',
            'nombre_producto' => 'Producto sintético',
            'sku' => 'SKU-11',
        ]];
    }
}

require_once $root . '/core/Model.php';
require_once $root . '/models/Proveedor.php';

function afirmarContrato(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$db = Database::getInstance();
$catalogo = (new Proveedor())->catalogoPorProveedor();
$consulta = $db->lastQuery;
$esquema = file_get_contents($root . '/db/jv3000_portable_v6.sql');
$vista = file_get_contents($root . '/views/compras/index.php');

afirmarContrato($esquema !== false && $vista !== false, 'No se pudieron leer los archivos del contrato.');
afirmarContrato(
    preg_match('/CREATE TABLE `productos` \((.*?)\) ENGINE=/s', $esquema, $coincidencia) === 1,
    'No se encontró la definición de productos en el esquema v6.'
);
afirmarContrato(
    !preg_match('/^\s*`descripcion`\s/m', $coincidencia[1]),
    'El esquema v6 ahora define descripcion en productos; revisar la expectativa de esta prueba.'
);
afirmarContrato(
    str_contains($consulta, 'p.nombre_producto')
        && str_contains($consulta, 'p.sku')
        && str_contains($consulta, 'cc.codigo_proveedor')
        && !preg_match('/\bp\.descripcion\b/i', $consulta),
    'La consulta debe conservar los campos soportados sin solicitar productos.descripcion.'
);
afirmarContrato(
    !str_contains($vista, "\$entrada_cat['descripcion']")
        && !str_contains($vista, 'cat-item-desc'),
    'La vista no debe presentar una descripción de producto no persistida.'
);
afirmarContrato(
    isset($catalogo[3][0])
        && $catalogo[3][0]['nombre_producto'] === 'Producto sintético'
        && $catalogo[3][0]['sku'] === 'SKU-11'
        && $catalogo[3][0]['costo'] === '2.50'
        && $catalogo[3][0]['codigo_proveedor'] === 'SKU-PROV',
    'El catálogo debe conservar su agrupación y los datos restantes del producto/proveedor.'
);

echo "OK: consulta, vista y contrato de catálogo compatibles con esquema v6; sin conexión a BD.\n";
