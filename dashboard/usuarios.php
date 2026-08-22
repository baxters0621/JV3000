<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
Security::soloAdmin();

$base_assets = BASE_PATH . 'assets/';
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
        $password = $_POST['password'] ?? '';
        $rol_final = ($id_target == $id_propio) ? (int)$_SESSION['id_rol'] : (int)$_POST['id_rol'];
        $status_final = ($id_target == $id_propio) ? 'Activo' : ($_POST['status'] ?? 'Activo');

        if ($db->fetchOne("SELECT id_usuario FROM usuarios WHERE LOWER(usuario) = LOWER(?) AND id_usuario != ?", [$usuario, $id_target])) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL USUARIO YA EXISTE.'];
            header("Location: usuarios.php");
            exit();
        }

        $correo_valido = !empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL);
        if (!empty($correo) && !$correo_valido) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'CORREO ELECTRÓNICO INVÁLIDO.'];
            header("Location: usuarios.php");
            exit();
        }

        if (!empty($correo) && $db->fetchOne("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario != ?", [$correo, $id_target])) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL CORREO YA ESTÁ EN USO.'];
            header("Location: usuarios.php");
            exit();
        }

        if (strlen($usuario) < 4 || !preg_match('/^[a-zA-Z0-9_]+$/', $usuario)) {
            $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'EL USUARIO DEBE TENER MÍN 4 CARACTERES (letras, números, guion bajo).'];
            header("Location: usuarios.php");
            exit();
        }

        if (!empty($password)) {
            if (!validarPasswordFuerte($password)) {
                $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'CONTRASEÑA DÉBIL: MÍN 8 CARACTERES, MAYÚSCULAS, NÚMEROS Y SÍMBOLOS.'];
                header("Location: usuarios.php");
                exit();
            }
            $pass_hash = password_hash($password, PASSWORD_BCRYPT);
            $db->execute(
                "UPDATE usuarios SET usuario=?, correo=?, password=?, id_rol=?, status=?, aprobado=? WHERE id_usuario=?",
                [$usuario, $correo, $pass_hash, $rol_final, $status_final, ($status_final == 'Activo' ? 1 : 0), $id_target]
            );
        } else {
            $db->execute(
                "UPDATE usuarios SET usuario=?, correo=?, id_rol=?, status=?, aprobado=? WHERE id_usuario=?",
                [$usuario, $correo, $rol_final, $status_final, ($status_final == 'Activo' ? 1 : 0), $id_target]
            );
        }

        $pregunta = trim($_POST['pregunta_seguridad'] ?? '');
        $respuesta = trim($_POST['respuesta_seguridad'] ?? '');
        if ($pregunta !== '' && $respuesta !== '') {
            if (!validarRespuestaSeguridad($respuesta)) {
                $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'RESPUESTA DE SEGURIDAD INVÁLIDA. ESCRIBE AL MENOS UN CARACTER.'];
                header("Location: usuarios.php");
                exit();
            }
            $resp_hash = password_hash(normalizarRespuestaSeguridad($respuesta), PASSWORD_BCRYPT);
            $db->execute("UPDATE usuarios SET pregunta_seguridad = ?, respuesta_seguridad = ? WHERE id_usuario = ?", [$pregunta, $resp_hash, $id_target]);
        }

        registrarAuditoria('editar', 'Usuario modificado');
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'COLABORADOR ACTUALIZADO.'];
        header("Location: usuarios.php");
        exit();
    }
}

// ==========================================
// CAMBIAR ESTADO DE USUARIO
// ==========================================
if (isset($_POST['aprobar_usuario'])) {
    $id_target = intval($_POST['aprobar_usuario']);
    $rol_aprobado = intval($_POST['id_rol'] ?? 0);
    if ($id_target == $id_propio || !in_array($rol_aprobado, [2, 3], true)) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'SELECCIONA UN ROL OPERATIVO VÁLIDO.'];
        header("Location: usuarios.php");
        exit();
    }
    $pending_user = $db->fetchOne("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND COALESCE(aprobado,0) = 0", [$id_target]);
    if (!$pending_user) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'LA CUENTA NO ESTÁ PENDIENTE DE APROBACIÓN.'];
        header("Location: usuarios.php");
        exit();
    }
    $db->execute("UPDATE usuarios SET id_rol = ?, status = 'Activo', aprobado = 1 WHERE id_usuario = ?", [$rol_aprobado, $id_target]);
    registrarAuditoria('aprobar', 'Usuario aprobado y rol asignado');
    $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'COLABORADOR APROBADO Y ROL ASIGNADO.'];
    header("Location: usuarios.php");
    exit();
}

if (isset($_POST['toggle_status'])) {
    $id_target = intval($_POST['toggle_status']);
    if ($id_target == $id_propio) {
        $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'NO PUEDES DESACTIVAR TU PROPIA CUENTA.'];
        header("Location: usuarios.php");
        exit();
    }
    $row = $db->fetchOne("SELECT status FROM usuarios WHERE id_usuario = ?", [$id_target]);
    if ($row) {
        $nuevo_status = ($row['status'] == 'Activo') ? 'Inactivo' : 'Activo';
        $nuevo_aprobado = ($nuevo_status == 'Activo') ? 1 : 0;
        $db->execute("UPDATE usuarios SET status = ?, aprobado = ? WHERE id_usuario = ?", [$nuevo_status, $nuevo_aprobado, $id_target]);
        registrarAuditoria('toggle_status', 'Cambio de estado');
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'ESTADO DEL COLABORADOR CAMBIADO.'];
        header("Location: usuarios.php");
        exit();
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
// ==========================================
?>

<head>
    <?php include '../includes/diseno.php'; ?>
    <title>Colaboradores | JV3000</title>
    <link rel="stylesheet" href="../assets/dashboard/usuarios.css?v=6">
</head>

<body class="usuarios-page">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="pagina-usuarios">
            <div class="container py-5">

                <div class="d-flex align-items-center gap-4 mb-4">
                    <div class="user-header-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <h1 class="module-title">COLABORADORES</h1>
                        <p class="module-subtitle">Gestión de Personal Autorizado</p>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4">
                        <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($flash['texto']); ?>
                    </div>
                <?php endif; ?>

                <?php // ==========================================
                // WIDGETS DE ESTADÍSTICAS
                // ==========================================
                ?>
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
                // ==========================================
                ?>
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
                                    <th style="width:70px;">#</th>
                                    <th style="width:20%;">USUARIO</th>
                                    <th style="width:26%;">CORREO</th>
                                    <th style="width:15%;">ROL</th>
                                    <th class="text-center" style="width:10%;">APROBADO</th>
                                    <th class="text-center" style="width:10%;">ESTADO</th>
                                    <th class="text-center" style="width:180px;">CONTROL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($usuarios)): ?>
                                    <?php foreach ($usuarios as $row): ?>
                                        <tr>
                                            <td class="text-secondary small"><span class="codigo-badge">#<?php echo $row['id_usuario']; ?></span></td>
                                            <td class="fw-bold usuario-cell" title="<?php echo htmlspecialchars($row['usuario']); ?>">
                                                <?php echo htmlspecialchars($row['usuario']); ?>
                                            </td>
                                            <td class="text-secondary small correo-cell" title="<?php echo htmlspecialchars($row['correo'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($row['correo'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php
                                                $role_class = 'badge-secondary';
                                                $role_text = $row['nombre_rol'] ?? '';
                                                if (empty($role_text)) {
                                                    $role_text = 'SIN ROL';
                                                }
                                                if ($row['id_rol'] == 1) $role_class = 'badge-warning';
                                                if ($row['id_rol'] == 2 || $row['id_rol'] == 3) $role_class = 'badge-success';
                                                ?>
                                                <span class="role-badge <?php echo $role_class; ?>" title="<?php echo htmlspecialchars($role_text); ?>"><?php echo $role_text; ?></span>
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
                                                <div class="d-flex justify-content-center">
                                                    <?php if ($row['id_usuario'] == $id_propio): ?>
                                                        <span class="badge-jv badge-secondary" style="font-size:.72rem;padding:6px 12px;"><i class="bi bi-lock-fill me-1"></i>CUENTA PRINCIPAL</span>
                                                    <?php elseif ((int)$row['aprobado'] === 0): ?>
                                                        <form method="POST" class="d-flex align-items-center gap-1">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                            <input type="hidden" name="aprobar_usuario" value="<?php echo (int)$row['id_usuario']; ?>">
                                                            <select name="id_rol" class="input-jv" style="width:145px;padding:8px 10px;font-size:.75rem;" aria-label="Rol para <?php echo htmlspecialchars($row['usuario']); ?>" required>
                                                                <option value="">Asignar rol...</option>
                                                                <?php foreach ($roles_lista as $role): if (in_array((int)$role['id_rol'], [2, 3], true)): ?>
                                                                        <option value="<?php echo (int)$role['id_rol']; ?>"><?php echo htmlspecialchars($role['nombre_rol']); ?></option>
                                                                <?php endif;
                                                                endforeach; ?>
                                                            </select>
                                                            <button type="submit" class="btn-suspend btn-aprobar" title="Aprobar usuario">
                                                                <i class="bi bi-person-check-fill me-1"></i>APROBAR
                                                            </button>
                                                        </form>
                                                    <?php elseif ($row['status'] == 'Activo'): ?>
                                                        <button class="btn-suspend" onclick="confirmarToggle(<?php echo (int)$row['id_usuario']; ?>, <?php echo htmlspecialchars(json_encode($row['usuario'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, 'suspender')">
                                                            <i class="bi bi-person-lock me-1"></i>SUSPENDER
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-suspend btn-reactivar" onclick="confirmarToggle(<?php echo (int)$row['id_usuario']; ?>, <?php echo htmlspecialchars(json_encode($row['usuario'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, 'activar')">
                                                            <i class="bi bi-person-check-fill me-1"></i>REACTIVAR
                                                        </button>
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
    // JAVASCRIPT
    // ==========================================
    ?>
    <script src="<?php echo $base_assets; ?>js/bootstrap.bundle.min.js"></script>
    <script>
        window.JV_CONFIG = {
            csrfToken: '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>'
        };
    </script>
    <script src="../assets/dashboard/usuarios.js?v=4"></script>


</body>

</html>