<?php

// ==========================================
// CONTROLADOR: Productos / Inventario
// ==========================================
// index(): renderiza la vista y procesa los POST
// de toggle / baja por vencimiento / edición de
// productos y de categorías (árbol integrado).
// Toda la SQL está delegada en el Modelo.

/**
 * ProductosController: gestiona el módulo de productos/inventario.
 *
 * Renderiza el árbol de categorías (consulta para los tres roles) y el
 * listado paginado de productos filtrable por categoría. Procesa las
 * acciones POST de cambiar estado, dar de baja por vencimiento y editar
 * productos, y el alta/edición/ocultado de categorías (admin o carga).
 * Toda la lógica de datos está delegada en los modelos.
 */
class ProductosController extends Controller
{
    /**
     * Listado de productos y procesamiento de acciones POST.
     *
     * GET: árbol de categorías y lista paginada de productos, filtrable
     * con ?cat=ID (incluye la rama completa de la categoría) y paginable
     * con ?p=N. POST: "toggle" y "baja_vencido" solo para admin;
     * "editar_producto" actualiza stocks, precios y estado; si llega por
     * AJAX responde JSON y termina la ejecución, y si llega como POST normal
     * conserva el flash y la redirección. Las acciones de categoría quedan
     * en manos de admin o carga.
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
        $modeloCategoria = new Categoria();

        // --- Acciones POST ---
        $toggleProductId = (int)($_POST['toggle'] ?? 0);
        $expiredProductId = (int)($_POST['baja_vencido'] ?? 0);

        if ($toggleProductId && $esAdmin) {
            $toggleResult = $modelo->toggleStatus($toggleProductId);
            $this->flash($toggleResult['ok'] ? 'success' : 'danger', $toggleResult['mensaje'], 'prod_toggle');
            $this->redirect('productos');
        }

        if ($expiredProductId && $esAdmin) {
            $modelo->bajaVencido($expiredProductId);
            $this->flash('success', 'PRODUCTO DADO DE BAJA POR VENCIMIENTO. LOTES VENCIDOS PUESTOS EN CERO.', 'prod_baja');
            $this->redirect('productos');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'editar_producto') {
            $esFetch = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if (!$esAdmin) {
                if ($esFetch) {
                    $this->json([
                        'success' => false,
                        'message' => 'SIN PERMISOS PARA EDITAR PRODUCTOS.',
                    ], 403);
                }
                $this->redirect('productos');
            }
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
            $idProveedor = (int)($_POST['id_proveedor'] ?? 0);
            $resultado = $idProveedor > 0
                ? $modelo->editar([
                    'id_producto'     => (int)($_POST['id_producto'] ?? 0),
                    // Categoría: 0 = sin categoría; el modelo valida que exista y esté activa
                    'id_categoria'    => (int)($_POST['id_categoria'] ?? 0),
                    // Proveedor: obligatorio; el modelo valida que exista y esté activo
                    'id_proveedor'    => $idProveedor,
                    // Stocks crudos: el modelo valida que sean enteros puros en rango
                    'stock_minimo'    => trim((string)($_POST['stock_minimo'] ?? '')),
                    'stock_maximo'    => trim((string)($_POST['stock_maximo'] ?? '')),
                    'precio_venta'    => $parsePrice($_POST['precio_venta'] ?? 0),
                    'status'          => $_POST['status'] ?? 'Activo',
                    'fecha_vencimiento' => trim($_POST['fecha_vencimiento'] ?? ''),
                ])
                : ['ok' => false, 'mensaje' => 'EL PROVEEDOR ES OBLIGATORIO'];
            if ($esFetch) {
                $this->flash(
                    $resultado['ok'] ? 'success' : 'danger',
                    $resultado['mensaje'],
                    'prod_editar:' . (int)($_POST['id_producto'] ?? 0)
                );
                $this->json([
                    'success' => (bool)$resultado['ok'],
                    'message' => (string)$resultado['mensaje'],
                ], $resultado['ok'] ? 200 : 422);
            }
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje'], 'prod_editar:' . (int)($_POST['id_producto'] ?? 0));
            $this->redirect('productos');
        }

        // --- Gestión integrada de categorías (árbol dentro de Inventario) ---
        // Mismo permiso que tenía el módulo independiente: admin o carga
        // (verificarPermisoCarga ya cubrió el acceso completo a esta página).
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_categoria'])) {
            Security::verificarPermisoCarga();
            $resultado = $modeloCategoria->procesar([
                'accion'            => $_POST['accion_categoria'],
                'nombre'            => $_POST['nombre'] ?? '',
                'descripcion'       => $_POST['descripcion'] ?? '',
                'clasificacion_abc' => $_POST['clasificacion_abc'] ?? '',
                'tipo_manejo'       => $_POST['tipo_manejo'] ?? 'normal',
                'id_padre'          => (int)($_POST['id_padre'] ?? 0),
                'id_categoria'      => (int)($_POST['id_categoria'] ?? 0),
            ]);
            // El origen del flash (con el id en edición) permite al JS reabrir
            // el mismo formulario que falló, ya rellenado.
            if ($_POST['accion_categoria'] === 'editar') {
                $origen = 'cat_editar:' . (int)($_POST['id_categoria'] ?? 0);
            } else {
                $origen = 'cat_crear';
            }
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje'], $origen);
            $this->redirect('productos');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_categoria'])) {
            Security::verificarPermisoCarga();
            $resultado = $modeloCategoria->toggleStatus((int)$_POST['toggle_categoria']);
            $this->flash($resultado['ok'] ? 'success' : 'danger', $resultado['mensaje'], 'cat_toggle');
            $this->redirect('productos');
        }

        // --- Datos para la vista ---
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
        $flash = $_SESSION['flash_msg'] ?? null;
        unset($_SESSION['flash_msg']);

        // Filtro por categoría (?cat=ID): incluye la rama completa (vía ruta).
        $id_categoria_filtro = max(0, (int)($_GET['cat'] ?? 0));
        $categoria_filtro = null;
        if ($id_categoria_filtro > 0) {
            $categoria_filtro = $modeloCategoria->fila($id_categoria_filtro);
            if ($categoria_filtro === null) $id_categoria_filtro = 0;
        }

        // Árbol de categorías: consulta para los tres roles (solo admin o
        // carga ven los botones de escritura, decidido en la vista).
        // Antes de listar, se aplica la red de seguridad: reparación de
        // códigos y jerarquía heredados y sincronización de estados por
        // stock, para que el árbol siempre refleje la realidad.
        $modeloCategoria->repararCodigos();
        $modeloCategoria->repararJerarquia();
        $modeloCategoria->sincronizarPorStock();
        $categorias = $modeloCategoria->listarArbol();

        // Proveedores activos para el select obligatorio del modal de edición.
        $proveedores = array_values(array_filter(
            (new Proveedor())->listar(),
            static fn(array $proveedor): bool => ($proveedor['status'] ?? '') === 'Activo'
        ));

        $this->view('productos/index', [
            'titulo'       => 'Inventario | JV3000 C.A.',
            'wrapper_class' => 'pagina-productos',
            'css_extra'    => ['modules/productos/productos.css'],
            'js_extra'     => ['modules/productos/productos.js'],
            'csrf'         => Security::generateToken(),
            'flash'        => $flash,
            'esAdmin'      => $esAdmin,
            'productos'    => $modelo->listar($registros_por_pagina, $offset, $id_categoria_filtro),
            'total_registros'  => $modelo->totalRegistros($id_categoria_filtro),
            'total_paginas'    => max(1, (int)ceil($modelo->totalRegistros($id_categoria_filtro) / $registros_por_pagina)),
            'pagina_actual'    => $pagina_actual,
            'offset'           => $offset,
            'registros_por_pagina' => $registros_por_pagina,
            'categorias'        => $categorias,
            'proveedores'       => $proveedores,
            'id_categoria_filtro' => $id_categoria_filtro,
            'categoria_filtro'    => $categoria_filtro,
        ]);
    }

    /**
     * Descarta un producto para venta desde la alarma de stock (solo admin).
     *
     * Exige POST y XHR. El producto pasa a Inactivo y se guarda su id en
     * `configuracion.productos_descartados` para que no vuelva a alarmar ni
     * se reactive automáticamente.
     *
     * @return void
     */
    public function descartar(): void
    {
        if (!Security::esAdmin()) {
            $this->json(['success' => false, 'message' => 'SIN PERMISOS.'], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'MÉTODO NO PERMITIDO.'], 405);
        }
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            $this->json(['success' => false, 'message' => 'PETICIÓN NO VÁLIDA.'], 403);
        }

        $idProducto = $this->postInt('id_producto', 1);
        if ($idProducto === null) {
            $this->json(['success' => false, 'message' => 'PRODUCTO INVÁLIDO.'], 422);
        }

        $resultado = (new Producto())->descartar($idProducto);
        $this->json([
            'success' => (bool)$resultado['ok'],
            'message' => (string)$resultado['mensaje'],
        ], $resultado['ok'] ? 200 : 422);
    }
}
