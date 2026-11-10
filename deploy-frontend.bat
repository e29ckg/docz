@echo off
setlocal
title DOCZ - Frontend Deployment

echo ======================================================
echo    DOCZ - Frontend Deployment (Vue 3 + Vite)
echo ======================================================
echo.

cd /d "%~dp0"

:: 1. Check Node.js
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Node.js is not found in PATH!
    echo Please install Node.js before running this script.
    echo.
    pause
    exit /b 1
)

:: 2. Check npm
where npm >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] npm is not found in PATH!
    echo.
    pause
    exit /b 1
)

:: 3. Check node_modules in frontend
if not exist "frontend\node_modules\" (
    echo [INFO] Installing frontend dependencies...
    call npm --prefix frontend install
    if %errorlevel% neq 0 (
        echo.
        echo [ERROR] Failed to install dependencies!
        echo.
        pause
        exit /b 1
    )
    echo [SUCCESS] Dependencies installed successfully.
    echo.
)

:: 4. Build and Deploy Frontend to web/app
echo [INFO] Building production bundle for Frontend...
call npm --prefix frontend run build

if %errorlevel% neq 0 (
    echo.
    echo ======================================================
    echo    [ERROR] Frontend build failed!
    echo ======================================================
    echo.
    pause
    exit /b %errorlevel%
)

echo.
echo ======================================================
echo    [SUCCESS] Frontend deployed successfully!
echo ======================================================
echo.
echo    Output directory: web\app\
echo    URL (Direct Node.js): http://localhost:3000/
echo.
pause
