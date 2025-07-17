@echo off
REM Windows Batch Script to run the auto-absent marking script
REM This file should be scheduled to run daily at 4:16 PM (Monday-Friday)

REM Change this path to match your XAMPP installation
set PHP_PATH=C:\xampp\php\php.exe
set SCRIPT_PATH=C:\xampp\htdocs\smart\cron\mark_absent.php
set LOG_PATH=C:\xampp\htdocs\smart\logs\batch_execution.log

REM Check if PHP exists
if not exist "%PHP_PATH%" (
    echo ERROR: PHP not found at %PHP_PATH% >> "%LOG_PATH%"
    echo ERROR: PHP not found at %PHP_PATH%
    pause
    exit /b 1
)

REM Check if script exists
if not exist "%SCRIPT_PATH%" (
    echo ERROR: Script not found at %SCRIPT_PATH% >> "%LOG_PATH%"
    echo ERROR: Script not found at %SCRIPT_PATH%
    pause
    exit /b 1
)

REM Log start time
echo [%date% %time%] Starting auto-absent script... >> "%LOG_PATH%"

REM Run the script and capture output
"%PHP_PATH%" "%SCRIPT_PATH%" >> "%LOG_PATH%" 2>&1

REM Check if the script ran successfully
if %ERRORLEVEL% equ 0 (
    echo [%date% %time%] Auto-absent script completed successfully >> "%LOG_PATH%"
) else (
    echo [%date% %time%] Auto-absent script failed with error code %ERRORLEVEL% >> "%LOG_PATH%"
)

REM Optional: Display completion message (comment out for silent execution)
echo Auto-absent script execution completed. Check log file for details.
