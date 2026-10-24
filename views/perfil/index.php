<?php if (!empty($flash)): ?>
    <div class="alert-jv alert-jv-<?php echo $flash['tipo']; ?> flash-auto mb-3">
        <?php echo htmlspecialchars($flash['texto']); ?>
    </div>
<?php endif; ?>

<div class="d-flex align-items-center gap-4 mb-4">
    <div class="perfil-header-icon"><i class="bi bi-person-gear"></i></div>
    <div>
        <h1 class="module-title">MI PERFIL</h1>
        <p class="module-subtitle">Gestiona tu información de acceso</p>
    </div>
</div>

<div class="profile-hero mb-4">
    <div class="profile-hero-bg" aria-hidden="true"></div>
    <div class="profile-avatar"><?php echo $inicial; ?></div>
    <div class="profile-hero-info">
        <h2 class="profile-name" title="<?php echo htmlspecialchars($usuario_data['usuario']); ?>"><?php echo htmlspecialchars($usuario_data['usuario']); ?></h2>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="profile-role"><i class="bi bi-shield-lock me-1"></i><?php echo htmlspecialchars($rol_perfil); ?></span>
            <span class="profile-role profile-role-status"><i class="bi bi-check-circle me-1"></i>ACTIVO</span>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card-jv p-4 perfil-form">
            <div class="header-card p-0 mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-person-gear" style="color:var(--jv-navy);"></i>
                <span class="fw-bold small text-secondary text-uppercase">Editar Datos de la Cuenta</span>
            </div>
            <form method="POST" class="row g-3" id="formPerfil">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="accion" value="actualizar_perfil">

                <section class="col-12 profile-section-card" aria-labelledby="profile-account-title">
                    <h2 class="profile-section-title" id="profile-account-title"><i class="bi bi-person-vcard"></i>Datos de la cuenta</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-jv" for="perfil_usuario">USUARIO</label>
                            <input type="text" id="perfil_usuario" name="usuario" class="input-jv" required maxlength="20" value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" autocomplete="username">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-jv" for="perfil_correo">CORREO ELECTRÓNICO</label>
                            <input type="email" id="perfil_correo" name="correo" class="input-jv" required maxlength="100" value="<?php echo htmlspecialchars($usuario_data['correo'] ?? ''); ?>" autocomplete="email">
                        </div>
                    </div>
                </section>
                <section class="col-12 profile-section-card" aria-labelledby="profile-role-title">
                    <h2 class="profile-section-title" id="profile-role-title"><i class="bi bi-shield-lock"></i>Acceso al sistema</h2>
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-shield-lock"></i></div>
                        <div class="info-text">
                            <div class="info-label">ROL DE ACCESO</div>
                            <div class="info-value"><?php echo htmlspecialchars($rol_perfil); ?></div>
                        </div>
                    </div>
                </section>

                <section class="col-12 profile-section-card" aria-labelledby="profile-password-title">
                    <h2 class="profile-section-title" id="profile-password-title"><i class="bi bi-key"></i>Cambiar contraseña <span>(opcional; deja ambos campos en blanco para conservarla)</span></h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-jv" for="perfil_password_actual">CONTRASEÑA ACTUAL</label>
                            <div class="password-field">
                                <input type="password" id="perfil_password_actual" name="password_actual" class="input-jv" autocomplete="current-password" placeholder="Solo si vas a cambiar la contraseña">
                                <button type="button" class="password-toggle" data-toggle-password data-password-label="la contraseña actual" aria-label="Mostrar contraseña actual" aria-controls="perfil_password_actual" aria-pressed="false">
                                    <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-jv" for="perfil_password_nueva">NUEVA CONTRASEÑA</label>
                            <div class="password-field">
                                <input type="password" id="perfil_password_nueva" name="password_nueva" class="input-jv" maxlength="36" autocomplete="new-password" placeholder="8 a 18 caracteres">
                                <button type="button" class="password-toggle" data-toggle-password data-password-label="la nueva contraseña" aria-label="Mostrar nueva contraseña" aria-controls="perfil_password_nueva" aria-pressed="false">
                                    <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-jv" for="perfil_password_confirm">CONFIRMAR NUEVA</label>
                            <div class="password-field">
                                <input type="password" id="perfil_password_confirm" name="password_confirm" class="input-jv" maxlength="36" autocomplete="new-password" placeholder="8 a 18 caracteres">
                                <button type="button" class="password-toggle" data-toggle-password data-password-label="la confirmación de contraseña" aria-label="Mostrar confirmación de contraseña" aria-controls="perfil_password_confirm" aria-pressed="false">
                                    <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="col-12 profile-section-card" aria-labelledby="profile-pin-title">
                    <h2 class="profile-section-title" id="profile-pin-title"><i class="bi bi-shield-exclamation"></i>PIN de emergencia</h2>
                    <?php if ((int)($usuario_data['id_rol'] ?? 0) === 1): ?>
                        <p class="small text-jv-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i>La cuenta del Administrador no usa PIN: se recupera por el correo electrónico registrado. Asegúrate de que ese correo sea real y que puedas abrirlo.
                        </p>
                    <?php elseif ($pin_global !== ''): ?>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label-jv" for="perfil_pin">TU PIN DE EMERGENCIA</label>
                                <input type="text" id="perfil_pin" class="input-jv" value="<?php echo htmlspecialchars($pin_global); ?>" readonly aria-readonly="true">
                            </div>
                            <div class="col-12">
                                <p class="small text-jv-muted mb-0">
                                    <i class="bi bi-envelope-paper me-1"></i><strong>Escríbelo y guárdalo en un lugar seguro</strong> (cartera, gaveta): es tu respaldo si no tienes internet o no te llega el correo. Lo define el Administrador para todos los operadores.
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="small text-jv-muted mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>El Administrador todavía no ha definido el PIN de emergencia. Si olvidas tu contraseña y no tienes internet, pídele que lo configure.
                        </p>
                    <?php endif; ?>
                </section>

                <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="btn btn-jv-primary px-5 py-3 fw-bolder text-uppercase">
                        <i class="bi bi-check2-circle me-2"></i>GUARDAR CAMBIOS
                    </button>
                    <button type="reset" class="btn btn-outline-secondary px-4 py-3 fw-bolder text-uppercase">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>RESTABLECER
                    </button>
                </div>
            </form>
            <p class="small text-jv-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>La contraseña debe tener mínimo 8 caracteres, incluir mayúsculas, números y símbolos.</p>
        </div>
    </div>
</div>
