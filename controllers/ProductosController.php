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
        Security::verificarPermisoCarga();
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
            $parsePrice = static function ($value): float {
                $text = trim((string)$value);
                if (str_contains($text, ',') && str_contains($text, '.')) {
                    $text = str_replace('.', '', $text);
                    $text = str_replace(',', '.', $text);
                } elseif (str_contains($text, ',')) {
                    $text = str_replace(',', '.', $text);
                } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $text)) {
                    $text = str_replace('.', '', $text);
                }
                return (float)$text;
            };
            $resultado = $modelo->editar([
                'id_producto'     => (int)($_POST['id_producto'] ?? 0),
                'stock_minimo'    => (int)($_POST['stock_minimo'] ?? 5),
                'stock_maximo'    => (int)($_POST['stock_maximo'] ?? 0),
                'precio_venta'    => $parsePrice($_POST['precio_venta'] ?? 0),
                'precio_costo'    => $parsePrice($_POST['precio_costo'] ?? 0),
                'status'          => $_POST['status'] ?? 'Activo',
                'fecha_vencimiento' => !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
                'id_proveedor'    => (int)($_POST['id_proveedor'] ?? 0),
            ]);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje']);
            $this->redirect('productos');
        }

        // --- Datos para la vista ---
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        $this->view('productos/index', [
            'titulo'       => 'Inventario | JV3000 C.A.',
            'wrapper_class' => 'pagina-productos',
            'css_extra'    => ['modules/productos/productos.css?v=12'],
            'js_extra'     => ['modules/productos/productos.js?v=7'],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'esAdmin'      => $esAdmin,
            'productos'    => $modelo->listar($registros_por_pagina, $offset),
            'proveedores_list' => $modelo->proveedoresActivos(),
            'total_registros'  => $modelo->totalRegistros(),
            'total_paginas'    => max(1, (int)ceil($modelo->totalRegistros() / $registros_por_pagina)),
            'pagina_actual'    => $pagina_actual,
            'offset'           => $offset,
            'registros_por_pagina' => $registros_por_pagina,
        ]);
    }
}
