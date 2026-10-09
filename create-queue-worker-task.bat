@echo off
setlocal

set TASK_NAME=JawdaInpatient-QueueWorker
set PROJECT_PATH=%~dp0
set WORKER_SCRIPT=%PROJECT_PATH%queue-worker.bat

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

echo Creating scheduled task "%TASK_NAME%" to run the queue worker at system startup...
schtasks /create ^
    /tn "%TASK_NAME%" ^
    /tr "cmd /c \"%WORKER_SCRIPT%\"" ^
    /sc onstart ^
    /delay 0001:00 ^
    /ru SYSTEM ^
    /rl HIGHEST ^
    /f

if %errorLevel% equ 0 (
    echo.
    echo Done. The task "%TASK_NAME%" starts the queue worker automatically at system boot.
    echo queue-worker.bat loops forever, restarting "php artisan queue:work" if it stops or crashes.
    echo To start it right now without rebooting, run: schtasks /run /tn "%TASK_NAME%"
    echo Worker output/errors are logged to storage\logs\queue-worker.log
) else (
    echo.
    echo Failed to create the scheduled task. See the error above.
)

pause
