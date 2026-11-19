<?php

// ==========================================
// CONTROLADOR: Backup y restauración
// ==========================================
// Exporta la base de datos a un .sql y
// restaura un respaldo. Solo Administrador.

/**
 * BackupController: gestiona el módulo de respaldo y restauración.
 *
 * Todas las acciones exigen el rol Administrador y un POST con CSRF para las
 * operaciones que leen o reemplazan la base de datos.
 */
class BackupController extends Controller
{
    /**
     * Muestra la pantalla con las tarjetas de exportar e importar.
     *
     * @return void
     */
    public function index(): void
    {
        $this->exigirAdmin();

        $modelo = new Backup();

        $this->view('backup/index', [
            'titulo'         => 'Backup y Restauración | JV3000 C.A.',
            'wrapper_class'  => 'backup-page',
            'css_extra'      => ['modules/backup/backup.css'],
            'js_extra'       => ['modules/backup/backup.js'],
            'csrf'           => Security::generateToken(),
            'max_mb'         => $modelo->maxMb(),
            'total_tablas'   => $modelo->totalTablas(),
            'flash'          => $this->consumeFlash(),
        ]);
    }

    /**
     * Genera el volcado y lo descarga como archivo .sql.
     *
     * Se escribe primero en un temporal del sistema para no enviar una
     * descarga parcial si algo falla a mitad de la generación.
     *
     * @return void
     */
    public function exportar(): void
    {
        $this->exigirAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('backup');
        }
        Security::validateCSRF();

        $modelo = new Backup();
        $temporal = tempnam(sys_get_temp_dir(), 'jv3000_export_');
        if ($temporal === false) {
            $this->flash('danger', 'No se pudo preparar el archivo de exportación.', 'exportar');
            $this->redirect('backup');
        }

        try {
            $modelo->volcarA($temporal);
        } catch (Throwable $e) {
            @unlink($temporal);
            error_log('[JV3000] Exportación fallida: ' . $e->getMessage());
            $this->flash('danger', 'No se pudo generar el respaldo. Revisa el diario de errores.', 'exportar');
            $this->redirect('backup');
        }

        $nombre = 'jv3000_' . date('Ymd_His') . '.sql';
        $tamano = filesize($temporal);

        // La exportación debe quedar auditada, pero un fallo del registro no
        // puede impedir que el administrador descargue su respaldo.
        try {
            auditar('BACKUP', 'Backup', 'Exportación de base de datos: ' . $nombre);
        } catch (Throwable $e) {
            error_log('[JV3000] No se pudo auditar la exportación: ' . $e->getMessage());
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        set_time_limit(300);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . ($tamano === false ? 0 : $tamano));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        session_write_close();

        readfile($temporal);
        @unlink($temporal);
        exit;
    }

    /**
     * Valida y restaura el archivo .sql subido.
     *
     * @return void
     */
    public function importar(): void
    {
        $this->exigirAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('backup');
        }
        Security::validateCSRF();

        $modelo = new Backup();
        $validacion = $modelo->validarArchivo($_FILES['archivo'] ?? null);
        if (!$validacion['ok']) {
            $this->flash('danger', $validacion['mensaje'], 'importar');
            $this->redirect('backup');
        }

        $resultado = $modelo->importar($validacion['contenido'], $validacion['nombre']);
        if (!$resultado['ok']) {
            $this->flash('danger', $resultado['mensaje'], 'importar');
            $this->redirect('backup');
        }

        // El respaldo restaurado puede ser anterior a este módulo. Si la tabla
        // nueva no existe, la restauración ya fue exitosa y no debe fallar por
        // el registro de auditoría.
        try {
            auditar('BACKUP', 'Backup', 'Importación de base de datos: ' . $validacion['nombre']);
        } catch (Throwable $e) {
            error_log('[JV3000] No se pudo auditar la importación: ' . $e->getMessage());
        }

        // La base restaurada puede no contener al usuario que inició sesión e
        // incluso puede no tener tabla usuarios: nunca debe romper la respuesta.
        try {
            $usuario = Database::getInstance()->fetchOne(
                'SELECT id_rol, status FROM usuarios WHERE id_usuario = ? LIMIT 1',
                [(int)($_SESSION['id_usuario'] ?? 0)]
            );
        } catch (Throwable) {
            $usuario = null;
        }

        if (!$usuario || (int)$usuario['id_rol'] !== 1 || $usuario['status'] !== 'Activo') {
            session_destroy();
            header('Location: ' . BASE_PATH . 'login/login.php?error=restaurado');
            exit;
        }

        $this->flash('success', $resultado['mensaje'], 'importar');
        $this->redirect('backup');
    }

    /**
     * Exige el rol Administrador y responde 403 si no lo es.
     *
     * @return void
     */
    private function exigirAdmin(): void
    {
        if (Security::esAdmin()) {
            return;
        }

        http_response_code(403);
        $titulo = '403 — Acceso denegado';
        require APP_VIEWS . '/errors/403.php';
        exit;
    }
}
