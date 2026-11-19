<?php

// ==========================================
// CONTROLADOR: Auditoría del sistema
// ==========================================
// Módulo de solo lectura para el Administrador.
// Consulta la bitácora, aplica filtros y pagina de 50 en 50.

/**
 * AuditoriaController: muestra la trazabilidad de acciones del sistema.
 *
 * El acceso es exclusivo del rol Administrador. No existen acciones de
 * edición ni de borrado: la bitácora es inmutable desde la interfaz.
 */
class AuditoriaController extends Controller
{
    /**
     * Lista paginada y filtrada de eventos de auditoría.
     *
     * @return void
     */
    public function index(): void
    {
        if (!Security::esAdmin()) {
            http_response_code(403);
            $titulo = '403 — Acceso denegado';
            require APP_VIEWS . '/errors/403.php';
            exit;
        }

        $modelo = new Auditoria();
        $modulos = $modelo->modulosDisponibles();
        $acciones = $modelo->accionesDisponibles();

        $buscar = mb_substr($this->parametroTexto('buscar'), 0, 100, 'UTF-8');
        $modulo_recibido = $this->parametroTexto('modulo');
        $accion_recibida = $this->parametroTexto('accion');
        $fecha_desde = $this->parametroFecha('fecha_desde');
        $fecha_hasta = $this->parametroFecha('fecha_hasta');

        $filtros = [
            'buscar' => $buscar,
            'modulo' => in_array($modulo_recibido, $modulos, true) ? $modulo_recibido : '',
            'accion' => in_array($accion_recibida, $acciones, true) ? $accion_recibida : '',
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta,
        ];

        $page = max(1, (int)$this->parametroTexto('page'));
        $limit = 50;
        $total_registros = $modelo->totalEventosFiltrados($filtros);
        $total_paginas = max(1, (int)ceil($total_registros / $limit));
        $page = min($page, $total_paginas);
        $registros = $modelo->listarEventos($filtros, $page, $limit);

        $query_string = http_build_query(array_filter(
            $filtros,
            static fn(string $valor): bool => $valor !== ''
        ));

        $this->view('auditoria/index', [
            'titulo' => 'Registro de Auditoría | JV3000 C.A.',
            'wrapper_class' => 'pagina-auditoria',
            'css_extra' => ['modules/auditoria/auditoria.css'],
            'flash' => $this->consumeFlash(),
            'resumen' => $modelo->resumen(),
            'modulos_disponibles' => $modulos,
            'acciones_disponibles' => $acciones,
            'filtro_buscar' => $filtros['buscar'],
            'filtro_modulo' => $filtros['modulo'],
            'filtro_accion' => $filtros['accion'],
            'filtro_fecha_desde' => $filtros['fecha_desde'],
            'filtro_fecha_hasta' => $filtros['fecha_hasta'],
            'total_registros' => $total_registros,
            'total_paginas' => $total_paginas,
            'page' => $page,
            'registros' => $registros,
            'query_string' => $query_string,
        ]);
    }

    /**
     * Lee un parámetro GET de texto sin aceptar arreglos.
     *
     * @param string $nombre Nombre del parámetro.
     * @return string Valor recortado o cadena vacía.
     */
    private function parametroTexto(string $nombre): string
    {
        $valor = $_GET[$nombre] ?? '';
        return is_string($valor) ? trim($valor) : '';
    }

    /**
     * Lee un parámetro GET de fecha en formato YYYY-MM-DD.
     *
     * Rechaza valores vacíos, arreglos y fechas imposibles como 2026-02-31.
     * La fecha se usa después como parámetro preparado en el modelo.
     *
     * @param string $nombre Nombre del parámetro.
     * @return string Fecha válida o cadena vacía.
     */
    private function parametroFecha(string $nombre): string
    {
        $valor = $this->parametroTexto($nombre);
        if ($valor === '') {
            return '';
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return ($fecha !== false && $fecha->format('Y-m-d') === $valor) ? $valor : '';
    }
}
