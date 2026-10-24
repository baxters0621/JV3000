<?php

/**
 * Regresión: PIN de emergencia único y compartido.
 *
 * El PIN dejó de ser un dato por usuario: ahora es una sola clave que el
 * Administrador define en la tabla `configuracion` y que sirve para todos los
 * usuarios, incluida la propia cuenta del Administrador. Es el respaldo de
 * recuperación cuando el canal de correo no está disponible. Ejecutar con PHP
 * CLI; sin base de datos real.
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
// 3b) Perfil: cambiar solo usuario/correo sin contraseña actual
// -----------------------------------------------
$db->escrituras = [];
$db->respuestas = [];
$fila = [
    'id_usuario' => 5, 'usuario' => 'OPERADOR', 'correo' => 'op@empresa.com',
    'password' => password_hash('Abc12345!', PASSWORD_BCRYPT),
    'password_check' => 'hash_chk',
];
$db->respuestas = [$fila, null, null]; // actual + sin duplicados
$soloDatos = ['password_actual' => '', 'usuario' => 'operador2', 'correo' => 'op2@empresa.com'];
$res = (new Usuario())->actualizarPerfil($soloDatos);
afirmar($res['ok'] === true, 'Debe permitir cambiar usuario/correo sin contraseña actual: ' . ($res['mensaje'] ?? ''));
$upd = escrituraPor('UPDATE usuarios SET usuario=?');
$params = $upd['params'];
// Debe mantener el hash y el password_check originales
afirmar($params[2] === $fila['password'], 'Al no cambiar contraseña debe conservarse el hash original.');
afirmar($params[3] === $fila['password_check'], 'Al no cambiar contraseña debe conservarse el password_check original.');

// Nueva contraseña sin contraseña actual debe rechazarse
$db->escrituras = [];
$db->respuestas = [];
$fila = [
    'id_usuario' => 5, 'usuario' => 'OPERADOR', 'correo' => 'op@empresa.com',
    'password' => password_hash('Abc12345!', PASSWORD_BCRYPT),
    'password_check' => 'hash_chk',
];
$db->respuestas = [$fila];
$sinActual = ['password_actual' => '', 'usuario' => 'operador', 'correo' => 'op@empresa.com', 'password_nueva' => 'NuevaClave9!', 'password_confirm' => 'NuevaClave9!'];
$r2 = (new Usuario())->actualizarPerfil($sinActual);
afirmar($r2['ok'] === false && stripos($r2['mensaje'], 'CONTRASEÑA ACTUAL') !== false, 'Al poner nueva contraseña sin la actual debe rechazarse.');

// -----------------------------------------------
// 4) El PIN dejó de ser un dato del usuario
// -----------------------------------------------
$modelo = file_get_contents($root . '/models/Usuario.php');
afirmar(strpos($modelo, 'pin_emergencia') === false, 'El modelo de Usuario ya no debe usar la columna pin_emergencia.');

// El PIN sirve para todas las cuentas, incluida la del Administrador: no se
// excluye a nadie, es el respaldo cuando el canal de correo falla.
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
    strpos($recuperacion, '$rec_es_admin && $pinGlobal') === false
        && strpos($recuperacion, '!$rec_es_admin && $pinGlobal') === false,
    'La recuperación no debe excluir al Administrador del PIN compartido.'
);
afirmar(
    strpos($recuperacion, 'o con el PIN de emergencia') !== false,
    'La recuperación debe informar que el Administrador también puede usar el PIN.'
);
// El código de recuperación se sustituye en el cuerpo antes de enviarlo.
afirmar(
    strpos($recuperacion, "'{CODIGO}'") !== false && strpos($recuperacion, '$solicitud[\'codigo\']') !== false,
    'El cuerpo del correo debe llevar el código real, no el marcador {CODIGO}.'
);

// Al escribir el PIN en la recuperación también se puede ver: ojo en el campo.
afirmar(strpos($recuperacion, 'btnEyePin') !== false, 'El campo del PIN en la recuperación debe llevar el botón para verlo.');

// El Administrador define el PIN compartido desde el módulo de Colaboradores.
$vistaUsuarios = file_get_contents($root . '/views/usuarios/index.php');
afirmar(strpos($vistaUsuarios, 'pin_emergencia_global') !== false, 'La vista de Colaboradores debe permitir definir el PIN compartido.');
afirmar(strpos($vistaUsuarios, 'crear_pin') === false, 'El alta ya no debe pedir PIN por colaborador.');

// El PIN y las contraseñas de los modales se pueden ver mientras se teclea.
afirmar(
    substr_count($vistaUsuarios, 'data-toggle-password') === 6
        && substr_count($vistaUsuarios, 'class="password-field"') === 6,
    'El PIN y los cuatro campos de contraseña de alta y restablecer deben envolverse en .password-field con su ojo.'
);

// El PIN se valida en vivo antes de enviar: guías bajo los dos campos.
afirmar(
    strpos($vistaUsuarios, 'id="pin_emergencia_global_guia"') !== false
        && strpos($vistaUsuarios, 'id="pin_emergencia_global_confirm_guia"') !== false,
    'Los dos campos del PIN deben tener su guía de validación en vivo.'
);
afirmar(
    substr_count($vistaUsuarios, 'oninput="validarPinGlobal()"') === 2,
    'Los dos campos del PIN deben disparar la validación en vivo al teclear.'
);
afirmar(
    strpos($vistaUsuarios, 'Protege la recuperación de cuentas') !== false
        && substr_count($vistaUsuarios, 'class="pin-step pin-step-') === 2
        && strpos($vistaUsuarios, 'class="pin-step-number"') !== false,
    'La configuración visual debe presentar instrucciones claras y dos pasos numerados.'
);

// La ayuda del PIN abre desde el botón (?) de la cabecera en un modal
// propio; los campos del formulario quedan como primer contenido de la tarjeta.
afirmar(
    strpos($vistaUsuarios, 'class="pin-help') !== false
        && strpos($vistaUsuarios, 'data-bs-target="#modalAyudaPin"') !== false
        && strpos($vistaUsuarios, 'id="modalAyudaPin"') !== false,
    'La ayuda del PIN debe abrirse desde el botón (?) de la cabecera en el modal #modalAyudaPin.'
);
afirmar(
    substr_count($vistaUsuarios, 'class="pin-info') === 1,
    'El texto de ayuda del PIN debe existir una sola vez, dentro del modal.'
);
afirmar(
    strpos($vistaUsuarios, 'class="pin-status') !== false,
    'La cabecera del PIN debe mostrar la pastilla de estado (DEFINIDO / SIN DEFINIR).'
);
afirmar(
    strpos($vistaUsuarios, 'pin-ayuda') === false,
    'La ayuda ya no debe usar el «?» con globo del cambio anterior.'
);
afirmar(
    strpos($vistaUsuarios, '<details>') === false,
    'La ayuda ya no debe usar el desplegable details/summary del cambio anterior.'
);

// El ojo nace tachado: el campo está enmascarado hasta que se pide verlo.
afirmar(
    substr_count($vistaUsuarios, 'bi bi-eye-slash" aria-hidden') === 6,
    'Los seis campos con ojo deben arrancar con el icono tachado, porque empiezan ocultos.'
);

// Los textos explicativos de los modales se leen a tamaño cómodo.
afirmar(
    substr_count($vistaUsuarios, 'alert-lectura') === 2,
    'La alerta de restablecer y el aviso de recuperación del alta deben usar la clase de lectura cómoda.'
);

$js = file_get_contents($root . '/assets/dashboard/usuarios.js');
afirmar(strpos($js, 'crear_pin') === false, 'El JS del alta ya no debe validar un PIN por usuario.');
afirmar(
    strpos($js, "querySelectorAll('[data-toggle-password]')") !== false,
    'El JS de Colaboradores debe alternar la visibilidad del PIN al pulsar el ojo.'
);
afirmar(
    strpos($js, 'function validarPinGlobal()') !== false,
    'El JS debe validar el PIN en vivo: 6 dígitos y confirmación coincidente.'
);

// El color de esa validación vive en el CSS del módulo (antes no existía en ningún sitio).
$cssUsuarios = file_get_contents($root . '/assets/dashboard/usuarios.css');
afirmar(
    strpos($cssUsuarios, '.pagina-usuarios .pin-form-intro') !== false
        && strpos($cssUsuarios, '.pagina-usuarios .pin-step-confirmar') !== false
        && strpos($cssUsuarios, '.pagina-usuarios .pin-form .form-label-jv') !== false,
    'El formulario PIN debe tener superficies con color y etiquetas destacadas.'
);
afirmar(
    strpos($cssUsuarios, '.guia-ok') !== false && strpos($cssUsuarios, '.guia-err') !== false,
    'Las guías de validación en vivo deben pintarse en verde y rojo.'
);
afirmar(
    strpos($cssUsuarios, '.pagina-usuarios .pin-info') !== false,
    'La línea fija de ayuda del PIN debe tener estilo en el CSS del módulo.'
);
afirmar(
    strpos($cssUsuarios, '.pagina-usuarios .alert-lectura') !== false,
    'La clase de lectura cómoda de los textos explicativos debe estar en el CSS del módulo.'
);

// El ojo abierto significa "visible": la lógica no puede estar invertida.
afirmar(
    strpos($js, "'bi bi-eye' : 'bi bi-eye-slash'") !== false,
    'El ojo de Colaboradores debe abrirse al mostrar el texto y tacharse al ocultarlo.'
);
$jsPerfil = file_get_contents($root . '/assets/dashboard/perfil.js');
afirmar(
    strpos($jsPerfil, "'bi bi-eye' : 'bi bi-eye-slash'") !== false,
    'El ojo de Mi Perfil debe usar la misma lógica: abierto = visible.'
);

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

// -----------------------------------------------
// 6) Los assets propios se versionan con su mtime
// -----------------------------------------------
// Evita mantener ?v=N a mano: el navegador recibe la fecha real del archivo y
// solo vuelve a descargarlo cuando cambia (bug de JS viejo en caché).
require_once $root . '/core/Controller.php';

afirmar(function_exists('jv_asset_version'), 'Debe existir el helper jv_asset_version().');
afirmar(
    preg_match('/^\?v=\d+$/', jv_asset_version('dashboard/usuarios.js')) === 1,
    'El helper debe devolver ?v=<mtime> para un asset existente.'
);
afirmar(jv_asset_version('no/existe.js') === '', 'El helper debe devolver cadena vacía si el asset no existe.');

$layout = file_get_contents($root . '/views/layouts/main.php');
afirmar(strpos($layout, '$this->viewData') !== false, 'El layout debe recibir los assets ya versionados desde el controlador.');

$controladorBase = file_get_contents($root . '/core/Controller.php');
afirmar(
    strpos($controladorBase, 'versionarAssets') !== false && strpos($controladorBase, "jv_asset_version(") !== false,
    'El Controller base debe versionar automáticamente css_extra/js_extra.'
);

$dashboardVista = file_get_contents($root . '/dashboard/index.php');
afirmar(strpos($dashboardVista, 'index.js?v=13') === false, 'El panel legacy ya no debe llevar ?v fijo.');

$loginVista = file_get_contents($root . '/login/login.php');
afirmar(strpos($loginVista, "login.js<?php") !== false, 'El login debe versionar login.js con su mtime.');

echo "OK - pin_global_piloto: $afirmaciones aserciones superadas.\n";
