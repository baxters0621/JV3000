

        // Activa el filtro de vencimiento correspondiente según la alerta recibida.
        function filtrarPorAlerta(clase) {
            var alertFilterButton = document.querySelector('.btn-filtro-venc[data-venc="' + clase + '"]');
            if (alertFilterButton) filtrarVenc(alertFilterButton);
        }

        // Normaliza texto para buscar sin distinguir mayúsculas, minúsculas ni acentos.
        function normalizarBusqueda(valor) {
            var texto = String(valor || '');
            if (texto.normalize) {
                texto = texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            }
            return texto.toLowerCase().replace(/\s+/g, ' ').trim();
        }

        // Crea o retira el aviso cuando los filtros ocultan todas las filas.
        function actualizarAvisoSinResultados(tbody, visibles, totalFilas) {
            var aviso = document.getElementById('productosSinResultados');
            if (visibles > 0 || totalFilas === 0) {
                if (aviso) aviso.remove();
                return;
            }
            if (aviso) return;

            var tabla = tbody.closest('table');
            var columnas = tabla ? tabla.querySelectorAll('thead th').length : 9;
            aviso = document.createElement('tr');
            aviso.id = 'productosSinResultados';
            aviso.setAttribute('data-empty-row', '1');

            var celda = document.createElement('td');
            celda.colSpan = columnas;
            celda.className = 'text-center py-5';
            celda.style.color = 'var(--jv-text-muted)';
            celda.innerHTML = '<i class="bi bi-search d-block mb-2" style="font-size:2rem;" aria-hidden="true"></i>'
                + '<span style="font-weight:700;">Sin resultados</span>'
                + '<p class="mt-2 mb-0" style="font-size:.95rem;">No hay productos que coincidan con la búsqueda.</p>';

            aviso.appendChild(celda);
            tbody.appendChild(aviso);
        }

        // Aplica los filtros activos (estado, vencimiento, stock bajo y búsqueda) sobre las filas.
        function aplicarFiltros() {
            var searchInput = document.getElementById('buscar');
            var searchValue = normalizarBusqueda(searchInput ? searchInput.value : '');
            var activeExpirationButton = document.querySelector('.btn-filtro-venc.active');
            var expirationFilter = activeExpirationButton ? activeExpirationButton.getAttribute('data-venc') : 'todas';
            var activeStatusButton = document.querySelector('.btn-filter-prod.active');
            var statusFilter = activeStatusButton ? activeStatusButton.getAttribute('data-status') : 'todas';
            var tbody = document.getElementById('tablaProductos');
            if (!tbody) return;

            var productRows = Array.from(tbody.querySelectorAll('tr[data-id]'));
            var visibles = 0;

            productRows.forEach(function(productRow) {
                var productStatus = productRow.getAttribute('data-status-efectivo')
                    || productRow.getAttribute('data-status')
                    || '';
                if (statusFilter !== 'todas' && productStatus !== statusFilter) {
                    productRow.style.display = 'none';
                    return;
                }

                var expirationClass = productRow.getAttribute('data-venc-cls') || '';
                if (expirationFilter !== 'todas' && expirationClass !== expirationFilter) {
                    productRow.style.display = 'none';
                    return;
                }

                if (filtroBajos) {
                    var currentStock = parseInt(productRow.getAttribute('data-stock') || '', 10);
                    var minimumStock = parseInt(productRow.getAttribute('data-minimo') || '', 10);
                    if (isNaN(currentStock) || currentStock > minimumStock) {
                        productRow.style.display = 'none';
                        return;
                    }
                }

                var rowText = normalizarBusqueda(productRow.textContent);
                var coincide = searchValue === '' || rowText.indexOf(searchValue) !== -1;
                productRow.style.display = coincide ? '' : 'none';
                if (coincide) visibles++;
            });

            actualizarAvisoSinResultados(tbody, visibles, productRows.length);
        }
        // Marca el botón de filtro por vencimiento activo y vuelve a aplicar los filtros.
        function filtrarVenc(expirationButton) {
            document.querySelectorAll('.btn-filtro-venc').forEach(function(b) {
                b.classList.remove('active');
                b.style.background = 'transparent';
                b.style.color = b.dataset.venc === 'vencido' ? '#DC2626' : b.dataset.venc === 'proximo' ? '#D97706' : b.dataset.venc === 'pronto' ? '#D97706' : b.dataset.venc === 'vigente' ? '#16A34A' : '#1E3A8A';
            });
            expirationButton.classList.add('active');
            expirationButton.style.background = 'rgba(30,58,138,0.10)';
            expirationButton.style.color = '#1E3A8A';
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

        // Valida la edición y guarda por AJAX para poder controlar la espera y el resultado.
        function validarEditarProducto(btn) {
            var precioVenta = document.getElementById('edit_pvp');
            var valorVenta = leerPrecioEdicion(precioVenta.value);
            var canonicoVenta = Number.isFinite(valorVenta) ? valorVenta.toFixed(2) : '';
            limpiarErrores();
            let primerError = null;
            let primerMensaje = '';
            const minimo = document.getElementById('edit_minimo');
            const maximo = document.getElementById('edit_maximo');
            const pvp = document.getElementById('edit_pvp');
            const vencimiento = document.getElementById('edit_vencimiento');
            const proveedor = document.getElementById('edit_proveedor');
            const estado = document.getElementById('edit_status');

            const esEntero = function(valor) {
                return /^\d+$/.test(String(valor).trim());
            };
            const fechaValida = function(valor) {
                const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(valor).trim());
                if (!partes) return false;
                const anio = Number(partes[1]);
                const mes = Number(partes[2]);
                const dia = Number(partes[3]);
                if (anio < 1900 || mes < 1 || mes > 12 || dia < 1) return false;
                const fecha = new Date(anio, mes - 1, dia);
                return fecha.getFullYear() === anio
                    && fecha.getMonth() === mes - 1
                    && fecha.getDate() === dia;
            };
            const marcar = function(campo, mensaje) {
                marcarError(campo, mensaje);
                if (!primerError) {
                    primerError = campo;
                    primerMensaje = mensaje;
                }
            };

            const minimoTexto = minimo.value.trim();
            const maximoTexto = maximo.value.trim();

            if (!esEntero(minimoTexto) || Number(minimoTexto) < 1 || Number(minimoTexto) > 99999) {
                marcar(minimo, 'STOCK MÍNIMO: ENTERO ENTRE 1 Y 99.999');
            }
            if (!esEntero(maximoTexto) || Number(maximoTexto) < 0 || Number(maximoTexto) > 999999) {
                marcar(maximo, 'CAPACIDAD MÁXIMA: 0 (HEREDAR CATEGORÍA) O ENTERO HASTA 999.999');
            }
            if (esEntero(minimoTexto) && esEntero(maximoTexto)
                && Number(maximoTexto) > 0 && Number(maximoTexto) < Number(minimoTexto)) {
                marcar(maximo, 'DEBE SER MAYOR O IGUAL AL STOCK MÍNIMO');
            }
            if (!precioTieneFormatoValido(canonicoVenta)) {
                marcar(pvp, valorVenta > 99999.99 ? 'MÁXIMO 99.999,99' : 'FORMATO: 0,00 (MÁXIMO 99.999,99)');
            }
            const fechaTexto = vencimiento.value.trim();
            const estaActivo = estado && estado.value === 'Activo';
            if (!fechaTexto || fechaTexto === '0000-00-00') {
                if (estaActivo) {
                    marcar(vencimiento, 'UN PRODUCTO ACTIVO DEBE TENER FECHA DE VENCIMIENTO');
                }
            } else if (!fechaValida(fechaTexto)) {
                marcar(vencimiento, 'FECHA DE VENCIMIENTO VÁLIDA REQUERIDA');
            }
            if (!proveedor.value || Number(proveedor.value) <= 0) {
                marcar(proveedor, 'EL PROVEEDOR ES OBLIGATORIO');
            }
            if (primerError) {
                Swal.fire({
                    title: 'REVISA EL FORMULARIO',
                    text: primerMensaje,
                    icon: 'warning',
                    confirmButtonColor: '#DC2626'
                });
                primerError.focus();
                return false;
            }
            document.getElementById('edit_pvp_valor').value = canonicoVenta;
            const form = btn.form;
            if (!form) {
                Swal.fire({
                    title: 'FORMULARIO NO DISPONIBLE',
                    text: 'No se pudo asociar el botón con el formulario. Recargue la página e inténtelo de nuevo.',
                    icon: 'error',
                    confirmButtonColor: '#DC2626'
                });
                return false;
            }
            const textoOriginal = btn.innerHTML;
            const restaurarBoton = function() {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            };
            btn.disabled = true;
            btn.innerHTML = '<span class=\'spinner-border spinner-border-sm me-1\'></span>GUARDANDO...';
            jvFetchConTope(form.action || window.location.href, {
                ms: 12000,
                init: {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form)
                }
            })
                .then(function(response) {
                    return response.text().then(function(texto) {
                        let data;
                        try {
                            data = JSON.parse(texto);
                        } catch (error) {
                            throw new Error('El servidor devolvió una respuesta inválida. Revise el producto antes de reintentar.');
                        }
                        if (response.status === 422 && data.success === false) {
                            restaurarBoton();
                            window.location.reload();
                            return null;
                        }
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || data.error || 'NO SE PUDO ACTUALIZAR EL PRODUCTO.');
                        }
                        return data;
                    });
                })
                .then(function(data) {
                    if (!data) return;
                    restaurarBoton();
                    window.location.reload();
                })
                .catch(function(error) {
                    restaurarBoton();
                    Swal.fire({
                        title: error.jvTope ? 'LA SOLICITUD TARDÓ DEMASIADO' : 'NO SE PUDO ACTUALIZAR EL PRODUCTO',
                        text: error.jvTope
                            ? 'Verifique el producto en el inventario antes de volver a guardar; el servidor pudo completar la operación.'
                            : (error.message || 'Verifique la conexión e inténtelo de nuevo.'),
                        icon: 'error',
                        confirmButtonColor: '#DC2626'
                    });
                });
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
            // Proveedor actual. Si está inactivo no figura entre las opciones
            // activas: se agrega como temporal para no cambiarlo en silencio.
            var provId = parseInt(row.getAttribute('data-prov-id'), 10) || 0;
            var provNombre = row.getAttribute('data-prov') || '';
            var selectProveedor = document.getElementById('edit_proveedor');
            var opcionProvTemporal = document.getElementById('edit_proveedor_temp');
            if (opcionProvTemporal) opcionProvTemporal.remove();
            if (provId > 0 && !selectProveedor.querySelector('option[value="' + provId + '"]')) {
                var opcionProvActual = document.createElement('option');
                opcionProvActual.id = 'edit_proveedor_temp';
                opcionProvActual.value = String(provId);
                opcionProvActual.textContent = provNombre + ' (inactivo)';
                selectProveedor.appendChild(opcionProvActual);
            }
            selectProveedor.value = provId > 0 ? String(provId) : '';
            // Categoría actual del producto. Si está inactiva no figura entre
            // las opciones del select (que solo lista activas): se agrega
            // marcada como temporal para que no salte de rama en silencio.
            var catId = parseInt(row.getAttribute('data-cat'), 10) || 0;
            var catNombre = row.getAttribute('data-cat-nombre') || '';
            var selectCategoria = document.getElementById('edit_categoria');
            var opcionTemporal = document.getElementById('edit_categoria_temp');
            if (opcionTemporal) opcionTemporal.remove();
            if (catId > 0 && !selectCategoria.querySelector('option[value="' + catId + '"]')) {
                var opcionActual = document.createElement('option');
                opcionActual.id = 'edit_categoria_temp';
                opcionActual.value = String(catId);
                opcionActual.textContent = catNombre + ' (inactiva)';
                selectCategoria.appendChild(opcionActual);
            }
            selectCategoria.value = String(catId);
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
                b.style.color = b.dataset.venc === 'vencido' ? '#DC2626' : b.dataset.venc === 'proximo' ? '#D97706' : b.dataset.venc === 'pronto' ? '#D97706' : b.dataset.venc === 'vigente' ? '#16A34A' : '#1E3A8A';
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
                // GESTOR DE CATEGORÍAS + FORMULARIO
                // Consulta para todos los roles; escritura según rol.
                // ==========================================

        var catVolverAlGestor = false;
        var catEnviando = false;

        // Las categorías conservan borrador solo cuando hay nombre o descripción;
        // al cancelar o cerrar se purga para no volver a preguntar.
        function catLimpiarBorrador() {
            if (typeof jvBorradorLimpiar === 'function') {
                jvBorradorLimpiar('productos_categoria');
                return;
            }
            try { window.localStorage.removeItem('jv_borrador_v1_productos_categoria'); } catch (error) { /* sin almacenamiento */ }
        }

        // Abre el gestor sin quitar espacio al listado de productos.
        function catAbrirGestor() {
            const modalCategorias = document.getElementById('modalCategorias');
            if (modalCategorias) bootstrap.Modal.getOrCreateInstance(modalCategorias).show();
        }

        // Busca por nombre/código: oculta lo que no coincide, mantiene visible
        // la raíz cuando algún hijo coincide, despliega las ramas con resultados
        // y muestra el aviso de "sin resultados" si no aparece nada.
        function catFiltrar() {
            const texto = ((document.getElementById('buscarCat') || {}).value || '').toLowerCase().trim();
            let visibles = 0;
            document.querySelectorAll('#arbolCategorias > .cat-nodo').forEach(function(raiz) {
                const coincideRaiz = !texto || (raiz.dataset.texto || '').indexOf(texto) !== -1;
                let coincideHijos = false;
                raiz.querySelectorAll('.cat-hijos > .cat-nodo').forEach(function(hijo) {
                    const coincide = !texto || coincideRaiz || (hijo.dataset.texto || '').indexOf(texto) !== -1;
                    hijo.style.display = coincide ? '' : 'none';
                    if (coincide) { coincideHijos = true; visibles++; }
                });
                const visible = coincideRaiz || coincideHijos;
                raiz.style.display = visible ? '' : 'none';
                if (coincideRaiz) visibles++;

                const colapso = raiz.querySelector('.cat-colapso');
                if (colapso && bootstrap.Collapse) {
                    const instancia = bootstrap.Collapse.getOrCreateInstance(colapso, { toggle: false });
                    if (visible && texto) {
                        if (!colapso.classList.contains('show')) instancia.show();
                    } else if (!visible && colapso.classList.contains('show')) {
                        instancia.hide();
                    }
                }
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
            catEnviando = false;
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

        // Confirma ocultar/mostrar una categoría SIN productos. Si tiene
        // productos, muestra el aviso y no envía la petición: su estado lo
        // controla el stock.
        function catToggleStatus(idCategoria, nombre, statusActual, totalProductos) {
            const activa = statusActual === 'Activo';
            const productos = Number(totalProductos) || 0;

            if (activa && productos > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ACCI\u00d3N NO PERMITIDA',
                    text: 'No puedes inactivar esta categor\u00eda porque tiene productos. Reas\u00edgnalos primero.',
                    confirmButtonColor: '#DC2626',
                    confirmButtonText: 'ENTENDIDO',
                    background: '#fff',
                    color: '#212529'
                });
                return;
            }

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
                modalFormulario.addEventListener('hide.bs.modal', function() {
                    if (!catEnviando) catLimpiarBorrador();
                });
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
                    catEnviando = true;
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

    
