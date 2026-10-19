<?php

/**
 * Rescate del Administrador (último recurso, solo línea de comandos).
 *
 * Uso:  php tools/resetear_admin.php <usuario> [nueva_contraseña]
 *
 * La cuenta del Administrador no usa el PIN compartido: se recupera por el
 * correo electrónico registrado. Si ese correo no está disponible y se olvida
 * la contraseña, este script restablece la clave desde la propia máquina
 * servidor, sin tocar phpMyAdmin. Solo funciona por consola: exige acceso al
 * equipo (el mismo requisito que tener phpMyAdmin abierto), nunca por la web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$usuario_objetivo = trim($argv[1] ?? '');
if ($usuario_objetivo === '') {
    echo "Uso: php tools/resetear_admin.php <usuario> [nueva_contraseña]\n";
    echo "Ejemplo: php tools/resetear_admin.php Administrador\n";
    exit(1);
}

$db = Database::getInstance();
$db->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

$cuenta = $db->fetchOne(
    "SELECT id_usuario, usuario, correo, id_rol FROM usuarios WHERE LOWER(usuario) = LOWER(?) LIMIT 1",
    [$usuario_objetivo]
);

if (!$cuenta) {
    echo "No existe ninguna cuenta con el usuario indicado.\n";
    exit(1);
}

if ((int)$cuenta['id_rol'] !== 1) {
    echo "La cuenta '{$cuenta['usuario']}' no es Administrador.\n";
    echo "Los operadores recuperan su contraseña con el PIN compartido o por correo.\n";
    exit(1);
}

$password = trim($argv[2] ?? '');
if ($password === '') {
    echo 'Nueva contraseña para ' . $cuenta['usuario'] . ': ';
    $leida = fgets(STDIN);
    $password = trim($leida === false ? '' : $leida);
}

if (!validarPasswordFuerte($password)) {
    $faltantes = requisitosFaltantesPassword($password);
    echo 'CONTRASEÑA DÉBIL. FALTA: ' . strtoupper($faltantes) . ".\n";
    echo "Mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo.\n";
    exit(1);
}

$ok = $db->execute(
    "UPDATE usuarios SET password = ?, password_check = ? WHERE id_usuario = ?",
    [password_hash($password, PASSWORD_BCRYPT), generarPasswordCheck($password), (int)$cuenta['id_usuario']]
);

if (!$ok) {
    echo "No se pudo actualizar la contraseña.\n";
    exit(1);
}

registrarAuditoria('editar', "Contraseña del Administrador restablecida por consola (rescate local)", [
    'id_usuario' => (int)$cuenta['id_usuario'],
    'usuario'    => $cuenta['usuario'],
    'origen'     => 'CLI',
]);

echo "OK - contraseña del Administrador '{$cuenta['usuario']}' restablecida.\n";
echo 'Correo registrado: ' . ($cuenta['correo'] !== '' ? $cuenta['correo'] : '(vacío)') . "\n";
echo "Recuerda: el Administrador se recupera por correo, así que ese correo debe ser real y accesible.\n";
