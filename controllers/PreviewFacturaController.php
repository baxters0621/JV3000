<?php

// ==========================================
// CONTROLADOR: Preview de Nota / Nota de Entrega
// ==========================================
// POST index.php?url=preview_factura/store  → guarda preview en sesión (AJAX)
// GET  index.php?url=preview_factura&token= → nota imprimible desde preview
// GET  index.php?url=preview_factura&id=    → reimpresión desde BD

/**
 * PreviewFacturaController: genera y muestra la nota imprimible.
 *
 * POST AJAX (store) guarda el preview de una venta en sesión; GET con
 * token muestra la nota desde el preview en sesión y GET con id permite
 * la reimpresión desde la base de datos.
 */
class PreviewFacturaController extends Controller
{
    /**
     * Almacena el preview de la venta en sesión (endpoint AJAX).
     *
     * Valida método POST + cabecera XMLHttpRequest, construye el preview
     * mediante el modelo PreviewNota, purga previews previos y guarda los
     * datos bajo un token aleatorio en la sesión. Responde el token en JSON.
     *
     * @return void
     */
    public function store(): void
    {
        Security::verificarPermisoVenta();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST'
            || empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            $this->json(['ok' => false, 'error' => 'NO_AJAX'], 403);
        }

        $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
        $resultado = (new PreviewNota())->construirPreview($_POST, $idUsuario);

        if (!$resultado['ok']) {
            $this->json(['ok' => false, 'error' => $resultado['error'] ?? 'ERROR AL GENERAR EL PREVIEW.'], 400);
        }

        purgarPreviewsSesion();
        $token = bin2hex(random_bytes(16));
        $_SESSION['preview_data'][$token] = $resultado['data'];
        $_SESSION['preview_limit'] = 20;

        $this->json(['ok' => true, 'token' => $token]);
    }

    /**
     * Muestra la nota imprimible desde preview (token) o desde BD (id).
     *
     * GET con "id": reimpresión de una salida guardada en la base de datos.
     * GET con "token" (o preview único en sesión): arma la nota a partir de
     * los datos del preview. Si no hay datos, renderiza la vista de error.
     *
     * @return void
     */
    public function index(): void
    {
        Security::verificarPermisoVenta();

        $modelo = new PreviewNota();

        // Reimpresión desde la base de datos
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $data = $modelo->obtenerPorId($id);
            if (!$data) {
                $this->renderRaw('preview_factura/error');
                return;
            }
            $detalles = $modelo->obtenerDetalles($id);
            $this->renderRaw('preview_factura/nota', $modelo->armarNota($data, $detalles, ''));
        }

        // Preview desde sesión
        $preview_token = $_GET['token'] ?? '';
        $data = $preview_token !== ''
            ? ($_SESSION['preview_data'][$preview_token] ?? null)
            : ($_SESSION['preview_data'] ?? null);
        if (!$data) {
            $this->renderRaw('preview_factura/error');
            return;
        }

        $detalles = $modelo->obtenerDetallesPreview($data);
        $tn = $modelo->obtenerTipoNombre((int)($data['id_tipo_mov'] ?? 0));
        if ($tn !== '') {
            $data['tipo_nombre'] = $tn;
        }

        $datos = $modelo->armarNota($data, $detalles, $preview_token);
        $datos['csrf'] = Security::generateToken();

        $this->renderRaw('preview_factura/nota', $datos);
    }
}
