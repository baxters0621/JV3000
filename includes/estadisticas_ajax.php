<?php
// ==========================================
// ENDPOINT AJAX DE ESTADÍSTICAS
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
header('Content-Type: application/json');

// Verificar que sea una peticion AJAX legítima
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    echo json_encode(['success' => false, 'error' => 'acceso_denegado']);
    exit();
}

// Verificar permiso
$rol_ajax = (int)($_SESSION['id_rol'] ?? 0);
if ($rol_ajax !== 1 && $rol_ajax !== 3) {
    echo json_encode(['success' => false, 'error' => 'acceso_denegado']);
    exit();
}

require_once __DIR__ . '/estadisticas_logic.php';

$periodo = preg_match('/^(dia|semana|quincena|mes|trimestre|semestre|rango)$/', $_GET['periodo'] ?? '') ? $_GET['periodo'] : 'semana';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
if ($periodo === 'rango' && ($desde === '' || $hasta === '')) {
    $periodo = 'semana';
}

$d = jv_est_obtener_datos($db, $periodo, $desde, $hasta);

echo json_encode([
    'success'   => true,
    'periodo'   => $d['periodo'],
    'etiqueta'  => $d['etiqueta'],
    'mensaje'   => $d['mensaje'],
    'ventas'    => number_format($d['ventas'], 2),
    'compras'   => number_format($d['compras'], 2),
    'ganancia'  => number_format($d['ganancia'], 2),
    'pct_ventas'   => $d['pct_ventas'],
    'pct_compras'  => $d['pct_compras'],
    'pct_ganancia' => $d['pct_ganancia'],
    'labels'     => $d['labels_ventas'],
    'data_ventas' => $d['data_ventas'],
    'data_compras'=> $d['data_compras'],
    'topLabels'  => $d['top_labels'],
    'topCant'    => $d['top_cant'],
]);
