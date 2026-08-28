<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
$base_assets = BASE_PATH . 'assets/';

// ==========================================
// VERIFICAR SESIÓN Y REDIRIGIR
// ==========================================
if (isset($_SESSION['id_usuario'])) {
    $userCheck = Database::getInstance()->fetchOne("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND status = 'Activo' AND COALESCE(aprobado,0) = 1 LIMIT 1", [$_SESSION['id_usuario']]);
    if ($userCheck) {
        header("Location: ../dashboard/index.php");
        exit();
    }
    session_destroy();
}

$error = "";
$exito = "";
$intentos_actuales = 0;
$max_intentos = 3;
$tiempo_bloqueo = 30; // segundos

// ==========================================
// MENSAJES DE ERROR DESDE URL
// ==========================================
$error_get = $_GET['error'] ?? '';
switch ($error_get) {
    case 'cuenta_pendiente':
        $error = 'TU CUENTA ESTÁ PENDIENTE DE APROBACIÓN. CONTACTA AL ADMINISTRADOR.';
        break;
    case 'cuenta_desactivada':
        $error = 'TU CUENTA ESTÁ DESACTIVADA. CONTACTA AL ADMINISTRADOR.';
        break;
    case 'acceso_denegado':
    case 'acceso_prohibido':
        $error = 'ACCESO DENEGADO. NO TIENES PERMISOS PARA ESTA SECCIÓN.';
        break;
    case 'expired':
        $error = 'SESIÓN EXPIRADA. LA PESTAÑA ANTERIOR FUE CERRADA. VUELVE A INICIAR SESIÓN.';
        break;
}

$sistema_vacio = ($db->fetchOne("SELECT COUNT(*) as total FROM usuarios")['total'] == 0);

$csrf_token = Security::generateToken();

$preguntas_opciones = getPreguntasRespuestas();

// ==========================================
// LIMPIAR INTENTOS EXPIRADOS
// ==========================================
$db->execute("DELETE FROM login_intentos WHERE TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) >= ?", [$tiempo_bloqueo]);

// ==========================================
// VERIFICAR BLOQUEO POR IP
// ==========================================
$segundos_restantes = 0;
$ip_actual = $_SERVER['REMOTE_ADDR'];
$user_check = trim($_POST['usuario'] ?? '');
// Lockout dual: verificar por IP O por usuario
if ($user_check !== '') {
    $row_rest = $db->fetchOne("SELECT intentos, $tiempo_bloqueo - TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) as restante FROM login_intentos WHERE (ip_address = ? OR usuario = ?) AND intentos >= ? AND TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) < ? ORDER BY restante DESC LIMIT 1", [$ip_actual, $user_check, $max_intentos, $tiempo_bloqueo]);
} else {
    $row_rest = $db->fetchOne("SELECT intentos, $tiempo_bloqueo - TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) as restante FROM login_intentos WHERE ip_address = ? AND intentos >= ? AND TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) < ?", [$ip_actual, $max_intentos, $tiempo_bloqueo]);
}
if ($row_rest && $row_rest['restante'] > 0) {
    $segundos_restantes = (int)$row_rest['restante'];
    $error = "ACCESO BLOQUEADO - Espere $segundos_restantes segundos";
}

// ==========================================
// PROCESAR REGISTRO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_registro'])) {
    $new_user = trim($_POST['reg_usuario']);
    $new_email = strtolower(trim($_POST['reg_email']));
    $new_pass = $_POST['reg_password'];

    if (strlen($new_user) > 20) {
        $error = "USUARIO DEMASIADO LARGO (MAX 20 CARACTERES)";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $new_user)) {
        $error = "USUARIO: MIN 4 Y MAX 20 CARACTERES (letras, numeros, guion bajo)";
    } elseif (strlen($new_email) > 50) {
        $error = "CORREO DEMASIADO LARGO (MAX 50 CARACTERES)";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "CORREO: FORMATO INVALIDO";
    } elseif (!validarPasswordFuerte($new_pass)) {
        $faltan = requisitosFaltantesPassword($new_pass);
        $error = "CONTRASEÑA DÉBIL: FALTA $faltan.";
    } elseif ($new_pass !== $_POST['reg_password_confirm']) {
        $error = "LAS CONTRASEÑAS NO COINCIDEN";
    } else {
        $reg_pregunta = trim($_POST['reg_pregunta'] ?? '');
        $reg_respuesta = trim($_POST['reg_respuesta'] ?? '');
        if ($reg_pregunta === '' || $reg_respuesta === '') {
            $error = "DEBE SELECCIONAR UNA PREGUNTA DE SEGURIDAD Y SU RESPUESTA.";
        } elseif (!validarRespuestaSeguridad($reg_respuesta)) {
            $error = "RESPUESTA DE SEGURIDAD INVÁLIDA. ESCRIBE AL MENOS UN CARACTER.";
        } else {
            $dup = $db->fetchOne("SELECT id_usuario FROM usuarios WHERE BINARY usuario = ? OR BINARY correo = ?", [$new_user, $new_email]);
            if ($dup) {
                $error = "EL USUARIO O CORREO YA ESTA EN USO";
            } else {
                $pass_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $resp_hash = password_hash(normalizarRespuestaSeguridad($reg_respuesta), PASSWORD_BCRYPT);
                $es_admin = $sistema_vacio;
                $db->insert('usuarios', [
                    'usuario'             => $new_user,
                    'correo'              => $new_email,
                    'password'            => $pass_hash,
                    'id_rol'              => $es_admin ? 1 : NULL,
                    'pregunta_seguridad'  => $reg_pregunta,
                    'respuesta_seguridad' => $resp_hash,
                    'status'              => $es_admin ? 'Activo' : 'Inactivo',
                    'aprobado'            => $es_admin ? 1 : 0,
                ]);
                registrarAuditoria('crear', 'Nuevo usuario registrado');
                $exito = $es_admin
                    ? "ADMINISTRADOR CREADO. YA PUEDES INICIAR SESION."
                    : "REGISTRO EXITOSO. ESPERE A QUE EL ADMINISTRADOR APROBE SU CUENTA.";
            }
        }
    }
}

// ==========================================
// PROCESAR INICIO DE SESIÓN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_login'])) {
    $ip_usuario = $_SERVER['REMOTE_ADDR'];
    $user_login = trim($_POST['usuario']);

    // Lockout dual: verificar por IP O por usuario
    $blocked = $db->fetchOne("SELECT intentos, $tiempo_bloqueo - TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) as restante FROM login_intentos WHERE (ip_address = ? OR usuario = ?) AND intentos >= ? AND TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) < ? ORDER BY restante DESC LIMIT 1", [$ip_usuario, $user_login, $max_intentos, $tiempo_bloqueo]);
    if ($blocked) {
        $segundos_restantes = max(1, (int)$blocked['restante']);
        $error = "ACCESO BLOQUEADO - Espere $segundos_restantes segundos";
    } else {
        $pass = $_POST['password'];

        if (strlen($user_login) > 30) {
            $error = "USUARIO DEMASIADO LARGO (MAX 30 CARACTERES)";
        } else {
            $login_exitoso = false;
            $clave_correcta = false;
            $row = $db->fetchOne("SELECT * FROM usuarios WHERE BINARY usuario = ? LIMIT 1", [$user_login]);
            if ($row) {
                if (password_verify($pass, $row['password'])) {
                    $clave_correcta = true;
                    // Limpiar intentos por IP Y por usuario
                    $db->execute("DELETE FROM login_intentos WHERE ip_address = ? OR usuario = ?", [$ip_usuario, $user_login]);
                    $aprobado = $row['aprobado'] ?? 0;
                    if ($aprobado == 0) {
                        $error = "TU CUENTA ESTA PENDIENTE DE APROBACION. CONTACTA AL ADMINISTRADOR.";
                    } elseif ($row['status'] === 'Inactivo') {
                        $error = "TU CUENTA ESTA DESACTIVADA. CONTACTA AL ADMINISTRADOR.";
                    } else {
                        $login_exitoso = true;
                        session_regenerate_id(true);
                        $_SESSION['id_usuario']   = $row['id_usuario'];
                        $_SESSION['usuario']      = $row['usuario'];
                        $_SESSION['id_rol']       = (int)$row['id_rol'];
                        $_SESSION['ip_addr']      = $_SERVER['REMOTE_ADDR'];
                        $_SESSION['fresh_login']  = true;
                        registrarAuditoria('login', "Inicio de sesión");
                        header("Location: ../dashboard/index.php");
                        exit();
                    }
                }
            }

            // Registrar intento fallido si NO hubo login exitoso (incluye cuentas inactivas/pendientes)
            if (!$login_exitoso) {
                // Registrar por IP
                $db->execute(
                    "INSERT INTO login_intentos (ip_address, usuario, intentos, ultimo_intento) VALUES (?, NULL, 1, NOW())
                     ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = NOW()",
                    [$ip_usuario]
                );
                // Registrar por usuario si existe
                if ($user_login !== '') {
                    $db->execute(
                        "INSERT INTO login_intentos (ip_address, usuario, intentos, ultimo_intento) VALUES ('0.0.0.0', ?, 1, NOW())
                         ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = NOW()",
                        [$user_login]
                    );
                }
                // Obtener intentos (el mayor entre IP y usuario)
                $row_ip = $db->fetchOne("SELECT intentos FROM login_intentos WHERE ip_address = ? LIMIT 1", [$ip_usuario]);
                $row_user = $user_login !== '' ? $db->fetchOne("SELECT intentos FROM login_intentos WHERE usuario = ? LIMIT 1", [$user_login]) : null;
                $intentos_actuales = max((int)($row_ip['intentos'] ?? 0), (int)($row_user['intentos'] ?? 0));
                $restantes = $max_intentos - $intentos_actuales;
                if ($restantes <= 0) {
                    $segundos_restantes = $tiempo_bloqueo;
                    $error = "ACCESO BLOQUEADO POR 30 SEGUNDOS (3 intentos fallidos)";
                } else {
                    $error = "CREDENCIALES INVÁLIDAS (intento $intentos_actuales de $max_intentos)";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include '../includes/diseno.php'; ?>
    <title>JV3000 C.A. | Terminal de Acceso</title>
    <link rel="stylesheet" href="../assets/login/login.css">
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo">
                <img src="../assets/img/logo-jv3000.svg?v=1" alt="JV3000 C.A.">
                <p>Sistema Web para la Gestión de Inventario, Compras y Ventas</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-card-jv <?php echo $segundos_restantes > 0 ? 'alert-card-blocked' : 'alert-card-danger flash-auto'; ?>" id="alerta-bloqueo">
                    <div class="alert-icon-box"><i class="bi bi-shield-slash-fill"></i></div>
                    <div class="alert-body">
                        <div class="alert-title"><?php echo $segundos_restantes > 0 ? 'ACCESO BLOQUEADO' : 'ERROR DE ACCESO'; ?></div>
                        <div class="alert-text"><?php echo htmlspecialchars($error); ?></div>
                        <?php if ($segundos_restantes > 0): ?>
                            <div class="alert-timer" id="alertTimer"><?php echo $segundos_restantes; ?> <small>seg</small></div>
                            <div class="alert-progress">
                                <div class="alert-progress-fill" id="alertProgressFill"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($exito): ?>
                <div class="alert-card-jv alert-card-success flash-auto" id="alerta-exito">
                    <div class="alert-icon-box"><i class="bi bi-shield-check-fill"></i></div>
                    <div class="alert-body">
                        <div class="alert-title">OPERACIÓN EXITOSA</div>
                        <div class="alert-text"><?php echo htmlspecialchars($exito); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php // ==========================================
            // FORMULARIO DE INICIO DE SESIÓN
            // ========================================== 
            ?>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="field-group">
                    <i class="field-icon bi bi-person-fill"></i>
                    <input type="text" id="f-user" name="usuario" class="field-input" placeholder="ID de Operador" required autofocus maxlength="30" <?php echo $segundos_restantes > 0 ? 'disabled' : ''; ?>>
                </div>

                <div class="field-group">
                    <i class="field-icon bi bi-lock-fill"></i>
                    <input type="password" id="f-pass" name="password" class="field-input" placeholder="Clave de Acceso" required maxlength="72" <?php echo $segundos_restantes > 0 ? 'disabled' : ''; ?>>
                    <button type="button" class="field-eye" id="btnEyePass" aria-label="Mostrar contraseña">
                        <i class="bi bi-eye-slash-fill" id="iconEyePass"></i>
                    </button>
                </div>

                <button type="submit" name="btn_login" class="btn-access mt-2" id="btn-login" <?php echo $segundos_restantes > 0 ? 'disabled' : ''; ?>>
                    AUTENTICAR <i class="bi bi-cpu ms-2"></i>
                </button>

                <div class="text-center mt-3">
                    <a href="recuperar.php" class="text-decoration-none text-jv-orange fw-bold" style="font-size:1rem;">
                        <i class="bi bi-question-circle me-1"></i>¿Olvidaste tu contraseña?
                    </a>
                </div>
            </form>

            <div class="divider">Nuevo Personal</div>

            <div class="text-center">
                <a href="#" class="text-decoration-none text-jv-orange fw-bold" style="font-size:0.95rem;" data-bs-toggle="modal" data-bs-target="#modalReg">
                    <i class="bi bi-person-plus me-1"></i>
                    <?php echo $sistema_vacio ? 'Configurar Administrador Inicial' : 'Solicitar Acceso de Personal'; ?>
                </a>
            </div>
        </div>
    </div>

    <?php // ==========================================
    // MODAL DE REGISTRO
    // ========================================== 
    ?>
    <div class="modal fade modal-reg" id="modalReg" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-brand text-jv-orange">
                        <i class="bi bi-shield-plus me-2"></i>
                        <?php echo $sistema_vacio ? 'INSTALACION DE SISTEMA' : 'SOLICITUD DE ACCESO'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="modal-body">

                        <label class="form-label">Nombre de Usuario</label>
                        <div class="field-group">
                            <i class="field-icon bi bi-at"></i>
                            <input type="text" name="reg_usuario" id="r-user" class="field-input" required oninput="validarReg()" maxlength="20" placeholder="Ej: admin_sistema">
                        </div>
                        <small class="reg-hint" id="r-user-hint">Minimo 4 caracteres, solo letras, numeros y guiones bajos.</small>

                        <label class="form-label" style="margin-top:14px;">Correo Electronico</label>
                        <div class="field-group">
                            <i class="field-icon bi bi-envelope-fill"></i>
                            <input type="email" name="reg_email" class="field-input" required maxlength="50" placeholder="ejemplo@jv3000.com">
                        </div>

                        <label class="form-label" style="margin-top:14px;">Contraseña</label>
                        <div class="field-group">
                            <i class="field-icon bi bi-key-fill"></i>
                            <input type="password" name="reg_password" id="r-pass" class="field-input" required oninput="validarReg()" maxlength="20" placeholder="Cree una clave fuerte">
                            <button type="button" class="field-eye" id="btnEyeR1" aria-label="Mostrar">
                                <i class="bi bi-eye-slash-fill" id="iconEyeR1"></i>
                            </button>
                        </div>
                        <div class="strength-meter">
                            <div class="strength-fill" id="r-meter"></div>
                        </div>
                        <small class="reg-hint" id="r-pass-hint">Mín. 8 caracteres, 1 mayúscula, 1 minúscula, 1 número, 1 símbolo.</small>

                        <label class="form-label" style="margin-top:14px;">Confirmar Contraseña</label>
                        <div class="field-group">
                            <i class="field-icon bi bi-key"></i>
                            <input type="password" name="reg_password_confirm" id="r-pass2" class="field-input" required oninput="validarReg()" maxlength="20" placeholder="Repita la contraseña">
                            <button type="button" class="field-eye" id="btnEyeR2" aria-label="Mostrar">
                                <i class="bi bi-eye-slash-fill" id="iconEyeR2"></i>
                            </button>
                            <small class="reg-match" id="r-match-hint"></small>
                        </div>

                        <div class="reg-section-title">Pregunta de Seguridad</div>
                        <div class="reg-section-desc">Se usará para recuperar tu contraseña si la olvidas.</div>

                        <label class="form-label" style="margin-top:14px;">Pregunta</label>
                        <div class="field-group">
                            <i class="field-icon bi bi-question-circle"></i>
                            <select name="reg_pregunta" id="r-preg" class="field-input" required onchange="validarReg()">
                                <option value="">Seleccione una pregunta...</option>
                                <option value="Nombre de tu mascota">Nombre de tu mascota</option>
                                <option value="Ciudad donde naciste">Ciudad donde naciste</option>
                                <option value="Nombre de tu mejor amigo">Nombre de tu mejor amigo</option>
                                <option value="Comida favorita">Comida favorita</option>
                                <option value="Nombre de tu escuela primaria">Nombre de tu escuela primaria</option>
                                <option value="Apellido de tu abuela materna">Apellido de tu abuela materna</option>
                                <option value="Marca de tu primer auto">Marca de tu primer auto</option>
                                <option value="Color favorito">Color favorito</option>
                            </select>
                        </div>

                        <label class="form-label" style="margin-top:10px;">Respuesta</label>
                        <div class="field-group">
                            <i class="field-icon bi bi-shield-lock"></i>
                            <input type="text" name="reg_respuesta" id="r-resp" class="field-input" required maxlength="255" oninput="validarReg()" placeholder="Escribe tu respuesta" autocomplete="off">
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-jv-outline" data-bs-dismiss="modal">CANCELAR</button>
                        <button type="submit" name="btn_registro" id="btn-reg" class="btn btn-jv-primary" disabled>
                            <i class="bi bi-check2 me-2"></i>
                            <?php echo $sistema_vacio ? 'CREAR ADMINISTRADOR' : 'ENVIAR SOLICITUD'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php // ==========================================
    // JAVASCRIPT
    // ========================================== 
    ?>
    <script src="<?php echo $base_assets; ?>js/bootstrap.bundle.min.js?v=2"></script>
    <script>
        window.JV_CONFIG = {
            remainingLockoutSeconds: <?php echo $segundos_restantes; ?>
        };
    </script>
    <script src="../assets/login/login.js"></script>


</body>

</html>