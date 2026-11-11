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

    function htmlFila(fila, puedeReponer, puedeDescartar) {
        const stock = Number(fila.stock) || 0;
        const minimo = Number(fila.minimo) || 0;
        const acciones = [];
        if (puedeReponer) {
            acciones.push('<a class="jv-alarma-reponer" href="' + enlaceReponer(fila) + '">Reponer</a>');
        }
        acciones.push('<button type="button" class="jv-alarma-posponer" data-posponer="' + Number(fila.id) + '">Posponer 2 h</button>');
        if (puedeDescartar) {
            acciones.push('<button type="button" class="jv-alarma-descartar" data-descartar="' + Number(fila.id) + '">Ya no vender</button>');
        }

        return '<div class="jv-alarma-item" data-producto="' + Number(fila.id) + '">' +
            '<div class="jv-alarma-info">' +
                '<strong>' + escapar(fila.nombre) + '</strong>' +
                '<span>Quedan ' + stock + ' · mínimo ' + minimo + '</span>' +
            '</div>' +
            '<div class="jv-alarma-acciones">' + acciones.join('') + '</div>' +
        '</div>';
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
        const html = '<div class="jv-alarma-lista">' +
            visibles.slice(0, 8).map(fila => htmlFila(fila, puedeReponer, puedeDescartar)).join('') +
            '</div>';

        Swal.fire({
            icon: 'warning',
            title: 'STOCK CRÍTICO',
            html: html,
            width: 560,
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
