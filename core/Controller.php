<?php

// ==========================================
// CONTROLLER BASE — El intermediario
// ==========================================
// Recibe la petición, pide datos al Modelo y
// le pasa el resultado a la Vista. No toca SQL.

/**
 * Controller: clase base de la que heredan todos los controladores.
 *
 * Actúa de intermediario entre la petición web, el modelo y la vista.
 * Ofrece utilidades para renderizar vistas (con o sin layout), responder
 * JSON (AJAX), redirigir y guardar mensajes flash en sesión.
 */
abstract class Controller
{
    protected array $viewData = [];

    /**
     * Añade la versión de caché (?v=mtime) a los assets propios.
     *
     * Recorre $css_extra/$js_extra y añade ?v=<fecha de modificación> a cada
     * archivo local que exista en assets/. Los recursos externos (http/https)
     * y los que ya traen ?v= o # se respetan reemplazando la versión existente
     * para evitar generar ?v=5?v=12345. Si el archivo no existe, se deja el
     * recurso tal cual sin añadir nada nuevo.
     *
     * @param array $data Datos de vista que pueden contener css_extra/js_extra.
     * @return array Datos con los assets versionados.
     */
    protected function versionarAssets(array $data): array
    {
        foreach (['css_extra', 'js_extra'] as $clave) {
            if (empty($data[$clave])) {
                continue;
            }
            $lista = is_array($data[$clave]) ? $data[$clave] : [$data[$clave]];
            foreach ($lista as $indice => $recurso) {
                if (!is_string($recurso) || preg_match('#^(https?:)?//#i', $recurso)) {
                    continue;
                }
                $base = preg_split('/[?#]/', $recurso)[0];
                $version = jv_asset_version($base);
                if ($version === '') {
                    $limpio = preg_replace('/([?&])v=[^&]*/', '$1', $recurso);
                    $limpio = rtrim($limpio, '?&');
                    $lista[$indice] = $limpio;
                    continue;
                }
                $rutaLimpia = preg_replace('/([?&])v=[^&]*/', '$1', $base);
                $rutaLimpia = rtrim($rutaLimpia, '?&');
                if (strpos($rutaLimpia, '?') === false) {
                    $lista[$indice] = $rutaLimpia . $version;
                } else {
                    $lista[$indice] = $rutaLimpia . '&' . ltrim($version, '?');
                }
            }
            $data[$clave] = $lista;
        }
        return $data;
    }

    /**
     * Carga una vista dentro del layout principal.
     *
     * Guarda los datos en $this->viewData, extrae el arreglo $data a variables
     * locales y requiere el layout principal. Si el layout no existe, hace un
     * render directo sin layout para no romper la salida.
     *
     * @param string $view Nombre de la vista (ruta relativa dentro de APP_VIEWS).
     * @param array  $data Datos que se exponen como variables a la vista.
     * @return void
     */
    protected function view(string $view, array $data = []): void
    {
        $data = $this->versionarAssets($data);
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

    /**
     * Render directo sin layout (útil para vistas embebidas o imprimibles).
     *
     * Requiere directamente el archivo de vista y responde 500 con un mensaje
     * si la vista no existe.
     *
     * @param string $view Nombre de la vista (APP_VIEWS/{$view}.php).
     * @param array  $data Datos que se exponen como variables a la vista.
     * @return void
     */
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

    /**
     * Respuesta JSON (AJAX).
     *
     * Emite la respuesta como application/json con el código de estado indicado
     * y termina la ejecución. Se usa en los endpoints AJAX del sistema.
     *
     * @param array $data   Arreglo serializable a JSON.
     * @param int   $status Código HTTP de la respuesta (200 por defecto).
     * @return void
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirección amigable a una ruta MVC.
     *
     * Construye la URL con APP_URL_BASE e index.php?url=..., añade los query
     * params opcionales y emite el header Location. Termina la ejecución.
     *
     * @param string $route Ruta MVC de destino (p. ej. "compras").
     * @param array  $query Pares clave→valor adicionales como query string.
     * @return void
     */
    protected function redirect(string $route = '', array $query = []): void
    {
        $url = APP_URL_BASE . 'index.php?url=' . ltrim($route, '/');
        if (!empty($query)) {
            $url .= '&' . http_build_query($query);
        }
        header('Location: ' . $url);
        exit;
    }

    /**
     * Mensaje flash en sesión.
     *
     * Guarda un mensaje de una sola vez (tipo: success/danger) que la siguiente
     * petición lee y limpia, usado para notificar el resultado de operaciones.
     * El origen opcional (p. ej. 'crear', 'editar', 'reset') permite que el JS
     * del módulo sepa qué formulario falló y reabra el modal correcto.
     *
     * @param string      $tipo   Tipo del mensaje ('success' o 'danger').
     * @param string      $texto  Contenido del mensaje a mostrar.
     * @param string|null $origen Acción que generó el mensaje (opcional).
     * @return void
     */
    protected function flash(string $tipo, string $texto, ?string $origen = null): void
    {
        $_SESSION['flash_msg'] = ['tipo' => $tipo, 'texto' => $texto, 'origen' => $origen];
    }

    /**
     * Lee y limpia el mensaje flash pendiente de la sesión.
     *
     * Obtiene el mensaje guardado por operaciones previas y lo elimina de la
     * sesión para que solo se muestre una vez. Devuelve null si no hay mensaje.
     *
     * @return array|null Arreglo ['tipo'=>.., 'texto'=>.., 'origen'=>..] o null si no hay.
     */
    protected function consumeFlash(): ?array
    {
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);
        return $flash;
    }

    /**
     * Límite de errores por acción: responde dentro del contexto del módulo.
     *
     * Equivalente del "Error Boundary" de React para este stack: una acción
     * que lanza Throwable no derriba la aplicación. Deshace la transacción
     * activa, registra el detalle en el log y responde según el tipo de
     * petición: JSON para AJAX (header X-Requested-With) y flash con
     * redirección validada para la navegación normal. El manejador global
     * de init.php queda como última red para errores fuera del Router.
     *
     * @param Throwable $e Error capturado durante la acción.
     * @return never
     */
    public function responderError(Throwable $e): never
    {
        $db = Database::getInstance();
        if ($db->inTransaction()) {
            $db->rollback();
        }
        error_log('[JV3000] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

        $es_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($es_ajax) {
            // Claves "ok" y "success" juntas: los módulos revisan una u otra.
            $this->json([
                'ok' => false,
                'success' => false,
                'error' => 'No se completó la operación. Intenta de nuevo.',
            ], 500);
        }

        $this->flash('danger', 'No se completó la operación. Intenta de nuevo; si vuelve a fallar, avisa al administrador.');

        // Volver al referer solo si es del mismo host, si no al panel.
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '') {
            $partes = parse_url($referer);
            if (($partes['host'] ?? '') !== ($_SERVER['HTTP_HOST'] ?? '')) {
                $referer = '';
            }
        }
        header('Location: ' . ($referer !== '' ? $referer : APP_URL_BASE . 'dashboard/index.php'));
        exit;
    }
}
