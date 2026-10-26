

        // Activa el filtro de vencimiento correspondiente según la alerta recibida.
        function filtrarPorAlerta(clase) {
            var alertFilterButton = document.querySelector('.btn-filtro-venc[data-venc="' + clase + '"]');
            if (alertFilterButton) filtrarVenc(alertFilterButton);
        }

        // Aplica los filtros activos (estado, vencimiento, stock bajo y búsqueda) sobre las filas.
        function aplicarFiltros() {
            var activeExpirationButton = document.querySelector('.btn-filtro-venc.active');
            var expirationFilter = activeExpirationButton ? activeExpirationButton.getAttribute('data-venc') : 'todas';
            var activeStatusButton = document.querySelector('.btn-filter-prod.active');
            var statusFilter = activeStatusButton ? activeStatusButton.getAttribute('data-status') : 'todas';
            var searchValue = document.getElementById('buscar').value.toLowerCase();
            var productRows = document.getElementById('tablaProductos').getElementsByTagName('tr');
            for (var rowIndex = 0; rowIndex < productRows.length; rowIndex++) {
                var productRow = productRows[rowIndex];
                var productStatus = productRow.getAttribute('data-status') || '';
                if (statusFilter !== 'todas' && productStatus !== statusFilter) { productRow.style.display = 'none'; continue; }
                var expirationClass = productRow.getAttribute('data-venc-cls') || '';
                if (expirationFilter !== 'todas' && expirationClass !== expirationFilter) { productRow.style.display = 'none'; continue; }
                if (filtroBajos) {
                    var currentStock = parseInt(productRow.getAttribute('data-stock') || '', 10);
                    var minimumStock = parseInt(productRow.getAttribute('data-minimo') || '', 10);
                    if (isNaN(currentStock) || currentStock > minimumStock) { productRow.style.display = 'none'; continue; }
                }
                var rowText = productRow.textContent.toLowerCase();
                productRow.style.display = (searchValue === '' || rowText.includes(searchValue)) ? '' : 'none';
            }
        }
        // Marca el botón de filtro por vencimiento activo y vuelve a aplicar los filtros.
        function filtrarVenc(expirationButton) {
            document.querySelectorAll('.btn-filtro-venc').forEach(function(b) {
                b.classList.remove('active');
                b.style.background = 'transparent';
                b.style.color = b.dataset.venc === 'vencido' ? '#DC2626' : b.dataset.venc === 'proximo' ? '#D97706' : b.dataset.venc === 'pronto' ? '#D97706' : b.dataset.venc === 'vigente' ? '#16A34A' : '#EA580C';
            });
            expirationButton.classList.add('active');
            expirationButton.style.background = 'rgba(234,88,12,0.15)';
            expirationButton.style.color = '#EA580C';
            aplicarFiltros();
        }

        // Pide confirmación para dar de baja un producto vencido (marcarlo inactivo).
        function bajaVencido(id, nombre) {
            Swal.fire({
                title: '¿DAR DE BAJA?',
                html: 'Se marcará como <strong>Inactivo</strong> por vencimiento: ' + escapeHtml(nombre),
                icon: 'warning',
                showCancelButton: true,
                background: '#fff',
                color: '#212529',
                confirmButtonColor: '#64748B',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'SÍ, DAR DE BAJA',
                cancelButtonText: 'CANCELAR'
            }).then(function(r) {
                if (r.isConfirmed) jvPost({ baja_vencido: id, csrf_token: window.JV_CONFIG.csrfToken });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#formEditar input, #formEditar select').forEach(function(el) {
                el.addEventListener('input', function() {
                    this.classList.remove('input-error');
                    var e = document.getElementById(this.id + '_err');
                    if (e) e.remove();
                });
                el.addEventListener('change', function() {
                    this.classList.remove('input-error');
                    var e = document.getElementById(this.id + '_err');
                    if (e) e.remove();
                });
            });
        });

        var modalEditar = null;
        document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('modalEditar');
            if (el) modalEditar = new bootstrap.Modal(el);
        });

        // Convierte un precio local (1.000,00) a número para validarlo o enviarlo.
        function leerPrecioEdicion(valor) {
            var texto = String(valor || '').trim().replace(/\s/g, '');
            if (!texto) return NaN;
            if (texto.includes(',') && texto.includes('.')) {
                texto = texto.replace(/\./g, '').replace(',', '.');
            } else if (texto.includes(',')) {
                texto = texto.replace(',', '.');
            } else if (/^\d{1,3}(\.\d{3})+$/.test(texto)) {
                texto = texto.replace(/\./g, '');
            }
            return Number(texto);
        }

        // Convierte lo tecleado en un precio ordenado con reglas fijas:
        // - El punto SIEMPRE es separador de miles (nunca decimal): borrar
        //   "1.000,00" deja "1.000" y sigue valiendo mil, no se corrompe a "1,00".
        // - La coma (o un punto final recien tecleado) activa los decimales (max 2).
        // - Maximo 5 digitos enteros: imposible escribir numeros infinitos.
        function formatearEntradaPrecio(input) {
            var cursor = input.selectionStart || 0;
            var original = input.value;
            var antesCursor = original.slice(0, cursor);
            var digitosAntes = (antesCursor.match(/\d/g) || []).length;
            var limpio = original.replace(/[^0-9.,]/g, '');
            var salida = '';

            function grupoEntero(digitos) {
                // Sin ceros a la izquierda sobrantes y tope de 5 digitos (99.999)
                var entero = digitos.replace(/^0+(?=\d)/, '').slice(0, 5);
                return entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            if (/[.,]$/.test(limpio)) {
                // El usuario acaba de teclear el separador decimal: entra en modo decimales
                var idxFin = limpio.length - 1;
                salida = grupoEntero(limpio.slice(0, idxFin).replace(/\D/g, '')) + ',';
            } else if (limpio.indexOf(',') !== -1) {
                // Modo decimal activo: coma como separador y hasta 2 decimales
                var idxComa = limpio.indexOf(',');
                var enteroDec = grupoEntero(limpio.slice(0, idxComa).replace(/\D/g, ''));
                var decimales = limpio.slice(idxComa + 1).replace(/\D/g, '').slice(0, 2);
                salida = enteroDec + ',' + decimales;
            } else {
                // Solo enteros: todos los puntos son miles
                salida = grupoEntero(limpio.replace(/\D/g, ''));
            }

            input.value = salida;
            if (!salida) {
                input.setSelectionRange(0, 0);
                return;
            }
            // Reubica el cursor segun la cantidad de digitos que tenia a su espalda
            var nuevoCursor = 0;
            var vistos = 0;
            while (nuevoCursor < salida.length && vistos < digitosAntes) {
                if (/\d/.test(salida[nuevoCursor])) vistos++;
                nuevoCursor++;
            }
            if (digitosAntes === 0 && antesCursor.match(/[,.]$/)) nuevoCursor = Math.min(1, salida.length);
            input.setSelectionRange(nuevoCursor, nuevoCursor);
        }

        // Muestra precios con separador de miles y dos decimales.
        function formatearPrecioEdicion(input) {
            if (!input || input.value.trim() === '') return;
            var valor = leerPrecioEdicion(input.value);
            var maximo = Number(input.dataset.max || 999999);
            if (Number.isFinite(valor) && valor > 0 && valor <= maximo) {
                input.value = valor.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        function precioCanonicoEdicion(input) {
            var valor = leerPrecioEdicion(input.value);
            return Number.isFinite(valor) ? valor.toFixed(2) : input.value;
        }

        function precioTieneFormatoValido(valor) {
            return /^(?:0\.(?:0[1-9]|[1-9]\d)|[1-9]\d{0,4}\.\d{2})$/.test(valor)
                && Number.isFinite(Number(valor))
                && Number(valor) >= 0.01
                && Number(valor) <= 99999.99;
        }

        document.addEventListener('DOMContentLoaded', function() {
            ['edit_pvp', 'edit_costo'].forEach(function(id) {
                var input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', function() {
                        formatearEntradaPrecio(this);
                    });
                    input.addEventListener('blur', function() {
                        formatearPrecioEdicion(this);
                    });
                }
            });
            // Stocks: SOLO digitos enteros (bloquea decimales, negativos y numeros infinitos)
            ['edit_minimo', 'edit_maximo'].forEach(function(id) {
                var input = document.getElementById(id);
                if (input) {
                    input.setAttribute('inputmode', 'numeric');
                    input.addEventListener('input', function() {
                        var limpio = this.value.replace(/\D/g, '').slice(0, 6);
                        if (limpio !== this.value) this.value = limpio;
                    });
                }
            });
        });

        // Valida los campos del formulario de edición y envía el formulario si son correctos.
        function validarEditarProducto(btn) {
            var precioVenta = document.getElementById('edit_pvp');
            var valorVenta = leerPrecioEdicion(precioVenta.value);
            var canonicoVenta = Number.isFinite(valorVenta) ? valorVenta.toFixed(2) : '';
            limpiarErrores();
            let primerError = null;
            const minimo = document.getElementById('edit_minimo');
            const maximo = document.getElementById('edit_maximo');
            const pvp = document.getElementById('edit_pvp');
            if (!/^\d{1,5}$/.test(minimo.value.trim()) || parseInt(minimo.value, 10) <= 0) {
                marcarError(minimo, 'STOCK MÍNIMO: ENTERO ENTRE 1 Y 99.999');
                if (!primerError) primerError = minimo;
            }
            if (maximo && !/^\d{1,6}$/.test(maximo.value.trim())) {
                marcarError(maximo, 'CAPACIDAD MÁXIMA: 0 (HEREDAR CATEGORÍA) O ENTERO HASTA 999.999');
                if (!primerError) primerError = maximo;
            }
            if (maximo && /^\d{1,6}$/.test(maximo.value.trim()) && parseInt(maximo.value, 10) > 0
                && /^\d{1,5}$/.test(minimo.value.trim()) && parseInt(maximo.value, 10) < parseInt(minimo.value, 10)) {
                marcarError(maximo, 'DEBE SER MAYOR O IGUAL AL STOCK MÍNIMO');
                if (!primerError) primerError = maximo;
            }
            if (!precioTieneFormatoValido(canonicoVenta)) {
                marcarError(pvp, valorVenta > 99999.99 ? 'MÁXIMO 99.999,99' : 'FORMATO: 0,00 (MÁXIMO 99.999,99)');
                if (!primerError) primerError = pvp;
            }
            const vencimiento = document.getElementById('edit_vencimiento');
            if (!vencimiento.value.trim()) {
                marcarError(vencimiento, 'FECHA DE VENCIMIENTO REQUERIDA');
                if (!primerError) primerError = vencimiento;
            }
            if (primerError) {
                primerError.focus();
                return false;
            }
            document.getElementById('edit_pvp_valor').value = canonicoVenta;
            btn.disabled = true;
            btn.innerHTML = '<span class=\'spinner-border spinner-border-sm me-1\'></span>GUARDANDO...';
            btn.form.submit();
            return false;
        }

        // Llena el modal de edición con los datos del producto de la fila seleccionada.
        function editarProducto(id) {
            limpiarErrores();
            var row = document.querySelector('tr[data-id="' + id + '"]');
            if (!row) return;
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = row.getAttribute('data-nombre').toUpperCase();
            document.getElementById('edit_sku').value = row.getAttribute('data-sku');
            document.getElementById('edit_stock').value = row.getAttribute('data-stock');
            document.getElementById('edit_minimo').value = row.getAttribute('data-minimo');
            document.getElementById('edit_maximo').value = row.getAttribute('data-maximo');
            document.getElementById('edit_pvp').value = parseFloat(row.getAttribute('data-pvp')).toFixed(2);
            document.getElementById('edit_costo').value = parseFloat(row.getAttribute('data-costo')).toFixed(2);
            formatearPrecioEdicion(document.getElementById('edit_pvp'));
            formatearPrecioEdicion(document.getElementById('edit_costo'));
            document.getElementById('edit_status').value = row.getAttribute('data-status');
            document.getElementById('edit_vencimiento').value = row.getAttribute('data-venc');
            if (modalEditar) modalEditar.show();
        }

        // Pide confirmación para activar o desactivar un producto según la acción solicitada.
        function toggleProducto(id, nombre, accion) {
            var esActivar = accion === 'activar';
            Swal.fire({
                title: esActivar ? '¿REACTIVAR?' : '¿DESACTIVAR?',
                html: (esActivar ? 'Se reactivará <strong>' : 'Se desactivará <strong>') + escapeHtml(nombre) + '</strong>' + (esActivar ? '' : ' del inventario.'),
                icon: 'warning',
                showCancelButton: true,
                background: '#fff',
                color: '#212529',
                confirmButtonColor: esActivar ? '#16A34A' : '#DC2626',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: esActivar ? 'SÍ, REACTIVAR' : 'SÍ, DESACTIVAR',
                cancelButtonText: 'CANCELAR'
            }).then(function(r) {
                if (r.isConfirmed) {
                    jvPost({ toggle: id, csrf_token: window.JV_CONFIG.csrfToken });
                }
            });
        }

        // Marca el botón de filtro por estado activo y vuelve a aplicar los filtros.
        function filtrarStatus(btn) {
            document.querySelectorAll('.btn-filter-prod').forEach(function(b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            aplicarFiltros();
        }

        // Vuelve a aplicar los filtros activos de la tabla.
        function filtrar() {
            aplicarFiltros();
        }

        var filtroBajos = false;

        // Activa o desactiva el filtro de stock bajo y vuelve a aplicar los filtros.
        function filtrarBajos() {
            filtroBajos = !filtroBajos;
            document.querySelectorAll('.btn-filtro-venc').forEach(function(b) {
                b.classList.remove('active');
                b.style.background = 'transparent';
                b.style.color = b.dataset.venc === 'vencido' ? '#DC2626' : b.dataset.venc === 'proximo' ? '#D97706' : b.dataset.venc === 'pronto' ? '#D97706' : b.dataset.venc === 'vigente' ? '#16A34A' : '#EA580C';
            });
            aplicarFiltros();
        }

        // Resalta la fila del producto en la tabla, la busca y la ubica en la vista.
        function destacarProducto(id) {
            var fila = document.querySelector('#tablaProductos tr[data-id="' + id + '"]');
            if (!fila) return;
            var nombre = fila.getAttribute('data-nombre') || '';
            var buscar = document.getElementById('buscar');
            if (buscar && nombre) {
                buscar.value = nombre.toUpperCase();
                aplicarFiltros();
            }
            setTimeout(function() {
                fila.scrollIntoView({ block: 'center', behavior: 'smooth' });
                fila.classList.add('flash-prod');
                setTimeout(function() { fila.classList.remove('flash-prod'); }, 3000);
            }, 250);
        }

        // Lee los parámetros de la URL y ejecuta la acción correspondiente (destacar o filtrar).
        function iniciarDesdeURL() {
            var params = new URLSearchParams(window.location.search);
            var pid = params.get('producto');
            var alerta = params.get('alerta');
            if (pid) {
                destacarProducto(pid);
            } else if (alerta === 'vencidos') {
                filtrarPorAlerta('vencido');
            } else if (alerta === 'proximos') {
                filtrarPorAlerta('proximo');
            } else if (alerta === 'prontos') {
                filtrarPorAlerta('pronto');
            } else if (alerta === 'bajos') {
                filtrarBajos();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            iniciarDesdeURL();
        });

                // ==========================================
        // ÁRBOL DE CATEGORÍAS (panel izquierdo) + FORMULARIO
        // Consulta para todos los roles; escritura según rol.
        // ==========================================

        // Expande o contrae la rama de una categoría raíz.
        function catAlternarRama(boton) {
            const nodo = boton.closest('.cat-nodo');
            if (nodo) nodo.classList.toggle('cat-abierta');
        }

        var catVolverAlGestor = false;

        // Abre el gestor sin quitar espacio al listado de productos.
        function catAbrirGestor() {
            const modalCategorias = document.getElementById('modalCategorias');
            if (modalCategorias) bootstrap.Modal.getOrCreateInstance(modalCategorias).show();
        }

        // Busca por nombre/código: oculta lo que no coincide, mantiene visible
        // la raíz cuando algún hijo coincide, expande las ramas con resultados
        // y muestra el aviso de "sin resultados" si no aparece nada.
        function catFiltrar() {
            const texto = ((document.getElementById('buscarCat') || {}).value || '').toLowerCase().trim();
            let visibles = 0;
            document.querySelectorAll('#arbolCategorias > .cat-nodo').forEach(function(raiz) {
                const coincideRaiz = !texto || (raiz.dataset.texto || '').indexOf(texto) !== -1;
                let coincideHijos = false;
                raiz.querySelectorAll('.cat-hijos > .cat-nodo').forEach(function(hijo) {
                    const coincide = !texto || (hijo.dataset.texto || '').indexOf(texto) !== -1;
                    hijo.style.display = coincide ? '' : 'none';
                    if (coincide) { coincideHijos = true; visibles++; }
                });
                const visible = coincideRaiz || coincideHijos;
                raiz.style.display = visible ? '' : 'none';
                if (texto && visible) raiz.classList.add('cat-abierta');
                if (coincideRaiz) visibles++;
            });
            const vacio = document.getElementById('catSinResultados');
            if (vacio) vacio.style.display = visibles === 0 ? '' : 'none';
        }

        // Llena el selector de categoría padre con las raíces activas.
        // En edición se mantiene elegible el padre actual aunque esté inactivo.
        function llenarPadresCat(datos) {
            const selectPadre = document.getElementById('cat_padre');
            const propia = datos ? Number(datos.id_categoria) : 0;
            const padreActual = datos ? Number(datos.parent_id || 0) : 0;
            let opciones = '<option value="0">\u2014 Categor\u00eda ra\u00edz (nivel 1)</option>';
            (window.JV_CATS || []).forEach(function(cat) {
                if (Number(cat.parent_id || 0) > 0) return;           // solo raíces
                if (Number(cat.id_categoria) === propia) return;      // la propia no es su padre
                const esActual = Number(cat.id_categoria) === padreActual;
                if (cat.status !== 'Activo' && !esActual) return;     // el padre debe estar activo
                opciones += '<option value="' + Number(cat.id_categoria) + '">' + escapeHtml(cat.nombre) + (esActual ? ' (ACTUAL)' : '') + '</option>';
            });
            selectPadre.innerHTML = opciones;
            selectPadre.value = String(padreActual > 0 ? padreActual : 0);
        }

        // Abre el formulario de categoría:
        //   catAbrirForm('registrar', 0, idRaiz)  → nueva (sub)categoría
        //   catAbrirForm('editar', idCategoria)   → edición rellenada desde JV_CATS
        function catAbrirForm(modo, idCategoria, idPadre) {
            const esEdicion = modo === 'editar';
            let datos = null;
            if (esEdicion) {
                datos = (window.JV_CATS || []).find(function(c) { return Number(c.id_categoria) === Number(idCategoria); }) || null;
                if (!datos) return;
            }
            document.getElementById('cat_accion').value = esEdicion ? 'editar' : 'registrar';
            document.getElementById('cat_id_edit').value = esEdicion ? datos.id_categoria : '';
            document.getElementById('cat_nombre').value = esEdicion ? datos.nombre : '';
            document.getElementById('cat_desc').value = esEdicion ? (datos.descripcion || '') : '';
            document.getElementById('cat_abc').value = esEdicion ? (datos.clasificacion_abc || '') : '';
            document.getElementById('cat_manejo').value = esEdicion ? (datos.tipo_manejo || 'normal') : 'normal';
            llenarPadresCat(datos);
            if (!esEdicion && Number(idPadre || 0) > 0) document.getElementById('cat_padre').value = String(Number(idPadre));
            document.getElementById('modalTitleCat').innerHTML = esEdicion
                ? '<i class="bi bi-folder-plus-fill me-2"></i>EDITAR CATEGOR\u00cdA'
                : '<i class="bi bi-folder-plus-fill me-2"></i>NUEVA CATEGOR\u00cdA';
            limpiarErrores();
            const btnGuardarCat = document.getElementById('btn-cat-guardar');
            btnGuardarCat.disabled = false;
            btnGuardarCat.innerHTML = '<i class="bi bi-check-lg me-2"></i>GUARDAR CATEGOR\u00cdA';
            const modalCategorias = document.getElementById('modalCategorias');
            const modalFormulario = document.getElementById('modalCat');
            const formularioCategoria = bootstrap.Modal.getOrCreateInstance(modalFormulario);
            if (modalCategorias && modalCategorias.classList.contains('show')) {
                catVolverAlGestor = true;
                modalCategorias.addEventListener('hidden.bs.modal', function mostrarFormulario() {
                    modalCategorias.removeEventListener('hidden.bs.modal', mostrarFormulario);
                    formularioCategoria.show();
                    document.getElementById('cat_nombre').focus();
                });
                bootstrap.Modal.getOrCreateInstance(modalCategorias).hide();
            } else {
                formularioCategoria.show();
            }
        }

        // Detecta si el nombre ya existe entre hermanas del nivel elegido
        // (mismo padre). Devuelve el mensaje de error o '' si está libre.
        function catErrorNombreDuplicado() {
            const nombre = document.getElementById('cat_nombre').value.trim().toLowerCase();
            if (!nombre) return '';
            const nivel = Number(document.getElementById('cat_padre').value || 0);
            const propia = Number(document.getElementById('cat_id_edit').value || 0);
            const duplicada = (window.JV_CATS || []).find(function(c) {
                return Number(c.id_categoria) !== propia
                    && Number(c.parent_id || 0) === nivel
                    && String(c.nombre || '').trim().toLowerCase() === nombre;
            });
            return duplicada ? 'YA EXISTE UNA CATEGOR\u00cdA CON ESE NOMBRE EN ESTE NIVEL.' : '';
        }

        // Confirma ocultar/mostrar una categoría SIN productos (el estado de las
        // categorías con productos lo maneja el stock de forma automática).
        function catToggleStatus(idCategoria, nombre, statusActual) {
            const activa = statusActual === 'Activo';
            Swal.fire({
                title: activa ? '\u00bfOCULTAR CATEGOR\u00cdA?' : '\u00bfMOSTRAR DE NUEVO?',
                html: activa
                    ? '<strong>' + escapeHtml(nombre) + '</strong> no tiene productos: quedar\u00e1 oculta del \u00e1rbol.'
                    : '<strong>' + escapeHtml(nombre) + '</strong> volver\u00e1 a verse en el \u00e1rbol.',
                icon: activa ? 'warning' : 'info',
                showCancelButton: true,
                confirmButtonColor: activa ? '#DC2626' : '#16A34A',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: activa ? 'S\u00cd, OCULTAR' : 'S\u00cd, MOSTRAR',
                cancelButtonText: 'CANCELAR',
                background: '#fff',
                color: '#212529'
            }).then(function(result) {
                if (result.isConfirmed) {
                    jvPost({ toggle_categoria: idCategoria, csrf_token: window.JV_CONFIG.csrfToken });
                }
            });
        }

        // Inicialización: validaciones del formulario y flash con origen.
        document.addEventListener('DOMContentLoaded', function() {
            const formCategorias = document.getElementById('formCat');
            const modalCategorias = document.getElementById('modalCategorias');
            const modalFormulario = document.getElementById('modalCat');

            if (modalCategorias) {
                modalCategorias.addEventListener('shown.bs.modal', function() {
                    const buscarCategoria = document.getElementById('buscarCat');
                    if (buscarCategoria) buscarCategoria.focus();
                });
            }

            if (modalFormulario) {
                modalFormulario.addEventListener('hidden.bs.modal', function() {
                    if (!catVolverAlGestor) return;
                    catVolverAlGestor = false;
                    catAbrirGestor();
                });
            }

            if (formCategorias) {
                // Validación antes de enviar (anti-doble-click incluido)
                formCategorias.addEventListener('submit', function(e) {
                    limpiarErrores();
                    const nombreCategoria = document.getElementById('cat_nombre');
                    let primerError = null;
                    if (!nombreCategoria.value.trim()) {
                        marcarError(nombreCategoria, 'NOMBRE REQUERIDO');
                        primerError = nombreCategoria;
                    } else {
                        const duplicado = catErrorNombreDuplicado();
                        if (duplicado) { marcarError(nombreCategoria, duplicado); primerError = nombreCategoria; }
                    }
                    if (primerError) {
                        e.preventDefault();
                        primerError.focus();
                        return;
                    }
                    const btnGuardarCat = document.getElementById('btn-cat-guardar');
                    btnGuardarCat.disabled = true;
                    btnGuardarCat.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
                });

                // Validación en vivo del nombre: marca el duplicado mientras se
                // escribe y limpia el error al corregirlo.
                const nombreCat = document.getElementById('cat_nombre');
                nombreCat.addEventListener('input', function() {
                    const duplicado = catErrorNombreDuplicado();
                    if (duplicado) {
                        marcarError(nombreCat, duplicado);
                    } else {
                        nombreCat.classList.remove('input-error');
                        const aviso = document.getElementById('cat_nombre_err');
                        if (aviso) aviso.remove();
                    }
                });

                // Limpia marcas de error al escribir o cambiar cualquier otro campo
                formCategorias.querySelectorAll('input, select, textarea').forEach(function(el) {
                    if (el === nombreCat) return;
                    el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id + '_err'); if (e) e.remove(); });
                    el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id + '_err'); if (e) e.remove(); });
                });
            }

            // Si el servidor rechazó el guardado, reabre el formulario marcando
            // el campo exacto que lo causó (origen fijo del controlador).
            const flash = document.getElementById('flashMsg');
            if (!flash || !flash.classList.contains('alert-jv-danger')) return;
            const textoError = flash.textContent.trim();
            const mensaje = (flash.dataset.texto || '').toUpperCase();
            const origen = flash.dataset.origen || '';
            if (origen === 'cat_crear') {
                catAbrirForm('registrar', 0, 0);
                marcarError(document.getElementById('cat_nombre'), textoError);
                document.getElementById('cat_nombre').focus();
            } else if (origen.indexOf('cat_editar:') === 0) {
                catAbrirForm('editar', Number(origen.split(':')[1]) || 0);
                marcarError(
                    mensaje.indexOf('PADRE') !== -1 ? document.getElementById('cat_padre') : document.getElementById('cat_nombre'),
                    textoError
                );
            } else if (origen.indexOf('prod_editar:') === 0) {
                editarProducto(Number(origen.split(':')[1]) || 0);
            }
        });

    
