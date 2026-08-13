<?php

// ==========================================
// MODELO: Proveedor
// ==========================================
// Única capa que consulta la base de datos.
// Validaciones de RIF/teléfono/duplicados y
// KPIs de crédito viven aquí.

/**
 * Proveedor: modelo del módulo de proveedores.
 *
 * Única capa autorizada para consultar la base de datos. Aquí viven las
 * validaciones de RIF, teléfono y duplicados, el registro/edición, el
 * cambio de estado y los KPIs de crédito, además de la migración de
 * teléfonos legacy.
 */
class Proveedor extends Model
{
    /**
     * Cambia el estado Activo/Inactivo de un proveedor (solo admin).
     *
     * Consulta el estado actual, lo invierte, actualiza el registro, registra
     * la auditoría y guarda un flash de éxito en sesión.
     *
     * @param int $idProveedor Identificador del proveedor.
     * @return void
     */
    public function toggleStatus(int $idProveedor): void
    {
        $prov = $this->db->fetchOne("SELECT status FROM proveedores WHERE id_proveedor = ?", [$idProveedor]);
        if ($prov) {
            $nuevo = $prov['status'] === 'Activo' ? 'Inactivo' : 'Activo';
            $this->db->execute("UPDATE proveedores SET status = ? WHERE id_proveedor = ?", [$nuevo, $idProveedor]);
            $accion = $nuevo === 'Activo' ? 'activar' : 'desactivar';
            registrarAuditoria($accion, 'Proveedor ' . $accion . 'do');
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PROVEEDOR ' . strtoupper($accion) . 'DO CON ÉXITO.'];
        }
    }

    /**
     * Procesa registrar o editar según la acción recibida.
     *
     * Normaliza y valida RIF, nombre, teléfono, email, lead time, límite y
     * días de crédito, condiciones de pago, moneda y status; luego delega
     * en registrar() o editar() según $d['accion'].
     *
     * @param array $d Datos del formulario del proveedor.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function procesar(array $d): array
    {
        $rif = normalizarDocumento($d['rif']);
        $nombre_empresa = mb_strtoupper(trim($d['nombre_empresa']));
        $telefono = trim($d['telefono_completo']);
        $contacto = trim($d['contacto_nombre']);
        $email = trim($d['email']);
        $direccion = trim($d['direccion']);
        $lead_time = !empty($d['lead_time']) ? min(365, max(0, (int)$d['lead_time'])) : null;

        $limite_raw = preg_replace('/[^0-9.]/', '', str_replace(',', '', trim($d['limite_credito'])));
        $limite_credito = !empty($limite_raw) ? min(999999999.99, max(0, (float)$limite_raw)) : null;

        $dias_credito = !empty($d['dias_credito']) ? min(360, max(0, (int)$d['dias_credito'])) : 0;
        $condiciones_pago = in_array($d['condiciones_pago'], ['Contado', 'Credito']) ? $d['condiciones_pago'] : 'Contado';
        $moneda = in_array($d['moneda'], ['USD', 'EUR', 'VES']) ? $d['moneda'] : 'USD';
        $status = in_array($d['status'], ['Activo', 'Inactivo']) ? $d['status'] : 'Activo';

        if (empty($nombre_empresa)) {
            return ['ok' => false, 'mensaje' => 'EL NOMBRE DE LA EMPRESA ES OBLIGATORIO.'];
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'FORMATO DE CORREO ELECTRÓNICO INVÁLIDO.'];
        }
        if (!validarRIF($rif)) {
            return ['ok' => false, 'mensaje' => 'FORMATO DE RIF INVÁLIDO. USE: J-12345678-0'];
        }
        if (empty($telefono) || strlen(preg_replace('/[^0-9+]/', '', $telefono)) < 7) {
            return ['ok' => false, 'mensaje' => 'TELÉFONO INVÁLIDO. INGRESE UN NÚMERO VÁLIDO.'];
        }

        $data = [
            'rif' => $rif,
            'nombre_empresa' => $nombre_empresa,
            'contacto' => $contacto,
            'telefono' => $telefono,
            'email' => $email,
            'direccion' => $direccion,
            'lead_time' => $lead_time,
            'limite_credito' => $limite_credito,
            'dias_credito' => $dias_credito,
            'condiciones_pago' => $condiciones_pago,
            'moneda' => $moneda,
            'status' => $status,
        ];
        $data = array_filter($data, fn($v) => $v !== null);

        if ($d['accion'] === 'registrar') {
            return $this->registrar($data, $rif, $nombre_empresa, $email);
        }

        if ($d['accion'] === 'editar') {
            return $this->editar((int)$d['id_proveedor'], $data, $rif, $nombre_empresa, $email);
        }

        return ['ok' => false, 'mensaje' => 'ACCIÓN INVÁLIDA.'];
    }

    /**
     * Registra un nuevo proveedor verificando duplicados.
     *
     * Rechaza si el RIF, el nombre de empresa o el email ya pertenecen a
     * otro proveedor. Inserta el registro y audita la creación.
     *
     * @param array  $data   Datos normalizados listos para insertar.
     * @param string $rif    RIF normalizado.
     * @param string $nombre Nombre de empresa normalizado.
     * @param string $email  Email del proveedor (puede estar vacío).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    private function registrar(array $data, string $rif, string $nombre, string $email): array
    {
        if ($this->db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(rif) = LOWER(?)", [$rif])) {
            return ['ok' => false, 'mensaje' => 'EL RIF YA PERTENECE A OTRO PROVEEDOR.'];
        }
        if ($this->db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(nombre_empresa) = LOWER(?)", [$nombre])) {
            return ['ok' => false, 'mensaje' => 'YA EXISTE UN PROVEEDOR CON ESE NOMBRE DE EMPRESA.'];
        }
        if (!empty($email) && $this->db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(email) = LOWER(?)", [$email])) {
            return ['ok' => false, 'mensaje' => 'EL CORREO ELECTRÓNICO YA PERTENECE A OTRO PROVEEDOR.'];
        }
        try {
            $this->db->insert('proveedores', $data);
            registrarAuditoria('crear', 'Proveedor registrado: ' . $nombre);
            return ['ok' => true, 'mensaje' => 'PROVEEDOR REGISTRADO CON ÉXITO.'];
        } catch (Exception $e) {
            error_log("Error al registrar proveedor: " . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'ERROR AL REGISTRAR: ' . $e->getMessage()];
        }
    }

/**
     * Edita un proveedor existente verificando duplicados (excluyendo el propio).
     *
     * Rechaza si el RIF, el nombre o el email ya pertenecen a otro
     * proveedor. Actualiza el registro y audita la modificación.
     *
     * @param int    $idProv Identificador del proveedor a editar.
     * @param array  $data   Datos normalizados listos para actualizar.
     * @param string $rif    RIF normalizado.
     * @param string $nombre Nombre de empresa normalizado.
     * @param string $email  Email del proveedor (puede estar vacío).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    private function editar(int $idProv, array $data, string $rif, string $nombre, string $email): array
    {
        if ($this->db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(rif) = LOWER(?) AND id_proveedor != ?", [$rif, $idProv])) {
            return ['ok' => false, 'mensaje' => 'EL RIF YA PERTENECE A OTRO PROVEEDOR.'];
        }
        if ($this->db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(nombre_empresa) = LOWER(?) AND id_proveedor != ?", [$nombre, $idProv])) {
            return ['ok' => false, 'mensaje' => 'YA EXISTE UN PROVEEDOR CON ESE NOMBRE DE EMPRESA.'];
        }
        if (!empty($email) && $this->db->fetchOne("SELECT id_proveedor FROM proveedores WHERE LOWER(email) = LOWER(?) AND id_proveedor != ?", [$email, $idProv])) {
            return ['ok' => false, 'mensaje' => 'EL CORREO ELECTRÓNICO YA PERTENECE A OTRO PROVEEDOR.'];
        }
        try {
            $this->db->update('proveedores', $data, 'id_proveedor = ?', [$idProv]);
            registrarAuditoria('editar', 'Proveedor modificado: ' . $nombre);
            return ['ok' => true, 'mensaje' => 'DATOS ACTUALIZADOS CORRECTAMENTE.'];
        } catch (Exception $e) {
            error_log("Error al editar proveedor: " . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'ERROR AL ACTUALIZAR: ' . $e->getMessage()];
        }
    }

    /**
     * Listado completo de proveedores ordenado por nombre.
     *
     * @return array Todos los proveedores.
     */
    public function listar(): array
    {
        return $this->db->fetchAll("SELECT * FROM proveedores ORDER BY nombre_empresa ASC");
    }

    /**
     * KPI: número de proveedores activos.
     *
     * @return int Cantidad de proveedores con estado Activo.
     */
    public function totalActivos(): int
    {
        return (int)($this->db->fetchOne("SELECT COUNT(*) as t FROM proveedores WHERE status='Activo'")['t'] ?? 0);
    }

    /**
     * KPI: límite de crédito total de los proveedores activos.
     *
     * Suma los límites de crédito de los proveedores activos con límite > 0.
     *
     * @return float Límite de crédito total.
     */
    public function limiteCreditoTotal(): float
    {
        return (float)($this->db->fetchOne("SELECT COALESCE(SUM(limite_credito),0) as t FROM proveedores WHERE limite_credito > 0 AND status = 'Activo'")['t'] ?? 0);
    }

    /**
     * Mapa id_proveedor → crédito usado (compras Activas a crédito).
     *
     * @return array Mapa [id_proveedor => total usado en crédito].
     */
    public function creditoUsado(): array
    {
        $mapa = [];
        foreach ($this->db->fetchAll("SELECT id_proveedor, COALESCE(SUM(total),0) as usado FROM compras WHERE status = 'Activa' AND condiciones_pago = 'Credito' AND id_proveedor IS NOT NULL GROUP BY id_proveedor") as $r) {
            $mapa[$r['id_proveedor']] = (float)$r['usado'];
        }
        return $mapa;
    }

    /**
     * Migra teléfonos legacy (04XX... / (0...) a formato E.164 +58.
     *
     * Recorre los proveedores cuyo teléfono empieza con "(0", le quita los
     * caracteres no numéricos y el 0 inicial, y lo convierte a +58 seguido
     * del número, actualizando el registro.
     *
     * @return void
     */
    public function migrarTelefonosLegacy(): void
    {
        foreach ($this->db->fetchAll("SELECT id_proveedor, telefono FROM proveedores WHERE telefono LIKE '(0%'") as $p) {
            $limpio = preg_replace('/[^0-9]/', '', $p['telefono']);
            $limpio = ltrim($limpio, '0');
            $e164 = '+58' . $limpio;
            $this->db->execute("UPDATE proveedores SET telefono = ? WHERE id_proveedor = ?", [$e164, $p['id_proveedor']]);
        }
    }
}
