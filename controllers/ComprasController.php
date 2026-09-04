<?php

// ==========================================
// CONTROLADOR: Compras (facturas de proveedores)
// ==========================================
// Recibe la petición, delega en el Modelo y
// entrega los datos a la Vista. Sin SQL aquí.
//   GET  index.php?url=compras
//   POST index.php?url=compras  (accion_compra | eliminar |
//          accion_proveedor | accion_catalogo | eliminar_catalogo |
//          accion_recepcion)
//
// Integra los sub-procesos del flujo de abastecimiento:
//   - Solicitudes de reposición pendientes (atender/cancelar).
//   - Recepción de mercancía (modal que registra lotes y stock).

/**
 * ComprasController: gestiona el módulo de compras (facturas de proveedores).
 *
 * Recibe la petición, delega en el modelo Compra y entrega los datos a la
 * vista. Aquí no hay SQL: solo orquestación de acciones (registrar, anular,
 * atender solicitud) y preparación de los datos para el tablero.
 *
 * Además integra la gestión de proveedores como sub-módulo del propio
 * módulo de compras (pop-up): registrar/editar/activar-desactivar y su
 * catálogo de costos, delegando en el modelo Proveedor. Tras la
 * simplificación del flujo, también vive aquí la recepción de mercancía
 * (modal que registra lotes y stock vía el modelo Recepcion) y el listado
 * de solicitudes de reposición pendientes (modelo Solicitud).
 */
class ComprasController extends Controller
{
    /**
     * Página principal de compras: tablero, filtros y acciones POST.
     *
     * GET: atiende "atender_solicitud" guardando el prefill en sesión; además
     * aplica los filtros de proveedor/estado de pago y renderiza la vista con
     * las solicitudes pendientes y los datos de recepción integrados.
     * POST: procesa "accion_compra" (registrar), "eliminar" (anular, solo
     * admin), "accion_recepcion" (registrar recepción de mercancía) y las
     * acciones del gestor de proveedores, mostrando flash y redirigiendo.
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

            // Recepción de mercancía (modal integrado en Compras)
            if (isset($_POST['accion_recepcion'])) {
                $resultado = (new Recepcion())->registrar($_POST);
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('compras');
            }

            // Registro de pago parcial
            if (isset($_POST['accion_pago'])) {
                $resultado = (new PagoCompra())->registrar(
                    (int)($_POST['id_compra'] ?? 0),
                    (float)($_POST['monto_pago'] ?? 0),
                    $_POST['metodo_pago'] ?? 'Efectivo',
                    [
                        'telefono'    => $_POST['pago_telefono'] ?? '',
                        'banco'       => $_POST['pago_banco'] ?? '',
                        'referencia'  => $_POST['pago_referencia'] ?? '',
                        'descripcion' => $_POST['pago_descripcion'] ?? '',
                    ],
                    (int)($_SESSION['id_usuario'] ?? 0)
                );
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

        // Sub-procesos integrados: solicitudes de reposición pendientes y
        // datos de recepción de mercancía (compras por recibir, stock entrante).
        $sol_pendientes = (new Solicitud())->obtenerPendientes();
        $rec_datos = (new Recepcion())->dashboard();

        // Datos de pagos para cada compra
        $pagoModelo = new PagoCompra();
        $comprasData = $modelo->obtenerCompras($filtro_proveedor, $filtro_pago);
        foreach ($comprasData as &$c) {
            $c['monto_pagado'] = $pagoModelo->totalPagado((int)$c['id_compra']);
            $c['saldo_pendiente'] = max(0, (float)$c['total'] - $c['monto_pagado']);
        }
        unset($c);

        $this->view('compras/index', [
            'titulo'              => 'Compras | JV3000 C.A.',
            'wrapper_class'       => 'pagina-compras',
            'css_extra'           => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
                'modules/compras/compras.css?v=20',
            ],
            'js_extra'            => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
                'modules/compras/compras.js?v=10',
            ],
            'compras'             => $comprasData,
            'proveedores'         => $modelo->obtenerProveedores(),
            'catalogo_costos'     => json_encode($modelo->mapaCostosCatalogo()),
            'kpis'                => $modelo->kpis(),
            'solicitud_prefill'   => $sol_seleccionada,
            'solicitudes_pendientes' => $sol_pendientes,
            'filtro_pago'         => $filtro_pago,
            'iva_pct'             => $iva_pct,
            'es_admin'            => Security::esAdmin(),
            'flash'               => $this->consumeFlash(),
            'csrf'                => Security::generateToken(),
            'js_config'           => [
                'taxPercentage'      => $iva_pct,
                'recepcionDatos'     => $rec_datos['datos_recepcion'],
            ],
            'prov_gestion'        => $provModelo->listar(),
            'prov_activos'        => $provModelo->totalActivos(),
            'prov_catalogo'       => $provModelo->catalogoPorProveedor(),
            'productos_activos'   => $provModelo->productosActivos(),
            'compras_pendientes'  => $rec_datos['compras_pendientes'],
            'unidades_por_recibir' => $rec_datos['unidades_por_recibir'],
            'recepciones'         => $rec_datos['recepciones'],
            'recepciones_hoy'     => $rec_datos['recepciones_hoy'],
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
}
