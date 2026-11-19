<?php

// ==========================================
// MODELO: Backup y restauración
// ==========================================
// Genera volcados SQL con estructura y datos,
// valida los archivos .sql y los restaura.

/**
 * Backup: volcado y restauración de la base de datos.
 *
 * El volcado se genera en PHP puro (sin exec ni mysqldump) para funcionar en
 * instalaciones donde las funciones de proceso están deshabilitadas. La
 * restauración valida el archivo completo antes de tocar la base, crea un
 * respaldo de seguridad, aplica la estructura y luego inserta los datos dentro
 * de una transacción.
 *
 * Limitación conocida: en MariaDB/MySQL las sentencias DDL (DROP/CREATE/ALTER)
 * provocan un commit implícito y no se pueden revertir con rollback. Por eso el
 * respaldo previo es la red de seguridad real ante un fallo a mitad del DDL.
 */
class Backup extends Model
{
    /** Tamaño máximo aceptado para un archivo de restauración (10 MB). */
    private const TAMANO_MAXIMO = 10485760;

    /** Filas por cada INSERT generado en el volcado. */
    private const FILAS_POR_LOTE = 100;

    /** Bytes acumulados antes de escribir el buffer en disco. */
    private const BYTES_BUFFER = 1048576;

    /** Tablas mínimas que debe contener un volcado de JV3000. */
    private const TABLAS_NUCLEO = ['usuarios', 'productos', 'configuracion', 'compras', 'salidas'];

    /**
     * Tamaño máximo de carga en megabytes, para mostrarlo en la vista.
     *
     * @return int
     */
    public function maxMb(): int
    {
        return (int)(self::TAMANO_MAXIMO / 1048576);
    }

    /**
     * Cantidad de tablas base de la base de datos actual.
     *
     * @return int
     */
    public function totalTablas(): int
    {
        return count($this->tablasBase());
    }

    /**
     * Escribe un volcado completo (estructura y datos) en el destino indicado.
     *
     * El destino puede ser una ruta de archivo o el flujo php://output. Se
     * escribe por tablas, sin cargar todas las filas en memoria.
     *
     * @param string $destino Ruta o flujo de escritura.
     * @return void
     */
    public function volcarA(string $destino): void
    {
        $conn = $this->db->getConnection();
        if (!$conn) {
            throw new RuntimeException('No hay conexión con la base de datos.');
        }

        $salida = fopen($destino, 'wb');
        if ($salida === false) {
            throw new RuntimeException('No se pudo abrir el destino del respaldo.');
        }

        try {
            $tablas = $this->tablasBase();
            fwrite($salida, "-- JV3000 C.A. - Respaldo de base de datos\n");
            fwrite($salida, '-- Generado: ' . date('Y-m-d H:i:s') . "\n");
            fwrite($salida, '-- Base de datos: ' . DB_NAME . "\n");
            fwrite($salida, '-- Tablas: ' . count($tablas) . "\n");
            fwrite($salida, "-- ADVERTENCIA: contiene datos sensibles (usuarios, contraseñas cifradas y configuración).\n");
            fwrite($salida, "SET NAMES utf8mb4;\n");
            fwrite($salida, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($tablas as $tabla) {
                $this->volcarTabla($conn, $salida, $tabla);
            }

            fwrite($salida, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($salida);
        }
    }

    /**
     * Vuelca la estructura y las filas de una tabla.
     *
     * @param mysqli      $conn   Conexión activa.
     * @param resource    $salida Flujo de escritura.
     * @param string      $tabla  Nombre de la tabla.
     * @return void
     */
    private function volcarTabla(mysqli $conn, $salida, string $tabla): void
    {
        $cita = $this->citar($tabla);

        $estructura = $conn->query("SHOW CREATE TABLE {$cita}");
        if (!$estructura instanceof mysqli_result) {
            throw new RuntimeException("No se pudo leer la estructura de {$tabla}.");
        }
        $fila = $estructura->fetch_row();
        $estructura->free();
        if (!$fila || !isset($fila[1])) {
            throw new RuntimeException("La tabla {$tabla} no devolvió su estructura.");
        }

        fwrite($salida, "DROP TABLE IF EXISTS {$cita};\n");
        fwrite($salida, $fila[1] . ";\n\n");

        $resultado = $conn->query("SELECT * FROM {$cita}", MYSQLI_USE_RESULT);
        if (!$resultado instanceof mysqli_result) {
            throw new RuntimeException("No se pudieron leer los datos de {$tabla}.");
        }

        try {
            $campos = [];
            foreach ($resultado->fetch_fields() as $campo) {
                $campos[] = $this->citar((string)$campo->name);
            }
            $lista_campos = implode(', ', $campos);

            $buffer = '';
            $lote = [];
            while (($registro = $resultado->fetch_row()) !== null) {
                $valores = [];
                foreach ($registro as $valor) {
                    $valores[] = $valor === null
                        ? 'NULL'
                        : "'" . $conn->real_escape_string((string)$valor) . "'";
                }
                $lote[] = '(' . implode(', ', $valores) . ')';

                if (count($lote) >= self::FILAS_POR_LOTE) {
                    $buffer .= "INSERT INTO {$cita} ({$lista_campos}) VALUES\n"
                        . implode(",\n", $lote) . ";\n";
                    $lote = [];
                }

                if (strlen($buffer) >= self::BYTES_BUFFER) {
                    fwrite($salida, $buffer);
                    $buffer = '';
                }
            }

            if (!empty($lote)) {
                $buffer .= "INSERT INTO {$cita} ({$lista_campos}) VALUES\n"
                    . implode(",\n", $lote) . ";\n";
            }
            if ($buffer !== '') {
                fwrite($salida, $buffer);
            }
        } finally {
            $resultado->free();
        }

        fwrite($salida, "\n");
    }

    /**
     * Valida el archivo subido antes de leer su contenido.
     *
     * @param array|null $archivo Entrada de $_FILES.
     * @return array{ok:bool,mensaje?:string,contenido?:string,nombre?:string}
     */
    public function validarArchivo(?array $archivo): array
    {
        if (!is_array($archivo) || !array_key_exists('error', $archivo)) {
            return $this->error('No se recibió ningún archivo.');
        }

        $codigo = (int)$archivo['error'];
        if ($codigo !== UPLOAD_ERR_OK) {
            $mensajes = [
                UPLOAD_ERR_INI_SIZE => 'El archivo supera el límite de carga del servidor.',
                UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite permitido por el formulario.',
                UPLOAD_ERR_PARTIAL => 'La carga del archivo se interrumpió. Inténtalo de nuevo.',
                UPLOAD_ERR_NO_FILE => 'Selecciona un archivo .sql para restaurar.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene un directorio temporal disponible.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo temporal.',
                UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la carga del archivo.',
            ];
            return $this->error($mensajes[$codigo] ?? 'No se pudo recibir el archivo (código ' . $codigo . ').');
        }

        $ruta = (string)($archivo['tmp_name'] ?? '');
        if ($ruta === '' || !is_uploaded_file($ruta)) {
            return $this->error('El archivo no llegó por una carga válida.');
        }

        $nombre = basename((string)($archivo['name'] ?? ''));
        $nombre = preg_replace('/[\x00-\x1F\x7F]/', '', $nombre) ?? '';
        if ($nombre === '' || strtolower((string)pathinfo($nombre, PATHINFO_EXTENSION)) !== 'sql') {
            return $this->error('El archivo debe tener extensión .sql.');
        }

        $tamano = (int)($archivo['size'] ?? 0);
        $tamano_real = filesize($ruta);
        if ($tamano <= 0 || $tamano_real === false || $tamano_real <= 0) {
            return $this->error('El archivo está vacío.');
        }
        if ($tamano > self::TAMANO_MAXIMO || $tamano_real > self::TAMANO_MAXIMO) {
            return $this->error('El archivo supera el máximo de ' . $this->maxMb() . ' MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return $this->error('No se pudo verificar el tipo de archivo.');
        }
        $mime = finfo_file($finfo, $ruta);
        finfo_close($finfo);
        $permitidos = ['text/plain', 'application/sql', 'application/x-sql', 'text/x-sql', 'application/octet-stream'];
        if (!is_string($mime) || !in_array(strtolower($mime), $permitidos, true)) {
            return $this->error('El tipo de archivo no corresponde a un volcado SQL.');
        }

        $contenido = file_get_contents($ruta);
        if ($contenido === false || $contenido === '') {
            return $this->error('No se pudo leer el contenido del archivo.');
        }
        if (strlen($contenido) > self::TAMANO_MAXIMO) {
            return $this->error('El archivo supera el máximo de ' . $this->maxMb() . ' MB.');
        }
        $contenido = $this->quitarBom($contenido);
        if (stripos($contenido, '<?') !== false) {
            return $this->error('El archivo contiene código PHP y fue rechazado.');
        }

        return ['ok' => true, 'contenido' => $contenido, 'nombre' => $nombre];
    }

    /**
     * Analiza el SQL completo y devuelve las sentencias ejecutables.
     *
     * Rechaza el archivo entero si encuentra una sentencia fuera de la lista
     * blanca, código PHP, tablas esenciales ausentes o referencias a tablas
     * inexistentes. No ejecuta nada.
     *
     * @param string $sql Contenido del volcado.
     * @return array{ok:bool,mensaje?:string,ddl?:array<int,string>,dml?:array<int,string>}
     */
    public function analizar(string $sql): array
    {
        $sql = $this->quitarBom($sql);
        if (trim($sql) === '') {
            return $this->error('El archivo está vacío.');
        }
        if (strlen($sql) > self::TAMANO_MAXIMO) {
            return $this->error('El archivo supera el máximo de ' . $this->maxMb() . ' MB.');
        }
        if (strpos($sql, "\0") !== false) {
            return $this->error('El archivo contiene bytes nulos y fue rechazado.');
        }
        if (stripos($sql, '<?') !== false) {
            return $this->error('El archivo contiene código PHP y fue rechazado.');
        }
        if (stripos($sql, '<script') !== false) {
            return $this->error('El archivo contiene etiquetas HTML no permitidas.');
        }
        if (preg_match('/^\s*DELIMITER\b/im', $sql)) {
            return $this->error('El archivo usa DELIMITER, que no está permitido.');
        }

        $sentencias = $this->dividirSentencias($sql);
        $ddl = [];
        $dml = [];
        $creadas = [];
        $eliminadas = [];
        $insertadas = [];

        foreach ($sentencias as $sentencia) {
            $sentencia = trim($sentencia);
            if ($sentencia === '') {
                continue;
            }

            if (preg_match('/\b(INTO\s+(OUTFILE|DUMPFILE)|LOAD_FILE\s*\(|LOAD\s+DATA)\b/i', $sentencia)) {
                return $this->error('El archivo contiene una operación de archivos no permitida.');
            }

            if (preg_match('/^(START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK|LOCK\s+TABLES|UNLOCK\s+TABLES)\b/i', $sentencia)) {
                continue;
            }

            if (preg_match('/^SET\b/i', $sentencia)) {
                if (preg_match('/^SET\s+(GLOBAL|PERSIST|PERSIST_ONLY)\b/i', $sentencia)) {
                    return $this->error('El archivo intenta modificar la configuración global del servidor.');
                }
                continue;
            }

            if (preg_match('/^(USE|CREATE\s+DATABASE|DROP\s+DATABASE|ALTER\s+DATABASE)\b/i', $sentencia)) {
                if (preg_match('/(?:USE|DATABASE)\s+`?([A-Za-z0-9_$]+)`?/i', $sentencia, $coincidencia)) {
                    if (strcasecmp($coincidencia[1], DB_NAME) !== 0) {
                        return $this->error('El archivo apunta a otra base de datos (' . $coincidencia[1] . ').');
                    }
                }
                continue;
            }

            if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_$]+)`?/i', $sentencia, $coincidencia)) {
                $creadas[] = $this->normalizarTabla($coincidencia[1]);
                $ddl[] = $sentencia;
                continue;
            }

            if (preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(.+)$/is', $sentencia, $coincidencia)) {
                foreach (preg_split('/\s*,\s*/', trim($coincidencia[1])) as $nombre) {
                    $nombre = trim($nombre, "` \t\n\r\0\x0B");
                    if (str_contains($nombre, '.')) {
                        $nombre = substr($nombre, (int)strrpos($nombre, '.') + 1);
                    }
                    $nombre = trim($nombre, "` \t\n\r\0\x0B");
                    if ($nombre !== '') {
                        $eliminadas[] = $this->normalizarTabla($nombre);
                    }
                }
                $ddl[] = $sentencia;
                continue;
            }

            if (preg_match('/^ALTER\s+TABLE\s+.+?\s+(DISABLE|ENABLE)\s+KEYS\s*$/is', $sentencia)) {
                $ddl[] = $sentencia;
                continue;
            }

            if (preg_match('/^ALTER\s+TABLE\b/i', $sentencia)) {
                return $this->error('El archivo altera la estructura de las tablas, una operación no permitida.');
            }

            if (preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`?([A-Za-z0-9_$]+)`?/i', $sentencia, $coincidencia)) {
                $insertadas[] = $this->normalizarTabla($coincidencia[1]);
                $dml[] = $sentencia;
                continue;
            }

            $resumen = preg_replace('/\s+/', ' ', substr($sentencia, 0, 60));
            return $this->error('Sentencia no permitida: ' . $resumen . '...');
        }

        if (empty($ddl) && empty($dml)) {
            return $this->error('El archivo no contiene sentencias SQL ejecutables.');
        }

        $disponibles = [];
        foreach (array_merge($this->tablasBase(), $creadas) as $tabla) {
            $disponibles[$this->normalizarTabla($tabla)] = true;
        }

        foreach (self::TABLAS_NUCLEO as $tabla) {
            if (!isset($disponibles[$tabla])) {
                return $this->error('El archivo no contiene las tablas esenciales de JV3000 (falta ' . $tabla . ').');
            }
            if (in_array($tabla, $eliminadas, true) && !in_array($tabla, $creadas, true)) {
                return $this->error('El archivo elimina la tabla esencial ' . $tabla . ' y no la vuelve a crear.');
            }
        }

        foreach ($insertadas as $tabla) {
            if (!isset($disponibles[$tabla])) {
                return $this->error('El archivo inserta en la tabla ' . $tabla . ', que no existe ni se crea en el volcado.');
            }
        }

        return ['ok' => true, 'ddl' => $ddl, 'dml' => $dml];
    }

    /**
     * Divide un volcado en sentencias respetando cadenas y comentarios.
     *
     * Un explode(';') rompe los INSERT con punto y coma dentro de un texto.
     * Esta máquina de estados distingue comillas simples, dobles, backticks,
     * comentarios de línea (-- y #) y comentarios de bloque.
     *
     * @param string $sql Contenido completo.
     * @return array<int,string> Sentencias sin el punto y coma final.
     */
    public function dividirSentencias(string $sql): array
    {
        $sentencias = [];
        $actual = '';
        $longitud = strlen($sql);
        $comilla = null;
        $comentario_linea = false;
        $comentario_bloque = false;

        for ($i = 0; $i < $longitud; $i++) {
            $caracter = $sql[$i];
            $siguiente = $i + 1 < $longitud ? $sql[$i + 1] : '';

            if ($comentario_linea) {
                if ($caracter === "\n") {
                    $comentario_linea = false;
                    $actual .= "\n";
                }
                continue;
            }

            if ($comentario_bloque) {
                if ($caracter === '*' && $siguiente === '/') {
                    $comentario_bloque = false;
                    $i++;
                }
                continue;
            }

            if ($comilla !== null) {
                $actual .= $caracter;

                if ($caracter === '\\' && $comilla !== '`') {
                    if ($siguiente !== '') {
                        $actual .= $siguiente;
                        $i++;
                    }
                    continue;
                }

                if ($caracter === $comilla) {
                    if ($siguiente === $comilla) {
                        $actual .= $siguiente;
                        $i++;
                        continue;
                    }
                    $comilla = null;
                }
                continue;
            }

            if ($caracter === "'" || $caracter === '"' || $caracter === '`') {
                $comilla = $caracter;
                $actual .= $caracter;
                continue;
            }

            if ($caracter === '-' && $siguiente === '-'
                && ($i + 2 >= $longitud || ord($sql[$i + 2]) <= 32)) {
                $comentario_linea = true;
                $actual .= ' ';
                $i++;
                continue;
            }

            if ($caracter === '#') {
                $comentario_linea = true;
                $actual .= ' ';
                continue;
            }

            if ($caracter === '/' && $siguiente === '*') {
                $comentario_bloque = true;
                $actual .= ' ';
                $i++;
                continue;
            }

            if ($caracter === ';') {
                $sentencia = trim($actual);
                if ($sentencia !== '') {
                    $sentencias[] = $sentencia;
                }
                $actual = '';
                continue;
            }

            $actual .= $caracter;
        }

        $sentencia = trim($actual);
        if ($sentencia !== '') {
            $sentencias[] = $sentencia;
        }

        return $sentencias;
    }

    /**
     * Restaura un volcado ya validado.
     *
     * Fases: analizar todo, crear respaldo de seguridad, ejecutar en una
     * transacción y verificar la integridad final.
     *
     * @param string $contenido Contenido del archivo.
     * @param string $nombre    Nombre original del archivo.
     * @return array{ok:bool,mensaje:string,respaldo?:string,advertencia?:string}
     */
    public function importar(string $contenido, string $nombre = ''): array
    {
        $analisis = $this->analizar($contenido);
        if (!$analisis['ok']) {
            return $analisis;
        }

        try {
            $respaldo = $this->crearRespaldoPrevio();
        } catch (Throwable $e) {
            return $this->error('No se pudo crear el respaldo de seguridad previo: ' . $e->getMessage());
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            return $this->error('No hay conexión con la base de datos.');
        }

        $conn->query('SET NAMES utf8mb4');
        $conn->query('SET FOREIGN_KEY_CHECKS=0');

        // Fase 1: estructura. El DDL no es transaccional en MariaDB; si falla,
        // el respaldo previo es la vía de recuperación.
        try {
            foreach ($analisis['ddl'] as $indice => $sentencia) {
                if ($conn->query($sentencia) === false) {
                    throw new RuntimeException('DDL ' . ($indice + 1) . ': ' . $conn->error);
                }
            }
        } catch (Throwable $e) {
            $conn->query('SET FOREIGN_KEY_CHECKS=1');
            return $this->error(
                'La restauración falló al aplicar la estructura. '
                . $e->getMessage()
                . ' Respaldo previo: ' . basename($respaldo)
            );
        }

        // Fase 2: datos. Aquí sí hay una transacción real: si un INSERT falla,
        // se revierten todos los datos nuevos y el esquema queda vacío.
        try {
            if (!empty($analisis['dml'])) {
                $this->db->begin();
                foreach ($analisis['dml'] as $indice => $sentencia) {
                    if ($conn->query($sentencia) === false) {
                        throw new RuntimeException('Datos ' . ($indice + 1) . ': ' . $conn->error);
                    }
                }
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $conn->query('SET FOREIGN_KEY_CHECKS=1');
            return $this->error(
                'La restauración de los datos falló y se revirtieron las inserciones. '
                . $e->getMessage()
                . ' Usa el respaldo previo si el esquema quedó incompleto: ' . basename($respaldo)
            );
        }

        $conn->query('SET FOREIGN_KEY_CHECKS=1');

        $integridad = $this->verificarIntegridad();
        $origen = $nombre !== '' ? $nombre : 'el archivo cargado';
        $mensaje = 'Base de datos restaurada desde ' . $origen
            . '. Respaldo previo: ' . basename($respaldo) . '.';

        if (!$integridad['ok']) {
            $detalle = [];
            if (!empty($integridad['faltantes'])) {
                $detalle[] = 'faltan tablas: ' . implode(', ', $integridad['faltantes']);
            }
            if ((int)$integridad['admins'] === 0) {
                $detalle[] = 'no hay un administrador activo';
            }
            $advertencia = implode('; ', $detalle);
            return [
                'ok' => true,
                'mensaje' => $mensaje . ' Advertencia: ' . $advertencia . '.',
                'respaldo' => basename($respaldo),
                'advertencia' => $advertencia,
            ];
        }

        return ['ok' => true, 'mensaje' => $mensaje, 'respaldo' => basename($respaldo)];
    }

    /**
     * Crea un volcado de seguridad antes de restaurar.
     *
     * Se guarda fuera del webroot y con nombre aleatorio para que no pueda
     * descargarse desde el navegador. La ruta se puede cambiar con la variable
     * de entorno JV_BACKUP_DIR.
     *
     * @return string Ruta absoluta del respaldo creado.
     */
    public function crearRespaldoPrevio(): string
    {
        $directorio = getenv('JV_BACKUP_DIR');
        if (!is_string($directorio) || trim($directorio) === '') {
            $directorio = dirname(APP_ROOT, 2) . DIRECTORY_SEPARATOR . 'jv3000_backups';
        }

        if (!is_dir($directorio) && !mkdir($directorio, 0700, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio de respaldos.');
        }

        $archivo = $directorio . DIRECTORY_SEPARATOR
            . 'jv3000_pre_restore_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';

        $this->volcarA($archivo);
        @chmod($archivo, 0600);

        if (!is_file($archivo) || filesize($archivo) === 0) {
            throw new RuntimeException('El respaldo de seguridad quedó vacío.');
        }

        return $archivo;
    }

    /**
     * Comprueba que la base restaurada tenga lo mínimo para operar.
     *
     * @return array{ok:bool,faltantes:array<int,string>,admins:int}
     */
    public function verificarIntegridad(): array
    {
        $tablas = array_map([$this, 'normalizarTabla'], $this->tablasBase());
        $faltantes = [];
        foreach (self::TABLAS_NUCLEO as $tabla) {
            if (!in_array($tabla, $tablas, true)) {
                $faltantes[] = $tabla;
            }
        }

        $admins = 0;
        if (empty($faltantes)) {
            try {
                $fila = $this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM usuarios WHERE id_rol = 1 AND status = 'Activo'"
                );
                $admins = (int)($fila['total'] ?? 0);
            } catch (Throwable) {
                $admins = 0;
            }
        }

        return ['ok' => empty($faltantes) && $admins > 0, 'faltantes' => $faltantes, 'admins' => $admins];
    }

    /**
     * Lista las tablas base de la base de datos actual.
     *
     * @return array<int,string>
     */
    private function tablasBase(): array
    {
        $conn = $this->db->getConnection();
        if (!$conn) {
            throw new RuntimeException('No hay conexión con la base de datos.');
        }

        $resultado = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        if (!$resultado instanceof mysqli_result) {
            throw new RuntimeException('No se pudo listar las tablas de la base de datos.');
        }

        $tablas = [];
        while (($fila = $resultado->fetch_row()) !== null) {
            $tablas[] = (string)$fila[0];
        }
        $resultado->free();
        sort($tablas, SORT_NATURAL | SORT_FLAG_CASE);

        return $tablas;
    }

    /**
     * Cita un identificador para evitar inyección en nombres de tabla/columna.
     *
     * @param string $nombre Identificador original.
     * @return string Identificador entre backticks.
     */
    private function citar(string $nombre): string
    {
        return '`' . str_replace('`', '``', $nombre) . '`';
    }

    /**
     * Elimina la marca BOM UTF-8 que añaden algunos editores de Windows.
     *
     * Sin esto, el primer SET del volcado se interpretaría como una sentencia
     * desconocida y un archivo válido guardado con Bloc de notas sería
     * rechazado.
     *
     * @param string $sql Contenido original.
     * @return string Contenido sin BOM.
     */
    private function quitarBom(string $sql): string
    {
        return str_starts_with($sql, "\xEF\xBB\xBF") ? substr($sql, 3) : $sql;
    }

    /**
     * Normaliza un nombre de tabla para comparaciones.
     *
     * @param string $nombre Nombre original.
     * @return string Nombre en minúsculas.
     */
    private function normalizarTabla(string $nombre): string
    {
        return strtolower(trim($nombre));
    }

    /**
     * Construye una respuesta de error uniforme.
     *
     * @param string $mensaje Texto para el usuario.
     * @return array{ok:bool,mensaje:string}
     */
    private function error(string $mensaje): array
    {
        return ['ok' => false, 'mensaje' => $mensaje];
    }
}
