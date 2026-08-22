
function setupEye(btnId, iconId, inputId) {
    var buttonElement = document.getElementById(btnId);
    var iconElement = document.getElementById(iconId);
    var inputElement = document.getElementById(inputId);
    if (!buttonElement || !iconElement || !inputElement) return;
    buttonElement.addEventListener('click', function() {
        if (inputElement.type === 'password') {
            inputElement.type = 'text';
            iconElement.className = 'bi bi-eye-slash-fill';
        } else {
            inputElement.type = 'password';
            iconElement.className = 'bi bi-eye-fill';
        }
    });
}

setupEye('btnEyePass', 'iconEyePass', 'f-pass');
setupEye('btnEyeR1', 'iconEyeR1', 'r-pass');
setupEye('btnEyeR2', 'iconEyeR2', 'r-pass2');

function validarReg() {
    var username = document.getElementById('r-user').value.trim();
    var password = document.getElementById('r-pass').value;
    var passwordConfirmation = document.getElementById('r-pass2').value;
    var securityQuestion = document.getElementById('r-preg').value;
    var securityAnswer = document.getElementById('r-resp').value.trim();
    var registerButton = document.getElementById('btn-reg');
    var usernameHint = document.getElementById('r-user-hint');
    var passwordHint = document.getElementById('r-pass-hint');
    var passwordMeter = document.getElementById('r-meter');

    var answerIsValid = securityAnswer.length >= 1;
    document.getElementById('r-resp').style.borderColor = securityAnswer.length > 0 ? 'var(--jv-success)' : '';

    var usernameIsValid = username.length >= 4 && /^[a-zA-Z0-9_]+$/.test(username);

    if (username.length > 0) {
        usernameHint.style.color = usernameIsValid ? 'var(--jv-success)' : 'var(--jv-danger)';
    } else {
        usernameHint.style.color = 'var(--jv-text-muted)';
    }

    var passwordStrength = 0;
    if (password.length > 0) {
        if (password.length >= 8) passwordStrength++;
        if (/[a-z]/.test(password)) passwordStrength++;
        if (/[A-Z]/.test(password)) passwordStrength++;
        if (/[0-9]/.test(password)) passwordStrength++;
        if (/[\W_]/.test(password)) passwordStrength++;
        var strengthColors = ['var(--jv-danger)', 'var(--jv-danger)', 'var(--jv-warning)', 'var(--jv-info)', 'var(--jv-success)'];
        var strengthWidths = ['20%', '40%', '60%', '80%', '100%'];
        var strengthIndex = Math.max(0, Math.min(passwordStrength - 1, 4));
        passwordMeter.style.width = strengthWidths[strengthIndex];
        passwordMeter.style.backgroundColor = strengthColors[strengthIndex];
        if (passwordStrength < 3) { passwordHint.textContent = 'Contraseña debil'; passwordHint.style.color = 'var(--jv-danger)'; }
        else if (passwordStrength < 4) { passwordHint.textContent = 'Contraseña aceptable'; passwordHint.style.color = 'var(--jv-warning)'; }
        else if (passwordStrength < 5) { passwordHint.textContent = 'Contraseña buena'; passwordHint.style.color = 'var(--jv-info)'; }
        else { passwordHint.textContent = 'Contraseña fuerte'; passwordHint.style.color = 'var(--jv-success)'; }
    } else {
        passwordMeter.style.width = '0%';
        passwordHint.textContent = 'Min. 8 caracteres con letras, numeros y simbolos.';
        passwordHint.style.color = 'var(--jv-text-muted)';
    }

    var passwordIsValid = password.length >= 8;
    var passwordsMatch = password.length > 0 && password === passwordConfirmation;
    var matchHint = document.getElementById('r-match-hint');
    if (passwordConfirmation.length > 0) {
        matchHint.className = 'reg-match bi ' + (passwordsMatch ? 'bi-check-circle-fill text-jv-success' : 'bi-x-circle-fill text-jv-danger');
    } else {
        matchHint.className = 'reg-match';
        matchHint.textContent = '';
    }
    registerButton.disabled = !(usernameIsValid && (passwordStrength >= 3) && passwordsMatch && securityQuestion !== '' && answerIsValid);
}

document.getElementById('r-preg').addEventListener('change', function() {
    validarReg();
});


// Contador regresivo de bloqueo con barra de progreso
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


document.querySelectorAll('.flash-auto').forEach(el => {
    setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
});

