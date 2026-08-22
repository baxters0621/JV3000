@echo off
REM ============================================
REM  BACKUP DE BASE DE DATOS - JV3000 C.A.
REM  Genera un .sql con fecha y hora
REM ============================================

if not defined JV_DB_USER set JV_DB_USER=root
if not defined JV_DB_PASS set JV_DB_PASS=
if not defined JV_DB_NAME set JV_DB_NAME=jv3000_db
set ENV_FILE=%~dp0..\config\.env
if exist "%ENV_FILE%" (
    for /f "usebackq tokens=1,* delims==" %%A in ("%ENV_FILE%") do (
        if /i "%%A"=="JV_DB_USER" set JV_DB_USER=%%B
        if /i "%%A"=="JV_DB_PASS" set JV_DB_PASS=%%B
        if /i "%%A"=="JV_DB_NAME" set JV_DB_NAME=%%B
    )
)
set DB_USER=%JV_DB_USER%
set DB_PASS=%JV_DB_PASS%
set DB_NAME=%JV_DB_NAME%
set BACKUP_DIR=%~dp0

REM --- Detectar mysqldump.exe (XAMPP o PATH) ---
set MYSQLDUMP=
if exist "C:\xampp\mysql\bin\mysqldump.exe" set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
if not defined MYSQLDUMP (
    for %%d in ("C:\xampp" "D:\xampp" "%ProgramFiles%\XAMPP" "%ProgramFiles(x86)%\XAMPP") do (
        if not defined MYSQLDUMP if exist "%%~d\mysql\bin\mysqldump.exe" set "MYSQLDUMP=%%~d\mysql\bin\mysqldump.exe"
    )
)
if not defined MYSQLDUMP (
    for /f "delims=" %%i in ('where mysqldump 2^>nul') do (
        if not defined MYSQLDUMP set "MYSQLDUMP=%%i"
    )
)

if not defined MYSQLDUMP (
    echo [ERROR] No se encontro mysqldump.exe.
    echo.
    echo Se busco en C:\xampp, D:\xampp, %%ProgramFiles%%\XAMPP y en el PATH.
    echo Instala XAMPP o edita este archivo y coloca la ruta en MYSQLDUMP.
    pause
    exit /b 1
)

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmmss"') do set TIMESTAMP=%%i
set FILENAME=%BACKUP_DIR%jv3000_db_%TIMESTAMP%.sql

echo ============================================
echo  Respaldando base de datos: %DB_NAME%
echo ============================================
echo.

"%MYSQLDUMP%" -u%DB_USER% --databases %DB_NAME% --single-transaction --routines --triggers --events --result-file="%FILENAME%"

if errorlevel 1 (
    echo [ERROR] Fallo al crear el backup.
    if exist "%FILENAME%" del /q "%FILENAME%" >nul 2>&1
) else (
    echo [OK] Backup creado exitosamente:
    echo      %FILENAME%
    echo.
    for %%A in ("%FILENAME%") do echo      Tamanio: %%~zA bytes
)

echo.
pause
