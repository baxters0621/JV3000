<?php

// ==========================================
// ROUTER — Convierte la URL en Controlador@método
// ==========================================
// Formato:  index.php?url=controlador/accion/param1/param2
// Ejemplo:  index.php?url=compras

/**
 * Router: convierte la URL recibida (vía parámetro ?url=) en la llamada
 * al controlador y método adecuados, cargando previamente las clases base.
 */
class Router
{
    // Clase base de controladores que se autoload al inicio
    private static bool $baseLoaded = false;

    /**
     * Despacha la URL solicitada hacia el controlador y método correspondientes.
     *
     * Descompone la URL en [controlador]/[accion]/[parametros...], instancia el
     * controlador (por defecto ComprasController::index) e invoca la acción con
     * los parámetros restantes. Carga antes las clases base (Controller/Model)
     * y responde con 404 si el controlador, la clase o la acción no existen.
     *
     * @param string $url Ruta MVC completa, p. ej. "compras".
     * @return void
     */
    public static function dispatch(string $url): void
    {
        self::loadBase();

        $parts = array_values(array_filter(explode('/', trim($url, '/')), 'strlen'));

        // Controlador por defecto
        $controllerName = 'ComprasController';
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

    /**
     * Convierte un segmento de URL en el nombre CamelCase del controlador.
     *
     * Separa por guiones/guiones bajos, pone cada palabra en mayúscula y las
     * une. Ejemplo: "compras" → "Compras". El sufijo "Controller" lo añade
     * quien lo invoca.
     *
     * @param string $segment Segmento de URL (normalmente la primera parte).
     * @return string Palabras unidas en CamelCase.
     */
    private static function camelize(string $segment): string
    {
        $words = preg_split('/[-_]/', $segment);
        $words = array_map(fn($w) => ucfirst(strtolower($w)), $words);
        return implode('', $words);
    }

    /**
     * Carga una sola vez las clases base Controller y Model (autoload perezoso).
     *
     * Evita incluirlas manualmente en cada controlador: se registran aquí la
     * primera vez que se despacha una ruta.
     *
     * @return void
     */
    private static function loadBase(): void
    {
        if (self::$baseLoaded) return;
        require_once APP_CORE . '/Controller.php';
        require_once APP_CORE . '/Model.php';
        self::$baseLoaded = true;
    }

    /**
     * Responde con un error 404 y el mensaje correspondiente.
     *
     * Termina la ejecución del script inmediatamente después de emitir la
     * respuesta, indicando al usuario qué pieza de la ruta no se encontró.
     *
     * @param string $msg Mensaje descriptivo mostrado en la página 404.
     * @return void
     */
    private static function notFound(string $msg): void
    {
        http_response_code(404);
        die('<h1 style="font-family:sans-serif;color:#4338ca;">404 — ' . htmlspecialchars($msg) . '</h1>');
    }
}
