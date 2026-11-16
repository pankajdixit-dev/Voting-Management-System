<?php
	session_start();
	include 'includes/conn.php';

	if(isset($_POST['login'])){
		$voter = trim($_POST['voter']);
		$password = $_POST['password'];

		$escaped_voter = $conn->real_escape_string($voter);
		$sql = "SELECT * FROM voters WHERE voters_id = '$escaped_voter'";
		$query = $conn->query($sql);

		if(!$query || !($query instanceof mysqli_result) || $query->num_rows < 1){
			$_SESSION['error'] = 'Cannot find voter with the ID';
		}
		else{
			$row = $query->fetch_assoc();
			if(password_verify($password, $row['password'])){
				$_SESSION['voter'] = $row['id'];
				header('location: home.php');
				exit();
			}
			else{
				$_SESSION['error'] = 'Incorrect password';
			}
		}
		
	}
	else{
		$_SESSION['error'] = 'Input voter credentials first';
	}

	header('location: index.php');
	exit();
?>