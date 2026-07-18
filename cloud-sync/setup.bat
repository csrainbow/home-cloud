@echo off
title Cloud Sync Uploader Installer
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
if %errorlevel% equ 0 (
    echo.
    echo Installation complete. Starting app...
    call "%~dp0start.bat"
) else (
    echo.
    echo Installation failed.
    pause
)
