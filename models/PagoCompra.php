<?php

// ==========================================
// MODELO: PagoCompra (historial de pagos)
// ==========================================
// Registra pagos parciales contra compras,
// calcula saldo pendiente y aging.

/**
 * PagoCompra: gestiona el historial de pagos de compras.
 *
 * Permite registrar pagos parciales, consultar el total pagado
 * y el saldo pendiente de una compra.
 */
class PagoCompra extends Model
{
    /**
     * Registra un pago parcial contra una compra.
     *
     * Valida que la compra exista, no esté anulada y que el monto
     * no exceda el saldo pendiente. Actualiza status_pago y monto_pago
     * en la tabla compras.
     *
     * @param int    $idCompra  Identificador de la compra.
     * @param float  $monto     Monto del pago (debe ser > 0 y <= saldo).
     * @param string $metodo    Método de pago: Efectivo, Transferencia, Cheque, Otro.
     * @param array  $detalle   Detalles adicionales (JSON: banco, referencia, teléfono, etc.).
     * @param int    $idUsuario Identificador del usuario que registra el pago.
     * @return array ['ok'=>bool, 'mensaje'=>string, 'saldo_pendiente'=>float].
     */
    public function registrar(int $idCompra, float $monto, string $metodo, array $detalle, int $idUsuario): array
    {
        $compra = $this->db->fetchOne(
            "SELECT id_compra, total, status, status_pago, COALESCE(monto_pago, 0) as monto_pago
             FROM compras WHERE id_compra = ?",
            [$idCompra]
        );

        if (!$compra) {
            return ['ok' => false, 'mensaje' => 'LA COMPRA NO EXISTE.'];
        }
        if ($compra['status'] === 'Anulada') {
            return ['ok' => false, 'mensaje' => 'LA COMPRA ESTÁ ANULADA.'];
        }
        if ($monto <= 0) {
            return ['ok' => false, 'mensaje' => 'EL MONTO DEBE SER MAYOR A CERO.'];
        }

        $total = (float)$compra['total'];
        $yaPagado = (float)$compra['monto_pago'];
        $saldo = $total - $yaPagado;

        if ($saldo <= 0) {
            return ['ok' => false, 'mensaje' => 'LA COMPRA YA ESTÁ PAGADA COMPLETAMENTE.'];
        }
        if ($monto > $saldo + 0.01) {
            return ['ok' => false, 'mensaje' => "EL MONTO EXCEDE EL SALDO PENDIENTE (\$" . number_format($saldo, 2) . ")."];
        }

        $metodosValidos = ['Efectivo', 'Transferencia', 'Cheque', 'Otro'];
        if (!in_array($metodo, $metodosValidos, true)) {
            return ['ok' => false, 'mensaje' => 'MÉTODO DE PAGO INVÁLIDO.'];
        }

        $this->db->begin();
        try {
            // Insertar pago
            $this->db->execute(
                "INSERT INTO pagos_compra (id_compra, id_usuario, monto, metodo_pago, detalle_pago, fecha_pago)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$idCompra, $idUsuario, $monto, $metodo, !empty($detalle) ? json_encode($detalle) : null]
            );

            // Actualizar acumulado en compras
            $nuevoPagado = $yaPagado + $monto;
            $nuevoStatus = $nuevoPagado >= $total ? 'Pagada' : 'Parcial';
            $this->db->execute(
                "UPDATE compras SET monto_pago = ?, status_pago = ?, fecha_pago = NOW() WHERE id_compra = ?",
                [$nuevoPagado, $nuevoStatus, $idCompra]
            );

            $this->db->commit();

            $saldoPendiente = max(0, $total - $nuevoPagado);
            registrarAuditoria('crear', "Pago de \$" . number_format($monto, 2) . " registrado en Compra #$idCompra ($metodo)");

            $mensaje = $saldoPendiente <= 0.01
                ? 'PAGO REGISTRADO. COMPRA PAGADA COMPLETAMENTE.'
                : 'PAGO REGISTRADO. SALDO PENDIENTE: $' . number_format($saldoPendiente, 2) . '.';

            return ['ok' => true, 'mensaje' => $mensaje, 'saldo_pendiente' => $saldoPendiente];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR AL REGISTRAR EL PAGO.'];
        }
    }

    /**
     * Lista los pagos de una compra ordenados por fecha descendente.
     *
     * @param int $idCompra Identificador de la compra.
     * @return array Lista de pagos con datos del usuario.
     */
    public function listarPorCompra(int $idCompra): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, u.usuario as registrado_por
             FROM pagos_compra p
             JOIN usuarios u ON p.id_usuario = u.id_usuario
             WHERE p.id_compra = ?
             ORDER BY p.fecha_pago DESC",
            [$idCompra]
        );
    }

    /**
     * Total pagado de una compra.
     *
     * @param int $idCompra Identificador de la compra.
     * @return float Monto total pagado.
     */
    public function totalPagado(int $idCompra): float
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(monto), 0) as total FROM pagos_compra WHERE id_compra = ?",
            [$idCompra]
        );
        return (float)($row['total'] ?? 0);
    }

    /**
     * Saldo pendiente de una compra.
     *
     * @param int $idCompra Identificador de la compra.
     * @return float Monto pendiente (> 0 si no está pagado completo).
     */
    public function saldoPendiente(int $idCompra): float
    {
        $compra = $this->db->fetchOne("SELECT total FROM compras WHERE id_compra = ?", [$idCompra]);
        if (!$compra) return 0;
        return max(0, (float)$compra['total'] - $this->totalPagado($idCompra));
    }
}
