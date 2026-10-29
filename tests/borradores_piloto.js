// ==========================================
// PRUEBA: borradores de formularios (data-borrador)
// ==========================================
// Verifica el módulo jvBorrador de diseno.js:
//  - Lista de exclusión: contraseñas, PINs, códigos, tokens, csrf_token y
//    productos_data jamás viajan a localStorage.
//  - Serializar/Restaurar: textos, radios, checkboxes y selects.
//  - Guardar/Leer: usuario atado al id, caducidad de 24 h, JSON corrupto,
//    preservación de la marca "enviado".
//  - Marca de envío nativo: solo cuando el submit no fue prevenido.
//  - DecidirSobre: purga con éxito, aviso automático en fallo, pregunta con
//    Swal (restaurar / descartar / esc), cola de preguntas.
//  - Preparar: decisión diferida hasta shown.bs.modal en formularios ocultos.
// Además revisa la integración en las vistas (data-borrador, JV_CONFIG.userId
// e inclusión de diseno.js en login y recuperar).
//
// Ejecutar desde la raíz del proyecto: node tests/borradores_piloto.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const fuente = fs.readFileSync('assets/js/diseno.js', 'utf8');

let afirmaciones = 0;
function afirmar(condicion, mensaje) {
    afirmaciones++;
    assert.ok(condicion, 'FALLO: ' + mensaje);
}

// ---------- Elementos y formularios simulados (solo lo que el módulo toca) ----------
function campoFake(nombre, tipo, valor, extra) {
    const c = Object.assign({
        name: nombre,
        type: tipo,
        value: valor === undefined ? '' : valor,
        checked: false,
        disabled: false,
        options: null
    }, extra || {});
    // Emula al <select> real: asignar un valor que no existe en sus opciones
    // no cambia la selección.
    if (c.options) {
        let interno = valor === undefined ? '' : valor;
        Object.defineProperty(c, 'value', {
            get: function() { return interno; },
            set: function(v) {
                const existe = c.options.some(function(o) { return String(o.value) === String(v); });
                if (existe) interno = v;
            },
            configurable: true
        });
    }
    return c;
}

// Constrye el coleccionable form.elements: acceso por índice (length incluido)
// y acceso por nombre; los nombres repetidos (radios/checkboxes) dan una
// colección tipo RadioNodeList ({length, 0, 1, ...} y sin .options).
function construirElements(campos) {
    const elementos = [];
    const conteo = {};
    const grupos = {};
    campos.forEach(function(c) {
        if (c.name) conteo[c.name] = (conteo[c.name] || 0) + 1;
    });
    campos.forEach(function(c) {
        elementos.push(c);
        if (!c.name) return;
        if (conteo[c.name] > 1) {
            if (!grupos[c.name]) {
                grupos[c.name] = { length: 0 };
                elementos[c.name] = grupos[c.name];
            }
            const grupo = grupos[c.name];
            grupo[grupo.length] = c;
            grupo.length++;
        } else {
            elementos[c.name] = c;
        }
    });
    return elementos;
}

function formFake(clave, campos, visible) {
    const form = {
        offsetParent: visible === false ? null : {},
        escuchas: {},
        campos: campos,
        elements: construirElements(campos),
        addEventListener: function(ev, cb) { (form.escuchas[ev] = form.escuchas[ev] || []).push(cb); },
        getAttribute: function(k) { return k === 'data-borrador' ? clave : null; },
        contains: function(nodo) { return form.campos.indexOf(nodo) !== -1 || nodo === form; }
    };
    return form;
}

function almacenFake() {
    const pares = new Map();
    return {
        pares: pares,
        getItem: function(k) { return pares.has(k) ? pares.get(k) : null; },
        setItem: function(k, v) { pares.set(k, String(v)); },
        removeItem: function(k) { pares.delete(k); }
    };
}

// ---------- Sandbox: documento, ventana, Swal y temporizadores síncronos ----------
const doc = {
    escuchas: {},
    addEventListener: function(ev, cb) { (doc.escuchas[ev] = doc.escuchas[ev] || []).push(cb); },
    querySelector: function() { return null; },
    querySelectorAll: function() { return []; },
    createElement: function() { return { appendChild: function() {}, style: {}, remove: function() {} }; }
};

const escuchasVentana = [];
const Swal = {
    llamadas: [],
    resultado: { isConfirmed: true },
    fire: function(config) {
        Swal.llamadas.push(config);
        const r = typeof Swal.resultado === 'function' ? Swal.resultado(config) : Swal.resultado;
        return Promise.resolve(r);
    }
};

const sandbox = {
    console: { error: function() {}, warn: function() {}, log: function() {} },
    setTimeout: function(cb) { cb(); return { jvSync: true }; }, // síncrono: pruebas deterministas
    clearTimeout: function() {},
    document: doc,
    window: {
        addEventListener: function(ev) { escuchasVentana.push(ev); },
        JV_CONFIG: { userId: 7 },
        localStorage: almacenFake()
    },
    Swal: Swal
};
vm.runInNewContext(fuente, sandbox);

const {
    jvBorradorExcluido, jvBorradorSerializar, jvBorradorRestaurar,
    jvBorradorGuardar, jvBorradorLeer, jvBorradorLimpiar,
    jvBorradorHaceTiempo, jvBorradorHaceTexto, jvBorradorDecidirSobre,
    jvBorradorPreparar, jvBorradorActivar, jvBorradorLlave
} = sandbox;

// Reinicia el estado del módulo entre escenarios (las "var" de arriba son
// globales del contexto de vm: se pueden reasignar desde el sandbox).
function reiniciar() {
    sandbox.jvBorradorAlmacenEstado = null;
    sandbox.jvBorradorTemporizadores.clear();
    sandbox.jvBorradorCola = Promise.resolve();
    sandbox.window.localStorage = almacenFake();
    sandbox.window.JV_CONFIG.userId = 7;
    doc.querySelector = function() { return null; };
    doc.querySelectorAll = function() { return []; };
    doc.escuchas = {};
    Swal.llamadas = [];
    Swal.resultado = { isConfirmed: true };
}

(async () => {
    // ---------- 0. El módulo existe ----------
    afirmar(typeof jvBorradorExcluido === 'function', 'El módulo jvBorrador quedó definido en diseno.js.');

    // ---------- 1. Lista de exclusión ----------
    const sensibles = ['csrf_token', 'productos_data', 'password', 'password_check', 'pin_emergencia',
        'codigo', 'codigo_proveedor', 'rec_codigo', 'token', 'clave', 'CSRF_TOKEN'];
    sensibles.forEach(function(nombre) {
        afirmar(jvBorradorExcluido(nombre) === true, 'Campo sensible excluido: ' + nombre);
    });
    const normales = ['usuario', 'monto_pago', 'metodo_pago', 'rec_input', 'observaciones',
        'id_solicitud', 'nombre_completo', 'usuario_solicita'];
    normales.forEach(function(nombre) {
        afirmar(jvBorradorExcluido(nombre) === false, 'Campo normal conservado: ' + nombre);
    });

    // ---------- 2. Serializar: excluye, agrupa radios y checkboxes ----------
    {
        reiniciar();
        const form = formFake('prueba', [
            campoFake('csrf_token', 'hidden', 'x'),
            campoFake('productos_data', 'hidden', '[]'),
            campoFake('usuario', 'text', 'jperez'),
            campoFake('password', 'password', 'secreto'),
            campoFake('monto_pago', 'number', '12.50'),
            campoFake('rol', 'radio', 'a', { checked: false }),
            campoFake('rol', 'radio', 'b', { checked: true }),
            campoFake('extras', 'checkbox', '1', { checked: true }),
            campoFake('extras', 'checkbox', '2', { checked: false }),
            campoFake('bloqueado', 'text', 'no', { disabled: true }),
            campoFake('adjunto', 'file', ''),
            campoFake('guardar', 'submit', 'Enviar')
        ]);
        const datos = jvBorradorSerializar(form);
        afirmar(!('csrf_token' in datos) && !('productos_data' in datos) && !('password' in datos),
            'Serializar omite csrf, grilla de productos y contraseña.');
        afirmar(!('bloqueado' in datos) && !('adjunto' in datos) && !('guardar' in datos),
            'Serializar omite campos disabled, file y submit.');
        afirmar(datos.usuario === 'jperez' && datos.monto_pago === '12.50',
            'Serializar guarda textos y números.');
        afirmar(datos.rol === 'b', 'Serializar guarda solo el radio marcado.');
        afirmar(Array.isArray(datos.extras) && datos.extras.length === 1 && datos.extras[0] === '1',
            'Serializar guarda solo los checkboxes marcados.');
    }

    // ---------- 3. Guardar / Leer: ciclo completo y usuario ----------
    {
        reiniciar();
        const form = formFake('compras_alta', [
            campoFake('usuario', 'text', 'jperez'),
            campoFake('csrf_token', 'hidden', 'x')
        ]);
        afirmar(jvBorradorGuardar(form, false) === true, 'Guardar escribe en localStorage.');
        const registro = jvBorradorLeer('compras_alta');
        afirmar(!!registro && registro.v === 1 && registro.clave === 'compras_alta',
            'Leer devuelve el registro con versión y clave.');
        afirmar(registro.usuario === 7, 'El registro queda atado al id de usuario.');
        afirmar(!('csrf_token' in registro.datos), 'El csrf guardado por error en el form no viaja.');
        afirmar(registro.enviado === null, 'Sin envío, la marca enviado nace en null.');

        sandbox.window.JV_CONFIG.userId = 9;
        afirmar(jvBorradorLeer('compras_alta') === null, 'Otro usuario no ve el borrador ajeno.');
        sandbox.window.JV_CONFIG.userId = 7;

        // Caducidad de 24 h
        sandbox.window.localStorage.setItem(jvBorradorLlave('caduco'), JSON.stringify({
            v: 1, clave: 'caduco', usuario: 7, datos: { a: '1' },
            enviado: null, actualizado: Date.now() - 25 * 60 * 60 * 1000
        }));
        afirmar(jvBorradorLeer('caduco') === null, 'Un borrador de hace 25 h caducó.');
        afirmar(sandbox.window.localStorage.getItem(jvBorradorLlave('caduco')) === null,
            'El borrador caducado se elimina del almacenamiento.');

        // JSON corrupto
        sandbox.window.localStorage.setItem(jvBorradorLlave('roto'), '{no-es-json');
        afirmar(jvBorradorLeer('roto') === null, 'Un borrador corrupto se ignora sin romper nada.');

        // Almacenamiento no disponible
        sandbox.window.localStorage = undefined;
        sandbox.jvBorradorAlmacenEstado = null;
        afirmar(jvBorradorGuardar(form, false) === false, 'Sin localStorage, Guardar falla en silencio.');
        afirmar(jvBorradorLeer('compras_alta') === null, 'Sin localStorage, Leer devuelve null.');
    }

    // ---------- 4. Guardar: preservación de la marca enviado ----------
    {
        reiniciar();
        const form = formFake('salidas', [campoFake('motivo', 'text', 'venta')]);
        jvBorradorGuardar(form, true);
        const primero = jvBorradorLeer('salidas');
        afirmar(primero.enviado !== null, 'Marcar enviado estampa la hora del primer envío.');

        // Tecleo posterior no borra la marca...
        form.campos[0].value = 'venta mayor';
        jvBorradorGuardar(form, false);
        afirmar(jvBorradorLeer('salidas').enviado === primero.enviado,
            'El guardado por tecleo preserva la marca enviado.');

        // ...y un segundo intento de envío tampoco la re-estampa.
        jvBorradorGuardar(form, true);
        afirmar(jvBorradorLeer('salidas').enviado === primero.enviado,
            'Marcar enviado dos veces conserva la hora del primero.');
    }

    // ---------- 5. Restaurar: textos, radios, checkboxes y selects ----------
    {
        reiniciar();
        const select = campoFake('metodo_pago', 'select-one', 'efectivo', {
            options: [{ value: 'efectivo' }, { value: 'punto' }]
        });
        const selectRoto = campoFake('metodo_roto', 'select-one', '', {
            options: [{ value: 'efectivo' }]
        });
        const form = formFake('prueba', [
            campoFake('usuario', 'text', ''),
            campoFake('rol', 'radio', 'a'),
            campoFake('rol', 'radio', 'b'),
            campoFake('extras', 'checkbox', '1'),
            campoFake('extras', 'checkbox', '2'),
            select,
            selectRoto,
            campoFake('desaparecido', 'text', '')
        ]);
        const cuenta = jvBorradorRestaurar(form, {
            usuario: 'jperez',
            rol: 'b',
            extras: ['2'],
            metodo_pago: 'punto',
            metodo_roto: 'punto', // opción inexistente: no debe contarse
            campoFantasma: 'x'    // campo que ya no existe en el formulario
        });
        afirmar(form.elements.usuario.value === 'jperez', 'Restaurar devuelve el texto.');
        afirmar(form.elements.rol[1].checked === true && form.elements.rol[0].checked === false,
            'Restaurar marca el radio correcto del grupo.');
        afirmar(form.elements.extras[1].checked === true && form.elements.extras[0].checked === false,
            'Restaurar marca solo el checkbox indicado.');
        afirmar(select.value === 'punto', 'Restaurar selecciona la opción del select.');
        afirmar(selectRoto.value === '', 'Restaurar ignora una opción que ya no existe.');
        afirmar(cuenta === 4, 'Restaurar cuenta solo los campos realmente restaurados (4).');
    }

    // ---------- 6. Marca de envío nativo vs. envío prevenido ----------
    {
        reiniciar();
        const form = formFake('perfil', [campoFake('nombre', 'text', 'ana')]);
        jvBorradorPreparar(form);

        // Envío nativo (el navegador va a navegar): se marca "enviado".
        form.escuchas.submit[0]({ defaultPrevented: false });
        afirmar(jvBorradorLeer('perfil').enviado !== null,
            'Un submit no prevenido queda marcado como enviado.');

        // Envío prevenido (validación o fetch): solo re-guarda, sin marca.
        reiniciar();
        jvBorradorPreparar(form);
        form.escuchas.submit[0]({ defaultPrevented: true });
        afirmar(jvBorradorLeer('perfil').enviado === null,
            'Un submit prevenido (fetch) NO queda marcado como enviado.');
        afirmar(jvBorradorLeer('perfil').datos.nombre === 'ana',
            'El envío prevenido igual guarda el contenido.');

        // Tecleo dispara el guardado con retardo (aquí síncrono).
        reiniciar();
        jvBorradorPreparar(form);
        form.campos[0].value = 'ana luisa';
        form.escuchas.input[0]({});
        afirmar(jvBorradorLeer('perfil').datos.nombre === 'ana luisa',
            'El evento input guarda el borrador (retardo ejecutado).');
    }

    // ---------- 7. DecidirSobre: purga con éxito / aviso en fallo ----------
    {
        reiniciar();
        const form = formFake('perfil', [campoFake('nombre', 'text', 'borrador')]);
        jvBorradorGuardar(form, true); // enviado = ahora

        doc.querySelector = function() { return { clase: 'alert-jv-success' }; };
        jvBorradorDecidirSobre(jvBorradorLeer('perfil'), form);
        afirmar(jvBorradorLeer('perfil') === null,
            'Enviado reciente + mensaje de éxito purga el borrador.');
        afirmar(Swal.llamadas.length === 0, 'Con éxito no se pregunta nada al usuario.');

        reiniciar();
        jvBorradorGuardar(form, true);
        form.elements.nombre.value = ''; // como una página recién cargada
        jvBorradorDecidirSobre(jvBorradorLeer('perfil'), form);
        afirmar(form.elements.nombre.value === 'borrador',
            'Enviado reciente sin éxito restaura el contenido (POST rebotado).');
        afirmar(Swal.llamadas.length === 1 && Swal.llamadas[0].toast === true,
            'El fallo del POST avisa con un toast, no con un diálogo bloqueante.');
        afirmar(jvBorradorLeer('perfil') !== null,
            'El aviso automático conserva el borrador por si falla otra vez.');

        reiniciar();
        sandbox.Swal = undefined; // páginas de acceso: restauración silenciosa
        jvBorradorGuardar(form, true);
        form.elements.nombre.value = '';
        jvBorradorDecidirSobre(jvBorradorLeer('perfil'), form);
        afirmar(form.elements.nombre.value === 'borrador', 'Sin Swal, el aviso automático restaura en silencio.');
        sandbox.Swal = Swal;
    }

    // ---------- 8. DecidirSobre: pregunta con Swal ----------
    {
        reiniciar();
        // Restaurar: el borrador trae 15.30 y el campo llega vacío.
        const form = formFake('productos_editar', [campoFake('pvp', 'text', '15.30')]);
        jvBorradorGuardar(form, false); // sin enviado: va a pregunta
        form.elements.pvp.value = '';
        Swal.resultado = { isConfirmed: true };
        jvBorradorDecidirSobre(jvBorradorLeer('productos_editar'), form);
        await sandbox.jvBorradorCola;
        afirmar(form.elements.pvp.value === '15.30', 'La opción Restaurar devuelve el contenido.');
        afirmar(jvBorradorLeer('productos_editar') !== null, 'Restaurar conserva el borrador.');

        // Descartar: purga y no toca el campo.
        reiniciar();
        const formD = formFake('productos_editar', [campoFake('pvp', 'text', '9.99')]);
        jvBorradorGuardar(formD, false);
        formD.elements.pvp.value = '';
        Swal.resultado = { isDismissed: true, dismiss: 'cancel' };
        jvBorradorDecidirSobre(jvBorradorLeer('productos_editar'), formD);
        await sandbox.jvBorradorCola;
        afirmar(jvBorradorLeer('productos_editar') === null, 'La opción Descartar purga el borrador.');
        afirmar(formD.elements.pvp.value === '', 'Descartar no restaura nada.');

        // Esc: conserva, no restaura
        reiniciar();
        const formE = formFake('productos_editar', [campoFake('pvp', 'text', '7.77')]);
        jvBorradorGuardar(formE, false);
        formE.elements.pvp.value = '';
        Swal.resultado = { isDismissed: true, dismiss: 'escape' };
        jvBorradorDecidirSobre(jvBorradorLeer('productos_editar'), formE);
        await sandbox.jvBorradorCola;
        afirmar(jvBorradorLeer('productos_editar') !== null, 'Esc conserva el borrador para después.');
        afirmar(formE.elements.pvp.value === '', 'Esc no restaura por su cuenta.');

        // Cola: dos decisiones se preguntan de a una
        reiniciar();
        const formC = formFake('productos_editar', [campoFake('pvp', 'text', '15.30')]);
        const formB = formFake('usuarios_editar', [campoFake('correo', 'text', 'a@b.c')]);
        jvBorradorGuardar(formC, false);
        jvBorradorGuardar(formB, false);
        let llamadas = 0;
        Swal.resultado = function() { llamadas++; return { isConfirmed: false, isDismissed: true, dismiss: 'escape' }; };
        jvBorradorDecidirSobre(jvBorradorLeer('productos_editar'), formC);
        jvBorradorDecidirSobre(jvBorradorLeer('usuarios_editar'), formB);
        await sandbox.jvBorradorCola;
        afirmar(llamadas === 2, 'Dos borradores en cola se preguntan uno tras otro.');
    }

    // ---------- 9. Preparar: formulario de modal se decide al mostrarse ----------
    {
        reiniciar();
        const form = formFake('compras_proveedor', [campoFake('razon_social', 'text', 'Distribuidora X')], false);
        form.contains = function(nodo) { return nodo === this; };
        jvBorradorGuardar(form, false);
        const escuchasPrevias = doc.escuchas['shown.bs.modal'] ? doc.escuchas['shown.bs.modal'].length : 0;

        jvBorradorPreparar(form);
        afirmar(Swal.llamadas.length === 0, 'Un formulario en modal oculto no decide todavía.');

        // Se muestra OTRO modal: nada debe pasar.
        const otroModal = { contains: function() { return false; } };
        doc.escuchas['shown.bs.modal'].slice(escuchasPrevias).forEach(function(cb) { cb({ target: otroModal }); });
        afirmar(Swal.llamadas.length === 0, 'El shown.bs.modal de otro modal no dispara la decisión.');

        // Se muestra EL modal correcto: pregunta y restaura al confirmar.
        const modal = { contains: function(nodo) { return nodo === form; } };
        doc.escuchas['shown.bs.modal'].slice(escuchasPrevias).forEach(function(cb) { cb({ target: modal }); });
        await sandbox.jvBorradorCola;
        afirmar(Swal.llamadas.length === 1, 'Al mostrarse el modal correcto se decide el borrador.');
        afirmar(form.elements.razon_social.value === 'Distribuidora X',
            'Confirmar dentro del modal restaura el contenido.');

        // Reabrir el modal NO vuelve a preguntar.
        doc.escuchas['shown.bs.modal'].slice(escuchasPrevias).forEach(function(cb) { cb({ target: modal }); });
        await sandbox.jvBorradorCola;
        afirmar(Swal.llamadas.length === 1, 'Reabrir el modal no pregunta dos veces.');
    }

    // ---------- 10. Preparar: sin borrador no pasa nada, pero escucha ----------
    {
        reiniciar();
        const form = formFake('nota_entrega', [campoFake('direccion', 'text', '')]);
        jvBorradorPreparar(form);
        afirmar(Swal.llamadas.length === 0, 'Sin borrador guardado no hay preguntas.');
        form.campos[0].value = 'Calle 5';
        form.escuchas.input[0]({});
        afirmar(jvBorradorLeer('nota_entrega').datos.direccion === 'Calle 5',
            'Sin borrador previo, escribir igual empieza a guardar.');
    }

    // ---------- 11. Activar conecta todos los formularios marcados ----------
    {
        reiniciar();
        const formA = formFake('usuarios_crear', [campoFake('correo', 'text', 'a')]);
        const formB = formFake('recuperar_buscar', [campoFake('rec_input', 'text', 'b')]);
        doc.querySelectorAll = function(selector) {
            afirmar(selector === 'form[data-borrador]', 'Activar busca solo formularios marcados.');
            return [formA, formB];
        };
        jvBorradorActivar();
        afirmar(!!formA.escuchas.input && !!formB.escuchas.submit,
            'Activar deja listeners de guardado y de envío en cada formulario.');
    }

    // ---------- 12. HaceTiempo / HaceTexto ----------
    {
        reiniciar();
        afirmar(jvBorradorHaceTiempo(Date.now() - 5000) >= 4900, 'HaceTiempo mide el transcurrido.');
        afirmar(jvBorradorHaceTiempo(Date.now() + 5000) === 0, 'HaceTiempo nunca es negativo.');
        afirmar(jvBorradorHaceTexto(Date.now() - 30000) === 'unos segundos', 'HaceTexto: segundos.');
        afirmar(jvBorradorHaceTexto(Date.now() - 5 * 60000) === '5 minutos', 'HaceTexto: minutos.');
        afirmar(jvBorradorHaceTexto(Date.now() - 2 * 3600000) === '2 horas', 'HaceTexto: horas.');
        afirmar(jvBorradorHaceTexto(Date.now() - 30 * 3600000) === 'más de un día', 'HaceTexto: días.');
    }

    // ---------- 13. Integración en fuentes (vistas, sidebar, login) ----------
    {
        const marcasEsperadas = [
            ['login/login.php', ['data-borrador="login"'], 1],
            ['login/recuperar.php', ['data-borrador="recuperar_buscar"'], 1],
            ['views/usuarios/index.php', ['data-borrador="usuarios_editar"', 'data-borrador="usuarios_crear"'], 2],
            ['views/perfil/index.php', ['data-borrador="perfil"'], 1],
            ['views/productos/index.php', ['data-borrador="productos_categoria"', 'data-borrador="productos_editar"'], 2],
            ['views/compras/index.php', ['data-borrador="compras_alta"', 'data-borrador="compras_pago"',
                'data-borrador="compras_proveedor"', 'data-borrador="compras_catalogo"',
                'data-borrador="compras_recepcion"'], 5],
            ['views/salidas/index.php', ['data-borrador="salidas"'], 1],
            ['views/nota_entrega/nota.php', ['data-borrador="nota_entrega"'], 1]
        ];
        let total = 0;
        marcasEsperadas.forEach(function(par) {
            const contenido = fs.readFileSync(par[0], 'utf8');
            par[1].forEach(function(marca) {
                afirmar(contenido.indexOf(marca) !== -1, par[0] + ' marca ' + marca);
            });
            const cuenta = (contenido.match(/data-borrador="/g) || []).length;
            afirmar(cuenta === par[2], par[0] + ' tiene exactamente ' + par[2] + ' marca(s) (' + cuenta + ').');
            total += cuenta;
        });
        afirmar(total === 14, 'El total de formularios marcados es 14 (' + total + ').');

        const sidebar = fs.readFileSync('includes/sidebar.php', 'utf8');
        afirmar(sidebar.includes('JV_CONFIG.userId'), 'El sidebar expone JV_CONFIG.userId.');

        const login = fs.readFileSync('login/login.php', 'utf8');
        afirmar(login.includes('assets/js/diseno.js') && login.includes('$jv_cache_bust(__DIR__'),
            'login.php incluye diseno.js con su helper de caché.');
        const recuperar = fs.readFileSync('login/recuperar.php', 'utf8');
        afirmar(recuperar.includes('assets/js/diseno.js') && recuperar.includes("jv_asset_version('js/diseno.js')"),
            'recuperar.php incluye diseno.js con su helper de caché.');

        // Nada del módulo persiste contraseñas: la exclusión es por patrón.
        afirmar(/pass\|pin\|codigo\|token\|clave/.test(fuente), 'La lista de exclusión cubre patrones sensibles.');
        afirmar(fuente.includes('almacen.setItem(jvBorradorLlave'),
            'El módulo solo escribe en localStorage a través de su llave controlada.');
    }

    console.log('Piloto de borradores: ' + afirmaciones + ' afirmaciones en verde.');
})().catch(function(error) {
    console.error(error.message);
    process.exit(1);
});
