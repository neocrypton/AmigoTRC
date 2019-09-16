<?php
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
   
    // Inhalt Start

    if (isset($_GET['site'], $site[$_GET['site']]))
    {
       include $site[$_GET['site']];
       //$_SESSION['sitetitel'] = $_GET['acp'];
    } else {
       echo '<META   HTTP-EQUIV = "Refresh"; CONTENT = "0; URL=index.php?lang=de&site=dashboard">';
    }
?>

        <?php #include_once('footer.php'); ?>
        
    </body>
</html>