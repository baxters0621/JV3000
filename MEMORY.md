# MEMORY.md — Memoria de JV3000

Memoria del proyecto entre sesiones. Máximo ~50 líneas: resume o elimina lo que ya no aporte.
Sin datos sensibles (claves, tokens, PIN).

## Estado actual

- Instalación automática en equipo nuevo verificada de punta a punta (`init.php` + `db/jv3000_portable_v6.sql`).
- Validación global en verde: `tools/validar.ps1` (lint PHP/JS, rutas 403, integridad BD, ACL, comportamiento global).
- 9 módulos a HTTP 200 con sesión; tests en verde: `pin_global_piloto` (56 aserciones), `flash_auto_cierre`, `salidas_captura_duplicados`.
- PIN de emergencia global fijado por la interfaz y probado E2E (recuperación completa y bloqueo a los 3 intentos); el valor lo cambia el administrador desde Colaboradores.
- Interfaz del PIN pulida: ojos tachados por defecto, `.alert-lectura` en modales, ayuda como línea fija y flash que se auto-cierra a los 4 s.
- Colaboradores: modales de edición y restablecimiento refinados con jerarquía visual, responsive y mensajes accesibles; la edición valida usuario/correo/duplicados/rol en cliente y servidor, y el restablecimiento ya envía la acción y los campos esperados por el controlador.
- Encabezado del dashboard reorganizado en dos bandas: identidad arriba y, abajo, chip de ubicación en naranja a la izquierda + fecha/hora/campana/usuario a la derecha. El ítem «Panel de Inicio» del sidebar desaparece solo en el panel y reaparece en el resto de páginas.
- Tarjeta del PIN compartido (Colaboradores) rediseñada: cabecera violeta con pastilla de estado, botón (?) de 52 px que abre el modal #modalAyudaPin (el texto .pin-info vive ahí, ya no en la tarjeta), PIN vigente en panel centrado agrupado 3+3 vía `chunk_split` y formulario .pin-form centrado (letter-spacing .45em + padding-left .45em). La normalización/validación viven en usuarios.js sin cambios.

## Decisiones (y por qué)

- PIN único en `configuracion.pin_emergencia_global`, válido también para el Administrador: respaldo cuando no hay correo ni internet.
- Ayuda del PIN como línea fija bajo el encabezado: los globos se recortaban por el `overflow-hidden` de la tarjeta y tapaban los campos.
- Ojos tachados por defecto en todos los campos de contraseña/PIN: el icono indica el estado actual, no la acción.
- CSS y JS por módulo, sin globalizar (decisión explícita del usuario).
- Edición de colaborador valida los datos también en el servidor; los avisos por campo del cliente mejoran la experiencia, pero nunca sustituyen validación y unicidad en la base.
- Pregunta/respuesta de seguridad retirada de los formularios; columnas por legado, no usar.
- `MEMORY.md` guarda solo estado y decisiones; el historial lineal sigue siendo `docs/BITACORA.md`.

## Aprendizajes y errores a evitar

- `diseno.js` se carga en el `<head>`: su código de DOM debe esperar a `DOMContentLoaded`.
- El login ignora el POST si falta `btn_login` (sin error: solo vuelve a pintar la pantalla).
- PowerShell 5.1 lee los `.ps1` como ANSI: guiones con ñ o tildes necesitan BOM o escribirse en ASCII.
- Assets versionados por mtime: basta recargar y el asset servido debe traer `?v` nuevo.
- La bitácora se escribe con PHP y se verifica después (BOM, sin `Ã`/`Â`); PowerShell da falsos positivos.
- `ventas_stock_piloto`, `compras_recepcion_piloto` y `envio_correo_piloto` se niegan contra la BD real: no es un fallo.
- La paginación del listado de Colaboradores no llevaba `class="pagination-jv"`: salía como enlaces sueltos sin estilo.
- El ítem «Panel de Inicio» se oculta con `$en_panel_inicio` (`index.php` sin `?url=`): si se reutiliza esta condición en el sidebar, recordar que el chip del hero también contiene el texto, así que los `Contains()` de verificación deben apuntar a `nav-ram-panel`.
- Al escribir la bitácora desde PHP, las cadenas con `\n` dentro de comillas simples no se expanden: quedan en una línea con `\n` literales. Usar concatenación con `PHP_EOL` (o comillas dobles).
- El test `pin_global_piloto.php` fija el diseño de la tarjeta del PIN (botón (?) + #modalAyudaPin + pastilla + único .pin-info): si vuelve a cambiar el diseño, actualizar también esas aserciones.
- Los formularios personalizados con `novalidate` y envío vía `jvPost` necesitan un listener submit explícito y deben mandar las claves que procesa el controlador; el modal de restablecimiento no lo tenía.
- **Las cuentas de Colaboradores NO se eliminan: solo se suspenden** (decisión del usuario, 2026-10-23). No volver a crear `Usuario::eliminar()`, `eliminar_usuario`, `confirmarEliminacion()` ni el botón de papelera; el borrado físico está en el historial de git si algún día se necesita.
- Al limpiar sesiones de prueba en `C:\xampp\tmp` usar el `sid` exacto, nunca comodines (`sess_jv*` puede llevarse ficheros ajenos).
- Skills ya instaladas a nivel global (Starter Pack completo): `find-skills`, `grill-me`, `frontend-design`, `web-design-guidelines`, `systematic-debugging`.

## Próximos pasos

- Cambiar el PIN provisional por uno definitivo desde Colaboradores.
- Pruebas visuales en navegador: ojo de contraseña, tamaño de letra de los modales, auto-cierre del flash.
- Instalar skills puntuales con `npx skills add` cuando haga falta (`npx skills update` para mantenerlas al día).
