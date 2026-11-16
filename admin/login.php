<?php
	session_start();
	include 'includes/conn.php';

	if(isset($_POST['login'])){
		$username = trim($_POST['username']);
		$password = $_POST['password'];

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