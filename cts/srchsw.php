<h3 class="text-light pl-4 pt-4"><?php echo str_head_searchGame ?></h3>
<div  class="container-fluid text-light pb-3">
    <div class="container-fluid m-3">
        <form action="<?php $_SERVER['PHP_SELF'] ?>" class="form-inline" method="post">
            <input class="form-control mr-sm-2" type="search" placeholder="Search" aria-label="Search" name="search_title">
            <button class="btn btn-outline-success my-2 my-sm-0" type="submit" name="search">Search</button>
          </form>
    </div>
    
</div>
<hr style="background-color: yellow" />

<?php
if(isset($_POST['search'])) {  

$searching = $_POST['search_title'].'%';
    
$search = $db->query("SELECT * FROM collecting_games WHERE cg_title LIKE '$searching' ORDER BY cg_category, cg_title ASC");
$row = $search->fetchAll();
$eintraege = $search->rowCount();    
    
?>
<div  class="container-fluid text-light pb-3">
    <div class="container-fluid m-0">
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
        </table>
<?php
}
?>
        <center>
        <a id="back-to-top" href="#" class="btn btn-primary btn-lg back-to-top " role="button"><img src="./img/top.png" class="d-inline-block align-middle" alt=""></a></center>
    </div>
</div>