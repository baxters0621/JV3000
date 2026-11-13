/**
 * Alarma de stock: modal insistente pero no bloqueante.
 *
 * Se muestra al cargar el dashboard y antes de redirigir tras confirmar una
 * venta. Permite reponer (admin/carga) y descartar el producto para venta
 * (solo administrador). Al cerrar con ENTENDIDO reaparece en la próxima carga.
 */
(function () {
    'use strict';

    const config = window.JV_CONFIG || {};
    let mostrado = false;
    let cerrando = false;
    // SweetAlert2 no admite dos modales abiertos: al pedir confirmación se
    // cierra la alarma y este flag evita que ese cierre dispare la redirección.
    let confirmandoDescarte = false;
    let ultimosDatos = null;
    let alCerrarPendiente = null;
    let errorDescarte = null;

    // URL base del sistema, con barra final cuando corresponde.
    function baseUrl() {
        const base = window.JV_BASE || config.prefijo || '';
        if (base === '') return '';
        return base.endsWith('/') ? base : base + '/';
    }

    function escapar(texto) {
        return String(texto ?? '').replace(/[&<>"']/g, caracter => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[caracter]);
    }

    function enlaceReponer(fila) {
        // Si la reposición ya está pedida pero sin recibir, el botón lleva
        // directo a la recepción de esa compra en lugar de a una nueva.
        if (Number(fila.id_compra) > 0) {
            return baseUrl() + 'index.php?url=compras&recepcion_compra=' + encodeURIComponent(fila.id_compra);
        }
        let url = baseUrl() + 'index.php?url=compras&reponer_producto=' + encodeURIComponent(fila.id);
        if (Number(fila.id_proveedor) > 0) {
            url += '&reponer_proveedor=' + encodeURIComponent(fila.id_proveedor);
        }
        return url;
    }

    // Inyecta los estilos propios del modal una sola vez. La Nota de Entrega
    // se renderiza sin el layout global, así que el CSS no puede depender de
    // diseno.css para que la alarma se vea igual en todos los contextos.
    function inyectarEstilos() {
        if (document.getElementById('jv-alarma-estilos')) return;
        const estilos = document.createElement('style');
        estilos.id = 'jv-alarma-estilos';
        estilos.textContent = `
            .jv-alarma-popup { width: min(600px, 94vw); border-radius: 18px; }
            .swal2-title { color: #0f172a !important; font-weight: 800; letter-spacing: -.3px; }
            .swal2-icon.swal2-warning { border-color: #fecaca; color: #dc2626; animation: jvAlarmaPulso 2s ease-in-out infinite; }
            .jv-alarma-intro { margin: 0 0 .7rem; font-size: .85rem; font-weight: 600; color: #94a3b8; }
            .jv-alarma-lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .65rem; max-height: 46vh; overflow-y: auto; }
            .jv-alarma-item { display: flex; align-items: center; justify-content: space-between; gap: .85rem; padding: .85rem .95rem; background: #fff; border: 1px solid #eef2f7; border-radius: 14px; box-shadow: 0 1px 2px rgba(15,42,76,.06); text-align: left; }
            .jv-alarma-datos { position: relative; min-width: 0; padding-left: 2.9rem; }
            .jv-alarma-datos::before { content: "!"; position: absolute; left: 0; top: .1rem; width: 36px; height: 36px; display: grid; place-items: center; border-radius: 50%; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 1.05rem; font-weight: 900; line-height: 1; }
            .jv-alarma-nombre { margin: 0; font-size: .94rem; font-weight: 750; color: #0f2a4c; line-height: 1.3; }
            .jv-alarma-metricas { margin: .15rem 0 0; font-size: .78rem; color: #64748b; }
            .jv-alarma-stock { color: #b91c1c; font-weight: 800; }
            .jv-alarma-camino { color: #047857; font-weight: 750; }
            .jv-alarma-sep { margin: 0 .22rem; color: #cbd5e1; }
            .jv-alarma-acciones { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .45rem; flex-shrink: 0; }
            .jv-alarma-acciones .swal2-styled { margin: 0; font-size: .78rem; padding: .5rem .95rem; min-height: 0; box-shadow: none; border-radius: 999px; }
            .jv-alarma-acciones .jv-alarma-reponer { background: #0f2a4c; border: 1px solid #0f2a4c; color: #fff; font-weight: 750; text-decoration: none; transition: transform .16s ease, background-color .16s ease, box-shadow .16s ease; }
            .jv-alarma-acciones .jv-alarma-reponer:hover { background: #1e3a5f; color: #fff; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(15,42,76,.22); }
            .jv-alarma-descartar { margin-left: auto; border: 0; background: transparent; padding: .2rem .1rem; font: inherit; font-size: .72rem; font-weight: 650; color: #94a3b8; text-decoration: none; cursor: pointer; white-space: nowrap; }
            .jv-alarma-descartar::before { content: "🗑"; margin-right: .25rem; font-size: .8rem; }
            .jv-alarma-descartar:hover { color: #b91c1c; text-decoration: underline; }
            .jv-alarma-error { display: block; margin-top: .35rem; font-size: .74rem; font-weight: 700; color: #b91c1c; }
            @keyframes jvAlarmaPulso {
                0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220,38,38,.18); }
                50% { transform: scale(1.04); box-shadow: 0 0 0 10px rgba(220,38,38,0); }
            }
            @media (max-width: 576px) {
                .jv-alarma-popup { width: 94vw; }
                .jv-alarma-item { flex-direction: column; align-items: stretch; }
                .jv-alarma-acciones { justify-content: flex-start; }
                .jv-alarma-descartar { margin-left: 0; }
            }
            @media (prefers-reduced-motion: reduce) {
                .swal2-icon.swal2-warning { animation: none; }
                .jv-alarma-acciones .jv-alarma-reponer { transition: none; }
                .jv-alarma-acciones .jv-alarma-reponer:hover { transform: none; }
            }
        `;
        document.head.appendChild(estilos);
    }

    // Ejecuta la acción de cierre una sola vez. Se ignora mientras la alarma
    // está oculta por el diálogo de confirmación para no redirigir antes de
    // que el usuario decida.
    function finalizar() {
        if (cerrando || confirmandoDescarte) return;
        cerrando = true;
        if (typeof alCerrarPendiente === 'function') alCerrarPendiente();
    }

    function htmlFila(fila, puedeReponer, puedeDescartar) {
        const stock = Number(fila.stock) || 0;
        const minimo = Number(fila.minimo) || 0;
        const enCamino = Number(fila.en_camino) || 0;
        const acciones = [];
        if (puedeReponer) {
            acciones.push('<a class="swal2-styled swal2-confirm jv-alarma-reponer" role="button" href="' + enlaceReponer(fila) + '">' + (enCamino > 0 ? 'Recibir' : 'Reponer') + '</a>');
        }
        if (puedeDescartar) {
            acciones.push('<button type="button" class="jv-alarma-descartar" data-descartar="' + Number(fila.id) + '">Ya no vender</button>');
        }

        const error = errorDescarte && Number(errorDescarte.id) === Number(fila.id)
            ? '<span class="jv-alarma-error">' + escapar(errorDescarte.mensaje) + '</span>'
            : '';

        // «En camino» evita que el usuario registre una segunda compra cuando
        // la primera todavía está pendiente de recepción.
        const camino = enCamino > 0
            ? '<span class="jv-alarma-sep">·</span><span class="jv-alarma-camino">En camino ' + enCamino + '</span>'
            : '';

        return '<li class="jv-alarma-item" data-producto="' + Number(fila.id) + '">' +
            '<div class="jv-alarma-datos">' +
                '<p class="jv-alarma-nombre">' + escapar(fila.nombre) + '</p>' +
                '<p class="jv-alarma-metricas">' +
                    '<span class="jv-alarma-stock">Quedan ' + stock + '</span>' +
                    camino +
                    '<span class="jv-alarma-sep">·</span>' +
                    '<span>mínimo ' + minimo + '</span>' +
                '</p>' + error +
            '</div>' +
            '<div class="jv-alarma-acciones">' + acciones.join('') + '</div>' +
        '</li>';
    }

    // Vuelve a pintar la alarma con el estado guardado. El error de un descarte
    // fallido se muestra en su fila; los demás productos siguen disponibles.
    function reabrirAlarma() {
        confirmandoDescarte = false;
        mostrado = false;
        if (!ultimosDatos) return;
        const bajos = Array.isArray(ultimosDatos.bajos) ? ultimosDatos.bajos : [];
        if (bajos.length === 0) {
            finalizar();
            return;
        }
        mostrar(ultimosDatos, alCerrarPendiente);
    }

    function quitarProducto(id) {
        if (!ultimosDatos || !Array.isArray(ultimosDatos.bajos)) return;
        const objetivo = Number(id);
        ultimosDatos.bajos = ultimosDatos.bajos.filter(fila => Number(fila.id) !== objetivo);
    }

    // Confirma el descarte con el mismo Swal del resto del sistema.
    function confirmarDescarte(id, contenedor) {
        const nombre = contenedor.querySelector('.jv-alarma-nombre');
        confirmandoDescarte = true;
        Swal.fire({
            icon: 'warning',
            title: '¿YA NO VENDER?',
            html: 'Dejará de venderse y no volverá a aparecer en la alarma:' +
                '<br><strong>' + escapar(nombre ? nombre.textContent : 'este producto') + '</strong>',
            showCancelButton: true,
            background: '#fff',
            color: '#212529',
            confirmButtonColor: '#DC2626',
            cancelButtonColor: '#CED4DA',
            confirmButtonText: 'SÍ, DESCARTAR',
            cancelButtonText: 'CANCELAR',
            reverseButtons: true
        }).then(resultado => {
            if (!resultado.isConfirmed) {
                reabrirAlarma();
                return;
            }
            descartarProducto(id);
        });
    }

    function descartarProducto(id) {
        const datos = new FormData();
        datos.append('id_producto', String(id));
        if (config.csrfToken) datos.append('csrf_token', config.csrfToken);

        fetch(baseUrl() + 'index.php?url=productos/descartar', {
            method: 'POST',
            body: datos,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(respuesta => respuesta.json())
            .then(respuesta => {
                if (!respuesta || !respuesta.success) {
                    throw new Error((respuesta && respuesta.message) || 'No se pudo descartar.');
                }
                quitarProducto(id);
                if (!ultimosDatos || !Array.isArray(ultimosDatos.bajos) || ultimosDatos.bajos.length === 0) {
                    confirmandoDescarte = false;
                    finalizar();
                    return;
                }
                reabrirAlarma();
            })
            .catch(error => {
                errorDescarte = { id: Number(id), mensaje: error.message || 'No se pudo descartar.' };
                reabrirAlarma();
            });
    }

    /**
     * Muestra la alarma si hay productos críticos.
     *
     * @param {object} datos Respuesta de jv_alertas_por_rol().
     * @param {Function} [alCerrar] Acción a ejecutar al cerrar (p. ej. redirigir).
     * @returns {boolean} True si el modal se abrió.
     */
    function mostrar(datos, alCerrar) {
        if (mostrado) return false;
        if (typeof alCerrar === 'function') alCerrarPendiente = alCerrar;
        ultimosDatos = datos;
        const bajos = datos && Array.isArray(datos.bajos) ? datos.bajos : [];
        const visibles = bajos.slice(0, 8);
        if (visibles.length === 0) {
            finalizar();
            return false;
        }

        mostrado = true;
        const puedeReponer = Boolean(datos.puede_reponer);
        const puedeDescartar = Boolean(datos.puede_descartar);
        const html = '<div class="jv-alarma">' +
            '<p class="jv-alarma-intro">Estos productos requieren atención:</p>' +
            '<ul class="jv-alarma-lista">' +
                visibles.slice(0, 8).map(fila => htmlFila(fila, puedeReponer, puedeDescartar)).join('') +
            '</ul>' +
        '</div>';
        errorDescarte = null;

        inyectarEstilos();
        Swal.fire({
            icon: 'warning',
            title: 'STOCK CRÍTICO',
            html: html,
            allowOutsideClick: true,
            allowEscapeKey: true,
            confirmButtonText: 'ENTENDIDO',
            confirmButtonColor: '#0f2a4c',
            customClass: { popup: 'jv-alarma-popup' },
            didOpen: () => {
                const popup = Swal.getPopup();
                if (!popup) return;
                popup.addEventListener('click', evento => {
                    const botonDescartar = evento.target.closest('[data-descartar]');
                    if (botonDescartar) {
                        const fila = botonDescartar.closest('.jv-alarma-item');
                        if (fila) confirmarDescarte(botonDescartar.dataset.descartar, fila);
                    }
                });
            }
        }).then(() => finalizar());

        return true;
    }

    window.JVAlarmaStock = { mostrar: mostrar };
})();
