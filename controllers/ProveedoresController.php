<?php

// ==========================================
// CONTROLADOR: Proveedores
// ==========================================
// index(): renderiza y procesa POST de
// registrar / editar / toggle_status.

/**
 * ProveedoresController: gestiona el módulo de proveedores.
 *
 * Renderiza el listado con KPIs de crédito y procesa las acciones POST
 * de registrar, editar y cambiar estado (toggle_status). También ejecuta
 * la migración de teléfonos legacy y muestra flashes por query string.
 */
class ProveedoresController extends Controller
{
    /**
     * Listado de proveedores y procesamiento de acciones POST.
     *
     * POST: valida CSRF, procesa "accion_proveedor" (registrar/editar) o
     * "toggle_status" (solo admin). GET: migra teléfonos legacy, resuelve
     * flashes por ?res=/?err= y entrega los datos con KPIs de crédito.
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

            $accion = $_POST['accion_proveedor'];

            if ($accion === 'toggle_status') {
                Security::soloAdmin();
                $modelo->toggleStatus((int)($_POST['id_proveedor'] ?? 0));
                $this->redirect('proveedores');
            }

            $resultado = $modelo->procesar([
                'accion'         => $accion,
                'rif'            => $_POST['rif'] ?? '',
                'nombre_empresa' => $_POST['nombre_empresa'] ?? '',
                'telefono_completo' => $_POST['telefono_completo'] ?? '',
                'contacto_nombre'=> $_POST['contacto_nombre'] ?? '',
                'email'          => $_POST['email'] ?? '',
                'direccion'      => $_POST['direccion'] ?? '',
                'lead_time'      => $_POST['lead_time'] ?? '',
                'limite_credito' => $_POST['limite_credito'] ?? '',
                'dias_credito'   => $_POST['dias_credito'] ?? '',
                'condiciones_pago' => $_POST['condiciones_pago'] ?? '',
                'moneda'         => $_POST['moneda'] ?? '',
                'status'         => $_POST['status'] ?? '',
                'id_proveedor'   => (int)($_POST['id_proveedor'] ?? 0),
            ]);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('proveedores');
        }

        // Side-effects en GET: migración de teléfonos legacy
        $modelo->migrarTelefonosLegacy();

        // Flash legacy por GET ?res= / ?err=
        $flash = null;
        if (isset($_GET['res'])) {
            $map = ['success' => 'PROVEEDOR REGISTRADO CON ÉXITO.', 'updated' => 'DATOS ACTUALIZADOS CORRECTAMENTE.'];
            $flash = ['tipo' => 'success', 'texto' => $map[$_GET['res']] ?? 'OPERACIÓN EXITOSA.'];
        } elseif (isset($_GET['err'])) {
            $map = ['rif_exists' => 'EL RIF YA PERTENECE A OTRO PROVEEDOR.', 'csrf' => 'ERROR DE SEGURIDAD. INTENTE DE NUEVO.', 'rif_invalido' => 'FORMATO DE RIF INVÁLIDO. USE: J-12345678-0', 'tel_invalido' => 'TELÉFONO INVÁLIDO. INGRESE UN NÚMERO VÁLIDO CON CÓDIGO DE PAÍS.', 'db_error' => 'ERROR EN LA BASE DE DATOS.'];
            $flash = ['tipo' => 'danger', 'texto' => $map[$_GET['err']] ?? 'ERROR DESCONOCIDO.'];
        }
        $flash_s = $_SESSION['flash_msg'] ?? $flash;
        if ($flash_s) $flash = $flash_s;
        unset($_SESSION['flash_msg']);

        $proveedores = $modelo->listar();
        $esAdmin = Security::esAdmin();

        $this->view('proveedores/index', [
            'titulo'       => 'Proveedores | JV3000 C.A.',
            'wrapper_class'=> 'pagina-proveedores',
            'css_extra'    => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
                'modules/proveedores/proveedores.css?v=2',
            ],
            'js_extra'     => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
                'modules/proveedores/proveedores.js?v=5',
            ],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'esAdmin'      => $esAdmin,
            'proveedores'  => $proveedores,
            'total_prov'   => count($proveedores),
            'activos_prov' => $modelo->totalActivos(),
            'limite_credito_total' => $modelo->limiteCreditoTotal(),
            'credito_usado' => $modelo->creditoUsado(),
        ]);
    }
}
