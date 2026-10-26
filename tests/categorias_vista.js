// ==========================================
// PRUEBA: árbol de categorías en Inventario (vista + JS + CSS + sidebar)
// ==========================================
// Ejecutar desde la raíz: node tests/categorias_vista.js
// Chequeo estático del rediseño: el árbol se abre en un modal, el formulario
// gana el selector de padre, los flashes viajan con origen y los errores de
// formulario son globales (marcarError/limpiarErrores en diseno.js).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const leer = (ruta) => fs.readFileSync(ruta, 'utf8');
const jsDiseno = leer('assets/js/diseno.js');
const jsProductos = leer('assets/modules/productos/productos.js');
const vista = leer('views/productos/index.php');
const cssProductos = leer('assets/modules/productos/productos.css');
const cssDiseno = leer('assets/css/diseno.css');
const sidebar = leer('includes/sidebar.php');

// 1. Sintaxis real de los dos JS tocados (además de node --check manual).
assert.doesNotThrow(() => new vm.Script(jsDiseno), 'diseno.js debe compilar sin errores.');
assert.doesNotThrow(() => new vm.Script(jsProductos), 'productos.js debe compilar sin errores.');

// 2. Helpers globales de error (bug latente de compras/salidas/productos).
assert.match(jsDiseno, /function marcarError\(/, 'diseno.js debe definir marcarError global.');
assert.match(jsDiseno, /function limpiarErrores\(/, 'diseno.js debe definir limpiarErrores global.');
assert.match(cssDiseno, /\.jv-error-msg/, 'diseno.css debe estilar el mensaje .jv-error-msg.');
assert.match(jsProductos, /marcarError\(/, 'productos.js debe usar marcarError.');

// 3. El inventario conserva todo el ancho y el árbol se abre desde el botón superior.
assert.match(vista, /onclick="catAbrirGestor\(\)"/, 'Debe existir el botón superior para abrir Categorías.');
assert.match(vista, /id="modalCategorias"/, 'El gestor de categorías debe mostrarse en un modal.');
assert.match(vista, /modal-dialog[^"]*cat-manager-dialog/, 'El gestor debe abrir centrado con ancho dedicado.');
assert.match(vista, /class="card-jv card-jv-table p-0"/, 'El listado de productos debe conservar su contenedor completo.');
assert.ok(!/col-lg-4|col-lg-8/.test(vista), 'La vista no debe repartir el ancho entre el árbol y los productos.');
assert.match(jsProductos, /function catAbrirGestor\(/, 'productos.js debe definir la apertura del gestor.');
assert.match(jsProductos, /catVolverAlGestor/, 'Cerrar el formulario debe devolver al gestor.');
assert.match(cssProductos, /\.cat-manager-dialog/, 'El CSS debe ajustar el modal de categorías.');
assert.match(vista, /id="arbolCategorias"/, 'La vista debe renderizar el árbol #arbolCategorias.');
assert.match(vista, /class="cat-panel/, 'La vista debe usar el panel del árbol.');
assert.match(cssProductos, /\.cat-panel \{/, 'El CSS debe estilar el panel del árbol.');
assert.match(cssProductos, /\.cat-chip-sinstock/, 'El CSS debe estilar la etiqueta SIN STOCK.');
assert.match(cssProductos, /\.cat-seleccionada/, 'El CSS debe resaltar la categoría seleccionada.');
assert.match(vista, /'sinstock' : 'oculta'/, 'La vista debe etiquetar con SIN STOCK u OCULTA las categorías inactivas.');
assert.match(vista, /'SIN STOCK' : 'OCULTA'/, 'La vista debe mostrar el motivo de la inactividad.');
assert.match(vista, /catAbrirForm\('registrar', 0, 0\)/, 'El botón nueva categoría raíz debe existir.');
assert.match(vista, /catAbrirForm\('registrar', 0, /, 'Las raíces deben permitir agregar subcategorías.');
assert.match(vista, /catAbrirForm\('editar', /, 'El árbol debe permitir editar categorías.');
assert.match(vista, /catToggleStatus\(/, 'El árbol debe permitir el cambio a mano.');
assert.match(vista, /catAlternarRama\(this\)/, 'El expansor de ramas debe estar cableado.');
assert.match(jsProductos, /function catAbrirForm\(/, 'productos.js debe definir catAbrirForm.');
assert.match(jsProductos, /function catFiltrar\(/, 'productos.js debe definir catFiltrar.');
assert.match(jsProductos, /function catErrorNombreDuplicado\(/, 'productos.js debe validar duplicados entre hermanas.');
assert.match(jsProductos, /function llenarPadresCat\(/, 'productos.js debe llenar el selector de padre.');
assert.ok(!/abrirGestorCat|nuevaCat\(|editarCat\(|catDesdeLista|catFiltrarEstado/.test(jsProductos), 'productos.js no debe conservar funciones del pop-up.');
assert.ok(!/abrirGestorCat|nuevaCat\(|editarCat\(/.test(vista), 'La vista no debe llamar funciones del pop-up.');

// 4. Formulario: selector de padre, sin estado manual y JV_CATS aplanado.
assert.match(vista, /id="cat_padre"/, 'El formulario debe tener el selector de categoría padre.');
assert.ok(!/name="status" id="cat_status"/.test(vista), 'El formulario no debe llevar estado oculto (lo maneja el stock).');
assert.match(vista, /\$categorias_plano/, 'JV_CATS debe salir del árbol aplanado.');
assert.match(vista, /name="id_padre"/, 'El formulario debe enviar id_padre al servidor.');

// 5. Filtro ?cat=N: banner, paginación y enlaces del árbol.
assert.match(vista, /id="bannerFiltroCat"/, 'Debe existir el aviso de filtro por categoría.');
assert.match(vista, /url=productos&cat=/, 'Los nodos del árbol deben filtrar con ?cat=N.');
assert.match(vista, /'&cat=' \. \$id_categoria_filtro/, 'La paginación debe conservar el filtro de categoría.');

// 6. Flashes con origen (el JS reabre el formulario exacto).
assert.match(vista, /data-origen=/, 'La vista debe publicar el origen del flash.');
assert.match(jsProductos, /origen === 'cat_crear'/, 'El JS debe reaccionar al origen cat_crear.');
assert.match(jsProductos, /origen\.indexOf\('cat_editar:'\)/, 'El JS debe reabrir la edición con su id.');
assert.match(jsProductos, /origen\.indexOf\('prod_editar:'\)/, 'El JS debe reabrir el producto editado.');
assert.match(jsProductos, /catToggleStatus|toggle_categoria: idCategoria/, 'El toggle debe enviar toggle_categoria con CSRF.');

// 7. Sidebar renombrado.
assert.match(sidebar, /<span>Categor&iacute;as<\/span>|<span>Categorías<\/span>/, 'El sidebar debe llamarse «Categorías».');
assert.ok(!/Productos · Categorías/.test(sidebar), 'El sidebar no debe conservar «Productos · Categorías».');

console.log('OK categorias_vista: gestor modal, formulario con padre, orígenes de flash y sidebar renombrado.');
