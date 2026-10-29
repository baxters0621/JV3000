// ==========================================
// POST DINÁMICO — envía parámetros como formulario oculto
// ==========================================
// Crea un <form> oculto con inputs para cada parámetro y lo envía con POST.
// Se usa para acciones que requieren método POST desde código JavaScript.
function jvPost(params, url) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = url || window.location.href;
    form.style.display = 'none';
    var keys = Object.keys(params);
    for (var i = 0; i < keys.length; i++) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = keys[i];
        input.value = params[keys[i]];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}

// ==========================================
// ESCAPADO HTML — evita inyección en el DOM
// ==========================================
// Convierte los caracteres especiales (&, <, >, comillas) de un texto en sus
// entidades HTML para poder insertarlo de forma segura en la página.
function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function(character) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
}

// ==========================================
// ERRORES DE FORMULARIO — marcar y limpiar campos
// ==========================================
// Contrato usado por productos, compras y salidas: marcarError colorea el
// campo (clase .input-error, ya definida en el CSS de cada módulo) y coloca
// el mensaje justo debajo con id "<id>_err"; al teclear, cada módulo retira
// la clase y borra ese mensaje por su id. limpiarErrores deja el formulario
// limpio. Vivían sin definir (bug latente: "Guardar Compra" y "Guardar
// Cambios" lanzaban ReferenceError); desde aquí son globales en todo el panel.
/**
 * Marca un campo con error: borde rojo y mensaje bajo el campo.
 *
 * @param {HTMLElement} input   Campo a marcar (si no existe, no hace nada).
 * @param {string}      [mensaje] Texto del aviso bajo el campo (opcional).
 * @returns {void}
 */
function marcarError(input, mensaje) {
    if (!input) return;
    input.classList.add('input-error');
    input.setAttribute('aria-invalid', 'true');
    if (mensaje) {
        // El aviso vive justo después del campo y comparte id "_err"
        // para que el listener de tecleo pueda retirarlo.
        var aviso = document.getElementById((input.id || '') + '_err');
        if (!aviso || !aviso.classList.contains('jv-error-msg')) {
            if (aviso) aviso.remove();
            aviso = document.createElement('div');
            aviso.className = 'jv-error-msg';
            aviso.id = (input.id || '') + '_err';
            input.insertAdjacentElement('afterend', aviso);
        }
        aviso.textContent = mensaje;
    }
}

/**
 * Limpia todas las marcas de error de la página (clases y mensajes).
 *
 * @returns {void}
 */
function limpiarErrores() {
    document.querySelectorAll('.input-error').forEach(function(campo) {
        campo.classList.remove('input-error');
        campo.removeAttribute('aria-invalid');
    });
    document.querySelectorAll('.jv-error-msg').forEach(function(aviso) { aviso.remove(); });
}

// ==========================================
// MENSAJES FLASH — se cierran solos tras su cuenta atrás
// ==========================================
// El CSS ya dibuja la barra de tiempo (4 s) y la animación de salida
// (.jv-flash-out): aquí solo se aplica esa salida al terminar y se retira el
// nodo. Este bloque faltaba —los comentarios de historial.js y perfil.js
// apuntaban a diseno.js, pero aquí no existía—, así que el mensaje se quedaba
// pegado en pantalla en todos los módulos del panel.
// Se espera a DOMContentLoaded porque este fichero se carga en el <head>.
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.flash-auto').forEach(function(el) {
        window.setTimeout(function() {
            el.classList.add('jv-flash-out');
            window.setTimeout(function() { el.remove(); }, 500);
        }, 4000);
    });
});

// ==========================================
// LÍMITE DE ERRORES DEL NAVEGADOR — un aviso amable, nunca un clic mudo
// ==========================================
// Equivalente del "Error Boundary" de React para este stack (JS nativo):
// si cualquier script del panel falla en tiempo de ejecución (error o promesa
// sin manejar), la página NO se recarga ni el clic muere en silencio: se
// muestra UN solo aviso por carga de página con lenguaje claro, y el detalle
// técnico queda en la consola para diagnóstico. Los formularios siguen en
// pantalla: lo que la persona escribió no se pierde.
var jv_aviso_error_mostrado = false;

/**
 * Muestra una única vez el aviso amable de error inesperado del panel.
 *
 * @returns {void}
 */
function jvAvisoErrorNavegador() {
    if (jv_aviso_error_mostrado || typeof Swal === 'undefined') return;
    jv_aviso_error_mostrado = true;
    Swal.fire({
        icon: 'warning',
        title: 'No se completó la acción',
        html: 'Algo falló al procesar lo último que intentaste. Lo que ya escribiste sigue ahí, no se perdió nada. Puedes intentarlo de nuevo; si vuelve a fallar, avisa al administrador.',
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#EA580C'
    });
}

// Errores de ejecución de los scripts del panel (los fallos de carga de
// recursos como imágenes no burbujean: no llegan aquí ni molestan).
window.addEventListener('error', function(evento) {
    console.error('[JV3000] Error no manejado:', evento.error || evento.message);
    jvAvisoErrorNavegador();
});

// Promesas sin catch (por ejemplo, un fetch que falló por red) caen aquí.
window.addEventListener('unhandledrejection', function(evento) {
    console.error('[JV3000] Promesa sin manejar:', evento.reason);
    jvAvisoErrorNavegador();
});
