<?php

require_once __DIR__ . '/SchemaCompatibility.php';
require_once __DIR__ . '/EstadoInstalacion.php';

/**
 * Coordina la instalación inicial después de autorización y validaciones explícitas.
 */
final class InstaladorInicial
{
    public const REQUIERE_CONFIRMACION = 'requiere_confirmacion';
    public const CSRF_INVALIDO = 'csrf_invalido';
    public const DESTINO_EXISTENTE = 'destino_existente';
    public const MARCADOR_NO_VALIDO = 'marcador_no_valido';
    public const INCOMPLETA = 'incompleta';
    public const ESQUEMA_INCOMPATIBLE = 'esquema_incompatible';
    public const LISTA = 'lista';

    /**
     * Ejecuta la importación solo con POST, confirmación, CSRF válido y destino vacío.
     *
     * @param string $metodo Método HTTP de la solicitud.
     * @param bool $confirmada Confirmación deliberada de instalación limpia.
     * @param bool $csrfValido Resultado de validar CSRF.
     * @param string $estadoDestino Estado recién observado: ausente, vacio o existente.
     * @param callable():bool $instalar Importador que vuelve a comprobar el destino.
     * @param callable():array $verificarEsquema Verificador del perfil importado.
     * @param callable():void $iniciarAplicacion Acción de éxito posterior a la validación.
     * @param ?EstadoInstalacion $estadoInstalacion Marcador privado del destino.
     * @return array{estado:string}
     */
    public static function procesar(
        string $metodo,
        bool $confirmada,
        bool $csrfValido,
        string $estadoDestino,
        callable $instalar,
        callable $verificarEsquema,
        callable $iniciarAplicacion,
        ?EstadoInstalacion $estadoInstalacion = null
    ): array {
        if ($metodo !== 'POST') {
            return ['estado' => self::REQUIERE_CONFIRMACION];
        }
        if (!$csrfValido) {
            return ['estado' => self::CSRF_INVALIDO];
        }
        if (!$confirmada) {
            return ['estado' => self::REQUIERE_CONFIRMACION];
        }
        if (!in_array($estadoDestino, ['ausente', 'vacio'], true)) {
            return ['estado' => self::DESTINO_EXISTENTE];
        }
        if (!$estadoInstalacion instanceof EstadoInstalacion) {
            return ['estado' => self::MARCADOR_NO_VALIDO, 'motivo' => EstadoInstalacion::DIRECTORIO_NO_CONFIGURADO];
        }

        $resultado = $estadoInstalacion->ejecutarConBloqueo(
            static function (EstadoInstalacion $marcador) use (
                $instalar,
                $verificarEsquema,
                $iniciarAplicacion
            ): array {
                if (!$marcador->iniciar()) {
                    return [
                        'estado' => self::MARCADOR_NO_VALIDO,
                        'motivo' => $marcador->diagnosticar() === EstadoInstalacion::LISTA
                            ? EstadoInstalacion::SIN_ACCESO
                            : $marcador->diagnosticar(),
                    ];
                }

                try {
                    if (!$instalar()) {
                        $marcador->marcarIncompleta();
                        return ['estado' => self::INCOMPLETA];
                    }
                } catch (Throwable) {
                    $marcador->marcarIncompleta();
                    return ['estado' => self::INCOMPLETA];
                }

                try {
                    $comparacion = $verificarEsquema();
                } catch (Throwable) {
                    $marcador->marcarIncompleta();
                    return ['estado' => self::ESQUEMA_INCOMPATIBLE];
                }
                if (($comparacion['status'] ?? null) !== SchemaCompatibility::COMPATIBLE) {
                    $marcador->marcarIncompleta();
                    return ['estado' => self::ESQUEMA_INCOMPATIBLE];
                }
                if (!$marcador->marcarCompletada()) {
                    return ['estado' => self::INCOMPLETA];
                }

                $iniciarAplicacion();
                return ['estado' => self::LISTA];
            }
        );

        if (!in_array($resultado['estado'] ?? null, [
            self::LISTA,
            self::INCOMPLETA,
            self::ESQUEMA_INCOMPATIBLE,
            self::MARCADOR_NO_VALIDO,
        ], true)) {
            return [
                'estado' => self::MARCADOR_NO_VALIDO,
                'motivo' => (string)($resultado['estado'] ?? EstadoInstalacion::BLOQUEADA),
            ];
        }

        return $resultado;
    }
}
