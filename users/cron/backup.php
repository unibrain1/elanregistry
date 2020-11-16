<?php
ini_set('max_execution_time', 1356);
ini_set('memory_limit', '1024M');
require_once '../init.php';
include $abs_us_root . $us_url_root . 'users/lang/en-US.php';

$db = DB::getInstance();
$ip = ipCheck();
logger(1, "BackupRequest", "Cron backup request from $ip.");
$settingsQ = $db->query("Select * FROM settings");
$settings = $settingsQ->first();
if ($settings->cron_ip != '') {
	if ($ip != $settings->cron_ip && $ip != '127.0.0.1') {
		logger(1, "BackupRequest", "Cron backup request DENIED from $ip.");
		die;
	}
}
$errors = [];
$successes = [];
$command = $settings->backup_source;

$backup_age = 60 * 60 * 24 * 30; // 30 days = Secs * Mins * Hours * Days TODO - Get days from configuration

# The name of the script being run
$self = 'backup.php';

$from = Input::get('from');
if ($from == '') {
	$from = 'users/cron_manager.php';
}
$checkQuery = $db->query("SELECT id,name FROM crons WHERE file = ? AND active = 1", [$self]);
if ($checkQuery->count() == 1) {
	$successes[] = lang('AB_COMMAND') . $command;

	//Create backup destination folder: $settings->backup_dest
	//$backup_dest = $settings->backup_dest;
	$backup_dest = "@" . $settings->backup_dest; //::from us v4.2.9a
	$backupTable = $settings->backup_table;
	if ($command != "db_table") {
		$backupSource = $command;
	} elseif ($command == "db_table") {
		$backupSource = $command . '_' . $backupTable;
	}
	$destPath = $abs_us_root . $us_url_root . $backup_dest;
	if (!file_exists($destPath)) {
		if (mkdir($destPath)) {
			$destPathSuccess = true;
			$successes[] = lang('AB_PATHCREATE');
		} else {
			$destPathSuccess = false;
			$errors[] = lang('AB_PATHERROR');
		}
	} else {
		$successes[] = lang('AB_PATHEXISTED');
	}
	// Generate backup path
	$backupDateTimeString = date("Y-m-d\TH-i-s");
	$backupPath = $abs_us_root . $us_url_root . $backup_dest . 'backup_' . $backupSource . '_' . $backupDateTimeString . '/';
	$backupLogFilename = $abs_us_root . $us_url_root . $backup_dest . 'backup_' . $backupSource . '_' . $backupDateTimeString . '.log';
	$successes[] = lang('AB_BACKUPFILE') . $backupLogFilename;

	if (!file_exists($backupPath)) {
		if (mkdir($backupPath)) {
			$backupPathSuccess = true;
			$successes[] = lang('AB_PATHCREATE') . $backupPath;
		} else {
			$backupPathSuccess = false;
			$errors[] = lang('AB_PATHERROR') . $backupPath;
		}
	} else {
		$successes[] = lang('AB_PATHEXISTED') . $backupPath;
	}

	if ($backupPathSuccess) {
		// Since the backup path is just created with a timestamp,
		// no need to check if these subfolders exist or if they are writable
		mkdir($backupPath . 'files');
		mkdir($backupPath . 'sql');
		$backupItems = [];

		switch ($command) {
			case 'everything':
				$backupItems[] = $abs_us_root . $us_url_root;
				$backupItems[] = $abs_us_root . $us_url_root . 'users';
				$backupItems[] = $abs_us_root . $us_url_root . 'usersc';

				if (backupObjects($backupItems, $backupPath . 'files/')) {
					$successes[] = lang('AB_BACKUPSUCCESS');
				} else {
					$errors[] = lang('AB_BACKUPFAIL');
				}
				backupUsTables($backupPath);
				$targetZipFile = backupZip($backupPath, true);

				break;
			case 'db_us_files':
				$backupItems[] = $abs_us_root . $us_url_root . 'users';
				$backupItems[] = $abs_us_root . $us_url_root . 'usersc';
				if (backupObjects($backupItems, $backupPath . 'files/')) {
					$successes[] = lang('AB_BACKUPSUCCESS');
				} else {
					$errors[] = lang('AB_BACKUPFAIL');
				}
				backupUsTables($backupPath);
				break;
			case 'db_only':
				backupUsTables($backupPath);
				break;
			case 'us_files':
				$backupItems[] = $abs_us_root . $us_url_root . 'users';
				$backupItems[] = $abs_us_root . $us_url_root . 'usersc';
				if (backupObjects($backupItems, $backupPath . 'files/')) {
					$successes[] = lang('AB_BACKUPSUCCESS');
				} else {
					$errors[] = lang('AB_BACKUPFAIL');
				}
				break;
			case 'db_table':
				backupUsTable($backupPath);
				break;
			default:
				// Unknown state
				break;
		}

		// Now create the zip file
		$targetZipFile = backupZip($backupPath, true);
		if ($targetZipFile) {
			$successes[] = lang('AB_DB_FILES_ZIP');
			$backupZipHash = hash_file('sha1', $targetZipFile);
			$backupZipHashFilename = substr($targetZipFile, 0, strlen($targetZipFile) - 4) . '_SHA1_' . $backupZipHash . '.zip';

			if (rename($targetZipFile, $backupZipHashFilename)) {
				$successes[] = lang('AB_FILE_RENAMED') . $backupZipHashFilename;

				// Now that we have a succesful backup and created a backup file, delete old backups
				$successes[] = " -- looking for old files to remove in " . $destPath;
				$files = glob($destPath . "*");
				$now   = time();

				foreach ($files as $file) {
					$successes[] = " -- checking file " . $file;
					if (is_file($file)) {
						if ($now - filemtime($file) >= $backup_age) { // 3 days
							$successes[] = " -- REMOVING " . $file;
							unlink($file);
						}
					}
				}
			} else {
				$errors[] = lang('AB_NOT_RENAME');
			}
		} else {
			$errors[] = lang('AB_ERROR_CREATE');
		}
	}

	# Output some output
	if (!$errors == '') {
		foreach ($errors as $error) {
			file_put_contents($backupLogFilename, "Error: " . $error . "\r\n", FILE_APPEND);
			echo "Error: " . $error . "\r\n";
		}
	}

	if (!$successes == '') {
		foreach ($successes as $success) {
			file_put_contents($backupLogFilename, "Success: " . $success . "\r\n", FILE_APPEND);
			echo "Success: " . $success . "\r\n";
		}
	}

	if ($currentPage == $self) {
		$query = $db->query("SELECT id,name FROM crons WHERE file = ?", [$self]);
		if ($user->isLoggedIn()) {
			$user_id = $user->data()->id;
		} else {
			$user_id = 1;
		}
		$results = $query->first();
		$cronfields = array(
			'cron_id' => $results->id,
			'datetime' => date("Y-m-d H:i:s"),
			'user_id' => $user_id
		);
		$db->insert('crons_logs', $cronfields);
		Redirect::to('../../' . $from);
	}
} else {
	Redirect::to('../../' . $from . '?err=Cron is disabled, cannot be ran.');
}
