@echo off
title AI Chatbot ML Server — E-commerce Support
color 0A

echo ============================================
echo   AI-Powered Chatbot For E-commerce Support
echo   Starting All Services...
echo ============================================
echo.

:: Start XAMPP Apache + MySQL
echo [1/3] Starting XAMPP Apache and MySQL...
"C:\xampp\xampp_start.exe" >nul 2>&1
timeout /t 5 /nobreak >nul

:: Start Flask ML API (auto-restart loop)
echo [2/3] Starting Flask ML API on port 5000...
start "Flask ML API" cmd /k "C:\xampp\htdocs\ecommerce-chatbot\chatbot-ml\run_forever.bat"
timeout /t 4 /nobreak >nul

:: Open browser
echo [3/3] Opening store in browser...
start "" "http://localhost/ecommerce-chatbot/"

echo.
echo ============================================
echo   All services started!
echo   Store  : http://localhost/ecommerce-chatbot/
echo   Admin  : http://localhost/ecommerce-chatbot/admin/
echo   ML API : http://localhost:5000/health
echo ============================================
echo.
