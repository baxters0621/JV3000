<?php
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::soloAdmin();

$csrf_token = Security::generateToken();

$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$filtro_desde = $_GET['desde'] ?? '';
$filtro_hasta = $_GET['hasta'] ?? '';

$where = [];
$params = [];

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

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$total_registros = (int)$db->fetchOne("SELECT COUNT(*) as total FROM auditoria a $sql_where", $params)['total'];
$total_paginas = max(1, ceil($total_registros / $limit));

$registros = $db->fetchAll("SELECT a.* FROM auditoria a $sql_where ORDER BY a.fecha_hora DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

$acciones_disponibles = ['crear', 'editar', 'eliminar', 'anular', 'login', 'logout'];
$accion_nombres = ['login' => 'Inicio de Sesión', 'logout' => 'Sesión Cerrada', 'crear' => 'Crear', 'editar' => 'Editar', 'eliminar' => 'Eliminar', 'anular' => 'Anular'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpiar'])) {
    $db->execute("DELETE FROM auditoria");
    $eliminados = $db->getConnection()->affected_rows;
    registrarAuditoria('eliminar', "Historial de auditoría limpiado ($eliminados registro(s))");
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => "SE ELIMINARON $eliminados REGISTRO(S) DE AUDITORÍA."];
    header('Location: auditoria.php');
    exit;
}

$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>
    <style>
    .aud-header-icon {
        width:48px;height:48px;border-radius:14px;
        background:linear-gradient(135deg,#7c3aed,#5b21b6);
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-size:1.5rem;flex-shrink:0;
        box-shadow:0 0 30px rgba(124,58,237,0.3);
    }
    .card-jv-table { border-top:4px solid #8b5cf6; border-radius:var(--jv-radius) !important;overflow:hidden; }
    .pagina-aud .table-jv thead th {
        background:linear-gradient(135deg,#5b21b6,#4c1d95);
        color:#ddd6fe;
        font-size:.75rem;
        padding:12px 14px;
    }
    .pagina-aud .table-jv tbody td {
        padding:10px 14px;
        font-size:.85rem;
        border-bottom:1px solid rgba(139,92,246,0.08);
        color:var(--jv-text-primary);
    }
    .pagina-aud .table-jv tbody td.text-muted { color:var(--jv-text-secondary); }
    .pagina-aud .table-jv tbody tr:hover { background:rgba(139,92,246,0.04); }
    .badge-accion {
        display:inline-block;padding:3px 10px;border-radius:12px;
        font-size:.7rem;font-weight:700;
    }
    .b-crear { background:rgba(34,197,94,0.15);color:#4ade80; }
    .b-editar { background:rgba(6,182,212,0.15);color:#22d3ee; }
    .b-eliminar { background:rgba(239,68,68,0.15);color:#f87171; }
    .b-toggle { background:rgba(245,158,11,0.15);color:#fbbf24; }
    .b-login { background:rgba(99,102,241,0.15);color:#818cf8; }
    .b-logout { background:rgba(100,116,139,0.15);color:#94a3b8; }
    .b-default { background:rgba(148,163,184,0.15);color:#94a3b8; }
    .filtro-box {
        background:rgba(139,92,246,0.04);
        border:1px solid rgba(139,92,246,0.12);
        border-radius:var(--jv-radius);
        padding:14px 18px;margin-bottom:16px;
    }
    .pagination-jv {
        display:flex;gap:6px;justify-content:center;padding:14px 0;
    }
    .pagination-jv a,.pagination-jv span {
        display:inline-flex;align-items:center;justify-content:center;
        min-width:36px;height:36px;border-radius:8px;
        font-size:.8rem;font-weight:700;text-decoration:none;
        border:1px solid var(--jv-border);color:var(--jv-text);
        background:var(--jv-bg-card);transition:.15s;
    }
    .pagination-jv a:hover { border-color:#8b5cf6;color:#8b5cf6; }
    .pagination-jv .active { background:#8b5cf6;border-color:#8b5cf6;color:#fff; }
    .pagination-jv .disabled { opacity:.4;pointer-events:none; }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-wrapper" id="mainWrapper">
<div class="container-fluid px-4 py-4 pagina-aud">

    <?php if ($flash): ?>
        <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?>" style="padding:12px 18px;font-size:.85rem;font-weight:600;">
            <?php echo htmlspecialchars($flash['texto']); ?>
        </div>
    <?php endif; ?>

    <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" style="padding:18px 24px;border-left:4px solid #8b5cf6;">
        <div class="d-flex align-items-center gap-3">
            <div class="aud-header-icon"><i class="bi bi-shield-check"></i></div>
            <div>
                <h1 class="font-brand fw-bold m-0 text-white" style="font-size:1.4rem;">AUDITORÍA</h1>
                <p class="m-0 text-white opacity-75" style="font-size:.85rem;">Registro de Actividades del Sistema</p>
            </div>
        </div>
        <span class="text-jv-muted small fw-bold"><?php echo $total_registros; ?> registro(s)</span>
    </div>

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

    <!-- Mantenimiento: Limpiar historial -->
    <details class="mb-3" style="background:rgba(239,68,68,0.04);border:1px solid rgba(239,68,68,0.15);border-radius:var(--jv-radius);padding:10px 16px;">
        <summary style="cursor:pointer;font-size:.8rem;font-weight:700;color:#f87171;text-transform:uppercase;letter-spacing:1px;list-style:none;">
            <i class="bi bi-trash3 me-2"></i>MANTENIMIENTO — LIMPIAR HISTORIAL
        </summary>
        <div class="mt-2">
            <form method="POST" onsubmit="return confirmarLimpieza(event)" class="d-flex align-items-end gap-3 flex-wrap">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="limpiar" value="1">
                <button type="submit" class="btn-jv-danger" style="padding:10px 24px;font-size:.8rem;font-weight:700;">
                    <i class="bi bi-trash3 me-1"></i>LIMPIAR HISTORIAL COMPLETO
                </button>
            </form>
            <p class="small text-jv-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Esta acción elimina permanentemente TODOS los registros de auditoría. No se puede deshacer.</p>
        </div>
    </details>

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
                            <td style="color:#e2e8f0;font-weight:600;font-size:.82rem;"><?php echo date('d/m/Y H:i', strtotime($r['fecha_hora'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-jv-muted">No hay registros de auditoría</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div class="pagination-jv">
        <a href="?page=1<?php echo htmlspecialchars($query_string ?? ''); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">&laquo;</a>
        <?php for ($i = max(1, $page - 3); $i <= min($total_paginas, $page + 3); $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <a href="?page=<?php echo $total_paginas; ?>" class="<?php echo $page >= $total_paginas ? 'disabled' : ''; ?>">&raquo;</a>
    </div>
    <?php endif; ?>
</div>
</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sweetalert2.all.min.js"></script>
<script>
function confirmarLimpieza(e) {
    e.preventDefault();
    const f = e.target;
    Swal.fire({
        title: '¿LIMPIAR HISTORIAL?',
        html: 'Se eliminarán <strong>TODOS</strong> los registros de auditoría. Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        background: '#0f172a', color: '#fff',
        confirmButtonColor: '#ef4444', cancelButtonColor: '#1e293b',
        confirmButtonText: 'SÍ, ELIMINAR',
        cancelButtonText: 'CANCELAR'
    }).then(r => { if (r.isConfirmed) f.submit(); });
}
(function() {
    var alerts = document.querySelectorAll('.alert-jv');
    for (var i = 0; i < alerts.length; i++) {
        (function(a) {
            setTimeout(function() {
                a.style.transition = 'opacity 0.6s';
                a.style.opacity = '0';
                setTimeout(function() { a.remove(); }, 600);
            }, 4000);
        })(alerts[i]);
    }
})();
const mainWrapper = document.getElementById('mainWrapper');
const observer = new MutationObserver(function() {
    if (document.body.classList.contains('sidebar-open')) mainWrapper.classList.add('sidebar-open');
    else mainWrapper.classList.remove('sidebar-open');
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
</script>
</body>
</html>
