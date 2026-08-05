
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

function validarReg() {
    var u = document.getElementById('r-user').value.trim();
    var p = document.getElementById('r-pass').value;
    var p2 = document.getElementById('r-pass2').value;
    var preg = document.getElementById('r-preg').value;
    var resp = document.getElementById('r-resp').value.trim();
    var btn = document.getElementById('btn-reg');
    var uHint = document.getElementById('r-user-hint');
    var pHint = document.getElementById('r-pass-hint');
    var meter = document.getElementById('r-meter');

    var resp = document.getElementById('r-resp').value.trim();
    var respOk = false;
    if (resp.length >= 5 && resp.length <= 20 && /[a-zA-Z]/.test(resp) && /[aeiouAEIOU]/.test(resp) && !/(.)\1{3,}/.test(resp) && !/abcdef|bcdefg|cdefgh|defghi|efghij|fghijk|ghijkl|hijklm|ijklmn/i.test(resp) && !/asdf|qwerty|zxcv|abcd|1234/i.test(resp)) {
        respOk = true;
        document.getElementById('r-resp').style.borderColor = 'var(--jv-success)';
    } else if (resp.length > 0) {
        document.getElementById('r-resp').style.borderColor = 'var(--jv-danger)';
    } else {
        document.getElementById('r-resp').style.borderColor = '';
    }

    var uOk = u.length >= 4 && /^[a-zA-Z0-9_]+$/.test(u);

    if (u.length > 0) {
        uHint.style.color = uOk ? 'var(--jv-success)' : 'var(--jv-danger)';
    } else {
        uHint.style.color = 'var(--jv-text-muted)';
    }

    if (p.length > 0) {
        var s = 0;
        if (p.length >= 8) s++;
        if (/[a-z]/.test(p)) s++;
        if (/[A-Z]/.test(p)) s++;
        if (/[0-9]/.test(p)) s++;
        if (/[\W_]/.test(p)) s++;
        var cols = ['var(--jv-danger)', 'var(--jv-danger)', 'var(--jv-warning)', 'var(--jv-info)', 'var(--jv-success)'];
        var wids = ['20%', '40%', '60%', '80%', '100%'];
        var idx = Math.max(0, Math.min(s - 1, 4));
        meter.style.width = wids[idx];
        meter.style.backgroundColor = cols[idx];
        if (s < 3) { pHint.textContent = 'Contraseña debil'; pHint.style.color = 'var(--jv-danger)'; }
        else if (s < 4) { pHint.textContent = 'Contraseña aceptable'; pHint.style.color = 'var(--jv-warning)'; }
        else if (s < 5) { pHint.textContent = 'Contraseña buena'; pHint.style.color = 'var(--jv-info)'; }
        else { pHint.textContent = 'Contraseña fuerte'; pHint.style.color = 'var(--jv-success)'; }
    } else {
        meter.style.width = '0%';
        pHint.textContent = 'Min. 8 caracteres con letras, numeros y simbolos.';
        pHint.style.color = 'var(--jv-text-muted)';
    }

    var pOk = p.length >= 8;
    var pMatch = p.length > 0 && p === p2;
    var matchHint = document.getElementById('r-match-hint');
    if (p2.length > 0) {
        matchHint.className = 'reg-match bi ' + (pMatch ? 'bi-check-circle-fill text-jv-success' : 'bi-x-circle-fill text-jv-danger');
    } else {
        matchHint.className = 'reg-match';
        matchHint.textContent = '';
    }
    btn.disabled = !(uOk && (s >= 3) && pMatch && preg !== '' && respOk);
}

document.getElementById('r-preg').addEventListener('change', function() {
    validarReg();
});


// Contador regresivo de bloqueo con barra de progreso
var segRestantes = window.JV_CONFIG.c0;
var totalSegundos = segRestantes;
if (segRestantes > 0) {
    var elAlerta = document.getElementById('alerta-bloqueo');
    var timerEl = document.getElementById('alertTimer');
    var progressFill = document.getElementById('alertProgressFill');
    var timerBloqueo = setInterval(function() {
        segRestantes--;
        if (timerEl) {
            timerEl.innerHTML = segRestantes + ' <small>seg</small>';
        }
        if (progressFill && totalSegundos > 0) {
            var pct = (segRestantes / totalSegundos) * 100;
            progressFill.style.width = pct + '%';
        }
        if (segRestantes <= 0) {
            clearInterval(timerBloqueo);
            document.getElementById('f-user').disabled = false;
            document.getElementById('f-pass').disabled = false;
            document.getElementById('btn-login').disabled = false;
            document.getElementById('f-user').focus();
            if (elAlerta) {
                elAlerta.className = 'alert-card-jv alert-card-success flash-auto';
                elAlerta.innerHTML = '<div class="alert-icon-box"><i class="bi bi-unlock-fill"></i></div><div class="alert-body"><div class="alert-title">BLOQUEO TERMINADO</div><div class="alert-text">YA PUEDES INTENTAR DE NUEVO.</div></div>';
                setTimeout(function() {
                    elAlerta.style.transition = 'opacity .5s';
                    elAlerta.style.opacity = '0';
                    setTimeout(function() { elAlerta.remove(); }, 500);
                }, 4000);
            }
        }
    }, 1000);
}


document.querySelectorAll('.flash-auto').forEach(el => {
    setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
});

