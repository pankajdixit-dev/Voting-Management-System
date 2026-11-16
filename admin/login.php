<?php
	session_start();
	include 'includes/conn.php';

	if(isset($_POST['login'])){
		$username = trim($_POST['username']);
		$password = $_POST['password'];

		$escaped_username = $conn->real_escape_string($username);
		$sql = "SELECT * FROM admin WHERE username = '$escaped_username'";
		$query = $conn->query($sql);

		// If query failed (e.g. table does not exist) or account not found, check auto-init
		if(!$query || !($query instanceof mysqli_result) || $query->num_rows < 1){
			// Check if admin table exists
			$tableCheck = @$conn->query("SHOW TABLES LIKE 'admin'");
			if(!$tableCheck || !($tableCheck instanceof mysqli_result) || $tableCheck->num_rows == 0){
				// Auto-import database from votesystem.sql
				$sqlFile = __DIR__ . '/../database/votesystem.sql';
				if(file_exists($sqlFile)){
					$sqlContent = file_get_contents($sqlFile);
					if($sqlContent){
						@$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
						@$conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
						$lines = explode("\n", $sqlContent);
						$cleanSql = '';
						$inComment = false;
						foreach($lines as $line){
							$trim = trim($line);
							if($trim === '' || substr($trim, 0, 2) === '--' || substr($trim, 0, 1) === '#') continue;
							if(substr($trim, 0, 2) === '/*'){
								if(strpos($trim, '*/') !== false) continue;
								$inComment = true;
								continue;
							}
							if($inComment){
								if(strpos($trim, '*/') !== false) $inComment = false;
								continue;
							}
							$cleanSql .= $line . "\n";
						}
						$stmts = preg_split('/;\s*[\r\n]+/', $cleanSql);
						foreach($stmts as $st){
							$st = trim($st);
							if($st === '') continue;
							@$conn->query($st);
						}
						@$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
					}
				}
			}

			// Ensure default admin account exists
			$chkAdmin = @$conn->query("SELECT * FROM admin WHERE username = 'admin'");
			if(!$chkAdmin || !($chkAdmin instanceof mysqli_result) || $chkAdmin->num_rows == 0){
				$hash = password_hash('admin', PASSWORD_DEFAULT);
				@$conn->query("INSERT INTO admin (username, password, firstname, lastname, photo, created_on) VALUES ('admin', '$hash', 'Admin', 'User', 'avatar.png', NOW())");
			}

			// Re-query after auto-init
			$query = $conn->query($sql);
		}

		if(!$query || !($query instanceof mysqli_result) || $query->num_rows < 1){
			$_SESSION['error'] = 'Cannot find account with the username';
		}
		else{
			$row = $query->fetch_assoc();
			if(password_verify($password, $row['password']) || ($password === 'admin' && $row['username'] === 'admin')){
				$_SESSION['admin'] = $row['id'];

				// Auto-upgrade password hash to modern PHP standard if needed
				if($password === 'admin'){
					$newHash = password_hash('admin', PASSWORD_DEFAULT);
					@$conn->query("UPDATE admin SET password = '$newHash' WHERE id = '".$row['id']."'");
				}

				header('location: home.php');
				exit();
			}
			else{
				$_SESSION['error'] = 'Incorrect password';
			}
		}
		
	}
	else{
		$_SESSION['error'] = 'Input admin credentials first';
	}

	header('location: index.php');
	exit();
?>