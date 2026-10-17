<?php

// ==========================================
// MODELO: Usuario
// ==========================================
// CRUD de usuarios: listar, crear, editar,
// aprobar, toggle status, eliminar y
// validación de uniqueness.

/**
 * Usuario: modelo del módulo de gestión de usuarios.
 *
 * Capa de acceso a datos: listar, crear, editar, aprobar, toggle status,
 * eliminar, validación de nombre/correo duplicados y cambio de contraseña.
 */
class Usuario extends Model
{
    /**
     * Lista todos los usuarios con nombre de rol.
     *
     * @return array Usuarios con información de rol.
     */
    public function listar(): array
    {
        return $this->db->fetchAll(
            "SELECT u.id_usuario, u.usuario, u.correo, u.id_rol, r.nombre_rol,
                    u.status, COALESCE(u.aprobado, 1) as aprobado, u.pregunta_seguridad
             FROM usuarios u
             LEFT JOIN roles r ON u.id_rol = r.id_rol
             ORDER BY u.usuario ASC"
        );
    }

    /**
     * Obtiene un usuario por ID.
     *
     * @param int $id Identificador del usuario.
     * @return array|null Datos del usuario o null.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT u.*, r.nombre_rol
             FROM usuarios u
             LEFT JOIN roles r ON u.id_rol = r.id_rol
             WHERE u.id_usuario = ?",
            [$id]
        );
    }

    /**
     * Lista de roles disponibles.
     *
     * @return array Roles del sistema.
     */
    public function listarRoles(): array
    {
        return $this->db->fetchAll("SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol");
    }

    /**
     * Total de usuarios.
     */
    public function total(): int
    {
        return (int)($this->db->fetchOne("SELECT COUNT(*) as t FROM usuarios")['t'] ?? 0);
    }

    /**
     * Total de usuarios activos.
     */
    public function totalActivos(): int
    {
        return (int)($this->db->fetchOne("SELECT COUNT(*) as t FROM usuarios WHERE status = 'Activo'")['t'] ?? 0);
    }

    /**
     * Total de usuarios pendientes de aprobación.
     * Un usuario está pendiente cuando no tiene rol asignado y no ha sido aprobado.
     */
    public function totalPendientes(): int
    {
        return (int)($this->db->fetchOne("SELECT COUNT(*) as t FROM usuarios WHERE COALESCE(aprobado, 0) = 0 AND id_rol IS NULL")['t'] ?? 0);
    }

    /**
     * Crea un colaborador desde el panel del administrador.
     *
     * Valida nombre, correo, rol (solo Operador de Carga o de Ventas), fortaleza
     * y unicidad de la contraseña, y pregunta/respuesta de seguridad. La cuenta
     * nace directamente Activa y Aprobada, sin pasar por la cola de aprobación.
     *
     * @param array $datos Datos del formulario.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function crear(array $datos): array
    {
        $usuario = normalizarUsuario($datos['usuario'] ?? '');
        $correo = strtolower(trim($datos['correo'] ?? ''));
        $password = trim($datos['password'] ?? '');
        $passwordConfirm = trim($datos['password_confirm'] ?? '');
        $idRol = (int)($datos['id_rol'] ?? 0);
        $pregunta = trim($datos['pregunta_seguridad'] ?? '');
        $respuesta = trim($datos['respuesta_seguridad'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $usuario)) {
            return ['ok' => false, 'mensaje' => 'NOMBRE DE USUARIO: 4-20 CARACTERES (LETRAS, NÚMEROS, GUION BAJO).'];
        }
        if ($correo === '' || strlen($correo) > 100 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'CORREO INVÁLIDO.'];
        }
        // El admin solo puede crear Operadores de Carga (2) o de Ventas (3).
        if (!in_array($idRol, [2, 3], true)) {
            return ['ok' => false, 'mensaje' => 'ROL INVÁLIDO. SOLO OPERADOR DE CARGA O DE VENTAS.'];
        }
        if (!validarPasswordFuerte($password)) {
            $faltantes = requisitosFaltantesPassword($password);
            return ['ok' => false, 'mensaje' => 'CONTRASEÑA DÉBIL. FALTA: ' . strtoupper($faltantes) . '.'];
        }
        if ($password !== $passwordConfirm) {
            return ['ok' => false, 'mensaje' => 'LAS CONTRASEÑAS NO COINCIDEN.'];
        }
        if ($pregunta === '' || !in_array($pregunta, getPreguntasRespuestas(), true)) {
            return ['ok' => false, 'mensaje' => 'SELECCIONE UNA PREGUNTA DE SEGURIDAD VÁLIDA.'];
        }
        if (!validarRespuestaSeguridad($respuesta)) {
            return ['ok' => false, 'mensaje' => 'RESPUESTA DE SEGURIDAD INVÁLIDA. ESCRIBE AL MENOS UN CARÁCTER.'];
        }

        $dup = $this->db->fetchOne(
            "SELECT id_usuario FROM usuarios WHERE LOWER(usuario) = LOWER(?) OR LOWER(correo) = LOWER(?)",
            [$usuario, $correo]
        );
        if ($dup) return ['ok' => false, 'mensaje' => 'EL USUARIO O CORREO YA ESTÁ EN USO.'];

        $passCheck = generarPasswordCheck($password);
        if (existePasswordDuplicado($this->db, $passCheck)) {
            return ['ok' => false, 'mensaje' => 'LA CONTRASEÑA YA ESTÁ EN USO POR OTRO USUARIO. ELIGE OTRA DIFERENTE.'];
        }

        try {
            $this->db->insert('usuarios', [
                'usuario'             => $usuario,
                'correo'              => $correo,
                'password'            => password_hash($password, PASSWORD_BCRYPT),
                'password_check'      => $passCheck,
                'id_rol'              => $idRol,
                'pregunta_seguridad'  => $pregunta,
                'respuesta_seguridad' => password_hash(normalizarRespuestaSeguridad($respuesta), PASSWORD_BCRYPT),
                'status'              => 'Activo',
                'aprobado'            => 1,
            ]);
        } catch (Exception $e) {
            return ['ok' => false, 'mensaje' => 'ERROR AL CREAR EL USUARIO.'];
        }

        registrarAuditoria('crear', "Usuario creado desde el panel: $usuario");
        return ['ok' => true, 'mensaje' => 'COLABORADOR CREADO.'];
    }

    /**
     * Edita un usuario existente.
     *
     * @param array $datos Datos del formulario.
     * @param int   $idPropio ID del usuario logueado (para proteger su propio rol/status).
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function editar(array $datos, int $idPropio): array
    {
        $id = (int)($datos['id_usuario'] ?? 0);
        $usuario = mb_strtoupper(trim($datos['usuario'] ?? ''));
        $correo = strtolower(trim($datos['correo'] ?? ''));
        $password = trim($datos['password'] ?? '');
        $idRol = (int)($datos['id_rol'] ?? 0);
        $status = $datos['status'] ?? 'Activo';

        if ($id <= 0) return ['ok' => false, 'mensaje' => 'USUARIO INVÁLIDO.'];

        // Si edita a sí mismo, proteger rol y status
        if ($id === $idPropio) {
            $current = $this->db->fetchOne("SELECT id_rol, status FROM usuarios WHERE id_usuario = ?", [$id]);
            $idRol = (int)$current['id_rol'];
            $status = $current['status'];
        }

        if (!in_array($idRol, [1, 2, 3])) $idRol = 0;
        if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $usuario)) {
            return ['ok' => false, 'mensaje' => 'NOMBRE DE USUARIO: 4-20 CARACTERES (LETRAS, NÚMEROS, GUION BAJO).'];
        }

        // Uniqueness: nombre (case-insensitive)
        $dup = $this->db->fetchOne("SELECT id_usuario FROM usuarios WHERE LOWER(usuario) = LOWER(?) AND id_usuario != ?", [$usuario, $id]);
        if ($dup) return ['ok' => false, 'mensaje' => 'YA EXISTE UN USUARIO CON ESE NOMBRE.'];

        // Uniqueness: correo
        if ($correo !== '') {
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'mensaje' => 'CORREO INVÁLIDO.'];
            }
            $dupEmail = $this->db->fetchOne("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario != ?", [$correo, $id]);
            if ($dupEmail) return ['ok' => false, 'mensaje' => 'YA EXISTE UN USUARIO CON ESE CORREO.'];
        }

        // Password opcional
        $passHash = null;
        $passCheck = null;
        if ($password !== '') {
            if (!validarPasswordFuerte($password)) {
                $faltantes = requisitosFaltantesPassword($password);
                return ['ok' => false, 'mensaje' => 'CONTRASEÑA DÉBIL. FALTA: ' . strtoupper(implode(', ', $faltantes)) . '.'];
            }
            if (existePasswordDuplicado($password, $id)) {
                return ['ok' => false, 'mensaje' => 'LA CONTRASEÑA YA FUE UTILIZADA. ELIGE OTRA.'];
            }
            $passHash = password_hash($password, PASSWORD_BCRYPT);
            $passCheck = generarPasswordCheck($password);
        }

        // Pregunta y respuesta de seguridad: cambio OPCIONAL. El admin puede
        // reasignarlas a un colaborador que olvidó su respuesta. Si ambos campos
        // llegan vacíos se conservan las actuales (nunca se ponen a NULL).
        $pregunta = trim($datos['pregunta_seguridad'] ?? '');
        $respuesta = trim($datos['respuesta_seguridad'] ?? '');
        $cambiaSeguridad = ($pregunta !== '' || $respuesta !== '');
        if ($cambiaSeguridad) {
            if (!in_array($pregunta, getPreguntasRespuestas(), true)) {
                return ['ok' => false, 'mensaje' => 'SELECCIONE UNA PREGUNTA DE SEGURIDAD VÁLIDA.'];
            }
            if (!validarRespuestaSeguridad($respuesta)) {
                return ['ok' => false, 'mensaje' => 'RESPUESTA DE SEGURIDAD INVÁLIDA. ESCRIBE AL MENOS UN CARÁCTER.'];
            }
        }

        // El SET se arma dinámicamente: contraseña y seguridad solo se incluyen
        // cuando realmente cambian.
        $campos = ['usuario = ?', 'correo = ?', 'id_rol = ?', 'status = ?'];
        $params = [$usuario, $correo, $idRol, $status];
        if ($passHash) {
            $campos[] = 'password = ?';
            $params[] = $passHash;
            $campos[] = 'password_check = ?';
            $params[] = $passCheck;
        }
        if ($cambiaSeguridad) {
            $campos[] = 'pregunta_seguridad = ?';
            $params[] = $pregunta;
            $campos[] = 'respuesta_seguridad = ?';
            $params[] = password_hash(normalizarRespuestaSeguridad($respuesta), PASSWORD_BCRYPT);
        }
        $params[] = $id;

        $this->db->begin();
        try {
            $this->db->execute("UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id_usuario = ?", $params);
            $this->db->commit();
            registrarAuditoria('editar', "Usuario editado: $usuario");
            return ['ok' => true, 'mensaje' => 'USUARIO ACTUALIZADO.'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR AL EDITAR USUARIO.'];
        }
    }

    /**
     * Aprueba un usuario pendiente y le asigna un rol.
     *
     * @param int $idUsuario ID del usuario a aprobar.
     * @param int $idRol     Rol a asignar (2 o 3).
     * @param int $idPropio  ID del usuario logueado.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function aprobar(int $idUsuario, int $idRol, int $idPropio): array
    {
        if ($idUsuario === $idPropio) return ['ok' => false, 'mensaje' => 'NO PUEDES APROBARTE A TI MISMO.'];
        if (!in_array($idRol, [2, 3])) return ['ok' => false, 'mensaje' => 'ROL INVÁLIDO.'];

        $user = $this->db->fetchOne("SELECT aprobado FROM usuarios WHERE id_usuario = ?", [$idUsuario]);
        if (!$user) return ['ok' => false, 'mensaje' => 'USUARIO NO ENCONTRADO.'];
        if ((int)$user['aprobado'] !== 0) return ['ok' => false, 'mensaje' => 'EL USUARIO YA FUE APROBADO.'];

        $this->db->execute(
            "UPDATE usuarios SET id_rol = ?, status = 'Activo', aprobado = 1 WHERE id_usuario = ?",
            [$idRol, $idUsuario]
        );
        $nombre = $this->db->fetchOne("SELECT usuario FROM usuarios WHERE id_usuario = ?", [$idUsuario])['usuario'] ?? '';
        registrarAuditoria('aprobar', "Usuario aprobado: $nombre");
        return ['ok' => true, 'mensaje' => 'USUARIO APROBADO.'];
    }

    /**
     * Cambia el estado Activo/Inactivo de un usuario.
     *
     * @param int $idUsuario ID del usuario.
     * @param int $idPropio  ID del usuario logueado.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function toggleStatus(int $idUsuario, int $idPropio): array
    {
        if ($idUsuario === $idPropio) return ['ok' => false, 'mensaje' => 'NO PUEDES DESACTIVAR TU PROPIA CUENTA.'];

        $user = $this->db->fetchOne("SELECT status, usuario FROM usuarios WHERE id_usuario = ?", [$idUsuario]);
        if (!$user) return ['ok' => false, 'mensaje' => 'USUARIO NO ENCONTRADO.'];

        $nuevoStatus = $user['status'] === 'Activo' ? 'Inactivo' : 'Activo';
        $aprobado = $nuevoStatus === 'Activo' ? 1 : 0;
        $this->db->execute(
            "UPDATE usuarios SET status = ?, aprobado = ? WHERE id_usuario = ?",
            [$nuevoStatus, $aprobado, $idUsuario]
        );
        $accion = $nuevoStatus === 'Activo' ? 'reactivado' : 'desactivado';
        registrarAuditoria($accion, "Usuario $accion: " . $user['usuario']);
        return ['ok' => true, 'mensaje' => "USUARIO $accion."];
    }

    /**
     * Elimina definitivamente un usuario.
     *
     * Guardas: no puede eliminarse a sí mismo ni al último administrador activo.
     * Si el usuario tiene operaciones registradas (compras, pagos, movimientos,
     * salidas o solicitudes) el borrado se bloquea —las claves foráneas lo
     * impedirían igualmente— y se sugiere desactivarlo en su lugar. Al eliminar
     * se limpian también sus intentos de login para no arrastrar bloqueos.
     *
     * @param int $idUsuario ID del usuario a eliminar.
     * @param int $idPropio  ID del usuario logueado.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function eliminar(int $idUsuario, int $idPropio): array
    {
        if ($idUsuario <= 0) return ['ok' => false, 'mensaje' => 'USUARIO INVÁLIDO.'];
        if ($idUsuario === $idPropio) return ['ok' => false, 'mensaje' => 'NO PUEDES ELIMINAR TU PROPIA CUENTA.'];

        $user = $this->db->fetchOne(
            "SELECT id_usuario, usuario, id_rol, status, COALESCE(aprobado, 0) as aprobado
             FROM usuarios WHERE id_usuario = ?",
            [$idUsuario]
        );
        if (!$user) return ['ok' => false, 'mensaje' => 'USUARIO NO ENCONTRADO.'];

        // No dejar el sistema sin administrador.
        if ((int)$user['id_rol'] === 1) {
            $otrosAdmins = (int)($this->db->fetchOne(
                "SELECT COUNT(*) as t FROM usuarios
                 WHERE id_rol = 1 AND status = 'Activo' AND COALESCE(aprobado, 1) = 1 AND id_usuario != ?",
                [$idUsuario]
            )['t'] ?? 0);
            if ($otrosAdmins === 0) {
                return ['ok' => false, 'mensaje' => 'NO PUEDES ELIMINAR AL ÚLTIMO ADMINISTRADOR ACTIVO.'];
            }
        }

        // Historial operativo: las FK son RESTRICT, así que un DELETE fallaría.
        $historial = (int)($this->db->fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM compras             WHERE id_usuario = ?) +
                (SELECT COUNT(*) FROM pagos_compra        WHERE id_usuario = ?) +
                (SELECT COUNT(*) FROM movimientos         WHERE id_usuario = ?) +
                (SELECT COUNT(*) FROM salidas             WHERE id_usuario = ?) +
                (SELECT COUNT(*) FROM solicitudes_compra  WHERE id_usuario_solicitante = ?) AS t",
            [$idUsuario, $idUsuario, $idUsuario, $idUsuario, $idUsuario]
        )['t'] ?? 0);
        if ($historial > 0) {
            return ['ok' => false, 'mensaje' => 'TIENE OPERACIONES REGISTRADAS. DESACTÍVALO EN LUGAR DE ELIMINARLO.'];
        }

        $this->db->begin();
        try {
            $this->db->execute("DELETE FROM login_intentos WHERE usuario = ?", [$user['usuario']]);
            $this->db->execute("DELETE FROM usuarios WHERE id_usuario = ?", [$idUsuario]);
            $this->db->commit();
            registrarAuditoria('eliminar', "Usuario eliminado: " . $user['usuario']);
            return ['ok' => true, 'mensaje' => 'USUARIO ELIMINADO.'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['ok' => false, 'mensaje' => 'ERROR AL ELIMINAR EL USUARIO.'];
        }
    }

    /**
     * Actualiza el perfil del usuario logueado.
     *
     * @param array $datos Datos del formulario.
     * @return array ['ok'=>bool, 'mensaje'=>string].
     */
    public function actualizarPerfil(array $datos): array
    {
        $id = (int)($_SESSION['id_usuario'] ?? 0);
        $current = $this->db->fetchOne("SELECT * FROM usuarios WHERE id_usuario = ?", [$id]);
        if (!$current) return ['ok' => false, 'mensaje' => 'USUARIO NO ENCONTRADO.'];

        $passwordActual = trim($datos['password_actual'] ?? '');
        if (!password_verify($passwordActual, $current['password'])) {
            return ['ok' => false, 'mensaje' => 'LA CONTRASEÑA ACTUAL ES INCORRECTA.'];
        }

        $usuario = mb_strtoupper(trim($datos['usuario'] ?? ''));
        $correo = strtolower(trim($datos['correo'] ?? ''));
        $passwordNueva = trim($datos['password_nueva'] ?? '');
        $passwordConfirm = trim($datos['password_confirm'] ?? '');
        $pregunta = trim($datos['pregunta_seguridad'] ?? '');
        $respuesta = trim($datos['respuesta_seguridad'] ?? '');
        $pin = trim($datos['pin_emergencia'] ?? '');
        $pinConfirm = trim($datos['pin_emergencia_confirm'] ?? '');
        $pinEliminar = !empty($datos['pin_emergencia_eliminar']);

        $errores = [];

        // Nombre
        if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $usuario)) {
            $errores[] = 'NOMBRE: 4-20 CARACTERES (LETRAS, NÚMEROS, GUION BAJO).';
        }
        $dup = $this->db->fetchOne("SELECT id_usuario FROM usuarios WHERE LOWER(usuario) = LOWER(?) AND id_usuario != ?", [$usuario, $id]);
        if ($dup) $errores[] = 'YA EXISTE UN USUARIO CON ESE NOMBRE.';

        // Correo
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'CORREO REQUERIDO Y VÁLIDO.';
        }
        $dupEmail = $this->db->fetchOne("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario != ?", [$correo, $id]);
        if ($dupEmail) $errores[] = 'YA EXISTE UN USUARIO CON ESE CORREO.';

        // Contraseña nueva (opcional)
        $passHash = $current['password'];
        $passCheck = $current['password_check'];
        if ($passwordNueva !== '') {
            if ($passwordNueva !== $passwordConfirm) $errores[] = 'LAS CONTRASEÑAS NO COINCIDEN.';
            if (!validarPasswordFuerte($passwordNueva)) {
                $faltantes = requisitosFaltantesPassword($passwordNueva);
                $errores[] = 'CONTRASEÑA DÉBIL. FALTA: ' . strtoupper(implode(', ', $faltantes)) . '.';
            }
            if (password_verify($passwordNueva, $current['password'])) {
                $errores[] = 'LA NUEVA CONTRASEÑA DEBE SER DIFERENTE A LA ACTUAL.';
            }
            if (existePasswordDuplicado($passwordNueva, $id)) {
                $errores[] = 'LA CONTRASEÑA YA FUE UTILIZADA.';
            }
            if (empty($errores)) {
                $passHash = password_hash($passwordNueva, PASSWORD_BCRYPT);
                $passCheck = generarPasswordCheck($passwordNueva);
            }
        }

        // Seguridad: se normaliza igual que en el registro (trim + minúsculas +
        // sin acentos) para que la respuesta se pueda verificar después.
        if ($pregunta === '' || $respuesta === '') $errores[] = 'PREGUNTA Y RESPUESTA DE SEGURIDAD SON OBLIGATORIAS.';
        $respuestaHash = verificarRespuestaSeguridad($respuesta, $current['respuesta_seguridad'] ?? '')
            ? $current['respuesta_seguridad']
            : password_hash(normalizarRespuestaSeguridad($respuesta), PASSWORD_BCRYPT);

        // PIN
        $pinHash = $current['pin_emergencia'];
        if ($pinEliminar) {
            $pinHash = null;
        } elseif ($pin !== '') {
            if (!preg_match('/^\d{6}$/', $pin)) $errores[] = 'EL PIN DEBE SER 6 DÍGITOS.';
            if ($pin !== $pinConfirm) $errores[] = 'LOS PINES NO COINCIDEN.';
            if (empty($errores)) $pinHash = password_hash($pin, PASSWORD_BCRYPT);
        }

        if (!empty($errores)) return ['ok' => false, 'mensaje' => implode(' ', $errores)];

        $this->db->execute(
            "UPDATE usuarios SET usuario=?, correo=?, password=?, password_check=?, pregunta_seguridad=?, respuesta_seguridad=?, pin_emergencia=? WHERE id_usuario=?",
            [$usuario, $correo, $passHash, $passCheck, $pregunta, $respuestaHash, $pinHash, $id]
        );

        $_SESSION['usuario'] = $usuario;
        registrarAuditoria('editar', "Perfil actualizado: $usuario");
        return ['ok' => true, 'mensaje' => 'PERFIL ACTUALIZADO.'];
    }
}
