const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const vista = fs.readFileSync(
    path.join(__dirname, '..', 'views', 'usuarios', 'index.php'),
    'utf8'
);
['longitud', 'minuscula', 'mayuscula', 'numero', 'simbolo', 'sin_saltos', 'sin_espacios'].forEach((regla) => {
    assert.match(vista, new RegExp(`id="reset_password_regla_${regla}"`));
});
assert.doesNotMatch(vista, /id="reset_password_regla_maximo"/);
assert.match(vista, /id="reset_password_contador"[^>]*>0 de 18 caracteres válidos</);
assert.match(fs.readFileSync(path.join(__dirname, '..', 'assets', 'dashboard', 'usuarios.css'), 'utf8'), /\.password-requirement\.invalido \{[^}]*color:#b91c1c/s);
assert.match(vista, /aria-describedby="reset_password_guia reset_password_estado reset_password_contador"/);
assert.match(vista, /id="reset_password"[^>]*maxlength="36"/);
assert.match(vista, /id="reset_password_confirm"[^>]*maxlength="36"/);
assert.match(vista, /id="crear_password"[^>]*maxlength="36"/);
assert.match(vista, /id="crear_password_confirm"[^>]*maxlength="36"/);
assert.match(vista, /id="crear_password_guia"[^>]*>[^<]*sin espacios/);
assert.match(vista, /id="modalCrear"[\s\S]*class="modal-body modal-usuarios-body"/);
['crear_usuario', 'crear_correo', 'crear_password', 'crear_password_confirm', 'crear_rol'].forEach((id) => {
    assert.match(vista, new RegExp(`id="${id}"[^>]*required`));
});
assert.match(vista, /create-collab-intro/);
assert.match(vista, /create-collab-section/);
assert.match(vista, /create-collab-note/);
const estilosUsuarios = fs.readFileSync(path.join(__dirname, '..', 'assets', 'dashboard', 'usuarios.css'), 'utf8');
assert.match(estilosUsuarios, /#modalCrear \.create-collab-intro \{/);
assert.match(estilosUsuarios, /#modalCrear \.create-collab-section-title h3 \{/);
assert.match(estilosUsuarios, /\.modal-usuarios \.input-jv \{/);
assert.doesNotMatch(estilosUsuarios, /\.pagina-usuarios #modalCrear \.create-collab/);

const registro = fs.readFileSync(path.join(__dirname, '..', 'login', 'login.php'), 'utf8');
assert.match(registro, /id="r-pass"[^>]*maxlength="36"/);
assert.match(registro, /id="r-pass2"[^>]*maxlength="36"/);

const recuperacion = fs.readFileSync(path.join(__dirname, '..', 'login', 'recuperar.php'), 'utf8');
assert.match(recuperacion, /id="rec-pass"[^>]*maxlength="36"/);
assert.match(recuperacion, /id="rec-pass2"[^>]*maxlength="36"/);

const perfil = fs.readFileSync(path.join(__dirname, '..', 'views', 'perfil', 'index.php'), 'utf8');
assert.match(perfil, /id="perfil_password_nueva"[^>]*maxlength="36"/);
assert.match(perfil, /id="perfil_password_confirm"[^>]*maxlength="36"/);
const estilos = fs.readFileSync(path.join(__dirname, '..', 'assets', 'dashboard', 'usuarios.css'), 'utf8');
assert.match(estilos, /\.modal-usuarios \.password-requirement \{[^}]*font-weight:700/s);
assert.match(estilos, /\.modal-usuarios \.password-character-count \{[^}]*font-weight:700/s);
assert.match(
    fs.readFileSync(path.join(__dirname, '..', 'assets', 'dashboard', 'usuarios.js'), 'utf8'),
    /const MAX_PASSWORD_CHARACTERS = 18;/
);

function crearElemento(id, value = '') {
    const clases = new Set();
    return {
        id,
        value,
        type: id.startsWith('reset_password') ? 'password' : '',
        textContent: '',
        disabled: false,
        attributes: {},
        listeners: {},
        classList: {
            add: (...nombres) => nombres.forEach((nombre) => clases.add(nombre)),
            remove: (...nombres) => nombres.forEach((nombre) => clases.delete(nombre)),
            contains: (nombre) => clases.has(nombre),
            toggle: (nombre, activo) => {
                if (activo) clases.add(nombre);
                else clases.delete(nombre);
            }
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
        setSelectionRange(inicio, fin) {
            this.selectionStart = inicio;
            this.selectionEnd = fin;
        },
        focus() {
            this.focused = true;
        }
    };
}

function ejecutarAlta(valores, filas = []) {
    const ids = {
        formCrear: crearElemento('formCrear'),
        crear_usuario: crearElemento('crear_usuario', valores.usuario || ''),
        crear_correo: crearElemento('crear_correo', valores.correo || ''),
        crear_password: crearElemento('crear_password', valores.password || ''),
        crear_password_confirm: crearElemento('crear_password_confirm', valores.confirmar || ''),
        crear_rol: crearElemento('crear_rol', valores.rol || ''),
        btnCrear: crearElemento('btnCrear')
    };
    ids.crear_password.type = 'password';
    ids.crear_password_confirm.type = 'password';
    ids.crear_rol.options = ['2', '3'].map((value) => ({ value, disabled: false }));
    ['crear_usuario', 'crear_correo', 'crear_password', 'crear_password_confirm', 'crear_rol'].forEach((id) => {
        ids[`${id}_guia`] = crearElemento(`${id}_guia`);
    });
    ids.formCrear.querySelector = (selector) => selector === '.is-invalid'
        ? Object.values(ids).find((campo) => campo.classList && campo.classList.contains('is-invalid')) || null
        : null;

    const document = {
        body: {},
        getElementById(id) {
            if (!ids[id] && id.endsWith('_guia')) ids[id] = crearElemento(id);
            return ids[id] || null;
        },
        querySelectorAll(selector) {
            return selector === '#cuerpoUsuarios tr.usuario-fila' ? filas : [];
        },
        addEventListener() {}
    };
    let envio = null;
    const contexto = {
        document,
        window: { JV_CONFIG: { csrfToken: 'csrf-piloto' } },
        TextEncoder,
        MutationObserver: class { observe() {} },
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
    let prevenido = false;
    ids.formCrear.listeners.submit({
        preventDefault() {
            prevenido = true;
        }
    });
    return { envio, prevenido, ids };
}

function ejecutarEnvio(confirmacion, password = 'ClaveSegura1!') {
    const ids = {
        formResetPassword: crearElemento('formResetPassword'),
        reset_id_usuario: crearElemento('reset_id_usuario', '42'),
        reset_usuario_nombre: crearElemento('reset_usuario_nombre'),
        reset_password: crearElemento('reset_password', password),
        reset_password_confirm: crearElemento('reset_password_confirm', confirmacion),
        btnResetPassword: crearElemento('btnResetPassword'),
        reset_password_estado: crearElemento('reset_password_estado')
    };
        ids.reset_password_contador = crearElemento('reset_password_contador');
    const textosReglas = {
        longitud: '8 caracteres como mínimo',
        minuscula: 'Una letra minúscula',
        mayuscula: 'Una letra mayúscula',
        numero: 'Un número',
        simbolo: 'Un símbolo',
        sin_saltos: 'Sin saltos de línea',
        sin_espacios: 'Sin espacios'
    };
    Object.entries(textosReglas).forEach(([regla, texto]) => {
        ids[`reset_password_regla_${regla}`] = crearElemento(`reset_password_regla_${regla}`);
        ids[`reset_password_regla_${regla}`].textContent = texto;
    });
    ids.reset_password_confirm_guia = crearElemento('reset_password_confirm_guia');
    ids.reset_password_confirm_guia.textContent = 'Repite la nueva contraseña.';
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
        TextEncoder,
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
    if (ids.reset_password.listeners.input) {
        ids.reset_password.listeners.input({ type: 'input' });
    }
    ids.formResetPassword.listeners.submit({
        preventDefault() {
            prevenido = true;
        }
    });

    return { envio, prevenido, ids };
}

const altaValida = ejecutarAlta({
    usuario: 'Nuevo_Operador',
    correo: 'nuevo@empresa.com',
    password: 'Abc12345!',
    confirmar: 'Abc12345!',
    rol: '2'
});
assert.equal(altaValida.prevenido, true);
assert.deepEqual(
    JSON.parse(JSON.stringify(altaValida.envio)),
    {
        params: {
            accion_usuario: 'crear',
            usuario: 'Nuevo_Operador',
            correo: 'nuevo@empresa.com',
            password: 'Abc12345!',
            password_confirm: 'Abc12345!',
            id_rol: '2',
            csrf_token: 'csrf-piloto'
        },
        url: 'index.php?url=usuarios'
    }
);

const altaCorreoInvalido = ejecutarAlta({
    usuario: 'Nuevo_Operador',
    correo: 'nombre..apellido@empresa.com',
    password: 'Abc12345!',
    confirmar: 'Abc12345!',
    rol: '2'
});
assert.equal(altaCorreoInvalido.envio, null);
assert.equal(altaCorreoInvalido.ids.crear_correo.classList.contains('is-invalid'), true);

const altaConfirmacionDistinta = ejecutarAlta({
    usuario: 'Nuevo_Operador',
    correo: 'nuevo@empresa.com',
    password: 'Abc12345!',
    confirmar: 'Abc12345?',
    rol: '2'
});
assert.equal(altaConfirmacionDistinta.envio, null);
assert.equal(altaConfirmacionDistinta.ids.crear_password_confirm.classList.contains('is-invalid'), true);

const filaUsuarioDuplicado = {
    dataset: { id: '7', usuario: 'Nuevo_Operador', correo: 'otro@empresa.com' }
};
const altaUsuarioDuplicado = ejecutarAlta({
    usuario: 'Nuevo_Operador',
    correo: 'nuevo@empresa.com',
    password: 'Abc12345!',
    confirmar: 'Abc12345!',
    rol: '2'
}, [filaUsuarioDuplicado]);
assert.equal(altaUsuarioDuplicado.envio, null);
assert.equal(altaUsuarioDuplicado.ids.crear_usuario.classList.contains('is-invalid'), true);

const filaCorreoDuplicado = {
    dataset: { id: '8', usuario: 'Otro_Usuario', correo: 'NUEVO@EMPRESA.COM' }
};
const altaCorreoDuplicado = ejecutarAlta({
    usuario: 'Nuevo_Operador',
    correo: 'nuevo@empresa.com',
    password: 'Abc12345!',
    confirmar: 'Abc12345!',
    rol: '2'
}, [filaCorreoDuplicado]);
assert.equal(altaCorreoDuplicado.envio, null);
assert.equal(altaCorreoDuplicado.ids.crear_correo.classList.contains('is-invalid'), true);

const altaRolAdmin = ejecutarAlta({
    usuario: 'Nuevo_Operador',
    correo: 'nuevo@empresa.com',
    password: 'Abc12345!',
    confirmar: 'Abc12345!',
    rol: '1'
});
assert.equal(altaRolAdmin.envio, null);
assert.equal(altaRolAdmin.ids.crear_rol.classList.contains('is-invalid'), true);

const altaPasswordLarga = ejecutarAlta({
    usuario: 'Nuevo_Operador',
    correo: 'nuevo@empresa.com',
    password: 'Abc12345!1234567890',
    confirmar: 'Abc12345!1234567890',
    rol: '2'
});
assert.equal(altaPasswordLarga.envio, null);
assert.equal(altaPasswordLarga.ids.crear_password.classList.contains('is-invalid'), true);
assert.match(altaPasswordLarga.ids.crear_password_guia.textContent, /18 caracteres/);

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

const envioConEspacios = ejecutarEnvio(' ClaveSegura1! ', ' ClaveSegura1! ');
assert.equal(envioConEspacios.envio, null);
assert.equal(envioConEspacios.ids.reset_password.classList.contains('is-invalid'), true);
assert.equal(envioConEspacios.ids.reset_password_regla_sin_espacios.classList.contains('invalido'), true);

const envioConSalto = ejecutarEnvio('', 'ClaveSegura1!\n');
assert.equal(envioConSalto.envio, null);
assert.equal(envioConSalto.ids.reset_password.value, 'ClaveSegura1!\n');

const entradaMuyLarga = ejecutarEnvio('', 'aA1!' + 'b'.repeat(80));
entradaMuyLarga.ids.reset_password.listeners.input({ type: 'input' });
assert.equal(
    Array.from(entradaMuyLarga.ids.reset_password.value).length,
    18,
    'La nueva contraseña debe limitarse a 18 caracteres al escribir o pegar.'
);
assert.equal(entradaMuyLarga.ids.reset_password_contador.textContent, '18 de 18 caracteres válidos');

const entradaUnicodeLarga = ejecutarEnvio('', 'aA1!' + 'ñ'.repeat(40));
entradaUnicodeLarga.ids.reset_password.listeners.input({ type: 'input' });
assert.ok(
    Array.from(entradaUnicodeLarga.ids.reset_password.value).length <= 18,
    'El límite debe contar caracteres multibyte como caracteres.'
);
assert.equal(
    Array.from(entradaUnicodeLarga.ids.reset_password.value).join(''),
    entradaUnicodeLarga.ids.reset_password.value,
    'El límite no debe dejar un par sustituto Unicode incompleto.'
);
entradaUnicodeLarga.ids.reset_password_confirm.value = 'aA1!' + 'ñ'.repeat(40);
entradaUnicodeLarga.ids.reset_password_confirm.listeners.input({ type: 'input' });
assert.ok(
    Array.from(entradaUnicodeLarga.ids.reset_password_confirm.value).length <= 18,
    'La confirmación también debe limitarse a 18 caracteres.'
);

const envioInvalido = ejecutarEnvio('ClaveDistinta1!');
assert.equal(envioInvalido.prevenido, true);
assert.equal(envioInvalido.envio, null);
assert.equal(envioInvalido.ids.reset_password_confirm.classList.contains('is-invalid'), true);
assert.equal(envioInvalido.ids.reset_password_confirm.focused, true);

const validacionEnVivo = ejecutarEnvio('', '4444444444444444');
validacionEnVivo.ids.reset_password.listeners.input({ type: 'input' });
assert.equal(
    validacionEnVivo.ids.reset_password_confirm.classList.contains('is-invalid'),
    false,
    'La confirmación vacía debe permanecer neutra mientras se escribe la contraseña nueva.'
);
assert.equal(
    validacionEnVivo.ids.reset_password_confirm_guia.textContent,
    'Repite la nueva contraseña.',
    'La guía de confirmación vacía no debe afirmar que las contraseñas son distintas.'
);
assert.equal(validacionEnVivo.ids.reset_password_regla_longitud.classList.contains('cumplido'), true);
assert.equal(validacionEnVivo.ids.reset_password_regla_numero.classList.contains('cumplido'), true);
assert.equal(validacionEnVivo.ids.reset_password_regla_minuscula.classList.contains('cumplido'), false);
assert.equal(validacionEnVivo.ids.reset_password_regla_mayuscula.classList.contains('cumplido'), false);
assert.equal(validacionEnVivo.ids.reset_password_regla_simbolo.classList.contains('cumplido'), false);
assert.match(validacionEnVivo.ids.reset_password_estado.textContent, /Una letra mayúscula/);

const campoVacio = ejecutarEnvio('', '');
campoVacio.ids.reset_password.listeners.input({ type: 'input' });
assert.equal(campoVacio.ids.reset_password_contador.textContent, '0 de 18 caracteres válidos');
assert.equal(campoVacio.ids.reset_password_regla_longitud.classList.contains('cumplido'), false);

const passwordConEspacio = ejecutarEnvio('Clave Valida1!', 'Clave Valida1!');
passwordConEspacio.ids.reset_password.listeners.input({ type: 'input' });
assert.equal(passwordConEspacio.ids.reset_password.classList.contains('is-invalid'), true);
assert.equal(passwordConEspacio.ids.reset_password_regla_sin_espacios.classList.contains('invalido'), true);
['longitud', 'minuscula', 'mayuscula', 'numero', 'simbolo', 'sin_saltos', 'sin_espacios'].forEach((regla) => {
    assert.equal(
        passwordConEspacio.ids[`reset_password_regla_${regla}`].classList.contains('cumplido'),
        false,
        'No se debe marcar ningún requisito como cumplido mientras haya espacios.'
    );
});
assert.equal(passwordConEspacio.ids.reset_password_contador.textContent, '13 de 18 caracteres válidos');
assert.match(passwordConEspacio.ids.reset_password_estado.textContent, /Sin espacios/);
assert.equal(passwordConEspacio.envio, null);

const espacioComoSimbolo = ejecutarEnvio('', 'Abc12345 ');
espacioComoSimbolo.ids.reset_password.listeners.input({ type: 'input' });
assert.match(espacioComoSimbolo.ids.reset_password_estado.textContent, /Un símbolo/);
assert.match(espacioComoSimbolo.ids.reset_password_estado.textContent, /Sin espacios/);
assert.equal(espacioComoSimbolo.ids.reset_password_regla_simbolo.classList.contains('cumplido'), false);
assert.equal(espacioComoSimbolo.ids.reset_password_regla_sin_espacios.classList.contains('invalido'), true);
assert.match(
    fs.readFileSync(path.join(__dirname, '..', 'assets', 'login', 'login.js'), 'utf8'),
    /passwordHasWhitespace/
);
assert.match(
    fs.readFileSync(path.join(__dirname, '..', 'assets', 'login', 'recuperar.js'), 'utf8'),
    /passwordHasWhitespace/
);
assert.match(
    fs.readFileSync(path.join(__dirname, '..', 'assets', 'dashboard', 'perfil.js'), 'utf8'),
    /No se permiten espacios/
);

const confirmacionConEspacio = ejecutarEnvio('ClaveSegura1! ', 'ClaveSegura1!');
assert.equal(confirmacionConEspacio.envio, null);
assert.equal(confirmacionConEspacio.ids.reset_password_confirm.classList.contains('is-invalid'), true);
assert.match(confirmacionConEspacio.ids.reset_password_confirm_guia.textContent, /No se permiten espacios/);

const espaciosNoSonVacio = ejecutarEnvio('', '        ');
assert.equal(espaciosNoSonVacio.ids.reset_password.classList.contains('is-invalid'), true);
assert.equal(espaciosNoSonVacio.envio, null);

console.log('OK validación de alta y restablecimiento de colaborador');
