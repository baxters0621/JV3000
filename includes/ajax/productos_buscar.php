<?php
// ==========================================
// ENDPOINT AJAX — BUSCADOR DE PRODUCTOS (TOOLBOX)
// ==========================================
// Parámetros GET:
//   q                 texto libre (sku, nombre, categoría o proveedor)
//   id_proveedor      filtro por proveedor
//   id_categoria      filtro por categoría (incluye subcategorías)
//   vencidos          1 = stock de lotes vencidos, 0 = vigentes (default)
//   solo_con_stock    1 = solo productos con stock > 0 en el modo elegido
//   incluir_inactivos 1 = incluye productos Inactivo
//   id_producto       búsqueda exacta de un producto
//   limit             máx resultados (default 20, máx 100)

require_once __DIR__ . '/../../init.php';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$db = Database::getInstance();

$q = trim((string)($_GET['q'] ?? ''));
$id_proveedor = (int)($_GET['id_proveedor'] ?? 0);
$id_categoria = (int)($_GET['id_categoria'] ?? 0);
$vencidos = !empty($_GET['vencidos']) ? 1 : 0;
$solo_con_stock = !empty($_GET['solo_con_stock']) ? 1 : 0;
$incluir_inactivos = !empty($_GET['incluir_inactivos']) ? 1 : 0;
$id_producto = (int)($_GET['id_producto'] ?? 0);
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

$where = [];
$params = [];

if ($id_producto > 0) {
    $where[] = 'p.id_producto = ?';
    $params[] = $id_producto;
} else {
    if ($q !== '') {
        $where[] = '(p.sku LIKE ? OR p.nombre_producto LIKE ? OR c.nombre LIKE ? OR EXISTS (SELECT 1 FROM detalle_compras dc JOIN compras co ON dc.id_compra = co.id_compra JOIN proveedores pr2 ON co.id_proveedor = pr2.id_proveedor WHERE dc.id_producto = p.id_producto AND pr2.nombre_empresa LIKE ?))';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($id_proveedor > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM detalle_compras dc JOIN compras co ON dc.id_compra = co.id_compra WHERE dc.id_producto = p.id_producto AND co.id_proveedor = ? AND co.status = \'Activa\')';
        $params[] = $id_proveedor;
    }
    if ($id_categoria > 0) {
        $cat = $db->fetchOne("SELECT ruta FROM categorias WHERE id_categoria = ?", [$id_categoria]);
        if ($cat && !empty($cat['ruta'])) {
            $where[] = '(c.ruta = ? OR c.ruta LIKE ?)';
            $params[] = $cat['ruta'];
            $params[] = $cat['ruta'] . '/%';
        } else {
            $where[] = 'p.id_categoria = ?';
            $params[] = $id_categoria;
        }
    }
    if (!$incluir_inactivos) {
        $where[] = "p.status = 'Activo'";
    }
}

$stock_expr = $vencidos
    ? "(CASE WHEN (SELECT COUNT(*) FROM lotes l0 WHERE l0.id_producto = p.id_producto AND l0.cantidad_restante > 0) = 0
            THEN (CASE WHEN p.fecha_vencimiento IS NOT NULL AND p.fecha_vencimiento <= CURDATE() THEN p.stock_actual ELSE 0 END)
            ELSE (SELECT COALESCE(SUM(l.cantidad_restante),0) FROM lotes l WHERE l.id_producto = p.id_producto AND l.cantidad_restante > 0 AND l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento <= CURDATE()) END)"
    : "(CASE WHEN (SELECT COUNT(*) FROM lotes l0 WHERE l0.id_producto = p.id_producto AND l0.cantidad_restante > 0) = 0
            THEN (CASE WHEN (p.fecha_vencimiento IS NULL OR p.fecha_vencimiento > CURDATE()) THEN p.stock_actual ELSE 0 END)
            ELSE (SELECT COALESCE(SUM(l.cantidad_restante),0) FROM lotes l WHERE l.id_producto = p.id_producto AND l.cantidad_restante > 0 AND (l.fecha_vencimiento IS NULL OR l.fecha_vencimiento > CURDATE())) END)";

$sql = "SELECT p.id_producto, p.sku, p.nombre_producto, p.precio_venta, p.precio_costo,
               p.stock_actual, p.status, p.id_categoria, c.nombre AS categoria,
               $stock_expr AS stock,
               (SELECT MIN(l.fecha_vencimiento) FROM lotes l WHERE l.id_producto = p.id_producto AND l.cantidad_restante > 0 AND l.fecha_vencimiento IS NOT NULL) AS proximo_vencimiento
        FROM productos p
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY p.nombre_producto ASC, p.id_producto ASC LIMIT $limit";

$items = [];
foreach ($db->fetchAll($sql, $params) as $r) {
    $stock = (int)$r['stock'];
    if ($solo_con_stock && $stock <= 0) continue;
    $items[] = [
        'id' => (int)$r['id_producto'],
        'sku' => (string)$r['sku'],
        'nombre' => (string)$r['nombre_producto'],
        'precio_venta' => (float)$r['precio_venta'],
        'precio_costo' => (float)$r['precio_costo'],
        'stock' => $stock,
        'stock_actual' => (int)$r['stock_actual'],
        'categoria' => (string)($r['categoria'] ?? ''),
        'id_categoria' => (int)$r['id_categoria'],
        'proximo_vencimiento' => $r['proximo_vencimiento'],
        'vencido' => $vencidos,
    ];
}

echo json_encode(['success' => true, 'items' => $items]);
exit;
