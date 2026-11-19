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
     * Traducciones visibles de las acciones técnicas.
     *
     * El valor enviado al SQL sigue siendo la clave; solo cambia el texto
     * que ve el administrador en el filtro.
     *
     * @var array<string, string>
     */
    private const ETIQUETAS_ACCION = [
        'LOGIN' => 'Inicio de sesión',
        'LOGOUT' => 'Cierre de sesión',
        'CREAR' => 'Creación',
        'EDITAR' => 'Edición',
        'ANULAR' => 'Anulación',
        'BACKUP' => 'Copia de seguridad',
        'DESACTIVADO' => 'Desactivado (Manual)',
        'DESACTIVADO_POR_STOCK' => 'Desactivado (Sin stock)',
        'REACTIVADO' => 'Reactivado',
        'DESCARTADO' => 'Descartado',
        'TASA BCV MANUAL' => 'Tasa BCV (Manual)',
        'TASA BCV MANUAL RETIRADA' => 'Tasa BCV (Retirada)',
    ];

    /**
     * Traducciones visibles de los módulos técnicos.
     *
     * El value enviado al SQL sigue siendo la clave; solo cambia el texto
     * que ve el administrador en el filtro y en la tabla.
     *
     * @var array<string, string>
     */
    private const ETIQUETAS_MODULO = [
        'BACKUP' => 'Respaldos',
        'COMPRAS' => 'Compras',
        'INVENTARIO' => 'Inventario',
        'SEGURIDAD' => 'Seguridad',
        'SISTEMA' => 'Sistema',
        'VENTAS' => 'Ventas',
    ];

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

        $etiquetas_acciones = [];
        foreach ($acciones as $accion) {
            $etiquetas_acciones[$accion] = $this->etiquetaAccion($accion);
        }

        $etiquetas_modulos = [];
        foreach ($modulos as $modulo) {
            $etiquetas_modulos[$modulo] = $this->etiquetaModulo($modulo);
        }

        $this->view('auditoria/index', [
            'titulo' => 'Registro de Auditoría | JV3000 C.A.',
            'wrapper_class' => 'pagina-auditoria',
            'css_extra' => ['modules/auditoria/auditoria.css'],
            'flash' => $this->consumeFlash(),
            'resumen' => $modelo->resumen(),
            'modulos_disponibles' => $modulos,
            'modulos_etiquetas' => $etiquetas_modulos,
            'acciones_disponibles' => $acciones,
            'acciones_etiquetas' => $etiquetas_acciones,
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
     * Devuelve la etiqueta amigable de una acción técnica.
     *
     * Las acciones conocidas usan el mapa; las desconocidas se limpian
     * cambiando guiones bajos por espacios y aplicando mayúscula inicial.
     *
     * @param string $accion Valor técnico guardado en logs_auditoria.
     * @return string Texto visible para el filtro.
     */
    private function etiquetaAccion(string $accion): string
    {
        $accion = trim($accion);
        if (isset(self::ETIQUETAS_ACCION[$accion])) {
            return self::ETIQUETAS_ACCION[$accion];
        }
        $texto = str_replace('_', ' ', mb_strtolower($accion, 'UTF-8'));
        if ($texto === '') {
            return '';
        }
        return mb_strtoupper(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($texto, 1, null, 'UTF-8');
    }

    /**
     * Devuelve la etiqueta amigable de un módulo técnico.
     *
     * Los módulos conocidos usan el mapa; los desconocidos se limpian
     * cambiando guiones bajos por espacios y aplicando mayúscula inicial.
     *
     * @param string $modulo Valor técnico guardado en logs_auditoria.
     * @return string Texto visible para el filtro y la tabla.
     */
    private function etiquetaModulo(string $modulo): string
    {
        $modulo = strtoupper(trim($modulo));
        if (isset(self::ETIQUETAS_MODULO[$modulo])) {
            return self::ETIQUETAS_MODULO[$modulo];
        }
        $texto = str_replace('_', ' ', mb_strtolower($modulo, 'UTF-8'));
        if ($texto === '') {
            return '';
        }
        return mb_strtoupper(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($texto, 1, null, 'UTF-8');
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
