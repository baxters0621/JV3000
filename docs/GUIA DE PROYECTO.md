# Guía de Proyecto JV3000

## 1. Visión general del sistema

JV3000 es un sistema web para gestionar inventario, compras, ventas, recepciones, solicitudes de reposición, reportes y administración de usuarios. Está desarrollado en PHP con MySQL, tiene una arquitectura MVC propia y está pensado para uso interno de una empresa.

El sistema está organizado por módulos y la lógica se divide en:

- Front controller: entrada principal
- Capa de seguridad: sesión, permisos, CSRF
- Enrutador MVC: convierte URL en controlador y método
- Controladores: orquestan la lógica por módulo
- Modelos: contienen consultas y reglas del negocio
- Vistas: renderizan HTML y componentes visuales
- Base de datos: MySQL / MariaDB

---

## 2. Stack y estructura tecnológica

### Backend

- PHP 8.x
- MySQL / MariaDB
- MVC propio sin framework

### Frontend

- Bootstrap 5
- Bootstrap Icons
- Chart.js
- SweetAlert2
- JavaScript modular por módulo

### Estructura principal del proyecto

- [index.php](../index.php): entrada principal
- [init.php](../init.php): arranque global, sesi�n, seguridad y conexi�n
- [config](../config): configuraci�n de la aplicaci�n
- [core](../core): Router, Controller, Model
- [controllers](../controllers): l�gica por m�dulo
- [models](../models): consultas y reglas del negocio
- [views](../views): pantallas y plantillas
- [includes](../includes): helpers, seguridad, sidebar, ajax
- [dashboard](../dashboard): panel principal autenticado
- [login](../login): login, recuperaci�n y logout
- [db](../db): SQL portable y migraciones
- [backups](../backups): respaldos autom�ticos

### Documentación del proyecto

Los archivos Markdown se organizan en la carpeta `docs/` para mantener la raíz limpia:

- [README.md](README.md): instalaci�n, estructura general y funcionamiento b�sico.
- [GUIA DE PROYECTO.md](GUIA%20DE%20PROYECTO.md): explicaci�n completa de la arquitectura, flujo y convenciones de nombres.
- [BITACORA.md](BITACORA.md): historial obligatorio de cambios y pruebas realizadas.
- [AGENTS.md](../AGENTS.md): reglas de trabajo del agente; permanece en la ra�z porque las herramientas lo buscan all�.

### Convenciones de nombres

- [PLAN DE CONTINUACION.md](PLAN%20DE%20CONTINUACION.md): tareas pendientes y orden recomendado para la siguiente sesión.

Los nombres del código deben explicar qué representa cada dato o qué acción realiza cada función. La regla principal es preferir claridad sobre brevedad.

- PHP: usar `camelCase` para variables, parámetros y métodos; elegir nombres descriptivos en español para la lógica de negocio, por ejemplo `$datosFormulario`, `$stockDisponible` o `registrarRecepcion()`.
- JavaScript: usar `camelCase` y evitar variables de una sola letra o abreviaturas que obliguen a leer otro bloque para entenderlas, por ejemplo `passwordConfirmation` en vez de `p2`.
- Clases: usar `PascalCase`, por ejemplo `Recepcion`, `Producto` y `SolicitudesController`.
- Base de datos: conservar `snake_case`, por ejemplo `id_producto`, `fecha_solicitud` y `ultimo_intento`.
- Archivos y carpetas: nombrarlos según el módulo o responsabilidad que contienen, por ejemplo `NotaEntrega.php`, `recuperar.js` y `controllers/`.
- Abreviaturas aceptadas: `id`, `URL`, `CSRF`, `AJAX`, `SKU`, `RIF` e `IVA`, porque son términos técnicos establecidos.
- Datos compartidos entre PHP y JavaScript: usar claves descriptivas. Evitar nombres como `c0`, `c1`, `$d`, `$row` o `fmt` cuando exista un nombre que explique su contenido.
- Al renombrar una función o variable, buscar primero todos sus usos y ejecutar `php -l` o `node --check` según corresponda.

### Renombrados aplicados

Estas son algunas equivalencias reales que ya fueron aplicadas en el proyecto:

| Nombre anterior                                       | Nombre actual                                                         | Significado                                           |
| ----------------------------------------------------- | --------------------------------------------------------------------- | ----------------------------------------------------- |
| `JV_CONFIG.c0` en login                               | `JV_CONFIG.remainingLockoutSeconds`                                   | Segundos restantes del bloqueo de acceso              |
| `JV_CONFIG.c0`                                        | `JV_CONFIG.csrfToken`                                                 | Token CSRF común del layout                           |
| `JV_CONFIG.c1` en compras                             | `JV_CONFIG.taxPercentage`                                             | Porcentaje de impuesto aplicado a la compra           |
| `JV_CONFIG.c0` en salidas                             | `JV_CONFIG.movementTypeGroups`                                        | Mapa de tipos de movimiento y sus grupos              |
| `$post` en recepción                                  | `$receptionFormData`                                                  | Datos del formulario de recepción                     |
| `$post` en compras                                    | `$purchaseFormData`                                                   | Datos del formulario de compra                        |
| `$d` en proveedores                                   | `$datosProveedor`                                                     | Datos procesados del proveedor                        |
| `$d` en productos                                     | `$datosProducto`                                                      | Datos procesados del producto                         |
| `$d` en estadísticas                                  | `$statisticsData`                                                     | Resultado de la consulta estadística                  |
| `$row` en categorías                                  | `$category`                                                           | Registro de una categoría                             |
| `$row` en solicitudes                                 | `$pendingRequest` / `$processedRequest`                               | Solicitud pendiente o procesada                       |
| `$r` en historial                                     | `$auditRecord`                                                        | Registro de auditoría                                 |
| `$r` en reporte                                       | `$producto`                                                           | Producto del reporte de inventario                    |
| `$id`, `$data`, `$detalles`, `$tn` en nota de entrega | `$outgoingId`, `$previewData`, `$previewDetails`, `$movementTypeName` | Salida, preview, detalles y tipo de movimiento        |
| `d`, `fmt`, `cant` en estadísticas JS                 | `statisticsResponse`, `formatCurrency`, `quantities`                  | Respuesta, formato monetario y cantidades             |
| `datos`, `it`, `filter`, `rows` en recepción JS       | `purchaseData`, `pendingItem`, `searchValue`, `tableRows`             | Datos de compra, producto pendiente, búsqueda y filas |

Los nombres de campos de la base de datos, campos `POST`, rutas y claves de respuesta JSON se conservan cuando forman parte de un contrato entre capas. Solo se renombran identificadores internos cuando se pueden actualizar todos sus usos de forma segura.

Equivalencias añadidas en las últimas tandas:

| Nombre anterior                                             | Nombre actual                                                                                          | Significado                                                 |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------- |
| `cfg`, `qs`, `r`, `d`, `el` en utilidades JS                | `tabConfiguration`, `queryParts`, `tooltipBounds`, `responseData`, `tooltipElement`                    | Configuración, consulta, límites, respuesta y elemento DOM  |
| `q`, `d`, `el`, `cant`, `idx` en compras JS                 | `searchTerm`, `searchResponse`, `productElement`, `requestedQuantity`, `productIndex`                  | Búsqueda, respuesta, producto, cantidad e índice            |
| `q`, `doc`, `m`, `cant`, `stockDisp`, `idx` en salidas JS   | `searchTerm`, `clientDocument`, `documentMatch`, `requestedQuantity`, `availableStock`, `productIndex` | Búsqueda, documento, coincidencia, cantidad, stock e índice |
| `r`, `el`, `btn`, `pw`, `ph` en dashboard JS                | `tooltipBounds`, `tooltipElement`, `alertButton`, `panelWidth`, `panelHeight`                          | Límites, elementos y dimensiones del panel                  |
| `$id`, `$data`, `$detalles`, `$tn` en nota                  | `$outgoingId`, `$previewData`, `$previewDetails`, `$movementTypeName`                                  | Salida, preview, detalles y tipo de movimiento              |
| `$input`, `$row`, `$respuesta`, `$new_pass` en recuperación | `$recoveryInput`, `$userAccount`, `$securityAnswer`, `$newPassword`                                    | Entrada, cuenta, respuesta y nueva contraseña               |
| `cfg`, `seg`, `qs`, `r`, `d`, `el` en utilidades JS         | `tabConfiguration`, `pathSegments`, `queryParts`, `tooltipBounds`, `responseData`, `tooltipElement`    | Configuración, ruta, parámetros, límites y respuesta        |
| `r`, `el`, `btn`, `pw`, `ph` en dashboard JS                | `tooltipBounds`, `tooltipElement`, `alertButton`, `panelWidth`, `panelHeight`                          | Límites, elementos y dimensiones visuales                   |

---

## 3. Cómo arranca la aplicación

La app entra por [index.php](../index.php).

Ese archivo hace lo siguiente:

1. Incluye [init.php](../init.php)
2. Carga configuración global
3. Registra un autoload para clases de modelo y core
4. Lee la URL en `?url=...`
5. Delega al router para enviar la petición al controlador correcto

Ejemplos:

- `index.php?url=solicitudes`
- `index.php?url=compras`
- `index.php?url=salidas/crear`

Si la URL está vacía, redirige al dashboard.

---

## 4. El arranque global: init.php

[init.php](../init.php) es el archivo más importante del sistema porque encapsula la configuración de seguridad y entorno.

### Funciones principales

- Define la zona horaria
- Inicia sesión con política estricta
- Configura cookies de sesión seguras
- Añade headers de seguridad:
  - CSP
  - X-Frame-Options
  - X-Content-Type-Options
  - Referrer-Policy
  - Permissions-Policy
- Desactiva errores en pantalla y activa manejo robusto
- Carga configuración, Database, Security y helpers
- Conecta a la base de datos
- Si la base no existe, intenta restaurar backups o instalar desde SQL portable
- Ejecuta migraciones automáticas
- Valida sesión para rutas autenticadas
- Sanitiza `GET`/`POST`
- Valida CSRF
- Redirige errores fatales de una forma controlada

### Importante

El sistema no deja que cualquier persona acceda sin autenticación. Antes de la pantalla principal, valida sesión y permisos.

---

## 5. Seguridad del sistema

La seguridad está centralizada en [includes/Security.php](../includes/Security.php).

### Roles del sistema

- 1 = Administrador
- 2 = Operador de Carga
- 3 = Operador de Ventas

### Métodos clave

- `validateSession()`: valida que exista sesión, usuario activo, aprobado y con IP válida
- `sanitizeGlobals()`: limpia inputs globales
- `generateToken()`: crea CSRF token
- `validateCSRF()`: valida el token en requests POST
- `esAdmin()`
- `puedeCargar()`
- `puedeVender()`
- `soloAdmin()`
- `verificarPermisoCarga()`
- `verificarPermisoVenta()`

### Qué hace esto en la práctica

Cada acción sensible exige:

- usuario autenticado
- rol adecuado
- CSRF válido
- validación de sesión y estado del usuario

Esto hace que la app esté protegida frente a errores habituales de seguridad web.

---

## 6. Enrutamiento MVC

La navegación no usa rutas de archivos por cada pantalla; en lugar de eso, usa un router.

Archivo clave: [core/Router.php](../core/Router.php)

### Cómo funciona

La URL se recibe como:

- `index.php?url=solicitudes`
- `index.php?url=compras`
- `index.php?url=salidas/crear/5`

Se procesa así:

1. Se divide por `/`
2. El primer segmento se convierte a CamelCase
3. Se busca el controlador
4. Se instancia la clase
5. Se ejecuta el método correspondiente
6. Si no existe, responde 404

Ejemplo:

- `solicitudes` → `SolicitudesController`
- `solicitudes/cancelar/5` → `SolicitudesController::cancelar(5)`

---

## 7. Capa base de controladores

Archivo: [core/Controller.php](../core/Controller.php)

Es la clase base para todos los controladores.

### Métodos importantes

- `view()`: renderiza una vista con layout principal
- `renderRaw()`: renderiza vista sin layout
- `json()`: devuelve respuestas JSON para AJAX
- `redirect()`: redirige a otra ruta interna
- `flash()`: guarda mensajes temporales en sesión

### Patrón de trabajo

Los controladores no hacen SQL directo. Lo que hacen es:

- validar permisos
- recibir datos
- llamar al modelo
- preparar variables para la vista
- devolver JSON o redireccionar

---

## 8. Base de datos centralizada

Archivo: [includes/Database.php](../includes/Database.php)

Es un singleton que encapsula toda la conexión con MySQL.

### Características

- conexión única a la base durante toda la app
- prepared statements
- métodos `fetchOne()`, `fetchAll()`, `insert()`, `update()`
- transacciones con `begin()`, `commit()`, `rollback()`
- manejo centralizado de errores SQL

### ¿Por qué importa?

Porque la lógica del negocio no se conecta directamente a la base en cada archivo. Todos los módulos dependen de esta capa y eso hace el código más limpio, seguro y mantenible.

---

## 9. Cómo funciona el flujo general del negocio

La lógica principal del sistema es la siguiente:

1. El usuario ingresa y se autentica
2. El sistema valida permisos por rol
3. El usuario accede a un módulo
4. El controlador carga datos del modelo
5. El modelo consulta la base
6. La vista muestra la información
7. Si se hace una operación, el flujo repite el patrón

Esto aplica para compras, ventas, recepciones, inventario y solicitudes.

---

## 10. Módulo de compras

Archivo principal:

- [controllers/ComprasController.php](../controllers/ComprasController.php)
- [models/Compra.php](../models/Compra.php)
- [views/compras/index.php](../views/compras/index.php)

### Flujo real

Cuando se registra una compra:

- se valida el proveedor
- se valida que no exista otra factura duplicada para ese proveedor
- se validan productos, cantidades y precios
- se calcula subtotal + IVA + total
- se valida crédito si aplica
- se registra la cabecera de la compra
- se registran los detalles de cada producto
- si la compra viene de una solicitud, la solicitud pasa a “Atendida”
- se marca el estado de pago como Pagada o Pendiente

### Importante

La compra no mueve el inventario directamente. Eso queda separado para la recepción.

---

## 11. Módulo de recepción

Archivos relevantes:

- [controllers/RecepcionController.php](../controllers/RecepcionController.php)
- [models/Recepcion.php](../models/Recepcion.php)
- [views/recepcion/index.php](../views/recepcion/index.php)

### Qué hace

La recepción confirma que la mercadería física llegó.

El flujo real es:

- se revisa la compra pendiente
- se comparan cantidades recibidas contra pendientes
- se valida que no se reciba más de lo esperado
- se actualiza el inventario
- se crean o actualizan lotes
- se marca la compra como parcialmente o totalmente recibida
- se actualiza el estado de recepción

### Regla de negocio clave

La compra crea la obligación de compra; la recepción agrega el stock real.

---

## 12. Módulo de ventas / salidas

Archivos relevantes:

- [controllers/SalidasController.php](../controllers/SalidasController.php)
- [models/Salida.php](../models/Salida.php)
- [views/salidas/index.php](../views/salidas/index.php)

### Flujo real

Cuando se registra una salida:

- se valida permiso de venta
- se comprueba la disponibilidad de stock
- se revisa el producto y la cantidad
- se registra la salida
- se descuenta el inventario
- se registra el movimiento correspondiente
- se puede generar una nota de entrega asociada

### Relación con el negocio

La salida es la operación que consume el inventario que ya fue recibido y validado.

---

## 13. Módulo de solicitudes de reposición

Archivos relevantes:

- [controllers/SolicitudesController.php](../controllers/SolicitudesController.php)
- [models/Solicitud.php](../models/Solicitud.php)
- [views/solicitudes/index.php](../views/solicitudes/index.php)

### Qué hacen

Las solicitudes de reposición permiten:

- pedir productos que faltan o se necesitan
- evitar compras duplicadas y desordenadas
- poner la necesidad en una cola para atenderla luego

### Flujo

- un usuario crea una solicitud con productos y cantidades
- la solicitud queda pendiente
- desde compras se puede atender la solicitud
- la solicitud cambia a “Atendida”
- la compra queda asociada a esa solicitud

Esto ayuda a controlar la reposición antes de comprar.

---

## 14. Módulo de productos y categorías

Archivos relevantes:

- [controllers/ProductosController.php](../controllers/ProductosController.php)
- [models/Producto.php](../models/Producto.php)
- [controllers/CategoriasController.php](../controllers/CategoriasController.php)
- [models/Categoria.php](../models/Categoria.php)

### Funciones

- registrar productos
- clasificar por categorías
- controlar SKU, precios y costo
- gestionar stock y lotes
- revisar alertas de inventario
- validar vencimientos y existencias mínimas

---

## 15. Módulo de proveedores

Archivos relevantes:

- [controllers/ProveedoresController.php](../controllers/ProveedoresController.php)
- [models/Proveedor.php](../models/Proveedor.php)

### Funciones

- registrar proveedores
- guardar datos fiscales y de contacto
- administrar condiciones de pago y crédito
- controlar límite de crédito
- vincular compras con proveedores

Este módulo es clave porque la compra depende del proveedor escogido y del crédito disponible.

---

## 16. Dashboard y reportes

Archivos relevantes:

- [dashboard/index.php](../dashboard/index.php)
- [controllers/EstadisticasController.php](../controllers/EstadisticasController.php)
- [models/Estadistica.php](../models/Estadistica.php)

### Qué muestra

- KPIs del negocio
- total de compras
- salidas o ventas
- stock crítico
- ventas por período
- Estados financieros o tendencias
- actividad reciente

Es la capa operativa del sistema para decisiones rápidas.

---

## 17. AJAX y búsquedas rápidas

La app usa endpoints AJAX para facilitar la operación sin recargar la pantalla.

Carpetas relevantes:

- [includes/ajax](../includes/ajax)

### Ejemplos

- buscar clientes
- buscar productos
- buscar proveedores
- cargar alertas

Esto permite que el sistema sea más ágil y parecido a una app moderna.

---

## 18. Validación de formularios y CSRF

El sistema valida formularios y tokens de seguridad.

### CSRF

- cada formulario usa un token generado por `Security::generateToken()`
- el sistema lo valida en `Security::validateCSRF()`
- si falla, la app bloquea la operación y redirige

### Por qué es importante

Porque evita que un tercero emule una acción desde otro sitio o formulario ajeno.

---

## 19. Flash messages

El sistema usa mensajes temporales en sesión para informar el resultado de una acción.

### Ejemplo

- compra registrada
- solicitud cancelada
- producto eliminado
- acceso denegado

Se guardan con `flash()` y se leen con `consumeFlash()` para mostrar el mensaje una sola vez.

---

## 20. Cómo leer el proyecto correctamente

La forma más efectiva de entender este sistema es esta:

1. [index.php](../index.php)
2. [init.php](../init.php)
3. [includes/Security.php](../includes)/Security.php)
4. [core/Router.php](../core)/Router.php)
5. [core/Controller.php](../core)/Controller.php)
6. [includes/Database.php](../includes)/Database.php)
7. Un controlador real (por ejemplo [controllers/ComprasController.php](../controllers)/ComprasController.php))
8. Su modelo asociado (por ejemplo [models/Compra.php](../models)/Compra.php))
9. La vista correspondiente

Con ese recorrido ya se entiende la lógica global del sistema.

---

## 21. Resumen ejecutivo

JV3000 es un sistema de gestión empresarial orientado a inventario y compras/ventas. Su lógica central es:

- la compra registra el compromiso comercial
- la recepción confirma la entrada real del producto
- la salida consume el stock
- la seguridad limita acciones por rol
- la base de datos centraliza toda la operación
- la arquitectura MVC facilita mantenimiento y orden

Es un sistema pensado para operación real de negocio, no solo para mostrar información.

---

## 22. Recomendación final

Si quieres mantenerte productivo con este proyecto, debes trabajar siempre con esta mentalidad:

- sesión y permisos primero
- luego la ruta
- luego el controlador
- luego el modelo
- luego la base de datos
- luego la vista

Es decir: la lógica del negocio siempre corre a través de la capa de datos y la validación de seguridad, no en la vista ni en el HTML.

---

## 23. Conclusión

Este proyecto está bien estructurado para ser una aplicación interna de gestión operativa. Tiene una lógica clara, separada por módulos, con seguridad, validaciones, permisos y flujos de negocio definidos.

El punto clave para entenderlo es reconocer que el sistema no es solo “pantallas”; es una cadena de decisión:

URL → controlador → permisos → modelo → base de datos → vista → respuesta

Y esa cadena es la que define el funcionamiento real de JV3000.

## Continuación pendiente

Para la siguiente sesión queda como fase separada:

- Crear pruebas automatizadas para los flujos de login, compras, recepción, ventas FEFO, solicitudes y recuperación de contraseña.
- Ejecutar pruebas funcionales de escritura con una base de datos de prueba, sin modificar los datos reales.
- Revisar accesibilidad y comportamiento visual en escritorio y móvil.
- Preparar una configuración de despliegue y respaldos para producción.
