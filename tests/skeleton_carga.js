// ==========================================
// PRUEBA: carga visible — skeletons con tope de espera
// ==========================================
// Verifica los helpers de diseno.js que evitan las esperas "infinitas":
//  - jvSkeletonHtml: variantes de skeleton (línea, kpi, fila, gráfico).
//  - jvCargaIniciar/fin: overlay con gracia (no parpadea en respuestas
//    rápidas), limpieza completa y protección contra doble inicio.
//  - jvCargaTarde: convierte el skeleton en "está tardando" + Reintentar.
//  - jvFetchConTope: aborta al vencer el tope (error.jvTope) y respeta
//    respuestas a tiempo.
// Además revisa la integración en compras (tasa BCV), estadísticas y el
// backend en paralelo del controlador de tasas.
//
// Ejecutar desde la raíz del proyecto: node tests/skeleton_carga.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const fuente = fs.readFileSync('assets/js/diseno.js', 'utf8');
const css = fs.readFileSync('assets/css/diseno.css', 'utf8');
const compras = fs.readFileSync('assets/modules/compras/compras.js', 'utf8');
const estadisticas = fs.readFileSync('assets/modules/estadisticas/estadisticas.js', 'utf8');
const tasas = fs.readFileSync('controllers/TasasController.php', 'utf8');

let afirmaciones = 0;
function afirmar(condicion, mensaje) {
    afirmaciones++;
    assert.ok(condicion, 'FALLO: ' + mensaje);
}

// ---------- Elemento DOM simulado (solo lo que los helpers tocan) ----------
function elementoFake(etiqueta) {
    const el = {
        etiqueta: etiqueta,
        hijos: [],
        escuchas: {},
        atributos: {},
        texto: '',
        padre: null,
        className: '',
        classList: {
            clases: new Set(),
            add: (...c) => c.forEach(x => el.classList.clases.add(x)),
            remove: (...c) => c.forEach(x => el.classList.clases.delete(x)),
            contains: (c) => el.classList.clases.has(c)
        },
        setAttribute: (k, v) => { el.atributos[k] = String(v); },
        getAttribute: (k) => (k in el.atributos ? el.atributos[k] : null),
        removeAttribute: (k) => { delete el.atributos[k]; },
        appendChild: (hijo) => { hijo.padre = el; el.hijos.push(hijo); return hijo; },
        addEventListener: (ev, cb) => { (el.escuchas[ev] = el.escuchas[ev] || []).push(cb); },
        remove: () => { if (el.padre) el.padre.hijos = el.padre.hijos.filter(h => h !== el); }
    };
    return el;
}

const escuchasVentana = [];
const sandbox = {
    console: { error: () => {}, warn: () => {}, log: () => {} },
    setTimeout, clearTimeout,
    AbortController,
    document: {
        addEventListener: () => {},
        createElement: (etiqueta) => elementoFake(etiqueta)
    },
    window: {
        addEventListener: (ev, cb) => { if (ev === 'error' || ev === 'unhandledrejection') escuchasVentana.push(ev); },
        fetch: undefined
    }
};
vm.runInNewContext(fuente, sandbox);

const { jvSkeletonHtml, jvCargaIniciar, jvCargaTarde, jvFetchConTope } = sandbox;

(async () => {
    // ---------- 1. jvSkeletonHtml: variantes ----------
    afirmar(jvSkeletonHtml('linea').includes('jv-skel-linea'), 'La variante linea produce su clase.');
    afirmar(jvSkeletonHtml('kpi').includes('jv-skel-kpi'), 'La variante kpi produce su clase.');
    afirmar(jvSkeletonHtml('grafico').includes('jv-skel-grafico'), 'La variante grafico produce su clase.');
    afirmar(jvSkeletonHtml('desconocida').includes('jv-skel-linea'), 'Una variante desconocida cae en linea (seguro).');

    // ---------- 2. Gracia: respuesta rápida no dibuja overlay ----------
    {
        const contenedor = elementoFake('div');
        const token = jvCargaIniciar(contenedor, { trasMs: 30 });
        token.fin();
        await new Promise(r => setTimeout(r, 60));
        afirmar(contenedor.hijos.length === 0, 'Cancelar antes de la gracia no dibuja skeleton (sin parpadeo).');
        afirmar(!contenedor.classList.contains('jv-cargando'), 'Cancelar antes de la gracia deja el contenedor limpio.');
    }

    // ---------- 3. Overlay inmediato y por variante ----------
    {
        const contenedor = elementoFake('div');
        const token = jvCargaIniciar(contenedor, { trasMs: 0, variante: 'kpi' });
        afirmar(contenedor.classList.contains('jv-cargando'), 'Iniciar marca el contenedor como cargando.');
        afirmar(contenedor.atributos['aria-busy'] === 'true', 'Iniciar declara aria-busy (accesibilidad).');
        afirmar(contenedor.hijos.length === 1 && contenedor.hijos[0].className === 'jv-skel-overlay', 'Iniciar crea el overlay dentro del contenedor.');
        afirmar(token.overlay.hijos.length === 1 && token.overlay.hijos[0].className.includes('jv-skel-kpi'), 'La variante kpi dibuja una pieza kpi.');

        const grafico = elementoFake('div');
        jvCargaIniciar(grafico, { trasMs: 0, variante: 'grafico' });
        afirmar(grafico.hijos[0].hijos[0].className.includes('jv-skel-grafico'), 'La variante grafico dibuja su pieza.');

        const filas = elementoFake('div');
        jvCargaIniciar(filas, { trasMs: 0 });
        afirmar(filas.hijos[0].hijos.length === 3, 'Sin variante se dibujan 3 filas (por defecto).');

        // Doble inicio: el segundo token no debe duplicar overlay.
        const segundo = jvCargaIniciar(contenedor, { trasMs: 0 });
        afirmar(contenedor.hijos.length === 1, 'Iniciar dos veces no duplica el overlay.');
        segundo.fin(); // Inerte: no rompe nada.

        token.fin();
        afirmar(contenedor.hijos.length === 0, 'fin() retira el overlay.');
        afirmar(!contenedor.classList.contains('jv-cargando') && !('aria-busy' in contenedor.atributos), 'fin() limpia clase y aria-busy.');
    }

    // ---------- 4. jvCargaTarde: mensaje + Reintentar ----------
    {
        const contenedor = elementoFake('div');
        let reintentado = 0;
        const token = jvCargaIniciar(contenedor, { trasMs: 0 });
        jvCargaTarde(contenedor, 'Esto está tardando más de lo normal.', () => { reintentado++; });
        const overlay = contenedor.hijos[0];
        afirmar(overlay.classList.contains('jv-skel-tarde'), 'El estado tarde marca el overlay.');
        afirmar(overlay.hijos.length === 2, 'El estado tarde deja mensaje + botón (sin piezas de skeleton).');
        const boton = overlay.hijos[1];
        afirmar(boton.etiqueta === 'button' && boton.textContent === 'Reintentar', 'El botón Reintentar se crea con su texto.');
        (boton.escuchas.click || []).forEach(cb => cb());
        afirmar(reintentado === 1, 'Pulsar Reintentar ejecuta la acción dada.');
        token.fin();
    }

    // jvCargaTarde sin carga previa también funciona (dibuja overlay al momento).
    {
        const contenedor = elementoFake('div');
        jvCargaTarde(contenedor, 'Sin carga previa.', () => {});
        afirmar(contenedor.hijos.length === 1 && contenedor.hijos[0].classList.contains('jv-skel-tarde'), 'Tarde sin carga previa dibuja el overlay al momento.');
    }

    // ---------- 5. jvFetchConTope: aborta al vencer, respeta a tiempo ----------
    {
        let recibioSignal = false;
        const rechazo = await jvFetchConTope('http://prueba', {
            ms: 25,
            fetchImpl: (url, init) => {
                recibioSignal = !!(init && init.signal);
                afirmar(init.headers['X-Requested-With'] === 'XMLHttpRequest', 'El tope envía el header AJAX por defecto.');
                // Un fetch real rechaza al abortar: el simulador lo honra igual.
                return new Promise((resolver, rechazar) => {
                    init.signal.addEventListener('abort', () => {
                        const errorAborte = new Error('Aborted');
                        errorAborte.name = 'AbortError';
                        rechazar(errorAborte);
                    });
                });
            }
        }).then(() => null).catch(e => e);
        afirmar(recibioSignal, 'El tope conecta la señal de aborto al fetch.');
        afirmar(rechazo && rechazo.jvTope === true, 'Al vencer el tope, el error queda marcado con jvTope.');
    }
    {
        const respuesta = await jvFetchConTope('http://prueba', {
            ms: 1000,
            fetchImpl: () => Promise.resolve({ ok: true })
        });
        afirmar(respuesta && respuesta.ok === true, 'Una respuesta a tiempo atraviesa el tope sin marca.');
    }

    // ---------- 6. Integraciones en los módulos ----------
    afirmar(/\.jv-skel\b/.test(css) && css.includes('jv-skel-brillo') && css.includes('prefers-reduced-motion'), 'El CSS global define el skeleton, su animación y el fallback de movimiento reducido.');
    afirmar(compras.includes('jvFetchConTope') && compras.includes('jvSkeletonHtml'), 'La tasa BCV usa el skeleton y el tope de espera.');
    afirmar(compras.includes('BCV no responde ahora. Digita la tasa manualmente.'), 'El tope de la tasa activa la edición manual con aviso claro.');
    afirmar(estadisticas.includes('jvCargaIniciar') && estadisticas.includes('jvFetchConTope'), 'El refresh de estadísticas usa skeletons y tope.');
    afirmar(estadisticas.includes('Esto está tardando más de lo normal.'), 'Estadísticas comunica la espera prolongada con su texto propio.');
    afirmar(tasas.includes('curl_multi_init') && tasas.includes('CURLOPT_TIMEOUT') && !tasas.includes('stream_context_create'), 'El backend de tasas consulta en paralelo con curl (sin stream_context de serie).');
    afirmar(!compras.includes('Consultando tasa BCV...'), 'El texto colgante antiguo de la tasa ya no existe.');

    console.log('OK skeleton_carga: ' + afirmaciones + ' afirmaciones en verde (skeleton, gracia, tope y tarde).');
})().catch(e => {
    console.error(e && e.message ? e.message : e);
    process.exit(1);
});
