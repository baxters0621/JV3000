<?php

// ==========================================
// CONTROLADOR: Nota de Entrega / Salida (imprimible)
// ==========================================
// POST index.php?url=nota_entrega/store  → guarda preview en sesión (AJAX)
// GET  index.php?url=nota_entrega&token= → nota imprimible desde preview
// GET  index.php?url=nota_entrega&id=    → reimpresión desde BD

/**
 * NotaEntregaController: genera y muestra la nota imprimible.
 *
 * POST AJAX (store) guarda el preview de una venta en sesión; GET con
 * token muestra la nota desde el preview en sesión y GET con id permite
 * la reimpresión desde la base de datos.
 */
class NotaEntregaController extends Controller
{
    /**
     * Almacena el preview de la venta en sesión (endpoint AJAX).
     *
     * Valida método POST + cabecera XMLHttpRequest, construye el preview
     * mediante el modelo NotaEntrega, purga previews previos y guarda los
     * datos bajo un token aleatorio en la sesión. Responde el token en JSON.
     *
     * @return void
     */
    public function store(): void
    {
        Security::verificarPermisoVenta();

        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST'
            || empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
        ) {
            $this->json(['ok' => false, 'error' => 'NO_AJAX'], 403);
        }

        $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
        $resultado = (new NotaEntrega())->construirPreview($_POST, $idUsuario);

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

        $modelo = new NotaEntrega();

        // Reimpresión desde la base de datos
        if (isset($_GET['id'])) {
            $outgoingId = (int)$_GET['id'];
            $outgoingData = $modelo->obtenerPorId($outgoingId);
            if (!$outgoingData) {
                $this->renderRaw('nota_entrega/error');
                return;
            }
            $outgoingDetails = $modelo->obtenerDetalles($outgoingId);
            $this->renderRaw('nota_entrega/nota', $modelo->armarNota($outgoingData, $outgoingDetails, ''));
            return;
        }

        // Preview desde sesión
        $previewToken = $_GET['token'] ?? '';
        $previewData = $previewToken !== ''
            ? ($_SESSION['preview_data'][$previewToken] ?? null)
            : ($_SESSION['preview_data'] ?? null);
        if (!$previewData) {
            $this->renderRaw('nota_entrega/error');
            return;
        }

        $previewDetails = $modelo->obtenerDetallesPreview($previewData);
        $movementTypeName = $modelo->obtenerTipoNombre((int)($previewData['id_tipo_mov'] ?? 0));
        if ($movementTypeName !== '') {
            $previewData['tipo_nombre'] = $movementTypeName;
        }

        $noteData = $modelo->armarNota($previewData, $previewDetails, $previewToken);
        $noteData['csrf'] = Security::generateToken();

        $this->renderRaw('nota_entrega/nota', $noteData);
    }
}
