<?php

//UserSpice User-Related functions
//Do not deactivate!

//Check if a user ID exists in the DB
if (!function_exists('userIdExists')) {
    function userIdExists($id)
    {
        $db = DB::getInstance();
        $query = $db->query('SELECT * FROM users WHERE id = ?', [$id]);
        $num_returns = $query->count();
        if ($num_returns > 0) {
            return true;
        } else {
            return false;
        }
    }
}

//Retrieve information for all users
if (!function_exists('fetchAllUsers')) {
    function fetchAllUsers($orderBy = [], $desc = [], $disabled = true)
    {
        $db = DB::getInstance();
        $q = 'SELECT * FROM users';
        if (!$disabled) {
            $q .= ' WHERE permissions=1';
        }
        if (!empty($orderBy)) {
            if ($desc === true) {
                $q .= " ORDER BY $orderBy DESC";
            } else {
                $q .= " ORDER BY $orderBy";
            }
        }
        $query = $db->query($q);
        $results = $query->results();

        return $results;
    }
}

//Retrieve complete user information by username, token or ID
if (!function_exists('fetchUserDetails')) {
    function fetchUserDetails($username = null, $token = null, $id = null)
    {
        $db = DB::getInstance();
        $column = $data = $results = null;

        if ($username != null) {
            $column = 'username';
            $data = $username;
        } elseif ($id != null) {
            $column = 'id';
            $data = $id;
        }

        if (!is_null($column) && !is_null($data)) {
            $query = $db->query("SELECT * FROM users WHERE $column = ? LIMIT 1", [$data]);
            if ($query->count() == 1) {
                $results = $query->first();
            }
        }

        return $results;
    }
}

//Delete a defined array of users
if (!function_exists('deleteUsers')) {
    function deleteUsers($users)
    {
        global $abs_us_root, $us_url_root;
        $db = DB::getInstance();
        $i = 0;
        foreach ($users as $id) {
            $query1 = $db->query('DELETE FROM users WHERE id = ?', [$id]);
            $query2 = $db->query('DELETE FROM user_permission_matches WHERE user_id = ?', [$id]);
            if (file_exists($abs_us_root.$us_url_root.'usersc/scripts/after_user_deletion.php')) {
                include $abs_us_root.$us_url_root.'usersc/scripts/after_user_deletion.php';
            }
            ++$i;
        }

        return $i;
    }
}

if (!function_exists('echouser')) {
    function echouser($id)
    {
        $db = DB::getInstance();
        $settingsQ = $db->query('SELECT echouser FROM settings');
        $settings = $settingsQ->first();

        if ($settings->echouser == 0) {
            $query = $db->query('SELECT fname,lname FROM users WHERE id = ? LIMIT 1', [$id]);
            $count = $query->count();
            if ($count > 0) {
                $results = $query->first();
                echo $results->fname.' '.$results->lname;
            } else {
                echo 'Unknown';
            }
        }

        if ($settings->echouser == 1) {
            $query = $db->query('SELECT username FROM users WHERE id = ? LIMIT 1', [$id]);
            $count = $query->count();
            if ($count > 0) {
                $results = $query->first();
                echo ucfirst($results->username);
            } else {
                echo '-';
            }
        }

        if ($settings->echouser == 2) {
            $query = $db->query('SELECT username,fname,lname FROM users WHERE id = ? LIMIT 1', [$id]);
            $count = $query->count();
            if ($count > 0) {
                $results = $query->first();
                echo ucfirst($results->username).' ('.$results->fname.' '.$results->lname.')';
            } else {
                echo 'Unknown';
            }
        }

        if ($settings->echouser == 3) {
            $query = $db->query('SELECT username,fname FROM users WHERE id = ? LIMIT 1', [$id]);
            $count = $query->count();
            if ($count > 0) {
                $results = $query->first();
                echo ucfirst($results->username).' ('.$results->fname.')';
            } else {
                echo 'Unknown';
            }
        }
    }
}

if (!function_exists('echousername')) {
    function echousername($id)
    {
        $db = DB::getInstance();
        $query = $db->query('SELECT username FROM users WHERE id = ? LIMIT 1', [$id]);
        $count = $query->count();
        if ($count > 0) {
            $results = $query->first();

            return $results->username;
        } else {
            return 'Unknown';
        }
    }
}

if (!function_exists('updateUser')) {
    //Update User
    function updateUser($column, $id, $value)
    {
        $db = DB::getInstance();
        $result = $db->query("UPDATE users SET $column = ? WHERE id = ?", [$value, $id]);

        return $result;
    }
}

if (!function_exists('fetchUserName')) {
    //Fetchs CONCAT of Fname Lname
    function fetchUserName($username = null, $token = null, $id = null)
    {
        if ($username != null) {
            $column = 'username';
            $data = $username;
        } elseif ($id != null) {
            $column = 'id';
            $data = $id;
        }
        $db = DB::getInstance();
        $query = $db->query("SELECT CONCAT(fname,' ',lname) AS name FROM users WHERE $column = $data LIMIT 1");
        $count = $query->count();
        if ($count > 0) {
            $results = $query->first();

            return $results->name;
        } else {
            return 'Unknown';
        }
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        global $user;
        if (($user->isLoggedIn() && hasPerm([2], $user->data()->id)) || (isset($_SESSION['cloak_from']) && hasPerm([2], $_SESSION['cloak_from']))) {
            return true;
        } else {
            return false;
        }
    }
}
