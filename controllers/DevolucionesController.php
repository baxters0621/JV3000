<?php

// ==========================================
// CONTROLADOR: Devoluciones
// ==========================================
// Procesa devoluciones de ventas con FEFO.

/**
 * DevolucionesController: gestiona devoluciones de clientes.
 *
 * Busca la venta original, muestra productos y lotes disponibles,
 * y registra la devolución restaurando stock FEFO.
 */
class DevolucionesController extends Controller
{
    /**
     * Página principal: listado y acciones POST.
     *
     * @return void
     */
    public function index(): void
    {
        Security::verificarPermisoVenta();

        $modelo = new Devolucion();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['accion_devolucion'])) {
                $resultado = $modelo->registrar($_POST, (int)($_SESSION['id_usuario'] ?? 0));
                $this->flash($resultado['ok'] ? 'success' : 'danger',
                    $resultado['ok'] ? 'DEVOLUCIÓN REGISTRADA EXITOSAMENTE.' : ($resultado['error'] ?? 'ERROR AL REGISTRAR DEVOLUCIÓN.'));
                $this->redirect('devoluciones');
            }
        }

        $busqueda = trim($_GET['q'] ?? '');
        $ventas = $modelo->buscarVentas($busqueda);

        $this->view('devoluciones/index', [
            'titulo'        => 'Devoluciones | JV3000 C.A.',
            'wrapper_class' => 'pagina-devoluciones',
            'css_extra'     => ['modules/devoluciones/devoluciones.css'],
            'js_extra'      => ['modules/devoluciones/devoluciones.js'],
            'csrf'          => Security::generateToken(),
            'ventas'        => $ventas,
            'devoluciones'  => $modelo->listar(),
            'flash'         => $this->consumeFlash(),
        ]);
    }

    /**
     * API AJAX: detalle de una venta para devolución.
     *
     * GET con parámetro id_salida. Devuelve JSON con productos y lotes.
     *
     * @return void
     */
    public function detalle(): void
    {
        Security::verificarPermisoVenta();

        $idSalida = (int)($_GET['id_salida'] ?? 0);
        $modelo = new Devolucion();
        $venta = $modelo->detalleVenta($idSalida);

        if (!$venta) {
            $this->json(['success' => false, 'error' => 'Venta no encontrada'], 404);
            return;
        }

        // Enriquecer con lotes disponibles por producto
        foreach ($venta['detalles'] as &$det) {
            $det['lotes'] = $modelo->lotesDisponibles((int)$det['id_producto']);
        }
        unset($det);

        $this->json(['success' => true, 'venta' => $venta]);
    }
}
