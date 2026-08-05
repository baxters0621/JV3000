
        function validarRespuesta() {
            var resp = document.getElementById('rec-resp').value.trim();
            var btn = document.getElementById('rec-btn');
            var hint = document.getElementById('rec-resp-hint');
            if (resp.length === 0) {
                btn.disabled = true;
                hint.textContent = '';
                document.getElementById('rec-resp').style.borderColor = '';
                return;
            }
            var ok = resp.length >= 5 && resp.length <= 20 && /[a-zA-Z]/.test(resp) && /[aeiouAEIOU]/.test(resp) && !/(.)\1{3,}/.test(resp) && !/abcdef|bcdefg|cdefgh|defghi|efghij|fghijk|ghijkl|hijklm|ijklmn/i.test(resp) && !/asdf|qwerty|zxcv|abcd|1234/i.test(resp);
            btn.disabled = !ok;
            document.getElementById('rec-resp').style.borderColor = ok ? '#16A34A' : '#DC2626';
            hint.textContent = ok ? '' : 'Mín. 5 y máx. 20 caracteres, sin patrones (asdf, 1234, etc).';
        }

        function validarPassRec() {
            var p = document.getElementById('rec-pass').value;
            var p2 = document.getElementById('rec-pass2').value;
            var btn = document.getElementById('rec-btn-pass');
            var meter = document.getElementById('rec-meter');
            var hint = document.getElementById('rec-pass-hint');

            var s = 0;
            if (p.length >= 8) s++;
            if (/[a-z]/.test(p)) s++;
            if (/[A-Z]/.test(p)) s++;
            if (/[0-9]/.test(p)) s++;
            if (/[\W_]/.test(p)) s++;

            var cols = ['#DC2626', '#DC2626', '#D97706', '#2563EB', '#16A34A'];
            var wids = ['20%', '40%', '60%', '80%', '100%'];
            var idx = Math.max(0, Math.min(s - 1, 4));
            meter.style.width = wids[idx];
            meter.style.backgroundColor = cols[idx];

            var pwdOk = p.length >= 8 && s >= 5;
            var matchOk = p.length > 0 && p === p2;

            if (p.length > 0 && s < 5) {
                hint.textContent = 'Debe tener mayúsculas, minúsculas, números y símbolos.';
                hint.style.color = '#DC2626';
            } else if (p2.length > 0 && !matchOk) {
                hint.textContent = 'Las contraseñas no coinciden.';
                hint.style.color = '#DC2626';
            } else if (matchOk && pwdOk) {
                hint.textContent = '✓ Contraseña segura';
                hint.style.color = '#16A34A';
            } else {
                hint.textContent = '';
            }

            btn.disabled = !(pwdOk && matchOk);
        }
    
