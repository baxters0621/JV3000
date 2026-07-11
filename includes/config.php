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

    $conn_no_db = mysqli_connect($host, $user, $pass);
    if (!$conn_no_db) {
        die("<div style='background:#020617; color:#f87171; font-family:sans-serif; text-align:center; padding:100px; height:100vh;'>
                <div style='max-width:600px; margin:auto; border:1px solid rgba(248,113,113,0.3); padding:40px; border-radius:20px; background:#0f172a;'>
                    <h2 style='color:#ef4444; text-transform:uppercase; letter-spacing:2px;'>&#x26A0;&#xFE0F;<br>Error de Enlace de Datos</h2>
                    <p style='color:#94a3b8; margin-top:20px; font-size:18px;'>El motor <b>JV3000 C.A.</b> no puede conectar con MySQL.</p>
                    <div style='background:#1e293b; padding:15px; border-radius:10px; color:#38bdf8; font-family:monospace; margin-top:20px; border:1px solid #334155;'>" . mysqli_connect_error() . "</div>
                    <p style='color:#64748b; margin-top:20px; font-size:14px;'>Verifica que XAMPP/MariaDB est&eacute; corriendo.</p>
                </div>
            </div>");
    }

    $db_check = mysqli_query($conn_no_db, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db'");
    if (mysqli_num_rows($db_check) == 0) {
        $sql_path = __DIR__ . '/../db/jv3000_db.sql';
        if (file_exists($sql_path)) {
            $sql_content = file_get_contents($sql_path);
            if (!empty($sql_content)) {
                mysqli_multi_query($conn_no_db, $sql_content);
                do {
                    if ($res = mysqli_store_result($conn_no_db)) {
                        mysqli_free_result($res);
                    }
                } while (mysqli_next_result($conn_no_db));
                $import_err = mysqli_error($conn_no_db);
                if ($import_err) {
                    die("<div style='background:#020617; color:#f87171; font-family:sans-serif; text-align:center; padding:100px; height:100vh;'>
                            <div style='max-width:600px; margin:auto; border:1px solid rgba(248,113,113,0.3); padding:40px; border-radius:20px; background:#0f172a;'>
                                <h2 style='color:#ef4444; text-transform:uppercase; letter-spacing:2px;'>Error de Instalacion</h2>
                                <p style='color:#94a3b8; margin-top:20px;'>No se pudo importar la base de datos.</p>
                                <div style='background:#1e293b; padding:15px; border-radius:10px; color:#38bdf8; font-family:monospace; margin-top:20px; border:1px solid #334155;'>$import_err</div>
                            </div>
                        </div>");
                }
            }
        } else {
            die("<div style='background:#020617; color:#f87171; font-family:sans-serif; text-align:center; padding:100px; height:100vh;'>
                    <div style='max-width:600px; margin:auto; border:1px solid rgba(248,113,113,0.3); padding:40px; border-radius:20px; background:#0f172a;'>
                        <h2 style='color:#ef4444; text-transform:uppercase; letter-spacing:2px;'>Archivo Faltante</h2>
                        <p style='color:#94a3b8; margin-top:20px;'>No se encuentra <b>db/jv3000_db.sql</b>. Copia el archivo SQL en la carpeta <i>db/</i>.</p>
                    </div>
                </div>");
        }
    }
    mysqli_close($conn_no_db);

    $conn = mysqli_connect($host, $user, $pass, $db);
    if (!$conn) {
        die("<div style='background:#020617; color:#f87171; font-family:sans-serif; text-align:center; padding:100px; height:100vh;'>
                <div style='max-width:600px; margin:auto; border:1px solid rgba(248,113,113,0.3); padding:40px; border-radius:20px; background:#0f172a;'>
                    <h2 style='color:#ef4444; text-transform:uppercase; letter-spacing:2px;'>Error de Conexion</h2>
                    <p style='color:#94a3b8; margin-top:20px;'>No se pudo conectar a la base de datos <b>$db</b>.</p>
                    <div style='background:#1e293b; padding:15px; border-radius:10px; color:#38bdf8; font-family:monospace; margin-top:20px; border:1px solid #334155;'>" . mysqli_connect_error() . "</div>
                </div>
            </div>");
    }

    mysqli_set_charset($conn, "utf8mb4");
    date_default_timezone_set('America/Caracas');

    include_once __DIR__ . '/helpers.php';
}
