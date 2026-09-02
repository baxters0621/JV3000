<?php

// ==========================================
// CONTROLADOR: Usuarios
// ==========================================
// Gestión de usuarios: listar, editar, aprobar,
// activar/desactivar.

/**
 * UsuariosController: gestiona el módulo de administración de usuarios.
 */
class UsuariosController extends Controller
{
    public function index(): void
    {
        Security::soloAdmin();

        $modelo = new Usuario();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['accion_usuario']) && $_POST['accion_usuario'] === 'editar') {
                $resultado = $modelo->editar($_POST, (int)($_SESSION['id_usuario'] ?? 0));
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('usuarios');
            }

            if (isset($_POST['aprobar_usuario'])) {
                $resultado = $modelo->aprobar(
                    (int)$_POST['aprobar_usuario'],
                    (int)($_POST['id_rol'] ?? 0),
                    (int)($_SESSION['id_usuario'] ?? 0)
                );
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('usuarios');
            }

            if (isset($_POST['toggle_status'])) {
                $resultado = $modelo->toggleStatus(
                    (int)$_POST['toggle_status'],
                    (int)($_SESSION['id_usuario'] ?? 0)
                );
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('usuarios');
            }
        }

        $this->view('usuarios/index', [
            'titulo'        => 'Colaboradores | JV3000 C.A.',
            'wrapper_class' => 'usuarios-page',
            'css_extra'     => ['dashboard/usuarios.css'],
            'js_extra'      => ['dashboard/usuarios.js'],
            'roles_lista'   => $modelo->listarRoles(),
            'usuarios'      => $modelo->listar(),
            'total_users'   => $modelo->total(),
            'activos'       => $modelo->totalActivos(),
            'pendientes'    => $modelo->totalPendientes(),
            'flash'         => $this->consumeFlash(),
            'csrf'          => Security::generateToken(),
            'id_propio'     => (int)($_SESSION['id_usuario'] ?? 0),
        ]);
    }
}
