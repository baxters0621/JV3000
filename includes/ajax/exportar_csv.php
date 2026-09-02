<?php
// ==========================================
// ENDPOINT: Exportar datos a CSV
// ==========================================
// Parámetros GET:
//   tabla    productos | compras | salidas | clientes | proveedores | historial
//   formato  csv (default)
//
// Descarga un archivo CSV con BOM UTF-8 para Excel.

require_once __DIR__ . '/../../init.php';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit;
}

$db = Database::getInstance();
$tabla = strtolower(trim($_GET['tabla'] ?? ''));

$queries = [
    'productos' => "
        SELECT p.sku, p.nombre_producto, c.nombre as categoria,
               p.precio_venta, p.precio_costo,
               p.stock_actual, p.stock_minimo, p.stock_maximo,
               p.fecha_vencimiento, p.status
        FROM productos p
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
        ORDER BY p.nombre_producto ASC",
    'compras' => "
        SELECT c.nro_factura, c.nro_control, pr.nombre_empresa as proveedor,
               c.subtotal, c.iva, c.total,
               c.status, c.status_pago, c.monto_pago, c.metodo_pago,
               c.fecha_compra
        FROM compras c
        LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
        ORDER BY c.fecha_compra DESC",
    'salidas' => "
        SELECT s.nro_factura_manual, s.nro_control, s.cliente, s.rif_cliente,
               tm.nombre as tipo, s.fecha_salida, s.status,
               SUM(ds.cantidad * ds.precio_venta) as total
        FROM salidas s
        JOIN tipos_movimientos tm ON s.id_tipo_mov = tm.id_tipo_mov
        JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
        WHERE s.status = 'Activa'
        GROUP BY s.id_salida
        ORDER BY s.fecha_salida DESC",
    'clientes' => "
        SELECT nombre, documento, telefono, direccion, status
        FROM clientes ORDER BY nombre ASC",
    'proveedores' => "
        SELECT rif, nombre_empresa, telefono, email, direccion,
               lead_time, moneda, status
        FROM proveedores ORDER BY nombre_empresa ASC",
    'historial' => "
        SELECT a.usuario_nombre, a.accion, a.detalle, a.fecha_hora,
               a.ip_origen, a.ruta, a.metodo
        FROM auditoria a
        ORDER BY a.fecha_hora DESC LIMIT 5000",
];

if (!isset($queries[$tabla])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Tabla no válida.']);
    exit;
}

$rows = $db->fetchAll($queries[$tabla]);

if (empty($rows)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No hay datos para exportar.']);
    exit;
}

// Generar CSV
$timestamp = date('Y-m-d_His');
$filename = "jv3000_{$tabla}_{$timestamp}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$fp = fopen('php://output', 'w');

// BOM UTF-8 para Excel
fwrite($fp, "\xEF\xBB\xBF");

// Encabezados
$headers = array_keys($rows[0]);
fputcsv($fp, $headers, ';');

// Datos
foreach ($rows as $row) {
    $clean = [];
    foreach ($row as $val) {
        $clean[] = is_string($val) ? trim($val) : $val;
    }
    fputcsv($fp, $clean, ';');
}

fclose($fp);
exit;
