@echo off
cd /d "%~dp0"
if exist "%LOCALAPPDATA%\NunnoTools\cloudflared.exe" set "CLOUDFLARED_PATH=%LOCALAPPDATA%\NunnoTools\cloudflared.exe"
if not defined CLOUDFLARED_PATH if exist "%LOCALAPPDATA%\Packages\OpenAI.Codex_2p2nqsd0c76g0\LocalCache\Local\NunnoTools\cloudflared.exe" set "CLOUDFLARED_PATH=%LOCALAPPDATA%\Packages\OpenAI.Codex_2p2nqsd0c76g0\LocalCache\Local\NunnoTools\cloudflared.exe"
echo ==========================================================
echo Starting Nunno Live WhatsApp Bridge for omnidesk.lalagpt.pk
echo ==========================================================
echo Keep this terminal open while using the online dashboard.
echo Real WhatsApp messages will be sent directly through your phone.
node bridge/pc-connect.js
pause
