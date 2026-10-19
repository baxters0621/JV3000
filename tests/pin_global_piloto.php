<?php

/**
 * Regresión: PIN de emergencia único y compartido.
 *
 * El PIN dejó de ser un dato por usuario: ahora es una sola clave que el
 * Administrador define en la tabla `configuracion` y que sirve para todos los
 * Operadores (Carga y Ventas). La cuenta del Administrador queda fuera del PIN
 * y solo se recupera por correo. Ejecutar con PHP CLI; sin base de datos real.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$root = dirname(__DIR__);

/**
 * Base de datos simulada: no hay conexión real, solo se registran las
 * consultas y escrituras para poder afirmar sobre ellas.
 */
class Database
{
    private static ?Database $instance = null;
    private static ?Database $instancia = null;

    /** Respuestas que devolverá fetchOne(), en orden. */
    public array $respuestas = [];

    /** Escrituras registradas: ['sql' => ..., 'params' => []] */
    public array $escrituras = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function fetchOne(string $sql, array $params = [])
    {
        return $this->respuestas ? array_shift($this->respuestas) : null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return [];
    }

    public function execute(string $sql, array $params = []): bool
    {
        $this->escrituras[] = ['sql' => $sql, 'params' => $params];
        return true;
    }

    public function insert(string $tabla, array $datos): bool
    {
        $this->escrituras[] = ['sql' => 'INSERT ' . $tabla, 'params' => $datos];
        return true;
    }
}

require_once $root . '/includes/helpers.php';
require_once $root . '/core/Model.php';
require_once $root . '/models/Usuario.php';

$afirmaciones = 0;

function afirmar(bool $condicion, string $mensaje): void
{
    global $afirmaciones;
    $afirmaciones++;
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

/** Devuelve la última escritura cuya SQL empieza por el prefijo indicado. */
function escrituraPor(string $prefijo): array
{
    global $db;
    foreach (array_reverse($db->escrituras) as $escritura) {
        if (str_starts_with($escritura['sql'], $prefijo)) {
            return $escritura;
        }
    }
    throw new RuntimeException('No se registró ninguna escritura que empiece por: ' . $prefijo);
}

$db = Database::getInstance();

// -----------------------------------------------
// 1) getConfig / setConfig: escritura y frescura del cache
// -----------------------------------------------
$db->respuestas = [['valor' => '111111']];
afirmar(getConfig('pin_emergencia_global') === '111111', 'getConfig debe devolver el valor de la tabla configuracion.');

// Segunda lectura: debe salir del cache sin volver a consultar (no hay respuestas).
afirmar(getConfig('pin_emergencia_global') === '111111', 'getConfig debe servir del cache la segunda lectura.');

// Al escribir, el cache se invalida y la siguiente lectura vuelve a consultar.
$db->respuestas = [['id_config' => 10]];
afirmar(setConfig('pin_emergencia_global', '222222') === true, 'setConfig debe reportar éxito al actualizar.');
$db->respuestas = [['valor' => '222222']];
afirmar(getConfig('pin_emergencia_global') === '222222', 'Tras setConfig, getConfig debe reconsultar y devolver el valor nuevo.');

$escritura = escrituraPor('UPDATE configuracion SET valor');
afirmar($escritura['params'] === ['222222', 'pin_emergencia_global'], 'setConfig debe actualizar la fila existente por clave.');

// Clave inexistente: se crea.
$db->escrituras = [];
$db->respuestas = [null];
setConfig('clave_nueva', 'valor_nuevo');
$escritura = escrituraPor('INSERT INTO configuracion');
afirmar($escritura['params'][0] === 'clave_nueva', 'setConfig debe insertar la fila cuando la clave no existe.');

// -----------------------------------------------
// 2) Alta de colaborador: ya no exige PIN
// -----------------------------------------------
$datosBase = [
    'usuario'               => 'prueba_pin',
    'correo'                => 'prueba_pin@empresa.com',
    'password'              => 'Abc12345!',
    'password_confirm'      => 'Abc12345!',
    'id_rol'                => 2,
];

$db->escrituras = [];
$db->respuestas = [null]; // sin duplicados
$resultado = (new Usuario())->crear($datosBase);
afirmar($resultado['ok'] === true, 'El alta sin PIN debe funcionar: ' . $resultado['mensaje']);

$escritura = escrituraPor('INSERT usuarios');
afirmar(
    !array_key_exists('pin_emergencia', $escritura['params']),
    'El alta ya no debe persistir un PIN por usuario.'
);
afirmar(
    !array_key_exists('pregunta_seguridad', $escritura['params'])
        && !array_key_exists('respuesta_seguridad', $escritura['params']),
    'El alta ya no debe persistir la pregunta/respuesta de seguridad.'
);

// Regresión: la contraseña débil sigue rechazándose.
$db->escrituras = [];
$db->respuestas = [];
$debil = $datosBase;
$debil['password'] = 'abc';
$debil['password_confirm'] = 'abc';
$resultado = (new Usuario())->crear($debil);
afirmar($resultado['ok'] === false, 'La contraseña débil debe seguir rechazándose.');

// -----------------------------------------------
// 3) Perfil: ya no exige PIN
// -----------------------------------------------
$_SESSION['id_usuario'] = 5;
$fila = [
    'id_usuario' => 5, 'usuario' => 'OPERADOR', 'correo' => 'op@empresa.com',
    'password' => password_hash('Abc12345!', PASSWORD_BCRYPT),
    'password_check' => 'x', 'pin_emergencia' => null,
];
$db->respuestas = [$fila, null, null]; // actual + sin duplicados
$db->escrituras = [];

$perfil = [
    'password_actual' => 'Abc12345!', 'usuario' => 'operador', 'correo' => 'op@empresa.com',
];
$resultado = (new Usuario())->actualizarPerfil($perfil);
afirmar($resultado['ok'] === true, 'El perfil sin PIN debe guardarse sin exigirlo: ' . $resultado['mensaje']);

$escritura = escrituraPor('UPDATE usuarios SET usuario=?');
afirmar(
    !str_contains($escritura['sql'], 'pin_emergencia'),
    'El UPDATE del perfil ya no debe tocar pin_emergencia.'
);
afirmar(
    !str_contains($escritura['sql'], 'pregunta_seguridad'),
    'El UPDATE del perfil ya no debe tocar la pregunta/respuesta de seguridad.'
);

// -----------------------------------------------
// 4) El PIN dejó de ser un dato del usuario
// -----------------------------------------------
$modelo = file_get_contents($root . '/models/Usuario.php');
afirmar(strpos($modelo, 'pin_emergencia') === false, 'El modelo de Usuario ya no debe usar la columna pin_emergencia.');

// La recuperación verifica contra el PIN compartido y excluye al Administrador.
$recuperacion = file_get_contents($root . '/login/recuperar.php');
afirmar(
    strpos($recuperacion, "getConfig('pin_emergencia_global'") !== false,
    'La recuperación debe verificar contra el PIN compartido.'
);
afirmar(
    strpos($recuperacion, 'usuarios.pin_emergencia') === false && strpos($recuperacion, 'SELECT pin_emergencia') === false,
    'La recuperación ya no debe leer el PIN desde usuarios.'
);
afirmar(
    strpos($recuperacion, '$rec_es_admin') !== false && strpos($recuperacion, '=== 1') !== false,
    'La recuperación debe detectar al Administrador por su rol.'
);
afirmar(
    strpos($recuperacion, 'SE RECUPERA POR CORREO') !== false,
    'La recuperación debe explicar que el Administrador se recupera por correo.'
);

// El Administrador define el PIN compartido desde el módulo de Colaboradores.
$vistaUsuarios = file_get_contents($root . '/views/usuarios/index.php');
afirmar(strpos($vistaUsuarios, 'pin_emergencia_global') !== false, 'La vista de Colaboradores debe permitir definir el PIN compartido.');
afirmar(strpos($vistaUsuarios, 'crear_pin') === false, 'El alta ya no debe pedir PIN por colaborador.');

$js = file_get_contents($root . '/assets/dashboard/usuarios.js');
afirmar(strpos($js, 'crear_pin') === false, 'El JS del alta ya no debe validar un PIN por usuario.');

$controlador = file_get_contents($root . '/controllers/UsuariosController.php');
afirmar(
    strpos($controlador, "'pin_global'") !== false && strpos($controlador, "setConfig('pin_emergencia_global'") !== false,
    'El controlador debe exponer la acción que guarda el PIN compartido.'
);

$perfilVista = file_get_contents($root . '/views/perfil/index.php');
afirmar(strpos($perfilVista, '$pin_global') !== false, 'El perfil debe mostrar el PIN compartido al operador.');
afirmar(strpos($perfilVista, 'name="pin_emergencia"') === false, 'El perfil ya no debe enviar el PIN como dato del usuario.');

// -----------------------------------------------
// 5) El instalador limpio trae la clave
// -----------------------------------------------
$seed = file_get_contents($root . '/db/jv3000_portable_v6.sql');
afirmar(strpos($seed, "'pin_emergencia_global'") !== false, 'El seed debe traer la clave del PIN compartido.');

// El rescate del Administrador existe y es solo para CLI.
$rescate = @file_get_contents($root . '/tools/resetear_admin.php');
afirmar(is_string($rescate) && strpos($rescate, "PHP_SAPI !== 'cli'") !== false, 'El rescate del admin debe existir y rechazar la web.');

echo "OK - pin_global_piloto: $afirmaciones aserciones superadas.\n";
