<?php

/** @var array<string, string>|null $flash */
/** @var array{total: int, hoy: int, ultimo_login: array|null} $resumen */
/** @var array<int, string> $modulos_disponibles */
/** @var array<int, string> $acciones_disponibles */
/** @var array<string, string> $acciones_etiquetas */
/** @var string $filtro_buscar */
/** @var string $filtro_modulo */
/** @var string $filtro_accion */
/** @var string $filtro_fecha_desde */
/** @var string $filtro_fecha_hasta */
/** @var int $total_registros */
/** @var int $total_paginas */
/** @var int $page */
/** @var array<int, array<string, mixed>> $registros */
/** @var string $query_string */

$ultimo_login = $resumen['ultimo_login'] ?? null;
$url_base = APP_URL_BASE . 'index.php?url=auditoria';
$url_pagina = static function (int $numero) use ($url_base, $query_string): string {
    return $url_base . '&page=' . $numero . ($query_string !== '' ? '&' . $query_string : '');
};
?>
<?php if ($flash): ?>
    <div class="alert-jv alert-jv-<?php echo htmlspecialchars((string)$flash['tipo'], ENT_QUOTES, 'UTF-8'); ?> flash-auto mb-3" role="status" aria-live="polite">
        <?php echo htmlspecialchars((string)$flash['texto'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="module-header auditoria-cabecera">
    <div class="module-header-icon auditoria-header-icono" aria-hidden="true">
        <i class="bi bi-shield-check"></i>
    </div>
    <div>
        <h1 class="module-title">Registro de Auditoría</h1>
        <p class="module-subtitle">Trazabilidad e historial de acciones del sistema</p>
    </div>
    <div class="auditoria-contador" aria-label="Eventos encontrados">
        <span class="auditoria-contador-numero"><?php echo number_format($total_registros); ?></span>
        <span class="auditoria-contador-label">eventos</span>
    </div>
</div>

<div class="row g-3 mb-4 auditoria-resumen">
    <div class="col-12 col-md-4">
        <div class="auditoria-card">
            <div class="auditoria-card-icono auditoria-card-icono-total" aria-hidden="true">
                <i class="bi bi-journal-text"></i>
            </div>
            <div>
                <span class="auditoria-card-label">Total de eventos</span>
                <strong class="auditoria-card-valor"><?php echo number_format($resumen['total']); ?></strong>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="auditoria-card">
            <div class="auditoria-card-icono auditoria-card-icono-hoy" aria-hidden="true">
                <i class="bi bi-calendar2-check"></i>
            </div>
            <div>
                <span class="auditoria-card-label">Eventos hoy</span>
                <strong class="auditoria-card-valor"><?php echo number_format($resumen['hoy']); ?></strong>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="auditoria-card">
            <div class="auditoria-card-icono auditoria-card-icono-login" aria-hidden="true">
                <i class="bi bi-box-arrow-in-right"></i>
            </div>
            <div class="auditoria-card-texto">
                <span class="auditoria-card-label">Último inicio de sesión</span>
                <?php if ($ultimo_login): ?>
                    <?php $fecha_login = strtotime((string)$ultimo_login['fecha_hora']); ?>
                    <strong class="auditoria-card-valor auditoria-card-valor-usuario">
                        <?php echo htmlspecialchars((string)$ultimo_login['usuario_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                    </strong>
                    <span class="auditoria-card-detalle">
                        <?php echo $fecha_login ? date('d/m/Y H:i', $fecha_login) : 'Fecha no disponible'; ?>
                    </span>
                <?php else: ?>
                    <strong class="auditoria-card-valor auditoria-card-valor-usuario">Sin registros</strong>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<form class="auditoria-filtros card-jv p-4" method="GET" action="<?php echo APP_URL_BASE; ?>index.php">
    <input type="hidden" name="url" value="auditoria">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-6 col-xl-6">
            <label for="auditoriaBuscar">Buscar</label>
            <div class="auditoria-busqueda">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    type="search"
                    id="auditoriaBuscar"
                    name="buscar"
                    class="input-jv"
                    value="<?php echo htmlspecialchars($filtro_buscar, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Usuario, descripción, IP o acción…"
                    maxlength="100"
                    autocomplete="off"
                >
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl-3">
            <label for="auditoriaModulo">Módulo</label>
            <select id="auditoriaModulo" name="modulo" class="input-jv auditoria-select">
                <option value="">Todos los módulos</option>
                <?php foreach ($modulos_disponibles as $modulo): ?>
                    <option value="<?php echo htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtro_modulo === $modulo ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-3">
            <label for="auditoriaAccion">Acción</label>
            <select id="auditoriaAccion" name="accion" class="input-jv auditoria-select">
                <option value="">Todas las acciones</option>
                <?php foreach ($acciones_disponibles as $accion): ?>
                    <option value="<?php echo htmlspecialchars($accion, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filtro_accion === $accion ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($acciones_etiquetas[$accion] ?? $accion, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-3">
            <label for="auditoriaFechaDesde">Desde</label>
            <input
                type="date"
                id="auditoriaFechaDesde"
                name="fecha_desde"
                class="form-control input-jv auditoria-fecha"
                value="<?php echo htmlspecialchars($filtro_fecha_desde, ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="off"
            >
        </div>
        <div class="col-6 col-md-3 col-xl-3">
            <label for="auditoriaFechaHasta">Hasta</label>
            <input
                type="date"
                id="auditoriaFechaHasta"
                name="fecha_hasta"
                class="form-control input-jv auditoria-fecha"
                value="<?php echo htmlspecialchars($filtro_fecha_hasta, ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="off"
            >
        </div>
        <div class="col-12 col-md-6 col-xl-6">
            <div class="auditoria-acciones-filtro">
                <button type="submit" class="btn btn-dark auditoria-btn-filtrar">
                    <i class="bi bi-funnel" aria-hidden="true"></i> Filtrar
                </button>
                <a href="<?php echo APP_URL_BASE; ?>index.php?url=auditoria" class="btn btn-outline-secondary auditoria-btn-limpiar">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<section class="auditoria-resultados" aria-label="Eventos de auditoría">
    <div class="table-responsive auditoria-tabla-scroll">
        <table class="table table-hover table-striped align-middle auditoria-tabla mb-0">
            <caption class="visually-hidden">Eventos de auditoría del sistema, del más reciente al más antiguo.</caption>
            <thead>
                <tr>
                    <th scope="col">Fecha / Hora</th>
                    <th scope="col">Usuario</th>
                    <th scope="col">Módulo</th>
                    <th scope="col">Acción</th>
                    <th scope="col">Descripción</th>
                    <th scope="col">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $evento): ?>
                    <?php
                        $accion_evento = strtoupper((string)$evento['accion']);
                        $clase_badge = match ($accion_evento) {
                            'LOGIN', 'LOGOUT' => 'bg-info text-dark',
                            'CREAR' => 'bg-success',
                            'EDITAR' => 'bg-warning text-dark',
                            'ANULAR', 'ELIMINAR' => 'bg-danger',
                            'BACKUP' => 'bg-primary',
                            default => 'bg-secondary',
                        };
                        $fecha_evento = strtotime((string)$evento['fecha_hora']);
                        $nombre_usuario = (string)$evento['usuario_nombre'];
                        $inicial_usuario = mb_strtoupper(mb_substr($nombre_usuario, 0, 1, 'UTF-8'), 'UTF-8');
                    ?>
                    <tr>
                        <td class="auditoria-fecha-cell" data-label="Fecha / Hora">
                            <time datetime="<?php echo $fecha_evento ? date('Y-m-d\TH:i:s', $fecha_evento) : ''; ?>">
                                <span class="auditoria-fecha"><?php echo $fecha_evento ? date('d/m/Y', $fecha_evento) : '—'; ?></span>
                                <span class="auditoria-hora"><?php echo $fecha_evento ? date('H:i:s', $fecha_evento) : ''; ?></span>
                            </time>
                        </td>
                        <td data-label="Usuario">
                            <div class="auditoria-usuario">
                                <span class="auditoria-avatar" aria-hidden="true"><?php echo htmlspecialchars($inicial_usuario !== '' ? $inicial_usuario : '?', ENT_QUOTES, 'UTF-8'); ?></span>
                                <div>
                                    <span class="auditoria-usuario-nombre"><?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="auditoria-usuario-id">
                                        <?php echo $evento['id_usuario'] !== null ? 'ID #' . (int)$evento['id_usuario'] : 'Cuenta eliminada'; ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Módulo">
                            <span class="auditoria-modulo"><?php echo htmlspecialchars((string)$evento['modulo'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Acción">
                            <span class="badge <?php echo $clase_badge; ?> auditoria-badge">
                                <?php echo htmlspecialchars($accion_evento, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td class="auditoria-descripcion-cell" data-label="Descripción">
                            <span class="auditoria-descripcion" title="<?php echo htmlspecialchars((string)$evento['descripcion'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string)$evento['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td data-label="IP">
                            <?php if (!empty($evento['ip_origen'])): ?>
                                <code class="auditoria-ip"><?php echo htmlspecialchars((string)$evento['ip_origen'], ENT_QUOTES, 'UTF-8'); ?></code>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($registros === []): ?>
                    <tr>
                        <td colspan="6" class="auditoria-vacio">
                            <i class="bi bi-shield-slash" aria-hidden="true"></i>
                            <strong>No hay eventos para mostrar</strong>
                            <span>Prueba con otro texto, módulo o acción.</span>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($total_paginas > 1): ?>
    <nav class="auditoria-paginacion" aria-label="Paginación de auditoría">
        <a href="<?php echo htmlspecialchars($url_pagina(max(1, $page - 1)), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" aria-label="Página anterior">&lsaquo;</a>
        <?php for ($numero_pagina = max(1, $page - 2); $numero_pagina <= min($total_paginas, $page + 2); $numero_pagina++): ?>
            <a href="<?php echo htmlspecialchars($url_pagina($numero_pagina), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $numero_pagina === $page ? 'active' : ''; ?>" <?php echo $numero_pagina === $page ? 'aria-current="page"' : ''; ?>>
                <?php echo $numero_pagina; ?>
            </a>
        <?php endfor; ?>
        <a href="<?php echo htmlspecialchars($url_pagina(min($total_paginas, $page + 1)), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $page >= $total_paginas ? 'disabled' : ''; ?>" aria-label="Página siguiente">&rsaquo;</a>
    </nav>
<?php endif; ?>
