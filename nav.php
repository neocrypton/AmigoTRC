<?php
#
# AMIGO! The Retro Collector Project
#
# File:             nav.php
# Description:      nav-Template-File
# Author:           Tony Alkier
# Date:             11.09.2019
# 
?>
<nav class="navbar navbar-expand-md navbar-dark bg-dark border-bottom border-warning">
    <a class="navbar-brand align-middle" href="#">
            <img src="./img/logo_square.png" height="70" class="d-inline-block align-top" alt="">
    </a><div class=" text-center text-success font-weight-bolder font-italic"><h3>AMIGO!</h3>The Retro Collector</div>
    
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent">
        <span class="navbar-toggler-icon"></span>
    </button> 
        
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item active"><a href="index.php?lang=de&site=dashboard" class="nav-link text-warning"><?php echo str_start ?></a></li>
            <?php
        if(isset($_SESSION['userid'])) {
        ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-warning" href="#" id="navbarDropdown" role="button" data-toggle="dropdown"><?php echo str_collect ?></a>
                <div class="dropdown-menu bg-dark border-warning" aria-labelledby="navbarDropdown">
                    <a class="dropdown-item text-warning disabled" href="index.php?lang=de&site=listhw"><?php echo str_listhw ?></a>
                    <a class="dropdown-item text-warning" href="index.php?lang=de&site=listsw"><?php echo str_listsw ?></a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-warning disabled" href="#"><?php echo str_addhw ?></a>
                    <a class="dropdown-item text-warning" href="index.php?lang=de&site=addsw"><?php echo str_addsw ?></a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-warning disabled" href="#"><?php echo str_srchhw ?></a>
                    <a class="dropdown-item text-warning" href="index.php?lang=de&site=srchsw"><?php echo str_srchsw ?></a>
                </div>
            </li>        
                <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-warning" href="#" id="navbarDropdown" role="button" data-toggle="dropdown"><?php echo str_acp_link ?></a>
                <div class="dropdown-menu bg-dark border-warning" aria-labelledby="navbarDropdown">
                    <a class="dropdown-item text-warning" href="index.php?lang=de&site=adduser"><?php echo str_acp_adduser ?></a>
                    <a class="dropdown-item text-warning" href="index.php?lang=de&site=userview"><?php echo str_acp_userview ?></a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-warning disabled" href="#"><?php echo str_acp_brand ?></a>
                    <a class="dropdown-item text-warning disabled" href="#"><?php echo str_acp_category ?></a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-warning disabled" href="#"><?php echo str_acp_settings ?></a>
                </div>
            </li>
            
            <li class="nav-item active"><a href="index.php?lang=de&site=logout" class="nav-link text-warning"><?php echo str_nav_logout ?></a></li>
        <?php
        }
        ?>
        </ul>
    </div>
</nav>