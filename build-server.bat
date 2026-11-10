@echo off
setlocal
title DOCZ - Build Express Backend

echo ======================================================
echo    DOCZ - Build Express Backend (TypeScript Compile)
echo ======================================================
echo.

cd /d "%~dp0"

echo [INFO] Compiling TypeScript in server...
call npm --prefix server run build

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Build failed!
    echo.
    pause
    exit /b %errorlevel%
)

echo.
echo [SUCCESS] Backend build completed! Output directory: server\dist
echo.
pause
