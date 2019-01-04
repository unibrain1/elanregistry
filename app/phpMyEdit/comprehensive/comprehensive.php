<style type="text/css">
table { border: #004d9c 1px solid; border-collapse: collapse; border-spacing: 0px; width: 100%; }
th    { border: #004d9c 1px solid; padding: 4px; background: #add8e6; }
td    { border: #004d9c 1px solid; padding: 3px; }
hr    { border: 0px solid; padding: 0px; margin: 0px; border-top-width: 1px; height: 1px; }
</style><?php

ini_set('include_path', '../:'.ini_get('include_path'));

/* Modify this array to get only particular subset from testing fields. */
$used_cols = array(
		'tinyint'   => 0,
		'smallint'  => 0,
		'mediumint' => 0,
		'int'       => 0,
		'bigint'    => 0,
		'float'     => 0,
		'decimal'   => 0,
		'datetime'  => 0,
		'timestamp' => 0,
		'char'      => 0,
		'text'      => 0,
		'enum'      => 1
		);

$opts['cgi']['overwrite']['PME_sys_fl']=1;
$opts['cgi']['append']['PME_sys_qf0']=-1;

/*
 * IMPORTANT NOTE: This generated file contains only a subset of huge amount
 * of options that can be used with phpMyEdit. To get information about all
 * features offered by phpMyEdit, check official documentation. It is available
 * online and also for download on phpMyEdit project management page:
 *
 * http://www.platon.sk/projects/main_page.php?project_id=5
 */

// MySQL host name, user name, password, database, and table
$opts['hn'] = 'localhost';
$opts['un'] = 'test';
$opts['pw'] = 'test';
$opts['db'] = 'test';
$opts['tb'] = 'comprehensive';

// Name of field which is the unique key
$opts['key'] = 'xtinyint';

// Type of key field (int/real/string/date etc.)
$opts['key_type'] = 'int';

// Sorting field(s)
$opts['sort_field'] = array('xtinyint');

// Number of records to display on the screen
// Value of -1 lists all records in a table
$opts['inc'] = 15;

// Options you wish to give the users
// A - add,  C - change, P - copy, V - view, D - delete,
// F - filter, I - initial sort suppressed
$opts['options'] = 'ACPVDF';

// Number of lines to display on multiple selection filters
$opts['multiple'] = '4';

// Navigation style: B - buttons (default), T - text links, G - graphic links
// Buttons position: U - up, D - down (default)
$opts['navigation'] = 'DB';

// Display special page elements
$opts['display'] = array(
		'query' => true,
		'sort'  => true,
		'time'  => true
		);

/* Get the user's default language and use it if possible or you can
   specify particular one you want to use. Refer to official documentation
   for list of available languages. */
$opts['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

/* Table-level filter capability. If set, it is included in the WHERE clause
   of any generated SELECT statement in SQL query. This gives you ability to
   work only with subset of data from table.

   $opts['filters'] = "column1 like '%11%' AND column2<17";
   $opts['filters'] = "section_id = 9";
   $opts['filters'] = "PMEtable0.sessions_count > 200";
 */

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
   ['required'] true or false; if generate javascript to prevent null entries
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

$opts['fdd']['xtinyint'] = array(
		'name'     => 'Xtinyint',
		'select'   => 'T',
		'maxlen'   => 4,
		'default'  => '0',
		'sort'     => true
		);
if ($used_cols['tinyint']) {
	$opts['fdd']['xtinyint_u'] = array(
			'name'     => 'Xtinyint u',
			'select'   => 'T',
			'maxlen'   => 3,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xtinyint_z'] = array(
			'name'     => 'Xtinyint z',
			'select'   => 'T',
			'maxlen'   => 3,
			'default'  => '000',
			'sort'     => true
			);
	$opts['fdd']['xtinyint_u_z'] = array(
			'name'     => 'Xtinyint u z',
			'select'   => 'T',
			'maxlen'   => 3,
			'default'  => '000',
			'sort'     => true
			);
}
if ($used_cols['smallint']) {
	$opts['fdd']['xsmallint'] = array(
			'name'     => 'Xsmallint',
			'select'   => 'T',
			'maxlen'   => 6,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xsmallint_u'] = array(
			'name'     => 'Xsmallint u',
			'select'   => 'T',
			'maxlen'   => 5,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xsmallint_z'] = array(
			'name'     => 'Xsmallint z',
			'select'   => 'T',
			'maxlen'   => 5,
			'default'  => '00000',
			'sort'     => true
			);
	$opts['fdd']['xsmallint_u_z'] = array(
			'name'     => 'Xsmallint u z',
			'select'   => 'T',
			'maxlen'   => 5,
			'default'  => '00000',
			'sort'     => true
			);
}
if ($used_cols['mediumint']) {
	$opts['fdd']['xmediumint'] = array(
			'name'     => 'Xmediumint',
			'select'   => 'T',
			'maxlen'   => 9,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xmediumint_u'] = array(
			'name'     => 'Xmediumint u',
			'select'   => 'T',
			'maxlen'   => 8,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xmediumint_z'] = array(
			'name'     => 'Xmediumint z',
			'select'   => 'T',
			'maxlen'   => 8,
			'default'  => '00000000',
			'sort'     => true
			);
	$opts['fdd']['xmediumint_u_z'] = array(
			'name'     => 'Xmediumint u z',
			'select'   => 'T',
			'maxlen'   => 8,
			'default'  => '00000000',
			'sort'     => true
			);
}
if ($used_cols['int']) {
	$opts['fdd']['xint'] = array(
			'name'     => 'Xint',
			'select'   => 'T',
			'maxlen'   => 11,
			'default'  => '0',
			'required' => true,
			'sort'     => true
			);
	$opts['fdd']['xint_u'] = array(
			'name'     => 'Xint u',
			'select'   => 'T',
			'maxlen'   => 10,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xint_z'] = array(
			'name'     => 'Xint z',
			'select'   => 'T',
			'maxlen'   => 10,
			'default'  => '0000000000',
			'sort'     => true
			);
	$opts['fdd']['xint_u_z'] = array(
			'name'     => 'Xint u z',
			'select'   => 'T',
			'maxlen'   => 10,
			'default'  => '0000000000',
			'sort'     => true
			);
	$opts['fdd']['xinteger'] = array(
			'name'     => 'Xinteger',
			'select'   => 'T',
			'maxlen'   => 11,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xinteger_u'] = array(
			'name'     => 'Xinteger u',
			'select'   => 'T',
			'maxlen'   => 10,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xinteger_z'] = array(
			'name'     => 'Xinteger z',
			'select'   => 'T',
			'maxlen'   => 10,
			'default'  => '0000000000',
			'sort'     => true
			);
	$opts['fdd']['xinteger_u_z'] = array(
			'name'     => 'Xinteger u z',
			'select'   => 'T',
			'maxlen'   => 10,
			'default'  => '0000000000',
			'sort'     => true
			);
}
if ($used_cols['bigint']) {
	$opts['fdd']['xbigint'] = array(
			'name'     => 'Xbigint',
			'select'   => 'T',
			'maxlen'   => 20,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xbigint_u'] = array(
			'name'     => 'Xbigint u',
			'select'   => 'T',
			'maxlen'   => 20,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xbigint_z'] = array(
			'name'     => 'Xbigint z',
			'select'   => 'T',
			'maxlen'   => 20,
			'default'  => '00000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xbigint_u_z'] = array(
			'name'     => 'Xbigint u z',
			'select'   => 'T',
			'maxlen'   => 20,
			'default'  => '00000000000000000000',
			'sort'     => true
			);
}
if ($used_cols['float']) {
	$opts['fdd']['xfloat1'] = array(
			'name'     => 'Xfloat1',
			'select'   => 'T',
			'maxlen'   => 12,
			'default'  => '0',
			'sort'     => true
			);
	$opts['fdd']['xfloat1_z'] = array(
			'name'     => 'Xfloat1 z',
			'select'   => 'T',
			'maxlen'   => 12,
			'default'  => '000000000000',
			'sort'     => true
			);
	$opts['fdd']['xfloat2'] = array(
			'name'     => 'Xfloat2',
			'select'   => 'T',
			'maxlen'   => 25,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xfloat2_z'] = array(
			'name'     => 'Xfloat2 z',
			'select'   => 'T',
			'maxlen'   => 25,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xdouble2'] = array(
			'name'     => 'Xdouble2',
			'select'   => 'T',
			'maxlen'   => 25,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xdouble2_z'] = array(
			'name'     => 'Xdouble2 z',
			'select'   => 'T',
			'maxlen'   => 25,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xreal2'] = array(
			'name'     => 'Xreal2',
			'select'   => 'T',
			'maxlen'   => 25,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xreal2_z'] = array(
			'name'     => 'Xreal2 z',
			'select'   => 'T',
			'maxlen'   => 25,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
}
if ($used_cols['decimal']) {
	$opts['fdd']['xdecimal2'] = array(
			'name'     => 'Xdecimal2',
			'select'   => 'T',
			'maxlen'   => 27,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xdecimal2_z'] = array(
			'name'     => 'Xdecimal2 z',
			'select'   => 'T',
			'maxlen'   => 26,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xnumeric2'] = array(
			'name'     => 'Xnumeric2',
			'select'   => 'T',
			'maxlen'   => 27,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
	$opts['fdd']['xnumeric2_z'] = array(
			'name'     => 'Xnumeric2 z',
			'select'   => 'T',
			'maxlen'   => 26,
			'default'  => '0.000000000000000000000000',
			'sort'     => true
			);
}
if ($used_cols['datetime']) {
	$opts['fdd']['xdate'] = array(
			'name'     => 'Xdate',
			'select'   => 'T',
			'maxlen'   => 10,
			'sort'     => true
			);
	$opts['fdd']['xdatetime'] = array(
			'name'     => 'Xdatetime',
			'select'   => 'T',
			'maxlen'   => 19,
			'sort'     => true
			);
	$opts['fdd']['xtime'] = array(
			'name'     => 'Xtime',
			'select'   => 'T',
			'maxlen'   => 8,
			'sort'     => true
			);
	$opts['fdd']['xyear'] = array(
			'name'     => 'Xyear',
			'select'   => 'T',
			'maxlen'   => 4,
			'sort'     => true
			);
}
if ($used_cols['timestamp']) {
	$opts['fdd']['xtimestamp2'] = array(
			'name'     => 'Xtimestamp2',
			'select'   => 'T',
			'options'  => 'AVCPDR', // updated automatically (MySQL feature)
			'maxlen'   => 2,
			'required' => true,
			'sort'     => true
			);
	$opts['fdd']['xtimestamp4'] = array(
			'name'     => 'Xtimestamp4',
			'select'   => 'T',
			'options'  => 'AVCPD',
			'maxlen'   => 4,
			'required' => true,
			'sort'     => true
			);
	$opts['fdd']['xtimestamp6'] = array(
			'name'     => 'Xtimestamp6',
			'select'   => 'T',
			'options'  => 'AVCPD',
			'maxlen'   => 6,
			'required' => true,
			'sort'     => true
			);
	$opts['fdd']['xtimestamp8'] = array(
			'name'     => 'Xtimestamp8',
			'select'   => 'T',
			'options'  => 'AVCPD',
			'maxlen'   => 8,
			'required' => true,
			'sort'     => true
			);
	$opts['fdd']['xtimestamp10'] = array(
			'name'     => 'Xtimestamp10',
			'select'   => 'T',
			'options'  => 'AVCPD',
			'maxlen'   => 10,
			'required' => true,
			'sort'     => true
			);
	$opts['fdd']['xtimestamp12'] = array(
			'name'     => 'Xtimestamp12',
			'select'   => 'T',
			'options'  => 'AVCPD',
			'maxlen'   => 12,
			'required' => true,
			'sort'     => true
			);
	$opts['fdd']['xtimestamp14'] = array(
			'name'     => 'Xtimestamp14',
			'select'   => 'T',
			'options'  => 'AVCPD',
			'maxlen'   => 14,
			'required' => true,
			'sort'     => true
			);
}
if ($used_cols['char']) {
	$opts['fdd']['xchar1'] = array(
			'name'     => 'Xchar1',
			'select'   => 'T',
			'maxlen'   => 1,
			'sort'     => true
			);
	$opts['fdd']['xchar255'] = array(
			'name'     => 'Xchar255',
			'select'   => 'T',
			'maxlen'   => 255,
			'sort'     => true
			);
	$opts['fdd']['xbit'] = array(
			'name'     => 'Xbit',
			'select'   => 'T',
			'maxlen'   => 1,
			'sort'     => true
			);
	$opts['fdd']['xbool'] = array(
			'name'     => 'Xbool',
			'select'   => 'T',
			'maxlen'   => 1,
			'sort'     => true
			);
	$opts['fdd']['xchar'] = array(
			'name'     => 'Xchar',
			'select'   => 'T',
			'maxlen'   => 1,
			'sort'     => true
			);
	$opts['fdd']['xvarchar1'] = array(
			'name'     => 'Xvarchar1',
			'select'   => 'T',
			'maxlen'   => 1,
			'sort'     => true
			);
	$opts['fdd']['xvarchar255'] = array(
			'name'     => 'Xvarchar255',
			'select'   => 'T',
			'maxlen'   => 255,
			'sort'     => true
			);
}
if ($used_cols['text']) {
	$opts['fdd']['xtinytext'] = array(
			'name'     => 'Xtinytext',
			'select'   => 'T',
			'maxlen'   => 255,
			'textarea' => array(
				'rows' => 5,
				'cols' => 50),
			'sort'     => true
			);
	$opts['fdd']['xblob'] = array(
			'name'     => 'Xblob',
			'select'   => 'T',
			'maxlen'   => 65535,
			'textarea' => array(
				'rows' => 5,
				'cols' => 50),
			'sort'     => true
			);
	$opts['fdd']['xtext'] = array(
			'name'     => 'Xtext',
			'select'   => 'T',
			'maxlen'   => 65535,
			'textarea' => array(
				'rows' => 5,
				'cols' => 50),
			'sort'     => true
			);
	$opts['fdd']['xmediumblob'] = array(
			'name'     => 'Xmediumblob',
			'select'   => 'T',
			'maxlen'   => 16777215,
			'textarea' => array(
				'rows' => 5,
				'cols' => 50),
			'sort'     => true
			);
	$opts['fdd']['xmediumtext'] = array(
			'name'     => 'Xmediumtext',
			'select'   => 'T',
			'maxlen'   => 16777215,
			'textarea' => array(
				'rows' => 5,
				'cols' => 50),
			'sort'     => true
			);
	$opts['fdd']['xlongblob'] = array(
			'name'     => 'Xlongblob',
			'select'   => 'T',
			'maxlen'   => 16777215,
			'textarea' => array(
				'rows' => 5,
				'cols' => 50),
			'sort'     => true
			);
	$opts['fdd']['xlongtext'] = array(
			'name'     => 'Xlongtext',
			'select'   => 'T',
			'maxlen'   => 16777215,
			'textarea' => array(
				'rows' => 5,
				'cols' => 50),
			'sort'     => true
			);
}
if ($used_cols['enum']) {
	$opts['fdd']['xenum'] = array(
			'name'     => 'Xenum',
			'select'   => 'T',
			'maxlen'   => 5,
			'values'   => array('enum1','enum2','enum3'),
			'sort'     => true
			);
	$opts['fdd']['xset'] = array(
			'name'     => 'Xset',
			'select'   => 'M',
			'maxlen'   => 14,
			'values'   => array('set0','set1','set2'),
			'sort'     => true
			);
}

// Now important call to phpMyEdit
require_once 'phpMyEdit.class.php';
new phpMyEdit($opts);

?>
