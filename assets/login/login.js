
// ==========================================
// EYE TOGGLE — LOGIN + REGISTRO
// ==========================================
function setupEye(btnId, iconId, inputId) {
    var btn = document.getElementById(btnId);
    var icon = document.getElementById(iconId);
    var input = document.getElementById(inputId);
    if (!btn || !icon || !input) return;
    btn.addEventListener('click', function() {
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash-fill';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye-fill';
        }
    });
}
setupEye('btnEyePass', 'iconEyePass', 'f-pass');
setupEye('btnEyeR1', 'iconEyeR1', 'r-pass');
setupEye('btnEyeR2', 'iconEyeR2', 'r-pass2');

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
    var pregunta  = document.getElementById('r-preg').value;
    var respuesta = document.getElementById('r-resp').value.trim();
    var btn       = document.getElementById('btn-reg');

    // --- USUARIO ---
    var userDefault = 'Mínimo 4 caracteres, solo letras, números y guiones bajos.';
    if (username.length === 0) {
        setFieldState('r-user', 'r-user-status', 'r-user-hint', 'neutral', userDefault);
    } else if (username.length < 4) {
        setFieldState('r-user', 'r-user-status', 'r-user-hint', 'invalid', 'Faltan ' + (4 - username.length) + ' caracteres (mínimo 4).');
    } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        setFieldState('r-user', 'r-user-status', 'r-user-hint', 'invalid', 'Solo letras, números y guiones bajos.');
    } else {
        setFieldState('r-user', 'r-user-status', 'r-user-hint', 'valid', 'Nombre de usuario válido.');
    }
    var usernameIsValid = username.length >= 4 && /^[a-zA-Z0-9_]+$/.test(username);

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
    var passDefault = 'Mín. 8 caracteres, 1 mayúscula, 1 minúscula, 1 número, 1 símbolo.';
    var strength = 0;
    if (password.length > 0) {
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[\W_]/.test(password)) strength++;
        if (password.length >= 12) strength++;

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
    var passwordIsValid = password.length >= 8;

    // --- CONFIRMAR CONTRASEÑA ---
    var matchDefault = 'Debe coincidir con la contraseña anterior.';
    if (pass2.length === 0) {
        setFieldState('r-pass2', 'r-match-status', 'r-match-hint', 'neutral', matchDefault);
    } else if (password !== pass2) {
        setFieldState('r-pass2', 'r-match-status', 'r-match-hint', 'invalid', 'Las contraseñas no coinciden.');
    } else {
        setFieldState('r-pass2', 'r-match-status', 'r-match-hint', 'valid', 'Las contraseñas coinciden.');
    }
    var passwordsMatch = password.length > 0 && password === pass2;

    // --- PREGUNTA ---
    var preguntaIsValid = pregunta !== '';

    // --- RESPUESTA ---
    var respDefault = 'Escribe una respuesta que recuerdes fácilmente.';
    if (respuesta.length === 0) {
        setFieldState('r-resp', 'r-resp-status', 'r-resp-hint', 'neutral', respDefault);
    } else if (respuesta.length < 1) {
        setFieldState('r-resp', 'r-resp-status', 'r-resp-hint', 'invalid', 'Escribe al menos 1 carácter.');
    } else {
        setFieldState('r-resp', 'r-resp-status', 'r-resp-hint', 'valid', 'Respuesta válida.');
    }
    var answerIsValid = respuesta.length >= 1;

    // --- BOTÓN HABILITAR ---
    btn.disabled = !(usernameIsValid && emailIsValid && passwordIsValid && passwordsMatch && preguntaIsValid && answerIsValid);
}

// Listener para select de pregunta
document.getElementById('r-preg').addEventListener('change', function() { validarReg(); });

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
