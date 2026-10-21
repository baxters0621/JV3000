<?php

// ==========================================
// CONTROLADOR: Perfil
// ==========================================
// El usuario logueado gestiona su propio perfil.

/**
 * PerfilController: permite al usuario actualizar su perfil.
 */
class PerfilController extends Controller
{
    public function index(): void
    {
        $modelo = new Usuario();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $resultado = $modelo->actualizarPerfil($_POST);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('perfil');
        }

        $id = (int)($_SESSION['id_usuario'] ?? 0);
        $usuario = $modelo->findById($id);
        $rolesMap = [1 => 'Administrador', 2 => 'Operador de Carga', 3 => 'Operador de Ventas'];
        $rolPerfil = $rolesMap[$usuario['id_rol'] ?? 0] ?? 'Sin rol';
        $inicial = strtoupper(substr($usuario['usuario'] ?? 'U', 0, 1));

        $this->view('perfil/index', [
            'titulo'             => 'Mi Perfil | JV3000 C.A.',
            'wrapper_class'      => 'pagina-perfil',
            'css_extra'          => ['dashboard/perfil.css'],
            'js_extra'           => ['dashboard/perfil.js'],
            'usuario_data'       => $usuario,
            'rol_perfil'         => $rolPerfil,
            'inicial'            => $inicial,
            'pin_global'         => trim(getConfig('pin_emergencia_global', '')),
            'flash'              => $this->consumeFlash(),
            'csrf'               => Security::generateToken(),
        ]);
    }
}
