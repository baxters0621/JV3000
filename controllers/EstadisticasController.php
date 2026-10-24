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

        $datos   = $modelo->obtenerDatos($periodo, $desde, $hasta);
        $periodos = $modelo->periodos();

        // Validar que $datos tenga las keys esperadas; si no, usar valores por defecto
        $expectedKeys = ['periodo', 'etiqueta', 'mensaje', 'desde', 'hasta', 'ventas', 'compras', 'ganancia', 'ventas_ant', 'compras_ant', 'pct_ventas', 'pct_compras', 'pct_ganancia', 'labels_ventas', 'data_ventas', 'labels_compras', 'data_compras', 'top_labels', 'top_cant'];
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
            'datos'         => $datos,
            'error_fechas'  => $filtros_periodo['error'],
            'js_config'     => [
                'periodo'   => $datos['periodo'],
                'labels'    => $datos['labels_ventas'],
                'ventas'    => $datos['data_ventas'],
                'compras'   => $datos['data_compras'],
                'topLabels' => $datos['top_labels'],
                'topCant'   => $datos['top_cant'],
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
            $filtros_periodo['hasta']
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
            'ventas_ant'   => (float)$statisticsData['ventas_ant'],
            'compras_ant'  => (float)$statisticsData['compras_ant'],
            'pct_ventas'   => $statisticsData['pct_ventas'],
            'pct_compras'  => $statisticsData['pct_compras'],
            'pct_ganancia' => $statisticsData['pct_ganancia'],
            'labels'       => $statisticsData['labels_ventas'],
            'data_ventas'  => $statisticsData['data_ventas'],
            'data_compras' => $statisticsData['data_compras'],
            'topLabels'    => $statisticsData['top_labels'],
            'topCant'      => $statisticsData['top_cant'],
        ]);
    }

    /**
     * Valida los filtros de período compartidos por la página y el refresco AJAX.
     *
     * @return array{periodo: string, desde: string, hasta: string, error: string}
     */
    private function filtrosPeriodo(): array
    {
        $periodo_recibido = $this->parametroGet('periodo');
        $periodo = preg_match('/^(dia|semana|quincena|mes|trimestre|semestre|rango)$/', $periodo_recibido)
            ? $periodo_recibido
            : 'trimestre';
        $desde = $this->parametroGet('desde');
        $hasta = $this->parametroGet('hasta');
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
                return ['periodo' => 'trimestre', 'desde' => '', 'hasta' => '', 'error' => $error];
            }
        } else {
            $desde = '';
            $hasta = '';
        }

        return ['periodo' => $periodo, 'desde' => $desde, 'hasta' => $hasta, 'error' => ''];
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
