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

// ==========================================
// CAMBIAR CONTRASEÑA
// ==========================================
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_password') {
    $actual = $_POST['password_actual'] ?? '';
    $nueva = $_POST['password_nueva'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (!password_verify($actual, $usuario_data['password'])) {
        $errores[] = 'La contraseña actual es incorrecta.';
    } else {
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

    if (empty($errores)) {
        $hash = password_hash($nueva, PASSWORD_BCRYPT);
        $db->execute("UPDATE usuarios SET password = ? WHERE id_usuario = ?", [$hash, $id_usuario]);
        registrarAuditoria('editar', 'Contraseña modificada por el propio usuario.');
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'CONTRASEÑA ACTUALIZADA CORRECTAMENTE.'];
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
        <link rel="stylesheet" href="../assets/dashboard/perfil.css?v=2">
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
            <h1 class="font-brand mb-1" style="font-size:2rem;letter-spacing:-1px; color: var(--jv-text-primary);">MI PERFIL</h1>
            <p class="text-secondary small fw-bold text-uppercase mb-0">Gestiona tu información de acceso</p>
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
        <!-- INFORMACIÓN DE LA CUENTA -->
        <div class="col-lg-5">
            <div class="card-jv p-0 overflow-hidden h-100">
                <div class="header-card d-flex align-items-center gap-2">
                    <i class="bi bi-person-lines-fill" style="color:var(--jv-navy);"></i>
                    <span class="fw-bold small text-secondary text-uppercase">Información de la Cuenta</span>
                </div>
                <div class="perfil-info">
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-person-badge"></i></div>
                        <div class="info-text">
                            <div class="info-label">USUARIO</div>
                            <div class="info-value"><?php echo htmlspecialchars($usuario_data['usuario']); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-envelope"></i></div>
                        <div class="info-text">
                            <div class="info-label">CORREO ELECTRÓNICO</div>
                            <div class="info-value text-truncate" style="max-width:100%;"><?php echo htmlspecialchars($usuario_data['correo'] ?? '—'); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-shield-lock"></i></div>
                        <div class="info-text">
                            <div class="info-label">ROL DE ACCESO</div>
                            <div class="info-value"><?php echo htmlspecialchars($rol_perfil); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CAMBIO DE CONTRASEÑA -->
        <div class="col-lg-7">
            <div class="card-jv p-4 h-100 perfil-form">
                <div class="header-card p-0 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-key" style="color:var(--jv-navy);"></i>
                    <span class="fw-bold small text-secondary text-uppercase">Cambiar Contraseña</span>
                </div>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion" value="cambiar_password">
                    <div class="col-12">
                        <label class="form-label-jv">CONTRASEÑA ACTUAL</label>
                        <input type="password" name="password_actual" class="input-jv" required autocomplete="current-password" placeholder="••••••••">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-jv">NUEVA CONTRASEÑA</label>
                        <input type="password" name="password_nueva" class="input-jv" required autocomplete="new-password" placeholder="••••••••">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-jv">CONFIRMAR NUEVA</label>
                        <input type="password" name="password_confirm" class="input-jv" required autocomplete="new-password" placeholder="••••••••">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-jv-primary w-100 py-3 fw-bolder text-uppercase">
                            <i class="bi bi-shield-check me-2"></i>GUARDAR CONTRASEÑA
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
    <script src="../assets/dashboard/perfil.js"></script>
</body>
</html>
