
        // ==========================================
        // PREVIEW NOTA — confirmación de registro
        // ==========================================

        // Deshabilita el botón de confirmación para evitar doble envío y envía el formulario de la nota.
        function confirmarRegistro(e) {
            e.preventDefault();
            const btn = document.getElementById('btnConfirmar');
            btn.disabled = true;
            btn.textContent = '⏳ REGISTRANDO...';
            document.querySelector('.buttons form').submit();
        }
    
