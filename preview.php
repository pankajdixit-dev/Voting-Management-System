<?php
	
	include 'includes/session.php';
	include 'includes/slugify.php';

	$output = array('error'=>false,'list'=>'');

	$sql = "SELECT * FROM positions";
	$query = $conn->query($sql);

	if($query && $query instanceof mysqli_result){
		while($row = $query->fetch_assoc()){
			$position = slugify($row['description']);
			$pos_id = $row['id'];
			if(isset($_POST[$position])){
				if($row['max_vote'] > 1){
					if(is_array($_POST[$position]) && count($_POST[$position]) > $row['max_vote']){
						$output['error'] = true;
						$output['message'][] = '<li>You can only choose '.$row['max_vote'].' candidates for '.$row['description'].'</li>';
					}
					else{
						if(is_array($_POST[$position])){
							foreach($_POST[$position] as $key => $values){
								$values = $conn->real_escape_string($values);
								$sql = "SELECT * FROM candidates WHERE id = '$values'";
								$cmquery = $conn->query($sql);
								if($cmquery && $cmquery instanceof mysqli_result && ($cmrow = $cmquery->fetch_assoc())){
									$output['list'] .= "
										<div class='row votelist'>
					                      	<span class='col-sm-4'><span class='pull-right'><b>".htmlspecialchars($row['description'])." :</b></span></span> 
					                      	<span class='col-sm-8'>".htmlspecialchars($cmrow['firstname']." ".$cmrow['lastname'])."</span>
					                    </div>
									";
								}
							}
						}
					}
					
				}
				else{
					$candidate = $conn->real_escape_string($_POST[$position]);
					$sql = "SELECT * FROM candidates WHERE id = '$candidate'";
					$csquery = $conn->query($sql);
					if($csquery && $csquery instanceof mysqli_result && ($csrow = $csquery->fetch_assoc())){
						$output['list'] .= "
							<div class='row votelist'>
		                      	<span class='col-sm-4'><span class='pull-right'><b>".htmlspecialchars($row['description'])." :</b></span></span> 
		                      	<span class='col-sm-8'>".htmlspecialchars($csrow['firstname']." ".$csrow['lastname'])."</span>
		                    </div>
						";
					}
				}

			}
			
		}
	}

	echo json_encode($output);

?>