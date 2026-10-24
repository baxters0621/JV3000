<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

class Database
{
    private static ?self $instance = null;
    public ?array $usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
    public ?array $usuarioRestablecimiento = ['usuario' => 'Operador'];
    public ?array $usuarioDuplicado = null;
    public ?array $correoDuplicado = null;
    public array $consultas = [];
    public array $escrituras = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->consultas[] = ['sql' => $sql, 'params' => $params];
        if (strpos($sql, 'LOWER(usuario)') !== false) {
            return $this->usuarioDuplicado;
        }
        if (strpos($sql, 'LOWER(correo)') !== false) {
            return $this->correoDuplicado;
        }
        if (strpos($sql, 'SELECT usuario FROM usuarios') !== false) {
            return $this->usuarioRestablecimiento;
        }
        if (strpos($sql, 'FROM usuarios WHERE id_usuario = ?') !== false) {
            return $this->usuarioActual;
        }
        return null;
    }

    public function execute(string $sql, array $params = []): bool
    {
        $this->escrituras[] = ['sql' => $sql, 'params' => $params];
        return true;
    }

    public function insert(string $tabla, array $datos): int
    {
        $this->escrituras[] = ['tabla' => $tabla, 'datos' => $datos];
        return 1;
    }

    public function begin(): void {}
    public function commit(): void {}
    public function rollback(): void {}
}

$root = dirname(__DIR__);
require_once $root . '/includes/helpers.php';
require_once $root . '/core/Model.php';
require_once $root . '/models/Usuario.php';

$db = Database::getInstance();
$modelo = new Usuario();
$afirmaciones = 0;

$afirmar = static function (bool $condicion, string $mensaje) use (&$afirmaciones): void {
    $afirmaciones++;
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
};

$datosBase = [
    'id_usuario' => 25,
    'usuario' => 'Operador',
    'correo' => 'operador@jv3000.com',
    'id_rol' => '2',
    'status' => 'Activo',
];

$db->consultas = [];
$db->escrituras = [];
$resultado = $modelo->crear([
    'usuario' => 'Operador',
    'correo' => 'operador@jv3000.com',
    'password' => 'Abc12345!',
    'password_confirm' => 'Abc12345!',
    'id_rol' => '1',
]);
$afirmar(!$resultado['ok'], 'El alta de colaboradores no debe aceptar el rol Administrador.');
$afirmar($db->consultas === [] && $db->escrituras === [], 'El alta con rol Administrador debe rechazarse antes de consultar o escribir.');

$datosAltaValidos = [
    'usuario' => 'Operador_Nuevo',
    'correo' => 'nuevo@jv3000.com',
    'password' => 'Abc12345!',
    'password_confirm' => 'Abc12345!',
    'id_rol' => '2',
];
$afirmar(validarNombreUsuario('Operador_25'), 'Un nombre que inicia con letra y continúa con números debe ser válido.');
$afirmar(!validarNombreUsuario('123456'), 'Un nombre compuesto solo por números debe rechazarse.');
$afirmar(!validarNombreUsuario('2Operador'), 'El nombre de usuario debe comenzar con una letra.');
foreach (['usuario', 'correo', 'password', 'password_confirm', 'id_rol'] as $campo) {
    $db->consultas = [];
    $db->escrituras = [];
    $datosInvalidos = $datosAltaValidos;
    $datosInvalidos[$campo] = ['valor'];
    $resultado = $modelo->crear($datosInvalidos);
    $afirmar(!$resultado['ok'], "El campo $campo debe rechazar valores que no sean texto plano.");
    $afirmar($db->consultas === [] && $db->escrituras === [], "El campo $campo mal formado no debe consultar ni escribir.");
}

$db->consultas = [];
$db->escrituras = [];
$datosConEspaciosPassword = $datosAltaValidos;
$datosConEspaciosPassword['password'] = ' Abc12345! ';
$datosConEspaciosPassword['password_confirm'] = ' Abc12345! ';
$resultado = $modelo->crear($datosConEspaciosPassword);
$afirmar(!$resultado['ok'], 'El alta no debe recortar espacios para convertir una contraseña inválida en válida.');
$afirmar($db->consultas === [] && $db->escrituras === [], 'La contraseña con espacios exteriores debe rechazarse antes de consultar o escribir.');

$casosAltaInvalidos = [
    ['usuario', 'abc', 'El alta debe rechazar un usuario demasiado corto.'],
    ['usuario', '123456', 'El alta debe rechazar un usuario compuesto solo por números.'],
    ['usuario', '2Operador', 'El alta debe rechazar un usuario que no empieza con letra.'],
    ['correo', 'correo-invalido', 'El alta debe rechazar un correo con formato inválido.'],
    ['correo', str_repeat('a', 90) . '@empresa.com', 'El alta debe rechazar un correo de más de 100 caracteres.'],
    ['id_rol', '1', 'El alta debe rechazar el rol Administrador.'],
    ['id_rol', '99', 'El alta debe rechazar roles inexistentes.'],
];
foreach ($casosAltaInvalidos as [$campo, $valor, $mensaje]) {
    $db->consultas = [];
    $db->escrituras = [];
    $db->usuarioDuplicado = null;
    $db->correoDuplicado = null;
    $datosInvalidos = $datosAltaValidos;
    $datosInvalidos[$campo] = $valor;
    $resultado = $modelo->crear($datosInvalidos);
    $afirmar(!$resultado['ok'], $mensaje);
    $afirmar($db->escrituras === [], "$campo inválido no debe crear una cuenta.");
}

$db->consultas = [];
$db->escrituras = [];
$datosConConfirmacionDistinta = $datosAltaValidos;
$datosConConfirmacionDistinta['password_confirm'] = 'Abc12345?';
$resultado = $modelo->crear($datosConConfirmacionDistinta);
$afirmar(!$resultado['ok'], 'El alta debe exigir que la confirmación coincida exactamente.');
$afirmar($db->consultas === [] && $db->escrituras === [], 'Una confirmación distinta debe rechazarse antes de consultar o escribir.');

$db->consultas = [];
$db->escrituras = [];
$db->usuarioDuplicado = ['id_usuario' => 31];
$resultado = $modelo->crear($datosAltaValidos);
$afirmar(!$resultado['ok'], 'El alta debe rechazar usuario o correo ya registrados.');
$afirmar(count($db->consultas) === 1 && $db->escrituras === [], 'Un colaborador duplicado debe bloquear la inserción.');

$db->consultas = [];
$db->escrituras = [];
$db->usuarioDuplicado = null;
$db->correoDuplicado = null;
$resultado = $modelo->crear($datosAltaValidos);
$afirmar($resultado['ok'], 'Una creación válida debe completar el alta.');
$afirmar(
    $db->escrituras[0]['tabla'] === 'usuarios'
        && $db->escrituras[0]['datos']['password_check'] === generarPasswordCheck('Abc12345!'),
    'El alta válida debe guardar el usuario y verificar la contraseña exacta.'
);

$db->consultas = [];
$db->escrituras = [];
$datosAltaVentas = $datosAltaValidos;
$datosAltaVentas['usuario'] = 'Ventas_Nuevo';
$datosAltaVentas['correo'] = 'ventas@jv3000.com';
$datosAltaVentas['id_rol'] = '3';
$resultado = $modelo->crear($datosAltaVentas);
$afirmar($resultado['ok'], 'El alta válida debe aceptar también el rol Operador de Ventas.');
$afirmar($db->escrituras[0]['datos']['id_rol'] === 3, 'El alta debe guardar el rol de ventas seleccionado.');

$db->consultas = [];
$db->escrituras = [];
$db->usuarioDuplicado = null;
$resultado = $modelo->aprobar(25, 1, 1);
$afirmar(!$resultado['ok'], 'La aprobación de cuentas no debe aceptar el rol Administrador.');
$afirmar($db->consultas === [] && $db->escrituras === [], 'La aprobación con rol Administrador debe rechazarse antes de consultar o escribir.');

$db->consultas = [];
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['id_usuario' => ['25']]), 1);
$afirmar(!$resultado['ok'], 'El identificador no debe aceptar valores compuestos.');
$afirmar($db->consultas === [] && $db->escrituras === [], 'Un identificador mal formado no debe consultar ni escribir.');

$db->consultas = [];
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['usuario' => ['Operador']]), 1);
$afirmar(!$resultado['ok'], 'El nombre de usuario debe ser un valor de texto.');
$afirmar($db->consultas === [] && $db->escrituras === [], 'Un nombre mal formado no debe consultar ni escribir.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['id_rol' => '99']), 1);
$afirmar(!$resultado['ok'], 'Debe rechazarse un rol fuera de los roles permitidos.');
$afirmar($db->escrituras === [], 'Un rol inválido no debe actualizar la cuenta.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->usuarioDuplicado = null;
$db->correoDuplicado = null;
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['id_rol' => '1']), 1);
$afirmar(!$resultado['ok'], 'No debe poder asignarse el rol de Administrador a otro colaborador.');
$afirmar($db->escrituras === [], 'Una asignación del rol Administrador no debe actualizar la cuenta.');

$db->usuarioActual = ['id_rol' => 1, 'status' => 'Activo'];
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['id_usuario' => 1, 'id_rol' => '2']), 1);
$afirmar($resultado['ok'], 'La cuenta Administrador debe poder conservar su propio rol.');
$afirmar(
    $db->escrituras[0]['params'][2] === 1,
    'La cuenta Administrador debe conservar el rol actual aunque el POST envíe otro rol.'
);

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->usuarioDuplicado = null;
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['usuario' => 'abc']), 1);
$afirmar(!$resultado['ok'], 'El nombre de usuario debe respetar la longitud mínima.');
$afirmar($db->escrituras === [], 'Un nombre inválido no debe actualizar la cuenta.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->usuarioDuplicado = null;
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['usuario' => '123456']), 1);
$afirmar(!$resultado['ok'], 'La edición debe rechazar nombres compuestos solo por números.');
$afirmar($db->escrituras === [], 'Un nombre de solo números no debe actualizar la cuenta.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->usuarioDuplicado = null;
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['usuario' => '2Operador']), 1);
$afirmar(!$resultado['ok'], 'La edición debe exigir que el usuario comience con una letra.');
$afirmar($db->escrituras === [], 'Un nombre que comienza con número no debe actualizar la cuenta.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo', 'password' => 'hash', 'password_check' => 'check'];
$db->usuarioDuplicado = null;
$db->correoDuplicado = null;
$db->escrituras = [];
$_SESSION = ['id_usuario' => 25];
$resultado = $modelo->actualizarPerfil([
    'usuario' => '123456',
    'correo' => 'operador@jv3000.com',
]);
$afirmar(!$resultado['ok'], 'El perfil debe rechazar usuarios compuestos solo por números.');
$afirmar($db->escrituras === [], 'Un usuario de solo números no debe actualizar el perfil.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo', 'password' => 'hash', 'password_check' => 'check'];
$db->escrituras = [];
$resultado = $modelo->actualizarPerfil([
    'usuario' => ['123456'],
    'correo' => 'operador@jv3000.com',
]);
$afirmar(!$resultado['ok'], 'El perfil debe rechazar un usuario que no sea texto plano.');
$afirmar($db->escrituras === [], 'Un usuario mal formado no debe actualizar el perfil.');

$registroFuente = file_get_contents($root . '/login/login.php');
$afirmar(
    strpos($registroFuente, 'validarNombreUsuario($new_user)') !== false,
    'El registro público debe compartir la validación de nombre de usuario del servidor.'
);

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['correo' => '   ']), 1);
$afirmar(!$resultado['ok'], 'El correo requerido no debe poder quedar vacío.');
$afirmar($db->escrituras === [], 'Un correo vacío no debe actualizar la cuenta.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->usuarioDuplicado = null;
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['correo' => 'correo-invalido']), 1);
$afirmar(!$resultado['ok'], 'Debe rechazarse un correo con formato inválido.');
$afirmar($db->escrituras === [], 'Un correo inválido no debe actualizar la cuenta.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->usuarioDuplicado = null;
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['correo' => str_repeat('a', 90) . '@empresa.com']), 1);
$afirmar(!$resultado['ok'], 'El correo no debe superar los 100 caracteres.');
$afirmar($db->escrituras === [], 'Un correo demasiado largo no debe actualizar la cuenta.');

$db->usuarioActual = ['id_rol' => 2, 'status' => 'Activo'];
$db->usuarioDuplicado = null;
$db->correoDuplicado = ['id_usuario' => 31];
$db->consultas = [];
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['correo' => 'OPERADOR@JV3000.COM']), 1);
$afirmar(!$resultado['ok'], 'El correo duplicado debe rechazarse sin distinguir mayúsculas.');
$afirmar($db->escrituras === [], 'Un correo duplicado no debe actualizar la cuenta.');
$afirmar(
    count(array_filter($db->consultas, static function (array $consulta): bool {
        return strpos($consulta['sql'], 'LOWER(correo)') !== false;
    })) > 0,
    'La búsqueda de duplicados de correo debe ser insensible a mayúsculas.'
);

$db->usuarioActual = null;
$db->usuarioDuplicado = null;
$db->correoDuplicado = null;
$db->escrituras = [];
$resultado = $modelo->editar($datosBase, 1);
$afirmar(!$resultado['ok'], 'No debe editarse una cuenta inexistente.');
$afirmar($db->escrituras === [], 'Una cuenta inexistente no debe provocar escrituras.');

$db->usuarioRestablecimiento = ['usuario' => 'Operador'];
$db->escrituras = [];
$resultado = $modelo->resetearPassword(25, 'ClaveValida1!', 'ClaveDistinta1!');
$afirmar(!$resultado['ok'], 'El restablecimiento debe rechazar contraseñas que no coinciden.');
$afirmar($db->escrituras === [], 'Una confirmación distinta no debe actualizar la contraseña.');

$db->usuarioRestablecimiento = ['usuario' => 'Operador'];
$db->escrituras = [];
$resultado = $modelo->resetearPassword(25, 'debil', 'debil');
$afirmar(!$resultado['ok'], 'El restablecimiento debe rechazar contraseñas débiles.');
$afirmar($db->escrituras === [], 'Una contraseña débil no debe actualizar la cuenta.');

$passwordConSalto = "ClaveValida1!\n";
$db->escrituras = [];
$resultado = $modelo->resetearPassword(25, $passwordConSalto, $passwordConSalto);
$afirmar(!$resultado['ok'], 'El restablecimiento debe rechazar contraseñas con saltos de línea.');
$afirmar(
    strpos($resultado['mensaje'], 'sin saltos de línea') !== false,
    'La respuesta debe identificar el requisito del salto de línea.'
);
$afirmar($db->escrituras === [], 'Una contraseña con salto de línea no debe actualizar la cuenta.');

$passwordMuyLargo = str_repeat('aA1!', 4) . 'xYz';
$passwordMaximo = str_repeat('aA1!', 4) . 'xY';
$passwordUnicodeMaximo = str_repeat('aA1!', 4) . 'xñ';
$passwordUnicodeExcedido = str_repeat('aA1!', 4) . 'xññ';
$passwordMinimo = 'aA1!bbbb';
$passwordMinimoUnicode = 'aA1!ññññ';
$passwordConEspacio = 'Clave Valida1!';
$passwordConEspacioComoSimbolo = 'Abc12345 ';
$afirmar(mb_strlen($passwordMinimo, 'UTF-8') === 8 && validarPasswordFuerte($passwordMinimo), 'El mínimo debe aceptar exactamente 8 caracteres.');
$afirmar(
    mb_strlen($passwordMinimoUnicode, 'UTF-8') === 8 && validarPasswordFuerte($passwordMinimoUnicode),
    'El mínimo también debe contar caracteres acentuados, no bytes.'
);
$afirmar(mb_strlen($passwordMaximo, 'UTF-8') === 18 && validarPasswordFuerte($passwordMaximo), 'El límite de 18 caracteres debe aceptarse.');
$afirmar(
    mb_strlen($passwordUnicodeMaximo, 'UTF-8') === 18 && validarPasswordFuerte($passwordUnicodeMaximo),
    'El límite debe contar letras UTF-8 por caracteres, no por bytes.'
);
$afirmar(
    mb_strlen($passwordUnicodeExcedido, 'UTF-8') === 19 && !validarPasswordFuerte($passwordUnicodeExcedido),
    'Debe rechazarse una contraseña que exceda 18 caracteres UTF-8.'
);
$afirmar(!validarPasswordFuerte($passwordConEspacio), 'No se deben permitir espacios en una contraseña nueva.');
$afirmar(
    strpos(requisitosFaltantesPassword($passwordConEspacio), 'sin espacios') !== false,
    'La validación debe indicar que los espacios no están permitidos.'
);
$faltanConEspacioComoSimbolo = requisitosFaltantesPassword($passwordConEspacioComoSimbolo);
$afirmar(
    strpos($faltanConEspacioComoSimbolo, '1 símbolo') !== false
        && strpos($faltanConEspacioComoSimbolo, 'sin espacios') !== false,
    'Un espacio no debe satisfacer el requisito de símbolo y debe rechazarse.'
);
$db->escrituras = [];
$resultado = $modelo->resetearPassword(25, $passwordMuyLargo, $passwordMuyLargo);
$afirmar(!$resultado['ok'], 'El restablecimiento debe rechazar contraseñas de más de 18 caracteres.');
$afirmar(
    strpos($resultado['mensaje'], '18 caracteres') !== false,
    'El error debe informar el límite máximo de 18 caracteres.'
);
$afirmar($db->escrituras === [], 'Una contraseña sobre el límite no debe actualizar la cuenta.');

$passwordExacto = 'Clave Valida1!';
$db->escrituras = [];
$resultado = $modelo->resetearPassword(25, $passwordExacto, $passwordExacto);
$afirmar(!$resultado['ok'], 'El restablecimiento debe rechazar contraseñas con espacios.');
$afirmar(
    $db->escrituras === [],
    'Una contraseña con espacios no debe actualizar la cuenta.'
);

echo "OK validación de alta, edición y restablecimiento ({$afirmaciones} afirmaciones)\n";
