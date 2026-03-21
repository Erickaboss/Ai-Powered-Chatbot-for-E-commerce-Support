@echo off
title Install Startup — AI Chatbot
color 0B

echo Installing chatbot auto-start on Windows startup...

:: Copy start_chatbot.bat to Windows startup folder
set STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup
copy /Y "%~dp0start_chatbot.bat" "%STARTUP%\start_chatbot.bat"

echo.
echo Done! The chatbot will now start automatically when Windows boots.
echo Startup folder: %STARTUP%
echo.
echo To remove auto-start, delete:
echo %STARTUP%\start_chatbot.bat
echo.
pause
