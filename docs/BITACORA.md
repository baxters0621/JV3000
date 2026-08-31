# BITÁCORA DE CAMBIOS — JV3000 C.A.

Historial único de actividad del sistema. Cada entrada se registra AL TERMINAR una tarea y se commitea junto con el cambio (regla obligatoria en `AGENTS.md`).

## Formato de entrada

```
[YYYY-MM-DD HH:MM] · Módulo: <módulo> · Cambio: <qué se hizo y por qué> · Archivos: <ruta(s)> · Prueba: <verificación realizada>
```

---

[2026-08-30 22:40] · Módulo: Estadísticas · Cambio: Mejoras de interfaz y validación matemática: cálculo de ganancia mediante JOIN a lotes (costo real consumido vs precio actual), indicador de carga en auto-refresh, validación de fechas en cliente, estados vacíos en gráficos y mensajes contextuales; cálculo de pct() conservado por honestidad matemática (null cuando baseline ausente). · Archivos: models/Estadistica.php, views/estadisticas/index.php, assets/modules/estadisticas/estadisticas.js, assets/modules/estadisticas/estadisticas.css · Prueba: harness transaccional con siembra de datos (ROLLBACK) confirmó ventas=250, compras=160, ganancia=200 (vs 150 anterior), pct=-36% correcto, series suman a KPIs, rango diario 24h y inválido → semana; php -l y node --check sin errores.

---

[2026-08-31 16:30] · Módulo: Categorías · Cambio: Fix modal formulario no se abría en primera carga: catAbrirForm() ahora verifica si #modalCategorias tiene clase 'show' antes de usar hidden.bs.modal; si ya está oculto, muestra #modalCat directamente. · Archivos: assets/modules/productos/productos.js (v=18), controllers/ProductosController.php (bump v=18) · Prueba: php -l y node --check sin errores; commit 5a9a81a pushed.

---

[2026-08-31 17:10] · Módulo: Categorías/UI · Cambio: Unificación del formulario de categorías con patrón global de modales. Se crearon clases compartidas .jv-modal-* (header, close, section, section-head, chip, section-title, section-sub, label, hint, footer) en diseno.css con colores por custom properties; se refactorizó modalCat y el header del listado modalCategorias en vistas, eliminando las clases duplicadas cat-* (~250 líneas) de productos.css. · Archivos: assets/css/diseno.css (v=11), views/productos/index.php, assets/modules/productos/productos.css (v=23), controllers/ProductosController.php (bump v=23), includes/diseno.php (bump v=11) · Prueba: php -l sin errores; página productos HTTP 200 con clases jv-modal-* renderizadas (header x2, section x2, chip x2, label x4, hint x3, footer) y CSS v=23 / diseno v=11 servidos; sin referencias residuales a cat-*.

---