# MEMORY.md - Memoria de JV3000

Memoria del proyecto entre sesiones. Máximo ~50 líneas: resume o elimina lo que ya no aporte.
Sin datos sensibles (claves, tokens, PIN).

## Estado actual

- Instalación automática en equipo nuevo verificada de punta a punta (`init.php` + `db/jv3000_portable_v6.sql`); validación global en verde (`tools/validar.ps1`).
- PIN de emergencia global fijado y probado E2E (bloqueo a los 3 intentos, interfaz pulida con ojos tachados y flash de 4 s); contraseñas de 8-18 sin espacios en alta, restablecimiento, recuperación y perfil, marcadas en rojo y rechazadas sin recortar.
- Colaboradores: alta/edición con validación en cliente y servidor, nombres Unicode de 4-20 con `normalizarUsuario()` (Title_Case), insignias por rol y «Guardar cambios» solo con cambios reales. Las cuentas NO se eliminan: solo se suspenden (decisión del usuario, 2026-10-23).
- Encabezado del dashboard en jerarquía vertical (29/10): fila superior con el estado (fecha/hora, campana, usuario) reducido y pegado a la derecha EN FLUJO; marca centrada en su propia fila (logo 90px, título 2.2rem); chip «Panel de Inicio» centrado, en azul cielo (único acento no naranja del hero). ≤768px el estado se centra y todo se compacta. «Panel de Inicio» se oculta solo en el panel.
- Historial de operaciones paginado y filtrable por rol (compras/ventas), rediseñado con el patrón module-header y color verde/rojo por tipo (compra/venta); Estadísticas abre los últimos 90 días y compara con el período previo.
- EDITAR PRODUCTO incluye campo CATEGORÍA (solo admin): select del árbol activo + «Sin categoría», precarga desde data-cat con opción temporal «(inactiva)»; el modelo valida, audita «categoría: A → B» y sincroniza el árbol al cambiar de rama. Sin alta de productos en la UI: entran por seed/BD (decisión del usuario, 29/10).
- Categorías: sidebar «Categorías» (icono de jerarquía); árbol de dos niveles con conteo con-stock/total por rama y etiquetas SIN STOCK/OCULTA; el estado de las categorías con productos lo maneja el stock (todo en cero → Inactiva, entra mercancía → Activa; sincronizado tras recepción, salidas y baja por vencimiento, y como red de seguridad al abrir la página); cambio a mano solo en categorías vacías; productos filtrados por rama con ?cat=N (banner + paginación que conserva el filtro); alta/edición con categoría padre (solo raíces activas, máximo 2 niveles, nombre único entre hermanas) y flashes con origen (cat_crear, cat_editar:ID, cat_toggle, prod_toggle, prod_baja, prod_editar:ID).
- El árbol vive dentro del gestor modal de Categorías (botón superior); la tabla de productos queda a ancho completo. Nombres en Title_Case.
- Observabilidad activa: tabla `errores` + panel Diagnóstico (solo admin, Control) + reporte del navegador; correo al admin solo si JV_MAIL_ADMIN tiene dirección (destinatario fijado en .env; sin red o sin SMTP el aviso cae en silencio y queda en el panel).
- Borradores de formulario (fase 1): jvBorrador en diseno.js guarda data-borrador de 14 formularios en localStorage (excluye secretos, atado a userId, caduca 24 h); éxito <120 s purga, sin éxito autorestaura con toast, resto pregunta con Swal. Fase 2 (grillas dinámicas) pendiente.
- Reglas de acceso cerradas en `includes/ajax`: `exportar_csv.php` exporta solo según el mapa de roles (historial solo admin), `clientes_buscar.php` exige ventas y `productos_buscar.php` es lectura común; eran endpoints huérfanos con datos abiertos a cualquier rol.

## Decisiones (y por qué)

- Normalización Title_Case unificada con `normalizarUsuario()`; nunca `mb_strtoupper()` en nombres de persona ni de categoría.
- Los flashes llevan origen (`Controller::flash(..., $origen)` + `data-origen`): el JS reabre el modal correcto y marca el campo responsable; el heurístico por texto queda de respaldo.
- Límites de error: Router captura el Throwable de cada acción y delega en Controller::responderError (JSON en AJAX — todo fetch lleva X-Requested-With —, flash y redirección en web); el navegador muestra UN aviso amable por página desde diseno.js. Un módulo roto no bota al usuario ni muere en silencio.
- Carga visible: skeletons globales (jv-skel en diseno.css; jvCargaIniciar/jvCargaTarde/jvFetchConTope en diseno.js) con gracia de 700 ms y tope de 12 s — nunca una espera sin rostro ni sin salida. La tasa BCV consulta USD/EUR en paralelo (curl_multi, tope 6 s: peor caso ~6 s, antes ~20 s); la campanita del panel se descartó porque pinta server-side.
- Ojos tachados por defecto en contraseñas/PIN: el icono indica el estado actual, no la acción.
- CSS/JS por módulo, sin globalizar; en Estadísticas/Historial el color identifica el tipo de operación, con foco visible y movimiento reducido.
- El árbol/gestor de categorías es una herramienta bajo demanda (botón), no un panel permanente que reduzca la tabla.
- Edición de colaborador valida también en el servidor; los avisos del cliente no sustituyen validación y unicidad en la base.
- El rol Administrador es único: los colaboradores solo son Carga o Ventas.
- Acceso al Historial por alcance: administración ve compras y ventas, carga compras, ventas ventas; el servidor aplica el mismo límite que el menú.
- Pregunta/respuesta de seguridad retirada; columnas por legado, no usar.
- La huella de error agrupa por archivo:línea (o inicio del mensaje sin archivo) y el INSERT suma ocurrencias: un error repetido nunca duplica filas ni dispara un segundo correo antes de 1 hora.

## Aprendizajes y errores a evitar

- Si falta `config/.env`, los valores de XAMPP son válidos; no convertir un puerto ausente en configuración inválida.
- La copia universitaria se prueba con su SQL en entorno aislado; los pilotos por separado no garantizan el arranque del paquete.
- `diseno.js` se carga en el `<head>`: su código de DOM debe esperar a DOMContentLoaded.
- El login ignora el POST si falta `btn_login` (vuelve a pintar la pantalla, sin error).
- PowerShell 5.1 corrompe UTF-8 (dejó bytes NUL en MEMORY.md y una entrada en UTF-16/latin-1 en la bitácora): bitácora y memoria SOLO se escriben con PHP y se verifican después (BOM, sin NUL, sin dobles codificaciones).
- Un piloto puede quedar obsoleto cuando el modelo evoluciona (categorias_jerarquia esperaba rutas en mayúsculas tras el paso a Title_Case): al tocar un modelo, correr su piloto y alinear sus aserciones.
- Un fetch simulado debe honrar la señal de aborto: si no rechaza al abortar, la promesa queda colgada y Node sale en silencio con código 0 (pasó en skeleton_carga).
- Un CSS de módulo puede arrastrar dos diseños superpuestos cuando varias sesiones lo tocan en paralelo: antes de retocar, leer el fichero completo y su vista (pasó en historial.css).
- Los modales de Colaboradores se renderizan fuera de `.pagina-usuarios`: no escribir reglas de modal bajo ese ancestro.
- El ítem «Panel de Inicio» se oculta con `$en_panel_inicio`; los `Contains()` de verificación deben apuntar a `nav-ram-panel` (el chip del hero repite el texto).
- Formularios con `novalidate` y envío vía `jvPost` necesitan listener submit explícito y mandar las claves que procesa el controlador.
- Al limpiar sesiones de prueba en `C:\xampp\tmp` usar el `sid` exacto, nunca comodines; para barridos HTTP con sesión, Invoke-WebRequest descarta el encabezado Cookie de -Headers: usar `curl.exe -H "Cookie: PHPSESSID=sid"`.
- Las cadenas con `\n` en comillas simples no se expanden: usar comillas dobles o concatenar con PHP_EOL en los guiones PHP.
- `NULL ?? 'x'` es 'x': isset() niega los NULL reales; en asserts y comparaciones usar `=== null` sin coalescing.
- Los ficheros de `includes/ajax/` pasan por init.php: la sesión ya viene validada; la puerta por rol va tras el chequeo XHR y responde 403 JSON (no redirigir). Sesiones artesanales por cookie no valen para pruebas HTTP: use_strict_mode borra el fichero de sesión.
- El render CLI de páginas reales pasa por init.php: proveer $_SESSION con `ip_addr` = REMOTE_ADDR además de id_usuario, o Security::validateSession() redirige con exit en silencio; capturar la salida con try/catch y borrar las sess_* de C:\xampp\tmp al terminar.
- Restos de herramientas de agentes en el repo (refs/agents, refs/cline, ramas agents/* y copilot/*): los checkpoints se borraron el 29/10; las ramas agents/* guardan commits sin fusionar en main — no borrarlas ni fusionarlas sin revisar su contenido.
