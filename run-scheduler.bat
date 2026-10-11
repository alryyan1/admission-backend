@echo off
cd /d "%~dp0"
"C:\xampp\php\php.exe" artisan schedule:run
