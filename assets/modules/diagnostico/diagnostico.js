
// Diagnóstico: detalle y resolución de errores del diario (solo Administrador).
// La captura de errores vive en diseno.js (boundary del navegador); aquí solo
// se consulta lo ya registrado y se marca el cierre de cada error. El escape
// de HTML para la ficha usa el escapeHtml global de diseno.js.

/**
 * Abre la ventana de detalle de un error del diario.
 *
 * Consulta diagnostico/detalle (JSON) y arma la ficha completa: mensaje,
 * ubicación, contexto de la petición y stack técnico en un bloque scrolleable.
 *
 * @param {HTMLElement} boton Botón con data-id del error.
 * @returns {void}
 */
function jvAbrirDetalleError(boton) {
    var idError = String(boton.dataset.id || '');
    if (!idError || typeof Swal === 'undefined') return;

    jvFetchConTope((window.JV_BASE || '') + 'index.php?url=diagnostico/detalle&id=' + idError)
        .then(function(respuesta) { return respuesta.json(); })
        .then(function(datos) {
            if (!datos.ok) {
                Swal.fire({ icon: 'error', title: 'No se pudo abrir el detalle', text: datos.error || 'El error ya no existe.', confirmButtonColor: '#EA580C' });
                return;
            }
            var e = datos.error;
            var ficha =
                '<div class="diagnostico-detalle text-start">' +
                '<p class="diagnostico-detalle-mensaje">' + escapeHtml(e.mensaje) + '</p>' +
                '<dl class="row diagnostico-detalle-datos">' +
                '<dt class="col-5">Origen</dt><dd class="col-7">' + escapeHtml(e.origen === 'navegador' ? 'Navegador' : 'Servidor') + '</dd>' +
                (e.clase ? '<dt class="col-5">Clase</dt><dd class="col-7">' + escapeHtml(e.clase) + '</dd>' : '') +
                '<dt class="col-5">Ubicación</dt><dd class="col-7">' + escapeHtml(e.archivo ? e.archivo + ':' + e.linea : '—') + '</dd>' +
                '<dt class="col-5">Apareció</dt><dd class="col-7">' + Number(e.ocurrencias || 0).toLocaleString('es-VE') + ' veces · primera: ' + escapeHtml(e.primera_vez) + ' · última: ' + escapeHtml(e.ultima_vez) + '</dd>' +
                (e.url ? '<dt class="col-5">Petición</dt><dd class="col-7">' + escapeHtml(e.metodo || 'GET') + (Number(e.es_ajax) ? ' (AJAX)' : '') + '<br><span class="text-break">' + escapeHtml(e.url) + '</span></dd>' : '') +
                (e.agente ? '<dt class="col-5">Navegador</dt><dd class="col-7 text-break">' + escapeHtml(e.agente) + '</dd>' : '') +
                (e.id_usuario ? '<dt class="col-5">Sesión</dt><dd class="col-7">Usuario #' + escapeHtml(e.id_usuario) + ' (rol ' + escapeHtml(e.rol) + ')</dd>' : '') +
                '</dl>' +
                (e.stack ? '<p class="diagnostico-detalle-rotulo mb-1">Stack</p><pre class="diagnostico-detalle-stack">' + escapeHtml(e.stack) + '</pre>' : '') +
                '</div>';
            Swal.fire({
                title: 'Detalle del error',
                html: ficha,
                width: 640,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#EA580C'
            });
        })
        .catch(function() {
            Swal.fire({ icon: 'error', title: 'No se pudo abrir el detalle', text: 'Revisa tu conexión e inténtalo de nuevo.', confirmButtonColor: '#EA580C' });
        });
}

/**
 * Marca un error como resuelto por AJAX (intercepta el submit del formulario).
 *
 * Pide confirmación, envía el POST con CSRF y recarga la página al confirmar:
 * la recarga trae el conteo del encabezado y la tabla al día.
 *
 * @param {HTMLFormElement} formulario Formulario con csrf_token e id_error.
 * @returns {void}
 */
function jvResolverError(formulario) {
    var idError = String(formulario.querySelector('[name="id_error"]').value || '');
    Swal.fire({
        icon: 'question',
        title: '¿Marcar como resuelto?',
        text: 'El error dejará de aparecer en la lista de activos.',
        showCancelButton: true,
        confirmButtonText: 'Sí, resuelto',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#16A34A'
    }).then(function(resultado) {
        if (!resultado.isConfirmed) return;
        jvFetchConTope(formulario.getAttribute('action'), {
            init: { method: 'POST', body: new FormData(formulario) }
        }).then(function(respuesta) { return respuesta.json(); })
            .then(function(datos) {
                if (datos.ok) {
                    Swal.fire({ icon: 'success', title: 'Error resuelto', text: 'La lista se actualizará.', timer: 1400, showConfirmButton: false }).then(function() { window.location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'No se pudo marcar', text: datos.error || 'Intenta de nuevo.', confirmButtonColor: '#EA580C' });
                }
            })
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'No se pudo marcar', text: 'Revisa tu conexión e inténtalo de nuevo.', confirmButtonColor: '#EA580C' });
            });
    });
}

// Listeners del panel (el script carga al final del body: el DOM ya existe).
document.querySelectorAll('.diagnostico-btn-detalle').forEach(function(boton) {
    boton.addEventListener('click', function() { jvAbrirDetalleError(boton); });
});

document.querySelectorAll('.diagnostico-form-resolver').forEach(function(formulario) {
    formulario.addEventListener('submit', function(evento) {
        evento.preventDefault();
        jvResolverError(formulario);
    });
});
