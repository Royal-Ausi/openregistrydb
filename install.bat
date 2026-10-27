@echo off
echo Open Registry Database - Installation Script
echo ===========================================
echo.

echo Checking if Composer is installed...
composer --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Composer is not installed. Please install Composer first.
    echo Download from: https://getcomposer.org/download/
    echo.
    pause
    exit /b 1
)

echo Composer found. Installing dependencies...
echo.

composer install

if %errorlevel% equ 0 (
    echo.
    echo ✓ Dependencies installed successfully!
    echo.
    echo You can now:
    echo - Run install.php to check system requirements
    echo - Access admin_dashboard.php
    echo - Use Excel export functionality
) else (
    echo.
    echo ✗ Failed to install dependencies.
    echo Check the error messages above.
)

echo.
pause

