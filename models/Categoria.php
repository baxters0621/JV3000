<?php

// ==========================================
// MODELO: Categoría
// ==========================================
// Única capa que consulta la base de datos.
// Categorías jerárquicas (raíz + subcategorías) con estado
// automático según el stock y generación de códigos CAT-XXX.

/**
 * Categoria: modelo del módulo de categorías.
 *
 * Única capa autorizada para consultar la base de datos. Gestiona el árbol
 * de dos niveles (categorías raíz y subcategorías), la unicidad de nombres
 * entre hermanos, el estado automático por stock (una categoría con
 * productos se desactiva sola cuando todo su stock llega a cero y se
 * reactiva cuando vuelve a entrar mercancía), el cambio manual de estado
 * (solo categorías sin productos) y la generación de códigos CAT-XXX
 * mediante contador con transacción y bloqueo FOR UPDATE.
 */
class Categoria extends Model
{
    /** Tipos de manejo válidos para una categoría. */
    private const TIPOS_MANEJO = ['normal', 'inflamable', 'liquido', 'peligroso', 'voluminoso', 'aerosol'];

    /**
     * Procesa el alta o la edición de una categoría.
     *
     * Normaliza los campos (nombre en mayúsculas), valida la categoría
     * padre (solo raíces activas, árbol de dos niveles), la unicidad del
     * nombre entre hermanos y delega en registrar() o editar() según la
     * acción recibida.
     *
     * @param array $datos Datos del formulario (accion, nombre, id_padre...).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function procesar(array $datos): array
    {
        // 1. Normalizar cada campo del formulario
        $nombre = mb_strtoupper(trim((string)($datos['nombre'] ?? '')));
        $descripcion = trim((string)($datos['descripcion'] ?? ''));
        $clasificacion_abc = strtoupper(trim((string)($datos['clasificacion_abc'] ?? '')));
        if (!in_array($clasificacion_abc, ['A', 'B', 'C', ''], true)) $clasificacion_abc = '';
        $tipo_manejo = (string)($datos['tipo_manejo'] ?? 'normal');
        if (!in_array($tipo_manejo, self::TIPOS_MANEJO, true)) $tipo_manejo = 'normal';
        $id_padre = max(0, (int)($datos['id_padre'] ?? 0));
        $id_categoria = (int)($datos['id_categoria'] ?? 0);
        $accion = (string)($datos['accion'] ?? '');

        // 2. Validaciones básicas
        if ($nombre === '') {
            return ['ok' => false, 'mensaje' => 'EL NOMBRE DE LA CATEGORÍA ES OBLIGATORIO.'];
        }
        if (mb_strlen($nombre) > 100) {
            return ['ok' => false, 'mensaje' => 'EL NOMBRE NO PUEDE SUPERAR 100 CARACTERES.'];
        }

        // 3. Categoría padre: solo raíces activas (árbol de dos niveles)
        $padre = null;
        if ($id_padre > 0) {
            $padre = $this->db->fetchOne(
                "SELECT id_categoria, nombre, ruta, status, parent_id FROM categorias WHERE id_categoria = ?",
                [$id_padre]
            );
            if (!$padre) {
                return ['ok' => false, 'mensaje' => 'LA CATEGORÍA PADRE NO EXISTE.'];
            }
            if ($padre['parent_id'] !== null) {
                return ['ok' => false, 'mensaje' => 'SOLO LAS CATEGORÍAS RAÍZ PUEDEN TENER SUBCATEGORÍAS (MÁXIMO 2 NIVELES).'];
            }
            if ($padre['status'] !== 'Activo') {
                return ['ok' => false, 'mensaje' => 'LA CATEGORÍA PADRE DEBE ESTAR ACTIVA.'];
            }
            if ($accion === 'editar' && $id_padre === $id_categoria) {
                return ['ok' => false, 'mensaje' => 'UNA CATEGORÍA NO PUEDE SER SU PROPIA CATEGORÍA PADRE.'];
            }
        }

        // En edición: una categoría con subcategorías no puede volverse
        // subcategoría (rompería el árbol de dos niveles).
        if ($accion === 'editar' && $id_padre > 0) {
            $tiene_hijos = (bool)$this->db->fetchOne(
                "SELECT id_categoria FROM categorias WHERE parent_id = ? LIMIT 1",
                [$id_categoria]
            );
            if ($tiene_hijos) {
                return ['ok' => false, 'mensaje' => 'UNA CATEGORÍA CON SUBCATEGORÍAS NO PUEDE VOLVERSE SUBCATEGORÍA.'];
            }
        }

        // 4. Regla de negocio: nombre único ENTRE HERMANOS (mismo nivel)
        if ($this->nombreDuplicado($nombre, $id_padre, $id_categoria)) {
            return ['ok' => false, 'mensaje' => 'YA EXISTE ESA CATEGORÍA CON ESE NOMBRE EN ESTE NIVEL.'];
        }

        // 5. Delegar según la acción
        if ($accion === 'registrar') {
            return $this->registrar($nombre, $descripcion, $clasificacion_abc, $tipo_manejo, $padre);
        }
        if ($accion === 'editar') {
            return $this->editar($id_categoria, $nombre, $descripcion, $clasificacion_abc, $tipo_manejo, $padre);
        }
        return ['ok' => false, 'mensaje' => 'ACCIÓN INVÁLIDA.'];
    }

    /**
     * Comprueba si el nombre ya existe en el mismo nivel del árbol.
     *
     * Dos categorías hermanas (mismo padre, o ambas raíces) no pueden
     * compartir nombre; en niveles distintos sí. En edición se excluye la
     * propia categoría de la búsqueda.
     *
     * @param string $nombre     Nombre normalizado a buscar.
     * @param int    $id_padre   Padre destino (0 = raíz).
     * @param int    $id_actual  Categoría en edición (0 en alta).
     * @return bool True si ya existe una hermana con ese nombre.
     */
    private function nombreDuplicado(string $nombre, int $id_padre, int $id_actual): bool
    {
        // En edición, la comparación es contra el nivel ACTUAL de la categoría
        // cuando el formulario no cambió el padre.
        if ($id_actual > 0 && $id_padre === 0) {
            $propia = $this->db->fetchOne("SELECT parent_id FROM categorias WHERE id_categoria = ?", [$id_actual]);
            $id_padre = $propia ? (int)($propia['parent_id'] ?? 0) : 0;
        }

        if ($id_padre > 0) {
            $duplicado = $this->db->fetchOne(
                "SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?) AND parent_id = ? AND id_categoria != ?",
                [$nombre, $id_padre, $id_actual]
            );
        } else {
            $duplicado = $this->db->fetchOne(
                "SELECT id_categoria FROM categorias WHERE LOWER(nombre) = LOWER(?) AND parent_id IS NULL AND id_categoria != ?",
                [$nombre, $id_actual]
            );
        }
        return (bool)$duplicado;
    }

    /**
     * Registra una nueva categoría con su código CAT-XXX.
     *
     * Calcula nivel y ruta a partir de la categoría padre (nivel 0 y ruta
     * = nombre para las raíces), genera el siguiente código dentro de una
     * transacción, inserta la categoría y registra la auditoría.
     *
     * @param string     $nombre      Nombre normalizado (mayúsculas).
     * @param string     $descripcion Descripción opcional.
     * @param string     $abc         Clasificación ABC ('A', 'B', 'C' o '').
     * @param string     $manejo      Tipo de manejo (normal, inflamable...).
     * @param array|null $padre       Fila de la categoría padre o null si es raíz.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    private function registrar(string $nombre, string $descripcion, string $abc, string $manejo, ?array $padre): array
    {
        // Transacción: si algo falla a mitad, se revierte TODO (no queda
        // código gastado ni categoría a medias).
        $this->db->begin();
        try {
            $codigo = $this->siguienteCodigo();
            $this->db->insert('categorias', [
                'nombre'            => $nombre,
                'codigo'            => $codigo,
                'descripcion'       => $descripcion,
                'parent_id'         => $padre ? (int)$padre['id_categoria'] : null,
                'nivel'             => $padre ? 1 : 0,
                'ruta'              => $this->rutaNueva($nombre, $padre),
                'clasificacion_abc' => $abc,
                'tipo_manejo'       => $manejo,
                'status'            => 'Activo',
            ]);
            $this->db->commit();
            registrarAuditoria('crear', 'Categoría creada: ' . ($padre ? $padre['nombre'] . '/' : '') . $nombre);
            return ['ok' => true, 'mensaje' => 'CATEGORÍA REGISTRADA CON ÉXITO.'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR EN LA BASE DE DATOS.'];
        }
    }

    /**
     * Edita una categoría conservando su código original.
     *
     * Actualiza nombre, descripción, clasificación, manejo, padre, nivel y
     * ruta. Si la ruta cambia (renombre o cambio de padre) recalcula la
     * ruta de todos sus descendientes dentro de la misma transacción.
     *
     * @param int        $id_categoria Identificador de la categoría.
     * @param string     $nombre       Nombre normalizado (mayúsculas).
     * @param string     $descripcion  Descripción opcional.
     * @param string     $abc          Clasificación ABC.
     * @param string     $manejo       Tipo de manejo.
     * @param array|null $padre        Fila de la categoría padre o null si queda como raíz.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    private function editar(int $id_categoria, string $nombre, string $descripcion, string $abc, string $manejo, ?array $padre): array
    {
        $actual = $this->db->fetchOne("SELECT * FROM categorias WHERE id_categoria = ?", [$id_categoria]);
        if (!$actual) {
            return ['ok' => false, 'mensaje' => 'LA CATEGORÍA NO EXISTE.'];
        }

        $this->db->begin();
        try {
            $nueva_ruta = $this->rutaNueva($nombre, $padre);
            $this->db->update('categorias', [
                'nombre'            => $nombre,
                'descripcion'       => $descripcion,
                'parent_id'         => $padre ? (int)$padre['id_categoria'] : null,
                'nivel'             => $padre ? 1 : 0,
                'ruta'              => $nueva_ruta,
                'clasificacion_abc' => $abc,
                'tipo_manejo'       => $manejo,
                // El código (ej. CAT-007) y el estado no cambian al editar:
                // el estado lo maneja el stock, no el formulario.
            ], 'id_categoria = ?', [$id_categoria]);

            // Renombrar o reparentar cambia la ruta: arrastrar a los hijos.
            $ruta_vieja = (string)($actual['ruta'] ?? '');
            if ($ruta_vieja !== '' && $ruta_vieja !== $nueva_ruta) {
                $hijos = $this->db->fetchAll(
                    "SELECT id_categoria, ruta FROM categorias WHERE parent_id = ? AND ruta LIKE ?",
                    [$id_categoria, $ruta_vieja . '/%']
                );
                foreach ($hijos as $hijo) {
                    // Conserva la cola de la ruta del hijo ("/NOMBRE") y
                    // reemplaza solo el tramo del padre.
                    $cola = substr((string)$hijo['ruta'], strlen($ruta_vieja));
                    $this->db->execute(
                        "UPDATE categorias SET ruta = ? WHERE id_categoria = ?",
                        [$nueva_ruta . $cola, (int)$hijo['id_categoria']]
                    );
                }
            }

            $this->db->commit();
            registrarAuditoria('editar', 'Categoría modificada: ' . ($padre ? $padre['nombre'] . '/' : '') . $nombre);
            return ['ok' => true, 'mensaje' => 'CATEGORÍA ACTUALIZADA CORRECTAMENTE.'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR EN LA BASE DE DATOS.'];
        }
    }

    /**
     * Calcula la ruta y el prefijo de ruta de un padre, con resguardo.
     *
     * Para una raíz la ruta es el propio nombre; para una subcategoría es
     * ruta_del_padre + "/" + nombre. Si el padre vino sin ruta (dato
     * heredado), se usa su nombre como tramo.
     *
     * @param string     $nombre Nombre de la categoría nueva o editada.
     * @param array|null $padre  Fila del padre o null si es raíz.
     * @return string Ruta completa de la categoría.
     */
    private function rutaNueva(string $nombre, ?array $padre): string
    {
        if (!$padre) return $nombre;
        $ruta_padre = (string)($padre['ruta'] ?? '');
        if ($ruta_padre === '') $ruta_padre = (string)$padre['nombre'];
        return $ruta_padre . '/' . $nombre;
    }

    /**
     * Devuelve la fila completa de una categoría o null si no existe.
     *
     * Lectura puntual usada por el controlador para el filtro ?cat=N y
     * para descartar identificadores inexistentes.
     *
     * @param int $id_categoria Identificador de la categoría.
     * @return array|null Fila completa o null si no existe.
     */
    public function fila(int $id_categoria): ?array
    {
        return $this->db->fetchOne("SELECT * FROM categorias WHERE id_categoria = ?", [$id_categoria]);
    }

    /**
     * Cambia manualmente el estado de una categoría sin productos.
     *
     * Regla de negocio: el estado de una categoría CON productos lo maneja
     * el stock de forma automática (ver sincronizarPorStock), así que el
     * cambio manual solo se permite cuando ni ella ni sus subcategorías
     * tienen productos. Sirve para ocultar categorías planificadas
     * y aún sin uso, o volver a mostrarlas.
     *
     * @param int $id_categoria Identificador de la categoría.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function toggleStatus(int $id_categoria): array
    {
        $categoria = $this->db->fetchOne("SELECT id_categoria, nombre, status, ruta FROM categorias WHERE id_categoria = ?", [$id_categoria]);
        if (!$categoria) {
            return ['ok' => false, 'mensaje' => 'LA CATEGORÍA NO EXISTE.'];
        }

        // Productos del nodo y de toda su rama (por ruta), sin importar su estado.
        $ruta = (string)($categoria['ruta'] ?? '');
        if ($ruta === '') $ruta = (string)$categoria['nombre'];
        $total = (int)($this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM productos p
             JOIN categorias c ON p.id_categoria = c.id_categoria
             WHERE c.ruta = ? OR c.ruta LIKE ?",
            [$ruta, $ruta . '/%']
        )['total'] ?? 0);

        if ($total > 0) {
            return ['ok' => false, 'mensaje' => 'NO SE PUEDE CAMBIAR A MANO: LA CATEGORÍA TIENE PRODUCTOS Y SU ESTADO LO MANEJA EL STOCK.'];
        }

        $nuevo_estado = $categoria['status'] === 'Activo' ? 'Inactivo' : 'Activo';
        $this->db->execute("UPDATE categorias SET status = ? WHERE id_categoria = ?", [$nuevo_estado, $id_categoria]);
        registrarAuditoria(
            'toggle_status',
            ($nuevo_estado === 'Inactivo' ? 'Categoría sin productos ocultada: ' : 'Categoría sin productos visible: ') . $categoria['nombre']
        );
        return [
            'ok' => true,
            'mensaje' => $nuevo_estado === 'Inactivo' ? 'CATEGORÍA OCULTADA (SIN PRODUCTOS).' : 'CATEGORÍA VISIBLE DE NUEVO.',
        ];
    }

    /**
     * Ajusta los estados de todas las categorías según el stock.
     *
     * Regla automática: una categoría con productos queda Inactiva cuando
     * TODOS sus productos tienen stock_actual = 0, sin importar su estado,
     * y vuelve a Activa
     * en cuanto entra mercancía. Las raíces se evalúan con el stock de todo
     * su subárbol (sus productos directos + los de sus subcategorías). Las
     * categorías sin productos conservan su estado manual. Cada cambio se
     * registra en la auditoría en una sola entrada.
     *
     * Se invoca tras cada operación que mueve stock (recepción, salidas,
     * bajas por vencimiento) y como red de seguridad al abrir la página.
     *
     * @return void
     */
    public function sincronizarPorStock(): void
    {
        $afectadas = [];

        // Subcategorías: su propio stock decide.
        $subcategorias = $this->db->fetchAll(
            "SELECT c.id_categoria, c.nombre, c.status,
                (SELECT COUNT(*) FROM productos p WHERE p.id_categoria = c.id_categoria) AS total,
                (SELECT COALESCE(SUM(p.stock_actual > 0), 0) FROM productos p WHERE p.id_categoria = c.id_categoria) AS con_stock
             FROM categorias c
             WHERE c.parent_id IS NOT NULL"
        );
        foreach ($subcategorias as $fila) {
            $estado = $this->estadoPorStock($fila);
            if ($estado !== null && $estado !== $fila['status']) {
                $this->db->execute("UPDATE categorias SET status = ? WHERE id_categoria = ?", [$estado, (int)$fila['id_categoria']]);
                $afectadas[] = $fila['nombre'];
            }
        }

        // Raíces: el stock de toda la rama decide.
        $raices = $this->db->fetchAll(
            "SELECT c.id_categoria, c.nombre, c.status,
                (SELECT COUNT(*) FROM productos p JOIN categorias h ON p.id_categoria = h.id_categoria
                    WHERE h.id_categoria = c.id_categoria OR h.parent_id = c.id_categoria) AS total,
                (SELECT COALESCE(SUM(p.stock_actual > 0), 0) FROM productos p JOIN categorias h ON p.id_categoria = h.id_categoria
                    WHERE h.id_categoria = c.id_categoria OR h.parent_id = c.id_categoria) AS con_stock
             FROM categorias c
             WHERE c.parent_id IS NULL"
        );
        foreach ($raices as $fila) {
            $estado = $this->estadoPorStock($fila);
            if ($estado !== null && $estado !== $fila['status']) {
                $this->db->execute("UPDATE categorias SET status = ? WHERE id_categoria = ?", [$estado, (int)$fila['id_categoria']]);
                $afectadas[] = $fila['nombre'];
            }
        }

        if ($afectadas !== []) {
            registrarAuditoria('sincronizar_stock', 'Estado de categorías actualizado por stock: ' . implode(', ', $afectadas));
        }
    }

    /**
     * Deduce el estado que le corresponde a una categoría según su stock.
     *
     * Con productos: todo en cero devuelve 'Inactivo' y con stock
     * devuelve 'Activo'. Sin productos devuelve null: la categoría está
     * vacía y su estado lo maneja la persona, no el stock.
     *
     * @param array $fila Fila con total de productos y con_stock.
     * @return string|null Estado deducido o null si es una categoría vacía.
     */
    private function estadoPorStock(array $fila): ?string
    {
        if ((int)($fila['total'] ?? 0) > 0) {
            return (int)($fila['con_stock'] ?? 0) > 0 ? 'Activo' : 'Inactivo';
        }
        return null;
    }

    /**
     * Listado completo de categorías en orden de árbol.
     *
     * Devuelve cada categoría con el conteo de productos directos
     * y con stock (para los avisos del árbol). El orden agrupa cada raíz
     * con sus subcategorías: primero la raíz (nivel 0) y luego sus hijos
     * por nombre.
     *
     * @return array Listado plano de categorías ordenado padre-antes-que-hijos.
     */
    public function listar(): array
    {
        return $this->db->fetchAll(
            "SELECT c.*,
                (SELECT COUNT(*) FROM productos p WHERE p.id_categoria = c.id_categoria) AS productos_directos,
                (SELECT COALESCE(SUM(p.stock_actual > 0), 0) FROM productos p WHERE p.id_categoria = c.id_categoria) AS productos_con_stock
             FROM categorias c
             ORDER BY COALESCE(c.parent_id, c.id_categoria) ASC, c.nivel ASC, c.nombre ASC"
        );
    }

    /**
     * Árbol de categorías listo para renderizar.
     *
     * Agrupa el listado plano en raíces con sus subcategorías anidadas y
     * suma a cada raíz el total de productos de toda su rama (directos +
     * hijos), que es lo que muestra el árbol y lo que usa la regla manual
     * de ocultar/mostrar.
     *
     * @return array Lista de raíces; cada una lleva 'hijos' y 'productos_total'.
     */
    public function listarArbol(): array
    {
        $por_id = [];
        foreach ($this->listar() as $categoria) {
            $categoria['hijos'] = [];
            $categoria['productos_total'] = (int)($categoria['productos_directos'] ?? 0);
            $por_id[(int)$categoria['id_categoria']] = $categoria;
        }
        // Engancha cada subcategoría bajo su raíz y suma a la raíz los
        // productos directos de la subcategoría (rama completa).
        foreach ($por_id as $categoria) {
            $id_padre = (int)($categoria['parent_id'] ?? 0);
            if ($id_padre > 0 && isset($por_id[$id_padre])) {
                $por_id[$id_padre]['hijos'][] = $categoria;
                $por_id[$id_padre]['productos_total'] += (int)($categoria['productos_directos'] ?? 0);
                $por_id[$id_padre]['productos_con_stock'] += (int)($categoria['productos_con_stock'] ?? 0);
            }
        }
        $arbol = [];
        foreach ($por_id as $categoria) {
            if ((int)($categoria['parent_id'] ?? 0) === 0) {
                $arbol[] = $categoria;
            }
        }
        return $arbol;
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
     * Repara nivel y ruta de categorías heredadas sin jerarquía.
     *
     * Datos antiguos pueden venir con nivel o ruta nulos. Recalcula ambos
     * campos en cascada (raíz: nivel 0 y ruta = nombre; subcategoría: nivel
     * 1 y ruta = ruta_del_padre + "/" + nombre) y solo escribe las filas
     * que difieren. Se invoca junto a repararCodigos() al abrir la página.
     *
     * @return void
     */
    public function repararJerarquia(): void
    {
        $categorias = $this->db->fetchAll(
            "SELECT id_categoria, nombre, parent_id, nivel, ruta FROM categorias ORDER BY nivel ASC, id_categoria ASC"
        );
        $rutas = [];
        foreach ($categorias as $categoria) {
            $id = (int)$categoria['id_categoria'];
            $id_padre = (int)($categoria['parent_id'] ?? 0);
            $nivel_esperado = $id_padre > 0 ? 1 : 0;
            $ruta_padre = $id_padre > 0 ? ($rutas[$id_padre] ?? '') : '';
            $ruta_esperada = $ruta_padre !== '' ? $ruta_padre . '/' . $categoria['nombre'] : $categoria['nombre'];
            $rutas[$id] = $ruta_esperada;
            if ((int)($categoria['nivel'] ?? 0) !== $nivel_esperado || (string)($categoria['ruta'] ?? '') !== $ruta_esperada) {
                $this->db->execute(
                    "UPDATE categorias SET nivel = ?, ruta = ? WHERE id_categoria = ?",
                    [$nivel_esperado, $ruta_esperada, $id]
                );
            }
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
