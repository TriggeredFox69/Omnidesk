@echo off
cd /d "%~dp0"
if exist "%LOCALAPPDATA%\NunnoTools\cloudflared.exe" set "CLOUDFLARED_PATH=%LOCALAPPDATA%\NunnoTools\cloudflared.exe"
echo Close the regular local launcher first. This starts both local and online WhatsApp.
node bridge/pc-connect.js
pause
