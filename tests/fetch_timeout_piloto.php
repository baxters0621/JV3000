<?php

/**
 * Verifica los contratos JSON, el tope de espera y las barreras del seed temporal.
 */
$raiz = dirname(__DIR__);
$productos_controller = file_get_contents($raiz . '/controllers/ProductosController.php');
$compras_controller = file_get_contents($raiz . '/controllers/ComprasController.php');
$salidas_controller = file_get_contents($raiz . '/controllers/SalidasController.php');
$nota_controller = file_get_contents($raiz . '/controllers/NotaEntregaController.php');
$solicitudes_controller = file_get_contents($raiz . '/controllers/SolicitudesController.php');
$productos_js = file_get_contents($raiz . '/assets/modules/productos/productos.js');
$compras_js = file_get_contents($raiz . '/assets/modules/compras/compras.js');
$salidas_js = file_get_contents($raiz . '/assets/modules/salidas/salidas.js');
$nota_js = file_get_contents($raiz . '/assets/modules/nota_entrega/nota_entrega.js');
$nota_view = file_get_contents($raiz . '/views/nota_entrega/nota.php');
$controlador_base = file_get_contents($raiz . '/core/Controller.php');
$seguridad = file_get_contents($raiz . '/includes/Security.php');
$inicio = file_get_contents($raiz . '/init.php');
$seed = file_get_contents($raiz . '/seed_pruebas.php');

function afirmarTimeoutFetch(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarTimeoutFetch(
    strpos($productos_controller, "'success' => (bool)\$resultado['ok']") !== false
    && strpos($productos_controller, "'message' => (string)\$resultado['mensaje']") !== false
    && strpos($productos_controller, '? 200 : 422') !== false,
    'La edición AJAX de productos debe devolver JSON con estado HTTP explícito.'
);
afirmarTimeoutFetch(strpos($controlador_base, 'echo json_encode($data);' . "\n        exit;") !== false, 'La respuesta JSON MVC debe terminar la ejecución.');
foreach ([$compras_controller, $salidas_controller, $nota_controller, $solicitudes_controller, $controlador_base, $seguridad, $inicio] as $controlador) {
    afirmarTimeoutFetch(
        strpos($controlador, "'success' =>") !== false && strpos($controlador, "'message' =>") !== false,
        'Una respuesta AJAX no incluye success/message.'
    );
}
afirmarTimeoutFetch(
    strpos($seguridad, 'http_response_code(401);') !== false
    && strpos($seguridad, 'LA SESIÓN NO ESTÁ ACTIVA. INICIE SESIÓN NUEVAMENTE.') !== false,
    'Una sesión vencida debe devolver JSON con estado HTTP 401 a las peticiones AJAX.'
);
foreach ([$productos_js, $compras_js, $salidas_js] as $javascript) {
    afirmarTimeoutFetch(strpos($javascript, 'jvFetchConTope(') !== false, 'Una petición AJAX operativa sigue sin tope de espera.');
    afirmarTimeoutFetch(strpos($javascript, 'ms: 12000') !== false, 'Los módulos deben usar el tope de espera de 12 segundos.');
    afirmarTimeoutFetch(strpos($javascript, 'error.jvTope') !== false || strpos($javascript, 'e.jvTope') !== false, 'El módulo no informa de forma clara el timeout.');
}
afirmarTimeoutFetch(
    strpos($salidas_controller, "'redirect_url' => \$redirectUrl") !== false
    && strpos($nota_js, 'jvFetchConTope(form.action') !== false
    && strpos($nota_js, 'ms: 12000') !== false
    && strpos($nota_js, 'error.jvTope') !== false
    && strpos($nota_js, 'resultado = await response.json()') !== false
    && strpos($nota_view, 'assets/js/diseno.js') !== false,
    'La confirmación final de venta debe usar AJAX con timeout, respuesta JSON y redirección explícita.'
);
afirmarTimeoutFetch(strpos($productos_js, 'restaurarBoton();') !== false, 'La edición de producto no restaura el botón al terminar o fallar.');
afirmarTimeoutFetch(strpos($compras_js, 'btn.disabled = productos.length === 0;') !== false, 'El formulario de compra no libera el botón tras la respuesta.');
afirmarTimeoutFetch(!preg_match('/\bfetch\s*\(/', $productos_js . $compras_js . $salidas_js . $nota_js), 'Quedó un fetch sin el helper de timeout en los módulos revisados.');

afirmarTimeoutFetch(strpos($seed, "Security::esAdmin()") !== false, 'El seed debe exigir rol Administrador.');
afirmarTimeoutFetch(strpos($seed, "['localhost', '127.0.0.1', '[::1]', '::1']") !== false, 'El seed debe limitarse a localhost.');
afirmarTimeoutFetch(strpos($seed, "DB_NAME !== 'jv3000_db'") !== false, 'El seed debe rechazar bases distintas a la local de desarrollo.');
afirmarTimeoutFetch(strpos($seed, '$pesos = [3, 2, 7, 6, 5, 4, 3, 2];') !== false, 'El seed debe generar el dígito verificador de los RIF.');
afirmarTimeoutFetch(strpos($seed, 'name="csrf_token"') !== false, 'El seed debe incluir protección CSRF.');
afirmarTimeoutFetch(strpos($seed, '$confirmacion_requerida') !== false, 'El seed debe exigir confirmación explícita.');
afirmarTimeoutFetch(
    substr_count($seed, '$indice <= 5') > 0
    && substr_count($seed, '$indice <= 10') > 0
    && substr_count($seed, '$indice <= 100') > 0
    && strpos($seed, "'stock_actual' => 50") !== false
    && strpos($seed, "'cantidad_restante' => 50") !== false,
    'El seed no genera las cantidades solicitadas o deja desalineado el stock.'
);
afirmarTimeoutFetch(
    !preg_match('/\b(?:DELETE\s+FROM|TRUNCATE|DROP\s+TABLE)\b/i', $seed),
    'El seed temporal no debe borrar tablas ni registros.'
);

echo "OK timeout de AJAX y barreras de seguridad del seed verificados\n";
