---
name: diagnosticar-fallo
description: Diagnostica fallos de JV3000 con evidencia de código y datos sintéticos, sin editar archivos ni tocar la base de datos.
argument-hint: Pega el error redactado y describe los pasos para reproducirlo.
---

# Diagnosticar JV3000 sin modificar datos

1. Lee `AGENTS.md` y localiza el flujo implicado desde la ruta/controlador hasta modelo, helpers, vista o JavaScript.
2. Separa causa demostrada, hipótesis y evidencia faltante. Pide un error redactado y un caso mínimo sintético si falta contexto.
3. Propón la menor verificación no destructiva que pueda confirmar el diagnóstico. No la ejecutes si lee datos privados, modifica archivos/datos, llama a producción o usa una base no autorizada.
4. Devuelve la evidencia, rutas y líneas relevantes, causas probables ordenadas, próximos pasos seguros y decisiones de negocio necesarias. No implementes el arreglo.

No leas `config/.env`, logs locales, respaldos, credenciales ni datos reales. No ejecutes `tools/validar.ps1` ni comandos que escriban en la base de datos. No atribuyas una obligación fiscal/legal sin fuente y confirmación del negocio.
