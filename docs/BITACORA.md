# BITÁCORA DE CAMBIOS — JV3000 C.A.

Historial único de actividad del sistema. Cada entrada se registra AL TERMINAR una tarea y se commitea junto con el cambio (regla obligatoria en `AGENTS.md`).

## Formato de entrada

```
[YYYY-MM-DD HH:MM] · Módulo: <módulo> · Cambio: <qué se hizo y por qué> · Archivos: <ruta(s)> · Prueba: <verificación realizada>
```

---

[2026-08-31 14:49] · Módulo: Productos/Compras · Cambio: Fix scroll recortado en todos los modales que combinaban modal-dialog-centered + modal-dialog-scrollable (incompatibles en Bootstrap 5). Se eliminó modal-dialog-centered de modalCategorias (productos), y de los dos modales scrollables de compras (líneas 698/817). Los formularios cortos (modalCat, modalCli, etc.) mantienen solo centered sin scrollable. · Archivos: views/productos/index.php, views/compras/index.php · Prueba: php -l sin errores en ambos; count centered+scrollable = 0 en los 3 módulos; centered restante = solo en modales de formulario corto (modalCat, modalCli, etc.).

---
[2026-08-31 13:59] · Módulo: Salidas/Ventas · Cambio: Fix scroll del modal de salida recortado: el modalSalida y modalCliList combinaban Bootstrap modal-dialog-centered + modal-dialog-scrollable, lo que al tener contenido más alto que la ventana centraba verticalmente y recortaba sin poder desplazarse hasta el footer (botones CANCELAR/VISTA PREVIA). Se eliminó modal-dialog-centered de ambos (queda modal-dialog-scrollable anclado arriba con scroll interno del modal-body). · Archivos: views/salidas/index.php · Prueba: php -l sin errores; HTTP 200 con login confirmó dialog modal-lg modal-dialog-scrollable (sin centered) en modalSalida/modalCliList, count centered+scrollable = 0 y 3 headers jv-modal-header.

---
[2026-08-31 13:46] · Módulo: Salidas/Ventas · Cambio: Rediseño del módulo con patrón global de modales: se movieron modalCliList y modalCli FUERA del form de modalSalida (antes anidados → cierres de div rotos) y se refactorizó todo a clases .jv-modal-* (header rojo para salida, verde para clientes vía overrides #modalCliListHeader/#modalCliHeader) con secciones, chips, labels, hints y footers; fix bug en Cliente::procesar que leía 'accion' pero el form envía 'accion_cliente' (Editar cliente registraba uno nuevo en vez de actualizar); eliminación de dead code (procesarAccionSalida en controller, obtenerProductoBasico y LIMITE_UNIDADES en models/Salida.php, CSS muerto .cli-modal-header-jv/.cli-active-badge/.cli-inactive-badge/.header-card/.cant-badge, variables de color por .pagina-salidas) y bump salidas.css a v=6. · Archivos: views/salidas/index.php, assets/modules/salidas/salidas.css (v=6), controllers/SalidasController.php, models/Salida.php, models/Cliente.php · Prueba: php -l sin errores en los 3 PHP; HTTP 200 con login + CSRF (session cookie) confirmó jv-modal-header, salidas.css?v=6, modalSalida/modalCliList/modalCli, headers modalCliListHeader/modalCliHeader y oculto accion_cliente; balance de llaves CSS 86=86.

---

[2026-08-30 22:40] · Módulo: Estadísticas · Cambio: Mejoras de interfaz y validación matemática: cálculo de ganancia mediante JOIN a lotes (costo real consumido vs precio actual), indicador de carga en auto-refresh, validación de fechas en cliente, estados vacíos en gráficos y mensajes contextuales; cálculo de pct() conservado por honestidad matemática (null cuando baseline ausente). · Archivos: models/Estadistica.php, views/estadisticas/index.php, assets/modules/estadisticas/estadisticas.js, assets/modules/estadisticas/estadisticas.css · Prueba: harness transaccional con siembra de datos (ROLLBACK) confirmó ventas=250, compras=160, ganancia=200 (vs 150 anterior), pct=-36% correcto, series suman a KPIs, rango diario 24h y inválido → semana; php -l y node --check sin errores.

---

[2026-08-31 16:30] · Módulo: Categorías · Cambio: Fix modal formulario no se abría en primera carga: catAbrirForm() ahora verifica si #modalCategorias tiene clase 'show' antes de usar hidden.bs.modal; si ya está oculto, muestra #modalCat directamente. · Archivos: assets/modules/productos/productos.js (v=18), controllers/ProductosController.php (bump v=18) · Prueba: php -l y node --check sin errores; commit 5a9a81a pushed.

---

[2026-08-31 17:10] · Módulo: Categorías/UI · Cambio: Unificación del formulario de categorías con patrón global de modales. Se crearon clases compartidas .jv-modal-* (header, close, section, section-head, chip, section-title, section-sub, label, hint, footer) en diseno.css con colores por custom properties; se refactorizó modalCat y el header del listado modalCategorias en vistas, eliminando las clases duplicadas cat-* (~250 líneas) de productos.css. · Archivos: assets/css/diseno.css (v=11), views/productos/index.php, assets/modules/productos/productos.css (v=23), controllers/ProductosController.php (bump v=23), includes/diseno.php (bump v=11) · Prueba: php -l sin errores; página productos HTTP 200 con clases jv-modal-* renderizadas (header x2, section x2, chip x2, label x4, hint x3, footer) y CSS v=23 / diseno v=11 servidos; sin referencias residuales a cat-*.

---