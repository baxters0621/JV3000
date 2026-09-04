<?php

// ==========================================
// CONTROLADOR: Salidas / Ventas
// ==========================================
// Recibe la petición, delega en el Modelo y entrega
// los datos a la Vista. Sin SQL aquí.

/**
 * SalidasController: gestiona el módulo de salidas/ventas.
 *
 * Recibe la petición, delega en el modelo Salida y entrega los datos a la
 * vista. Maneja la venta vía NotaEntrega (store) y la confirmación
 * transaccional (confirm), además de la gestión de clientes (solo admin).
 */
class SalidasController extends Controller
{
    /**
     * Vista principal de salidas y acciones POST del formulario.
     *
     * GET: renderiza el tablero de salidas con sus KPIs y tipos de movimiento.
     * POST: "eliminar" anula una salida (solo admin, restaura stock) y
     * "accion_cliente" gestiona clientes (solo admin).
     *
     * @return void
     */
    public function index(): void
    {
        Security::verificarPermisoVenta();

        $modelo = new Salida();

        // ---- Acciones POST (CSRF ya validado por init.php) ----
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Anular salida (solo admin) — stock restaurado
            if (isset($_POST['eliminar'])) {
                if (!Security::esAdmin()) {
                    $this->redirect('salidas');
                }
                $outgoingId = (int)$_POST['eliminar'];
                $cancellationResult = $modelo->anular($outgoingId);
                if ($cancellationResult['ok']) {
                    $this->flash('success', 'SALIDA ANULADA. STOCK RESTAURADO.');
                } else {
                    $this->flash('danger', $cancellationResult['error'] ?? 'ERROR EN LA BASE DE DATOS.');
                }
                $this->redirect('salidas');
            }

            // Gestión de clientes (solo admin): registrar/editar
            if (isset($_POST['accion_cliente'])) {
                if (!Security::esAdmin()) {
                    $this->redirect('salidas');
                }
                $resultado = (new Cliente())->procesar($_POST);
                if (!$resultado['ok']) {
                    $this->flash('danger', $resultado['mensaje'] ?? 'ERROR AL PROCESAR EL CLIENTE.');
                }
                $this->redirect('salidas');
            }

            // Toggle status cliente (solo admin)
            if (isset($_POST['toggle_cliente'])) {
                if (!Security::esAdmin()) {
                    $this->redirect('salidas');
                }
                (new Cliente())->toggleStatus((int)$_POST['toggle_cliente']);
                $this->redirect('salidas');
            }
        }

        $esAdmin = Security::esAdmin();
        $csrf = Security::generateToken();
        $tipos_mov_map = $modelo->mapaTiposGrupo();

        $this->view('salidas/index', [
            'titulo'        => 'Salidas / Ventas | JV3000 C.A.',
            'wrapper_class' => 'pagina-salidas',
            'css_extra'     => ['modules/salidas/salidas.css?v=13'],
            'js_extra'      => ['modules/salidas/salidas.js?v=10'],
            'csrf'          => $csrf,
            'js_config'     => ['movementTypeGroups' => $tipos_mov_map, 'csrfToken' => $csrf],
            'salidas'       => $modelo->obtenerSalidas(),
            'tipos_mov'     => $modelo->obtenerTiposMov(),
            'tipos_mov_map' => $tipos_mov_map,
            'kpis'          => $modelo->kpis(),
            'flash'         => $this->consumeFlash(),
            'cli_gestion'   => $esAdmin ? (new Cliente())->listar() : [],
            'cli_activos'   => $esAdmin ? (new Cliente())->totalActivos() : 0,
            'es_admin'      => $esAdmin,
        ]);
    }

    // POST index.php?url=salidas/confirm&token=...
    // Confirma la venta guardada en $_SESSION['preview_data'][$token]
    // ejecutando la transacción completa del modelo.

    /**
     * Confirma la venta guardada en sesión (transacción completa).
     *
     * Lee el preview indicado por el token, ejecuta la confirmación en el
     * modelo Salida (inserta/actualiza salida, descuenta stock y lotes FEFO,
     * registra el movimiento) y limpia el preview usado. Al final redirige
     * con flash y, en caso de éxito, apunta al ancla de la salida creada.
     *
     * @return void
     */
    public function confirm(): void
    {
        Security::verificarPermisoVenta();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('salidas');
        }

        $previewToken = $_GET['token'] ?? '';
        $previewData = $previewToken !== ''
            ? ($_SESSION['preview_data'][$previewToken] ?? null)
            : ($_SESSION['preview_data'] ?? null);

        if (!$previewData) {
            $this->redirect('salidas');
        }

        $confirmationResult = (new Salida())->confirmar($previewData);

        // Limpiar el preview usado (éxito o error), como hacía el original
        if ($previewToken !== '') {
            unset($_SESSION['preview_data'][$previewToken]);
        } else {
            unset($_SESSION['preview_data']);
        }

        if ($confirmationResult['ok']) {
            $this->flash('success', $confirmationResult['edicion'] ? 'SALIDA ACTUALIZADA CORRECTAMENTE.' : 'VENTA REGISTRADA EXITOSAMENTE.');
            header('Location: ' . APP_URL_BASE . 'index.php?url=salidas#salida-' . (int)$confirmationResult['id_salida']);
            exit;
        }

        $this->flash('danger', $confirmationResult['error'] ?? 'ERROR AL REGISTRAR LA SALIDA.');
        $this->redirect('salidas');
    }
}
