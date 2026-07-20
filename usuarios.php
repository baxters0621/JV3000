<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/init.php';

$db = Database::getInstance();
Security::soloAdmin();

$id_propio = $_SESSION['id_usuario'];
$csrf_token = Security::generateToken();

// ==========================================
// PROCESAR EDICIÓN DE USUARIO
// ==========================================
if (isset($_POST['accion_usuario'])) {
    $accion = $_POST['accion_usuario'];
    $usuario = trim($_POST['usuario'] ?? '');

    if ($accion == "editar") {
        $id_target = intval($_POST['id_usuario']);
        $correo = strtolower(trim($_POST['correo'] ?? ''));
        $password = $_POST['password'];
        $rol_final = ($id_target == $id_propio) ? $_SESSION['rol'] : $_POST['rol'];
        $status_final = ($id_target == $id_propio) ? 'Activo' : ($_POST['status'] ?? 'Activo');

        if ($db->fetchOne("SELECT id_usuario FROM usuarios WHERE LOWER(usuario) = LOWER(?) AND id_usuario != ?", [$usuario, $id_target])) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'EL USUARIO YA EXISTE.'];
            header("Location: usuarios.php"); exit();
        }

        $correo_valido = !empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL);
        if (!empty($correo) && !$correo_valido) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'CORREO ELECTRÓNICO INVÁLIDO.'];
            header("Location: usuarios.php"); exit();
        }

        if (!empty($correo) && $db->fetchOne("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario != ?", [$correo, $id_target])) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'EL CORREO YA ESTÁ EN USO.'];
            header("Location: usuarios.php"); exit();
        }

        if (strlen($usuario) < 4 || !preg_match('/^[a-zA-Z0-9_]+$/', $usuario)) {
            $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'EL USUARIO DEBE TENER MÍN 4 CARACTERES (letras, números, guion bajo).'];
            header("Location: usuarios.php"); exit();
        }

        if (!empty($password)) {
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
                $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'CONTRASEÑA DÉBIL: MÍN 8 CARACTERES, MAYÚSCULAS, NÚMEROS Y SÍMBOLOS.'];
                header("Location: usuarios.php"); exit();
            }
            $pass_hash = password_hash($password, PASSWORD_BCRYPT);
            $db->execute("UPDATE usuarios SET usuario=?, correo=?, password=?, rol=?, status=?, aprobado=? WHERE id_usuario=?", 
                [$usuario, $correo, $pass_hash, $rol_final, $status_final, ($status_final == 'Activo' ? 1 : 0), $id_target]);
        } else {
            $db->execute("UPDATE usuarios SET usuario=?, correo=?, rol=?, status=?, aprobado=? WHERE id_usuario=?", 
                [$usuario, $correo, $rol_final, $status_final, ($status_final == 'Activo' ? 1 : 0), $id_target]);
        }

        $pregunta = trim($_POST['pregunta_seguridad'] ?? '');
        $respuesta = trim($_POST['respuesta_seguridad'] ?? '');
        if ($pregunta !== '' && $respuesta !== '') {
            if (!validarRespuestaSeguridad($respuesta)) {
                $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'RESPUESTA INVÁLIDA. MÍN 3 CARACTERES, DEBE TENER VOCALES, SIN PATRONES (asdf, qwerty, etc).'];
                header("Location: usuarios.php"); exit();
            }
            $resp_hash = password_hash($respuesta, PASSWORD_BCRYPT);
            $db->execute("UPDATE usuarios SET pregunta_seguridad = ?, respuesta_seguridad = ? WHERE id_usuario = ?", [$pregunta, $resp_hash, $id_target]);
        }

        registrarAuditoria('editar', 'Usuario modificado');
        $_SESSION['flash_msg'] = ['tipo'=>'success','texto'=>'COLABORADOR ACTUALIZADO.'];
        header("Location: usuarios.php"); exit();
    }
}

// ==========================================
// CAMBIAR ESTADO DE USUARIO
// ==========================================
if (isset($_GET['toggle_status'])) {
    $id_target = intval($_GET['toggle_status']);
    if ($id_target == $id_propio) {
        $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'NO PUEDES DESACTIVAR TU PROPIA CUENTA.'];
        header("Location: usuarios.php"); exit();
    }
    $row = $db->fetchOne("SELECT status FROM usuarios WHERE id_usuario = ?", [$id_target]);
    if ($row) {
        $nuevo_status = ($row['status'] == 'Activo') ? 'Inactivo' : 'Activo';
        $nuevo_aprobado = ($nuevo_status == 'Activo') ? 1 : 0;
        $db->execute("UPDATE usuarios SET status = ?, aprobado = ? WHERE id_usuario = ?", [$nuevo_status, $nuevo_aprobado, $id_target]);
        registrarAuditoria('toggle_status', 'Cambio de estado');
        $_SESSION['flash_msg'] = ['tipo'=>'success','texto'=>'ESTADO DEL COLABORADOR CAMBIADO.'];
        header("Location: usuarios.php"); exit();
    }
}

// ==========================================
// OBTENER DATOS
// ==========================================
$usuarios = $db->fetchAll("SELECT id_usuario, usuario, correo, rol, status, COALESCE(aprobado, 1) as aprobado, pregunta_seguridad FROM usuarios ORDER BY usuario ASC");

$total_users = $db->fetchOne("SELECT COUNT(*) as t FROM usuarios")['t'];
$activos = $db->fetchOne("SELECT COUNT(*) as t FROM usuarios WHERE status='Activo'")['t'];
$pendientes = $db->fetchOne("SELECT COUNT(*) as t FROM usuarios WHERE COALESCE(aprobado,0)=0")['t'];

$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">
<?php // ==========================================
// HEAD Y ESTILOS HTML
// ========================================== ?>
<head>
<?php include 'includes/diseno.php'; ?>
    <title>Colaboradores | JV3000</title>
    <style>
    /* === THEME: USUARIOS (Orange) ======================= */

    .user-header-icon {
        width:52px;height:52px;border-radius:14px;
        background:linear-gradient(135deg,#ea580c,#c2410c);
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-size:1.5rem;flex-shrink:0;
        box-shadow:0 0 30px rgba(234,88,12,0.3);
    }

    .codigo-badge {
        background:rgba(234,88,12,0.12);color:#fdba74;
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
        background:var(--jv-bg-hover);border-color:#ea580c;
        color:#ea580c;
    }

    .estado-vacio { padding:60px 20px;text-align:center; }
    .estado-vacio i {
        font-size:3.5rem;color:rgba(234,88,12,0.2);display:block;margin-bottom:16px;
    }
    .estado-vacio span {
        font-size:.85rem;font-weight:700;text-transform:uppercase;
        letter-spacing:1px;color:rgba(148,163,184,0.5);
    }

    .pagina-usuarios .card-jv {
        border-color:rgba(234,88,12,0.25);
        box-shadow:0 20px 50px -12px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(234,88,12,0.06);
    }
    .pagina-usuarios .card-jv:hover { border-color:rgba(234,88,12,0.45); }
    .pagina-usuarios .table-jv thead th {
        background:linear-gradient(135deg,#c2410c,#ea580c);
        color:#fed7aa;
        border-bottom:2px solid rgba(234,88,12,0.3);
    }
    .pagina-usuarios .table-jv tbody td {
        border-bottom:1px solid rgba(234,88,12,0.07);
    }
    .pagina-usuarios .table-jv tbody tr:hover {
        background:rgba(234,88,12,0.03);
    }
    .pagina-usuarios .btn-jv-primary {
        background:linear-gradient(135deg,#ea580c,#c2410c);
    }
    .pagina-usuarios .btn-jv-primary:hover {
        box-shadow:0 8px 25px -5px rgba(234,88,12,0.4);
        transform:translateY(-2px);
    }
    .pagina-usuarios .input-jv:focus {
        border-color:#ea580c;
        box-shadow:0 0 0 3px rgba(234,88,12,0.15);
    }
    .pagina-usuarios .header-card {
        padding:18px 24px;
        border-left:4px solid #ea580c;
    }

    /* ── Widget cards ── */
    .pagina-usuarios .widget-card {
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
    .pagina-usuarios .widget-card:hover {
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

    /* ── Badge overrides ── */
    .badge-jv { padding:4px 12px;border-radius:20px;font-weight:800;font-size:.7rem;letter-spacing:.5px;display:inline-flex;align-items:center;gap:5px; }
    .badge-success { background:rgba(34,197,94,0.18);color:#4ade80;border:1px solid rgba(34,197,94,0.4); }
    .badge-danger { background:rgba(239,68,68,0.18);color:#f87171;border:1px solid rgba(239,68,68,0.4); }
    .badge-warning { background:rgba(245,158,11,0.18);color:#fbbf24;border:1px solid rgba(245,158,11,0.4); }

    /* ── Alert ── */
    .alert-jv { border-left:4px solid;border-radius:8px;padding:14px 20px !important;font-size:.9rem; }
    .alert-jv-success { border-left-color:#22c55e;background:rgba(34,197,94,0.1); }
    .alert-jv-danger { border-left-color:#ef4444;background:rgba(239,68,68,0.1); }

    /* ── Modal section groups ── */
    .section-bg {
        background:rgba(2,6,23,0.3);
        border:1px solid rgba(234,88,12,0.08);
        border-radius:var(--jv-radius);
        padding:14px 16px;
        margin-bottom:12px;
    }
    .section-label {
        font-size:.65rem;font-weight:800;text-transform:uppercase;
        letter-spacing:1px;color:#fb923c;
        margin-bottom:8px;padding-bottom:6px;
        border-bottom:1px solid rgba(234,88,12,0.15);
        display:flex;align-items:center;gap:4px;
    }

    /* ── Strength meter ── */
    .strength-meter { height:6px;background:rgba(255,255,255,0.05);border-radius:10px;overflow:hidden; }
    .strength-meter-fill { height:100%;width:0%;border-radius:10px;transition:all .3s ease; }
    .input-error { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,0.15) !important; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
    <div class="pagina-usuarios">
    <div class="container py-5">

        <div class="d-flex align-items-center gap-4 mb-4">
            <div class="user-header-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <h1 class="font-brand mb-1" style="font-size:1.8rem;letter-spacing:-1px;">COLABORADORES</h1>
                <p class="text-white opacity-75 small fw-bold text-uppercase mb-0">Gestión de Personal Autorizado</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4">
                <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($flash['texto']); ?>
            </div>
        <?php endif; ?>

        <?php // ==========================================
        // WIDGETS DE ESTADÍSTICAS
        // ========================================== ?>
        <!-- Stats Widgets -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(148,163,184,0.12);color:#94a3b8;">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="widget-label">Total Colaboradores</div>
                        <div class="widget-value"><?php echo $total_users; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(34,197,94,0.12);color:#4ade80;">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <div class="widget-label">Activos</div>
                        <div class="widget-value"><?php echo $activos; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(234,88,12,0.12);color:#fb923c;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="widget-label">Pendientes</div>
                        <div class="widget-value"><?php echo $pendientes; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php // ==========================================
        // TABLA DE USUARIOS
        // ========================================== ?>
        <!-- Tabla Premium -->
        <div class="card-jv p-0 overflow-hidden">
            <div class="header-card d-flex align-items-center gap-2">
                <i class="bi bi-person-lines-fill" style="color:#ea580c;"></i>
                <span class="fw-bold small text-secondary text-uppercase">Listado de Accesos</span>
                <span class="codigo-badge ms-auto"><?php echo $total_users; ?> registros</span>
            </div>
            <div class="table-responsive">
                <table class="table-jv mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>USUARIO</th>
                            <th>CORREO</th>
                            <th>ROL</th>
                            <th class="text-center">APROBADO</th>
                            <th class="text-center">ESTADO</th>
                            <th class="text-center">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $row): ?>
                                <tr>
                                    <td class="text-secondary small"><span class="codigo-badge">#<?php echo $row['id_usuario']; ?></span></td>
                                    <td class="fw-bold">
                                        <?php echo htmlspecialchars($row['usuario']); ?>
                                    </td>
                                    <td class="text-secondary small"><?php echo htmlspecialchars($row['correo'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $role_class = 'badge-secondary';
                                        $role_text = $row['rol'] ?? '';
                                        if (empty($role_text)) { $role_text = 'SIN ROL'; }
                                        if ($role_text === 'Administrador') $role_class = 'badge-warning';
                                        if ($role_text === 'Operador de Carga' || $role_text === 'Operador de Ventas') $role_class = 'badge-success';
                                        ?>
                                        <span class="badge-jv <?php echo $role_class; ?>"><?php echo $role_text; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['aprobado'] == 1): ?>
                                            <i class="bi bi-check-circle-fill fs-5" style="color:#4ade80;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-hourglass-split fs-5" style="color:#fbbf24;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-jv <?php echo ($row['status'] == 'Activo') ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo strtoupper($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button class="btn-action" onclick='editarUsuario(<?php echo json_encode($row); ?>)' title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <?php if ($row['id_usuario'] != $id_propio): ?>
                                                <?php if ($row['status'] == 'Activo'): ?>
                                                    <button class="btn-action" onclick="confirmarToggle(<?php echo $row['id_usuario']; ?>, '<?php echo htmlspecialchars($row['usuario']); ?>', 'suspender')" title="Suspender">
                                                        <i class="bi bi-person-x-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn-action" onclick="confirmarToggle(<?php echo $row['id_usuario']; ?>, '<?php echo htmlspecialchars($row['usuario']); ?>', 'activar')" title="Reactivar">
                                                        <i class="bi bi-person-check-fill"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="estado-vacio">
                                        <i class="bi bi-people-fill"></i>
                                        <span>No hay colaboradores registrados</span>
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
</div>

    <?php // ==========================================
    // MODAL EDITAR USUARIO
    // ========================================== ?>
    <!-- Modal Premium -->
    <div class="modal fade" id="modalUser" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background:var(--jv-bg-secondary); border:1px solid var(--jv-border); border-radius:var(--jv-radius-xl);">
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion_usuario" id="u_accion" value="registrar">
                    <input type="hidden" name="id_usuario" id="u_id_edit">
                    <div class="modal-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bolder font-brand m-0" id="modalTitle" style="color:#ea580c;letter-spacing:-.5px;">EDITAR USUARIO</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-person-vcard"></i> Datos del Colaborador</div>
                            <div class="mb-3">
                                <label class="small fw-bold text-secondary mb-2">USUARIO</label>
                                <input type="text" name="usuario" id="u_nombre" class="input-jv" required oninput="validarFormulario()" placeholder="Ej: operador_01">
                                <small id="u_error_text" class="text-info mt-1 d-block fw-bold" style="font-size:0.75rem;">Mín. 4 caracteres (letras, números o guion bajo).</small>
                            </div>
                            <div class="mb-0">
                                <label class="small fw-bold text-secondary mb-2">CORREO ELECTRÓNICO</label>
                                <input type="email" name="correo" id="u_correo" class="input-jv" required placeholder="correo@ejemplo.com">
                            </div>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-lock"></i> Contraseña</div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="password" name="password" id="u_pass" class="input-jv" style="border-radius:var(--jv-radius) 0 0 var(--jv-radius);" oninput="validarFormulario()" placeholder="Nueva contraseña de acceso">
                                    <button type="button" onclick="togglePassword()" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-left:none; border-radius:0 var(--jv-radius) var(--jv-radius) 0; padding:12px 14px; display:flex; align-items:center; color:#64748b; cursor:pointer;">
                                        <i class="bi bi-eye-slash-fill" id="toggleIcon"></i>
                                    </button>
                                </div>
                                <small class="text-info" id="passHelp" style="display:none; font-size:0.75rem; font-weight:bold;">Dejar en blanco para no cambiarla.</small>
                            </div>
                            <div class="strength-meter">
                                <div id="meter-fill" class="strength-meter-fill"></div>
                            </div>
                            <small class="text-info mt-1 d-block fw-bold" style="font-size:0.75rem;" id="meter-text">Mín. 8 caracteres: Mayúsculas, Minúsculas, Números y Símbolos.</small>
                        </div>

                        <div class="section-bg">
                            <div class="section-label"><i class="bi bi-shield"></i> Rol de Acceso</div>
                            <select name="rol" id="u_rol" class="input-jv" required>
                                <option value="Operador de Carga">Operador de Carga (Entradas)</option>
                                <option value="Operador de Ventas">Operador de Ventas (Salidas)</option>
                            </select>
                        </div>

                        <div class="section-bg mb-4">
                            <div class="section-label"><i class="bi bi-toggle-on"></i> Estado</div>
                            <select name="status" id="u_status" class="input-jv" required>
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>

                        <div class="section-bg mb-4">
                            <div class="section-label"><i class="bi bi-question-circle"></i> Pregunta de Seguridad</div>
                            <select name="pregunta_seguridad" id="u_preg" class="input-jv">
                                <option value="">Sin cambiar / No tiene</option>
                                <option value="Nombre de tu mascota">Nombre de tu mascota</option>
                                <option value="Ciudad donde naciste">Ciudad donde naciste</option>
                                <option value="Nombre de tu mejor amigo">Nombre de tu mejor amigo</option>
                                <option value="Comida favorita">Comida favorita</option>
                                <option value="Nombre de tu escuela primaria">Nombre de tu escuela primaria</option>
                                <option value="Apellido de tu abuela materna">Apellido de tu abuela materna</option>
                                <option value="Marca de tu primer auto">Marca de tu primer auto</option>
                                <option value="Color favorito">Color favorito</option>
                            </select>
                            <small class="text-jv-muted mt-1 d-block" style="font-size:.7rem;">Selecciona una pregunta o déjalo vacío para mantener la actual.</small>
                            <input type="text" name="respuesta_seguridad" id="u_resp" class="input-jv mt-2" maxlength="50" placeholder="Tu respuesta personalizada" autocomplete="off">
                        </div>

                        <button type="submit" id="btn-user-submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase" disabled>
                            <i class="bi bi-shield-check me-2"></i>GUARDAR CAMBIOS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php // ==========================================
    // JAVASCRIPT
    // ========================================== ?>
    <script src="<?php echo $base_assets; ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base_assets; ?>js/sweetalert2.all.min.js"></script>
    <script>
        const modalU = new bootstrap.Modal(document.getElementById('modalUser'));

        function togglePassword() {
            const passInput = document.getElementById('u_pass');
            const icon = document.getElementById('toggleIcon');
            if (passInput.type === "password") {
                passInput.type = "text";
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye-fill');
            } else {
                passInput.type = "password";
                icon.classList.remove('bi-eye-fill');
                icon.classList.add('bi-eye-slash-fill');
            }
        }

        function limpiarErrores() {
            document.querySelectorAll('.input-error').forEach(function(el) { el.classList.remove('input-error'); });
            document.querySelectorAll('.field-error').forEach(function(el) { el.remove(); });
        }
        function marcarError(el, msg) {
            el.classList.add('input-error');
            if (msg && el.id) {
                var errEl = document.getElementById(el.id + '_err');
                if (!errEl) {
                    errEl = document.createElement('small');
                    errEl.id = el.id + '_err';
                    errEl.className = 'field-error';
                    errEl.style.cssText = 'color:#ef4444;font-size:.7rem;margin-top:2px;display:block;';
                    el.parentNode.appendChild(errEl);
                }
                errEl.textContent = msg;
            }
        }
        function validarFormulario() {
            const user = document.getElementById('u_nombre').value.trim();
            const pass = document.getElementById('u_pass').value;
            const correo = document.getElementById('u_correo').value.trim();
            const btn = document.getElementById('btn-user-submit');
            const uError = document.getElementById('u_error_text');
            const fill = document.getElementById('meter-fill');
            const meterText = document.getElementById('meter-text');

            const userRegex = /^[a-zA-Z0-9_]{4,}$/;
            const userValido = userRegex.test(user);
            if (uError) {
                if (user.length > 0) {
                    uError.className = userValido ? 'text-jv-success mt-1 d-block fw-bold' : 'text-jv-danger mt-1 d-block fw-bold';
                } else {
                    uError.className = 'text-info mt-1 d-block fw-bold';
                }
            }
            const correoValido = correo === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo);

            if (pass.length > 0) {
                let score = 0;
                if (pass.length >= 8) score++;
                if (/[a-z]/.test(pass)) score++;
                if (/[A-Z]/.test(pass)) score++;
                if (/[0-9]/.test(pass)) score++;
                if (/[\W_]/.test(pass)) score++;

                const colors = ['#ef4444', '#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
                const widths = ['20%', '40%', '60%', '80%', '100%'];
                if (fill) {
                    fill.style.width = widths[score - 1] || '10%';
                    fill.style.backgroundColor = colors[score - 1] || colors[0];
                }

                if (meterText) {
                    if (score < 3) { meterText.textContent = 'Contraseña débil'; meterText.className = 'text-jv-danger mt-1 d-block fw-bold'; }
                    else if (score < 5) { meterText.textContent = 'Contraseña aceptable'; meterText.className = 'text-jv-warning mt-1 d-block fw-bold'; }
                    else { meterText.textContent = 'Contraseña fuerte'; meterText.className = 'text-jv-success mt-1 d-block fw-bold'; }
                }
            } else {
                if (fill) fill.style.width = '0%';
                if (meterText) { meterText.textContent = 'Mín. 8 caracteres: Mayúsculas, Minúsculas, Números y Símbolos.'; meterText.className = 'text-info mt-1 d-block fw-bold'; }
            }

            // Highlight errores
            document.getElementById('u_nombre').classList.toggle('input-error', !userValido && user.length > 0);
            document.getElementById('u_correo').classList.toggle('input-error', !correoValido && correo.length > 0);

            var esEdicion = document.getElementById('u_accion').value === 'editar';
            if (esEdicion && pass === '') { btn.disabled = !userValido; return; }
            btn.disabled = !userValido || !correoValido;
        }

        function editarUsuario(data) {
            document.getElementById('u_accion').value = "editar";
            document.getElementById('u_id_edit').value = data.id_usuario;
            document.getElementById('modalTitle').innerText = "EDITAR USUARIO";
            document.getElementById('u_nombre').value = data.usuario;
            document.getElementById('u_correo').value = data.correo || "";
            document.getElementById('u_pass').value = "";
            document.getElementById('u_pass').required = false;
            document.getElementById('passHelp').style.display = "block";

            document.getElementById('u_pass').type = "password";
            document.getElementById('toggleIcon').className = "bi bi-eye-slash-fill text-secondary";

            document.getElementById('meter-fill').style.width = '0%';
            document.getElementById('btn-user-submit').disabled = false;

            const selectRol = document.getElementById('u_rol');
            selectRol.value = data.rol;
            const esPropio = (data.id_usuario == "<?php echo $id_propio; ?>");
            selectRol.disabled = esPropio;

            const selectStatus = document.getElementById('u_status');
            if (selectStatus) selectStatus.value = data.status || 'Activo';

            const selectPreg = document.getElementById('u_preg');
            if (selectPreg) {
                selectPreg.value = data.pregunta_seguridad || '';
            }
            const inputResp = document.getElementById('u_resp');
            if (inputResp) inputResp.value = '';

            modalU.show();
        }

        function confirmarToggle(id, nombre, accion) {
            const esSuspender = (accion === 'suspender');
            Swal.fire({
                title: esSuspender ? '¿SUSPENDER USUARIO?' : '¿REACTIVAR USUARIO?',
                text: esSuspender ? `El usuario ${nombre} ya no podrá acceder al sistema.` : `Se restaurará el acceso al sistema para ${nombre}.`,
                icon: esSuspender ? 'warning' : 'info',
                showCancelButton: true,
                confirmButtonColor: esSuspender ? '#ef4444' : '#22c55e',
                cancelButtonColor: '#1e293b',
                confirmButtonText: esSuspender ? 'SÍ, SUSPENDER' : 'SÍ, ACTIVAR',
                cancelButtonText: 'CANCELAR',
                background: '#0f172a',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = `usuarios.php?toggle_status=${id}`;
            });
        }
    </script>
    <script>
        // Sincronizar main-wrapper con sidebar
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
    document.querySelectorAll('#formUsuario input, #formUsuario select, #formUsuario textarea').forEach(function(el) {
        el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
        el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id+'_err'); if(e) e.remove(); });
    });
    </script>
</body>
</html>