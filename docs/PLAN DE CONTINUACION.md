# Plan de continuación JV3000

## Estado actual

La fase de legibilidad, organización documental, correcciones de seguridad y validación básica quedó completada en el commit `68a969b`.

Validaciones ya realizadas:

- Todos los archivos PHP pasan `php -l`.
- Todo JavaScript propio no minificado pasa `node --check`.
- El diagnóstico del editor está limpio.
- Apache y MySQL responden correctamente.
- Login real probado con sesión temporal.
- Las rutas MVC principales responden `HTTP 200`.
- El endpoint AJAX de estadísticas responde `success=true`.
- Los recursos CDN de proveedores cargan correctamente.
- La guía y la documentación están organizadas en `docs/`.

## Actualización de cierre

Las tareas descritas a continuación ya fueron ejecutadas y validadas en la base de prueba. También se completaron la automatización del validador, la preparación productiva, la protección HTTP y el endurecimiento ACL de las carpetas internas. La configuración productiva apunta a `jv3000_db`; por seguridad, no se ejecutan nuevas operaciones de escritura sobre esa base sin un respaldo restaurable y confirmación previa.

## Tareas para la siguiente sesión

### 1. Pruebas funcionales con base de datos de prueba

Crear o restaurar una base de datos de prueba antes de ejecutar operaciones que escriban datos.

Probar los flujos completos:

- Registro y aprobación de usuarios.
- Inicio de sesión correcto.
- Bloqueo después de tres intentos fallidos.
- Recuperación de contraseña por pregunta de seguridad.
- Crear una categoría.
- Crear un proveedor.
- Crear o editar un producto.
- Registrar una compra.
- Recibir mercancía.
- Confirmar que el stock aumente.
- Registrar una venta.
- Confirmar consumo FEFO de lotes.
- Anular una venta y verificar restauración de stock.
- Crear una solicitud de reposición.
- Atender la solicitud desde compras.
- Probar filtros, paginación y estadísticas.
- Reimprimir una nota de entrega.

Cada prueba debe dejar la base de datos en un estado conocido y documentar el resultado en `docs/BITACORA.md`.

### 2. Revisión de seguridad

- Verificar acceso directo a `backups/`, `db/`, `models/`, `controllers/`, `views/`, `config/`, `includes/` y `core/`.
- Confirmar que las rutas protegidas redirijan al login sin sesión.
- Probar tokens CSRF válidos, ausentes y alterados.
- Revisar que los roles 1, 2 y 3 solo puedan ejecutar sus acciones permitidas.
- Confirmar que los handlers JavaScript no se rompan con nombres que incluyan comillas o HTML.
- Revisar headers de seguridad y cookies de sesión.

### 3. Revisión de interfaz y accesibilidad

- Probar escritorio y móvil.
- Revisar textos, botones, tablas, modales y estados vacíos.
- Confirmar que no existan textos con codificación dañada.
- Revisar etiquetas, foco de teclado, mensajes de error y controles deshabilitados.
- Verificar que las tablas y formularios no desborden en pantallas pequeñas.
- Confirmar que los recursos CSS, JavaScript y CDN carguen sin errores.

### 4. Automatización

Evaluar la creación de scripts de smoke test para:

- Lint PHP.
- Validación de JavaScript.
- Rutas públicas y protegidas.
- Login y sesión.
- Endpoint AJAX de estadísticas.
- Integridad de stock y lotes.
- Claves foráneas y registros huérfanos.

### 5. Preparación para despliegue

- Revisar configuración de producción.
- Separar credenciales del código fuente.
- Confirmar política de backups y restauración.
- Revisar permisos de carpetas.
- Confirmar que los errores internos no se muestren al usuario.
- Preparar una copia de seguridad antes de cada migración.
- Revisar la eliminación local pendiente de `.htaccess` antes de decidir si debe conservarse o restaurarse.

## Orden recomendado

1. Copia de seguridad.
2. Base de datos de prueba.
3. Pruebas funcionales de escritura.
4. Pruebas de seguridad.
5. Revisión responsive y accesibilidad.
6. Automatización.
7. Preparación para producción.

## Regla de trabajo

No ejecutar pruebas de compra, recepción, venta o anulación sobre la base de datos real sin crear antes un respaldo y confirmar que los datos pueden restaurarse.

Cada modificación de código debe registrarse en `docs/BITACORA.md` y validarse antes de continuar con la siguiente tarea.
