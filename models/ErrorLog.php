<?php

// ==========================================
// MODEL: ErrorLog — el diario de errores
// ==========================================
// Es la pieza de observabilidad del sistema: cuando algo falla (en el
// servidor o en el navegador de una usuaria), el error queda registrado
// aquí para que el administrador lo descubra ANTES que la persona usuaria
// lo reporte. Nunca lanza excepciones: un fallo al registrar un error no
// puede empeorar el error original (cae al error_log de Apache).

/**
 * ErrorLog: registro agrupado y consultable de errores del sistema.
 *
 * Cada error se agrupa por una "huella" (fingerprint) estable: si el mismo
 * fallo ocurre cincuenta veces, la tabla guarda una sola fila con contador
 * de ocurrencias y la primera/última vez. Avisa por correo al administrador
 * cuando aparece una huella nueva (con tope de 1 aviso por hora) y purga
 * los registros viejos para que la tabla no crezca sin fin.
 */
class ErrorLog extends Model
{
    /** Días que se conserva un error antes de ser purgado. */
    private const DIAS_RETENCION = 30;

    /** Segundos mínimos entre correos de aviso para la misma huella. */
    private const CADA_AVISO_SEGUNDOS = 3600;

    /** Directorio de cache (banderas de aviso y demás artefactos de tiempo). */
    private const CACHE_DIR = __DIR__ . '/../cache';

    /**
     * Registra una excepción capturada por los límites de error (servidor).
     *
     * @param Throwable $e Error capturado en Router o en el handler global.
     * @return void
     */
    public function registrar(Throwable $e): void
    {
        $this->registrarFila([
            'origen'  => 'servidor',
            'clase'   => mb_substr(get_class($e), 0, 150),
            'mensaje' => mb_substr($e->getMessage(), 0, 800),
            'archivo' => mb_substr($e->getFile(), 0, 255),
            'linea'   => $e->getLine(),
            'stack'   => mb_substr($e->getTraceAsString(), 0, 4000),
        ]);
    }

    /**
     * Registra un error reportado desde el navegador (boundary de diseno.js).
     *
     * Los campos llegan por POST ya sanitizados; aquí solo se acotan las
     * longitudes para que un detalle gigante no distorsione la tabla.
     *
     * @param array $datos mensaje (obligatorio), archivo, linea y stack.
     * @return void
     */
    public function registrarCliente(array $datos): void
    {
        $mensaje = trim((string)($datos['mensaje'] ?? ''));
        if ($mensaje === '') {
            return; // Sin detalle no hay nada diagnosticable.
        }
        $this->registrarFila([
            'origen'  => 'navegador',
            'clase'   => null,
            'mensaje' => mb_substr($mensaje, 0, 800),
            'archivo' => mb_substr((string)($datos['archivo'] ?? ''), 0, 255),
            'linea'   => (int)($datos['linea'] ?? 0) ?: null,
            'stack'   => mb_substr((string)($datos['stack'] ?? ''), 0, 4000),
        ]);
    }

    /**
     * Inserta la fila (o suma una ocurrencia si la huella ya existía).
     *
     * Calcula contexto de la petición, agrupa por huella, purga con poca
     * frecuencia (1 de cada 20 registros) y avisa por correo solo cuando la
     * huella es nueva. Toda la operación es silenciosa: ante cualquier fallo
     * deja la evidencia en el error_log y nunca interrumpe la respuesta.
     *
     * @param array $fila Campos ya recortados: origen, clase, mensaje,
     *   archivo, linea y stack.
     * @return void
     */
    private function registrarFila(array $fila): void
    {
        try {
            $huella = $this->calcularHuella($fila);
            $url = mb_substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 255);
            $agente = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

            $this->db->execute(
                "INSERT INTO errores (huella, origen, clase, mensaje, archivo, linea, url, metodo, es_ajax, id_usuario, rol, agente, stack, ocurrencias, primera_vez, ultima_vez)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE ocurrencias = ocurrencias + 1, ultima_vez = NOW()",
                [
                    $huella,
                    $fila['origen'],
                    $fila['clase'],
                    $fila['mensaje'],
                    $fila['archivo'] !== '' ? $fila['archivo'] : null,
                    $fila['linea'],
                    $url !== '' ? $url : null,
                    mb_substr((string)($_SERVER['REQUEST_METHOD'] ?? ''), 0, 10) ?: null,
                    !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                        && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ? 1 : 0,
                    (int)($_SESSION['id_usuario'] ?? 0) ?: null,
                    isset($_SESSION['id_rol']) ? (int)$_SESSION['id_rol'] : null,
                    $agente !== '' ? $agente : null,
                    $fila['stack'] !== '' ? $fila['stack'] : null,
                ]
            );
            $es_nuevo = $this->db->affectedRows() === 1;

            if (random_int(1, 20) === 1) {
                $this->purgar();
            }
            if ($es_nuevo) {
                $this->avisarCorreo([
                    'huella'   => $huella,
                    'origen'   => $fila['origen'],
                    'clase'    => $fila['clase'],
                    'mensaje'  => $fila['mensaje'],
                    'archivo'  => $fila['archivo'],
                    'linea'    => $fila['linea'],
                ]);
            }
        } catch (Throwable $fallo_registro) {
            error_log('[JV3000] No se pudo registrar el error en el diario: ' . $fallo_registro->getMessage());
        }
    }

    /**
     * Calcula la huella estable de un error para agrupar ocurrencias.
     *
     * Usa origen + clase + archivo:línea cuando hay archivo (estable aunque
     * el mensaje traiga IDs variables); si no, recurre al inicio del mensaje.
     *
     * @param array $fila Campos del error.
     * @return string Huella MD5 de 32 caracteres.
     */
    private function calcularHuella(array $fila): string
    {
        if ((string)$fila['archivo'] !== '') {
            $base = $fila['origen'] . '|' . $fila['clase'] . '|' . $fila['archivo'] . ':' . $fila['linea'];
        } else {
            $base = $fila['origen'] . '|' . $fila['clase'] . '|' . mb_substr($fila['mensaje'], 0, 120);
        }
        return md5($base);
    }

    /**
     * Elimina errores viejos: resueltos con más de 7 días o no resueltos con
     * más del plazo de retención.
     *
     * @return void
     */
    public function purgar(): void
    {
        $this->db->execute(
            "DELETE FROM errores WHERE (resuelto = 1 AND ultima_vez < NOW() - INTERVAL 7 DAY) OR ultima_vez < NOW() - INTERVAL " . (int)self::DIAS_RETENCION . " DAY",
            []
        );
    }

    /**
     * Cuenta los errores activos (no resueltos), para el pill del panel.
     *
     * @return int Cantidad de huellas activas.
     */
    public function contarActivos(): int
    {
        $fila = $this->db->fetchOne("SELECT COUNT(*) AS total FROM errores WHERE resuelto = 0");
        return (int)($fila['total'] ?? 0);
    }

    /**
     * Lista errores con filtros y paginación (más recientes primero).
     *
     * @param array $filtros origen ('servidor'|'navegador'|''), estado
     *   ('activos'|'resueltos'|'') y buscar (texto libre).
     * @param int   $pagina   Página 1-based.
     * @param int   $porPagina Filas por página.
     * @return array Filas resumidas para la tabla del panel.
     */
    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        [$condicion, $params] = $this->condicionFiltros($filtros);
        $offset = max(0, ($pagina - 1) * $porPagina);
        return $this->db->fetchAll(
            "SELECT id_error, origen, clase, mensaje, archivo, linea, url, es_ajax, id_usuario, rol, ocurrencias, primera_vez, ultima_vez, resuelto
             FROM errores {$condicion}
             ORDER BY resuelto ASC, ultima_vez DESC
             LIMIT {$offset}, {$porPagina}",
            $params
        );
    }

    /**
     * Cuenta cuántas huellas cumplen los filtros (para la paginación).
     *
     * @param array $filtros Mismos filtros de listar().
     * @return int Total de filas.
     */
    public function totalErrores(array $filtros): int
    {
        [$condicion, $params] = $this->condicionFiltros($filtros);
        $fila = $this->db->fetchOne("SELECT COUNT(*) AS total FROM errores {$condicion}", $params);
        return (int)($fila['total'] ?? 0);
    }

    /**
     * Devuelve el detalle completo de un error (incluido el stack).
     *
     * @param int $idError Identificador de la fila.
     * @return array|null Fila completa o null si no existe.
     */
    public function detalle(int $idError): ?array
    {
        return $this->db->fetchOne("SELECT * FROM errores WHERE id_error = ? LIMIT 1", [$idError]);
    }

    /**
     * Marca un error como resuelto.
     *
     * @param int $idError Identificador de la fila.
     * @return bool True si la fila existía y se actualizó.
     */
    public function marcarResuelto(int $idError): bool
    {
        return $this->db->update('errores', ['resuelto' => 1], 'id_error = ?', [$idError]) > 0;
    }

    /**
     * Construye el WHERE y los parámetros según los filtros del panel.
     *
     * @param array $filtros origen, estado y buscar.
     * @return array [condicion SQL (con "WHERE" o vacío), parámetros].
     */
    private function condicionFiltros(array $filtros): array
    {
        $condiciones = [];
        $params = [];
        if (in_array($filtros['origen'] ?? '', ['servidor', 'navegador'], true)) {
            $condiciones[] = 'origen = ?';
            $params[] = $filtros['origen'];
        }
        if (($filtros['estado'] ?? '') === 'activos') {
            $condiciones[] = 'resuelto = 0';
        } elseif (($filtros['estado'] ?? '') === 'resueltos') {
            $condiciones[] = 'resuelto = 1';
        }
        $buscar = trim((string)($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $condiciones[] = '(mensaje LIKE ? OR archivo LIKE ? OR clase LIKE ? OR url LIKE ?)';
            $como = '%' . $buscar . '%';
            array_push($params, $como, $como, $como, $como);
        }
        return [$condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones), $params];
    }

    /**
     * Avisa por correo al administrador cuando aparece un error nuevo.
     *
     * Solo actúa si existe la clave JV_MAIL_ADMIN, hay ruta al servidor de
     * correo y no se avisó por la misma huella en la última hora (bandera en
     * cache/). Un fallo de envío queda en error_log y nunca interrumpe.
     *
     * @param array $fila Resumen del error nuevo (huella, origen, clase,
     *   mensaje, archivo, linea).
     * @return void
     */
    public function avisarCorreo(array $fila): void
    {
        try {
            if (!defined('JV_MAIL_ADMIN') || JV_MAIL_ADMIN === '') {
                return; // Sin destinatario configurado: el aviso vive en el panel.
            }
            $bandera = self::CACHE_DIR . '/aviso_error_' . $fila['huella'] . '.json';
            if (file_exists($bandera)) {
                $previo = json_decode((string)file_get_contents($bandera), true);
                if (is_array($previo) && (time() - (int)($previo['aviso'] ?? 0)) < self::CADA_AVISO_SEGUNDOS) {
                    return; // Ya se avisó por esta huella hace poco.
                }
            }
            if (!is_dir(self::CACHE_DIR)) {
                mkdir(self::CACHE_DIR, 0755, true);
            }
            file_put_contents($bandera, json_encode(['aviso' => time()]));

            require_once __DIR__ . '/Mail.php';
            if (!Mail::hayInternet()) {
                return;
            }
            $cuerpo = "Error nuevo registrado en JV3000.\n\n"
                . 'Origen: ' . $fila['origen'] . "\n"
                . 'Clase: ' . ($fila['clase'] !== null && $fila['clase'] !== '' ? $fila['clase'] : '(no aplica)') . "\n"
                . 'Mensaje: ' . $fila['mensaje'] . "\n"
                . 'Ubicación: ' . ($fila['archivo'] !== '' ? $fila['archivo'] . ':' . $fila['linea'] : '(navegador)') . "\n"
                . 'Cuándo: ' . date('Y-m-d H:i') . "\n\n"
                . 'Consulta el panel Diagnóstico (sección Control) para el detalle completo.';
            $resultado = Mail::enviar(
                JV_MAIL_ADMIN,
                '[JV3000] Error nuevo (' . $fila['origen'] . '): ' . mb_substr($fila['mensaje'], 0, 60),
                $cuerpo
            );
            if (!($resultado['ok'] ?? false)) {
                error_log('[JV3000] El aviso de error no pudo enviarse: ' . (string)($resultado['mensaje'] ?? 'sin detalle'));
            }
        } catch (Throwable $fallo_aviso) {
            error_log('[JV3000] El aviso de error falló: ' . $fallo_aviso->getMessage());
        }
    }
}
