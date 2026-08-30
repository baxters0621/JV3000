# BITÁCORA DE CAMBIOS — JV3000 C.A.

Historial único de actividad del sistema. Cada entrada se registra AL TERMINAR una tarea y se commitea junto con el cambio (regla obligatoria en `AGENTS.md`).

## Formato de entrada

```
[YYYY-MM-DD HH:MM] · Módulo: <módulo> · Cambio: <qué se hizo y por qué> · Archivos: <ruta(s)> · Prueba: <verificación realizada>
```

---

[2026-08-30 22:40] · Módulo: Estadísticas · Cambio: Mejoras de interfaz y validación matemática: cálculo de ganancia mediante JOIN a lotes (costo real consumido vs precio actual), indicador de carga en auto-refresh, validación de fechas en cliente, estados vacíos en gráficos y mensajes contextuales; cálculo de pct() conservado por honestidad matemática (null cuando baseline ausente). · Archivos: models/Estadistica.php, views/estadisticas/index.php, assets/modules/estadisticas/estadisticas.js, assets/modules/estadisticas/estadisticas.css · Prueba: harness transaccional con siembra de datos (ROLLBACK) confirmó ventas=250, compras=160, ganancia=200 (vs 150 anterior), pct=-36% correcto, series suman a KPIs, rango diario 24h y inválido → semana; php -l y node --check sin errores.

---