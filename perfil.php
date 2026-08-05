<?php
// ==========================================
// CONFIGURACIÓN INICIAL
// ==========================================
require_once __DIR__ . '/init.php';

$db = Database::getInstance();
$csrf_token = Security::generateToken();

$id_usuario = (int)$_SESSION['id_usuario'];
$usuario_data = $db->fetchOne("SELECT id_usuario, usuario, correo, password FROM usuarios WHERE id_usuario = ?", [$id_usuario]);
if (!$usuario_data) {
    header("Location: index.php");
    exit();
}

$roles_map = [1 => 'Administrador', 2 => 'Operador de Carga', 3 => 'Operador de Ventas'];
$rol_perfil = $roles_map[(int)($_SESSION['id_rol'] ?? 0)] ?? 'Sin rol';

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
<?php include 'includes/diseno.php'; ?>
    <title>Mi Perfil | JV3000 C.A.</title>
        <link rel="stylesheet" href="assets/dashboard/perfil.css">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
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
    <div class="card-jv d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" style="padding:18px 24px;border-left:4px solid var(--jv-orange);">
        <div class="d-flex align-items-center gap-3">
            <div class="perfil-header-icon"><i class="bi bi-person-gear"></i></div>
            <div>
                <h1 class="font-brand fw-bold m-0" style="font-size:1.4rem; color: var(--jv-text-primary);">MI PERFIL</h1>
                <p class="m-0 text-secondary" style="font-size:.85rem;">Gestiona tu información de acceso</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- INFORMACIÓN DE LA CUENTA -->
        <div class="col-lg-5">
            <div class="perfil-info h-100">
                <p class="label mb-4">INFORMACIÓN DE LA CUENTA</p>
                <div class="mb-4">
                    <p class="label mb-1">USUARIO</p>
                    <p class="valor mb-0"><?php echo htmlspecialchars($usuario_data['usuario']); ?></p>
                </div>
                <div class="mb-4">
                    <p class="label mb-1">CORREO ELECTRÓNICO</p>
                    <p class="valor mb-0"><?php echo htmlspecialchars($usuario_data['correo'] ?? '—'); ?></p>
                </div>
                <div class="mb-0">
                    <p class="label mb-1">ROL</p>
                    <p class="valor mb-0"><?php echo htmlspecialchars($rol_perfil); ?></p>
                </div>
            </div>
        </div>

        <!-- CAMBIO DE CONTRASEÑA -->
        <div class="col-lg-7">
            <div class="card-jv p-4 h-100 perfil-form">
                <p class="label mb-3">CAMBIAR CONTRASEÑA</p>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="accion" value="cambiar_password">
                    <div class="col-12">
                        <label class="small fw-bold text-secondary mb-1">CONTRASEÑA ACTUAL</label>
                        <input type="password" name="password_actual" class="input-jv" required autocomplete="current-password">
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary mb-1">NUEVA CONTRASEÑA</label>
                        <input type="password" name="password_nueva" class="input-jv" required autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-secondary mb-1">CONFIRMAR NUEVA CONTRASEÑA</label>
                        <input type="password" name="password_confirm" class="input-jv" required autocomplete="new-password">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn-jv-primary">GUARDAR CONTRASEÑA</button>
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
    <script src="assets/dashboard/perfil.js"></script>
</body>
</html>
