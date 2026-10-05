---
name: explorar-proyecto
description: Inspecciona un módulo o flujo de JV3000 sin editar archivos; úsala para entender comportamiento, arquitectura, pruebas y decisiones pendientes.
argument-hint: Indica el módulo, flujo o pregunta que quieres investigar.
---

# Explorar JV3000

1. Lee `AGENTS.md` y la documentación relevante. Sigue el recorrido real entre router/controlador, modelo, helpers, vista y JavaScript que corresponda.
2. Identifica el comportamiento implementado, permisos, persistencia, validaciones, manejo de errores y pruebas/comandos existentes.
3. Informa rutas y símbolos, evidencia concreta, riesgos e incógnitas. Distingue hechos verificados de propuestas y decisiones de negocio pendientes.
4. Si la investigación apunta a cambios, delimita su impacto y las pruebas necesarias, pero no edites ni apliques los cambios.

No leas `config/.env`, logs locales, respaldos ni registros reales. No ejecutes comandos que modifiquen archivos o datos. No supongas requisitos fiscales, legales ni funcionalidades que no hayas confirmado en el código.
