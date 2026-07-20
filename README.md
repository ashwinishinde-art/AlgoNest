# DSA Online Judge

A minimal, beautiful, and secure Online Judge system for C++ developers. This platform evaluates solutions directly on the user's computer via a local runner and submits only the results back to the server, solving performance, sandbox security, and infrastructure constraints.

## Architecture

1. **Frontend**: HTML5, CSS3 (Tailwind CSS), Monaco Editor, Alpine.js / Vanilla JS.
2. **Backend**: PHP 8.3 REST API (routing, database authentication, submissions log, problem library).
3. **Database**: MySQL 8.
4. **Local Runner**: Lightweight Python 3 runner script running on user's machine on port 8000. It compiles `.cpp` files with local `g++` and runs test cases locally.

## Setup Instructions

### Quick Start (Multi-Device Local Server)
A helper script is provided to start both the Frontend and Backend servers simultaneously, bound to all network interfaces (`0.0.0.0`) so that other devices on your local network (Wi-Fi/LAN) can access the project.

1. Ensure MySQL is running and set up (see *Database Setup* below).
2. Execute the start script:
   ```bash
   ./start.sh
   ```
3. The script will output the network URLs for your host machine (e.g., `http://<your-local-ip>:8081` for the Frontend, and `http://<your-local-ip>:8080` for the Backend REST API).

---

### Manual Setup

#### 1. Database Setup
1. Import the database schema from `backend/src/Database/schema.sql` into MySQL.
2. Run the initial seeds from `backend/src/Database/seed.sql`.
3. Update database credentials in `backend/config/database.php`.

#### 2. Start PHP Backend REST API
Run the PHP built-in server bound to `0.0.0.0` so other devices can access it:
```bash
cd backend/public
php -S 0.0.0.0:8080
```

#### 3. Start Frontend Web Server
Serve the frontend directory via PHP or another static web server (like Python `http.server`) bound to `0.0.0.0`:
```bash
cd frontend
php -S 0.0.0.0:8081
```

#### 4. Local Compiler Runner (Must run on the coding machine)
The platform evaluates code using a local runner script. On the machine where you are writing/compiling code, start the local runner:
```bash
python3 local-runner/runner.py
```
This starts an HTTP server at `http://localhost:8000`. The frontend in the browser connects to `localhost:8000` to execute the C++ files locally.

---

## Multi-Device Access Guidelines

To access the platform from other devices (e.g., phones, tablets, or other laptops) on the same local network:

1. **Connect to the same network**: Make sure the host machine running the backend/database and the accessing devices are connected to the same Wi-Fi network or mobile hotspot.
2. **Find the Host IP Address**:
   - On Linux/macOS: Run `hostname -I` or `ip a` to find the private IPv4 address (e.g., `192.168.x.x` or `172.16.x.x`).
   - On Windows: Run `ipconfig` in CMD to find the `IPv4 Address`.
3. **Access via browser**:
   - Open a browser on the other device and enter the frontend URL: `http://<HOST-IP>:8081` (e.g., `http://172.16.83.96:8081`).
4. **Compile code on other devices**:
   - If you want to write and run code on another computer (client computer) accessing this host, that client computer must run the local compilation runner (`python3 local-runner/runner.py`) so the browser can send compilation requests to `localhost:8000` on that device.
   - If you are just viewing the leaderboard, problems, or submissions on a phone, no local runner is needed.

---

## Directory Structure

```text
DSA-website/
├── backend/                  # PHP REST API Backend
│   ├── config/               # Configuration files
│   │   ├── database.php      # DB configuration
│   │   └── jwt.php           # JWT encryption helpers
│   ├── public/               # Public entry points
│   │   └── index.php         # Front Controller & Routing
│   └── src/                  # Source files
│       ├── Controllers/      # Business logic handlers
│       │   ├── AdminController.php
│       │   ├── AuthController.php
│       │   ├── LeaderboardController.php
│       │   ├── ProblemController.php
│       │   └── SubmissionController.php
│       ├── Database/         # DB SQL Schema and Seeds
│       │   ├── schema.sql
│       │   └── seed.sql
│       ├── Middleware/       # Route guards (Authentication)
│       │   └── AuthMiddleware.php
│       └── Models/           # Database schema representations
│           ├── Problem.php
│           ├── Submission.php
│           └── User.php
├── frontend/                 # Client UI (HTML, CSS, JS)
│   ├── assets/               # CSS and JS resources
│   │   ├── css/
│   │   │   └── styles.css
│   │   └── js/
│   │       ├── app.js
│   │       ├── auth.js
│   │       ├── editor.js
│   │       └── runner-client.js
│   ├── admin.html            # Admin management pages
│   ├── index.html            # Main User Dashboard
│   ├── leaderboard.html      # Rank listing
│   ├── login.html            # Signup/Signin form
│   ├── problem.html          # Individual problem coding page
│   └── problems.html         # Problem bank
└── local-runner/             # Compiles & executes code locally
    ├── runner.py             # Python HTTP execution daemon
    └── requirements.txt      # Dependencies configuration
```
