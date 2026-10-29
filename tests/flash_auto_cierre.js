// ==========================================
// PRUEBA: auto-cierre de los mensajes flash del panel
// ==========================================
// diseno.js se carga en el <head>, así que su lógica debe esperar a
// DOMContentLoaded; y debe aplicar la salida del CSS (.jv-flash-out) y
// retirar el nodo. Este bloque no existía —historial.js y perfil.js
// apuntaban a diseno.js— y por eso el mensaje se quedaba pegado en
// pantalla en todos los módulos.
//
// Ejecutar desde la raíz del proyecto: node tests/flash_auto_cierre.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/diseno.js', 'utf8');
assert.match(source, /\.flash-auto/, 'diseno.js debe encargarse de los mensajes .flash-auto del panel.');

const listeners = [];
const listenersVentana = [];
const temporizadores = [];

// Mensaje simulado: solo lo que el código real toca (classList y remove).
function mensajeFake() {
    const el = { clases: new Set(['alert-jv', 'alert-jv-success', 'flash-auto']), salida: false, eliminado: false };
    el.classList = {
        add: (clase) => { if (clase === 'jv-flash-out') el.salida = true; el.clases.add(clase); },
        contains: (clase) => el.clases.has(clase)
    };
    el.remove = () => { el.eliminado = true; };
    return el;
}

const mensajes = [mensajeFake(), mensajeFake()];

const sandbox = {
    document: {
        addEventListener: (evento, cb) => { if (evento === 'DOMContentLoaded') listeners.push(cb); },
        querySelectorAll: (selector) => (selector === '.flash-auto' ? mensajes : []),
        createElement: () => ({ style: {}, appendChild: () => {}, submit: () => {} }),
        body: { appendChild: () => {} }
    },
    window: {
        setTimeout: (cb, ms) => { temporizadores.push({ cb, ms }); return temporizadores.length; },
        clearTimeout: () => {},
        location: { href: '' },
        addEventListener: (evento, cb) => {
            if (evento === 'error' || evento === 'unhandledrejection') listenersVentana.push({ evento, cb });
        }
    },
    console
};

vm.runInNewContext(source, sandbox);

assert.equal(listeners.length, 2, 'Debe esperar a DOMContentLoaded: el fichero se carga en el <head> (flashes y borradores).');
assert.equal(
    listenersVentana.map((l) => l.evento).sort().join(','),
    'error,unhandledrejection',
    'El límite de errores del navegador debe registrar sus dos escuchas (error y unhandledrejection).'
);
listeners[0]();

assert.equal(temporizadores.length, mensajes.length, 'Cada mensaje debe programar su propia salida.');
assert.equal(temporizadores[0].ms, 4000, 'La salida debe coincidir con la barra de cuenta atrás del CSS (4 s).');

// Cada mensaje tiene su propio ciclo: primero su salida a los 4 s…
temporizadores[0].cb();
temporizadores[1].cb();
assert.deepEqual(
    mensajes.map((m) => m.salida),
    [true, true],
    'Debe aplicarse la clase de salida .jv-flash-out a cada mensaje.'
);
assert.deepEqual(
    mensajes.map((m) => m.eliminado),
    [false, false],
    'No debe borrarse antes de que termine la animación.'
);

// …y después su borrado a los 500 ms.
const borrados = temporizadores.filter((t) => t.ms === 500);
assert.equal(borrados.length, mensajes.length, 'Debe programarse un borrado por mensaje tras la animación.');
borrados.forEach((t) => t.cb());
assert.ok(mensajes.every((m) => m.eliminado), 'Todos los mensajes deben retirarse del DOM.');

console.log('OK flash_auto_cierre: los .flash-auto se cierran solos a los 4 s y desaparecen del DOM.');
