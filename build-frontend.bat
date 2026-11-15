@echo off
setlocal
title DOCZ - Build Frontend

:: Usage: build-frontend.bat [--no-pause]
set "DOCZ_BUILD_NO_PAUSE="
if /i "%~1"=="--no-pause" set "DOCZ_BUILD_NO_PAUSE=1"

pushd "%~dp0"
if errorlevel 1 exit /b 1

echo ======================================================
echo    DOCZ - Build Frontend (Vue 3 + Vite)
echo ======================================================
echo.

where node >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Install Node.js 22.12 or later and reopen this terminal.
    goto failed
)

node -e "const [major,minor]=process.versions.node.split('.').map(Number);process.exit(major<22||(major===22&&minor<12)?1:0)"
if errorlevel 1 (
    echo [ERROR] Node.js 22.12 or later is required.
    goto failed
)

where npm.cmd >nul 2>nul
if errorlevel 1 (
    echo [ERROR] npm is not found in PATH. Reinstall Node.js with npm.
    goto failed
)

if not exist "frontend\package.json" (
    echo [ERROR] frontend\package.json was not found beside this script.
    goto failed
)

if not exist "frontend\node_modules\.bin\vite.cmd" goto install_dependencies
if not exist "frontend\node_modules\.bin\vue-tsc.cmd" goto install_dependencies
goto build

:install_dependencies
echo [INFO] Installing frontend dependencies...
if exist "frontend\package-lock.json" (
    call npm --prefix frontend ci --include=dev
) else (
    call npm --prefix frontend install --include=dev
)
if errorlevel 1 goto failed

:build
echo [INFO] Building frontend...
call npm --prefix frontend run build
if errorlevel 1 goto failed

echo.
echo [SUCCESS] Frontend build completed.
echo Output: %CD%\web\app
echo Open: http://localhost:3000/docz/
echo Refresh the browser with Ctrl+F5 to load the updated frontend.
echo.
popd
if not defined DOCZ_BUILD_NO_PAUSE pause
exit /b 0

:failed
echo.
echo [ERROR] Frontend build did not complete. Check the message above.
echo.
popd
if not defined DOCZ_BUILD_NO_PAUSE pause
exit /b 1
