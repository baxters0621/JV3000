# BITÁCORA DE CAMBIOS — JV3000 C.A.

Historial único de actividad del sistema. Cada entrada se registra AL TERMINAR una tarea y se commitea junto con el cambio (regla obligatoria en `AGENTS.md`).

## Formato de entrada

```
[YYYY-MM-DD HH:MM] · Módulo: <módulo> · Cambio: <qué se hizo y por qué> · Archivos: <ruta(s)> · Prueba: <verificación realizada>
```

---

## Registro

[2026-08-13 15:36] · Módulo: Infraestructura MVC · Cambio: Respaldo pre-optimización — tag git `pre-optimizacion-v1` subido a GitHub y dump completo de la BD en backups/. · Archivos: `backups/jv3000_db_2026-08-13_153655.sql`, tag `pre-optimizacion-v1` · Prueba: dump verificado (20 tablas, 6 INSERTs, 30 KB); `git status` limpio.

[2026-08-13 15:38] · Módulo: Reglas del agente · Cambio: Se agrega la sección "Bitácora de cambios (OBLIGATORIA)" a AGENTS.md y se crea BITACORA.md como historial único de actividad del sistema. · Archivos: `AGENTS.md`, `BITACORA.md` · Prueba: regla documentada; formato de entrada definido.

[2026-08-13 15:40] · Módulo: Migración MVC · Cambio: Migración completa de módulos legacy a arquitectura MVC (Router/Controller/Model/Vista) — 11 controladores, 11 modelos, 13 vistas; front controller `index.php?url=...`. · Archivos: `core/*`, `controllers/*`, `models/*`, `views/*`, `index.php`, `config/config.php` · Prueba: `php -l` sin errores, smoke test HTTP de 10 rutas + POSTs + AJAX (commit `48e1e37`).

[2026-08-13 16:00] · Módulo: Dashboard · Cambio: Se reemplazan los enlaces legacy (dashboard/módulos/...) por rutas MVC en el panel y en el JS del dashboard, para que los accesos directos a productos/salidas/compras funcionen tras la migración. · Archivos: `dashboard/index.php`, `assets/dashboard/index.js` · Prueba: smoke test HTTP de rutas referenciadas (productos/salidas/compras) devuelven 200.

[2026-08-13 16:05] · Módulo: Legacy · Cambio: Los módulos legacy (módulos/*.php), su lógica de estadísticas y el AJAX de estadísticas se mueven a la carpeta `legacy/` como respaldo (decisión del usuario: mover y mantener). Se conservan activos los AJAX usados por el dashboard y el toolbox. · Archivos: `legacy/modules/*`, `legacy/includes/estadisticas_logic.php`, `legacy/includes/ajax/estadisticas_ajax.php` · Prueba: `php -l` de archivos movidos; smoke test HTTP de 11 rutas MVC = 200.

[2026-08-13 16:10] · Módulo: Helpers · Cambio: La función `jv_sello()` (sello ▲/▼ de comparación de porcentajes) se unifica: antes estaba duplicada en la vista de estadísticas y en el módulo legacy. Ahora vive en `includes/helpers.php` como helper global con docblock. · Archivos: `includes/helpers.php`, `views/estadisticas/index.php`, `legacy/modules/estadisticas.php` · Prueba: `php -l` sin errores; `index.php?url=estadisticas` devuelve 200.

[2026-08-13 16:20] · Módulo: JS compartido · Cambio: Se crea `assets/js/tooltips.js` con el tooltip global (mostrarTip/posicionarTip/ocultarTip) que antes estaba duplicado en 8 módulos JS; se eliminan las 8 copias locales y se carga el único archivo en el layout MVC y en la nota imprimible. También se unifican `limpiarErrores()`/`marcarError()` (duplicadas en 5 módulos) moviéndolas a `diseno.js`. · Archivos: `assets/js/tooltips.js`, `assets/js/diseno.js`, `assets/modules/*/*.js` (8 módulos), `includes/diseno.php`, `views/preview_factura/nota.php` · Prueba: `node --check` OK en los 10 JS; sin referencias huérfanas a los tooltips locales; smoke test HTTP = 200 y `?v=5` servido en todas las rutas.

[2026-08-13 16:40] · Módulo: Documentación del código · Cambio: Se añaden docblocks detallados en español a toda la capa PHP del proyecto (core/, controllers/, models/, includes/helpers.php) y comentarios de sección en `assets/js/diseno.js`, para que cada clase y método explique qué hace y por qué. Solo se insertaron comentarios; ninguna línea de lógica fue modificada. · Archivos: `core/*.php` (3), `controllers/*.php` (11), `models/*.php` (11), `includes/helpers.php`, `assets/js/diseno.js` · Prueba: `php -l` sin errores en los 26 archivos PHP; `node --check` OK en diseno.js; `git diff` confirma que solo se eliminaron líneas de comentario `//` (convertidas a docblocks); smoke test HTTP de 10 rutas = 200.

[2026-08-13 16:55] · Módulo: Documentación del proyecto · Cambio: Se actualiza README.md con la arquitectura MVC actual, la estructura de carpetas (core/controllers/models/views, legacy/, BITACORA.md) y la instalación mediante el auto-instalador `init.php` con `db/jv3000_portable_v4.sql`. · Archivos: `README.md` · Prueba: verificación de que `config/config.php`, `init.php` y `db/jv3000_portable_v4.sql` existen; referencias corregidas (antes apuntaban a `db/schema.sql` y `includes/config.php` inexistentes).

---

## Plantilla para futuras entradas

Copiar la línea de abajo al final de la sección Registro, completar y borrar esta plantilla cuando ya exista al menos una entrada real.

```
[YYYY-MM-DD HH:MM] · Módulo: · Cambio: · Archivos: · Prueba:
```