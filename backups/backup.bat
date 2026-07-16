@echo off
REM ============================================
REM  BACKUP DE BASE DE DATOS - JV3000 C.A.
REM  Genera un .sql con fecha y hora
REM ============================================

set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set DB_USER=root
set DB_PASS=
set DB_NAME=jv3000_db
set BACKUP_DIR=%~dp0

for /f "tokens=1-5 delims=/:. " %%a in ("%date% %time%") do set TIMESTAMP=%%a-%%b-%%c_%%d%%e
set TIMESTAMP=%TIMESTAMP: =0%
set FILENAME=%BACKUP_DIR%jv3000_db_%TIMESTAMP%.sql

if not exist "%MYSQLDUMP%" (
    echo [ERROR] No se encuentra mysqldump en:
    echo %MYSQLDUMP%
    echo.
    echo Asegurate de que XAMPP este instalado en C:\xampp
    pause
    exit /b 1
)

echo ============================================
echo  Respaldando base de datos: %DB_NAME%
echo ============================================
echo.

"%MYSQLDUMP%" -u%DB_USER% --databases %DB_NAME% > "%FILENAME%"

if %ERRORLEVEL% equ 0 (
    echo [OK] Backup creado exitosamente:
    echo      %FILENAME%
    echo.
    for %%A in ("%FILENAME%") do echo      Tamanio: %%~zA bytes
) else (
    echo [ERROR] Fallo al crear el backup.
)

echo.
pause
