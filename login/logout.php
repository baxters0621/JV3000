<?php
// ==========================================
// CERRAR SESIÓN
// ==========================================
require_once __DIR__ . '/../init.php';

$db = Database::getInstance();

$id_usuario = intval($_SESSION['id_usuario'] ?? 0);

if ($id_usuario > 0) {
    registrarAuditoria('logout', 'Cierre de sesión');
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: login.php?res=logout");
exit();
