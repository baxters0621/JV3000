
        // ==========================================
        // PREVIEW NOTA — confirmación de registro
        // ==========================================

        function confirmarRegistro(e) {
            e.preventDefault();
            const btn = document.getElementById('btnConfirmar');
            btn.disabled = true;
            btn.textContent = '⏳ REGISTRANDO...';
            document.querySelector('.buttons form').submit();
        }
    
