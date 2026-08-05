<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::soloAdmin();

$csrf_token = Security::generateToken();

// ==========================================
// FILTROS
// ==========================================
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$filtro_desde = $_GET['desde'] ?? '';
$filtro_hasta = $_GET['hasta'] ?? '';

$where = [];
$params = [];

$where[] = "a.accion IN ('crear','editar','eliminar','anular')";

if ($filtro_usuario !== '') {
    $where[] = "a.usuario_nombre LIKE ?";
    $params[] = '%' . $filtro_usuario . '%';
}
if ($filtro_accion !== '') {
    $where[] = "a.accion = ?";
    $params[] = $filtro_accion;
}
if ($filtro_desde !== '') {
    $where[] = "a.fecha_hora >= ?";
    $params[] = $filtro_desde . ' 00:00:00';
}
if ($filtro_hasta !== '') {
    $where[] = "a.fecha_hora <= ?";
    $params[] = $filtro_hasta . ' 23:59:59';
}

$sql_where = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$query_string = http_build_query(array_filter([
    'usuario' => $filtro_usuario,
    'accion'  => $filtro_accion,
    'desde'   => $filtro_desde,
    'hasta'   => $filtro_hasta,
], fn($v) => $v !== ''));
if ($query_string !== '') {
    $query_string = '&' . $query_string;
}

// ==========================================
// PAGINACIÓN
// ==========================================
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$total_registros = (int)$db->fetchOne("SELECT COUNT(*) as total FROM auditoria a $sql_where", $params)['total'];
$total_paginas = max(1, ceil($total_registros / $limit));

$registros = $db->fetchAll("SELECT a.* FROM auditoria a $sql_where ORDER BY a.fecha_hora DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

$acciones_disponibles = ['crear', 'editar', 'eliminar', 'anular'];
$accion_nombres = ['crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'anular' => 'Anular'];

// ==========================================
// LIMPIAR HISTORIAL (eliminado: historial es inmutable por política)
// ==========================================
$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>
    <!-- ESTILOS -->
        <link rel="stylesheet" href="../assets/modules/historial/historial.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-wrapper" id="mainWrapper">
<div class="container-fluid px-4 py-4 pagina-aud">

    <!-- MENSAJES FLASH -->
    <?php if ($flash): ?>
        <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?>" style="padding:12px 18px;font-size:.85rem;font-weight:600;">
            <?php echo htmlspecialchars($flash['texto']); ?>
        </div>
    <?php endif; ?>

    <!-- ENCABEZADO -->
    <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" style="padding:18px 24px;border-left:4px solid var(--jv-orange);">
        <div class="d-flex align-items-center gap-3">
            <div class="aud-header-icon"><i class="bi bi-shield-check"></i></div>
            <div>
                <h1 class="font-brand fw-bold m-0" style="font-size:1.4rem; color: var(--jv-text-primary);">HISTORIAL</h1>
                <p class="m-0 text-secondary" style="font-size:.85rem;">Registro de Actividades del Sistema</p>
            </div>
        </div>
        <span class="text-jv-muted small fw-bold"><?php echo $total_registros; ?> registro(s)</span>
    </div>

    <!-- FORMULARIO DE FILTROS -->
    <form class="filtro-box" method="GET">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-secondary mb-1">USUARIO</label>
                <input type="text" name="usuario" class="input-jv" placeholder="Buscar..." value="<?php echo htmlspecialchars($filtro_usuario); ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-secondary mb-1">ACCIÓN</label>
                <select name="accion" class="input-jv">
                    <option value="">Todas</option>
                    <?php foreach ($acciones_disponibles as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $filtro_accion === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars($accion_nombres[$a] ?? $a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-secondary mb-1">DESDE</label>
                <input type="date" name="desde" class="input-jv" value="<?php echo htmlspecialchars($filtro_desde); ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-secondary mb-1">HASTA</label>
                <input type="date" name="hasta" class="input-jv" value="<?php echo htmlspecialchars($filtro_hasta); ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn-jv-primary w-100" style="padding:10px;font-size:.75rem;"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </form>

    <!-- TABLA PRINCIPAL -->
    <div class="card-jv card-jv-table p-0">
        <div class="table-responsive">
            <table class="table-jv mb-0">
                <thead>
                    <tr>
                        <th style="width:60px;">N°</th>
                        <th>USUARIO</th>
                        <th>ACCIÓN</th>
                        <th>DETALLE</th>
                        <th>FECHA / HORA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($registros)): ?>
                        <?php foreach ($registros as $r):
                            $badge_class = 'b-default';
                            if ($r['accion'] === 'crear') $badge_class = 'b-crear';
                            elseif ($r['accion'] === 'editar') $badge_class = 'b-editar';
                            elseif ($r['accion'] === 'eliminar' || $r['accion'] === 'anular') $badge_class = 'b-eliminar';
                            elseif (in_array($r['accion'], ['toggle_status', 'desactivar', 'activar'])) $badge_class = 'b-toggle';
                            elseif ($r['accion'] === 'login') $badge_class = 'b-login';
                            elseif ($r['accion'] === 'logout') $badge_class = 'b-logout';
                        ?>
                        <tr>
                            <td class="fw-bold text-jv-muted">#<?php echo $r['id_auditoria']; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($r['usuario_nombre'] ?? '?'); ?></td>
                            <td><span class="badge-accion <?php echo $badge_class; ?>"><?php echo htmlspecialchars($accion_nombres[$r['accion']] ?? $r['accion']); ?></span></td>
                            <td class="text-jv-muted"><?php echo htmlspecialchars($r['detalle'] ?? ''); ?></td>
                            <td style="color:var(--jv-text-primary);font-weight:600;font-size:.82rem;"><?php echo date('d/m/Y H:i', strtotime($r['fecha_hora'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-jv-muted">No hay registros de auditoría</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINACIÓN -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination-jv">
        <a href="?page=1<?php echo htmlspecialchars($query_string); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">&laquo;</a>
        <?php for ($i = max(1, $page - 3); $i <= min($total_paginas, $page + 3); $i++): ?>
            <a href="?page=<?php echo $i; ?><?php echo htmlspecialchars($query_string); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <a href="?page=<?php echo $total_paginas; ?><?php echo htmlspecialchars($query_string); ?>" class="<?php echo $page >= $total_paginas ? 'disabled' : ''; ?>">&raquo;</a>
    </div>
    <?php endif; ?>
</div>
</div>
<!-- JAVASCRIPT -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sweetalert2.all.min.js"></script>
    <script src="../assets/modules/historial/historial.js"></script>
</body>
</html>
