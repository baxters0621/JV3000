# JV3000 C.A. - Sistema Web para la Gestión de Inventario, Compras y Ventas

Sistema web para control de inventario, compras, ventas y administración
de usuarios de JV3000 C.A. Desarrollado en PHP 8.2.12 + MySQL (MariaDB) + Bootstrap,
bajo arquitectura **MVC**.

## Stack tecnológico

- **Backend:** PHP 8.2.12 (MVC propio: `core/`)
- **Base de datos:** MySQL / MariaDB
- **Frontend:** Bootstrap 5 + Bootstrap Icons + Chart.js + SweetAlert2
- **Servidor:** Apache (XAMPP)

## Características

- Control de inventario con categorías y productos
- Gestión de compras y ventas (salidas) con recepción de mercancía (FEFO)
- Emisión de Nota de Entrega (imprimible)
- Dashboard con KPIs y gráficos en tiempo real
- Módulo de estadísticas con proyecciones
- Control de usuarios con roles (Admin, Operador de Ventas, Operador de Carga)
- Autenticación con pregunta de seguridad y recuperación de contraseña
- Auditoría de eventos
- Protección CSRF en todos los POST y validación de sesión por IP

## Estructura del proyecto

```
index.php                 Front controller (rutas: index.php?url=controlador/accion/param)
config/                   Configuración de la app y la BD
core/                     Base MVC: Router, Controller, Model
controllers/              Controladores (uno por módulo)
models/                   Modelos (lógica de datos)
views/                    Vistas + layout principal (layouts/main.php)
includes/                 Helpers, diseno.php, sidebar.php, AJAX activos
assets/                   CSS, JS compartidos (diseno.js, tooltips.js) y JS por módulo
dashboard/                Panel principal (login tras autenticación)
login/                    Login y recuperación de contraseña
db/                       Esquema portátil de instalación limpia
backups/                  Respaldos de la base de datos
docs/                     Documentación del proyecto y bitácora de cambios
```

## Instalación y acceso desde una red local

La instalación, configuración y base de datos pertenecen al **PC servidor**.
Los demás equipos son clientes que abren JV3000 en un navegador; no instalan
MySQL ni configuran una base local.

Requisitos confirmados por el arranque: PHP 8.2 o superior y la extensión
`mysqli`, además de Apache y un servidor MySQL/MariaDB accesible. No está
verificada una versión mínima de MySQL/MariaDB ni de Apache/XAMPP.

`config/.env.example` es solo una plantilla. La configuración efectiva
`config/.env` es local al PC servidor, está excluida de Git y no debe copiarse
ni compartirse. No uses **Instalar sistema** para recuperar datos existentes:
la ruta de instalación nueva es solo para un destino confirmado como ausente o
vacío y requiere confirmación explícita. JV3000 no restaura respaldos ni aplica
migraciones durante el arranque normal.

## Orden operativo del sistema

El menú lateral sigue las fases reales de trabajo. Este es el procedimiento estándar:

### Fase 1 — Configuración inicial (solo la primera vez, Administrador)
1. **Categorías** → crear las clasificaciones de productos
2. **Proveedores** → registrar proveedores con su RIF y condiciones de crédito
3. **Usuarios** → crear los operadores y asignarles rol (2 = Carga, 3 = Ventas)

### Fase 2 — Ciclo diario de abastecimiento (Operador de Carga)
4. **Compras** → en el módulo de Compras viven también las **Solicitudes de Reposición** que nacen desde Ventas (columna superior "Solicitudes Pendientes", con ATENDER/CANCELAR) y la **Recepción de Mercancía** (tarjetas "Compras Pendientes de Recepción" con RECIBIR y "Últimas Recepciones"). Registrar la compra al proveedor deja la solicitud Atendida; recibir la mercancía crea lotes, sube el stock y genera el movimiento Entrada. Toda fecha de vencimiento es obligatoria.
5. **Inventario** → verificar el resultado

### Fase 3 — Ventas (Operador de Ventas)
6. **Ventas / Salidas** → validar y confirmar: descuenta por FEFO, genera NDE y movimiento Salida

### Fase 4 — Análisis (todos según rol)
7. **Estadísticas** → ventas y comportamiento (Admin y Operador de Ventas)
8. **Imprimir** → reporte de inventario (todos los roles)

### Fase 5 — Control (Administrador)
9. **Historial** → auditoría de todas las operaciones

### Permisos por rol

| Módulo | Admin | Op. Carga | Op. Ventas |
|---|---|---|---|
| Categorías, Proveedores | ✔ gestionar | ✔ gestionar | ✖ |
| Usuarios | ✔ | ✖ | ✖ |
| Compras (incluye recepción y solicitudes) | ✔ | ✔ | ✖ |
| Inventario | ✔ editar | ✔ consultar | ✔ solo consulta |
| Ventas / Salidas | ✔ (además anular) | ✖ | ✔ |
| Estadísticas | ✔ | ✖ | ✔ |
| Imprimir reporte | ✔ | ✔ | ✔ |
| Historial | ✔ | ✖ | ✖ |

## Pruebas aisladas del piloto de ventas/stock

`tests/ventas_stock_piloto.php` verifica FEFO, rollback, concurrencia, anulación,
permisos, CSRF directo, render MVC, preview multiítem y confirmación válida de
productos distintos. El preview de VENTA acepta productos distintos y rechaza
el mismo producto repetido con un snapshot sin cambios, incluido el contador
secuencial `CTRL`; un preview válido debe reservar exactamente el siguiente
número. Envía además dos líneas del mismo producto directamente a
`Salida::confirmar()` y exige rechazo con error y sin escrituras en stock,
lotes, cabecera, detalles, movimientos, auditoría ni contador `CTRL`. Comprueba
también que un fallo tardío al confirmar revierte los efectos transaccionales
sin alterar el número `CTRL` ya reservado por el preview. Un trigger temporal
fuerza además el fallo del generador NDE durante una venta y exige error,
rollback completo de salidas, detalles, stock, lotes, movimientos y auditoría,
contador NDE intacto, ausencia de `NDE-ERROR` y ninguna transacción activa. No
carga `config/.env`, no incluye `init.php` ni ejecuta migraciones. Para simular
fallos transaccionales usa
triggers temporales de prueba, eliminados al terminar cada escenario. Las
comprobaciones de CSRF y permisos son directas, no peticiones HTTP. La suite no
verifica recuperación visual ante un fallo de confirmación ni el reenvío del
formulario; requieren verificación HTTP/UI aislada.

Ejecute el runner solo contra una instancia MariaDB desechable en loopback,
con esquema limpio v6, datadir dedicado y cuenta restringida a la base de
prueba. No use la cuenta root. En el mismo proceso de PowerShell configure
`JV_PILOT_TEST_DB_NAME` (prefijo `jv3000_pilot_`), `JV_PILOT_TEST_HOST`
(`127.0.0.1`), `JV_PILOT_TEST_PORT`, `JV_PILOT_TEST_DATADIR`,
`JV_PILOT_TEST_USER` (`jv_sales_test`) y `JV_PILOT_TEST_PASS`; la contraseña
debe generarse para la ejecución y no guardarse en el repositorio. El runner
compara la conexión con `DATABASE()`, `@@datadir`, `@@port` y
`@@bind_address`, y aborta antes de insertar fixtures si no coinciden.
Verifique también fuera del runner que la cuenta solo tenga permisos sobre
esa base. Nunca use el servicio o configuración local de la aplicación.

## Pruebas aisladas del piloto de compras y recepción

`tests/compras_recepcion_piloto.php` cubre el alta multiítem, anticipos y
solicitudes, recepción parcial/completa, rollback, exceso de recepción,
concurrencia por factura, solicitud, saldo, anulación/recepción, reenvíos
idempotentes secuenciales/concurrentes, mismo documento con clave técnica
distinta, permisos y auditoría. También incluye líneas válidas mezcladas con
IDs inválidos, recepción parcial con cantidades cero en líneas omitidas y
rechazo de envíos todo-cero. Usa fixtures sintéticos, no carga `config/.env`
ni `init.php`, y crea triggers temporales de fallo/retardo que elimina al
terminar cada caso. Permisos y CSRF/modelo no equivalen a una petición HTTP.
La clave y huella de replay se guardan en el movimiento dentro de la
transacción; el esquema limpio v6 e `init.php` contemplan esas columnas.

Use únicamente MariaDB desechable enlazado a `127.0.0.1`, datadir dedicado,
esquema v6 y cuenta de mínimo privilegio. Configure en el proceso PowerShell
`JV_PURCHASES_TEST_DB_NAME` (prefijo `jv3000_compras_piloto_`),
`JV_PURCHASES_TEST_HOST` (`127.0.0.1`), `JV_PURCHASES_TEST_PORT`,
`JV_PURCHASES_TEST_DATADIR`, `JV_PURCHASES_TEST_USER` (`jv_purchase_test`) y
`JV_PURCHASES_TEST_PASS` con una contraseña efímera no persistida. El runner
verifica nombre, datadir, puerto y bind del servidor antes de insertar
fixtures; compruebe además que el usuario solo tenga permisos sobre esa base.
No reutilice bases, datadirs, puertos ni cuentas de ejecuciones anteriores.

### Resultado de la verificación T6 (2026-10-06 22:50)

`tests/ventas_stock_piloto.php` terminó con código 0 en la base v6 sintética
nueva `jv3000_pilot_smi_20261006a`, MariaDB enlazado únicamente a
`127.0.0.1:33341`, datadir dedicado y cuenta limitada a esa base. Pasaron el
preview válido con productos distintos, el rechazo en preview de producto
repetido sin cambios observados, la regresión existente de rechazo en
confirmación sin escrituras, además de rollback, concurrencia, anulación,
permisos/CSRF directos y render MVC. El error de auditoría durante la prueba
de anulación fue la inyección sintética esperada y el rollback pasó. La suite
incluye render MVC directo, no prueba de navegador ni petición HTTP real.
`php -l` en el modelo y runner y `git diff --check` pasaron. Una primera
repetición completa se detuvo porque la copia temporal mínima omitía el layout
requerido por el test de render; se completó la copia, se reimportó v6 limpio y
el runner completo pasó. No se ejecutó HTTP ni navegador. La instancia y copia
temporal fueron detenidas/eliminadas tras verificar identidad; el puerto quedó
libre.

**Antecedente histórico:** una ejecución anterior de compras/recepción se detuvo
en su primer alta asociada a solicitud con “LA SOLICITUD YA FUE ATENDIDA POR
OTRA COMPRA”; por eso en esa ejecución replay, concurrencia y rollback quedaron
sin verificar. La reproducción diagnóstica posterior en base v6 sintética
registró solicitud ID 1 en estado Pendiente e `id_compra` nulo antes del
UPDATE; después observó `stmt->affected_rows = 1`, `conn->affected_rows = 1` y
la solicitud Atendida enlazada a la compra. Esa reproducción aislada pasó, pero
no ejecutó la suite completa.

**Ejecución completa de compras/recepción (2026-10-06 12:14):** `tests/compras_recepcion_piloto.php` terminó con
código 0 en una instancia desechable MariaDB enlazada únicamente a
`127.0.0.1:33337`, con esquema v6 sintético y una cuenta limitada a esa base.
Pasaron asociación normal y concurrente, rollback de compra y recepción,
validación de líneas, recepción parcial/completa, rechazo de cero y exceso,
replay secuencial/concurrente, mismo documento con clave distinta,
concurrencia por factura y recepción, anulación/recepción, rollback de
anulación y permiso del controlador. En la asociación normal la solicitud 1
estaba Pendiente/sin compra y ambos contadores fueron 1; quedó Atendida y
asociada. En la carrera de solicitud 2, la compra ganadora reportó ambos
contadores en 1; la competidora, tras ver la fila ya Atendida/enlazada a la
ganadora, reportó ambos en 0. No había triggers de solicitud ni conexiones
ajenas visibles durante la asociación; el trigger de fallo tardío de la suite
se eliminó en su `finally`. La verificación final no encontró triggers de
solicitud restantes.

El runner emitió un mensaje de error de anulación causado por el trigger
sintético de fallo de auditoría; el caso esperaba ese fallo y pasó sus
aserciones de rollback. No se ejecutaron migraciones, aplicación, servidor HTTP
ni peticiones HTTP reales. Se eliminaron la base/cuenta temporales, proceso,
datadir y copia después de verificar sus identidades; el servicio MySQL de la
aplicación permaneció detenido y el puerto temporal quedó libre. Los checks de
CSRF/permisos del runner son directos, no E2E HTTP.

### Ejecución de estabilidad posterior (2026-10-06)

- `tests/compras_recepcion_piloto.php` y `tests/ventas_stock_piloto.php` pasaron completos en las bases v6 sintéticas `jv3000_compras_piloto_smi_20261006b` y `jv3000_pilot_smi_20261006b` del staging `jv3000_stability_20261006b`, MariaDB loopback `127.0.0.1:33357`. La prueba temporal de `Database::affectedRows()` reprodujo el fallo antes de corregirlo y pasó después de capturar `stmt->affected_rows` en `Database::execute()`.
- La validación unitaria aislada de cantidades pasó. Los runners rechazaron cantidades fraccionarias de unidades y preservaron las transacciones; navegador verificó que compra/venta rechazan `1.5` y aceptan `1`.
- En el endpoint real `nota_entrega/store`, un POST manipulado repetido devolvió HTTP 400 sin token; el fraccionario también devolvió 400 y los productos distintos devolvieron 200 con token. Antes/después se mantuvieron iguales: productos 8, lotes 9, salidas 3, detalles 5, movimientos 3, detalles de movimiento 4, auditorías 4, stock y lotes.
- En navegador, la segunda línea repetida de VENTA no se agregó; dos productos distintos sí generaron un preview visible con subtotal $20.00 e importe total $23.20. No se confirmó una venta.
- El servidor de staging usó el puerto HTTP `8087`; una petición AJAX de búsqueda ejecutó migraciones ya existentes solo sobre una DB sintética temporal. No se modificaron el esquema ni archivos de migración del proyecto. El servicio y el staging desechable se detuvieron/eliminaron después de revalidar sus identidades. Esta evidencia no cubre UI/HTTP de recepción ni recuperación/reintento final.

### Validación aislada de cantidades (2026-10-07)

`php tests\cantidad_unidades.php` no inicia la aplicación ni se conecta a la
base de datos. Comprueba cantidades enteras, decimales textuales con fracción
cero, fracciones y una fracción JSON de alta precisión que PHP decodifica como
un float entero por redondeo. El caso de precisión falló antes de endurecer el
validador y pasó después. En esta actualización también pasaron `php -l` de
`includes\helpers.php`, `models\Compra.php` y `models\NotaEntrega.php`, además
de `node --check` en los dos módulos de captura; no se repitieron suites con DB,
HTTP ni navegador.

### Recuperación de formularios (implementación, 2026-10-06)

La decisión D2 es excluir el anticipo y todos los detalles de pago de la recuperación; el operador debe ingresarlos nuevamente. Tras un rechazo de compra se recuperan proveedor, referencias y líneas no sensibles en una whitelist limitada de sesión. Tras rechazo de recepción se recupera el modal y se conserva la misma clave idempotente; si las líneas no se pueden interpretar unívocamente, las cantidades no se restauran y se advierte revisión manual. No cambió el tratamiento del servidor de IDs de detalle repetidos ni de productos repetidos en compras. La confirmación de venta conserva el preview en error explícito y lo consume solo tras éxito.

`tests/recuperacion_formularios.php` pasó sin conexión a DB, incluyendo exclusión de pago/CSRF, preservación de líneas de compra repetidas, reutilización de clave de recepción y no restauración de IDs ambiguos. En staging sintético desechable (2026-10-07), HTTP verificó recuperación de compra/recepción, CSRF renderizado, borrador consumido tras éxito, rechazo de recepción inválida sin escrituras, recepción parcial y replay sin duplicación; el POST con `id_detalle` ambiguo mantuvo `items=null`, mostró el aviso y no cambió estado persistido. No se repitió HTTP en esta actualización. Chrome no estuvo disponible, por lo que no se verificó visualmente la reapertura/hidratación del modal, el cero visible de las líneas omitidas ni el aviso; T7 permanece parcial.

## Control de cambios

Todo cambio de código debe registrarse en `docs/BITACORA.md` (regla obligatoria en `AGENTS.md`).

## Licencia

Uso interno exclusivo de JV3000 C.A.
