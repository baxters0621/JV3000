<?php

// ==========================================
// CONTROLADOR: Diagnóstico (observabilidad)
// ==========================================
// La captura interna permanece activa, pero el panel no se expone a usuarios.

/**
 * DiagnosticoController: diario interno de errores y endpoint de captura.
 *
 * La interfaz y sus acciones están deshabilitadas para todos los usuarios.
 * cliente() permanece disponible para guardar reportes del navegador.
 */
class DiagnosticoController extends Controller
{
    /** Filas por página en el listado del panel. */
    private const POR_PAGINA = 50;

    /**
     * Panel del diario de errores (interfaz deshabilitada).
     *
     * Lee filtros de GET (origen, estado, búsqueda), consulta el modelo y
     * entrega los datos paginados a la vista.
     *
     * @return void
     */
    public function index(): void
    {
        $this->denegarAccesoDiagnostico();

        $origen_recibido = $this->parametroTexto('origen');
        $estado_recibido = $this->parametroTexto('estado');
        $filtros = [
            'origen' => in_array($origen_recibido, ['servidor', 'navegador'], true) ? $origen_recibido : '',
            'estado' => in_array($estado_recibido, ['activos', 'resueltos'], true) ? $estado_recibido : 'activos',
            'buscar' => mb_substr($this->parametroTexto('buscar'), 0, 100, 'UTF-8'),
        ];
        $pagina = max(1, (int)$this->parametroTexto('page'));

        $modelo = new ErrorLog();
        $total = $modelo->totalErrores($filtros);
        $total_paginas = max(1, (int)ceil($total / self::POR_PAGINA));
        $pagina = min($pagina, $total_paginas);

        $this->view('diagnostico/index', [
            'titulo_pagina'  => 'Diagnóstico',
            'css_extra'      => ['modules/diagnostico/diagnostico.css'],
            'js_extra'       => ['modules/diagnostico/diagnostico.js'],
            'flash'          => $this->consumeFlash(),
            'errores'        => $modelo->listar($filtros, $pagina, self::POR_PAGINA),
            'total'          => $total,
            'activos'        => $modelo->contarActivos(),
            'filtros'        => $filtros,
            'query_string'   => http_build_query(array_filter($filtros, fn($v) => $v !== '')),
            'pagina'         => $pagina,
            'total_paginas'  => $total_paginas,
        ]);
    }

    /**
     * Recibe un error reportado desde el navegador del usuario.
     *
     * Punto de llegada del boundary de diseno.js (fetch con X-Requested-With
     * y CSRF, validados por el arranque). Responde JSON mínimo: el navegador
     * no está esperando nada útil de aquí.
     *
     * @return void
     */
    public function cliente(): void
    {
        $mensaje = trim((string)($_POST['mensaje'] ?? ''));
        if ($mensaje === '') {
            $this->json(['ok' => false, 'error' => 'Sin detalle no hay nada que registrar.'], 400);
        }
        (new ErrorLog())->registrarCliente([
            'mensaje' => $mensaje,
            'archivo' => (string)($_POST['archivo'] ?? ''),
            'linea'   => (int)($_POST['linea'] ?? 0),
            'stack'   => (string)($_POST['stack'] ?? ''),
        ]);
        $this->json(['ok' => true]);
    }

    /**
     * Devuelve el detalle completo de un error (JSON, deshabilitado).
     *
     * Alimenta la ventana de detalle del panel sin recargar la página.
     *
     * @return void
     */
    public function detalle(): void
    {
        $this->denegarAccesoDiagnostico(true);
    }

    /**
     * Resuelve un error (acción deshabilitada para todos los usuarios).
     *
     * Responde JSON para AJAX y flash + redirección para la navegación normal.
     *
     * @return void
     */
    public function resolver(): void
    {
        $this->denegarAccesoDiagnostico(
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );
    }

    /**
     * Rechaza cualquier acceso a la interfaz y acciones del panel.
     *
     * @param bool $json Indica si la respuesta debe ser JSON.
     * @return void
     */
    private function denegarAccesoDiagnostico(bool $json = false): void
    {
        if ($json) {
            $this->json(['ok' => false, 'error' => 'Acceso denegado.'], 403);
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Acceso denegado.';
        exit;
    }

    /**
     * Lee un parámetro GET como texto recortado.
     *
     * @param string $clave Nombre del parámetro.
     * @return string Valor recortado o cadena vacía.
     */
    private function parametroTexto(string $clave): string
    {
        return trim((string)($_GET[$clave] ?? ''));
    }
}
