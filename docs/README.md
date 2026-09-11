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

## Instalación local

1. Clonar el repositorio en `C:\xampp\htdocs\JV3000_db`
2. Iniciar el servicio `mysql` (Windows) y Apache (XAMPP)
3. El auto-instalador `init.php` crea la BD a partir de `db/jv3000_portable_v5.sql`
   (esquema completo + datos de sistema, sin datos demo)
4. Usuario inicial: `Administrador` / `Admin123*` (cambiar tras el primer inicio)
5. Acceder via `http://localhost/JV3000_db`

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

## Control de cambios

Todo cambio de código debe registrarse en `docs/BITACORA.md` (regla obligatoria en `AGENTS.md`).

## Licencia

Uso interno exclusivo de JV3000 C.A.

## Configuracion de entorno

La aplicacion lee primero variables de entorno y despues `config/.env`. Copia
`config/.env.example` como `config/.env` y ajusta al menos:

```ini
JV_DB_HOST=localhost
JV_DB_USER=usuario_de_aplicacion
JV_DB_PASS=clave_de_la_base
JV_DB_NAME=jv3000_db
```

No publiques `config/.env`, contrasenas ni respaldos con datos reales. En
produccion la cuenta de MySQL debe tener solo los permisos necesarios para la
aplicacion y el directorio de respaldos debe estar fuera del webroot cuando sea
posible.

## Actualizacion de una instalacion existente

1. Realiza y verifica un respaldo antes de copiar archivos nuevos.
2. Deten temporalmente las tareas programadas que escriban en `backups/`.
3. Copia el codigo nuevo sin sobrescribir `config/.env`.
4. Confirma que `JV_DB_NAME` apunta a la base correcta.
5. Inicia Apache y MySQL y abre la aplicacion una vez para que el arranque
   compruebe el esquema compatible.
6. Ejecuta `tools/validar.ps1` con los parametros de tu instalacion.
7. Prueba login, permisos, compras, recepcion, FEFO, salidas, reportes y
   anulaciones antes de reabrir la operacion.

No elimines ni reemplaces una base existente con `db/jv3000_portable_v5.sql`.
Ese archivo es para instalacion limpia; una restauracion sobre una base existente
debe hacerse con un respaldo comprobado y un plan de rollback.

## Respaldos y restauracion

El respaldo manual se ejecuta con:

```powershell
backups\backup.bat
```

La tarea diaria puede configurarse ejecutando `backups\configurar_backup.bat`
como Administrador. El script conserva 30 dias de archivos `jv3000_db_*.sql`.
Comprueba periodicamente que el archivo se cree y que su tamano sea razonable.

Para probar una restauracion, usa una base temporal, nunca la base operativa:

```powershell
mysql -u USUARIO -p -e "CREATE DATABASE jv3000_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u USUARIO -p jv3000_restore_test < backups\jv3000_db_FECHA.sql
```

Despues valida tablas, roles, productos, movimientos, triggers y eventos. Si la
restauracion falla, conserva el archivo original y no borres la base operativa.

## Validacion antes de entregar o desplegar

Desde PowerShell, ejecuta:

```powershell
.\tools\validar.ps1 -BaseUrl http://localhost/JV3000_db -Database jv3000_db
```

Si la aplicacion esta instalada en otra ruta, cambia `-BaseUrl`. La validacion
comprueba sintaxis PHP y JavaScript, bloqueo de archivos internos, invariantes
basicos de inventario, consistencia de base configurada y permisos de carpetas.

## Lista de puesta en produccion

- Cambiar la contrasena inicial del Administrador.
- Configurar `config/.env` fuera del control de versiones.
- Desactivar cuentas de prueba o dejarlas inactivas.
- Confirmar HTTPS, Apache y MySQL activos.
- Confirmar que `display_errors` no este habilitado en produccion.
- Ejecutar un respaldo y una restauracion de prueba.
- Configurar la tarea diaria y revisar su primera ejecucion.
- Probar los tres roles con una cuenta de prueba por rol.
- Registrar el cambio en `docs/BITACORA.md`.