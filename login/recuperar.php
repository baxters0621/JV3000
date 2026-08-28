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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['reset'])) {
    $_SESSION['rec_step'] = 1;
    $_SESSION['rec_id'] = 0;
    unset($_SESSION['rec_user'], $_SESSION['rec_pregunta'], $_SESSION['rec_intentos'], $_SESSION['rec_modo'], $_SESSION['rec_pin_intentos']);
}

// Cambio de método de verificación en el paso 2 (pregunta <-> PIN de emergencia)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['modo']) && isset($_SESSION['rec_step']) && $_SESSION['rec_step'] == 2) {
    $modo_solicitado = $_GET['modo'];
    if (in_array($modo_solicitado, ['pregunta', 'pin'], true)) {
        $_SESSION['rec_modo'] = $modo_solicitado;
        if ($modo_solicitado === 'pin') {
            $_SESSION['rec_pin_intentos'] = 0;
        }
    }
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
                unset($_SESSION['rec_modo'], $_SESSION['rec_pin_intentos']);
            } elseif ($userAccount) {
                // Cuenta encontrada PERO sin pregunta de seguridad: solo se puede avanzar con PIN de emergencia
                $pinRow = $db->fetchOne("SELECT pin_emergencia FROM usuarios WHERE id_usuario = ? LIMIT 1", [$userAccount['id_usuario']]);
                $tiene_pin = $pinRow && !empty($pinRow['pin_emergencia']);
                if ($tiene_pin) {
                    $_SESSION['rec_id'] = $userAccount['id_usuario'];
                    $_SESSION['rec_user'] = $userAccount['usuario'];
                    unset($_SESSION['rec_pregunta']);
                    $_SESSION['rec_modo'] = 'pin';
                    $_SESSION['rec_step'] = 2;
                    $_SESSION['rec_pin_intentos'] = 0;
                } else {
                    $error = "NO SE ENCONTRÓ UNA CUENTA CON ESE DATO. VERIFÍQUELO O CONTACTE AL ADMINISTRADOR.";
                }
            } else {
                $error = "NO SE ENCONTRÓ UNA CUENTA CON ESE DATO. VERIFÍQUELO O CONTACTE AL ADMINISTRADOR.";
            }
        }
    }

    // --- Step 2a: Answer security question ---
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
                    unset($_SESSION['rec_modo'], $_SESSION['rec_pin_intentos']);
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

    // --- Step 2b: Verify emergency PIN (fallback) ---
    elseif ($action === 'pin') {
        if ($_SESSION['rec_step'] != 2 || $_SESSION['rec_id'] == 0) {
            $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
            $_SESSION['rec_step'] = 1;
        } else {
            $pin = trim($_POST['rec_pin'] ?? '');
            if (!preg_match('/^[0-9]{6}$/', $pin)) {
                $error = "PIN DE EMERGENCIA INVÁLIDO. DEBE SER DE 6 DÍGITOS NUMÉRICOS.";
            } else {
                $pinRecord = $db->fetchOne("SELECT pin_emergencia FROM usuarios WHERE id_usuario = ? LIMIT 1", [$_SESSION['rec_id']]);
                if (!$pinRecord || empty($pinRecord['pin_emergencia'])) {
                    $error = "ESTA CUENTA NO TIENE PIN DE EMERGENCIA CONFIGURADO. CONTACTE AL ADMINISTRADOR.";
                    $_SESSION['rec_step'] = 1;
                } elseif (password_verify($pin, $pinRecord['pin_emergencia'])) {
                    $_SESSION['rec_step'] = 3;
                    unset($_SESSION['rec_modo'], $_SESSION['rec_pin_intentos']);
                } else {
                    $_SESSION['rec_pin_intentos'] = ($_SESSION['rec_pin_intentos'] ?? 0) + 1;
                    if ($_SESSION['rec_pin_intentos'] >= 3) {
                        $error = "DEMASIADOS INTENTOS. INICIE EL PROCESO DE NUEVO.";
                        $_SESSION['rec_step'] = 1;
                        $_SESSION['rec_id'] = 0;
                    } else {
                        $remainingAttempts = 3 - $_SESSION['rec_pin_intentos'];
                        $error = "PIN INCORRECTO. INTENTOS RESTANTES: $remainingAttempts";
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
                $pass_check = generarPasswordCheck($newPassword);
                if (existePasswordDuplicado($db, $pass_check, $_SESSION['rec_id'])) {
                    $error = "LA CONTRASEÑA YA ESTA EN USO POR OTRO USUARIO. ELIGE UNA DIFERENTE.";
                } else {
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $db->execute("UPDATE usuarios SET password = ?, password_check = ? WHERE id_usuario = ?", [$passwordHash, $pass_check, $_SESSION['rec_id']]);
                    registrarAuditoria('editar', 'Contraseña recuperada por pregunta de seguridad');
                    $exito = "CONTRASEÑA ACTUALIZADA. YA PUEDES INICIAR SESIÓN.";
                    $_SESSION['rec_step'] = 4;
                }
            }
        }
    }
}

// ==========================================
// LÓGICA DE VISTAS
// ==========================================
$step = $_SESSION['rec_step'] ?? 1;
$modo_rec = $_SESSION['rec_modo'] ?? 'pregunta';
$show_buscar = ($step == 1);
$show_pregunta = ($step == 2 && $modo_rec === 'pregunta' && !empty($_SESSION['rec_pregunta']));
$show_pin = ($step == 2 && $modo_rec === 'pin');
$show_cambiar = ($step == 3);
$show_exito = ($step == 4);

// ¿La cuenta tiene PIN de emergencia configurado para poder enlazar al modo PIN?
$rec_tiene_pin = false;
if (($_SESSION['rec_id'] ?? 0) > 0) {
    $pinCheck = $db->fetchOne("SELECT pin_emergencia FROM usuarios WHERE id_usuario = ? LIMIT 1", [$_SESSION['rec_id']]);
    $rec_tiene_pin = !empty($pinCheck['pin_emergencia']);
}

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
    <link rel="stylesheet" href="../assets/login/login.css?v=2">
    <link rel="stylesheet" href="../assets/login/recuperar.css?v=4">
</head>

<body class="rec-page">
    <div class="rec-card">
        <div class="login-logo">
            <img class="rec-logo" src="../assets/img/logo-jv3000.svg?v=1" alt="JV3000 C.A.">
            <p>Recuperación de Acceso</p>
        </div>

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
                <label class="rec-label"><i class="bi bi-envelope me-1"></i>Correo Electrónico <span class="rec-label-or">o</span> <i class="bi bi-person me-1"></i>Usuario</label>
                <div class="field-group">
                    <i class="field-icon bi bi-person-badge"></i>
                    <input type="text" name="rec_input" class="field-input" required placeholder="ejemplo@correo.com o Usuario" autofocus>
                </div>
                <button type="submit" class="btn-access"><span><i class="bi bi-search me-2"></i>BUSCAR</span></button>
                <a href="login.php" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Volver al inicio</a>
            </form>
        </div>

        <?php // ==========================================
        // PASO 2a: RESPONDER PREGUNTA DE SEGURIDAD
        // ========================================== 
        ?>
        <div id="rec-step-pregunta" class="rec-step <?php echo $show_pregunta ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rec_action" value="responder">
                <div class="rec-user">Usuario: <strong><?php echo htmlspecialchars($_SESSION['rec_user'] ?? ''); ?></strong></div>
                <div class="rec-question"><i class="bi bi-question-circle me-2"></i><?php echo htmlspecialchars($_SESSION['rec_pregunta'] ?? ''); ?></div>
                <div class="field-group">
                    <i class="field-icon bi bi-shield-check"></i>
                    <input type="text" name="rec_respuesta" id="rec-resp" class="field-input" required maxlength="255" autofocus placeholder="Escribe tu respuesta" autocomplete="off" oninput="validarRespuesta()">
                </div>
                <small id="rec-resp-hint" class="rec-hint-msg"></small>
                <button type="submit" id="rec-btn" class="btn-access"><span><i class="bi bi-shield-check me-2"></i>VERIFICAR</span></button>
                <a href="recuperar.php?reset=1" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Intentar con otro correo</a>
                <?php if ($rec_tiene_pin): ?>
                    <a href="recuperar.php?modo=pin" class="login-link-pill login-link-pill--outline"><i class="bi bi-key me-2"></i>¿Olvidó también la respuesta? Usar PIN de emergencia</a>
                <?php endif; ?>
            </form>
        </div>

        <?php // ==========================================
        // PASO 2b: VERIFICAR PIN DE EMERGENCIA (fallback)
        // ========================================== 
        ?>
        <div class="rec-step <?php echo $show_pin ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rec_action" value="pin">
                <div class="rec-user">Usuario: <strong><?php echo htmlspecialchars($_SESSION['rec_user'] ?? ''); ?></strong></div>
                <label class="rec-label"><i class="bi bi-key me-1"></i>PIN de Emergencia</label>
                <div class="field-group">
                    <i class="field-icon bi bi-shield-lock"></i>
                    <input type="password" name="rec_pin" id="rec-pin" class="field-input" required maxlength="6" inputmode="numeric" autofocus placeholder="6 dígitos" autocomplete="off" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6); validarPin()">
                    <button type="button" class="field-eye" id="btnEyePin" aria-label="Mostrar"><i class="bi bi-eye-slash-fill" id="iconEyePin"></i></button>
                </div>
                <small id="rec-pin-hint" class="rec-hint-msg"></small>
                <button type="submit" id="rec-btn-pin" class="btn-access"><span><i class="bi bi-key me-2"></i>VERIFICAR PIN</span></button>
                <?php if (!empty($_SESSION['rec_pregunta'])): ?>
                    <a href="recuperar.php?modo=pregunta" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Volver a mi respuesta de seguridad</a>
                <?php else: ?>
                    <a href="recuperar.php?reset=1" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Intentar con otro correo</a>
                <?php endif; ?>
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
                <label class="rec-label"><i class="bi bi-lock-fill me-1"></i>Nueva contraseña</label>
                <div class="field-group">
                    <i class="field-icon bi bi-key-fill"></i>
                    <input type="password" name="rec_password" id="rec-pass" class="field-input" required minlength="8" placeholder="Mín. 8 caracteres, mayús, minús, núm, símbolo" autofocus oninput="validarPassRec()">
                    <button type="button" class="field-eye" id="btnEyeRec1" aria-label="Mostrar"><i class="bi bi-eye-slash-fill" id="iconEyeRec1"></i></button>
                </div>
                <div class="strength-meter mb-3" style="height:5px;background:var(--jv-border);border-radius:4px;overflow:hidden;">
                    <div class="strength-fill" id="rec-meter" style="height:100%;width:0%;border-radius:4px;transition:all .35s ease;"></div>
                </div>
                <small id="rec-pass-hint" style="color:var(--jv-text-muted);font-size:.8rem;font-weight:600;display:block;height:16px;text-align:center;margin-top:-10px;margin-bottom:10px;"></small>
                <label class="rec-label"><i class="bi bi-key me-1"></i>Confirmar contraseña</label>
                <div class="field-group">
                    <i class="field-icon bi bi-lock"></i>
                    <input type="password" name="rec_password2" id="rec-pass2" class="field-input" required minlength="8" placeholder="Repite la contraseña" oninput="validarPassRec()">
                    <button type="button" class="field-eye" id="btnEyeRec2" aria-label="Mostrar"><i class="bi bi-eye-slash-fill" id="iconEyeRec2"></i></button>
                </div>
                <button type="submit" id="rec-btn-pass" class="btn-access"><span><i class="bi bi-check2 me-2"></i>CAMBIAR CONTRASEÑA</span></button>
                <a href="recuperar.php?reset=1" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Cancelar</a>
            </form>
        </div>

        <?php // ==========================================
        // PASO 4: ÉXITO
        // ========================================== 
        ?>
        <div class="rec-step <?php echo $show_exito ? 'active' : ''; ?>">
            <div class="text-center">
                <div style="font-size:3rem;color:var(--jv-success);margin-bottom:12px;"><i class="bi bi-check-circle-fill"></i></div>
                <a href="login.php" class="btn-access text-decoration-none d-inline-flex" style="width:auto;padding:14px 36px;">
                    <span>IR AL INICIO</span> <i class="bi bi-box-arrow-in-right"></i>
                </a>
            </div>
        </div>
    </div>

    <script src="../assets/login/recuperar.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js?v=2"></script>
</body>

</html>