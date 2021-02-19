<?php

// Get the car history
require_once '../../users/init.php';

//Forms posted now process it
if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        // Get the DB
        $db = DB::getInstance();

        $table = Input::get('table');
        switch ($table) {
            case 'cars':
                $table = 'cars';
                break;

            case 'factory':
                $table = 'elan_factory_info';
                break;

            default:
                $table = 'cars';
                break;
        }

        $draw = Input::get('draw');
        $row = Input::get('start');
        $rowperpage = Input::get('length'); // Rows display per page
        $columnIndex = $_POST['order'][0]['column']; // Column index
        $columnName = $_POST['columns'][$columnIndex]['data']; // Column name
        $columnSortOrder = $_POST['order'][0]['dir']; // asc or desc
        $searchValue = $_POST['search']['value']; // Search value

        $sort = [];
        ## Get the order by
        foreach ($_POST['order'] as $key => $order) {
            $columnIndex = $order['column'];
            $columnName = $_POST['columns'][$columnIndex]['data'];
            $columnSortOrder = $order['dir'];
            $sort[$key] = "$columnName $columnSortOrder";
        }
        $sortValue = implode(',', $sort);

        ## Search 
        $searchQuery = " ";
        if ($searchValue != '') {
            $searchQuery = " and (";

            ## Find the searchable columns
            foreach ($_POST['columns'] as $key => $column) {
                if ($column['searchable']) {
                    $columnName = $_POST['columns'][$key]['data'];
                    $searchQuery .=  "$columnName like '%$searchValue%' or ";
                }
            }
            $searchQuery .= " 0)";
        }


        ## Total number of records without filtering
        $totalRecords  = $db->findAll($table)->count();

        ## Total number of record with filtering
        $Q = $db->query("SELECT * FROM $table WHERE 1 $searchQuery");
        $totalRecordwithFilter = $Q->count();

        ## Fetch records
        // $empQuery = "select * from employee WHERE 1 " . $searchQuery . " order by " . $columnName . " " . $columnSortOrder . " limit " . $row . "," . $rowperpage;
        $Q = $db->query("SELECT * FROM $table WHERE 1 $searchQuery order by $sortValue limit $row,$rowperpage");

        $data = $Q->results();

        // $carQ  = $db->findAll('cars');
        // $cars  = $carQ->results();
        // $count = $carQ->count();
        // $error = ""; // Place holder for error messages.  If there is text in here it issues a pop-up.  Do not include if there is no error.

        // echo json_encode(array('draw' => $draw, 'recordsTotal' => $count, 'recordsFiltered' => $count, 'history' => $cars));

        ## Response
        $response = array(
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalRecordwithFilter,
            "data" => $data
        );

        echo json_encode($response);
    }
}
