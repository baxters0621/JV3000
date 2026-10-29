# MEMORY.md - Memoria de JV3000

Memoria del proyecto entre sesiones. Máximo ~50 líneas: resume o elimina lo que ya no aporte.
Sin datos sensibles (claves, tokens, PIN).

## Estado actual

- Instalación automática en equipo nuevo verificada de punta a punta (`init.php` + `db/jv3000_portable_v6.sql`); validación global en verde (`tools/validar.ps1`).
- PIN de emergencia global fijado por la interfaz y probado E2E (recuperación completa y bloqueo a los 3 intentos); interfaz pulida (ojos tachados, ayuda fija, flash que se auto-cierra a los 4 s).
- Contraseñas nuevas: 8-18 caracteres sin espacios en creación, restablecimiento, recuperación y perfil; el cliente marca espacios en rojo y el servidor los rechaza sin recortar.
- Colaboradores: alta/edición con validación en cliente y servidor, nombres Unicode de 4-20 con `normalizarUsuario()` (Title_Case), insignias por rol y «Guardar cambios» solo con cambios reales. Las cuentas NO se eliminan: solo se suspenden (decisión del usuario, 2026-10-23).
- Encabezado del dashboard en dos bandas (identidad arriba; ubicación + fecha/hora/campana/usuario abajo); «Panel de Inicio» se oculta solo en el panel.
- Historial de operaciones paginado y filtrable por rol (compras/ventas) con auditoría separada; Estadísticas abre los últimos 90 días y compara con el período previo. Historial rediseñado en dos pasadas (26 y 28/10): la segunda unifica el encabezado con el patrón global module-header (icono navy, contador en pill) y reescribe el CSS en limpio — el fichero tenía dos diseños superpuestos, ~230 líneas muertas y 3 variables usadas sin definirse; color por operación compra verde / venta rojo reutilizado de Estadísticas.
- Categorías: sidebar «Categorías» (icono de jerarquía); árbol de dos niveles con conteo con-stock/total por rama y etiquetas SIN STOCK/OCULTA; el estado de las categorías con productos lo maneja el stock (todo en cero → Inactiva, entra mercancía → Activa; sincronizado tras recepción, salidas y baja por vencimiento, y como red de seguridad al abrir la página); cambio a mano solo en categorías vacías; productos filtrados por rama con ?cat=N (banner + paginación que conserva el filtro); alta/edición con categoría padre (solo raíces activas, máximo 2 niveles, nombre único entre hermanas) y flashes con origen (cat_crear, cat_editar:ID, cat_toggle, prod_toggle, prod_baja, prod_editar:ID).
- El árbol vive dentro del gestor modal de Categorías (botón superior); la tabla de productos queda a ancho completo. Nombres en Title_Case.
- `marcarError`/`limpiarErrores` son globales en diseno.js (productos, compras y salidas los usaban sin definir) y diseno.css se versiona por mtime (antes ?v=11 fijo).
- Observabilidad activa: tabla `errores` + panel Diagnóstico (solo admin, Control) + reporte del navegador; correo al admin solo si JV_MAIL_ADMIN tiene dirección (hoy vacía: sin correos, todo queda en el panel).

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
- Al limpiar sesiones de prueba en `C:\xampp\tmp` usar el `sid` exacto, nunca comodines.
- Las cadenas con `\n` en comillas simples no se expanden: usar comillas dobles o concatenar con PHP_EOL en los guiones PHP.
- `NULL ?? 'x'` es 'x': isset() niega los NULL reales; en asserts y comparaciones usar `=== null` sin coalescing.
