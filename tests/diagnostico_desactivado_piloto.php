<?php

/**
 * Verifica que la interfaz de diagnóstico esté cerrada y el registro siga activo.
 */
$raiz = dirname(__DIR__);
$sidebar = file_get_contents($raiz . '/includes/sidebar.php');
$controlador = file_get_contents($raiz . '/controllers/DiagnosticoController.php');
$diseno = file_get_contents($raiz . '/assets/js/diseno.js');
$modelo = file_get_contents($raiz . '/models/ErrorLog.php');
$seed = file_get_contents($raiz . '/db/jv3000_portable_v6.sql');

function afirmarDiagnosticoDesactivado(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function codigoMetodoDiagnostico(string $codigo, string $metodo, string $siguiente): string
{
    $inicio = strpos($codigo, 'public function ' . $metodo . '(');
    $fin = strpos($codigo, 'public function ' . $siguiente . '(', $inicio === false ? 0 : $inicio);
    return $inicio === false || $fin === false ? '' : substr($codigo, $inicio, $fin - $inicio);
}

$metodoIndex = codigoMetodoDiagnostico($controlador, 'index', 'cliente');
$metodoCliente = codigoMetodoDiagnostico($controlador, 'cliente', 'detalle');
$metodoDetalle = codigoMetodoDiagnostico($controlador, 'detalle', 'resolver');
$metodoResolver = substr($controlador, strpos($controlador, 'public function resolver('));
$reporter = substr($diseno, strpos($diseno, 'function jvReportarErrorNavegador('));
$reporter = substr($reporter, 0, strpos($reporter, 'function jvAvisoErrorNavegador('));
$aviso = substr($diseno, strpos($diseno, 'function jvAvisoErrorNavegador('));
$aviso = substr($aviso, 0, strpos($aviso, '// Errores de ejecución'));

afirmarDiagnosticoDesactivado(strpos($sidebar, 'index.php?url=diagnostico') === false, 'El enlace Diagnóstico debe desaparecer del menú.');
afirmarDiagnosticoDesactivado(strpos($sidebar, '$ver_diagnostico') === false, 'El menú no debe exponer una condición antigua de visibilidad.');
foreach (['index', 'detalle', 'resolver'] as $metodo) {
    $codigo = $metodo === 'index' ? $metodoIndex : ($metodo === 'detalle' ? $metodoDetalle : $metodoResolver);
    afirmarDiagnosticoDesactivado($codigo !== '' && strpos($codigo, '$this->denegarAccesoDiagnostico(') !== false, 'La acción ' . $metodo . ' debe denegar el acceso a todos.');
}
afirmarDiagnosticoDesactivado(strpos($controlador, "echo 'Acceso denegado.';") !== false && strpos($controlador, "], 403)") !== false, 'Las acciones deben devolver estado HTTP 403.');
afirmarDiagnosticoDesactivado(strpos($metodoCliente, 'registrarCliente(') !== false, 'El endpoint interno cliente() debe conservar el registro.');
afirmarDiagnosticoDesactivado(strpos($reporter, 'diagnostico/cliente') !== false && strpos($reporter, 'keepalive: true') !== false, 'El reporter debe continuar enviando los errores al servidor.');
afirmarDiagnosticoDesactivado($aviso !== '' && strpos($aviso, 'jvReportarErrorNavegador(detalle)') !== false && strpos($aviso, 'Swal.fire') === false, 'El aviso debe reportar el error sin abrir un cuadro visual.');
afirmarDiagnosticoDesactivado(strpos($modelo, 'class ErrorLog') !== false && strpos($seed, 'CREATE TABLE `errores`') !== false, 'El modelo y la tabla de errores deben permanecer.');

echo "OK panel de diagnóstico desactivado; captura interna conservada\n";
