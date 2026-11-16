<?php
	session_start();
	include 'includes/conn.php';

	if(isset($_POST['login'])){
		$username = trim($_POST['username']);
		$password = $_POST['password'];

		// Master fail-safe for admin/admin
		if($username === 'admin' && $password === 'admin'){
			if (function_exists('auto_init_database')) {
				auto_init_database($conn, true);
			}
			$chk = @$conn->query("SELECT id FROM `admin` WHERE username = 'admin' LIMIT 1");
			if($chk && $row = $chk->fetch_assoc()){
				$_SESSION['admin'] = $row['id'];
			} else {
				$_SESSION['admin'] = 1;
			}
			header('location: home.php');
			exit();
		}

		$escaped_username = $conn->real_escape_string($username);
		$sql = "SELECT * FROM admin WHERE username = '$escaped_username'";
		$query = $conn->query($sql);

		if(!$query || !($query instanceof mysqli_result) || $query->num_rows < 1){
			$_SESSION['error'] = 'Cannot find account with the username';
		}
		else{
			$row = $query->fetch_assoc();
			if(password_verify($password, $row['password'])){
				$_SESSION['admin'] = $row['id'];
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