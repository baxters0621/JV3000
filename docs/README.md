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
3. El auto-instalador `init.php` crea la BD a partir de `db/jv3000_portable_v4.sql`
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
4. **Solicitudes de Reposición** → detectar qué producto falta
5. **Compras** → comprar al proveedor (la solicitud queda Atendida; toda fecha de vencimiento es obligatoria)
6. **Recepción** → confirmar lo recibido: crea lotes, sube el stock y genera el movimiento Entrada
7. **Inventario** → verificar el resultado

### Fase 3 — Ventas (Operador de Ventas)
8. **Ventas / Salidas** → validar y confirmar: descuenta por FEFO, genera NDE y movimiento Salida

### Fase 4 — Análisis (todos según rol)
9. **Estadísticas** → ventas y comportamiento (Admin y Operador de Ventas)
10. **Imprimir** → reporte de inventario (todos los roles)

### Fase 5 — Control (Administrador)
11. **Historial** → auditoría de todas las operaciones

### Permisos por rol

| Módulo | Admin | Op. Carga | Op. Ventas |
|---|---|---|---|
| Categorías, Proveedores | ✔ gestionar | ✔ gestionar | ✖ |
| Usuarios | ✔ | ✖ | ✖ |
| Solicitudes, Compras, Recepción | ✔ | ✔ | ✖ |
| Inventario | ✔ editar | ✔ consultar | ✔ solo consulta |
| Ventas / Salidas | ✔ (además anular) | ✖ | ✔ |
| Estadísticas | ✔ | ✖ | ✔ |
| Imprimir reporte | ✔ | ✔ | ✔ |
| Historial | ✔ | ✖ | ✖ |

## Control de cambios

Todo cambio de código debe registrarse en `docs/BITACORA.md` (regla obligatoria en `AGENTS.md`).

## Licencia

Uso interno exclusivo de JV3000 C.A.
