<?php

// ==========================================
// CONTROLADOR: Diagnóstico (observabilidad)
// ==========================================
// Panel del diario de errores: el administrador descubre los fallos del
// sistema ANTES de que la persona usuaria los reporte. Recibe además los
// errores reportados desde el navegador (boundary de diseno.js).

/**
 * DiagnosticoController: consulta y gestión del diario de errores.
 *
 * index es exclusivo del Administrador. El endpoint cliente() está abierto
 * a cualquier sesión válida (los errores del navegador de cada operador
 * también son observabilidad) y exige CSRF por la validación global de POST.
 */
class DiagnosticoController extends Controller
{
    /** Filas por página en el listado del panel. */
    private const POR_PAGINA = 50;

    /**
     * Panel del diario de errores (solo Administrador).
     *
     * Lee filtros de GET (origen, estado, búsqueda), consulta el modelo y
     * entrega los datos paginados a la vista.
     *
     * @return void
     */
    public function index(): void
    {
        if (!Security::esAdmin()) {
            header("Location: " . BASE_PATH . "dashboard/index.php?error=acceso_prohibido");
            exit;
        }

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
     * Devuelve el detalle completo de un error (JSON, solo Administrador).
     *
     * Alimenta la ventana de detalle del panel sin recargar la página.
     *
     * @return void
     */
    public function detalle(): void
    {
        if (!Security::esAdmin()) {
            $this->json(['ok' => false, 'error' => 'Solo el administrador consulta el diagnóstico.'], 403);
        }
        $fila = (new ErrorLog())->detalle((int)($_GET['id'] ?? 0));
        if (!$fila) {
            $this->json(['ok' => false, 'error' => 'El error ya no existe (posiblemente fue purgado).'], 404);
        }
        $this->json(['ok' => true, 'error' => $fila]);
    }

    /**
     * Marca un error como resuelto (solo Administrador, POST con CSRF).
     *
     * Responde JSON para AJAX y flash + redirección para la navegación normal.
     *
     * @return void
     */
    public function resolver(): void
    {
        if (!Security::esAdmin()) {
            header("Location: " . BASE_PATH . "dashboard/index.php?error=acceso_prohibido");
            exit;
        }
        $id_error = (int)($_POST['id_error'] ?? 0);
        $resuelto = $id_error > 0 && (new ErrorLog())->marcarResuelto($id_error);

        $es_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($es_ajax) {
            $this->json(['ok' => $resuelto]);
        }
        $this->flash(
            $resuelto ? 'success' : 'danger',
            $resuelto ? 'Error marcado como resuelto.' : 'No se pudo marcar el error (¿ya fue purgado?).'
        );
        $this->redirect('diagnostico');
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
