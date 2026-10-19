<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/Mail.php';

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
    limpiarCodigoRecuperacion();
}

// Cambio de método de verificación en el paso 2 (código por correo <-> PIN de emergencia)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['modo']) && isset($_SESSION['rec_step']) && $_SESSION['rec_step'] == 2) {
    $modo_solicitado = $_GET['modo'];
    if (in_array($modo_solicitado, ['codigo', 'pin'], true)) {
        $_SESSION['rec_modo'] = $modo_solicitado;
        if ($modo_solicitado === 'pin') {
            $_SESSION['rec_pin_intentos'] = 0;
        }
    } elseif ($modo_solicitado === 'elegir') {
        // Volver a la pantalla de métodos sin descartar un código pendiente.
        $_SESSION['rec_modo'] = '';
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
        } elseif (strlen($recoveryInput) > 120) {
            $error = "EL CAMPO USUARIO/CORREO NO PUEDE EXCEDER 120 CARACTERES.";
        } else {
            if (filter_var($recoveryInput, FILTER_VALIDATE_EMAIL)) {
                $normalizedRecoveryInput = strtolower($recoveryInput);
                $userAccount = $db->fetchOne("SELECT id_usuario, usuario, correo FROM usuarios WHERE LOWER(correo) = ? LIMIT 1", [$normalizedRecoveryInput]);
            } else {
                $userAccount = $db->fetchOne("SELECT id_usuario, usuario, correo FROM usuarios WHERE LOWER(usuario) = ? LIMIT 1", [strtolower($recoveryInput)]);
            }
            if ($userAccount) {
                $_SESSION['rec_id'] = $userAccount['id_usuario'];
                $_SESSION['rec_user'] = $userAccount['usuario'];
                $_SESSION['rec_step'] = 2;
                $_SESSION['rec_modo'] = '';
                $_SESSION['rec_intentos'] = 0;
                $_SESSION['rec_pin_intentos'] = 0;
                // Una nueva búsqueda descarta cualquier código pendiente.
                limpiarCodigoRecuperacion();
            } else {
                $error = "NO SE ENCONTRÓ UNA CUENTA CON ESE DATO. VERIFÍQUELO O CONTACTE AL ADMINISTRADOR.";
            }
        }
    }

    // --- Step 2a: Send recovery code by email ---
    elseif ($action === 'solicitar_codigo') {
        if ($_SESSION['rec_step'] != 2 || $_SESSION['rec_id'] == 0) {
            $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
            $_SESSION['rec_step'] = 1;
        } elseif (!Mail::hayInternet()) {
            $error = "SIN CONEXIÓN A INTERNET. USE EL PIN DE EMERGENCIA.";
            $_SESSION['rec_modo'] = 'pin';
        } else {
            $solicitud = solicitarCodigoRecuperacion();
            if (!$solicitud['ok']) {
                $error = $solicitud['mensaje'];
            } else {
                $cuentaRec = $db->fetchOne("SELECT correo FROM usuarios WHERE id_usuario = ? LIMIT 1", [$_SESSION['rec_id']]);
                $destinatario = $cuentaRec['correo'] ?? '';
                if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                    limpiarCodigoRecuperacion();
                    $error = "ESTA CUENTA NO TIENE UN CORREO VÁLIDO. USE EL PIN DE EMERGENCIA.";
                    $_SESSION['rec_modo'] = 'pin';
                } else {
                    $envio = Mail::enviar($destinatario, 'Codigo de recuperacion - JV3000', codigoRecuperacionCuerpo($destinatario));
                    if (empty($envio['ok'])) {
                        limpiarCodigoRecuperacion();
                        $error = "NO SE PUDO ENVIAR EL CORREO. REVISE LA CONEXIÓN O USE EL PIN DE EMERGENCIA.";
                    } else {
                        $_SESSION['rec_modo'] = 'codigo';
                        $exito = "CÓDIGO ENVIADO A " . enmascararCorreo($destinatario) . ". VIGENTE 15 MINUTOS.";
                    }
                }
            }
        }
    }

    // --- Step 2b: Verify the 6-digit code received by email ---
    elseif ($action === 'verificar_codigo') {
        if ($_SESSION['rec_step'] != 2 || $_SESSION['rec_id'] == 0) {
            $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
            $_SESSION['rec_step'] = 1;
        } else {
            $resultadoCodigo = verificarCodigoRecuperacion(trim($_POST['rec_codigo'] ?? ''));
            if ($resultadoCodigo['ok']) {
                $_SESSION['rec_step'] = 3;
                unset($_SESSION['rec_modo']);
            } else {
                $error = $resultadoCodigo['mensaje'];
            }
        }
    }

    // --- Step 2b: Verify emergency PIN (fallback sin internet)
    elseif ($action === 'pin') {
        if ($_SESSION['rec_step'] != 2 || $_SESSION['rec_id'] == 0) {
            $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
            $_SESSION['rec_step'] = 1;
        } else {
            $pin = trim($_POST['rec_pin'] ?? '');
            if (!preg_match('/^[0-9]{6}$/', $pin)) {
                $error = "PIN DE EMERGENCIA INVÁLIDO. DEBE SER DE 6 DÍGITOS NUMÉRICOS.";
            } else {
                $usuarioRec = $db->fetchOne("SELECT id_usuario, id_rol FROM usuarios WHERE id_usuario = ? LIMIT 1", [$_SESSION['rec_id']]);
                if (!$usuarioRec) {
                    $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
                    $_SESSION['rec_step'] = 1;
                } elseif ((int)$usuarioRec['id_rol'] === 1) {
                    $error = "LA CUENTA DEL ADMINISTRADOR SE RECUPERA POR CORREO ELECTRÓNICO.";
                    $_SESSION['rec_step'] = 1;
                } else {
                    $pinGlobal = trim(getConfig('pin_emergencia_global', ''));
                    if ($pinGlobal === '') {
                        $error = "ESTA CUENTA NO TIENE PIN DE EMERGENCIA CONFIGURADO. CONTACTE AL ADMINISTRADOR.";
                        $_SESSION['rec_step'] = 1;
                    } elseif (hash_equals($pinGlobal, $pin)) {
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
                $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $db->execute("UPDATE usuarios SET password = ?, password_check = ? WHERE id_usuario = ?", [$passwordHash, $pass_check, $_SESSION['rec_id']]);
                limpiarCodigoRecuperacion();
                registrarAuditoria('editar', 'Contraseña recuperada por código de correo o PIN de emergencia');
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
$modo_rec = $_SESSION['rec_modo'] ?? '';
$show_buscar = ($step == 1);
// Paso 2 sin método elegido todavía: se muestran las tarjetas disponibles.
$show_elegir = ($step == 2 && $modo_rec === '');
$show_codigo = ($step == 2 && $modo_rec === 'codigo');
$show_pin = ($step == 2 && $modo_rec === 'pin');
$show_cambiar = ($step == 3);
$show_exito = ($step == 4);

// Datos de la cuenta para el paso 2: correo disponible, PIN configurado
// y si ya hay un código pendiente de verificar.
$rec_correo = '';
$rec_tiene_pin = false;
$rec_es_admin = false;
if (($_SESSION['rec_id'] ?? 0) > 0) {
    $cuentaRec = $db->fetchOne("SELECT correo, id_rol FROM usuarios WHERE id_usuario = ? LIMIT 1", [$_SESSION['rec_id']]);
    $rec_correo = $cuentaRec['correo'] ?? '';
    $rec_es_admin = ((int)$cuentaRec['id_rol'] ?? 0) === 1;
    $pinGlobal = trim(getConfig('pin_emergencia_global', ''));
    $rec_tiene_pin = !$rec_es_admin && $pinGlobal !== '';
}
// Sin internet no se ofrece el canal de correo: el PIN cubre esa emergencia.
// El sondeo solo se hace en el paso 2, donde el canal de correo se muestra.
$rec_puede_correo = $step == 2
    && Mail::hayInternet()
    && filter_var($rec_correo, FILTER_VALIDATE_EMAIL);
$rec_codigo_pendiente = isset($_SESSION['rec_codigo_hash']);
// Segundos que faltan para poder pedir un reenvío (60 s entre envíos).
$rec_reenvio_espera = $rec_codigo_pendiente
    ? max(0, 60 - (time() - (int)($_SESSION['rec_ultimo_envio'] ?? 0)))
    : 0;

// Si la red cae mientras se elegía el canal de correo, se vuelve a la pantalla
// de métodos: así solo queda el PIN, que funciona sin conexión. Un código ya
// emitido sí se conserva, porque el usuario puede verificarlo aunque no haya red.
if ($show_codigo && !$rec_puede_correo && !$rec_codigo_pendiente) {
    $_SESSION['rec_modo'] = '';
    $show_codigo = false;
    $show_elegir = true;
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
            <p>Verificación por correo electrónico o PIN de emergencia</p>
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
                    <input type="text" name="rec_input" class="field-input" required maxlength="120" placeholder="ejemplo@correo.com o Usuario" autofocus>
                </div>
                <button type="submit" class="btn-access"><span><i class="bi bi-search me-2"></i>BUSCAR</span></button>
                <a href="login.php" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Volver al inicio</a>
            </form>
        </div>

        <?php // ==========================================
        // PASO 2: ELEGIR MÉTODO DE VERIFICACIÓN
        // ========================================== 
        ?>
        <div class="rec-step <?php echo $show_elegir ? 'active' : ''; ?>">
            <div class="rec-user">Usuario: <strong><?php echo htmlspecialchars($_SESSION['rec_user'] ?? ''); ?></strong></div>

            <?php if ($rec_es_admin): ?>
                <div class="rec-question">
                    <i class="bi bi-envelope-paper me-2"></i>
                    La cuenta del Administrador se recupera por correo electrónico.
                </div>
                <?php if ($rec_puede_correo): ?>
                    <p class="rec-label mb-2"><i class="bi bi-ui-checks me-1"></i>Elija cómo verificar su identidad</p>
                    <a href="recuperar.php?modo=codigo" class="btn-access text-decoration-none d-flex flex-column mb-2" style="width:100%;">
                        <span><i class="bi bi-envelope-paper me-2"></i>ENVIAR CÓDIGO A MI CORREO</span>
                        <small class="fw-normal" style="font-size:.72rem;opacity:.85;">
                            Se enviará a <?php echo htmlspecialchars(enmascararCorreo($rec_correo)); ?> · vence en 15 min
                        </small>
                    </a>
                <?php else: ?>
                    <div class="rec-question">
                        <i class="bi bi-wifi-off me-2"></i>
                        Sin conexión a internet no hay forma de verificar esta cuenta.
                        Restablezca su contraseña desde el equipo servidor.
                    </div>
                <?php endif; ?>
            <?php elseif (!$rec_puede_correo && !$rec_tiene_pin): ?>
                <div class="rec-question">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Esta cuenta no tiene ningún método de verificación disponible.
                    Contacte al administrador para restablecer su contraseña.
                </div>
            <?php else: ?>
                <p class="rec-label mb-2"><i class="bi bi-ui-checks me-1"></i>Elija cómo verificar su identidad</p>

                <?php if ($rec_puede_correo): ?>
                    <a href="recuperar.php?modo=codigo" class="btn-access text-decoration-none d-flex flex-column mb-2" style="width:100%;">
                        <span><i class="bi bi-envelope-paper me-2"></i>ENVIAR CÓDIGO A MI CORREO</span>
                        <small class="fw-normal" style="font-size:.72rem;opacity:.85;">
                            Se enviará a <?php echo htmlspecialchars(enmascararCorreo($rec_correo)); ?> · vence en 15 min
                        </small>
                    </a>
                <?php endif; ?>

                <?php if ($rec_tiene_pin): ?>
                    <a href="recuperar.php?modo=pin" class="btn-access text-decoration-none d-flex flex-column mb-2" style="width:100%;">
                        <span><i class="bi bi-key me-2"></i>USAR PIN DE EMERGENCIA</span>
                        <small class="fw-normal" style="font-size:.72rem;opacity:.85;">
                            Funciona sin conexión a internet
                        </small>
                    </a>
                <?php endif; ?>

                <?php if (!$rec_puede_correo && $rec_tiene_pin): ?>
                    <small class="rec-hint-msg d-block mb-2" style="text-align:center;">
                        Sin conexión a internet: el envío de correos está disponible solo cuando hay red.
                    </small>
                <?php endif; ?>
            <?php endif; ?>

            <a href="recuperar.php?reset=1" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Intentar con otro correo</a>
        </div>

        <?php // ==========================================
        // PASO 2a: CÓDIGO DE 6 DÍGITOS POR CORREO
        // ========================================== 
        ?>
        <div class="rec-step <?php echo $show_codigo ? 'active' : ''; ?>">
            <div class="rec-user">Usuario: <strong><?php echo htmlspecialchars($_SESSION['rec_user'] ?? ''); ?></strong></div>

            <?php if (!$rec_codigo_pendiente): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="rec_action" value="solicitar_codigo">
                    <p class="rec-question">
                        <i class="bi bi-envelope-paper me-2"></i>
                        Enviaremos un código de 6 dígitos a
                        <strong><?php echo htmlspecialchars(enmascararCorreo($rec_correo)); ?></strong>.
                        Vence en 15 minutos y sirve una sola vez.
                    </p>
                    <button type="submit" class="btn-access"><span><i class="bi bi-send me-2"></i>ENVIAR CÓDIGO</span></button>
                    <a href="recuperar.php?reset=1" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Intentar con otro correo</a>
                    <?php if ($rec_tiene_pin): ?>
                        <a href="recuperar.php?modo=pin" class="login-link-pill login-link-pill--outline"><i class="bi bi-key me-2"></i>Usar PIN de emergencia</a>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="rec_action" value="verificar_codigo">
                    <label class="rec-label"><i class="bi bi-hash me-1"></i>Código de 6 dígitos</label>
                    <div class="field-group">
                        <i class="field-icon bi bi-envelope-check"></i>
                        <input type="text" name="rec_codigo" id="rec-codigo" class="field-input" required
                            maxlength="6" inputmode="numeric" autocomplete="one-time-code" autofocus
                            placeholder="000000"
                            oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6); validarCodigoRec()">
                    </div>
                    <small id="rec-codigo-hint" class="rec-hint-msg"></small>
                    <button type="submit" id="rec-btn-codigo" class="btn-access"><span><i class="bi bi-shield-check me-2"></i>VERIFICAR CÓDIGO</span></button>
                    <button type="submit" form="form-reenviar" id="rec-btn-reenviar"
                        class="login-link-pill login-link-pill--outline" style="width:100%;border:1px solid var(--jv-border);"
                        <?php echo $rec_reenvio_espera > 0 ? 'disabled' : ''; ?>>
                        <i class="bi bi-arrow-clockwise me-1"></i><span id="rec-reenviar-texto">REENVIAR CÓDIGO</span>
                    </button>
                    <input type="hidden" id="rec-reenvio-espera" value="<?php echo (int)$rec_reenvio_espera; ?>">
                    <a href="recuperar.php?reset=1" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Intentar con otro correo</a>
                </form>
                <form method="POST" id="form-reenviar" class="d-none">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="rec_action" value="solicitar_codigo">
                </form>
                <?php if ($rec_tiene_pin): ?>
                    <a href="recuperar.php?modo=pin" class="login-link-pill login-link-pill--outline"><i class="bi bi-key me-2"></i>Usar PIN de emergencia</a>
                <?php endif; ?>
            <?php endif; ?>
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
                <a href="recuperar.php?modo=elegir" class="login-link-pill login-link-pill--outline"><i class="bi bi-arrow-left"></i>Elegir otro método</a>
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