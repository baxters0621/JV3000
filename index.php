<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/init.php';

$db = Database::getInstance();
$nombre_user = $_SESSION['usuario'] ?? 'Usuario';
$rol_user = $_SESSION['rol'] ?? 'Operador';
$id_user = $_SESSION['id_usuario'] ?? 0;
$esAdmin = ($rol_user === 'Administrador');
$esOpVentas = ($rol_user === 'Operador de Ventas');
$esOpCarga = ($rol_user === 'Operador de Carga');

$fecha_hoy = date('d/m/Y');

// ==========================================
// ENDPOINT AJAX DEL DASHBOARD
// ==========================================
if (isset($_GET['ajax_dashboard'])) {
    header('Content-Type: application/json');
    $datos = [];

    $vd = $db->fetchOne("SELECT COALESCE(SUM(cantidad * precio_venta), 0) as total FROM salidas WHERE DATE(fecha_salida) = CURRENT_DATE AND id_tipo_mov = 1 AND status = 'Activa'");
    $datos['ventas_dia'] = number_format($vd['total'], 2);

    $vi = $db->fetchOne("SELECT COALESCE(SUM(stock_actual * precio_costo), 0) as valor FROM productos WHERE status = 'Activo'");
    $datos['valor_inventario'] = number_format($vi['valor'], 2);

    $pc = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE stock_actual <= stock_minimo AND status = 'Activo'");
    $datos['productos_criticos'] = (int)$pc['total'];

    $g1 = $db->fetchAll("SELECT DATE(fecha_salida) as fecha, SUM(cantidad * precio_venta) as total FROM salidas WHERE fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND id_tipo_mov = 1 AND status = 'Activa' GROUP BY DATE(fecha_salida) ORDER BY fecha");
    $datos['grafico_ventas'] = array_map(fn($r) => ['fecha' => $r['fecha'], 'total' => (float)$r['total']], $g1);

    $g2 = $db->fetchAll("SELECT p.id_producto, p.nombre_producto, SUM(s.cantidad) as cantidad FROM salidas s JOIN productos p ON s.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY p.id_producto ORDER BY cantidad DESC LIMIT 5");
    $paleta_idx = ['#38bdf8','#22c55e','#f59e0b','#ef4444','#a855f7','#f472b6','#34d399','#fbbf24','#a78bfa','#fb7185'];
    $datos['grafico_productos'] = array_map(fn($r) => ['producto' => $r['nombre_producto'], 'cantidad' => (int)$r['cantidad'], 'color' => $paleta_idx[$r['id_producto'] % count($paleta_idx)]], $g2);

    $fac = $db->fetchAll("SELECT cliente, MAX(fecha_salida) as fecha_salida, SUM(cantidad * precio_venta) as total, nro_factura_manual FROM salidas WHERE id_tipo_mov = 1 AND status = 'Activa' GROUP BY nro_factura_manual ORDER BY MAX(fecha_salida) DESC LIMIT 5");
    $datos['ultimas_facturas'] = array_map(fn($r) => ['cliente' => $r['cliente'] ?: 'S/N', 'fecha' => date('d/m/Y', strtotime($r['fecha_salida'])), 'total' => number_format($r['total'], 2)], $fac);

    // Productos próximos a vencer (≤15 días) y expirados
    $venc_now = $db->fetchAll("SELECT id_producto, nombre_producto, fecha_vencimiento, stock_actual FROM productos WHERE fecha_vencimiento <= CURRENT_DATE() AND status = 'Activo' ORDER BY fecha_vencimiento ASC LIMIT 8");
    $venc_pending = $db->fetchAll("SELECT id_producto, nombre_producto, fecha_vencimiento, stock_actual FROM productos WHERE fecha_vencimiento <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND fecha_vencimiento > CURRENT_DATE() AND status = 'Activo' ORDER BY fecha_vencimiento ASC LIMIT 5");
    
    $productos_vencer = array_map(fn($r) => [
        'id' => $r['id_producto'],
        'nombre' => $r['nombre_producto'],
        'fecha' => date('d/m/Y', strtotime($r['fecha_vencimiento'])),
        'dias' => floor((strtotime($r['fecha_vencimiento']) - time()) / 86400),
        'stock' => (int)$r['stock_actual']
    ], $venc_now);
    
    $productos_pronto = array_map(fn($r) => [
        'id' => $r['id_producto'],
        'nombre' => $r['nombre_producto'],
        'fecha' => date('d/m/Y', strtotime($r['fecha_vencimiento'])),
        'dias' => floor((strtotime($r['fecha_vencimiento']) - time()) / 86400),
        'stock' => (int)$r['stock_actual']
    ], $venc_pending);
    
    $datos['productos_vencer'] = $productos_vencer;
    $datos['productos_pronto'] = $productos_pronto;

    echo json_encode(['success' => true] + $datos);
    exit();
}

// ==========================================
// CONSULTAS INICIALES DEL DASHBOARD
// ==========================================
$vd = $db->fetchOne("SELECT COALESCE(SUM(cantidad * precio_venta), 0) as total FROM salidas WHERE DATE(fecha_salida) = CURRENT_DATE AND id_tipo_mov = 1 AND status = 'Activa'");
$ventas_dia = $vd['total'];

$vi = $db->fetchOne("SELECT COALESCE(SUM(stock_actual * precio_costo), 0) as valor FROM productos WHERE status = 'Activo'");
$valor_inventario = $vi['valor'];

$pc = $db->fetchOne("SELECT COUNT(*) as total FROM productos WHERE stock_actual <= stock_minimo AND status = 'Activo'");
$productos_criticos = (int)$pc['total'];

$gv = $db->fetchAll("SELECT DATE(fecha_salida) as fecha, SUM(cantidad * precio_venta) as total FROM salidas WHERE fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND id_tipo_mov = 1 AND status = 'Activa' GROUP BY DATE(fecha_salida) ORDER BY fecha");
$grafico_ventas = array_map(fn($r) => ['fecha' => $r['fecha'], 'total' => (float)$r['total']], $gv);

$gp = $db->fetchAll("SELECT p.id_producto, p.nombre_producto, SUM(s.cantidad) as cantidad FROM salidas s JOIN productos p ON s.id_producto = p.id_producto WHERE s.fecha_salida >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) AND s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY p.id_producto ORDER BY cantidad DESC LIMIT 5");
$paleta_idx = ['#38bdf8','#22c55e','#f59e0b','#ef4444','#a855f7','#f472b6','#34d399','#fbbf24','#a78bfa','#fb7185'];
$grafico_productos = array_map(fn($r) => ['producto' => $r['nombre_producto'], 'cantidad' => (int)$r['cantidad'], 'color' => $paleta_idx[$r['id_producto'] % count($paleta_idx)]], $gp);

$fac = $db->fetchAll("SELECT cliente, MAX(fecha_salida) as fecha_salida, SUM(cantidad * precio_venta) as total, nro_factura_manual FROM salidas WHERE id_tipo_mov = 1 AND status = 'Activa' GROUP BY nro_factura_manual ORDER BY MAX(fecha_salida) DESC LIMIT 5");
$ultimas_facturas = array_map(fn($r) => ['cliente' => $r['cliente'] ?: 'S/N', 'fecha' => date('d/m/Y', strtotime($r['fecha_salida'])), 'total' => number_format($r['total'], 2)], $fac);

$crit = $db->fetchAll("SELECT nombre_producto, stock_actual, stock_minimo FROM productos WHERE (stock_actual <= stock_minimo OR stock_actual = 0) AND status = 'Activo' ORDER BY stock_actual ASC LIMIT 5");
$tabla_criticos = array_map(fn($r) => ['producto' => $r['nombre_producto'], 'stock' => $r['stock_actual'], 'minimo' => $r['stock_minimo'], 'estado' => ($r['stock_actual'] == 0) ? 'critico' : 'bajo'], $crit);
?>
<!DOCTYPE html>
<html lang="es">

<?php // ==========================================
// HEAD Y ESTILOS HTML
// ========================================== ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | JV3000 C.A.</title>
    <?php include 'includes/diseno.php'; ?>
    <script src="assets/js/chart.umd.min.js"></script>

    <style>
        /* Widgets mejorados - Minimalista pero informativo */
        .widget-card {
            background: rgba(8, 12, 28, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(56, 189, 248, 0.1);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .widget-card:hover {
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateY(-2px);
        }

        .widget-label {
            font-size: 0.65rem;
            letter-spacing: 1.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .widget-value {
            font-size: 1.5rem;
            font-weight: 800;
        }

        .widget-sub {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        /* Grid de Módulos */
        .card-modulo {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.4), rgba(15, 23, 42, 0.8));
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px 20px;
            text-decoration: none !important;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .card-modulo::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, var(--hover-color) 0%, transparent 70%);
            opacity: 0;
            transition: 0.4s;
            z-index: 0;
        }

        .card-modulo:hover::before {
            opacity: 0.1;
        }

        .card-modulo:hover {
            transform: translateY(-8px);
            border-color: var(--hover-color);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.5), 0 0 15px -5px var(--hover-color);
        }

        .card-modulo i, .card-modulo span {
            position: relative;
            z-index: 1;
        }

        .card-modulo i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            transition: 0.3s;
        }

        .card-modulo:hover i {
            transform: scale(1.1);
            filter: drop-shadow(0 0 8px var(--hover-color));
        }

        .card-modulo span {
            font-family: 'Orbitron', sans-serif;
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.8);
        }

        .main-wrapper .container-fluid {
            max-width: 100%;
            padding-left: 30px;
            padding-right: 30px;
        }

        /* Badges */
        .role-badge {
            background: rgba(56, 189, 248, 0.1);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.3);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.65rem;
            letter-spacing: 1px;
            font-weight: 800;
        }

        .pulse-alert {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* Íconos de los widgets */
        .widget-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        /* ===== NUEVO DASHBOARD ===== */
        
        /* Header Dashboard */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 24px;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.9), rgba(8, 12, 28, 0.95));
            border: 1px solid rgba(56, 189, 248, 0.2);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }
        
        .dashboard-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .dashboard-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 50%, #22c55e 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Orbitron', sans-serif;
            font-size: 1.4rem;
            font-weight: 900;
            color: #fff;
            box-shadow: 0 6px 20px rgba(56, 189, 248, 0.5);
        }
        
        .dashboard-title h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.2rem;
            font-weight: 900;
            margin: 0;
            background: linear-gradient(90deg, #fff, #38bdf8);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(56, 189, 248, 0.5);
        }
        
        .dashboard-title .subtitle {
            color: rgba(56, 189, 248, 0.9);
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 4px;
            letter-spacing: 1px;
        }
        
        .dashboard-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .dashboard-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
        }
        
        .dashboard-user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #a855f7, #6366f1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
        }
        
        .dashboard-user-name {
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
        }
        
        .dashboard-user-role {
            color: rgba(168, 85, 247, 0.9);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .dashboard-date {
            background: linear-gradient(135deg, rgba(56, 189, 248, 0.2), rgba(56, 189, 248, 0.1));
            border: 1px solid rgba(56, 189, 248, 0.3);
            padding: 14px 24px;
            border-radius: 14px;
            color: #38bdf8;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 4px 15px rgba(56, 189, 248, 0.2);
        }

        /* Section Titles */
        .section-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #38bdf8;
        }

        /* KPIs Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.95));
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 28px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .kpi-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: var(--kpi-color);
            opacity: 1;
        }

        .kpi-card::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle at top right, var(--kpi-color), transparent);
            opacity: 0.15;
            pointer-events: none;
        }

        .kpi-card:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 20px 50px -15px var(--kpi-color), 0 0 0 1px var(--kpi-color);
            border-color: var(--kpi-color);
        }

        .kpi-card .kpi-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 18px;
            background: linear-gradient(135deg, rgba(var(--kpi-rgb), 0.25), rgba(var(--kpi-rgb), 0.1));
            color: var(--kpi-color);
            box-shadow: 0 6px 20px rgba(var(--kpi-rgb), 0.3);
        }

        .kpi-card .kpi-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .kpi-card .kpi-value {
            font-size: 2rem;
            font-weight: 900;
            color: #fff;
            font-family: 'Orbitron', sans-serif;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .kpi-card.kpi-verde { --kpi-color: #22c55e; --kpi-rgb: 34, 197, 94; }
        .kpi-card.kpi-cyan { --kpi-color: #38bdf8; --kpi-rgb: 56, 189, 248; }
        .kpi-card.kpi-amarillo { --kpi-color: #f59e0b; --kpi-rgb: 245, 158, 11; }
        .kpi-card.kpi-rojo { --kpi-color: #ef4444; --kpi-rgb: 239, 68, 68; }

        /* Accesos Rápidos */
        .shortcuts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .shortcut-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 28px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 800;
            text-transform: uppercase;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            color: #ffffff;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }

        .shortcut-btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.2) 0%, transparent 50%);
            pointer-events: none;
        }

        .shortcut-btn:hover {
            transform: translateY(-4px) scale(1.02);
            color: #ffffff;
        }

        .shortcut-btn i {
            font-size: 2.5rem;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.4));
            color: #ffffff;
        }

        .shortcut-btn span {
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: #ffffff;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }

        .shortcut-facturar {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            box-shadow: 0 12px 40px rgba(34, 197, 94, 0.5), 0 0 0 1px rgba(34, 197, 94, 0.3);
        }
        .shortcut-facturar:hover {
            box-shadow: 0 20px 50px rgba(34, 197, 94, 0.6), 0 0 0 2px rgba(34, 197, 94, 0.5);
        }

        .shortcut-entrada {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.5), 0 0 0 1px rgba(59, 130, 246, 0.3);
        }
        .shortcut-entrada:hover {
            box-shadow: 0 20px 50px rgba(59, 130, 246, 0.6), 0 0 0 2px rgba(59, 130, 246, 0.5);
        }

        /* Gráficos */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .chart-card h5 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chart-container {
            height: 240px;
            position: relative;
        }

        /* Tablas */
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .table-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 28px;
            max-height: 380px;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .table-card h5 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .data-table th {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 700;
            padding: 12px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: left;
        }

        .data-table td {
            padding: 14px 8px;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.8);
            vertical-align: middle;
        }
        .data-table tbody tr { transition: background 0.2s; }
        .data-table tbody tr:nth-child(odd) td { background: rgba(255,255,255,0.02); }
        .data-table tbody tr:nth-child(even) td { background: rgba(255,255,255,0.06); }
        .table-card:first-child .data-table tbody tr td:first-child { border-left: 3px solid rgba(6,182,212,0.3); padding-left: 12px; }
        .table-card:last-child .data-table tbody tr td:first-child { border-left: 3px solid rgba(239,68,68,0.3); padding-left: 12px; }
        .data-table td:nth-child(2),
        .data-table td:nth-child(3) { text-align: center; }
        .data-table th:nth-child(2),
        .data-table th:nth-child(3) { text-align: center; }
        .table-card:first-child .data-table td:last-child { text-align: right; }
        .table-card:first-child .data-table th:last-child { text-align: right; }

        .data-table tr:hover td {
            background: rgba(255, 255, 255, 0.03);
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .stock-badge.critico {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .stock-badge.bajo {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
            .tables-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .dashboard-header { flex-direction: column; gap: 16px; text-align: center; }
            .shortcuts-grid { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<?php // ==========================================
// LAYOUT DEL DASHBOARD
// ========================================== ?>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">
        
        <!-- Header Dashboard -->
        <div class="dashboard-header">
            <div class="dashboard-title">
                <div class="dashboard-logo">JV</div>
                <div>
                    <h1>3000</h1>
                    <div class="subtitle">Centro de Control | <?php echo htmlspecialchars($nombre_user); ?></div>
                </div>
            </div>
            <div class="dashboard-info">
                <div class="dashboard-user">
                    <div class="dashboard-user-avatar"><i class="bi bi-person-fill"></i></div>
                    <div>
                        <div class="dashboard-user-name"><?php echo htmlspecialchars($nombre_user); ?></div>
                        <div class="dashboard-user-role"><?php echo strtoupper($rol_user); ?></div>
                    </div>
                </div>
                <div class="dashboard-date">
                    <i class="bi bi-calendar3 me-2"></i><?php echo $fecha_hoy; ?>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="section-title">
            <i class="bi bi-speedometer2"></i> Indicadores Clave
        </div>
        <div class="kpi-grid">
            <div class="kpi-card kpi-verde">
                <div class="kpi-icon"><i class="bi bi-currency-dollar"></i></div>
                <div class="kpi-label">Ventas Hoy</div>
                <div class="kpi-value" id="kpi-ventas-dia">$<?php echo number_format($ventas_dia, 2); ?></div>
            </div>
            <div class="kpi-card kpi-amarillo">
                <div class="kpi-icon"><i class="bi bi-box-seam"></i></div>
                <div class="kpi-label">Valor Inventario</div>
                <div class="kpi-value" id="kpi-valor-inv">$<?php echo number_format($valor_inventario, 2); ?></div>
            </div>
            <div class="kpi-card kpi-rojo">
                <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="kpi-label">Productos Críticos</div>
                <div class="kpi-value" id="kpi-criticos"><?php echo $productos_criticos; ?></div>
            </div>
        </div>

        <!-- Accesos Rápidos -->
        <div class="section-title">
            <i class="bi bi-lightning-charge"></i> Accesos Rápidos
        </div>
        <div class="shortcuts-grid">
            <?php if ($esAdmin || $esOpVentas): ?>
            <a href="modules/salidas.php" class="shortcut-btn shortcut-facturar">
                <i class="bi bi-plus-circle"></i>
                <span>+ NUEVA VENTA</span>
            </a>
            <?php endif; ?>
            <?php if ($esAdmin || $esOpCarga): ?>
            <a href="modules/compras.php" class="shortcut-btn shortcut-entrada">
                <i class="bi bi-arrow-down-circle-fill"></i>
                <span>+ NUEVA ENTRADA</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- Gráficos -->
        <div class="section-title">
            <i class="bi bi-pie-chart"></i> Análisis de Tendencias
        </div>
        <div class="charts-grid">
            <div class="chart-card">
                <h5 class="text-white mb-3 fw-bold">Ventas - Últimos 7 Días</h5>
                <div class="chart-container">
                    <canvas id="chartVentas"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h5 class="text-white mb-3 fw-bold">Productos Más Vendidos</h5>
                <div class="chart-container">
                    <canvas id="chartProductos"></canvas>
                </div>
            </div>
        </div>

        <!-- Tablas de Actividad -->
        <div class="section-title">
            <i class="bi bi-clock-history"></i> Actividad Reciente
        </div>
        <div class="tables-grid">
            <div class="table-card">
                <h5 class="text-white mb-3 fw-bold">Últimas 5 Notas de Entrega</h5>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-facturas">
                        <?php foreach ($ultimas_facturas as $f): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['cliente']); ?></td>
                            <td style="color:#e2e8f0;font-weight:600;"><?php echo $f['fecha']; ?></td>
                            <td class="text-success fw-bold" style="text-align:right;">$<?php echo $f['total']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-card">
                <h5 class="text-white mb-3 fw-bold">Productos Críticos</h5>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Stock</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-criticos">
                        <?php foreach ($tabla_criticos as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['producto']); ?></td>
                            <td><?php echo $c['stock']; ?></td>
                            <td>
                                <span class="stock-badge <?php echo $c['estado']; ?>">
                                    <?php echo $c['estado'] === 'critico' ? 'Crítico' : 'Bajo'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>

<?php // ==========================================
// JAVASCRIPT
// ========================================== ?>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
        // Gráficos Chart.js
        const chartVentasCtx = document.getElementById('chartVentas').getContext('2d');
        const chartProductosCtx = document.getElementById('chartProductos').getContext('2d');

        // Datos iniciales para gráficos
        const datosVentas = <?php echo json_encode($grafico_ventas); ?>;
        const datosProductos = <?php echo json_encode($grafico_productos); ?>;

        let chartVentas = null;
        let chartProductos = null;

        function renderCharts(vData, pData) {
            if (chartVentas) chartVentas.destroy();
            if (chartProductos) chartProductos.destroy();

            chartVentas = new Chart(chartVentasCtx, {
                type: 'line',
                data: {
                    labels: vData.map(d => d.fecha.slice(5)),
                    datasets: [{
                        label: 'Ventas $',
                        data: vData.map(d => d.total),
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                    }
                }
            });

            chartProductos = new Chart(chartProductosCtx, {
                type: 'bar',
                data: {
                    labels: pData.map(d => d.producto.substring(0, 15)),
                    datasets: [{
                        label: 'Cantidad',
                        data: pData.map(d => d.cantidad),
                        backgroundColor: pData.map(d => d.color),
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                    }
                }
            });
        }

        renderCharts(datosVentas, datosProductos);

        // Actualización en tiempo real del dashboard
        function actualizarDashboard() {
            fetch('index.php?ajax_dashboard=1')
                .then(response => response.json())
                .then(data => {
                    try {
                        if (data.success) {
                            document.getElementById('kpi-ventas-dia').textContent = '$' + data.ventas_dia;
                            document.getElementById('kpi-valor-inv').textContent = '$' + data.valor_inventario;
                            document.getElementById('kpi-criticos').textContent = data.productos_criticos;

                            let htmlFacturas = '';
                            data.ultimas_facturas.forEach(f => {
                                htmlFacturas += `<tr><td>${f.cliente}</td><td>${f.fecha}</td><td style="text-align:right;color:#4ade80;font-weight:700;">$${f.total}</td></tr>`;
                            });
                            document.getElementById('tabla-facturas').innerHTML = htmlFacturas;

                            let htmlCriticos = '';
                            data.tabla_criticos.forEach(c => {
                                let badge = c.estado === 'critico' ? 'Crítico' : 'Bajo';
                                htmlCriticos += `<tr><td>${c.producto}</td><td>${c.stock}</td><td><span class="stock-badge ${c.estado}">${badge}</span></td></tr>`;
                            });
                            document.getElementById('tabla-criticos').innerHTML = htmlCriticos;

                            if (data.grafico_ventas) renderCharts(data.grafico_ventas, data.grafico_productos);
                        }
                    } catch(e) {
                        console.error('Dashboard refresh error:', e);
                    }
                })
                .catch(error => console.warn('Dashboard sync error:', error));
        }

        // Auto-actualización inteligente
        var intervaloDash = null;

        function iniciarDashboard() {
            if (intervaloDash) return;
            actualizarDashboard();
            intervaloDash = setInterval(actualizarDashboard, 45000);
        }

        function detenerDashboard() {
            if (intervaloDash) {
                clearInterval(intervaloDash);
                intervaloDash = null;
            }
        }

        // Solo actualiza si la pestaña está visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                detenerDashboard();
            } else {
                iniciarDashboard();
            }
        });

        // Detener al salir de la página
        window.addEventListener('beforeunload', detenerDashboard);

        iniciarDashboard();
    </script>
</body>

</html>