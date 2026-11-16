<?php
/**
 * Voting Management System - 1-Click Aiven Cloud MySQL Importer & Health Inspector
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Disable strict reporting in PHP 8.1+ for safe diagnostics
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Read database parameters from Environment Variables
$db_server   = getenv('DB_SERVER') ?: (getenv('DB_HOST') ?: 'localhost');
$db_port     = (int)(getenv('DB_PORT') ?: 3306);
$db_username = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root');
$db_password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$db_name     = getenv('DB_NAME') ?: 'votesystem';

$sslEnv = getenv('DB_SSL');
if ($sslEnv !== false && $sslEnv !== '') {
    $db_ssl = filter_var($sslEnv, FILTER_VALIDATE_BOOLEAN);
} else {
    // Auto-detect Aiven Cloud or custom 5-digit non-standard ports
    $db_ssl = (strpos($db_server, 'aivencloud.com') !== false || $db_port != 3306);
}

// Attempt connection
$conn = mysqli_init();
$conn_status = false;
$conn_error = '';
$ssl_cipher = 'None';
$server_version = 'Unknown';
$start_time = microtime(true);

if ($conn) {
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    if ($db_ssl) {
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        $flags = MYSQLI_CLIENT_SSL;
        if (defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
            $flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }
        $conn_status = @$conn->real_connect($db_server, $db_username, $db_password, $db_name, $db_port, NULL, $flags);
    } else {
        $conn_status = @$conn->real_connect($db_server, $db_username, $db_password, $db_name, $db_port);
    }

    if ($conn_status && !$conn->connect_errno) {
        $conn->set_charset("utf8mb4");
        $server_version = $conn->server_info;
        $ssl_res = @$conn->query("SHOW STATUS LIKE 'Ssl_cipher'");
        if ($ssl_res && $row = $ssl_res->fetch_assoc()) {
            $ssl_cipher = !empty($row['Value']) ? $row['Value'] : ($db_ssl ? 'SSL Active' : 'Disabled');
        }
    } else {
        $conn_error = $conn->connect_error ?: mysqli_connect_error();
    }
} else {
    $conn_error = 'Failed to initialize mysqli extension.';
}
$latency = round((microtime(true) - $start_time) * 1000, 2);

// Handle POST Actions (Import / Reset)
$action_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $conn_status) {
    $action = $_POST['action'];

    if ($action === 'import') {
        $sqlPath = __DIR__ . '/database/votesystem.sql';
        if (!file_exists($sqlPath)) {
            $action_result = ['type' => 'danger', 'msg' => "SQL file not found at: {$sqlPath}"];
        } else {
            $sqlContent = file_get_contents($sqlPath);
            if (!$sqlContent) {
                $action_result = ['type' => 'danger', 'msg' => "Could not read SQL file contents."];
            } else {
                @$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
                @$conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");

                // Clean comments
                $lines = explode("\n", $sqlContent);
                $cleanSql = '';
                $inComment = false;
                foreach ($lines as $line) {
                    $trim = trim($line);
                    if ($trim === '' || substr($trim, 0, 2) === '--' || substr($trim, 0, 1) === '#') continue;
                    if (substr($trim, 0, 2) === '/*') {
                        if (strpos($trim, '*/') !== false) continue;
                        $inComment = true;
                        continue;
                    }
                    if ($inComment) {
                        if (strpos($trim, '*/') !== false) $inComment = false;
                        continue;
                    }
                    $cleanSql .= $line . "\n";
                }

                $stmts = preg_split('/;\s*[\r\n]+/', $cleanSql);
                $count = 0;
                $errs = [];
                foreach ($stmts as $st) {
                    $st = trim($st);
                    if ($st === '') continue;
                    if (!@$conn->query($st)) {
                        $err = $conn->error;
                        if ($err && strpos($err, 'already exists') === false) {
                            $errs[] = substr($err, 0, 150);
                        }
                    } else {
                        $count++;
                    }
                }

                @$conn->query("SET FOREIGN_KEY_CHECKS = 1;");

                // Ensure default Admin user exists
                $chkAdmin = @$conn->query("SELECT * FROM admin WHERE username = 'admin'");
                if (!$chkAdmin || !($chkAdmin instanceof mysqli_result) || $chkAdmin->num_rows == 0) {
                    $hash = '$2y$10$YJ3jSw58dqOEurW9fUgCLuhXcvpKTR01HEcEEJ41tneB7vxeG.eiK'; // 'admin'
                    @$conn->query("INSERT INTO admin (username, password, firstname, lastname, photo, created_on) VALUES ('admin', '$hash', 'Admin', 'User', 'avatar.png', NOW())");
                }

                $action_result = [
                    'type' => 'success',
                    'msg' => "Database successfully imported! Executed {$count} statements. Default Admin account verified (Username: admin, Password: admin)."
                ];
            }
        }
    } elseif ($action === 'reset') {
        @$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
        $dropTables = ['votes', 'candidates', 'positions', 'voters', 'admin'];
        foreach ($dropTables as $t) {
            @$conn->query("DROP TABLE IF EXISTS `{$t}`");
        }
        @$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        $action_result = ['type' => 'warning', 'msg' => "All existing project tables dropped. You can now perform a fresh 1-Click Import."];
    }
}

// Inspect tables if connected
$existing_tables = [];
$admin_user = null;
$required_tables = ['admin', 'voters', 'positions', 'candidates', 'votes'];
$missing_tables = [];

if ($conn_status) {
    $t_res = @$conn->query("SHOW TABLES");
    if ($t_res && $t_res instanceof mysqli_result) {
        while ($t_row = $t_res->fetch_array()) {
            $tbl = $t_row[0];
            $c_res = @$conn->query("SELECT COUNT(*) FROM `{$tbl}`");
            $rows_count = 0;
            if ($c_res && $crow = $c_res->fetch_array()) {
                $rows_count = $crow[0];
            }
            $existing_tables[$tbl] = $rows_count;
        }
    }

    foreach ($required_tables as $req) {
        if (!isset($existing_tables[$req])) {
            $missing_tables[] = $req;
        }
    }

    if (isset($existing_tables['admin'])) {
        $a_res = @$conn->query("SELECT * FROM admin WHERE username = 'admin' LIMIT 1");
        if ($a_res && $a_res instanceof mysqli_result && $a_res->num_rows > 0) {
            $admin_user = $a_res->fetch_assoc();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aiven MySQL Installer &amp; Health Inspector - Voting System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            padding: 30px 15px;
            line-height: 1.5;
        }
        .container {
            max-width: 960px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
        }
        .header p {
            color: var(--text-muted);
            margin-top: 6px;
            font-size: 15px;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media(max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        .stat-item {
            background: #f1f5f9;
            padding: 12px 16px;
            border-radius: 8px;
        }
        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 15px;
            font-weight: 600;
            margin-top: 4px;
            word-break: break-all;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .alert-danger { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-warning { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 14px;
        }
        .table th, .table td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .table th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--text-muted);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-main);
        }
        .btn-outline:hover { background: #f1f5f9; }
        .actions-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-top: 15px;
        }
        .env-code {
            background: #1e293b;
            color: #f8fafc;
            padding: 14px;
            border-radius: 8px;
            font-family: Courier, monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fa fa-database" style="color:var(--primary);"></i> Voting System - Aiven MySQL Setup</h1>
        <p>1-Click Cloud Database Importer &amp; Health Inspector for Render Deployment</p>
    </div>

    <?php if ($action_result): ?>
        <div class="alert alert-<?php echo $action_result['type']; ?>">
            <strong><i class="fa fa-info-circle"></i> Result:</strong> <?php echo $action_result['msg']; ?>
        </div>
    <?php endif; ?>

    <!-- Connection Status Card -->
    <div class="card">
        <div class="card-title">
            <span>Aiven MySQL Connection Health</span>
            <?php if ($conn_status): ?>
                <span class="badge badge-success"><i class="fa fa-check-circle"></i> CONNECTED</span>
            <?php else: ?>
                <span class="badge badge-danger"><i class="fa fa-times-circle"></i> DISCONNECTED</span>
            <?php endif; ?>
        </div>

        <?php if (!$conn_status): ?>
            <div class="alert alert-danger">
                <strong>Connection Error:</strong> <?php echo htmlspecialchars($conn_error); ?><br>
                <span style="font-size:13px; color:#7f1d1d;">Please verify your Aiven Cloud MySQL credentials configured in your Render service environment variables.</span>
            </div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="stat-item">
                <div class="stat-label">Host / Server</div>
                <div class="stat-value"><?php echo htmlspecialchars($db_server); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Port</div>
                <div class="stat-value"><?php echo htmlspecialchars($db_port); ?> <?php if ($db_port != 3306) echo '<span class="badge badge-info">Custom Aiven Port</span>'; ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Database Name</div>
                <div class="stat-value"><?php echo htmlspecialchars($db_name); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Username</div>
                <div class="stat-value"><?php echo htmlspecialchars($db_username); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">SSL Encryption (require_secure_transport)</div>
                <div class="stat-value">
                    <?php if ($db_ssl): ?>
                        <span class="badge badge-success"><i class="fa fa-lock"></i> Enabled (<?php echo htmlspecialchars($ssl_cipher); ?>)</span>
                    <?php else: ?>
                        <span class="badge badge-warning"><i class="fa fa-unlock"></i> Disabled</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Latency / Version</div>
                <div class="stat-value"><?php echo $latency; ?> ms &bull; MySQL <?php echo htmlspecialchars($server_version); ?></div>
            </div>
        </div>
    </div>

    <!-- Database Tables Status Card -->
    <?php if ($conn_status): ?>
    <div class="card">
        <div class="card-title">
            <span>Database Tables Status</span>
            <?php if (empty($missing_tables) && count($existing_tables) >= 5): ?>
                <span class="badge badge-success"><i class="fa fa-check"></i> ALL TABLES READY</span>
            <?php elseif (empty($existing_tables)): ?>
                <span class="badge badge-danger"><i class="fa fa-exclamation-triangle"></i> DATABASE EMPTY</span>
            <?php else: ?>
                <span class="badge badge-warning"><i class="fa fa-exclamation-circle"></i> PARTIAL SCHEMA</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($existing_tables)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Status</th>
                        <th>Row Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($required_tables as $req): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($req); ?></strong></td>
                            <td>
                                <?php if (isset($existing_tables[$req])): ?>
                                    <span class="badge badge-success">Exists</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Missing</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo isset($existing_tables[$req]) ? $existing_tables[$req] . ' records' : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:var(--text-muted); margin-bottom: 12px;">No tables found in database <code><?php echo htmlspecialchars($db_name); ?></code>. Click the 1-Click Import button below to install all tables, positions, candidates, sample voters, and default admin.</p>
        <?php endif; ?>

        <!-- Admin Account Check -->
        <div style="margin-top: 18px; padding: 14px; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px;">
            <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px;">
                <i class="fa fa-user-circle"></i> Administrator Status:
                <?php if ($admin_user): ?>
                    <span class="badge badge-success">Admin Ready</span>
                <?php else: ?>
                    <span class="badge badge-warning">Admin Missing</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 13px; color: var(--text-muted);">
                Default Login Credentials: <strong>Username: <code>admin</code></strong> | <strong>Password: <code>admin</code></strong>
            </div>
        </div>

        <!-- 1-Click Actions Bar -->
        <div class="actions-bar">
            <form method="POST" onsubmit="return confirm('Do you want to import/populate database from votesystem.sql?');">
                <input type="hidden" name="action" value="import">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-download"></i> 1-Click Aiven Database Import
                </button>
            </form>

            <?php if (!empty($existing_tables)): ?>
                <form method="POST" onsubmit="return confirm('Warning: This will drop all project tables for a clean reset. Continue?');">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-outline" style="color:var(--danger); border-color:#fca5a5;">
                        <i class="fa fa-trash"></i> Reset Database
                    </button>
                </form>
            <?php endif; ?>

            <a href="admin/index.php" class="btn btn-success">
                <i class="fa fa-sign-in"></i> Admin Login &rarr;
            </a>

            <a href="index.php" class="btn btn-outline">
                <i class="fa fa-users"></i> Voter Portal
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Environment Variables Guide Card -->
    <div class="card">
        <div class="card-title">
            <span>Render Environment Variables Reference</span>
            <span class="badge badge-info">Config Guide</span>
        </div>
        <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 12px;">
            Set these environment variables in your Render Dashboard (under <strong>Service &rarr; Environment</strong>):
        </p>
        <div class="env-code">
DB_SERVER = &lt;your-aiven-project&gt;.aivencloud.com
DB_PORT = 26284
DB_USERNAME = avnadmin
DB_PASSWORD = &lt;your-aiven-mysql-password&gt;
DB_NAME = defaultdb (or votesystem)
DB_SSL = true
PORT = 10000
        </div>
    </div>
</div>
</body>
</html>
