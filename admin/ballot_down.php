<?php
	include 'includes/session.php';

	if(isset($_POST['id'])){
		$id = $conn->real_escape_string($_POST['id']);

		$sql = "SELECT * FROM positions";
		$pquery = $conn->query($sql);
		$total = ($pquery && $pquery instanceof mysqli_result) ? $pquery->num_rows : 0;

		$output = array('error'=>false);

		$sql = "SELECT * FROM positions WHERE id='$id'";	
		$query = $conn->query($sql);
		$row = ($query && $query instanceof mysqli_result) ? $query->fetch_assoc() : null;

		if(!$row){
			$output['error'] = true;
			$output['message'] = 'Position not found';
		}
		else{
			$priority = $row['priority'] + 1;

			if($priority > $total){
				$output['error'] = true;
				$output['message'] = 'This position is already at the bottom';
			}
			else{
				$sql = "UPDATE positions SET priority = priority - 1 WHERE priority = '$priority'";
				$conn->query($sql);

				$sql = "UPDATE positions SET priority = '$priority' WHERE id = '$id'";
				$conn->query($sql);
			}
		}

		echo json_encode($output);

	}
	
?>