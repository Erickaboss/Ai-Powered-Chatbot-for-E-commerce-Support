@echo off
title Install Auto-Start — AI Chatbot
color 0B

echo ============================================
echo   AI-Powered Chatbot For E-commerce Support
echo   Installing Auto-Start on Windows Boot...
echo ============================================
echo.

:: Must run as Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Please right-click this file and choose "Run as administrator"
    pause
    exit /b 1
)

:: ── 1. Install Apache as Windows Service ──
echo [1/4] Installing Apache as Windows Service...
"C:\xampp\apache\bin\httpd.exe" -k install >nul 2>&1
sc config Apache2.4 start= auto >nul 2>&1
net start Apache2.4 >nul 2>&1
echo     Apache service set to AUTO-START.

:: ── 2. Install MySQL as Windows Service ──
echo [2/4] Installing MySQL as Windows Service...
"C:\xampp\mysql\bin\mysqld.exe" --install MySQL --defaults-file="C:\xampp\mysql\bin\my.ini" >nul 2>&1
sc config MySQL start= auto >nul 2>&1
net start MySQL >nul 2>&1
echo     MySQL service set to AUTO-START.

:: ── 3. Copy Flask startup script to Windows Startup folder ──
echo [3/4] Installing Flask ML auto-start...
set STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup
copy /Y "%~dp0start_flask.bat" "%STARTUP%\start_flask.bat" >nul 2>&1
echo     Flask startup script installed.

:: ── 4. Create a scheduled task for Flask (more reliable than Startup folder) ──
echo [4/4] Creating scheduled task for Flask ML API...
schtasks /delete /tn "ChatbotFlaskML" /f >nul 2>&1
schtasks /create /tn "ChatbotFlaskML" /tr "C:\xampp\htdocs\ecommerce-chatbot\start_flask.bat" /sc onlogon /delay 0000:30 /ru "%USERNAME%" /f >nul 2>&1
echo     Scheduled task created (runs 30s after login).

echo.
echo ============================================
echo   DONE! Everything will auto-start on boot.
echo.
echo   Apache  : Windows Service (auto)
echo   MySQL   : Windows Service (auto)
echo   Flask   : Scheduled Task (30s after login)
echo ============================================
echo.
pause
