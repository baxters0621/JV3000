<?php

// ==========================================
// CONTROLADOR: Estadísticas
// ==========================================
// index(): renderiza la vista con KPIs y gráficos
//          del periodo elegido (GET periodo/desde/hasta).
// datos():  endpoint AJAX de auto-refresh (reemplaza
//          includes/ajax/estadisticas_ajax.php).
// Toda la SQL está delegada en el Modelo.

/**
 * EstadisticasController: muestra KPIs y gráficos del periodo elegido.
 *
 * Renderiza la vista con las estadísticas (ventas, compras y ganancia) del
 * periodo seleccionado y expone un endpoint AJAX de auto-refresh que
 * reemplazó al antiguo includes/ajax/estadisticas_ajax.php.
 */
class EstadisticasController extends Controller
{
    /**
     * Página principal de estadísticas.
     *
     * Valida el periodo (GET periodo/desde/hasta), consulta los datos al
     * modelo Estadistica y los entrega a la vista junto con la configuración
     * necesaria para los gráficos (Chart.js).
     *
     * @return void
     */
    public function index(): void
    {
        if (!Security::puedeVender()) {
            header("Location: " . BASE_PATH . "dashboard/index.php?error=acceso_denegado");
            exit;
        }

        $modelo = new Estadistica();

        $filtros_periodo = $this->filtrosPeriodo();
        $periodo = $filtros_periodo['periodo'];
        $desde = $filtros_periodo['desde'];
        $hasta = $filtros_periodo['hasta'];

        $datos   = $modelo->obtenerDatos($periodo, $desde, $hasta, $filtros_periodo['categoria'], $filtros_periodo['proveedor']);
        $periodos = $modelo->periodos();
        $categorias = (new Categoria())->listar();
        $proveedores = (new Proveedor())->listar();

        // Validar que $datos tenga las keys esperadas; si no, usar valores por defecto
        $expectedKeys = ['periodo', 'etiqueta', 'mensaje', 'desde', 'hasta', 'ventas', 'compras', 'ganancia', 'unidades', 'operaciones', 'ticket', 'margen_pct', 'ventas_ant', 'compras_ant', 'ganancia_ant', 'unidades_ant', 'pct_ventas', 'pct_compras', 'pct_ganancia', 'pct_unidades', 'labels_ventas', 'data_ventas', 'labels_compras', 'data_compras', 'data_margen', 'cat_labels', 'cat_data', 'top_rows', 'top_labels', 'top_cant', 'rent_rows', 'rent_total', 'rent_pagina', 'rent_paginas'];
        $hasAllKeys = count(array_intersect($expectedKeys, array_keys($datos))) === count($expectedKeys);
        if (!$hasAllKeys) {
            error_log('Los datos calculados para Estadísticas no contienen todas las columnas esperadas.');
            throw new UnexpectedValueException('No fue posible calcular todos los datos de estadísticas.');
        }

        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        $this->view('estadisticas/index', [
            'titulo'        => 'Estadísticas | JV3000 C.A.',
            'wrapper_class' => 'pagina-estadisticas',
            'css_extra'     => ['modules/estadisticas/estadisticas.css'],
            'js_extra'      => ['js/chart.umd.min.js', 'modules/estadisticas/estadisticas.js'],
            'csrf'          => Security::generateToken(),
            'flash'         => $flash,
            'periodos'      => $periodos,
            'categorias'    => $categorias,
            'proveedores'   => $proveedores,
            'datos'         => $datos,
            'error_fechas'  => $filtros_periodo['error'],
            'js_config'     => [
                'periodo'     => $datos['periodo'],
                'labels'      => $datos['labels_ventas'],
                'ventas'      => $datos['data_ventas'],
                'compras'     => $datos['data_compras'],
                'margenes'    => $datos['data_margen'],
                'catLabels'   => $datos['cat_labels'],
                'catData'     => $datos['cat_data'],
                'topRows'     => $datos['top_rows'],
                'topLabels'   => $datos['top_labels'],
                'topCant'     => $datos['top_cant'],
                'rentRows'    => $datos['rent_rows'],
                'rentTotal'   => $datos['rent_total'],
                'rentPagina'  => $datos['rent_pagina'],
                'rentPaginas' => $datos['rent_paginas'],
            ],
        ]);
    }

    /**
     * Endpoint AJAX de auto-refresh de las estadísticas (cada 60s).
     *
     * Valida que la petición sea AJAX (X-Requested-With) y que el rol pueda
     * vender, recalcula los datos del periodo y los devuelve en JSON para
     * que la vista actualice los KPIs y gráficos sin recargar la página.
     *
     * @return void
     */
    public function datos(): void
    {
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            $this->json(['success' => false, 'error' => 'acceso_denegado']);
        }
        if (!Security::puedeVender()) {
            $this->json(['success' => false, 'error' => 'acceso_denegado']);
        }

        $filtros_periodo = $this->filtrosPeriodo();
        if ($filtros_periodo['error'] !== '') {
            $this->json([
                'success' => false,
                'error' => 'rango_invalido',
                'mensaje' => $filtros_periodo['error'],
            ], 422);
        }

        $statisticsData = (new Estadistica())->obtenerDatos(
            $filtros_periodo['periodo'],
            $filtros_periodo['desde'],
            $filtros_periodo['hasta'],
            $filtros_periodo['categoria'],
            $filtros_periodo['proveedor']
        );

        $this->json([
            'success'      => true,
            'periodo'      => $statisticsData['periodo'],
            'etiqueta'     => $statisticsData['etiqueta'],
            'mensaje'      => $statisticsData['mensaje'],
            'desde'        => $statisticsData['desde'],
            'hasta'        => $statisticsData['hasta'],
            'ventas'       => (float)$statisticsData['ventas'],
            'compras'      => (float)$statisticsData['compras'],
            'ganancia'     => (float)$statisticsData['ganancia'],
            'unidades'     => (float)$statisticsData['unidades'],
            'operaciones'  => (int)$statisticsData['operaciones'],
            'ticket'       => (float)$statisticsData['ticket'],
            'margen_pct'   => $statisticsData['margen_pct'],
            'ventas_ant'   => (float)$statisticsData['ventas_ant'],
            'compras_ant'  => (float)$statisticsData['compras_ant'],
            'pct_ventas'   => $statisticsData['pct_ventas'],
            'pct_compras'  => $statisticsData['pct_compras'],
            'pct_ganancia' => $statisticsData['pct_ganancia'],
            'pct_unidades' => $statisticsData['pct_unidades'],
            'labels'       => $statisticsData['labels_ventas'],
            'data_ventas'  => $statisticsData['data_ventas'],
            'data_compras' => $statisticsData['data_compras'],
            'data_margen'  => $statisticsData['data_margen'],
            'catLabels'    => $statisticsData['cat_labels'],
            'catData'      => $statisticsData['cat_data'],
            'topRows'      => $statisticsData['top_rows'],
            'topLabels'    => $statisticsData['top_labels'],
            'topCant'      => $statisticsData['top_cant'],
            'rentRows'     => $statisticsData['rent_rows'],
            'rentTotal'    => (int)$statisticsData['rent_total'],
            'rentPagina'   => (int)$statisticsData['rent_pagina'],
            'rentPaginas'  => (int)$statisticsData['rent_paginas'],
        ]);
    }

    /**
     * Endpoint AJAX de la tabla de rentabilidad paginada.
     *
     * Recalcula la ventana con los mismos filtros globales y devuelve una
     * página de productos ordenados por margen real (costo del lote vendido).
     *
     * @return void
     */
    public function rentabilidad(): void
    {
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            $this->json(['success' => false, 'error' => 'acceso_denegado']);
        }
        if (!Security::puedeVender()) {
            $this->json(['success' => false, 'error' => 'acceso_denegado']);
        }

        $filtros = $this->filtrosPeriodo();
        if ($filtros['error'] !== '') {
            $this->json([
                'success' => false,
                'error'   => 'rango_invalido',
                'mensaje' => $filtros['error'],
            ], 422);
        }

        $pagina = (int)$this->parametroGet('pagina');
        $modelo = new Estadistica();
        $ventana = $modelo->resolverVentana($filtros['periodo'], $filtros['desde'], $filtros['hasta']);
        $rentabilidad = $modelo->obtenerRentabilidad($ventana, $filtros['categoria'], $filtros['proveedor'], $pagina);

        $this->json([
            'success'     => true,
            'filas'       => $rentabilidad['filas'],
            'total'       => (int)$rentabilidad['total'],
            'pagina'      => (int)$rentabilidad['pagina'],
            'paginas'     => (int)$rentabilidad['paginas'],
            'por_pagina'  => (int)$rentabilidad['por_pagina'],
            'etiqueta'    => $ventana['etiqueta'],
        ]);
    }

    /**
     * Valida los filtros compartidos por la página y el refresco AJAX.
     *
     * @return array{periodo: string, desde: string, hasta: string, categoria: int, proveedor: int, error: string}
     */
    private function filtrosPeriodo(): array
    {
        $periodo_recibido = $this->parametroGet('periodo');
        $periodo = preg_match('/^(dia|semana|quincena|mes|trimestre|semestre|rango)$/', $periodo_recibido)
            ? $periodo_recibido
            : 'trimestre';
        $desde = $this->parametroGet('desde');
        $hasta = $this->parametroGet('hasta');
        $categoria = max(0, (int)$this->parametroGet('categoria'));
        $proveedor = max(0, (int)$this->parametroGet('proveedor'));
        $error = '';

        if ($periodo === 'rango') {
            $fecha_desde = $this->fechaValida($desde);
            $fecha_hasta = $this->fechaValida($hasta);
            $hoy = new DateTimeImmutable('today');
            if ($fecha_desde === null || $fecha_hasta === null) {
                $error = 'Selecciona una fecha inicial y final válidas.';
            } elseif ($fecha_desde > $fecha_hasta) {
                $error = 'La fecha final no puede ser anterior a la fecha inicial.';
            } elseif ($fecha_hasta > $hoy) {
                $error = 'Las estadísticas no pueden consultar fechas futuras.';
            }
            if ($error !== '') {
                return ['periodo' => 'trimestre', 'desde' => '', 'hasta' => '', 'categoria' => $categoria, 'proveedor' => $proveedor, 'error' => $error];
            }
        } else {
            $desde = '';
            $hasta = '';
        }

        return ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta, 'categoria' => $categoria, 'proveedor' => $proveedor, 'error' => ''];
    }

    /**
     * Lee un parámetro GET de texto y rechaza arreglos u otros tipos.
     *
     * @param string $nombre Nombre del parámetro.
     * @return string Valor textual saneado.
     */
    private function parametroGet(string $nombre): string
    {
        $valor = $_GET[$nombre] ?? '';
        return is_string($valor) ? trim($valor) : '';
    }

    /**
     * Valida una fecha ISO sin normalizar días inexistentes.
     *
     * @param string $fecha Fecha en formato YYYY-MM-DD.
     * @return DateTimeImmutable|null Fecha válida o null.
     */
    private function fechaValida(string $fecha): ?DateTimeImmutable
    {
        if ($fecha === '') {
            return null;
        }
        $fecha_objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        $errores = DateTimeImmutable::getLastErrors();
        if (
            !$fecha_objeto
            || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))
            || $fecha_objeto->format('Y-m-d') !== $fecha
        ) {
            return null;
        }
        return $fecha_objeto;
    }
}
