@echo off
cd /d "%~dp0"
where node >nul 2>nul
if errorlevel 1 (
  echo Install Node.js 22 or newer, then run this file again.
  pause
  exit /b 1
)
if not exist "bridge\node_modules\whatsapp-web.js\package.json" (
  echo Installing WhatsApp dependencies. This first step needs internet.
  pushd bridge
  call npm ci
  if errorlevel 1 (
    popd
    pause
    exit /b 1
  )
  popd
)
echo Starting local WhatsApp. Keep this window open while testing.
node bridge/local.js
pause
