@echo off
setlocal
set "SILENT=0"
if /i "%~1"=="--silent" set "SILENT=1"
if not defined JV_DB_USER set "JV_DB_USER=root"
if not defined JV_DB_PASS set "JV_DB_PASS="
if not defined JV_DB_NAME set "JV_DB_NAME=jv3000_db"
set "ENV_FILE=%~dp0..\config\.env"
if exist "%ENV_FILE%" for /f "usebackq tokens=1,* delims==" %%A in ("%ENV_FILE%") do call :read_env "%%A" "%%B"
set "DB_USER=%JV_DB_USER%"
set "DB_PASS=%JV_DB_PASS%"
set "DB_NAME=%JV_DB_NAME%"
set "MYSQL_PWD=%DB_PASS%"
set "BACKUP_DIR=%~dp0"
set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
if not exist "%MYSQLDUMP%" for /f "delims=" %%I in ('where mysqldump 2^>nul') do if not defined MYSQLDUMP set "MYSQLDUMP=%%I"
if not exist "%MYSQLDUMP%" goto :missing_tool
for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmmss"') do set "TIMESTAMP=%%I"
set "FILENAME=%BACKUP_DIR%jv3000_db_%TIMESTAMP%.sql"
if "%SILENT%"=="0" echo Respaldando base de datos: %DB_NAME%
"%MYSQLDUMP%" -u%DB_USER% --databases %DB_NAME% --single-transaction --routines --triggers --events --result-file="%FILENAME%"
if errorlevel 1 goto :backup_failed
if "%SILENT%"=="0" echo Backup creado: %FILENAME%
endlocal
exit /b 0
:read_env
if /i "%~1"=="JV_DB_USER" set "JV_DB_USER=%~2"
if /i "%~1"=="JV_DB_PASS" set "JV_DB_PASS=%~2"
if /i "%~1"=="JV_DB_NAME" set "JV_DB_NAME=%~2"
exit /b 0
:missing_tool
echo [ERROR] No se encontro mysqldump.exe.
endlocal
exit /b 1
:backup_failed
echo [ERROR] Fallo al crear el backup.
if exist "%FILENAME%" del /q "%FILENAME%" >nul 2>&1
endlocal
exit /b 1