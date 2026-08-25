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
        // 1. CONTROL DE ACCESO: solo pueden entrar quienes tienen permiso
        //    de carga (Admin o Operador de Carga). Si no, se redirige.
        Security::verificarPermisoCarga();

        // 2. Crear el modelo que consultará la base de datos.
        $modelo = new Categoria();

        // ==========================================
        // BLOQUE POST: cuando llega un formulario
        // ==========================================

        // --- Acción "registrar" o "editar" (vienen del modal) ---
        if (isset($_POST['accion_categoria'])) {
            // Juntar los datos del formulario en un solo arreglo para
            // entregárselo ordenado al modelo. Los valores que no vienen
            // reciben un valor por defecto (?? es "si no existe, usa esto").
            $categoryFormData = [
                'accion'           => $_POST['accion_categoria'],
                'nombre'           => $_POST['nombre'] ?? '',
                'descripcion'      => $_POST['descripcion'] ?? '',
                'clasificacion_abc' => $_POST['clasificacion_abc'] ?? '',
                'tipo_manejo'      => $_POST['tipo_manejo'] ?? 'normal',
                'status'           => $_POST['status'] ?? 'Activo',
                'id_categoria'     => (int)($_POST['id_categoria'] ?? 0),
            ];
            // El modelo hace la validación y guarda/actualiza en la BD.
            $resultado = $modelo->procesar($categoryFormData);
            // Guardar un mensaje de resultado ("flash") que se mostrará
            // una sola vez en la siguiente página.
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            // Volver al listado para que el usuario vea el mensaje.
            $this->redirect('categorias');
        }

        // --- Acción "toggle_status": activar / desactivar una categoría ---
        if (isset($_POST['toggle_status'])) {
            $modelo->toggleStatus((int)$_POST['toggle_status']);
            $this->flash('success', 'ESTADO DE LA CATEGORÍA CAMBIADO.');
            $this->redirect('categorias');
        }

        // ==========================================
        // BLOQUE GET: cuando solo se navega a la página
        // ==========================================

        // Reparar códigos nulos (side-effect en GET): si alguna categoría
        // quedó sin código CAT-XXX, se le asigna uno.
        $modelo->repararCodigos();

        // Leer y borrar el mensaje flash pendiente (si existe).
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        // Entregar los datos a la vista dentro del layout principal.
        $this->view('categorias/index', [
            'titulo'       => 'Categorías | JV3000 C.A.',
            'wrapper_class' => 'pagina-categorias',
            'css_extra'    => ['modules/categorias/categorias.css?v=10'],
            'js_extra'     => ['modules/categorias/categorias.js?v=7'],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'categorias'   => $modelo->listar(),
        ]);
    }
}
