
        // ==========================================
        // PREVIEW NOTA — confirmación de registro
        // ==========================================

        // Confirma la venta por AJAX y mantiene disponible el preview si el servidor responde con error.
        function confirmarRegistro(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const confirmButton = document.getElementById('btnConfirmar');
            const errorContainer = document.getElementById('confirmacionError');

            errorContainer.classList.add('d-none');
            confirmButton.disabled = true;
            confirmButton.textContent = '⏳ REGISTRANDO...';

            jvFetchConTope(form.action, {
                ms: 12000,
                init: {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'Accept': 'application/json' }
                }
            }).then(async function(response) {
                let resultado;
                try {
                    resultado = await response.json();
                } catch (error) {
                    throw new Error('El servidor no devolvió una respuesta válida. Revise Salidas antes de volver a registrar.');
                }

                if (!response.ok || !resultado.success) {
                    throw new Error(resultado.message || 'No se pudo registrar la venta.');
                }

                const irAlDestino = function () { window.location.assign(resultado.redirect_url); };
                if (window.JVAlarmaStock) {
                    window.JVAlarmaStock.mostrar(resultado.alertas_stock, irAlDestino);
                } else {
                    irAlDestino();
                }
            }).catch(function(error) {
                const mensaje = error.jvTope
                    ? 'La operación superó el tiempo de espera.'
                    : error.message;
                errorContainer.textContent = mensaje + ' Revise Salidas antes de volver a registrar para evitar duplicados.';
                errorContainer.classList.remove('d-none');
                confirmButton.disabled = false;
                confirmButton.textContent = '✓ CONFIRMAR Y REGISTRAR';
            });

            return false;
        }
    
