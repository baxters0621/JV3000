# MEMORY.md - Memoria de JV3000

Memoria del proyecto entre sesiones. Máximo ~50 líneas: resume o elimina lo que ya no aporte.
Sin datos sensibles (claves, tokens, PIN).

## Estado actual

- Ventas (Cliente rápido): Módulo 1 implementado, validado y probado por el usuario; el alta AJAX devuelve `success/id_cliente/nombre/documento` y completa cliente/RIF.
- Compras: precio unitario inmutable tomado del catálogo; disponible reservado al crear compra y devuelto al anular, fecha editable hasta hoy y carrito con montos alineados; feed de recepciones y tabla principal rediseñados, proveedor con modal simétrico. Anulación bloqueada para recepciones parciales/completas y validada también en el modelo. Dashboard: Actividad Reciente prioriza montos y fechas, Productos Críticos usa badges sin barras; las tarjetas y KPIs se recalculan al cargar, y una señal de éxito en localStorage actualiza al instante otras pestañas del Dashboard tras venta, compra, pago, anulación o recepción. El seed_pruebas.php es temporal: localhost + jv3000_db, sesión admin, CSRF y confirmación; no borra, crea lotes/catálogo coherentes y se elimina al terminar las pruebas.
- Instalación automática en equipo nuevo verificada de punta a punta (`init.php` + `db/jv3000_portable_v6.sql`); validación global en verde (`tools/validar.ps1`).
- PIN de emergencia global fijado y probado E2E (bloqueo a los 3 intentos, interfaz pulida con ojos tachados y flash de 4 s); contraseñas de 8-18 sin espacios en alta, restablecimiento, recuperación y perfil, marcadas en rojo y rechazadas sin recortar.
- Colaboradores: alta/edición con validación en cliente y servidor, nombres Unicode de 4-20 con `normalizarUsuario()` (Title_Case), insignias por rol y «Guardar cambios» solo con cambios reales. La interfaz usa navy/azul cielo y textos visibles de colaborador; nombres de usuario e IDs técnicos se conservan. Las cuentas NO se eliminan: solo se suspenden (decisión del usuario, 2026-10-23). Sidebar Control usa navy/azul suave y el enlace dice Colaboradores.
- Historial de Operaciones: filtros dispuestos con Grid Bootstrap; tabla centrada, estados compactos y totales verdes en ventas/navy en compras. Solo presentación; filtros, permisos y datos permanecen iguales.
- EDITAR PRODUCTO incluye campo CATEGORÍA (solo admin): select del árbol activo + «Sin categoría», precarga desde data-cat con opción temporal «(inactiva)»; el modelo valida, audita «categoría: A → B» y sincroniza el árbol al cambiar de rama. Sin alta de productos en la UI: entran por seed/BD (decisión del usuario, 29/10).
- Categorías: sidebar «Categorías» (icono de jerarquía); árbol de dos niveles con conteo con-stock/total por rama y etiquetas SIN STOCK/OCULTA; el estado de las categorías con productos lo maneja el stock (todo en cero → Inactiva, entra mercancía → Activa; sincronizado tras recepción, salidas y baja por vencimiento, y como red de seguridad al abrir la página); cambio a mano solo en categorías vacías; productos filtrados por rama con ?cat=N (banner + paginación que conserva el filtro); alta/edición con categoría padre (solo raíces activas, máximo 2 niveles, nombre único entre hermanas) y flashes con origen (cat_crear, cat_editar:ID, cat_toggle, prod_toggle, prod_baja, prod_editar:ID).
- Observabilidad: la tabla `errores`, ErrorLog y captura del navegador siguen activos; el panel y sus acciones (`index`, `detalle`, `resolver`) responden 403 para todos, se retiro del sidebar y el reporter ya no muestra SweetAlert. Correo al admin solo si JV_MAIL_ADMIN tiene direccion.
- Borradores de formulario (fase 1): jvBorrador en diseno.js guarda data-borrador de 14 formularios en localStorage (excluye secretos, atado a userId, caduca 24 h); solo persiste eventos confiables del usuario. Compras purga borradores sin proveedor/factura/control/observaciones antes de auto-restaurar, evitando falsos avisos. Éxito <120 s purga; sin éxito autorestaura con toast; resto pregunta con Swal. Fase 2 (grillas dinámicas) pendiente.
- Reglas de acceso cerradas en `includes/ajax`: `exportar_csv.php` exporta solo según el mapa de roles (historial solo admin), `clientes_buscar.php` exige ventas y `productos_buscar.php` es lectura común; eran endpoints huérfanos con datos abiertos a cualquier rol.

## Decisiones (y por qué)

- Normalización Title_Case unificada con `normalizarUsuario()`; nunca `mb_strtoupper()` en nombres de persona ni de categoría.
- Los flashes llevan origen (`Controller::flash(..., $origen)` + `data-origen`): el JS reabre el modal correcto y marca el campo responsable; el heurístico por texto queda de respaldo.
- Límites de error: Router captura el Throwable de cada acción y delega en Controller::responderError (JSON en AJAX — todo fetch lleva X-Requested-With —, flash y redirección en web); el navegador muestra UN aviso amable por página desde diseno.js. Un módulo roto no bota al usuario ni muere en silencio.
- Carga visible: skeletons globales (jv-skel en diseno.css; jvCargaIniciar/jvCargaTarde/jvFetchConTope en diseno.js) con gracia de 700 ms y tope de 12 s. Los endpoints AJAX devuelven success/message y terminan; si una escritura supera el tope, verificar el registro antes de reintentar porque el servidor podria haberla completado. La confirmación final de venta desde la nota también usa JSON con tope; la tasa BCV consulta USD/EUR en paralelo (curl_multi, tope 6 s: peor caso ~6 s, antes ~20 s); la campanita del panel se descartó porque pinta server-side.
- Ojos tachados por defecto en contraseñas/PIN: el icono indica el estado actual, no la acción.
- CSS/JS por módulo, sin globalizar; en Estadísticas/Historial el color identifica el tipo de operación, con foco visible y movimiento reducido.
- El árbol/gestor de categorías es una herramienta bajo demanda (botón), no un panel permanente que reduzca la tabla.
- Edición de colaborador valida también en el servidor; los avisos del cliente no sustituyen validación y unicidad en la base.
- El rol Administrador es único: los colaboradores solo son Carga o Ventas.
- Acceso al Historial por alcance: administración ve compras y ventas, carga compras, ventas ventas; el servidor aplica el mismo límite que el menú.
- Pregunta/respuesta de seguridad retirada; columnas por legado, no usar.
- La huella de error agrupa por archivo:línea (o inicio del mensaje sin archivo) y el INSERT suma ocurrencias: un error repetido nunca duplica filas ni dispara un segundo correo antes de 1 hora.

- Formularios dinámicos de edición: declarar `data-borrador-contexto` para validar el identificador del registro; si el borrador pertenece a otra fila, descartarlo antes de restaurar y conservar el borrador coincidente.

## Aprendizajes y errores a evitar

- Si falta `config/.env`, los valores de XAMPP son válidos; no convertir un puerto ausente en configuración inválida.
- La copia universitaria se prueba con su SQL en entorno aislado; los pilotos por separado no garantizan el arranque del paquete.
- `diseno.js` se carga en el `<head>`: su código de DOM debe esperar a DOMContentLoaded.
- El login ignora el POST si falta `btn_login` (vuelve a pintar la pantalla, sin error).
- PowerShell 5.1 corrompe UTF-8 (dejó bytes NUL en MEMORY.md y una entrada en UTF-16/latin-1 en la bitácora): bitácora y memoria SOLO se escriben con PHP y se verifican después (BOM, sin NUL, sin dobles codificaciones).
- Un piloto puede quedar obsoleto cuando el modelo evoluciona (categorias_jerarquia esperaba rutas en mayúsculas tras el paso a Title_Case): al tocar un modelo, correr su piloto y alinear sus aserciones.
- Los modales de Colaboradores se renderizan fuera de `.pagina-usuarios`: no escribir reglas de modal bajo ese ancestro.
- El ítem «Panel de Inicio» se oculta con `$en_panel_inicio`; verificar `nav-ram-panel` en lugar del chip del hero. Formularios con `novalidate` y `jvPost` necesitan listener submit explícito y las claves del controlador.
- Al limpiar sesiones de prueba en `C:\xampp\tmp` usar el `sid` exacto, nunca comodines; `Invoke-WebRequest` descarta Cookie: usar `curl.exe -H "Cookie: PHPSESSID=sid"`. El render CLI con `init.php` requiere `$_SESSION['ip_addr'] = REMOTE_ADDR`.
- En guiones PHP, `'\n'` no se expande (usar comillas dobles o `PHP_EOL`); `NULL ?? 'x'` da `'x'`, así que comprobar nulos con `=== null`, no con coalescing ni `isset()`.
- Los ficheros de `includes/ajax/` pasan por init.php: la sesión ya viene validada; la puerta por rol va tras el chequeo XHR y responde 403 JSON (no redirigir). Sesiones artesanales por cookie no valen para pruebas HTTP: use_strict_mode borra el fichero de sesión.
- Restos de herramientas de agentes en el repo (refs/agents, refs/cline, ramas agents/* y copilot/*): los checkpoints se borraron el 29/10; las ramas agents/* guardan commits sin fusionar en main — no borrarlas ni fusionarlas sin revisar su contenido.
