<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();

// ==========================================
// AJAX — Crear solicitud de compra (desde Ventas)
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'crear') {
    if (!(Security::puedeVender() || Security::esAdmin())) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'SIN PERMISOS PARA CREAR SOLICITUDES.']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => false, 'error' => 'MÉTODO NO PERMITIDO.']);
        exit;
    }

    $items_raw = json_decode($_POST['items'] ?? '[]', true);
    $items = is_array($items_raw) ? $items_raw : [];
    if (empty($items)) {
        echo json_encode(['ok' => false, 'error' => 'DEBE INDICAR AL MENOS UN PRODUCTO.']);
        exit;
    }

    $vistos = [];
    $detalles = [];
    foreach ($items as $it) {
        $id_producto = intval($it['id_producto'] ?? 0);
        $cantidad = intval($it['cantidad'] ?? 0);
        if ($id_producto <= 0 || $cantidad < 1 || $cantidad > 999999) continue;
        if (isset($vistos[$id_producto])) continue;
        $vistos[$id_producto] = true;
        $detalles[$id_producto] = $cantidad;
    }
    if (empty($detalles)) {
        echo json_encode(['ok' => false, 'error' => 'DEBE INDICAR CANTIDADES VÁLIDAS (1 A 999,999).']);
        exit;
    }

    $ids = array_keys($detalles);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $productos = $db->fetchAll("SELECT id_producto, nombre_producto FROM productos WHERE id_producto IN ($placeholders) AND status = 'Activo'", $ids);
    if (count($productos) !== count($ids)) {
        echo json_encode(['ok' => false, 'error' => 'ALGUNO DE LOS PRODUCTOS NO EXISTE O ESTÁ INACTIVO.']);
        exit;
    }

    // Evitar duplicados: el producto no debe estar en una solicitud Pendiente
    $dups = $db->fetchAll("SELECT d.id_producto FROM detalle_solicitud_compra d
                           JOIN solicitudes_compra s ON d.id_solicitud = s.id_solicitud
                           WHERE s.estado = 'Pendiente' AND d.id_producto IN ($placeholders)", $ids);
    if (!empty($dups)) {
        $nombres = [];
        $mapa = [];
        foreach ($productos as $p) { $mapa[(int)$p['id_producto']] = $p['nombre_producto']; }
        foreach ($dups as $d) { if (isset($mapa[(int)$d['id_producto']])) $nombres[] = $mapa[(int)$d['id_producto']]; }
        echo json_encode(['ok' => false, 'error' => 'YA HAY UNA SOLICITUD PENDIENTE PARA: ' . implode(', ', array_slice($nombres, 0, 3)) . (count($nombres) > 3 ? '...' : '')]);
        exit;
    }

    $motivo = trim($_POST['motivo'] ?? '');
    if ($motivo === '') $motivo = 'Venta sin stock';

    $id_usuario = intval($_SESSION['id_usuario'] ?? 0);

    $db->begin();
    try {
        $id_solicitud = $db->insert('solicitudes_compra', [
            'id_usuario_solicitante' => $id_usuario,
            'motivo'                 => substr($motivo, 0, 150),
            'estado'                 => 'Pendiente',
        ]);
        foreach ($detalles as $id_prod => $cant) {
            $db->insert('detalle_solicitud_compra', [
                'id_solicitud'        => $id_solicitud,
                'id_producto'         => $id_prod,
                'cantidad_solicitada' => $cant,
            ]);
        }
        registrarAuditoria('crear', "Solicitud de reposición #$id_solicitud (" . count($detalles) . " producto(s), $motivo)");
        $db->commit();
        echo json_encode(['ok' => true, 'id_solicitud' => $id_solicitud]);
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['ok' => false, 'error' => 'ERROR AL CREAR LA SOLICITUD. INTENTA DE NUEVO.']);
    }
    exit;
}

Security::verificarPermisoCarga();
$csrf_token = Security::generateToken();

// ==========================================
// PROCESAR ACCIONES POST
// ==========================================

// Cancelar solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cancelar_solicitud'])) {
    $id_solicitud = intval($_POST['id_solicitud'] ?? 0);
    $sol = $db->fetchOne("SELECT estado FROM solicitudes_compra WHERE id_solicitud = ?", [$id_solicitud]);
    if (!$sol || $sol['estado'] !== 'Pendiente') {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'LA SOLICITUD NO EXISTE O YA FUE PROCESADA.'];
        header('Location: solicitudes_compra.php');
        exit;
    }
    $db->execute("UPDATE solicitudes_compra SET estado = 'Cancelada' WHERE id_solicitud = ?", [$id_solicitud]);
    registrarAuditoria('anular', "Solicitud de reposición #$id_solicitud cancelada");
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'SOLICITUD CANCELADA.'];
    header('Location: solicitudes_compra.php');
    exit;
}

// ==========================================
// OBTENER DATOS
// ==========================================

$solicitudes = $db->fetchAll("
    SELECT s.id_solicitud, s.motivo, s.fecha_solicitud, s.estado, s.id_compra,
           u.usuario AS solicitante,
           COUNT(d.id_detalle) AS num_productos,
           COALESCE(SUM(d.cantidad_solicitada),0) AS total_unidades
    FROM solicitudes_compra s
    JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
    LEFT JOIN detalle_solicitud_compra d ON s.id_solicitud = d.id_solicitud
    WHERE s.estado = 'Pendiente'
    GROUP BY s.id_solicitud
    ORDER BY s.fecha_solicitud ASC, s.id_solicitud ASC
");

$historial = $db->fetchAll("
    SELECT s.id_solicitud, s.motivo, s.fecha_solicitud, s.estado, s.id_compra, s.fecha_atendida,
           u.usuario AS solicitante,
           COUNT(d.id_detalle) AS num_productos,
           COALESCE(SUM(d.cantidad_solicitada),0) AS total_unidades,
           c.nro_factura
    FROM solicitudes_compra s
    JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
    LEFT JOIN detalle_solicitud_compra d ON s.id_solicitud = d.id_solicitud
    LEFT JOIN compras c ON s.id_compra = c.id_compra
    WHERE s.estado != 'Pendiente'
    GROUP BY s.id_solicitud
    ORDER BY s.fecha_atendida DESC, s.id_solicitud DESC
    LIMIT 30
");

// KPIs para la cabecera
$kpi_pendientes = count($solicitudes);
$kpi_productos  = array_sum(array_map(fn($r) => (int)$r['num_productos'], $solicitudes));
$kpi_unidades   = array_sum(array_map(fn($r) => (int)$r['total_unidades'], $solicitudes));
$kpi_atendidas  = count(array_filter($historial, fn($r) => $r['estado'] === 'Atendida'));

$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Reposición | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>
    <link rel="stylesheet" href="../assets/modules/solicitudes_compra/solicitudes_compra.css?v=1">
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container-fluid px-4 py-4 pagina-solicitudes">

            <!-- Encabezado -->
            <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 header-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="module-header-icon">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    <div>
                        <h1 class="module-title">Solicitudes de Reposición</h1>
                        <p class="module-subtitle">Pedidos de reposición generados desde Ventas cuando no hay stock</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge-jv badge-primary" style="font-size:.75rem;padding:8px 16px;"><i class="bi bi-cart-check me-1"></i>SOLICITUDES DE REPOSICIÓN</span>
                </div>
            </div>

            <!-- Mensajes flash -->
            <?php if ($flash): ?>
                <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3 px-3 py-2">
                    <i class="bi bi-<?php echo $flash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($flash['texto']); ?>
                </div>
            <?php endif; ?>

            <!-- KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="widget-card">
                        <div class="widget-icon" style="background:rgba(99,102,241,0.12);color:#4F46E5;"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="widget-label">Pendientes</div>
                            <div class="widget-value"><?php echo $kpi_pendientes; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="widget-card">
                        <div class="widget-icon" style="background:rgba(59,130,246,0.12);color:#2563EB;"><i class="bi bi-box-seam"></i></div>
                        <div>
                            <div class="widget-label">Productos Solicitados</div>
                            <div class="widget-value"><?php echo $kpi_productos; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="widget-card">
                        <div class="widget-icon" style="background:rgba(14,165,233,0.12);color:#0284C7;"><i class="bi bi-stack"></i></div>
                        <div>
                            <div class="widget-label">Unidades a Reponer</div>
                            <div class="widget-value"><?php echo $kpi_unidades; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="widget-card">
                        <div class="widget-icon" style="background:rgba(22,163,74,0.12);color:#16A34A;"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <div class="widget-label">Atendidas</div>
                            <div class="widget-value"><?php echo $kpi_atendidas; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Solicitudes pendientes -->
            <div class="card-jv card-jv-table p-0 mb-4">
                <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper">
                    <i class="bi bi-hourglass-split me-1" style="font-size:1rem;"></i>
                    <span class="fw-bold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--jv-navy);">Pendientes de Atención</span>
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th style="width:7%;">#</th>
                                <th style="width:18%;">Motivo</th>
                                <th style="width:14%;">Solicitante</th>
                                <th class="text-center" style="width:10%;">Productos</th>
                                <th class="text-center" style="width:10%;">Unidades</th>
                                <th style="width:12%;">Fecha</th>
                                <th class="text-center" style="width:200px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($solicitudes) > 0): foreach ($solicitudes as $row): ?>
                                    <tr>
                                        <td><span class="codigo-badge">#<?php echo $row['id_solicitud']; ?></span></td>
                                        <td class="text-uppercase fw-bold"><?php echo htmlspecialchars($row['motivo'] ?? 'Solicitud de reposición'); ?></td>
                                        <td style="color:var(--jv-text-muted);"><?php echo htmlspecialchars($row['solicitante']); ?></td>
                                        <td class="text-center"><span class="cant-badge"><?php echo (int)$row['num_productos']; ?></span></td>
                                        <td class="text-center fw-bold"><?php echo (int)$row['total_unidades']; ?></td>
                                        <td class="fecha-cell"><?php echo date('d/m/Y H:i', strtotime($row['fecha_solicitud'])); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <a class="btn-atender" href="compras.php?atender_solicitud=<?php echo $row['id_solicitud']; ?>">
                                                    <i class="bi bi-check-lg me-1"></i>ATENDER
                                                </a>
                                                <button type="button" class="btn-cancelar" onclick="confirmarCancelar(<?php echo $row['id_solicitud']; ?>)" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="estado-vacio">
                                            <i class="bi bi-check2-circle"></i>
                                            <span>No hay solicitudes pendientes</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historial -->
            <div class="card-jv card-jv-table p-0">
                <div class="d-flex align-items-center gap-2 px-3 py-2 buscador-wrapper">
                    <i class="bi bi-clock-history me-1" style="font-size:1rem;"></i>
                    <span class="fw-bold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--jv-navy);">Historial (Atendidas / Canceladas)</span>
                </div>
                <div class="table-responsive">
                    <table class="table-jv mb-0">
                        <thead>
                            <tr>
                                <th style="width:7%;">#</th>
                                <th style="width:16%;">Motivo</th>
                                <th style="width:12%;">Solicitante</th>
                                <th class="text-center" style="width:9%;">Productos</th>
                                <th class="text-center" style="width:9%;">Unidades</th>
                                <th style="width:14%;">Fecha</th>
                                <th style="width:12%;">Factura</th>
                                <th class="text-center" style="width:11%;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($historial) > 0): foreach ($historial as $row): ?>
                                    <tr>
                                        <td><span class="codigo-badge">#<?php echo $row['id_solicitud']; ?></span></td>
                                        <td class="text-uppercase fw-bold"><?php echo htmlspecialchars($row['motivo'] ?? 'Solicitud de reposición'); ?></td>
                                        <td style="color:var(--jv-text-muted);"><?php echo htmlspecialchars($row['solicitante']); ?></td>
                                        <td class="text-center"><?php echo (int)$row['num_productos']; ?></td>
                                        <td class="text-center"><?php echo (int)$row['total_unidades']; ?></td>
                                        <td class="fecha-cell"><?php echo date('d/m/Y H:i', strtotime($row['fecha_solicitud'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['nro_factura'] ?: '-'); ?></td>
                                        <td class="text-center">
                                            <?php $est = $row['estado']; ?>
                                            <span class="badge-jv <?php echo $est === 'Atendida' ? 'badge-success' : 'badge-danger'; ?>"><i class="bi <?php echo $est === 'Atendida' ? 'bi-check-circle' : 'bi-x-circle'; ?> me-1"></i><?php echo $est; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="estado-vacio">
                                            <i class="bi bi-inbox"></i>
                                            <span>Aún no hay solicitudes procesadas</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        window.JV_CONFIG = { c0: '<?php echo $csrf_token; ?>' };
    </script>
    <script src="../assets/modules/solicitudes_compra/solicitudes_compra.js?v=2"></script>
</body>

</html>
