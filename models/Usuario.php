<?php

// ==========================================
// MODELO: Usuario
// ==========================================
// CRUD de usuarios: listar, editar, aprobar,
// toggle status, validación de uniqueness.

/**
 * Usuario: modelo del módulo de gestión de usuarios.
 *
 * Capa de acceso a datos: listar, editar, aprobar, toggle status,
 * validación de nombre/correo duplicados y cambio de contraseña.
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
        $pregunta = trim($datos['pregunta_seguridad'] ?? '');
        $respuesta = trim($datos['respuesta_seguridad'] ?? '');

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

        // Seguridad
        if ($pregunta !== '' && $respuesta !== '') {
            $respuestaHash = password_hash(mb_strtolower(trim($respuesta)), PASSWORD_BCRYPT);
        } else {
            $pregunta = null;
            $respuestaHash = null;
        }

        $this->db->begin();
        try {
            if ($passHash) {
                $this->db->execute(
                    "UPDATE usuarios SET usuario=?, correo=?, password=?, password_check=?, id_rol=?, status=?, pregunta_seguridad=?, respuesta_seguridad=? WHERE id_usuario=?",
                    [$usuario, $correo, $passHash, $passCheck, $idRol, $status, $pregunta, $respuestaHash, $id]
                );
            } else {
                $this->db->execute(
                    "UPDATE usuarios SET usuario=?, correo=?, id_rol=?, status=?, pregunta_seguridad=?, respuesta_seguridad=? WHERE id_usuario=?",
                    [$usuario, $correo, $idRol, $status, $pregunta, $respuestaHash, $id]
                );
            }
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

        // Seguridad
        if ($pregunta === '' || $respuesta === '') $errores[] = 'PREGUNTA Y RESPUESTA DE SEGURIDAD SON OBLIGATORIAS.';
        $respuestaHash = password_verify(mb_strtolower($respuesta), $current['respuesta_seguridad'] ?? '') ? $current['respuesta_seguridad'] : password_hash(mb_strtolower($respuesta), PASSWORD_BCRYPT);

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
