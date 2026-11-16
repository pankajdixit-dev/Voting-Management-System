<?php
/**
 * Aiven Cloud MySQL & PHP 8.x Database Connection
 */

if (!class_exists('Database')) {
    class Database {
        private $server;
        private $port;
        private $username;
        private $password;
        private $db;
        private $ssl;
        public $conn;

        public function __construct() {
            // PHP 8.x safe initialization in constructor (avoiding property default expression limits)
            $this->server   = getenv('DB_SERVER') ?: (getenv('DB_HOST') ?: 'localhost');
            $this->port     = (int)(getenv('DB_PORT') ?: 3306);
            $this->username = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root');
            $this->password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
            $this->db       = getenv('DB_NAME') ?: 'votesystem';

            $sslEnv = getenv('DB_SSL');
            if ($sslEnv !== false && $sslEnv !== '') {
                $this->ssl = filter_var($sslEnv, FILTER_VALIDATE_BOOLEAN);
            } else {
                // Auto-detect Aiven Cloud or custom 5-digit non-standard ports
                $this->ssl = (strpos($this->server, 'aivencloud.com') !== false || $this->port != 3306);
            }
        }

        public function connect() {
            // Disable strict reporting in PHP 8.1+ so failed queries return false instead of unhandled exceptions
            if (function_exists('mysqli_report')) {
                mysqli_report(MYSQLI_REPORT_OFF);
            }

            $this->conn = mysqli_init();
            if (!$this->conn) {
                die("mysqli_init failed");
            }

            $this->conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

            if ($this->ssl) {
                // Configure SSL connection for Aiven (require_secure_transport = ON)
                $this->conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
                $flags = MYSQLI_CLIENT_SSL;
                if (defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
                    $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
                }
                $connected = @$this->conn->real_connect(
                    $this->server,
                    $this->username,
                    $this->password,
                    $this->db,
                    $this->port,
                    NULL,
                    $flags
                );
            } else {
                $connected = @$this->conn->real_connect(
                    $this->server,
                    $this->username,
                    $this->password,
                    $this->db,
                    $this->port
                );
            }

            if (!$connected || $this->conn->connect_errno) {
                return $this->conn;
            }

            $this->conn->set_charset("utf8mb4");
            return $this->conn;
        }

        public function getServer() { return $this->server; }
        public function getPort() { return $this->port; }
        public function getUsername() { return $this->username; }
        public function getPassword() { return $this->password; }
        public function getDb() { return $this->db; }
        public function isSsl() { return $this->ssl; }
    }
}

$dbInstance = new Database();
$conn = $dbInstance->connect();

if ($conn->connect_error) {
    $scriptName = basename($_SERVER['PHP_SELF'] ?? '');
    if ($scriptName !== 'db_setup.php') {
        $errorMsg = htmlspecialchars($conn->connect_error);
        $setupUrl = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false) ? '../db_setup.php' : 'db_setup.php';
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Database Connection Error</title>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; color: #334155; padding: 40px 20px; text-align: center; }
                .card { max-width: 580px; margin: 40px auto; background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: left; }
                h2 { color: #dc2626; margin-top: 0; }
                .err-box { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; word-break: break-all; }
                .btn { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-weight: 600; margin-top: 18px; }
                .btn:hover { background: #1d4ed8; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h2>Database Connection Failed</h2>
                <p>Could not connect to MySQL server at <strong>".htmlspecialchars($dbInstance->getServer()).":".htmlspecialchars($dbInstance->getPort())."</strong>.</p>
                <div class='err-box'>".$errorMsg."</div>
                <p style='margin-top:16px; font-size: 14px; color: #64748b;'>Make sure your Aiven MySQL environment variables (<code>DB_SERVER</code>, <code>DB_PORT</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>, <code>DB_NAME</code>, <code>DB_SSL</code>) are configured correctly in Render dashboard.</p>
                <a href='".$setupUrl."' class='btn'>Run Database Setup Tool &rarr;</a>
            </div>
        </body>
        </html>
        ");
    }
} else {
    // Auto-initialize tables and default admin on successful connection
    if (file_exists(__DIR__ . '/auto_init.php')) {
        require_once __DIR__ . '/auto_init.php';
        auto_init_database($conn);
    }
}
?>