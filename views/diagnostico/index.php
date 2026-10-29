<?php

/** @var array<string, string>|null $flash */
/** @var array<int, array<string, mixed>> $errores */
/** @var int $total */
/** @var int $activos */
/** @var array<string, string> $filtros */
/** @var int $pagina */
/** @var int $total_paginas */
/** @var string $query_string */
?>
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3">
        <?php echo htmlspecialchars($flash['texto'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="module-header diagnostico-cabecera">
    <div class="module-header-icon diagnostico-header-icono" aria-hidden="true">
        <i class="bi bi-activity"></i>
    </div>
    <div>
        <h1 id="diagnostico-header-title" class="module-title">Diagnóstico</h1>
        <p class="module-subtitle">Errores del sistema, antes de que la persona usuaria los reporte</p>
    </div>
    <div class="diagnostico-conteo">
        <span class="diagnostico-conteo-numero"><?php echo number_format($activos); ?></span>
        <span class="diagnostico-conteo-label">activos</span>
    </div>
</div>

<form class="diagnostico-filtros" method="GET" action="<?php echo APP_URL_BASE; ?>index.php">
    <input type="hidden" name="url" value="diagnostico">
    <div class="diagnostico-filtro">
        <label for="filtroOrigen">Origen</label>
        <select id="filtroOrigen" name="origen" class="input-jv">
            <option value="">Todos</option>
            <option value="servidor" <?php echo $filtros['origen'] === 'servidor' ? 'selected' : ''; ?>>Servidor</option>
            <option value="navegador" <?php echo $filtros['origen'] === 'navegador' ? 'selected' : ''; ?>>Navegador</option>
        </select>
    </div>
    <div class="diagnostico-filtro">
        <label for="filtroEstado">Estado</label>
        <select id="filtroEstado" name="estado" class="input-jv">
            <option value="activos" <?php echo $filtros['estado'] === 'activos' ? 'selected' : ''; ?>>Activos</option>
            <option value="resueltos" <?php echo $filtros['estado'] === 'resueltos' ? 'selected' : ''; ?>>Resueltos</option>
            <option value="" <?php echo $filtros['estado'] === '' ? 'selected' : ''; ?>>Todos</option>
        </select>
    </div>
    <div class="diagnostico-filtro-busqueda">
        <label for="filtroBuscar">Buscar</label>
        <input type="search" id="filtroBuscar" name="buscar" class="input-jv"
               value="<?php echo htmlspecialchars($filtros['buscar'], ENT_QUOTES, 'UTF-8'); ?>"
               placeholder="Mensaje, archivo o URL" maxlength="100" autocomplete="off">
    </div>
    <div class="diagnostico-acciones-filtro">
        <button type="submit" class="btn-jv-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrar</button>
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=diagnostico" class="diagnostico-limpiar">Limpiar</a>
    </div>
</form>

<section class="diagnostico-resultados" aria-label="Errores registrados">
    <div class="diagnostico-tabla-scroll">
        <table class="diagnostico-tabla" id="tablaErrores">
            <thead>
                <tr>
                    <th scope="col">Origen</th>
                    <th scope="col">Error</th>
                    <th scope="col">Ubicación</th>
                    <th scope="col" class="diagnostico-th-centro">Veces</th>
                    <th scope="col">Última vez</th>
                    <th scope="col">Estado</th>
                    <th class="diagnostico-accion" scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errores as $fila): ?>
                    <tr class="diagnostico-fila">
                        <td data-label="Origen">
                            <span class="diagnostico-origen <?php echo $fila['origen'] === 'navegador' ? 'diagnostico-origen-navegador' : 'diagnostico-origen-servidor'; ?>">
                                <i class="bi <?php echo $fila['origen'] === 'navegador' ? 'bi-browser-chrome' : 'bi-hdd-network'; ?>" aria-hidden="true"></i>
                                <?php echo $fila['origen'] === 'navegador' ? 'Navegador' : 'Servidor'; ?>
                            </span>
                        </td>
                        <td data-label="Error" class="diagnostico-celda-mensaje">
                            <span class="diagnostico-mensaje" title="<?php echo htmlspecialchars($fila['mensaje'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(mb_substr($fila['mensaje'], 0, 120), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if (!empty($fila['clase'])): ?>
                                <span class="diagnostico-clase"><?php echo htmlspecialchars($fila['clase'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Ubicación">
                            <code class="diagnostico-ubicacion">
                                <?php echo $fila['archivo']
                                    ? htmlspecialchars(basename((string)$fila['archivo']) . ':' . (int)$fila['linea'], ENT_QUOTES, 'UTF-8')
                                    : '—'; ?>
                            </code>
                        </td>
                        <td data-label="Veces" class="diagnostico-total">
                            <span class="badge-jv <?php echo (int)$fila['ocurrencias'] > 1 ? 'badge-warning' : 'badge-info'; ?>">
                                <?php echo number_format((int)$fila['ocurrencias']); ?>
                            </span>
                        </td>
                        <td data-label="Última vez"><?php echo htmlspecialchars(date('d/m/Y, H:i', strtotime((string)$fila['ultima_vez'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="Estado">
                            <span class="badge-jv <?php echo $fila['resuelto'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $fila['resuelto'] ? 'Resuelto' : 'Activo'; ?>
                            </span>
                        </td>
                        <td data-label="Acciones" class="diagnostico-accion-cell">
                            <button type="button" class="btn btn-sm btn-outline-secondary diagnostico-btn-detalle" data-id="<?php echo (int)$fila['id_error']; ?>"
                                    aria-label="Ver detalle del error <?php echo (int)$fila['id_error']; ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                            <?php if (!$fila['resuelto']): ?>
                            <form method="POST" action="<?php echo APP_URL_BASE; ?>index.php?url=diagnostico/resolver" class="diagnostico-form-resolver">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id_error" value="<?php echo (int)$fila['id_error']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" aria-label="Marcar error <?php echo (int)$fila['id_error']; ?> como resuelto">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($errores === []): ?>
                    <tr>
                        <td colspan="7" class="diagnostico-vacio">
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                            <strong>No hay errores que revisar</strong>
                            <span>Con estos filtros el diario está limpio.</span>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($total_paginas > 1): ?>
    <nav class="diagnostico-paginacion" aria-label="Paginación del diario de errores">
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=diagnostico&page=<?php echo max(1, $pagina - 1); ?><?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $pagina <= 1 ? 'disabled' : ''; ?>" aria-label="Página anterior">&lsaquo;</a>
        <?php for ($numero_pagina = max(1, $pagina - 2); $numero_pagina <= min($total_paginas, $pagina + 2); $numero_pagina++): ?>
            <a href="<?php echo APP_URL_BASE; ?>index.php?url=diagnostico&page=<?php echo $numero_pagina; ?><?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $numero_pagina === $pagina ? 'active' : ''; ?>" <?php echo $numero_pagina === $pagina ? 'aria-current="page"' : ''; ?>>
                <?php echo $numero_pagina; ?>
            </a>
        <?php endfor; ?>
        <a href="<?php echo APP_URL_BASE; ?>index.php?url=diagnostico&page=<?php echo min($total_paginas, $pagina + 1); ?><?php echo $query_string !== '' ? '&' . $query_string : ''; ?>" class="<?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>" aria-label="Página siguiente">&rsaquo;</a>
    </nav>
<?php endif; ?>
