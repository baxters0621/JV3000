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
        $rol_final = ($id_target == $id_propio) ? (int)$_SESSION['id_rol'] : (int)$_POST['id_rol'];
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
            if (!validarPasswordFuerte($password)) {
                $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'CONTRASEÑA DÉBIL: MÍN 8 CARACTERES, MAYÚSCULAS, NÚMEROS Y SÍMBOLOS.'];
                header("Location: usuarios.php"); exit();
            }
            $pass_hash = password_hash($password, PASSWORD_BCRYPT);
            $db->execute("UPDATE usuarios SET usuario=?, correo=?, password=?, id_rol=?, status=?, aprobado=? WHERE id_usuario=?", 
                [$usuario, $correo, $pass_hash, $rol_final, $status_final, ($status_final == 'Activo' ? 1 : 0), $id_target]);
        } else {
            $db->execute("UPDATE usuarios SET usuario=?, correo=?, id_rol=?, status=?, aprobado=? WHERE id_usuario=?", 
                [$usuario, $correo, $rol_final, $status_final, ($status_final == 'Activo' ? 1 : 0), $id_target]);
        }

        $pregunta = trim($_POST['pregunta_seguridad'] ?? '');
        $respuesta = trim($_POST['respuesta_seguridad'] ?? '');
        if ($pregunta !== '' && $respuesta !== '') {
            if (!validarRespuestaSeguridad($respuesta)) {
                $_SESSION['flash_msg'] = ['tipo'=>'danger','texto'=>'RESPUESTA INVÁLIDA. MÍN 5 Y MÁX 20 CARACTERES, DEBE TENER VOCALES, SIN PATRONES (asdf, qwerty, etc).'];
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
if (isset($_POST['toggle_status'])) {
    $id_target = intval($_POST['toggle_status']);
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
$roles_lista = $db->fetchAll("SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol");
$usuarios = $db->fetchAll("SELECT u.id_usuario, u.usuario, u.correo, u.id_rol, r.nombre_rol, u.status, COALESCE(u.aprobado, 1) as aprobado, u.pregunta_seguridad FROM usuarios u LEFT JOIN roles r ON u.id_rol = r.id_rol ORDER BY u.usuario ASC");

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
        <link rel="stylesheet" href="assets/dashboard/usuarios.css">
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
                <h1 class="font-brand mb-1" style="font-size:1.8rem;letter-spacing:-1px; color: var(--jv-text-primary);">COLABORADORES</h1>
                <p class="text-secondary small fw-bold text-uppercase mb-0">Gestión de Personal Autorizado</p>
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
                    <div class="widget-icon" style="background:rgba(108,117,125,0.12);color:var(--jv-text-muted);">
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
                    <div class="widget-icon" style="background:rgba(25,135,84,0.12);color:var(--jv-success);">
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
                    <div class="widget-icon" style="background:rgba(234,88,12,0.12);color:var(--jv-orange);">
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
                <i class="bi bi-person-lines-fill" style="color:var(--jv-navy);"></i>
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
                                        $role_text = $row['nombre_rol'] ?? '';
                                        if (empty($role_text)) { $role_text = 'SIN ROL'; }
                                        if ($row['id_rol'] == 1) $role_class = 'badge-warning';
                                        if ($row['id_rol'] == 2 || $row['id_rol'] == 3) $role_class = 'badge-success';
                                        ?>
                                        <span class="badge-jv <?php echo $role_class; ?>"><?php echo $role_text; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['aprobado'] == 1): ?>
                                            <i class="bi bi-check-circle-fill fs-5" style="color:var(--jv-success);"></i>
                                        <?php else: ?>
                                            <i class="bi bi-hourglass-split fs-5" style="color:var(--jv-warning);"></i>
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
                            <h5 class="fw-bolder font-brand m-0" id="modalTitle" style="color:var(--jv-navy);letter-spacing:-.5px;">EDITAR USUARIO</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            <select name="id_rol" id="u_rol" class="input-jv" required>
                                <?php foreach ($roles_lista as $rl): ?>
                                    <option value="<?php echo $rl['id_rol']; ?>"><?php echo htmlspecialchars($rl['nombre_rol']); ?></option>
                                <?php endforeach; ?>
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
                            <input type="text" name="respuesta_seguridad" id="u_resp" class="input-jv mt-2" maxlength="20" oninput="validarFormulario()" placeholder="Mín. 5 y máx. 20 caracteres" autocomplete="off">
                            <small id="u_resp_hint" class="field-error" style="color:var(--jv-danger);font-size:.7rem;margin-top:2px;display:block;height:14px;"></small>
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
    window.JV_CONFIG = { c0: <?php echo $id_propio; ?>, c1: '<?php echo $csrf_token; ?>' };
</script>
    <script src="assets/dashboard/usuarios.js"></script>
    
    
</body>
</html>