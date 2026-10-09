@echo off
setlocal

set PHP_PATH=C:\xampp\php\php.exe
set PROJECT_PATH=%~dp0

cd /d "%PROJECT_PATH%"

:loop
"%PHP_PATH%" artisan queue:work --tries=3 --sleep=3 --max-time=3600 >> storage\logs\queue-worker.log 2>&1
echo [%date% %time%] Worker exited, restarting in 5 seconds... >> storage\logs\queue-worker.log
timeout /t 5 /nobreak >nul
goto loop
