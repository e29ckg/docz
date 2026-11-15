@echo off
setlocal DisableDelayedExpansion
title DOCZ - Manual Backup

rem Run the existing backup workflow and keep its result visible.
rem Backup destination and retention are configured in backup-auto.bat.
rem Usage: backup.bat [--no-pause]

if not exist "%~dp0backup-auto.bat" (
    echo [ERROR] Cannot find "%~dp0backup-auto.bat".
    set "BACKUP_EXIT_CODE=1"
    goto :finish
)

call "%~dp0backup-auto.bat"
set "BACKUP_EXIT_CODE=%errorlevel%"

echo.
if "%BACKUP_EXIT_CODE%"=="0" (
    echo [SUCCESS] Backup completed. See the archive path above.
) else (
    echo [FAILED] Backup failed. Check "%~dp0logs\backup.log".
)

:finish
if /i not "%~1"=="--no-pause" pause
exit /b %BACKUP_EXIT_CODE%
