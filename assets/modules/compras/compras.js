
        // ==========================================
        // COMPRAS — Registro de factura del proveedor y productos
        // (No mueve stock: la recepción se hace desde Inventario)
        // ==========================================

        let productos = [];
        let productoSeleccionado = null;
        let proveedorCarritoId = '';

        const taxPercentage = (window.JV_CONFIG && typeof window.JV_CONFIG.taxPercentage === 'number') ? window.JV_CONFIG.taxPercentage : 16;
        const proveedorSelect = document.getElementById('selProveedor');
        const productoSelect = document.getElementById('selProductoProveedor');
        const catalogoEstado = document.getElementById('catalogoProveedorEstado');

        // Formatea un número como moneda en dólares: $ con separador de miles y 2 decimales.
        function formatCurrency(amount) {
            return '$' + Number(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // ---- Formateo de precio en el input ----
        // Normaliza lo escrito en el input de precio: solo dígitos y punto, separador de miles y tope de 99,999,999.99.
        function formatearPrecioCompra(el) {
            var raw = el.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
            var parts = raw.split('.');
            var entero = parts[0].replace(/^0+/, '') || '0';
            var decimales = parts[1] ? parts[1].slice(0, 2) : '';
            var formateado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (decimales) formateado += '.' + decimales;
            var num = parseFloat(entero + '.' + (decimales || '0'));
            if (num > 99999999.99) {
                entero = '99999999';
                decimales = '99';
                formateado = '99,999,999.99';
            }
            el.value = formateado;
        }

        // ==========================================
        // CATÁLOGO DEL PROVEEDOR
        // ==========================================
        // Suma las unidades de un producto que ya están reservadas en el carrito.
        function cantidadEnCarrito(idProducto) {
            return productos.reduce(function(total, producto) {
                const cantidad = Number(producto.cantidad);
                return Number(producto.id) === Number(idProducto) && Number.isSafeInteger(cantidad) && cantidad > 0
                    ? total + cantidad
                    : total;
            }, 0);
        }

        // Calcula las unidades que aún pueden agregarse sin exceder el saldo del proveedor.
        function disponibleEnCarrito(producto) {
            const disponible = Number(producto.stock_disponible);
            return Number.isFinite(disponible) ? Math.max(0, disponible - cantidadEnCarrito(producto.id)) : 0;
        }

        // Llena el selector solo con productos activos asociados al proveedor elegido.
        function actualizarCatalogoProveedor() {
            if (!productoSelect) return;
            const idProveedor = proveedorSelect ? proveedorSelect.value : '';
            const catalogo = (window.JV_CATALOGO || {})[idProveedor] || [];
            productoSelect.innerHTML = '';
            const cantidadEl = document.getElementById('inputCant');

            const opcionInicial = document.createElement('option');
            opcionInicial.value = '';
            opcionInicial.textContent = idProveedor ? 'Selecciona un producto...' : 'Selecciona primero un proveedor';
            productoSelect.appendChild(opcionInicial);

            let productosConExistencia = 0;
            catalogo.forEach(function(producto) {
                const disponible = disponibleEnCarrito(producto);
                if (disponible > 0) productosConExistencia++;
                const opcion = document.createElement('option');
                opcion.value = String(producto.id);
                opcion.textContent = producto.nombre
                    + (producto.sku ? ' (' + producto.sku + ')' : '')
                    + ' · Disponible: ' + disponible;
                opcion.disabled = disponible <= 0;
                productoSelect.appendChild(opcion);
            });

            productoSelect.disabled = !idProveedor || catalogo.length === 0;
            if (cantidadEl) {
                const seleccionado = catalogo.find(function(producto) {
                    return productoSeleccionado && Number(producto.id) === Number(productoSeleccionado.id);
                });
                cantidadEl.max = seleccionado
                    ? String(Math.min(999999, disponibleEnCarrito(seleccionado)))
                    : '999999';
            }
            if (catalogoEstado) {
                catalogoEstado.textContent = !idProveedor
                    ? 'Selecciona un proveedor para ver sus productos.'
                    : productosConExistencia
                        ? productosConExistencia + ' producto(s) con existencias para agregar.'
                        : catalogo.length
                            ? 'Las existencias registradas ya están en el carrito o son cero.'
                        : 'Este proveedor no tiene productos en su catálogo. Agrégalos desde Gestión de proveedores.';
            }
        }

        // Al elegir un producto del catálogo carga su costo asociado al proveedor.
        function seleccionarProductoCatalogo() {
            productoSeleccionado = null;
            const precioEl = document.getElementById('inputPrecio');
            if (precioEl) precioEl.value = '';

            const idProveedor = proveedorSelect ? proveedorSelect.value : '';
            const idProducto = productoSelect ? Number(productoSelect.value) : 0;
            const catalogo = (window.JV_CATALOGO || {})[idProveedor] || [];
            const producto = catalogo.find(function(entrada) {
                return Number(entrada.id) === idProducto;
            });
            if (!producto) return;
            const disponible = disponibleEnCarrito(producto);
            if (disponible <= 0) return;

            productoSeleccionado = {
                id: Number(producto.id),
                nombre: producto.nombre
            };
            if (precioEl) precioEl.value = Number(producto.costo).toFixed(2);
            const cantidadEl = document.getElementById('inputCant');
            if (cantidadEl) {
                cantidadEl.max = String(Math.min(999999, disponible));
                cantidadEl.focus();
            }
        }

        // ==========================================
        // LÍNEA DE PRODUCTO
        // ==========================================
        // Valida cantidad y precio y agrega el producto elegido al array 'productos' como línea de la factura.
        function agregarProducto() {
            if (!proveedorSelect || !proveedorSelect.value) {
                Swal.fire({
                    title: 'Seleccione un proveedor',
                    text: 'Primero elija el proveedor para cargar su catálogo de productos.',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                if (proveedorSelect) proveedorSelect.focus();
                return;
            }
            if (!productoSeleccionado || !productoSeleccionado.id) {
                Swal.fire({
                    title: 'Seleccione un producto',
                    text: 'Elija un producto del catálogo de este proveedor y complete la cantidad, el precio y el vencimiento.',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                if (productoSelect) productoSelect.focus();
                return;
            }
            const quantityText = document.getElementById('inputCant').value.trim();
            const requestedQuantity = Number(quantityText);
            const precio = parseFloat(document.getElementById('inputPrecio').value.replace(/,/g, '')) || 0;
            const expirationDate = document.getElementById('inputVencimiento').value || '';
            const catalogo = (window.JV_CATALOGO || {})[proveedorSelect.value] || [];
            const productoCatalogo = catalogo.find(function(entrada) {
                return Number(entrada.id) === Number(productoSeleccionado.id);
            });
            const disponible = productoCatalogo ? disponibleEnCarrito(productoCatalogo) : 0;

            if (!/^\d+(?:\.0+)?$/.test(quantityText) || !Number.isInteger(requestedQuantity) || requestedQuantity < 1 || requestedQuantity > 999999) {
                Swal.fire({
                    title: 'Cantidad inválida',
                    text: 'Ingrese una cantidad entera entre 1 y 999,999',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            if (!productoCatalogo || requestedQuantity > disponible) {
                Swal.fire({
                    title: 'Existencias insuficientes',
                    text: 'El proveedor solo tiene ' + disponible + ' unidad(es) disponibles para este producto.',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            if (precio < 0 || precio > 99999999.99) {
                Swal.fire({
                    title: 'Precio inválido',
                    text: 'Ingrese un precio entre 0 y 99,999,999.99',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            // REGLA DE NEGOCIO: todo lote exige fecha de vencimiento (control FEFO)
            if (!expirationDate) {
                Swal.fire({
                    title: 'Fecha de vencimiento requerida',
                    text: 'Indique la fecha de vencimiento del lote para agregar el producto',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                document.getElementById('inputVencimiento').focus();
                return;
            }

            productos.push({
                id: productoSeleccionado.id,
                nombre: productoSeleccionado.nombre,
                cantidad: requestedQuantity,
                precio: precio,
                fecha_vencimiento: expirationDate,
                total: requestedQuantity * precio
            });
            actualizarTabla();

            productoSeleccionado = null;
            if (productoSelect) productoSelect.value = '';
            document.getElementById('inputCant').value = 1;
            document.getElementById('inputCant').max = '999999';
            document.getElementById('inputPrecio').value = '';
            document.getElementById('inputVencimiento').value = '';
            if (productoSelect && !productoSelect.disabled) productoSelect.focus();
        }

        // Elimina del array la línea de producto en la posición dada y redibuja la tabla.
        function quitarProducto(productIndex) {
            productos.splice(productIndex, 1);
            actualizarTabla();
        }

        // Redibuja la tabla del carrito, calcula subtotal, IVA y total, y actualiza el campo oculto JSON y el botón Guardar.
        function actualizarTabla() {
            const body = document.getElementById('productosBody');
            if (productos.length === 0) {
                body.innerHTML = '<tr id="filaVacia"><td colspan="7" style="padding:24px 12px;text-align:center;color:var(--jv-text-muted);font-size:.85rem;border-bottom:1px solid var(--jv-border);">Seleccione un proveedor y un producto disponible para comenzar</td></tr>';
            } else {
                body.innerHTML = '';
                productos.forEach((product, productIndex) => {
                    const tr = document.createElement('tr');
                    const formattedExpirationDate = product.fecha_vencimiento ? product.fecha_vencimiento.split('-').reverse().join('/') : '—';
                    tr.innerHTML = '<td style="padding:10px 12px;color:var(--jv-text-muted);text-align:center;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + (productIndex + 1) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + escapeHtml(product.nombre) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:center;border-bottom:1px solid var(--jv-border);">' + product.cantidad + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:right;color:var(--jv-text-muted);border-bottom:1px solid var(--jv-border);">' + formatCurrency(product.precio) + '</td>' +
                        '<td style="padding:10px 12px;font-size:.95rem;text-align:center;color:var(--jv-text-muted);border-bottom:1px solid var(--jv-border);">' + formattedExpirationDate + '</td>' +
                        '<td style="padding:10px 12px;font-size:1rem;text-align:right;color:var(--jv-navy);font-weight:700;border-bottom:1px solid var(--jv-border);">' + formatCurrency(product.total) + '</td>' +
                        '<td style="padding:10px 12px;border-bottom:1px solid var(--jv-border);"><button type="button" class="btn btn-sm border-0" style="padding:0;color:var(--jv-danger);font-size:1rem;line-height:1;" onclick="quitarProducto(' + productIndex + ')"><i class="bi bi-x-circle"></i></button></td>';
                    body.appendChild(tr);
                });
            }
            const subtotal = productos.reduce(function(runningSubtotal, product) {
                return runningSubtotal + product.total;
            }, 0);
            const iva = subtotal * taxPercentage / 100;
            const total = subtotal + iva;
            document.getElementById('totalItems').textContent = productos.length;
            document.getElementById('totalSubtotal').textContent = formatCurrency(subtotal);
            document.getElementById('totalIva').textContent = formatCurrency(iva);
            document.getElementById('totalCosto').textContent = formatCurrency(total);
            document.getElementById('btnGuardar').disabled = productos.length === 0;
            document.getElementById('productosData').value = JSON.stringify(productos);
            actualizarCatalogoProveedor();
        }

        // ==========================================
        // PROVEEDOR — RIF del proveedor elegido
        // ==========================================
        proveedorSelect.addEventListener('change', function() {
            if (proveedorCarritoId && proveedorCarritoId !== this.value && productos.length > 0) {
                this.value = proveedorCarritoId;
                Swal.fire({
                    title: 'Carrito con productos',
                    text: 'Quite los productos del carrito antes de cambiar de proveedor.',
                    icon: 'warning',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            proveedorCarritoId = this.value;
            const opt = this.options[this.selectedIndex];
            const rifEl = document.getElementById('displayRif');
            if (opt && opt.value) {
                const rif = opt.dataset.rif || '';
                rifEl.value = rif;
                const rifOk = /^[VEJGPC]-\d{8}-\d$/.test(rif);
                rifEl.style.color = rifOk ? 'var(--jv-text-muted)' : '#DC2626';
            } else {
                rifEl.value = '-';
                rifEl.style.color = 'var(--jv-text-muted)';
            }
            productoSeleccionado = null;
            if (productoSelect) productoSelect.value = '';
            document.getElementById('inputPrecio').value = '';
            actualizarCatalogoProveedor();
        });
        productoSelect.addEventListener('change', seleccionarProductoCatalogo);

        // ==========================================
        // VALIDACIÓN DEL FORMULARIO
        // ==========================================
        // Valida proveedor, factura, control fiscal y líneas del carrito;
        // si hay errores los marca, los muestra en un resumen y evita el envío.
        function validarFormulario(btn) {
            limpiarErrores();
            const errores = [];
            let primerError = null;

            const prov = document.getElementById('selProveedor');
            if (!prov.value) {
                errores.push('SELECCIONE UN PROVEEDOR');
                marcarError(prov);
                if (!primerError) primerError = prov;
            } else {
                const opt = prov.options[prov.selectedIndex];
                const rifProv = (opt.dataset.rif || '').toUpperCase().replace(/\s+/g, '');
                if (!/^[VEJGPC]-\d{8}-\d$/.test(rifProv)) {
                    errores.push('EL PROVEEDOR SELECCIONADO TIENE UN RIF INVÁLIDO');
                    marcarError(prov);
                    if (!primerError) primerError = prov;
                }
            }

            const fac = document.querySelector('input[name="nro_factura"]');
            const facVal = fac.value.trim();
            if (!facVal) {
                errores.push('EL NÚMERO DE FACTURA ES OBLIGATORIO');
                marcarError(fac);
                if (!primerError) primerError = fac;
            } else if (!/^\d{6,8}$/.test(facVal)) {
                errores.push('NRO. FACTURA: DEBE TENER ENTRE 6 Y 8 DÍGITOS');
                marcarError(fac);
                if (!primerError) primerError = fac;
            }

            const ctrl = document.querySelector('input[name="nro_control"]');
            const ctrlVal = ctrl.value.trim();
            if (!ctrlVal) {
                errores.push('EL NÚMERO DE CONTROL ES OBLIGATORIO');
                marcarError(ctrl);
                if (!primerError) primerError = ctrl;
            } else if (!/^\d{2}-\d{8}$/.test(ctrlVal)) {
                errores.push('NRO. CONTROL INVÁLIDO (00-00000000)');
                marcarError(ctrl);
                if (!primerError) primerError = ctrl;
            }

            const fechaCompra = document.getElementById('fechaCompra');
            if (!fechaCompra.value || fechaCompra.value < fechaCompra.min || fechaCompra.value > fechaCompra.max) {
                errores.push('LA FECHA DE COMPRA DEBE ESTAR ENTRE 01/01/1970 Y HOY');
                marcarError(fechaCompra);
                if (!primerError) primerError = fechaCompra;
            }

            if (productos.length === 0) errores.push('AGREGUE AL MENOS UN PRODUCTO');
            const catalogoProveedor = (window.JV_CATALOGO || {})[prov.value] || [];
            const productosFueraCatalogo = productos.some(function(producto) {
                return !catalogoProveedor.some(function(entrada) {
                    return Number(entrada.id) === Number(producto.id);
                });
            });
            if (productosFueraCatalogo) {
                errores.push('HAY PRODUCTOS QUE NO PERTENECEN AL CATÁLOGO DEL PROVEEDOR. ACTUALICE EL CATÁLOGO O QUÍTELOS DEL CARRITO.');
            }
            const cantidadesPorProducto = new Map();
            productos.forEach(function(producto) {
                const idProducto = Number(producto.id);
                cantidadesPorProducto.set(idProducto, (cantidadesPorProducto.get(idProducto) || 0) + Number(producto.cantidad));
            });
            const excedeDisponible = catalogoProveedor.some(function(producto) {
                const stock = Number(producto.stock_disponible);
                return (cantidadesPorProducto.get(Number(producto.id)) || 0) > (Number.isFinite(stock) ? stock : 0);
            });
            if (excedeDisponible) {
                errores.push('LA CANTIDAD SOLICITADA SUPERA LAS EXISTENCIAS DEL PROVEEDOR. AJUSTE O QUITE PRODUCTOS DEL CARRITO.');
            }

            if (errores.length > 0) {
                if (primerError) {
                    primerError.focus();
                    primerError.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
                Swal.fire({
                    title: 'CAMPOS REQUERIDOS',
                    html: errores.join('<br>'),
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return false;
            }

            const form = btn.form;
            if (!form) {
                btn.disabled = productos.length === 0;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar Compra';
                Swal.fire({
                    title: 'FORMULARIO NO DISPONIBLE',
                    text: 'No se pudo asociar el botón con el formulario de compra. Recargue la página e inténtelo de nuevo.',
                    icon: 'error',
                    confirmButtonColor: '#DC2626'
                });
                return false;
            }
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> GUARDANDO...';
            const formData = new FormData(form);
            fetch(form.action || window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(function(response) {
                    return response.text().then(function(text) {
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (error) {
                            throw new Error('El servidor devolvió una respuesta inválida. Revise la compra antes de reintentar.');
                        }
                        if (!response.ok || !data.success) {
                            throw new Error(data.error || 'NO SE PUDO REGISTRAR LA COMPRA.');
                        }
                        return data;
                    });
                })
                .then(function() {
                    jvBorradorLimpiar('compras_alta');
                    window.location.reload();
                })
                .catch(function(error) {
                    btn.disabled = productos.length === 0;
                    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar Compra';
                    Swal.fire({
                        title: 'NO SE PUDO GUARDAR LA COMPRA',
                        text: error.message || 'Verifique la conexión e inténtelo de nuevo.',
                        icon: 'error',
                        background: '#fff',
                        color: '#212529',
                        confirmButtonColor: '#DC2626'
                    });
                });
            return false;
        }

        // ==========================================
        // LISTADO — filtro local y anulación
        // ==========================================
        // Filtra localmente el listado de compras según el texto escrito, sin recargar la página.
        function filtrar() {
            const input = document.getElementById('buscar');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('tablaEntradas').getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(filter) ? '' : 'none';
            }
        }

        // Pide confirmación con SweetAlert y, si se acepta, envía el POST para anular la factura.
        function confirmarEliminar(id) {
            Swal.fire({
                title: '¿ANULAR?',
                text: 'La factura quedará anulada. No se puede anular si la mercancía ya fue recibida.',
                icon: 'warning',
                showCancelButton: true,
                background: '#fff',
                color: '#212529',
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'SÍ, ANULAR',
                cancelButtonText: 'CANCELAR'
            }).then(r => {
                if (r.isConfirmed) jvPost({ eliminar: id, csrf_token: window.JV_CONFIG.csrfToken });
            });
        }

        // ==========================================
        // GESTIÓN INTEGRADA DE PROVEEDORES (pop-ups)
        // Registrar / modificar / desactivar proveedores y
        // administrar su catálogo de costos sin salir de Compras.
        // ==========================================

        let provEstado = 'todos';
        let provDesdeLista = false;

        // Abre el pop-up del gestor reiniciando búsqueda y filtro de estado.
        function abrirGestorProv() {
            const buscadorProv = document.getElementById('buscarProv');
            if (buscadorProv) buscadorProv.value = '';
            provEstado = 'todos';
            document.querySelectorAll('#modalProvList .btn-filter').forEach(function(b) { b.classList.remove('active'); });
            const btnTodos = document.querySelector('#modalProvList .btn-filter');
            if (btnTodos) btnTodos.classList.add('active');
            provFiltrar();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).show();
        }

        // Cambia el filtro por estado (todos/Activo/Inactivo) y reaplica el filtrado.
        function provSetFiltro(status, boton) {
            provEstado = status;
            document.querySelectorAll('#modalProvList .btn-filter').forEach(function(b) { b.classList.remove('active'); });
            boton.classList.add('active');
            provFiltrar();
        }

        // Filtra las filas por texto (empresa/RIF/teléfono) y estado seleccionado;
        // al ocultar un proveedor también oculta su panel de catálogo expandido.
        function provFiltrar() {
            const texto = (document.getElementById('buscarProv').value || '').toLowerCase().trim();
            document.querySelectorAll('#provTbody .prov-fila').forEach(function(fila) {
                const visible = (provEstado === 'todos' || fila.dataset.status === provEstado)
                    && (!texto || (fila.dataset.texto || '').indexOf(texto) !== -1);
                fila.style.display = visible ? '' : 'none';
                const detalle = fila.nextElementSibling;
                if (detalle && detalle.classList.contains('prov-detalle-row') && !visible) {
                    detalle.style.display = 'none';
                }
            });
        }

        // Muestra u oculta el panel con los productos que suministra un proveedor.
        function provToggleDetalle(idProveedor) {
            const detalle = document.getElementById('prov-detalle-' + idProveedor);
            if (detalle) detalle.style.display = (detalle.style.display === 'none') ? '' : 'none';
        }

        // Prepara y abre el formulario en modo registro (viene desde el listado).
        function nuevoProv() {
            provDesdeLista = true;
            provAbrirForm(null);
        }

        // Prepara y abre el formulario en modo edición con los datos recibidos.
        function editarProv(proveedorData) {
            provDesdeLista = true;
            provAbrirForm(proveedorData);
        }

        // Rellena el formulario de proveedor y cambia del listado al formulario
        // (oculta el listado para evitar modales apilados).
        function provAbrirForm(proveedorData) {
            const esEdicion = !!proveedorData;
            document.getElementById('p_accion').value = esEdicion ? 'editar' : 'registrar';
            document.getElementById('p_id_edit').value = esEdicion ? proveedorData.id_proveedor : '';
            document.getElementById('modalTitle').innerText = esEdicion ? 'Editar Proveedor' : 'Registrar Nuevo Proveedor';
            document.getElementById('p_rif_tipo').value = esEdicion ? (proveedorData.rif.charAt(0) || 'J') : 'J';
            document.getElementById('p_rif_numero').value = esEdicion ? proveedorData.rif.substring(2) : '';
            document.getElementById('p_rif').value = esEdicion ? proveedorData.rif : '';
            document.getElementById('p_empresa').value = esEdicion ? proveedorData.nombre_empresa : '';
            document.getElementById('p_contacto_nombre').value = esEdicion ? (proveedorData.contacto || '') : '';
            document.getElementById('p_email').value = esEdicion ? (proveedorData.email || '') : '';
            document.getElementById('p_direccion').value = esEdicion ? (proveedorData.direccion || '') : '';
            document.getElementById('p_lead_time').value = esEdicion ? (proveedorData.lead_time || '') : '';
            document.getElementById('p_moneda').value = esEdicion ? (proveedorData.moneda || 'USD') : 'USD';
            document.getElementById('p_status').value = esEdicion ? (proveedorData.status || 'Activo') : 'Activo';

            const btnSubmit = document.getElementById('btn-prov-submit');
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="bi bi-shield-check me-2"></i>GUARDAR PROVEEDOR';
            document.getElementById('p_rif_numero').dispatchEvent(new Event('input'));

            if (window.provIti && typeof window.provIti.setNumber === 'function') {
                window.provIti.setNumber(esEdicion ? (proveedorData.telefono || '') : '');
                document.getElementById('p_tel_full').value = window.provIti.getNumber();
            } else if (window.provIti && typeof window.provIti.reset === 'function') {
                window.provIti.reset();
                document.getElementById('p_tel_full').value = '';
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).hide();
            setTimeout(function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProveedor')).show();
            }, 300);
        }

        // Confirma activar/desactivar un proveedor y envía la acción al servidor.
        function provToggleStatus(idProveedor, nombre, statusActual) {
            const activo = statusActual === 'Activo';
            Swal.fire({
                title: activo ? '\u00bfDesactivar proveedor?' : '\u00bfActivar proveedor?',
                html: activo ? 'Se desactivar\u00e1 <strong>' + escapeHtml(nombre) + '</strong>.' : 'Se reactivar\u00e1 <strong>' + escapeHtml(nombre) + '</strong>.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: activo ? '#DC2626' : '#16A34A',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: activo ? 'S\u00ed, desactivar' : 'S\u00ed, activar',
                cancelButtonText: 'Cancelar',
                background: '#fff',
                color: '#212529',
                reverseButtons: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    jvPost({
                        accion_proveedor: 'toggle_status',
                        id_proveedor: idProveedor,
                        csrf_token: window.JV_CONFIG.csrfToken
                    });
                }
            });
        }

        // Formatea en vivo el costo con separador de miles y un solo punto decimal.
        function provFormatMoney(inputElement) {
            let rawValue = inputElement.value.replace(/[^0-9.]/g, '');
            let valueParts = rawValue.split('.');
            if (valueParts.length > 2) valueParts = [valueParts[0], valueParts.slice(1).join('')];
            valueParts[0] = valueParts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            inputElement.value = valueParts.join('.');
        }

        // Abre el pop-up de catálogo en modo registro para el proveedor indicado.
        function provAgregarCat(idProveedor, nombreProveedor) {
            document.getElementById('cat_accion').value = 'registrar';
            document.getElementById('cat_id_edit').value = '';
            document.getElementById('cat_id_prov').value = idProveedor;
            document.getElementById('cat_proveedor_nombre').value = nombreProveedor;
            document.getElementById('catTitulo').innerText = 'AGREGAR PRODUCTO';
            document.getElementById('catSubtitulo').innerText = 'Asocia un producto a este proveedor con su costo de compra.';
            document.getElementById('cat_producto').value = '';
            document.getElementById('cat_costo').value = '';
            document.getElementById('cat_stock_disponible').value = '0';
            document.getElementById('cat_codigo_prov').value = '';
            const btnCat = document.getElementById('btn-cat-submit');
            btnCat.disabled = false;
            btnCat.innerHTML = '<i class="bi bi-check-lg me-2"></i>GUARDAR EN CAT\u00c1LOGO';
            provDesdeLista = true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).hide();
            setTimeout(function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCatalogo')).show();
            }, 300);
        }

        // Abre el pop-up de catálogo en modo edición con los datos de la entrada.
        function editarProductoCatalogo(entradaData) {
            document.getElementById('cat_accion').value = 'editar';
            document.getElementById('cat_id_edit').value = entradaData.id_catalogo;
            document.getElementById('cat_id_prov').value = entradaData.id_proveedor;
            const proveedorOrigen = (window.JV_PROVS || []).find(function(p) { return parseInt(p.id_proveedor, 10) === parseInt(entradaData.id_proveedor, 10); });
            document.getElementById('cat_proveedor_nombre').value = proveedorOrigen ? proveedorOrigen.nombre_empresa : '';
            document.getElementById('catTitulo').innerText = 'EDITAR PRODUCTO DEL CAT\u00c1LOGO';
            document.getElementById('catSubtitulo').innerText = 'Actualiza el costo o el c\u00f3digo interno del producto.';
            document.getElementById('cat_producto').value = entradaData.id_producto;
            document.getElementById('cat_costo').value = parseFloat(entradaData.costo).toFixed(2);
            provFormatMoney(document.getElementById('cat_costo'));
            document.getElementById('cat_stock_disponible').value = String(entradaData.stock_disponible || 0);
            document.getElementById('cat_codigo_prov').value = entradaData.codigo_proveedor || '';
            const btnCat = document.getElementById('btn-cat-submit');
            btnCat.disabled = false;
            btnCat.innerHTML = '<i class="bi bi-check-lg me-2"></i>GUARDAR EN CAT\u00c1LOGO';
            provDesdeLista = true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).hide();
            setTimeout(function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCatalogo')).show();
            }, 300);
        }

        // Confirma quitar un producto del catálogo y envía la acción al servidor.
        function provEliminarCat(idCatalogo, nombreProducto) {
            Swal.fire({
                title: '\u00bfQUITAR DEL CAT\u00c1LOGO?',
                html: 'Se quitar\u00e1 <strong>' + escapeHtml(nombreProducto) + '</strong> del cat\u00e1logo de este proveedor.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EA580C',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'S\u00ed, quitar',
                cancelButtonText: 'Cancelar',
                background: '#fff',
                color: '#212529',
                reverseButtons: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    jvPost({ eliminar_catalogo: idCatalogo, csrf_token: window.JV_CONFIG.csrfToken });
                }
            });
        }

        // Inicialización exclusiva del gestor de proveedores.
        document.addEventListener('DOMContentLoaded', function() {
            // Selector internacional de teléfono del formulario de proveedor
            const telInputProv = document.getElementById('p_tel');
            if (telInputProv && window.intlTelInput) {
                window.provIti = window.intlTelInput(telInputProv, {
                    initialCountry: 've',
                    preferredCountries: ['ve', 'us', 'co', 'es', 'mx', 'pa'],
                    separateDialCode: true,
                    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js'
                });
                const sincronizarTel = function() {
                    document.getElementById('p_tel_full').value = window.provIti.getNumber();
                };
                telInputProv.addEventListener('countrychange', sincronizarTel);
                telInputProv.addEventListener('input', sincronizarTel);
            }

            // Máscara en vivo del RIF: solo dígitos + guión verificador en el campo numérico
            const rifNumero = document.getElementById('p_rif_numero');
            const rifTipo = document.getElementById('p_rif_tipo');
            const rifHidden = document.getElementById('p_rif');

            function sincronizarRif() {
                var tipo = rifTipo ? rifTipo.value : 'J';
                var numero = rifNumero ? rifNumero.value.replace(/[^0-9-]/g, '') : '';
                rifHidden.value = tipo + '-' + numero;
            }

            if (rifTipo) {
                rifTipo.addEventListener('change', sincronizarRif);
            }
            if (rifNumero) {
                rifNumero.addEventListener('input', function(e) {
                    let valor = e.target.value.replace(/[^0-9]/g, '');
                    let formateado = '';
                    if (valor.length > 0) {
                        const esJuridico = rifTipo && ['J','G','P','C'].includes(rifTipo.value);
                        const maxCuerpo = esJuridico ? 9 : 8;
                        const cuerpo = valor.substring(0, maxCuerpo);
                        formateado = cuerpo;
                        if (esJuridico && valor.length > 8) {
                            formateado = cuerpo.substring(0, 8) + '-' + cuerpo.substring(8);
                        }
                    }
                    e.target.value = formateado;
                    sincronizarRif();
                });
            }

            // Formateo en vivo del costo del catálogo
            const costoCat = document.getElementById('cat_costo');
            if (costoCat) costoCat.addEventListener('input', function() { provFormatMoney(this); });

            // Validación del formulario de proveedor (con anti-doble-click)
            const formProv = document.getElementById('formProveedor');
            if (formProv) {
                formProv.addEventListener('submit', function(e) {
                    limpiarErrores();
                    let primerError = null;

                    const empresaEl = document.getElementById('p_empresa');
                    if (!empresaEl.value.trim()) {
                        marcarError(empresaEl, 'NOMBRE REQUERIDO');
                        e.preventDefault();
                        if (!primerError) primerError = empresaEl;
                    }

                    // Sincronizar RIF antes de validar
                    sincronizarRif();
                    const rifValidar = document.getElementById('p_rif');
                    if (!/^[VEJGPC]-\d{8}(-\d)?$/.test(rifValidar.value)) {
                        marcarError(document.getElementById('p_rif_numero'), 'RIF INV\u00c1LIDO (use: V-12345678 o J-12345678-9)');
                        e.preventDefault();
                        if (!primerError) primerError = rifValidar;
                    }

                    if (window.provIti) {
                        document.getElementById('p_tel_full').value = window.provIti.getNumber();
                        var telRaw = window.provIti.getNumber().replace(/\D/g, '');
                        // Venezuela: +58 + 10 dígitos = 12 dígitos totales
                        if (telRaw.length < 12) {
                            const telValidar = document.getElementById('p_tel');
                            marcarError(telValidar, 'TEL\u00c9FONO INV\u00c1LIDO (10 d\u00edgitos requeridos)');
                            e.preventDefault();
                            if (!primerError) primerError = telValidar;
                        }
                    }

                    const emailValidar = document.getElementById('p_email');
                    if (emailValidar.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValidar.value.trim())) {
                        marcarError(emailValidar, 'EMAIL INV\u00c1LIDO');
                        e.preventDefault();
                        if (!primerError) primerError = emailValidar;
                    }

                    if (primerError) {
                        primerError.focus();
                        return;
                    }

                    const btnGuardarProv = document.getElementById('btn-prov-submit');
                    btnGuardarProv.disabled = true;
                    btnGuardarProv.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
                });
            }

            // Validación del formulario de catálogo (anti-doble-click incluido)
            const formCat = document.getElementById('formCatalogo');
            if (formCat) {
                formCat.addEventListener('submit', function(e) {
                    limpiarErrores();
                    const prodSel = document.getElementById('cat_producto');
                    if (!prodSel.value) {
                        marcarError(prodSel, 'SELECCIONA UN PRODUCTO');
                        e.preventDefault();
                        prodSel.focus();
                        return;
                    }
                    const costoRaw = document.getElementById('cat_costo').value.replace(/,/g, '');
                    if (!(parseFloat(costoRaw) > 0)) {
                        marcarError(document.getElementById('cat_costo'), 'COSTO REQUERIDO (MAYOR A 0)');
                        e.preventDefault();
                        document.getElementById('cat_costo').focus();
                        return;
                    }
                    const stockDisponible = document.getElementById('cat_stock_disponible');
                    const stockRaw = stockDisponible.value.trim();
                    if (!/^\d{1,10}$/.test(stockRaw) || Number(stockRaw) > 4294967295) {
                        marcarError(stockDisponible, 'EXISTENCIAS INVÁLIDAS (0 A 4.294.967.295)');
                        e.preventDefault();
                        stockDisponible.focus();
                        return;
                    }
                    const btnGuardarCat = document.getElementById('btn-cat-submit');
                    btnGuardarCat.disabled = true;
                    btnGuardarCat.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>GUARDANDO...';
                });
            }

            // Si el usuario cierra el formulario/catálogo sin guardar, vuelve al listado
            ['modalProveedor', 'modalCatalogo'].forEach(function(idModal) {
                const elemento = document.getElementById(idModal);
                if (elemento) {
                    elemento.addEventListener('hidden.bs.modal', function() {
                        if (provDesdeLista) {
                            provDesdeLista = false;
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProvList')).show();
                        }
                    });
                }
            });

            // Si el servidor rechazó el guardado, marca el campo exacto que lo causó
            // traduciendo el texto del flash de peligro al input correspondiente.
            const flashProv = document.getElementById('flashMsg');
            if (flashProv && flashProv.classList.contains('alert-jv-danger')) {
                const textoFlash = (flashProv.dataset.texto || '').toUpperCase();
                const mapaCampos = [
                    ['RIF', 'p_rif_numero'],
                    ['NOMBRE', 'p_empresa'],
                    ['CORREO', 'p_email'],
                    ['EMAIL', 'p_email'],
                    ['TEL\u00c9FONO', 'p_tel']
                ];
                for (let i = 0; i < mapaCampos.length; i++) {
                    if (textoFlash.indexOf(mapaCampos[i][0]) !== -1) {
                        const campoFallido = document.getElementById(mapaCampos[i][1]);
                        if (campoFallido) {
                            marcarError(campoFallido, textoFlash);
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProveedor')).show();
                        }
                        break;
                    }
                }
            }

            // Limpia marcas de error del formulario de proveedor al escribir
            document.querySelectorAll('#formProveedor input, #formProveedor select, #formProveedor textarea, #formCatalogo input, #formCatalogo select')
                .forEach(function(elementoForm) {
                    elementoForm.addEventListener('input', function() {
                        this.classList.remove('input-error');
                        const err = document.getElementById(this.id + '_err');
                        if (err) err.remove();
                    });
                });
        });

        // ==========================================
        // INICIALIZACIÓN
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            // (El auto-cierre de mensajes flash lo maneja diseno.js globalmente.)
            proveedorCarritoId = proveedorSelect.value;
            proveedorSelect.dispatchEvent(new Event('change', { bubbles: true }));

            const compraRecuperacion = (window.JV_CONFIG || {}).compraRecuperacion;
            const recepcionRecuperacion = (window.JV_CONFIG || {}).recepcionRecuperacion;
            if (compraRecuperacion) {
                document.getElementById('selProveedor').value = String(compraRecuperacion.id_proveedor || '');
                document.getElementById('selProveedor').dispatchEvent(new Event('change', { bubbles: true }));
                document.querySelector('input[name="nro_factura"]').value = compraRecuperacion.nro_factura || '';
                document.querySelector('input[name="nro_control"]').value = compraRecuperacion.nro_control || '';
                document.getElementById('fechaCompra').value = compraRecuperacion.fecha_compra || document.getElementById('fechaCompra').value;
                productos = Array.isArray(compraRecuperacion.productos)
                    ? compraRecuperacion.productos.map(function(producto) {
                        const cantidad = Number(producto.cantidad);
                        const precio = Number(producto.precio);
                        return {
                            id: Number(producto.id),
                            nombre: String(producto.nombre || ''),
                            cantidad: cantidad,
                            precio: precio,
                            fecha_vencimiento: producto.fecha_vencimiento || '',
                            total: cantidad * precio
                        };
                    })
                    : [];
                actualizarTabla();
                const modalRecuperado = document.getElementById('modalCompra');
                if (modalRecuperado) {
                    setTimeout(function() {
                        bootstrap.Modal.getOrCreateInstance(modalRecuperado).show();
                    }, 200);
                }
            }

            // Prefill desde una solicitud de compra (atender_solicitud)
            else if (!recepcionRecuperacion && window.COMPRAS_SOLICITUD && window.COMPRAS_SOLICITUD.items && window.COMPRAS_SOLICITUD.items.length) {
                window.COMPRAS_SOLICITUD.items.forEach(function(requestItem) {
                    productos.push({
                        id: requestItem.id,
                        nombre: requestItem.nombre,
                        cantidad: requestItem.cantidad,
                        precio: requestItem.precio,
                        fecha_vencimiento: requestItem.fecha_vencimiento || '',
                        total: requestItem.cantidad * requestItem.precio
                    });
                });
                actualizarTabla();
                const modalC = document.getElementById('modalCompra');
                if (modalC) {
                    setTimeout(function() {
                        const m = bootstrap.Modal.getOrCreateInstance(modalC);
                        m.show();
                    }, 200);
                }
            }

            if (recepcionRecuperacion) {
                setTimeout(function() {
                    abrirRecepcion(Number(recepcionRecuperacion.id_compra), recepcionRecuperacion);
                }, 250);
            }

            document.querySelectorAll('input, select, textarea').forEach(function(el) {
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

            // Sidebar (mainWrapper definido en sidebar.js)
            if (typeof mainWrapper !== 'undefined' && mainWrapper) {
                const observer = new MutationObserver(() => {
                    if (document.body.classList.contains('sidebar-open')) {
                        mainWrapper.classList.add('sidebar-open');
                    } else {
                        mainWrapper.classList.remove('sidebar-open');
                    }
                });
                observer.observe(document.body, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }
        });

        // ==========================================
        // SOLICITUDES DE REPOSICIÓN (integradas)
        // Cancela una solicitud pendiente desde el listado de Compras.
        // ==========================================
        function confirmarCancelarSolicitud(idSolicitud) {
            Swal.fire({
                title: '\u00bfCANCELAR SOLICITUD?',
                text: 'La solicitud quedar\u00e1 anulada.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#DC2626',
                cancelButtonColor: '#CED4DA',
                confirmButtonText: 'S\u00ed, cancelar',
                cancelButtonText: 'Volver',
                background: '#fff',
                color: '#212529',
                reverseButtons: true
            }).then(function(result) {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('accion_cancelar_solicitud', '1');
                formData.append('id_solicitud', idSolicitud);
                formData.append('csrf_token', (window.JV_CONFIG || {}).csrfToken || '');
                fetch((window.JV_BASE || '') + 'index.php?url=solicitudes/cancelar', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                }).then(function(response) {
                    return response.json().then(function(data) {
                        if (!response.ok || !data.ok) {
                            throw new Error(data.error || 'No se pudo cancelar la solicitud.');
                        }
                        window.location.reload();
                    });
                }).catch(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'NO SE PUDO CANCELAR',
                        text: 'Verifica la conexión y vuelve a intentarlo.'
                    });
                });
            });
        }

        // ==========================================
        // RECEPCIÓN DE MERCADERÍA (integrada en Compras)
        // Registra el ingreso a inventario: lotes, stock y costo promedio.
        // El stock solo sube al confirmar la recepción (FEFO con vencimiento).
        // ==========================================
        let recepcionCompraId = null;

        // Abre el modal de recepción cargando los datos de la compra y la tabla
        // de productos pendientes por recibir (desde window.JV_CONFIG.recepcionDatos).
        function abrirRecepcion(idCompra, recuperacion) {
            const recConfig = window.JV_CONFIG || {};
            const purchaseData = recConfig.recepcionDatos && recConfig.recepcionDatos[idCompra];
            if (!purchaseData || !purchaseData.items || !purchaseData.items.length) {
                Swal.fire({
                    title: 'Sin productos pendientes',
                    text: 'Esta compra no tiene productos pendientes por recibir.',
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return;
            }
            recepcionCompraId = idCompra;
            document.getElementById('recIdCompra').value = idCompra;
            const recuperando = recuperacion && Number(recuperacion.id_compra) === Number(idCompra);
            const itemsRecuperados = new Map();
            if (recuperando && Array.isArray(recuperacion.items)) {
                recuperacion.items.forEach(function(item) {
                    itemsRecuperados.set(Number(item.id_detalle), item);
                });
            }
            const idsDetallePendientes = new Set(purchaseData.items.map(function(item) {
                return Number(item.id_detalle);
            }));
            document.getElementById('recClaveIdempotencia').value = recuperando
                ? recuperacion.clave_idempotencia
                : purchaseData.clave_idempotencia;
            document.getElementById('recFactura').value = purchaseData.nro_factura;
            document.getElementById('recProveedor').value = purchaseData.proveedor;
            document.getElementById('recDocumento').value = recuperando ? recuperacion.documento_recepcion : '';
            const recoveryNotice = document.getElementById('recuperacionRecepcionAviso');
            const detailRestored = recuperando
                && Array.isArray(recuperacion.items)
                && itemsRecuperados.size === recuperacion.items.length
                && recuperacion.items.every(function(item) {
                    return idsDetallePendientes.has(Number(item.id_detalle));
                });
            if (recoveryNotice) {
                recoveryNotice.textContent = recuperando && !detailRestored
                    ? 'No se restauraron las cantidades porque el detalle no se pudo interpretar de forma unívoca. Revise cantidades y vencimientos antes de continuar; si el resultado es incierto, verifique el estado de la recepción.'
                    : '';
                recoveryNotice.style.display = recuperando && !detailRestored ? '' : 'none';
            }

            const recBody = document.getElementById('recItemsBody');
            recBody.innerHTML = '';
            // Al restaurar, las líneas omitidas quedan en cero para no convertir el reintento en recepción completa.
            purchaseData.items.forEach(function(pendingItem, itemIndex) {
                const restoredItem = recuperando ? itemsRecuperados.get(Number(pendingItem.id_detalle)) : null;
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td style="padding:10px 12px;color:var(--jv-text-muted);text-align:center;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + (itemIndex + 1) + '</td>' +
                    '<td style="padding:10px 12px;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' +
                        '<div style="font-weight:600;">' + escapeHtml(pendingItem.nombre) + '</div>' +
                        '<div class="text-muted" style="font-size:.8rem;">' + escapeHtml(pendingItem.sku || '') + '</div>' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:center;color:var(--jv-text-muted);font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + pendingItem.cantidad + '</td>' +
                    '<td style="padding:10px 12px;text-align:center;color:var(--jv-text-muted);font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + pendingItem.recibida + '</td>' +
                    '<td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--jv-border);">' +
                        '<input type="number" class="input-jv rec-cant" data-id="' + pendingItem.id_detalle + '" data-restante="' + pendingItem.restante + '" value="' + (recuperando ? (restoredItem ? restoredItem.cantidad : 0) : pendingItem.restante) + '" min="0" max="' + pendingItem.restante + '" style="width:86px;padding:8px 10px;text-align:center;">' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:center;border-bottom:1px solid var(--jv-border);">' +
                        '<input type="date" class="input-jv rec-venc" data-id="' + pendingItem.id_detalle + '" value="' + (restoredItem ? restoredItem.fecha_vencimiento : (pendingItem.vence || '')) + '" style="width:130px;padding:8px 10px;">' +
                    '</td>' +
                    '<td style="padding:10px 12px;text-align:right;color:var(--jv-text-primary);font-weight:600;font-size:.95rem;border-bottom:1px solid var(--jv-border);">' + pendingItem.precio.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</td>';
                recBody.appendChild(tr);
            });

            const recModal = new bootstrap.Modal(document.getElementById('modalRecepcion'));
            recModal.show();
        }

        // Filtra por texto la tabla de compras pendientes de recepción, sin recargar.
        function filtrarPendientes() {
            const input = document.getElementById('buscarPendientes');
            const searchValue = input ? input.value.toLowerCase() : '';
            const tableRows = document.getElementById('tablaPendientes') ? document.getElementById('tablaPendientes').getElementsByTagName('tr') : [];
            for (let rowIndex = 0; rowIndex < tableRows.length; rowIndex++) {
                tableRows[rowIndex].style.display = tableRows[rowIndex].textContent.toLowerCase().includes(searchValue) ? '' : 'none';
            }
        }

        // Filtra por texto la tabla de recepciones ya registradas, sin recargar.
        function filtrarRecepciones() {
            const input = document.getElementById('buscarRecepciones');
            const searchValue = input ? input.value.toLowerCase() : '';
            const tableRows = document.getElementById('tablaRecepciones') ? document.getElementById('tablaRecepciones').getElementsByTagName('tr') : [];
            for (let rowIndex = 0; rowIndex < tableRows.length; rowIndex++) {
                tableRows[rowIndex].style.display = tableRows[rowIndex].textContent.toLowerCase().includes(searchValue) ? '' : 'none';
            }
        }

        // Valida las cantidades a recibir (entre 0 y el restante), arma el array de ítems,
        // lo guarda en el campo oculto y envía el formulario para registrar la recepción.
        function confirmarRecepcion(submitButton) {
            if (!recepcionCompraId) return false;

            const filas = document.querySelectorAll('#recItemsBody tr');
            const items = [];
            const errores = [];

            filas.forEach(function(receivingRow) {
                const quantityInput = receivingRow.querySelector('.rec-cant');
                if (!quantityInput) return;
                const receivedQuantity = Number(quantityInput.value);
                const remainingQuantity = Number(quantityInput.dataset.restante);
                if (!Number.isInteger(receivedQuantity) || receivedQuantity < 0 || receivedQuantity > remainingQuantity) {
                    errores.push('Cantidad inv\u00e1lida para un producto (m\u00e1x. ' + remainingQuantity + ').');
                    quantityInput.classList.add('input-error');
                } else if (receivedQuantity > 0) {
                    const expirationInput = receivingRow.querySelector('.rec-venc');
                    // REGLA DE NEGOCIO: todo lote exige fecha de vencimiento (control FEFO)
                    if (!expirationInput || !expirationInput.value) {
                        errores.push('Indique la fecha de vencimiento de todos los productos a recibir.');
                        if (expirationInput) expirationInput.classList.add('input-error');
                    }
                    items.push({
                        id_detalle: parseInt(quantityInput.dataset.id, 10),
                        cantidad: receivedQuantity,
                        fecha_vencimiento: expirationInput && expirationInput.value ? expirationInput.value : ''
                    });
                } else {
                    quantityInput.classList.remove('input-error');
                }
            });

            if (items.length === 0) {
                errores.push('Indique una cantidad mayor que cero en al menos un producto.');
            }
            if (errores.length > 0) {
                Swal.fire({
                    title: 'CANTIDADES INV\u00c1LIDAS',
                    text: errores.join(' '),
                    icon: 'warning',
                    background: '#fff',
                    color: '#212529',
                    confirmButtonColor: '#EA580C'
                });
                return false;
            }

            document.getElementById('recItemsData').value = JSON.stringify(items);
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> REGISTRANDO...';
            document.getElementById('formRecepcion').submit();
            return false;
        }

        // ==========================================
        // MODAL DE PAGO
        // ==========================================

        let pagoTotal = 0;
        let pagoSaldo = 0;

        function abrirModalPago(idCompra, factura, total, saldo) {
            pagoTotal = total;
            pagoSaldo = saldo;
            document.getElementById('pago_id_compra').value = idCompra;
            document.getElementById('pago_factura_num').textContent = factura || '-';
            document.getElementById('pago_total').value = formatCurrency(total);
            document.getElementById('pago_saldo').value = formatCurrency(saldo);
            document.getElementById('pago_monto').value = '';
            document.getElementById('pago_monto').max = saldo.toFixed(2);
            document.getElementById('pago_monto').placeholder = saldo.toFixed(2);
            document.getElementById('pago_metodo').value = 'Efectivo';
            togglePagoDetalle();
            var modal = new bootstrap.Modal(document.getElementById('modalPago'));
            modal.show();
        }

        function setPagoMonto(pct) {
            var monto = (pagoSaldo * pct / 100).toFixed(2);
            document.getElementById('pago_monto').value = monto;
        }

        function togglePagoDetalle() {
            var metodo = document.getElementById('pago_metodo').value;
            document.getElementById('pago_detalle_transferencia').style.display = metodo === 'Transferencia' ? 'block' : 'none';
            document.getElementById('pago_detalle_cheque').style.display = metodo === 'Cheque' ? 'block' : 'none';
            document.getElementById('pago_detalle_otro').style.display = metodo === 'Otro' ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            var metodoSelect = document.getElementById('pago_metodo');
            if (metodoSelect) {
                metodoSelect.addEventListener('change', togglePagoDetalle);
            }
        });
