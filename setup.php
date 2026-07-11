<?php
// ============================================================
// SETUP - Instalador web de JV3000 C.A.
// ============================================================
// 1. Ejecutar este archivo desde el navegador en el PC nuevo
// 2. Ingresar credenciales del usuario root de MySQL
// 3. El instalador crea la BD, el usuario jv3000_app, importa esquema
//    y te permite crear el primer administrador.
// 4. Al finalizar, redirige a login.php.
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');

$step = $_GET['step'] ?? '1';
$error = '';
$exito = '';
$db_host = 'localhost';

// Detectar si ya está instalado
$ya_instalado = false;
try {
    $tmp = new mysqli($db_host, 'jv3000_app', 'JV3000_S3gur0!', 'jv3000_db');
    if (!$tmp->connect_error) {
        $res = $tmp->query("SELECT 1 FROM usuarios LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $ya_instalado = true;
        }
    }
    $tmp->close();
} catch (Throwable $e) {}

if ($ya_instalado && $step !== 'completado') {
    header('Location: login.php');
    exit;
}

// ── STEP 1: Formulario de conexión MySQL ──
if ($step === '1' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación | JV3000 C.A.</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #020617; color: #f8fafc;
            font-family: system-ui, 'Segoe UI', sans-serif;
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center; padding: 24px;
        }
        .card {
            background: #0f172a; border: 1px solid rgba(6,182,212,0.15);
            border-radius: 24px; padding: 40px 36px;
            max-width: 480px; width: 100%;
            box-shadow: 0 20px 50px -12px rgba(0,0,0,0.5);
        }
        h1 {
            font-size: 1.3rem; font-weight: 800; color: #22d3ee;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;
        }
        .sub { color: #94a3b8; font-size: .85rem; margin-bottom: 24px; }
        label { font-size: .75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 4px; }
        input {
            width: 100%; padding: 12px 16px; border-radius: 12px;
            background: #020617; border: 1px solid rgba(255,255,255,0.1);
            color: #f8fafc; font-size: .9rem; margin-bottom: 16px;
            transition: .15s;
        }
        input:focus { outline: none; border-color: #06b6d4; box-shadow: 0 0 0 3px rgba(6,182,212,0.15); }
        .info { background: rgba(6,182,212,0.08); border: 1px solid rgba(6,182,212,0.2); border-radius: 12px; padding: 12px 16px; font-size: .8rem; color: #94a3b8; margin-bottom: 20px; line-height: 1.5; }
        .info strong { color: #22d3ee; }
        button {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            background: linear-gradient(135deg,#06b6d4,#0891b2);
            color: #020617; font-weight: 700; font-size: .85rem;
            letter-spacing: .5px; cursor: pointer; transition: .2s;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -5px rgba(6,182,212,0.4); }
        .err { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; border-radius: 12px; padding: 12px 16px; font-size: .8rem; margin-bottom: 16px; }
        .ok { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; border-radius: 12px; padding: 12px 16px; font-size: .8rem; margin-bottom: 16px; }
        small { color: #64748b; font-size: .7rem; display: block; margin-top: -12px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>⚙️ INSTALACIÓN</h1>
        <p class="sub">Configuración inicial de la base de datos</p>

        <div class="info">
            <strong>¿Qué va a pasar?</strong><br>
            1. Se conectará al motor MySQL con las credenciales root<br>
            2. Se creará la base de datos <strong>jv3000_db</strong><br>
            3. Se creará el usuario <strong>jv3000_app</strong> con permisos<br>
            4. Se importarán las tablas y datos iniciales<br>
            5. Se actualizará <strong>includes/config.php</strong>
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
    </div>
</body>
</html>
<?php
    exit;
}

// ── STEP 2: Ejecutar instalación ──
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_root = trim($_POST['db_root_user'] ?? 'root');
    $db_pass = $_POST['db_root_pass'] ?? '';

    try {
        $mysqli = new mysqli($db_host, $db_root, $db_pass);
        if ($mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $mysqli->connect_error);
        }

        // Crear base de datos si no existe
        $mysqli->query("CREATE DATABASE IF NOT EXISTS jv3000_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $mysqli->select_db('jv3000_db');

        // Importar esquema (schema.sql)
        $schema = file_get_contents(__DIR__ . '/db/schema.sql');
        if ($schema === false) {
            throw new Exception("No se encontró db/schema.sql");
        }
        // Separar por ; y ejecutar cada sentencia
        $sentencias = explode(';', $schema);
        foreach ($sentencias as $sql) {
            $sql = trim($sql);
            if (!empty($sql)) {
                if (!$mysqli->query($sql)) {
                    // Ignorar errores de "already exists" en CREATE TABLE IF NOT EXISTS
                    if (strpos($mysqli->error, 'already exists') === false && strpos($mysqli->error, 'Duplicate key') === false) {
                        throw new Exception("Error en schema: " . $mysqli->error);
                    }
                }
            }
        }

        // Importar datos iniciales (seed.sql)
        $seed = file_get_contents(__DIR__ . '/db/seed.sql');
        if ($seed === false) {
            throw new Exception("No se encontró db/seed.sql");
        }
        $sentencias = explode(';', $seed);
        foreach ($sentencias as $sql) {
            $sql = trim($sql);
            if (!empty($sql)) {
                if (!$mysqli->query($sql)) {
                    if (strpos($mysqli->error, 'Duplicate entry') === false) {
                        throw new Exception("Error en seed: " . $mysqli->error);
                    }
                }
            }
        }

        // Crear usuario jv3000_app (si no existe)
        $mysqli->query("CREATE USER IF NOT EXISTS 'jv3000_app'@'localhost' IDENTIFIED BY 'JV3000_S3gur0!'");
        $mysqli->query("GRANT SELECT, INSERT, UPDATE, DELETE ON jv3000_db.* TO 'jv3000_app'@'localhost'");
        $mysqli->query("FLUSH PRIVILEGES");

        $mysqli->close();

        // Actualizar includes/config.php con credenciales
        $configPath = __DIR__ . '/includes/config.php';
        $configContent = file_get_contents($configPath);
        if ($configContent !== false) {
            $configContent = preg_replace(
                "/define\('DB_HOST',\s*'[^']*'\)/",
                "define('DB_HOST', '$db_host')",
                $configContent
            );
            file_put_contents($configPath, $configContent);
        }

        // Redirigir al paso 3: crear admin
        header("Location: ?step=3");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
        // Mostrar formulario de nuevo con error
    }
}

// ── STEP 3: Crear administrador ──
if ($step === '3') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $admin_user = trim($_POST['admin_user'] ?? '');
        $admin_pass = $_POST['admin_pass'] ?? '';
        $admin_email = strtolower(trim($_POST['admin_email'] ?? ''));

        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $admin_user)) {
            $error = "USUARIO: MIN 4 Y MAX 20 CARACTERES (letras, numeros, guion bajo)";
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = "CORREO: FORMATO INVÁLIDO";
        } elseif (!preg_match('/^.{8,}$/', $admin_pass)) {
            $error = "CONTRASEÑA: MIN 8 CARACTERES";
        } else {
            try {
                $db = new mysqli($db_host, 'jv3000_app', 'JV3000_S3gur0!', 'jv3000_db');
                if ($db->connect_error) {
                    throw new Exception("Error conectando con jv3000_app: " . $db->connect_error);
                }

                $pass_hash = password_hash($admin_pass, PASSWORD_BCRYPT);
                $pregunta = '¿Cuál es tu color favorito?';
                $resp_hash = password_hash('admin', PASSWORD_BCRYPT);

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Admin | JV3000 C.A.</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #020617; color: #f8fafc;
            font-family: system-ui, 'Segoe UI', sans-serif;
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center; padding: 24px;
        }
        .card {
            background: #0f172a; border: 1px solid rgba(6,182,212,0.15);
            border-radius: 24px; padding: 40px 36px;
            max-width: 480px; width: 100%;
            box-shadow: 0 20px 50px -12px rgba(0,0,0,0.5);
        }
        h1 { font-size: 1.3rem; font-weight: 800; color: #22d3ee; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .sub { color: #94a3b8; font-size: .85rem; margin-bottom: 24px; }
        .step { font-size: .7rem; color: #64748b; margin-bottom: 16px; letter-spacing: 1px; }
        label { font-size: .75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 4px; }
        input {
            width: 100%; padding: 12px 16px; border-radius: 12px;
            background: #020617; border: 1px solid rgba(255,255,255,0.1);
            color: #f8fafc; font-size: .9rem; margin-bottom: 16px;
        }
        input:focus { outline: none; border-color: #06b6d4; }
        button {
            width: 100%; padding: 14px; border: none; border-radius: 12px;
            background: linear-gradient(135deg,#06b6d4,#0891b2);
            color: #020617; font-weight: 700; font-size: .85rem;
            letter-spacing: .5px; cursor: pointer;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -5px rgba(6,182,212,0.4); }
        .err { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; border-radius: 12px; padding: 12px 16px; font-size: .8rem; margin-bottom: 16px; }
        .ok { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; border-radius: 12px; padding: 12px 16px; font-size: .8rem; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="step">PASO 2 DE 2</div>
        <h1>👤 ADMINISTRADOR</h1>
        <p class="sub">Crea el primer usuario administrador</p>

        <?php if ($error): ?>
            <div class="err"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Usuario</label>
            <input type="text" name="admin_user" required minlength="4" maxlength="20" placeholder="admin">
            <label>Correo electrónico</label>
            <input type="email" name="admin_email" required placeholder="admin@correo.com">
            <label>Contraseña</label>
            <input type="password" name="admin_pass" required minlength="8" placeholder="Min. 8 caracteres">
            <small style="color:#64748b;font-size:.7rem;display:block;margin-top:-12px;margin-bottom:16px;">La pregunta de seguridad se configurará por defecto.</small>
            <button type="submit">CREAR ADMINISTRADOR</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// ── COMPLETADO ──
if ($step === 'completado') {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación Completa | JV3000 C.A.</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #020617; color: #f8fafc;
            font-family: system-ui, 'Segoe UI', sans-serif;
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center; padding: 24px;
        }
        .card {
            background: #0f172a; border: 1px solid rgba(6,182,212,0.15);
            border-radius: 24px; padding: 40px 36px;
            max-width: 480px; width: 100%;
            box-shadow: 0 20px 50px -12px rgba(0,0,0,0.5);
            text-align: center;
        }
        .icon { font-size: 3rem; margin-bottom: 12px; }
        h1 { font-size: 1.3rem; font-weight: 800; color: #22d3ee; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        p { color: #94a3b8; font-size: .85rem; line-height: 1.5; }
        .btn {
            display: inline-block; margin-top: 24px; padding: 14px 32px;
            border-radius: 12px; background: linear-gradient(135deg,#06b6d4,#0891b2);
            color: #020617; font-weight: 700; font-size: .85rem; text-decoration: none;
            letter-spacing: .5px; transition: .2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -5px rgba(6,182,212,0.4); }
        .warn { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); border-radius: 12px; padding: 12px 16px; font-size: .75rem; color: #fbbf24; margin-top: 20px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>INSTALACIÓN COMPLETA</h1>
        <p>La base de datos y el administrador se crearon correctamente.</p>
        <p style="margin-top:8px;">Ya puedes iniciar sesión con tu usuario administrador.</p>
        <a href="login.php" class="btn">IR AL INICIO DE SESIÓN</a>
        <div class="warn">
            ⚠️ Por seguridad, elimina el archivo <strong>setup.php</strong> después de la instalación.
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// Si llegamos aquí sin step válido, redirigir
header("Location: ?step=1");
exit;
