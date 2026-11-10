@echo off
setlocal
cd /d "%~dp0"
where node >nul 2>nul
if errorlevel 1 (
  echo Install Node.js 22.12 or later before running this installer.
  exit /b 1
)
node scripts\setup.mjs
exit /b %errorlevel%
