@echo off
setlocal enabledelayedexpansion
title DOCZ - Restore Backup Utility

echo ==============================================================================
echo    DOCZ - เครื่องมือกู้คืนข้อมูลจากไฟล์สำรอง (Restore Backup)
echo ==============================================================================
echo.

set "BACKUP_DIR=D:\DOCZ_BACKUP"
set "DOCZ_DIR=%~dp0"
set "DOCZ_DIR=%DOCZ_DIR:~0,-1%"

rem Use 7-Zip for ZIP64 archives and files larger than 2 GB.
set "SEVEN_ZIP=%ProgramFiles%\7-Zip\7z.exe"
if not exist "%SEVEN_ZIP%" set "SEVEN_ZIP=%ProgramFiles(x86)%\7-Zip\7z.exe"
if not exist "%SEVEN_ZIP%" (
    for /f "delims=" %%Z in ('where 7z.exe 2^>nul') do (
        if not exist "!SEVEN_ZIP!" set "SEVEN_ZIP=%%Z"
    )
)
if not exist "%SEVEN_ZIP%" (
    echo [ERROR] 7-Zip was not found. Install 7-Zip from https://www.7-zip.org/
    pause
    exit /b 1
)
"%SEVEN_ZIP%" i >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Cannot run 7-Zip at "%SEVEN_ZIP%".
    pause
    exit /b 1
)

if not exist "%BACKUP_DIR%" (
    echo [ERROR] ไม่พบโฟลเดอร์สำรองข้อมูลที่: %BACKUP_DIR%
    pause
    exit /b 1
)

echo รายการไฟล์สำรองที่มีอยู่ใน %BACKUP_DIR%:
echo ------------------------------------------------------------------------------
dir /b /o-d "%BACKUP_DIR%\DOCZ_BACKUP_*.zip" 2>nul
echo ------------------------------------------------------------------------------
echo.

set /p "TARGET_ZIP=กรุณากรอกชื่อไฟล์ ZIP ที่ต้องการกู้คืน (เช่น DOCZ_BACKUP_20261104_060000.zip): "

if "%TARGET_ZIP%"=="" (
    echo [CANCEL] ไม่ได้ระบุชื่อไฟล์ ยกเลิกการกู้คืน
    pause
    exit /b 0
)

set "FULL_ZIP_PATH=%BACKUP_DIR%\%TARGET_ZIP%"
if not exist "%FULL_ZIP_PATH%" (
    echo [ERROR] ไม่พบไฟล์: %FULL_ZIP_PATH%
    pause
    exit /b 1
)

echo.
echo [WARNING] การกู้คืนข้อมูลจะทำการเขียนทับฐานข้อมูลและไฟล์เอกสารปัจจุบัน!
set /p "CONFIRM=คุณแน่ใจว่าต้องการดำเนินการต่อหรือไม่ (พิมพ์ Y เพื่อยืนยัน): "
if /i not "%CONFIRM%"=="Y" (
    echo [CANCEL] ยกเลิกการกู้คืน
    pause
    exit /b 0
)

set "TEMP_RESTORE=%BACKUP_DIR%\temp_restore"
mkdir "%TEMP_RESTORE%" 2>nul

echo.
echo [1/3] กำลังแตกไฟล์สำรอง...
"%SEVEN_ZIP%" x -tzip "%FULL_ZIP_PATH%" "-o%TEMP_RESTORE%" -y -bsp1
if errorlevel 1 (
    echo [ERROR] Archive extraction failed. Database restore was not started.
    pause
    exit /b 1
)

echo [2/3] กำลังกู้คืนฐานข้อมูล MySQL...
set "MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe"
for %%S in ("%TEMP_RESTORE%\*.sql") do (
    set "SQL_FILE=%%S"
)

if exist "!SQL_FILE!" (
    echo นำเข้าข้อมูลจาก !SQL_FILE!...
    "%MYSQL_EXE%" -u root docz < "!SQL_FILE!"
    echo [SUCCESS] กู้คืนฐานข้อมูลเรียบร้อยแล้ว
) else (
    echo [WARNING] ไม่พบไฟล์ .sql ในชุดข้อมูลสำรอง
)

echo [3/3] กำลังกู้คืนไฟล์เอกสารและลายเซ็น (web/uploads)...
if exist "%TEMP_RESTORE%\uploads" (
    robocopy "%TEMP_RESTORE%\uploads" "%DOCZ_DIR%\web\uploads" /E /R:2 /W:3 /NP /NDL /NFL >nul
    echo [SUCCESS] กู้คืนไฟล์เอกสารถึงโฟลเดอร์ web/uploads เรียบร้อยแล้ว
)

rem Older archives may not contain action attachments.
if exist "%TEMP_RESTORE%\server\storage\action-files\" (
    echo [INFO] Restoring action attachments...
    robocopy "%TEMP_RESTORE%\server\storage\action-files" "%DOCZ_DIR%\server\storage\action-files" /E /XJ /R:2 /W:3 /NP /NDL /NFL
    if !errorlevel! geq 8 (
        echo [ERROR] Action attachment restore failed. Extracted files kept at: %TEMP_RESTORE%
        pause
        exit /b 1
    )
    echo [SUCCESS] Action attachments restored.
) else (
    echo [WARNING] This archive has no action attachments. Existing files were kept.
)

rmdir /s /q "%TEMP_RESTORE%" 2>nul

echo.
echo ==============================================================================
echo    [COMPLETED] กู้คืนข้อมูลจาก %TARGET_ZIP% เสร็จสมบูรณ์!
echo ==============================================================================
echo.
pause
