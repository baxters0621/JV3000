<?php

// ==========================================
// ROUTER — Convierte la URL en Controlador@método
// ==========================================
// Formato:  index.php?url=controlador/accion/param1/param2
// Ejemplo:  index.php?url=solicitudes/cancelar/5
class Router
{
    // Clase base de controladores que se autoload al inicio
    private static bool $baseLoaded = false;

    public static function dispatch(string $url): void
    {
        self::loadBase();

        $parts = array_values(array_filter(explode('/', trim($url, '/')), 'strlen'));

        // Controlador por defecto
        $controllerName = 'SolicitudesController';
        $action = 'index';
        $params = [];

        if (isset($parts[0]) && $parts[0] !== '') {
            $controllerName = self::camelize($parts[0]) . 'Controller';
        }

        if (isset($parts[1]) && $parts[1] !== '') {
            $action = $parts[1];
        }

        if (isset($parts[2])) {
            $params = array_slice($parts, 2);
        }

        $controllerFile = APP_CONTROLLERS . "/{$controllerName}.php";

        if (!file_exists($controllerFile)) {
            self::notFound("Controlador no encontrado: {$controllerName}");
        }

        require_once $controllerFile;

        if (!class_exists($controllerName)) {
            self::notFound("Clase no encontrada: {$controllerName}");
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $action)) {
            self::notFound("Acción no encontrada: {$action}");
        }

        call_user_func_array([$controller, $action], $params);
    }

    // Convierte "solicitudes-de-repo" / "solicitudes" → "Solicitudes"
    private static function camelize(string $segment): string
    {
        $words = preg_split('/[-_]/', $segment);
        $words = array_map(fn($w) => ucfirst(strtolower($w)), $words);
        return implode('', $words);
    }

    private static function loadBase(): void
    {
        if (self::$baseLoaded) return;
        require_once APP_CORE . '/Controller.php';
        require_once APP_CORE . '/Model.php';
        self::$baseLoaded = true;
    }

    private static function notFound(string $msg): void
    {
        http_response_code(404);
        die('<h1 style="font-family:sans-serif;color:#4338ca;">404 — ' . htmlspecialchars($msg) . '</h1>');
    }
}
