<?php
session_start();
#error_reporting(E_ALL);
#ini_set('display_errors', 1);
include_once('./support/con_db.php');

?>
<!doctype html>
<html lang="en">
    <?php include_once('head.php'); ?>
    <body class="bg-dark">
        <?php include_once('nav.php'); ?>
<?php    
    $site = array();
    $site['dashboard']    = './cts/dashboard.php';
    $site['addsw']        = './cts/addsw.php';
    $site['addhw']        = './cts/addhw.php';
    $site['listsw']       = './cts/listsw.php';
    $site['listhw']       = './cts/listhw.php';
    $site['srchsw']       = './cts/srchsw.php';
    $site['adduser']      = './cts/adduser.php';
    $site['login']        = './cts/login.php';
    $site['logout']       = './cts/logout.php';
    
   
    // Inhalt Start


    if (!isset($_GET['site'], $site[$_GET['site']]))
    {
        
       if(isset($_SESSION['userid'])) {
           echo '<meta http-equiv="refresh" content="0; URL=index.php?lang=de&site=dashboard">';
       } else if(!isset($_SESSION['userid'])) {
           echo '<meta http-equiv="refresh" content="0; URL=index.php?lang=de&site=login">';
       }
    } else {
            
        if(isset($_SESSION['userid'])) {
           include $site[$_GET['site']]; 
        } else {
           include $site['login']; 
           if ($_GET['site']!='login') {
               echo '<meta http-equiv="refresh" content="0; URL=index.php?lang=de&site=login">';
           }
        }    
    }
    
?>

        <?php #include_once('footer.php'); ?>
        
    </body>
</html>