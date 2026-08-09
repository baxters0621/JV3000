<?php

// ==========================================
// CONTROLADOR: Solicitudes de Reposición
// ==========================================
// Recibe la petición, delega en el Modelo y
// entrega los datos a la Vista. Sin SQL aquí.
class SolicitudesController extends Controller
{
    // GET  index.php?url=solicitudes
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

    // POST index.php?url=solicitudes/crear  (AJAX desde Ventas)
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

    // POST index.php?url=solicitudes/cancelar
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

    private function consumeFlash(): ?array
    {
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);
        return $flash;
    }
}
