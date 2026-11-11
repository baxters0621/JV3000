/**
 * Alarma de stock: modal insistente pero no bloqueante.
 *
 * Se muestra al cargar el dashboard y antes de redirigir tras confirmar una
 * venta. Permite reponer (admin/carga), posponer por producto durante 2 horas
 * y descartar el producto para venta (solo administrador).
 */
(function () {
    'use strict';

    const CLAVE_SNOOZE = 'jv_stock_snooze_v1:';
    const DURACION_SNOOZE = 2 * 60 * 60 * 1000;
    const config = window.JV_CONFIG || {};
    let mostrado = false;
    let cerrando = false;

    // URL base del sistema, con barra final cuando corresponde.
    function baseUrl() {
        const base = window.JV_BASE || config.prefijo || '';
        if (base === '') return '';
        return base.endsWith('/') ? base : base + '/';
    }

    function claveSnooze() {
        return CLAVE_SNOOZE + (Number(config.userId) || 0);
    }

    function leerSnooze() {
        try {
            const crudo = localStorage.getItem(claveSnooze());
            const mapa = crudo ? JSON.parse(crudo) : {};
            return mapa && typeof mapa === 'object' ? mapa : {};
        } catch (error) {
            return {};
        }
    }

    function guardarSnooze(mapa) {
        try {
            localStorage.setItem(claveSnooze(), JSON.stringify(mapa));
        } catch (error) {
            // Sin almacenamiento disponible la sesión actual sigue funcionando.
        }
    }

    // Limpia expirados y devuelve el mapa vigente.
    function snoozeVigente() {
        const ahora = Date.now();
        const mapa = leerSnooze();
        let cambio = false;
        Object.keys(mapa).forEach(id => {
            if (!Number.isFinite(Number(mapa[id])) || Number(mapa[id]) <= ahora) {
                delete mapa[id];
                cambio = true;
            }
        });
        if (cambio) guardarSnooze(mapa);
        return mapa;
    }

    function posponer(id) {
        const mapa = snoozeVigente();
        mapa[String(id)] = Date.now() + DURACION_SNOOZE;
        guardarSnooze(mapa);
    }

    function escapar(texto) {
        return String(texto ?? '').replace(/[&<>"']/g, caracter => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[caracter]);
    }

    function enlaceReponer(fila) {
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
            .jv-alarma-popup { width: min(680px, 94vw); }
            .jv-alarma-intro { margin: 0 0 .55rem; font-size: .85rem; font-weight: 600; color: #64748b; }
            .jv-alarma-lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .6rem; max-height: 46vh; overflow-y: auto; }
            .jv-alarma-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem .8rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; text-align: left; }
            .jv-alarma-datos { min-width: 0; }
            .jv-alarma-nombre { margin: 0; font-size: .92rem; font-weight: 750; color: #0f2a4c; line-height: 1.3; }
            .jv-alarma-metricas { margin: .15rem 0 0; font-size: .78rem; color: #64748b; }
            .jv-alarma-stock { color: #b91c1c; font-weight: 800; }
            .jv-alarma-sep { margin: 0 .2rem; color: #94a3b8; }
            .jv-alarma-acciones { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .4rem; flex-shrink: 0; }
            .jv-alarma-acciones .swal2-styled { margin: 0; font-size: .75rem; padding: .4rem .7rem; min-height: 0; box-shadow: none; }
            .jv-alarma-acciones .jv-alarma-reponer { background: #0f2a4c; border: 1px solid #0f2a4c; border-radius: 8px; color: #fff; font-weight: 750; text-decoration: none; }
            .jv-alarma-acciones .jv-alarma-reponer:hover { background: #1e3a5f; color: #fff; }
            .jv-alarma-acciones .jv-alarma-posponer { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; color: #0f2a4c; font-weight: 750; }
            .jv-alarma-acciones .jv-alarma-posponer:hover { border-color: #0e7490; color: #0e7490; }
            .jv-alarma-descartar { border: 0; background: transparent; padding: .2rem .1rem; font: inherit; font-size: .74rem; font-weight: 700; color: #b91c1c; text-decoration: underline; cursor: pointer; }
            .jv-alarma-descartar:hover { color: #7f1d1d; }
            .jv-alarma-error { display: block; margin-top: .35rem; font-size: .74rem; font-weight: 700; color: #b91c1c; }
            @media (max-width: 576px) {
                .jv-alarma-item { flex-direction: column; align-items: flex-start; }
                .jv-alarma-acciones { justify-content: flex-start; }
            }
        `;
        document.head.appendChild(estilos);
    }

    function htmlFila(fila, puedeReponer, puedeDescartar) {
        const stock = Number(fila.stock) || 0;
        const minimo = Number(fila.minimo) || 0;
        const acciones = [];
        if (puedeReponer) {
            acciones.push('<a class="swal2-styled swal2-confirm jv-alarma-reponer" role="button" href="' + enlaceReponer(fila) + '">Reponer</a>');
        }
        acciones.push('<button type="button" class="swal2-styled swal2-cancel jv-alarma-posponer" data-posponer="' + Number(fila.id) + '">Posponer 2 h</button>');
        if (puedeDescartar) {
            acciones.push('<button type="button" class="jv-alarma-descartar" data-descartar="' + Number(fila.id) + '">Ya no vender</button>');
        }

        return '<li class="jv-alarma-item" data-producto="' + Number(fila.id) + '">' +
            '<div class="jv-alarma-datos">' +
                '<p class="jv-alarma-nombre">' + escapar(fila.nombre) + '</p>' +
                '<p class="jv-alarma-metricas">' +
                    '<span class="jv-alarma-stock">Quedan ' + stock + '</span>' +
                    '<span class="jv-alarma-sep">·</span>' +
                    '<span>mínimo ' + minimo + '</span>' +
                '</p>' +
            '</div>' +
            '<div class="jv-alarma-acciones">' + acciones.join('') + '</div>' +
        '</li>';
    }

    function descartarProducto(id, contenedor) {
        if (!window.confirm('¿Descartar este producto? Dejará de venderse y no volverá a aparecer en la alarma.')) {
            return;
        }
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
                contenedor.remove();
                if (!document.querySelector('.jv-alarma-item')) {
                    Swal.close();
                }
            })
            .catch(error => {
                const aviso = document.createElement('span');
                aviso.className = 'jv-alarma-error';
                aviso.textContent = error.message || 'No se pudo descartar.';
                contenedor.appendChild(aviso);
            });
    }

    /**
     * Muestra la alarma si hay productos pendientes y no se pospusieron.
     *
     * @param {object} datos Respuesta de jv_alertas_por_rol().
     * @param {Function} [alCerrar] Acción a ejecutar al cerrar (p. ej. redirigir).
     * @returns {boolean} True si el modal se abrió.
     */
    function mostrar(datos, alCerrar) {
        const finalizar = () => {
            if (cerrando) return;
            cerrando = true;
            if (typeof alCerrar === 'function') alCerrar();
        };

        if (mostrado) return false;
        const bajos = datos && Array.isArray(datos.bajos) ? datos.bajos : [];
        const snooze = snoozeVigente();
        const visibles = bajos.filter(fila => !snooze[String(fila.id)]);
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
                    const botonPosponer = evento.target.closest('[data-posponer]');
                    if (botonPosponer) {
                        const id = botonPosponer.dataset.posponer;
                        posponer(id);
                        const fila = botonPosponer.closest('.jv-alarma-item');
                        if (fila) fila.remove();
                        if (!popup.querySelector('.jv-alarma-item')) Swal.close();
                        return;
                    }
                    const botonDescartar = evento.target.closest('[data-descartar]');
                    if (botonDescartar) {
                        const fila = botonDescartar.closest('.jv-alarma-item');
                        if (fila) descartarProducto(botonDescartar.dataset.descartar, fila);
                    }
                });
            }
        }).then(() => finalizar());

        return true;
    }

    window.JVAlarmaStock = { mostrar: mostrar };
})();
