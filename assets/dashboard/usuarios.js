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

// Abre el modal de alta con el formulario limpio.
function abrirCreacion() {
    document.getElementById('formCrear').reset();
    ['crear_usuario', 'crear_correo', 'crear_password', 'crear_password_confirm', 'crear_respuesta'].forEach(function (id) {
        document.getElementById(id).classList.remove('input-error');
    });
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCrear')).show();
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