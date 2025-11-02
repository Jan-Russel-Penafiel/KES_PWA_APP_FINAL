@echo off
REM KES-SMART Automatic Cache Cleanup for Windows
REM Run this batch file to perform cache cleanup

echo Starting KES-SMART Cache Cleanup...
echo.

REM Get the directory where this batch file is located
set SCRIPT_DIR=%~dp0
set PHP_SCRIPT=%SCRIPT_DIR%cache_cleanup.php

REM Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP is not installed or not in PATH
    echo Please install PHP or add it to your system PATH
    pause
    exit /b 1
)

REM Check if the PHP script exists
if not exist "%PHP_SCRIPT%" (
    echo ERROR: Cache cleanup script not found at %PHP_SCRIPT%
    pause
    exit /b 1
)

echo Running cache cleanup...
echo.

REM Run the PHP script
php "%PHP_SCRIPT%"

REM Check if the script ran successfully
if %errorlevel% equ 0 (
    echo.
    echo Cache cleanup completed successfully!
) else (
    echo.
    echo Cache cleanup failed with error code %errorlevel%
)

echo.
echo Press any key to exit...
pause >nul