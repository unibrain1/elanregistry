<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
?>
<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/header.php';
require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])){die();} ?>

<?php
//PHP Goes Here!
?>
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="row">
			<div class="col-sm-12">
			</br></br>

<?php

include("include/constants.php");
// include("include/car_database_config.php");

	// Options you wish to give the users
	// A - add,  C - change, P - copy, V - view, D - delete,
	// F - filter, I - initial sort suppressed
	$opts['options'] = 'VF'; /* Guest */
	
	/* Table-level filter capability. If set, it is included in the WHERE clause
   	of any generated SELECT statement in SQL query. This gives you ability to
   	work only with subset of data from table.
	
	$opts['filters'] = "column1 like '%11%' AND column2<17";
	$opts['filters'] = "section_id = 9";
	$opts['filters'] = "PMEtable0.sessions_count > 200";
	*/

	$DISPLAY_USERINFO = true; // TODO Need to figure out how to Join or dislplay user with the car information

// MySQL host name, user name, password, database, and table


$opts['hn'] = $config[mysql][host];
$opts['un'] = $config[mysql][username];
$opts['pw'] = $config[mysql][password];
$opts['db'] = $config[mysql][db];

$opts['tb'] = 'users_carsView';

$opts['ssl'] = 0; // TODO No SSL - Need to change when I move to HTTPS 

// Set the base URL for phpMyAdmin images - 
$opts['url']['images'] = $us_url_root.'app/phpMyEdit/images/';

// Log changes
$opts['logtable'] = 'changelog';
 
// And email them

 $opts['notify']['from'] = EMAIL_FROM_ADDR;
 $opts['notify']['prefix'] = 'Registry: ';
 $opts['notify']['update'] = EMAIL_FROM_ADDR;
 $opts['notify']['insert'] = EMAIL_FROM_ADDR;
 $opts['notify']['delete'] = EMAIL_FROM_ADDR;

// Name of field which is the unique key
$opts['key'] = 'id';

// Type of key field (int/real/string/date etc.)
$opts['key_type'] = 'int';

// Sorting field(s)
$opts['sort_field'] = array('year', 'type', 'chassis' );

// Triggers
// $opts['triggers']['insert']['before'] = 'categories.TIB.inc';
// $opts['triggers']['insert']['after']  = 'post_process_insert.inc';
// $opts['triggers']['update']['before'] = 'categories.TUB.inc';
$opts['triggers']['update']['after']  = 'trigger/date_modified.inc';
// $opts['triggers']['delete']['before'] = 'categories.TDB.inc';
// $opts['triggers']['delete']['after']  = 'categories.TDA.inc';


// Number of records to display on the screen
// Value of -1 lists all records in a table
$opts['inc'] = 25;

// Number of lines to display on multiple selection filters
$opts['multiple'] = '5';

// Navigation style: B - buttons (default), T - text links, G - graphic links
// Buttons position: U - up, D - down (default)
$opts['navigation'] = 'GUD';

// A - add,  C - change, P - copy, V - view, D - delete,
// F - filter, I - initial sort suppressed
/* For the LIST view */
if($DISPLAY_USERINFO){
	$opts['buttons']['L']['up'] = array(
		'-add', '<<', '<', 'Page', 'goto_text', 'of', 'total_pages', '>', '>>');
	$opts['buttons']['V']['up'] = array(
		'cancel');
	$opts['buttons']['V']['down'] = array( '');
}else {
	$opts['buttons']['L']['up'] = array(
		'-add', '-<<', '-<', '->', '->>');
	$opts['buttons']['V']['up'] = array(
		'change', 'cancel');
	$opts['buttons']['V']['down'] = array( '');
	$opts['buttons']['A']['up'] = array(
		'save', 'cancel');
	$opts['buttons']['A']['down'] = array( '');
	$opts['buttons']['C']['up'] = array(
		'save', 'cancel');
	$opts['buttons']['C']['down'] = array( '');
}
$opts['buttons']['L']['down'] = $opts['buttons']['L']['up'];
$opts['buttons']['F']['up'] = $opts['buttons']['L']['up'];
$opts['buttons']['F']['down'] = $opts['buttons']['L']['up'];

// $opts['buttons']['V']['down'] = $opts['buttons']['V']['up'];

// Display special page elements
$opts['display'] = array(
	'form'  => true,
	'query' => false,
	'sort'  => true,
	'time'  => false,
	'tabs'  => true
);

// Set default prefixes for variables
$opts['js']['prefix']               = 'PME_js_';
$opts['dhtml']['prefix']            = 'PME_dhtml_';
$opts['cgi']['prefix']['operation'] = 'PME_op_';
$opts['cgi']['prefix']['sys']       = 'PME_sys_';
$opts['cgi']['prefix']['data']      = 'PME_data_';

/* Get the user's default language and use it if possible or you can
   specify particular one you want to use. Refer to official documentation
   for list of available languages. */
// $opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] . '-UTF8';
$opts['language'] = 'EN-US-CUSTOM';  // Custom version of EN-US to change button names

/* Field definitions
   
Fields will be displayed left to right on the screen in the order in which they
appear in generated list. Here are some most used field options documented.

['name'] is the title used for column headings, etc.;
['maxlen'] maximum length to display add/edit/search input boxes
['trimlen'] maximum length of string content to display in row listing
['width'] is an optional display width specification for the column
          e.g.  ['width'] = '100px';
['mask'] a string that is used by sprintf() to format field output
['sort'] true or false; means the users may sort the display on this column
['strip_tags'] true or false; whether to strip tags from content
['nowrap'] true or false; whether this field should get a NOWRAP
['select'] T - text, N - numeric, D - drop-down, M - multiple selection
['options'] optional parameter to control whether a field is displayed
  L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
            Another flags are:
            R - indicates that a field is read only
            W - indicates that a field is a password field
            H - indicates that a field is to be hidden and marked as hidden
['URL'] is used to make a field 'clickable' in the display
        e.g.: 'mailto:$value', 'http://$value' or '$page?stuff';
['URLtarget']  HTML target link specification (for example: _blank)
['textarea']['rows'] and/or ['textarea']['cols']
  specifies a textarea is to be used to give multi-line input
  e.g. ['textarea']['rows'] = 5; ['textarea']['cols'] = 10
['values'] restricts user input to the specified constants,
           e.g. ['values'] = array('A','B','C') or ['values'] = range(1,99)
['values']['table'] and ['values']['column'] restricts user input
  to the values found in the specified column of another table
['values']['description'] = 'desc_column'
  The optional ['values']['description'] field allows the value(s) displayed
  to the user to be different to those in the ['values']['column'] field.
  This is useful for giving more meaning to column values. Multiple
  descriptions fields are also possible. Check documentation for this.
*/


/* ********************************************** 
 * CAR information
 * ********************************************** */
$opts['fdd']['id'] = array(
  'name'     => 'ID',
  'tab'     => 'Car Information',
  'select'   => 'T',
  'options'  => 'RV', // auto increment
  'maxlen'   => 10,
  'default'  => '0',
  'sort'     => true
);
$opts['fdd']['year'] = array(
  'name'     => 'Year',
  'select'   => 'D',
  'values'   => array( '1963', '1964', '1965', '1966', '1967', '1968', '1969', '1970', '1971', '1972', '1973','1974'),
  'maxlen'   => 4,
  'required' => true,
  'sort'     => true
);
$opts['fdd']['type'] = array(
  'name'     => 'Type',
  'select'   => 'D',
  'values'   => array('26R','26','36','45','50'),
  'maxlen'   => 2,
  'required' => true,
  'sort'     => true
);
$opts['fdd']['chassis'] = array(
  'name'     => 'Chassis',
  'help|A'     => 'Do not include the type number (No 26 from 26/1234)',
  'select'   => 'T',
  'maxlen'   => 10,
  'required' => true,
  'sort'     => true
);
$opts['fdd']['series'] = array(
  'name'     => 'Series',
  'select'   => 'D',
  'values'   => array('S1','S2','S3','S3 SE','S4','S4 SE','Sprint','+2','+2S','+2S/130','+2S/130/5'),
  'maxlen'   => 12,
  'required' => true,
  'sort'     => true
);
$opts['fdd']['variant'] = array(
  'name'     => 'Variant',
  'select'   => 'D',
  'values'   => array('Roadster','DHC','FHC-preairflow','FHC-airflow','Federal','Race'),
  'maxlen'   => 15,
  'required' => true,
  'sort'     => true
);
$opts['fdd']['color'] = array(
  'name'     => 'Color',
  'select'   => 'G',
  'options'  => 'LFACPDV', /* L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view */
  'maxlen'   => 25,
  'required' => false,
  'sort'     => true
);
$opts['fdd']['engine'] = array(
  'name'     => 'Engine Number',
  'select'   => 'T',
  'help|A'     => 'Do not use spaces',
  'options'  => 'FACPDV', /* L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view */
  'maxlen'   => 15,
  'required' => false,
  'sort'     => true
);
$opts['fdd']['purchasedate'] = array(
  'name'     => 'Purchase Date',
  'select'   => 'T',
  'maxlen'   => 10,
  'options'  => 'ACPDV', /* L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view */
  'default'  => '0000-00-00',
  'required' => false,
  'sort'     => true
);
$opts['fdd']['solddate'] = array(
  'name'     => 'Sold Date',
  'select'   => 'T',
  'options'  => 'ACPDV', /* L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view */
  'maxlen'   => 10,
  'default'  => '0000-00-00',
  'required' => false,
  'sort'     => true
);
$opts['fdd']['comments'] = array(
  'name'     => 'Comments',
  'strip_tags'   => 'true',
  'escape'	=> false,
  'select'   => 'T',
  'options'  => 'ACPDV', /* L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view */
  'maxlen'   => 65535,
  'nowrap'  => 'false',
  'textarea' => array(
    'rows' => 5,
    'cols' => 50),
  'required' => false,
  'nowrap' => false,
  'sort'     => true

);

$opts['fdd']['image'] = array(
  'name'     => 'Image',
  'options'  => 'LVR', 
  'select'   => 'G',
  'escape'	=> false,
  'sql|FL'		=> "IF(image = '', NULL, CONCAT(' <a href=\"userimages/', image, '\" rel=\"lightbox\"><img src=\"userimages/thumbs/', image, '\"></a> '))" ,
  'sql|V'		=> "IF(image = '', NULL, CONCAT('<img src=\"userimages/', image, '\">'))" ,
  'sort'     => true
  );



if($DISPLAY_USERINFO){
	/* ********************************************** 
 	* Owner Information
 	* ********************************************** */
	$opts['fdd']['fname'] = array(
  	'name' => 'First Name',
  	'select'   => 'T',
	'tab'     => 'Owner Information',
  	'options' => 'LVFR',
  	'sort'     => true
	);

	$opts['fdd']['lname'] = array(
  	'name'     => 'Last Name',
  	'select'   => 'T',
  	'options' => 'LVFR',
  	'sort'     => true
	);

	$opts['fdd']['city'] = array(
  	'name'     => 'City',
  	'select'   => 'T',
  	'options' => 'LFVR',
  	'sort'     => true
	);
	$opts['fdd']['state'] = array(
  	'name'     => 'State/Province',
  	'select'   => 'T',
  	'options' => 'LFVR',
  	'sort'     => true
	);
	$opts['fdd']['country'] = array(
  	'name'     => 'Country',
  	'select'   => 'T',
  	'options' => 'LFVR',
  	'sort'     => true
	);

/* TODO Need to find a better way to handle NULL Url 
	$opts['fdd']['website'] = array(
  	'name'     => 'WebSite',
  	'select'   => 'G',
   	'URL' => '$value',
   	'URLtarget' => ' _blank',
   	'URLdisp' => 'link',
  	'options' => 'LFVR',
  	'sort'     => true
	);
*/
}

/* ********************************************** 
 * More DB Related items
 * ********************************************** */
$opts['fdd']['ctime'] = array(
  'name'     => 'Date Added',
  'tab'     => 'Change Log',
  'datemask' => 'Y-m-d',
  // 'select'   => 'T',
  'options'  => 'ALVR', /* L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view */
  'maxlen'   => 19,
  'default'  => date("Y-m-d H:i:s", strtotime("now")), 
  'required' => true,
  'sort'     => true
);
$opts['fdd']['mtime'] = array(
  'name'     => 'Modified Date',
  'datemask' => 'Y-m-d',
  'options'  => 'CVR', /* L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view */
  // 'select'   => 'T',
  'maxlen'   => 19,
  'default'  => '',
  'required' => true,
  'sort'     => true
);

// Now important call to phpMyEdit
require_once 'phpMyEdit/phpMyEdit.class.php';
new phpMyEdit($opts);

?>








					<!-- End of main content section -->
			</div> <!-- /.col -->
		</div> <!-- /.row -->
	</div> <!-- /.container -->
</div> <!-- /.wrapper -->


	<!-- footers -->
<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls ?>

<!-- Place any per-page javascript here -->

<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html ?>
