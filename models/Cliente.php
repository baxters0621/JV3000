<?php

// ==========================================
// MODELO: Cliente
// ==========================================
// Gestión CRUD de clientes dentro del módulo Ventas.
// Validación de documento fiscal, duplicados y toggle status.

/**
 * Cliente: modelo del módulo de clientes (gestionado desde Ventas).
 *
 * Capa de acceso a datos: listar, registrar, editar, toggle status y
 * validación de documento fiscal duplicado.
 */
class Cliente extends Model
{
    /**
     * Lista todos los clientes ordenados por id descendente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar(): array
    {
        return $this->db->fetchAll(
            "SELECT id_cliente, nombre, documento, telefono, direccion, status
             FROM clientes ORDER BY id_cliente DESC"
        );
    }

    /**
     * Retorna el total de clientes con status Activo.
     *
     * @return int
     */
    public function totalActivos(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS total FROM clientes WHERE status = 'Activo'");
        return $row ? (int)$row['total'] : 0;
    }

    /**
     * Cambia el estado Activo/Inactivo de un cliente (solo admin).
     *
     * @param int $idCliente Identificador del cliente.
     * @return void
     */
    public function toggleStatus(int $idCliente): void
    {
        $cliente = $this->db->fetchOne(
            "SELECT status, nombre FROM clientes WHERE id_cliente = ?",
            [$idCliente]
        );
        if ($cliente) {
            $nuevoStatus = $cliente['status'] === 'Activo' ? 'Inactivo' : 'Activo';
            $this->db->execute(
                "UPDATE clientes SET status = ? WHERE id_cliente = ?",
                [$nuevoStatus, $idCliente]
            );
            $accion = $nuevoStatus === 'Activo' ? 'activar' : 'desactivar';
            registrarAuditoria($accion, 'Cliente ' . $accion . 'do: ' . $cliente['nombre']);
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'CLIENTE ' . strtoupper($accion) . 'DO CON ÉXITO.'];
        }
    }

    /**
     * Procesa registrar o editar un cliente.
     *
     * @param array $datos ['accion','nombre','documento','telefono','direccion','status']
     * @return array ['ok'=>bool, 'mensaje'=>string]
     */
    public function procesar(array $datos): array
    {
        $nombre   = mb_strtoupper(trim($datos['nombre'] ?? ''));
        $documento = normalizarDocumento($datos['documento'] ?? '');
        $telefono  = trim($datos['telefono'] ?? '');
        $direccion = trim($datos['direccion'] ?? '');
        $status    = in_array($datos['status'] ?? '', ['Activo', 'Inactivo']) ? $datos['status'] : 'Activo';
        $accion    = $datos['accion'] ?? '';
        $id        = (int)($datos['id_cliente'] ?? 0);

        if ($nombre === '') {
            return ['ok' => false, 'mensaje' => 'EL NOMBRE ES OBLIGATORIO.'];
        }

        if ($documento !== '' && $documento !== 'N/A') {
            if (!validarDocumentoFiscal($documento)) {
                return ['ok' => false, 'mensaje' => 'DOCUMENTO FISCAL INVÁLIDO.'];
            }
            $duplicado = $this->db->fetchOne(
                "SELECT id_cliente FROM clientes WHERE documento = ? AND id_cliente != ?",
                [$documento, $id ?: 0]
            );
            if ($duplicado) {
                return ['ok' => false, 'mensaje' => 'YA EXISTE UN CLIENTE CON ESE DOCUMENTO.'];
            }
        }

        if ($accion === 'editar' && $id > 0) {
            return $this->editar($id, $nombre, $documento, $telefono, $direccion, $status);
        }
        return $this->registrar($nombre, $documento, $telefono, $direccion, $status);
    }

    /**
     * Inserta un nuevo cliente.
     */
    private function registrar(string $nombre, string $documento, string $telefono, string $direccion, string $status): array
    {
        $this->db->execute(
            "INSERT INTO clientes (nombre, documento, telefono, direccion, status)
             VALUES (?, ?, ?, ?, ?)",
            [$nombre, $documento ?: null, $telefono ?: null, $direccion ?: null, $status]
        );
        registrarAuditoria('registrar', 'Cliente registrado: ' . $nombre);
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'CLIENTE REGISTRADO EXITOSAMENTE.'];
        return ['ok' => true, 'mensaje' => 'Cliente registrado.'];
    }

    /**
     * Actualiza un cliente existente.
     */
    private function editar(int $id, string $nombre, string $documento, string $telefono, string $direccion, string $status): array
    {
        $this->db->execute(
            "UPDATE clientes SET nombre=?, documento=?, telefono=?, direccion=?, status=?
             WHERE id_cliente=?",
            [$nombre, $documento ?: null, $telefono ?: null, $direccion ?: null, $status, $id]
        );
        registrarAuditoria('editar', 'Cliente editado: ' . $nombre);
        $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'CLIENTE ACTUALIZADO CON ÉXITO.'];
        return ['ok' => true, 'mensaje' => 'Cliente actualizado.'];
    }
}
