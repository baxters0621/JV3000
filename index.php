<?php

// ==========================================
// FRONT CONTROLLER — Punto de entrada único
// ==========================================
// Carga init (sesión, seguridad, DB), configura
// las rutas MVC y delega al Router.
//   http://localhost/JV3000_db/index.php?url=compras
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/config/config.php';

// Autoload de modelos y núcleo
spl_autoload_register(function ($class) {
    $map = [
        APP_MODELS . "/{$class}.php",
        APP_CORE . "/{$class}.php",
    ];
    foreach ($map as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Sesión de usuario presente → permitir rutas autenticadas.
// (init.php ya validó la sesión salvo páginas públicas.)
$url = trim($_GET['url'] ?? '', '/');

if ($url === '') {
    // Sin ruta: comportamiento original → panel de inicio
    header('Location: ' . BASE_PATH . 'dashboard/index.php');
    exit;
}

Router::dispatch($url);
