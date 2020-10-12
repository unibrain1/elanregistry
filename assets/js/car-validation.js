

// See  https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/HTML5/Constraint_validation
// for validation example
//
function checkVin() {

    // Get the chassis/vin field
    var chassisField = document.getElementById("chassis");


    var valid_type = ['26', '36', '45', '50'];
    var valid_suffix = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N'];

    var year = document.newCar.year.value;
    var chassis = document.newCar.chassis.value;

    var len = chassis.length;

    if (year >= 1970) {
        if (len !== 11) {
            chassisField.setCustomValidity("Invalid: 1970+ chassis should be 10 digits and 1 letter suffix. You entered " + len + " digits/characters. " + chassis + ".");
        } else {
            // YYMMBBSSSSx
            year = chassis.substring(0, 2);
            month = chassis.substring(2, 4);
            batch = chassis.substring(4, 6);
            serial = chassis.substring(6, 10);
            suffix = chassis.substring(10).toUpperCase();

            // YY, MM, BB, SS should all be numbers
            if (isNaN(year) || isNaN(month) || isNaN(batch) || isNaN(serial)) {
                chassisField.setCustomValidity("Invalid: 1970+ Year " + year + " Month " + month + " Batch " + batch + " Serial " + serial + " should be numbers.");
            } else {
                // Now check the suffix
                if (valid_suffix.indexOf(suffix) == -1) {
                    chassisField.setCustomValidity("Invalid Suffix:  1970+ Year " + year + " Month " + month + " Batch " + batch + " Serial " + serial + " Suffix " + suffix + ".");

                } else {
                    // chassisField.setCustomValidity("Valid 1970+  Chassis and Suffix:  Year "+ year + " Month " + month + " Batch " + batch + " Serial " + serial + " Suffix " + suffix + ".");
                    chassisField.setCustomValidity("");
                }
            }
        }
    } else {
        switch (len) {
            case 4:
                if (isNaN(chassis)) {
                    chassisField.setCustomValidity("Invalid: Chassis should be 4 digits" + chassis + ".");
                } else {
                    //chassisField.setCustomValidity("Valid: 4 digit Chassis " + chassis + ".");
                    chassisField.setCustomValidity("");
                }
                break;
            case 5:
                serial = chassis.substring(0, 4);
                suffix = chassis.substring(4).toUpperCase();

                if (isNaN(serial)) {
                    chassisField.setCustomValidity("Invalid Chassis: " + chassis + " Serial " + serial + " Suffix " + suffix + ".");
                } else {
                    // Now check the suffix
                    if (valid_suffix.indexOf(suffix) == -1) {
                        chassisField.setCustomValidity("Invalid Suffix:  Chassis " + chassis + " Serial " + serial + " Suffix " + suffix + ".");
                    } else {
                        //chassisField.setCustomValidity("Valid Chassis and Suffix: " + chassis + " Serial " + serial + " Suffix " + suffix + ".");
                        chassisField.setCustomValidity("");
                    }
                }
                break;

            default:
                chassisField.setCustomValidity("Invalid: Chassis should be 4 digits or 4 digits and 1 character.  You entered " + len + " digits/characters. " + chassis + ".");

                break;
        }
    }
}

function checkYear() {

    var yearField = document.getElementById("inputYear");
    var year = document.newCar.year.value;

    if (year == 0) {
        yearField.setCustomValidity("Please select a year");
        return;
    }
    // No custom constraint violation
    yearField.setCustomValidity("");

}



