<?php

// Proteger contra redeclaración
if (!function_exists('validarRIF')) {
    /**
     * Valida el formato del RIF/CI venezolano.
     * Formato esperado: Letra-Cuerpo-Dígito (Ej: J-12345678-0)
     * @param string $rif
     * @return bool
     */
    function validarRIF($rif)
    {
        $rif_regex = '/^[VJGPE]-\d{7,9}(?:-\d)?$/';
        return (bool)preg_match($rif_regex, $rif);
    }
}

if (!function_exists('validarTelefono')) {
    /**
     * Valida el formato de teléfono venezolano.
     * Formato esperado: (04XX) 000-0000 o (02XX) 000-0000
     * @param string $tel
     * @return bool
     */
    function validarTelefono($tel)
    {
        return (bool)preg_match('/^\(\d{4}\) \d{3}-\d{4}$/', $tel);
    }
}

if (!function_exists('generarTokenCSRF')) {
    /**
     * Genera un token CSRF y lo guarda en sesión
     * @return string
     */
    function generarTokenCSRF()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validarTokenCSRF')) {
    /**
     * Valida un token CSRF
     * @param string $token
     * @return bool
     */
    function validarTokenCSRF($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('getConfig')) {
    function getConfig(string $clave, string $default = ''): string
    {
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT valor FROM configuracion WHERE clave = ?", [$clave]);
        return $row ? $row['valor'] : $default;
    }
}

if (!function_exists('generarControlNumero')) {
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
    function generarFacturaNumero()
    {
        $db = Database::getInstance();
        $db->begin();
        try {
            $cnt = $db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='FAC' FOR UPDATE");
            if (!$cnt) {
                $db->execute("INSERT INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('FAC', 0)");
                $prox = 1;
            } else {
                $prox = intval($cnt['ultimo_numero']) + 1;
            }
            $db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='FAC'", [$prox]);
            $db->commit();
            return 'FAC-' . str_pad($prox, 4, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            $db->rollback();
            return 'FAC-ERROR';
        }
    }
}

if (!function_exists('registrarAuditoria')) {
    function registrarAuditoria(string $accion, string $detalle = '')
    {
        $db = Database::getInstance();
        $id_usuario = intval($_SESSION['id_usuario'] ?? 0);
        $usuario_nombre = $_SESSION['usuario'] ?? 'Sistema';
        $db->execute("INSERT INTO auditoria (id_usuario, usuario_nombre, accion, detalle) VALUES (?, ?, ?, ?)", [$id_usuario, $usuario_nombre, $accion, $detalle]);
    }
}

if (!function_exists('getPreguntasRespuestas')) {
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

function validarRespuestaSeguridad(string $respuesta): bool
{
    $r = trim($respuesta);
    if (strlen($r) < 3 || strlen($r) > 50) return false;
    if (!preg_match('/[a-zA-Z]/', $r)) return false;
    if (!preg_match('/[aeiouAEIOU]/', $r)) return false;
    if (preg_match('/(.)\1{3,}/', $r)) return false;
    if (preg_match('/abcdef|bcdefg|cdefgh|defghi|efghij|fghijk|ghijkl|hijklm|ijklmn|jklmno|klmnop|lmnopq|mnopqr|nopqrs|opqrst|pqrstu|qrstuv|rstuvw|stuvwx|tuvwxy|uvwxyz/i', $r)) return false;
    if (preg_match('/0123|1234|2345|3456|4567|5678|6789/', $r)) return false;
    $patrones = ['/asdf/i', '/qwerty/i', '/zxcv/i', '/abcd/i'];
    foreach ($patrones as $p) {
        if (preg_match($p, $r)) return false;
    }
    return true;
}
}
