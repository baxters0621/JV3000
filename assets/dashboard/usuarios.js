// ==========================================
// COLABORADORES — JS DEL MÓDULO
// Paginación, búsqueda, filtros y edición
// ==========================================

const POR_PAGINA = 10;
let estadoFiltroUsu = 'todos';
let paginaUsuActual = 1;

// Normaliza el nombre igual que el backend: cada segmento empieza en mayúscula.
function normalizarUsuarioCliente(raw) {
    return String(raw).trim().toLowerCase().replace(/(^|_)([a-z])/g, function (m, sep, letra) {
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
        tr.innerHTML = '<td colspan="6"><div class="estado-vacio"><i class="bi bi-search"></i><span>Sin colaboradores que coincidan con la búsqueda</span></div></td>';
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

    const boton = function (texto, pagina) {
        const a = document.createElement('a');
        a.href = '#';
        a.textContent = texto;
        a.className = pagina === paginaUsuActual ? 'active' : '';
        a.addEventListener('click', function (e) {
            e.preventDefault();
            paginaUsuActual = pagina;
            rendTablaUsuarios();
            document.getElementById('tablaUsuarios').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
        return a;
    };

    const navItem = function (elemento, deshabilitado) {
        if (deshabilitado) elemento.classList.add('disabled');
        nav.appendChild(elemento);
        return elemento;
    };

    navItem(boton('‹', paginaUsuActual - 1), paginaUsuActual <= 1);

    const inicioP = Math.max(1, paginaUsuActual - 2);
    const finP = Math.min(totalPaginas, paginaUsuActual + 2);
    for (let n = inicioP; n <= finP; n++) {
        navItem(boton(n, n), false);
    }
    if (finP < totalPaginas) {
        const puntos = document.createElement('span');
        puntos.textContent = '…';
        puntos.className = 'disabled';
        nav.appendChild(puntos);
        navItem(boton(totalPaginas, totalPaginas), false);
    }

    navItem(boton('›', paginaUsuActual + 1), paginaUsuActual >= totalPaginas);
}

// Búsqueda en tiempo real: vuelve a página 1 al escribir.
function aplicarBusquedaUsuarios() {
    paginaUsuActual = 1;
    rendTablaUsuarios();
}

// Cambio de filtro por estado: marca el botón activo y re-renderiza.
function setFiltroUsuarios(btn) {
    estadoFiltroUsu = btn.dataset.statusFiltro;
    document.querySelectorAll('.btn-filter-usu').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
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
    // Seguridad opcional: siempre se abre en blanco ("sin cambios").
    document.getElementById('edit_pregunta').value = '';
    document.getElementById('edit_respuesta').value = '';
    document.getElementById('edit_usuario').classList.remove('input-error');
    document.getElementById('edit_correo').classList.remove('input-error');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();
}

// Confirma y envía la eliminación definitiva de un colaborador.
function confirmarEliminacion(id, nombre) {
    Swal.fire({
        title: '¿ELIMINAR USUARIO?',
        html: 'Se eliminará la cuenta de <strong>' + escapeHtml(nombre) + '</strong>.<br>'
            + '<small>Si tiene operaciones registradas, el sistema impedirá el borrado y sugerirá desactivarlo.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#CED4DA',
        confirmButtonText: 'SÍ, ELIMINAR',
        cancelButtonText: 'CANCELAR',
        background: '#fff',
        color: '#212529'
    }).then((result) => {
        if (result.isConfirmed) jvPost({ eliminar_usuario: id, csrf_token: window.JV_CONFIG.csrfToken });
    });
}

// ==========================================
// VALIDACIÓN EN VIVO DE LOS MODALES
// ==========================================
// Cada campo se marca mientras se escribe: verde con ✓ si es válido, rojo con ✗
// y el motivo si no lo es. El botón de envío solo se habilita con todo correcto.

// Texto que vuelve a mostrarse cuando el campo queda vacío.
const TEXTO_GUIA_USU = {
    crear_usuario: 'Mín 4 - Máx 20 caracteres (letras, números, guion bajo).',
    crear_correo: 'Formato válido de correo electrónico.',
    crear_password: 'Mín. 8 caracteres, 1 mayúscula, 1 minúscula, 1 número y 1 símbolo.',
    crear_password_confirm: 'Debe coincidir con la contraseña anterior.',
    crear_rol: 'Solo se pueden crear Operadores de Carga y de Ventas.',
    crear_pregunta: 'Obligatoria para recuperar la cuenta.',
    crear_respuesta: 'Escribe una respuesta que recuerdes fácilmente.',
    edit_usuario: 'Mín 4 - Máx 20 caracteres (letras, números, guion bajo).',
    edit_correo: 'Formato válido de correo electrónico.',
    edit_rol: 'Controla los permisos del colaborador dentro del sistema.',
    edit_pregunta: 'Se aplica solo si también escribes la respuesta.',
    edit_respuesta: 'Se aplica solo si también eliges la pregunta.',
    reset_password: 'Mín. 8 caracteres, 1 mayúscula, 1 minúscula, 1 número y 1 símbolo.',
    reset_password_confirm: 'Debe coincidir con la contraseña anterior.'
};

// Marca un campo como válido ('ok'), inválido ('err') o neutro (vacío).
// El mensaje se pinta en la guía que está debajo del campo.
function marcarCampoUsu(campoId, estado, mensaje) {
    const input = document.getElementById(campoId);
    const guia = document.getElementById(campoId + '_guia');
    if (!input) return;
    input.classList.remove('is-valid', 'is-invalid');
    if (guia) guia.classList.remove('guia-ok', 'guia-err');

    if (estado === 'ok') {
        input.classList.add('is-valid');
        if (guia) { guia.classList.add('guia-ok'); if (mensaje) guia.textContent = mensaje; }
    } else if (estado === 'err') {
        input.classList.add('is-invalid');
        if (guia) { guia.classList.add('guia-err'); if (mensaje) guia.textContent = mensaje; }
    } else if (guia) {
        guia.textContent = TEXTO_GUIA_USU[campoId] || '';
    }
}

// Un campo vacío queda neutro (sin color) pero con su texto guía.
function validarCampoUsu(campoId, valorOk, mensajeOk, mensajeErr) {
    const vacio = document.getElementById(campoId).value.trim() === '';
    if (vacio) marcarCampoUsu(campoId, '', '');
    else marcarCampoUsu(campoId, valorOk ? 'ok' : 'err', valorOk ? mensajeOk : mensajeErr);
    return !vacio && valorOk;
}

function correoValidoUsu(correo) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo);
}

function passwordSeguraUsu(password) {
    return password.length >= 8 && /[a-z]/.test(password) && /[A-Z]/.test(password)
        && /\d/.test(password) && /[\W_]/.test(password);
}

// Valida todo el modal de alta y habilita el botón solo si todo está correcto.
function validarAlta() {
    const usuario = normalizarUsuarioCliente(document.getElementById('crear_usuario').value);
    const correo = document.getElementById('crear_correo').value.trim();
    const password = document.getElementById('crear_password').value;
    const confirmar = document.getElementById('crear_password_confirm').value;

    const usuarioOk = validarCampoUsu('crear_usuario',
        usuario.length >= 4 && usuario.length <= 20 && /^[a-zA-Z0-9_]+$/.test(usuario),
        'Nombre de usuario válido.', 'Solo letras, números y guion bajo (4 a 20 caracteres).');
    const correoOk = validarCampoUsu('crear_correo', correoValidoUsu(correo),
        'Correo electrónico válido.', 'El correo no tiene un formato válido.');
    const passwordOk = validarCampoUsu('crear_password', passwordSeguraUsu(password),
        'Contraseña segura.', 'Debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.');
    const confirmarOk = validarCampoUsu('crear_password_confirm', password !== '' && password === confirmar,
        'Las contraseñas coinciden.', 'Las contraseñas no coinciden.');
    const rolOk = validarCampoUsu('crear_rol', document.getElementById('crear_rol').value !== '',
        '', 'Selecciona un rol para el colaborador.');
    const preguntaOk = validarCampoUsu('crear_pregunta', document.getElementById('crear_pregunta').value !== '',
        '', 'Selecciona una pregunta de seguridad.');
    const respuestaOk = validarCampoUsu('crear_respuesta', document.getElementById('crear_respuesta').value.trim() !== '',
        'Respuesta válida.', 'Escribe la respuesta de seguridad.');

    const todoOk = usuarioOk && correoOk && passwordOk && confirmarOk && rolOk && preguntaOk && respuestaOk;
    document.getElementById('btnCrear').disabled = !todoOk;
    return todoOk;
}

// Valida el modal de edición (la seguridad es un par opcional).
function validarEdicion() {
    const usuario = normalizarUsuarioCliente(document.getElementById('edit_usuario').value);
    const correo = document.getElementById('edit_correo').value.trim();
    const pregunta = document.getElementById('edit_pregunta').value;
    const respuesta = document.getElementById('edit_respuesta').value.trim();

    const usuarioOk = validarCampoUsu('edit_usuario',
        usuario.length >= 4 && usuario.length <= 20 && /^[a-zA-Z0-9_]+$/.test(usuario),
        'Nombre de usuario válido.', 'Solo letras, números y guion bajo (4 a 20 caracteres).');
    const correoOk = validarCampoUsu('edit_correo', correoValidoUsu(correo),
        'Correo electrónico válido.', 'El correo no tiene un formato válido.');
    const rolOk = document.getElementById('edit_rol').value !== '';

    // La seguridad se aplica completa o no se aplica: si se rellena una mitad
    // sola, se marca la otra en rojo.
    const seguridadIncompleta = (pregunta !== '' || respuesta !== '') && !(pregunta !== '' && respuesta !== '');
    marcarCampoUsu('edit_pregunta', seguridadIncompleta && pregunta === '' ? 'err' : '',
        seguridadIncompleta && pregunta === '' ? 'Selecciona la pregunta que va con la respuesta.' : '');
    marcarCampoUsu('edit_respuesta', seguridadIncompleta && respuesta === '' ? 'err' : '',
        seguridadIncompleta && respuesta === '' ? 'Escribe la respuesta que va con la pregunta.' : '');

    const todoOk = usuarioOk && correoOk && rolOk && !seguridadIncompleta;
    document.getElementById('btnEditar').disabled = !todoOk;
    return todoOk;
}

// Valida el modal de restablecer contraseña.
function validarReset() {
    const password = document.getElementById('reset_password').value;
    const confirmar = document.getElementById('reset_password_confirm').value;

    const passwordOk = validarCampoUsu('reset_password', passwordSeguraUsu(password),
        'Contraseña segura.', 'Debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.');
    const confirmarOk = validarCampoUsu('reset_password_confirm', password !== '' && password === confirmar,
        'Las contraseñas coinciden.', 'Las contraseñas no coinciden.');

    const todoOk = passwordOk && confirmarOk;
    document.getElementById('btnResetPassword').disabled = !todoOk;
    return todoOk;
}

// Revalida los tres modales en cada tecla de sus campos.
['crear_usuario', 'crear_correo', 'crear_password', 'crear_password_confirm', 'crear_respuesta'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('input', validarAlta);
});
['crear_rol', 'crear_pregunta'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('change', validarAlta);
});
['edit_usuario', 'edit_correo', 'edit_respuesta'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('input', validarEdicion);
});
['edit_rol', 'edit_pregunta'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('change', validarEdicion);
});
['reset_password', 'reset_password_confirm'].forEach(function (id) {
    const campo = document.getElementById(id);
    if (campo) campo.addEventListener('input', validarReset);
});

// Abre el modal de alta con el formulario limpio.
function abrirCreacion() {
    document.getElementById('formCrear').reset();
    ['crear_usuario', 'crear_correo', 'crear_password', 'crear_password_confirm', 'crear_rol', 'crear_pregunta', 'crear_respuesta'].forEach(function (id) {
        marcarCampoUsu(id, '', '');
    });
    validarAlta();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCrear')).show();
}

// Abre el modal para que el administrador le restablezca la contraseña a un colaborador.
function abrirResetPassword(id, nombre) {
    document.getElementById('formResetPassword').reset();
    document.getElementById('reset_id_usuario').value = id;
    document.getElementById('reset_usuario_nombre').textContent = nombre;
    ['reset_password', 'reset_password_confirm'].forEach(function (id) {
        marcarCampoUsu(id, '', '');
    });
    validarReset();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalResetPassword')).show();
}

// Validación y envío del formulario de edición.
document.getElementById('formEditar').addEventListener('submit', function (e) {
    e.preventDefault();
    const inputUsuario = document.getElementById('edit_usuario');
    const inputCorreo = document.getElementById('edit_correo');
    const idUsuario = document.getElementById('edit_id_usuario').value;
    const status = document.getElementById('edit_status').value;

    const usuario = normalizarUsuarioCliente(inputUsuario.value);
    const correo = inputCorreo.value.trim();
    const rol = document.getElementById('edit_rol').value;

    if (usuario.length < 4 || usuario.length > 20 || !/^[a-zA-Z0-9_]+$/.test(usuario)) {
        inputUsuario.classList.add('input-error');
        Swal.fire('DATOS INVÁLIDOS', 'El usuario debe tener 4 a 20 caracteres: letras, números y guion bajo.', 'error');
        return;
    }
    inputUsuario.classList.remove('input-error');

    if (correo.length > 100 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        inputCorreo.classList.add('input-error');
        Swal.fire('DATOS INVÁLIDOS', 'Escribe un correo electrónico válido (máx 100 caracteres).', 'error');
        return;
    }
    inputCorreo.classList.remove('input-error');

    if (!rol) {
        Swal.fire('DATOS INVÁLIDOS', 'Selecciona un rol para el colaborador.', 'error');
        return;
    }

    // Seguridad opcional: o se rellenan las dos, o ninguna.
    const preguntaSeg = document.getElementById('edit_pregunta').value;
    const respuestaSeg = document.getElementById('edit_respuesta').value.trim();
    if ((preguntaSeg !== '' || respuestaSeg !== '') && (preguntaSeg === '' || respuestaSeg === '')) {
        Swal.fire('DATOS INCOMPLETOS', 'Para cambiar la seguridad, selecciona la pregunta y escribe la respuesta.', 'error');
        return;
    }

    Swal.fire({
        title: '¿GUARDAR CAMBIOS?',
        text: `Se actualizarán los datos del colaborador ${usuario}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7C3AED',
        cancelButtonColor: '#CED4DA',
        confirmButtonText: 'SÍ, GUARDAR',
        cancelButtonText: 'CANCELAR'
    }).then((result) => {
        if (result.isConfirmed) {
            jvPost({
                accion_usuario: 'editar',
                id_usuario: idUsuario,
                usuario: usuario,
                correo: correo,
                id_rol: rol,
                status: status,
                pregunta_seguridad: preguntaSeg,
                respuesta_seguridad: respuestaSeg,
                csrf_token: window.JV_CONFIG.csrfToken
            });
        }
    });
});

// Validación y envío del formulario de alta de colaborador.
document.getElementById('formCrear').addEventListener('submit', function (e) {
    e.preventDefault();
    const inputUsuario = document.getElementById('crear_usuario');
    const inputCorreo = document.getElementById('crear_correo');
    const inputPassword = document.getElementById('crear_password');
    const inputConfirm = document.getElementById('crear_password_confirm');

    const usuario = normalizarUsuarioCliente(inputUsuario.value);
    const correo = inputCorreo.value.trim();
    const password = inputPassword.value;
    const passwordConfirm = inputConfirm.value;
    const rol = document.getElementById('crear_rol').value;
    const pregunta = document.getElementById('crear_pregunta').value;
    const respuesta = document.getElementById('crear_respuesta').value.trim();

    if (usuario.length < 4 || usuario.length > 20 || !/^[a-zA-Z0-9_]+$/.test(usuario)) {
        inputUsuario.classList.add('input-error');
        Swal.fire('DATOS INVÁLIDOS', 'El usuario debe tener 4 a 20 caracteres: letras, números y guion bajo.', 'error');
        return;
    }
    inputUsuario.classList.remove('input-error');

    if (correo.length > 100 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        inputCorreo.classList.add('input-error');
        Swal.fire('DATOS INVÁLIDOS', 'Escribe un correo electrónico válido (máx 100 caracteres).', 'error');
        return;
    }
    inputCorreo.classList.remove('input-error');

    if (!(password.length >= 8 && /[a-z]/.test(password) && /[A-Z]/.test(password) && /\d/.test(password) && /[\W_]/.test(password))) {
        inputPassword.classList.add('input-error');
        Swal.fire('CONTRASEÑA DÉBIL', 'Debe tener mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.', 'error');
        return;
    }
    inputPassword.classList.remove('input-error');

    if (password !== passwordConfirm) {
        inputConfirm.classList.add('input-error');
        Swal.fire('DATOS INVÁLIDOS', 'Las contraseñas no coinciden.', 'error');
        return;
    }
    inputConfirm.classList.remove('input-error');

    if (!rol) {
        Swal.fire('DATOS INVÁLIDOS', 'Selecciona un rol para el colaborador.', 'error');
        return;
    }

    if (!pregunta || respuesta === '') {
        Swal.fire('DATOS INCOMPLETOS', 'Selecciona la pregunta de seguridad y escribe su respuesta.', 'error');
        return;
    }

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
                pregunta_seguridad: pregunta,
                respuesta_seguridad: respuesta,
                csrf_token: window.JV_CONFIG.csrfToken
            });
        }
    });
});

// Paginación inicial al cargar la página.
document.addEventListener('DOMContentLoaded', () => rendTablaUsuarios());

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