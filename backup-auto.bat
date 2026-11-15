@echo off
setlocal enabledelayedexpansion
title DOCZ - Automatic Daily Backup System

:: ==============================================================================
:: DOCZ Automatic Daily Backup System for Windows Server
:: ระบบสำรองข้อมูลอัตโนมัติรายวัน (ฐานข้อมูล MySQL + ไฟล์เอกสาร uploads)
:: ==============================================================================

:: ------------------------------------------------------------------------------
:: 1. ตั้งค่าคอนฟิกการสำรองข้อมูล (Configurations)
:: ------------------------------------------------------------------------------
:: โฟลเดอร์ปลายทางสำหรับเก็บไฟล์สำรอง (สามารถเปลี่ยนเป็นไดรฟ์ D:\ หรือ Network Drive/NAS ได้)
set "BACKUP_DIR=D:\DOCZ_BACKUP"

:: จำนวนวันที่ต้องการเก็บไฟล์สำรองย้อนหลัง (ไฟล์ที่เก่ากว่านี้จะถูกลบอัตโนมัติ)
set "RETENTION_DAYS=7"

:: ZIP store mode: fastest creation, archive size stays close to source size.
:: Change to 1 for fast compression if disk space matters more than CPU time.
set "ZIP_LEVEL=0"

:: Check 7-Zip before exporting the database or copying large files.
set "SEVEN_ZIP=%ProgramFiles%\7-Zip\7z.exe"
if not exist "%SEVEN_ZIP%" set "SEVEN_ZIP=%ProgramFiles(x86)%\7-Zip\7z.exe"
if not exist "%SEVEN_ZIP%" (
    for /f "delims=" %%Z in ('where 7z.exe 2^>nul') do (
        if not exist "!SEVEN_ZIP!" set "SEVEN_ZIP=%%Z"
    )
)
if not exist "%SEVEN_ZIP%" (
    echo [ERROR] 7-Zip was not found. Install 7-Zip from https://www.7-zip.org/
    echo [ERROR] Expected 7z.exe in Program Files\7-Zip or in PATH.
    exit /b 1
)
"%SEVEN_ZIP%" i >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Cannot run 7-Zip at "%SEVEN_ZIP%".
    exit /b 1
)
echo [INFO] 7-Zip: "%SEVEN_ZIP%"

:: ไดเรกทอรีของระบบ DOCZ
set "DOCZ_DIR=%~dp0"
set "DOCZ_DIR=%DOCZ_DIR:~0,-1%"

:: โฟลเดอร์สำหรับเก็บ Log
set "LOG_FILE=%DOCZ_DIR%\logs\backup.log"

:: ตำแหน่งของ mysqldump.exe (ตรวจสอบ XAMPP เป็นค่าเริ่มต้น)
if exist "C:\xampp\mysql\bin\mysqldump.exe" (
    set "MYSQL_DUMP=C:\xampp\mysql\bin\mysqldump.exe"
) else (
    where mysqldump.exe >nul 2>nul
    if !errorlevel! equ 0 (
        set "MYSQL_DUMP=mysqldump.exe"
    ) else (
        set "MYSQL_DUMP=C:\xampp\mysql\bin\mysqldump.exe"
    )
)

:: ข้อมูลฐานข้อมูล (ค่าเริ่มต้น)
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_USER=root"
set "DB_PASS="
set "DB_NAME=docz"

:: อ่านการตั้งค่าจริงจาก server/.env หากมีอยู่
if exist "%DOCZ_DIR%\server\.env" (
    for /f "usebackq tokens=1,* delims==" %%A in ("%DOCZ_DIR%\server\.env") do (
        set "key=%%A"
        set "val=%%B"
        if "!key!"=="DB_HOST" set "DB_HOST=!val!"
        if "!key!"=="DB_PORT" set "DB_PORT=!val!"
        if "!key!"=="DB_USER" set "DB_USER=!val!"
        if "!key!"=="DB_PASSWORD" set "DB_PASS=!val!"
        if "!key!"=="DB_NAME" set "DB_NAME=!val!"
    )
)

:: ------------------------------------------------------------------------------
:: 2. สร้าง Timestamp ที่แม่นยำ (รองรับทั้ง Windows ภาษาไทย และอังกฤษ)
:: ------------------------------------------------------------------------------
for /f "usebackq" %%T in (`powershell -NoProfile -Command "Get-Date -Format 'yyyyMMdd_HHmmss'"`) do (
    set "TIMESTAMP=%%T"
)
for /f "usebackq delims=" %%D in (`powershell -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd HH:mm:ss'"`) do (
    set "LOG_TIME=%%D"
)

set "START_TIME=%LOG_TIME%"
set "STAGING_DIR=%BACKUP_DIR%\staging_%TIMESTAMP%"
set "FINAL_ZIP=%BACKUP_DIR%\DOCZ_BACKUP_%TIMESTAMP%.zip"
set "WORK_ZIP=%FINAL_ZIP%.partial"

mkdir "%BACKUP_DIR%" 2>nul
if not exist "%BACKUP_DIR%\" (
    echo [ERROR] Cannot create backup directory: "%BACKUP_DIR%"
    exit /b 1
)
if exist "%WORK_ZIP%" (
    echo [ERROR] A partial archive already exists: "%WORK_ZIP%"
    exit /b 1
)
if exist "%FINAL_ZIP%" (
    echo [ERROR] A backup with this timestamp already exists. Retry later.
    exit /b 1
)
mkdir "%DOCZ_DIR%\logs" 2>nul
mkdir "%STAGING_DIR%" 2>nul
if not exist "%STAGING_DIR%\" (
    echo [ERROR] Cannot create database staging directory.
    exit /b 1
)

echo ============================================================================== >> "%LOG_FILE%"
echo [%LOG_TIME%] Starting DOCZ Automatic Backup... >> "%LOG_FILE%"
echo ==============================================================================
echo    DOCZ Automatic Daily Backup System
echo    Timestamp: %LOG_TIME%
echo    Target:    %FINAL_ZIP%
echo ==============================================================================

:: ------------------------------------------------------------------------------
:: 3. ทำการสำรองฐานข้อมูล MySQL (Database Dump)
:: ------------------------------------------------------------------------------
echo [INFO] Step 1/3: Exporting MySQL Database (%DB_NAME%)...
echo [%LOG_TIME%] [INFO] Step 1/3: Exporting MySQL Database (%DB_NAME%)... >> "%LOG_FILE%"

if not exist "%MYSQL_DUMP%" (
    echo [ERROR] mysqldump.exe not found at: %MYSQL_DUMP%
    echo [%LOG_TIME%] [ERROR] mysqldump.exe not found at: %MYSQL_DUMP% >> "%LOG_FILE%"
    goto :ERROR_EXIT
)

set "DUMP_SQL=%STAGING_DIR%\database_%DB_NAME%_%TIMESTAMP%.sql"

if "%DB_PASS%"=="" (
    "%MYSQL_DUMP%" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --single-transaction --quick --routines --triggers --hex-blob --default-character-set=utf8mb4 %DB_NAME% > "%DUMP_SQL%"
) else (
    "%MYSQL_DUMP%" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --single-transaction --quick --routines --triggers --hex-blob --default-character-set=utf8mb4 %DB_NAME% > "%DUMP_SQL%"
)

if %errorlevel% neq 0 (
    echo [ERROR] MySQL database export failed!
    echo [%LOG_TIME%] [ERROR] MySQL database export failed! >> "%LOG_FILE%"
    goto :ERROR_EXIT
)

for %%F in ("%DUMP_SQL%") do set "SQL_SIZE=%%~zF"
echo [SUCCESS] Database exported successfully. Size: %SQL_SIZE% bytes
echo [%LOG_TIME%] [SUCCESS] Database exported. Size: %SQL_SIZE% bytes >> "%LOG_FILE%"

:: ------------------------------------------------------------------------------
:: 4. สำรองโฟลเดอร์ไฟล์เอกสารและภาพลายเซ็น (web/uploads)
:: ------------------------------------------------------------------------------
echo.
echo [INFO] Step 2/3: Checking source directories for direct archiving...
echo [%LOG_TIME%] [INFO] Reading uploads and action-files directly from source. >> "%LOG_FILE%"

set "UPLOADS_SRC=%DOCZ_DIR%\web\uploads"
if not exist "%UPLOADS_SRC%\" (
    echo [ERROR] Directory web/uploads not found.
    echo [%LOG_TIME%] [ERROR] Directory web/uploads not found. >> "%LOG_FILE%"
    goto :ERROR_EXIT
)

:: Include action attachments using their original relative path in the archive.
set "ACTION_FILES_SRC=%DOCZ_DIR%\server\storage\action-files"
if not exist "%ACTION_FILES_SRC%\" (
    echo [ERROR] Directory server/storage/action-files not found.
    echo [%LOG_TIME%] [ERROR] Directory server/storage/action-files not found. >> "%LOG_FILE%"
    goto :ERROR_EXIT
)

:: สำรองฟอนต์ราชการและไฟล์คอนฟิกสภาพแวดล้อม (.env)
set "FONTS_ARG="
if exist "%DOCZ_DIR%\web\fonts\" set FONTS_ARG="%DOCZ_DIR%\web\fonts"
if exist "%DOCZ_DIR%\server\.env" (
    copy /y "%DOCZ_DIR%\server\.env" "%STAGING_DIR%\server_env_backup.txt" >nul
    if errorlevel 1 (
        echo [ERROR] Failed to stage server environment settings.
        goto :ERROR_EXIT
    )
)

:: ------------------------------------------------------------------------------
:: 5. บีบอัดไฟล์เป็น ZIP Archive และลบ Staging Folder
:: ------------------------------------------------------------------------------
echo.
echo [INFO] Step 3/3: Creating ZIP with 7-Zip, level %ZIP_LEVEL%...
echo [%LOG_TIME%] [INFO] Creating ZIP with 7-Zip, level %ZIP_LEVEL%... >> "%LOG_FILE%"

rem One archive pass: absolute folders keep their base names; the relative
rem action-files path retains server/storage/action-files for existing restores.
pushd "%DOCZ_DIR%"
if errorlevel 1 goto :ERROR_EXIT
"%SEVEN_ZIP%" a -tzip "%WORK_ZIP%" "%STAGING_DIR%\*" "%UPLOADS_SRC%" "server\storage\action-files" %FONTS_ARG% -mx=%ZIP_LEVEL% -bsp1 2>> "%LOG_FILE%"
set "ZIP_RESULT=%errorlevel%"
popd

if not "%ZIP_RESULT%"=="0" (
    echo [ERROR] Failed to create ZIP archive!
    echo [%LOG_TIME%] [ERROR] Failed to create ZIP archive! >> "%LOG_FILE%"
    goto :ERROR_EXIT
)

:: Publish the final ZIP name only when 7-Zip exits without warnings/errors.
move /y "%WORK_ZIP%" "%FINAL_ZIP%" >nul
if errorlevel 1 (
    echo [ERROR] Failed to finalize ZIP archive.
    goto :ERROR_EXIT
)

:: ลบ Staging directory ชั่วคราว
rmdir /s /q "%STAGING_DIR%" 2>nul

for %%Z in ("%FINAL_ZIP%") do set "ZIP_SIZE=%%~zZ"
echo [SUCCESS] Backup Archive Created: %FINAL_ZIP% (Size: %ZIP_SIZE% bytes)
echo [%LOG_TIME%] [SUCCESS] Backup Archive Created: %FINAL_ZIP% (Size: %ZIP_SIZE% bytes) >> "%LOG_FILE%"

:: ------------------------------------------------------------------------------
:: 6. ลบไฟล์สำรองเก่าที่เกินกำหนด (Retention Policy: %RETENTION_DAYS% วัน)
:: ------------------------------------------------------------------------------
echo.
echo [INFO] Cleaning up backups older than %RETENTION_DAYS% days...
echo [%LOG_TIME%] [INFO] Cleaning up backups older than %RETENTION_DAYS% days... >> "%LOG_FILE%"

powershell -NoProfile -Command "try { $ErrorActionPreference = 'Stop'; Get-ChildItem -LiteralPath $env:BACKUP_DIR -Filter 'DOCZ_BACKUP_*.zip' -File | Where-Object { $_.Name -match '^DOCZ_BACKUP_[0-9]{8}_[0-9]{6}\.zip$' -and $_.LastWriteTime -lt (Get-Date).AddDays(-[int]$env:RETENTION_DAYS) } | ForEach-Object { Remove-Item -LiteralPath $_.FullName -Force; Write-Host ('Deleted old backup: ' + $_.Name) } } catch { Write-Error $_; exit 1 }" >> "%LOG_FILE%" 2>&1
if errorlevel 1 (
    echo [WARNING] Backup created, but old backup cleanup failed. See backup.log.
    exit /b 1
)

:: ------------------------------------------------------------------------------
:: 7. สรุปผลการทำงาน
:: ------------------------------------------------------------------------------
for /f "usebackq delims=" %%E in (`powershell -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd HH:mm:ss'"`) do (
    set "END_TIME=%%E"
)

echo.
echo ==============================================================================
echo    [COMPLETED] Backup Finished Successfully!
echo    Archive File: %FINAL_ZIP%
echo    Time:         %END_TIME%
echo ==============================================================================
echo [%END_TIME%] [COMPLETED] Backup Finished Successfully! File: %FINAL_ZIP% >> "%LOG_FILE%"
echo.

exit /b 0

:ERROR_EXIT
if defined WORK_ZIP if exist "%WORK_ZIP%" del /q "%WORK_ZIP%" 2>nul
rmdir /s /q "%STAGING_DIR%" 2>nul
echo.
echo ==============================================================================
echo    [FAILED] Backup failed! Please check logs at: %LOG_FILE%
echo ==============================================================================
echo [%LOG_TIME%] [FAILED] Backup failed! >> "%LOG_FILE%"
echo.
exit /b 1
