<?php
session_destroy();
header("Location: index.php?lang=de&site=login");
?>
<h3 class="text-light pl-4 pt-4"><?php echo str_head_logout ?></h3> 
<div  class="container-fluid text-light">
    <div class="container-fluid">
        <p class="subtitle-1 pl-4"><?php echo str_logout_success ?></p>
    </div>
</div>