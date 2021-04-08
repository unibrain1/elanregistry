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

    /*
     *  Instantiates the Car object.  
     *  
     *  @param int Optional Car ID.   If this is given the information for Car will be populated.
     *  @return Bool Always true
     *  @access public 
     */
    public function __construct($id = null)
    {
        global $user; // Get the logged in user 

        $this->_db = DB::getInstance();
        $settings = getSettings();  // Get global settings from plugin
        $this->imageDir = $settings->elan_image_dir;

        // Get the logged in user information
        if (isset($user) && $user->isLoggedIn()) {
            $this->_owner = $user->data(); // TODO this should be from the user/profile JOIN
        }

        if ($id) {
            $this->find($id);
        }
        return true;
    }

    /*
     *  Creates a Car in the Database  
     *  
     *  @param array Key value pairs for car data
     *  @return Bool True of car is created.
     *  @access public 
     */
    public function create($fields = [])
    {
        if (empty($fields)) {
            return false;
        }  //TOD test this

        $fields['ctime'] = date('Y-m-d G:i:s');
        if (!empty($fields['images'])) {
            $fields['image'] = json_encode($fields['images']); //TODO until the DB field is renamed images
            unset($fields['images']);  //TODO until the DB field is renamed images
        }

        if (!$this->_db->insert($this->tableName, $fields)) {
            throw new Exception($this->_db->errorString());
        } else {
            $id = $this->_db->lastId();
            $this->find($id);  // Populate the car with the data
            $this->_db->insert('car_user', array('userid' => $this->data()->user_id, 'carid' => $id));
            return true;
        }
    }
    public function update($fields = [])
    {
        if (is_null($fields['id'])) {
            return false;
        }

        $fields['mtime'] = date('Y-m-d G:i:s');
        if (!empty($fields['images'])) {
            $fields['image'] = json_encode($fields['images']); //TODO until the DB field is renamed images
            unset($fields['images']);  //TODO until the DB field is renamed images
        }

        if (!$this->_db->update($this->tableName, $fields['id'], $fields)) {
            throw new Exception('There was a problem updating.');
        } else {
            $this->find($fields['id']);  // Populate the car with the data
        }

        return true;
    }
    public function find($carID = null)
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
            $temp = pathinfo($abs_us_root . $us_url_root . $this->imageDir  . $carImages[$key]);
            $file = $temp['dirname'] . "/" . $temp['basename'];
            if (is_file($file)) {
                // Do not include name if file does not exist
                $images[$key] = $temp;
                $images[$key]['url'] = $us_url_root . $this->imageDir . $images[$key]['basename'];
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
        foreach ($search as $s) {
            $factory = $this->_db->query('SELECT * FROM elan_factory_info WHERE serial = ? ', [$s]);
            // Did it return anything?
            if ($factory->count()) {
                if ($factory->first()->suffix != "") {
                    $factory->first()->suffix = $factory->first()->suffix . " (" . $this->suffixtotext($factory->first()->suffix) . ")";
                }
                $this->_factory = $factory->first();
            } else {
                $this->_factory = null;
            }
        }

        // Get the car owner.  
        // Owner Information is copied from the _data section for now but could be retrieved from DB
        $this->_owner = [
            'user_id'  => $this->_data->user_id,
            'email'  => $this->_data->email,
            'fname'  => $this->_data->fname,
            'lname'  => $this->_data->lname,
            'join_date' => $this->_data->join_date,
            'city'  => $this->_data->city,
            'state'  => $this->_data->state,
            'country'  => $this->_data->country,
            'lat'  => $this->_data->lat,
            'lon'  => $this->_data->lon
        ];

        return true;
    }
    public function findAll()
    {
        $this->_data = $this->_db->findAll($this->tableName)->results();

        return true;
    }
    public function exists()
    {
        return (!empty($this->_data)) ? true : false;
    }
    public function data()
    {
        return $this->_data;
    }
    public function history()
    {
        return $this->_history;
    }
    public function factory()
    {
        return $this->_factory;
    }
    public function images()
    {
        return $this->_images;
    }
    public function owner()
    {
        return $this->_owner;
    }
    private function suffixtotext($suffix)
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
                $desc = "Error";
        }
        return $desc;
    }
}
