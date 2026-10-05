---
name: implementar-cambio
description: Implementa un cambio JV3000 con alcance y criterios aprobados; úsala cuando el usuario haya autorizado expresamente la implementación.
argument-hint: Describe el cambio autorizado y sus criterios de aceptación.
---

# Implementar un cambio aprobado

1. Confirma que el alcance solicitado esté aprobado en la conversación y permitido por sus límites. Lee `AGENTS.md`, documentación y código afectado; busca un patrón existente y las pruebas más específicas.
2. Traza las capas y contratos involucrados. Haz cambios pequeños, conserva rutas y formatos de datos, añade pruebas para reglas afectadas y presenta los errores según los patrones existentes.
3. Detente y solicita aprobación antes de cambiar esquema/migraciones, datos, dependencias, permisos, contratos, importes, moneda, numeración o reglas fiscales. No empieces el piloto de ventas/stock hasta que se confirmen escenarios, concurrencia, permisos, reversión y entorno de pruebas.
4. No leas secretos, logs locales, respaldos ni registros reales. Usa solo pruebas sintéticas en entornos autorizados; no ejecutes `tools/validar.ps1` sin autorización para su configuración y base de datos locales.
5. Ejecuta verificaciones seguras y pertinentes, registra en `docs/BITACORA.md` los archivos y pruebas realmente realizados, y resume los resultados y limitaciones. No afirmes haber ejecutado pruebas que no corriste.

Si una decisión no tiene aprobación o falta un entorno seguro, detente en ese punto y expón qué confirmación falta; no inventes una alternativa de negocio.
