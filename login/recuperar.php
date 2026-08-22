<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();

// ==========================================
// VERIFICAR SESIÓN
// ==========================================
if (isset($_SESSION['id_usuario'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

// ==========================================
// INICIAR PROCESO DE RECUPERACIÓN
// ==========================================
if (isset($_GET['reset'])) {
    $_SESSION['rec_step'] = 1;
    $_SESSION['rec_id'] = 0;
    unset($_SESSION['rec_user'], $_SESSION['rec_pregunta'], $_SESSION['rec_intentos']);
}

$error = '';
$exito = '';
$step = 1;
$user_found = null;

$csrf_token = Security::generateToken();

if (!isset($_SESSION['rec_step'])) $_SESSION['rec_step'] = 1;
if (!isset($_SESSION['rec_id'])) $_SESSION['rec_id'] = 0;

// ==========================================
// PROCESAR PETICIONES POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['rec_action'] ?? '';

    // --- Step 1: Search by email or username ---
    if ($action === 'buscar') {
        $recoveryInput = trim($_POST['rec_input'] ?? '');
        if ($recoveryInput === '') {
            $error = "INGRESE SU CORREO O NOMBRE DE USUARIO.";
        } else {
            if (filter_var($recoveryInput, FILTER_VALIDATE_EMAIL)) {
                $normalizedRecoveryInput = strtolower($recoveryInput);
                $userAccount = $db->fetchOne("SELECT id_usuario, usuario, pregunta_seguridad FROM usuarios WHERE LOWER(correo) = ? LIMIT 1", [$normalizedRecoveryInput]);
            } else {
                $userAccount = $db->fetchOne("SELECT id_usuario, usuario, pregunta_seguridad FROM usuarios WHERE LOWER(usuario) = ? LIMIT 1", [strtolower($recoveryInput)]);
            }
            if ($userAccount && !empty($userAccount['pregunta_seguridad'])) {
                $_SESSION['rec_id'] = $userAccount['id_usuario'];
                $_SESSION['rec_user'] = $userAccount['usuario'];
                $_SESSION['rec_pregunta'] = $userAccount['pregunta_seguridad'];
                $_SESSION['rec_step'] = 2;
                $_SESSION['rec_intentos'] = 0;
            } else {
                $error = "NO SE ENCONTRÓ UNA CUENTA CON ESE DATO. VERIFÍQUELO O CONTACTE AL ADMINISTRADOR.";
            }
        }
    }

    // --- Step 2: Answer security question ---
    elseif ($action === 'responder') {
        if ($_SESSION['rec_step'] != 2 || $_SESSION['rec_id'] == 0) {
            $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
            $_SESSION['rec_step'] = 1;
        } else {
            $securityAnswer = trim($_POST['rec_respuesta'] ?? '');
            if (!validarRespuestaSeguridad($securityAnswer)) {
                $error = "RESPUESTA DE SEGURIDAD INVÁLIDA. ESCRIBE AL MENOS UN CARACTER.";
            } else {
                $securityAnswerRecord = $db->fetchOne("SELECT respuesta_seguridad FROM usuarios WHERE id_usuario = ? LIMIT 1", [$_SESSION['rec_id']]);
                if ($securityAnswerRecord && verificarRespuestaSeguridad($securityAnswer, $securityAnswerRecord['respuesta_seguridad'])) {
                    $_SESSION['rec_step'] = 3;
                } else {
                    $_SESSION['rec_intentos'] = ($_SESSION['rec_intentos'] ?? 0) + 1;
                    if ($_SESSION['rec_intentos'] >= 3) {
                        $error = "DEMASIADOS INTENTOS. INICIE EL PROCESO DE NUEVO.";
                        $_SESSION['rec_step'] = 1;
                        $_SESSION['rec_id'] = 0;
                    } else {
                        $remainingAttempts = 3 - $_SESSION['rec_intentos'];
                        $error = "RESPUESTA INCORRECTA. INTENTOS RESTANTES: $remainingAttempts";
                    }
                }
            }
        }
    }

    // --- Step 3: Change password ---
    elseif ($action === 'cambiar') {
        if ($_SESSION['rec_step'] != 3 || $_SESSION['rec_id'] == 0) {
            $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
            $_SESSION['rec_step'] = 1;
        } else {
            $newPassword = $_POST['rec_password'] ?? '';
            $newPasswordConfirmation = $_POST['rec_password2'] ?? '';
            if (!validarPasswordFuerte($newPassword)) {
                $error = "CONTRASEÑA DÉBIL: MÍN 8 CARACTERES, MAYÚSCULAS, NÚMEROS Y SÍMBOLOS.";
            } elseif ($newPassword !== $newPasswordConfirmation) {
                $error = "LAS CONTRASEÑAS NO COINCIDEN.";
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $db->execute("UPDATE usuarios SET password = ? WHERE id_usuario = ?", [$passwordHash, $_SESSION['rec_id']]);
                registrarAuditoria('editar', 'Contraseña recuperada por pregunta de seguridad');
                $exito = "CONTRASEÑA ACTUALIZADA. YA PUEDES INICIAR SESIÓN.";
                $_SESSION['rec_step'] = 4;
            }
        }
    }
}

// ==========================================
// LÓGICA DE VISTAS
// ==========================================
$step = $_SESSION['rec_step'] ?? 1;
$show_buscar = ($step == 1);
$show_pregunta = ($step == 2);
$show_cambiar = ($step == 3);
$show_exito = ($step == 4);

// Reset session on successful completion or error redirect
if ($step == 4) {
    $_SESSION['rec_step'] = 1;
    $_SESSION['rec_id'] = 0;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | JV3000 C.A.</title>
    <?php include '../includes/diseno.php'; ?>
    <link rel="stylesheet" href="../assets/login/recuperar.css?v=3">
</head>

<body class="rec-page">
    <div class="rec-card">
        <img class="rec-logo" src="../assets/img/logo-jv3000.svg?v=1" alt="JV3000 C.A.">
        <div class="rec-header">
            <div class="icon"><i class="bi bi-key"></i></div>
            <h1>RECUPERAR ACCESO</h1>
            <p>Verificación por pregunta de seguridad</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-jv alert-jv-danger mb-3 py-2 px-3" style="font-size:.8rem;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert-jv alert-jv-success mb-3 py-2 px-3" style="font-size:.8rem;"><?php echo htmlspecialchars($exito); ?></div>
        <?php endif; ?>

        <?php // ==========================================
        // PASO 1: BUSCAR POR CORREO O USUARIO
        // ========================================== 
        ?>
        <div class="rec-step <?php echo $show_buscar ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rec_action" value="buscar">
                <label class="small fw-bold mb-1 d-block"><span class="text-jv-warning"><i class="bi bi-envelope me-1"></i>Correo</span> <span class="text-jv-muted">o</span> <span class="text-jv-info"><i class="bi bi-person me-1"></i>Usuario</span></label>
                <input type="text" name="rec_input" class="rec-input mb-3" required placeholder="admin@correo.com  o  Usuario" autofocus>
                <button type="submit" class="rec-btn"><i class="bi bi-search me-2"></i>BUSCAR</button>
                <a href="login.php" class="rec-back"><i class="bi bi-arrow-left me-1"></i>Volver al inicio</a>
            </form>
        </div>

        <?php // ==========================================
        // PASO 2: RESPONDER PREGUNTA
        // ========================================== 
        ?>
        <div id="rec-step-pregunta" class="rec-step <?php echo $show_pregunta ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rec_action" value="responder">
                <div class="small text-jv-muted mb-2">Usuario: <strong style="color:var(--jv-text-primary)"><?php echo htmlspecialchars($_SESSION['rec_user'] ?? ''); ?></strong></div>
                <div class="rec-question"><i class="bi bi-question-circle me-2"></i><?php echo htmlspecialchars($_SESSION['rec_pregunta'] ?? ''); ?></div>
                <input type="text" name="rec_respuesta" id="rec-resp" class="rec-input mb-3" required maxlength="255" autofocus placeholder="Escribe tu respuesta" autocomplete="off" oninput="validarRespuesta()">
                <small id="rec-resp-hint" style="color:#DC2626;font-size:.7rem;display:block;height:14px;text-align:center;"></small>
                <button type="submit" id="rec-btn" class="rec-btn"><i class="bi bi-shield-check me-2"></i>VERIFICAR</button>
                <a href="recuperar.php?reset=1" class="rec-back"><i class="bi bi-arrow-left me-1"></i>Intentar con otro correo</a>
            </form>
        </div>

        <?php // ==========================================
        // PASO 3: NUEVA CONTRASEÑA
        // ========================================== 
        ?>
        <div class="rec-step <?php echo $show_cambiar ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rec_action" value="cambiar">
                <label class="small fw-bold text-jv-muted mb-1 d-block">Nueva contraseña</label>
                <input type="password" name="rec_password" id="rec-pass" class="rec-input mb-1" required minlength="8" placeholder="Min. 8 caracteres" autofocus oninput="validarPassRec()">
                <div class="strength-meter mb-3" style="height:4px;background:var(--jv-border);border-radius:4px;overflow:hidden;">
                    <div class="strength-fill" id="rec-meter" style="height:100%;width:0%;border-radius:4px;transition:all .35s ease;"></div>
                </div>
                <label class="small fw-bold text-jv-muted mb-1 d-block">Confirmar contraseña</label>
                <input type="password" name="rec_password2" id="rec-pass2" class="rec-input mb-3" required minlength="8" placeholder="Repite la contraseña" oninput="validarPassRec()">
                <small id="rec-pass-hint" style="color:var(--jv-text-muted);font-size:.7rem;display:block;height:16px;text-align:center;margin-top:-10px;margin-bottom:10px;"></small>
                <button type="submit" id="rec-btn-pass" class="rec-btn"><i class="bi bi-check2 me-2"></i>CAMBIAR CONTRASEÑA</button>
                <a href="recuperar.php?reset=1" class="rec-back"><i class="bi bi-arrow-left me-1"></i>Cancelar</a>
            </form>
        </div>

        <?php // ==========================================
        // PASO 4: ÉXITO
        // ========================================== 
        ?>
        <div class="rec-step <?php echo $show_exito ? 'active' : ''; ?>">
            <div class="text-center">
                <div style="font-size:3rem;color:var(--jv-success);margin-bottom:12px;"><i class="bi bi-check-circle-fill"></i></div>
                <a href="login.php" class="rec-btn text-decoration-none d-inline-block" style="width:auto;padding:12px 32px;">
                    <i class="bi bi-box-arrow-in-right me-2"></i>IR AL INICIO
                </a>
            </div>
        </div>
    </div>

    <script src="../assets/login/recuperar.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js?v=2"></script>
</body>

</html>