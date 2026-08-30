// ==========================================
// MÓDULO: GUÍA DE USO (Manual del sistema)
// Navegación del índice de contenidos:
// scroll suave al pulsar y resaltado de la
// sección visible mientras se desplaza.
// ==========================================

(function () {
    'use strict';

    var toc = document.getElementById('manualToc');
    if (!toc || !('IntersectionObserver' in window)) {
        return;
    }

    var enlaces = Array.prototype.slice.call(toc.querySelectorAll('.manual-toc-item'));
    if (enlaces.length === 0) {
        return;
    }

    // Scroll suave al pulsar una entrada del índice (evita el salto seco).
    enlaces.forEach(function (enlace) {
        enlace.addEventListener('click', function (e) {
            var destino = document.getElementById(enlace.getAttribute('data-ancla'));
            if (destino) {
                e.preventDefault();
                destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Resalta la entrada del índice cuya sección está visible en pantalla.
    var secciones = enlaces
        .map(function (enlace) {
            return document.getElementById(enlace.getAttribute('data-ancla'));
        })
        .filter(Boolean);

    function activarEnlace(id) {
        enlaces.forEach(function (enlace) {
            enlace.classList.toggle('active', enlace.getAttribute('data-ancla') === id);
        });
    }

    var observador = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (entrada) {
            if (entrada.isIntersecting) {
                activarEnlace(entrada.target.id);
            }
        });
    }, { rootMargin: '-15% 0px -75% 0px', threshold: 0 });

    secciones.forEach(function (seccion) {
        observador.observe(seccion);
    });
})();