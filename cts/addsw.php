<?php

if (isset($_POST['submit'])) {
    if(isset($_POST['game_title']) AND $_POST['game_system']!=5 AND $_POST['game_region']!=5 AND $_POST['game_cart_cond']!=5 AND $_POST['game_box_cond']!=5 AND $_POST['game_manual_cond']!=5) {
        
        $gametitle   = $_POST['game_title'];
        $gamesystem  = $_POST['game_system'];
        $gameregion  = $_POST['game_region'];
        $cart_cond   = $_POST['game_cart_cond'];
        $box_cond    = $_POST['game_box_cond'];
        $manual_cond = $_POST['game_manual_cond'];
        $syncdate = time();
            
        $entry_game = $db->prepare('INSERT INTO collecting_games (cg_title, cg_category, cg_region, cg_cart, cg_box, cg_manual, syncdate) VALUES (:title, :cat, :region, :cart, :box, :manual, :zeit)');
        
        $entry_game->execute(array(':title'=>$gametitle, ':cat'=>$gamesystem, ':region'=>$gameregion, ':cart'=>$cart_cond, ':box'=>$box_cond, ':manual'=>$manual_cond, ':zeit'=>$syncdate));
        
        echo '<div class="container-fluid"><div class="box bg-success text-light ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/accept.png" class="d-inline-block align-middel m-0" alt=""> '.str_form_success.'</div></div>';
        
    } else { echo '<div class="container-fluid"><div class="box bg-warning ml-3 mr-3 mt-4 pl-2 pt-3 pb-3 rounded-lg"><img src="./img/16/exclamation.png" class="d-inline-block align-middel m-0" alt=""> '.str_form_warning.'</div></div>'; }
}



$sql = $db->query("SELECT * FROM categories ORDER BY cat_name ASC");
$row = $sql->fetchAll();

?>
<h3 class="text-light pl-4 pt-4"><?php echo str_head_entryGame ?></h3> 
<div  class="container-fluid text-light">
    <div class="container-fluid">
        
        <form action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
                <div class="row">
                    <div class="form-group col-12 col-sm-6">
                        <label><?php echo str_gametitle ?></label>
                        <input type="text" class="form-control" placeholder="" name="game_title">
                    </div>
                    <div class="form-group col-12 col-sm-6">
                        <label><?php echo str_gamesystem ?></label>
                        <select class="form-control" name="game_system">
                            <option value="5" selected  class="bg-dark text-light"><?php echo str_gs_choice ?></option>
                            <?php
                            foreach ($row as $row) { ?>
                            <option value="<?php echo $row['cat_short']; ?>" class="bg-dark text-light"><?php echo $row['cat_name']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="row">                    
                    <div class="form-group col-12 col-sm-6">
                        <label><?php echo str_gameregion ?></label>
                        <select class="form-control" name="game_region">
                            <option value="5" selected  class="bg-dark text-light"><?php echo str_gameregion_choice ?></option>
                            <option value="PAL" class="bg-dark text-light"><?php echo str_gr_pal ?></option>
                            <option value="JAP" class="bg-dark text-light"><?php echo str_gr_jntsc ?></option>
                            <option value="NTSC" class="bg-dark text-light"><?php echo str_gr_ntsc ?></option>
                        </select>
                    </div>
                    <div class="form-group col-12 col-sm-6">
                        <label><?php echo str_CartCD ?></label>
                        <select class="form-control" name="game_cart_cond">
                            <option value="5" selected  class="bg-dark text-light"><?php echo str_cond_choice ?></option>
                            <option value="0" class="bg-dark text-light"><?php echo str_cond0 ?></option>
                            <option value="1" class="bg-dark text-light"><?php echo str_cond1 ?></option>
                            <option value="2" class="bg-dark text-light"><?php echo str_cond2 ?></option>
                            <option value="3" class="bg-dark text-light"><?php echo str_cond3 ?></option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-12 col-sm-6">
                        <label><?php echo str_box ?></label>
                        <select class="form-control" name="game_box_cond">
                            <option value="5" selected  class="bg-dark text-light"><?php echo str_cond_choice ?></option>
                            <option value="0" class="bg-dark text-light"><?php echo str_cond0 ?></option>
                            <option value="1" class="bg-dark text-light"><?php echo str_cond1 ?></option>
                            <option value="2" class="bg-dark text-light"><?php echo str_cond2 ?></option>
                            <option value="3" class="bg-dark text-light"><?php echo str_cond3 ?></option>
                        </select>
                    </div>
                    <div class="form-group col-12 col-sm-6">
                        <label><?php echo str_manual ?></label>
                        <select class="form-control" name="game_manual_cond">
                            <option value="5" selected  class="bg-dark text-light"><?php echo str_cond_choice ?></option>
                            <option value="0" class="bg-dark text-light"><?php echo str_cond0 ?></option>
                            <option value="1" class="bg-dark text-light"><?php echo str_cond1 ?></option>
                            <option value="2" class="bg-dark text-light"><?php echo str_cond2 ?></option>
                            <option value="3" class="bg-dark text-light"><?php echo str_cond3 ?></option>
                        </select>
                    </div>
                </div>
            
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-primary" name="submit"><?php echo str_submit_entry ?></button>
                </div>
            </form>
        
        
    </div>
</div>