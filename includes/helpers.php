<?php

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================
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

// Códigos derivados de entidades (sin estado adicional)
if (!function_exists('codigoCliente')) {
    function codigoCliente(int $id_cliente): string
    {
        return 'CLI-' . str_pad($id_cliente, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('codigoProveedor')) {
    function codigoProveedor(int $id_proveedor): string
    {
        return 'PROV-' . str_pad($id_proveedor, 6, '0', STR_PAD_LEFT);
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

// Normalizar respuesta de seguridad (trim + minúsculas + sin acentos)
function normalizarRespuestaSeguridad(string $respuesta): string
{
    $r = trim($respuesta);
    $r = strtr($r, [
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'À' => 'a', 'È' => 'e', 'Ì' => 'i', 'Ò' => 'o', 'Ù' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'Â' => 'a', 'Ê' => 'e', 'Î' => 'i', 'Ô' => 'o', 'Û' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'Ã' => 'a', 'Õ' => 'o', 'ã' => 'a', 'õ' => 'o',
    ]);
    return strtolower($r);
}

// Validar respuesta de seguridad: flexible, acepta desde un solo carácter
function validarRespuestaSeguridad(string $respuesta): bool
{
    return normalizarRespuestaSeguridad($respuesta) !== '';
}

// Verificar respuesta: normaliza ambos textos antes de comparar
// (con retro-compatibilidad para respuestas guardadas sin normalizar)
function verificarRespuestaSeguridad(string $respuesta, string $hash): bool
{
    $normalizada = normalizarRespuestaSeguridad($respuesta);
    if ($normalizada !== '' && password_verify($normalizada, $hash)) return true;
    return $respuesta !== '' && password_verify($respuesta, $hash);
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

// ==========================================
// ALERTAS CRÍTICAS DE STOCK (CAMPANA)
// ==========================================
if (!function_exists('jv_alertas_por_rol')) {
    /**
     * Alertas de stock para la campana del Panel de Inicio.
     * - Vencidos (< hoy), Próximos (hoy a +7 días), Prontos (+8 a +30 días), Stock bajo.
     * - Roles 1 (Admin) y 2 (Operador de Carga): vencidos + próximos + prontos + stock bajo.
     * - Rol 3 (Operador de Ventas): solo vencidos + próximos + prontos.
     * Devuelve hasta 8 productos por categoría y los totales reales para el badge.
     */
    function jv_alertas_por_rol(?int $id_rol = null): array
    {
        $db = Database::getInstance();
        $id_rol = $id_rol ?? (int)($_SESSION['id_rol'] ?? 0);
        $es_ventas = ($id_rol === 3);

        $vencidos = [];
        $proximos = [];
        $prontos = [];
        $bajos = [];

        $filas = $db->fetchAll(
            "SELECT id_producto, nombre_producto, fecha_vencimiento
             FROM productos
             WHERE status = 'Activo' AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento < CURDATE()
             ORDER BY fecha_vencimiento ASC
             LIMIT 8"
        );
        foreach ($filas as $r) {
            $vencidos[] = [
                'id' => (int)$r['id_producto'],
                'nombre' => (string)$r['nombre_producto'],
                'fecha' => date('d/m/Y', strtotime($r['fecha_vencimiento'])),
            ];
        }

        $filas = $db->fetchAll(
            "SELECT id_producto, nombre_producto, fecha_vencimiento
             FROM productos
             WHERE status = 'Activo' AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento >= CURDATE()
               AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             ORDER BY fecha_vencimiento ASC
             LIMIT 8"
        );
        foreach ($filas as $r) {
            $proximos[] = [
                'id' => (int)$r['id_producto'],
                'nombre' => (string)$r['nombre_producto'],
                'fecha' => date('d/m/Y', strtotime($r['fecha_vencimiento'])),
            ];
        }

        $filas = $db->fetchAll(
            "SELECT id_producto, nombre_producto, fecha_vencimiento
             FROM productos
             WHERE status = 'Activo' AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY fecha_vencimiento ASC
             LIMIT 8"
        );
        foreach ($filas as $r) {
            $prontos[] = [
                'id' => (int)$r['id_producto'],
                'nombre' => (string)$r['nombre_producto'],
                'fecha' => date('d/m/Y', strtotime($r['fecha_vencimiento'])),
            ];
        }

        $count_vencidos = (int)($db->fetchOne(
            "SELECT COUNT(*) AS n FROM productos
             WHERE status = 'Activo' AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE()"
        )['n'] ?? 0);

        $count_proximos = (int)($db->fetchOne(
            "SELECT COUNT(*) AS n FROM productos
             WHERE status = 'Activo' AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento >= CURDATE()
               AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        )['n'] ?? 0);

        $count_prontos = (int)($db->fetchOne(
            "SELECT COUNT(*) AS n FROM productos
             WHERE status = 'Activo' AND fecha_vencimiento IS NOT NULL
               AND fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        )['n'] ?? 0);

        if (!$es_ventas) {
            $filas = $db->fetchAll(
                "SELECT id_producto, nombre_producto, stock_actual, stock_minimo
                 FROM productos
                 WHERE status = 'Activo' AND stock_actual <= stock_minimo
                 ORDER BY stock_actual ASC
                 LIMIT 8"
            );
            foreach ($filas as $r) {
                $bajos[] = [
                    'id' => (int)$r['id_producto'],
                    'nombre' => (string)$r['nombre_producto'],
                    'stock' => (int)$r['stock_actual'],
                    'minimo' => (int)$r['stock_minimo'],
                ];
            }
            $count_bajos = (int)($db->fetchOne(
                "SELECT COUNT(*) AS n FROM productos WHERE status = 'Activo' AND stock_actual <= stock_minimo"
            )['n'] ?? 0);
        } else {
            $count_bajos = 0;
        }

        $total = $count_vencidos + $count_proximos + $count_prontos + $count_bajos;

        return [
            'total' => $total,
            'counts' => ['vencidos' => $count_vencidos, 'proximos' => $count_proximos, 'prontos' => $count_prontos, 'bajos' => $count_bajos],
            'vencidos' => $vencidos,
            'proximos' => $proximos,
            'prontos' => $prontos,
            'bajos' => $bajos,
        ];
    }
}

