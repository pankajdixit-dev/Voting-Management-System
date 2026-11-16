<?php
  	session_start();
  	if(isset($_SESSION['admin'])){
    	header('location:home.php');
    	exit();
  	}

    $tableNotice = false;
    if(file_exists('includes/conn.php')){
        include_once 'includes/conn.php';
        if(isset($conn) && $conn && !$conn->connect_error){
            $chk = @$conn->query("SHOW TABLES LIKE 'admin'");
            if(!$chk || !($chk instanceof mysqli_result) || $chk->num_rows == 0){
                $tableNotice = true;
            }
        }
    }
?>
<?php include 'includes/header.php'; ?>
<body class="hold-transition login-page">
<div class="login-box">
  	<div class="login-logo">
  		<b>Voting System Admin</b>
  	</div>
  
  	<div class="login-box-body">
    	<p class="login-box-msg">Sign in as Administrator</p>

      <?php if($tableNotice): ?>
        <div class="callout callout-warning text-center" style="margin-bottom: 15px; background-color: #fef3c7 !important; color: #92400e !important; border-color: #f59e0b;">
            <p style="margin-bottom: 6px;"><b><i class="fa fa-database"></i> Database Empty / Not Initialized</b></p>
            <p style="font-size: 13px; margin-bottom: 8px;">Pehle 1-Click Import tool se database tables install karein:</p>
            <a href="../db_setup.php" class="btn btn-warning btn-xs" style="color: #000; font-weight: bold; padding: 5px 12px;">
                <i class="fa fa-download"></i> Open Database Setup Tool &rarr;
            </a>
        </div>
      <?php endif; ?>

    	<form action="login.php" method="POST">
      		<div class="form-group has-feedback">
        		<input type="text" class="form-control" name="username" placeholder="Username (Default: admin)" required>
        		<span class="glyphicon glyphicon-user form-control-feedback"></span>
      		</div>
          <div class="form-group has-feedback">
            <input type="password" class="form-control" name="password" placeholder="Password (Default: admin)" required>
            <span class="glyphicon glyphicon-lock form-control-feedback"></span>
          </div>
      		<div class="row">
    			<div class="col-xs-12">
          			<button type="submit" class="btn btn-primary btn-block btn-flat" name="login"><i class="fa fa-sign-in"></i> Sign In</button>
        		</div>
      		</div>
    	</form>
      <div style="margin-top: 15px; text-align: center;">
        <a href="../index.php" class="btn btn-default btn-block btn-flat"><i class="fa fa-arrow-left"></i> Go to Voter Portal</a>
      </div>
  	</div>
  	<?php
  		if(isset($_SESSION['error'])){
  			echo "
  				<div class='callout callout-danger text-center mt20'>
			  		<p>".$_SESSION['error']."</p> 
			  	</div>
  			";
  			unset($_SESSION['error']);
  		}
  	?>
</div>
	
<?php include 'includes/scripts.php' ?>
</body>
<footer>
<p><center>Default Credentials: <b>admin / admin</b><br><a href="../db_setup.php" class="text-muted"><i class="fa fa-database"></i> Database Setup &amp; Health Check</a></p></center>
</footer>
</html>