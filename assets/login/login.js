
// ==========================================
// EYE TOGGLE — LOGIN + REGISTRO
// ==========================================
function setupEye(btnId, iconId, inputId) {
    var btn = document.getElementById(btnId);
    var icon = document.getElementById(iconId);
    var input = document.getElementById(inputId);
    if (!btn || !icon || !input) return;
    btn.addEventListener('click', function() {
        // Ojo abierto = contraseña visible; ojo tachado = oculta.
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-fill';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye-slash-fill';
        }
    });
}
setupEye('btnEyePass', 'iconEyePass', 'f-pass');
setupEye('btnEyeR1', 'iconEyeR1', 'r-pass');
setupEye('btnEyeR2', 'iconEyeR2', 'r-pass2');

function limitarPasswordNueva(input) {
    var limitada = Array.from(input.value).slice(0, 18).join('');
    if (limitada !== input.value) {
        input.value = limitada;
        input.setSelectionRange(limitada.length, limitada.length);
    }
}

// ==========================================
// NORMALIZACIÓN — USERNAME
// ==========================================
var rUser = document.getElementById('r-user');
if (rUser) {
    rUser.addEventListener('input', function() {
        var pos = this.selectionStart;
        var raw = this.value;
        var normalized = raw.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
        if (raw !== normalized) {
            this.value = normalized;
            this.setSelectionRange(normalized.length, normalized.length);
        }
        validarReg();
    });
}

// ==========================================
// VALIDACIÓN EN TIEMPO REAL — REGISTRO
// ==========================================
function setFieldState(inputId, statusId, hintId, state, message) {
    var input = document.getElementById(inputId);
    var status = document.getElementById(statusId);
    var hint = document.getElementById(hintId);
    if (!input) return;

    input.classList.remove('is-valid', 'is-invalid');
    if (status) {
        status.classList.remove('status-ok', 'status-err');
        status.textContent = '';
    }
    if (hint) {
        hint.classList.remove('hint-ok', 'hint-err');
    }

    if (state === 'valid') {
        input.classList.add('is-valid');
        if (status) { status.classList.add('status-ok'); status.textContent = '\u2713'; }
        if (hint && message) { hint.textContent = message; hint.classList.add('hint-ok'); }
    } else if (state === 'invalid') {
        input.classList.add('is-invalid');
        if (status) { status.classList.add('status-err'); status.textContent = '\u2717'; }
        if (hint && message) { hint.textContent = message; hint.classList.add('hint-err'); }
    } else {
        if (hint && message) { hint.textContent = message; }
    }
}

function validarReg() {
    var username  = document.getElementById('r-user').value.trim();
    var email     = document.getElementById('r-email').value.trim();
    var password  = document.getElementById('r-pass').value;
    var pass2     = document.getElementById('r-pass2').value;
    var btn       = document.getElementById('btn-reg');

    // --- USUARIO ---
    var userDefault = 'Empieza con una letra; luego usa letras, números y guion bajo (4 a 20 caracteres).';
    if (username.length === 0) {
        setFieldState('r-user', 'r-user-status', 'r-user-hint', 'neutral', userDefault);
    } else if (username.length < 4) {
        setFieldState('r-user', 'r-user-status', 'r-user-hint', 'invalid', 'Faltan ' + (4 - username.length) + ' caracteres (mínimo 4).');
    } else if (!/^[a-zA-Z][a-zA-Z0-9_]{3,19}$/.test(username)) {
        setFieldState('r-user', 'r-user-status', 'r-user-hint', 'invalid', 'Debe comenzar con una letra; después usa letras, números o guion bajo.');
    } else {
        setFieldState('r-user', 'r-user-status', 'r-user-hint', 'valid', 'Nombre de usuario válido.');
    }
    var usernameIsValid = /^[a-zA-Z][a-zA-Z0-9_]{3,19}$/.test(username);

    // --- CORREO ---
    var emailDefault = 'Formato válido de correo electrónico.';
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (email.length === 0) {
        setFieldState('r-email', 'r-email-status', 'r-email-hint', 'neutral', emailDefault);
    } else if (!emailRegex.test(email)) {
        setFieldState('r-email', 'r-email-status', 'r-email-hint', 'invalid', 'El correo no tiene un formato válido.');
    } else {
        setFieldState('r-email', 'r-email-status', 'r-email-hint', 'valid', 'Correo electrónico válido.');
    }
    var emailIsValid = emailRegex.test(email);

    // --- CONTRASEÑA — STRENGTH METER ---
    var meter = document.getElementById('r-meter');
    var passHint = document.getElementById('r-pass-hint');
    var passDefault = 'De 8 a 18 caracteres, con mayúscula, minúscula, número y símbolo.';
    var passwordLength = Array.from(password).filter(function(character) {
        return !/\s/.test(character);
    }).length;
    var passwordHasWhitespace = /\s/.test(password);
    var passwordInput = document.getElementById('r-pass');
    var strength = 0;
    if (password.length > 0) {
        if (passwordLength >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^\w\s]|_/.test(password)) strength++;
        if (passwordLength >= 12) strength++;

        var colors = ['#dc2626', '#f97316', '#eab308', '#22c55e', '#16a34a', '#16a34a'];
        var widths = ['12%', '30%', '50%', '75%', '100%', '100%'];
        var labels = ['Muy débil', 'Débil', 'Media', 'Fuerte', 'Muy fuerte', 'Muy fuerte'];
        var idx = Math.max(0, Math.min(strength, 5));

        meter.style.width = widths[idx];
        meter.style.backgroundColor = colors[idx];
        passHint.textContent = labels[idx];
        passHint.style.color = colors[idx];
        passHint.className = 'reg-hint';
    } else {
        meter.style.width = '0%';
        passHint.textContent = passDefault;
        passHint.style.color = '';
        passHint.className = 'reg-hint';
    }
    if (passwordHasWhitespace) {
        meter.style.backgroundColor = '#dc2626';
        passHint.textContent = 'No se permiten espacios en la contraseña.';
        passHint.style.color = '#dc2626';
    }
    if (passwordInput) passwordInput.classList.toggle('is-invalid', passwordHasWhitespace);
    var passwordIsValid = !passwordHasWhitespace && passwordLength >= 8 && passwordLength <= 18
        && /[a-z]/.test(password) && /[A-Z]/.test(password)
        && /\d/.test(password) && (/[^\w\s]|_/.test(password));

    // --- CONFIRMAR CONTRASEÑA ---
    var matchDefault = 'Debe coincidir con la contraseña anterior.';
    if (pass2.length === 0) {
        setFieldState('r-pass2', 'r-match-status', 'r-match-hint', 'neutral', matchDefault);
    } else if (/\s/.test(pass2)) {
        setFieldState('r-pass2', 'r-match-status', 'r-match-hint', 'invalid', 'No se permiten espacios en la contraseña.');
    } else if (password !== pass2) {
        setFieldState('r-pass2', 'r-match-status', 'r-match-hint', 'invalid', 'Las contraseñas no coinciden.');
    } else {
        setFieldState('r-pass2', 'r-match-status', 'r-match-hint', 'valid', 'Las contraseñas coinciden.');
    }
    var passwordsMatch = password.length > 0 && password === pass2;

    // --- BOTÓN HABILITAR ---
    btn.disabled = !(usernameIsValid && emailIsValid && passwordIsValid && passwordsMatch);
}

// ==========================================
// VALIDACIÓN CONTINUA — CAMPOS DEL REGISTRO
// ==========================================
// Validar también al escribir en correo y contraseñas. Antes solo el usuario
// disparaba validarReg(), así que los demás campos se quedaban sin revisar.
['r-email', 'r-pass', 'r-pass2'].forEach(function (id) {
    var campo = document.getElementById(id);
    if (campo) campo.addEventListener('input', function() {
        if (id === 'r-pass' || id === 'r-pass2') limitarPasswordNueva(campo);
        validarReg();
    });
});

// ==========================================
// CONTADOR REGRESIVO DE BLOQUEO
// ==========================================
var remainingSeconds = window.JV_CONFIG.remainingLockoutSeconds;
var totalBlockedSeconds = remainingSeconds;
if (remainingSeconds > 0) {
    var alertElement = document.getElementById('alerta-bloqueo');
    var alertTimerElement = document.getElementById('alertTimer');
    var progressFillElement = document.getElementById('alertProgressFill');
    var lockoutTimer = setInterval(function() {
        remainingSeconds--;
        if (alertTimerElement) {
            alertTimerElement.innerHTML = remainingSeconds + ' <small>seg</small>';
        }
        if (progressFillElement && totalBlockedSeconds > 0) {
            var progressPercentage = (remainingSeconds / totalBlockedSeconds) * 100;
            progressFillElement.style.width = progressPercentage + '%';
        }
        if (remainingSeconds <= 0) {
            clearInterval(lockoutTimer);
            document.getElementById('f-user').disabled = false;
            document.getElementById('f-pass').disabled = false;
            document.getElementById('btn-login').disabled = false;
            document.getElementById('f-user').focus();
            if (alertElement) {
                alertElement.className = 'alert-card-jv alert-card-success flash-auto';
                alertElement.innerHTML = '<div class="alert-icon-box"><i class="bi bi-unlock-fill"></i></div><div class="alert-body"><div class="alert-title">BLOQUEO TERMINADO</div><div class="alert-text">YA PUEDES INTENTAR DE NUEVO.</div></div>';
                setTimeout(function() {
                    alertElement.style.transition = 'opacity .5s';
                    alertElement.style.opacity = '0';
                    setTimeout(function() { alertElement.remove(); }, 500);
                }, 4000);
            }
        }
    }, 1000);
}

// Auto-dismiss flash alerts
document.querySelectorAll('.flash-auto').forEach(function(el) {
    setTimeout(function() { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }, 4000);
});
