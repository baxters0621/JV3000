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
    ],
    'tabla_compras' => [
        ['proveedor' => 'Proveedor actual', 'fecha' => $hoy->format('d/m/Y'), 'total' => '40.00'],
    ],
    'csrf' => '',
    'css_extra' => [],
    'js_extra' => [],
]);
afirmarActividadDashboard(strpos($vista_renderizada, '<h4 class="activity-date-heading">HOY</h4>') !== false, 'La vista renderizada debe agrupar la actividad de hoy.');
afirmarActividadDashboard(strpos($vista_renderizada, 'Cliente de nombre muy largo que debe fluir sin cortes') !== false, 'La vista renderizada debe preservar nombres largos completos.');
afirmarActividadDashboard(strpos($vista_renderizada, 'Producto sin existencias') !== false && strpos($vista_renderizada, 'small text-danger fw-semibold">Stock: 0 unidades') !== false, 'La vista renderizada debe resaltar el stock cero en rojo.');
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
    afirmarActividadDashboard(strpos($actividad, 'class="row g-4"') !== false, 'Las tarjetas deben usar el grid Bootstrap con separación g-4.');
    afirmarActividadDashboard(substr_count($actividad, 'class="card border-light shadow-sm h-100 dashboard-activity-card dashboard-activity-card-') === 3, 'Actividad Reciente debe tener tres tarjetas claras.');
    afirmarActividadDashboard(substr_count($actividad, '<table') === 0, 'Las tarjetas no deben contener tablas.');
    afirmarActividadDashboard(strpos($actividad, 'text-truncate') === false && strpos($actividad, 'producto-tooltip') === false, 'Los nombres deben fluir sin truncamiento ni tooltip.');
    foreach (['lista-facturas', 'lista-criticos', 'lista-compras'] as $elemento) {
        afirmarActividadDashboard(strpos($actividad, $elemento) !== false, 'Falta el grupo o lista ' . $elemento . '.');
    }
    foreach (["'titulo' => 'HOY'", "'titulo' => 'ESTA SEMANA'", "'titulo' => 'ANTERIORES'"] as $grupo) {
        afirmarActividadDashboard(strpos($vista, $grupo) !== false, 'Falta la agrupación de fecha ' . $grupo . '.');
    }
    afirmarActividadDashboard(strpos($actividad, 'text-danger fw-semibold') !== false, 'El stock cero debe mostrarse en rojo.');
    foreach (['bi-receipt', 'bi-exclamation-triangle', 'bi-box-seam'] as $icono) {
        afirmarActividadDashboard(strpos($actividad, $icono) !== false, 'Falta el icono contextual ' . $icono . '.');
    }
}

afirmarActividadDashboard(strpos($css, '.activity-item-name') !== false && strpos($css, 'overflow-wrap: anywhere;') !== false, 'Los nombres largos deben fluir en varias líneas.');
foreach (['dashboard-activity-card-ventas', 'dashboard-activity-card-criticos', 'dashboard-activity-card-compras'] as $clase) {
    afirmarActividadDashboard(strpos($css, '.' . $clase) !== false, 'Falta el acento visual ' . $clase . '.');
}
afirmarActividadDashboard(
    strpos($css, 'border-top: 4px solid transparent;') !== false
    && strpos($css, 'linear-gradient(112deg, #ECFDF5') !== false
    && strpos($css, 'linear-gradient(112deg, #FFF7ED') !== false
    && strpos($css, 'linear-gradient(112deg, #EFF6FF') !== false,
    'Las cabeceras deben usar gradientes suaves y un borde superior por tema.'
);
afirmarActividadDashboard(
    strpos($css, 'transition: transform .2s ease, box-shadow .2s ease;') !== false
    && strpos($css, 'transform: translateY(-2px);') !== false,
    'Las tarjetas deben elevarse suavemente al pasar el puntero.'
);
afirmarActividadDashboard(
    strpos($css, '.activity-icon {') !== false
    && strpos($css, 'flex: 0 0 42px;') !== false
    && strpos($css, 'border-bottom: 1px solid var(--jv-border);') !== false,
    'Los ítems deben llevar avatares circulares y separadores discretos.'
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
afirmarActividadDashboard(strpos($javascript, 'producto-tooltip') === false && strpos($javascript, 'tabla-facturas') === false, 'El refresco no debe conservar referencias a tablas o tooltips antiguos.');

echo "OK tarjetas y agrupación de Actividad Reciente verificadas\n";
