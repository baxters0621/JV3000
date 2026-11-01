<?php

/**
 * Verifica que cambios de ventas/compras refresquen KPIs y Actividad Reciente.
 */
$raiz = dirname(__DIR__);
$salidas_controller = file_get_contents($raiz . '/controllers/SalidasController.php');
$compras_controller = file_get_contents($raiz . '/controllers/ComprasController.php');
$dashboard_controller = file_get_contents($raiz . '/controllers/DashboardController.php');
$dashboard_legacy = file_get_contents($raiz . '/dashboard/index.php');
$salida_model = file_get_contents($raiz . '/models/Salida.php');
$compra_model = file_get_contents($raiz . '/models/Compra.php');
$salidas_view = file_get_contents($raiz . '/views/salidas/index.php');
$compras_view = file_get_contents($raiz . '/views/compras/index.php');
$salidas_js = file_get_contents($raiz . '/assets/modules/salidas/salidas.js');
$compras_js = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$dashboard_js = file_get_contents($raiz . '/assets/dashboard/index.js');
$diseno_js = file_get_contents($raiz . '/assets/js/diseno.js');

function afirmarSincronizacionKpi(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

foreach ([
    [$salidas_controller, "'salida_anular'", 'Anular ventas debe quedar marcado como actualización confirmada.'],
    [$salidas_controller, "'salida_editar' : 'salida_crear'", 'Guardar o editar ventas debe marcar la operación confirmada.'],
    [$compras_controller, "'compra_recepcion'", 'La recepción debe notificar cambios de inventario.'],
    [$compras_controller, "'compra_pago'", 'Registrar pagos debe notificar la actualización del KPI por pagar.'],
    [$compras_controller, "'compra_crear'", 'Guardar compras debe notificar la actualización de sus KPIs.'],
    [$compras_controller, "'compra_anular'", 'Anular compras debe notificar la actualización de sus KPIs.'],
] as [$contenido, $texto, $mensaje]) {
    afirmarSincronizacionKpi(strpos($contenido, $texto) !== false, $mensaje);
}

afirmarSincronizacionKpi(
    strpos($salidas_controller, "'kpis'          => \$modelo->kpis()") !== false
    && strpos($compras_controller, "'kpis'                => \$modelo->kpis()") !== false
    && strpos($salida_model, "WHERE s.status = 'Activa'") !== false
    && strpos($compra_model, "WHERE status = 'Activa'") !== false,
    'Los KPIs de módulos deben consultar el estado activo después de cada redirección.'
);
afirmarSincronizacionKpi(
    strpos($dashboard_controller, '$datos = $this->obtenerDatosDashboard($db);') !== false
    && strpos($dashboard_controller, '$this->json([\'success\' => true] + $datos)') !== false
    && strpos($dashboard_controller, "WHERE s.id_tipo_mov = 1 AND s.status = 'Activa'") !== false
    && strpos($dashboard_legacy, "WHERE s.id_tipo_mov = 1 AND s.status = 'Activa'") !== false,
    'El endpoint debe recalcular los indicadores y excluir ventas anuladas del feed.'
);

foreach ([
    [$salidas_view, "'salida_anular', 'salida_editar', 'salida_crear'"],
    [$compras_view, "'compra_crear', 'compra_anular', 'compra_pago', 'compra_recepcion'"],
] as [$vista, $origenes]) {
    afirmarSincronizacionKpi(
        strpos($vista, "data-actualiza-dashboard=\"1\"") !== false
        && strpos($vista, $origenes) !== false,
        'La vista solo debe emitir la señal tras una operación transaccional exitosa.'
    );
}
afirmarSincronizacionKpi(
    strpos($salidas_js, "document.querySelector('[data-actualiza-dashboard=\"1\"]')") !== false
    && strpos($salidas_js, 'jvNotificarActualizacionDashboard();') !== false
    && strpos($compras_js, "document.querySelector('[data-actualiza-dashboard=\"1\"]')") !== false
    && strpos($compras_js, 'jvNotificarActualizacionDashboard();') !== false,
    'Ventas y Compras deben publicar la señal al cargar el resultado confirmado.'
);
afirmarSincronizacionKpi(
    strpos($diseno_js, "localStorage.setItem('jv3000_dashboard_actualizado'") !== false
    && strpos($dashboard_js, "evento.key === 'jv3000_dashboard_actualizado'") !== false
    && strpos($dashboard_js, 'actualizarDashboard();') !== false,
    'La señal debe refrescar inmediatamente el dashboard abierto en otra pestaña.'
);

echo "OK sincronización de KPIs y Actividad Reciente verificada\n";
