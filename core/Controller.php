<?php

// ==========================================
// CONTROLLER BASE — El intermediario
// ==========================================
// Recibe la petición, pide datos al Modelo y
// le pasa el resultado a la Vista. No toca SQL.
abstract class Controller
{
    protected array $viewData = [];

    // Cargar una vista dentro del layout principal
    protected function view(string $view, array $data = []): void
    {
        $this->viewData = $data;
        $__view = $view;
        extract($data);

        $layout = APP_VIEWS . '/layouts/main.php';
        if (file_exists($layout)) {
            require $layout;
        } else {
            $this->renderRaw($view, $data);
        }
    }

    // Render directo sin layout (útil para vistas embebidas)
    protected function renderRaw(string $view, array $data = []): void
    {
        extract($data);
        $file = APP_VIEWS . "/{$view}.php";
        if (!file_exists($file)) {
            http_response_code(500);
            die('Vista no encontrada: ' . htmlspecialchars($view));
        }
        require $file;
    }

    // Respuesta JSON (AJAX)
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    // Redirección amigable a una ruta MVC
    protected function redirect(string $route = '', array $query = []): void
    {
        $url = APP_URL_BASE . 'index.php?url=' . ltrim($route, '/');
        if (!empty($query)) {
            $url .= '&' . http_build_query($query);
        }
        header('Location: ' . $url);
        exit;
    }

    // Mensaje flash
    protected function flash(string $tipo, string $texto): void
    {
        $_SESSION['flash_msg'] = ['tipo' => $tipo, 'texto' => $texto];
    }
}
