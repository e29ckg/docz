@echo off
setlocal
title DOCZ - Frontend Dev Server

echo ======================================================
echo    DOCZ - Frontend Development Server (Vite)
echo ======================================================
echo.

cd /d "%~dp0"

where npm >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] npm is not found in PATH!
    pause
    exit /b 1
)

if not exist "frontend\node_modules\" (
    echo [INFO] Installing frontend dependencies...
    call npm --prefix frontend install
)

echo [INFO] Starting Vite development server...
echo URL: http://127.0.0.1:5173
echo.

call npm --prefix frontend run dev
pause
