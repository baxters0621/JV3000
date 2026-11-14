<?php

// ==========================================
// PILOTO: alarma de stock y reactivación
// ==========================================
// Verificación estática del blindaje de stock: métodos del modelo,
// llamadas en ventas/recepción, flags de permisos, endpoint de descarte
// y presentación del modal (sin snooze). No toca la base de datos.

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
$dashboardIndex = file_get_contents($raiz . '/dashboard/index.php');
$alarmaJs = file_get_contents($raiz . '/assets/js/alarma_stock.js');
$notaJs = file_get_contents($raiz . '/assets/modules/nota_entrega/nota_entrega.js');
$notaVista = file_get_contents($raiz . '/views/nota_entrega/nota.php');
$dashboardJs = file_get_contents($raiz . '/assets/dashboard/index.js');
$campanaVista = file_get_contents($raiz . '/views/dashboard/_campana_alertas.php');
$comprasJs = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$productosVista = file_get_contents($raiz . '/views/productos/index.php');
$productosJs = file_get_contents($raiz . '/assets/modules/productos/productos.js');

// Modelo de producto
$afirmar(str_contains($producto, 'function sincronizarEstadoPorStock'), 'Producto debe sincronizar estado por stock.');
$afirmar(str_contains($producto, 'function reactivarPorStock'), 'Producto debe reactivar por stock.');
$afirmar(str_contains($producto, 'function descartar'), 'Producto debe permitir descartar.');
$afirmar(str_contains($producto, "'productos_descartados'"), 'El descarte debe guardarse en configuracion.');
$afirmar(str_contains($producto, 'desactivado_por_stock'), 'La desactivación automática debe auditarse.');
$afirmar(str_contains($producto, 'reactivado_por_stock'), 'La reactivación automática debe auditarse.');
$afirmar(str_contains($producto, "status = 'Inactivo'"), 'El producto agotado debe quedar Inactivo.');
$afirmar(str_contains($producto, "status = 'Activo'"), 'El producto con stock debe reactivarse.');
$afirmar(!str_contains($producto, 'proveedor_status'), 'La reactivación no debe depender del estado del proveedor.');
$afirmar(str_contains($producto, 'El proveedor de este producto está inactivo. Reactívalo para modificar los datos.'), 'La edición debe explicar que el proveedor está inactivo.');
$afirmar(str_contains($productosVista, 'id="avisoProveedorInactivo"'), 'El modal de edición debe mostrar el aviso de proveedor inactivo.');
$afirmar(str_contains($productosJs, 'function actualizarAvisoProveedorInactivo'), 'El JS debe alternar el aviso de proveedor inactivo.');

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
$afirmar(!str_contains($alarmaJs, 'jv_stock_snooze_v1'), 'El snooze debe haberse eliminado del modal.');
$afirmar(!str_contains($alarmaJs, 'localStorage'), 'El modal no debe silenciar alertas con localStorage.');
$afirmar(str_contains($alarmaJs, 'allowOutsideClick: true'), 'El modal no debe bloquear la navegación.');
$afirmar(str_contains($alarmaJs, 'allowEscapeKey: true'), 'El modal debe cerrarse con Escape.');
$afirmar(!str_contains($alarmaJs, 'posponer') && !str_contains($alarmaJs, 'Posponer'), 'No debe quedar la acción de posponer.');
$afirmar(!str_contains($alarmaJs, '[data-posponer]'), 'No debe quedar el botón de posponer.');
$afirmar(str_contains($alarmaJs, 'productos/descartar'), 'El modal debe llamar al endpoint de descarte.');
$afirmar(str_contains($alarmaJs, 'JVAlarmaStock'), 'El módulo debe exponerse globalmente.');
$afirmar(str_contains($alarmaJs, 'swal2-styled') && str_contains($alarmaJs, 'jv-alarma-reponer'), 'El modal debe usar botones nativos de SweetAlert2.');
$afirmar(str_contains($alarmaJs, 'ENTENDIDO'), 'El modal debe cerrar con ENTENDIDO.');

// Integraciones
$afirmar(str_contains($notaJs, 'JVAlarmaStock'), 'La nota de entrega debe mostrar la alarma al confirmar.');
$afirmar(str_contains($notaVista, 'alarma_stock.js'), 'La nota debe cargar el script de la alarma.');
$afirmar(str_contains($dashboardCtrl, 'alarma_stock.js'), 'El controlador del dashboard declara el script de la alarma.');
$afirmar(str_contains($dashboardIndex, 'alarma_stock.js'), 'El panel del dashboard debe cargar el script de la alarma.');

// Reposición en camino (compra registrada y pendiente de recepción)
$afirmar(str_contains($helpers, "'en_camino'"), 'Las alertas deben informar las unidades en camino.');
$afirmar(str_contains($helpers, "'id_compra'"), 'Las alertas deben indicar la compra pendiente de recepción.');
$afirmar(str_contains($alarmaJs, 'recepcion_compra'), 'El modal debe enlazar a la recepción si la compra está pendiente.');
$afirmar(str_contains($alarmaJs, "enCamino > 0 ? 'Recibir' : 'Reponer'"), 'El modal debe rotular Recibir cuando la reposición está en camino.');
$afirmar(str_contains($campanaVista, 'recepcion_compra') && str_contains($campanaVista, 'dash-bell-camino'), 'La campanita debe mostrar En camino y enlazar a la recepción.');
$afirmar(str_contains($dashboardJs, 'dash-bell-camino'), 'El refresco de la campanita debe conservar En camino.');
$afirmar(str_contains($comprasJs, 'recepcionDesdeUrl'), 'Compras debe abrir la recepción indicada por la URL.');

if ($fallos > 0) {
    fwrite(STDERR, "Piloto de alarma de stock: {$fallos} fallo(s) de {$total}.\n");
    exit(1);
}

echo "OK alarma_stock_piloto: {$total} afirmaciones en verde.\n";
exit(0);
