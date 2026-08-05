<?php

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================
if (!function_exists('validarRIF')) {
    /**
     * Valida el formato del RIF/CI venezolano.
     * Formato esperado: Letra-Cuerpo-Dígito (Ej: J-12345678-0)
     * @param string $rif
     * @return bool
     */
    // Validar RIF/CI venezolano
    function validarRIF($rif)
    {
        $rif_regex = '/^[VJGPE]-\d{7,9}-\d$/';
        return (bool)preg_match($rif_regex, $rif);
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

if (!function_exists('formatearTelefono')) {
    /**
     * Convierte E.164 (+584124862167) a formato legible (+58 412-486-2167)
     * @param string $e164
     * @return string
     */
function formatearTelefono($e164)
{
    if (str_starts_with($e164, '+58')) {
        $num = substr($e164, 3);
        return '(58) ' . substr($num, 0, 3) . '-' . substr($num, 3);
    }
    if (preg_match('/^\+(\d{1,3})(\d+)$/', $e164, $m)) {
        $parts = str_split($m[2], 3);
        return '+' . $m[1] . ' ' . implode('-', $parts);
    }
    return $e164;
}
}

// Helpers de BD / Config
if (!function_exists('getConfig')) {
    // Obtener valor de configuración
    function getConfig(string $clave, string $default = ''): string
    {
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT valor FROM configuracion WHERE clave = ?", [$clave]);
        return $row ? $row['valor'] : $default;
    }
}

// Generadores de números secuenciales
if (!function_exists('generarControlNumero')) {
    // Generar número de control
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
    // Generar número de Nota de Entrega
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
        } catch (Exception $e) {
            $db->rollback();
            return 'NDE-ERROR';
        }
    }
}

if (!function_exists('validarPasswordFuerte')) {
    // Validar contraseña fuerte: mín 8, mayúscula, minúscula, número y símbolo
    function validarPasswordFuerte(string $password): bool
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password) === 1;
    }
}

if (!function_exists('purgarPreviewsSesion')) {
    // Elimina previews de ventas abandonados (solo queda el más reciente en sesión)
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
    // Registrar acción en auditoría
    function registrarAuditoria(string $accion, string $detalle = '')
    {
        $db = Database::getInstance();
        $id_usuario = intval($_SESSION['id_usuario'] ?? 0);
        $usuario_nombre = $_SESSION['usuario'] ?? 'Sistema';
        $db->execute("INSERT INTO auditoria (id_usuario, usuario_nombre, accion, detalle) VALUES (?, ?, ?, ?)", [$id_usuario, $usuario_nombre, $accion, $detalle]);
    }
}

// Preguntas de seguridad
if (!function_exists('getPreguntasRespuestas')) {
// Obtener preguntas de seguridad
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

// Validar respuesta de seguridad
function validarRespuestaSeguridad(string $respuesta): bool
{
    $r = trim($respuesta);
    if (strlen($r) < 5 || strlen($r) > 20) return false;
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

// ==========================================
// LOTES DE INVENTARIO (FEFO)
// ==========================================
if (!function_exists('capacidadProducto')) {
    /**
     * Capacidad de almacenamiento efectiva de un producto:
     * propia (stock_maximo) si > 0; si no, la de su categoría; si no, 100.
     */
    function capacidadProducto($db, int $id_producto): int
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
     */
    function lotesConsumibles($db, int $id_producto, bool $solo_vencidos = false): array
    {
        if ($solo_vencidos) {
            return $db->fetchAll(
                "SELECT id_lote, cantidad_restante, fecha_vencimiento
                 FROM lotes
                 WHERE id_producto = ? AND cantidad_restante > 0
                   AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= CURDATE()
                 ORDER BY fecha_vencimiento ASC, id_lote ASC",
                [$id_producto]
            );
        }
        return $db->fetchAll(
            "SELECT id_lote, cantidad_restante, fecha_vencimiento
             FROM lotes
             WHERE id_producto = ? AND cantidad_restante > 0
               AND (fecha_vencimiento IS NULL OR fecha_vencimiento > CURDATE())
             ORDER BY (fecha_vencimiento IS NULL) ASC, fecha_vencimiento ASC, id_lote ASC",
            [$id_producto]
        );
    }
}

if (!function_exists('stockLoteDisponible')) {
    /** Stock total disponible de un producto según el modo (solo vencidos o vigentes). */
    function stockLoteDisponible($db, int $id_producto, bool $solo_vencidos = false): int
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
     */
    function consumirLotes($db, int $id_producto, int $cantidad, bool $solo_vencidos = false): array
    {
        $restante = $cantidad;
        $usados = [];
        foreach (lotesConsumibles($db, $id_producto, $solo_vencidos) as $lote) {
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
    function devolverLote($db, int $id_lote, int $cantidad): void
    {
        $db->execute("UPDATE lotes SET cantidad_restante = cantidad_restante + ? WHERE id_lote = ?", [$cantidad, $id_lote]);
    }
}

