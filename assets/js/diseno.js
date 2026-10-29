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

// ==========================================
// CARGA VISIBLE — skeletons con tope de espera
// ==========================================
// Compañero del límite de errores: ninguna espera del panel queda sin rostro.
// jvCargaIniciar coloca un skeleton con la forma del contenido (solo si la
// espera supera unos milisegundos, para no hacer parpadear respuestas
// rápidas); jvCargaFin lo retira; jvCargaTarde convierte el skeleton en el
// aviso "está tardando" con botón Reintentar; y jvFetchConTope corta las
// peticiones que superan su tiempo límite. document/fetch se consultan al
// invocar para poder simularlos en el piloto de tests.

/** Elementos con token de carga activo (uno por contenedor). */
var jvOverlaysCarga = new Map();

/**
 * Devuelve el HTML de un skeleton según la variante pedida.
 * Se usa cuando el contenedor no tiene contenido previo que conservar
 * (por ejemplo, la línea de estado de la tasa BCV).
 *
 * @param {string} variante 'linea' | 'kpi' | 'fila' | 'grafico'.
 * @returns {string} HTML listo para inyectar.
 */
function jvSkeletonHtml(variante) {
    var clase = { linea: 'jv-skel-linea', kpi: 'jv-skel-kpi', fila: 'jv-skel-fila', grafico: 'jv-skel-grafico' }[variante] || 'jv-skel-linea';
    return '<span class="jv-skel ' + clase + '" aria-hidden="true"></span>';
}

/**
 * Crea y muestra el overlay de skeleton dentro de un contenedor.
 * Uso interno de jvCargaIniciar y jvCargaTarde.
 *
 * @param {HTMLElement} contenedor Elemento a cubrir.
 * @param {object} opciones variante ('kpi'|'filas'|'grafico').
 * @returns {{overlay: HTMLElement, piezas: HTMLElement[]}} El overlay y sus
 *   piezas, para que jvCargaTarde pueda retirarlas después.
 */
function jvCrearOverlay(contenedor, opciones) {
    var overlay = document.createElement('div');
    overlay.className = 'jv-skel-overlay';
    var recetas = {
        kpi:     ['jv-skel-kpi'],
        filas:   ['jv-skel-fila', 'jv-skel-fila', 'jv-skel-fila'],
        grafico: ['jv-skel-grafico']
    };
    var piezas = [];
    (recetas[opciones.variante] || recetas.filas).forEach(function(clase) {
        var pieza = document.createElement('span');
        pieza.className = 'jv-skel ' + clase;
        overlay.appendChild(pieza);
        piezas.push(pieza);
    });
    contenedor.classList.add('jv-cargando');
    contenedor.setAttribute('aria-busy', 'true');
    contenedor.appendChild(overlay);
    return { overlay: overlay, piezas: piezas };
}

/**
 * Muestra el overlay de carga dentro de un contenedor ya pintado.
 *
 * El skeleton solo aparece si la espera supera `trasMs` (las respuestas
 * rápidas no parpadean; con trasMs 0 aparece de inmediato). Devuelve un
 * token: llama token.fin() al terminar, aunque el skeleton no se haya
 * dibujado todavía.
 *
 * @param {HTMLElement|null} contenedor Elemento a cubrir.
 * @param {object} [opciones] variante, trasMs (700 por defecto) y otros.
 * @returns {{fin: Function}} Token de limpieza.
 */
function jvCargaIniciar(contenedor, opciones) {
    opciones = opciones || {};
    if (!contenedor || jvOverlaysCarga.has(contenedor)) return { fin: function() {} };

    var token = { temporizador: null, overlay: null, piezas: [] };
    token.fin = function() {
        if (token.temporizador) { clearTimeout(token.temporizador); token.temporizador = null; }
        if (token.overlay) { token.overlay.remove(); token.overlay = null; }
        jvOverlaysCarga.delete(contenedor);
        contenedor.classList.remove('jv-cargando');
        contenedor.removeAttribute('aria-busy');
    };

    var aparecer = function() {
        token.temporizador = null;
        var creado = jvCrearOverlay(contenedor, opciones);
        token.overlay = creado.overlay;
        token.piezas = creado.piezas;
    };
    if (opciones.trasMs === 0) aparecer();
    else token.temporizador = setTimeout(aparecer, opciones.trasMs || 700);

    jvOverlaysCarga.set(contenedor, token);
    return token;
}

/**
 * Cambia el overlay de un contenedor al estado "está tardando": un mensaje
 * claro y un botón Reintentar. Si el skeleton aún no se dibujó, lo dibuja.
 *
 * @param {HTMLElement|null} contenedor Contenedor con carga iniciada.
 * @param {string} mensaje Texto amable para la persona usuaria.
 * @param {Function} reintentar Acción a ejecutar al pulsar el botón.
 * @returns {void}
 */
function jvCargaTarde(contenedor, mensaje, reintentar) {
    if (!contenedor) return;
    var token = jvOverlaysCarga.get(contenedor);
    if (!token) token = jvCargaIniciar(contenedor, { trasMs: 0 });
    if (!token || !token.overlay) return;

    var overlay = token.overlay;
    overlay.classList.add('jv-skel-tarde');
    token.piezas.forEach(function(pieza) { pieza.remove(); });
    token.piezas = [];

    var texto = document.createElement('div');
    texto.className = 'jv-skel-tarde-msg';
    texto.textContent = mensaje;
    overlay.appendChild(texto);

    var boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'jv-skel-reintentar';
    boton.textContent = 'Reintentar';
    boton.addEventListener('click', function() { if (typeof reintentar === 'function') reintentar(); });
    overlay.appendChild(boton);
}

/**
 * fetch con tope de espera: si la respuesta no llega en `ms` milisegundos,
 * la petición se aborta y la promesa rechaza con error.jvTope = true, para
 * que el módulo decida mostrar el aviso de "tardando" o el de fallo.
 *
 * @param {string} url URL a consultar.
 * @param {object} [opciones] ms (12000 por defecto), init (opciones de fetch)
 *   y fetchImpl (inyectable para pruebas).
 * @returns {Promise<Response>} La respuesta o el rechazo marcado.
 */
function jvFetchConTope(url, opciones) {
    opciones = opciones || {};
    var ms = opciones.ms || 12000;
    var doFetch = opciones.fetchImpl || window.fetch;
    var init = Object.assign({}, opciones.init || {});
    init.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, init.headers || {});

    var controlador = typeof AbortController !== 'undefined' ? new AbortController() : null;
    if (controlador) init.signal = controlador.signal;
    var reloj = controlador ? setTimeout(function() { controlador.abort(); }, ms) : null;

    return doFetch(url, init).finally(function() {
        if (reloj) clearTimeout(reloj);
    }).catch(function(error) {
        if (error && error.name === 'AbortError') error.jvTope = true;
        throw error;
    });
}
