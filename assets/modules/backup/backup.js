/* ==========================================
   Backup y restauración — comportamiento
   ========================================== */

document.addEventListener('DOMContentLoaded', () => {
    const formExportar = document.querySelector('[data-exportar]');
    const formImportar = document.querySelector('[data-importar]');

    // La descarga no recarga la página: se restaura el botón tras unos segundos.
    if (formExportar) {
        formExportar.addEventListener('submit', () => {
            const boton = formExportar.querySelector('button[type="submit"]');
            if (!boton || boton.disabled) {
                return;
            }
            const contenidoOriginal = boton.innerHTML;
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Generando...';
            window.setTimeout(() => {
                boton.disabled = false;
                boton.innerHTML = contenidoOriginal;
            }, 4000);
        });
    }

    if (!formImportar) {
        return;
    }

    formImportar.addEventListener('submit', async (evento) => {
        // Tras confirmar se envía con form.submit(), que no vuelve a pasar por aquí.
        if (formImportar.dataset.enviando === '1') {
            return;
        }
        evento.preventDefault();

        const input = formImportar.querySelector('input[type="file"]');
        const archivo = input && input.files ? input.files[0] : null;

        if (!archivo) {
            mostrarError('Selecciona un archivo .sql para restaurar.');
            return;
        }

        if (!/\.sql$/i.test(archivo.name)) {
            mostrarError('El archivo debe tener extensión .sql.');
            return;
        }

        const maxMb = Number(formImportar.dataset.maxMb || 10);
        if (archivo.size > maxMb * 1024 * 1024) {
            mostrarError('El archivo supera el máximo de ' + maxMb + ' MB.');
            return;
        }

        const confirmado = window.Swal
            ? await window.Swal.fire({
                icon: 'warning',
                title: '¿Restaurar la base de datos?',
                text: 'Se reemplazarán los datos actuales con "' + archivo.name + '". Esta acción no se puede deshacer.',
                showCancelButton: true,
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                focusCancel: true
            }).then((resultado) => resultado.isConfirmed)
            : window.confirm('Se reemplazarán los datos actuales. ¿Continuar?');

        if (!confirmado) {
            return;
        }

        formImportar.dataset.enviando = '1';
        const boton = formImportar.querySelector('button[type="submit"]');
        if (boton) {
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Restaurando...';
        }
        formImportar.submit();
    });

    /**
     * Muestra un error de validación antes de enviar el formulario.
     *
     * @param {string} mensaje Texto para el usuario.
     * @returns {void}
     */
    function mostrarError(mensaje) {
        if (window.Swal) {
            window.Swal.fire({ icon: 'error', title: 'Archivo no válido', text: mensaje });
            return;
        }
        window.alert(mensaje);
    }
});
