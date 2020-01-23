<div class="col-sm-8">
  <div class="page-header float-right">
    <div class="page-title">
      <ol class="breadcrumb text-right">
        <ol class="breadcrumb text-right">
          <li><a href="<?=$us_url_root?>users/admin.php">Dashboard</a></li>
          <li class="active">Admin Registry</li>
        </ol>
      </ol>
    </div>
  </div>
</div>
</div>
</header>

<div class="content mt-3">
<h2>Admin Utilities</h2>
<hr>

<!-- Button to Open the Modal -->
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#badUser">Remove Bad Users</button>
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#cleanProfile">Clean Unused Profiles</button>
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#orphanCars">Assign Orphan Cars</button>

<!-- The Modal for Bad Users-->
        <div class="modal fade" id="badUser">
          <div class="modal-dialog">
            <div class="modal-content">

              <!-- Modal Header -->
              <div class="modal-header">
                <h4 class="modal-title">Remove Bad Users</h4>c
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>

              <!-- Modal body -->
              <div class="modal-body">
                <?php
                  $q="
                  SELECT  users.id
                  FROM users
                  LEFT JOIN car_user
                  ON (users.id = car_user.userid)
                  where ( users.email_verified = 0 AND users.last_login = 0 AND car_user.carid is NULL AND users.join_date  < CURRENT_DATE - INTERVAL 30 DAY)
                  GROUP BY users.id 
                  ";

                  $usersQ = $db->query( $q );
                  echo "Delete ". $usersQ->count() ." SPAM users</br>";
                  $users = $usersQ->results();
                  foreach($users as $u)
                  {
                    echo "- user_id ". $u->id ."</br>" ;
                    deleteUsers(array($u->id));
                    $db->query("DELETE FROM profiles WHERE user_id = ?",array($u->id));
                  }

                ?>              
            </div>

      <!-- Modal footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<!-- The Modal for Clean Profiles-->
        <div class="modal fade" id="cleanProfile">
          <div class="modal-dialog">
            <div class="modal-content">

              <!-- Modal Header -->
              <div class="modal-header">
                <h4 class="modal-title">Clean Unused Profiles</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>

              <!-- Modal body -->
              <div class="modal-body">
                <?php
                $q="
                SELECT t1.user_id
                FROM profiles t1
                    LEFT JOIN users t2 ON t1.user_id = t2.id
                WHERE t2.id IS NULL
                ";

                $profileQ = $db->query( $q );

                echo "Delete ". $profileQ->count() ." profiles</br>";

                $profile = $profileQ->results();
                foreach($profile as $p)
                {
                  echo "- user_id ". $p->user_id ."</br>" ;
                  $db->query("DELETE FROM profiles WHERE user_id = ?",array($p->user_id));
                }
                ?>              
              </div>

      <!-- Modal footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<!-- The Modal for Orphan Cars-->
        <div class="modal fade" id="orphanCars" role="dialog">
          <div class="modal-dialog">
            <div class="modal-content">

              <!-- Modal Header -->
              <div class="modal-header">
                <h4 class="modal-title">Assign Orphan Cars</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>

              <!-- Modal body -->
              <div class="modal-body">
                <?php
                $q="
                SELECT t1.userid
                FROM car_user t1
                    LEFT JOIN users t2 ON t1.userid = t2.id
                WHERE t2.id IS NULL
                ";
                $qResult = $db->query( $q );
                echo "There are ". $qResult->count() ." car_user rows without corresponding owner</br>";

                $profile = $qResult->results();
                foreach($profile as $p)
                {
                  echo "- userid ". $p->userid ."</br>" ;
                  $db->query("DELETE FROM car_user WHERE userid = ?",array($p->userid));
                }


                $q="
                SELECT t1.id
                FROM cars t1
                    LEFT JOIN car_user t2 ON t1.id = t2.carid
                WHERE t2.carid IS NULL";

                $qResult = $db->query( $q );

                echo "There are ". $qResult->count() ." cars  without corresponding car_owner entry</br>";

                $car = $qResult->results();
                foreach($car as $c)
                {
                  echo "- carid ". $c->id ."</br>" ;
                  $db->query("INSERT INTO car_user (userid, carid ) VALUES (83, ?)",array($c->id));
                }


                ?>
              </div>

      <!-- Modal footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<hr>
</div>
