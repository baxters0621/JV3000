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
$db->escrituras = [];
$resultado = $modelo->editar(array_merge($datosBase, ['usuario' => 'abc']), 1);
$afirmar(!$resultado['ok'], 'El nombre de usuario debe respetar la longitud mínima.');
$afirmar($db->escrituras === [], 'Un nombre inválido no debe actualizar la cuenta.');

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

echo "OK validación de edición y restablecimiento ({$afirmaciones} afirmaciones)\n";
