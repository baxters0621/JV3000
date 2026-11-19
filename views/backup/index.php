<?php
// ==========================================
// VISTA: Backup y restauración
// ==========================================
// Dos tarjetas: exportar el volcado y restaurar un archivo .sql.
?>
<div class="pagina-backup">
    <div class="container py-5">

        <div class="d-flex align-items-center gap-3 gap-md-4 mb-4 flex-wrap">
            <div class="backup-header-icon">
                <i class="bi bi-database-lock" aria-hidden="true"></i>
            </div>
            <div>
                <h1 class="module-title">BACKUP Y RESTAURACIÓN</h1>
                <p class="module-subtitle">Gestión de copias de seguridad del sistema</p>
            </div>
        </div>

        <?php if (!empty($flash)): ?>
            <div class="alert-jv alert-jv-<?php echo htmlspecialchars((string)$flash['tipo']); ?> flash-auto mb-4" role="status" aria-live="polite">
                <i class="bi bi-shield-check me-2" aria-hidden="true"></i><?php echo htmlspecialchars((string)$flash['texto']); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <div class="col-lg-6">
                <div class="card backup-card h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="bi bi-download" aria-hidden="true"></i>
                        <h2 class="h4 fw-bold mb-0">Exportar</h2>
                    </div>
                    <div class="card-body d-flex flex-column p-4">
                        <p class="mb-2">
                            Descarga un archivo <code>.sql</code> con la estructura y los datos
                            de las <strong><?php echo (int)$total_tablas; ?></strong> tablas del sistema.
                        </p>
                        <p class="backup-meta mb-4">
                            El archivo contiene información sensible: usuarios, contraseñas cifradas
                            y configuración. Guárdalo en un lugar seguro y no lo compartas.
                        </p>

                        <form method="post" action="<?php echo BASE_PATH; ?>index.php?url=backup/exportar" class="mt-auto" data-exportar>
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-database-down me-1" aria-hidden="true"></i>Descargar backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card backup-card h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="bi bi-upload" aria-hidden="true"></i>
                        <h2 class="h4 fw-bold mb-0">Importar</h2>
                    </div>
                    <div class="card-body d-flex flex-column p-4">
                        <div class="alert alert-danger backup-aviso" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                            Al restaurar, los datos actuales serán reemplazados. Asegúrese de tener un backup previo.
                        </div>

                        <form method="post" action="<?php echo BASE_PATH; ?>index.php?url=backup/importar" enctype="multipart/form-data" data-importar data-max-mb="<?php echo (int)$max_mb; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8'); ?>">

                            <label for="archivoBackup" class="form-label">Archivo de respaldo (.sql)</label>
                            <input type="file" class="form-control" id="archivoBackup" name="archivo" accept=".sql,application/sql,text/plain" required>
                            <div class="form-text">
                                Máximo <?php echo (int)$max_mb; ?> MB. Se aceptan volcados generados por JV3000 o por mysqldump.
                            </div>

                            <button type="submit" class="btn btn-danger w-100 mt-3">
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restaurar Base de Datos
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
