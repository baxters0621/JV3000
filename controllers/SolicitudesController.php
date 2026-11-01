<?php

// ==========================================
// CONTROLADOR: Solicitudes de Reposición (endpoints AJAX)
// ==========================================
// Tras integrar Solicitudes dentro del módulo de Compras,
// este controlador queda solo como API: la creación desde
// Ventas (cuando no hay stock) y la cancelación de pendientes
// desde el panel de Compras. No renderiza vistas.

/**
 * SolicitudesController: endpoints AJAX de solicitudes de reposición.
 *
 * Tras integrar el tablero de solicitudes dentro del módulo de Compras,
 * este controlador queda solo como API: la creación desde Ventas (cuando
 * un producto queda sin stock) y la cancelación de solicitudes pendientes
 * desde el listado integrado en Compras.
 */
class SolicitudesController extends Controller
{
    /**
     * Crea una solicitud de reposición desde Ventas (AJAX).
     *
     * Valida permisos (ventas o admin) y método POST, decodifica los ítems
     * JSON del formulario y delega en el modelo Solicitud. Responde el
     * resultado como JSON con el código HTTP correspondiente.
     *
     * @return void
     */
    public function crear(): void
    {
        if (!(Security::puedeVender() || Security::esAdmin())) {
            $this->json([
                'success' => false,
                'message' => 'SIN PERMISOS PARA CREAR SOLICITUDES.',
                'ok' => false,
                'error' => 'SIN PERMISOS PARA CREAR SOLICITUDES.',
            ], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'message' => 'MÉTODO NO PERMITIDO.',
                'ok' => false,
                'error' => 'MÉTODO NO PERMITIDO.',
            ], 405);
        }

        $rawRequestItems = json_decode($_POST['items'] ?? '[]', true);
        $requestReason = trim($_POST['motivo'] ?? '');
        $currentUserId = (int)($_SESSION['id_usuario'] ?? 0);

        $creationResult = (new Solicitud())->crear(is_array($rawRequestItems) ? $rawRequestItems : [], $requestReason, $currentUserId);
        $success = (bool)$creationResult['ok'];
        $message = (string)($creationResult['error'] ?? 'SOLICITUD CREADA.');
        $this->json(array_merge($creationResult, [
            'success' => $success,
            'message' => $message,
        ]), $success ? 200 : 400);
    }

    /**
     * Cancela una solicitud de reposición pendiente.
     *
     * Requiere método POST. Delega la cancelación en el modelo Solicitud,
     * guarda un flash con el resultado y redirige al módulo de Compras,
     * donde ahora vive el listado de solicitudes pendientes.
     *
     * @return void
     */
    public function cancelar(): void
    {
        if (!Security::puedeCargar()) {
            $this->json([
                'success' => false,
                'message' => 'SIN PERMISOS PARA CANCELAR SOLICITUDES.',
                'ok' => false,
                'error' => 'SIN PERMISOS PARA CANCELAR SOLICITUDES.',
            ], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'message' => 'MÉTODO NO PERMITIDO.',
                'ok' => false,
                'error' => 'MÉTODO NO PERMITIDO.',
            ], 405);
        }

        $requestId = (int)($_POST['id_solicitud'] ?? 0);
        $cancellationResult = (new Solicitud())->cancelar($requestId);
        $success = (bool)$cancellationResult['ok'];
        $message = (string)($cancellationResult['error'] ?? 'SOLICITUD CANCELADA.');
        $this->json(array_merge($cancellationResult, [
            'success' => $success,
            'message' => $message,
        ]), $success ? 200 : 400);
    }
}