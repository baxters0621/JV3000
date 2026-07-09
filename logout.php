<?php
// --- Tab close beacon: silent session destroy ---
if (isset($_GET['action']) && $_GET['action'] === 'tab_closed') {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/init.php';

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
