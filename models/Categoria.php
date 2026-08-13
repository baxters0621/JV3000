<?php

// ==========================================
// MODELO: Categoría
// ==========================================
// Única capa que consulta la base de datos.
// Incluye generación de códigos CAT-XXX con contador.

/**
 * Categoria: modelo del módulo de categorías.
 *
 * Única capa autorizada para consultar la base de datos. Incluye el
 * alta/edición de categorías con validaciones, el cambio de estado, el
 * listado y la generación de códigos CAT-XXX mediante contador (con
 * transacción y bloqueo FOR UPDATE para evitar duplicados).
 */
class Categoria extends Model
{
    /**
     * Procesa registrar o editar según la acción recibida.
     *
     * Normaliza y valida nombre, descripción, clasificación ABC, tipo de
     * manejo y status; luego delega en registrar() o editar() según el
     * valor de $d['accion'].
     *
     * @param array $d Datos del formulario (accion, nombre, descripcion, etc.).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function procesar(array $d): array
    {
        $nombre = mb_strtoupper(trim($d['nombre']));
        $descripcion = trim($d['descripcion']);
        $clasificacion_abc = strtoupper(trim($d['clasificacion_abc']));
        if (!in_array($clasificacion_abc, ['A', 'B', 'C', ''])) $clasificacion_abc = '';
        $tipo_manejo = in_array($d['tipo_manejo'], ['normal', 'inflamable', 'liquido', 'peligroso', 'voluminoso', 'aerosol']) ? $d['tipo_manejo'] : 'normal';
        $status = in_array($d['status'], ['Activo', 'Inactivo']) ? $d['status'] : 'Activo';

        if (empty($nombre)) {
            return ['ok' => false, 'mensaje' => 'EL NOMBRE DE LA CATEGORÍA ES OBLIGATORIO.'];
        }

        if ($d['accion'] === 'registrar') {
            return $this->registrar($nombre, $descripcion, $clasificacion_abc, $tipo_manejo, $status);
        }

        if ($d['accion'] === 'editar') {
            return $this->editar((int)$d['id_categoria'], $nombre, $descripcion, $clasificacion_abc, $tipo_manejo, $status);
        }

        return ['ok' => false, 'mensaje' => 'ACCIÓN INVÁLIDA.'];
    }

    /**
     * Registra una nueva categoría con su código CAT-XXX.
     *
     * Verifica que no exista duplicado por nombre, genera el siguiente
     * código dentro de una transacción, inserta la categoría y registra la
     * auditoría. En caso de error revierte la transacción.
     *
     * @param string $nombre        Nombre normalizado (mayúsculas).
     * @param string $descripcion   Descripción opcional.
     * @param string $abc           Clasificación ABC ('A', 'B', 'C' o '').
     * @param string $tipo          Tipo de manejo (normal, inflamable, etc.).
     * @param string $status        Estado inicial ('Activo'/'Inactivo').
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    private function registrar(string $nombre, string $descripcion, string $abc, string $tipo, string $status): array
    {
        $dup = $this->db->fetchOne("SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?)", [$nombre]);
        if ($dup) return ['ok' => false, 'mensaje' => 'YA EXISTE UNA CATEGORÍA CON ESE NOMBRE.'];

        $this->db->begin();
        try {
            $codigo = $this->siguienteCodigo();
            $this->db->insert('categorias', [
                'nombre'          => $nombre,
                'codigo'          => $codigo,
                'descripcion'     => $descripcion,
                'clasificacion_abc' => $abc,
                'tipo_manejo'     => $tipo,
                'status'          => $status,
            ]);
            $this->db->commit();
            registrarAuditoria('crear', 'Categoría creada');
            return ['ok' => true, 'mensaje' => 'CATEGORÍA REGISTRADA CON ÉXITO.'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR EN LA BASE DE DATOS.'];
        }
    }

/**
     * Edita una categoría existente conservando su código original.
     *
     * Verifica que no haya duplicado por nombre (excluyendo el propio
     * registro), conserva el código ya asignado y actualiza los datos,
     * registrando la auditoría correspondiente.
     *
     * @param int    $idCat         Identificador de la categoría.
     * @param string $nombre        Nombre normalizado (mayúsculas).
     * @param string $descripcion   Descripción opcional.
     * @param string $abc           Clasificación ABC.
     * @param string $tipo          Tipo de manejo.
     * @param string $status        Estado ('Activo'/'Inactivo').
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    private function editar(int $idCat, string $nombre, string $descripcion, string $abc, string $tipo, string $status): array
    {
        $dup = $this->db->fetchOne("SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?) AND id_categoria != ?", [$nombre, $idCat]);
        if ($dup) return ['ok' => false, 'mensaje' => 'YA EXISTE UNA CATEGORÍA CON ESE NOMBRE.'];

        $existente = $this->db->fetchOne("SELECT codigo FROM categorias WHERE id_categoria = ?", [$idCat]);
        $codigo_final = $existente['codigo'] ?? '';
        $this->db->execute("UPDATE categorias SET nombre=?, codigo=?, descripcion=?, clasificacion_abc=?, tipo_manejo=?, status=? WHERE id_categoria=?",
            [$nombre, $codigo_final, $descripcion, $abc, $tipo, $status, $idCat]);
        registrarAuditoria('editar', 'Categoría modificada');
        return ['ok' => true, 'mensaje' => 'CATEGORÍA ACTUALIZADA CORRECTAMENTE.'];
    }

    /**
     * Cambia el estado Activo/Inactivo de una categoría.
     *
     * Consulta el estado actual, lo invierte y actualiza la categoría,
     * registrando la auditoría correspondiente. No hace nada si la
     * categoría no existe.
     *
     * @param int $idCategoria Identificador de la categoría.
     * @return void
     */
    public function toggleStatus(int $idCategoria): void
    {
        $row = $this->db->fetchOne("SELECT status FROM categorias WHERE id_categoria = ?", [$idCategoria]);
        if ($row) {
            $nuevo = $row['status'] === 'Activo' ? 'Inactivo' : 'Activo';
            $this->db->execute("UPDATE categorias SET status = ? WHERE id_categoria = ?", [$nuevo, $idCategoria]);
            registrarAuditoria('toggle_status', 'Cambio de estado');
        }
    }

    /**
     * Listado completo de categorías ordenado por nombre.
     *
     * Devuelve todas las categorías, sin paginar, ordenadas alfabéticamente.
     *
     * @return array Listado de categorías.
     */
    public function listar(): array
    {
        return $this->db->fetchAll("SELECT * FROM categorias ORDER BY nombre ASC");
    }

    /**
     * Asigna CAT-XXX a categorías cuyo código quedó nulo o vacío.
     *
     * Operación de reparación invocada como side-effect en GET: recorre las
     * categorías sin código y les asigna el siguiente disponible. No abre
     * transacción propia (cada siguienteCodigo() la espera del llamador).
     *
     * @return void
     */
    public function repararCodigos(): void
    {
        $nulls = $this->db->fetchAll("SELECT id_categoria FROM categorias WHERE codigo IS NULL OR codigo = '' ORDER BY id_categoria");
        foreach ($nulls as $n) {
            $codigo = $this->siguienteCodigo();
            $this->db->execute("UPDATE categorias SET codigo=? WHERE id_categoria=?", [$codigo, (int)$n['id_categoria']]);
        }
    }

    /**
     * Genera el siguiente código CAT-XXX con bloqueo de fila.
     *
     * Asume que el llamador tiene una transacción abierta: lee el último
     * número con FOR UPDATE, lo incrementa y devuelve el código formateado
     * (ej. CAT-001). El bloqueo evita códigos duplicados concurrentes.
     *
     * @return string Código CAT-XXX generado.
     */
    private function siguienteCodigo(): string
    {
        $cnt = $this->db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='CAT' FOR UPDATE");
        if (!$cnt) {
            $this->db->execute("INSERT INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('CAT', 0)");
            $prox = 1;
        } else {
            $prox = (int)$cnt['ultimo_numero'] + 1;
        }
        $this->db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='CAT'", [$prox]);
        return 'CAT-' . str_pad($prox, 3, '0', STR_PAD_LEFT);
    }
}
