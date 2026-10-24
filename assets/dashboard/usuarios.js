// ==========================================
// COLABORADORES — JS DEL MÓDULO
// Paginación, búsqueda, filtros y edición
// ==========================================

// Ojo para ver u ocultar el PIN mientras se teclea (mismo patrón que Mi Perfil).
// El ojo abierto significa "el texto se ve" y el tachado "está oculto", así que
// el icono nace tachado: los campos arrancan enmascarados.
document.querySelectorAll('[data-toggle-password]').forEach(function(button) {
    button.addEventListener('click', function() {
        var inputId = button.getAttribute('aria-controls');
        var input = inputId ? document.getElementById(inputId) : null;
        var icon = button.querySelector('i');
        if (!input || !icon) return;

        var mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(mostrar));
        button.setAttribute('aria-label', (mostrar ? 'Ocultar ' : 'Mostrar ') + button.dataset.passwordLabel);
        icon.className = mostrar ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

const POR_PAGINA = 10;
let estadoFiltroUsu = 'todos';
let paginaUsuActual = 1;

// Normaliza el nombre igual que el backend: cada segmento empieza en mayúscula.
function normalizarUsuarioCliente(raw) {
    return String(raw).trim().toLowerCase().replace(/(^|_)(\p{L})/gu, function (m, sep, letra) {
        return (sep === '_' ? '_' : '') + letra.toUpperCase();
    });
}

function obtenerFilasUsuarios() {
    return Array.from(document.querySelectorAll('#cuerpoUsuarios tr.usuario-fila'));
}

// Decide si una fila coincide con la búsqueda y el filtro de estado actuales.
function filaCoincideUsuarios(tr) {
    const termino = document.getElementById('buscarUsuario').value.trim().toLowerCase();
    const nombre = (tr.dataset.usuario || '').toLowerCase();
    const correo = (tr.dataset.correo || '').toLowerCase();
    const cumpleTexto = termino === '' || nombre.includes(termino) || correo.includes(termino);

    let cumpleEstado = true;
    switch (estadoFiltroUsu) {
        case 'activo':      cumpleEstado = tr.dataset.estado === 'activo'; break;
        case 'pendiente':   cumpleEstado = tr.dataset.estado === 'pendiente'; break;
        case 'inactivo':    cumpleEstado = tr.dataset.estado === 'inactivo'; break;
    }
    return cumpleTexto && cumpleEstado;
}

// Muestra la fila vacía "sin resultados" cuando el filtro no encuentra nada.
function manejarSinResultados(visible, sinFila) {
    const vacio = document.getElementById('filaSinResultados');
    if (visible.length === 0 && !sinFila) {
        const tbody = document.getElementById('cuerpoUsuarios');
        const tr = document.createElement('tr');
        tr.id = 'filaSinResultados';
        tr.innerHTML = '<td colspan="6"><div class="estado-vacio"><i class="bi bi-search" aria-hidden="true"></i><span>Sin colaboradores que coincidan con la búsqueda</span></div></td>';
        tbody.appendChild(tr);
        return tr;
    }
    if (vacio) vacio.remove();
    return vacio;
}

// Recalcula la lista visible, la página y pinta la tabla + paginación.
function rendTablaUsuarios() {
    const filas = obtenerFilasUsuarios();
    const visibles = filas.filter(filaCoincideUsuarios);
    const sinFila = document.getElementById('filaSinResultados') !== null;
    manejarSinResultados(visibles, sinFila);

    const totalPaginas = Math.max(1, Math.ceil(visibles.length / POR_PAGINA));
    if (paginaUsuActual > totalPaginas) paginaUsuActual = totalPaginas;

    const desde = (paginaUsuActual - 1) * POR_PAGINA;
    const hasta = Math.min(desde + POR_PAGINA, visibles.length);

    filas.forEach(function (tr) { tr.classList.add('d-none'); });
    visibles.slice(desde, hasta).forEach(function (tr) { tr.classList.remove('d-none'); });

    const contador = document.getElementById('contadorUsuarios');
    if (contador) {
        contador.textContent = visibles.length === 0
            ? 'Sin resultados'
            : 'Mostrando ' + (desde + 1) + '–' + hasta + ' de ' + visibles.length + ' colaboradores';
    }
    rendPaginacionUsuarios(totalPaginas);
}

// Construye los botones ‹ Anterior · 1..N · Siguiente ›
function rendPaginacionUsuarios(totalPaginas) {
    const nav = document.getElementById('paginacionUsuarios');
    nav.innerHTML = '';
    if (totalPaginas <= 1) { return; }

    // Respeta la preferencia del sistema de reducir animaciones.
    const reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Botón de página: <button> (acción, no navegación) con etiqueta accesible.
    const boton = function (texto, pagina, etiqueta) {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = texto;
        b.className = pagina === paginaUsuActual ? 'active' : '';
        if (etiqueta) b.setAttribute('aria-label', etiqueta);
        if (pagina === paginaUsuActual) b.setAttribute('aria-current', 'page');
        b.addEventListener('click', function () {
            paginaUsuActual = pagina;
            rendTablaUsuarios();
            document.getElementById('tablaUsuarios').scrollIntoView({
                behavior: reducirMovimiento ? 'auto' : 'smooth',
                block: 'nearest'
            });
        });
        return b;
    };

    const navItem = function (elemento, deshabilitado) {
        if (deshabilitado) {
            elemento.classList.add('disabled');
            elemento.disabled = true;
        }
        nav.appendChild(elemento);
        return elemento;
    };

    navItem(boton('‹', paginaUsuActual - 1, 'Página anterior'), paginaUsuActual <= 1);

    const inicioP = Math.max(1, paginaUsuActual - 2);
    const finP = Math.min(totalPaginas, paginaUsuActual + 2);
    for (let n = inicioP; n <= finP; n++) {
        navItem(boton(String(n), n, 'Página ' + n), false);
    }
    if (finP < totalPaginas) {
        const puntos = document.createElement('span');
        puntos.textContent = '…';
        puntos.className = 'disabled';
        nav.appendChild(puntos);
        navItem(boton(String(totalPaginas), totalPaginas, 'Página ' + totalPaginas), false);
    }

    navItem(boton('›', paginaUsuActual + 1, 'Página siguiente'), paginaUsuActual >= totalPaginas);
}

// Búsqueda en tiempo real: vuelve a página 1 al escribir.
function aplicarBusquedaUsuarios() {
    paginaUsuActual = 1;
    rendTablaUsuarios();
}

// Cambio de filtro por estado: marca el botón activo y re-renderiza.
// aria-pressed refleja el estado para lectores de pantalla (los botones
// son un grupo de filtros, no tres acciones sueltas).
function setFiltroUsuarios(btn) {
    estadoFiltroUsu = btn.dataset.statusFiltro;
    document.querySelectorAll('.btn-filter-usu').forEach(function (b) {
        b.classList.remove('active');
        b.setAttribute('aria-pressed', 'false');
    });
    btn.classList.add('active');
    btn.setAttribute('aria-pressed', 'true');
    aplicarBusquedaUsuarios();
}

// Suspender / Reactivar usuario
function confirmarToggle(id, nombre, accion) {
    const esSuspender = (accion === 'suspender');
    Swal.fire({
        title: esSuspender ? '¿SUSPENDER USUARIO?' : '¿REACTIVAR USUARIO?',
        text: esSuspender ? `El usuario ${nombre} ya no podrá acceder al sistema.` : `Se restaurará el acceso al sistema para ${nombre}.`,
        icon: esSuspender ? 'warning' : 'info',
        showCancelButton: true,
        confirmButtonColor: esSuspender ? '#DC2626' : '#16A34A',
        cancelButtonColor: '#CED4DA',
        confirmButtonText: esSuspender ? 'SÍ, SUSPENDER' : 'SÍ, ACTIVAR',
        cancelButtonText: 'CANCELAR',
        background: '#fff',
        color: '#212529'
    }).then((result) => {
        if (result.isConfirmed) jvPost({ toggle_status: id, csrf_token: window.JV_CONFIG.csrfToken });
    });
}

// Abre el modal de edición con los datos del colaborador (fila donde está el botón).
function abrirEdicion(btn) {
    const tr = btn.closest('tr');
    document.getElementById('edit_id_usuario').value = tr.dataset.id;
    document.getElementById('edit_usuario').value = tr.dataset.usuario;
    document.getElementById('edit_correo').value = tr.dataset.correo;
    document.getElementById('edit_rol').value = tr.dataset.rol;
    document.getElementById('edit_status').value = tr.dataset.status;
    ['edit_usuario', 'edit_correo', 'edit_rol'].forEach(function (id) {
        document.getElementById(id).classList.remove('input-error');
        marcarCampoUsu(id, '', '');
    });
    validarEdicion(false);
    ['edit_usuario', 'edit_correo', 'edit_rol'].forEach(function (id) {
        marcarCampoUsu(id, '', '');
    });
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();
}

// ==========================================
// VALIDACIÓN EN VIVO DE LOS MODALES
// ==========================================
// Cada campo se marca mientras se escribe: verde con ✓ si es válido, rojo con ✗
// y el motivo si no lo es. El botón de envío solo se habilita con todo correcto.

// Texto que vuelve a mostrarse cuando el campo queda vacío.
const TEXTO_GUIA_USU = {
    crear_usuario: 'Debe comenzar con una letra; luego admite letras de cualquier idioma, números y guion bajo (4 a 20 caracteres, sin otros símbolos).',
    crear_correo: 'Formato válido de correo electrónico.',
    crear_password: 'De 8 a 18 caracteres, sin espacios, con mayúscula, minúscula, número y símbolo.',
    crear_password_confirm: 'Debe coincidir con la contraseña anterior.',
    crear_rol: 'Solo se pueden crear Operadores de Carga y de Ventas.',
    edit_usuario: 'Debe comenzar con una letra; luego admite letras de cualquier idioma, números y guion bajo (4 a 20 caracteres, sin otros símbolos).',
    edit_correo: 'Correo válido y no asociado a otra cuenta.',
    edit_rol: 'Solo se puede asignar Operador de Carga o de Ventas. Administrador es un rol único.',
    reset_password_confirm: 'Repite la nueva contraseña.',
    pin_emergencia_global: '6 dígitos, sin letras.',
    pin_emergencia_global_confirm: 'Repite el mismo PIN.'
};

const MIN_PASSWORD_CHARACTERS = 8;
const MAX_PASSWORD_CHARACTERS = 18;

// Marca un campo como válido ('ok'), inválido ('err') o neutro (vacío).
// El mensaje se pinta en la guía que está debajo del campo.
function marcarCampoUsu(campoId, estado, mensaje) {
    const input = document.getElementById(campoId);
    const guia = document.getElementById(campoId + '_guia');
    if (!input) return;
    input.classList.remove('is-valid', 'is-invalid');
    input.removeAttribute('aria-invalid');
    if (guia) guia.classList.remove('guia-ok', 'guia-err');

    const guiaEsLista = campoId === 'reset_password';
    if (estado === 'ok') {
        input.classList.add('is-valid');
        input.setAttribute('aria-invalid', 'false');
        if (guia && !guiaEsLista) { guia.classList.add('guia-ok'); if (mensaje) guia.textContent = mensaje; }
    } else if (estado === 'err') {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (guia && !guiaEsLista) { guia.classList.add('guia-err'); if (mensaje) guia.textContent = mensaje; }
    } else if (guia && !guiaEsLista) {
        guia.textContent = TEXTO_GUIA_USU[campoId] || '';
    }
}

// Un campo vacío queda neutro (sin color) pero con su texto guía.
function validarCampoUsu(campoId, valorOk, mensajeOk, mensajeErr, mostrarVacio) {
    const input = document.getElementById(campoId);
    const vacio = input.type === 'password' ? input.value === '' : input.value.trim() === '';
    if (vacio && !mostrarVacio) marcarCampoUsu(campoId, '', '');
    else marcarCampoUsu(campoId, valorOk ? 'ok' : 'err', valorOk ? mensajeOk : mensajeErr);
    return !vacio && valorOk;
}

function correoValidoUsu(correo) {
    const partes = correo.split('@');
    if (partes.length !== 2) return false;
    const local = partes[0];
    const dominio = partes[1];
    return local.length <= 64
        && !local.startsWith('.')
        && !local.endsWith('.')
        && !local.includes('..')
        && /^[A-Z0-9.!#$%&'*+/=?^_`{|}~-]+$/i.test(local)
        && /^[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?(?:\.[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?)+$/i.test(dominio);
}

function usuarioValidoUsu(usuario) {
    return /^[\p{L}][\p{L}\p{N}\p{M}_]{3,19}$/u.test(usuario);
}

function requisitosPasswordUsu(password) {
    const longitud = Array.from(password).filter(function (caracter) {
        return !/\s/u.test(caracter);
    }).length;
    const sinEspacios = !/\s/u.test(password);
    return {
        longitud: longitud >= MIN_PASSWORD_CHARACTERS,
        minuscula: /[a-z]/.test(password),
        mayuscula: /[A-Z]/.test(password),
        numero: /\d/.test(password),
        simbolo: /[^\w\s]|_/.test(password),
        sin_saltos: !/[\r\n]/.test(password),
        sin_espacios: sinEspacios,
        maximo: longitud <= MAX_PASSWORD_CHARACTERS
    };
}

function passwordSeguraUsu(password) {
    return Object.values(requisitosPasswordUsu(password)).every(Boolean);
}

function limitarPasswordCaracteres(campo) {
    const limitado = Array.from(campo.value).slice(0, MAX_PASSWORD_CHARACTERS).join('');
    if (limitado !== campo.value) {
        campo.value = limitado;
        campo.setSelectionRange(limitado.length, limitado.length);
    }
    return Array.from(limitado).length;
}

// Compara con los nombres ya visibles en la tabla, excluyendo la cuenta editada.
function usuarioDuplicadoUsu(usuario, idActual) {
    const nombreNormalizado = usuario.toLowerCase();
    return Array.from(document.querySelectorAll('#cuerpoUsuarios tr.usuario-fila')).some(function (fila) {
        return fila.dataset.id !== idActual && (fila.dataset.usuario || '').toLowerCase() === nombreNormalizado;
    });
}

// Evita avisar como duplicado el correo que ya pertenece al mismo colaborador.
function correoDuplicadoUsu(correo, idActual) {
    const correoNormalizado = correo.toLowerCase();
    return Array.from(document.querySelectorAll('#cuerpoUsuarios tr.usuario-fila')).some(function (fila) {
        return fila.dataset.id !== idActual && (fila.dataset.correo || '').trim().toLowerCase() === correoNormalizado;
    });
}

// Valida todo el modal de alta y habilita el botón solo si todo está correcto.
function validarAlta(mostrarErrores) {
    const campoUsuario = document.getElementById('crear_usuario');
    if (!campoUsuario) return false;
    const usuario = normalizarUsuarioCliente(campoUsuario.value);
    const correo = document.getElementById('crear_correo').value.trim();
    const password = document.getElementById('crear_password').value;
    const confirmar = document.getElementById('crear_password_confirm').value;
    const rol = document.getElementById('crear_rol');
    const usuarioValido = usuarioValidoUsu(usuario);
    const correoValido = correo.length <= 100 && correoValidoUsu(correo);
    const usuarioDuplicado = usuarioValido && usuarioDuplicadoUsu(usuario, '');
    const correoDuplicado = correoValido && correoDuplicadoUsu(correo, '');
    const usuarioOk = validarCampoUsu('crear_usuario', usuarioValido && !usuarioDuplicado,
        'Nombre de usuario válido.', usuarioDuplicado
            ? 'Ese nombre de usuario ya está en uso.'
            : 'Debe comenzar con una letra; luego usa letras de cualquier idioma, números o guion bajo (4 a 20 caracteres, sin otros símbolos).', mostrarErrores);
    const correoOk = validarCampoUsu('crear_correo', correoValido && !correoDuplicado,
        'Correo electrónico válido.', correoDuplicado
            ? 'Ese correo ya está asociado a otro colaborador.'
            : (correo.length > 100 ? 'El correo no puede superar 100 caracteres.' : 'Escribe un correo electrónico válido.'),
        mostrarErrores);
    const passwordRequisitos = requisitosPasswordUsu(password);
    const caracteresFaltantes = Math.max(0, MIN_PASSWORD_CHARACTERS - Array.from(password).length);
    const passwordOk = validarCampoUsu('crear_password', passwordSeguraUsu(password),
        'Contraseña válida. Cumple todos los requisitos.', /\s/u.test(password)
            ? 'No se permiten espacios en la contraseña.'
            : (!passwordRequisitos.maximo
                ? 'La contraseña no puede superar 18 caracteres.'
                : (!passwordRequisitos.longitud
                    ? 'Te ' + (caracteresFaltantes === 1 ? 'falta 1 carácter' : 'faltan ' + caracteresFaltantes + ' caracteres') + ' para llegar al mínimo de ' + MIN_PASSWORD_CHARACTERS + '.'
                    : 'Debe incluir una mayúscula, una minúscula, un número y un símbolo.')),
        mostrarErrores);
    const confirmarOk = validarCampoUsu('crear_password_confirm', password !== '' && password === confirmar,
        'Las contraseñas coinciden.', /\s/u.test(confirmar)
            ? 'No se permiten espacios en la contraseña.'
            : 'Las contraseñas no coinciden.', mostrarErrores);
    const rolPermitido = ['2', '3'].includes(rol.value)
        && Array.from(rol.options).some(function (opcion) {
            return opcion.value === rol.value && !opcion.disabled;
        });
    const rolOk = validarCampoUsu('crear_rol', rolPermitido,
        'Rol válido.', 'Selecciona Operador de Carga o de Ventas.', mostrarErrores);

    const todoOk = usuarioOk && correoOk && passwordOk && confirmarOk && rolOk;
    const boton = document.getElementById('btnCrear');
    if (boton) boton.disabled = !todoOk;
    return todoOk;
}

// Valida el modal de edición. Cada modal solo tiene sus propios campos, así que
// se valida el que realmente existe: la misma función sirve para la vista MVC y
// para cualquier variante sin dependencias cruzadas.
function validarEdicion(mostrarErrores) {
    const campoUsuario = document.getElementById('edit_usuario');
    if (!campoUsuario) return false;
    const usuario = normalizarUsuarioCliente(campoUsuario.value);
    const correo = document.getElementById('edit_correo').value.trim();
    const idActual = document.getElementById('edit_id_usuario').value;
    const rol = document.getElementById('edit_rol').value;
    const usuarioDuplicado = usuarioDuplicadoUsu(usuario, idActual);
    const correoDuplicado = correoDuplicadoUsu(correo, idActual);

    const usuarioValido = usuarioValidoUsu(usuario);
    const usuarioOk = validarCampoUsu('edit_usuario', usuarioValido && !usuarioDuplicado,
        'Nombre de usuario válido.', usuarioDuplicado
            ? 'Ese nombre de usuario ya está en uso.'
            : 'Debe comenzar con una letra; luego usa letras de cualquier idioma, números o guion bajo (4 a 20 caracteres, sin otros símbolos).', mostrarErrores);
    const correoValido = correo.length <= 100 && correoValidoUsu(correo);
    const correoOk = validarCampoUsu('edit_correo', correoValido && !correoDuplicado,
        'Correo electrónico válido.', correoDuplicado
            ? 'Ese correo ya está asociado a otro colaborador.'
            : (correo.length > 100 ? 'El correo no puede superar 100 caracteres.' : 'Escribe un correo electrónico válido.'),
        mostrarErrores);
    const rolPermitido = ['2', '3'].includes(rol)
        && Array.from(document.getElementById('edit_rol').options).some(function (opcion) {
            return opcion.value === rol && !opcion.disabled;
        });
    const rolOk = validarCampoUsu('edit_rol', rolPermitido, 'Rol válido.',
        'Selecciona uno de los roles disponibles.', mostrarErrores);

    const todoOk = usuarioOk && correoOk && rolOk;
    const boton = document.getElementById('btnEditar');
    if (boton) boton.disabled = !todoOk;
    return todoOk;
}

// Valida el modal de restablecer contraseña.
function validarReset(mostrarErrores) {
    const campoPassword = document.getElementById('reset_password');
    if (!campoPassword) return false;
    const password = campoPassword.value;
    const confirmar = document.getElementById('reset_password_confirm').value;
    const requisitos = requisitosPasswordUsu(password);
    const contieneEspacios = !requisitos.sin_espacios;
    Object.keys(requisitos).forEach(function (regla) {
        const elemento = document.getElementById('reset_password_regla_' + regla);
        if (!elemento) return;
        elemento.classList.toggle(
            'cumplido',
            password !== '' && !contieneEspacios && requisitos[regla]
        );
        elemento.classList.toggle(
            'invalido',
            regla === 'sin_espacios' && password !== '' && contieneEspacios
        );
    });
    const reglasPendientes = Object.keys(requisitos).filter(function (regla) {
        return regla !== 'maximo' && !requisitos[regla];
    });
    const textosPendientes = reglasPendientes.map(function (regla) {
        return document.getElementById('reset_password_regla_' + regla).textContent;
    });
    if (!requisitos.maximo) textosPendientes.push('Máximo 18 caracteres');
    const estadoRequisitos = document.getElementById('reset_password_estado');
    if (estadoRequisitos) {
        estadoRequisitos.textContent = password === ''
            ? ''
            : (textosPendientes.length === 0
                ? 'Todos los requisitos se cumplen.'
                : 'Pendientes: ' + textosPendientes.join(', ') + '.');
    }
    const contador = document.getElementById('reset_password_contador');
    const caracteresValidos = Array.from(password).filter(function (caracter) {
        return !/\s/u.test(caracter);
    }).length;
    if (contador) contador.textContent = caracteresValidos + ' de ' + MAX_PASSWORD_CHARACTERS + ' caracteres válidos';

    const passwordOk = validarCampoUsu('reset_password', textosPendientes.length === 0,
        'Contraseña segura.', contieneEspacios
            ? 'No se permiten espacios en la contraseña.'
            : 'Completa los requisitos pendientes.', mostrarErrores);
    const mensajeErrorConfirmacion = /\s/u.test(confirmar)
        ? 'No se permiten espacios en la contraseña.'
        : (password === ''
        ? 'Escribe primero la nueva contraseña.'
        : (mostrarErrores ? 'Confirma la nueva contraseña.' : 'Las contraseñas no coinciden.'));
    const confirmarOk = validarCampoUsu(
        'reset_password_confirm',
        password !== '' && password === confirmar,
        'Las contraseñas coinciden.',
        mensajeErrorConfirmacion,
        mostrarErrores
    );

    const todoOk = passwordOk && confirmarOk;
    const boton = document.getElementById('btnResetPassword');
    if (boton) boton.disabled = !todoOk;
    return todoOk;
}

// Validación en vivo del formulario del PIN global, sin enviar el formulario.
// Normaliza a dígitos, avisa si faltan y si la confirmación no coincide. El
// servidor vuelve a validar todo al guardar: esto solo evita el viaje inútil.
function validarPinGlobal() {
    const pin = document.getElementById('pin_emergencia_global');
    const confirm = document.getElementById('pin_emergencia_global_confirm');
    if (!pin || !confirm) return;

    // Solo dígitos, con el mismo criterio que la recuperación por PIN.
    [pin, confirm].forEach(function(campo) {
        const limpio = campo.value.replace(/[^0-9]/g, '').slice(0, 6);
        if (campo.value !== limpio) campo.value = limpio;
    });

    const pinCompleto = /^[0-9]{6}$/.test(pin.value);

    if (pin.value === '') {
        marcarCampoUsu('pin_emergencia_global', '', '');
    } else {
        marcarCampoUsu('pin_emergencia_global', pinCompleto, 'PIN de 6 dígitos.',
            'Faltan dígitos: llevas ' + pin.value.length + ' de 6.');
    }

    if (pin.value === '' && confirm.value === '') {
        marcarCampoUsu('pin_emergencia_global_confirm', '', '');
    } else if (!pinCompleto) {
        marcarCampoUsu('pin_emergencia_global_confirm', 'err', 'Termina el PIN antes de confirmarlo.');
    } else {
        marcarCampoUsu('pin_emergencia_global_confirm', confirm.value === pin.value,
            'Los PIN coinciden.', 'Los PIN no coinciden.');
    }
}

// Revalida los tres modales en cada tecla de sus campos.
['crear_usuario', 'crear_correo'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('input', function () { validarAlta(false); });
});
['crear_password', 'crear_password_confirm'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('input', function () {
        limitarPasswordCaracteres(campo);
        validarAlta(false);
    });
});
['crear_rol'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('change', function () { validarAlta(false); });
});
['edit_usuario', 'edit_correo'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('input', function () { validarEdicion(false); });
});
['edit_rol'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('change', function () { validarEdicion(false); });
});
['reset_password', 'reset_password_confirm'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('input', function () {
        limitarPasswordCaracteres(campo);
        validarReset(false);
    });
});

// Abre el modal de alta con el formulario limpio.
function abrirCreacion() {
    const formulario = document.getElementById('formCrear');
    if (!formulario) return;
    formulario.reset();
    ['crear_usuario', 'crear_correo', 'crear_password', 'crear_password_confirm', 'crear_rol'].forEach(function (id) {
        marcarCampoUsu(id, '', '');
    });
    validarAlta();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCrear')).show();
}

// Abre el modal para que el administrador le restablezca la contraseña a un colaborador.
function abrirResetPassword(id, nombre) {
    const formulario = document.getElementById('formResetPassword');
    const idOculto = document.getElementById('reset_id_usuario');
    if (!formulario || !idOculto) return;
    formulario.reset();
    idOculto.value = id;
    document.getElementById('reset_usuario_nombre').textContent = nombre;
    ['reset_password', 'reset_password_confirm'].forEach(function (id) {
        marcarCampoUsu(id, '', '');
    });
    validarReset();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalResetPassword')).show();
}

// Validación y envío del formulario de edición.
const formEditar = document.getElementById('formEditar');
if (formEditar) formEditar.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validarEdicion(true)) {
        const primerCampoInvalido = formEditar.querySelector('.is-invalid');
        if (primerCampoInvalido) primerCampoInvalido.focus();
        return;
    }

    const inputUsuario = document.getElementById('edit_usuario');
    const idUsuario = document.getElementById('edit_id_usuario').value;
    const status = document.getElementById('edit_status').value;

    const usuario = normalizarUsuarioCliente(inputUsuario.value);
    const correo = document.getElementById('edit_correo').value.trim();
    const rol = document.getElementById('edit_rol').value;

    Swal.fire({
        title: '¿Guardar cambios?',
        text: `Se actualizarán los datos del colaborador ${usuario}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7C3AED',
        cancelButtonColor: '#CED4DA',
        confirmButtonText: 'Guardar cambios',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            jvPost({
                accion_usuario: 'editar',
                id_usuario: idUsuario,
                usuario: usuario,
                correo: correo,
                id_rol: rol,
                status: status,
                csrf_token: window.JV_CONFIG.csrfToken
            }, 'index.php?url=usuarios');
        }
    });
});

// Valida y envía el restablecimiento con los nombres que procesa el controlador.
const formResetPassword = document.getElementById('formResetPassword');
if (formResetPassword) formResetPassword.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validarReset(true)) {
        const primerCampoInvalido = formResetPassword.querySelector('.is-invalid');
        if (primerCampoInvalido) primerCampoInvalido.focus();
        return;
    }

    const idUsuario = document.getElementById('reset_id_usuario').value;
    if (!/^[1-9]\d*$/.test(idUsuario)) {
        Swal.fire('No se pudo restablecer', 'Vuelve a seleccionar el colaborador e inténtalo otra vez.', 'error');
        return;
    }

    Swal.fire({
        title: '¿Restablecer contraseña?',
        text: `Se asignará una nueva contraseña a ${document.getElementById('reset_usuario_nombre').textContent}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7C3AED',
        cancelButtonColor: '#CED4DA',
        confirmButtonText: 'Restablecer contraseña',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            jvPost({
                accion_usuario: 'restablecer_password',
                id_usuario: idUsuario,
                password: document.getElementById('reset_password').value,
                password_confirm: document.getElementById('reset_password_confirm').value,
                csrf_token: window.JV_CONFIG.csrfToken
            }, 'index.php?url=usuarios');
        }
    });
});

// Validación y envío del formulario de alta de colaborador.
const formCrear = document.getElementById('formCrear');
if (formCrear) formCrear.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validarAlta(true)) {
        const primerCampoInvalido = formCrear.querySelector('.is-invalid');
        if (primerCampoInvalido) primerCampoInvalido.focus();
        return;
    }

    const inputUsuario = document.getElementById('crear_usuario');
    const inputCorreo = document.getElementById('crear_correo');
    const inputPassword = document.getElementById('crear_password');
    const inputConfirm = document.getElementById('crear_password_confirm');

    const usuario = normalizarUsuarioCliente(inputUsuario.value);
    const correo = inputCorreo.value.trim();
    const password = inputPassword.value;
    const passwordConfirm = inputConfirm.value;
    const rol = document.getElementById('crear_rol').value;

    Swal.fire({
        title: '¿CREAR COLABORADOR?',
        text: `Se registrará la cuenta de ${usuario} como activa.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7C3AED',
        cancelButtonColor: '#CED4DA',
        confirmButtonText: 'SÍ, CREAR',
        cancelButtonText: 'CANCELAR'
    }).then((result) => {
        if (result.isConfirmed) {
            jvPost({
                accion_usuario: 'crear',
                usuario: usuario,
                correo: correo,
                password: password,
                password_confirm: passwordConfirm,
                id_rol: rol,
                csrf_token: window.JV_CONFIG.csrfToken
            }, 'index.php?url=usuarios');
        }
    });
});

// Paginación inicial al cargar la página.
document.addEventListener('DOMContentLoaded', function () {
    rendTablaUsuarios();

    const flash = document.getElementById('flashMsg');
    if (!flash || !flash.classList.contains('alert-jv-danger')) return;

    const mensaje = (flash.dataset.texto || '').toUpperCase();
    let campoId = '';
    let modalId = '';
    if (mensaje.startsWith('LA CONTRASEÑA') || mensaje.startsWith('LAS CONTRASEÑAS')) {
        modalId = 'modalResetPassword';
        campoId = mensaje.includes('NO COINCIDEN') ? 'reset_password_confirm' : 'reset_password';
    } else if (mensaje.includes('CORREO')) {
        modalId = 'modalEditar';
        campoId = 'edit_correo';
    } else if (mensaje.includes('NOMBRE DE USUARIO') || mensaje.includes('USUARIO CON ESE NOMBRE')) {
        modalId = 'modalEditar';
        campoId = 'edit_usuario';
    } else if (mensaje.includes('ROL')) {
        modalId = 'modalEditar';
        campoId = 'edit_rol';
    }

    if (campoId && modalId) {
        marcarCampoUsu(campoId, 'err', flash.textContent.trim());
        const modal = document.getElementById(modalId);
        modal.addEventListener('shown.bs.modal', function () {
            document.getElementById(campoId).focus();
        }, { once: true });
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});

// Sincronizar main-wrapper con sidebar
const observer = new MutationObserver(() => {
    if (typeof mainWrapper === 'undefined' || !mainWrapper) return;
    if (document.body.classList.contains('sidebar-open')) {
        mainWrapper.classList.add('sidebar-open');
    } else {
        mainWrapper.classList.remove('sidebar-open');
    }
});
observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });