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
 * Envía el detalle del error al diario del servidor (tabla errores).
 *
 * Es fire-and-forget: el POST viaja con keepalive (sobrevive si la página
 * se descarga al instante) y nadie espera su respuesta. Solo participa si
 * hay sesión (existe csrfToken) — las páginas públicas no reportan. Cualquier
 * fallo del envío se descarta: el reporte es cortesía, nunca interrumpe.
 *
 * @param {object|null} detalle mensaje, archivo, linea y stack del error.
 * @returns {void}
 */
function jvReportarErrorNavegador(detalle) {
    try {
        var csrf = (window.JV_CONFIG || {}).csrfToken;
        var mensaje = detalle && detalle.mensaje ? String(detalle.mensaje) : '';
        // Sin sesión (csrf), sin FormData o sin fetch (entornos raros): nada que enviar.
        if (!mensaje || !csrf || typeof FormData === 'undefined' || typeof window.fetch !== 'function') return;

        var datos = new FormData();
        datos.append('csrf_token', String(csrf));
        datos.append('mensaje', mensaje.slice(0, 800));
        datos.append('archivo', String((detalle && detalle.archivo) || '').slice(0, 255));
        datos.append('linea', String((detalle && detalle.linea) || ''));
        datos.append('stack', String((detalle && detalle.stack) || '').slice(0, 4000));
        window.fetch((window.JV_BASE || '') + 'index.php?url=diagnostico/cliente', {
            method: 'POST',
            body: datos,
            keepalive: true,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(function() { /* El reporte no tiene cliente que avise. */ });
    } catch (fallo) {
        // El reporte es cortesía: jamás interrumpe al aviso del usuario.
    }
}

/**
 * Muestra una única vez el aviso amable de error inesperado del panel y
 * deja el detalle en el diario del servidor para el panel Diagnóstico.
 *
 * @param {object|null} detalle mensaje, archivo, linea y stack (del evento).
 * @returns {void}
 */
function jvAvisoErrorNavegador(detalle) {
    if (jv_aviso_error_mostrado) return;
    jv_aviso_error_mostrado = true;
    jvReportarErrorNavegador(detalle);
    if (typeof Swal === 'undefined') return;
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
    jvAvisoErrorNavegador({
        mensaje: evento.message || (evento.error && evento.error.message) || '',
        archivo: evento.filename || '',
        linea: evento.lineno || 0,
        stack: (evento.error && evento.error.stack) || ''
    });
});

// Promesas sin catch (por ejemplo, un fetch que falló por red) caen aquí.
window.addEventListener('unhandledrejection', function(evento) {
    console.error('[JV3000] Promesa sin manejar:', evento.reason);
    var razon = evento.reason;
    var es_error = razon instanceof Error;
    jvAvisoErrorNavegador({
        mensaje: (es_error ? razon.message : String(razon)) || 'Promesa sin manejar',
        archivo: '',
        linea: 0,
        stack: (es_error && razon.stack) || ''
    });
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

// ==========================================
// BORRADORES DE FORMULARIOS — lo tecleado sobrevive a recargas y fallos
// ==========================================
// Los formularios marcados con data-borrador="<clave>" guardan su contenido
// en el localStorage del navegador (nunca en el servidor). Si la página falla,
// se recarga o la sesión caduca a mitad del llenado, el texto se recupera.
//
// Reglas de seguridad (innegociables):
//  - NUNCA se guardan contraseñas, PINs, códigos ni tokens (jvBorradorExcluido).
//  - NUNCA se guarda el JSON de la grilla de productos (productos_data):
//    la tabla y el borrador podrían desincronizarse.
//  - El borrador queda atado al id de usuario: otra cuenta no lo ve.
//  - Caduca a las 24 horas.
//
// Cuándo se decide qué hacer con un borrador guardado (jvBorradorDecidir):
//  - enviado hace menos de 120 s + mensaje de éxito → se completó: se purga.
//  - enviado hace menos de 120 s sin éxito → el POST nativo falló (validación
//    de servidor, sesión vencida): se restaura solo y se avisa con un toast.
//  - más antiguos o sin marca de envío → se pregunta con Swal
//    (Restaurar / Descartar; Esc conserva el borrador para más tarde).
//  - sin Swal (páginas de acceso) → restauración silenciosa.
//
// Límite conocido: los envíos programados con form.submit() (grilla de
// compras y recepción) no disparan el evento submit, así que quedan sin la
// marca "enviado" y usan la vía de la pregunta en vez del aviso automático.

/** Disponibilidad de localStorage, calculada una sola vez (false = no hay). */
var jvBorradorAlmacenEstado = null;

/** Temporizadores del guardado con retardo, uno por formulario. */
var jvBorradorTemporizadores = new Map();

/** Cola que hace que las preguntas de restauración aparezcan de una en una. */
var jvBorradorCola = Promise.resolve();

/** Clave común de todas las entradas de borrador en localStorage. */
var jvBorradorPrefijoLlave = 'jv_borrador_v1_';

/** Vida útil máxima de un borrador, en milisegundos (24 h). */
var jvBorradorVida = 24 * 60 * 60 * 1000;

/** Ventana en la que un "enviado" reciente cuenta como intento en curso. */
var jvBorradorVentanaEnvio = 120000;

/**
 * Comprueba (y recuerda) si localStorage está disponible y escribible.
 *
 * @returns {object|false} el objeto localStorage o false si no se puede usar.
 */
function jvBorradorAlmacen() {
    if (jvBorradorAlmacenEstado !== null) return jvBorradorAlmacenEstado;
    var disponible = false;
    try {
        var almacen = window.localStorage;
        disponible = !!(almacen && typeof almacen.getItem === 'function' && typeof almacen.setItem === 'function');
        if (disponible) {
            almacen.setItem('jv_borrador_prueba', '1');
            almacen.removeItem('jv_borrador_prueba');
        }
    } catch (error) { disponible = false; }
    jvBorradorAlmacenEstado = disponible ? window.localStorage : false;
    return jvBorradorAlmacenEstado;
}

/**
 * Arma la llave completa de un borrador en localStorage.
 *
 * @param {string} clave la clave corta del atributo data-borrador.
 * @returns {string} llave completa, p. ej. jv_borrador_v1_compras_alta.
 */
function jvBorradorLlave(clave) {
    return jvBorradorPrefijoLlave + String(clave);
}

/**
 * Id de la cuenta activa, para que un borrador nunca cruce entre usuarios.
 *
 * @returns {number} id de usuario o 0 en páginas sin sesión.
 */
function jvBorradorUsuario() {
    return (window.JV_CONFIG || {}).userId || 0;
}

/**
 * Decide si un campo queda FUERA del borrador por ser sensible.
 *
 * @param {string} nombre atributo name del campo.
 * @returns {boolean} true si no debe guardarse jamás.
 */
function jvBorradorExcluido(nombre) {
    var nombreLimpio = String(nombre || '');
    if (/^(csrf_token|productos_data)$/i.test(nombreLimpio)) return true;
    return /pass|pin|codigo|token|clave/i.test(nombreLimpio);
}

/**
 * Toma una foto de los campos guardables del formulario.
 *
 * @param {HTMLFormElement} form formulario marcado con data-borrador.
 * @returns {object} mapa nombre → valor; radios y checkboxes solo si marcados.
 */
function jvBorradorSerializar(form) {
    var datos = {};
    var elementos = form.elements || [];
    for (var i = 0; i < elementos.length; i++) {
        var campo = elementos[i];
        var nombre = campo.name;
        if (!nombre || jvBorradorExcluido(nombre)) continue;
        var tipo = String(campo.type || '').toLowerCase();
        if (tipo === 'file' || tipo === 'submit' || tipo === 'button' || tipo === 'reset' || tipo === 'image') continue;
        if (campo.disabled) continue;
        if (tipo === 'radio') {
            if (campo.checked) datos[nombre] = campo.value;
            continue;
        }
        if (tipo === 'checkbox') {
            if (campo.checked) {
                if (!datos[nombre]) datos[nombre] = [];
                datos[nombre].push(campo.value);
            }
            continue;
        }
        datos[nombre] = campo.value;
    }
    return datos;
}

/**
 * Vuelve a dejar en el formulario los valores de un borrador guardado.
 *
 * Solo toca campos que aún existen; los selects restauran únicamente si la
 * opción sigue disponible. Devuelve cuántos campos pudo recuperar.
 *
 * @param {HTMLFormElement} form formulario destino.
 * @param {object} datos mapa nombre → valor del borrador.
 * @returns {number} cantidad de campos restaurados.
 */
function jvBorradorRestaurar(form, datos) {
    var restaurados = 0;
    if (!datos || !form.elements) return restaurados;
    Object.keys(datos).forEach(function(nombre) {
        var valor = datos[nombre];
        if (valor === null || valor === undefined) return;
        var campo = form.elements[nombre];
        if (!campo) return;
        // Colección de campos con el mismo nombre (grupo de radios o checks):
        // length está definido y no es un <select> (ese tiene .options).
        if (campo.length !== undefined && !campo.options) {
            var esArray = Array.isArray(valor);
            for (var i = 0; i < campo.length; i++) {
                var pieza = campo[i];
                if (!pieza) continue;
                var tipo = String(pieza.type || '').toLowerCase();
                if (tipo === 'radio' && String(pieza.value) === String(valor)) { pieza.checked = true; restaurados++; }
                else if (tipo === 'checkbox' && esArray) {
                    var marcado = valor.indexOf(String(pieza.value)) !== -1;
                    pieza.checked = marcado;
                    if (marcado) restaurados++;
                }
            }
            return;
        }
        campo.value = valor;
        // En un <select>, asignar un valor inexistente no cambia nada: se
        // cuenta como restaurado solo si de verdad quedó seleccionado.
        if (campo.options && String(campo.value) !== String(valor)) return;
        restaurados++;
    });
    return restaurados;
}

/**
 * Lee un borrador válido: versión correcta, de la misma cuenta y vigente.
 *
 * @param {string} clave la clave corta del formulario.
 * @returns {object|null} el registro guardado o null si no sirve.
 */
function jvBorradorLeer(clave) {
    var almacen = jvBorradorAlmacen();
    if (!almacen) return null;
    var crudo = null;
    try { crudo = almacen.getItem(jvBorradorLlave(clave)); } catch (error) { return null; }
    if (!crudo) return null;
    try {
        var registro = JSON.parse(crudo);
        if (!registro || registro.v !== 1) return null;
        if (registro.usuario !== jvBorradorUsuario()) return null;
        if (jvBorradorHaceTiempo(registro.actualizado) > jvBorradorVida) {
            jvBorradorLimpiar(clave);
            return null;
        }
        return registro;
    } catch (error) {
        return null;
    }
}

/**
 * Guarda (o actualiza) el borrador de un formulario.
 *
 * La marca de envío se preserva: guardar por tecleo nunca borra un "enviado"
 * previo, y marcar como enviado solo estampa la hora la primera vez.
 *
 * @param {HTMLFormElement} form formulario marcado con data-borrador.
 * @param {boolean} enviado true si se está marcando un envío nativo en curso.
 * @returns {boolean} true si se escribió en localStorage.
 */
function jvBorradorGuardar(form, enviado) {
    var clave = form.getAttribute('data-borrador');
    if (!clave) return false;
    var almacen = jvBorradorAlmacen();
    if (!almacen) return false;
    var datos = jvBorradorSerializar(form);
    // Un borrador sin un solo campo no dice nada: no pisar lo que ya había.
    if (!Object.keys(datos).length) return false;
    var previo = jvBorradorLeer(clave);
    var registro = {
        v: 1,
        clave: String(clave),
        usuario: jvBorradorUsuario(),
        datos: datos,
        enviado: null,
        actualizado: Date.now()
    };
    if (enviado) registro.enviado = (previo && previo.enviado) ? previo.enviado : Date.now();
    else if (previo) registro.enviado = previo.enviado || null;
    try {
        almacen.setItem(jvBorradorLlave(clave), JSON.stringify(registro));
        return true;
    } catch (error) {
        return false;
    }
}

/**
 * Borra definitivamente el borrador de una clave (uso interno y de la fase 2).
 *
 * @param {string} clave la clave corta del formulario.
 * @returns {void}
 */
function jvBorradorLimpiar(clave) {
    var almacen = jvBorradorAlmacen();
    if (!almacen) return;
    try { almacen.removeItem(jvBorradorLlave(clave)); } catch (error) { /* nada que borrar. */ }
}

/**
 * Milisegundos transcurridos desde un momento dado (nunca negativos).
 *
 * @param {number} momento marca de tiempo Date.now() anterior.
 * @returns {number} tiempo transcurrido en ms.
 */
function jvBorradorHaceTiempo(momento) {
    return Math.max(0, Date.now() - (Number(momento) || 0));
}

/**
 * Texto humano de "hace cuánto" para los mensajes de restauración.
 *
 * @param {number} momento marca de tiempo a describir.
 * @returns {string} p. ej. "unos segundos", "5 minutos", "2 horas".
 */
function jvBorradorHaceTexto(momento) {
    var minutos = Math.floor(jvBorradorHaceTiempo(momento) / 60000);
    if (minutos < 1) return 'unos segundos';
    if (minutos < 60) return minutos + (minutos === 1 ? ' minuto' : ' minutos');
    var horas = Math.floor(minutos / 60);
    if (horas < 24) return horas + (horas === 1 ? ' hora' : ' horas');
    return 'más de un día';
}

/**
 * Pregunta con Swal qué hacer con un borrador (o restaura en silencio en las
 * páginas de acceso, que no cargan Swal). Descartar purga; Esc y el clic en
 * el fondo conservan el borrador para más tarde.
 *
 * @param {object} registro borrador leído de localStorage.
 * @param {HTMLFormElement} form formulario destino de la restauración.
 * @returns {Promise<boolean>} true si se restauró.
 */
function jvBorradorPreguntar(registro, form) {
    if (typeof Swal === 'undefined') {
        jvBorradorRestaurar(form, registro.datos);
        return Promise.resolve(true);
    }
    return Swal.fire({
        title: '¿Retomar el borrador?',
        html: 'Guardamos lo que escribiste hace ' + jvBorradorHaceTexto(registro.actualizado) + '.',
        icon: 'question',
        confirmButtonText: 'Restaurar',
        showCancelButton: true,
        cancelButtonText: 'Descartar',
        reverseButtons: true
    }).then(function(resultado) {
        if (resultado && resultado.isConfirmed) {
            jvBorradorRestaurar(form, registro.datos);
            return true;
        }
        // Descartar explícito purga; Esc o clic en el fondo lo conservan.
        if (resultado && resultado.isDismissed && resultado.dismiss === 'cancel') {
            jvBorradorLimpiar(registro.clave);
        }
        return false;
    });
}

/**
 * Encola una decisión para que, si varias llegan juntas, se pregunten de a
 * una y en orden (Swal no soporta dos diálogos simultáneos).
 *
 * @param {object} registro borrador leído de localStorage.
 * @param {HTMLFormElement} form formulario destino.
 * @returns {Promise<boolean>} resuelve cuando la decisión se tomó.
 */
function jvBorradorDecidir(registro, form) {
    jvBorradorCola = jvBorradorCola.then(function() {
        return jvBorradorPreguntar(registro, form);
    });
    return jvBorradorCola;
}

/**
 * Decide qué hacer con un borrador ya leído: purgar, restaurar con aviso o
 * preguntar (ver las reglas en la cabecera del módulo).
 *
 * @param {object} registro borrador leído de localStorage.
 * @param {HTMLFormElement} form formulario destino.
 * @returns {void}
 */
function jvBorradorDecidirSobre(registro, form) {
    var clave = registro.clave;
    var envioReciente = registro.enviado && jvBorradorHaceTiempo(registro.enviado) < jvBorradorVentanaEnvio;
    if (envioReciente) {
        var exito = document.querySelector ? document.querySelector('.alert-jv-success') : null;
        if (exito) {
            // El POST terminó bien: el borrador ya cumplió su trabajo.
            jvBorradorLimpiar(clave);
            return;
        }
        // El POST nativo rebotó (validación de servidor, sesión vencida):
        // devolver el texto y avisar sin exigir ningún clic.
        jvBorradorRestaurar(form, registro.datos);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Recuperamos lo que habías escrito',
                timer: 4000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        }
        return;
    }
    jvBorradorDecidir(registro, form);
}

/**
 * Escucha un formulario: guardado con retardo al escribir y marca de envío.
 *
 * El listener de submit se registra en fase burbuja a propósito: los módulos
 * (compras, salidas, usuarios…) registran los suyos antes y pueden hacer
 * preventDefault; si el evento llega sin prevenir es un POST nativo real y
 * se marca "enviado" antes de que el navegador navegue.
 *
 * @param {HTMLFormElement} form formulario marcado con data-borrador.
 * @returns {void}
 */
function jvBorradorEscuchar(form) {
    var retardar = function() {
        var previo = jvBorradorTemporizadores.get(form);
        if (previo) clearTimeout(previo);
        jvBorradorTemporizadores.set(form, setTimeout(function() {
            jvBorradorTemporizadores.delete(form);
            jvBorradorGuardar(form, false);
        }, 400));
    };
    form.addEventListener('input', retardar);
    form.addEventListener('change', retardar);

    form.addEventListener('submit', function(evento) {
        // Un envío prevenido (validación de módulo o fetch) solo re-guarda.
        jvBorradorGuardar(form, !evento.defaultPrevented);
    });
}

/**
 * Prepara un formulario marcado: decide qué hacer con su borrador guardado y
 * activa los listeners. Si el formulario vive en un modal oculto
 * (offsetParent null), la decisión espera a que Bootstrap lo muestre
 * (evento delegado shown.bs.modal).
 *
 * @param {HTMLFormElement} form formulario con atributo data-borrador.
 * @returns {void}
 */
function jvBorradorPreparar(form) {
    var clave = form.getAttribute('data-borrador');
    if (!clave) return;
    var registro = jvBorradorLeer(clave);
    var oculto = registro && form.offsetParent === null;

    if (registro && !oculto) jvBorradorDecidirSobre(registro, form);

    if (oculto) {
        document.addEventListener('shown.bs.modal', function(evento) {
            if (!registro || !evento.target || !evento.target.contains(form)) return; // es otro modal
            var pendiente = registro;
            registro = null;
            jvBorradorDecidirSobre(pendiente, form);
        });
    }

    jvBorradorEscuchar(form);
}

/**
 * Activa los borradores en todos los formularios marcados de la página.
 *
 * @returns {void}
 */
function jvBorradorActivar() {
    if (!document.querySelectorAll) return;
    var formularios = document.querySelectorAll('form[data-borrador]');
    for (var i = 0; i < formularios.length; i++) jvBorradorPreparar(formularios[i]);
}

// Este fichero se carga en el <head>: los borradores se activan al estar el
// DOM listo, después de los listeners de los módulos (fase burbuja).
document.addEventListener('DOMContentLoaded', function() {
    jvBorradorActivar();
});
