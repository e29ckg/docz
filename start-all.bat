@echo off
setlocal
title DOCZ - Full Stack Launcher

echo ======================================================
echo    DOCZ - Full Stack Launcher (Node + Vue)
echo ======================================================
echo.

cd /d "%~dp0"

:: 1. Start Node.js Express Backend in a new window
echo [INFO] Starting Node.js Express Backend (Port 3000)...
start "DOCZ Node.js Backend" cmd /k "npm --prefix server run dev"

:: 2. Wait 2 seconds
timeout /t 2 /nobreak >nul

:: 3. Start Frontend Vite Dev Server in a new window
echo [INFO] Starting Frontend Dev Server (Port 5173)...
start "DOCZ Frontend Dev" cmd /k "npm --prefix frontend run dev"

echo.
echo ======================================================
echo    Servers are starting up!
echo    - Backend API: http://localhost:3000
echo    - Frontend App: http://localhost:5173
echo ======================================================
echo.
pause
