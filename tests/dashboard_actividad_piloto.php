<?php

/**
 * Verifica tarjetas, listas legibles y actualización AJAX de Actividad Reciente.
 */
$raiz = dirname(__DIR__);
$vistas = [
    file_get_contents($raiz . '/views/dashboard/index.php'),
    file_get_contents($raiz . '/dashboard/index.php'),
];
$css = file_get_contents($raiz . '/assets/dashboard/index.css');
$javascript = file_get_contents($raiz . '/assets/dashboard/index.js');
define('BASE_ASSETS', '/assets/');
define('BASE_PATH', '/');

function afirmarActividadDashboard(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function renderDashboardActividad(array $datos): string
{
    extract($datos, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/views/dashboard/index.php';
    return (string)ob_get_clean();
}

$hoy = new DateTimeImmutable('today');
$ayer = $hoy->modify('-1 day');
$fecha_anterior = $hoy->modify('-10 days');
$vista_renderizada = renderDashboardActividad([
    'titulo' => 'Panel piloto',
    'nombre_user' => 'Usuario_Piloto',
    'rol_user' => 'Administrador',
    'esAdmin' => true,
    'esOpVentas' => true,
    'esOpCarga' => true,
    'fecha_hoy' => date('d/m/Y'),
    'saludo' => 'Buenos días',
    'iniciales' => 'UP',
    'alertas' => ['total' => 0],
    'rol_user_id' => 1,
    'ventas_dia' => '0.00',
    'valor_inventario' => '0.00',
    'ultimas_facturas' => [
        ['cliente' => 'Cliente de nombre muy largo que debe fluir sin cortes', 'fecha' => $hoy->format('d/m/Y'), 'total' => '10.00'],
        ['cliente' => 'Cliente de ayer', 'fecha' => $ayer->format('d/m/Y'), 'total' => '20.00'],
        ['cliente' => 'Cliente anterior', 'fecha' => $fecha_anterior->format('d/m/Y'), 'total' => '30.00'],
    ],
    'tabla_criticos' => [
        ['producto' => 'Producto sin existencias', 'stock' => 0, 'estado' => 'critico'],
        ['producto' => 'Producto con stock bajo', 'stock' => 2, 'estado' => 'bajo'],
    ],
    'tabla_compras' => [
        ['proveedor' => 'Proveedor actual', 'fecha' => $hoy->format('d/m/Y'), 'total' => '40.00'],
    ],
    'csrf' => '',
    'css_extra' => [],
    'js_extra' => [],
]);
$vista_sin_stock = renderDashboardActividad([
    'titulo' => 'Panel piloto',
    'nombre_user' => 'Usuario_Piloto',
    'rol_user' => 'Administrador',
    'esAdmin' => true,
    'esOpVentas' => true,
    'esOpCarga' => true,
    'fecha_hoy' => date('d/m/Y'),
    'saludo' => 'Buenos días',
    'iniciales' => 'UP',
    'alertas' => ['total' => 0],
    'rol_user_id' => 1,
    'ventas_dia' => '0.00',
    'valor_inventario' => '0.00',
    'ultimas_facturas' => [],
    'tabla_criticos' => [
        ['producto' => 'Producto agotado', 'stock' => 0, 'estado' => 'critico'],
        ['producto' => 'Producto bajo', 'stock' => 2, 'estado' => 'bajo'],
    ],
    'tabla_compras' => [],
    'csrf' => '',
    'css_extra' => [],
    'js_extra' => [],
]);
afirmarActividadDashboard(strpos($vista_renderizada, '<h4 class="activity-date-heading">HOY</h4>') !== false, 'La vista renderizada debe agrupar la actividad de hoy.');
afirmarActividadDashboard(strpos($vista_renderizada, 'Cliente de nombre muy largo que debe fluir sin cortes') !== false, 'La vista renderizada debe preservar nombres largos completos.');
afirmarActividadDashboard(strpos($vista_renderizada, 'Producto sin existencias') !== false && strpos($vista_renderizada, 'Stock actual: <strong class="text-danger">0</strong>') !== false, 'La vista renderizada debe mostrar el stock actual cero en rojo y negrita.');
afirmarActividadDashboard(
    strpos($vista_renderizada, 'class="badge bg-danger">Agotado</span>') !== false
    && strpos($vista_renderizada, 'class="badge bg-danger">Crítico</span>') !== false
    && strpos($vista_renderizada, 'activity-stock-progress') === false
    && strpos($vista_renderizada, '<div class="progress') === false,
    'Los productos críticos deben usar badges y no barras de progreso.'
);
afirmarActividadDashboard(
    strpos($vista_renderizada, 'activity-financial-item') !== false
    && strpos($vista_renderizada, 'activity-financial-date">Fecha:') !== false
    && strpos($vista_renderizada, 'activity-total fs-5 fw-bold text-success">$10.00</div>') !== false
    && strpos($vista_renderizada, 'activity-total fs-5 fw-bold text-success">$40.00</div>') !== false,
    'Ventas y compras deben resaltar el monto a la derecha y mantener la fecha legible.'
);
afirmarActividadDashboard(
    strpos($vista_sin_stock, 'Stock actual: <strong class="text-danger">0</strong>') !== false
    && strpos($vista_sin_stock, 'class="badge bg-danger">Agotado</span>') !== false
    && strpos($vista_sin_stock, 'Stock actual: <strong class="text-secondary">2</strong>') !== false
    && strpos($vista_sin_stock, 'class="badge bg-danger">Crítico</span>') !== false
    && strpos($vista_sin_stock, '<div class="progress') === false,
    'La actividad crítica debe distinguir agotado/crítico sin barra de progreso.'
);
$ayer_es_semana = $ayer >= $hoy->modify('monday this week');
afirmarActividadDashboard(
    (strpos($vista_renderizada, '>ESTA SEMANA</h4>') !== false) === $ayer_es_semana,
    'La agrupación renderizada debe colocar ayer según el inicio de semana.'
);
afirmarActividadDashboard(strpos($vista_renderizada, '<h4 class="activity-date-heading">ANTERIORES</h4>') !== false, 'La vista renderizada debe agrupar elementos anteriores.');

foreach ($vistas as $indice => $vista) {
    $inicio = strpos($vista, '<!-- ACTIVIDAD RECIENTE -->');
    $fin = strrpos($vista, '</section>');
    $actividad = $inicio === false || $fin === false ? '' : substr($vista, $inicio, $fin - $inicio);
    afirmarActividadDashboard($actividad !== '', 'No se encontró Actividad Reciente en la vista ' . $indice . '.');
    afirmarActividadDashboard(strpos($actividad, 'class="row g-4 bg-transparent"') !== false, 'El grid debe flotar sobre un contenedor transparente.');
    afirmarActividadDashboard(substr_count($actividad, 'class="card border-0 rounded-4 shadow-sm h-100 card-actividad dashboard-activity-card dashboard-activity-card-') === 3, 'Actividad Reciente debe tener tres tarjetas flotantes y redondeadas.');
    afirmarActividadDashboard(substr_count($actividad, '<table') === 0, 'Las tarjetas no deben contener tablas.');
    afirmarActividadDashboard(strpos($actividad, 'text-truncate') === false && strpos($actividad, 'producto-tooltip') === false, 'Los nombres deben fluir sin truncamiento ni tooltip.');
    foreach (['lista-facturas', 'lista-criticos', 'lista-compras'] as $elemento) {
        afirmarActividadDashboard(strpos($actividad, $elemento) !== false, 'Falta el grupo o lista ' . $elemento . '.');
    }
    foreach (["'titulo' => 'HOY'", "'titulo' => 'ESTA SEMANA'", "'titulo' => 'ANTERIORES'"] as $grupo) {
        afirmarActividadDashboard(strpos($vista, $grupo) !== false, 'Falta la agrupación de fecha ' . $grupo . '.');
    }
    afirmarActividadDashboard(strpos($actividad, 'text-danger') !== false && strpos($actividad, 'badge bg-danger') !== false, 'El stock cero debe mostrar número rojo y badge de estado.');
    foreach (['bi-receipt', 'bi-exclamation-triangle', 'bi-box-seam'] as $icono) {
        afirmarActividadDashboard(strpos($actividad, $icono) !== false, 'Falta el icono contextual ' . $icono . '.');
    }
}

afirmarActividadDashboard(strpos($css, '.activity-item-name') !== false && strpos($css, 'overflow-wrap: anywhere;') !== false, 'Los nombres largos deben fluir en varias líneas.');
foreach (['dashboard-activity-card-ventas', 'dashboard-activity-card-criticos', 'dashboard-activity-card-compras'] as $clase) {
    afirmarActividadDashboard(strpos($css, '.' . $clase) !== false, 'Falta el acento visual ' . $clase . '.');
}
afirmarActividadDashboard(
    strpos($css, 'border-radius: 16px;') !== false
    && strpos($css, 'border: 0;') !== false
    && strpos($css, 'background: #E8F7EE;') !== false
    && strpos($css, 'background: #FFF3D6;') !== false
    && strpos($css, 'background: #E8F0FF;') !== false
    && strpos($css, 'border-radius: 50%;') !== false,
    'Las tarjetas deben ser redondeadas y las cabeceras pastel con iconos circulares.'
);
afirmarActividadDashboard(
    strpos($css, 'transition: transform .3s ease, box-shadow .3s ease;') !== false
    && strpos($css, 'transform: translateY(-5px);') !== false
    && strpos($css, 'box-shadow: 0 10px 20px rgba(0, 0, 0, .05) !important;') !== false,
    'Las tarjetas deben elevarse suavemente al pasar el puntero.'
);
afirmarActividadDashboard(
    strpos($css, '.activity-status-dot {') !== false
    && strpos($css, 'border-bottom: 1px solid var(--jv-border);') !== false
    && strpos($css, '.activity-financial-date {') !== false
    && strpos($css, 'font-size: .9rem;') !== false
    && strpos($css, '.activity-total {') !== false
    && strpos($css, 'font-variant-numeric: tabular-nums;') !== false
    && strpos($css, '.activity-stock-progress') === false,
    'Los ítems deben mostrar fecha legible, montos alineados y ninguna barra de progreso.'
);
afirmarActividadDashboard(strpos($css, '@media (prefers-reduced-motion: reduce)') !== false, 'La elevación debe respetar movimiento reducido.');
afirmarActividadDashboard(
    strpos($css, '.table-card-ventas') === false
    && strpos($css, '.table-card-criticos') === false
    && strpos($css, '.table-card-compras') === false,
    'Las tarjetas no deben conservar las bandas de color del diseño anterior.'
);
afirmarActividadDashboard(strpos($javascript, "lista-facturas") !== false && strpos($javascript, "lista-criticos") !== false && strpos($javascript, "lista-compras") !== false, 'El refresco AJAX debe actualizar las tres listas.');
afirmarActividadDashboard(strpos($javascript, 'renderActividadPorFecha') !== false, 'El refresco AJAX debe preservar la agrupación por fecha.');
afirmarActividadDashboard(
    strpos($javascript, 'activity-status-dot') !== false
    && strpos($javascript, 'activity-total fs-5 fw-bold text-success') !== false
    && strpos($javascript, 'activity-financial-date') !== false
    && strpos($javascript, 'Stock actual:') !== false
    && strpos($javascript, 'activity-stock-progress') === false
    && strpos($javascript, 'class="badge bg-danger"') !== false,
    'El refresco AJAX debe conservar montos, fechas y etiquetas de stock sin barras.'
);
afirmarActividadDashboard(strpos($javascript, 'producto-tooltip') === false && strpos($javascript, 'tabla-facturas') === false, 'El refresco no debe conservar referencias a tablas o tooltips antiguos.');

echo "OK tarjetas y agrupación de Actividad Reciente verificadas\n";
