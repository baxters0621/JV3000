<?php

// ==========================================
// CONTROLADOR: Compras (facturas de proveedores)
// ==========================================
// Recibe la petición, delega en el Modelo y
// entrega los datos a la Vista. Sin SQL aquí.
//   GET  index.php?url=compras
//   POST index.php?url=compras  (accion_compra | eliminar)

/**
 * ComprasController: gestiona el módulo de compras (facturas de proveedores).
 *
 * Recibe la petición, delega en el modelo Compra y entrega los datos a la
 * vista. Aquí no hay SQL: solo orquestación de acciones (registrar, anular,
 * atender solicitud) y preparación de los datos para el tablero.
 */
class ComprasController extends Controller
{
    /**
     * Página principal de compras: tablero, filtros y acciones POST.
     *
     * GET: atiende "atender_solicitud" guardando el prefill en sesión; además
     * aplica los filtros de proveedor/estado de pago y renderiza la vista.
     * POST: procesa "accion_compra" (registrar) y "eliminar" (anular, solo
     * admin), mostrando flash con el resultado y redirigiendo.
     *
     * @return void
     */
    public function index(): void
    {
        Security::verificarPermisoCarga();

        $modelo = new Compra();

        // GET atender_solicitud: guarda la solicitud a atender y redirige
        $atender_solicitud = (int)($_GET['atender_solicitud'] ?? 0);
        if ($atender_solicitud > 0) {
            $prefill = $modelo->prefillSolicitud($atender_solicitud);
            if ($prefill) {
                $_SESSION['sol_seleccionada'] = $prefill;
            }
            $this->redirect('compras');
        }

        // --- Acciones POST ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['accion_compra'])) {
                $resultado = $modelo->registrar($_POST, (int)($_SESSION['id_usuario'] ?? 0));
                if ($resultado['ok']) {
                    unset($_SESSION['sol_seleccionada']);
                }
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('compras');
            }

            if (isset($_POST['eliminar'])) {
                if (!Security::esAdmin()) {
                    $this->flash('danger', 'SIN PERMISOS PARA ANULAR COMPRAS.');
                    $this->redirect('compras');
                }
                $resultado = $modelo->anular((int)($_POST['eliminar'] ?? 0));
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('compras');
            }
        }

        // --- GET: filtros ---
        $filtro_proveedor = (int)($_GET['filtro_proveedor'] ?? 0);
        $filtro_pago = in_array($_GET['filtro_pago'] ?? '', ['Pendiente', 'Pagada'], true) ? $_GET['filtro_pago'] : '';
        $sol_seleccionada = $_SESSION['sol_seleccionada'] ?? null;
        $iva_pct = (float)getConfig('iva_porcentaje', '16');

        $this->view('compras/index', [
            'titulo'            => 'Compras | JV3000 C.A.',
            'wrapper_class'     => 'pagina-compras',
            'css_extra'         => ['modules/compras/compras.css?v=4'],
            'js_extra'          => ['modules/compras/compras.js?v=5'],
            'compras'           => $modelo->obtenerCompras($filtro_proveedor, $filtro_pago),
            'proveedores'       => $modelo->obtenerProveedores(),
            'credito_usado'     => $modelo->creditoUsadoPorProveedor(),
            'kpis'              => $modelo->kpis(),
            'solicitud_prefill' => $sol_seleccionada,
            'filtro_pago'       => $filtro_pago,
            'iva_pct'           => $iva_pct,
            'es_admin'          => Security::esAdmin(),
            'flash'             => $this->consumeFlash(),
            'csrf'              => Security::generateToken(),
            'js_config'         => ['c1' => $iva_pct],
        ]);
    }

    /**
     * Cancela la solicitud seleccionada para atender y vuelve a compras.
     *
     * Simplemente elimina la solicitud guardada en sesión (prefill) y
     * redirige al listado de compras.
     *
     * @return void
     */
    public function cancelar_solicitud(): void
    {
        Security::verificarPermisoCarga();
        unset($_SESSION['sol_seleccionada']);
        $this->redirect('compras');
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
