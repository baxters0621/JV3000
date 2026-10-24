const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function crearElemento(id, value = '') {
    const clases = new Set();
    return {
        id,
        value,
        textContent: '',
        disabled: false,
        attributes: {},
        listeners: {},
        classList: {
            add: (...nombres) => nombres.forEach((nombre) => clases.add(nombre)),
            remove: (...nombres) => nombres.forEach((nombre) => clases.delete(nombre)),
            contains: (nombre) => clases.has(nombre)
        },
        addEventListener(evento, listener) {
            this.listeners[evento] = listener;
        },
        setAttribute(nombre, valor) {
            this.attributes[nombre] = valor;
        },
        removeAttribute(nombre) {
            delete this.attributes[nombre];
        },
        focus() {
            this.focused = true;
        }
    };
}

function ejecutarEnvio(confirmacion) {
    const ids = {
        formResetPassword: crearElemento('formResetPassword'),
        reset_id_usuario: crearElemento('reset_id_usuario', '42'),
        reset_usuario_nombre: crearElemento('reset_usuario_nombre'),
        reset_password: crearElemento('reset_password', 'ClaveSegura1!'),
        reset_password_confirm: crearElemento('reset_password_confirm', confirmacion),
        btnResetPassword: crearElemento('btnResetPassword')
    };
    ids.reset_usuario_nombre.textContent = 'Operador';
    ids.formResetPassword.querySelector = function (selector) {
        return selector === '.is-invalid'
            ? [ids.reset_password, ids.reset_password_confirm].find((campo) => campo.classList.contains('is-invalid')) || null
            : null;
    };

    const document = {
        body: {},
        getElementById(id) {
            if (!ids[id] && id.endsWith('_guia')) ids[id] = crearElemento(id);
            return ids[id] || null;
        },
        querySelectorAll() {
            return [];
        },
        addEventListener() {}
    };
    let envio = null;
    let prevenido = false;
    const contexto = {
        document,
        window: { JV_CONFIG: { csrfToken: 'csrf-piloto' } },
        MutationObserver: class {
            observe() {}
        },
        bootstrap: { Modal: { getOrCreateInstance() { return { show() {} }; } } },
        Swal: {
            fire() {
                return { then(callback) { callback({ isConfirmed: true }); } };
            }
        },
        jvPost(params, url) {
            envio = { params, url };
        }
    };

    const script = fs.readFileSync(
        path.join(__dirname, '..', 'assets', 'dashboard', 'usuarios.js'),
        'utf8'
    );
    vm.runInNewContext(script, contexto);
    ids.formResetPassword.listeners.submit({
        preventDefault() {
            prevenido = true;
        }
    });

    return { envio, prevenido, ids };
}

const envioValido = ejecutarEnvio('ClaveSegura1!');
assert.equal(envioValido.prevenido, true);
assert.deepEqual(
    JSON.parse(JSON.stringify(envioValido.envio)),
    {
        params: {
            accion_usuario: 'restablecer_password',
            id_usuario: '42',
            password: 'ClaveSegura1!',
            password_confirm: 'ClaveSegura1!',
            csrf_token: 'csrf-piloto'
        },
        url: 'index.php?url=usuarios'
    }
);

const envioInvalido = ejecutarEnvio('ClaveDistinta1!');
assert.equal(envioInvalido.prevenido, true);
assert.equal(envioInvalido.envio, null);
assert.equal(envioInvalido.ids.reset_password_confirm.classList.contains('is-invalid'), true);
assert.equal(envioInvalido.ids.reset_password_confirm.focused, true);

console.log('OK envío y validación del formulario de restablecimiento');
