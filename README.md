# JV3000 C.A. - Sistema de Gestión Empresarial

Sistema web para control de inventario, compras, ventas, facturación y administración
de usuarios de JV3000 C.A. Desarrollado en PHP 8.2.12 + MySQL (MariaDB) + Bootstrap.

## Stack tecnológico

- **Backend:** PHP 8.2.12
- **Base de datos:** MySQL / MariaDB
- **Frontend:** Bootstrap 5 + Bootstrap Icons + Chart.js
- **Servidor:** Apache (XAMPP)

## Características

- Control de inventario con categorías y productos
- Gestión de compras y ventas (salidas)
- Facturación conforme a providencia SENIAT
- Dashboard con KPIs y gráficos en tiempo real
- Módulo de estadísticas con proyecciones
- Control de usuarios con roles (Admin, Operador de Ventas, Operador de Carga)
- Autenticación con pregunta de seguridad y recuperación de contraseña
- Auditoría de eventos

## Requisitos

- PHP >= 8.0
- MySQL / MariaDB
- Apache o Nginx
- Extensiones PHP: mysqli, pdo_mysql, json, mbstring

## Instalación local

1. Clonar el repositorio en `C:\xampp\htdocs\JV3000_db`
2. Importar `db/schema.sql` en phpMyAdmin o MySQL CLI
3. Configurar credenciales en `includes/config.php`
4. Acceder via `http://localhost/JV3000_db`

## Licencia

Uso interno exclusivo de JV3000 C.A.