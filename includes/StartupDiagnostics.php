<?php

require_once __DIR__ . '/SchemaCompatibility.php';

/**
 * Clasifica resultados observables del arranque sin conservar detalles sensibles.
 */
final class StartupDiagnostics
{
    public const SERVER_UNREACHABLE = 'server_unreachable';
    public const AUTHENTICATION_REJECTED = 'authentication_rejected';
    public const PERMISSION_DENIED = 'permission_denied';
    public const DATABASE_MISSING = 'database_missing';
    public const SCHEMA_COMPATIBLE = 'schema_compatible';
    public const SCHEMA_INCOMPLETE = 'schema_incomplete';
    public const SCHEMA_OUTDATED_KNOWN = 'schema_outdated_known';
    public const SCHEMA_UNKNOWN = 'schema_unknown';
    public const CONNECTION_UNKNOWN = 'connection_unknown';
    public const CONFIGURATION_MISSING = 'configuration_missing';
    public const CONFIGURATION_INVALID = 'configuration_invalid';
    public const INSTALL_STATE_BLOCKED = 'install_state_blocked';
    public const INSTALL_INTERRUPTED = 'install_interrupted';

    /**
     * Clasifica un error MySQL por su código, sin incluir su texto en el resultado.
     *
     * @param Throwable $error Error observable al conectar o seleccionar la base.
     * @return array{category:string,title:string,message:string,next_step:string}
     */
    public static function fromConnectionError(Throwable $error): array
    {
        $category = match ((int)$error->getCode()) {
            2002, 2003, 2006, 2013, 2055 => self::SERVER_UNREACHABLE,
            1045 => self::AUTHENTICATION_REJECTED,
            1044, 1142, 1143, 1227 => self::PERMISSION_DENIED,
            1049 => self::DATABASE_MISSING,
            default => self::CONNECTION_UNKNOWN,
        };

        return self::messageFor($category);
    }

    /**
     * Traduce el resultado puro del verificador de esquema a un diagnóstico seguro.
     *
     * @param array $comparison Resultado devuelto por SchemaCompatibility::compare().
     * @return array{category:string,title:string,message:string,next_step:string}
     */
    public static function fromSchemaComparison(array $comparison): array
    {
        $category = match ($comparison['status'] ?? null) {
            SchemaCompatibility::COMPATIBLE => self::SCHEMA_COMPATIBLE,
            SchemaCompatibility::INCOMPLETE => self::SCHEMA_INCOMPLETE,
            SchemaCompatibility::OUTDATED_KNOWN => self::SCHEMA_OUTDATED_KNOWN,
            default => self::SCHEMA_UNKNOWN,
        };

        return self::messageFor($category);
    }

    /**
     * Indica si el esquema permite iniciar la aplicación sin migraciones.
     *
     * @param array $comparison Resultado del comparador estructural.
     * @return bool True únicamente para el perfil compatible.
     */
    public static function allowsNormalStartup(array $comparison): bool
    {
        return ($comparison['status'] ?? null) === SchemaCompatibility::COMPATIBLE;
    }

    /**
     * Clasifica ausencia o invalidez de la configuración local sin revelar valores.
     *
     * @param string $problem Estado fijo de configuración local.
     * @param array $missingKeys Nombres de variables faltantes permitidos.
     * @return array Diagnóstico seguro y accionable.
     */
    public static function fromConfigurationProblem(string $problem, array $missingKeys = []): array
    {
        $category = $problem === 'invalid' ? self::CONFIGURATION_INVALID : self::CONFIGURATION_MISSING;
        $diagnostic = self::messageFor($category);
        $allowedKeys = ['JV_DB_HOST', 'JV_DB_USER', 'JV_DB_PASS', 'JV_DB_PASS_B64', 'JV_DB_NAME', 'JV_DB_PORT'];
        $missingKeys = array_values(array_intersect($allowedKeys, $missingKeys));
        if ($missingKeys !== []) {
            $diagnostic['evidence'] = 'Faltan variables de configuración local: ' . implode(', ', $missingKeys) . '.';
            $diagnostic['message'] = $diagnostic['evidence'];
        }

        return $diagnostic;
    }

    /**
     * Convierte estados del marcador en instrucciones seguras sin mostrar su ruta.
     *
     * @param string $state Estado fijo devuelto por EstadoInstalacion.
     * @return array Diagnóstico seguro del marcador.
     */
    public static function fromInstallationState(string $state): array
    {
        $category = match ($state) {
            'directorio_no_configurado', 'ruta_invalida', 'ruta_publica', 'sin_acceso',
            'marcador_ausente', 'marcador_corrupto', 'destino_distinto' => self::INSTALL_STATE_BLOCKED,
            'en_progreso', 'incompleta', 'completada', 'instalacion_bloqueada' => self::INSTALL_INTERRUPTED,
            default => self::INSTALL_STATE_BLOCKED,
        };
        $diagnostic = self::messageFor($category);
        $states = [
            'directorio_no_configurado' => 'No se configuró JV_INSTALL_STATE_DIR.',
            'ruta_invalida' => 'La ubicación local del marcador no pudo validarse de forma canónica.',
            'ruta_publica' => 'La ubicación del marcador está dentro del directorio servido por la web.',
            'sin_acceso' => 'El proceso PHP no puede leer o actualizar de forma segura el marcador privado.',
            'marcador_ausente' => 'No existe el marcador inicial requerido; no se asumió una instalación nueva.',
            'marcador_corrupto' => 'El marcador falta en formato válido, tiene una versión no reconocida o apunta mediante un enlace no admitido.',
            'destino_distinto' => 'El marcador pertenece a otro destino lógico de base de datos.',
            'en_progreso' => 'El marcador indica que una importación pudo quedar interrumpida.',
            'incompleta' => 'El marcador registra que una importación anterior no terminó correctamente.',
            'completada' => 'El marcador ya registra una instalación completada para este destino.',
            'instalacion_bloqueada' => 'El estado registrado no autoriza una nueva instalación.',
        ];
        $diagnostic['evidence'] = $states[$state] ?? 'El estado del marcador no autoriza una instalación nueva.';
        $diagnostic['message'] = $diagnostic['evidence'];
        $diagnostic['state'] = $state;

        return $diagnostic;
    }

    /**
     * Ejecuta el lector de metadatos inyectado y clasifica el esquema sin propagar errores sensibles.
     *
     * @param callable():array $profileReader Lector que devuelve un perfil estructural.
     * @param array $current Perfil requerido por la versión actual.
     * @param array $knownProfiles Perfiles anteriores verificados.
     * @return array Diagnóstico de esquema o de acceso de solo lectura.
     */
    public static function inspectSchema(callable $profileReader, array $current, array $knownProfiles = []): array
    {
        try {
            return self::fromSchemaComparison(
                SchemaCompatibility::compare($profileReader(), $current, $knownProfiles)
            );
        } catch (Throwable $error) {
            return self::fromConnectionError($error);
        }
    }

    /**
     * Devuelve mensajes fijos, accionables y sin datos del error o del entorno.
     *
     * @param string $category Categoría observable.
     * @return array{category:string,title:string,message:string,next_step:string,component:string,evidence:string,cause_status:string,cause:string,checks:array,escalate:string,support_data:array}
     */
    private static function messageFor(string $category): array
    {
        $messages = [
            self::SERVER_UNREACHABLE => [
                'title' => 'No se alcanza el servidor de base de datos',
                'message' => 'No fue posible establecer comunicación con MySQL/MariaDB. Desde la aplicación no se puede distinguir si el servicio está detenido, si el host o puerto no son correctos, o si la red/firewall bloquea el acceso.',
                'next_step' => 'En el PC servidor, comprueba el servicio MySQL/MariaDB y valida localmente el host y puerto configurados; si accedes desde otro equipo, revisa también la conectividad LAN.',
            ],
            self::AUTHENTICATION_REJECTED => [
                'title' => 'Autenticación de base de datos rechazada',
                'message' => 'El servidor respondió, pero rechazó la autenticación configurada para la aplicación.',
                'next_step' => 'Un administrador debe verificar localmente el usuario y la contraseña configurados. No compartas ni publiques esos valores.',
            ],
            self::PERMISSION_DENIED => [
                'title' => 'Permisos de base de datos insuficientes',
                'message' => 'El servidor respondió, pero el usuario configurado no tiene permiso para acceder a la base o realizar la operación solicitada.',
                'next_step' => 'Solicita al administrador de MySQL que revise los permisos mínimos de ese usuario para la base configurada; no uses otra cuenta automáticamente.',
            ],
            self::DATABASE_MISSING => [
                'title' => 'La base configurada no existe',
                'message' => 'El servidor respondió, pero no encontró la base solicitada con la configuración actual. Esto no confirma que sea seguro crear una base nueva.',
                'next_step' => 'Verifica localmente el nombre configurado y busca la base institucional o un respaldo antes de considerar una instalación nueva.',
                'options' => [
                    'Revisa el nombre de la base y la configuración local de conexión antes de cambiarlos.',
                    'Busca un respaldo. La restauración debe hacerse manualmente y esta pantalla no aplica ningún archivo.',
                    'Una instalación nueva solo podrá iniciarse desde una ruta explícita de instalación; no se inicia desde este diagnóstico.',
                ],
            ],
            self::SCHEMA_COMPATIBLE => [
                'title' => 'Esquema compatible',
                'message' => 'La estructura observada coincide con un perfil de esquema conocido por esta versión.',
                'next_step' => 'No se requiere ninguna acción de diagnóstico.',
            ],
            self::SCHEMA_INCOMPLETE => [
                'title' => 'Esquema incompleto',
                'message' => 'Faltan objetos estructurales requeridos por el perfil actual. No se puede determinar que se trate de una instalación nueva solo por esa diferencia.',
                'next_step' => 'El arranque funcional está bloqueado. Solicita un respaldo verificado y una actualización manual autorizada; no intentes completar el esquema desde el arranque.',
            ],
            self::SCHEMA_OUTDATED_KNOWN => [
                'title' => 'Esquema anterior conocido',
                'message' => 'La estructura coincide con un perfil anterior registrado; no se ha actualizado.',
                'next_step' => 'El arranque funcional está bloqueado. Requiere un respaldo verificado y una actualización manual autorizada por soporte.',
            ],
            self::SCHEMA_UNKNOWN => [
                'title' => 'Esquema incompatible o no reconocido',
                'message' => 'La estructura observada no coincide con el perfil actual ni con un perfil anterior registrado.',
                'next_step' => 'El arranque funcional está bloqueado. No reinstales ni modifiques la base; solicita un respaldo verificado y una actualización manual autorizada tras revisión técnica.',
            ],
            self::CONNECTION_UNKNOWN => [
                'title' => 'No se pudo clasificar el error de conexión',
                'message' => 'La aplicación no pudo completar la conexión y el resultado no coincide con una categoría conocida.',
                'next_step' => 'Revisa el servicio, la configuración y los permisos localmente; comparte solo el código numérico del error con soporte.',
            ],
            self::CONFIGURATION_MISSING => [
                'title' => 'Falta configuración local del servidor',
                'message' => 'JV3000 no encontró todos los valores de conexión requeridos en el proceso del servidor o en config/.env. No se intentó conectar usando valores supuestos.',
                'next_step' => 'En el PC servidor, una persona administradora debe completar config/.env desde config/.env.example o definir las variables JV_DB_* para el proceso que ejecuta PHP. No introduzcas esos valores en esta página.',
            ],
            self::CONFIGURATION_INVALID => [
                'title' => 'La configuración local no se pudo leer',
                'message' => 'El archivo local de configuración no pudo interpretarse o la contraseña codificada existente no es válida. Su contenido no se muestra.',
                'next_step' => 'En el PC servidor, revisa manualmente el formato de config/.env contra config/.env.example. No compartas el archivo ni pegues sus valores en esta página.',
            ],
            self::INSTALL_STATE_BLOCKED => [
                'title' => 'Instalación nueva bloqueada por el estado privado',
                'message' => 'El sistema no pudo verificar que el marcador privado de instalación esté preparado y asociado de forma segura a este servidor y destino.',
                'next_step' => 'No crees la base ni borres o recrees el marcador. Revisa JV_INSTALL_STATE_DIR y el archivo estado-instalacion.json fuera del directorio público con la persona administradora del servidor.',
            ],
            self::INSTALL_INTERRUPTED => [
                'title' => 'Instalación nueva no autorizada',
                'message' => 'El estado guardado indica una importación en curso, incompleta o ya terminada. No se iniciará otra importación.',
                'next_step' => 'Detente y solicita revisión manual del destino y del marcador a soporte. No borres el marcador, no limpies tablas y no reintentes la instalación.',
            ],
        ];

        if (!isset($messages[$category])) {
            $category = self::CONNECTION_UNKNOWN;
        }

        $guidance = self::guidanceFor($category);

        return ['category' => $category] + $messages[$category] + $guidance;
    }

    /**
     * Agrega componente, evidencia, comprobaciones y escalamiento a cada estado.
     *
     * @param string $category Categoría segura del diagnóstico.
     * @return array Orientación no sensible.
     */
    private static function guidanceFor(string $category): array
    {
        $guidance = [
            self::SERVER_UNREACHABLE => [
                'component' => 'Conexión al servicio MySQL/MariaDB (host y puerto).',
                'evidence' => 'No se obtuvo respuesta del motor configurado.',
                'cause_status' => 'posible',
                'cause' => 'No se puede distinguir automáticamente entre servicio detenido, host/puerto incorrectos, firewall o conectividad de red.',
                'checks' => [
                    'Confirma que estás revisando el PC servidor donde se ejecuta PHP.',
                    'Pide a la persona administradora que compruebe localmente si el servicio MySQL/MariaDB está iniciado.',
                    'Compara host y puerto configurados con los valores confirmados por quien administra el servidor.',
                    'Si el motor está iniciado, solicita a soporte revisar firewall y conectividad sin cambiar reglas desde JV3000.',
                ],
                'escalate' => 'Si el servicio parece activo y sigue sin responder, detente y contacta al administrador del servidor o de la red.',
            ],
            self::AUTHENTICATION_REJECTED => [
                'component' => 'Credenciales locales del proceso PHP.',
                'evidence' => 'El servidor respondió y rechazó la autenticación.',
                'cause_status' => 'confirmada',
                'cause' => 'La identidad configurada no fue aceptada; el sistema no puede determinar ni mostrar qué valor local debe corregirse.',
                'checks' => [
                    'Confirma con la persona administradora que PHP está leyendo la configuración de este PC servidor.',
                    'Pide que revise localmente el nombre de usuario y la contraseña sin enviarlos por navegador o ticket.',
                    'No cambies a otra cuenta ni pruebes contraseñas en esta página.',
                ],
                'escalate' => 'Si la identidad local parece correcta, detente y solicita al administrador del motor que revise la cuenta.',
            ],
            self::PERMISSION_DENIED => [
                'component' => 'Permisos de la cuenta de base de datos.',
                'evidence' => 'El servidor respondió, pero rechazó el acceso a la base o a sus metadatos.',
                'cause_status' => 'confirmada',
                'cause' => 'Hay una denegación de acceso; la pantalla no cambia cuentas ni permisos.',
                'checks' => [
                    'Confirma con la persona administradora que el destino corresponde a esta instalación.',
                    'Solicita revisar localmente los permisos mínimos previstos para esa cuenta.',
                    'No concedas privilegios ni cambies a una cuenta más amplia como prueba.',
                ],
                'escalate' => 'Detente si se necesitan cambios de cuenta o permisos y contacta al administrador de MySQL/MariaDB.',
            ],
            self::DATABASE_MISSING => [
                'component' => 'Nombre o existencia de la base configurada.',
                'evidence' => 'El servidor respondió, pero indicó que no existe la base solicitada.',
                'cause_status' => 'confirmada',
                'cause' => 'La base con el nombre configurado no fue encontrada; eso no demuestra que el nombre sea correcto ni que autorice crearla.',
                'checks' => [
                    'Pide a la persona administradora comparar el nombre lógico configurado con el destino esperado.',
                    'Confirma si ese destino debía contener datos previos y localiza el procedimiento de respaldo institucional.',
                    'Solo si se confirmó que es una instalación limpia, revisa el marcador privado antes de solicitar la confirmación explícita.',
                ],
                'escalate' => 'Si esperabas datos previos o no puedes identificar el destino, detente y escala antes de instalar o restaurar.',
            ],
            self::SCHEMA_COMPATIBLE => [
                'component' => 'Esquema de la base.',
                'evidence' => 'Los metadatos coinciden con el perfil compatible de esta versión.',
                'cause_status' => 'confirmada',
                'cause' => 'La estructura requerida pudo verificarse sin ejecutar migraciones.',
                'checks' => ['Continúa con el arranque normal; el marcador de instalación nueva no se consulta para esta ruta.'],
                'escalate' => 'Si aparece otro error después de esta comprobación, comparte su categoría y hora, sin adjuntar configuración.',
            ],
            self::SCHEMA_INCOMPLETE => [
                'component' => 'Esquema de la base existente.',
                'evidence' => 'Los metadatos no coinciden con el perfil compatible requerido.',
                'cause_status' => 'confirmada',
                'cause' => 'La base contiene una estructura que no se reconoce como compatible; no se considera una instalación nueva.',
                'checks' => [
                    'No reinstales ni modifiques las tablas.',
                    'Solicita que soporte identifique la versión y confirme un respaldo verificado.',
                    'Espera un procedimiento manual de actualización autorizado.',
                ],
                'escalate' => 'Detente y contacta soporte técnico; el arranque bloquea esta base sin migrarla.',
            ],
            self::SCHEMA_OUTDATED_KNOWN => [
                'component' => 'Esquema de la base existente.',
                'evidence' => 'Los metadatos coinciden con un perfil anterior registrado.',
                'cause_status' => 'confirmada',
                'cause' => 'La base no coincide con el perfil actual y no se ha actualizado.',
                'checks' => [
                    'No reinstales ni modifiques las tablas.',
                    'Solicita que soporte identifique la versión y confirme un respaldo verificado.',
                    'Espera un procedimiento manual de actualización autorizado.',
                ],
                'escalate' => 'Detente y contacta soporte técnico; el arranque bloquea esta base sin migrarla.',
            ],
            self::SCHEMA_UNKNOWN => [
                'component' => 'Esquema de la base existente.',
                'evidence' => 'Los metadatos no coinciden con el perfil compatible requerido.',
                'cause_status' => 'confirmada',
                'cause' => 'La base contiene una estructura que no se reconoce como compatible; no se considera una instalación nueva.',
                'checks' => [
                    'No reinstales ni modifiques las tablas.',
                    'Solicita que soporte identifique la versión y confirme un respaldo verificado.',
                    'Espera un procedimiento manual de actualización autorizado.',
                ],
                'escalate' => 'Detente y contacta soporte técnico; el arranque bloquea esta base sin migrarla.',
            ],
            self::CONFIGURATION_MISSING => [
                'component' => 'Configuración local de PHP en el PC servidor.',
                'evidence' => 'No están definidas todas las variables de conexión requeridas.',
                'cause_status' => 'confirmada',
                'cause' => 'El asistente no encontró una configuración explícita completa y no probó valores predeterminados.',
                'checks' => [
                    'En el PC servidor, compara config/.env con config/.env.example o revisa las variables del proceso Apache/PHP.',
                    'Confirma host, usuario, contraseña (puede ser vacía si así se configuró), nombre de base y puerto localmente.',
                    'Reinicia el proceso web solo mediante el procedimiento aprobado para que lea cambios de entorno.',
                    'Vuelve a abrir JV3000; no introduzcas credenciales en un formulario web.',
                ],
                'escalate' => 'Si no puedes editar la configuración local o identificar qué proceso ejecuta PHP, detente y solicita al administrador del PC servidor.',
            ],
            self::CONFIGURATION_INVALID => [
                'component' => 'Formato de config/.env o configuración local del servidor.',
                'evidence' => 'La configuración no pudo interpretarse de forma segura.',
                'cause_status' => 'confirmada',
                'cause' => 'Se detectó una configuración inválida, pero su contenido se mantiene privado.',
                'checks' => [
                    'Pide a la persona administradora revisar el formato local contra config/.env.example.',
                    'Comprueba que las variables están escritas una por línea y que el proceso PHP puede leer el archivo.',
                    'No adjuntes el archivo ni copies su contenido a la pantalla de soporte.',
                ],
                'escalate' => 'Si persiste tras una revisión local autorizada, comparte únicamente esta categoría y la hora aproximada.',
            ],
            self::INSTALL_STATE_BLOCKED => [
                'component' => 'Ubicación privada y marcador de instalación.',
                'evidence' => 'El marcador no se pudo verificar con el formato, ubicación o destino requeridos.',
                'cause_status' => 'confirmada',
                'cause' => 'La ruta o el estado del marcador no autoriza iniciar una instalación limpia.',
                'checks' => [
                    'Pide al administrador revisar localmente que JV_INSTALL_STATE_DIR esté definido para el proceso PHP.',
                    'Confirma que la carpeta existe, está fuera del directorio público y permite al proceso leer y reemplazar archivos.',
                    'Verifica manualmente que estado-instalacion.json sea el marcador inicial aprobado y corresponda a este servidor.',
                    'No crees, borres ni edites el marcador desde el navegador.',
                ],
                'escalate' => 'Si la ubicación o el destino no se pueden verificar, detente y escala a soporte antes de modificar base o marcador.',
            ],
            self::INSTALL_INTERRUPTED => [
                'component' => 'Estado de una instalación limpia anterior.',
                'evidence' => 'El marcador registra una instalación en curso, incompleta o completada para este destino.',
                'cause_status' => 'confirmada',
                'cause' => 'El estado almacenado impide que esta ruta inicie otra importación.',
                'checks' => [
                    'Conserva el marcador y el destino tal como están.',
                    'Solicita a soporte revisar el esquema y decidir un procedimiento manual de recuperación.',
                    'No borres tablas, no borres el marcador y no repitas el POST.',
                ],
                'escalate' => 'Detente de inmediato y entrega a soporte la categoría, hora y estado mostrado; nunca envíes credenciales o datos de negocio.',
            ],
            self::CONNECTION_UNKNOWN => [
                'component' => 'Conexión o componente local aún no identificado.',
                'evidence' => 'La aplicación recibió un resultado que no puede clasificar con seguridad.',
                'cause_status' => 'posible',
                'cause' => 'No hay evidencia suficiente para atribuir una causa.',
                'checks' => [
                    'Confirma localmente el estado del servidor y la configuración con la persona administradora.',
                    'No cambies credenciales, permisos, red o base para probar hipótesis.',
                ],
                'escalate' => 'Detente y contacta soporte con esta categoría, la hora y las comprobaciones realizadas.',
            ],
        ];

        return $guidance[$category] + [
            'support_data' => [
                'categoría y hora aproximada',
                'versión de PHP y disponibilidad de mysqli',
                'host configurado sin usuario ni contraseña, puerto y nombre lógico de la base',
                'resultado de acceso web por separado desde el servidor y desde un cliente LAN',
                'comprobaciones locales ya realizadas, sin valores secretos ni datos comerciales',
            ],
        ];
    }
}
