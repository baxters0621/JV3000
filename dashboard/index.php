<?php
require_once __DIR__ . '/../init.php';

// Cache-busting: el navegador revalida el JS propio con el mtime del archivo.
// Así el login siempre sirve la última versión sin mantener ?v= a mano.
$jv_cache_bust = static fn(string $ruta): string => is_file($ruta) ? '?v=' . filemtime($ruta) : '';

$db = Database::getInstance();
$nombre_user = $_SESSION['usuario'] ?? 'Usuario';
$rol_user_id = $_SESSION['id_rol'] ?? 0;
$rol_data = $db->fetchOne("SELECT nombre_rol FROM roles WHERE id_rol = ?", [$rol_user_id]);
$rol_user = $rol_data ? $rol_data['nombre_rol'] : 'Sin Rol';

$esAdmin = ($rol_user_id === 1);
$esOpVentas = ($rol_user_id === 3);
$esOpCarga = ($rol_user_id === 2);

$fecha_hoy = date('d/m/Y');

$alertas = jv_alertas_por_rol($rol_user_id);

// Saludo contextual según la hora local de Venezuela (formato 12h)
$hora_ve = (int) (new DateTime('now', new DateTimeZone('America/Caracas')))->format('G');
if ($hora_ve >= 5 && $hora_ve < 12) {
    $saludo = 'Buenos días';
} elseif ($hora_ve >= 12 && $hora_ve < 18) {
    $saludo = 'Buenas tardes';
} else {
    $saludo = 'Buenas noches';
}

// Iniciales del usuario para el avatar (primera letra del nombre y del último
// segmento si el nombre es compuesto con guion bajo, ej: Operador_Ventas → OV)
$iniciales = mb_strtoupper(mb_substr($nombre_user, 0, 1), 'UTF-8');
if (str_contains($nombre_user, '_')) {
    $partes = explode('_', $nombre_user);
    $iniciales .= mb_strtoupper(mb_substr($partes[count($partes) - 1], 0, 1), 'UTF-8');
}

// ==========================================
// CONSULTAS UNIFICADAS DEL DASHBOARD
// ==========================================
function obtenerDatosDashboard(Database $db): array
{
    $datos = [];

    // El KPI de ventas del panel debe reflejar la última venta registrada, no solo
    // las ventas del día actual; de ese modo la tarjeta y la tabla muestran un
    // mismo criterio de "reciente" incluso cuando la última nota no pertenece a hoy.
    $vd = $db->fetchOne("SELECT COALESCE(ds.cantidad * ds.precio_venta, 0) as total
        FROM salidas s
        JOIN detalle_salidas ds ON s.id_salida = ds.id_salida
        WHERE s.id_tipo_mov = 1 AND s.status = 'Activa'
        ORDER BY s.fecha_salida DESC, s.id_salida DESC
        LIMIT 1");
    $datos['ventas_dia'] = number_format((float)($vd['total'] ?? 0), 2);

    $vi = $db->fetchOne("SELECT COALESCE(SUM(CASE WHEN lotes.valor_lotes IS NULL THEN p.stock_actual * p.precio_costo ELSE lotes.valor_lotes END), 0) AS valor
        FROM productos p
        LEFT JOIN (
            SELECT id_producto, SUM(cantidad_restante * precio_costo) AS valor_lotes
            FROM lotes
            WHERE cantidad_restante > 0
            GROUP BY id_producto
        ) lotes ON lotes.id_producto = p.id_producto
        WHERE p.status = 'Activo'");
    $datos['valor_inventario'] = number_format((float)($vi['valor'] ?? 0), 2);

    $fac = $db->fetchAll("SELECT s.cliente, MAX(s.fecha_salida) as fecha_salida, SUM(ds.cantidad * ds.precio_venta) as total, s.nro_factura_manual FROM salidas s JOIN detalle_salidas ds ON s.id_salida = ds.id_salida WHERE s.id_tipo_mov = 1 AND s.status = 'Activa' GROUP BY s.id_salida, s.nro_factura_manual ORDER BY MAX(s.fecha_salida) DESC LIMIT 5");
    $datos['ultimas_facturas'] = array_map(fn($r) => ['cliente' => $r['cliente'] ?: 'S/N', 'fecha' => date('d/m/Y', strtotime($r['fecha_salida'])), 'total' => number_format($r['total'], 2)], $fac);

    $compras = $db->fetchAll("SELECT c.nro_factura, c.fecha_compra, c.total, COALESCE(pr.nombre_empresa, 'S/P') as proveedor FROM compras c LEFT JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor WHERE c.status = 'Activa' ORDER BY c.fecha_compra DESC LIMIT 5");
    $datos['tabla_compras'] = array_map(fn($r) => [
        'proveedor' => $r['proveedor'],
        'fecha' => date('d/m/Y', strtotime($r['fecha_compra'])),
        'total' => number_format($r['total'], 2)
    ], $compras);

    $crit = $db->fetchAll("SELECT nombre_producto, stock_actual, stock_minimo FROM productos WHERE (stock_actual <= stock_minimo OR stock_actual = 0) AND status = 'Activo' ORDER BY stock_actual ASC LIMIT 5");
    $datos['tabla_criticos'] = array_map(fn($r) => [
        'producto' => $r['nombre_producto'],
        'stock' => (int)$r['stock_actual'],
        'estado' => $r['stock_actual'] <= 0 ? 'critico' : 'bajo'
    ], $crit);

    return $datos;
}

// ==========================================
// ENDPOINT AJAX DEL DASHBOARD
// ==========================================
if (isset($_GET['ajax_dashboard'])) {
    header('Content-Type: application/json');
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        echo json_encode(['success' => false, 'error' => 'acceso_denegado']);
        exit;
    }
    echo json_encode(['success' => true] + obtenerDatosDashboard($db));
    exit();
}

// ==========================================
// CONSULTAS INICIALES DEL DASHBOARD
// ==========================================
$datos = obtenerDatosDashboard($db);
$ventas_dia = $datos['ventas_dia'];
$valor_inventario = $datos['valor_inventario'];
$ultimas_facturas = $datos['ultimas_facturas'];
$tabla_criticos = $datos['tabla_criticos'];
$tabla_compras = $datos['tabla_compras'];
$agruparActividadPorFecha = static function (array $items): array {
    $hoy = new DateTimeImmutable('today');
    $inicio_semana = $hoy->modify('monday this week');
    $grupos = [
        'hoy' => ['titulo' => 'HOY', 'items' => []],
        'semana' => ['titulo' => 'ESTA SEMANA', 'items' => []],
        'anteriores' => ['titulo' => 'ANTERIORES', 'items' => []],
    ];

    foreach ($items as $item) {
        $fecha_item = DateTimeImmutable::createFromFormat('!d/m/Y', (string)($item['fecha'] ?? ''));
        $grupo = 'anteriores';
        if ($fecha_item !== false) {
            if ($fecha_item->format('Y-m-d') === $hoy->format('Y-m-d')) {
                $grupo = 'hoy';
            } elseif ($fecha_item >= $inicio_semana && $fecha_item < $hoy) {
                $grupo = 'semana';
            }
        }
        $grupos[$grupo]['items'][] = $item;
    }

    return array_filter($grupos, static fn(array $grupo): bool => $grupo['items'] !== []);
};
?>
<!DOCTYPE html>
<html lang="es">

<?php // ==========================================
// HEAD Y ESTILOS HTML
// ========================================== 
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Inicio | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>

    <link rel="stylesheet" href="../assets/dashboard/index.css<?php echo $jv_cache_bust(__DIR__ . '/../assets/dashboard/index.css'); ?>">
</head>

<?php
// ==========================================
// LAYOUT DEL DASHBOARD
// ========================================== 
?>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4">

            <!-- ENCABEZADO: jerarquía en una banda — estado en la esquina
                 superior derecha, marca centrada y chip de ubicación centrado -->
            <header class="dash-hero">
                <div class="dash-hero-status">
                        <div class="dash-date">
                            <i class="bi bi-calendar3 me-2" aria-hidden="true"></i><?php echo $fecha_hoy; ?>
                            <span class="dash-date-sep" aria-hidden="true">&middot;</span>
                            <i class="bi bi-clock-fill me-1" aria-hidden="true"></i><span id="dash-clock">--:--</span>
                        </div>
                        <div class="dash-bell-wrap">
                            <button type="button" class="dash-bell" id="dashBellBtn" onclick="toggleAlertas(event)" title="Alertas críticas de stock" aria-label="Alertas críticas de stock" aria-expanded="false" aria-controls="dashBellPanel">
                                <i class="bi bi-bell" aria-hidden="true"></i>
                                <?php if ($alertas['total'] > 0): ?><span class="dash-bell-badge" id="dashBellBadge"><?php echo min($alertas['total'], 99); ?></span><?php endif; ?>
                            </button>
                            <?php include __DIR__ . '/../views/dashboard/_campana_alertas.php'; ?>
                        </div>
                        <div class="dash-user">
                            <div class="dash-user-avatar"><?php echo $iniciales; ?></div>
                            <div>
                                <div class="dash-user-greeting"><?php echo $saludo; ?></div>
                                <div class="dash-user-name"><?php echo htmlspecialchars($nombre_user); ?></div>
                                <div class="dash-user-role"><?php echo strtoupper($rol_user); ?></div>
                            </div>
                        </div>
                </div>
                <div class="dash-brand">
                    <img class="dash-logo-badge" src="../assets/img/logo-mark.svg?v=1" alt="JV3000">
                    <div class="dash-brand-meta">
                        <div class="dash-brand-title">JV<span class="num">3000</span> <span class="dash-brand-ca">C.A.</span></div>
                        <p class="dash-brand-tag">Centro de Gestión de Inventario, Compras y Ventas</p>
                    </div>
                </div>
                <div class="dash-here" aria-current="page">
                    <i class="bi bi-house-door-fill" aria-hidden="true"></i>
                    <span>Panel de Inicio</span>
                </div>
            </header>

            <!-- ACCESOS RÁPIDOS -->
            <section class="dash-section">
                <div class="quick-grid">
                    <?php if ($esAdmin || $esOpVentas): ?>
                        <a href="<?php echo BASE_PATH; ?>index.php?url=salidas" class="quick-btn quick-venta">
                            <span class="quick-icon"><i class="bi bi-cart-fill"></i></span>
                            <span class="quick-text">
                                <span class="quick-label">Nueva Venta</span>
                                <span class="quick-sub">Registrar una nota de Entrega</span>
                            </span>
                            <span class="quick-arrow"><i class="bi bi-arrow-right"></i></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($esAdmin || $esOpCarga): ?>
                        <a href="<?php echo BASE_PATH; ?>index.php?url=compras" class="quick-btn quick-entrada">
                            <span class="quick-icon"><i class="bi bi-box-arrow-in-down"></i></span>
                            <span class="quick-text">
                                <span class="quick-label">Nueva Entrada</span>
                                <span class="quick-sub">Registrar una compra</span>
                            </span>
                            <span class="quick-arrow"><i class="bi bi-arrow-right"></i></span>
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <!-- INDICADORES CLAVE -->
            <section class="dash-section">
                <h2 class="sec-title"><i class="bi bi-speedometer2"></i> Indicadores Clave</h2>
                <div class="kpi-grid">
                    <div class="kpi-card kpi-card-verde">
                        <div class="kpi-icon kpi-icon-verde"><i class="bi bi-currency-dollar"></i></div>
                        <div class="kpi-label">Última Venta</div>
                        <div class="kpi-value" id="kpi-ventas-dia">$<?php echo $ventas_dia; ?></div>
                    </div>
                    <div class="kpi-card kpi-card-teal">
                        <div class="kpi-icon kpi-icon-teal"><i class="bi bi-box-seam"></i></div>
                        <div class="kpi-label">Valor del Inventario</div>
                        <div class="kpi-value" id="kpi-valor-inv">$<?php echo $valor_inventario; ?></div>
                    </div>
                </div>
            </section>

            <!-- ACTIVIDAD RECIENTE -->
            <section class="dash-section">
                <h2 class="sec-title"><i class="bi bi-clock-history"></i> Actividad Reciente</h2>
                <div class="row g-4 bg-transparent">
                    <?php if ($esAdmin || $esOpVentas): ?>
                        <div class="col-12 col-xl-4">
                            <article class="card border-0 rounded-4 shadow-sm h-100 card-actividad dashboard-activity-card dashboard-activity-card-ventas">
                                <div class="card-header dashboard-activity-header">
                                    <h3 class="h5 mb-0 fw-bold"><span class="dashboard-activity-heading-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>Últimas Notas de Entrega</h3>
                                </div>
                                <div class="card-body">
                                    <div class="activity-list" id="lista-facturas">
                                        <?php if (!empty($ultimas_facturas)): ?>
                                            <?php foreach ($agruparActividadPorFecha($ultimas_facturas) as $grupo): ?>
                                                <section class="activity-date-group">
                                                    <h4 class="activity-date-heading"><?php echo $grupo['titulo']; ?></h4>
                                                    <?php foreach ($grupo['items'] as $f): ?>
                                                        <div class="activity-item activity-financial-item">
                                                            <span class="activity-status-dot activity-status-dot-venta" aria-hidden="true"></span>
                                                            <div class="activity-item-copy">
                                                                <div class="fs-6 fw-bold activity-item-name"><?php echo htmlspecialchars((string)$f['cliente'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <div class="activity-financial-date">Fecha: <?php echo htmlspecialchars((string)$f['fecha'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                            </div>
                                                            <div class="activity-total fs-5 fw-bold text-success">$<?php echo htmlspecialchars((string)$f['total'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </section>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="mb-0 text-secondary">Sin ventas registradas.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endif; ?>

                    <?php if ($esAdmin || $esOpCarga): ?>
                        <div class="col-12 col-xl-4">
                            <article class="card border-0 rounded-4 shadow-sm h-100 card-actividad dashboard-activity-card dashboard-activity-card-criticos">
                                <div class="card-header dashboard-activity-header">
                                    <h3 class="h5 mb-0 fw-bold"><span class="dashboard-activity-heading-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>Productos Críticos</h3>
                                </div>
                                <div class="card-body">
                                    <div class="activity-list" id="lista-criticos">
                                        <?php if (!empty($tabla_criticos)): ?>
                                            <?php foreach ($tabla_criticos as $c): ?>
                                                <?php $stock = max(0, (int)$c['stock']); ?>
                                                <div class="activity-item">
                                                    <span class="activity-status-dot <?php echo $stock === 0 ? 'activity-status-dot-stock-vacio' : 'activity-status-dot-critico'; ?>" aria-hidden="true"></span>
                                                    <div class="activity-item-copy">
                                                        <div class="fs-6 fw-bold activity-item-name"><?php echo htmlspecialchars((string)$c['producto'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                                            <span class="activity-stock-label">Stock actual: <strong class="<?php echo $stock === 0 ? 'text-danger' : 'text-secondary'; ?>"><?php echo $stock; ?></strong></span>
                                                            <span class="badge bg-danger"><?php echo $stock === 0 ? 'Agotado' : 'Crítico'; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="mb-0 text-secondary">Sin productos críticos.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div class="col-12 col-xl-4">
                            <article class="card border-0 rounded-4 shadow-sm h-100 card-actividad dashboard-activity-card dashboard-activity-card-compras">
                                <div class="card-header dashboard-activity-header">
                                    <h3 class="h5 mb-0 fw-bold"><span class="dashboard-activity-heading-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>Últimas Compras</h3>
                                </div>
                                <div class="card-body">
                                    <div class="activity-list" id="lista-compras">
                                        <?php if (!empty($tabla_compras)): ?>
                                            <?php foreach ($agruparActividadPorFecha($tabla_compras) as $grupo): ?>
                                                <section class="activity-date-group">
                                                    <h4 class="activity-date-heading"><?php echo $grupo['titulo']; ?></h4>
                                                    <?php foreach ($grupo['items'] as $co): ?>
                                                        <div class="activity-item activity-financial-item">
                                                            <span class="activity-status-dot activity-status-dot-compra" aria-hidden="true"></span>
                                                            <div class="activity-item-copy">
                                                                <div class="fs-6 fw-bold activity-item-name"><?php echo htmlspecialchars((string)$co['proveedor'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                                <div class="activity-financial-date">Fecha: <?php echo htmlspecialchars((string)$co['fecha'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                            </div>
                                                            <div class="activity-total fs-5 fw-bold text-success">$<?php echo htmlspecialchars((string)$co['total'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </section>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="mb-0 text-secondary">Sin compras registradas.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </div>

    <?php // ==========================================
    // JAVASCRIPT
    // ========================================== 
    ?>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/dashboard/index.js<?php echo $jv_cache_bust(__DIR__ . '/../assets/dashboard/index.js'); ?>"></script>
</body>

</html>