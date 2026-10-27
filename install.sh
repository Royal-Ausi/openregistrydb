#!/bin/bash

echo "Open Registry Database - Installation Script"
echo "==========================================="
echo

echo "Checking if Composer is installed..."
if ! command -v composer &> /dev/null; then
    echo "Composer is not installed. Please install Composer first."
    echo "Download from: https://getcomposer.org/download/"
    echo
    exit 1
fi

echo "Composer found. Installing dependencies..."
echo

composer install

if [ $? -eq 0 ]; then
    echo
    echo "✓ Dependencies installed successfully!"
    echo
    echo "You can now:"
    echo "- Run install.php to check system requirements"
    echo "- Access admin_dashboard.php"
    echo "- Use Excel export functionality"
else
    echo
    echo "✗ Failed to install dependencies."
    echo "Check the error messages above."
fi

echo

