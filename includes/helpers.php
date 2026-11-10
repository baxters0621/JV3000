<?php

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

if (!function_exists('normalizarCantidadUnidades')) {
    /**
     * Acepta una cantidad de unidades entera dentro del rango comercial común.
     *
     * Admite enteros y cadenas decimales exactas con fracción cero. Rechaza
     * floats porque la decodificación puede redondear una fracción a entero.
     *
     * @param mixed $valor Valor recibido del formulario o del payload JSON.
     * @return int|null Cantidad normalizada o null si no es válida.
     */
    function normalizarCantidadUnidades($valor): ?int
    {
        if (is_int($valor)) {
            $cantidad = (float)$valor;
        } elseif (
            is_string($valor)
            && preg_match('/^[+-]?\d+(?:\.0+)?$/D', trim($valor))
        ) {
            $cantidad = (float)trim($valor);
        } else {
            return null;
        }

        if (
            !is_finite($cantidad)
            || floor($cantidad) !== $cantidad
            || $cantidad < 1
            || $cantidad > 999999
        ) {
            return null;
        }

        return (int)$cantidad;
    }
}

if (!function_exists('prepararRecuperacionCompra')) {
    /**
     * Conserva solo los datos no sensibles necesarios para rehidratar un alta fallida.
     *
     * Conserva la fecha de compra y las líneas; excluye deliberadamente anticipo,
     * método y detalles de pago, CSRF y cualquier campo ajeno a la lista permitida.
     *
     * @param array $datos Datos POST del alta de compra.
     * @return array|null Borrador limitado para la sesión, o null si el detalle no es recuperable.
     */
    function prepararRecuperacionCompra(array $datos): ?array
    {
        $productos_json = $datos['productos_data'] ?? '';
        if (!is_string($productos_json) || strlen($productos_json) > 131072) {
            return null;
        }

        $productos_recibidos = $productos_json === ''
            ? []
            : json_decode($productos_json, true, 16);
        if (!is_array($productos_recibidos) || !array_is_list($productos_recibidos) || count($productos_recibidos) > 200) {
            return null;
        }

        $productos = [];
        foreach ($productos_recibidos as $producto) {
            if (!is_array($producto)) {
                return null;
            }
            $id_producto = filter_var($producto['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id_producto === false || $id_producto <= 0) {
                return null;
            }

            foreach (['nombre', 'cantidad', 'precio', 'fecha_vencimiento'] as $campo) {
                if (isset($producto[$campo]) && !is_scalar($producto[$campo])) {
                    return null;
                }
            }

            $nombre = (string)($producto['nombre'] ?? '');
            $cantidad = (string)($producto['cantidad'] ?? '');
            $precio = (string)($producto['precio'] ?? '');
            $fecha_vencimiento = (string)($producto['fecha_vencimiento'] ?? '');
            if (strlen($nombre) > 200 || strlen($cantidad) > 32 || strlen($precio) > 32) {
                return null;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha_vencimiento)) {
                $fecha_vencimiento = '';
            }

            $productos[] = [
                'id' => (int)$id_producto,
                'nombre' => $nombre,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'fecha_vencimiento' => $fecha_vencimiento,
            ];
        }

        $id_proveedor = filter_var($datos['id_proveedor'] ?? null, FILTER_VALIDATE_INT);
        $nro_factura = is_scalar($datos['nro_factura'] ?? null) ? (string)$datos['nro_factura'] : '';
        $nro_control = is_scalar($datos['nro_control'] ?? null) ? (string)$datos['nro_control'] : '';
        $fecha_compra = is_scalar($datos['fecha_compra'] ?? null) ? (string)$datos['fecha_compra'] : '';
        if (strlen($nro_factura) > 8 || strlen($nro_control) > 11 || strlen($fecha_compra) > 10) {
            return null;
        }

        return [
            'id_proveedor' => $id_proveedor !== false && $id_proveedor > 0 ? (int)$id_proveedor : 0,
            'nro_factura' => $nro_factura,
            'nro_control' => $nro_control,
            'fecha_compra' => preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha_compra) ? $fecha_compra : date('Y-m-d'),
            'productos' => $productos,
        ];
    }
}

if (!function_exists('prepararRecuperacionRecepcion')) {
    /**
     * Prepara el contexto seguro de una recepción fallida para reabrir el modal.
     *
     * Si el detalle no puede representarse sin ambigüedad, conserva la compra y la
     * clave idempotente, pero deja los ítems en null para que la interfaz pida revisión.
     *
     * @param array $datos Datos POST de la recepción.
     * @return array|null Datos limitados para sesión, o null si compra/clave no son válidos.
     */
    function prepararRecuperacionRecepcion(array $datos): ?array
    {
        $id_compra = filter_var($datos['id_compra'] ?? null, FILTER_VALIDATE_INT);
        $clave_idempotencia = strtolower((string)($datos['clave_idempotencia'] ?? ''));
        if (
            $id_compra === false
            || $id_compra <= 0
            || !preg_match('/^[a-f0-9]{64}$/D', $clave_idempotencia)
        ) {
            return null;
        }

        $items_json = $datos['items_data'] ?? '';
        $items_recibidos = is_string($items_json) && strlen($items_json) <= 65536
            ? json_decode($items_json, true, 12)
            : null;
        $documento_recepcion = is_scalar($datos['documento_recepcion'] ?? null)
            ? (string)$datos['documento_recepcion']
            : '';
        if (strlen($documento_recepcion) > 100) {
            return null;
        }
        $items = [];
        $ids_detalle = [];
        $items_recuperables = is_array($items_recibidos)
            && array_is_list($items_recibidos)
            && count($items_recibidos) <= 200;

        if ($items_recuperables) {
            foreach ($items_recibidos as $item) {
                if (!is_array($item)) {
                    $items_recuperables = false;
                    break;
                }
                $id_detalle = filter_var($item['id_detalle'] ?? null, FILTER_VALIDATE_INT);
                $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
                if (
                    $id_detalle === false
                    || $id_detalle <= 0
                    || $cantidad === false
                    || $cantidad < 0
                    || isset($ids_detalle[$id_detalle])
                    || (isset($item['fecha_vencimiento']) && !is_scalar($item['fecha_vencimiento']))
                ) {
                    $items_recuperables = false;
                    break;
                }
                $ids_detalle[$id_detalle] = true;
                $fecha_vencimiento = (string)($item['fecha_vencimiento'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha_vencimiento)) {
                    $fecha_vencimiento = '';
                }
                $items[] = [
                    'id_detalle' => (int)$id_detalle,
                    'cantidad' => (int)$cantidad,
                    'fecha_vencimiento' => $fecha_vencimiento,
                ];
            }
        }

        return [
            'id_compra' => (int)$id_compra,
            'clave_idempotencia' => $clave_idempotencia,
            'documento_recepcion' => $documento_recepcion,
            'items' => $items_recuperables ? $items : null,
        ];
    }
}

// ==========================================
// DOCUMENTO FISCAL (CÉDULA / RIF)
// ==========================================
if (!function_exists('normalizarDocumento')) {
    /**
     * Limpia un documento fiscal: mayúsculas, sin espacios, guiones unificados.
     * Mantiene el guion único separador entre letra-cuerpo(-verificador).
     */
    function normalizarDocumento(string $d): string
    {
        $d = mb_strtoupper(trim($d));
        $d = preg_replace('/\s+/', '', $d);
        $d = preg_replace('/[._\/\\\\]+/', '-', $d);
        $d = preg_replace('/-{2,}/', '-', $d);
        return $d;
    }
}

if (!function_exists('validarCedula')) {
    /**
     * Cédula de Identidad: V o E + 6 a 9 dígitos (sin verificador). Ej: V-12345678
     */
    function validarCedula(string $d): bool
    {
        return (bool)preg_match('/^[VE]-[0-9]{6,9}$/', $d);
    }
}

if (!function_exists('validarRIF')) {
    /**
     * RIF: letra [VEJGPC] + exactamente 8 dígitos + guion + 1 dígito verificador.
     * Ej: J-12345678-9
     */
    function validarRIF(string $d): bool
    {
        return (bool)preg_match('/^[VEJGPC]-[0-9]{8}-[0-9]$/', $d);
    }
}

if (!function_exists('validarRifProveedor')) {
    /**
     * RIF de proveedor: V/E con 8 dígitos; J/G/P/C con 8 dígitos + verificador.
     * Ej: V-12345678 · J-12345678-9
     */
    function validarRifProveedor(string $d): bool
    {
        return (bool)preg_match('/^[VE]-[0-9]{8}$/', $d)
            || (bool)preg_match('/^[JGPC]-[0-9]{8}-[0-9]$/', $d);
    }
}

if (!function_exists('validarDocumentoFiscal')) {
    /**
     * Acepta cédula o RIF (para el campo "RIF / Cédula" de ventas).
     */
    function validarDocumentoFiscal(string $d): bool
    {
        return validarCedula($d) || validarRIF($d);
    }
}

if (!function_exists('migrarFormatoDocumento')) {
    /**
     * Corrige formatos antiguos de documento fiscal.
     *  - J-123456789  → J-12345678-9 (último dígito = verificador)
     *  - J123456789 / V12345678 (sin guion) → inserta el guion
     *  - minúsculas / espacios → normaliza
     * Devuelve el documento corregido válido, o null si NO es recuperable.
     */
    function migrarFormatoDocumento(string $d): ?string
    {
        $d = normalizarDocumento($d);
        if ($d === '' || $d === 'N/A' || $d === 'S/RIF' || $d === 'SIN IDENTIFICACION') {
            return null;
        }

        // Ya válido (cédula o RIF) → se mantiene
        if (validarDocumentoFiscal($d)) {
            return $d;
        }

        // Quitar todos los guiones para reconstruir: LETRA + NÚMEROS
        $solo = preg_replace('/[^VEJGPC0-9]/i', '', $d);
        $solo = mb_strtoupper($solo);
        if (!preg_match('/^([VEJGPC])(\d+)$/', $solo, $m)) {
            return null;
        }
        $letra = $m[1];
        $nums  = $m[2];

        if ($letra === 'V' || $letra === 'E') {
            // Cédula: 6-9 dígitos, sin verificador
            if (strlen($nums) >= 6 && strlen($nums) <= 9) {
                $cand = $letra . '-' . $nums;
                return validarCedula($cand) ? $cand : null;
            }
            // RIF persona natural: 8 + verificador
            if (strlen($nums) === 9) {
                $cand = $letra . '-' . substr($nums, 0, 8) . '-' . substr($nums, 8, 1);
                return validarRIF($cand) ? $cand : null;
            }
            return null;
        }

        // J/G/P/C: RIF obligatorio, 8 + 1 verificador
        if (strlen($nums) === 9) {
            $cand = $letra . '-' . substr($nums, 0, 8) . '-' . substr($nums, 8, 1);
            return validarRIF($cand) ? $cand : null;
        }
        if (strlen($nums) === 8) {
            // Sin verificador → no recuperable (no se adivina)
            return null;
        }
        return null;
    }
}

if (!function_exists('validarTelefono')) {
    /**
     * Valida formato E.164: +584124862167
     * @param string $tel
     * @return bool
     */
    function validarTelefono($tel)
    {
        $digits = preg_replace('/\D/', '', $tel);
        return str_starts_with($tel, '+') && strlen($digits) >= 8 && strlen($digits) <= 15;
    }
}

if (!function_exists('validarTelefonoProveedor')) {
    /**
     * Valida el teléfono del proveedor: Venezuela exige un prefijo móvil
     * válido; el resto de países usa el E.164 general.
     */
    function validarTelefonoProveedor(string $tel): bool
    {
        if (preg_match('/^\+58(412|414|416|424|426|410|411|418|419)[0-9]{7}$/', $tel)) {
            return true;
        }
        if (str_starts_with($tel, '+58')) {
            return false;
        }
        return validarTelefono($tel);
    }
}

if (!function_exists('formatearTelefono')) {
    /**
     * Convierte E.164 (+584124862167) a formato legible (+58 412-486-2167)
     * @param string $e164
     * @return string
     */
    function formatearTelefono($e164)
    {
        $digitos = preg_replace('/\D/', '', (string)$e164);
        $formatos = [
            '507' => [4, 4],
            '58'  => [3, 3, 4],
            '57'  => [3, 3, 4],
            '52'  => [3, 3, 4],
            '34'  => [3, 3, 3],
            '1'   => [3, 3, 4],
        ];
        foreach ($formatos as $codigo => $grupos) {
            if (!str_starts_with($digitos, $codigo)) continue;
            $nacional = substr($digitos, strlen($codigo));
            if (strlen($nacional) !== array_sum($grupos)) continue;
            $partes = [];
            $pos = 0;
            foreach ($grupos as $largo) {
                $partes[] = substr($nacional, $pos, $largo);
                $pos += $largo;
            }
            return '+' . $codigo . ' ' . implode('-', $partes);
        }
        return $digitos !== '' ? '+' . $digitos : (string)$e164;
    }
}

// Helpers de BD / Config
if (!function_exists('getConfig')) {
    /**
     * Obtiene el valor de una clave de configuración.
     *
     * Consulta la tabla de configuración y devuelve su valor, o el valor por
     * defecto si la clave no existe.
     *
     * @param string $clave   Clave de configuración a buscar.
     * @param string $default Valor por defecto si la clave no existe.
     * @return string Valor de la configuración o el default.
     */
    function getConfig(string $clave, string $default = ''): string
    {
        if (isset($GLOBALS['jv_config_cache'][$clave])) {
            return $GLOBALS['jv_config_cache'][$clave];
        }
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT valor FROM configuracion WHERE clave = ?", [$clave]);
        $valor = $row ? $row['valor'] : $default;
        $GLOBALS['jv_config_cache'][$clave] = $valor;
        return $valor;
    }
}

if (!function_exists('setConfig')) {
    /**
     * Guarda o actualiza un valor de configuración.
     *
     * @param string $clave Clave de configuración.
     * @param string $valor Nuevo valor.
     * @return bool
     */
    function setConfig(string $clave, string $valor): bool
    {
        $db = Database::getInstance();
        $existe = $db->fetchOne("SELECT id_config FROM configuracion WHERE clave = ? LIMIT 1", [$clave]);
        $ok = false;
        if ($existe) {
            $ok = $db->execute("UPDATE configuracion SET valor = ?, fecha_actualizado = NOW() WHERE clave = ?", [$valor, $clave]);
        } else {
            $ok = $db->execute("INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, '')", [$clave, $valor]);
        }
        if (isset($GLOBALS['jv_config_cache'])) {
            unset($GLOBALS['jv_config_cache'][$clave]);
        }
        return (bool)$ok;
    }
}

// Códigos derivados de entidades (sin estado adicional)
if (!function_exists('codigoCliente')) {
    /**
     * Genera el código de un cliente a partir de su id.
     *
     * @param int $id_cliente Identificador del cliente.
     * @return string Código formateado CLI-000001.
     */
    function codigoCliente(int $id_cliente): string
    {
        return 'CLI-' . str_pad($id_cliente, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('codigoProveedor')) {
    /**
     * Genera el código de un proveedor a partir de su id.
     *
     * @param int $id_proveedor Identificador del proveedor.
     * @return string Código formateado PROV-000001.
     */
    function codigoProveedor(int $id_proveedor): string
    {
        return 'PROV-' . str_pad($id_proveedor, 6, '0', STR_PAD_LEFT);
    }
}

// Generadores de números secuenciales
if (!function_exists('generarControlNumero')) {
    /**
     * Genera el siguiente número de control secuencial (CTRL).
     *
     * Lee e incrementa el contador de la tabla sku_contadores con bloqueo
     * FOR UPDATE dentro de una transacción y devuelve el número formateado
     * como "00-00000000". Ante un error devuelve '00-00000000'.
     *
     * @return string Número de control formateado.
     */
    function generarControlNumero()
    {
        $db = Database::getInstance();
        $db->begin();
        try {
            $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='CTRL' FOR UPDATE");
            if (!$cnt) {
                $db->execute("INSERT INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('CTRL', 0)");
                $prox = 1;
            } else {
                $prox = intval($cnt['ultimo_numero']) + 1;
            }
            $db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='CTRL'", [$prox]);
            $db->commit();
            $num = str_pad($prox, 10, '0', STR_PAD_LEFT);
            return substr($num, 0, 2) . '-' . substr($num, 2);
        } catch (Exception $e) {
            $db->rollback();
            return '00-00000000';
        }
    }
}

if (!function_exists('generarFacturaNumero')) {
    /**
     * Genera el siguiente número de Nota de Entrega (NDE).
     *
     * Lee e incrementa el contador sku_contadores con bloqueo FOR UPDATE
     * dentro de una transacción y devuelve el número formateado como
     * "NDE-000001". Ante un error revierte y propaga la excepción.
     *
     * @return string Número de Nota de Entrega formateado.
     * @throws Throwable Si no puede reservarse el siguiente número.
     */
    function generarFacturaNumero()
    {
        $db = Database::getInstance();
        $db->begin();
        try {
            $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='NDE' FOR UPDATE");
            if (!$cnt) {
                $db->execute("INSERT INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('NDE', 0)");
                $prox = 1;
            } else {
                $prox = intval($cnt['ultimo_numero']) + 1;
            }
            $db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='NDE'", [$prox]);
            $db->commit();
            return 'NDE-' . str_pad($prox, 6, '0', STR_PAD_LEFT);
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}

if (!defined('MAX_PASSWORD_BYTES')) {
    define('MAX_PASSWORD_BYTES', 72);
}
if (!defined('MAX_PASSWORD_CHARACTERS')) {
    define('MAX_PASSWORD_CHARACTERS', 18);
}

if (!function_exists('validarPasswordFuerte')) {
    /**
     * Valida que una contraseña sea fuerte.
     *
     * Requiere mínimo 8 caracteres, al menos una mayúscula, una minúscula,
     * un dígito y un símbolo; no permite espacios ni saltos de línea y admite hasta
     * 18 caracteres UTF-8 para mantenerse dentro del límite de bcrypt.
     *
     * @param string $password Contraseña a validar.
     * @return bool True si cumple la política de fortaleza.
     */
    function validarPasswordFuerte(string $password): bool
    {
        return mb_check_encoding($password, 'UTF-8')
            && mb_strlen($password, 'UTF-8') <= MAX_PASSWORD_CHARACTERS
            && strlen($password) <= MAX_PASSWORD_BYTES
            && !preg_match('/[\r\n]/', $password)
            && !preg_match('/\s/u', $password)
            && mb_strlen($password, 'UTF-8') >= 8
            && preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(?:[^\w\s]|_)).*\z/u', $password) === 1;
    }

    /**
     * Clasifica la fortaleza de una contraseña en niveles 0-4.
     *
     * Retorna un arreglo con nivel (0-4), texto descriptivo y porcentaje
     * para la barra de indicador visual. La puntuación es:
     * ≥8 chars (+1), minúscula (+1), mayúscula (+1), número (+1), símbolo (+1), ≥12 chars (+1 bonus).
     *
     * @param string $password Contraseña a clasificar.
     * @return array{nivel: int, texto: string, color: string, porcentaje: int}
     */
    function clasificarFortalezaPassword(string $password): array
    {
        $puntos = 0;
        $passwordSinEspacios = preg_replace('/\s/u', '', $password) ?? $password;
        if (mb_strlen($passwordSinEspacios, 'UTF-8') >= 8)  $puntos++;
        if (preg_match('/[a-z]/', $password)) $puntos++;
        if (preg_match('/[A-Z]/', $password)) $puntos++;
        if (preg_match('/\d/', $password))    $puntos++;
        if (preg_match('/(?:[^\w\s]|_)/u', $password)) $puntos++;
        if (mb_strlen($passwordSinEspacios, 'UTF-8') >= 12) $puntos++;

        if ($puntos <= 1) {
            return ['nivel' => 0, 'texto' => 'Muy débil',   'color' => '#ef4444', 'porcentaje' => 15];
        } elseif ($puntos === 2) {
            return ['nivel' => 1, 'texto' => 'Débil',       'color' => '#f97316', 'porcentaje' => 35];
        } elseif ($puntos === 3) {
            return ['nivel' => 2, 'texto' => 'Media',       'color' => '#eab308', 'porcentaje' => 55];
        } elseif ($puntos === 4) {
            return ['nivel' => 3, 'texto' => 'Fuerte',      'color' => '#22c55e', 'porcentaje' => 80];
        } else {
            return ['nivel' => 4, 'texto' => 'Muy fuerte',  'color' => '#16a34a', 'porcentaje' => 100];
        }
    }

    /**
     * Retorna los requisitos faltantes de una contraseña como texto legible.
     *
     * @param string $password Contraseña a evaluar.
     * @return string Lista de requisitos faltantes o cadena vacía si cumple todos.
     */
    function requisitosFaltantesPassword(string $password): string
    {
        $faltan = [];
        if (!mb_check_encoding($password, 'UTF-8')) $faltan[] = 'texto válido';
        elseif (mb_strlen($password, 'UTF-8') < 8) $faltan[] = 'mínimo 8 caracteres';
        if (!preg_match('/[a-z]/', $password)) $faltan[] = '1 minúscula';
        if (!preg_match('/[A-Z]/', $password)) $faltan[] = '1 mayúscula';
        if (!preg_match('/\d/', $password))    $faltan[] = '1 número';
        if (!preg_match('/(?:[^\w\s]|_)/u', $password)) $faltan[] = '1 símbolo';
        if (preg_match('/\s/u', $password)) $faltan[] = 'sin espacios';
        if (preg_match('/[\r\n]/', $password)) $faltan[] = 'sin saltos de línea';
        if (!mb_check_encoding($password, 'UTF-8')) $faltan[] = 'texto válido';
        elseif (mb_strlen($password, 'UTF-8') > MAX_PASSWORD_CHARACTERS) $faltan[] = 'máximo 18 caracteres';
        return $faltan ? implode(', ', $faltan) : '';
    }
}

if (!function_exists('generarPasswordCheck')) {
    /**
     * Genera un hash SHA-256 de la contraseña en minúsculas para detección rápida de duplicados.
     * Se almacena en la columna password_check de usuarios.
     *
     * @param string $password Contraseña en texto plano.
     * @return string Hash SHA-256 en minúsculas (64 caracteres hex).
     */
    function generarPasswordCheck(string $password): string
    {
        return hash('sha256', mb_strtolower($password, 'UTF-8'));
    }
}

if (!function_exists('normalizarUsuario')) {
    /**
     * Normaliza un nombre de usuario a "Title_Case", conservando letras
     * Unicode y capitalizando el inicio y cada segmento separado por guion bajo.
     *
     * @param string $raw Nombre de usuario sin normalizar.
     * @return string Nombre de usuario normalizado.
     */
    function normalizarUsuario(string $raw): string
    {
        $clean = trim($raw);
        if (!mb_check_encoding($clean, 'UTF-8')) {
            return strtolower($clean);
        }
        $clean = mb_strtolower($clean, 'UTF-8');
        return preg_replace_callback(
            '/(^|_)(\p{L})/u',
            function ($m) {
                return ($m[1] === '_' ? '_' : '') . mb_strtoupper($m[2], 'UTF-8');
            },
            $clean
        ) ?? $clean;
    }
}

if (!function_exists('validarNombreUsuario')) {
    /**
     * Valida un nombre de usuario: empieza con una letra Unicode y admite
     * letras, marcas diacríticas, números y guion bajo (4 a 20 caracteres).
     *
     * @param string $usuario Nombre ya normalizado.
     * @return bool true si respeta el formato permitido.
     */
    function validarNombreUsuario(string $usuario): bool
    {
        return preg_match('/^[\p{L}][\p{L}\p{N}\p{M}_]{3,19}$/uD', $usuario) === 1;
    }
}

if (!function_exists('existePasswordDuplicado')) {
    /**
     * Verifica si ya existe otro usuario con la misma contraseña (case-insensitive).
     *
     * @param object $db Instancia de Database.
     * @param string $password_check Hash de la contraseña a verificar.
     * @param int|null $exclude_id ID de usuario a excluir (para cambios de contraseña propios).
     * @return bool true si ya existe otro usuario con esa contraseña.
     */
    function existePasswordDuplicado($db, string $password_check, ?int $exclude_id = null): bool
    {
        $sql = "SELECT id_usuario FROM usuarios WHERE password_check = ?";
        $params = [$password_check];
        if ($exclude_id !== null) {
            $sql .= " AND id_usuario != ?";
            $params[] = $exclude_id;
        }
        return (bool) $db->fetchOne($sql, $params);
    }
}

if (!function_exists('purgarPreviewsSesion')) {
    /**
     * Elimina los previews de ventas abandonados de la sesión.
     *
     * Recorre la sesión y elimina 'preview_data' y cualquier clave que
     * comience con 'preview_data_', dejando solo el preview más reciente.
     *
     * @return void
     */
    function purgarPreviewsSesion(): void
    {
        foreach (array_keys($_SESSION) as $clave) {
            if ($clave === 'preview_data' || strpos((string)$clave, 'preview_data_') === 0) {
                unset($_SESSION[$clave]);
            }
        }
    }
}

if (!function_exists('registrarAuditoria')) {
    /**
     * Registra una acción en la tabla de auditoría.
     *
     * Inserta el registro con el usuario actual de la sesión (o 'Sistema' si
     * no hay sesión), además de contexto útil del request actual: IP, ruta y
     * método HTTP. Permite pasar un arreglo opcional con información extra que
     * se adjunta al detalle del evento.
     *
     * @param string     $accion  Tipo de acción (crear, editar, eliminar, anular...).
     * @param string     $detalle Descripción de la acción realizada.
     * @param array|null $meta    Datos adicionales de contexto opcionales.
     * @return void
     */
    function registrarAuditoria(string $accion, string $detalle = '', ?array $meta = null): void
    {
        $db = Database::getInstance();
        $id_usuario = intval($_SESSION['id_usuario'] ?? 0);
        $usuario_nombre = $_SESSION['usuario'] ?? 'Sistema';
        $ip_origen = $_SERVER['REMOTE_ADDR'] ?? null;
        $ruta = $_SERVER['REQUEST_URI'] ?? null;
        $metodo = $_SERVER['REQUEST_METHOD'] ?? null;

        if (!empty($meta)) {
            $extra = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $detalle = trim($detalle) !== '' ? $detalle . ' | ' . $extra : $extra;
        }

        $db->execute(
            "INSERT INTO auditoria (id_usuario, usuario_nombre, accion, detalle, ip_origen, ruta, metodo) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$id_usuario, $usuario_nombre, $accion, $detalle, $ip_origen, $ruta, $metodo]
        );
    }
}

// Preguntas de seguridad
if (!function_exists('getPreguntasRespuestas')) {
    /**
     * Obtiene la lista de preguntas de seguridad disponibles.
     *
     * Devuelve el catálogo fijo de preguntas que el usuario puede elegir
     * al configurar la recuperación de su cuenta.
     *
     * @return array Lista de preguntas de seguridad.
     */
    function getPreguntasRespuestas(): array
    {
        return [
            'Nombre de tu mascota',
            'Ciudad donde naciste',
            'Nombre de tu mejor amigo',
            'Comida favorita',
            'Nombre de tu escuela primaria',
            'Apellido de tu abuela materna',
            'Marca de tu primer auto',
            'Color favorito',
        ];
    }

// Normalizar respuesta de seguridad (trim + minúsculas + sin acentos)
    /**
     * Normaliza una respuesta de seguridad para compararla de forma flexible.
     *
     * Recorta espacios, elimina acentos y convierte a minúsculas, de modo que
     * la misma respuesta en distintos formatos sea considerada equivalente.
     *
     * @param string $respuesta Respuesta del usuario.
     * @return string Respuesta normalizada.
     */
    function normalizarRespuestaSeguridad(string $respuesta): string
    {
        $r = trim($respuesta);
        $r = strtr($r, [
            'Á' => 'a',
            'É' => 'e',
            'Í' => 'i',
            'Ó' => 'o',
            'Ú' => 'u',
            'Ü' => 'u',
            'Ñ' => 'n',
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
            'À' => 'a',
            'È' => 'e',
            'Ì' => 'i',
            'Ò' => 'o',
            'Ù' => 'u',
            'à' => 'a',
            'è' => 'e',
            'ì' => 'i',
            'ò' => 'o',
            'ù' => 'u',
            'Â' => 'a',
            'Ê' => 'e',
            'Î' => 'i',
            'Ô' => 'o',
            'Û' => 'u',
            'â' => 'a',
            'ê' => 'e',
            'î' => 'i',
            'ô' => 'o',
            'û' => 'u',
            'Ã' => 'a',
            'Õ' => 'o',
            'ã' => 'a',
            'õ' => 'o',
        ]);
        return strtolower($r);
    }

// Validar respuesta de seguridad: flexible, acepta desde un solo carácter
    /**
     * Valida que una respuesta de seguridad no esté vacía.
     *
     * La normaliza y comprueba que tras normalizarla no quede en blanco;
     * acepta respuestas desde un solo carácter.
     *
     * @param string $respuesta Respuesta del usuario.
     * @return bool True si la respuesta es válida (no vacía).
     */
    function validarRespuestaSeguridad(string $respuesta): bool
    {
        return normalizarRespuestaSeguridad($respuesta) !== '';
    }

// Verificar respuesta: normaliza ambos textos antes de comparar
// (con retro-compatibilidad para respuestas guardadas sin normalizar)
    /**
     * Verifica una respuesta de seguridad contra su hash guardado.
     *
     * Normaliza la respuesta y la compara con password_verify; si eso falla,
     * reintenta con la respuesta sin normalizar para dar retro-compatibilidad
     * con respuestas guardadas antes de la normalización.
     *
     * @param string $respuesta Respuesta ingresada por el usuario.
     * @param string $hash      Hash guardado de la respuesta correcta.
     * @return bool True si la respuesta coincide con el hash.
     */
    function verificarRespuestaSeguridad(string $respuesta, string $hash): bool
    {
        $normalizada = normalizarRespuestaSeguridad($respuesta);
        if ($normalizada !== '' && password_verify($normalizada, $hash)) return true;
        return $respuesta !== '' && password_verify($respuesta, $hash);
    }
}

// ==========================================
// RECUPERACIÓN POR CORREO ELECTRÓNICO
// ==========================================
// El código de 6 dígitos vive solo en la sesión: se pide y se teclea en el mismo
// navegador, así que no hace falta persistirlo en la base. Tampoco se toca la
// tabla login_intentos para limitar los envíos, porque comparte filas con el
// bloqueo de login (login.php:70) y un usuario que pidiera varios códigos se
// quedaría sin poder iniciar sesión.
if (!function_exists('solicitarCodigoRecuperacion')) {
    /**
     * Genera el código de recuperación y lo guarda hasheado en la sesión.
     *
     * Aplica un máximo de 3 envíos cada 15 minutos dentro de la propia sesión,
     * el mismo patrón que ya usan `rec_intentos` y `rec_pin_intentos`.
     *
     * @return array ['ok'=>bool, 'codigo'=>int|null, 'mensaje'=>string].
     *                'codigo' solo viene en claro cuando ok=true, para que el
     *                llamador lo envíe por correo; nunca se guarda sin hashear.
     */
    function solicitarCodigoRecuperacion(): array
    {
        $max_envios = 3;
        $ventana = 900; // 15 minutos
        $ahora = time();

        $envios = $_SESSION['rec_envios'] ?? ['total' => 0, 'inicio' => $ahora];
        if ($ahora - (int)$envios['inicio'] >= $ventana) {
            $envios = ['total' => 0, 'inicio' => $ahora];
        }
        if ($envios['total'] >= $max_envios) {
            $restante = (int)ceil(($ventana - ($ahora - (int)$envios['inicio'])) / 60);
            return [
                'ok' => false,
                'codigo' => null,
                'mensaje' => "DEMASIADOS ENVÍOS. INTENTE DE NUEVO EN $restante MINUTO(S).",
            ];
        }

        $codigo = random_int(100000, 999999);
        $_SESSION['rec_codigo_hash'] = hash('sha256', (string)$codigo);
        $_SESSION['rec_codigo_expira'] = $ahora + $ventana;
        $_SESSION['rec_codigo_intentos'] = 0;
        $_SESSION['rec_ultimo_envio'] = $ahora;
        $_SESSION['rec_envios'] = ['total' => $envios['total'] + 1, 'inicio' => $envios['inicio']];

        return ['ok' => true, 'codigo' => $codigo, 'mensaje' => ''];
    }
}

if (!function_exists('verificarCodigoRecuperacion')) {
    /**
     * Comprueba un código de recuperación ingresado contra el hash de sesión.
     *
     * Valida formato, vigencia (15 min) y un máximo de 3 intentos, igual que
     * el PIN de emergencia. Usa hash_equals para comparar.
     *
     * @param string $ingresado Código tecleado por el usuario.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    function verificarCodigoRecuperacion(string $ingresado): array
    {
        $hash = $_SESSION['rec_codigo_hash'] ?? '';
        if ($hash === '' || empty($_SESSION['rec_codigo_expira'])) {
            return ['ok' => false, 'mensaje' => 'NO HAY UN CÓDIGO PENDIENTE. SOLICITE UNO NUEVO.'];
        }
        if (time() > (int)$_SESSION['rec_codigo_expira']) {
            limpiarCodigoRecuperacion();
            return ['ok' => false, 'mensaje' => 'EL CÓDIGO EXPIRÓ. SOLICITE UNO NUEVO.'];
        }
        if (!preg_match('/^\d{6}$/', $ingresado)) {
            return ['ok' => false, 'mensaje' => 'EL CÓDIGO DEBE SER DE 6 DÍGITOS.'];
        }
        if (!hash_equals($hash, hash('sha256', $ingresado))) {
            $_SESSION['rec_codigo_intentos'] = ($_SESSION['rec_codigo_intentos'] ?? 0) + 1;
            if ($_SESSION['rec_codigo_intentos'] >= 3) {
                limpiarCodigoRecuperacion();
                return ['ok' => false, 'mensaje' => 'DEMASIADOS INTENTOS. SOLICITE UN CÓDIGO NUEVO.'];
            }
            $restantes = 3 - $_SESSION['rec_codigo_intentos'];
            return ['ok' => false, 'mensaje' => "CÓDIGO INCORRECTO. INTENTOS RESTANTES: $restantes"];
        }
        // Un solo uso: verificado el código, deja de existir por esta sesión.
        limpiarCodigoRecuperacion();
        return ['ok' => true, 'mensaje' => ''];
    }
}

if (!function_exists('limpiarCodigoRecuperacion')) {
    /**
     * Descarta el código pendiente de la sesión (tras usarlo, expirar o agotar
     * intentos). No borra el contador de envíos: esa ventana caduca sola.
     */
    function limpiarCodigoRecuperacion(): void
    {
        unset(
            $_SESSION['rec_codigo_hash'],
            $_SESSION['rec_codigo_expira'],
            $_SESSION['rec_codigo_intentos']
        );
    }
}

if (!function_exists('codigoRecuperacionCuerpo')) {
    /**
     * Cuerpo del correo con el código de recuperación de 6 dígitos.
     *
     * @param string $correo Destinatario, solo para personalizar el saludo.
     * @return string HTML mínimo apto para el cuerpo del mensaje.
     */
    function codigoRecuperacionCuerpo(string $correo): string
    {
        $saludo = htmlspecialchars(enmascararCorreo($correo), ENT_QUOTES, 'UTF-8');
        return '<p>Hola:</p>'
            . '<p>Tu código de recuperación de acceso es:</p>'
            . '<p style="font-size:26px;font-weight:700;letter-spacing:6px;">{CODIGO}</p>'
            . '<p>Es de <strong>un solo uso</strong> y vence en <strong>15 minutos</strong>.</p>'
            . '<p>Si no solicitaste este código, ignora este mensaje.</p>'
            . '<p style="color:#777;font-size:12px;">Enviado a ' . $saludo . ' · JV3000 C.A.</p>';
    }
}

if (!function_exists('enmascararCorreo')) {
    /**
     * Enmascara un correo para mostrarlo en pantalla sin exponerlo completo.
     *
     * Evita filtrar la dirección registrada de una cuenta a partir de solo
     * conocer el nombre de usuario (p. ej. "gu••••••16@gmail.com").
     *
     * @param string $correo Direpción completa.
     * @return string Dirección enmascarada; "***" si no tiene formato de correo.
     */
    function enmascararCorreo(string $correo): string
    {
        $partes = explode('@', trim($correo));
        if (count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
            return '***';
        }
        [$local, $dominio] = $partes;
        $visible = substr($local, 0, 2);
        $oculto = str_repeat('*', max(3, strlen($local) - 2));
        return $visible . $oculto . '@' . $dominio;
    }
}

// ==========================================
// LOTES DE INVENTARIO (FEFO)
// ==========================================
if (!function_exists('capacidadProducto')) {
    /**
     * Capacidad de almacenamiento efectiva de un producto:
     * propia (stock_maximo) si > 0; si no, la de su categoría; si no, 100.
     */
    function capacidadProducto(Database $db, int $id_producto): int
    {
        $r = $db->fetchOne(
            "SELECT COALESCE(NULLIF(p.stock_maximo,0), c.stock_maximo, 100) as cap
             FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
             WHERE p.id_producto = ?",
            [$id_producto]
        );
        return max(0, (int)($r['cap'] ?? 100));
    }
}

if (!function_exists('lotesConsumibles')) {
    /**
     * Lotes disponibles de un producto en orden FEFO (primero lo que vence antes).
     * $solo_vencidos = true  → solo lotes vencidos (para ajustes por vencimiento).
     * $solo_vencidos = false → solo lotes vigentes (para ventas/regalías).
     * $lock = true → FOR UPDATE (bloquea filas dentro de transacción).
     */
    function lotesConsumibles(Database $db, int $id_producto, bool $solo_vencidos = false, bool $lock = false): array
    {
        $forUpdate = $lock ? ' FOR UPDATE' : '';
        if ($solo_vencidos) {
            return $db->fetchAll(
                "SELECT id_lote, cantidad_restante, fecha_vencimiento
                 FROM lotes
                 WHERE id_producto = ? AND cantidad_restante > 0
                   AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= CURDATE()
                 ORDER BY fecha_vencimiento ASC, id_lote ASC" . $forUpdate,
                [$id_producto]
            );
        }
        return $db->fetchAll(
            "SELECT id_lote, cantidad_restante, fecha_vencimiento
             FROM lotes
             WHERE id_producto = ? AND cantidad_restante > 0
               AND (fecha_vencimiento IS NULL OR fecha_vencimiento > CURDATE())
             ORDER BY (fecha_vencimiento IS NULL) ASC, fecha_vencimiento ASC, id_lote ASC" . $forUpdate,
            [$id_producto]
        );
    }
}

if (!function_exists('stockLoteDisponible')) {
    /** Stock total disponible de un producto según el modo (solo vencidos o vigentes). */
    function stockLoteDisponible(Database $db, int $id_producto, bool $solo_vencidos = false): int
    {
        $total = 0;
        foreach (lotesConsumibles($db, $id_producto, $solo_vencidos) as $l) {
            $total += (int)$l['cantidad_restante'];
        }
        return $total;
    }
}

if (!function_exists('consumirLotes')) {
    /**
     * Consume $cantidad del producto en modo FEFO y devuelve
     * [ ['id_lote' => int, 'cantidad' => int], ... ].
     * Lanza Exception si no hay stock suficiente en los lotes permitidos.
     * $lock = true → bloquea filas de lotes (usar dentro de transacción).
     */
    function consumirLotes(Database $db, int $id_producto, int $cantidad, bool $solo_vencidos = false, bool $lock = false): array
    {
        $restante = $cantidad;
        $usados = [];
        foreach (lotesConsumibles($db, $id_producto, $solo_vencidos, $lock) as $lote) {
            if ($restante <= 0) break;
            $disp = (int)$lote['cantidad_restante'];
            if ($disp <= 0) continue;
            $tomar = min($disp, $restante);
            $usados[] = ['id_lote' => (int)$lote['id_lote'], 'cantidad' => $tomar];
            $restante -= $tomar;
        }
        if ($restante > 0) {
            $modo = $solo_vencidos ? 'VENCIDOS' : 'VIGENTES';
            throw new Exception("STOCK $modo INSUFICIENTE (ID:$id_producto). Faltan $restante und(s).");
        }
        return $usados;
    }
}

if (!function_exists('devolverLote')) {
    /** Devuelve unidades a un lote (anulación/edición de salida). */
    function devolverLote(Database $db, int $id_lote, int $cantidad): void
    {
        $db->execute("UPDATE lotes SET cantidad_restante = cantidad_restante + ? WHERE id_lote = ?", [$cantidad, $id_lote]);
    }
}

// ==========================================
// ALERTAS CRÍTICAS DE STOCK (CAMPANA)
// ==========================================
if (!function_exists('jv_alertas_por_rol')) {
    /**
     * Alertas críticas para la campana del Panel de Inicio.
     *
     * Devuelve dos grupos sin solapamiento:
     * - VENCIDOS: productos activos con fecha de vencimiento anterior a hoy.
     * - STOCK BAJO: productos activos en o bajo el mínimo que NO estén vencidos.
     *
     * La lista se limita a 8 elementos por grupo para el panel, pero los
     * conteos siempre reflejan el total real. Las alertas de stock bajo se
     * muestran a todos los roles; las acciones se habilitan según el rol.
     *
     * @param int|null $id_rol Rol de la sesión activa (1 admin, 2 carga, 3 ventas).
     * @return array{total:int,counts:array<string,int>,vencidos:array<int,array<string,mixed>>,bajos:array<int,array<string,mixed>>,puede_baja:bool,puede_reponer:bool}
     */
    function jv_alertas_por_rol(?int $id_rol = null): array
    {
        $db = Database::getInstance();
        $id_rol = $id_rol ?? (int)($_SESSION['id_rol'] ?? 0);

        // Query 1: productos vencidos (fecha anterior a hoy).
        $vencimientoFilas = $db->fetchAll(
            "SELECT id_producto, nombre_producto, fecha_vencimiento
             FROM productos
             WHERE status = 'Activo'
               AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento <> '0000-00-00'
               AND fecha_vencimiento < CURDATE()
             ORDER BY fecha_vencimiento ASC"
        );

        $vencidos = [];
        foreach ($vencimientoFilas as $r) {
            if (count($vencidos) >= 8) {
                break;
            }
            $vencidos[] = [
                'id' => (int)$r['id_producto'],
                'nombre' => (string)$r['nombre_producto'],
                'fecha' => date('d/m/Y', strtotime($r['fecha_vencimiento'])),
            ];
        }

        // Query 2: stock bajo, excluyendo los ya listados como vencidos.
        $bajoFilas = $db->fetchAll(
            "SELECT id_producto, nombre_producto, stock_actual, stock_minimo, id_proveedor
             FROM productos
             WHERE status = 'Activo'
               AND stock_actual <= stock_minimo
               AND NOT (
                   fecha_vencimiento IS NOT NULL
                   AND fecha_vencimiento <> '0000-00-00'
                   AND fecha_vencimiento < CURDATE()
               )
             ORDER BY stock_actual ASC, nombre_producto ASC"
        );

        $bajos = [];
        foreach ($bajoFilas as $r) {
            if (count($bajos) >= 8) {
                break;
            }
            $bajos[] = [
                'id' => (int)$r['id_producto'],
                'nombre' => (string)$r['nombre_producto'],
                'stock' => (int)$r['stock_actual'],
                'minimo' => (int)$r['stock_minimo'],
                'id_proveedor' => (int)($r['id_proveedor'] ?? 0),
            ];
        }

        $count_vencidos = count($vencimientoFilas);
        $count_bajos = count($bajoFilas);

        return [
            'total' => $count_vencidos + $count_bajos,
            'counts' => ['vencidos' => $count_vencidos, 'bajos' => $count_bajos],
            'vencidos' => $vencidos,
            'bajos' => $bajos,
            'puede_baja' => ($id_rol === 1),
            'puede_reponer' => in_array($id_rol, [1, 2], true),
        ];
    }
}

/**
 * Genera el "sello" visual de comparación de porcentajes (▲/▼).
 *
 * Único helper compartido para este fin (antes estaba duplicado en la vista
 * y en el módulo legacy de estadísticas). Se usa en la vista de estadísticas
 * para indicar si un KPI aumentó o disminuyó respecto al periodo anterior.
 *
 * @param float|null $pct  Porcentaje de variación (null = sin datos previos).
 * @return string          HTML del sello con clase y tooltip según el signo.
 */
function jv_sello(?float $pct): string
{
    // Sin dato del periodo anterior → sello neutro (guion).
    if ($pct === null) {
        return '<span class="cmp-sello cmp-nulo" title="Sin datos en el periodo anterior">—</span>';
    }

    // Variación positiva → flecha arriba y signo +.
    if ($pct >= 0) {
        return '<span class="cmp-sello cmp-subida" title="Aumento respecto al periodo anterior"><i class="bi bi-arrow-up-right"></i> +' . number_format($pct, 1) . '%</span>';
    }

    // Variación negativa → flecha abajo (el número ya lleva su signo -).
    return '<span class="cmp-sello cmp-bajada" title="Descenso respecto al periodo anterior"><i class="bi bi-arrow-down-right"></i> ' . number_format($pct, 1) . '%</span>';
}

/**
 * Devuelve el sufijo de versión (?v=mtime) de un asset propio.
 *
 * Evita mantener a mano los ?v=N de CSS/JS: el navegador recibe la fecha de
 * modificación real del archivo y vuelve a descargarlo solo cuando cambia.
 * Si el archivo no existe, devuelve cadena vacía (útil para plantillas que
 * apuntan a recursos dinámicos).
 *
 * @param string $ruta Ruta del asset relativa a assets/ (p. ej. "dashboard/usuarios.js").
 * @return string "?v=1234567890" o '' si el archivo no existe.
 */
function jv_asset_version(string $ruta): string
{
    $raiz = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
    $archivo = $raiz . '/assets/' . ltrim($ruta, '/');
    return is_file($archivo) ? '?v=' . filemtime($archivo) : '';
}

if (!function_exists('jv_tasa_bcv')) {
    /**
     * Devuelve la tasa oficial USD→VES del BCV.
     *
     * Prioridad absoluta: si existe una tasa fijada manualmente, se devuelve
     * de inmediato sin consultar la API ni el caché temporal. Si no existe,
     * reutiliza cache/tasas_bcv.json (30 min) y, al vencer, consulta la API.
     * Si la API falla, devuelve el caché viejo o null.
     *
     * @return array{usd:?float,fecha:?string,cache:bool,manual:bool} Tasa, fecha y origen.
     */
    function jv_tasa_bcv(): array
    {
        // 1. La tasa manual manda sobre la API y sobre cualquier caché vieja.
        $manual = jv_tasa_bcv_manual();
        if ($manual['usd'] !== null) {
            return [
                'usd'    => $manual['usd'],
                'fecha'  => $manual['fecha'],
                'cache'  => false,
                'manual' => true,
            ];
        }

        $raiz = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $archivo_cache = $raiz . '/cache/tasas_bcv.json';
        $ttl = 1800;

        $cache = null;
        if (is_file($archivo_cache)) {
            $cache = json_decode((string)@file_get_contents($archivo_cache), true);
            if (!is_array($cache)) {
                $cache = null;
            }
        }

        $vigente = $cache
            && !empty($cache['usd_oficial'])
            && (time() - (int)($cache['timestamp'] ?? 0)) < $ttl;

        if ($vigente) {
            return [
                'usd'   => (float)$cache['usd_oficial'],
                'fecha' => (string)($cache['fecha'] ?? ''),
                'cache' => true,
                'manual' => false,
            ];
        }

        $usd = jv_consultar_tasa_bcv_api();
        if ($usd !== null && $usd > 0) {
            $directorio = dirname($archivo_cache);
            if (!is_dir($directorio)) {
                @mkdir($directorio, 0755, true);
            }
            @file_put_contents($archivo_cache, json_encode([
                'usd_oficial' => $usd,
                'eur_oficial' => $cache['eur_oficial'] ?? null,
                'fecha'       => date('Y-m-d'),
                'timestamp'   => time(),
            ]), LOCK_EX);

            return ['usd' => $usd, 'fecha' => date('Y-m-d'), 'cache' => false, 'manual' => false];
        }

        // Sin internet: se usa el caché viejo si existe.
        if ($cache && !empty($cache['usd_oficial'])) {
            return [
                'usd'   => (float)$cache['usd_oficial'],
                'fecha' => (string)($cache['fecha'] ?? ''),
                'cache' => true,
                'manual' => false,
            ];
        }

        return ['usd' => null, 'fecha' => null, 'cache' => false, 'manual' => false];
    }
}

if (!function_exists('jv_limpiar_cache_tasa_bcv')) {
    /**
     * Invalida el caché temporal de la tasa BCV.
     *
     * Se ejecuta al guardar o retirar la tasa manual para que la siguiente
     * lectura no devuelva el valor viejo de cache/tasas_bcv.json.
     *
     * @return void
     */
    function jv_limpiar_cache_tasa_bcv(): void
    {
        $raiz = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $archivo_cache = $raiz . '/cache/tasas_bcv.json';
        if (is_file($archivo_cache)) {
            @unlink($archivo_cache);
        }
    }
}

if (!function_exists('jv_tasa_bcv_manual')) {
    /**
     * Devuelve la tasa BCV fijada manualmente (respaldo sin internet).
     *
     * @return array{usd:?float,fecha:?string} Tasa manual y su fecha (Y-m-d).
     */
    function jv_tasa_bcv_manual(): array
    {
        $valor = trim(getConfig('tasa_bcv_manual', ''));
        if ($valor === '' || !is_numeric($valor) || (float)$valor <= 0) {
            return ['usd' => null, 'fecha' => null];
        }

        $marca = trim(getConfig('tasa_bcv_manual_fecha', ''));
        return [
            'usd'   => (float)$valor,
            'fecha' => $marca !== '' ? substr($marca, 0, 10) : date('Y-m-d'),
        ];
    }
}

if (!function_exists('jv_tasa_bcv_referencia')) {
    /**
     * Última referencia conocida para validar una tasa manual.
     *
     * Usa el caché oficial (aunque esté vencido) y, si no existe, la última
     * tasa manual guardada.
     *
     * @return float|null Tasa de referencia o null si no hay ninguna.
     */
    function jv_tasa_bcv_referencia(): ?float
    {
        $raiz = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $archivo_cache = $raiz . '/cache/tasas_bcv.json';
        if (is_file($archivo_cache)) {
            $cache = json_decode((string)@file_get_contents($archivo_cache), true);
            if (is_array($cache) && !empty($cache['usd_oficial'])) {
                return (float)$cache['usd_oficial'];
            }
        }

        return jv_tasa_bcv_manual()['usd'];
    }
}

if (!function_exists('jv_validar_tasa_manual')) {
    /**
     * Valida una tasa ingresada a mano contra la última referencia.
     *
     * Evita que un error de tipeo (por ejemplo 700 cuando la referencia es
     * 800) se guarde como tasa de trabajo.
     *
     * @param float      $tasa       Tasa propuesta.
     * @param float|null $referencia Última tasa conocida.
     * @param float      $margen     Desviación máxima permitida (0.20 = 20%).
     * @return string|null Mensaje de error, o null si es válida.
     */
    function jv_validar_tasa_manual(float $tasa, ?float $referencia, float $margen = 0.20): ?string
    {
        if ($tasa <= 0) {
            return 'La tasa debe ser mayor que cero.';
        }
        if ($tasa > 100000) {
            return 'La tasa ingresada está fuera de rango.';
        }
        if ($referencia === null || $referencia <= 0) {
            return null;
        }

        $desviacion = abs($tasa - $referencia) / $referencia;
        if ($desviacion > $margen) {
            return 'La tasa ' . jv_formatear_bolivares($tasa, 2)
                . ' se aleja más del ' . (int)round($margen * 100) . '% de la última referencia ('
                . jv_formatear_bolivares($referencia, 2) . '). Verifica el valor.';
        }

        return null;
    }
}

if (!function_exists('jv_consultar_tasa_bcv_oficial')) {
    /**
     * Consulta la tasa USD en la página oficial del BCV.
     *
     * El sitio del BCV no envía el certificado intermedio completo; por eso
     * se usa la cadena Sectigo incluida en includes/certificados, manteniendo
     * la verificación SSL activa.
     *
     * @return float|null Tasa redondeada a 4 decimales, o null si falla.
     */
    function jv_consultar_tasa_bcv_oficial(): ?float
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $raiz = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $certificado = $raiz . '/includes/certificados/bcv_sectigo.pem';

        $canal = curl_init('https://www.bcv.org.ve/');
        $opciones = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => 4000,
            CURLOPT_CONNECTTIMEOUT_MS => 2500,
            CURLOPT_USERAGENT      => 'JV3000/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
        ];
        if (is_file($certificado)) {
            $opciones[CURLOPT_CAINFO] = $certificado;
        }
        curl_setopt_array($canal, $opciones);
        $respuesta = curl_exec($canal);
        curl_close($canal);

        if (!is_string($respuesta) || trim($respuesta) === '') {
            return null;
        }

        if (!preg_match('/id="dolar".*?<strong class="strong-tb">\s*([0-9.,]+)\s*<\/strong>/s', $respuesta, $coincidencia)) {
            return null;
        }

        $valor = str_replace(',', '.', trim($coincidencia[1]));
        if (!is_numeric($valor) || (float)$valor <= 0 || (float)$valor > 100000) {
            return null;
        }

        return round((float)$valor, 4);
    }
}

if (!function_exists('jv_consultar_tasa_bcv_dolarapi')) {
    /**
     * Consulta la tasa USD en ve.dolarapi.com como respaldo.
     *
     * @return float|null Tasa redondeada a 4 decimales, o null si falla.
     */
    function jv_consultar_tasa_bcv_dolarapi(): ?float
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $canal = curl_init('https://ve.dolarapi.com/v1/dolares/oficial');
        curl_setopt_array($canal, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => 2500,
            CURLOPT_CONNECTTIMEOUT_MS => 1500,
            CURLOPT_USERAGENT      => 'JV3000/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $respuesta = curl_exec($canal);
        curl_close($canal);

        if (!is_string($respuesta) || trim($respuesta) === '') {
            return null;
        }

        $datos = json_decode($respuesta, true);
        if (!is_array($datos)) {
            return null;
        }
        $promedio = $datos['promedio'] ?? ($datos[0]['promedio'] ?? null);
        if (!is_numeric($promedio) || (float)$promedio <= 0) {
            return null;
        }

        return round((float)$promedio, 4);
    }
}

if (!function_exists('jv_consultar_tasa_bcv_api')) {
    /**
     * Consulta la tasa oficial USD del BCV.
     *
     * Prioriza la página oficial del BCV; si no responde, usa dolarapi.com
     * como respaldo para no dejar la tasa en null.
     *
     * @return float|null Tasa redondeada a 4 decimales, o null si falla.
     */
    function jv_consultar_tasa_bcv_api(): ?float
    {
        $oficial = jv_consultar_tasa_bcv_oficial();
        if ($oficial !== null) {
            return $oficial;
        }

        return jv_consultar_tasa_bcv_dolarapi();
    }
}

if (!function_exists('jv_formatear_bolivares')) {
    /**
     * Formatea un monto en bolívares con separador de miles por coma y
     * decimales por punto, para que no se confunda con un decimal.
     *
     * @param float $monto     Monto en VES.
     * @param int   $decimales Decimales a mostrar (2 por defecto).
     * @return string Ejemplo: "Bs. 1,234.00" o "Bs. 897.54".
     */
    function jv_formatear_bolivares(float $monto, int $decimales = 2): string
    {
        return 'Bs. ' . number_format($monto, $decimales, '.', ',');
    }
}

if (!function_exists('format_bs')) {
    /**
     * Convierte un monto en dólares a bolívares para mostrarlo en la interfaz.
     *
     * La base de datos y la lógica interna siguen trabajando en USD; esta
     * función es exclusivamente de presentación. Usa la tasa manual si está
     * configurada, o la tasa automática del BCV como respaldo. Si no hay una
     * tasa válida devuelve una raya para no inventar montos.
     *
     * @param float      $monto_usd Monto en dólares.
     * @param float|null $tasa      Tasa VES por USD; si se omite se consulta.
     * @return string Ejemplo: "Bs. 1,771,275.00" o "—" si no hay tasa.
     */
    function format_bs(float $monto_usd, ?float $tasa = null): string
    {
        static $tasa_cache = null;

        if ($tasa === null || $tasa <= 0) {
            if ($tasa_cache === null) {
                $datos_tasa = jv_tasa_bcv();
                $tasa_cache = isset($datos_tasa['usd']) && (float)$datos_tasa['usd'] > 0
                    ? (float)$datos_tasa['usd']
                    : 0.0;
            }
            $tasa = $tasa_cache;
        }

        if ($tasa <= 0) {
            return '—';
        }

        return jv_formatear_bolivares($monto_usd * $tasa, 2);
    }
}
