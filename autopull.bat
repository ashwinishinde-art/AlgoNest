@echo off
:: Auto-pull from GitHub every 30 seconds
:: Run this on the server machine alongside start.bat

echo AlgoNest Auto-Pull — watching for remote changes...
echo Press Ctrl+C to stop.
echo.

:loop
git -C "C:\Users\suvar.LAPPY\OneDrive\Desktop\algonest\AlgoNest" fetch origin >nul 2>&1
for /f %%i in ('git -C "C:\Users\suvar.LAPPY\OneDrive\Desktop\algonest\AlgoNest" rev-parse HEAD') do set LOCAL=%%i
for /f %%i in ('git -C "C:\Users\suvar.LAPPY\OneDrive\Desktop\algonest\AlgoNest" rev-parse origin/main') do set REMOTE=%%i

if not "%LOCAL%"=="%REMOTE%" (
    echo [%time%] New changes detected — pulling...
    git -C "C:\Users\suvar.LAPPY\OneDrive\Desktop\algonest\AlgoNest" pull origin main
    echo [%time%] Done. Live.
    echo.
)

timeout /t 30 /nobreak >nul
goto loop
