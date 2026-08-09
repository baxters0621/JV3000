<?php
// ==========================================
// ENDPOINT AJAX — ALERTAS CRÍTICAS DE STOCK
// ==========================================
require_once __DIR__ . '/../../init.php';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => true] + jv_alertas_por_rol());
exit;
