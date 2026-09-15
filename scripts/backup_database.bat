@echo off
REM HORION TIME - Backup para Windows
REM Programar con Task Scheduler

set DB_NAME=horion_time
set DB_USER=root
set DB_PASS=
set BACKUP_DIR=C:\Backups\horion_time
set DATE=%date:~-4%%date:~3,2%%date:~0,2%_%time:~0,2%%time:~3,2%
set DATE=%DATE: =0%

mkdir "%BACKUP_DIR%" 2>nul

echo [%date% %time%] Iniciando backup...

"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe" -u %DB_USER% %DB_NAME% > "%BACKUP_DIR%\horion_time_%DATE%.sql"

if %ERRORLEVEL% EQU 0 (
    echo [OK] Backup creado: horion_time_%DATE%.sql
) else (
    echo [ERROR] Falló el backup
    exit /b 1
)

REM Eliminar backups de más de 30 días
forfiles /p "%BACKUP_DIR%" /m *.sql /d -30 /c "cmd /c del @path" 2>nul