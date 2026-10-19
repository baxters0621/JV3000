<?php

// ==========================================
// CONTROLADOR: Usuarios
// ==========================================
// Gestión de usuarios: listar, crear, editar,
// aprobar, activar/desactivar, restablecer
// contraseña y eliminar.

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
            if (isset($_POST['accion_usuario']) && $_POST['accion_usuario'] === 'crear') {
                $resultado = $modelo->crear($_POST);
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('usuarios');
            }

            if (isset($_POST['accion_usuario']) && $_POST['accion_usuario'] === 'editar') {
                $resultado = $modelo->editar($_POST, (int)($_SESSION['id_usuario'] ?? 0));
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('usuarios');
            }

            if (isset($_POST['accion_usuario']) && $_POST['accion_usuario'] === 'restablecer_password') {
                $resultado = $modelo->resetearPassword(
                    (int)($_POST['id_usuario'] ?? 0),
                    (string)($_POST['password'] ?? ''),
                    (string)($_POST['password_confirm'] ?? '')
                );
                $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
                $this->redirect('usuarios');
            }

            if (isset($_POST['accion_usuario']) && $_POST['accion_usuario'] === 'pin_global') {
                if (!Security::esAdmin()) {
                    $this->flash('danger', 'SOLO EL ADMINISTRADOR PUEDE DEFINIR EL PIN GLOBAL.');
                    $this->redirect('usuarios');
                }
                $pin = trim($_POST['pin_emergencia_global'] ?? '');
                $pinConfirm = trim($_POST['pin_emergencia_global_confirm'] ?? '');
                if (!preg_match('/^\d{6}$/', $pin)) {
                    $this->flash('danger', 'EL PIN DE EMERGENCIA DEBE SER 6 DÍGITOS.');
                    $this->redirect('usuarios');
                }
                if ($pin !== $pinConfirm) {
                    $this->flash('danger', 'LOS PINES NO COINCIDEN.');
                    $this->redirect('usuarios');
                }
                if (setConfig('pin_emergencia_global', $pin)) {
                    $this->flash('success', 'PIN COMPARTIDO ACTUALIZADO. LOS OPERADORES DEBEN USAR ESTE PIN PARA RECUPERAR SU CUENTA.');
                } else {
                    $this->flash('danger', 'NO SE PUDO GUARDAR EL PIN GLOBAL.');
                }
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

            if (isset($_POST['eliminar_usuario'])) {
                $resultado = $modelo->eliminar(
                    (int)$_POST['eliminar_usuario'],
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
            'preguntas_seguridad' => getPreguntasRespuestas(),
            'usuarios'      => $modelo->listar(),
            'total_users'   => $modelo->total(),
            'activos'       => $modelo->totalActivos(),
            'pendientes'    => $modelo->totalPendientes(),
            'pin_global'    => trim(getConfig('pin_emergencia_global', '')),
            'flash'         => $this->consumeFlash(),
            'csrf'          => Security::generateToken(),
            'id_propio'     => (int)($_SESSION['id_usuario'] ?? 0),
        ]);
    }
}
