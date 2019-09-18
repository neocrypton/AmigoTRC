<?php
    if(isset($_POST['login'])) {
        $username = $_POST['benutzername'];
        $passwort = $_POST['passwort'];

        $statement = $db->prepare("SELECT * FROM users WHERE username = :username");
        $result = $statement->execute(array('username' => $username));
        $user = $statement->fetch();

        //Überprüfung des Passworts
        if ($user !== false && password_verify($passwort, $user['passwort'])) {
                $_SESSION['userid'] = $user['id'];
                echo '<meta http-equiv="refresh" content="3; URL=index.php?lang=de&site=dashboard">';
                die('<div class="container-fluid"><div class="box bg-success text-light ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/accept.png" class="d-inline-block align-middel m-0" alt=""> '.str_login_success.'</div></div>');
                            
            } else {
                $errorMessage = '<div class="container-fluid"><div class="box bg-warning ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/exclamation.png" class="d-inline-block align-middel m-0" alt=""> '.str_login_error.'</div></div>';
        }

    }

    if(isset($errorMessage)) {
        echo $errorMessage;
    }
?>
<h3 class="text-light pl-4 pt-4"><?php echo str_head_login ?></h3>
<div  class="container-fluid text-light">
    <div class="container-fluid">
        <form action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
        <div class="row">
        <div class="form-group col-12 col-sm-6">        
            <?php echo str_login_user ?><br>
            <input type="text" class="form-control" placeholder="" name="benutzername">
        </div>
        <div class="form-group col-12 col-sm-6">
            <?php echo str_login_passwd ?><br>
            <input type="password" class="form-control" placeholder="" name="passwort">
        </div>        
        </div>
        <div class="mt-4 text-center">
            <button type="submit" class="btn btn-primary" name="login"><?php echo str_login_login ?></button>
        </div> 
        </form>
<?php
            
?>
    </div>
</div>