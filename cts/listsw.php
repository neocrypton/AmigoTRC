

<h3 class="text-light pl-4 pt-4"><?php echo str_head_showGames ?></h3>

<?php
$sql = $db->query("SELECT * FROM collecting_games ORDER BY cg_category, cg_title ASC");
$row = $sql->fetchAll();
$eintraege = $sql->rowCount();



?>
<p class="subtitle-1 pl-4"><?php echo str_gameview_info1 ?> <b class="text-danger"><?php echo $eintraege; ?></b> <?php echo str_gameview_info2 ?><br /></p>
<div  class="container-fluid text-light">
    <div class="container-fluid">
         <table class="table table-striped table-dark table-hover">
              <thead>
                <tr>
                  <th scope="col" width="15">#</th>
                  <th scope="col" class="text-center" width="18"><img src="./img/16/hw.png" class="d-inline-block align-middle m-0" alt=""></th>
                  <th scope="col"><?php echo str_gametitle ?></th>
                  
                </tr>
              </thead>
              <tbody>
<?php
$nummer =1;                
foreach ($row as $row) {
    ?>
                <tr>
                  <th scope="row"><?php echo $nummer; ?></th>
                    
                    <td class="text-center">
                    <?php echo $row['cg_category']; ?></td>
                    <td><?php echo $row['cg_title'].'<br />'; 
  
                        if($row['cg_region']=="PAL") { echo '<img src="./img/16/pal.png" class="d-inline-block align-middle m-0" alt=""> ';} else if($row['cg_region']=="NTSC") {echo '<img src="./img/16/us.png" class="d-inline-block align-middle m-0" alt=""> ';} else if($row['cg_region']=="JAP") {echo '<img src="./img/16/jap.png" class="d-inline-block align-middle m-0" alt=""> ';}
                        if($row['cg_cart']==1) { 
                            echo '<img src="./img/16/media1.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_cart']==2) { 
                            echo '<img src="./img/16/media2.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_cart']==3) { 
                            echo '<img src="./img/16/media3.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_cart']==0) { 
                            echo '<img src="./img/16/media0.png" class="d-inline-block align-middle m-0" alt=""> ';
                        }
    
                        if($row['cg_box']==1) { 
                            echo '<img src="./img/16/box1.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_box']==2) { 
                            echo '<img src="./img/16/box2.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_box']==3) { 
                            echo '<img src="./img/16/box3.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_box']==0) { 
                            echo '<img src="./img/16/box0.png" class="d-inline-block align-middle m-0" alt=""> ';
                        }
    
                        if($row['cg_manual']==1) { 
                            echo '<img src="./img/16/manual1.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_manual']==2) { 
                            echo '<img src="./img/16/manual2.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_manual']==3) { 
                            echo '<img src="./img/16/manual3.png" class="d-inline-block align-middle m-0" alt=""> ';
                        } else if($row['cg_manual']==0) { 
                            echo '<img src="./img/16/manual0.png" class="d-inline-block align-middle m-0" alt=""> ';
                        }
    
                    ?>
                    </td>
                </tr>
<?php
    $nummer++;
}
?>
              </tbody>
        </table><center>
        <a id="back-to-top" href="#" class="btn btn-primary btn-lg back-to-top mt-3 mb-3 " role="button"><img src="./img/top.png" class="d-inline-block align-middle m-0" alt=""></a></center>
    </div>
</div>

