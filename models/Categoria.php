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
     * valor de $datos['accion'].
     *
     * @param array $datos Datos del formulario (accion, nombre, descripcion, etc.).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function procesar(array $datos): array
    {
        // 1. Limpiar y normalizar cada campo del formulario
        // (mayúsculas para el nombre, sin espacios al inicio/fin).
        $nombre = mb_strtoupper(trim($datos['nombre']));
        $descripcion = trim($datos['descripcion']);
        $clasificacion_abc = strtoupper(trim($datos['clasificacion_abc']));
        if (!in_array($clasificacion_abc, ['A', 'B', 'C', ''])) $clasificacion_abc = '';
        $tipo_manejo = in_array($datos['tipo_manejo'], ['normal', 'inflamable', 'liquido', 'peligroso', 'voluminoso', 'aerosol']) ? $datos['tipo_manejo'] : 'normal';
        $status = in_array($datos['status'], ['Activo', 'Inactivo']) ? $datos['status'] : 'Activo';

        // 2. Regla de negocio: el nombre es obligatorio
        if (empty($nombre)) {
            return ['ok' => false, 'mensaje' => 'EL NOMBRE DE LA CATEGORÍA ES OBLIGATORIO.'];
        }

        // 3. Elegir qué operación ejecutar según la acción del formulario
        if ($datos['accion'] === 'registrar') {
            return $this->registrar($nombre, $descripcion, $clasificacion_abc, $tipo_manejo, $status);
        }

        if ($datos['accion'] === 'editar') {
            return $this->editar((int)$datos['id_categoria'], $nombre, $descripcion, $clasificacion_abc, $tipo_manejo, $status);
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
     * @param string $nombre            Nombre normalizado (mayúsculas).
     * @param string $descripcion       Descripción opcional.
     * @param string $clasificacion_abc Clasificación ABC ('A', 'B', 'C' o '').
     * @param string $tipo_manejo       Tipo de manejo (normal, inflamable, etc.).
     * @param string $status            Estado inicial ('Activo'/'Inactivo').
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    private function registrar(string $nombre, string $descripcion, string $clasificacion_abc, string $tipo_manejo, string $status): array
    {
        // Regla de negocio: no puede haber dos categorías con el mismo nombre.
        $duplicado = $this->db->fetchOne("SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?)", [$nombre]);
        if ($duplicado) return ['ok' => false, 'mensaje' => 'YA EXISTE UNA CATEGORÍA CON ESE NOMBRE.'];

        // Transacción: si algo falla a mitad, se revierte TODO (no queda código
        // gastado ni categoría a medias). begin() abre, commit() confirma,
        // rollback() deshace en caso de error.
        $this->db->begin();
        try {
            $codigo = $this->siguienteCodigo();
            $this->db->insert('categorias', [
                'nombre'            => $nombre,
                'codigo'            => $codigo,
                'descripcion'       => $descripcion,
                'clasificacion_abc' => $clasificacion_abc,
                'tipo_manejo'       => $tipo_manejo,
                'status'            => $status,
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
     * @param int    $id_categoria   Identificador de la categoría.
     * @param string $nombre          Nombre normalizado (mayúsculas).
     * @param string $descripcion     Descripción opcional.
     * @param string $clasificacion_abc Clasificación ABC.
     * @param string $tipo_manejo     Tipo de manejo.
     * @param string $status          Estado ('Activo'/'Inactivo').
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    private function editar(int $id_categoria, string $nombre, string $descripcion, string $clasificacion_abc, string $tipo_manejo, string $status): array
    {
        // No puede existir OTRA categoría con el mismo nombre
        // (se excluye la propia, por eso el "id_categoria != ?").
        $duplicado = $this->db->fetchOne("SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?) AND id_categoria != ?", [$nombre, $id_categoria]);
        if ($duplicado) return ['ok' => false, 'mensaje' => 'YA EXISTE UNA CATEGORÍA CON ESE NOMBRE.'];

        // El código (ej. CAT-007) no cambia al editar: se conserva el actual.
        $existente = $this->db->fetchOne("SELECT codigo FROM categorias WHERE id_categoria = ?", [$id_categoria]);
        $codigo_final = $existente['codigo'] ?? '';
        $this->db->execute("UPDATE categorias SET nombre=?, codigo=?, descripcion=?, clasificacion_abc=?, tipo_manejo=?, status=? WHERE id_categoria=?",
            [$nombre, $codigo_final, $descripcion, $clasificacion_abc, $tipo_manejo, $status, $id_categoria]);
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
    public function toggleStatus(int $id_categoria): void
    {
        // 1. Buscar el estado actual de la categoría
        $categoria_actual = $this->db->fetchOne("SELECT status FROM categorias WHERE id_categoria = ?", [$id_categoria]);
        if ($categoria_actual) {
            // 2. Invertirlo: si era Activo pasa a Inactivo y viceversa
            $nuevo_estado = $categoria_actual['status'] === 'Activo' ? 'Inactivo' : 'Activo';
            $this->db->execute("UPDATE categorias SET status = ? WHERE id_categoria = ?", [$nuevo_estado, $id_categoria]);
            registrarAuditoria('toggle_status', 'Cambio de estado');
        }
    }

    /**
     * Listado completo de categorías ordenado por reglas de negocio.
     *
     * Orden automático: Clasificación ABC (A→B→C, sin clasificar al final),
     * luego tipo de manejo y por último nombre. Así el listado siempre
     * nace organizado sin acomodos manuales.
     *
     * @return array Listado de categorías.
     */
    public function listar(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM categorias
             ORDER BY COALESCE(NULLIF(clasificacion_abc, ''), 'Z') ASC, tipo_manejo ASC, nombre ASC"
        );
    }

    /**
     * Carga en bloque una plantilla de rubro (categorías con reglas).
     *
     * Recibe las categorías normalizadas de la plantilla y las inserta
     * dentro de UNA transacción: las que ya existen (por nombre) se omiten
     * sin duplicar, las nuevas obtienen su código CAT-XXX correlativo y
     * sus reglas (ABC, manejo, stocks). Devuelve un resumen del resultado.
     *
     * @param array $items Categorías de la plantilla: nombre, descripcion,
     *                     abc, manejo, stock_min, stock_max.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function cargarPlantilla(array $items): array
    {
        if (empty($items)) {
            return ['ok' => false, 'mensaje' => 'LA PLANTILLA NO TIENE CATEGORÍAS.'];
        }

        $this->db->begin();
        try {
            $creadas = 0;
            $omitidas = 0;
            foreach ($items as $item) {
                $nombre = mb_strtoupper(trim((string)($item['nombre'] ?? '')));
                if ($nombre === '') { $omitidas++; continue; }

                // Omitir sin duplicar: si el nombre ya existe, no se toca
                $duplicado = $this->db->fetchOne("SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?)", [$nombre]);
                if ($duplicado) { $omitidas++; continue; }

                $abc = strtoupper(trim((string)($item['abc'] ?? '')));
                if (!in_array($abc, ['A', 'B', 'C', ''])) $abc = '';
                $manejo = in_array($item['manejo'] ?? '', ['normal', 'inflamable', 'liquido', 'peligroso', 'voluminoso', 'aerosol']) ? $item['manejo'] : 'normal';

                $this->db->insert('categorias', [
                    'nombre'            => $nombre,
                    'codigo'            => $this->siguienteCodigo(),
                    'descripcion'       => trim((string)($item['descripcion'] ?? '')),
                    'clasificacion_abc' => $abc,
                    'tipo_manejo'       => $manejo,
                    'stock_minimo'      => max(0, (int)($item['stock_min'] ?? 5)),
                    'stock_maximo'      => max(0, (int)($item['stock_max'] ?? 0)),
                    'status'            => 'Activo',
                ]);
                $creadas++;
            }
            $this->db->commit();
            registrarAuditoria('crear', "Plantilla de rubro cargada: {$creadas} categorías creadas, {$omitidas} ya existían");
            return ['ok' => true, 'mensaje' => "PLANTILLA CARGADA: {$creadas} CATEGORÍAS CREADAS, {$omitidas} YA EXISTÍAN."];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR EN LA BASE DE DATOS AL CARGAR LA PLANTILLA.'];
        }
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
        // Buscar las categorías que quedaron sin código (dato vacío o nulo).
        $sin_codigo = $this->db->fetchAll("SELECT id_categoria FROM categorias WHERE codigo IS NULL OR codigo = '' ORDER BY id_categoria");
        foreach ($sin_codigo as $categoria) {
            // A cada una le toca el siguiente código disponible (CAT-XXX).
            $codigo = $this->siguienteCodigo();
            $this->db->execute("UPDATE categorias SET codigo=? WHERE id_categoria=?", [$codigo, (int)$categoria['id_categoria']]);
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
        // Leer el último número usado para el prefijo "CAT".
        // FOR UPDATE "bloquea" esa fila mientras estemos trabajando,
        // para que dos usuarios no generen el mismo código a la vez.
        $contador = $this->db->fetchOne("SELECT ultimo_numero FROM sku_contadores WHERE sku_prefix='CAT' FOR UPDATE");
        if (!$contador) {
            // No existe fila del contador: se crea desde cero (número 0)
            // y el próximo código será el 1.
            $this->db->execute("INSERT INTO sku_contadores (sku_prefix, ultimo_numero) VALUES ('CAT', 0)");
            $siguiente_numero = 1;
        } else {
            // Sí existe: el siguiente es el actual + 1.
            $siguiente_numero = (int)$contador['ultimo_numero'] + 1;
        }
        // Guardar el nuevo número para la próxima vez.
        $this->db->execute("UPDATE sku_contadores SET ultimo_numero=? WHERE sku_prefix='CAT'", [$siguiente_numero]);
        // Formatear con ceros a la izquierda: 7 -> "CAT-007".
        return 'CAT-' . str_pad($siguiente_numero, 3, '0', STR_PAD_LEFT);
    }
}
