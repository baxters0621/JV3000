<?php

// ==========================================
// CONTROLADOR: Categorías
// ==========================================
// index(): renderiza y procesa POST de
// registrar / editar / toggle_status.

/**
 * CategoriasController: gestiona el módulo de categorías de inventario.
 *
 * Atiende tanto la visualización del listado como las acciones POST de
 * registrar, editar y cambiar el estado (Activo/Inactivo) de una categoría.
 * Toda la lógica de datos está delegada en el modelo Categoria.
 */
class CategoriasController extends Controller
{
    /**
     * Renderiza el listado de categorías y procesa las acciones POST.
     *
     * GET: muestra la vista con las categorías existentes.
     * POST: según 'accion_categoria' (registrar/editar) o 'toggle_status',
     * ejecuta la operación en el modelo, guarda un flash y redirige.
     *
     * @return void
     */
    public function index(): void
    {
        Security::verificarPermisoCarga();
        $modelo = new Categoria();

        // --- Acciones POST ---
        if (isset($_POST['accion_categoria'])) {
            $datos = [
                'accion'           => $_POST['accion_categoria'],
                'nombre'           => $_POST['nombre'] ?? '',
                'descripcion'      => $_POST['descripcion'] ?? '',
                'clasificacion_abc'=> $_POST['clasificacion_abc'] ?? '',
                'tipo_manejo'      => $_POST['tipo_manejo'] ?? 'normal',
                'status'           => $_POST['status'] ?? 'Activo',
                'id_categoria'     => (int)($_POST['id_categoria'] ?? 0),
            ];
            $resultado = $modelo->procesar($datos);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('categorias');
        }

        if (isset($_POST['toggle_status'])) {
            $modelo->toggleStatus((int)$_POST['toggle_status']);
            $this->flash('success', 'ESTADO DE LA CATEGORÍA CAMBIADO.');
            $this->redirect('categorias');
        }

        // Reparar códigos nulos (side-effect en GET)
        $modelo->repararCodigos();

        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        $this->view('categorias/index', [
            'titulo'       => 'Categorías | JV3000 C.A.',
            'wrapper_class'=> 'pagina-categorias',
            'css_extra'    => ['modules/categorias/categorias.css?v=6'],
            'js_extra'     => ['modules/categorias/categorias.js?v=5'],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'categorias'   => $modelo->listar(),
        ]);
    }
}
