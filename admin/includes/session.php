<?php
	session_start();
	include 'includes/conn.php';

	if(!isset($_SESSION['admin']) || trim($_SESSION['admin']) == ''){
		header('location: index.php');
		exit();
	}

	$sql = "SELECT * FROM admin WHERE id = '".$conn->real_escape_string($_SESSION['admin'])."'";
	$query = $conn->query($sql);
	$user = ($query && $query instanceof mysqli_result) ? $query->fetch_assoc() : null;

	if(!$user){
		$user = [
			'id' => $_SESSION['admin'],
			'username' => 'admin',
			'firstname' => 'Admin',
			'lastname' => 'User',
			'photo' => '',
			'created_on' => date('Y-m-d')
		];
	}
?>