
        function validarRespuesta() {
            var securityAnswer = document.getElementById('rec-resp').value.trim();
            var submitButton = document.getElementById('rec-btn');
            var validationHint = document.getElementById('rec-resp-hint');
            if (securityAnswer.length === 0) {
                submitButton.disabled = true;
                validationHint.textContent = '';
                document.getElementById('rec-resp').style.borderColor = '';
                return;
            }
            var answerIsValid = securityAnswer.length >= 1;
            submitButton.disabled = !answerIsValid;
            document.getElementById('rec-resp').style.borderColor = answerIsValid ? '#16A34A' : '';
            validationHint.textContent = '';
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

            var strengthColors = ['#DC2626', '#DC2626', '#D97706', '#2563EB', '#16A34A'];
            var strengthWidths = ['20%', '40%', '60%', '80%', '100%'];
            var strengthIndex = Math.max(0, Math.min(passwordStrength - 1, 4));
            passwordMeter.style.width = strengthWidths[strengthIndex];
            passwordMeter.style.backgroundColor = strengthColors[strengthIndex];

            var passwordIsValid = password.length >= 8 && passwordStrength >= 5;
            var passwordsMatch = password.length > 0 && password === passwordConfirmation;

            if (password.length > 0 && passwordStrength < 5) {
                passwordHint.textContent = 'Debe tener mayúsculas, minúsculas, números y símbolos.';
                passwordHint.style.color = '#DC2626';
            } else if (passwordConfirmation.length > 0 && !passwordsMatch) {
                passwordHint.textContent = 'Las contraseñas no coinciden.';
                passwordHint.style.color = '#DC2626';
            } else if (passwordsMatch && passwordIsValid) {
                passwordHint.textContent = '✓ Contraseña segura';
                passwordHint.style.color = '#16A34A';
            } else {
                passwordHint.textContent = '';
            }

            changePasswordButton.disabled = !(passwordIsValid && passwordsMatch);
        }
    
