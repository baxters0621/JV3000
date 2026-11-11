<?php

// ==========================================
// PILOTO: alarma de stock y reactivación
// ==========================================
// Verificación estática del blindaje de stock: métodos del modelo,
// llamadas en ventas/recepción, flags de permisos, endpoint de descarte
// y lógica de snooze del modal. No toca la base de datos.

$raiz = dirname(__DIR__);
$fallos = 0;
$total = 0;
$afirmar = static function (bool $condicion, string $mensaje) use (&$fallos, &$total): void {
    $total++;
    if (!$condicion) {
        $fallos++;
        fwrite(STDERR, "FALLO: {$mensaje}\n");
    }
};

$producto = file_get_contents($raiz . '/models/Producto.php');
$salida = file_get_contents($raiz . '/models/Salida.php');
$recepcion = file_get_contents($raiz . '/models/Recepcion.php');
$helpers = file_get_contents($raiz . '/includes/helpers.php');
$salidasCtrl = file_get_contents($raiz . '/controllers/SalidasController.php');
$productosCtrl = file_get_contents($raiz . '/controllers/ProductosController.php');
$dashboardCtrl = file_get_contents($raiz . '/controllers/DashboardController.php');
$alarmaJs = file_get_contents($raiz . '/assets/js/alarma_stock.js');
$notaJs = file_get_contents($raiz . '/assets/modules/nota_entrega/nota_entrega.js');
$notaVista = file_get_contents($raiz . '/views/nota_entrega/nota.php');

// Modelo de producto
$afirmar(str_contains($producto, 'function sincronizarEstadoPorStock'), 'Producto debe sincronizar estado por stock.');
$afirmar(str_contains($producto, 'function reactivarPorStock'), 'Producto debe reactivar por stock.');
$afirmar(str_contains($producto, 'function descartar'), 'Producto debe permitir descartar.');
$afirmar(str_contains($producto, "'productos_descartados'"), 'El descarte debe guardarse en configuracion.');
$afirmar(str_contains($producto, 'desactivado_por_stock'), 'La desactivación automática debe auditarse.');
$afirmar(str_contains($producto, 'reactivado_por_stock'), 'La reactivación automática debe auditarse.');
$afirmar(str_contains($producto, "status = 'Inactivo'"), 'El producto agotado debe quedar Inactivo.');
$afirmar(str_contains($producto, "status = 'Activo'"), 'El producto con stock debe reactivarse.');

// Ventas y recepción
$afirmar(str_contains($salida, 'sincronizarEstadoPorStock'), 'La venta debe desactivar el producto agotado.');
$afirmar(substr_count($salida, 'reactivarPorStock') >= 2, 'La anulación y la edición deben evaluar la reactivación.');
$afirmar(str_contains($recepcion, 'reactivarPorStock'), 'La recepción debe reactivar el producto.');

// Alertas y permisos
$afirmar(str_contains($helpers, 'puede_descartar'), 'Las alertas deben exponer puede_descartar.');
$afirmar(str_contains($helpers, "stock_actual <= 0"), 'Las alertas deben incluir agotados inactivos.');
$afirmar(str_contains($helpers, 'productos_descartados'), 'Las alertas deben excluir descartados.');
$afirmar(str_contains($salidasCtrl, "'alertas_stock'"), 'La confirmación de venta debe devolver alertas_stock.');

// Endpoint de descarte
$afirmar(str_contains($productosCtrl, 'function descartar'), 'ProductosController debe exponer descartar().');
$afirmar(str_contains($productosCtrl, "postInt('id_producto'"), 'El descarte debe parsear el id de forma estricta.');
$afirmar(str_contains($productosCtrl, 'Security::esAdmin'), 'El descarte debe exigir administrador.');

// JS del modal
$afirmar(str_contains($alarmaJs, 'jv_stock_snooze_v1'), 'El snooze debe usar localStorage versionado.');
$afirmar(str_contains($alarmaJs, 'allowOutsideClick: true'), 'El modal no debe bloquear la navegación.');
$afirmar(str_contains($alarmaJs, 'allowEscapeKey: true'), 'El modal debe cerrarse con Escape.');
$afirmar(str_contains($alarmaJs, 'posponer'), 'Debe existir la acción de posponer.');
$afirmar(str_contains($alarmaJs, 'productos/descartar'), 'El modal debe llamar al endpoint de descarte.');
$afirmar(str_contains($alarmaJs, '2 * 60 * 60 * 1000'), 'El snooze debe durar 2 horas.');
$afirmar(str_contains($alarmaJs, 'JVAlarmaStock'), 'El módulo debe exponerse globalmente.');

// Integraciones
$afirmar(str_contains($notaJs, 'JVAlarmaStock'), 'La nota de entrega debe mostrar la alarma al confirmar.');
$afirmar(str_contains($notaVista, 'alarma_stock.js'), 'La nota debe cargar el script de la alarma.');
$afirmar(str_contains($dashboardCtrl, 'alarma_stock.js'), 'El dashboard debe cargar el script de la alarma.');

if ($fallos > 0) {
    fwrite(STDERR, "Piloto de alarma de stock: {$fallos} fallo(s) de {$total}.\n");
    exit(1);
}

echo "OK alarma_stock_piloto: {$total} afirmaciones en verde.\n";
exit(0);
