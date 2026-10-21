<div class="pagina-usuarios">
    <div class="container py-5">

        <div class="d-flex align-items-center gap-4 mb-4">
            <div class="user-header-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <h1 class="module-title">COLABORADORES</h1>
                <p class="module-subtitle">Gestión de Personal Autorizado</p>
            </div>
            <button type="button" class="btn btn-jv-primary module-action-btn ms-auto" onclick="abrirCreacion()" data-tooltip="Registrar un nuevo colaborador">
                <i class="bi bi-person-plus-fill me-1"></i>NUEVO COLABORADOR
            </button>
        </div>

        <?php if (!empty($flash)): ?>
            <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-4">
                <i class="bi bi-shield-check me-2"></i><?php echo htmlspecialchars($flash['texto']); ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(108,117,125,0.12);color:var(--jv-text-muted);">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="widget-label">Total Colaboradores</div>
                        <div class="widget-value"><?php echo $total_users; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(25,135,84,0.12);color:var(--jv-success);">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <div class="widget-label">Activos</div>
                        <div class="widget-value"><?php echo $activos; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="widget-card">
                    <div class="widget-icon" style="background:rgba(245,158,11,0.12);color:var(--jv-warning);">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <div class="widget-label">Pendientes de Aprobación</div>
                        <div class="widget-value"><?php echo $pendientes; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-jv p-0 overflow-hidden mb-4">
            <div class="header-card d-flex align-items-center gap-2">
                <i class="bi bi-shield-exclamation" style="color:var(--jv-warning);"></i>
                <span class="fw-bold small text-secondary text-uppercase">PIN de Emergencia Compartido</span>
                <span class="pin-ayuda">
                    <button type="button" class="pin-ayuda-icono" aria-label="¿Qué es el PIN de emergencia?" aria-describedby="pin-ayuda-texto">?</button>
                    <span class="pin-ayuda-globo" id="pin-ayuda-texto" role="tooltip">Es un <strong>único PIN de 6 dígitos</strong> que sirve para que todas las cuentas (Operadores de Carga y Ventas, y también la del Administrador) recuperen su contraseña <strong>sin internet</strong>. Cada usuario lo verá en <em>Mi Perfil</em>. El PIN es el respaldo cuando el correo electrónico no llega.</span>
                </span>
                <span class="codigo-badge ms-auto"><?php echo $pin_global !== '' ? 'DEFINIDO' : 'SIN DEFINIR'; ?></span>
            </div>
            <div class="p-3">
                <?php if ($pin_global !== ''): ?>
                    <p class="mb-3">PIN vigente: <strong class="fs-5"><?php echo htmlspecialchars($pin_global); ?></strong></p>
                <?php endif; ?>
                <form method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="accion_usuario" value="pin_global">
                    <div class="col-md-4">
                        <label class="form-label-jv" for="pin_emergencia_global"><?php echo $pin_global !== '' ? 'NUEVO PIN' : 'PIN'; ?></label>
                        <div class="password-field">
                            <input type="password" class="input-jv w-100" id="pin_emergencia_global" name="pin_emergencia_global" maxlength="6" inputmode="numeric" autocomplete="off" placeholder="6 dígitos" required oninput="validarPinGlobal()">
                            <button type="button" class="password-toggle" data-toggle-password data-password-label="el PIN" aria-label="Mostrar el PIN" aria-controls="pin_emergencia_global" aria-pressed="false">
                                <i class="bi bi-eye-slash" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="form-text jv-form-text" id="pin_emergencia_global_guia">6 dígitos, sin letras.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-jv" for="pin_emergencia_global_confirm">CONFIRMAR PIN</label>
                        <div class="password-field">
                            <input type="password" class="input-jv w-100" id="pin_emergencia_global_confirm" name="pin_emergencia_global_confirm" maxlength="6" inputmode="numeric" autocomplete="off" placeholder="Repite el PIN" required oninput="validarPinGlobal()">
                            <button type="button" class="password-toggle" data-toggle-password data-password-label="la confirmación del PIN" aria-label="Mostrar la confirmación del PIN" aria-controls="pin_emergencia_global_confirm" aria-pressed="false">
                                <i class="bi bi-eye-slash" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="form-text jv-form-text" id="pin_emergencia_global_confirm_guia">Repite el mismo PIN.</div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-jv-primary w-100"><i class="bi bi-shield-check me-1"></i>GUARDAR PIN</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-jv p-0 overflow-hidden">
            <div class="header-card d-flex align-items-center gap-2">
                <i class="bi bi-person-lines-fill" style="color:#7c3aed;"></i>
                <span class="fw-bold small text-secondary text-uppercase">Listado de Accesos</span>
                <span class="codigo-badge ms-auto"><?php echo $total_users; ?> registros</span>
            </div>

            <div class="buscador-usu d-flex align-items-center gap-2 px-3 py-2 flex-wrap">
                <div class="usu-search">
                    <i class="bi bi-search"></i>
                    <input type="text" class="input-jv" id="buscarUsuario" placeholder="Buscar por usuario o correo..." onkeyup="aplicarBusquedaUsuarios()" aria-label="Buscar colaborador">
                </div>
                <span class="contador-usuarios small ms-2" id="contadorUsuarios"> </span>
                <div class="filter-group-usu ms-auto">
                    <button type="button" class="btn-filter-usu active" data-status-filtro="todos" onclick="setFiltroUsuarios(this)">Todos</button>
                    <button type="button" class="btn-filter-usu" data-status-filtro="activo" onclick="setFiltroUsuarios(this)">Activos</button>
                    <button type="button" class="btn-filter-usu" data-status-filtro="pendiente" onclick="setFiltroUsuarios(this)">Pendientes</button>
                    <button type="button" class="btn-filter-usu" data-status-filtro="inactivo" onclick="setFiltroUsuarios(this)">Inactivos</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-jv mb-0" id="tablaUsuarios">
                    <thead>
                        <tr>
                            <th style="width:52px;">#</th>
                            <th style="width:16%;">USUARIO</th>
                            <th style="width:20%;">CORREO</th>
                            <th style="width:19%;">ROL</th>
                            <th class="text-center" style="width:14%;">ESTADO</th>
                            <th class="text-center" style="width:260px;">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoUsuarios">
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $row):
                                $es_pendiente = ((int)$row['aprobado'] === 0) && ($row['id_rol'] === null);
                                // Nombre escapado para pasarlo con seguridad al JS de las acciones.
                                $nombre_json = htmlspecialchars(json_encode($row['usuario'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                                if ($es_pendiente) {
                                    $estado_key  = 'pendiente';
                                    $estado_css  = 'badge-pendiente';
                                    $estado_icon = 'bi-hourglass-split';
                                    $estado_texto = 'PENDIENTE';
                                } elseif ($row['status'] == 'Activo') {
                                    $estado_key  = 'activo';
                                    $estado_css  = 'badge-success';
                                    $estado_icon = 'bi-check-circle-fill';
                                    $estado_texto = 'ACTIVO';
                                } else {
                                    $estado_key  = 'inactivo';
                                    $estado_css  = 'badge-danger';
                                    $estado_icon = 'bi-x-circle-fill';
                                    $estado_texto = 'INACTIVO';
                                } ?>
                                <tr class="usuario-fila <?php echo $es_pendiente ? 'fila-pendiente' : ''; ?>"
                                    data-id="<?php echo (int)$row['id_usuario']; ?>"
                                    data-usuario="<?php echo htmlspecialchars($row['usuario']); ?>"
                                    data-correo="<?php echo htmlspecialchars($row['correo'] ?? ''); ?>"
                                    data-rol="<?php echo (int)$row['id_rol']; ?>"
                                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                    data-estado="<?php echo $estado_key; ?>">
                                    <td class="text-secondary small"><span class="codigo-badge">#<?php echo $row['id_usuario']; ?></span></td>
                                    <td class="fw-bold usuario-cell" data-tooltip="<?php echo htmlspecialchars($row['usuario']); ?>">
                                        <?php echo htmlspecialchars($row['usuario']); ?>
                                    </td>
                                    <td class="text-secondary correo-cell" data-tooltip="<?php echo htmlspecialchars($row['correo'] ?? 'Sin correo'); ?>"><?php echo htmlspecialchars($row['correo'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $role_text = $row['nombre_rol'] ?? '';
                                        if ($row['id_rol'] == 1)      { $role_class = 'badge-rol-admin';  $role_icon = 'bi-shield-lock-fill'; }
                                        elseif ($row['id_rol'] == 2)  { $role_class = 'badge-rol-carga';  $role_icon = 'bi-truck'; }
                                        elseif ($row['id_rol'] == 3)  { $role_class = 'badge-rol-ventas'; $role_icon = 'bi-cart-check'; }
                                        else                          { $role_class = 'badge-rol-sinrol'; $role_icon = 'bi-person-slash'; $role_text = 'SIN ROL'; }
                                        ?>
                                        <span class="role-badge <?php echo $role_class; ?>" data-tooltip="<?php echo htmlspecialchars($role_text); ?>"><i class="bi <?php echo $role_icon; ?>"></i><?php echo htmlspecialchars($role_text); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-jv <?php echo $estado_css; ?>"><i class="bi <?php echo $estado_icon; ?> me-1"></i><?php echo $estado_texto; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['id_usuario'] == $id_propio): ?>
                                            <span class="badge-jv badge-secondary" style="font-size:.7rem;padding:6px 12px;"><i class="bi bi-lock-fill me-1"></i>CUENTA PRINCIPAL</span>
                                        <?php elseif ($es_pendiente): ?>
                                            <div class="acciones-usuario">
                                                <form method="POST" class="form-aprobar">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                                    <input type="hidden" name="aprobar_usuario" value="<?php echo (int)$row['id_usuario']; ?>">
                                                    <select name="id_rol" class="input-jv select-rol-aprobar" aria-label="Rol para <?php echo htmlspecialchars($row['usuario']); ?>" required>
                                                        <option value="">Asignar rol...</option>
                                                        <?php foreach ($roles_lista as $role): if (in_array((int)$role['id_rol'], [2, 3], true)): ?>
                                                                <option value="<?php echo (int)$role['id_rol']; ?>"><?php echo htmlspecialchars($role['nombre_rol']); ?></option>
                                                        <?php endif; endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn-accion btn-aprobar-mini" title="Aprobar acceso">
                                                        <i class="bi bi-person-check-fill"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="btn-accion btn-eliminar" data-tooltip="Rechazar y eliminar solicitud" onclick="confirmarEliminacion(<?php echo (int)$row['id_usuario']; ?>, <?php echo $nombre_json; ?>)">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="acciones-usuario">
                                                <button type="button" class="btn-accion btn-editar" data-tooltip="Editar colaborador" onclick="abrirEdicion(this)">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn-accion btn-reset" data-tooltip="Restablecer contraseña" onclick="abrirResetPassword(<?php echo (int)$row['id_usuario']; ?>, <?php echo $nombre_json; ?>)">
                                                    <i class="bi bi-key-fill"></i>
                                                </button>
                                                <?php if ($row['status'] == 'Activo'): ?>
                                                    <button type="button" class="btn-accion btn-suspender" data-tooltip="Suspender acceso" onclick="confirmarToggle(<?php echo (int)$row['id_usuario']; ?>, <?php echo $nombre_json; ?>, 'suspender')">
                                                        <i class="bi bi-person-lock"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-accion btn-reactivar" data-tooltip="Reactivar acceso" onclick="confirmarToggle(<?php echo (int)$row['id_usuario']; ?>, <?php echo $nombre_json; ?>, 'activar')">
                                                        <i class="bi bi-person-check-fill"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn-accion btn-eliminar" data-tooltip="Eliminar colaborador" onclick="confirmarEliminacion(<?php echo (int)$row['id_usuario']; ?>, <?php echo $nombre_json; ?>)">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="estado-vacio">
                                        <i class="bi bi-people-fill"></i>
                                        <span>No hay colaboradores registrados</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="paginacion-footer d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2">
                <span class="small text-secondary" id="infoPagina"> </span>
                <nav id="paginacionUsuarios" aria-label="Paginación de colaboradores"></nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form id="formEditar" novalidate>
                <div class="modal-header modal-header-jv">
                    <h5 class="modal-title fw-bold" id="modalEditarLabel"><i class="bi bi-pencil-square me-2"></i>EDITAR COLABORADOR</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="edit_id_usuario">
                    <input type="hidden" id="edit_status">
                    <div class="section-bg">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="section-label" for="edit_usuario"><i class="bi bi-person-fill"></i> NOMBRE DE USUARIO</label>
                                <input type="text" class="input-jv w-100" id="edit_usuario" maxlength="20" autocomplete="off" placeholder="Ej: Juan_Perez" required>
                                <div class="form-text jv-form-text" id="edit_usuario_guia">Mín 4 - Máx 20 caracteres (letras, números, guion bajo).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="section-label" for="edit_correo"><i class="bi bi-envelope-fill"></i> CORREO ELECTRÓNICO</label>
                                <input type="email" class="input-jv w-100" id="edit_correo" maxlength="100" autocomplete="off" placeholder="usuario@empresa.com" required>
                                <div class="form-text jv-form-text" id="edit_correo_guia">Formato válido de correo electrónico.</div>
                            </div>
                            <div class="col-12">
                                <label class="section-label" for="edit_rol"><i class="bi bi-shield-fill-check"></i> ROL DEL USUARIO</label>
                                <select class="input-jv w-100" id="edit_rol" required>
                                    <option value="">Seleccionar rol...</option>
                                    <?php foreach ($roles_lista as $role): ?>
                                        <option value="<?php echo (int)$role['id_rol']; ?>"><?php echo htmlspecialchars($role['nombre_rol']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text jv-form-text" id="edit_rol_guia">Controla los permisos del colaborador dentro del sistema.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-jv">
                    <button type="button" class="btn-jv-secondary" data-bs-dismiss="modal">CANCELAR</button>
                    <button type="submit" class="btn-jv-primary" id="btnEditar"><i class="bi bi-check-lg me-1"></i>GUARDAR CAMBIOS</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrear" tabindex="-1" aria-labelledby="modalCrearLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form id="formCrear" novalidate>
                <div class="modal-header modal-header-jv">
                    <h5 class="modal-title fw-bold" id="modalCrearLabel"><i class="bi bi-person-plus-fill me-2"></i>NUEVO COLABORADOR</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="section-bg">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="section-label" for="crear_usuario"><i class="bi bi-person-fill"></i> NOMBRE DE USUARIO</label>
                                <input type="text" class="input-jv w-100" id="crear_usuario" maxlength="20" autocomplete="off" placeholder="Ej: Juan_Perez" required>
                                <div class="form-text jv-form-text" id="crear_usuario_guia">Mín 4 - Máx 20 caracteres (letras, números, guion bajo).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="section-label" for="crear_correo"><i class="bi bi-envelope-fill"></i> CORREO ELECTRÓNICO</label>
                                <input type="email" class="input-jv w-100" id="crear_correo" maxlength="100" autocomplete="off" placeholder="usuario@empresa.com" required>
                                <div class="form-text jv-form-text" id="crear_correo_guia">Formato válido de correo electrónico.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="section-label" for="crear_password"><i class="bi bi-key-fill"></i> CONTRASEÑA TEMPORAL</label>
                                <div class="password-field">
                                    <input type="password" class="input-jv w-100" id="crear_password" autocomplete="new-password" placeholder="Contraseña temporal" required>
                                    <button type="button" class="password-toggle" data-toggle-password data-password-label="la contraseña temporal" aria-label="Mostrar la contraseña temporal" aria-controls="crear_password" aria-pressed="false">
                                        <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="form-text jv-form-text" id="crear_password_guia">Mín. 8 caracteres, 1 mayúscula, 1 minúscula, 1 número y 1 símbolo.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="section-label" for="crear_password_confirm"><i class="bi bi-key-fill"></i> CONFIRMAR CONTRASEÑA</label>
                                <div class="password-field">
                                    <input type="password" class="input-jv w-100" id="crear_password_confirm" autocomplete="new-password" placeholder="Repite la contraseña" required>
                                    <button type="button" class="password-toggle" data-toggle-password data-password-label="la confirmación de la contraseña" aria-label="Mostrar la confirmación de la contraseña" aria-controls="crear_password_confirm" aria-pressed="false">
                                        <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="form-text jv-form-text" id="crear_password_confirm_guia">Debe coincidir con la contraseña anterior.</div>
                            </div>
                            <div class="col-12">
                                <label class="section-label" for="crear_rol"><i class="bi bi-shield-fill-check"></i> ROL DEL USUARIO</label>
                                <select class="input-jv w-100" id="crear_rol" required>
                                    <option value="">Seleccionar rol...</option>
                                    <?php foreach ($roles_lista as $role): if (in_array((int)$role['id_rol'], [2, 3], true)): ?>
                                        <option value="<?php echo (int)$role['id_rol']; ?>"><?php echo htmlspecialchars($role['nombre_rol']); ?></option>
                                    <?php endif; endforeach; ?>
                                </select>
                                <div class="form-text jv-form-text" id="crear_rol_guia">Solo se pueden crear Operadores de Carga y de Ventas.</div>
                            </div>
                        </div>
                    </div>
                    <div class="section-bg">
                        <div class="section-label"><i class="bi bi-shield-lock-fill"></i> RECUPERACIÓN DE CUENTA</div>
                        <div class="form-text jv-form-text alert-lectura">La cuenta se recupera con el <strong>código que llega al correo</strong> del colaborador o con el <strong>PIN de emergencia compartido</strong>, que el Administrador define una sola vez en esta misma pantalla y cada usuario consulta en <em>Mi Perfil</em>. Confirma que el correo sea real y que el colaborador pueda abrirlo.</div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-jv">
                    <button type="button" class="btn-jv-secondary" data-bs-dismiss="modal">CANCELAR</button>
                    <button type="submit" class="btn-jv-primary" id="btnCrear"><i class="bi bi-person-check-fill me-1"></i>CREAR COLABORADOR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalResetPassword" tabindex="-1" aria-labelledby="modalResetLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-jv">
            <form id="formResetPassword" novalidate>
                <div class="modal-header modal-header-jv">
                    <h5 class="modal-title fw-bold" id="modalResetLabel"><i class="bi bi-key-fill me-2"></i>RESTABLECER CONTRASEÑA</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="reset_id_usuario">
                    <div class="alert-jv alert-jv-info alert-lectura mb-3">
                        <i class="bi bi-info-circle me-2"></i>Se asignará una nueva contraseña a <strong id="reset_usuario_nombre"></strong>. Comunícasela para que pueda entrar; luego puede cambiarla desde <em>Mi Perfil</em>.
                    </div>
                    <div class="section-bg">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="section-label" for="reset_password"><i class="bi bi-key-fill"></i> NUEVA CONTRASEÑA</label>
                                <div class="password-field">
                                    <input type="password" class="input-jv w-100" id="reset_password" autocomplete="new-password" placeholder="Nueva contraseña" required>
                                    <button type="button" class="password-toggle" data-toggle-password data-password-label="la nueva contraseña" aria-label="Mostrar la nueva contraseña" aria-controls="reset_password" aria-pressed="false">
                                        <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="form-text jv-form-text" id="reset_password_guia">Mín. 8 caracteres, 1 mayúscula, 1 minúscula, 1 número y 1 símbolo.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="section-label" for="reset_password_confirm"><i class="bi bi-key-fill"></i> CONFIRMAR CONTRASEÑA</label>
                                <div class="password-field">
                                    <input type="password" class="input-jv w-100" id="reset_password_confirm" autocomplete="new-password" placeholder="Repite la contraseña" required>
                                    <button type="button" class="password-toggle" data-toggle-password data-password-label="la confirmación de la contraseña" aria-label="Mostrar la confirmación de la contraseña" aria-controls="reset_password_confirm" aria-pressed="false">
                                        <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="form-text jv-form-text" id="reset_password_confirm_guia">Debe coincidir con la contraseña anterior.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-footer-jv">
                    <button type="button" class="btn-jv-secondary" data-bs-dismiss="modal">CANCELAR</button>
                    <button type="submit" class="btn-jv-primary" id="btnResetPassword"><i class="bi bi-key-fill me-1"></i>RESTABLECER</button>
                </div>
            </form>
        </div>
    </div>
</div>
