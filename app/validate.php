<?php
/*
 * Functions to validate various aspectes of a car
 */

function validateVIN($vin){
    global $successes;
    global $errors;

    $valid_type = array("26", "36", "45", "50");
    $valid_suffix = array("A", "B", "C", "D", "E", "F", "G", "H", "J", "K", "L", "M", "N");

    $len = strlen($vin);

    switch ($len) {
        case 7:  // 26/5890
            sscanf($vin, "%2s/%4d", $type, $serial);
            if (ctype_digit($type) and ctype_digit($serial) and in_array($type, $valid_type)) {
                $successes[] = "$len digits code entered $vin. Type: $type, Serial: $serial";
            } else {
                $errors[] = "Type should be 26 or 36 or 45.  Serial should be 4 digits.  You entered $vin";
            }
            break;

        case 8:  // 26/5890x 
            sscanf($vin, "%2s/%4d%1s", $type, $serial, $suffix);
            if (ctype_digit($type) and ctype_digit($serial) and ctype_alpha($suffix) and in_array($type, $valid_type)) {
                $successes[] = "$len digits code entered $vin. Type: $type, Serial: $serial, Suffix: $suffix";
            } else {
                $errors[] = "Type should be 26 or 36 or 45.  Serial should be 4 digits.  You entered $vin";
            }
            break;

        case 11:
            sscanf($vin, "%2d%2d%2d%4d%1s", $year, $month, $batch, $serial, $suffix);
            $suffix = strtoupper($suffix);
            if (ctype_digit($year) and ctype_digit($month) and ctype_digit($batch) and ctype_digit($serial) and ctype_alpha($suffix) and in_array($suffix, $valid_suffix)) {
                $successes[] = "$len digits code entered $vin. Year: $year, Month: $month, Batch: $batch, Serial: $serial, Suffix: $suffix";
            } else {
                $errors[] = "Incorrect format.  The number should be all digits with 1 character at the end.  You entered $vin";
            }

            break;
        default:
            $errors[] = "Number is incomplete.  $len digits code entered $vin";
            break;
    }
    if (!$errors == '') {
        return false;
    } else {
        return true;
    }
}


function suffixtotext( $suffix ) {
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
?>
