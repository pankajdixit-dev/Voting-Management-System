<?php
	include 'includes/session.php';
	include 'includes/slugify.php';

	if(isset($_POST['vote'])){
		if(count($_POST) == 1){
			$_SESSION['error'][] = 'Please vote atleast one candidate';
		}
		else{
			$_SESSION['post'] = $_POST;
			$sql = "SELECT * FROM positions";
			$query = $conn->query($sql);
			$error = false;
			$sql_array = array();
			$voter_id = $conn->real_escape_string($voter['id']);

			if($query && $query instanceof mysqli_result){
				while($row = $query->fetch_assoc()){
					$position = slugify($row['description']);
					$pos_id = (int)$row['id'];
					if(isset($_POST[$position])){
						if($row['max_vote'] > 1){
							if(is_array($_POST[$position]) && count($_POST[$position]) > $row['max_vote']){
								$error = true;
								$_SESSION['error'][] = 'You can only choose '.$row['max_vote'].' candidates for '.$row['description'];
							}
							else{
								if(is_array($_POST[$position])){
									foreach($_POST[$position] as $key => $values){
										$val = $conn->real_escape_string($values);
										$sql_array[] = "INSERT INTO votes (voters_id, candidate_id, position_id) VALUES ('$voter_id', '$val', '$pos_id')";
									}
								}
							}
							
						}
						else{
							$candidate = $conn->real_escape_string($_POST[$position]);
							$sql_array[] = "INSERT INTO votes (voters_id, candidate_id, position_id) VALUES ('$voter_id', '$candidate', '$pos_id')";
						}

					}
					
				}
			}

			if(!$error){
				foreach($sql_array as $sql_row){
					$conn->query($sql_row);
				}

				unset($_SESSION['post']);
				$_SESSION['success'] = 'Ballot Submitted Successfully';

			}

		}

	}
	else{
		$_SESSION['error'][] = 'Select candidates to vote first';
	}

	header('location: home.php');
	exit();

?>