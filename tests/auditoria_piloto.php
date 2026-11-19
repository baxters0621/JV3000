<?php

/**
 * Piloto estático del módulo de Auditoría.
 *
 * Comprueba el contrato entre el esquema, el modelo, el helper, el
 * controlador, la vista, los estilos y el sidebar. No modifica la base
 * de datos.
 *
 * Ejecutar con PHP CLI desde la raíz del proyecto:
 *   C:\xampp\php\php.exe tests\auditoria_piloto.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$raiz = dirname(__DIR__);

/**
 * Lee un archivo del proyecto y detiene la prueba si no existe.
 */
function leerAuditoria(string $ruta): string
{
    if (!is_file($ruta)) {
        throw new RuntimeException('No existe el archivo: ' . $ruta);
    }
    return (string)file_get_contents($ruta);
}

/**
 * Detiene la prueba con un mensaje claro cuando una condición no se cumple.
 */
function afirmarAuditoria(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$modelo = leerAuditoria($raiz . '/models/Auditoria.php');
$helper = leerAuditoria($raiz . '/includes/helpers.php');
$controlador = leerAuditoria($raiz . '/controllers/AuditoriaController.php');
$vista = leerAuditoria($raiz . '/views/auditoria/index.php');
$css = leerAuditoria($raiz . '/assets/modules/auditoria/auditoria.css');
$sidebar = leerAuditoria($raiz . '/includes/sidebar.php');
$backup = leerAuditoria($raiz . '/controllers/BackupController.php');
$csv = leerAuditoria($raiz . '/includes/ajax/exportar_csv.php');
$migracion = str_replace('`', '', leerAuditoria($raiz . '/db/migraciones/2026_11_19_logs_auditoria.sql'));
$seed = leerAuditoria($raiz . '/db/jv3000_portable_v6.sql');

// Esquema y migración
afirmarAuditoria(strpos($migracion, 'CREATE TABLE IF NOT EXISTS logs_auditoria') !== false, 'La migración no crea logs_auditoria.');
afirmarAuditoria(strpos($migracion, 'usuario_nombre varchar(100)') !== false, 'usuario_nombre debe ser varchar(100).');
afirmarAuditoria(strpos($migracion, 'modulo varchar(50)') !== false, 'Falta la columna modulo.');
afirmarAuditoria(strpos($migracion, 'descripcion text') !== false, 'Falta la columna descripcion.');
afirmarAuditoria(strpos($migracion, 'FOREIGN KEY (id_usuario)') !== false, 'Falta la clave foránea de usuarios.');
afirmarAuditoria(strpos($migracion, 'ON DELETE SET NULL') !== false, 'La FK debe conservar el evento al borrar el usuario.');
afirmarAuditoria(strpos($migracion, 'INSERT INTO logs_auditoria') !== false, 'La migración no copia el histórico.');
afirmarAuditoria(strpos($seed, 'CREATE TABLE `logs_auditoria`') !== false, 'El seed no incluye logs_auditoria.');

// Modelo y helper
afirmarAuditoria(strpos($modelo, 'public static function registrar(') !== false, 'Falta Auditoria::registrar().');
afirmarAuditoria(strpos($modelo, 'INSERT INTO logs_auditoria') !== false, 'El registro no inserta en logs_auditoria.');
afirmarAuditoria(strpos($modelo, 'mb_strtoupper(trim(') !== false, 'La acción debe normalizarse a mayúsculas.');
afirmarAuditoria(strpos($modelo, "FROM usuarios") !== false, 'El modelo debe validar el usuario contra usuarios.');
afirmarAuditoria(strpos($modelo, 'function resumen(): array') !== false, 'Falta el resumen de tarjetas.');
afirmarAuditoria(strpos($modelo, 'function listarEventos(') !== false, 'Falta el listado paginado.');
afirmarAuditoria(strpos($modelo, 'a.fecha_hora >= ?') !== false, 'El modelo debe filtrar desde fecha_hora.');
afirmarAuditoria(strpos($modelo, 'a.fecha_hora <= ?') !== false, 'El modelo debe filtrar hasta fecha_hora.');
afirmarAuditoria(strpos($modelo, "' 00:00:00'") !== false && strpos($modelo, "' 23:59:59'") !== false, 'Las fechas deben cubrir el día completo.');
afirmarAuditoria(strpos($modelo, 'function modulosDisponibles(): array') !== false, 'Faltan los módulos para el filtro.');
afirmarAuditoria(strpos($modelo, 'function accionesDisponibles(): array') !== false, 'Faltan las acciones para el filtro.');

afirmarAuditoria(strpos($helper, 'function auditar(') !== false, 'Falta la función global auditar().');
afirmarAuditoria(strpos($helper, 'Auditoria::registrar(') !== false, 'auditar() no delega en el modelo.');
afirmarAuditoria(strpos($helper, 'function jv_modulo_auditoria()') !== false, 'Falta la inferencia de módulo.');
afirmarAuditoria(strpos($helper, "'Backup'") !== false, 'La inferencia no contempla Backup.');
afirmarAuditoria(strpos($helper, 'function registrarAuditoria(') !== false, 'Se perdió el envoltorio compatible registrarAuditoria().');

// Controlador
afirmarAuditoria(strpos($controlador, "class AuditoriaController extends Controller") !== false, 'Falta el controlador de Auditoría.');
afirmarAuditoria(strpos($controlador, 'Security::esAdmin()') !== false, 'El controlador no exige administrador.');
afirmarAuditoria(strpos($controlador, "http_response_code(403)") !== false, 'El controlador no responde 403.');
afirmarAuditoria(strpos($controlador, "\$limit = 50;") !== false, 'La paginación debe ser de 50.');
afirmarAuditoria(strpos($controlador, "'buscar'") !== false, 'Falta el filtro de búsqueda.');
afirmarAuditoria(strpos($controlador, "'modulo'") !== false, 'Falta el filtro por módulo.');
afirmarAuditoria(strpos($controlador, "'accion'") !== false, 'Falta el filtro por acción.');
afirmarAuditoria(strpos($controlador, "'fecha_desde'") !== false, 'Falta el filtro de fecha desde.');
afirmarAuditoria(strpos($controlador, "'fecha_hasta'") !== false, 'Falta el filtro de fecha hasta.');
afirmarAuditoria(strpos($controlador, 'private function parametroFecha(') !== false, 'Falta la validación estricta de fechas.');
afirmarAuditoria(strpos($controlador, "'LOGIN' => 'Inicio de sesión'") !== false, 'Falta la etiqueta amigable de LOGIN.');
afirmarAuditoria(strpos($controlador, "'DESACTIVADO_POR_STOCK' => 'Desactivado (Sin stock)'") !== false, 'Falta la etiqueta de desactivado por stock.');
afirmarAuditoria(strpos($controlador, "'TASA BCV MANUAL RETIRADA' => 'Tasa BCV (Retirada)'") !== false, 'Falta la etiqueta de tasa BCV retirada.');
afirmarAuditoria(strpos($controlador, 'private function etiquetaAccion(') !== false, 'Falta el fallback de etiquetas de acción.');
afirmarAuditoria(strpos($controlador, "'acciones_etiquetas' => \$etiquetas_acciones") !== false, 'El controlador debe pasar las etiquetas a la vista.');

// Vista y estilos
afirmarAuditoria(strpos($vista, 'Registro de Auditoría') !== false, 'Falta el título de la vista.');
afirmarAuditoria(strpos($vista, 'Trazabilidad e historial de acciones del sistema') !== false, 'Falta el subtítulo de la vista.');
afirmarAuditoria(strpos($vista, 'Total de eventos') !== false, 'Falta la tarjeta Total de eventos.');
afirmarAuditoria(strpos($vista, 'Eventos hoy') !== false, 'Falta la tarjeta Eventos hoy.');
afirmarAuditoria(strpos($vista, 'Último inicio de sesión') !== false, 'Falta la tarjeta de último inicio de sesión.');
afirmarAuditoria(strpos($vista, 'table table-hover table-striped align-middle') !== false, 'La tabla no usa las clases solicitadas.');
afirmarAuditoria(strpos($vista, "'LOGIN', 'LOGOUT' => 'bg-info text-dark'") !== false, 'Badge incorrecto para LOGIN/LOGOUT.');
afirmarAuditoria(strpos($vista, "'CREAR' => 'bg-success'") !== false, 'Badge incorrecto para CREAR.');
afirmarAuditoria(strpos($vista, "'EDITAR' => 'bg-warning text-dark'") !== false, 'Badge incorrecto para EDITAR.');
afirmarAuditoria(strpos($vista, "'ANULAR', 'ELIMINAR' => 'bg-danger'") !== false, 'Badge incorrecto para ANULAR/ELIMINAR.');
afirmarAuditoria(strpos($vista, "'BACKUP' => 'bg-primary'") !== false, 'Badge incorrecto para BACKUP.');
afirmarAuditoria(strpos($vista, 'auditoria-paginacion') !== false, 'Falta la paginación.');
afirmarAuditoria(strpos($vista, 'name="fecha_desde"') !== false, 'Falta el input Desde.');
afirmarAuditoria(strpos($vista, 'name="fecha_hasta"') !== false, 'Falta el input Hasta.');
afirmarAuditoria(strpos($vista, 'type="date"') !== false, 'Los filtros de fecha deben ser type=date.');
afirmarAuditoria(strpos($vista, '$acciones_etiquetas[$accion] ?? $accion') !== false, 'El filtro debe mostrar la etiqueta amigable.');
afirmarAuditoria(strpos($vista, 'value="<?php echo htmlspecialchars($accion') !== false, 'El value del filtro debe seguir siendo técnico.');
afirmarAuditoria(strpos($css, '#cbd5e1') !== false, 'El select debe tener borde gris claro.');
afirmarAuditoria(strpos($css, '#3b82f6') !== false, 'El foco del select debe ser azul, no naranja.');
afirmarAuditoria(strpos($css, '.auditoria-select:focus') !== false, 'Falta la regla de foco del select.');
afirmarAuditoria(strpos($vista, 'btn btn-dark auditoria-btn-filtrar') !== false, 'El botón Filtrar debe ser oscuro, no naranja.');
afirmarAuditoria(strpos($css, 'auditoria-btn-filtrar') !== false, 'Falta el estilo del botón Filtrar.');
afirmarAuditoria(strpos($css, 'auditoria-fecha') !== false, 'Falta el estilo de los inputs de fecha.');
afirmarAuditoria(strpos($vista, 'btn-editar') === false && strpos($vista, 'btn-eliminar') === false, 'La auditoría debe ser de solo lectura.');
afirmarAuditoria(strpos($css, '#f8f9fa') !== false, 'Falta el fondo corporativo.');
afirmarAuditoria(strpos($css, '#1f2937') !== false, 'Falta la cabecera navy de la tabla.');

// Integración
afirmarAuditoria(strpos($sidebar, 'url=auditoria') !== false, 'Falta el enlace en el sidebar.');
afirmarAuditoria(strpos($sidebar, '$ver_auditoria  = $es_admin') !== false, 'El enlace debe ser exclusivo del administrador.');
afirmarAuditoria(strpos($backup, "auditar('BACKUP', 'Backup'") !== false, 'Backup no registra exportación/importación.');
afirmarAuditoria(strpos($csv, 'FROM logs_auditoria') !== false, 'La exportación CSV no usa logs_auditoria.');

echo "OK módulo de auditoría verificado\n";
