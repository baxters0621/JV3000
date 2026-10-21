
        // Validar código de recuperación: exactamente 6 dígitos numéricos
        function validarCodigoRec() {
            var codigoInput = document.getElementById('rec-codigo');
            if (!codigoInput) return;
            var submitBtn = document.getElementById('rec-btn-codigo');
            var hint = document.getElementById('rec-codigo-hint');
            if (codigoInput.value.length === 0) {
                submitBtn.disabled = true;
                hint.textContent = '';
                codigoInput.style.borderColor = '';
                return;
            }
            var esValido = /^[0-9]{6}$/.test(codigoInput.value);
            submitBtn.disabled = !esValido;
            codigoInput.style.borderColor = esValido ? '#16A34A' : '#ef4444';
            hint.textContent = esValido ? '' : 'El código debe tener exactamente 6 dígitos numéricos.';
            hint.style.color = '#ef4444';
        }

        // Cuenta regresiva antes de poder pedir un reenvío del código.
        // El servidor entrega los segundos restantes en #rec-reenvio-espera.
        function iniciarConteoReenvio() {
            var submitBtn = document.getElementById('rec-btn-reenviar');
            var label = document.getElementById('rec-reenviar-texto');
            var campo = document.getElementById('rec-reenvio-espera');
            if (!submitBtn || !label || !campo) return;
            var segundos = parseInt(campo.value, 10) || 0;
            if (segundos <= 0) return;
            submitBtn.disabled = true;
            label.textContent = 'REENVIAR EN ' + segundos + ' S';
            var temporizador = setInterval(function() {
                segundos--;
                if (segundos <= 0) {
                    clearInterval(temporizador);
                    submitBtn.disabled = false;
                    label.textContent = 'REENVIAR CÓDIGO';
                    return;
                }
                label.textContent = 'REENVIAR EN ' + segundos + ' S';
            }, 1000);
        }
        iniciarConteoReenvio();

        // Validar PIN de emergencia: exactamente 6 dígitos numéricos
        function validarPin() {
            var pinInput = document.getElementById('rec-pin');
            if (!pinInput) return;
            var pin = pinInput.value;
            var submitBtn = document.getElementById('rec-btn-pin');
            var hint = document.getElementById('rec-pin-hint');
            if (pin.length === 0) {
                submitBtn.disabled = true;
                hint.textContent = '';
                pinInput.style.borderColor = '';
                return;
            }
            var esValido = /^[0-9]{6}$/.test(pin);
            submitBtn.disabled = !esValido;
            pinInput.style.borderColor = esValido ? '#16A34A' : '#ef4444';
            hint.textContent = esValido ? '' : 'El PIN debe tener exactamente 6 dígitos numéricos.';
            hint.style.color = '#ef4444';
        }

        function validarPassRec() {
            var password = document.getElementById('rec-pass').value;
            var passwordConfirmation = document.getElementById('rec-pass2').value;
            var changePasswordButton = document.getElementById('rec-btn-pass');
            var passwordMeter = document.getElementById('rec-meter');
            var passwordHint = document.getElementById('rec-pass-hint');

            var passwordStrength = 0;
            if (password.length >= 8) passwordStrength++;
            if (/[a-z]/.test(password)) passwordStrength++;
            if (/[A-Z]/.test(password)) passwordStrength++;
            if (/[0-9]/.test(password)) passwordStrength++;
            if (/[\W_]/.test(password)) passwordStrength++;
            if (password.length >= 12) passwordStrength++;

            var strengthColors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a', '#16a34a'];
            var strengthWidths = ['15%', '35%', '55%', '80%', '100%', '100%'];
            var strengthTexts = ['Muy débil', 'Débil', 'Media', 'Fuerte', 'Muy fuerte', 'Muy fuerte'];
            var strengthIndex = Math.max(0, Math.min(passwordStrength, 5));
            passwordMeter.style.width = password.length > 0 ? strengthWidths[strengthIndex] : '0%';
            passwordMeter.style.backgroundColor = strengthColors[strengthIndex];

            var passwordIsValid = password.length >= 8 && passwordStrength >= 5;
            var passwordsMatch = password.length > 0 && password === passwordConfirmation;

            if (password.length > 0 && passwordStrength < 5) {
                var faltan = [];
                if (password.length < 8) faltan.push('mínimo 8 caracteres');
                if (!/[a-z]/.test(password)) faltan.push('1 minúscula');
                if (!/[A-Z]/.test(password)) faltan.push('1 mayúscula');
                if (!/[0-9]/.test(password)) faltan.push('1 número');
                if (!/[\W_]/.test(password)) faltan.push('1 símbolo');
                passwordHint.textContent = 'Falta: ' + faltan.join(', ');
                passwordHint.style.color = '#ef4444';
            } else if (passwordConfirmation.length > 0 && !passwordsMatch) {
                passwordHint.textContent = 'Las contraseñas no coinciden.';
                passwordHint.style.color = '#ef4444';
            } else if (passwordsMatch && passwordIsValid) {
                passwordHint.textContent = '✓ Contraseña segura';
                passwordHint.style.color = '#16a34a';
            } else {
                passwordHint.textContent = '';
            }

            changePasswordButton.disabled = !(passwordIsValid && passwordsMatch);
        }

        // Toggle ver contraseña — recuperación
        function setupEyeRec(btnId, iconId, inputId) {
            var btn = document.getElementById(btnId);
            var icon = document.getElementById(iconId);
            var input = document.getElementById(inputId);
            if (!btn || !icon || !input) return;
            btn.addEventListener('click', function() {
                // Ojo abierto = texto visible; ojo tachado = oculto.
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'bi bi-eye-fill';
                } else {
                    input.type = 'password';
                    icon.className = 'bi bi-eye-slash-fill';
                }
            });
        }
        setupEyeRec('btnEyeRec1', 'iconEyeRec1', 'rec-pass');
        setupEyeRec('btnEyeRec2', 'iconEyeRec2', 'rec-pass2');
        setupEyeRec('btnEyePin', 'iconEyePin', 'rec-pin');
        // Estado inicial: sin 6 dígitos no se puede verificar el código.
        validarCodigoRec();
    
