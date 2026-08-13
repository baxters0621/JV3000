<?php

// ==========================================
// CONTROLADOR: Solicitudes de Reposición
// ==========================================
// Recibe la petición, delega en el Modelo y
// entrega los datos a la Vista. Sin SQL aquí.

/**
 * SolicitudesController: gestiona las solicitudes de reposición.
 *
 * Recibe la petición, delega en el modelo Solicitud y entrega los datos a
 * la vista. Incluye la creación de solicitudes desde Ventas (AJAX) y la
 * cancelación de solicitudes pendientes.
 */
class SolicitudesController extends Controller
{
    /**
     * Tablero de solicitudes de reposición.
     *
     * GET: renderiza la vista con las solicitudes pendientes, el historial
     * de atendidas/canceladas y los KPIs calculados por el modelo.
     *
     * @return void
     */
    public function index(): void
    {
        $modelo = new Solicitud();

        $this->view('solicitudes/index', [
            'titulo'       => 'Solicitudes de Reposición | JV3000 C.A.',
            'wrapper_class'=> 'pagina-solicitudes',
            'css_extra'    => ['modules/solicitudes_compra/solicitudes_compra.css?v=1'],
            'js_extra'     => ['modules/solicitudes_compra/solicitudes_compra.js?v=3'],
            'solicitudes' => $modelo->obtenerPendientes(),
            'historial'   => $modelo->obtenerHistorial(),
            'kpis'        => $modelo->kpis(),
            'flash'       => $this->consumeFlash(),
            'csrf'        => Security::generateToken(),
        ]);
    }

    /**
     * Crea una solicitud de reposición desde Ventas (AJAX).
     *
     * Valida permisos (ventas o admin) y método POST, decodifica los ítems
     * JSON del formulario y delega en el modelo Solicitud. Responde el
     * resultado como JSON con el código HTTP correspondiente.
     *
     * @return void
     */
    public function crear(): void
    {
        if (!(Security::puedeVender() || Security::esAdmin())) {
            $this->json(['ok' => false, 'error' => 'SIN PERMISOS PARA CREAR SOLICITUDES.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'error' => 'MÉTODO NO PERMITIDO.'], 405);
        }

        $itemsRaw = json_decode($_POST['items'] ?? '[]', true);
        $motivo = trim($_POST['motivo'] ?? '');
        $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

        $resultado = (new Solicitud())->crear(is_array($itemsRaw) ? $itemsRaw : [], $motivo, $idUsuario);
        $this->json($resultado, $resultado['ok'] ? 200 : 400);
    }

    /**
     * Cancela una solicitud de reposición pendiente.
     *
     * Requiere método POST. Delega la cancelación en el modelo Solicitud,
     * guarda un flash con el resultado y redirige al tablero.
     *
     * @return void
     */
    public function cancelar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('solicitudes');
        }

        $id = (int)($_POST['id_solicitud'] ?? 0);
        $resultado = (new Solicitud())->cancelar($id);

        if ($resultado['ok']) {
            $this->flash('success', 'SOLICITUD CANCELADA.');
        } else {
            $this->flash('danger', $resultado['error'] ?? 'ERROR AL CANCELAR LA SOLICITUD.');
        }
        $this->redirect('solicitudes');
    }

    /**
     * Lee y limpia el mensaje flash pendiente de la sesión.
     *
     * Obtiene el mensaje guardado por operaciones previas y lo elimina de la
     * sesión para que solo se muestre una vez. Devuelve null si no hay mensaje.
     *
     * @return array|null Arreglo ['tipo'=>.., 'texto'=>..] o null si no hay.
     */
    private function consumeFlash(): ?array
    {
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);
        return $flash;
    }
}
