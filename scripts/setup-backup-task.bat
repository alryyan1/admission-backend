@echo off
REM Creates Windows Task Scheduler jobs that run the DB backup directly
REM (no need for Laravel's scheduler / `schedule:run` to be wired into cron).
REM
REM Must be run as Administrator. Re-running it is safe; it overwrites the
REM existing tasks (/F) with whatever is configured below.

setlocal

REM ====== Edit these for the server this runs on ======
set "PHP_EXE=C:\xampp\php\php.exe"
set "PROJECT_DIR=C:\xampp\htdocs\jawda-inpatient"
set "BACKUP_TIME=02:00"
set "CLEANUP_TIME=02:30"
REM ======================================================

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo This script must be run as Administrator.
    pause
    exit /b 1
)

if not exist "%PHP_EXE%" (
    echo PHP_EXE not found at "%PHP_EXE%". Edit this script and fix the path.
    pause
    exit /b 1
)

if not exist "%PROJECT_DIR%\artisan" (
    echo PROJECT_DIR "%PROJECT_DIR%" does not look like the Laravel project root ^(no artisan file^).
    pause
    exit /b 1
)

set "TR_BACKUP=cmd /c cd /d %PROJECT_DIR% && %PHP_EXE% artisan backup:run --only-db >> %PROJECT_DIR%\storage\logs\backup.log 2>&1"
set "TR_CLEANUP=cmd /c cd /d %PROJECT_DIR% && %PHP_EXE% artisan backup:clean >> %PROJECT_DIR%\storage\logs\backup.log 2>&1"

echo Creating weekly backup task (Sundays %BACKUP_TIME%)...
schtasks /Create /TN "JawdaInpatient DB Backup" /TR "%TR_BACKUP%" /SC WEEKLY /D SUN /ST %BACKUP_TIME% /RU SYSTEM /F
if %errorlevel% neq 0 (
    echo Failed to create the backup task.
    pause
    exit /b 1
)

echo Creating weekly cleanup task (Sundays %CLEANUP_TIME%)...
schtasks /Create /TN "JawdaInpatient DB Backup Cleanup" /TR "%TR_CLEANUP%" /SC WEEKLY /D SUN /ST %CLEANUP_TIME% /RU SYSTEM /F
if %errorlevel% neq 0 (
    echo Failed to create the cleanup task.
    pause
    exit /b 1
)

echo.
echo Done. Current tasks:
schtasks /Query /TN "JawdaInpatient DB Backup"
schtasks /Query /TN "JawdaInpatient DB Backup Cleanup"

echo.
echo NOTE: Tasks run as the SYSTEM account, so they work even if nobody is
echo logged in. If BACKUP_DISK_PATH (.env) points to a mapped network drive
echo letter, SYSTEM may not see it - use a UNC path instead, e.g.
echo   \\server\share\Backups\jawda-inpatient
echo or change /RU above to a specific domain user and supply /RP for that
echo account's password.

pause
