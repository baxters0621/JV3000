<?php

/**
 * Verifica la automatización del número de control fiscal de Compras.
 *
 * Es una prueba estática: comprueba el contrato entre modelo, controlador,
 * vista y JavaScript, sin conectarse a la base de datos ni modificar datos.
 */

$raiz = dirname(__DIR__);
$modelo = file_get_contents($raiz . '/models/Compra.php');
$controlador = file_get_contents($raiz . '/controllers/ComprasController.php');
$vista = file_get_contents($raiz . '/views/compras/index.php');
$js = file_get_contents($raiz . '/assets/modules/compras/compras.js');

/**
 * Detiene la prueba con un mensaje claro cuando una condición no se cumple.
 */
function afirmarControlCompras(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

afirmarControlCompras(strpos($modelo, 'public function estadoControl(): array') !== false, 'Falta Compra::estadoControl().');
afirmarControlCompras(strpos($modelo, "REGEXP '^00-[0-9]{8}$'") !== false, 'La consulta debe aceptar solo el formato 00-########.');
afirmarControlCompras(strpos($modelo, 'MAX(CAST(SUBSTRING(nro_control, 4) AS UNSIGNED))') !== false, 'Debe tomar el mayor sufijo numérico del histórico.');
afirmarControlCompras(strpos($modelo, "'00-' . str_pad((string)\$maximo, 8, '0', STR_PAD_LEFT)") !== false, 'El último control debe formatearse con ocho dígitos.');
afirmarControlCompras(strpos($modelo, "'00-' . str_pad((string)(\$maximo + 1), 8, '0', STR_PAD_LEFT)") !== false, 'El siguiente control debe incrementarse y formatearse.');
afirmarControlCompras(strpos($modelo, "\$resultado['siguiente'] = '00-00000001';") !== false, 'Sin historial debe empezar en 00-00000001.');
afirmarControlCompras(strpos($modelo, "preg_match('/^00-\\d{8}$/', \$nro_control)") !== false, 'El servidor debe rechazar controles con otro formato.');

afirmarControlCompras(strpos($controlador, '$control_inicial = $modelo->estadoControl();') !== false, 'El listado debe calcular el control inicial.');
afirmarControlCompras(strpos($controlador, "'control_siguiente'   => \$control_inicial['siguiente']") !== false, 'La vista debe recibir el siguiente control.');
afirmarControlCompras(strpos($controlador, "'control_ultimo'      => \$control_inicial['ultimo']") !== false, 'La vista debe recibir el último control.');
afirmarControlCompras(strpos($controlador, 'public function control_estado(): void') !== false, 'Falta el endpoint compras/control_estado.');
afirmarControlCompras(strpos($controlador, 'Security::verificarPermisoCarga();') !== false, 'El endpoint debe exigir permiso de carga.');

afirmarControlCompras(strpos($vista, 'id="nroControl"') !== false, 'El campo debe tener id nroControl.');
afirmarControlCompras(strpos($vista, 'name="nro_control"') !== false, 'El campo debe conservar el nombre nro_control.');
afirmarControlCompras(strpos($vista, 'readonly aria-readonly="true"') !== false, 'El campo debe iniciar en solo lectura.');
afirmarControlCompras(strpos($vista, 'value="<?php echo htmlspecialchars($control_siguiente ??') !== false, 'El valor inicial debe venir del consecutivo del servidor.');
afirmarControlCompras(strpos($vista, 'id="btnEditarControl"') !== false, 'Falta el botón Editar número.');
afirmarControlCompras(strpos($vista, 'if (Security::esAdmin()):') !== false, 'El botón de edición debe estar reservado al administrador.');
afirmarControlCompras(strpos($vista, "this.value='00-'+v.slice(0,8)") !== false, 'El campo debe forzar el prefijo 00- y ocho dígitos.');

afirmarControlCompras(strpos($js, "url=compras/control_estado") !== false, 'El JS debe consultar el endpoint del control.');
afirmarControlCompras(strpos($js, 'function inicializarControlCompra()') !== false, 'Falta la inicialización del control en el JS.');
afirmarControlCompras(strpos($js, 'inicializarControlCompra();') !== false, 'La inicialización del control no se está invocando.');
afirmarControlCompras(strpos($js, "modalCompra.addEventListener('shown.bs.modal'") !== false, 'El consecutivo debe refrescarse al abrir el modal.');
afirmarControlCompras(strpos($js, '/^00-\\d{8}$/') !== false, 'El cliente debe validar el formato 00-########.');

echo "OK número de control automatico de Compras verificado\n";
