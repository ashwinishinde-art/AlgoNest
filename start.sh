#!/usr/bin/env bash

# ANSI Escape Codes for Styling
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m' # No Color

echo -e "${BOLD}${CYAN}=====================================================${NC}"
echo -e "${BOLD}${CYAN}         AlgoNest - Server Starter            ${NC}"
echo -e "${BOLD}${CYAN}=====================================================${NC}"

# Detect Host IP (Local Network IPv4)
LOCAL_IP=$(ip route get 1.1.1.1 2>/dev/null | grep -oP 'src \K[0-9.]+')
if [ -z "$LOCAL_IP" ]; then
    LOCAL_IP=$(hostname -I | awk '{print $1}')
fi

if [ -z "$LOCAL_IP" ]; then
    LOCAL_IP="127.0.0.1"
fi

# Check PHP installation
if ! command -v php &> /dev/null; then
    echo -e "${RED}[ERROR] PHP is not installed. Please install PHP 8.3+ to run the backend.${NC}"
    exit 1
fi

# Check MySQL Connection
echo -e "${YELLOW}Verifying Database Connection...${NC}"
DB_CHECK=$(php -r "
    require 'backend/config/database.php';
    try {
        \$db = new Database();
        \$conn = \$db->getConnection();
        if (\$conn) {
            echo 'SUCCESS';
        } else {
            echo 'FAILED';
        }
    } catch (Exception \$e) {
        echo 'FAILED';
    }
" 2>/dev/null)

if [ "$DB_CHECK" = "SUCCESS" ]; then
    echo -e "${GREEN}[OK] Database connection successful!${NC}"
else
    echo -e "${YELLOW}[WARNING] Database connection failed. Please ensure MySQL is running and credentials in backend/config/database.php are correct.${NC}"
fi

# Configuration ports
BACKEND_PORT=8080
FRONTEND_PORT=8081

# Clean up existing processes running on those ports
kill_port() {
    local port=$1
    local pid=$(lsof -t -i:$port)
    if [ ! -z "$pid" ]; then
        echo -e "${YELLOW}Port $port is in use. Stopping process $pid...${NC}"
        kill -9 $pid 2>/dev/null
    fi
}
kill_port $BACKEND_PORT
kill_port $FRONTEND_PORT

# Start Backend Server
echo -e "${YELLOW}Starting PHP Backend REST API on 0.0.0.0:$BACKEND_PORT...${NC}"
php -S 0.0.0.0:$BACKEND_PORT -t backend/public backend/public/router.php > backend.log 2>&1 &
BACKEND_PID=$!

# Start Frontend Server
echo -e "${YELLOW}Starting Frontend Web Server on 0.0.0.0:$FRONTEND_PORT...${NC}"
php -S 0.0.0.0:$FRONTEND_PORT -t frontend frontend/router.php > frontend.log 2>&1 &
FRONTEND_PID=$!

# Ensure they started successfully
sleep 1.5
if ! kill -0 $BACKEND_PID 2>/dev/null; then
    echo -e "${RED}[ERROR] Backend failed to start. Check backend.log for details.${NC}"
    exit 1
fi

if ! kill -0 $FRONTEND_PID 2>/dev/null; then
    echo -e "${RED}[ERROR] Frontend failed to start. Check frontend.log for details.${NC}"
    kill $BACKEND_PID 2>/dev/null
    exit 1
fi

echo -e "\n${BOLD}${GREEN}✔ Servers are successfully running!${NC}\n"
echo -e "${BOLD}${BLUE}-----------------------------------------------------${NC}"
echo -e "${BOLD}1. LOCAL DEVICE ACCESS (This Machine)${NC}"
echo -e "   Frontend UI:  ${CYAN}http://localhost:$FRONTEND_PORT${NC}"
echo -e "   Backend API:  ${CYAN}http://localhost:$BACKEND_PORT/api${NC}"
echo -e "${BOLD}${BLUE}-----------------------------------------------------${NC}"
echo -e "${BOLD}2. MULTI-DEVICE ACCESS (Other Devices on Same Network)${NC}"
echo -e "   Make sure your other devices are connected to the same Wi-Fi/Network."
echo -e "   Frontend UI:  ${BOLD}${GREEN}http://$LOCAL_IP:$FRONTEND_PORT${NC}"
echo -e "   Backend API:  ${GREEN}http://$LOCAL_IP:$BACKEND_PORT/api${NC}"
echo -e "${BOLD}${BLUE}-----------------------------------------------------${NC}"
echo -e "${BOLD}3. COMPILING & EXECUTING CODE${NC}"
echo -e "   C++ code is compiled server-side using ${BOLD}g++${NC} via the backend API."
echo -e "   All devices on the network can run code — no local setup needed."
echo -e "${BOLD}${CYAN}=====================================================${NC}"
echo -e "Press ${RED}Ctrl+C${NC} to stop all servers."

# Graceful cleanup
cleanup() {
    echo -e "\n\n${YELLOW}Shutting down servers...${NC}"
    kill $BACKEND_PID 2>/dev/null
    kill $FRONTEND_PID 2>/dev/null
    echo -e "${GREEN}Servers stopped. Goodbye!${NC}"
    exit 0
}
trap cleanup SIGINT SIGTERM

# Keep script running
wait $BACKEND_PID $FRONTEND_PID
