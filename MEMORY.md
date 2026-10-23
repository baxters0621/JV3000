# MEMORY.md — Memoria de JV3000

Memoria del proyecto entre sesiones. Máximo ~50 líneas: resume o elimina lo que ya no aporte.
Sin datos sensibles (claves, tokens, PIN).

## Estado actual

- Instalación automática en equipo nuevo verificada de punta a punta (`init.php` + `db/jv3000_portable_v6.sql`).
- Validación global en verde: `tools/validar.ps1` (lint PHP/JS, rutas 403, integridad BD, ACL, comportamiento global).
- 9 módulos a HTTP 200 con sesión; tests en verde: `pin_global_piloto` (54 aserciones), `flash_auto_cierre`, `salidas_captura_duplicados`.
- PIN de emergencia global fijado por la interfaz y probado E2E (recuperación completa y bloqueo a los 3 intentos); el valor lo cambia el administrador desde Colaboradores.
- Interfaz del PIN pulida: ojos tachados por defecto, `.alert-lectura` en modales, ayuda como línea fija y flash que se auto-cierra a los 4 s.
- Colaboradores revisado con las guías de interfaz: accesibilidad completa (aria-label/aria-pressed/role=status/scope/aria-describedby), paginación con `.pagination-jv` y botones, y respeto a `prefers-reduced-motion`.

## Decisiones (y por qué)

- PIN único en `configuracion.pin_emergencia_global`, válido también para el Administrador: respaldo cuando no hay correo ni internet.
- Ayuda del PIN como línea fija bajo el encabezado: los globos se recortaban por el `overflow-hidden` de la tarjeta y tapaban los campos.
- Ojos tachados por defecto en todos los campos de contraseña/PIN: el icono indica el estado actual, no la acción.
- CSS y JS por módulo, sin globalizar (decisión explícita del usuario).
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
- Al limpiar sesiones de prueba en `C:\xampp\tmp` usar el `sid` exacto, nunca comodines (`sess_jv*` puede llevarse ficheros ajenos).
- Skills ya instaladas a nivel global (Starter Pack completo): `find-skills`, `grill-me`, `frontend-design`, `web-design-guidelines`, `systematic-debugging`.

## Próximos pasos

- Cambiar el PIN provisional por uno definitivo desde Colaboradores.
- Pruebas visuales en navegador: ojo de contraseña, tamaño de letra de los modales, auto-cierre del flash.
- Instalar skills puntuales con `npx skills add` cuando haga falta (`npx skills update` para mantenerlas al día).
