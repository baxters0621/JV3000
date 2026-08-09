<?php
// ==========================================
// ENDPOINT AJAX — BUSCADOR DE CLIENTES (TOOLBOX VENTAS)
// ==========================================
// Parámetros GET:
//   q                  texto libre (nombre o documento RIF/cédula)
//   id_cliente         búsqueda exacta de un cliente
//   incluir_inactivos  1 = incluye clientes Inactivo
//   limit              máx resultados (default 20, máx 100)

require_once __DIR__ . '/../../init.php';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$db = Database::getInstance();

$q = trim((string)($_GET['q'] ?? ''));
$id_cliente = (int)($_GET['id_cliente'] ?? 0);
$incluir_inactivos = !empty($_GET['incluir_inactivos']) ? 1 : 0;
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

$where = [];
$params = [];

if ($id_cliente > 0) {
    $where[] = 'id_cliente = ?';
    $params[] = $id_cliente;
} else {
    if ($q !== '') {
        $where[] = '(nombre LIKE ? OR documento LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if (!$incluir_inactivos) {
        $where[] = "status = 'Activo'";
    }
}

$sql = "SELECT id_cliente, nombre, documento, telefono, direccion, status FROM clientes";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY nombre ASC, id_cliente ASC LIMIT $limit";

$items = [];
foreach ($db->fetchAll($sql, $params) as $r) {
    $items[] = [
        'id' => (int)$r['id_cliente'],
        'codigo' => codigoCliente((int)$r['id_cliente']),
        'nombre' => (string)$r['nombre'],
        'documento' => (string)($r['documento'] ?? ''),
        'telefono' => (string)($r['telefono'] ?? ''),
        'direccion' => (string)($r['direccion'] ?? ''),
        'status' => (string)$r['status'],
    ];
}

echo json_encode(['success' => true, 'items' => $items]);
exit;
