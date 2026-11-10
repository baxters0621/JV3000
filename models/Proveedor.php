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
 * cambio de estado y el catálogo de costos (entidad asociativa
 * Proveedor ↔ Producto con su costo y código interno).
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
        $proveedor = $this->db->fetchOne("SELECT status, updated_at FROM proveedores WHERE id_proveedor = ?", [$idProveedor]);
        if ($proveedor) {
            $nuevoStatus = $proveedor['status'] === 'Activo' ? 'Inactivo' : 'Activo';
            $this->db->execute("UPDATE proveedores SET status = ? WHERE id_proveedor = ? AND updated_at = ?", [$nuevoStatus, $idProveedor, $proveedor['updated_at']]);
            if ($this->db->affectedRows() === 0) {
                $_SESSION['flash_msg'] = ['tipo' => 'danger', 'texto' => 'CONFLICTO: OTRO USUARIO MODIFICÓ EL PROVEEDOR. RECARGUE.'];
                return;
            }
            $accion = $nuevoStatus === 'Activo' ? 'activar' : 'desactivar';
            registrarAuditoria($accion, 'Proveedor ' . $accion . 'do');
            $_SESSION['flash_msg'] = ['tipo' => 'success', 'texto' => 'PROVEEDOR ' . strtoupper($accion) . 'DO CON ÉXITO.'];
        }
    }

    /**
     * Procesa registrar o editar según la acción recibida.
     *
     * Normaliza y valida RIF, nombre, teléfono, email y lead time; luego
     * delega en registrar() o editar() según $datosProveedor['accion'].
     *
     * @param array $datosProveedor Datos del formulario del proveedor.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function procesar(array $datosProveedor): array
    {
        $rif = normalizarDocumento($datosProveedor['rif']);
        $nombre_empresa = mb_strtoupper(trim($datosProveedor['nombre_empresa']));
        $telefono = trim($datosProveedor['telefono_completo']);
        $contacto = trim($datosProveedor['contacto_nombre']);
        $email = trim($datosProveedor['email']);
        $direccion = trim($datosProveedor['direccion']);
        $lead_time = !empty($datosProveedor['lead_time']) ? min(365, max(0, (int)$datosProveedor['lead_time'])) : null;

        $status = in_array($datosProveedor['status'], ['Activo', 'Inactivo']) ? $datosProveedor['status'] : 'Activo';

        if (empty($nombre_empresa)) {
            return ['ok' => false, 'mensaje' => 'EL NOMBRE DE LA EMPRESA ES OBLIGATORIO.'];
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'FORMATO DE CORREO ELECTRÓNICO INVÁLIDO.'];
        }
        if (!validarRifProveedor($rif)) {
            return ['ok' => false, 'mensaje' => 'RIF INVÁLIDO. V/E: 8 DÍGITOS; J/G/P/C: 8 DÍGITOS + VERIFICADOR.'];
        }
        if (empty($telefono) || !validarTelefonoProveedor($telefono)) {
            return ['ok' => false, 'mensaje' => 'TELÉFONO INVÁLIDO PARA EL PAÍS SELECCIONADO.'];
        }

        $data = [
            'rif' => $rif,
            'nombre_empresa' => $nombre_empresa,
            'contacto' => $contacto,
            'telefono' => $telefono,
            'email' => $email,
            'direccion' => $direccion,
            'lead_time' => $lead_time,
            'moneda' => in_array($datosProveedor['moneda'], ['USD', 'EUR', 'VES']) ? $datosProveedor['moneda'] : 'USD',
            'status' => $status,
        ];
        $data = array_filter($data, fn($v) => $v !== null);

        if ($datosProveedor['accion'] === 'registrar') {
            return $this->registrar($data, $rif, $nombre_empresa, $email);
        }

        if ($datosProveedor['accion'] === 'editar') {
            return $this->editar((int)$datosProveedor['id_proveedor'], $data, $rif, $nombre_empresa, $email);
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

    // ==========================================
    // CATÁLOGO DE COSTOS (entidad asociativa)
    // ==========================================
    // La relación Proveedor ↔ Producto vive aquí: cada entrada dice qué
    // producto suministra el proveedor, a qué costo y con qué código interno.

    /**
     * Mapa id_proveedor → entradas de su catálogo de costos.
     *
     * Cada entrada trae producto, sku, costo y código interno del proveedor,
     * ordenado por nombre de producto. Usado para pintar la sección
     * "Productos que suministra" en cada tarjeta.
     *
     * @return array Mapa [id_proveedor => [entradas]].
     */
    public function catalogoPorProveedor(): array
    {
        $mapa = [];
        foreach ($this->db->fetchAll(
            "SELECT cc.id_catalogo, cc.id_proveedor, cc.id_producto, cc.costo, cc.stock_disponible, cc.codigo_proveedor,
                    p.nombre_producto, p.sku
             FROM catalogo_costos cc
             JOIN productos p ON cc.id_producto = p.id_producto
             ORDER BY p.nombre_producto ASC"
        ) as $entrada) {
            $mapa[(int)$entrada['id_proveedor']][] = $entrada;
        }
        return $mapa;
    }

    /**
     * Productos activos disponibles para asociar al catálogo.
     *
     * @return array Lista [id_producto, sku, nombre_producto].
     */
    public function productosActivos(): array
    {
        return $this->db->fetchAll(
            "SELECT id_producto, sku, nombre_producto FROM productos WHERE status = 'Activo' ORDER BY nombre_producto ASC"
        );
    }

    /**
     * Registra o edita una entrada del catálogo de costos.
     *
     * Valida proveedor y producto existentes/activos, costo, existencias,
     * código interno y asociaciones usadas por compras aún no recibidas.
     *
     * @param array $datos ['accion', 'id_catalogo', 'id_proveedor', 'id_producto', 'costo', 'stock_disponible', 'codigo_proveedor'].
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function procesarCatalogo(array $datos): array
    {
        $accion = ($datos['accion'] ?? '') === 'editar' ? 'editar' : 'registrar';
        $id_catalogo = (int)($datos['id_catalogo'] ?? 0);
        $id_proveedor = (int)($datos['id_proveedor'] ?? 0);
        $id_producto = (int)($datos['id_producto'] ?? 0);
        $codigo = trim((string)($datos['codigo_proveedor'] ?? ''));
        $stock_valor = $datos['stock_disponible'] ?? null;
        $stock_raw = is_scalar($stock_valor) ? trim((string)$stock_valor) : '';
        if (!preg_match('/^\d{1,10}$/D', $stock_raw) || (float)$stock_raw > 4294967295) {
            return ['ok' => false, 'mensaje' => 'EXISTENCIAS INVÁLIDAS. DEBEN ESTAR ENTRE 0 Y 4.294.967.295.'];
        }
        $stock_disponible = (int)$stock_raw;
        if (mb_strlen($codigo) > 50) {
            return ['ok' => false, 'mensaje' => 'EL CÓDIGO DEL PROVEEDOR NO PUEDE EXCEDER 50 CARACTERES.'];
        }
        $costo_raw = str_replace(',', '', trim((string)($datos['costo'] ?? '')));
        if (!preg_match('/^(?:0|[1-9]\d{0,4})(\.\d{1,2})?$/', $costo_raw) || (float)$costo_raw < 0.01 || (float)$costo_raw > 99999.99) {
            return ['ok' => false, 'mensaje' => 'COSTO INVÁLIDO. DEBE ESTAR ENTRE 0,01 Y 99.999,99.'];
        }
        $costo = round((float)$costo_raw, 2);

        if (!$this->db->fetchOne("SELECT id_proveedor FROM proveedores WHERE id_proveedor = ? AND status = 'Activo'", [$id_proveedor])) {
            return ['ok' => false, 'mensaje' => 'PROVEEDOR INVÁLIDO O INACTIVO.'];
        }
        if (!$this->db->fetchOne("SELECT id_producto FROM productos WHERE id_producto = ? AND status = 'Activo'", [$id_producto])) {
            return ['ok' => false, 'mensaje' => 'PRODUCTO INVÁLIDO O INACTIVO.'];
        }

        if ($accion === 'editar' && $id_catalogo <= 0) {
            return ['ok' => false, 'mensaje' => 'ENTRADA DE CATÁLOGO INVÁLIDA.'];
        }

        try {
            $this->db->begin();
            $catalogo_original = null;
            if ($accion === 'editar') {
                $catalogo_original = $this->db->fetchOne(
                    'SELECT id_catalogo, id_proveedor, id_producto FROM catalogo_costos WHERE id_catalogo = ? FOR UPDATE',
                    [$id_catalogo]
                );
                if (!$catalogo_original) {
                    $this->db->rollback();
                    return ['ok' => false, 'mensaje' => 'ENTRADA DE CATÁLOGO INVÁLIDA.'];
                }
                if (
                    ((int)$catalogo_original['id_proveedor'] !== $id_proveedor || (int)$catalogo_original['id_producto'] !== $id_producto)
                    && $this->catalogoTieneCompraPendiente((int)$catalogo_original['id_proveedor'], (int)$catalogo_original['id_producto'])
                ) {
                    $this->db->rollback();
                    return ['ok' => false, 'mensaje' => 'NO SE PUEDE CAMBIAR ESTA ASOCIACIÓN: HAY UNA COMPRA PENDIENTE DE RECEPCIÓN.'];
                }
            }

            $duplicado = $this->db->fetchOne(
                "SELECT id_catalogo FROM catalogo_costos WHERE id_proveedor = ? AND id_producto = ?" . ($accion === 'editar' ? " AND id_catalogo != ?" : ""),
                $accion === 'editar' ? [$id_proveedor, $id_producto, $id_catalogo] : [$id_proveedor, $id_producto]
            );
            if ($duplicado) {
                $this->db->rollback();
                return ['ok' => false, 'mensaje' => 'ESE PRODUCTO YA ESTÁ EN EL CATÁLOGO DE ESTE PROVEEDOR. EDÍTALO SI DESEAS CAMBIAR SU COSTO.'];
            }

            if ($accion === 'registrar') {
                $this->db->insert('catalogo_costos', [
                    'id_proveedor' => $id_proveedor,
                    'id_producto' => $id_producto,
                    'costo' => $costo,
                    'stock_disponible' => $stock_disponible,
                    'codigo_proveedor' => $codigo !== '' ? $codigo : null,
                ]);
                registrarAuditoria('crear', 'Entrada de catálogo de costos creada');
            } else {
                $this->db->execute(
                    "UPDATE catalogo_costos
                     SET id_proveedor=?, id_producto=?, costo=?, stock_disponible=?, codigo_proveedor=?
                     WHERE id_catalogo=?",
                    [$id_proveedor, $id_producto, $costo, $stock_disponible, $codigo !== '' ? $codigo : null, $id_catalogo]
                );
                registrarAuditoria('editar', 'Entrada de catálogo de costos modificada');
            }
            $this->db->commit();
            return ['ok' => true, 'mensaje' => 'CATÁLOGO ACTUALIZADO CORRECTAMENTE.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('Error al guardar entrada del catálogo: ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'ERROR AL GUARDAR LA ENTRADA DEL CATÁLOGO.'];
        }
    }

    /**
     * Elimina una entrada del catálogo de costos.
     *
     * @param int $id_catalogo Identificador de la entrada.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function eliminarCatalogo(int $id_catalogo): array
    {
        if ($id_catalogo <= 0) {
            return ['ok' => false, 'mensaje' => 'LA ENTRADA DEL CATÁLOGO NO EXISTE.'];
        }
        try {
            $this->db->begin();
            $entrada = $this->db->fetchOne(
                'SELECT id_catalogo, id_proveedor, id_producto FROM catalogo_costos WHERE id_catalogo = ? FOR UPDATE',
                [$id_catalogo]
            );
            if (!$entrada) {
                $this->db->rollback();
                return ['ok' => false, 'mensaje' => 'LA ENTRADA DEL CATÁLOGO NO EXISTE.'];
            }
            if ($this->catalogoTieneCompraPendiente((int)$entrada['id_proveedor'], (int)$entrada['id_producto'])) {
                $this->db->rollback();
                return ['ok' => false, 'mensaje' => 'NO SE PUEDE QUITAR: HAY UNA COMPRA PENDIENTE DE RECEPCIÓN.'];
            }
            $this->db->execute("DELETE FROM catalogo_costos WHERE id_catalogo = ?", [$id_catalogo]);
            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('No se pudo eliminar la entrada del catálogo.');
            }
            registrarAuditoria('eliminar', 'Entrada de catálogo de costos eliminada');
            $this->db->commit();
            return ['ok' => true, 'mensaje' => 'ENTRADA ELIMINADA DEL CATÁLOGO.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('Error al eliminar entrada del catálogo: ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'ERROR AL ELIMINAR LA ENTRADA DEL CATÁLOGO.'];
        }
    }

    /**
     * Comprueba si una asociación respalda una compra nueva aún anulable.
     *
     * @param int $id_proveedor Identificador del proveedor.
     * @param int $id_producto Identificador del producto.
     * @return bool Indica que existe una compra reservada sin recepción.
     */
    private function catalogoTieneCompraPendiente(int $id_proveedor, int $id_producto): bool
    {
        return $this->db->fetchOne(
            "SELECT dc.id_detalle
             FROM detalle_compras dc
             JOIN compras c ON c.id_compra = dc.id_compra
             WHERE c.id_proveedor = ? AND dc.id_producto = ?
               AND c.status = 'Activa' AND c.stock_proveedor_reservado = 1
               AND NOT EXISTS (SELECT 1 FROM lotes l WHERE l.id_compra = c.id_compra)
             LIMIT 1 FOR UPDATE",
            [$id_proveedor, $id_producto]
        ) !== null;
    }
}
