@echo off
setlocal
title DOCZ - Node.js Express Backend

echo ======================================================
echo    DOCZ - Express Backend Server
echo ======================================================
echo.

cd /d "%~dp0"

where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Node.js is not found in PATH!
    pause
    exit /b 1
)

if not exist "server\node_modules\" (
    echo [INFO] Installing server dependencies...
    call npm --prefix server install
)

echo [INFO] Starting Express API Server...
echo API URL: http://localhost:3000
echo Health check: http://localhost:3000/health
echo.

call npm --prefix server run dev
pause
