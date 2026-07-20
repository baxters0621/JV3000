<?php
// ==========================================
// ENDPOINT AJAX DE ESTADÍSTICAS
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
header('Content-Type: application/json');

// Verificar permiso
$rol_ajax = $_SESSION['rol'] ?? '';
if ($rol_ajax !== 'Administrador' && $rol_ajax !== 'Operador de Ventas') {
    echo json_encode(['success' => false, 'error' => 'acceso_denegado']);
    exit();
}

// Agregados de 7 días
$ventas_7d_raw = (float)$db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * ds.precio_venta), 0) as total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa'")['total'];
$ventas_7d = number_format($ventas_7d_raw, 2);

$compras_7d = number_format($db->fetchOne("SELECT COALESCE(SUM(dc.cantidad * dc.precio_costo), 0) as total FROM compras c JOIN detalle_compras dc ON c.id_compra = dc.id_compra WHERE c.fecha_compra >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND c.status = 'Activa'")['total'], 2);

$margen_7d_raw = (float)$db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * (ds.precio_venta - p.precio_costo)), 0) as margen FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa'")['margen'];
$margen_7d = number_format($margen_7d_raw, 2);

$costo_vendido_7d = number_format($db->fetchOne("SELECT COALESCE(SUM(ds.cantidad * p.precio_costo), 0) as total FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa'")['total'], 2);

$porc_margen = ($ventas_7d_raw > 0) ? round(($margen_7d_raw / $ventas_7d_raw) * 100, 1) : 0;

$transacciones_7d = (int)$db->fetchOne("SELECT COUNT(DISTINCT nro_factura_manual) as total FROM salidas WHERE fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND nro_factura_manual IS NOT NULL AND id_tipo_mov = 1 AND status = 'Activa'")['total'];

// Top 5 productos por ganancia
$top_rows = $db->fetchAll("SELECT p.nombre_producto, SUM(ds.cantidad) as unidades, SUM(ds.cantidad * (ds.precio_venta - p.precio_costo)) as ganancia FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida JOIN productos p ON ds.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY ds.id_producto ORDER BY ganancia DESC LIMIT 5");
$sum_prof = 0;
foreach ($top_rows as $tp) {
    $sum_prof += (float)$tp['ganancia'];
}
$top_ganancia_ajax = [];
foreach ($top_rows as $tp) {
    $pct = ($sum_prof > 0) ? round(((float)$tp['ganancia'] / $sum_prof) * 100, 1) : 0;
    $top_ganancia_ajax[] = [
        'producto' => $tp['nombre_producto'],
        'unidades' => (int)$tp['unidades'],
        'ganancia' => number_format((float)$tp['ganancia'], 2),
        'pct' => $pct
    ];
}

// Respuesta JSON
echo json_encode([
    'success' => true,
    'ventas_7d' => $ventas_7d,
    'compras_7d' => $compras_7d,
    'margen_7d' => $margen_7d,
    'transacciones_7d' => $transacciones_7d,
    'costo_vendido_7d' => $costo_vendido_7d,
    'porc_margen' => $porc_margen,
    'top_ganancia' => $top_ganancia_ajax
]);
