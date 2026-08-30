

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
        // GESTIÓN INTEGRADA DE CATEGORÍAS (pop-ups)
        // Registrar / modificar / desactivar categorías
        // sin salir de Inventario.
        // ==========================================

        let catEstadoFiltro = 'todos';
        let catDesdeLista = false;

        // Abre el pop-up del gestor reiniciando búsqueda y filtro.
        function abrirGestorCat() {
            const buscadorCategorias = document.getElementById('buscarCat');
            if (buscadorCategorias) buscadorCategorias.value = '';
            catEstadoFiltro = 'todos';
            document.querySelectorAll('.btn-filter-cat').forEach(function(b) { b.classList.remove('active'); });
            const btnTodasCategorias = document.querySelector('.btn-filter-cat[data-status="todos"]');
            if (btnTodasCategorias) btnTodasCategorias.classList.add('active');
            catFiltrar();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCategorias')).show();
        }

        // Marca el botón de filtro por estado activo y vuelve a filtrar.
        function catFiltrarEstado(btn) {
            document.querySelectorAll('.btn-filter-cat').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            catEstadoFiltro = btn.dataset.status;
            catFiltrar();
        }

        // Actualiza el contador de resultados y muestra la fila de "sin
        // resultados" cuando la búsqueda/filtro no encuentra nada.
        function catContar() {
            const filasCategorias = document.querySelectorAll('#tablaCategoriasPop tr.cat-fila');
            let visibles = 0;
            filasCategorias.forEach(function(fila) {
                if (fila.style.display !== 'none') visibles++;
            });
            const totalCategorias = filasCategorias.length;
            const botonEstado = document.querySelector('.btn-filter-cat.active');
            const estadoActivo = botonEstado ? botonEstado.textContent : 'Todas';
            const contador = document.getElementById('catContador');
            if (contador) {
                contador.innerHTML = totalCategorias === 0
                    ? 'No hay categor&iacute;as registradas.'
                    : 'Mostrando <strong>' + visibles + '</strong> de <strong>' + totalCategorias + '</strong> categor&iacute;as &middot; Estado: ' + estadoActivo;
            }
            const filaSinResultados = document.getElementById('catSinResultados');
            if (filaSinResultados) {
                filaSinResultados.style.display = (totalCategorias > 0 && visibles === 0) ? '' : 'none';
            }
        }

        // Filtra las filas por texto (nombre/código) y estado seleccionado.
        function catFiltrar() {
            const textoBusqueda = ((document.getElementById('buscarCat') || {}).value || '').toLowerCase().trim();
            document.querySelectorAll('#tablaCategoriasPop .cat-fila').forEach(function(fila) {
                const visible = (catEstadoFiltro === 'todos' || fila.dataset.status === catEstadoFiltro)
                    && (!textoBusqueda || (fila.dataset.texto || '').indexOf(textoBusqueda) !== -1);
                fila.style.display = visible ? '' : 'none';
            });
            catContar();
        }

        // Prepara y abre el formulario de categoría (null = modo registro).
        function catAbrirForm(categoriaData) {
            const esEdicion = !!categoriaData;
            document.getElementById('cat_accion').value = esEdicion ? 'editar' : 'registrar';
            document.getElementById('cat_id_edit').value = esEdicion ? categoriaData.id_categoria : '';
            document.getElementById('cat_status').value = esEdicion ? (categoriaData.status || 'Activo') : 'Activo';
            document.getElementById('modalTitleCat').innerHTML = esEdicion ? '<i class="bi bi-tag-fill me-2"></i>EDITAR CATEGOR\u00cdA' : '<i class="bi bi-tag-fill me-2"></i>NUEVA CATEGOR\u00cdA';
            document.getElementById('cat_nombre').value = esEdicion ? categoriaData.nombre : '';
            document.getElementById('cat_desc').value = esEdicion ? (categoriaData.descripcion || '') : '';
            document.getElementById('cat_abc').value = esEdicion ? (categoriaData.clasificacion_abc || '') : '';
            document.getElementById('cat_manejo').value = esEdicion ? (categoriaData.tipo_manejo || 'normal') : 'normal';

            const btnGuardarCat = document.getElementById('btn-cat-guardar');
            btnGuardarCat.disabled = false;
            btnGuardarCat.innerHTML = '<i class="bi bi-check-lg me-2"></i>GUARDAR CATEGOR\u00cdA';

            catDesdeLista = true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCategorias')).hide();
            setTimeout(function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCat')).show();
                document.getElementById('cat_nombre').focus();
            }, 300);
        }

        // Modo registro (botón NUEVA del listado).
        function nuevaCat() {
            catAbrirForm(null);
        }

        // Modo edición: recibe la categoría como objeto (desde onclick con JSON).
        function editarCat(categoriaData) {
            catAbrirForm(categoriaData);
        }

        // Confirma activar/desactivar una categoría y envía la acción al servidor.
        function catToggleStatus(idCategoria, nombre, statusActual) {
            const activa = statusActual === 'Activo';
            Swal.fire({
                title: activa ? '\u00bfDESACTIVAR CATEGOR\u00cdA?' : '\u00bfREACTIVAR CATEGOR\u00cdA?',
                text: activa ? 'Se desactivar\u00e1 \'' + nombre + '\'' : 'Se reactivar\u00e1 \'' + nombre + '\'',
                icon: activa ? 'warning' : 'info',
                showCancelButton: true,
                confirmButtonColor: activa ? '#DC2626' : '#16A34A',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: activa ? 'S\u00cd, DESACTIVAR' : 'S\u00cd, ACTIVAR',
                cancelButtonText: 'CANCELAR',
                background: '#fff',
                color: '#212529'
            }).then(function(result) {
                if (result.isConfirmed) {
                    jvPost({ toggle_categoria: idCategoria, csrf_token: window.JV_CONFIG.csrfToken });
                }
            });
        }

        // Inicialización exclusiva del gestor de categorías.
        document.addEventListener('DOMContentLoaded', function() {
            const formCategorias = document.getElementById('formCat');

            if (formCategorias) {
                // Validación antes de enviar (anti-doble-click incluido)
                formCategorias.addEventListener('submit', function(e) {
                    limpiarErrores();
                    const nombreCategoria = document.getElementById('cat_nombre');
                    if (!nombreCategoria.value.trim()) {
                        marcarError(nombreCategoria, 'NOMBRE REQUERIDO');
                        e.preventDefault();
                        nombreCategoria.focus();
                        return;
                    }
                    const btnGuardarCat = document.getElementById('btn-cat-guardar');
                    btnGuardarCat.disabled = true;
                    btnGuardarCat.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
                });

                // Limpia marcas de error al escribir o cambiar cualquier campo
                formCategorias.querySelectorAll('input, select, textarea').forEach(function(el) {
                    el.addEventListener('input', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id + '_err'); if (e) e.remove(); });
                    el.addEventListener('change', function() { this.classList.remove('input-error'); var e = document.getElementById(this.id + '_err'); if (e) e.remove(); });
                });

                // Si el usuario cierra el formulario sin guardar, vuelve al listado
                document.getElementById('modalCat').addEventListener('hidden.bs.modal', function() {
                    if (catDesdeLista) {
                        catDesdeLista = false;
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCategorias')).show();
                    }
                });
            }

            // Si el servidor rechazó el guardado (nombre duplicado), reabre el
            // formulario marcando el campo exacto que lo causó.
            const flashCat = document.getElementById('flashMsg');
            if (flashCat && flashCat.classList.contains('alert-jv-danger')) {
                const textoFlashCat = (flashCat.dataset.texto || '').toUpperCase();
                if (textoFlashCat.indexOf('NOMBRE') !== -1 || textoFlashCat.indexOf('CATEGOR\u00cdA') !== -1) {
                    const campoNombre = document.getElementById('cat_nombre');
                    if (campoNombre) {
                        marcarError(campoNombre, textoFlashCat);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCat')).show();
                    }
                }
            }
        });

    
