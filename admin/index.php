<?php
  	session_start();
  	if(isset($_SESSION['admin'])){
    	header('location:home.php');
    	exit();
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