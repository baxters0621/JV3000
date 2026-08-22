
        // ==========================================
        // PREVIEW NOTA — confirmación de registro
        // ==========================================

        // Deshabilita el botón de confirmación para evitar doble envío y envía el formulario de la nota.
        function confirmarRegistro(event) {
            event.preventDefault();
            const confirmButton = document.getElementById('btnConfirmar');
            confirmButton.disabled = true;
            confirmButton.textContent = '⏳ REGISTRANDO...';
            document.querySelector('.buttons form').submit();
        }
    
