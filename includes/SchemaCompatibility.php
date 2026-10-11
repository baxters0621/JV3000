<?php

/**
 * Analiza perfiles estructurales de esquemas sin conectarse a una base de datos.
 */
final class SchemaCompatibility
{
    public const COMPATIBLE = 'compatible';
    public const INCOMPLETE = 'incomplete';
    public const OUTDATED_KNOWN = 'outdated_known';
    public const UNKNOWN = 'unknown';

    /**
     * Construye un perfil de metadatos desde sentencias CREATE TABLE.
     *
     * @param string $sql SQL de referencia versionado dentro del proyecto.
     * @param string $version Identificador humano del perfil.
     * @return array Perfil estructural con tablas, columnas, índices y claves foráneas.
     */
    public static function profileFromSql(string $sql, string $version): array
    {
        preg_match_all(
            '/CREATE\s+TABLE\s+`((?:``|[^`])+)`\s*\(/i',
            $sql,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        $tables = [];

        foreach ($matches[0] as $index => [$statement, $statementOffset]) {
            $tableName = str_replace('``', '`', $matches[1][$index][0]);
            $openingOffset = strpos($sql, '(', $statementOffset + strlen($statement) - 1);
            if ($openingOffset === false) {
                throw new InvalidArgumentException('Definición de tabla sin paréntesis de apertura.');
            }

            [$body, $closingOffset] = self::readParenthesizedBody($sql, $openingOffset);
            if (isset($tables[$tableName])) {
                throw new InvalidArgumentException('Nombre de tabla duplicado en el perfil SQL.');
            }

            $table = [
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'engine' => null,
                'charset' => null,
                'collation' => null,
            ];

            foreach (self::splitDefinitions($body) as $definition) {
                $definition = trim($definition);
                if (preg_match('/^`((?:``|[^`])+)`\s+(.+)$/s', $definition, $columnMatch) === 1) {
                    $columnName = str_replace('``', '`', $columnMatch[1]);
                    if (isset($table['columns'][$columnName])) {
                        throw new InvalidArgumentException('Columna duplicada en el perfil SQL.');
                    }
                    $table['columns'][$columnName] = self::parseColumn($columnMatch[2]);
                } elseif (preg_match('/^PRIMARY\s+KEY\b/i', $definition) === 1) {
                    $table['indexes']['PRIMARY'] = self::parseIndex($definition, 'PRIMARY');
                } elseif (preg_match('/^(?:UNIQUE\s+)?(?:KEY|INDEX)\b/i', $definition) === 1) {
                    $parsedIndex = self::parseIndex($definition);
                    $table['indexes'][$parsedIndex['name']] = $parsedIndex;
                } elseif (preg_match('/^CONSTRAINT\b/i', $definition) === 1) {
                    $foreignKey = self::parseForeignKey($definition);
                    if ($foreignKey !== null) {
                        $table['foreign_keys'][$foreignKey['name']] = $foreignKey;
                    }
                } else {
                    throw new InvalidArgumentException('Definición estructural SQL no soportada.');
                }
            }

            $tailStart = $closingOffset + 1;
            $tailEnd = strpos($sql, ';', $tailStart);
            $tail = substr($sql, $tailStart, $tailEnd === false ? null : $tailEnd - $tailStart);
            if (preg_match('/\bENGINE\s*=\s*([a-z0-9_]+)/i', $tail, $engineMatch) === 1) {
                $table['engine'] = strtolower($engineMatch[1]);
            }
            if (preg_match('/\bDEFAULT\s+CHARSET\s*=\s*([a-z0-9_]+)/i', $tail, $charsetMatch) === 1) {
                $table['charset'] = strtolower($charsetMatch[1]);
            }
            if (preg_match('/\bCOLLATE\s*=\s*([a-z0-9_]+)/i', $tail, $collationMatch) === 1) {
                $table['collation'] = strtolower($collationMatch[1]);
            }

            $tables[$tableName] = $table;
        }

        if ($tables === []) {
            throw new InvalidArgumentException('El SQL de referencia no contiene tablas CREATE TABLE.');
        }

        return self::normalizeProfile(['version' => $version, 'tables' => $tables]);
    }

    /**
     * Compara metadatos observados con el perfil actual y perfiles antiguos conocidos.
     *
     * @param array $actual Perfil observado, sin datos de filas.
     * @param array $current Perfil de esquema que debe usar la aplicación.
     * @param array $knownProfiles Perfiles históricos verificados indexados por versión.
     * @return array Estado compatible, incompleto, anterior conocido o desconocido.
     */
    public static function compare(array $actual, array $current, array $knownProfiles = []): array
    {
        try {
            $actual = self::normalizeProfile($actual);
            $current = self::normalizeProfile($current);
            if (self::sameStructure($actual, $current)) {
                return [
                    'status' => self::COMPATIBLE,
                    'version' => $current['version'],
                    'differences' => [],
                ];
            }

            foreach ($knownProfiles as $version => $profile) {
                $profile = self::normalizeProfile($profile);
                if (self::sameStructure($actual, $profile)) {
                    return [
                        'status' => self::OUTDATED_KNOWN,
                        'version' => (string)$version,
                        'differences' => [],
                    ];
                }
            }

            $differences = self::findDifferences($actual, $current);
            return [
                'status' => self::containsOnlyMissingObjects($differences)
                    ? self::INCOMPLETE
                    : self::UNKNOWN,
                'version' => null,
                'differences' => $differences,
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'status' => self::UNKNOWN,
                'version' => null,
                'differences' => ['invalid_profile'],
            ];
        }
    }

    /**
     * Lee un cuerpo SQL equilibrando paréntesis y respetando cadenas/comentarios entrecomillados.
     *
     * @param string $sql SQL completo.
     * @param int $openingOffset Posición del paréntesis inicial.
     * @return array{0:string,1:int} Cuerpo y posición del paréntesis final.
     */
    private static function readParenthesizedBody(string $sql, int $openingOffset): array
    {
        $depth = 0;
        $quote = null;
        $length = strlen($sql);

        for ($offset = $openingOffset; $offset < $length; $offset++) {
            $character = $sql[$offset];
            if ($quote !== null) {
                if ($character === '\\') {
                    $offset++;
                    continue;
                }
                if ($character === $quote) {
                    if (($sql[$offset + 1] ?? null) === $quote) {
                        $offset++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
                if ($depth === 0) {
                    return [substr($sql, $openingOffset + 1, $offset - $openingOffset - 1), $offset];
                }
            }
        }

        throw new InvalidArgumentException('Definición de tabla con paréntesis sin cerrar.');
    }

    /**
     * Separa elementos del cuerpo de tabla por comas de nivel superior.
     *
     * @param string $body Cuerpo interno de CREATE TABLE.
     * @return array Elementos SQL en orden.
     */
    private static function splitDefinitions(string $body): array
    {
        $definitions = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($body);

        for ($offset = 0; $offset < $length; $offset++) {
            $character = $body[$offset];
            if ($quote !== null) {
                if ($character === '\\') {
                    $offset++;
                    continue;
                }
                if ($character === $quote) {
                    if (($body[$offset + 1] ?? null) === $quote) {
                        $offset++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $definitions[] = substr($body, $start, $offset - $start);
                $start = $offset + 1;
            }
        }

        $definitions[] = substr($body, $start);
        return $definitions;
    }

    /**
     * Extrae las propiedades estructurales de una columna SQL.
     *
     * @param string $definition Tipo y opciones de columna.
     * @return array Tipo, nulabilidad, default y atributos estructurales.
     */
    private static function parseColumn(string $definition): array
    {
        if (preg_match('/^([a-zA-Z]+(?:\s*\([^)]*\))?)\s*(.*)$/s', trim($definition), $matches) !== 1) {
            throw new InvalidArgumentException('Tipo de columna no reconocido en el perfil SQL.');
        }

        $type = preg_replace('/\s+/', '', $matches[1]);
        $type = preg_replace_callback('/^[a-z]+/i', static fn(array $match): string => strtolower($match[0]), $type);
        $options = $matches[2];
        $default = null;
        if (preg_match(
            '/\bDEFAULT\s+(NULL|CURRENT_TIMESTAMP(?:\(\d*\))?|\'(?:\\\\.|\'\'|[^\'])*\'|"(?:\\\\.|""|[^"])*"|[-+]?\d+(?:\.\d+)?)/i',
            $options,
            $defaultMatch
        ) === 1) {
            $default = self::normalizeDefault($defaultMatch[1]);
        }

        preg_match('/\bCHARACTER\s+SET\s+([a-z0-9_]+)/i', $options, $charsetMatch);
        preg_match('/\bCOLLATE\s+([a-z0-9_]+)/i', $options, $collationMatch);
        preg_match('/\bON\s+UPDATE\s+(CURRENT_TIMESTAMP(?:\(\d*\))?)/i', $options, $onUpdateMatch);
        preg_match_all('/\bCHECK\s*\(/i', $options, $checkMatches, PREG_OFFSET_CAPTURE);
        $checks = [];
        foreach ($checkMatches[0] as [$checkStart, $checkOffset]) {
            $openingOffset = strpos($options, '(', $checkOffset + strlen($checkStart) - 1);
            if ($openingOffset === false) {
                throw new InvalidArgumentException('Restricción CHECK incompleta en el perfil SQL.');
            }
            [$checkBody] = self::readParenthesizedBody($options, $openingOffset);
            $checks[] = strtolower(preg_replace('/\s+/', '', $checkBody));
        }

        return [
            'type' => $type,
            'unsigned' => preg_match('/\bUNSIGNED\b/i', $options) === 1,
            'nullable' => preg_match('/\bNOT\s+NULL\b/i', $options) !== 1,
            'default' => $default,
            'auto_increment' => preg_match('/\bAUTO_INCREMENT\b/i', $options) === 1,
            'charset' => strtolower($charsetMatch[1] ?? ''),
            'collation' => strtolower($collationMatch[1] ?? ''),
            'on_update' => strtolower($onUpdateMatch[1] ?? ''),
            'checks' => $checks,
        ];
    }

    /**
     * Normaliza constantes de default del dialecto MySQL.
     *
     * @param string $default Valor DEFAULT extraído del SQL.
     * @return string|null Valor normalizado.
     */
    private static function normalizeDefault(string $default): ?string
    {
        $default = trim($default);
        if (strcasecmp($default, 'NULL') === 0) {
            return null;
        }
        if (preg_match('/^CURRENT_TIMESTAMP(?:\(\d*\))?$/i', $default) === 1) {
            return strtolower($default);
        }
        if (($default[0] ?? '') === "'" || ($default[0] ?? '') === '"') {
            $quote = $default[0];
            $value = substr($default, 1, -1);
            return str_replace($quote . $quote, $quote, stripcslashes($value));
        }
        if (is_numeric($default)) {
            $negative = str_starts_with($default, '-');
            $number = ltrim($default, '+-');
            [$integer, $fraction] = array_pad(explode('.', $number, 2), 2, '');
            $integer = ltrim($integer, '0') ?: '0';
            $fraction = rtrim($fraction, '0');
            $normalized = $fraction === '' ? $integer : $integer . '.' . $fraction;
            return $negative && $normalized !== '0' ? '-' . $normalized : $normalized;
        }
        throw new InvalidArgumentException('Expresión DEFAULT no reconocida en el perfil SQL.');
    }

    /**
     * Extrae nombre, unicidad y columnas de un índice.
     *
     * @param string $definition Definición de índice.
     * @param string|null $forcedName Nombre fijo para PRIMARY.
     * @return array Definición normalizada del índice.
     */
    private static function parseIndex(string $definition, ?string $forcedName = null): array
    {
        if (preg_match(
            '/^(PRIMARY\s+KEY|UNIQUE\s+(?:KEY|INDEX)|(?:KEY|INDEX))\s*(?:`((?:``|[^`])+)`\s*)?\((.*)\)/is',
            trim($definition),
            $matches
        ) !== 1) {
            throw new InvalidArgumentException('Índice no reconocido en el perfil SQL.');
        }

        $kind = strtoupper(preg_replace('/\s+/', ' ', $matches[1]));
        $columns = [];
        foreach (self::splitDefinitions($matches[3]) as $columnDefinition) {
            if (preg_match('/^\s*`((?:``|[^`])+)`/s', $columnDefinition, $columnMatch) !== 1) {
                throw new InvalidArgumentException('Columna de índice no reconocida en el perfil SQL.');
            }
            $columns[] = str_replace('``', '`', $columnMatch[1]);
        }

        return [
            'name' => $forcedName ?? str_replace('``', '`', $matches[2] ?? ''),
            'kind' => match ($kind) {
                'PRIMARY KEY' => 'primary',
                'UNIQUE KEY', 'UNIQUE INDEX' => 'unique',
                default => 'index',
            },
            'columns' => $columns,
        ];
    }

    /**
     * Extrae una clave foránea y las acciones referenciales declaradas.
     *
     * @param string $definition Restricción SQL.
     * @return array|null Perfil normalizado de FK o null para otra restricción.
     */
    private static function parseForeignKey(string $definition): ?array
    {
        if (preg_match(
            '/^CONSTRAINT\s+`((?:``|[^`])+)`\s+FOREIGN\s+KEY\s*\(([^)]+)\)\s+REFERENCES\s+`((?:``|[^`])+)`\s*\(([^)]+)\)(.*)$/is',
            trim($definition),
            $matches
        ) !== 1) {
            return null;
        }

        $localColumns = self::parseIdentifierList($matches[2]);
        $referencedColumns = self::parseIdentifierList($matches[4]);
        preg_match('/\bON\s+DELETE\s+(CASCADE|RESTRICT|SET\s+NULL|NO\s+ACTION)\b/i', $matches[5], $deleteMatch);
        preg_match('/\bON\s+UPDATE\s+(CASCADE|RESTRICT|SET\s+NULL|NO\s+ACTION)\b/i', $matches[5], $updateMatch);

        return [
            'name' => str_replace('``', '`', $matches[1]),
            'columns' => $localColumns,
            'referenced_table' => str_replace('``', '`', $matches[3]),
            'referenced_columns' => $referencedColumns,
            'on_delete' => strtolower(preg_replace('/\s+/', ' ', $deleteMatch[1] ?? 'restrict')),
            'on_update' => strtolower(preg_replace('/\s+/', ' ', $updateMatch[1] ?? 'restrict')),
        ];
    }

    /**
     * Extrae identificadores de una lista SQL de columnas.
     *
     * @param string $list Lista entre paréntesis.
     * @return array Identificadores en su orden estructural.
     */
    private static function parseIdentifierList(string $list): array
    {
        $identifiers = [];
        foreach (self::splitDefinitions($list) as $item) {
            if (preg_match('/^\s*`((?:``|[^`])+)`/s', $item, $matches) !== 1) {
                throw new InvalidArgumentException('Identificador de columna no reconocido en clave foránea.');
            }
            $identifiers[] = str_replace('``', '`', $matches[1]);
        }
        return $identifiers;
    }

    /**
     * Ordena estructuras para que la comparación no dependa del orden de lectura.
     *
     * @param array $profile Perfil estructural.
     * @return array Perfil canonicalizado.
     */
    private static function normalizeProfile(array $profile): array
    {
        if (!isset($profile['version'], $profile['tables']) || !is_array($profile['tables'])) {
            throw new InvalidArgumentException('Perfil estructural inválido.');
        }

        $tables = $profile['tables'];
        foreach ($tables as $tableName => &$table) {
            if (!is_string($tableName) || !is_array($table)
                || !isset($table['columns'], $table['indexes'], $table['foreign_keys'])
                || !is_array($table['columns'])
                || !is_array($table['indexes'])
                || !is_array($table['foreign_keys'])) {
                throw new InvalidArgumentException('Definición de tabla inválida en el perfil.');
            }
            ksort($table['columns']);
            ksort($table['indexes']);
            ksort($table['foreign_keys']);
            $table['engine'] = strtolower((string)($table['engine'] ?? ''));
            $table['charset'] = strtolower((string)($table['charset'] ?? ''));
            $table['collation'] = strtolower((string)($table['collation'] ?? ''));
        }
        unset($table);
        ksort($tables);

        return ['version' => (string)$profile['version'], 'tables' => $tables];
    }

    /**
     * Compara la estructura ignorando el identificador nominal del perfil.
     *
     * @param array $left Primer perfil canonicalizado.
     * @param array $right Segundo perfil canonicalizado.
     * @return bool True si todos los elementos estructurales coinciden.
     */
    private static function sameStructure(array $left, array $right): bool
    {
        unset($left['version'], $right['version']);
        return $left === $right;
    }

    /**
     * Enumera faltantes y diferencias sin devolver valores sensibles.
     *
     * @param array $actual Perfil observado.
     * @param array $expected Perfil requerido.
     * @return array Códigos de diferencia con identificadores estructurales.
     */
    private static function findDifferences(array $actual, array $expected): array
    {
        $differences = [];
        foreach ($expected['tables'] as $tableName => $expectedTable) {
            if (!isset($actual['tables'][$tableName])) {
                $differences[] = "missing_table:$tableName";
                continue;
            }
            $actualTable = $actual['tables'][$tableName];
            foreach (['columns', 'indexes', 'foreign_keys'] as $section) {
                foreach ($expectedTable[$section] as $name => $definition) {
                    if (!isset($actualTable[$section][$name])) {
                        $differences[] = "missing_$section:$tableName.$name";
                    } elseif ($actualTable[$section][$name] !== $definition) {
                        $differences[] = "mismatch_$section:$tableName.$name";
                    }
                }
                foreach ($actualTable[$section] as $name => $_definition) {
                    if (!isset($expectedTable[$section][$name])) {
                        $differences[] = "extra_$section:$tableName.$name";
                    }
                }
            }
            foreach (['engine', 'charset', 'collation'] as $attribute) {
                if ($actualTable[$attribute] !== $expectedTable[$attribute]) {
                    $differences[] = "mismatch_$attribute:$tableName";
                }
            }
        }
        foreach ($actual['tables'] as $tableName => $_table) {
            if (!isset($expected['tables'][$tableName])) {
                $differences[] = "extra_table:$tableName";
            }
        }

        sort($differences);
        return $differences;
    }

    /**
     * Determina si el perfil solo carece de objetos requeridos.
     *
     * @param array $differences Diferencias entre perfiles.
     * @return bool True cuando todas las diferencias son faltantes.
     */
    private static function containsOnlyMissingObjects(array $differences): bool
    {
        if ($differences === []) {
            return false;
        }
        foreach ($differences as $difference) {
            if (!str_starts_with($difference, 'missing_')) {
                return false;
            }
        }
        return true;
    }
}
