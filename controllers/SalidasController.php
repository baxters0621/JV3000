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
 * vista. Maneja el flujo de venta en dos pasos: validación y preview en
 * sesión (procesarAccionSalida) y confirmación transaccional (confirm).
 */
class SalidasController extends Controller
{
    /**
     * Vista principal de salidas y acciones POST del formulario.
     *
     * GET: renderiza el tablero de salidas con sus KPIs y tipos de movimiento.
     * POST: "eliminar" anula una salida (solo admin, restaura stock) y
     * "accion_salida" valida el formulario dejando el preview en sesión.
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

            // Acción legacy "accion_salida" (registrar/editar):
            // valida los datos del formulario y genera el preview en sesión.
            if (isset($_POST['accion_salida'])) {
                $this->procesarAccionSalida($modelo);
            }
        }

        $csrf = Security::generateToken();
        $tipos_mov_map = $modelo->mapaTiposGrupo();

        $this->view('salidas/index', [
            'titulo'        => 'Salidas / Ventas | JV3000 C.A.',
            'wrapper_class' => 'pagina-salidas',
            'css_extra'     => ['modules/salidas/salidas.css?v=4'],
            'js_extra'      => ['modules/salidas/salidas.js?v=5'],
            'csrf'          => $csrf,
            'js_config'     => ['movementTypeGroups' => $tipos_mov_map, 'csrfToken' => $csrf],
            'salidas'       => $modelo->obtenerSalidas(),
            'tipos_mov'     => $modelo->obtenerTiposMov(),
            'tipos_mov_map' => $tipos_mov_map,
            'kpis'          => $modelo->kpis(),
            'flash'         => $this->consumeFlash(),
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

    // Acción legacy del formulario (POST accion_salida): valida y deja el
    // preview listo en sesión para que la nota imprimible lo confirme.

    /**
     * Valida el formulario de salida y guarda el preview en sesión.
     *
     * Acción legacy (POST accion_salida = registrar/editar). Valida acción,
     * producto, cantidad (límites), precio, documento fiscal, causa de ajuste
     * y stock/vencimiento; luego guarda el preview bajo un token y redirige
     * a la nota imprimible (nota_entrega) para su confirmación.
     *
     * @param Salida $modelo Instancia del modelo Salida ya creada.
     * @return void
     */
    private function procesarAccionSalida(Salida $modelo): void
    {
        $accion = in_array($_POST['accion_salida'] ?? '', ['registrar', 'editar']) ? $_POST['accion_salida'] : '';
        $id_producto = intval($_POST['id_producto'] ?? 0);
        $cantidad = intval($_POST['cantidad'] ?? 0);
        $id_tipo_mov = intval($_POST['id_tipo_mov'] ?? 0);

        if (!$accion) {
            $this->flash('danger', 'ACCIÓN INVÁLIDA.');
            $this->redirect('salidas');
        }
        if ($id_producto <= 0) {
            $this->flash('danger', 'SELECCIONE UN PRODUCTO.');
            $this->redirect('salidas');
        }
        if ($cantidad <= 0) {
            $this->flash('danger', 'LA CANTIDAD DEBE SER MAYOR A CERO.');
            $this->redirect('salidas');
        }
        if ($cantidad > Salida::LIMITE_UNIDADES) {
            $this->flash('danger', 'CANTIDAD MÁXIMA PERMITIDA: 999,999.');
            $this->redirect('salidas');
        }

        $tipo_nombre = $modelo->obtenerTipoNombre($id_tipo_mov);
        $grupo = Salida::grupoDeTipo($tipo_nombre);

        $precio_venta = 0;
        if ($grupo === 'venta') {
            $precio_venta = floatval($_POST['precio_venta'] ?? 0);
            if ($precio_venta < 0 || $precio_venta > 99999999.99) {
                $this->flash('danger', 'PRECIO INVÁLIDO.');
                $this->redirect('salidas');
            }
        }

        $nro_fac_man = 'PENDIENTE';
        $nro_control = generarControlNumero();
        $rif_cliente = mb_strtoupper(trim($_POST['rif_cliente'] ?? ''));
        $cliente = mb_strtoupper(trim($_POST['cliente'] ?? ''));
        $fecha_salida = $_POST['fecha_salida'] ?? date('Y-m-d');

        // Validar causa si es ajuste (merma/daño)
        $causa_ajuste = '';
        $motivo_merma = '';
        if ($grupo === 'merma') {
            $causa_ajuste = trim($_POST['causa_ajuste'] ?? '');
            if (!$causa_ajuste) {
                $this->flash('danger', 'SELECCIONE UNA CAUSA DE AJUSTE.');
                $this->redirect('salidas');
            }
            $motivo_merma = trim($_POST['descripcion_motivo'] ?? '');
        }

        $obs_extra = trim($_POST['observaciones'] ?? '');
        $partes = [];
        if ($causa_ajuste) $partes[] = "Causa: $causa_ajuste";
        if ($motivo_merma) $partes[] = "Motivo: $motivo_merma";
        if ($obs_extra) $partes[] = $obs_extra;
        $observaciones = implode(' | ', $partes);
        $id_usuario = $_SESSION['id_usuario'];
        $id_cliente = intval($_POST['id_cliente'] ?? 0) ?: null;

        $rif_cliente = normalizarDocumento($rif_cliente);

        if ($rif_cliente !== '' && $rif_cliente !== 'N/A' && !validarDocumentoFiscal($rif_cliente)) {
            $this->flash('danger', 'DOCUMENTO FISCAL INVÁLIDO (CÉDULA O RIF).');
            $this->redirect('salidas');
        }

        if ($accion !== 'registrar') {
            $this->redirect('salidas');
        }

        $prod_info = $modelo->obtenerProductoBasico($id_producto);

        if (!$prod_info) {
            $this->flash('danger', 'PRODUCTO NO ENCONTRADO.');
            $this->redirect('salidas');
        }
        if ($prod_info['fecha_vencimiento'] && $prod_info['fecha_vencimiento'] <= date('Y-m-d')) {
            $this->flash('danger', 'PRODUCTO VENCIDO. NO SE PUEDE VENDER.');
            $this->redirect('salidas');
        }
        if ((int)$prod_info['stock_actual'] < $cantidad) {
            $this->flash('danger', 'STOCK INSUFICIENTE.');
            $this->redirect('salidas');
        }

        purgarPreviewsSesion();
        $preview_token = bin2hex(random_bytes(16));
        $_SESSION['preview_data'][$preview_token] = [
            'id_producto'        => $id_producto,
            'cantidad'           => $cantidad,
            'precio_venta'       => $precio_venta,
            'cliente'            => $cliente,
            'rif_cliente'        => $rif_cliente ?: 'N/A',
            'id_cliente'         => $id_cliente,
            'nro_factura_manual' => $nro_fac_man,
            'nro_control'        => $nro_control,
            'fecha_salida'       => $fecha_salida,
            'id_tipo_mov'        => $id_tipo_mov,
            'grupo'              => $grupo,
            'causa_ajuste'       => $causa_ajuste,
            'observaciones'      => $observaciones,
            'id_usuario'         => $id_usuario,
        ];
        $this->redirect('nota_entrega', ['token' => $preview_token]);
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
