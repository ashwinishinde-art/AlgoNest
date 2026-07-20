@echo off
setlocal enabledelayedexpansion
title AlgoNest - Server Starter

:: ============================================================
::          AlgoNest - Windows Server Starter
:: ============================================================

echo.
echo =====================================================
echo        AlgoNest - Server Starter
echo =====================================================
echo.

:: ── Check we are in the right directory ──────────────────────
if not exist "backend\public\index.php" (
    echo [ERROR] Please run this script from the DSA-website root folder.
    echo         e.g. cd C:\path\to\DSA-website  then  start.bat
    pause
    exit /b 1
)

:: ── Check PHP ────────────────────────────────────────────────
where php >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP not found in PATH.
    echo         Install PHP 8.3+ and add it to your System PATH.
    echo         See WINDOWS_SETUP.txt for instructions.
    pause
    exit /b 1
)
for /f "tokens=*" %%v in ('php -r "echo PHP_VERSION;" 2^>nul') do set PHP_VER=%%v
echo [OK] PHP found: %PHP_VER%

:: ── Check g++ ────────────────────────────────────────────────
where g++ >nul 2>&1
if errorlevel 1 (
    echo [WARNING] g++ not found in PATH.
    echo           C++ compilation will not work until g++ is installed.
    echo           See WINDOWS_SETUP.txt for MSYS2/MinGW instructions.
    echo.
) else (
    for /f "tokens=*" %%v in ('g++ --version 2^>nul ^| findstr /r "[0-9]"') do (
        set GPP_VER=%%v
        goto :gpp_found
    )
    :gpp_found
    echo [OK] g++ found: %GPP_VER%
)

:: ── Detect local IP ──────────────────────────────────────────
set LOCAL_IP=127.0.0.1
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /r /c:"IPv4 Address" ^| findstr /v "127.0.0.1"') do (
    set RAW_IP=%%a
    :: Trim leading space
    for /f "tokens=*" %%b in ("!RAW_IP!") do set LOCAL_IP=%%b
    goto :ip_found
)
:ip_found

:: ── Check database connection ─────────────────────────────────
echo Verifying Database Connection...
for /f "tokens=*" %%r in ('php -r "require \"backend/config/database.php\"; try { $db = new Database(); $c = $db->getConnection(); echo $c ? \"SUCCESS\" : \"FAILED\"; } catch(Exception $e){ echo \"FAILED\"; }" 2^>nul') do set DB_CHECK=%%r

if "!DB_CHECK!"=="SUCCESS" (
    echo [OK] Database connection successful!
) else (
    echo [WARNING] Database connection failed.
    echo           Make sure MySQL is running and credentials in
    echo           backend\config\database.php are correct.
    echo           See WINDOWS_SETUP.txt for database setup steps.
)
echo.

:: ── Kill anything on ports 8080 / 8081 ───────────────────────
set BACKEND_PORT=8080
set FRONTEND_PORT=8081

for /f "tokens=5" %%p in ('netstat -aon ^| findstr ":%BACKEND_PORT% " ^| findstr "LISTENING"') do (
    echo [INFO] Port %BACKEND_PORT% in use by PID %%p. Stopping...
    taskkill /PID %%p /F >nul 2>&1
)
for /f "tokens=5" %%p in ('netstat -aon ^| findstr ":%FRONTEND_PORT% " ^| findstr "LISTENING"') do (
    echo [INFO] Port %FRONTEND_PORT% in use by PID %%p. Stopping...
    taskkill /PID %%p /F >nul 2>&1
)

:: ── Start Backend ─────────────────────────────────────────────
echo Starting PHP Backend REST API on 0.0.0.0:%BACKEND_PORT%...
start "DSA Backend :8080" /min cmd /c "php -S 0.0.0.0:%BACKEND_PORT% -t backend\public backend\public\router.php > backend.log 2>&1"

:: ── Start Frontend ────────────────────────────────────────────
echo Starting Frontend Web Server on 0.0.0.0:%FRONTEND_PORT%...
start "DSA Frontend :8081" /min cmd /c "php -S 0.0.0.0:%FRONTEND_PORT% -t frontend frontend\router.php > frontend.log 2>&1"

:: ── Wait for servers to initialise ───────────────────────────
timeout /t 2 /nobreak >nul

:: ── Verify backend is responding ─────────────────────────────
curl -s --max-time 3 http://localhost:%BACKEND_PORT%/api >nul 2>&1
if errorlevel 1 (
    echo [WARNING] Backend may not have started correctly.
    echo           Check backend.log for details.
) else (
    echo [OK] Backend is responding.
)

:: ── Print access info ─────────────────────────────────────────
echo.
echo =====================================================
echo   Servers are running!
echo =====================================================
echo.
echo  1. LOCAL DEVICE ACCESS (This Machine)
echo     Frontend UI : http://localhost:%FRONTEND_PORT%
echo     Backend API : http://localhost:%BACKEND_PORT%/api
echo.
echo  2. MULTI-DEVICE ACCESS (Other Devices on Same Network)
echo     Frontend UI : http://%LOCAL_IP%:%FRONTEND_PORT%
echo     Backend API : http://%LOCAL_IP%:%BACKEND_PORT%/api
echo.
echo  3. COMPILING ^& EXECUTING CODE
echo     C++ code is compiled server-side using g++ via the backend API.
echo     All devices on the network can run code - no local setup needed.
echo.
echo =====================================================
echo  Logs: backend.log  /  frontend.log
echo  To stop: close the two minimised CMD windows,
echo  or run:  taskkill /IM php.exe /F
echo =====================================================
echo.
echo Press any key to open the site in your browser...
pause >nul
start http://localhost:%FRONTEND_PORT%
