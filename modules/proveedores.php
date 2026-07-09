<?php
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::verificarPermisoCarga();
$csrf_token = Security::generateToken();

if (isset($_POST['accion_proveedor'])) {
    $accion = $_POST['accion_proveedor'];
    $rif = mb_strtoupper(trim($_POST['rif']));
    $nombre_empresa = mb_strtoupper(trim($_POST['nombre_empresa']));
    $telefono_contacto = trim($_POST['telefono'] ?? '');
    $contacto_nombre = trim($_POST['contacto_nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $lead_time = !empty($_POST['lead_time']) ? min(365, max(0, intval($_POST['lead_time']))) : null;
    $limite_credito_raw = str_replace(',', '', trim($_POST['limite_credito'] ?? ''));
    $limite_credito = !empty($limite_credito_raw) ? min(999999999.99, max(0, floatval($limite_credito_raw))) : null;
    $dias_credito = !empty($_POST['dias_credito']) ? min(360, max(0, intval($_POST['dias_credito']))) : 0;
    $condiciones_pago = $_POST['condiciones_pago'] ?? 'Contado';
    $moneda = $_POST['moneda'] ?? 'USD';
    $status = $_POST['status'] ?? 'Activo';

    if ($accion == "registrar") {
        if (!validarRIF($rif)) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'FORMATO DE RIF INVÁLIDO. USE: J-12345678-0'];
            header("Location: proveedores.php");
            exit();
        }
        if (!validarTelefono($telefono_contacto)) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'FORMATO DE TELÉFONO INVÁLIDO. USE: (04XX) 000-0000'];
            header("Location: proveedores.php");
            exit();
        }
        if ($db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(rif) = LOWER(?)", [$rif])) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'EL RIF YA PERTENECE A OTRO PROVEEDOR.']; header("Location: proveedores.php");
            exit();
        }
        if ($db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(nombre_empresa) = LOWER(?)", [$nombre_empresa])) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'YA EXISTE UN PROVEEDOR CON ESE NOMBRE DE EMPRESA.']; header("Location: proveedores.php");
            exit();
        }
        $db->insert('proveedores', [
            'rif' => $rif,
            'nombre_empresa' => $nombre_empresa,
            'contacto' => $contacto_nombre,
            'telefono' => $telefono_contacto,
            'lead_time' => $lead_time,
            'limite_credito' => $limite_credito,
            'dias_credito' => $dias_credito,
            'condiciones_pago' => $condiciones_pago,
            'moneda' => $moneda,
            'status' => $status,
            'email' => $email,
            'direccion' => $direccion,
        ]);
        registrarAuditoria('crear', 'Proveedor registrado');
        $_SESSION['flash_msg'] = ['tipo'=>'success','texto'=>'PROVEEDOR REGISTRADO CON ÉXITO.'];
        header("Location: proveedores.php");
        exit();
    }

    if ($accion == "editar") {
        if (!validarRIF($rif)) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'FORMATO DE RIF INVÁLIDO. USE: J-12345678-0'];
            header("Location: proveedores.php");
            exit();
        }
        if (!validarTelefono($telefono_contacto)) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'FORMATO DE TELÉFONO INVÁLIDO. USE: (04XX) 000-0000'];
            header("Location: proveedores.php");
            exit();
        }
        $id_proveedor = intval($_POST['id_proveedor']);
        if ($db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(rif) = LOWER(?) AND id_proveedor != ?", [$rif, $id_proveedor])) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'EL RIF YA PERTENECE A OTRO PROVEEDOR.']; header("Location: proveedores.php");
            exit();
        }
        if ($db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(nombre_empresa) = LOWER(?) AND id_proveedor != ?", [$nombre_empresa, $id_proveedor])) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'YA EXISTE UN PROVEEDOR CON ESE NOMBRE DE EMPRESA.']; header("Location: proveedores.php");
            exit();
        }
        $db->execute(
            "UPDATE proveedores SET rif=?, nombre_empresa=?, contacto=?, telefono=?, lead_time=?, limite_credito=?, dias_credito=?, condiciones_pago=?, moneda=?, status=?, email=?, direccion=? WHERE id_proveedor=?",
            [$rif, $nombre_empresa, $contacto_nombre, $telefono_contacto, $lead_time, $limite_credito, $dias_credito, $condiciones_pago, $moneda, $status, $email, $direccion, $id_proveedor]
        );
        registrarAuditoria('editar', 'Proveedor modificado');
        $_SESSION['flash_msg'] = ['tipo'=>'success','texto'=>'DATOS ACTUALIZADOS CORRECTAMENTE.'];
        header("Location: proveedores.php");
        exit();
    }
    if ($accion == "eliminar") {
        Security::soloAdmin();
        $id_proveedor = intval($_POST['id_proveedor'] ?? 0);
        $db->execute("UPDATE proveedores SET status = 'Inactivo' WHERE id_proveedor = ?", [$id_proveedor]);
        registrarAuditoria('eliminar', 'Proveedor desactivado');
        $_SESSION['flash_msg'] = ['tipo'=>'success','texto'=>'PROVEEDOR DESACTIVADO.'];
        header("Location: proveedores.php");
        exit();
    }
}

$proveedores = $db->fetchAll("SELECT * FROM proveedores WHERE status = 'Activo' ORDER BY nombre_empresa ASC");

$total_prov = count($proveedores);
$activos_prov = $db->fetchOne("SELECT COUNT(*) as t FROM proveedores WHERE status='Activo'")['t'];
$limite_credito_total = $db->fetchOne("SELECT COALESCE(SUM(limite_credito),0) as t FROM proveedores WHERE limite_credito > 0")['t'];

// Flash messages via session
$flash = null;
if (isset($_GET['res'])) {
    $map = ['success' => 'PROVEEDOR REGISTRADO CON ÉXITO.', 'updated' => 'DATOS ACTUALIZADOS CORRECTAMENTE.'];
    $flash = ['tipo' => 'success', 'texto' => $map[$_GET['res']] ?? 'OPERACIÓN EXITOSA.'];
} elseif (isset($_GET['err'])) {
    $map = ['rif_exists' => 'EL RIF YA PERTENECE A OTRO PROVEEDOR.', 'csrf' => 'ERROR DE SEGURIDAD. INTENTE DE NUEVO.', 'rif_invalido' => 'FORMATO DE RIF INVÁLIDO. USE: J-12345678-0', 'tel_invalido' => 'FORMATO DE TELÉFONO INVÁLIDO. USE: (04XX) 000-0000', 'db_error' => 'ERROR EN LA BASE DE DATOS.'];
    $flash = ['tipo' => 'danger', 'texto' => $map[$_GET['err']] ?? 'ERROR DESCONOCIDO.'];
}
$flash_s = $_SESSION['flash_msg'] ?? $flash;
if ($flash_s) $flash = $flash_s;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/diseno.php'; ?>
    <title>Proveedores | JV3000 C.A.</title>
    <style>
    /* === THEME: PROVEEDORES (Purple) =================== */
    .prov-header-icon {
        width:52px;height:52px;border-radius:14px;
        background:linear-gradient(135deg,#a855f7,#7c3aed);
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-size:1.5rem;flex-shrink:0;
        box-shadow:0 0 30px rgba(168,85,247,0.3);
    }

    .codigo-badge {
        background:rgba(168,85,247,0.12);color:#c084fc;
        font-size:.7rem;font-weight:800;padding:3px 10px;
        border-radius:20px;display:inline-block;
        letter-spacing:.5px;
    }

    .btn-action {
        width:40px;height:40px;border-radius:12px;
        display:inline-flex;align-items:center;justify-content:center;
        border:1px solid var(--jv-border);background:var(--jv-bg-primary);
        color:var(--jv-text);transition:.15s;
    }
    .btn-action:hover {
        background:var(--jv-bg-hover);border-color:#a855f7;
        color:#a855f7;
    }
    .btn-action-del:hover {
        border-color:#ef4444 !important;
        color:#ef4444 !important;
    }

    .estado-vacio { padding:60px 20px;text-align:center; }
    .estado-vacio i {
        font-size:3.5rem;color:rgba(168,85,247,0.2);display:block;margin-bottom:16px;
    }
    .estado-vacio span {
        font-size:.85rem;font-weight:700;text-transform:uppercase;
        letter-spacing:1px;color:rgba(148,163,184,0.5);
    }

    .pagina-proveedores .card-jv {
        border-color:rgba(168,85,247,0.25);
        box-shadow:0 20px 50px -12px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(168,85,247,0.06);
    }
    .pagina-proveedores .card-jv:hover { border-color:rgba(168,85,247,0.45); }
    .pagina-proveedores .btn-jv-primary {
        background:linear-gradient(135deg,#a855f7,#7c3aed);
    }
    .pagina-proveedores .btn-jv-primary:hover {
        box-shadow:0 8px 25px -5px rgba(168,85,247,0.4);
        transform:translateY(-2px);
    }
    .pagina-proveedores .input-jv:focus {
        border-color:#a855f7;
        box-shadow:0 0 0 3px rgba(168,85,247,0.15);
    }
    .pagina-proveedores .header-card {
        padding:18px 24px;
        border-left:4px solid #a855f7;
    }

    /* ── Widget cards ── */
    .pagina-proveedores .widget-card {
        border-radius:var(--jv-radius-lg);
        background:var(--jv-bg-card);
        backdrop-filter:blur(20px);
        border:1px solid var(--jv-border);
        padding:20px 22px;
        display:flex;
        align-items:center;
        gap:18px;
        transition:all .25s ease;
        min-height:90px;
    }
    .pagina-proveedores .widget-card:hover {
        border-color:var(--jv-border-hover);
        transform:translateY(-3px);
        box-shadow:0 12px 40px -8px rgba(0,0,0,0.4);
    }
    .widget-card .widget-icon {
        width:46px;height:46px;border-radius:14px;
        display:flex;align-items:center;justify-content:center;
        font-size:1.3rem;flex-shrink:0;
    }
    .widget-card .widget-label {
        font-size:.6rem;text-transform:uppercase;
        letter-spacing:1px;font-weight:700;
        color:rgba(148,163,184,0.7);
        margin-bottom:4px;
    }
    .widget-card .widget-value {
        font-size:1.4rem;font-weight:800;color:#fff;
        line-height:1.2;
    }

    /* ── Provider Cards ── */
    .prov-premium {
        background:var(--jv-bg-card);
        backdrop-filter:blur(20px);
        border:1px solid rgba(168,85,247,0.2);
        border-radius:var(--jv-radius-lg);
        overflow:hidden;
        transition:all .3s ease;
    }
    .prov-premium:hover {
        border-color:rgba(168,85,247,0.45);
        transform:translateY(-3px);
        box-shadow:0 12px 40px -8px rgba(0,0,0,0.4);
    }
    .prov-premium .prov-head {
        display:flex;justify-content:space-between;align-items:center;
        padding:14px 18px;
        background:rgba(0,0,0,0.2);
        border-bottom:1px solid rgba(168,85,247,0.08);
    }
    .prov-premium .prov-body {
        padding:18px;
        cursor:pointer;
    }
    .prov-premium .prov-name {
        font-size:1rem;font-weight:700;color:#fff;
        text-transform:uppercase;margin-bottom:2px;
    }
    .prov-premium .prov-rif {
        font-size:.75rem;color:#c084fc;font-weight:600;
        margin-bottom:10px;
    }
    .prov-premium .prov-info {
        font-size:.8rem;color:var(--jv-text-secondary);margin-bottom:4px;
    }
    .prov-premium .prov-info i { width:18px;color:#a855f7; }
    .prov-premium .prov-details {
        max-height:0;overflow:hidden;
        transition:max-height .3s ease, padding .3s ease;
        background:rgba(0,0,0,0.15);
    }
    .prov-premium.expanded .prov-details {
        max-height:250px;
        padding:14px 18px;
        border-top:1px solid rgba(168,85,247,0.08);
    }
    .prov-premium .prov-detail-row {
        display:flex;justify-content:space-between;
        padding:5px 0;font-size:.82rem;
        border-bottom:1px solid rgba(255,255,255,0.03);
    }
    .prov-premium .prov-detail-row:last-child { border-bottom:none; }
    .prov-premium .detail-label { color:var(--jv-text-secondary); }
    .prov-premium .detail-value { font-weight:600;color:#fff; }
    .prov-premium .prov-foot {
        padding:12px 18px;
        border-top:1px solid rgba(168,85,247,0.08);
    }

    /* ── Badges ── */
    .badge-jv { padding:4px 12px;border-radius:20px;font-weight:800;font-size:.7rem;letter-spacing:.5px;display:inline-flex;align-items:center;gap:5px; }
    .badge-success { background:rgba(34,197,94,0.18);color:#4ade80;border:1px solid rgba(34,197,94,0.4); }
    .badge-danger { background:rgba(239,68,68,0.18);color:#f87171;border:1px solid rgba(239,68,68,0.4); }
    .badge-warning { background:rgba(245,158,11,0.18);color:#fbbf24;border:1px solid rgba(245,158,11,0.4); }

    .status-dot-jv {
        width:8px;height:8px;border-radius:50%;display:inline-block;
    }
    .status-dot-jv.active { background:#22c55e;box-shadow:0 0 8px rgba(34,197,94,0.5); }
    .status-dot-jv.inactive { background:#64748b; }

    /* ── Alert ── */
    .alert-jv { border-left:4px solid;border-radius:8px;padding:14px 20px !important;font-size:.9rem; }
    .alert-jv-success { border-left-color:#22c55e;background:rgba(34,197,94,0.1); }
    .alert-jv-danger { border-left-color:#ef4444;background:rgba(239,68,68,0.1); }

    /* ── Modal section groups ── */
    .section-bg {
        background:rgba(2,6,23,0.3);
        border:1px solid rgba(168,85,247,0.08);
        border-radius:var(--jv-radius);
        padding:14px 16px;
        margin-bottom:12px;
    }
    .section-label {
        font-size:.65rem;font-weight:800;text-transform:uppercase;
        letter-spacing:1px;color:#c084fc;
        margin-bottom:8px;padding-bottom:6px;
        border-bottom:1px solid rgba(168,85,247,0.15);
        display:flex;align-items:center;gap:4px;
    }

    /* ── Filter btn group ── */
    .filter-group { display:flex;gap:6px; }
    .filter-group .btn-filter {
        padding:6px 14px;border-radius:20px;border:1px solid rgba(255,255,255,0.08);
        background:transparent;color:var(--jv-text-secondary);font-size:.75rem;
        font-weight:700;text-transform:uppercase;letter-spacing:.5px;
        transition:all .2s ease;cursor:pointer;
    }
    .filter-group .btn-filter:hover { border-color:#a855f7;color:#fff; }
    .filter-group .btn-filter.active {
        background:rgba(168,85,247,0.2);border-color:#a855f7;color:#c084fc;
    }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
    <div class="pagina-proveedores">
    <div class="container-fluid px-4 py-4">

        <div class="d-flex align-items-center gap-4 mb-4">
            <div class="prov-header-icon">
                <i class="bi bi-building"></i>
            </div>
            <div>
                <h1 class="font-brand mb-1" style="font-size:1.8rem;letter-spacing:-1px;">PROVEEDORES</h1>
                <p class="text-white opacity-75 small fw-bold text-uppercase mb-0">Directorio de Alianzas Comerciales</p>
            </div>
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="filter-group">
                    <button class="btn-filter active" onclick="filtrarProv('todos')" id="f-todos">Todos</button>
                    <button class="btn-filter" onclick="filtrarProv('Activo')" id="f-Activo">Activos</button>
                    <button class="btn-filter" onclick="filtrarProv('Inactivo')" id="f-Inactivo">Inactivos</button>
                </div>
                <button class="btn btn-jv-primary" onclick="nuevoProveedor()" id="btnNuevoProv">
                    <i class="bi bi-plus-lg me-2"></i>NUEVO
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4">
                <i class="bi bi-shield-check me-2"></i><?php echo $flash['texto']; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Widgets -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(96,165,250,0.12);color:#60a5fa;">
                        <i class="bi bi-building"></i>
                    </div>
                    <div>
                        <div class="widget-label">Total Proveedores</div>
                        <div class="widget-value"><?php echo $total_prov; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(34,197,94,0.12);color:#4ade80;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <div class="widget-label">Proveedores Activos</div>
                        <div class="widget-value"><?php echo $activos_prov; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(251,191,36,0.12);color:#fbbf24;">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div>
                        <div class="widget-label">Límite Crédito Total</div>
                        <div class="widget-value">$<?php echo number_format($limite_credito_total, 0); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Provider Cards Grid -->
        <div class="row g-3" id="provGrid">
            <?php if ($total_prov > 0): ?>
                <?php foreach ($proveedores as $row): ?>
                <div class="col-md-6 col-lg-4 prov-card" data-status="<?php echo $row['status']; ?>">
                    <div class="prov-premium">
                        <div class="prov-head">
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-dot-jv <?php echo $row['status'] == 'Activo' ? 'active' : 'inactive'; ?>"></span>
                                <span class="badge-jv <?php echo $row['status'] == 'Activo' ? 'badge-success' : 'badge-danger'; ?>"><?php echo $row['status']; ?></span>
                            </div>
                            <button class="btn-action" onclick='editarProveedor(<?php echo json_encode($row); ?>)' title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <?php if (Security::esAdmin()): ?>
                            <button class="btn-action btn-action-del" onclick="eliminarProveedor(<?php echo $row['id_proveedor']; ?>,'<?php echo htmlspecialchars($row['nombre_empresa'], ENT_QUOTES); ?>')" title="Eliminar">
                                <i class="bi bi-trash3"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="prov-body" onclick="toggleProv(this)">
                            <div class="prov-name"><?php echo htmlspecialchars($row['nombre_empresa']); ?></div>
                            <div class="prov-rif"><span class="codigo-badge"><?php echo htmlspecialchars($row['rif']); ?></span></div>
                            <div class="prov-info"><i class="bi bi-telephone"></i><?php echo htmlspecialchars($row['telefono'] ?? ($row['contacto'] ?? 'Sin teléfono')); ?></div>
                            <?php if (!empty($row['contacto'])): ?>
                            <div class="prov-info"><i class="bi bi-person"></i><?php echo htmlspecialchars($row['contacto']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['email'])): ?>
                            <div class="prov-info"><i class="bi bi-envelope"></i><?php echo htmlspecialchars($row['email']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="prov-details">
                            <div class="prov-detail-row">
                                <span class="detail-label">Lead Time</span>
                                <span class="detail-value"><?php echo $row['lead_time'] ? $row['lead_time'] . ' días' : '-'; ?></span>
                            </div>
                            <div class="prov-detail-row">
                                <span class="detail-label">Límite Crédito</span>
                                <span class="detail-value" style="color:#4ade80;"><?php echo $row['limite_credito'] ? '$' . number_format($row['limite_credito'], 2) : '-'; ?></span>
                            </div>
                            <div class="prov-detail-row">
                                <span class="detail-label">Plazo Pago</span>
                                <span class="detail-value"><?php echo $row['dias_credito'] ? $row['dias_credito'] . ' días' : 'Contado'; ?></span>
                            </div>
                            <div class="prov-detail-row">
                                <span class="detail-label">Moneda</span>
                                <span class="detail-value"><?php echo $row['moneda'] ?? 'USD'; ?></span>
                            </div>
                            <div class="prov-detail-row">
                                <span class="detail-label">Condición Pago</span>
                                <span class="detail-value"><?php echo $row['condiciones_pago'] ?? 'Contado'; ?></span>
                            </div>
                        </div>
                        <div class="prov-foot">
                            <button class="btn btn-jv-primary w-100 py-2" onclick="verHistorial(<?php echo $row['id_proveedor']; ?>)">
                                <i class="bi bi-clock-history me-2"></i>Ver Historial
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="estado-vacio">
                        <i class="bi bi-building"></i>
                        <span>No hay proveedores registrados</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
    </div>

    <!-- Modal Premium -->
    <div class="modal fade" id="modalProveedor" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background:var(--jv-bg-secondary); border:1px solid var(--jv-border); border-radius:var(--jv-radius-xl);">
                <form action="" method="POST" id="formProveedor">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_proveedor" id="p_accion" value="registrar">
                    <input type="hidden" name="id_proveedor" id="p_id_edit">
                    <div class="modal-body p-4">
                        <h5 class="fw-bolder mb-4 font-brand" id="modalTitle" style="color:#a855f7;letter-spacing:-.5px;">REGISTRAR PROVEEDOR</h5>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-building"></i> Información Fiscal</div>
                            <div class="row g-3 mb-0">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-2">RIF</label>
                                    <input type="text" name="rif" id="p_rif" class="input-jv" required placeholder="Ej: J-12345678-0" maxlength="13">
                                </div>
                                <div class="col-md-8">
                                    <label class="small fw-bold text-secondary mb-2">NOMBRE EMPRESA</label>
                                    <input type="text" name="nombre_empresa" id="p_empresa" class="input-jv text-uppercase" required placeholder="Nombre legal de la empresa" oninput="this.value = this.value.toUpperCase()">
                                </div>
                            </div>
                            <div class="mt-3 mb-0">
                                <label class="small fw-bold text-secondary mb-2">DIRECCIÓN</label>
                                <textarea name="direccion" id="p_direccion" class="input-jv" rows="2" placeholder="Dirección fiscal"></textarea>
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-person-lines-fill"></i> Contacto</div>
                            <div class="row g-3 mb-0">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-2">TELÉFONO</label>
                                    <input type="text" name="telefono" id="p_tel" class="input-jv" required placeholder="(04XX) 000-0000">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-2">CONTACTO NOMBRE</label>
                                    <input type="text" name="contacto_nombre" id="p_contacto_nombre" class="input-jv" placeholder="Nombre del contacto">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-2">EMAIL</label>
                                    <input type="email" name="email" id="p_email" class="input-jv" placeholder="correo@ejemplo.com">
                                </div>
                            </div>
                        </div>

                        <div class="section-bg mb-4">
                            <div class="section-label"><i class="bi bi-gear"></i> Condiciones Comerciales</div>
                            <div class="row g-3 mb-0">
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-2">PLAZO DE ENTREGA (DÍAS)</label>
                                    <input type="number" name="lead_time" id="p_lead_time" class="input-jv" placeholder="Días" min="0" max="365">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-2">LÍMITE CRÉDITO ($)</label>
                                    <input type="text" name="limite_credito" id="p_limite_credito" class="input-jv" placeholder="0.00" maxlength="15" inputmode="decimal">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-2">DÍAS DE CRÉDITO</label>
                                    <input type="number" name="dias_credito" id="p_dias_credito" class="input-jv" placeholder="Días" min="0" max="360" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="small fw-bold text-secondary mb-2">MONEDA</label>
                                    <select name="moneda" id="p_moneda" class="input-jv">
                                        <option value="USD">USD - Dólar</option>
                                        <option value="EUR">EUR - Euro</option>
                                        <option value="VES">VES - Bolívar</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mt-2 mb-0">
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-2">CONDICIÓN PAGO</label>
                                    <select name="condiciones_pago" id="p_condiciones_pago" class="input-jv">
                                        <option value="Contado">Contado</option>
                                        <option value="Credito">Crédito</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold text-secondary mb-2">ESTADO</label>
                                    <select name="status" id="p_status" class="input-jv">
                                        <option value="Activo">Activo</option>
                                        <option value="Inactivo">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="btn-prov-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase">
                            <i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sweetalert2.all.min.js"></script>
    <script>
    const modalP = new bootstrap.Modal(document.getElementById('modalProveedor'));
    const formP = document.getElementById('formProveedor');

    function formatMoney(el) {
        let val = el.value.replace(/[^0-9.]/g, '');
        let parts = val.split('.');
        if (parts.length > 2) parts = [parts[0], parts.slice(1).join('')];
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        el.value = parts.join('.');
    }

    document.getElementById('p_limite_credito').addEventListener('input', function() {
        formatMoney(this);
    });

    // RIF formatter
    document.getElementById('p_rif').addEventListener('input', function(e) {
        let val = e.target.value.toUpperCase().replace(/[^VJGPE0-9]/g, '');
        let formatted = '';
        if (val.length > 0) {
            formatted += val[0];
            if (val.length > 1) {
                formatted += '-';
                let body = val.substring(1);
                if (body.length > 7) {
                    formatted += body.substring(0, body.length - 1) + '-' + body.substring(body.length - 1);
                } else {
                    formatted += body;
                }
            }
        }
        e.target.value = formatted.substring(0, 13);
    });

    // Phone formatter
    document.getElementById('p_tel').addEventListener('input', function(e) {
        let val = e.target.value.replace(/\D/g, '');
        if (val.length > 11) val = val.substring(0, 11);
        let formatted = '';
        if (val.length > 0) {
            formatted = '(' + val.substring(0, 4);
            if (val.length > 4) {
                formatted += ') ' + val.substring(4, 7);
                if (val.length > 7) {
                    formatted += '-' + val.substring(7, 11);
                }
            }
        }
        e.target.value = formatted;
    });

    // Submit validation + anti-doble-click
    formP.addEventListener('submit', function(e) {
        const rifValue = document.getElementById('p_rif').value;
        const rifRegex = /^[VJGPE]-\d{7,9}-\d$/;
        if (!rifRegex.test(rifValue)) {
            e.preventDefault();
            Swal.fire({ icon:'error', title:'RIF INVÁLIDO', text:'Use el formato oficial: J-12345678-0', background:'#0f172a', color:'#fff', confirmButtonColor:'#ef4444' });
            return;
        }
        const telValue = document.getElementById('p_tel').value;
        if (!/^\(\d{4}\) \d{3}-\d{4}$/.test(telValue)) {
            e.preventDefault();
            Swal.fire({ icon:'error', title:'TELÉFONO INVÁLIDO', text:'Ingrese un número válido: (04XX) 000-0000', background:'#0f172a', color:'#fff', confirmButtonColor:'#ef4444' });
            return;
        }
        // Anti-doble-click
        const btn = document.getElementById('btn-prov-submit');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
    });

    function nuevoProveedor() {
        document.getElementById('p_accion').value = "registrar";
        document.getElementById('p_id_edit').value = "";
        document.getElementById('modalTitle').innerText = "REGISTRAR PROVEEDOR";
        document.getElementById('p_rif').value = "";
        document.getElementById('p_empresa').value = "";
        document.getElementById('p_tel').value = "";
        document.getElementById('p_contacto_nombre').value = "";
        document.getElementById('p_email').value = "";
        document.getElementById('p_direccion').value = "";
        document.getElementById('p_lead_time').value = "";
        document.getElementById('p_limite_credito').value = "";
        document.getElementById('p_dias_credito').value = "0";
        document.getElementById('p_condiciones_pago').value = "Contado";
        document.getElementById('p_moneda').value = "USD";
        document.getElementById('p_status').value = "Activo";
        document.getElementById('btn-prov-submit').disabled = false;
        document.getElementById('btn-prov-submit').innerHTML = '<i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR';
        modalP.show();
    }

    function editarProveedor(data) {
        document.getElementById('p_accion').value = "editar";
        document.getElementById('p_id_edit').value = data.id_proveedor;
        document.getElementById('modalTitle').innerText = "EDITAR PROVEEDOR";
        document.getElementById('p_rif').value = data.rif;
        document.getElementById('p_empresa').value = data.nombre_empresa;
        document.getElementById('p_tel').value = data.telefono || "";
        document.getElementById('p_contacto_nombre').value = data.contacto || "";
        document.getElementById('p_email').value = data.email || "";
        document.getElementById('p_direccion').value = data.direccion || "";
        document.getElementById('p_lead_time').value = data.lead_time || "";
        document.getElementById('p_limite_credito').value = data.limite_credito || "";
        if (document.getElementById('p_limite_credito').value) {
            formatMoney(document.getElementById('p_limite_credito'));
        }
        document.getElementById('p_dias_credito').value = data.dias_credito || 0;
        document.getElementById('p_condiciones_pago').value = data.condiciones_pago || "Contado";
        document.getElementById('p_moneda').value = data.moneda || "USD";
        document.getElementById('p_status').value = data.status || "Activo";
        document.getElementById('btn-prov-submit').disabled = false;
        document.getElementById('btn-prov-submit').innerHTML = '<i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR';
        document.getElementById('p_rif').dispatchEvent(new Event('input'));
        document.getElementById('p_tel').dispatchEvent(new Event('input'));
        modalP.show();
    }

    function filtrarProv(status) {
        document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
        document.getElementById('f-' + status).classList.add('active');
        document.querySelectorAll('.prov-card').forEach(card => {
            card.style.display = (status === 'todos' || card.dataset.status === status) ? 'block' : 'none';
        });
    }

    function toggleProv(el) {
        el.closest('.prov-premium').classList.toggle('expanded');
    }

    function verHistorial(idProveedor) {
        window.location.href = 'compras.php?filtro_proveedor=' + idProveedor;
    }

    function eliminarProveedor(id, nombre) {
        Swal.fire({
            title: '¿Desactivar proveedor?',
            html: `Se desactivará <strong>${nombre}</strong>.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, desactivar',
            cancelButtonText: 'Cancelar',
            background:'#0f172a', color:'#fff',
            reverseButtons: true
        }).then(result => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion_proveedor" value="eliminar">
                    <input type="hidden" name="id_proveedor" value="${id}">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    </script>
    <script>
    const mainWrapper = document.getElementById('mainWrapper');
    const observer = new MutationObserver(() => {
        if (document.body.classList.contains('sidebar-open')) {
            mainWrapper.classList.add('sidebar-open');
        } else {
            mainWrapper.classList.remove('sidebar-open');
        }
    });
    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    </script>
    <script>
    document.querySelectorAll('.flash-auto').forEach(el => {
        setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
    });
    </script>
</body>
</html>