<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();
$csrf_token = Security::generateToken();

$id_usuario = (int)$_SESSION['id_usuario'];
$usuario_data = $db->fetchOne("SELECT id_usuario, usuario, correo, password FROM usuarios WHERE id_usuario = ?", [$id_usuario]);
if (!$usuario_data) {
    header("Location: ../dashboard/index.php");
    exit();
}

$roles_map = [1 => 'Administrador', 2 => 'Operador de Carga', 3 => 'Operador de Ventas'];
$rol_perfil = $roles_map[(int)($_SESSION['id_rol'] ?? 0)] ?? 'Sin rol';
$inicial = strtoupper(substr($usuario_data['usuario'], 0, 1));
$preguntas_opciones = getPreguntasRespuestas();

// ==========================================
// ACTUALIZAR PERFIL (usuario, correo, contraseña y pregunta de seguridad)
// ==========================================
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_perfil') {
    $actual  = $_POST['password_actual'] ?? '';
    $usuario = trim($_POST['usuario'] ?? '');
    $correo  = strtolower(trim($_POST['correo'] ?? ''));
    $nueva   = $_POST['password_nueva'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $pregunta  = trim($_POST['pregunta_seguridad'] ?? '');
    $respuesta = trim($_POST['respuesta_seguridad'] ?? '');

    // Credencial requerida para cualquier cambio
    if (!password_verify($actual, $usuario_data['password'])) {
        $errores[] = 'Debes escribir tu contraseña actual para guardar los cambios.';
    }

    // Usuario
    if (strlen($usuario) < 4 || !preg_match('/^[a-zA-Z0-9_]+$/', $usuario)) {
        $errores[] = 'EL USUARIO DEBE TENER MÍN 4 CARACTERES (letras, números, guion bajo).';
    } elseif ($db->fetchOne("SELECT id_usuario FROM usuarios WHERE LOWER(usuario) = LOWER(?) AND id_usuario != ?", [$usuario, $id_usuario])) {
        $errores[] = 'EL NOMBRE DE USUARIO YA ESTÁ EN USO POR OTRA CUENTA.';
    }

    // Correo
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'CORREO ELECTRÓNICO INVÁLIDO.';
    } elseif ($db->fetchOne("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario != ?", [$correo, $id_usuario])) {
        $errores[] = 'EL CORREO YA ESTÁ EN USO POR OTRA CUENTA.';
    }

    // Contraseña nueva (opcional: solo si se escribe algo)
    if ($nueva !== '') {
        if ($nueva !== $confirm) {
            $errores[] = 'Las contraseñas nuevas no coinciden.';
        }
        if (!validarPasswordFuerte($nueva)) {
            $errores[] = 'CONTRASEÑA DÉBIL: MÍN 8 CARACTERES, MAYÚSCULAS, NÚMEROS Y SÍMBOLOS.';
        }
        if ($actual === $nueva) {
            $errores[] = 'La nueva contraseña debe ser diferente a la actual.';
        }
    }

    // Pregunta y respuesta de seguridad
    if ($pregunta === '' || $respuesta === '') {
        $errores[] = 'DEBES SELECCIONAR UNA PREGUNTA DE SEGURIDAD Y ESCRIBIR SU RESPUESTA.';
    } elseif (!validarRespuestaSeguridad($respuesta)) {
        $errores[] = 'RESPUESTA DE SEGURIDAD INVÁLIDA. ESCRIBE AL MENOS UN CARACTER.';
    }

    if (empty($errores)) {
        $hash_final = $usuario_data['password'];
        if ($nueva !== '') {
            $hash_final = password_hash($nueva, PASSWORD_BCRYPT);
        }
        $resp_hash = password_hash(normalizarRespuestaSeguridad($respuesta), PASSWORD_BCRYPT);
        $db->execute(
            "UPDATE usuarios SET usuario = ?, correo = ?, password = ?, pregunta_seguridad = ?, respuesta_seguridad = ? WHERE id_usuario = ?",
            [$usuario, $correo, $hash_final, $pregunta, $resp_hash, $id_usuario]
        );

        // Refrescar datos de sesión (nombre visible en el sidebar)
        $_SESSION['usuario'] = $usuario;
$usuario_data = $db->fetchOne("SELECT id_usuario, usuario, correo, password, pregunta_seguridad FROM usuarios WHERE id_usuario = ?", [$id_usuario]);

        registrarAuditoria('editar', 'Perfil actualizado por el propio usuario.');
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'TUS DATOS SE ACTUALIZARON CORRECTAMENTE.'];
        header("Location: perfil.php");
        exit();
    }
}

$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/diseno.php'; ?>
    <title>Mi Perfil | JV3000 C.A.</title>
        <link rel="stylesheet" href="../assets/dashboard/perfil.css?v=3">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-wrapper" id="mainWrapper">
<div class="container-fluid px-4 py-4">

    <?php if ($flash): ?>
        <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?>" style="padding:12px 18px;font-size:.85rem;font-weight:600;">
            <?php echo htmlspecialchars($flash['texto']); ?>
        </div>
    <?php endif; ?>
    <?php foreach ($errores as $err): ?>
        <div class="alert-jv alert-jv-danger" style="padding:12px 18px;font-size:.85rem;font-weight:600;">
            <?php echo htmlspecialchars($err); ?>
        </div>
    <?php endforeach; ?>

    <!-- ENCABEZADO -->
    <div class="d-flex align-items-center gap-4 mb-4">
        <div class="perfil-header-icon"><i class="bi bi-person-gear"></i></div>
        <div>
            <h1 class="module-title">MI PERFIL</h1>
            <p class="module-subtitle">Gestiona tu información de acceso</p>
        </div>
    </div>

    <!-- TARJETA HERO DEL USUARIO -->
    <div class="profile-hero mb-4">
        <div class="profile-hero-bg" aria-hidden="true"></div>
        <div class="profile-avatar"><?php echo $inicial; ?></div>
        <div class="profile-hero-info">
            <h2 class="profile-name" title="<?php echo htmlspecialchars($usuario_data['usuario']); ?>"><?php echo htmlspecialchars($usuario_data['usuario']); ?></h2>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="profile-role"><i class="bi bi-shield-lock me-1"></i><?php echo htmlspecialchars($rol_perfil); ?></span>
                <span class="profile-role profile-role-status"><i class="bi bi-check-circle me-1"></i>ACTIVO</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card-jv p-4 perfil-form">
                <div class="header-card p-0 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-person-gear" style="color:var(--jv-navy);"></i>
                    <span class="fw-bold small text-secondary text-uppercase">Editar Datos de la Cuenta</span>
                </div>
                <form method="POST" class="row g-3" id="formPerfil">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion" value="actualizar_perfil">

                    <div class="col-md-6">
                        <label class="form-label-jv">USUARIO</label>
                        <input type="text" name="usuario" class="input-jv" required maxlength="50" value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" autocomplete="username">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-jv">CORREO ELECTRÓNICO</label>
                        <input type="email" name="correo" class="input-jv" required maxlength="100" value="<?php echo htmlspecialchars($usuario_data['correo'] ?? ''); ?>" autocomplete="email">
                    </div>
                    <div class="col-12">
                        <div class="info-row">
                            <div class="info-icon"><i class="bi bi-shield-lock"></i></div>
                            <div class="info-text">
                                <div class="info-label">ROL DE ACCESO</div>
                                <div class="info-value"><?php echo htmlspecialchars($rol_perfil); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="row g-3 align-items-center">
                            <div class="col-12">
                                <hr style="border-color:rgba(30,58,138,0.15);">
                                <span class="fw-bold small text-secondary text-uppercase"><i class="bi bi-key me-1"></i>Cambiar Contraseña <span class="text-jv-muted">(opcional, deja en blanco para conservarla)</span></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-jv">CONTRASEÑA ACTUAL *</label>
                                <input type="password" name="password_actual" class="input-jv" required autocomplete="current-password" placeholder="Necesaria para guardar cambios">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-jv">NUEVA CONTRASEÑA</label>
                                <input type="password" name="password_nueva" class="input-jv" autocomplete="new-password" placeholder="••••••••">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-jv">CONFIRMAR NUEVA</label>
                                <input type="password" name="password_confirm" class="input-jv" autocomplete="new-password" placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="row g-3 align-items-center">
                            <div class="col-12">
                                <hr style="border-color:rgba(30,58,138,0.15);">
                                <span class="fw-bold small text-secondary text-uppercase"><i class="bi bi-shield-question me-1"></i>Pregunta de Seguridad <span class="text-jv-muted">(para recuperar tu contraseña)</span></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-jv">SELECCIONA UNA PREGUNTA</label>
                                <select name="pregunta_seguridad" class="input-jv" required>
                                    <option value="">Seleccione una pregunta...</option>
                                    <?php foreach ($preguntas_opciones as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p); ?>" <?php echo ($p === ($usuario_data['pregunta_seguridad'] ?? '')) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-jv">TU RESPUESTA</label>
                                <input type="text" name="respuesta_seguridad" class="input-jv" required maxlength="255" autocomplete="off" placeholder="Escribe tu respuesta">
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-jv-primary px-5 py-3 fw-bolder text-uppercase">
                            <i class="bi bi-check2-circle me-2"></i>GUARDAR CAMBIOS
                        </button>
                        <button type="reset" class="btn btn-outline-secondary px-4 py-3 fw-bolder text-uppercase">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>RESTABLECER
                        </button>
                    </div>
                </form>
                <p class="small text-jv-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>La contraseña debe tener mínimo 8 caracteres, incluir mayúsculas, números y símbolos.</p>
            </div>
        </div>
    </div>
</div>
</div>
<!-- JAVASCRIPT -->
<script src="<?php echo $base_assets; ?>js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_assets; ?>js/sweetalert2.all.min.js"></script>
    <script src="../assets/dashboard/perfil.js?v=2"></script>
</body>
</html>
