<?php

// ==========================================
// CONTROLADOR: Compras (facturas de proveedores)
// ==========================================
// Recibe la petición, delega en el Modelo y
// entrega los datos a la Vista. Sin SQL aquí.
//   GET  index.php?url=compras
//   POST index.php?url=compras  (accion_compra | eliminar |
//          accion_proveedor | accion_catalogo | eliminar_catalogo)

/**
 * ComprasController: gestiona el módulo de compras (facturas de proveedores).
 *
 * Recibe la petición, delega en el modelo Compra y entrega los datos a la
 * vista. Aquí no hay SQL: solo orquestación de acciones (registrar, anular,
 * atender solicitud) y preparación de los datos para el tablero.
 *
 * Además integra la gestión de proveedores como sub-módulo del propio
 * módulo de compras (pop-up): registrar/editar/activar-desactivar y su
 * catálogo de costos, delegando en el modelo Proveedor.
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
            // Gestión integrada de proveedores (pop-up dentro de Compras)
            if (isset($_POST['accion_proveedor'])) {
                $provModelo = new Proveedor();
                if ($_POST['accion_proveedor'] === 'toggle_status') {
                    Security::soloAdmin();
                    $provModelo->toggleStatus((int)($_POST['id_proveedor'] ?? 0));
                    $this->redirect('compras');
                }
                $resultado = $provModelo->procesar([
                    'accion'            => $_POST['accion_proveedor'],
                    'rif'               => $_POST['rif'] ?? '',
                    'nombre_empresa'    => $_POST['nombre_empresa'] ?? '',
                    'telefono_completo' => $_POST['telefono_completo'] ?? '',
                    'contacto_nombre'   => $_POST['contacto_nombre'] ?? '',
                    'email'             => $_POST['email'] ?? '',
                    'direccion'         => $_POST['direccion'] ?? '',
                    'lead_time'         => $_POST['lead_time'] ?? '',
                    'moneda'            => $_POST['moneda'] ?? '',
                    'status'            => $_POST['status'] ?? '',
                    'id_proveedor'      => (int)($_POST['id_proveedor'] ?? 0),
                ]);
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('compras');
            }

            // Catálogo de costos: asociar/editar producto de un proveedor
            if (isset($_POST['accion_catalogo'])) {
                $resultado = (new Proveedor())->procesarCatalogo([
                    'accion'           => $_POST['accion_catalogo'],
                    'id_catalogo'      => (int)($_POST['id_catalogo'] ?? 0),
                    'id_proveedor'     => (int)($_POST['id_proveedor'] ?? 0),
                    'id_producto'      => (int)($_POST['id_producto'] ?? 0),
                    'costo'            => $_POST['costo'] ?? '',
                    'codigo_proveedor' => $_POST['codigo_proveedor'] ?? '',
                ]);
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('compras');
            }

            // Quitar entrada del catálogo
            if (isset($_POST['eliminar_catalogo'])) {
                $resultado = (new Proveedor())->eliminarCatalogo((int)$_POST['eliminar_catalogo']);
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('compras');
            }

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

        // Gestión integrada de proveedores: listado completo, catálogo y productos
        // para los pop-ups de administración dentro del módulo de compras.
        $provModelo = new Proveedor();

        $this->view('compras/index', [
            'titulo'            => 'Compras | JV3000 C.A.',
            'wrapper_class'     => 'pagina-compras',
            'css_extra'         => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
                'modules/compras/compras.css?v=12',
            ],
            'js_extra'          => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
                'modules/compras/compras.js?v=9',
            ],
            'compras'           => $modelo->obtenerCompras($filtro_proveedor, $filtro_pago),
            'proveedores'       => $modelo->obtenerProveedores(),
            'catalogo_costos'   => json_encode($modelo->mapaCostosCatalogo()),
            'kpis'              => $modelo->kpis(),
            'solicitud_prefill' => $sol_seleccionada,
            'filtro_pago'       => $filtro_pago,
            'iva_pct'           => $iva_pct,
            'es_admin'          => Security::esAdmin(),
            'flash'             => $this->consumeFlash(),
            'csrf'              => Security::generateToken(),
            'js_config'         => ['taxPercentage' => $iva_pct],
            'prov_gestion'      => $provModelo->listar(),
            'prov_activos'      => $provModelo->totalActivos(),
            'prov_catalogo'     => $provModelo->catalogoPorProveedor(),
            'productos_activos' => $provModelo->productosActivos(),
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
