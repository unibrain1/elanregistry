<?php

class Car
{
    private $_db;
    private $_data;

    public $tableName = 'cars';
    public $historyTableName = 'cars_hist';

    public function __construct($id = null)
    {
        $this->_db = DB::getInstance();
        if ($id) {
            $this->find($id);
        }
    }


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
        }
    }

    public function update($fields = [], $id = null)
    {
        // TODO
        // Deal with NULL id  - aka new car vs update

        if (!empty($fields['images'])) {
            $fields['image'] = json_encode($fields['images']); //TODO until the DB field is renamed images
            unset($fields['images']);  //TODO until the DB field is renamed images
        }

        if (!$this->_db->update($this->tableName, $id, $fields)) {
            throw new Exception('There was a problem updating.');
        } else {
            $this->find($id);  // Populate the car with the data
        }
    }

    public function find($id = null)
    {
        global $us_url_root;
        global $abs_us_root;

        $settings = getSettings();  // Get global settings plugin

        $data = $this->_db->get($this->tableName, ['id', '=', $id]);

        if ($data->count() === 0) {
            return false;
        }

        $this->_data = $data->first();


        // Turn images into array 
        // Images can be encoded as JSON or simple CSV
        $carImages = json_decode($this->_data->image);

        if (is_null($carImages)) {
            $carImages = explode(',', $this->_data->image);
        }

        unset($this->_data->image);  // We don't need this element.  

        $images = [];
        foreach ($carImages as $key => $carimage) {
            $temp = pathinfo($abs_us_root . $us_url_root . $settings->elan_image_dir  . $carImages[$key]);
            $file = $temp['dirname'] . "/" . $temp['basename'];
            if (is_file($file)) {
                // Do not include name if file does not exist
                $images[$key] = $temp;
                $images[$key]['url'] = $us_url_root . $settings->elan_image_dir . $images[$key]['filename'];
                $images[$key]['size'] = filesize($file);
                $images[$key]['type'] = image_type_to_extension(exif_imagetype($file), false);
                $images[$key]['mime'] = mime_content_type($file);
            }
        }
        $this->_data->images =  array_values($images);  // Reindex in case a file didn't exist

        // Car history data
        $data = $this->_db->query("SELECT * from $this->historyTableName WHERE car_id = ? ORDER BY timestamp DESC", [$id]);
        if ($data->count()) {
            $this->_data->history = $data->results();
        } else {
            $this->_data->history = null;
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
                $this->_data->factory = $factory->first();
            } else {
                $this->_data->factory = null;
            }
        }
        return true;
    }

    public function list()
    {
        $this->_db->findAll($this->tableName);

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
        return $this->_data->history;
    }
    public function factory()
    {
        return $this->_data->factory;
    }

    public function images()
    {
        return $this->_data->image;
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
// 