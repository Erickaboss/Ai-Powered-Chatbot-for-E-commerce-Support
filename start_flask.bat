@echo off
title Flask ML API — AI Chatbot
color 0A

:: Wait for network/XAMPP to be fully ready
timeout /t 15 /nobreak >nul

:: Start Flask ML API with auto-restart loop
start "Flask ML API" cmd /k "C:\xampp\htdocs\ecommerce-chatbot\chatbot-ml\run_forever.bat"
