

<h3 class="text-light pl-4 pt-4"><?php echo str_head_userView ?></h3>

<?php
$sql = $db->query("SELECT * FROM users ORDER BY username ASC");
$row = $sql->fetchAll();
$eintraege = $sql->rowCount();



?>
<div  class="container-fluid text-light">
    <div class="container-fluid">
         <table class="table table-striped table-dark table-hover">
              <thead>
                <tr>
                  <th scope="col">#</th>
                  <th scope="col"><?php echo str_userview_email ?></th>
                  <th scope="col"><?php echo str_userview_uname ?></th>                  
                </tr>
              </thead>
              <tbody>
<?php
$nummer =1;                
foreach ($row as $row) {
    ?>
                <tr>
                    <th scope="row"><?php echo $nummer; ?></th>
                    <td><?php echo $row['email']; ?></td>
                    <td><?php echo $row['username']; ?></td>                    
                </tr>
                <tr>                    
                    <td colspan="3">
                        <a class="btn btn-primary mr-1" href="#" role="button"><img src="./img/16/information.png" class="align-middle m-0" alt=""></a> <a class="btn btn-primary mr-1" href="#" role="button"><img src="./img/16/edit.png" class="align-middle m-0" alt=""></a> <a class="btn btn-primary mr-1" href="#" role="button"><img src="./img/16/cancel.png" class="align-middle m-0" alt=""></a>
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

