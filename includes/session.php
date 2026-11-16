<?php
	include 'includes/conn.php';
	session_start();

	if(isset($_SESSION['voter'])){
		$sql = "SELECT * FROM voters WHERE id = '".$conn->real_escape_string($_SESSION['voter'])."'";
		$query = $conn->query($sql);
		$voter = ($query && $query instanceof mysqli_result) ? $query->fetch_assoc() : null;
		if(!$voter){
			unset($_SESSION['voter']);
			header('location: index.php');
			exit();
		}
	}
	else{
		header('location: index.php');
		exit();
	}
?>