# AlgoNest

A minimal, beautiful, and secure coding practice platform for programmers. Problems are compiled and executed on the server — users simply write code in the browser and get instant results.

## Architecture

1. **Frontend**: HTML5, Tailwind CSS, Monaco Editor, Alpine.js / Vanilla JS.
2. **Backend**: PHP 8.3 REST API — routing, JWT authentication, problem library, submission log, admin controls.
3. **Database**: MySQL 8.

---

## Setup Instructions

### Quick Start (Multi-Device Local Server)

A helper script starts both servers simultaneously, bound to all interfaces (`0.0.0.0`) so other devices on the same network can reach them.

1. Ensure MySQL is running and initialised (see *Database Setup* below).
2. Run the start script:
   ```bash
   ./start.sh
   ```
3. The script prints the network URLs — e.g. `http://<your-local-ip>:8081` (Frontend) and `http://<your-local-ip>:8080` (Backend API).

---

### Manual Setup

#### 1. Database Setup
1. Import the schema: `backend/src/Database/schema.sql`
2. Run the seeds: `backend/src/Database/seed.sql`
3. Update credentials in `backend/config/database.php`.

#### 2. Start PHP Backend
```bash
cd backend/public
php -S 0.0.0.0:8080
```

#### 3. Start Frontend Server
```bash
cd frontend
php -S 0.0.0.0:8081
```

---

## Multi-Device Access

To use AlgoNest from other devices on the same Wi-Fi/LAN:

1. **Same network** — host machine and all client devices must be on the same Wi-Fi or hotspot.
2. **Find host IP**:
   - Linux/macOS: `hostname -I` or `ip a`
   - Windows: `ipconfig` → IPv4 Address
3. **Open in browser**: `http://<HOST-IP>:8081`

---

## Directory Structure

```text
AlgoNest/
├── backend/                        # PHP REST API
│   ├── config/
│   │   ├── database.php            # DB connection settings
│   │   └── jwt.php                 # JWT signing helpers
│   ├── public/
│   │   ├── index.php               # Front controller & router
│   │   ├── router.php              # Dev router for PHP built-in server
│   │   ├── avatars/                # Uploaded user profile pictures
│   │   └── .user.ini               # PHP runtime config
│   └── src/
│       ├── Controllers/
│       │   ├── AdminController.php
│       │   ├── AuthController.php
│       │   ├── CommentController.php
│       │   ├── LeaderboardController.php
│       │   ├── NotificationController.php
│       │   ├── ProblemController.php
│       │   ├── RunnerController.php
│       │   └── SubmissionController.php
│       ├── Database/
│       │   ├── schema.sql
│       │   ├── seed.sql
│       │   └── notifications_migration.sql
│       ├── Middleware/
│       │   └── AuthMiddleware.php
│       └── Models/
│           ├── Comment.php
│           ├── Notification.php
│           ├── Problem.php
│           ├── Submission.php
│           └── User.php
├── frontend/                       # Client UI
│   ├── assets/
│   │   ├── css/
│   │   │   ├── styles.css
│   │   │   └── tailwind.min.css
│   │   └── js/
│   │       ├── app.js              # Global state, API base URL, theme
│   │       ├── auth.js             # JWT helpers & auth guard
│   │       ├── compile-client.js   # Compilation pipeline client
│   │       ├── editor.js           # Monaco editor setup
│   │       └── runner-client.js    # Backend submission client
│   ├── admin.html                  # Admin console (problems + approvals)
│   ├── index.html                  # User dashboard
│   ├── landing.html                # Public landing page
│   ├── leaderboard.html            # Global rankings
│   ├── login.html                  # Sign in / Sign up
│   ├── problem.html                # Problem workspace (editor + runner)
│   ├── problems.html               # Problem bank
│   ├── profile.html                # User profile & settings
│   ├── public-profile.html         # Public view of another user's profile
│   └── router.php                  # Dev router for PHP built-in server
├── start.sh                        # Linux/macOS server starter
├── start.bat                       # Windows server starter
└── MVP.md                          # Feature milestone tracker
```

---

## API Overview

All endpoints are prefixed with `/api`. Protected routes require `Authorization: Bearer <token>`.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/register` | — | Register a new user |
| POST | `/api/auth/login` | — | Login, returns JWT |
| GET | `/api/auth/profile` | ✓ | Get own profile |
| PUT | `/api/auth/profile/username` | ✓ | Update username |
| POST | `/api/auth/profile/avatar` | ✓ | Upload avatar |
| GET | `/api/problems` | — | List problems |
| GET | `/api/problems/:id` | — | Problem detail |
| GET | `/api/problems/:id/samples` | — | Sample test cases |
| GET | `/api/problems/:id/testcases` | ✓ | All test cases (for evaluation) |
| POST | `/api/problems` | ✓ | Submit a problem for approval |
| POST | `/api/submissions` | ✓ | Log a submission result |
| GET | `/api/submissions/history` | ✓ | Own submission history |
| GET | `/api/submissions/problem/:id` | ✓ | Submissions for a problem |
| GET | `/api/leaderboard` | — | Global leaderboard |
| POST | `/api/run` | ✓ | Execute code via backend runner |
| GET | `/api/admin/problems` | admin | List all problems |
| POST | `/api/admin/problems` | admin | Create a problem |
| PUT | `/api/admin/problems/:id` | admin | Update a problem |
| DELETE | `/api/admin/problems/:id` | admin | Delete a problem |
| POST | `/api/admin/problems/:id/approve` | admin | Approve a problem |
| POST | `/api/admin/problems/:id/reject` | admin | Reject a problem |
| GET | `/api/notifications` | ✓ | Get notifications |
| GET | `/api/notifications/count` | ✓ | Unread count |
| PUT | `/api/notifications/:id/read` | ✓ | Mark one as read |
| PUT | `/api/notifications` | ✓ | Mark all as read |
| GET | `/api/users/:id/profile` | — | Public user profile |
| GET | `/api/users/:id/submissions` | — | User's accepted submissions |
| GET | `/api/users/:id/stats` | — | User stats |
| GET | `/api/comments/problem/:id` | — | Comments on a problem |
| POST | `/api/comments` | ✓ | Post a comment |
| PUT | `/api/comments/:id` | ✓ | Edit a comment |
| DELETE | `/api/comments/:id` | ✓ | Delete a comment |
| POST | `/api/comments/:id/vote` | ✓ | Vote on a comment |
| DELETE | `/api/comments/:id/vote` | ✓ | Remove vote |
