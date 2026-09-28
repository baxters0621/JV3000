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
        if (!is_finite($monto) || $monto <= 0) {
            return ['ok' => false, 'mensaje' => 'EL MONTO DEBE SER MAYOR A CERO.'];
        }
        $metodosValidos = ['Efectivo', 'Transferencia', 'Cheque', 'Otro'];
        if (!in_array($metodo, $metodosValidos, true)) {
            return ['ok' => false, 'mensaje' => 'MÉTODO DE PAGO INVÁLIDO.'];
        }

        $monto = round($monto, 2);
        $this->db->begin();
        try {
            $compra = $this->db->fetchOne(
                "SELECT id_compra, total, status, status_pago, COALESCE(monto_pago, 0) as monto_pago
                 FROM compras WHERE id_compra = ? FOR UPDATE",
                [$idCompra]
            );
            $rechazar = function (string $mensaje): array {
                $this->db->rollback();
                return ['ok' => false, 'mensaje' => $mensaje];
            };
            if (!$compra) {
                return $rechazar('LA COMPRA NO EXISTE.');
            }
            if ($compra['status'] === 'Anulada') {
                return $rechazar('LA COMPRA ESTÁ ANULADA.');
            }

            $total = (float)$compra['total'];
            $yaPagado = (float)$compra['monto_pago'];
            $saldo = max(0, $total - $yaPagado);
            if ($saldo <= 0) {
                return $rechazar('LA COMPRA YA ESTÁ PAGADA COMPLETAMENTE.');
            }
            if ($monto > round($saldo, 2)) {
                return $rechazar("EL MONTO EXCEDE EL SALDO PENDIENTE (\$" . number_format($saldo, 2) . ").");
            }

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

            registrarAuditoria('crear', "Pago de \$" . number_format($monto, 2) . " registrado en Compra #$idCompra ($metodo)");
            $this->db->commit();

            $saldoPendiente = max(0, $total - $nuevoPagado);
            $mensaje = $saldoPendiente <= 0.01
                ? 'PAGO REGISTRADO. COMPRA PAGADA COMPLETAMENTE.'
                : 'PAGO REGISTRADO. SALDO PENDIENTE: $' . number_format($saldoPendiente, 2) . '.';

            return ['ok' => true, 'mensaje' => $mensaje, 'saldo_pendiente' => $saldoPendiente];
        } catch (Throwable $e) {
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
            "SELECT GREATEST(COALESCE(c.monto_pago, 0), COALESCE(SUM(p.monto), 0)) AS total
             FROM compras c
             LEFT JOIN pagos_compra p ON p.id_compra = c.id_compra
             WHERE c.id_compra = ?
             GROUP BY c.id_compra, c.monto_pago",
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
