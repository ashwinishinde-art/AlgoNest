@echo off
setlocal

set CF="C:\Program Files (x86)\cloudflared\cloudflared.exe"

echo.
echo  AlgoNest ^| Cloudflare Tunnel
echo  ================================
echo.
echo  This will expose BOTH servers publicly via Cloudflare.
echo.
echo  Starting tunnels...
echo  Wait 5-10 seconds for the URLs to appear in each window.
echo.

start "AlgoNest BACKEND  [8080]" cmd /k "%CF% tunnel --url http://localhost:8080"
start "AlgoNest FRONTEND [8081]" cmd /k "%CF% tunnel --url http://localhost:8081"

echo.
echo  ================================================================
echo  AFTER the URLs appear in both windows:
echo.
echo  1. Copy the BACKEND URL (from the [8080] window)
echo     It looks like: https://xxxx-xxxx.trycloudflare.com
echo.
echo  2. Open: frontend\assets\js\app.js
echo.
echo  3. Replace line 1 and 2 with:
echo.
echo     const API_BASE_URL = `https://YOUR-BACKEND-URL/api`;
echo     const RUNNER_URL   = `https://YOUR-BACKEND-URL/api/run`;
echo.
echo  4. Share the FRONTEND URL (from the [8081] window) with anyone.
echo     They open it in their browser — no local setup needed.
echo.
echo  NOTE: URLs change every time you restart this script.
echo        Keep both windows open while the tunnel is active.
echo  ================================================================
echo.
pause
