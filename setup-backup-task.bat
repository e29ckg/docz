@echo off
setlocal
title DOCZ - Setup Windows Task Scheduler for Daily Backup

echo ==============================================================================
echo    DOCZ - ติดตั้งระบบตั้งเวลาสำรองข้อมูลอัตโนมัติ (Windows Task Scheduler)
echo ==============================================================================
echo.

cd /d "%~dp0"
set "SCRIPT_PATH=%~dp0backup-auto.bat"

if not exist "%SCRIPT_PATH%" (
    echo [ERROR] ไม่พบไฟล์ backup-auto.bat ในไดเรกทอรีนี้!
    pause
    exit /b 1
)

echo รายละเอียดการตั้งเวลา:
echo  - ชื่องาน (Task Name): DOCZ_Daily_Backup
echo  - เวลาทำงาน:           ทุกวัน เวลา 23:00 น.
echo  - คำสั่งที่จะรัน:      %SCRIPT_PATH%
echo.
echo กำลังลงทะเบียนเข้าสู่ Windows Task Scheduler...
echo.

:: สร้าง Scheduled Task ให้รันทุกวันเวลา 23:00 น.
schtasks /create /tn "DOCZ_Daily_Backup" /tr "\"%SCRIPT_PATH%\"" /sc daily /st 23:00 /f /rl HIGHEST

if %errorlevel% equ 0 (
    echo.
    echo ==============================================================================
    echo    [SUCCESS] ลงทะเบียน Task Scheduler สำเร็จเรียบร้อยแล้ว!
    echo ==============================================================================
    echo.
    echo    ระบบจะทำการสำรองข้อมูลอัตโนมัติทุกคืน เวลา 23:00 น.
    echo    ปลายทางตาม BACKUP_DIR ใน backup-auto.bat ค่าเริ่มต้น D:\DOCZ_BACKUP
    echo.
    echo    สามารถทดสอบรันทันทีได้ด้วยคำสั่ง:
    echo    schtasks /run /tn "DOCZ_Daily_Backup"
    echo.
) else (
    echo.
    echo ==============================================================================
    echo    [NOTE] หากเกิดข้อผิดพลาด Access Denied
    echo    กรุณาคลิกขวาที่ไฟล์นี้ แล้วเลือก "Run as Administrator" (รันในฐานะผู้ดูแลระบบ)
    echo ==============================================================================
    echo.
)

pause
