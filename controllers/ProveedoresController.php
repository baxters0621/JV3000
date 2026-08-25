<?php

// ==========================================
// CONTROLADOR: Proveedores
// ==========================================
// index(): renderiza y procesa POST de
// registrar / editar / toggle_status.

/**
 * ProveedoresController: gestiona el módulo de proveedores.
 *
 * Renderiza el listado con el catálogo de costos por proveedor y procesa
 * las acciones POST de registrar/editar proveedor, toggle_status y las
 * acciones del catálogo (registrar/editar/eliminar entradas).
 */
class ProveedoresController extends Controller
{
    /**
     * Listado de proveedores y procesamiento de acciones POST.
     *
     * POST: valida CSRF y atiende "accion_proveedor" (registrar/editar),
     * "toggle_status" (solo admin), "accion_catalogo" y "eliminar_catalogo".
     * GET: resuelve el flash de sesión y entrega proveedores + catálogo.
     *
     * @return void
     */
    public function index(): void
    {
        Security::verificarPermisoCarga();
        $modelo = new Proveedor();

        // --- Acciones POST ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_proveedor'])) {
            // Validar CSRF (redundante con init.php pero se mantiene por seguridad)
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                $this->flash('danger', 'ERROR DE SEGURIDAD. INTENTE DE NUEVO.');
                $this->redirect('proveedores');
            }

            $supplierAction = $_POST['accion_proveedor'];

            if ($supplierAction === 'toggle_status') {
                Security::soloAdmin();
                $modelo->toggleStatus((int)($_POST['id_proveedor'] ?? 0));
                $this->redirect('proveedores');
            }

            $resultado = $modelo->procesar([
                'accion'         => $supplierAction,
                'rif'            => $_POST['rif'] ?? '',
                'nombre_empresa' => $_POST['nombre_empresa'] ?? '',
                'telefono_completo' => $_POST['telefono_completo'] ?? '',
                'contacto_nombre' => $_POST['contacto_nombre'] ?? '',
                'email'          => $_POST['email'] ?? '',
                'direccion'      => $_POST['direccion'] ?? '',
                'lead_time'      => $_POST['lead_time'] ?? '',
                'moneda'         => $_POST['moneda'] ?? '',
                'status'         => $_POST['status'] ?? '',
                'id_proveedor'   => (int)($_POST['id_proveedor'] ?? 0),
            ]);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('proveedores');
        }

        // --- Acciones POST del catálogo de costos ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_catalogo'])) {
            $resultado = $modelo->procesarCatalogo([
                'accion'           => $_POST['accion_catalogo'],
                'id_catalogo'      => (int)($_POST['id_catalogo'] ?? 0),
                'id_proveedor'     => (int)($_POST['id_proveedor'] ?? 0),
                'id_producto'      => (int)($_POST['id_producto'] ?? 0),
                'costo'            => $_POST['costo'] ?? '',
                'codigo_proveedor' => $_POST['codigo_proveedor'] ?? '',
            ]);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('proveedores');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_catalogo'])) {
            $resultado = $modelo->eliminarCatalogo((int)$_POST['eliminar_catalogo']);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('proveedores');
        }

        // Flash pendiente (lo escriben el modelo o las acciones de arriba).
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        $proveedores = $modelo->listar();
        $esAdmin = Security::esAdmin();

        $this->view('proveedores/index', [
            'titulo'       => 'Proveedores | JV3000 C.A.',
            'wrapper_class' => 'pagina-proveedores',
            'css_extra'    => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
                'modules/proveedores/proveedores.css?v=5',
            ],
            'js_extra'     => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
                'modules/proveedores/proveedores.js?v=6',
            ],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'esAdmin'      => $esAdmin,
            'proveedores'  => $proveedores,
            'total_prov'   => count($proveedores),
            'activos_prov' => $modelo->totalActivos(),
            'catalogo'     => $modelo->catalogoPorProveedor(),
            'productos_activos' => $modelo->productosActivos(),
        ]);
    }
}
