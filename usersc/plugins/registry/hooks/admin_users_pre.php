 
<?php 
// Override the default user selection query and get all users plus the carid


if(count(get_included_files()) ==1) die(); //Direct Access Not Permitted 

global $userData;  // We are going to overwrite/add to $userData

$q ="
SELECT  users.*, MAX(car_user.carid) as carid
FROM users
LEFT JOIN car_user
ON (users.id = car_user.userid)
GROUP BY users.id  
ORDER BY users.id ASC
";

$query = $db->query($q);
$userData = $query->results();

//dump($userData);

?>
