# MEMORY.md — Memoria de JV3000

Memoria del proyecto entre sesiones. Máximo ~50 líneas: resume o elimina lo que ya no aporte.
Sin datos sensibles (claves, tokens, PIN).

## Estado actual

- Instalación automática en equipo nuevo verificada de punta a punta (`init.php` + `db/jv3000_portable_v6.sql`).
- Validación global en verde: `tools/validar.ps1` (lint PHP/JS, rutas 403, integridad BD, ACL, comportamiento global).
- PIN de emergencia global fijado por la interfaz y probado E2E (recuperación completa y bloqueo a los 3 intentos); el valor lo cambia el administrador desde Colaboradores.
- Interfaz del PIN pulida: ojos tachados por defecto, `.alert-lectura` en modales, ayuda como línea fija y flash que se auto-cierra a los 4 s.
- Contraseñas nuevas: entre 8 y 18 caracteres sin espacios, en creación, restablecimiento, recuperación y perfil. El cliente marca espacios en rojo, no los cuenta en el contador ni da por cumplidos requisitos; el servidor los rechaza. Se mantienen mínimo 8, mayúscula, minúscula, número y símbolo; bcrypt conserva su resguardo interno.
- Colaboradores: edición valida usuario/correo/duplicados/rol también en servidor; Administrador no se ofrece ni asigna a otros colaboradores.
- Usuarios: nombres de 4–20 caracteres Unicode, empiezan con letra y luego admiten letras de cualquier idioma, números, marcas diacríticas y guion bajo; no admiten símbolos como `*`. Cliente, registro, perfil y servidor comparten la regla.
- Insignias de roles en Colaboradores (admin violeta, carga verde, ventas roja); el alta valida en cliente y servidor con botón condicionado y pie visible.
- Encabezado del dashboard reorganizado en dos bandas: identidad arriba y, abajo, chip de ubicación en naranja a la izquierda + fecha/hora/campana/usuario a la derecha. El ítem «Panel de Inicio» del sidebar desaparece solo en el panel y reaparece en el resto de páginas.
- Historial muestra compras/ventas paginadas y filtrables, con alcance por rol y auditoría separada; Estadísticas abre por defecto los últimos 90 días para evitar un panel vacío cuando hay operaciones recientes, compara con el período previo y expone fallos de actualización con reintento. Ambos usan compras verdes y ventas rojas.
- Categorías de Inventario: una categoría con productos queda Inactiva solo cuando todos están en cero; la raíz considera sus subcategorías, cuentan también productos Inactivos y el stock recibido la reactiva automáticamente. Categorías vacías conservan su estado manual y las agotadas siguen visibles como SIN STOCK.
- Interfaz de Categorías en Inventario: el listado de productos ocupa todo el ancho; el botón superior abre un modal centrado para buscar y filtrar, y desde allí crear/editar. Al cerrar el formulario de categoría se regresa al gestor.
- El gestor de Categorías usa filas azul/teal para distinguir raíces y subcategorías, botones de acción visibles y tipografía ampliada. «Nueva categoría» va agrupada con cerrar en la esquina superior derecha del encabezado.

## Decisiones (y por qué)

- Normalización Title_Case unificada en alta, edición y perfil con `normalizarUsuario()` (José_Pérez); nunca `mb_strtoupper()`, que convertía el nombre a mayúsculas al editarlo.
- Los flashes de Colaboradores llevan origen (`Controller::flash(..., $origen)` + `data-origen` en la vista): el JS reabre el modal correcto y marca el campo responsable; el heurístico por texto queda solo de respaldo.
- «Guardar cambios» en edición solo se habilita con cambios reales frente a la fila abierta (estado sucio): evita POST y auditoría sin motivo.
- PIN único en `configuracion.pin_emergencia_global`, válido también para el Administrador: respaldo cuando no hay correo ni internet.
- Ayuda del PIN como línea fija bajo el encabezado: los globos se recortaban por el `overflow-hidden` de la tarjeta y tapaban los campos.
- Ojos tachados por defecto en todos los campos de contraseña/PIN: el icono indica el estado actual, no la acción.
- CSS/JS por módulo, sin globalizar; en Estadísticas e Historial el color identifica el tipo de operación, con foco visible y movimiento reducido.
- El árbol de categorías es una herramienta de gestión bajo demanda, no un panel permanente que reduzca el espacio de la tabla; el filtro activo sigue visible fuera del modal.
- Categorías / Inventario: revisado y verificado (alta con CAT-XXX, edición, toggle, árbol, sincronización por stock). Funciona correctamente con CSRF y permisos. Cambios commiteados en 0f9d1f6.
- Edición de colaborador valida los datos también en el servidor; los avisos por campo del cliente mejoran la experiencia, pero nunca sustituyen validación y unicidad en la base.
- El rol Administrador es único: solo la cuenta principal conserva ese rol; creación, aprobación y edición de colaboradores solo asignan Operador de Carga o de Ventas.
- Acceso al Historial de operaciones por alcance: administración ve compras y ventas, carga ve compras y ventas ve ventas; el servidor aplica el mismo límite que el menú.
- Pregunta/respuesta de seguridad retirada de los formularios; columnas por legado, no usar.

## Aprendizajes y errores a evitar

- Si falta `config/.env`, los valores predeterminados de XAMPP son válidos (`localhost`, `root`, sin contraseña, `jv3000_db`, puerto 3306); no convertir un puerto ausente en configuración inválida.
- La copia universitaria debe probarse con su SQL y configuración en un entorno aislado; los pilotos por separado no garantizan el arranque del paquete entregado.
- `diseno.js` se carga en el `<head>`: su código de DOM debe esperar a `DOMContentLoaded`.
- El login ignora el POST si falta `btn_login` (sin error: solo vuelve a pintar la pantalla).
- PowerShell 5.1 lee los `.ps1` como ANSI: guiones con ñ o tildes necesitan BOM o escribirse en ASCII.
- Assets versionados por mtime: basta recargar y el asset servido debe traer `?v` nuevo.
- La bitácora se escribe con PHP y se verifica después (BOM, sin `Ã`/`Â`); PowerShell da falsos positivos.
- `ventas_stock_piloto`, `compras_recepcion_piloto` y `envio_correo_piloto` se niegan contra la BD real: no es un fallo.
- Los modales de Colaboradores se renderizan después de cerrar `.pagina-usuarios`; no escribir reglas de modal bajo ese ancestro, porque no se aplican. Seleccionar el propio `#modalCrear` o `.modal-usuarios`.
- El ítem «Panel de Inicio» se oculta con `$en_panel_inicio` (`index.php` sin `?url=`): si se reutiliza esta condición en el sidebar, recordar que el chip del hero también contiene el texto, así que los `Contains()` de verificación deben apuntar a `nav-ram-panel`.
- Al escribir la bitácora desde PHP, las cadenas con `\n` dentro de comillas simples no se expanden: quedan en una línea con `\n` literales. Usar concatenación con `PHP_EOL` (o comillas dobles).
- El test `pin_global_piloto.php` fija el diseño de la tarjeta del PIN (botón (?) + #modalAyudaPin + pastilla + único .pin-info): si vuelve a cambiar el diseño, actualizar también esas aserciones.
- Los formularios personalizados con `novalidate` y envío vía `jvPost` necesitan un listener submit explícito y deben mandar las claves que procesa el controlador; el modal de restablecimiento no lo tenía.
- **Las cuentas de Colaboradores NO se eliminan: solo se suspenden** (decisión del usuario, 2026-10-23). No volver a crear `Usuario::eliminar()`, `eliminar_usuario`, `confirmarEliminacion()` ni el botón de papelera; el borrado físico está en el historial de git si algún día se necesita.
- Al limpiar sesiones de prueba en `C:\xampp\tmp` usar el `sid` exacto, nunca comodines (`sess_jv*` puede llevarse ficheros ajenos).
