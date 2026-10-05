# Instrucciones complementarias para Copilot

Antes de trabajar en JV3000, lee el [`AGENTS.md`](../AGENTS.md), que es la fuente autoritativa de las convenciones, permisos, arquitectura actual y verificaciones. No copies ni mantengas aquí una segunda versión de esas reglas.

Respeta el alcance aprobado por el usuario. No inicies el piloto funcional de ventas/stock ni cambios de compras o facturación sin aprobación explícita de su alcance. Antes de proponer cambios de esquema, migraciones, datos, contratos, importes o reglas fiscales, detente y solicita aprobación; no infieras obligaciones legales o de negocio.

No leas ni incluyas `config/.env`, credenciales, logs locales, respaldos ni registros reales de clientes, ventas, compras o pagos. No ejecutes `tools/validar.ps1` sin autorización para usar su base de datos y configuración local. Usa solo datos sintéticos en un entorno de pruebas autorizado.

Para reutilizar procedimientos, invoca las skills `/explorar-proyecto`, `/implementar-cambio`, `/diagnosticar-fallo` o `/transacciones-inventario` en el chat de Copilot. Para una revisión, selecciona el agente **Revisor de cambios**; para apoyo específico de dominio, selecciona **Inventario y ventas**. Proporciona el cambio y el diff pertinentes. Estas personalizaciones están en `.github/agents/` y `.agents/skills/`; no se usan archivos `.prompt.md`, que no se cargan en sesiones Agent Host.

Si la extensión oficial de Copilot no está disponible, informa la limitación: la existencia de estos archivos no demuestra que el host los haya descubierto o invocado.
