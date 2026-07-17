<?php
require_once __DIR__ . '/init.php';

$db = Database::getInstance();

if (isset($_SESSION['id_usuario'])) {
    $userCheck = Database::getInstance()->fetchOne("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND status = 'Activo' AND COALESCE(aprobado,1) = 1 LIMIT 1", [$_SESSION['id_usuario']]);
    if ($userCheck) {
        header("Location: index.php");
        exit();
    }
    session_destroy();
}

$error = "";
$exito = "";

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

// Bloqueo por IP
$segundos_restantes = 0;
$ip_actual = $_SERVER['REMOTE_ADDR'];
$row_rest = $db->fetchOne("SELECT 45 - TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) as restante FROM login_intentos WHERE ip_address = ? AND intentos >= 3 AND TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) < 45", [$ip_actual]);
if ($row_rest && $row_rest['restante'] > 0) {
    $segundos_restantes = (int)$row_rest['restante'];
    $error = "DEMASIADOS INTENTOS. ESPERE $segundos_restantes SEGUNDOS.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_registro'])) {
    $new_user = trim($_POST['reg_usuario']);
    $new_email = strtolower(trim($_POST['reg_email']));
    $new_pass = $_POST['reg_password'];

    if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $new_user)) {
        $error = "USUARIO: MIN 4 Y MAX 20 CARACTERES (letras, numeros, guion bajo)";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "CORREO: FORMATO INVALIDO";
    } elseif (!preg_match('/^.{8,}$/', $new_pass)) {
        $error = "CONTRASEÑA: MIN 8 CARACTERES";
    } elseif ($new_pass !== $_POST['reg_password_confirm']) {
        $error = "LAS CONTRASEÑAS NO COINCIDEN";
    } else {
        $reg_pregunta = trim($_POST['reg_pregunta'] ?? '');
        $reg_respuesta = trim($_POST['reg_respuesta'] ?? '');
        if ($reg_pregunta === '' || $reg_respuesta === '') {
            $error = "DEBE SELECCIONAR UNA PREGUNTA DE SEGURIDAD Y SU RESPUESTA.";
        } elseif (!validarRespuestaSeguridad($reg_respuesta)) {
            $error = "RESPUESTA INVÁLIDA. MÍN 3 CARACTERES, DEBE TENER VOCALES, SIN PATRONES (asdf, qwerty, etc).";
        } else {
            $dup = $db->fetchOne("SELECT id_usuario FROM usuarios WHERE BINARY usuario = ? OR BINARY correo = ?", [$new_user, $new_email]);
            if ($dup) {
                $error = "EL USUARIO O CORREO YA ESTA EN USO";
            } else {
                $pass_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $resp_hash = password_hash($reg_respuesta, PASSWORD_BCRYPT);
                $es_admin = $sistema_vacio;
                $db->insert('usuarios', [
                    'usuario'             => $new_user,
                    'correo'              => $new_email,
                    'password'            => $pass_hash,
                    'pregunta_seguridad'  => $reg_pregunta,
                    'respuesta_seguridad' => $resp_hash,
                    'rol'                 => $es_admin ? 'Administrador' : 'Sin Asignar',
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_login'])) {
    $ip_usuario = $_SERVER['REMOTE_ADDR'];

    $blocked = $db->fetchOne("SELECT 1 FROM login_intentos WHERE ip_address = ? AND intentos >= 3 AND TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) < 45", [$ip_usuario]);
    if ($blocked) {
        $segundos_restantes = max(1, $segundos_restantes);
        $error = "DEMASIADOS INTENTOS. ESPERE $segundos_restantes SEGUNDOS.";
    } else {
        $user = trim($_POST['usuario']);
        $pass = $_POST['password'];

        $row = $db->fetchOne("SELECT * FROM usuarios WHERE BINARY usuario = ? LIMIT 1", [$user]);
        if ($row) {
            if (password_verify($pass, $row['password'])) {
                $aprobado = $row['aprobado'] ?? 1;
                if ($aprobado == 0) {
                    $error = "TU CUENTA ESTA PENDIENTE DE APROBACION. CONTACTA AL ADMINISTRADOR.";
                } elseif ($row['status'] === 'Inactivo') {
                    $error = "TU CUENTA ESTA DESACTIVADA. CONTACTA AL ADMINISTRADOR.";
                } else {
                    $db->execute("DELETE FROM login_intentos WHERE ip_address = ?", [$ip_usuario]);
                    $_SESSION['id_usuario']   = $row['id_usuario'];
                    $_SESSION['usuario']      = $row['usuario'];
                    $_SESSION['rol']          = $row['rol'];
                    $_SESSION['ip_addr']      = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['fresh_login']  = true;
                    registrarAuditoria('login', "Inicio de sesión");
                    header("Location: index.php");
                    exit();
                }
            } else {
                $existing = $db->fetchOne("SELECT 1 FROM login_intentos WHERE ip_address = ?", [$ip_usuario]);
                if ($existing) {
                    $db->execute("UPDATE login_intentos SET intentos = intentos + 1, ultimo_intento = NOW() WHERE ip_address = ?", [$ip_usuario]);
                } else {
                    $db->execute("INSERT INTO login_intentos (ip_address, intentos) VALUES (?, 1)", [$ip_usuario]);
                }
                $error = "CODIGO DE ACCESO INCORRECTO";
            }
        } else {
            $error = "EL USUARIO NO EXISTE EN EL SISTEMA";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/diseno.php'; ?>
<title>JV3000 C.A. | Terminal de Acceso</title>
<style>
.login-page {
    min-height: 100vh;
    background:
        linear-gradient(rgba(2, 6, 23, 0.82), rgba(2, 6, 23, 0.82)),
        url('assets/img/fondo-login.jpg') center/cover no-repeat fixed;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.brand-logo {
    font-family: 'Orbitron', monospace;
    font-size: 3rem;
    text-align: center;
    margin-bottom: 6px;
    letter-spacing: -2px;
    line-height: 1;
}

.brand-tagline {
    text-align: center;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 4px;
    color: var(--jv-text-muted);
    margin-bottom: 32px;
}

.field-group {
    position: relative;
    margin-bottom: 16px;
}

.field-group .field-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--jv-text-muted);
    font-size: 1rem;
    z-index: 2;
    pointer-events: none;
}

.field-group .field-input {
    width: 100%;
    background: rgba(2, 6, 23, 0.7);
    border: 1px solid rgba(56, 189, 248, 0.15);
    border-radius: var(--jv-radius);
    padding: 14px 48px 14px 48px;
    color: var(--jv-text-primary);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.field-group .field-input:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    background: rgba(2, 6, 23, 0.3);
}

.field-group .field-input::placeholder {
    color: var(--jv-text-muted);
    opacity: 1;
}

.field-group .field-input:focus {
    outline: none;
    border-color: var(--jv-cyan);
    box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15);
    background: rgba(2, 6, 23, 0.85);
}

.field-group .field-eye {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--jv-text-muted);
    cursor: pointer;
    padding: 4px 6px;
    font-size: 1.05rem;
    transition: color 0.3s ease;
    z-index: 3;
}

.field-group .field-eye:hover,
.field-group .field-input:focus ~ .field-eye {
    color: var(--jv-cyan);
}

.btn-access {
    background: linear-gradient(135deg, var(--jv-cyan) 0%, var(--jv-cyan-dark) 100%);
    border: none;
    border-radius: var(--jv-radius);
    padding: 14px;
    font-family: 'Orbitron', monospace;
    font-weight: 700;
    color: var(--jv-bg-primary);
    width: 100%;
    font-size: 0.8rem;
    letter-spacing: 1.5px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-access:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(6, 182, 212, 0.3);
    color: var(--jv-bg-primary);
}

.btn-access:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 24px 0 18px;
    color: var(--jv-text-muted);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--jv-border);
}

.modal-reg .modal-content {
    background: #0f172a;
    border: 1px solid var(--jv-border);
    border-radius: var(--jv-radius-xl);
    color: var(--jv-text-primary);
    max-width: 420px;
    margin: 0 auto;
}

.modal-reg .modal-header {
    border-bottom: 1px solid var(--jv-border);
    padding: 22px 24px 14px;
}

.modal-reg .modal-body {
    padding: 16px 24px 8px;
}

.modal-reg .modal-footer {
    border-top: 1px solid var(--jv-border);
    padding: 8px 24px 22px;
}

.modal-reg .form-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--jv-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.reg-hint {
    color: var(--jv-cyan);
    font-size: 0.8rem;
    margin-top: 6px;
    display: block;
}

.strength-meter {
    height: 3px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 6px;
}

.strength-fill {
    height: 100%;
    width: 0%;
    transition: all 0.35s ease;
    border-radius: 2px;
}

.modal-reg .field-group {
    position: relative;
    margin-bottom: 14px;
}

.reg-match {
    position: absolute;
    right: 44px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.1rem;
    pointer-events: none;
    z-index: 2;
}
</style>
</head>
<body>
<div class="login-page">
    <div class="glass-login" style="width:100%;max-width:380px;">
        <div class="brand-logo">
            JV<span class="text-jv-cyan">3000</span> <span style="font-size:.8rem;color:#94a3b8;">C.A.</span>
        </div>
        <div class="brand-tagline" style="color:#ffffff !important;">Sistema de Inventario y Ventas</div>

        <?php if ($error): ?>
        <div class="alert-jv alert-jv-danger mb-3 flash-auto" id="alerta-bloqueo">
            <i class="bi bi-shield-slash me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if ($exito): ?>
        <div class="alert-jv alert-jv-success mb-3 flash-auto">
            <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($exito); ?>
        </div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="field-group">
                <i class="field-icon bi bi-person-fill"></i>
                <input type="text" id="f-user" name="usuario" class="field-input" placeholder="ID de Operador" required autofocus <?php echo $segundos_restantes > 0 ? 'disabled' : ''; ?>>
            </div>

            <div class="field-group">
                <i class="field-icon bi bi-lock-fill"></i>
                <input type="password" id="f-pass" name="password" class="field-input" placeholder="Clave de Acceso" required <?php echo $segundos_restantes > 0 ? 'disabled' : ''; ?>>
                <button type="button" class="field-eye" id="btnEyePass" aria-label="Mostrar contraseña">
                    <i class="bi bi-eye-slash-fill" id="iconEyePass"></i>
                </button>
            </div>

            <button type="submit" name="btn_login" class="btn-access mt-2" id="btn-login" <?php echo $segundos_restantes > 0 ? 'disabled' : ''; ?>>
                AUTENTICAR <i class="bi bi-cpu ms-2"></i>
            </button>

            <div class="text-center mt-3">
                <a href="recuperar.php" class="text-decoration-none text-jv-cyan fw-bold" style="font-size:0.9rem;">
                    <i class="bi bi-question-circle me-1"></i>¿Olvidaste tu contraseña?
                </a>
            </div>
        </form>

        <div class="divider" style="color:#ffffff !important;">Nuevo Personal</div>

        <div class="text-center">
            <a href="#" class="text-decoration-none text-jv-cyan fw-bold" style="font-size:0.85rem;" data-bs-toggle="modal" data-bs-target="#modalReg">
                <i class="bi bi-person-plus me-1"></i>
                <?php echo $sistema_vacio ? 'Configurar Administrador Inicial' : 'Solicitar Acceso de Personal'; ?>
            </a>
        </div>
    </div>
</div>

<div class="modal fade modal-reg" id="modalReg" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-brand text-jv-cyan">
                    <i class="bi bi-shield-plus me-2"></i>
                    <?php echo $sistema_vacio ? 'INSTALACION DE SISTEMA' : 'SOLICITUD DE ACCESO'; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="modal-body">

                    <label class="form-label">Nombre de Usuario</label>
                    <div class="field-group">
                        <i class="field-icon bi bi-at"></i>
                        <input type="text" name="reg_usuario" id="r-user" class="field-input" required oninput="validarReg()" placeholder="Ej: admin_sistema">
                    </div>
                    <small class="reg-hint" id="r-user-hint">Minimo 4 caracteres, solo letras, numeros y guiones bajos.</small>

                    <label class="form-label" style="margin-top:14px;">Correo Electronico</label>
                    <div class="field-group">
                        <i class="field-icon bi bi-envelope-fill"></i>
                        <input type="email" name="reg_email" class="field-input" required placeholder="ejemplo@jv3000.com">
                    </div>

                    <label class="form-label" style="margin-top:14px;">Contraseña</label>
                    <div class="field-group">
                        <i class="field-icon bi bi-key-fill"></i>
                        <input type="password" name="reg_password" id="r-pass" class="field-input" required oninput="validarReg()" placeholder="Cree una clave fuerte">
                        <button type="button" class="field-eye" id="btnEyeR1" aria-label="Mostrar">
                            <i class="bi bi-eye-slash-fill" id="iconEyeR1"></i>
                        </button>
                    </div>
                    <div class="strength-meter">
                        <div class="strength-fill" id="r-meter"></div>
                    </div>
                    <small class="reg-hint" id="r-pass-hint">Min. 8 caracteres con Mayusculas, Minusculas, Numeros y Simbolos.</small>

                    <label class="form-label" style="margin-top:14px;">Confirmar Contraseña</label>
                    <div class="field-group">
                        <i class="field-icon bi bi-key"></i>
                        <input type="password" name="reg_password_confirm" id="r-pass2" class="field-input" required oninput="validarReg()" placeholder="Repita la contraseña">
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
                    <input type="text" name="reg_respuesta" id="r-resp" class="field-input" required maxlength="50" oninput="validarReg()" placeholder="Tu respuesta personalizada" autocomplete="off">
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

<script src="<?php echo $base_assets; ?>js/bootstrap.bundle.min.js?v=2"></script>
<script>
function setupEye(btnId, iconId, inputId) {
    var btn = document.getElementById(btnId);
    var icon = document.getElementById(iconId);
    var input = document.getElementById(inputId);
    if (!btn || !icon || !input) return;
    btn.addEventListener('click', function() {
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash-fill';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye-fill';
        }
    });
}

setupEye('btnEyePass', 'iconEyePass', 'f-pass');
setupEye('btnEyeR1', 'iconEyeR1', 'r-pass');
setupEye('btnEyeR2', 'iconEyeR2', 'r-pass2');

function validarReg() {
    var u = document.getElementById('r-user').value.trim();
    var p = document.getElementById('r-pass').value;
    var p2 = document.getElementById('r-pass2').value;
    var preg = document.getElementById('r-preg').value;
    var resp = document.getElementById('r-resp').value.trim();
    var btn = document.getElementById('btn-reg');
    var uHint = document.getElementById('r-user-hint');
    var pHint = document.getElementById('r-pass-hint');
    var meter = document.getElementById('r-meter');

    var resp = document.getElementById('r-resp').value.trim();
    var respOk = false;
    if (resp.length >= 3 && resp.length <= 50 && /[a-zA-Z]/.test(resp) && /[aeiouAEIOU]/.test(resp) && !/(.)\1{3,}/.test(resp) && !/abcdef|bcdefg|cdefgh|defghi|efghij|fghijk|ghijkl|hijklm|ijklmn/i.test(resp) && !/asdf|qwerty|zxcv|abcd|1234/i.test(resp)) {
        respOk = true;
        document.getElementById('r-resp').style.borderColor = 'var(--jv-success)';
    } else if (resp.length > 0) {
        document.getElementById('r-resp').style.borderColor = 'var(--jv-danger)';
    } else {
        document.getElementById('r-resp').style.borderColor = '';
    }

    var uOk = u.length >= 4 && /^[a-zA-Z0-9_]+$/.test(u);

    if (u.length > 0) {
        uHint.style.color = uOk ? 'var(--jv-success)' : 'var(--jv-danger)';
    } else {
        uHint.style.color = 'var(--jv-cyan)';
    }

    if (p.length > 0) {
        var s = 0;
        if (p.length >= 8) s++;
        if (/[a-z]/.test(p)) s++;
        if (/[A-Z]/.test(p)) s++;
        if (/[0-9]/.test(p)) s++;
        if (/[\W_]/.test(p)) s++;
        var cols = ['var(--jv-danger)', 'var(--jv-danger)', 'var(--jv-warning)', 'var(--jv-info)', 'var(--jv-success)'];
        var wids = ['20%', '40%', '60%', '80%', '100%'];
        var idx = Math.max(0, Math.min(s - 1, 4));
        meter.style.width = wids[idx];
        meter.style.backgroundColor = cols[idx];
        if (s < 3) { pHint.textContent = 'Contraseña debil'; pHint.style.color = 'var(--jv-danger)'; }
        else if (s < 4) { pHint.textContent = 'Contraseña aceptable'; pHint.style.color = 'var(--jv-warning)'; }
        else if (s < 5) { pHint.textContent = 'Contraseña buena'; pHint.style.color = 'var(--jv-info)'; }
        else { pHint.textContent = 'Contraseña fuerte'; pHint.style.color = 'var(--jv-success)'; }
    } else {
        meter.style.width = '0%';
        pHint.textContent = 'Min. 8 caracteres con letras, numeros y simbolos.';
        pHint.style.color = 'var(--jv-cyan)';
    }

    var pOk = p.length >= 8;
    var pMatch = p.length > 0 && p === p2;
    var matchHint = document.getElementById('r-match-hint');
    if (p2.length > 0) {
        matchHint.className = 'reg-match bi ' + (pMatch ? 'bi-check-circle-fill text-jv-success' : 'bi-x-circle-fill text-jv-danger');
    } else {
        matchHint.className = 'reg-match';
        matchHint.textContent = '';
    }
    btn.disabled = !(uOk && (s >= 3) && pMatch && preg !== '' && respOk);
}

document.getElementById('r-preg').addEventListener('change', function() {
    validarReg();
});
</script>
<script>
// Contador regresivo de bloqueo
var segRestantes = <?php echo $segundos_restantes; ?>;
if (segRestantes > 0) {
    var elAlerta = document.querySelector('.alert-jv-danger');
    var timerBloqueo = setInterval(function() {
        segRestantes--;
        if (elAlerta) {
            elAlerta.innerHTML = '<i class="bi bi-shield-slash me-2"></i>DEMASIADOS INTENTOS. ESPERE <strong>' + segRestantes + '</strong> SEGUNDOS.';
        }
        if (segRestantes <= 0) {
            clearInterval(timerBloqueo);
            document.getElementById('f-user').disabled = false;
            document.getElementById('f-pass').disabled = false;
            document.getElementById('btn-login').disabled = false;
            document.getElementById('f-user').focus();
            if (elAlerta) {
                elAlerta.className = 'alert-jv alert-jv-success mb-3';
                elAlerta.innerHTML = '<i class="bi bi-unlock-fill me-2"></i>BLOQUEO TERMINADO. YA PUEDES INTENTAR DE NUEVO.';
                setTimeout(function() {
                    elAlerta.style.transition = 'opacity .5s';
                    elAlerta.style.opacity = '0';
                    setTimeout(function() { elAlerta.remove(); }, 500);
                }, 4000);
            }
        }
    }, 1000);
}
</script>
<script>
document.querySelectorAll('.flash-auto').forEach(el => {
    setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
});
</script>
</body>
</html>