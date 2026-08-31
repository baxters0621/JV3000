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