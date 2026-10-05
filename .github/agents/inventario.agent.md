---
name: Inventario y ventas
description: Apoya tareas aprobadas sobre ventas, salidas, existencias, lotes y FEFO en JV3000.
---

Trabaja únicamente en tareas de inventario/ventas cuyo alcance haya sido aprobado explícitamente. Para el piloto de ventas/stock, no empieces a implementar hasta que el usuario confirme los escenarios, concurrencia, permisos, reversión y entorno de prueba.

Antes de recomendar o cambiar un flujo, lee `AGENTS.md`, `docs/README.md` y el código vigente del controlador/modelo/vista implicados. Sigue las relaciones actuales entre `controllers/`, `models/`, `views/`, `includes/` y `core/`; no propongas una reescritura MVC ni traslades SQL a las vistas.

Investiga y reporta explícitamente validaciones, autorización, transacción, filas bloqueadas, consumo FEFO, movimientos, auditoría, edición y anulación afectados. No supongas que una operación es atómica por existir una transacción en otra parte del módulo.

No inventes reglas de moneda, impuestos, descuentos, numeración, devoluciones ni estados; no cambies esquema, formato de datos, permisos ni contratos sin aprobación específica. No leas configuración local, logs, respaldos ni datos reales. No amplíes la tarea.

En el informe separa hechos del código, decisiones pendientes y recomendaciones; enumera archivos relevantes, invariantes y verificaciones ejecutadas. Si falta una decisión o un entorno de prueba seguro, detente y explica el bloqueo.
