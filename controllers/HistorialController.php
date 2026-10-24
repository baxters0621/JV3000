<?php

// ==========================================
// CONTROLADOR: Historial de Operaciones
// ==========================================
// Solo lectura. Recibe filtros GET + paginación,
// delega en el Modelo y entrega los datos a la Vista.

/**
 * HistorialController: consulta las operaciones registradas de compras y ventas.
 *
 * Módulo de solo lectura. Los permisos de consulta se aplican en el servidor:
 * administración ve compras y ventas, carga ve compras y ventas ve ventas.
 */
class HistorialController extends Controller
{
    /**
     * Lista paginada y filtrada de compras y ventas.
     *
     * Valida el rol y los filtros de GET, consulta el modelo y prepara los
     * datos de paginación para la vista.
     *
     * @return void
     */
    public function index(): void
    {
        $incluir_compras = Security::puedeCargar();
        $incluir_ventas = Security::puedeVender();
        if (!$incluir_compras && !$incluir_ventas) {
            header("Location: " . BASE_PATH . "dashboard/index.php?error=acceso_denegado");
            exit;
        }

        $tipos_disponibles = [];
        if ($incluir_compras) {
            $tipos_disponibles['compra'] = 'Compras';
        }
        if ($incluir_ventas) {
            $tipos_disponibles['venta'] = 'Ventas';
        }

        $desde_recibido = $this->parametroTexto('desde');
        $hasta_recibido = $this->parametroTexto('hasta');
        $desde = $this->fechaValida($desde_recibido);
        $hasta = $this->fechaValida($hasta_recibido);
        $error_filtros = '';
        if ($desde_recibido !== '' && $desde === '') {
            $error_filtros = 'La fecha inicial no es válida.';
        }
        if ($hasta_recibido !== '' && $hasta === '') {
            $error_filtros = 'La fecha final no es válida.';
        }
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            $error_filtros = 'La fecha inicial no puede ser posterior a la fecha final.';
        }

        $tipo_recibido = $this->parametroTexto('tipo');
        $estado_recibido = $this->parametroTexto('estado');
        $filtros = [
            'tipo' => isset($tipos_disponibles[$tipo_recibido])
                ? $tipo_recibido
                : '',
            'estado' => in_array($estado_recibido, ['Activa', 'Anulada'], true)
                ? $estado_recibido
                : '',
            'desde' => $desde,
            'hasta' => $hasta,
            'buscar' => mb_substr($this->parametroTexto('buscar'), 0, 100, 'UTF-8'),
        ];
        $page  = max(1, (int)$this->parametroTexto('page'));
        $limit = 50;

        $modelo = new Auditoria();
        $total_registros = $error_filtros === ''
            ? $modelo->totalOperaciones($filtros, $incluir_compras, $incluir_ventas)
            : 0;
        $total_paginas = max(1, (int)ceil($total_registros / $limit));
        $page = min($page, $total_paginas);
        $registros = $error_filtros === ''
            ? $modelo->listarOperaciones($filtros, $page, $limit, $incluir_compras, $incluir_ventas)
            : [];
        $flash  = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        $this->view('historial/index', [
            'titulo'       => 'Historial de operaciones | JV3000 C.A.',
            'wrapper_class'=> 'pagina-historial',
            'css_extra'    => ['modules/historial/historial.css'],
            'js_extra'     => ['modules/historial/historial.js'],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'filtro_tipo' => $filtros['tipo'],
            'filtro_estado' => $filtros['estado'],
            'filtro_desde' => $filtros['desde'],
            'filtro_hasta' => $filtros['hasta'],
            'filtro_buscar' => $filtros['buscar'],
            'error_filtros' => $error_filtros,
            'tipos_disponibles' => $tipos_disponibles,
            'query_string' => http_build_query(array_filter($filtros, fn($v) => $v !== '')),
            'total_registros' => $total_registros,
            'total_paginas' => $total_paginas,
            'page'           => $page,
            'registros' => $registros,
        ]);
    }

    /**
     * Valida una fecha en formato ISO sin aceptar valores normalizados.
     *
     * @param string $fecha Valor recibido desde el filtro.
     * @return string Fecha válida o cadena vacía.
     */
    private function fechaValida(string $fecha): string
    {
        if ($fecha === '') {
            return '';
        }
        $fecha_formateada = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $fecha_formateada && $fecha_formateada->format('Y-m-d') === $fecha ? $fecha : '';
    }

    /**
     * Lee un parámetro de texto sin convertir arreglos enviados en la URL.
     *
     * @param string $nombre Nombre del parámetro GET.
     * @return string Valor recibido o cadena vacía.
     */
    private function parametroTexto(string $nombre): string
    {
        $valor = $_GET[$nombre] ?? '';
        return is_string($valor) ? trim($valor) : '';
    }
}
