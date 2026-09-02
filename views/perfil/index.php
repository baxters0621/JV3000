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

                <div class="col-md-6">
                    <label class="form-label-jv" for="perfil_usuario">USUARIO</label>
                    <input type="text" id="perfil_usuario" name="usuario" class="input-jv" required maxlength="20" value="<?php echo htmlspecialchars($usuario_data['usuario']); ?>" autocomplete="username">
                </div>
                <div class="col-md-6">
                    <label class="form-label-jv" for="perfil_correo">CORREO ELECTRÓNICO</label>
                    <input type="email" id="perfil_correo" name="correo" class="input-jv" required maxlength="100" value="<?php echo htmlspecialchars($usuario_data['correo'] ?? ''); ?>" autocomplete="email">
                </div>
                <div class="col-12">
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-shield-lock"></i></div>
                        <div class="info-text">
                            <div class="info-label">ROL DE ACCESO</div>
                            <div class="info-value"><?php echo htmlspecialchars($rol_perfil); ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="row g-3 align-items-center">
                        <div class="col-12">
                            <hr style="border-color:rgba(30,58,138,0.15);">
                            <span class="fw-bold small text-secondary text-uppercase"><i class="bi bi-key me-1"></i>Cambiar Contraseña <span class="text-jv-muted">(opcional, deja en blanco para conservarla)</span></span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-jv" for="perfil_password_actual">CONTRASEÑA ACTUAL *</label>
                            <div style="position:relative;">
                                <input type="password" id="perfil_password_actual" name="password_actual" class="input-jv" required autocomplete="current-password" placeholder="Necesaria para guardar cambios" style="padding-right:40px;">
                                <button type="button" onclick="togglePass('perfil_password_actual', this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#64748b;font-size:1.1rem;"><i class="bi bi-eye-slash-fill"></i></button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-jv" for="perfil_password_nueva">NUEVA CONTRASEÑA</label>
                            <div style="position:relative;">
                                <input type="password" id="perfil_password_nueva" name="password_nueva" class="input-jv" autocomplete="new-password" placeholder="••••••••" style="padding-right:40px;">
                                <button type="button" onclick="togglePass('perfil_password_nueva', this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#64748b;font-size:1.1rem;"><i class="bi bi-eye-slash-fill"></i></button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-jv" for="perfil_password_confirm">CONFIRMAR NUEVA</label>
                            <div style="position:relative;">
                                <input type="password" id="perfil_password_confirm" name="password_confirm" class="input-jv" autocomplete="new-password" placeholder="••••••••" style="padding-right:40px;">
                                <button type="button" onclick="togglePass('perfil_password_confirm', this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#64748b;font-size:1.1rem;"><i class="bi bi-eye-slash-fill"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="row g-3 align-items-center">
                        <div class="col-12">
                            <hr style="border-color:rgba(30,58,138,0.15);">
                            <span class="fw-bold small text-secondary text-uppercase"><i class="bi bi-shield-question me-1"></i>Pregunta de Seguridad <span class="text-jv-muted">(para recuperar tu contraseña)</span></span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-jv" for="perfil_pregunta">SELECCIONA UNA PREGUNTA</label>
                            <select id="perfil_pregunta" name="pregunta_seguridad" class="input-jv" required>
                                <option value="">Seleccione una pregunta...</option>
                                <?php foreach ($preguntas_opciones as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo ($p === ($usuario_data['pregunta_seguridad'] ?? '')) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-jv" for="perfil_respuesta">TU RESPUESTA</label>
                            <input type="text" id="perfil_respuesta" name="respuesta_seguridad" class="input-jv" required maxlength="255" autocomplete="off" placeholder="Escribe tu respuesta">
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="row g-3 align-items-center">
                        <div class="col-12">
                            <hr style="border-color:rgba(30,58,138,0.15);">
                            <span class="fw-bold small text-secondary text-uppercase"><i class="bi bi-shield-exclamation me-1"></i>PIN de Emergencia <span class="text-jv-muted">(6 dígitos, para recuperar tu contraseña si olvidas la respuesta)</span></span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-jv" for="perfil_pin">PIN DE EMERGENCIA</label>
                            <div style="position:relative;">
                                <input type="password" id="perfil_pin" name="pin_emergencia" class="input-jv" inputmode="numeric" autocomplete="off" maxlength="6" placeholder="<?php echo !empty($usuario_data['pin_emergencia']) ? '•••••• (configurado)' : '6 dígitos'; ?>" style="padding-right:40px;">
                                <button type="button" onclick="togglePass('perfil_pin', this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#64748b;font-size:1.1rem;"><i class="bi bi-eye-slash-fill"></i></button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-jv" for="perfil_pin_confirm">CONFIRMAR PIN</label>
                            <div style="position:relative;">
                                <input type="password" id="perfil_pin_confirm" name="pin_emergencia_confirm" class="input-jv" inputmode="numeric" autocomplete="off" maxlength="6" placeholder="Repite el PIN" style="padding-right:40px;">
                                <button type="button" onclick="togglePass('perfil_pin_confirm', this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#64748b;font-size:1.1rem;"><i class="bi bi-eye-slash-fill"></i></button>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end pb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="perfil_pin_eliminar" name="pin_emergencia_eliminar" value="1">
                                <label class="form-check-label small text-secondary" for="perfil_pin_eliminar">Eliminar PIN</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
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
