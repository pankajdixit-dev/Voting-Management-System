# 🗳️ Online Voting Management System

A modern, secure, and production-ready **PHP 8.2 Voting Management System** configured for seamless deployment on **Render** (via Docker & Apache) and connected to **Aiven Cloud MySQL** with enforced SSL encryption (`require_secure_transport=ON`).

---

## 🚀 Live App Endpoints

| Portal | URL Route | Description |
|---|---|---|
| **Voter Portal** | `/` or `/index.php` | Main voting page for registered voters |
| **Admin Portal** | `/admin/index.php` | Administration dashboard to manage voters, candidates, and view tallies |
| **1-Click DB Installer** | `/db_setup.php` | Visual database health inspector & 1-click Aiven SQL importer |

---

## 🔑 Default Login Credentials

### 👨‍💼 Administrator
- **Login URL:** `/admin/index.php`
- **Username:** `admin`
- **Password:** `admin`

### 👥 Sample Registered Voter IDs
- **Login URL:** `/index.php`
- **Voter ID:** `pSMQYCjWk5dnmaD`
- **Voter ID:** `Is92CPnGcvOy4ue`
- **Voter ID:** `gwvIniKqT5xsWJc`
- **Voter ID:** `IpqGblR7m6tQFhz`

> *Note:* New voters can be added directly from the Admin Portal (`/admin/voters.php`), which auto-generates secure Voter IDs.

---

## ☁️ Aiven Cloud MySQL Environment Variables

Configure these environment variables in your **Render Service Dashboard** under **Environment**:

| Variable | Example Value | Description |
|---|---|---|
| `DB_SERVER` | `mysql-xxxx-project.aivencloud.com` | Aiven Cloud MySQL Hostname |
| `DB_PORT` | `26284` | Aiven custom 5-digit port (e.g., 26284) |
| `DB_USERNAME` | `avnadmin` | MySQL username |
| `DB_PASSWORD` | `AVNS_xxxxxxxxxxxx` | MySQL password |
| `DB_NAME` | `defaultdb` *(or `votesystem`)* | Database name |
| `DB_SSL` | `true` | Enforces SSL transport (`MYSQLI_CLIENT_SSL`) |
| `PORT` | `10000` | Port dynamically assigned by Render |

---

## 🛠️ PHP 8.x & Render Architecture Fixes

### 1. Aiven SSL & Custom Port Support
- Upgraded `includes/conn.php` to initialize via `mysqli_init()`.
- Added `MYSQLI_CLIENT_SSL` flag and server certificate verification bypass for cloud certificates.
- Dynamic environment variable parsing supporting 5-digit ports and cloud hosts.

### 2. PHP 8.2 Safe Handling
- Eliminated fatal errors on empty queries with safe `($query && $query instanceof mysqli_result)` checks across dashboard, candidate, position, voter, and ballot queries.
- Safe session default fallbacks preventing string offset errors on `null` in PHP 8.x.
- SQL injection protection using `real_escape_string()`.

### 3. Render Reverse Proxy & Port 10000 Fix (Critical)
- **Problem:** Apache's `mod_dir` appends the internal container listening port (e.g. `:10000`) on directory redirects (e.g. `https://domain.onrender.com/admin` -> `https://domain.onrender.com:10000/admin/`), breaking user connections.
- **Solution:** 
  - `start.sh` dynamically binds Apache to `${PORT:-10000}`.
  - Apache configured with `UseCanonicalName Off`, `UseCanonicalPhysicalPort Off`, and `ServerName localhost`.
  - `.htaccess` smart rewrite rules strip internal ports and enforce HTTPS trailing slashes.
  - Landing and login redirects use direct file routing (`admin/index.php`, `admin/home.php`).

---

## 📦 How to Deploy on Render

1. **Create New Web Service:**
   - Connect your GitHub repository: `https://github.com/pankajdixit-dev/Voting-Management-System.git`
   - Select **Docker** environment (or let Render detect `render.yaml`).
2. **Add Environment Variables:**
   - Set `DB_SERVER`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`, and `DB_SSL=true`.
3. **Deploy Service:**
   - Click **Deploy Web Service**.
4. **1-Click Database Setup:**
   - Once deployed, visit `https://<your-service>.onrender.com/db_setup.php`.
   - Click **1-Click Aiven Database Import**.
   - Your database will be initialized with all tables, default admin, and Marvel-themed sample election candidates!
5. **Login and Enjoy!**

---

## 💻 Local Testing via Docker

```bash
# Build Docker image
docker build -t voting-system .

# Run container with custom port and Aiven variables
docker run -p 10000:10000 \
  -e DB_SERVER="your-host.aivencloud.com" \
  -e DB_PORT="26284" \
  -e DB_USERNAME="avnadmin" \
  -e DB_PASSWORD="yourpassword" \
  -e DB_NAME="defaultdb" \
  -e DB_SSL="true" \
  voting-system
```
Open [http://localhost:10000](http://localhost:10000) in your browser.
