@echo off
REM ============================================
REM  CONFIGURAR BACKUP AUTOMATICO - JV3000 C.A.
REM  Crea una tarea programada en Windows Task
REM  Scheduler para ejecutar backup.bat a las
REM  2:00 AM diariamente.
REM ============================================

echo ============================================
echo  Configurando backup automatico
echo ============================================
echo.

set BACKUP_BAT=%~dp0backup.bat
set TASK_NAME=JV3000_Backup_Diario

REM Eliminar tarea existente si la hay
schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if not errorlevel 1 (
    echo Eliminando tarea existente...
    schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1
)

REM Crear tarea programada: diaria a las 2:00 AM
echo Creando tarea programada: %TASK_NAME%
echo   Horario: Diario a las 2:00 AM
echo   Accion: %BACKUP_BAT% --silent
echo.

schtasks /create /tn "%TASK_NAME%" /tr "\"%BACKUP_BAT%\" --silent" /sc daily /st 02:00 /ru SYSTEM /rl HIGHEST /f

if errorlevel 1 (
    echo.
    echo [ERROR] No se pudo crear la tarea programada.
    echo Ejecuta este script como Administrador.
    pause
    exit /b 1
)

echo.
echo ============================================
echo  BACKUP AUTOMATICO CONFIGURADO
echo ============================================
echo.
echo  Tarea: %TASK_NAME%
echo  Horario: Diario a las 2:00 AM
echo  Retencion: 30 dias
echo  Modo: Silencioso (sin ventanas)
echo.
echo  Para probar ahora: ejecuta backup.bat manualmente
echo  Para ver la tarea: schtasks /query /tn "%TASK_NAME%"
echo  Para eliminar: schtasks /delete /tn "%TASK_NAME%" /f
echo.
pause
