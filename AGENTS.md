# Reglas de Ponytail para el Agente de IA

Antes de escribir cualquier línea de código, detente en el primer nivel que funcione:
1. ¿Esto realmente necesita existir? -> Si la respuesta es NO, ignóralo.
2. ¿Ya existe algo similar en este proyecto? -> Reutilízalo, no lo vuelvas a escribir.
3. ¿La librería estándar del lenguaje de programación ya lo hace? -> Úsala.
4. ¿Es una función nativa del navegador o del sistema? -> Úsala.
5. ¿Hay alguna librería ya instalada que lo resuelva? -> Úsala.
6. ¿Se puede solucionar en una sola línea? -> Escribe una sola línea.
7. Solo si nada de lo anterior funciona: Escribe el código mínimo necesario.

*Nota de seguridad: Nunca recortes en validaciones de seguridad, accesibilidad o manejo de errores críticos.*

## Control de sesión y permisos
- `$_SESSION['id_rol']` = 1 (Administrador), 2 (Operador de Carga), 3 (Operador de Ventas)
- Roles desde tabla `roles` con FK en `usuarios.id_rol`
- `Security::esAdmin()` → `id_rol === 1`, `puedeCargar()` → `id_rol === 1 || id_rol === 2`, `puedeVender()` → `id_rol === 1 || id_rol === 3`
- Para mostrar el nombre del rol: JOIN con `roles` o usar el mapa inline `$roles_map = [1=>'Administrador', 2=>'Operador de Carga', 3=>'Operador de Ventas']`

## DB
- Portable: `db/jv3000_portable_v4.sql` — seed de **instalación limpia** (esquema completo + solo datos de sistema: roles, tipos de movimiento, configuración, usuarios, contadores en 0). **No incluye datos demo**.
- Auto-instalador en `init.php` apunta a `v4`; migración de BD v3 → v4: `php db/migrar_v3_v4.php`
- Usuario inicial: `Administrador` / `Admin123*` (cambiar tras el primer inicio)
- Backups en `backups/`

## Configuración XAMPP
- **MySQL corre como servicio Windows `mysql` (auto-arranque)**. Iniciar/detener con `Start-Service mysql` / `Stop-Service mysql` o `net start/stop mysql`. No hace falta arrancarlo manualmente.
- Si el puerto 3306 está ocupado (instancia manual previa), el panel XAMPP mostrará "MySQL shutdown unexpectedly": eso es un falso positivo por conflicto de puerto, no un fallo real.
- Si MySQL aborta con `Table '.\mysql\db' is marked as crashed`: reparar la tabla del sistema (respaldar antes) con `& "C:\xampp\mysql\bin\aria_chk.exe" --recover "C:\xampp\mysql\data\mysql\db"` (tablas del sistema son Aria `.MAD/.MAI`), luego `Restart-Service mysql`.
- Los permisos de `C:\xampp\mysql\data` ya dan `FullControl` a `BUILTIN\Users` (evita el error `ibdata1 must be writable` al arrancar sin elevación).
- PHP CLI requiere MySQL arriba (servicio) antes de scripts externos.