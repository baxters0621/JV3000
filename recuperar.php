<?php
require_once __DIR__ . '/init.php';

$db = Database::getInstance();

if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['rec_action'] ?? '';

    if ($action === 'buscar') {
        $correo = strtolower(trim($_POST['rec_correo'] ?? ''));
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "INGRESE UN CORREO VÁLIDO.";
        } else {
            $row = $db->fetchOne("SELECT id_usuario, usuario, pregunta_seguridad FROM usuarios WHERE LOWER(correo) = ? LIMIT 1", [$correo]);
            if ($row) {
                if (empty($row['pregunta_seguridad'])) {
                    $error = "ESTE USUARIO NO TIENE PREGUNTA DE SEGURIDAD CONFIGURADA. CONTACTA AL ADMIN.";
                } else {
                    $_SESSION['rec_id'] = $row['id_usuario'];
                    $_SESSION['rec_user'] = $row['usuario'];
                    $_SESSION['rec_pregunta'] = $row['pregunta_seguridad'];
                    $_SESSION['rec_step'] = 2;
                    $_SESSION['rec_intentos'] = 0;
                }
            } else {
                $error = "CORREO NO REGISTRADO EN EL SISTEMA.";
            }
        }
    }

    elseif ($action === 'responder') {
        if ($_SESSION['rec_step'] != 2 || $_SESSION['rec_id'] == 0) {
            $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
            $_SESSION['rec_step'] = 1;
        } else {
                $respuesta = trim($_POST['rec_respuesta'] ?? '');
                $preg_user = $_SESSION['rec_pregunta'] ?? '';
                if (!validarRespuestaSeguridad($respuesta)) {
                    $error = "RESPUESTA INVÁLIDA. MÍN 3 CARACTERES, DEBE TENER VOCALES, SIN PATRONES (asdf, qwerty, etc).";
                } else {
                    $row = $db->fetchOne("SELECT respuesta_seguridad FROM usuarios WHERE id_usuario = ? LIMIT 1", [$_SESSION['rec_id']]);
                    if ($row && password_verify($respuesta, $row['respuesta_seguridad'])) {
                        $_SESSION['rec_step'] = 3;
                    } else {
                        $_SESSION['rec_intentos'] = ($_SESSION['rec_intentos'] ?? 0) + 1;
                        if ($_SESSION['rec_intentos'] >= 3) {
                            $error = "DEMASIADOS INTENTOS. INICIE EL PROCESO DE NUEVO.";
                            $_SESSION['rec_step'] = 1;
                            $_SESSION['rec_id'] = 0;
                        } else {
                            $rest = 3 - $_SESSION['rec_intentos'];
                            $error = "RESPUESTA INCORRECTA. INTENTOS RESTANTES: $rest";
                        }
                    }
                }
            }
    }

    elseif ($action === 'cambiar') {
        if ($_SESSION['rec_step'] != 3 || $_SESSION['rec_id'] == 0) {
            $error = "SOLICITUD INVÁLIDA. INICIE DE NUEVO.";
            $_SESSION['rec_step'] = 1;
        } else {
            $new_pass = $_POST['rec_password'] ?? '';
            $new_pass2 = $_POST['rec_password2'] ?? '';
            if (!preg_match('/^.{8,}$/', $new_pass)) {
                $error = "CONTRASEÑA: MIN 8 CARACTERES.";
            } elseif ($new_pass !== $new_pass2) {
                $error = "LAS CONTRASEÑAS NO COINCIDEN.";
            } else {
                $pass_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $db->execute("UPDATE usuarios SET password = ? WHERE id_usuario = ?", [$pass_hash, $_SESSION['rec_id']]);
                registrarAuditoria('editar', 'Contraseña recuperada por pregunta de seguridad');
                $exito = "CONTRASEÑA ACTUALIZADA. YA PUEDES INICIAR SESIÓN.";
                $_SESSION['rec_step'] = 4;
            }
        }
    }
}

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
    <?php include 'includes/diseno.php'; ?>
    <style>
    .rec-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .rec-card {
        width:100%;max-width:440px;
        background:var(--jv-bg-card);
        backdrop-filter:blur(20px);
        border:1px solid var(--jv-border);
        border-radius:var(--jv-radius-xl);
        padding:36px 32px;
        box-shadow:var(--jv-shadow);
    }
    .rec-step { display:none; }
    .rec-step.active { display:block; }
    .rec-header {
        text-align:center;margin-bottom:28px;
    }
    .rec-header .icon {
        width:56px;height:56px;border-radius:16px;
        background:linear-gradient(135deg,#f59e0b,#d97706);
        display:inline-flex;align-items:center;justify-content:center;
        font-size:1.6rem;color:#fff;margin-bottom:12px;
        box-shadow:0 0 30px rgba(245,158,11,0.3);
    }
    .rec-header h1 {
        font-family:var(--jv-font-brand);
        font-size:1.2rem;font-weight:700;color:#fff;margin:0;
    }
    .rec-header p {
        color:var(--jv-text-secondary);font-size:.85rem;margin:4px 0 0;
    }
    .rec-input {
        width:100%;padding:14px 16px;border-radius:var(--jv-radius);
        background:var(--jv-bg-primary);border:1px solid rgba(255,255,255,0.1);
        color:var(--jv-text-primary);font-size:.9rem;transition:.15s;
    }
    .rec-input:focus {
        outline:none;border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,0.15);
    }
    .rec-btn {
        width:100%;padding:14px;border:none;border-radius:var(--jv-radius);
        font-weight:700;font-size:.85rem;letter-spacing:.5px;
        background:linear-gradient(135deg,#f59e0b,#d97706);
        color:var(--jv-bg-primary);transition:.2s;
    }
    .rec-btn:hover { transform:translateY(-2px);box-shadow:0 8px 25px -5px rgba(245,158,11,0.4); }
    .rec-btn:disabled { opacity:.5;pointer-events:none; }
    .rec-back {
        display:block;text-align:center;margin-top:16px;
        color:var(--jv-text-secondary);font-size:.8rem;text-decoration:none;
    }
    .rec-back:hover { color:var(--jv-cyan); }
    .rec-question {
        background:rgba(245,158,11,0.08);
        border:1px solid rgba(245,158,11,0.2);
        border-radius:var(--jv-radius);
        padding:14px 16px;margin-bottom:16px;
        color:#fbbf24;font-size:.9rem;font-weight:600;text-align:center;
    }
    </style>
</head>
<body class="rec-page">
    <div class="rec-card">
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

        <!-- STEP 1: Buscar por correo -->
        <div class="rec-step <?php echo $show_buscar ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rec_action" value="buscar">
                <label class="small fw-bold text-jv-muted mb-1 d-block">Correo electrónico registrado</label>
                <input type="email" name="rec_correo" class="rec-input mb-3" required placeholder="ejemplo@correo.com" autofocus>
                <button type="submit" class="rec-btn"><i class="bi bi-search me-2"></i>BUSCAR</button>
                <a href="login.php" class="rec-back"><i class="bi bi-arrow-left me-1"></i>Volver al inicio</a>
            </form>
        </div>

        <!-- STEP 2: Responder pregunta -->
        <div id="rec-step-pregunta" class="rec-step <?php echo $show_pregunta ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rec_action" value="responder">
                <div class="small text-jv-muted mb-2">Usuario: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['rec_user'] ?? ''); ?></strong></div>
                <div class="rec-question"><i class="bi bi-question-circle me-2"></i><?php echo htmlspecialchars($_SESSION['rec_pregunta'] ?? ''); ?></div>
                <input type="text" name="rec_respuesta" id="rec-resp" class="rec-input mb-3" required maxlength="50" autofocus placeholder="Tu respuesta personalizada" autocomplete="off">
                <button type="submit" class="rec-btn"><i class="bi bi-shield-check me-2"></i>VERIFICAR</button>
                <a href="recuperar.php?reset=1" class="rec-back"><i class="bi bi-arrow-left me-1"></i>Intentar con otro correo</a>
            </form>
        </div>

        <!-- STEP 3: Nueva contraseña -->
        <div class="rec-step <?php echo $show_cambiar ? 'active' : ''; ?>">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rec_action" value="cambiar">
                <label class="small fw-bold text-jv-muted mb-1 d-block">Nueva contraseña</label>
                <input type="password" name="rec_password" class="rec-input mb-3" required minlength="8" placeholder="Min. 8 caracteres" autofocus>
                <label class="small fw-bold text-jv-muted mb-1 d-block">Confirmar contraseña</label>
                <input type="password" name="rec_password2" class="rec-input mb-3" required minlength="8" placeholder="Repite la contraseña">
                <button type="submit" class="rec-btn"><i class="bi bi-check2 me-2"></i>CAMBIAR CONTRASEÑA</button>
                <a href="recuperar.php?reset=1" class="rec-back"><i class="bi bi-arrow-left me-1"></i>Cancelar</a>
            </form>
        </div>

        <!-- STEP 4: Éxito -->
        <div class="rec-step <?php echo $show_exito ? 'active' : ''; ?>">
            <div class="text-center">
                <div style="font-size:3rem;color:var(--jv-success);margin-bottom:12px;"><i class="bi bi-check-circle-fill"></i></div>
                <a href="login.php" class="rec-btn text-decoration-none d-inline-block" style="width:auto;padding:12px 32px;">
                    <i class="bi bi-box-arrow-in-right me-2"></i>IR AL INICIO
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js?v=2"></script>
</body>
</html>
