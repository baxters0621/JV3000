<?php

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Model.php';
require_once __DIR__ . '/models/Categoria.php';
require_once __DIR__ . '/models/Proveedor.php';

/**
 * Genera un RIF de prueba con dígito verificador válido para tipo jurídico.
 *
 * @return string RIF con formato J-########-#.
 */
function jv_seed_generar_rif_prueba(): string
{
    $digitos = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    $pesos = [3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 3 * 4;
    foreach (str_split($digitos) as $indice => $digito) {
        $suma += (int)$digito * $pesos[$indice];
    }
    $verificador = 11 - ($suma % 11);
    if ($verificador >= 10) {
        $verificador = 0;
    }
    return 'J-' . $digitos . '-' . $verificador;
}

if (!Security::esAdmin()) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$host_local = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$host_local = preg_replace('/:\d+$/', '', $host_local);
if (!in_array($host_local, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || DB_NAME !== 'jv3000_db') {
    http_response_code(404);
    exit('Script disponible solo en el entorno local de pruebas.');
}

$confirmacion_requerida = 'GENERAR 5 CATEGORIAS, 10 PROVEEDORES Y 100 PRODUCTOS';
$mensaje = '';
$tipo_mensaje = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirmacion'] ?? '') !== $confirmacion_requerida) {
        http_response_code(400);
        $mensaje = 'Escriba la frase de confirmación exactamente para continuar.';
    } else {
        $db = Database::getInstance();
        $categoria_modelo = new Categoria();
        $proveedor_modelo = new Proveedor();
        $lote_seed = strtoupper(bin2hex(random_bytes(3)));

        try {
            $existentes = $db->fetchOne(
                "SELECT
                    (SELECT COUNT(*) FROM categorias WHERE descripcion LIKE 'seed_pruebas.php:%') AS categorias,
                    (SELECT COUNT(*) FROM proveedores WHERE nombre_empresa LIKE 'Proveedor Test %') AS proveedores,
                    (SELECT COUNT(*) FROM productos WHERE sku LIKE 'TEST-SEED-%') AS productos"
            );
            if (
                (int)($existentes['categorias'] ?? 0) > 0
                || (int)($existentes['proveedores'] ?? 0) > 0
                || (int)($existentes['productos'] ?? 0) > 0
            ) {
                throw new RuntimeException('La semilla de prueba ya existe. No se repitió la carga.');
            }

            $db->begin();
            $categorias = [];
            for ($indice = 1; $indice <= 5; $indice++) {
                $nombre = 'Categoria Test ' . str_pad((string)$indice, 2, '0', STR_PAD_LEFT);
                $resultado = $categoria_modelo->procesar([
                    'accion' => 'registrar',
                    'nombre' => $nombre,
                    'descripcion' => 'seed_pruebas.php:' . $lote_seed,
                    'clasificacion_abc' => 'C',
                    'tipo_manejo' => 'normal',
                    'id_padre' => 0,
                ]);
                if (!$resultado['ok']) {
                    throw new RuntimeException($resultado['mensaje']);
                }
                $fila = $db->fetchOne('SELECT id_categoria FROM categorias WHERE nombre = ? ORDER BY id_categoria DESC LIMIT 1', [$nombre]);
                if (!$fila) {
                    throw new RuntimeException('No se pudo recuperar una categoría recién creada.');
                }
                $categorias[] = (int)$fila['id_categoria'];
            }

            $proveedores = [];
            for ($indice = 1; $indice <= 10; $indice++) {
                $rif = jv_seed_generar_rif_prueba();
                $resultado = $proveedor_modelo->procesar([
                    'accion' => 'registrar',
                    'rif' => $rif,
                    'nombre_empresa' => 'Proveedor Test ' . str_pad((string)$indice, 2, '0', STR_PAD_LEFT),
                    'telefono_completo' => '+58-412-555' . str_pad((string)$indice, 4, '0', STR_PAD_LEFT),
                    'contacto_nombre' => 'Contacto de Prueba',
                    'email' => 'proveedor' . str_pad((string)$indice, 2, '0', STR_PAD_LEFT) . '@example.invalid',
                    'direccion' => 'Dirección temporal para pruebas',
                    'lead_time' => '3',
                    'moneda' => 'USD',
                    'status' => 'Activo',
                ]);
                if (!$resultado['ok']) {
                    throw new RuntimeException($resultado['mensaje']);
                }
                $fila = $db->fetchOne('SELECT id_proveedor FROM proveedores WHERE rif = ? LIMIT 1', [$rif]);
                if (!$fila) {
                    throw new RuntimeException('No se pudo recuperar un proveedor recién creado.');
                }
                $proveedores[] = (int)$fila['id_proveedor'];
            }

            $fecha_vencimiento = (new DateTimeImmutable('today'))
                ->modify('+' . random_int(180, 730) . ' days')
                ->format('Y-m-d');
            for ($indice = 1; $indice <= 100; $indice++) {
                $precio_venta = random_int(100, 10000) / 100;
                $precio_costo = random_int(100, (int)round($precio_venta * 100)) / 100;
                $id_categoria = $categorias[array_rand($categorias)];
                $id_proveedor = $proveedores[array_rand($proveedores)];
                $sku = 'TEST-SEED-' . $lote_seed . '-' . str_pad((string)$indice, 3, '0', STR_PAD_LEFT);
                $id_producto = $db->insert('productos', [
                    'sku' => $sku,
                    'nombre_producto' => 'Producto Test ' . $indice,
                    'descripcion' => 'Producto temporal generado por seed_pruebas.php.',
                    'precio_venta' => $precio_venta,
                    'precio_costo' => $precio_costo,
                    'stock_actual' => 50,
                    'stock_minimo' => 5,
                    'stock_maximo' => 100,
                    'fecha_vencimiento' => $fecha_vencimiento,
                    'status' => 'Activo',
                    'id_categoria' => $id_categoria,
                ]);
                $db->insert('lotes', [
                    'id_producto' => $id_producto,
                    'id_proveedor' => $id_proveedor,
                    'id_compra' => null,
                    'cantidad' => 50,
                    'cantidad_restante' => 50,
                    'precio_costo' => $precio_costo,
                    'fecha_vencimiento' => $fecha_vencimiento,
                ]);
                $db->insert('catalogo_costos', [
                    'id_proveedor' => $id_proveedor,
                    'id_producto' => $id_producto,
                    'costo' => $precio_costo,
                    'stock_disponible' => 50,
                    'codigo_proveedor' => 'PT-' . str_pad((string)$indice, 3, '0', STR_PAD_LEFT),
                ]);
            }

            $categoria_modelo->sincronizarPorStock();
            $db->commit();
            $tipo_mensaje = 'success';
            $mensaje = 'Carga completada: 5 categorías, 10 proveedores y 100 productos con 50 unidades en lotes iniciales. Código: ' . $lote_seed . '. No se borraron datos.';
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('[JV3000] Falló seed_pruebas.php: ' . $error->getMessage());
            http_response_code(500);
            $mensaje = 'No se completó la carga. La transacción fue revertida; revise el registro del servidor.';
        }
    }
}

$csrf = Security::generateToken();
$escape = static fn(string $texto): string => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Semilla temporal de pruebas</title>
</head>
<body>
    <main>
        <h1>Semilla temporal de pruebas</h1>
        <p>Solo localhost y la base local <code>jv3000_db</code>. No borra datos y no permite repetir una carga anterior.</p>
        <?php if ($mensaje !== ''): ?>
            <p role="status" style="color:<?php echo $tipo_mensaje === 'success' ? '#166534' : '#b91c1c'; ?>">
                <?php echo $escape($mensaje); ?>
            </p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrf); ?>">
            <label for="confirmacion">Para crear los datos, escriba: <strong><?php echo $escape($confirmacion_requerida); ?></strong></label>
            <input id="confirmacion" name="confirmacion" type="text" required autocomplete="off">
            <button type="submit">Crear datos de prueba</button>
        </form>
    </main>
</body>
</html>
