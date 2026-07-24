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
- Portable: `db/jv3000_portable_v3.sql`
- Auto-instalador en `init.php` apunta a `v3`
- Backups en `backups/`

## Configuración XAMPP
- MySQL no corre como servicio Windows. Iniciar con: `Start-Process -FilePath "C:\xampp\mysql\bin\mysqld.exe" -NoNewWindow`
- PHP CLI requiere arrancar MySQL manualmente antes de scripts externos