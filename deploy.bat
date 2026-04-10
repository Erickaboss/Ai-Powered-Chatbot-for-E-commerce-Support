@echo off
REM =====================================================
REM E-Commerce Chatbot - Deployment Script
REM Version: 1.0
REM Date: April 3, 2026
REM =====================================================

echo.
echo ========================================
echo  E-Commerce Chatbot Deployment
echo ========================================
echo.

REM Check if XAMPP is running
echo [1/5] Checking XAMPP status...
tasklist /FI "IMAGENAME eq apache.exe" 2>NUL | find /I /N "apache.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo Apache is running - OK
) else (
    echo WARNING: Apache may not be running. Please start XAMPP.
    pause
)

REM Backup database
echo.
echo [2/5] Backing up database...
set BACKUP_FILE=backup_%DATE:~-4,4%%DATE:~-7,2%%DATE:~-10,2%_%TIME:~0,2%%TIME:~3,2%.sql
set BACKUP_FILE=%BACKUP_FILE: =0%
"C:\xampp\mysql\bin\mysqldump.exe" -u root ecommerce_chatbot > backups\%BACKUP_FILE%
if "%ERRORLEVEL%"=="0" (
    echo Database backed up to: backups\%BACKUP_FILE%
) else (
    echo ERROR: Database backup failed!
    pause
    exit /b 1
)

REM Run database migration
echo.
echo [3/5] Running database migration...
type feature_enhancements.sql | "C:\xampp\mysql\bin\mysql.exe" -u root ecommerce_chatbot
if "%ERRORLEVEL%"=="0" (
    echo Database migration completed successfully
) else (
    echo ERROR: Database migration failed!
    pause
    exit /b 1
)

REM Verify tables
echo.
echo [4/5] Verifying database tables...
"C:\xampp\mysql\bin\mysql.exe" -u root -e "USE ecommerce_chatbot; SHOW TABLES;" > verify_tables.txt
if "%ERRORLEVEL%"=="0" (
    echo Database verification completed
    type verify_tables.txt
) else (
    echo ERROR: Database verification failed!
)

REM Clear cache
echo.
echo [5/5] Clearing cache...
if exist "cache\" (
    del /Q cache\*.* 2>nul
    echo Cache cleared
) else (
    echo No cache directory found
)

echo.
echo ========================================
echo  Deployment Completed Successfully!
echo ========================================
echo.
echo Next steps:
echo 1. Open http://localhost/ecommerce-chatbot
echo 2. Test chatbot features
echo 3. Check admin dashboard
echo 4. Review error logs if any issues
echo.
echo Documentation files created:
echo - COMPLETE_IMPLEMENTATION_SUMMARY.md
echo - ADVANCED_CHATBOT_FEATURES_COMPLETE.md
echo - FEATURE_ENHANCEMENTS_GUIDE.md
echo - QUICK_START_NEW_FEATURES.md
echo.
pause
