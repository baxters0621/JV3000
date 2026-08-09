<?php

// ==========================================
// CONTROLADOR: Estadísticas
// ==========================================
// index(): renderiza la vista con KPIs y gráficos
//          del periodo elegido (GET periodo/desde/hasta).
// datos():  endpoint AJAX de auto-refresh (reemplaza
//          includes/ajax/estadisticas_ajax.php).
// Toda la SQL está delegada en el Modelo.
class EstadisticasController extends Controller
{
    // GET  index.php?url=estadisticas?periodo=..&desde=..&hasta=..
    public function index(): void
    {
        if (!Security::puedeVender()) {
            header("Location: " . BASE_PATH . "dashboard/index.php?error=acceso_denegado");
            exit;
        }

        $modelo = new Estadistica();

        $periodo = preg_match('/^(dia|semana|quincena|mes|trimestre|semestre|rango)$/', $_GET['periodo'] ?? '') ? $_GET['periodo'] : 'semana';
        $desde   = trim((string)($_GET['desde'] ?? ''));
        $hasta   = trim((string)($_GET['hasta'] ?? ''));
        if ($periodo === 'rango' && ($desde === '' || $hasta === '')) {
            $periodo = 'semana';
        }

        $datos   = $modelo->obtenerDatos($periodo, $desde, $hasta);
        $periodos = $modelo->periodos();

        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        $this->view('estadisticas/index', [
            'titulo'        => 'Estadísticas | JV3000 C.A.',
            'wrapper_class' => 'pagina-estadisticas',
            'css_extra'     => ['modules/estadisticas/estadisticas.css?v=4'],
            'js_extra'      => ['js/chart.umd.min.js', 'modules/estadisticas/estadisticas.js?v=4'],
            'csrf'          => Security::generateToken(),
            'flash'         => $flash,
            'periodos'      => $periodos,
            'datos'         => $datos,
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

    // GET  index.php?url=estadisticas/datos  (AJAX — auto-refresh cada 60s)
    public function datos(): void
    {
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            $this->json(['success' => false, 'error' => 'acceso_denegado']);
        }
        if (!Security::puedeVender()) {
            $this->json(['success' => false, 'error' => 'acceso_denegado']);
        }

        $periodo = preg_match('/^(dia|semana|quincena|mes|trimestre|semestre|rango)$/', $_GET['periodo'] ?? '') ? $_GET['periodo'] : 'semana';
        $desde = trim((string)($_GET['desde'] ?? ''));
        $hasta = trim((string)($_GET['hasta'] ?? ''));
        if ($periodo === 'rango' && ($desde === '' || $hasta === '')) {
            $periodo = 'semana';
        }

        $d = (new Estadistica())->obtenerDatos($periodo, $desde, $hasta);

        $this->json([
            'success'      => true,
            'periodo'      => $d['periodo'],
            'etiqueta'     => $d['etiqueta'],
            'mensaje'      => $d['mensaje'],
            'ventas'       => number_format($d['ventas'], 2),
            'compras'      => number_format($d['compras'], 2),
            'ganancia'     => number_format($d['ganancia'], 2),
            'pct_ventas'   => $d['pct_ventas'],
            'pct_compras'  => $d['pct_compras'],
            'pct_ganancia' => $d['pct_ganancia'],
            'labels'       => $d['labels_ventas'],
            'data_ventas'  => $d['data_ventas'],
            'data_compras' => $d['data_compras'],
            'topLabels'    => $d['top_labels'],
            'topCant'      => $d['top_cant'],
        ]);
    }
}
