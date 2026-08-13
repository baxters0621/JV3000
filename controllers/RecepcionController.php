<?php

// ==========================================
// CONTROLADOR: Recepción de Mercancía
// ==========================================
// index(): procesa el POST de recepción de
// mercancía (registrar) y renderiza el tablero
// con las compras pendientes y últimas recepciones.
// Toda la SQL está delegada en el Modelo.
class RecepcionController extends Controller
{
    // GET  index.php?url=recepcion
    // POST index.php?url=recepcion  (accion_recepcion)
    public function index(): void
    {
        Security::verificarPermisoCarga();

        $modelo = new Recepcion();

        // --- Acción POST: registrar recepción de mercancía ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_recepcion'])) {
            $resultado = $modelo->registrar($_POST);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('recepcion');
        }

        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        $datos = $modelo->dashboard();

        $this->view('recepcion/index', [
            'titulo'             => 'Recepción de Mercancía | JV3000 C.A.',
            'wrapper_class'      => 'pagina-recepcion',
            'css_extra'          => ['modules/recepcion/recepcion.css?v=4'],
            'js_extra'           => ['modules/recepcion/recepcion.js?v=5'],
            'csrf'               => Security::generateToken(),
            'flash'              => $flash,
            'compras_pendientes' => $datos['compras_pendientes'],
            'total_por_recibir'  => $datos['total_por_recibir'],
            'unidades_por_recibir' => $datos['unidades_por_recibir'],
            'recepciones'        => $datos['recepciones'],
            'recepciones_hoy'    => $datos['recepciones_hoy'],
            'js_config'          => ['recepcionDatos' => $datos['datos_recepcion']],
        ]);
    }
}
