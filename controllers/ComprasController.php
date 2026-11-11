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
                    'stock_disponible' => $_POST['stock_disponible'] ?? '',
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
                $mensaje = $resultado['mensaje'];
                if ($resultado['ok']) {
                    unset($_SESSION['compras_recuperacion']);
                } else {
                    $recuperacion = prepararRecuperacionRecepcion($_POST);
                    if ($recuperacion === null) {
                        unset($_SESSION['compras_recuperacion']);
                        $mensaje .= ' NO SE PUDO RESTAURAR EL FORMULARIO; REVISE LA COMPRA ANTES DE CONTINUAR.';
                    } else {
                        $_SESSION['compras_recuperacion'] = [
                            'tipo' => 'recepcion',
                            'datos' => $recuperacion,
                        ];
                        if ($recuperacion['items'] === null) {
                            $mensaje .= ' LAS CANTIDADES NO SE RESTAURARON PORQUE EL DETALLE NO ES UNÍVOCO; REVÍSELO MANUALMENTE.';
                        }
                    }
                    $mensaje .= ' SI EL RESULTADO NO ESTÁ CLARO, REVISE EL ESTADO DE LA RECEPCIÓN ANTES DE MODIFICAR DATOS O REENVIAR.';
                }
                $this->flash($resultado['ok'] ? 'success' : 'danger', $mensaje, $resultado['ok'] ? 'compra_recepcion' : null);
                $this->redirect('compras');
            }

            // Registro de pago parcial
            if (isset($_POST['accion_pago'])) {
                $id_compra_pago = $this->postInt('id_compra', 1);
                $monto_pago = $this->postFloat('monto_pago', 0.01);
                $metodo_pago = $this->postTexto('metodo_pago', 30) ?? '';
                if ($id_compra_pago === null || $monto_pago === null || $metodo_pago === '') {
                    $this->flash('danger', 'DATOS DE PAGO INVÁLIDOS. VERIFICA EL MONTO Y EL MÉTODO.');
                    $this->redirect('compras');
                }
                $detalle_pago = [];
                foreach (['pago_telefono', 'pago_banco', 'pago_referencia', 'pago_descripcion'] as $campo_pago) {
                    $detalle_pago[str_replace('pago_', '', $campo_pago)] = $this->postTexto($campo_pago, 255) ?? '';
                }
                $resultado = (new PagoCompra())->registrar(
                    $id_compra_pago,
                    $monto_pago,
                    $metodo_pago,
                    $detalle_pago,
                    (int)($_SESSION['id_usuario'] ?? 0)
                );
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje'], $resultado['ok'] ? 'compra_pago' : null);
                $this->redirect('compras');
            }

            if (isset($_POST['accion_compra'])) {
                $esFetch = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                    && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                $resultado = $modelo->registrar($_POST, (int)($_SESSION['id_usuario'] ?? 0));
                if ($resultado['ok']) {
                    unset($_SESSION['sol_seleccionada']);
                    unset($_SESSION['compras_recuperacion']);
                } else {
                    $recuperacion = prepararRecuperacionCompra($_POST);
                    if ($recuperacion === null) {
                        unset($_SESSION['compras_recuperacion']);
                        $resultado['mensaje'] .= ' NO SE PUDO RESTAURAR EL DETALLE COMPLETO; REVISE Y COMPLETE EL FORMULARIO ANTES DE REINTENTAR.';
                    } else {
                        $_SESSION['compras_recuperacion'] = [
                            'tipo' => 'compra',
                            'datos' => $recuperacion,
                        ];
                    }
                }
                if ($esFetch) {
                    if ($resultado['ok']) {
                        $this->flash('success', $resultado['mensaje'], 'compra_crear');
                        $this->json([
                            'success' => true,
                            'message' => $resultado['mensaje'],
                            'mensaje' => $resultado['mensaje'],
                        ]);
                    }
                    $this->json([
                        'success' => false,
                        'message' => $resultado['mensaje'],
                        'error' => $resultado['mensaje'],
                    ], 422);
                }
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje'], $resultado['ok'] ? 'compra_crear' : null);
                $this->redirect('compras');
            }

            if (isset($_POST['eliminar'])) {
                if (!Security::esAdmin()) {
                    $this->flash('danger', 'SIN PERMISOS PARA ANULAR COMPRAS.');
                    $this->redirect('compras');
                }
                $id_compra_anular = $this->postInt('eliminar', 1);
                if ($id_compra_anular === null) {
                    $this->flash('danger', 'COMPRA INVÁLIDA PARA ANULAR.');
                    $this->redirect('compras');
                }
                $resultado = $modelo->anular($id_compra_anular);
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje'], $resultado['ok'] ? 'compra_anular' : null);
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
        foreach ($rec_datos['datos_recepcion'] as &$datos_compra_recepcion) {
            $datos_compra_recepcion['clave_idempotencia'] = bin2hex(random_bytes(32));
        }
        unset($datos_compra_recepcion);
        $recuperacion_formulario = $_SESSION['compras_recuperacion'] ?? null;
        unset($_SESSION['compras_recuperacion']);
        $recuperacion_compra = null;
        $recuperacion_recepcion = null;
        if (
            is_array($recuperacion_formulario)
            && isset($recuperacion_formulario['tipo'], $recuperacion_formulario['datos'])
            && is_array($recuperacion_formulario['datos'])
        ) {
            if ($recuperacion_formulario['tipo'] === 'compra') {
                $recuperacion_compra = $recuperacion_formulario['datos'];
            } elseif (
                $recuperacion_formulario['tipo'] === 'recepcion'
                && isset($rec_datos['datos_recepcion'][(int)($recuperacion_formulario['datos']['id_compra'] ?? 0)])
            ) {
                $recuperacion_recepcion = $recuperacion_formulario['datos'];
            } elseif ($recuperacion_formulario['tipo'] === 'recepcion') {
                $mensajeFlash = $_SESSION['flash_msg'] ?? ['tipo' => 'warning', 'texto' => ''];
                if (!is_array($mensajeFlash)) {
                    $mensajeFlash = ['tipo' => 'warning', 'texto' => ''];
                }
                $mensajeFlash['tipo'] = $mensajeFlash['tipo'] ?? 'warning';
                $mensajeFlash['texto'] = ($mensajeFlash['texto'] ?? '')
                    . ' LA COMPRA YA NO APARECE PENDIENTE; REVISE EL TABLERO ANTES DE CONTINUAR.';
                $_SESSION['flash_msg'] = $mensajeFlash;
            }
        }

        // Datos de pagos para cada compra
        $pagoModelo = new PagoCompra();
        $comprasData = $modelo->obtenerCompras($filtro_proveedor, $filtro_pago);
        foreach ($comprasData as &$c) {
            $c['monto_pagado'] = $pagoModelo->totalPagado((int)$c['id_compra']);
            $c['saldo_pendiente'] = max(0, (float)$c['total'] - $c['monto_pagado']);
        }
        unset($c);

        // Tasa oficial BCV: los montos se guardan en USD y solo se convierten al mostrarse.
        $tasa_bcv = jv_tasa_bcv();

        $this->view('compras/index', [
            'titulo'              => 'Compras | JV3000 C.A.',
            'wrapper_class'       => 'pagina-compras',
            'css_extra'           => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css',
                'modules/compras/compras.css',
            ],
            'js_extra'            => [
                'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js',
                'modules/compras/compras.js',
            ],
            'compras'             => $comprasData,
            'proveedores'         => $modelo->obtenerProveedores(),
            'catalogo_productos'  => json_encode(
                $modelo->catalogoProductosPorProveedor(),
                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
            ),
            'kpis'                => $modelo->kpis(),
            'solicitud_prefill'   => $sol_seleccionada,
            'solicitudes_pendientes' => $sol_pendientes,
            'filtro_pago'         => $filtro_pago,
            'iva_pct'             => $iva_pct,
            'tasa_bcv'            => $tasa_bcv['usd'],
            'tasa_bcv_fecha'      => $tasa_bcv['fecha'],
            'tasa_bcv_manual'     => $tasa_bcv['manual'],
            'tasa_bcv_manual_guardada' => jv_tasa_bcv_manual(),
            'tasa_bcv_referencia' => jv_tasa_bcv_referencia(),
            'es_admin'            => Security::esAdmin(),
            'flash'               => $this->consumeFlash(),
            'csrf'                => Security::generateToken(),
            'js_config'           => [
                'taxPercentage'      => $iva_pct,
                'recepcionDatos'     => $rec_datos['datos_recepcion'],
                'compraRecuperacion' => $recuperacion_compra,
                'recepcionRecuperacion' => $recuperacion_recepcion,
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
     * Devuelve en JSON la cabecera de la compra (con los datos fiscales del
     * proveedor) y sus ítems.
     *
     * Endpoint de solo lectura usado por el modal "Ver detalle" del tablero.
     * Exige permisos de carga (administrador u operador de carga).
     *
     * @return void
     */
    public function detalle(): void
    {
        Security::verificarPermisoCarga();

        $id_compra = isset($_GET['id_compra']) && is_scalar($_GET['id_compra'])
            ? (int)$_GET['id_compra']
            : 0;
        if ($id_compra <= 0) {
            $this->json(['success' => false, 'message' => 'COMPRA INVÁLIDA.'], 422);
        }

        $detalle = (new Compra())->obtenerDetalle($id_compra);
        if ($detalle === null) {
            $this->json(['success' => false, 'message' => 'COMPRA NO ENCONTRADA.'], 404);
        }

        // La tasa viaja con el detalle para que el modal convierta sin otra petición.
        $tasa_bcv = jv_tasa_bcv();

        $this->json([
            'success'        => true,
            'tasa_bcv'       => $tasa_bcv['usd'],
            'tasa_bcv_fecha' => $tasa_bcv['fecha'],
            'compra'         => $detalle['compra'],
            'items'          => $detalle['items'],
        ]);
    }

    /**
     * Devuelve en JSON el estado de un número de factura y el último
     * registrado para el proveedor indicado.
     *
     * Endpoint de solo lectura usado por la validación en vivo del formulario
     * de alta (evento blur) y por la ayuda visual del campo. Exige permisos de
     * carga. Si no se recibe número, solo informa el último consecutivo.
     *
     * @return void
     */
    public function factura_estado(): void
    {
        Security::verificarPermisoCarga();

        $nro_factura = trim((string)($_GET['nro_factura'] ?? ''));
        $id_proveedor = (int)($_GET['id_proveedor'] ?? 0);

        $estado = (new Compra())->estadoFactura($nro_factura, $id_proveedor);

        $this->json(array_merge(['success' => true], $estado));
    }

    /**
     * Fija o retira la tasa BCV manual.
     *
     * La tasa manual tiene prioridad sobre la API y el caché. Al guardarla
     * se invalida cache/tasas_bcv.json y se responde JSON al AJAX para que
     * la interfaz actualice la tasa y los montos sin recargar la página.
     *
     * @return void
     */
    public function tasa_manual(): void
    {
        Security::verificarPermisoCarga();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('compras');
        }

        $es_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

        if (($_POST['accion_tasa'] ?? '') === 'automatica') {
            setConfig('tasa_bcv_manual', '');
            setConfig('tasa_bcv_manual_fecha', '');
            jv_limpiar_cache_tasa_bcv();
            registrarAuditoria('Tasa BCV manual retirada', 'Se vuelve a la tasa automática.');

            $automatica = $es_ajax ? jv_tasa_bcv() : ['usd' => null, 'fecha' => null];
            $this->responderTasaManual(
                true,
                $automatica['usd'],
                $automatica['fecha'],
                false,
                'Tasa BCV automática restablecida.',
                $es_ajax
            );
            return;
        }

        $valor = trim((string)($_POST['tasa_manual'] ?? ''));
        $valor = str_replace(',', '.', $valor);

        if ($valor === '' || !is_numeric($valor)) {
            $this->responderTasaManual(
                false,
                null,
                null,
                true,
                'Ingresa una tasa numérica válida.',
                $es_ajax,
                422
            );
            return;
        }

        $tasa = round((float)$valor, 4);
        $error = jv_validar_tasa_manual($tasa, jv_tasa_bcv_referencia());
        if ($error !== null) {
            $this->responderTasaManual(false, null, null, true, $error, $es_ajax, 422);
            return;
        }

        $fecha = date('Y-m-d H:i:s');
        setConfig('tasa_bcv_manual', (string)$tasa);
        setConfig('tasa_bcv_manual_fecha', $fecha);

        // Limpieza inmediata: la próxima consulta no puede devolver la caché vieja.
        jv_limpiar_cache_tasa_bcv();

        registrarAuditoria('Tasa BCV manual', 'Bs. ' . number_format($tasa, 2, '.', ','));

        $this->responderTasaManual(
            true,
            $tasa,
            $fecha,
            true,
            'Tasa BCV manual guardada: ' . jv_formatear_bolivares($tasa, 2) . '.',
            $es_ajax
        );
    }

    /**
     * Responde el resultado de fijar o retirar la tasa manual.
     *
     * En AJAX devuelve JSON con el nuevo valor; en POST nativo conserva el
     * flash y la redirección de siempre.
     *
     * @param bool        $ok      Indica si la operación fue exitosa.
     * @param float|null  $tasa    Tasa aplicada.
     * @param string|null $fecha   Fecha de la tasa (Y-m-d o Y-m-d H:i:s).
     * @param bool        $manual  Indica si la tasa es manual.
     * @param string      $mensaje Mensaje para el usuario.
     * @param bool        $es_ajax Indica si la petición es AJAX.
     * @param int         $status  Código HTTP para la respuesta JSON.
     * @return void
     */
    private function responderTasaManual(
        bool $ok,
        ?float $tasa,
        ?string $fecha,
        bool $manual,
        string $mensaje,
        bool $es_ajax,
        int $status = 200
    ): void {
        if ($es_ajax) {
            $this->json([
                'success'     => $ok,
                'tasa'        => $tasa,
                'valor'       => $tasa !== null && $tasa > 0 ? jv_formatear_bolivares($tasa, 2) : null,
                'valor_input' => $tasa !== null && $tasa > 0 ? number_format($tasa, 2, '.', '') : '',
                'fecha'       => $fecha !== null && $fecha !== '' ? date('d/m/Y', strtotime($fecha)) : null,
                'manual'      => $manual,
                'message'     => $mensaje,
            ], $status);
        }

        $this->flash($ok ? 'success' : 'danger', $mensaje);
        $this->redirect('compras');
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
