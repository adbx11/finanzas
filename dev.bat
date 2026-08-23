@echo off
setlocal
cd /d "%~dp0"

where php >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP no esta en el PATH.
    pause
    exit /b 1
)

where npm >nul 2>&1
if errorlevel 1 (
    echo [ERROR] npm no esta en el PATH.
    pause
    exit /b 1
)

if not exist "artisan" (
    echo [ERROR] Ejecuta este script desde la raiz del proyecto Laravel.
    pause
    exit /b 1
)

echo Iniciando ADB Finanzas ^(desarrollo^)...
echo   - php artisan serve
echo   - npm run dev
echo.
echo Cierra las dos ventanas para detener los servicios.
echo.

start "Finanzas - artisan serve" /D "%~dp0" cmd /k "php artisan serve"
start "Finanzas - npm run dev" /D "%~dp0" cmd /k "npm run dev"

endlocal
