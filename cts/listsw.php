<?php
$sql = 'SELECT * FROM collecting_games ORDER BY cg_category, cg_title ASC';
$result = $link->query($sql);

if (!$result) {
    die ('Etwas stimmte mit dem Query nicht: '.$db->error);
}
echo 'Die Ergebnistabelle besitzt '.$result->num_rows." Datensätze<br />\n";


?>

<div  class="container-fluid text-light">
    <div class="container-fluid">
         <table class="table table-striped table-dark table-hover">
              <thead>
                <tr>
                  <th scope="col" width="15">#</th>
                  <th scope="col" class="text-center" width="18"><img src="./img/16/hw.png" class="d-inline-block align-middle m-0" alt=""></th>
                  <th scope="col">Spieletitel</th>
                  
                </tr>
              </thead>
              <tbody>
<?php
while ($row = $result->fetch_assoc()) {
    ?>
                <tr>
                  <th scope="row">1</th>
                    
                    <td class="text-center">PS1</td>
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
}
?>
              </tbody>
        </table>
    </div>
</div>
