<?php

/**
 * Piloto del módulo Backup.
 *
 * Verifica el divisor de sentencias, el validador de seguridad y la generación
 * del volcado. Solo lee la base de datos: no importa ni modifica nada.
 * Ejecutar con PHP CLI desde la raíz del proyecto.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$root = dirname(__DIR__);

require_once $root . '/config/config.php';
require_once $root . '/includes/Database.php';
require_once $root . '/core/Model.php';
require_once $root . '/models/Backup.php';

Database::getInstance()->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

$fallos = 0;

/**
 * Registra el resultado de una comprobación.
 *
 * @param string $nombre Descripción breve.
 * @param bool   $ok     Resultado.
 * @return void
 */
function comprobar(string $nombre, bool $ok): void
{
    global $fallos;
    if ($ok) {
        echo "OK  {$nombre}\n";
        return;
    }
    $fallos++;
    echo "FALLO  {$nombre}\n";
}

$modelo = new Backup();

// --- Divisor de sentencias -------------------------------------------------
$sentencias = $modelo->dividirSentencias(
    "INSERT INTO t VALUES ('a;b');\nINSERT INTO t VALUES (\"c;d\");"
);
comprobar('separa por ; sin romper cadenas', count($sentencias) === 2);

$sentencias = $modelo->dividirSentencias(
    "INSERT INTO t VALUES ('it''s;ok'); -- comentario ;\nINSERT INTO t VALUES (1);"
);
comprobar('respeta comillas dobles y comentarios de línea', count($sentencias) === 2);
comprobar('conserva el punto y coma dentro del texto', str_contains($sentencias[0], "it''s;ok"));

$sentencias = $modelo->dividirSentencias("CREATE TABLE `a;b` (id INT);");
comprobar('respeta identificadores entre backticks', count($sentencias) === 1);

$sentencias = $modelo->dividirSentencias('/* bloque ; */ INSERT INTO t VALUES (1);');
comprobar('descarta comentarios de bloque', count($sentencias) === 1 && str_starts_with($sentencias[0], 'INSERT'));

// --- Validador de seguridad ------------------------------------------------
$resultado = $modelo->analizar('<?php echo 1;');
comprobar('rechaza etiquetas PHP', $resultado['ok'] === false);

$resultado = $modelo->analizar("GRANT ALL ON *.* TO 'x'@'%';");
comprobar('rechaza GRANT', $resultado['ok'] === false);

$resultado = $modelo->analizar('DELIMITER ;;');
comprobar('rechaza DELIMITER', $resultado['ok'] === false);

$resultado = $modelo->analizar('CREATE DATABASE otra_db;');
comprobar('rechaza operar sobre otra base de datos', $resultado['ok'] === false);

$resultado = $modelo->analizar('SET GLOBAL max_connections = 1;');
comprobar('rechaza cambios globales del servidor', $resultado['ok'] === false);

$resultado = $modelo->analizar('INSERT INTO tabla_fantasma (id) VALUES (1);');
comprobar('rechaza inserts en tablas inexistentes', $resultado['ok'] === false);

$resultado = $modelo->analizar("SET NAMES utf8mb4;\nINSERT INTO usuarios (id_usuario) VALUES (999999);");
comprobar('acepta un volcado de datos sobre tablas existentes', $resultado['ok'] === true);

$resultado = $modelo->analizar("\xEF\xBB\xBFSET NAMES utf8mb4;\nINSERT INTO usuarios (id_usuario) VALUES (999999);");
comprobar('acepta un BOM UTF-8 al inicio', $resultado['ok'] === true);

$resultado = $modelo->analizar("CREATE TABLE tabla_piloto (id INT);\nINSERT INTO tabla_piloto (id) VALUES (1);");
comprobar('separa estructura y datos', $resultado['ok'] === true && count($resultado['ddl']) === 1 && count($resultado['dml']) === 1);

$resultado = $modelo->analizar('DROP TABLE usuarios;');
comprobar('rechaza eliminar una tabla esencial sin recrearla', $resultado['ok'] === false);

$resultado = $modelo->analizar('ALTER TABLE usuarios ADD COLUMN prueba INT;');
comprobar('rechaza alteraciones de estructura no permitidas', $resultado['ok'] === false);

// --- Volcado ---------------------------------------------------------------
$temporal = tempnam(sys_get_temp_dir(), 'jv3000_pilot_');
if ($temporal === false) {
    comprobar('crea archivo temporal para el volcado', false);
} else {
    try {
        $modelo->volcarA($temporal);
        $contenido = (string)file_get_contents($temporal);
        comprobar('genera un CREATE TABLE por tabla', substr_count($contenido, 'CREATE TABLE') === $modelo->totalTablas());
        comprobar('incluye DROP TABLE IF EXISTS', str_contains($contenido, 'DROP TABLE IF EXISTS'));
        comprobar('incluye datos con INSERT INTO', str_contains($contenido, 'INSERT INTO'));
        comprobar('desactiva y reactiva las claves foráneas', str_contains($contenido, 'SET FOREIGN_KEY_CHECKS=0') && str_contains($contenido, 'SET FOREIGN_KEY_CHECKS=1'));
    } finally {
        @unlink($temporal);
    }
}

// --- Integridad ------------------------------------------------------------
$integridad = $modelo->verificarIntegridad();
comprobar('la base actual pasa la verificación de integridad', $integridad['ok'] === true);

echo "\n" . ($fallos === 0 ? 'PILOTO OK' : "PILOTO CON {$fallos} FALLO(S)") . "\n";
exit($fallos === 0 ? 0 : 1);
