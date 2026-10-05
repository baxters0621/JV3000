---
name: transacciones-inventario
description: Procedimiento para analizar o implementar cambios aprobados que afectan ventas, existencias, lotes, FEFO, recepción, edición o anulación en JV3000.
---

# Cambios transaccionales de inventario en JV3000

Activa este procedimiento cuando una tarea pueda crear, consumir, restaurar o ajustar stock, lotes, movimientos de inventario, ventas o recepciones.

## Procedimiento

1. Lee `AGENTS.md`, `docs/README.md` y el flujo vigente que toca la tarea. Sigue la llamada desde controlador a modelo y helpers hasta las escrituras SQL, y revisa las vistas/JavaScript solo si afectan el contrato o los permisos.
2. Antes de cambiar código, enumera el estado inicial y final esperado para cabecera, detalle, stock agregado, lotes, movimiento y auditoría. Separa comportamiento confirmado de decisiones pendientes. No presupongas que una venta crea una factura fiscal, reserva stock o admite devoluciones.
3. Revisa el límite y toda salida de la transacción. Comprueba qué filas se bloquean y en qué orden, si la validación final ocurre dentro del bloqueo, cómo se revierte cada escritura y qué pasa ante reintento, doble envío, edición, anulación o fallo parcial.
4. Para ventas con lotes, inspecciona la implementación de disponibilidad y `consumirLotes`; confirma que la deducción usa FEFO y que el stock agregado, cantidades de lote y movimiento se actualizan coherentemente. Verifica la ruta sin lotes y las variantes de movimiento solo cuando correspondan a la tarea.
5. Conserva las comprobaciones servidoras de permisos, sesión y CSRF. No uses la validación de interfaz como autorización.
6. Añade pruebas de reglas de dominio para los casos y límites acordados; añade integración con datos sintéticos únicamente en una base temporal autorizada. Comprueba rollback, concurrencia y duplicados relevantes. No uses una base de datos real ni producción.
7. Antes de implementar, detente y pide aprobación específica si el cambio exige esquema/migraciones, nueva política de importes/impuestos/moneda, numeración, reserva/devolución, estados, permisos o compatibilidad distinta.
8. Ejecuta solo las verificaciones seguras documentadas para el cambio; no ejecutes `tools/validar.ps1` sin autorización, pues usa configuración, base de datos y HTTP locales. Registra el cambio y pruebas reales en `docs/BITACORA.md`.

## Informe requerido

Indica el alcance autorizado, archivos y flujo inspeccionado, invariantes probadas, escenarios omitidos y motivo, comandos ejecutados con resultados, y decisiones que siguen pendientes. No afirmes que una transacción garantiza atomicidad si no inspeccionaste todas las escrituras involucradas.
