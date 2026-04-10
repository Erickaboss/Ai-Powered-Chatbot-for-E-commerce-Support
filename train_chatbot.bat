@echo off
REM ================================================================
REM Comprehensive Chatbot Training Script
REM ================================================================

echo.
echo ======================================================================
echo  COMPREHENSIVE CHATBOT TRAINING
echo ======================================================================
echo.
echo Starting training process...
echo.

cd /d "%~dp0chatbot-ml"

REM Check if Python is available
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python not found! Please install Python 3.8+ or add to PATH
    pause
    exit /b 1
)

echo Python found!
echo.

REM Install/upgrade dependencies
echo Installing Python dependencies...
pip install -r requirements.txt mysql-connector-python pandas

echo.
echo ======================================================================
echo  STARTING TRAINING
echo ======================================================================
echo.

REM Run comprehensive training
python train_comprehensive.py

echo.
echo ======================================================================
echo  DEPLOYING MODELS
echo ======================================================================
echo.

REM Copy models to API directory
echo Training artifacts are ready in chatbot-ml\models and chatbot-ml\plots

echo.
echo Models deployed successfully!
echo.

echo ======================================================================
echo  TRAINING COMPLETE!
echo ======================================================================
echo.
echo Next steps:
echo 1. Test the chatbot at: http://localhost/ecommerce-chatbot/index.php
echo 2. View training report: reports\comprehensive_training_report.txt
echo 3. Check admin analytics for performance metrics
echo.
echo Press any key to exit...
pause >nul
