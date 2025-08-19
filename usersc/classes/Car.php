<?php

/**
 *
 *  Car is a class for managing Car data
 *
 *  Car is a class that is used for creating, updating and retrieving information
 * about a Car for the Lotus Elan Registry
 *
 *  @author Jim Boone
 *  @version $Revision: 0.1 $
 *  @access public
 */

class Car
{
    private $_db;
    private $_data;
    private $_history;
    private $_images;
    private $_factory;
    private $_owner;
    private $tableName = 'cars';
    private $historyTableName = 'cars_hist';
    private $imageDir = '';

    /**
     * Instantiates the Car object.
     *
     * @param int|null $id Optional Car ID. If given, the information for Car will be populated.
     * @return void
     */
    public function __construct(?int $id = null)
    {
        global $user; // Get the logged in user

        $this->_db = DB::getInstance();
        $settings = getSettings();  // Get global settings from plugin

        // Get the logged in user information
        if (isset($user) && $user->isLoggedIn()) {
            $this->_owner = $user->data(); // TODO this should be from the user/profile JOIN
        }

        if ($id) {
            $this->imageDir = $settings->elan_image_dir  . $id . '/';
            $this->find($id);
        }
        return true;
    }

    /**
     * Creates a Car in the Database
     *
     * @param array $fields Key value pairs for car data
     * @return bool True if car is created
     */
    public function create(array $fields = []): bool
    {
        $settings = getSettings();  // Get global settings from plugin

        if (empty($fields)) {
            return false;
        }

        $fields['ctime'] = date('Y-m-d G:i:s');
        if (!empty($fields['images'])) {
            $fields['image'] = json_encode($fields['images']);
            unset($fields['images']);
        }

        if (!$this->_db->insert($this->tableName, $fields)) {
            throw new Exception($this->_db->errorString());
        } else {
            $id = $this->_db->lastId();
            $this->find($id);  // Populate the car with the data
            $this->imageDir = $settings->elan_image_dir  . $id . '/';
            $this->_db->insert('car_user', array('userid' => $this->data()->user_id, 'carid' => $id));
            return true;
        }
    }
    /**
     * Update an existing car record
     *
     * @param array $fields Car data to update
     * @return bool True if update succeeds
     */
    public function update(array $fields = []): bool
    {
        if (is_null($fields['id'])) {
            return false;
        }

        $fields['mtime'] = date('Y-m-d G:i:s');
        if (!empty($fields['images'])) {
            $fields['image'] = json_encode($fields['images']);
            unset($fields['images']);
        }

        if (!$this->_db->update($this->tableName, $fields['id'], $fields)) {
            throw new Exception('There was a problem updating.');
        } else {
            $this->find($fields['id']);  // Populate the car with the data
        }

        return true;
    }
    /**
     * Find car by ID or return all cars
     *
     * @param int|null $carID Car ID to find
     * @return bool True if found, false otherwise
     */
    public function find(?int $carID = null): bool
    {
        global $us_url_root;
        global $abs_us_root;

        if (is_null($carID)) {
            return $this->findAll();
        }

        $data = $this->_db->get($this->tableName, ['id', '=', $carID]);

        if ($data->count() === 0) {
            return false;
        }

        $this->_data = $data->first();
        // Get the car images
        // Turn images into array
        // Images can be encoded as JSON or simple CSV
        $carImages = json_decode($this->_data->image);

        if (is_null($carImages)) {
            $carImages = explode(',', $this->_data->image);
        }

        $images = [];
        foreach ($carImages as $key => $carimage) {
            $temp = pathinfo($abs_us_root . $us_url_root . $this->imageDir . $carImages[$key]);
            $file = $temp['dirname'] . "/" . $temp['basename'];
            if (is_file($file)) {
                // Do not include name if file does not exist
                $images[$key] = $temp;
                $images[$key]['path'] = $us_url_root . $this->imageDir . $images[$key]['basename'];
                $images[$key]['size'] = filesize($file);
                $images[$key]['type'] = image_type_to_extension(exif_imagetype($file), false);
                $images[$key]['mime'] = mime_content_type($file);
            }
        }
        $this->_images =  array_values($images);  // Reindex in case a file didn't exist

        // Get the car history
        $data = $this->_db->query("SELECT * from $this->historyTableName WHERE car_id = ? ORDER BY timestamp DESC", [$carID]);
        if ($data->count()) {
            $this->_history = $data->results();
        } else {
            $this->_history = null;
        }

        // Search in the elan_factory_info for details on the car.
        // The car.chassis can either match exactly (car.chassis = elan_factory_info.serial )
        //    or
        // The right most 5 digits of the car.chassis (post 1970 and some 1969) will =  elan_factory_info.serial

        $search = array($this->_data->chassis, substr($this->_data->chassis, -5));

        $factory = null;
        foreach ($search as $serialNumber) {
            $factory = $this->_db->query('SELECT * FROM elan_factory_info WHERE serial = ? ', [$serialNumber]);
            // Did it return anything?
            if ($factory->count()) {
                if ($factory->first()->suffix !== "") {
                    $factory->first()->suffix = $factory->first()->suffix .
                        " (" . $this->suffixtotext($factory->first()->suffix) . ")";
                }
                $this->_factory = $factory->first();
                break; // Found a match, no need to continue
            } else {
                $this->_factory = null;
            }
        }

        // Get the car owner
        // Owner Information is copied from the _data section for now but could be retrieved from DB
        $this->_owner = [
            'user_id'   => $this->_data->user_id,
            'email'     => $this->_data->email,
            'fname'     => $this->_data->fname,
            'lname'     => $this->_data->lname,
            'join_date' => $this->_data->join_date,
            'city'      => $this->_data->city,
            'state'     => $this->_data->state,
            'country'   => $this->_data->country,
            'lat'       => $this->_data->lat,
            'lon'       => $this->_data->lon
        ];

        return true;
    }
    /**
     * Find all cars
     *
     * @return bool Always returns true
     */
    public function findAll(): bool
    {
        $this->_data = $this->_db->findAll($this->tableName)->results();

        return true;
    }
    /**
     * Check if car data exists
     *
     * @return bool True if car data exists
     */
    public function exists(): bool
    {
        return (!empty($this->_data)) ? true : false;
    }
    /**
     * Get car data
     *
     * @return mixed Car data object or array
     */
    public function data()
    {
        return $this->_data;
    }
    /**
     * Get car history
     *
     * @return array|null Car history array or null
     */
    public function history(): ?array
    {
        return $this->_history;
    }
    /**
     * Get factory information for this car
     *
     * @return object|null Factory data object or null
     */
    public function factory(): ?object
    {
        return $this->_factory;
    }
    /**
     * Get car images
     *
     * @return array Array of image information
     */
    public function images(): array
    {
        return $this->_images;
    }
    /**
     * Get car owner information
     *
     * @return array|object Owner information
     */
    public function owner()
    {
        return $this->_owner;
    }
    /**
     * Secure DataTables server-side processing for cars and factory tables
     * 
     * @param array $request DataTables request parameters (sanitized via Input::get)
     * @param string $table Table type ('cars' or 'factory')
     * @return array DataTables response array
     */
    public function getDataTablesData($request, $table = 'cars')
    {
        // Validate and sanitize table parameter
        $validTables = [
            'cars' => 'cars',
            'factory' => 'elan_factory_info'
        ];
        
        if (!isset($validTables[$table])) {
            throw new Exception("Invalid table specified");
        }
        
        $tableName = $validTables[$table];
        
        // Extract and validate DataTables parameters
        $draw = (int) $request['draw'];
        $start = (int) $request['start'];
        $length = (int) $request['length'];
        $searchValue = isset($request['search']['value']) ? trim($request['search']['value']) : '';
        
        // Build ORDER BY clause securely
        $orderClauses = [];
        if (isset($request['order']) && is_array($request['order'])) {
            foreach ($request['order'] as $order) {
                $columnIndex = (int) $order['column'];
                $direction = strtoupper($order['dir']) === 'DESC' ? 'DESC' : 'ASC';
                
                if (isset($request['columns'][$columnIndex]['data'])) {
                    $columnName = $this->validateColumnName($request['columns'][$columnIndex]['data'], $tableName);
                    if ($columnName) {
                        $orderClauses[] = "`{$columnName}` {$direction}";
                    }
                }
            }
        }
        $orderBy = !empty($orderClauses) ? 'ORDER BY ' . implode(', ', $orderClauses) : 'ORDER BY id ASC';
        
        // Build WHERE clause for search
        $searchWhere = '';
        $searchParams = [];
        if (!empty($searchValue)) {
            $searchConditions = [];
            if (isset($request['columns']) && is_array($request['columns'])) {
                foreach ($request['columns'] as $column) {
                    if (isset($column['searchable']) && $column['searchable'] === 'true' && isset($column['data'])) {
                        $columnName = $this->validateColumnName($column['data'], $tableName);
                        if ($columnName) {
                            $searchConditions[] = "`{$columnName}` LIKE ?";
                            $searchParams[] = "%{$searchValue}%";
                        }
                    }
                }
            }
            
            if (!empty($searchConditions)) {
                $searchWhere = 'AND (' . implode(' OR ', $searchConditions) . ')';
            }
        }
        
        // Get total records without filtering
        $totalRecords = $this->_db->query("SELECT COUNT(*) as count FROM `{$tableName}`")->first()->count;
        
        // Get total records with filtering
        $totalFiltered = $totalRecords;
        if (!empty($searchWhere)) {
            $filterQuery = "SELECT COUNT(*) as count FROM `{$tableName}` WHERE 1 {$searchWhere}";
            $totalFiltered = $this->_db->query($filterQuery, $searchParams)->first()->count;
        }
        
        // Get the actual data
        $dataQuery = "SELECT * FROM `{$tableName}` WHERE 1 {$searchWhere} {$orderBy} LIMIT {$start}, {$length}";
        $data = $this->_db->query($dataQuery, $searchParams)->results();
        
        return [
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ];
    }
    
    /**
     * Validate column names to prevent SQL injection
     * 
     * @param string $columnName Column name to validate
     * @param string $tableName Table name for context
     * @return string|false Validated column name or false if invalid
     */
    private function validateColumnName($columnName, $tableName)
    {
        // Define allowed columns for each table (based on actual schema)
        $allowedColumns = [
            'cars' => [
                'id', 'username', 'ctime', 'mtime', 'vericode', 'last_verified', 'ModifiedBy',
                'model', 'series', 'variant', 'year', 'type', 'chassis', 'color', 'engine',
                'purchasedate', 'solddate', 'comments', 'image', 'user_id', 'email', 'fname',
                'lname', 'join_date', 'city', 'state', 'country', 'lat', 'lon', 'website'
            ],
            'elan_factory_info' => [
                'id', 'year', 'month', 'batch', 'type', 'serial', 'suffix',
                'engineletter', 'enginenumber', 'gearbox', 'color', 'builddate', 'note'
            ]
        ];
        
        if (!isset($allowedColumns[$tableName])) {
            return false;
        }
        
        // Check if column name is in the allowed list
        if (in_array($columnName, $allowedColumns[$tableName], true)) {
            return $columnName;
        }
        
        return false;
    }

    /**
     * Convert suffix code to descriptive text
     *
     * @param string $suffix Suffix code
     * @return string Description of the suffix
     */
    private function suffixtotext(string $suffix): string
    {
        $s = strtoupper($suffix);

        switch ($s) {
            case "A":
                $desc = "S4 FHC UK Market";
                break;
            case "B":
                $desc = "S4 FHC Export";
                break;
            case "C":
                $desc = "S4 DHC UK Market";
                break;
            case "D":
                $desc = "S4 DHC Export";
                break;
            case "E":
                $desc = "S4 S/E FHC UK Market";
                break;
            case "F":
                $desc = "S4 S/E FHC Export";
                break;
            case "G":
                $desc = "S4 S/E DHC UK Market";
                break;
            case "H":
                $desc = "S4 S/E DHC Export";
                break;
            case "J":
                $desc = "S4 FHC Federal";
                break;
            case "K":
                $desc = "S4 DHC Federal";
                break;
            case "L":
                $desc = "+2S and +2S/130 UK Market";
                break;
            case "M":
                $desc = "+2S and +2S/130 Export";
                break;
            case "N":
                $desc = "+2S and +2S/130 Federal";
                break;

            default:
                $desc = "Unknown suffix: " . $suffix;
        }
        return $desc;
    }
}
