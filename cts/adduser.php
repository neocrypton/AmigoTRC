<?php
$showFormular = true; //Variable ob das Registrierungsformular anezeigt werden soll
 
if(isset($_POST['submit'])) {
    $error      = false;
    $email      = $_POST['email'];
    $passwort   = $_POST['passwort'];
    $passwort2  = $_POST['passwort2'];
    $username   = $_POST['benutzername'];
    $vorname   = $_POST['vorname'];
    $nachname   = $_POST['nachname'];
  
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<div class="container-fluid"><div class="box bg-warning ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/exclamation.png" class="d-inline-block align-middel m-0" alt=""> '.str_adduser_mailcheck.'</div></div>';
        $error = true;
    }     
    if(strlen($passwort) == 0) {
        echo '<div class="container-fluid"><div class="box bg-warning ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/exclamation.png" class="d-inline-block align-middel m-0" alt=""> '.str_adduser_pwcheck.'</div></div>';
        $error = true;
    }
    if($passwort != $passwort2) {
        echo '<div class="container-fluid"><div class="box bg-warning ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/exclamation.png" class="d-inline-block align-middel m-0" alt=""> '.str_adduser_pwcompare.'</div></div>';
        $error = true;
    }
    
    //Überprüfe, dass die E-Mail-Adresse noch nicht registriert wurde
    if(!$error) { 
        $statement = $db->prepare("SELECT * FROM users WHERE email = :email OR username = :username");
        $result = $statement->execute(array('email' => $email, 'username' => $username));
        $user = $statement->fetch();
        
        if($user !== false) {
            echo '<div class="container-fluid"><div class="box bg-warning ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/exclamation.png" class="d-inline-block align-middel m-0" alt=""> '.str_adduser_usedMail.'</div></div>';
            $error = true;
        }    
    }
    
    //Keine Fehler, wir können den Nutzer registrieren
    if(!$error) {    
        $passwort_hash = password_hash($passwort, PASSWORD_DEFAULT);
        
        $statement = $db->prepare("INSERT INTO users (email, passwort, vorname, nachname, username) VALUES (:email, :passwort, :vorname, :nachname, :username)");
        $result = $statement->execute(array(':email' => $email, ':passwort' => $passwort_hash, ':vorname' => $vorname, ':nachname' => $nachname, ':username' => $username));
        
        if($result) {        
            echo '<div class="container-fluid"><div class="box bg-success text-light ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/accept.png" class="d-inline-block align-middel m-0" alt=""> '.str_form_success.'</div></div>';
            #$showFormular = false;
        } else {
            echo '<div class="container-fluid"><div class="box bg-warning ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/exclamation.png" class="d-inline-block align-middel m-0" alt=""> '.str_adduser_errorAdd.'</div></div>';
        }
    } 
}
?>
<h1 class="text-light pl-4 pt-4"><?php echo str_head_adduser ?></h1> 
<div  class="container-fluid text-light">
    <div class="container-fluid">
<?php
if($showFormular) {
?> 
    <form action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
    <div class="row">
        <div class="form-group col-12 col-sm-6">    
            <?php echo str_adduser_mail ?><br>
            <input type="email" class="form-control" placeholder="" name="email">
        </div>
        <div class="form-group col-12 col-sm-6">
            <?php echo str_adduser_username ?><br>
            <input type="text" class="form-control" placeholder="" name="benutzername">
        </div>
    </div>
    <div class="row">
        <div class="form-group col-12 col-sm-6">    
            <?php echo str_adduser_firstname ?><br>
            <input type="text" class="form-control" placeholder="" name="vorname">
        </div>
        <div class="form-group col-12 col-sm-6">
            <?php echo str_adduser_lastname ?><br>
            <input type="text" class="form-control" placeholder="" name="nachname">
        </div>
    </div>    
    <div class="row">
        <div class="form-group col-12 col-sm-6">        
            <?php echo str_adduser_passwd1 ?><br>
            <input type="password" class="form-control" placeholder="" name="passwort">
        </div>
        <div class="form-group col-12 col-sm-6">
            <?php echo str_adduser_passwd2 ?><br>
            <input type="password" class="form-control" placeholder="" name="passwort2">
        </div>
    </div>
    <div class="mt-4 text-center">
            <button type="submit" class="btn btn-primary" name="submit"><?php echo str_submit_entry ?></button>
    </div>
    </div>
</div>
    </form>
 
<?php
} //Ende von if($showFormular)
?>    

    </div>
</div>