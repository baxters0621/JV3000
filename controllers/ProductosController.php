<?php

// ==========================================
// CONTROLADOR: Productos / Inventario
// ==========================================
// index(): renderiza la vista y procesa los POST
// de toggle / baja por vencimiento / edición.
// Toda la SQL está delegada en el Modelo.

/**
 * ProductosController: gestiona el módulo de productos/inventario.
 *
 * Renderiza el listado paginado de productos y procesa las acciones POST
 * de cambiar estado (toggle), dar de baja por vencimiento y editar.
 * Toda la lógica de datos está delegada en el modelo Producto.
 */
class ProductosController extends Controller
{
    /**
     * Listado de productos y procesamiento de acciones POST.
     *
     * GET: lista paginada de productos (p / producto / alerta).
     * POST: "toggle" y "baja_vencido" solo para admin; "editar_producto"
     * actualiza stock mínimo/máximo, precios, estado y proveedor.
     * Todas las acciones terminan con flash y redirección.
     *
     * @return void
     */
    public function index(): void
    {
        // El inventario es de CONSULTA para los tres roles; toda ESCRITURA
        // (editar, desactivar, dar de baja) exige Administrador mas abajo.
        $esAdmin = Security::esAdmin();

        $registros_por_pagina = (isset($_GET['producto']) || isset($_GET['alerta'])) ? 1000 : 30;
        $pagina_actual = max(1, (int)($_GET['p'] ?? 1));

        $modelo = new Producto();

        // --- Acciones POST ---
        $toggleProductId = (int)($_POST['toggle'] ?? 0);
        $expiredProductId = (int)($_POST['baja_vencido'] ?? 0);

        if ($toggleProductId && $esAdmin) {
            $toggleResult = $modelo->toggleStatus($toggleProductId);
            $this->flash($toggleResult['ok'] ? 'success' : 'danger', $toggleResult['mensaje']);
            $this->redirect('productos');
        }

        if ($expiredProductId && $esAdmin) {
            $modelo->bajaVencido($expiredProductId);
            $this->flash('success', 'PRODUCTO DADO DE BAJA POR VENCIMIENTO. LOTES VENCIDOS PUESTOS EN CERO.');
            $this->redirect('productos');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar_producto' && $esAdmin) {
            $parsePrice = static function ($value): string {
                $text = trim((string)$value);
                if ($text === '' || preg_match('/[^0-9.,]/', $text)) return $text;
                if (str_contains($text, ',') && str_contains($text, '.')) {
                    $text = str_replace('.', '', $text);
                    $text = str_replace(',', '.', $text);
                } elseif (str_contains($text, ',')) {
                    $text = str_replace(',', '.', $text);
                } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $text)) {
                    $text = str_replace('.', '', $text);
                }
                return is_numeric($text) ? number_format((float)$text, 2, '.', '') : $text;
            };
            $resultado = $modelo->editar([
                'id_producto'     => (int)($_POST['id_producto'] ?? 0),
                // Stocks crudos: el modelo valida que sean enteros puros en rango
                'stock_minimo'    => trim((string)($_POST['stock_minimo'] ?? '')),
                'stock_maximo'    => trim((string)($_POST['stock_maximo'] ?? '')),
                'precio_venta'    => $parsePrice($_POST['precio_venta'] ?? 0),
                'status'          => $_POST['status'] ?? 'Activo',
                'fecha_vencimiento' => trim($_POST['fecha_vencimiento'] ?? ''),
            ]);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('productos');
        }

        // --- Gestión integrada de categorías (pop-up dentro de Inventario) ---
        // Mismo permiso que tenía el módulo independiente: admin o carga
        // (verificarPermisoCarga ya cubrió el acceso completo a esta página).
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_categoria'])) {
            Security::verificarPermisoCarga();
            $resultado = (new Categoria())->procesar([
                'accion'            => $_POST['accion_categoria'],
                'nombre'            => $_POST['nombre'] ?? '',
                'descripcion'       => $_POST['descripcion'] ?? '',
                'clasificacion_abc' => $_POST['clasificacion_abc'] ?? '',
                'tipo_manejo'       => $_POST['tipo_manejo'] ?? 'normal',
                'status'            => $_POST['status'] ?? 'Activo',
                'id_categoria'      => (int)($_POST['id_categoria'] ?? 0),
            ]);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('productos');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_categoria'])) {
            Security::verificarPermisoCarga();
            (new Categoria())->toggleStatus((int)$_POST['toggle_categoria']);
            $this->flash('success', 'ESTADO DE LA CATEGORÍA CAMBIADO.');
            $this->redirect('productos');
        }

        // --- Datos para la vista ---
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        // Gestión integrada de categorías: solo admin o carga la administran.
        // El inventario sigue siendo de consulta para los tres roles, así que
        // al rol de ventas simplemente no se le envían datos del gestor.
        $categorias_gestion = [];
        if ($esAdmin || (int)$_SESSION['id_rol'] === 2) {
            $categoriaModelo = new Categoria();
            $categoriaModelo->repararCodigos();
            $categorias_gestion = $categoriaModelo->listar();
        }

        $this->view('productos/index', [
            'titulo'       => 'Inventario | JV3000 C.A.',
            'wrapper_class' => 'pagina-productos',
            'css_extra'    => ['modules/productos/productos.css?v=21'],
            'js_extra'     => ['modules/productos/productos.js?v=16'],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'esAdmin'      => $esAdmin,
            'productos'    => $modelo->listar($registros_por_pagina, $offset),
            'total_registros'  => $modelo->totalRegistros(),
            'total_paginas'    => max(1, (int)ceil($modelo->totalRegistros() / $registros_por_pagina)),
            'pagina_actual'    => $pagina_actual,
            'offset'           => $offset,
            'registros_por_pagina' => $registros_por_pagina,
            'categorias_gestion' => $categorias_gestion,
        ]);
    }
}
