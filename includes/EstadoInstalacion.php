<?php

/**
 * Protege la instalación limpia mediante un marcador local fuera del document root.
 */
final class EstadoInstalacion
{
    public const LISTA = 'lista';
    public const EN_PROGRESO = 'en_progreso';
    public const INCOMPLETA = 'incompleta';
    public const COMPLETADA = 'completada';
    public const DIRECTORIO_NO_CONFIGURADO = 'directorio_no_configurado';
    public const RUTA_INVALIDA = 'ruta_invalida';
    public const RUTA_PUBLICA = 'ruta_publica';
    public const SIN_ACCESO = 'sin_acceso';
    public const MARCADOR_AUSENTE = 'marcador_ausente';
    public const MARCADOR_CORRUPTO = 'marcador_corrupto';
    public const DESTINO_DISTINTO = 'destino_distinto';
    public const BLOQUEADA = 'instalacion_bloqueada';

    private ?string $directorio = null;
    private ?string $directorioConfigurado = null;
    private ?string $documentRootConfigurado = null;
    private ?string $documentRootCanonico = null;
    private ?string $rutaMarcador = null;
    private ?string $rutaBloqueo = null;
    private ?string $errorRuta = null;
    private string $huellaDestino;

    /**
     * Prepara la identidad del destino y valida la ubicación configurada.
     *
     * @param ?string $directorio Ruta local indicada por JV_INSTALL_STATE_DIR.
     * @param string $documentRoot Raíz pública efectiva del servidor web.
     * @param string $host Host de base de datos, nunca persistido en claro.
     * @param int $puerto Puerto del motor de base de datos.
     * @param string $base Nombre lógico del destino, nunca persistido en claro.
     */
    public function __construct(
        ?string $directorio,
        string $documentRoot,
        string $host,
        int $puerto,
        string $base
    ) {
        $identidad = json_encode(
            [
                'host' => strtolower(trim($host)),
                'puerto' => $puerto,
                'base' => trim($base),
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if ($identidad === false) {
            $this->errorRuta = self::RUTA_INVALIDA;
            $this->huellaDestino = '';
            return;
        }
        $this->huellaDestino = hash('sha256', $identidad);

        if ($directorio === null || trim($directorio) === '') {
            $this->errorRuta = self::DIRECTORIO_NO_CONFIGURADO;
            return;
        }
        if (!self::esRutaAbsoluta($directorio) || !is_dir($directorio)) {
            $this->errorRuta = self::RUTA_INVALIDA;
            return;
        }

        $directorioReal = realpath($directorio);
        $documentRootReal = $documentRoot !== '' ? realpath($documentRoot) : false;
        if ($directorioReal === false || $documentRootReal === false || !is_dir($directorioReal)) {
            $this->errorRuta = self::RUTA_INVALIDA;
            return;
        }
        if (self::estaDentroDe($directorioReal, $documentRootReal)) {
            $this->errorRuta = self::RUTA_PUBLICA;
            return;
        }

        $this->directorio = $directorioReal;
        $this->directorioConfigurado = $directorio;
        $this->documentRootConfigurado = $documentRoot;
        $this->documentRootCanonico = $documentRootReal;
        $this->rutaMarcador = $directorioReal . DIRECTORY_SEPARATOR . 'estado-instalacion.json';
        $this->rutaBloqueo = $directorioReal . DIRECTORY_SEPARATOR . 'estado-instalacion.lock';
    }

    /**
     * Devuelve únicamente un estado fijo, sin exponer las rutas locales.
     *
     * @return string Estado seguro para diagnóstico.
     */
    public function diagnosticar(): string
    {
        if ($this->errorRuta !== null) {
            return $this->errorRuta;
        }
        $directorioActual = realpath((string)$this->directorioConfigurado);
        $documentRootActual = realpath((string)$this->documentRootConfigurado);
        if (
            $directorioActual === false
            || $documentRootActual === false
            || !self::rutasIguales($directorioActual, (string)$this->directorio)
            || !self::rutasIguales($documentRootActual, (string)$this->documentRootCanonico)
        ) {
            return self::RUTA_INVALIDA;
        }
        if (self::estaDentroDe($directorioActual, $documentRootActual)) {
            return self::RUTA_PUBLICA;
        }
        if (!is_readable($this->directorio) || !is_writable($this->directorio)) {
            return self::SIN_ACCESO;
        }
        if (is_link($this->rutaMarcador)) {
            return self::MARCADOR_CORRUPTO;
        }
        if (is_dir($this->rutaMarcador)) {
            return self::MARCADOR_CORRUPTO;
        }
        if (!is_file($this->rutaMarcador) || !is_readable($this->rutaMarcador)) {
            return self::MARCADOR_AUSENTE;
        }

        $contenido = @file_get_contents($this->rutaMarcador);
        if ($contenido === false || strlen($contenido) > 4096) {
            return self::MARCADOR_CORRUPTO;
        }
        $marcador = json_decode($contenido, true);
        if (!self::esMarcadorValido($marcador)) {
            return self::MARCADOR_CORRUPTO;
        }
        if ($marcador['estado'] === 'sin_iniciar') {
            return $marcador['destino_sha256'] === null ? self::LISTA : self::MARCADOR_CORRUPTO;
        }
        if (!hash_equals($this->huellaDestino, $marcador['destino_sha256'])) {
            return self::DESTINO_DISTINTO;
        }

        return match ($marcador['estado']) {
            'en_progreso' => self::EN_PROGRESO,
            'incompleta' => self::INCOMPLETA,
            'completada' => self::COMPLETADA,
            default => self::BLOQUEADA,
        };
    }

    /**
     * Serializa las confirmaciones concurrentes de instalación en este servidor.
     *
     * @param callable(self):array $accion Acción ejecutada manteniendo el bloqueo.
     * @return array Resultado fijo o resultado de la acción.
     */
    public function ejecutarConBloqueo(callable $accion): array
    {
        if ($this->diagnosticar() !== self::LISTA || $this->rutaBloqueo === null) {
            return ['estado' => $this->diagnosticar()];
        }
        if (is_link($this->rutaBloqueo)) {
            return ['estado' => self::SIN_ACCESO];
        }

        $archivoBloqueo = @fopen($this->rutaBloqueo, 'c+b');
        if ($archivoBloqueo === false) {
            return ['estado' => self::SIN_ACCESO];
        }

        try {
            if (!flock($archivoBloqueo, LOCK_EX)) {
                return ['estado' => self::SIN_ACCESO];
            }
            if ($this->diagnosticar() !== self::LISTA) {
                return ['estado' => $this->diagnosticar()];
            }

            return $accion($this);
        } finally {
            flock($archivoBloqueo, LOCK_UN);
            fclose($archivoBloqueo);
        }
    }

    /**
     * Persiste y relee el estado antes de permitir que comience la importación.
     *
     * @return bool True únicamente si el estado en_progreso quedó verificado.
     */
    public function iniciar(): bool
    {
        return $this->diagnosticar() === self::LISTA
            && $this->guardar('en_progreso')
            && $this->diagnosticar() === self::EN_PROGRESO;
    }

    /**
     * Conserva un bloqueo durable después de detectar un fallo de instalación.
     *
     * @return bool True si quedó registrado el estado incompleta.
     */
    public function marcarIncompleta(): bool
    {
        return $this->diagnosticar() === self::EN_PROGRESO
            && $this->guardar('incompleta')
            && $this->diagnosticar() === self::INCOMPLETA;
    }

    /**
     * Completa el marcador solo tras verificar el esquema importado.
     *
     * @return bool True si quedó registrado el estado completada.
     */
    public function marcarCompletada(): bool
    {
        return $this->diagnosticar() === self::EN_PROGRESO
            && $this->guardar('completada')
            && $this->diagnosticar() === self::COMPLETADA;
    }

    /**
     * Sustituye el JSON mediante un temporal en el mismo directorio y rename.
     *
     * @param string $estado Estado de destino permitido.
     * @return bool True si el reemplazo atómico se pudo completar.
     */
    private function guardar(string $estado): bool
    {
        if (
            $this->rutaMarcador === null
            || $this->directorio === null
            || is_link($this->rutaMarcador)
            || !in_array($estado, ['en_progreso', 'incompleta', 'completada'], true)
        ) {
            return false;
        }

        $contenido = json_encode(
            [
                'version' => 1,
                'estado' => $estado,
                'destino_sha256' => $this->huellaDestino,
                'actualizado_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            JSON_UNESCAPED_SLASHES
        );
        if ($contenido === false) {
            return false;
        }

        try {
            $temporal = $this->directorio . DIRECTORY_SEPARATOR . '.estado-instalacion-' . bin2hex(random_bytes(16)) . '.tmp';
        } catch (Throwable) {
            return false;
        }
        $archivoTemporal = @fopen($temporal, 'xb');
        if ($archivoTemporal === false) {
            return false;
        }

        $escrito = false;
        try {
            $longitud = strlen($contenido);
            $offset = 0;
            while ($offset < $longitud) {
                $cantidad = fwrite($archivoTemporal, substr($contenido, $offset));
                if ($cantidad === false || $cantidad === 0) {
                    return false;
                }
                $offset += $cantidad;
            }
            if (!fflush($archivoTemporal)) {
                return false;
            }
            if (function_exists('fsync') && !fsync($archivoTemporal)) {
                return false;
            }
            $escrito = true;
        } finally {
            fclose($archivoTemporal);
            if (!$escrito || !@rename($temporal, $this->rutaMarcador)) {
                @unlink($temporal);
                $escrito = false;
            }
        }

        return $escrito;
    }

    /**
     * Verifica estrictamente la forma y los valores admitidos por la versión 1.
     *
     * @param mixed $marcador Valor JSON decodificado.
     * @return bool True si el marcador contiene solo los campos esperados.
     */
    private static function esMarcadorValido(mixed $marcador): bool
    {
        if (
            !is_array($marcador)
            || array_keys($marcador) !== ['version', 'estado', 'destino_sha256', 'actualizado_utc']
            || $marcador['version'] !== 1
            || !in_array($marcador['estado'], ['sin_iniciar', 'en_progreso', 'incompleta', 'completada'], true)
            || !is_string($marcador['actualizado_utc'])
            || !self::esFechaUtc($marcador['actualizado_utc'])
        ) {
            return false;
        }
        if ($marcador['estado'] === 'sin_iniciar') {
            return $marcador['destino_sha256'] === null;
        }

        return is_string($marcador['destino_sha256'])
            && preg_match('/\A[a-f0-9]{64}\z/', $marcador['destino_sha256']) === 1;
    }

    /**
     * Comprueba que una ruta sea absoluta en la plataforma actual.
     *
     * @param string $ruta Ruta recibida localmente.
     * @return bool True si su forma es absoluta.
     */
    private static function esRutaAbsoluta(string $ruta): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return preg_match('/\A(?:[A-Za-z]:[\\\\\/]|\\\\\\\\[^\\\\\/]+[\\\\\/][^\\\\\/]+)/', $ruta) === 1;
        }

        return str_starts_with($ruta, DIRECTORY_SEPARATOR);
    }

    /**
     * Compara rutas canónicas respetando las reglas de Windows.
     *
     * @param string $ruta Ruta canónica candidata.
     * @param string $raiz Raíz canónica pública.
     * @return bool True si la candidata es igual o descendiente de la raíz.
     */
    private static function estaDentroDe(string $ruta, string $raiz): bool
    {
        $ruta = self::normalizarRuta($ruta);
        $raiz = self::normalizarRuta($raiz);
        if (DIRECTORY_SEPARATOR === '\\') {
            $ruta = strtolower($ruta);
            $raiz = strtolower($raiz);
        }
        if ($ruta === $raiz) {
            return true;
        }

        return str_starts_with($ruta, rtrim($raiz, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    /**
     * Compara dos rutas canónicas con semántica de mayúsculas de la plataforma.
     *
     * @param string $primera Primera ruta canónica.
     * @param string $segunda Segunda ruta canónica.
     * @return bool True si ambas rutas señalan el mismo destino canónico.
     */
    private static function rutasIguales(string $primera, string $segunda): bool
    {
        $primera = self::normalizarRuta($primera);
        $segunda = self::normalizarRuta($segunda);
        if (DIRECTORY_SEPARATOR === '\\') {
            $primera = strtolower($primera);
            $segunda = strtolower($segunda);
        }

        return $primera === $segunda;
    }

    /**
     * Valida fecha/hora UTC, no solo su forma textual.
     *
     * @param string $fecha Fecha ISO 8601 con sufijo UTC Z.
     * @return bool True si es una fecha UTC real en el formato del marcador.
     */
    private static function esFechaUtc(string $fecha): bool
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $fecha) !== 1) {
            return false;
        }
        $fechaParseada = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $fecha, new DateTimeZone('UTC'));
        $errores = DateTimeImmutable::getLastErrors();

        return $fechaParseada !== false
            && ($errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0))
            && $fechaParseada->format('Y-m-d\TH:i:s\Z') === $fecha;
    }

    /**
     * Unifica separadores para comparar límites de directorio sin falsos prefijos.
     *
     * @param string $ruta Ruta canónica.
     * @return string Ruta con separadores de la plataforma.
     */
    private static function normalizarRuta(string $ruta): string
    {
        $ruta = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta);
        $rutaSinFinal = rtrim($ruta, DIRECTORY_SEPARATOR);
        if ($rutaSinFinal === '' || preg_match('/\A[A-Za-z]:\z/', $rutaSinFinal) === 1) {
            return $rutaSinFinal . DIRECTORY_SEPARATOR;
        }

        return $rutaSinFinal;
    }
}
