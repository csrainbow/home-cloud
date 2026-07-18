@echo off
title Cloud Sync Uploader
cd /d "%~dp0"

:: Kill old processes
powershell -NoProfile -Command "Get-Process -Name 'node','TrayApp','CloudSyncApp' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue"
timeout /t 1 /nobreak >nul

:: Always recompile
"%SystemRoot%\Microsoft.NET\Framework64\v4.0.30319\csc.exe" /nologo /target:winexe /win32icon:app.ico /reference:System.dll /reference:System.Windows.Forms.dll /reference:System.Drawing.dll /reference:System.Net.Http.dll /out:"%~dp0CloudSyncApp.exe" "%~dp0CloudSyncApp.cs"

:: Launch app
start /b "" "%~dp0CloudSyncApp.exe"
exit
