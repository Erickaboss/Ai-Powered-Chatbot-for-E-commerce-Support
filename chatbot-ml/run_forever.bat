@echo off
title Flask ML API — Auto Restart
color 0A
cd /d "C:\xampp\htdocs\ecommerce-chatbot\chatbot-ml"

:loop
echo [%date% %time%] Starting Flask ML API...
python app.py
echo [%date% %time%] Flask stopped. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
