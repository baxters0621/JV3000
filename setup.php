<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$step = $_GET['step'] ?? '1';
$error = '';

// Si ya está instalado, redirigir a login
try {
    $tmp = new mysqli('localhost', 'jv3000_app', 'JV3000_S3gur0!', 'jv3000_db');
    if (!$tmp->connect_error) {
        $res = $tmp->query("SELECT 1 FROM usuarios LIMIT 1");
        if ($res && $res->num_rows > 0) {
            header('Location: login.php');
            exit;
        }
    }
    $tmp->close();
} catch (Throwable $e) {}

// Procesar paso 2 (instalación)
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host  = trim($_POST['db_host'] ?? 'localhost');
    $db_root  = trim($_POST['db_root_user'] ?? 'root');
    $db_pass  = $_POST['db_root_pass'] ?? '';

    try {
        $mysqli = new mysqli($db_host, $db_root, $db_pass);
        if ($mysqli->connect_error) throw new Exception("Error de conexión: " . $mysqli->connect_error);

        $mysqli->query("CREATE DATABASE IF NOT EXISTS jv3000_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $mysqli->select_db('jv3000_db');

        $schema = file_get_contents(__DIR__ . '/db/schema.sql');
        if ($schema === false) throw new Exception("No se encontró db/schema.sql");

        foreach (explode(';', $schema) as $sql) {
            $sql = trim($sql);
            if (!empty($sql) && !$mysqli->query($sql)) {
                if (strpos($mysqli->error, 'already exists') === false && strpos($mysqli->error, 'Duplicate key') === false) {
                    throw new Exception("Error en schema: " . $mysqli->error);
                }
            }
        }

        $seed = file_get_contents(__DIR__ . '/db/seed.sql');
        if ($seed === false) throw new Exception("No se encontró db/seed.sql");

        foreach (explode(';', $seed) as $sql) {
            $sql = trim($sql);
            if (!empty($sql) && !$mysqli->query($sql)) {
                if (strpos($mysqli->error, 'Duplicate entry') === false) {
                    throw new Exception("Error en seed: " . $mysqli->error);
                }
            }
        }

        $mysqli->query("CREATE USER IF NOT EXISTS 'jv3000_app'@'localhost' IDENTIFIED BY 'JV3000_S3gur0!'");
        $mysqli->query("GRANT SELECT, INSERT, UPDATE, DELETE ON jv3000_db.* TO 'jv3000_app'@'localhost'");
        $mysqli->query("FLUSH PRIVILEGES");
        $mysqli->close();

        // Crear includes/config.php automáticamente
        $configPath = __DIR__ . '/includes/config.php';
        $configContent = <<<'PHP'
<?php

// === CONSTANTES GLOBALES (usadas por init.php y módulos) ===
define('DB_HOST', 'localhost');
define('DB_USER', 'jv3000_app');
define('DB_PASS', 'JV3000_S3gur0!');
define('DB_NAME', 'jv3000_db');
define('APP_NAME', 'JV3000 C.A.');
define('VERSION', '3.0.0');
define('BASE_ASSETS', (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'modules') ? '../assets/' : 'assets/');

// === LEGACY: mantener compatibilidad con módulos no migrados ===
if (!defined('INIT_LOADED')) {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.gc_maxlifetime', 0);
        session_start();
    }

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    $db   = DB_NAME;

    $conn = mysqli_connect($host, $user, $pass, $db);
    if (!$conn) {
        die("<div style='background:#020617; color:#f87171; font-family:sans-serif; text-align:center; padding:100px; height:100vh;'>
                <div style='max-width:600px; margin:auto; border:1px solid rgba(248,113,113,0.3); padding:40px; border-radius:20px; background:#0f172a;'>
                    <h2 style='color:#ef4444; text-transform:uppercase; letter-spacing:2px;'>&#x26A0;&#xFE0F;<br>Error de Enlace de Datos</h2>
                    <p style='color:#94a3b8; margin-top:20px; font-size:18px;'>El motor <b>JV3000 C.A.</b> no puede conectar con MySQL.</p>
                    <div style='background:#1e293b; padding:15px; border-radius:10px; color:#38bdf8; font-family:monospace; margin-top:20px; border:1px solid #334155;'>" . mysqli_connect_error() . "</div>
                    <p style='color:#64748b; margin-top:20px; font-size:14px;'>Verifica que XAMPP/MariaDB est&eacute; corriendo.</p>
                </div>
            </div>");
    }

    mysqli_set_charset($conn, "utf8mb4");
    date_default_timezone_set('America/Caracas');

    include_once __DIR__ . '/helpers.php';
}
PHP;
        file_put_contents($configPath, $configContent);

        header("Location: ?step=3");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Mostrar formulario de credenciales con error
    }
}

// Procesar paso 3 (crear admin)
if ($step === '3' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_user  = trim($_POST['admin_user'] ?? '');
    $admin_pass  = $_POST['admin_pass'] ?? '';
    $admin_email = strtolower(trim($_POST['admin_email'] ?? ''));

    if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $admin_user)) {
        $error = "USUARIO: MIN 4 Y MAX 20 CARACTERES (letras, numeros, guion bajo)";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error = "CORREO: FORMATO INVÁLIDO";
    } elseif (!preg_match('/^.{8,}$/', $admin_pass)) {
        $error = "CONTRASEÑA: MIN 8 CARACTERES";
    } else {
        try {
            $db = new mysqli('localhost', 'jv3000_app', 'JV3000_S3gur0!', 'jv3000_db');
            if ($db->connect_error) throw new Exception("Error conectando con jv3000_app: " . $db->connect_error);

            $pass_hash  = password_hash($admin_pass, PASSWORD_BCRYPT);
            $pregunta   = '¿Cuál es tu color favorito?';
            $resp_hash  = password_hash('admin', PASSWORD_BCRYPT);

            $stmt = $db->prepare("INSERT INTO usuarios (usuario, correo, password, rol, status, aprobado, pregunta_seguridad, respuesta_seguridad) VALUES (?, ?, ?, 'Administrador', 'Activo', 1, ?, ?)");
            $stmt->bind_param('sssss', $admin_user, $admin_email, $pass_hash, $pregunta, $resp_hash);
            $stmt->execute();
            $stmt->close();
            $db->close();

            header("Location: ?step=completado");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalación | JV3000 C.A.</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#020617;color:#f8fafc;font-family:system-ui,'Segoe UI',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#0f172a;border:1px solid rgba(6,182,212,0.15);border-radius:24px;padding:40px 36px;max-width:480px;width:100%;box-shadow:0 20px 50px -12px rgba(0,0,0,.5)}
h1{font-size:1.3rem;font-weight:800;color:#22d3ee;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.sub{color:#94a3b8;font-size:.85rem;margin-bottom:24px}
.step{font-size:.7rem;color:#64748b;margin-bottom:16px;letter-spacing:1px}
label{font-size:.75rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px}
input{width:100%;padding:12px 16px;border-radius:12px;background:#020617;border:1px solid rgba(255,255,255,.1);color:#f8fafc;font-size:.9rem;margin-bottom:16px;transition:.15s}
input:focus{outline:none;border-color:#06b6d4;box-shadow:0 0 0 3px rgba(6,182,212,.15)}
.info{background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.2);border-radius:12px;padding:12px 16px;font-size:.8rem;color:#94a3b8;margin-bottom:20px;line-height:1.5}
.info strong{color:#22d3ee}
button{width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,#06b6d4,#0891b2);color:#020617;font-weight:700;font-size:.85rem;letter-spacing:.5px;cursor:pointer;transition:.2s}
button:hover{transform:translateY(-2px);box-shadow:0 8px 25px -5px rgba(6,182,212,.4)}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;border-radius:12px;padding:12px 16px;font-size:.8rem;margin-bottom:16px}
.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:12px;padding:12px 16px;font-size:.8rem;margin-bottom:16px}
small{color:#64748b;font-size:.7rem;display:block;margin-top:-12px;margin-bottom:16px}
.warn{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:12px;padding:12px 16px;font-size:.75rem;color:#fbbf24;margin-top:20px;line-height:1.5}
.btn{display:inline-block;margin-top:24px;padding:14px 32px;border-radius:12px;background:linear-gradient(135deg,#06b6d4,#0891b2);color:#020617;font-weight:700;font-size:.85rem;text-decoration:none;letter-spacing:.5px;transition:.2s}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px -5px rgba(6,182,212,.4)}
.icon{font-size:3rem;margin-bottom:12px}
</style>
</head>
<body>
 <div class="card">

<?php if ($step === 'completado'): ?>
  <div class="icon">✅</div>
  <h1>INSTALACIÓN COMPLETA</h1>
  <p>Base de datos y administrador creados correctamente.</p>
  <p style="margin-top:8px;">Ya puedes iniciar sesión.</p>
  <a href="login.php" class="btn">IR AL INICIO DE SESIÓN</a>
  <div class="warn">⚠️ Por seguridad, elimina el archivo <strong>setup.php</strong> después de la instalación.</div>

<?php elseif ($step === '3' || $step === 'error_admin'): ?>
  <div class="step">PASO 2 DE 2</div>
  <h1>👤 ADMINISTRADOR</h1>
  <p class="sub">Crea el primer usuario administrador</p>
  <?php if ($error): ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <form method="POST" action="?step=3">
    <label>Usuario</label>
    <input type="text" name="admin_user" required minlength="4" maxlength="20" placeholder="admin">
    <label>Correo electrónico</label>
    <input type="email" name="admin_email" required placeholder="admin@correo.com">
    <label>Contraseña</label>
    <input type="password" name="admin_pass" required minlength="8" placeholder="Min. 8 caracteres">
    <small>La pregunta de seguridad se configurará por defecto.</small>
    <button type="submit">CREAR ADMINISTRADOR</button>
  </form>

<?php else: ?>
  <h1>⚙️ INSTALACIÓN</h1>
  <p class="sub">Configuración inicial de la base de datos</p>
  <?php if ($error): ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <div class="info">
    <strong>¿Qué va a pasar?</strong><br>
    1. Se conectará al motor MySQL con las credenciales root<br>
    2. Se creará la base de datos <strong>jv3000_db</strong><br>
    3. Se creará el usuario <strong>jv3000_app</strong> con permisos<br>
    4. Se importarán las tablas y datos iniciales
  </div>
  <form method="POST" action="?step=2">
    <label>Host MySQL</label>
    <input type="text" name="db_host" value="localhost" required>
    <label>Usuario root</label>
    <input type="text" name="db_root_user" value="root" required>
    <label>Contraseña root</label>
    <input type="password" name="db_root_pass" required>
    <small>Se usa solo para la instalación, no se guarda.</small>
    <button type="submit">INICIAR INSTALACIÓN</button>
  </form>
<?php endif; ?>

 </div>
</body>
</html>
