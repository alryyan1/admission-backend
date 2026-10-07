@echo off
setlocal

set TASK_NAME=JawdaInpatient-Scheduler
set PHP_PATH=C:\xampp\php\php.exe
set PROJECT_PATH=%~dp0

net session >nul 2>&1
if %errorLevel% neq 0 (
    echo This must be run as Administrator. Right-click this file and choose "Run as administrator".
    pause
    exit /b 1
)

schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if %errorLevel% equ 0 (
    echo Removing existing task "%TASK_NAME%"...
    schtasks /delete /tn "%TASK_NAME%" /f
)

echo Creating scheduled task "%TASK_NAME%" to run every minute...
schtasks /create ^
    /tn "%TASK_NAME%" ^
    /tr "cmd /c cd /d \"%PROJECT_PATH%\" && \"%PHP_PATH%\" artisan schedule:run" ^
    /sc minute ^
    /mo 1 ^
    /ru SYSTEM ^
    /f

if %errorLevel% equ 0 (
    echo.
    echo Done. The task "%TASK_NAME%" now runs "php artisan schedule:run" every minute.
    echo Laravel's Kernel.php schedule decides what actually executes ^(backups at 02:00, WhatsApp reminders at 08:00, etc^).
) else (
    echo.
    echo Failed to create the scheduled task. See the error above.
)

pause
