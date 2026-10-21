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
