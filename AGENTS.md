AGENTS.md — JV3000 C.A.

Sistema web para el control de inventario, compras y ventas de JV3000 C.A., con roles
(Administrador, Operador de Carga y Operador de Ventas) y recuperación de cuentas por
código de correo o PIN de emergencia. Objetivo del agente: mantenerlo funcionando con
cambios quirúrgicos, siempre verificados y registrados.

## Memoria del proyecto

- Al empezar, lee `MEMORY.md` para conocer el estado del proyecto y las decisiones tomadas.
- Al terminar una tarea, actualízalo: estado actual, decisiones importantes (con su porqué) y errores a evitar.
- Mantenlo breve (máximo ~50 líneas): resume o elimina lo que ya no aporte.
- Si algo se convierte en una regla permanente, propón moverlo a `AGENTS.md` en lugar de dejarlo en la memoria.
- No guardes nunca datos sensibles (claves, tokens, datos personales, PIN).

## Stack y estructura

- **PHP 8.2.12** (XAMPP, ZTS) · **MariaDB 10.4** (servicio Windows `mysql`, puerto 3306) · **Apache** (XAMPP) · **Node 24** (solo lint y tests JS).
- **Frontend:** Bootstrap 5 + Bootstrap Icons + Chart.js + SweetAlert2. **Sin jQuery** y sin framework PHP.
- MVC artesanal: `index.php?url=modulo/accion/param` → `core/Router.php` → `controllers/` → `models/` → `views/`.
- Carpetas (solo lo no obvio):
  - `core/` — base MVC: `Router`, `Controller`, `Model`.
  - `includes/` — `Security.php` (CSRF y roles), `Mail.php`, `Database.php`, `helpers.php`, `diseno.php` (head global), `sidebar.php`, y `EstadoInstalacion/InstaladorInicial/SchemaCompatibility/StartupDiagnostics` (auto-instalador).
  - `assets/` — `js/diseno.js` + CSS global; `dashboard/` y `modules/` llevan el CSS/JS **de cada módulo**; `login/` es el JS de acceso.
  - `dashboard/` — panel tras el login · `login/` — login y recuperación de contraseña.
  - `db/jv3000_portable_v6.sql` — seed de instalación limpia · `backups/` — respaldos · `cache/`.
  - `tests/` — pilotos (8 PHP y 2 JS) · `tools/` — `validar.ps1` y `resetear_admin.php`.
  - `docs/` — `BITACORA.md` (historial obligatorio) y `README.md`.

## Comandos

- **Validación completa** (antes de dar cualquier tarea por terminada):
  `powershell -ExecutionPolicy Bypass -File tools\validar.ps1`
  Si marca `[000]` en los chequeos 403, Apache no está corriendo: lanzarlo con `C:\xampp\apache\bin\httpd.exe`.
- **Lint suelto:** `& "C:\xampp\php\php.exe" -l ruta\archivo.php` · `node --check ruta\archivo.js`
- **Tests** (desde la raíz): `& "C:\xampp\php\php.exe" tests\pin_global_piloto.php` · `node tests\flash_auto_cierre.js` · `node tests\salidas_captura_duplicados.js`
  `ventas_stock_piloto.php`, `compras_recepcion_piloto.php` y `envio_correo_piloto.php` exigen fixtures exclusivas o un correo real: **se niegan contra la BD de desarrollo y eso no es un fallo**.
- **MySQL** (servicio Windows con auto-arranque): `& "C:\xampp\mysql\bin\mysql.exe" -u root jv3000_db -e "SELECT ..."` · `Start-Service mysql` / `Restart-Service mysql`
- **Git:** la entrada de bitácora y el commit van juntos; el **push solo cuando el usuario lo autorice**.
- **Skills del agente** (instaladas a nivel global en `C:\Users\MamutteaM\.agents\skills\`):
  `npx skills update` (actualizar) · `npx skills add <owner/repo@skill>` (instalar) · `npx skills find <consulta>` (buscar).
  Uso habitual: `frontend-design` y `web-design-guidelines` al tocar diseño; `grill-me` para que el usuario depure un plan antes de implementarlo; `systematic-debugging` ante cualquier bug o test en rojo; `find-skills` para localizar capacidades nuevas.

## Convenciones y Eficiencia de Tokens

- **Directo al grano (Ahorro de tokens):** Elimina saludos, introducciones y despedidas (evita frases como "Aquí tienes el código"). Empieza directamente con la respuesta o el código.
- **Precisión quirúrgica:** Limítate estrictamente a lo que se pide. NO agregues funcionalidades no solicitadas, ni refactorices código no relacionado a menos que se indique.
- **Modificaciones en Diffs:** Al editar código existente, devuelve ÚNICAMENTE las funciones/líneas modificadas o un diff limpio. NO reescribas archivos completos a menos que sea estrictamente necesario.
- **Explicaciones:** Solo si se piden explícitamente o si existe un riesgo crítico de seguridad/rendimiento.
- **Ahorro de contexto:** NO repitas el prompt, ni bloques de código, ni texto que el usuario ya haya proporcionado.
- **Modos de aprendizaje (opt-in):** solo si los pide expresamente — «modo profesor», «modo pair», «modo senior» y «modo debug». Por defecto, respuesta directa.
- **Separación estricta de responsabilidades:**
  - **Vistas** (`views/`): HTML + clases Bootstrap, sin lógica pesada.
  - **JavaScript** (`assets/`): listeners, validaciones cliente y peticiones al backend.
  - **Backend** (`controllers/`, `models/`): procesamiento, validación de servidor y respuesta JSON con `Controller::json()`.
- **Funciones JS:** camelCase descriptivo (`abrirRecepcion()`), sin prefijos `fn_`.
- **IDs/clases HTML:** descriptivos simples (`edit_pvp`, `modalEditar`, `tablaProductos`), sin prefijos de tipo.
- **Variables PHP:** snake_case descriptivo (`$stock_actual`, `$credito_disponible`); prohibidas abreviaturas crípticas.
- **Controladores:** `controllers/[Modulo]Controller.php` con acciones camelCase · **modelos:** `models/[Entidad].php`.
- **AJAX:** solo `fetch` nativo. Flujo obligatorio: listeners → validaciones cliente → fetch → manejo JSON success/error.
- **Documentación:** docblocks en español en toda clase/método PHP y comentarios didácticos en funciones JS.
- El `includes/sidebar.php` va ordenado por fases operativas con títulos de sección: Configuración → Operaciones → Análisis → Control. Mantener ese orden.

## Reglas de dominio / trampas conocidas

- **Roles:** `$_SESSION['id_rol']` → 1 Administrador, 2 Operador de Carga, 3 Operador de Ventas. `Security::esAdmin()` → `id_rol === 1`; `puedeCargar()` → 1 o 2; `puedeVender()` → 1 o 3.
- **Mapa de accesos por módulo:** Categorías/Proveedores = admin + carga · Usuarios = solo admin · Historial de operaciones = admin (compras y ventas), carga (compras) y ventas (ventas) · Compras = admin + carga (incluye Solicitudes y Recepción) · Ventas/Salidas = admin + ventas · Inventario = consulta los tres, escritura exige admin · Estadísticas = admin + ventas · Imprimir reporte = los tres.
- **Recuperación de cuenta:** PIN único en `configuracion.pin_emergencia_global`, válido para **todas** las cuentas, y código por correo (15 min). El PIN bloquea a los 3 intentos. Se guarda y compara en claro (`hash_equals`).
- **Todo POST exige `csrf_token` de sesión.** El login exige además el campo `btn_login`: **si falta, el POST se ignora sin error**.
- **Mensajes flash:** se auto-cierran a los 4 s desde `assets/js/diseno.js` (clase `.jv-flash-out`).
- **Assets versionados por mtime** (`?v=`): para servir CSS/JS nuevo basta recargar.
- **Seed `db/jv3000_portable_v6.sql`:** instalación limpia con solo datos de sistema.
- **Pregunta/respuesta de seguridad:** retirada de los formularios — **no usarlas ni rehabilitarlas**.
- **Contraseñas:** `password` en bcrypt + `password_check` (SHA-256 en minúsculas) para detectar duplicados.
- **La bitácora se escribe con PHP** para no dañar el UTF-8 (BOM intacto y sin dobles codificaciones).

## Forma de trabajar

1. ¿Esto realmente necesita existir? → Si la respuesta es NO, ignóralo.
2. ¿Ya existe algo similar en este proyecto? → Reutilízalo.
3. ¿La librería estándar o nativa lo hace? → Úsala.
4. ¿Se puede solucionar en una sola línea? → Escribe una sola línea.
5. Solo si nada de lo anterior funciona: escribe el código mínimo necesario.

- **Modo quirúrgico:** al modificar algo existente, entregar solo el bloque afectado.
- **Bifurcación de diseño:** antes de tocar, preguntar con opciones y esperar la decisión del usuario.
- **Al terminar:** explicar qué cambió, por qué y cómo se verificó; dejar `MEMORY.md` y la bitácora actualizados y el árbol limpio.

## Límites

✅ **Siempre, sin preguntar:**
- Registrar cada cambio en `docs/BITACORA.md` y actualizar `MEMORY.md` al cerrar la tarea.
- Ejecutar la verificación completa antes de dar el trabajo por terminado.
- Respuestas directas, cambios quirúrgicos y docblocks en español.
- Mantener intactas las validaciones de seguridad y accesibilidad.

⚠️ **Pregunta antes:**
- Dependencias o librerías nuevas.
- Ficheros o módulos fuera del plan acordado.
- Cambios en el esquema de la base de datos o en el seed.
- Globalizar CSS/JS o cambiar el formato de los datos guardados.
- `git push`, y fijar o cambiar datos reales.

🚫 **Nunca:**
- Recortar validaciones de seguridad o manejo de errores críticos.
- Inventar datos o funcionalidades no probadas.
- Borrar o reescribir el historial de `docs/BITACORA.md`.
- Exponer credenciales en ficheros o código.
- Tocar `backups/` o columnas de legado sin tarea explícita.

## Verificación

1. Sintaxis del fichero tocado: `php -l` / `node --check`.
2. Tests del módulo afectado en `tests/` en verde.
3. `powershell -ExecutionPolicy Bypass -File tools\validar.ps1` → `Validacion completada`.
4. Comprobación HTTP real con sesión de administrador (página a **200**, asset con `?v` nuevo).
5. Comprobación en BD (sin basura o temporales).
6. Entrada en la bitácora con UTF-8 intacto.
7. Commit junto con la bitácora; push solo con autorización.