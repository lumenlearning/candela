<?php session_start(); ?>
<?php
	require_once( dirname(__FILE__) . '/lib/recover_forms.php');
?>
<?php
//echo "_SESSION<pre>"; print_r($_SESSION); echo "</pre>";
/*
if ( (isset($_SESSION['restore_form']['wordpress']['install-path'])) && (!empty($_SESSION['restore_form']['wordpress']['install-path']))
  && (isset($_SESSION['restore_form']['wordpress']['wp-config-db'])) && (!empty($_SESSION['restore_form']['wordpress']['wp-config-db'])) ) {

	if (file_exists(trailingslashit_snapshot($_SESSION['restore_form']['wordpress']['install-path']) ."wp-load.php")) {
		define( 'SHORTINIT', true );
		define( 'WP_USE_THEMES', false );

		// Load in WP core.
		require( trailingslashit_snapshot($_SESSION['restore_form']['wordpress']['install-path']) .'wp-load.php' );
	}
}

if (!isset($_SESSION['restore_form']['wordpress']['wp-config-db'])) {

	$wp_config_db = array();
	if (defined('DB_NAME'))
		$wp_config_db['DB_NAME'] = DB_NAME;
	if (defined('DB_NAME'))
		$wp_config_db['DB_USER'] = DB_USER;
	if (defined('DB_PASSWORD'))
		$wp_config_db['DB_PASSWORD'] = DB_PASSWORD;
	if (defined('DB_HOST'))
		$wp_config_db['DB_HOST'] = DB_HOST;
	if (!empty($table_prefix))
		$wp_config_db['DB_PREFIX'] = $table_prefix;

	if (!empty($wp_config_db))
		$_SESSION['restore_form']['wordpress']['wp-config-db'] = $wp_config_db;
}
*/
?><!DOCTYPE html>
<!--[if IE 8]>
<html xmlns="http://www.w3.org/1999/xhtml" class="ie8 wp-toolbar"  dir="ltr" lang="en-US">
<![endif]-->
<!--[if !(IE 8) ]><!-->
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
<!--<![endif]-->
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Snapshots Emergency Restore</title>
	<link rel="stylesheet" href="css/snapshots-recover-styles.css" type="text/css" media="all" />

	<script type="text/javascript">
	</script>
</head>
<body>
	<p>This is the Snapshot Emergency Restore page. If your site is broken and you need to restore your site from a Snapshot backup you are in the right place. First we need to gather some information about your site to start the restore process.</p>


<?php
	$restore_form = array();
	if (isset($_POST['restore'])) {
		$restore_form = $_POST['restore'];
	}

	if (isset($_GET['step']))
		$restore_form['step'] = intval($_GET['step']);
	else
		$restore_form['step'] = '1a';

	switch($restore_form['step']) {
		case '1':
			$form_errors = step_1b_validate_form($restore_form);
			if ( (!count($form_errors['form'])) && (!count($form_errors['message-error'])) )
				step_1_show_form($form_errors);
			else
				step_verify_stepb_show_form($form_errors);

			break;

		case '2':
			$form_errors = step_1_validate_form($restore_form);
			if ( (!count($form_errors['form'])) && (!count($form_errors['message-error'])) )
				step_2_show_form($form_errors);
			else
				step_1_show_form($form_errors);

			break;

		case '3':
			$form_errors = step_2_validate_form($restore_form);
			if ( (!count($form_errors['form'])) && (!count($form_errors['message-error'])) )
				step_3_show_form($form_errors);
			else
				step_2_show_form($form_errors);

			break;

		case '4':
			$form_errors = step_3_validate_form($restore_form);
			if ( (!count($form_errors['form'])) && (!count($form_errors['message-error'])) )
				step_4_show_form($form_errors);
			else
				step_3_show_form($form_errors);

			break;

		case 'verify-b':
			step_verify_stepb_show_form();
			break;

		//case 'verify-a':
		default:
			unset($_SESSION['restore_form']);
			//unset($_SESSION['snapshot']);
			step_verify_stepa_show_form();
			break;

	}
?>
</body>
</html>
<?php

/******************************************************************************/
/* Generic functions                                                          */
/******************************************************************************/

function db_connection_test($db_name, $db_user, $db_password, $db_host) {
	$errors = array();

	$db_link = mysql_connect($db_host, $db_user, $db_password);
	if (!$db_link) {
		$errors[] = "Could not connect to MySQL: ". mysql_error();
	} else {
		$db_selected = mysql_select_db($db_name, $db_link);
		if (!$db_selected) {
			$errors[] = "Can't select database [". $db_name ."]: ". mysql_error();
		}
	}
	mysql_close($db_link);

	return $errors;
}

function remote_url_to_local_file($remote_url, $local_file) {

	if (!file_exists(dirname($local_file)))
		mkdir(dirname($local_file), 0777, true);

	$local_fp = fopen($local_file, 'w+b');
	if (!$local_fp) {
		echo "Unable to open local file [". $local_file ."] for writing. Check parent folder permissions and reload the page.";
		die();
	}

	$remote_fp = curl_init($remote_url);
	curl_setopt($remote_fp, CURLOPT_FILE, $local_fp);
	//curl_setopt($remote_fp, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($remote_fp, CURLOPT_BINARYTRANSFER,1);
	//curl_setopt($remote_fp, CURLOPT_RETURNTRANSFER, true);
	$data = curl_exec($remote_fp);
	//echo "data[". $data ."]<br >";
	curl_close($remote_fp);
	fclose($local_fp);

}

function utility_recursive_rmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);

		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir")
					utility_recursive_rmdir($dir."/".$object);
				else unlink($dir."/".$object);
			}
		}
     	reset($objects);
		rmdir($dir);
	}
}

function unzip_archive($local_archive, $restore_path_base) {

	// First we clear the directory
	utility_recursive_rmdir($restore_path_base);

	//echo "local_archive=[". $local_archive ."]<br />";
	//echo "restore_path_base=[". $restore_path_base ."]<br />";
	//die();

	if (!file_exists($local_archive)) {
		echo "Archive file [". $local_archive ."] does not exist<br />";
		die();
	}

	$zip_filesize = filesize($local_archive);
	//echo "zip_filesize=[". $zip_filesize ."]<br />";
	//die();
	@ini_set('memory_limit', $zip_filesize*3);
	@set_time_limit(0);

	$zip_fp = zip_open($local_archive);
	if (!is_resource($zip_fp)) {
		echo "Unable to open local archive [". $local_archive ."] ". zipFileErrMsg($zip_fp) ."<br />";
		die();
	}

	while ($zip_entry = zip_read($zip_fp)) {
		$zip_entry_filename = zip_entry_name($zip_entry);
		//echo "zip_entry_filename=[". $zip_entry_filename ."]<br />";

		$local_filename = $restore_path_base . $zip_entry_filename;

		// Is entry a directory?
		if (substr($local_filename, -1) == "/") {
			if (!file_exists($local_filename)) {
				mkdir($local_filename, 0777, true);
			}
			continue;
		}

		// Else just process the files
		$local_filepath = dirname($local_filename);
		if (!file_exists($local_filepath)) {
			mkdir($local_filepath, 0777, true);
		}

		if (file_exists($local_filename)) {
			@unlink($local_filename);
		}

		$local_file_fp = fopen($local_filename, "w");
		if (zip_entry_open($zip_fp, $zip_entry, "r")) {
			$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
			fwrite($local_file_fp, $buf);
			zip_entry_close($zip_entry);
			fclose($local_file_fp);
		}
	}
	zip_close($zip_fp);
}

/*
from_dir - Source Directory
dest_dir - Destination Directory
move_files - (true) will move each file individually. (false) will remove destination sub-directories and move entire source sub-directory
*/
function move_tree($from_dir, $dest_dir, $move_files = false) {

	if (!is_dir($from_dir)) {
		echo "Source Directory does not exists [". $from_dir ."]<br />";
		die();
	}

	if (!is_dir($dest_dir)) {
		echo "Destination Directory does not exists [". $dest_dir ."]<br />";
		die();
	}

	if ($move_files == true) {
		$from_files = utility_scandir($from_dir);
		if ((is_array($from_files)) && (count($from_files))) {
			foreach($from_files as $from_file_full) {
				$from_file = str_replace(trailingslashit_snapshot($from_dir), '', $from_file_full);
				$dest_file_full = trailingslashit_snapshot($dest_dir) . $from_file;

				if (!file_exists( dirname($dest_file_full) )) {
					mkdir( dirname($dest_file_full), 0777, true );
				}

				if (file_exists($dest_file_full))
					unlink($dest_file_full);

				$rename_ret = rename($from_file_full, $dest_file_full);
				if ($rename_ret === false) {
					die();
				}
			}
		}
	} else {

		if ($from_dh = opendir($from_dir)) {
			while (($from_file = readdir($from_dh)) !== false) {
				if (($from_file == '.') || ($from_file == '..'))
					continue;

				$from_file_full = trailingslashit_snapshot($from_dir) . $from_file;
				$dest_file_full = trailingslashit_snapshot($dest_dir) . $from_file;

				if (file_exists($dest_file_full)) {
					if (is_dir($dest_file_full)) {
						utility_recursive_rmdir($dest_file_full);
					} else {
						unlink($dest_file_full);
					}
				}
				rename($from_file_full, $dest_file_full);
			}
			closedir($from_dh);
		}
	}
}

function maybe_unserialize_snapshot( $original ) {
	if ( is_serialized_snapshot( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize( $original );
	return $original;
}

function is_serialized_snapshot( $data ) {
	// if it isn't a string, it isn't serialized
	if ( ! is_string( $data ) )
		return false;
	$data = trim( $data );
 	if ( 'N;' == $data )
		return true;
	$length = strlen( $data );
	if ( $length < 4 )
		return false;
	if ( ':' !== $data[1] )
		return false;
	$lastc = $data[$length-1];
	if ( ';' !== $lastc && '}' !== $lastc )
		return false;
	$token = $data[0];
	switch ( $token ) {
		case 's' :
			if ( '"' !== $data[$length-2] )
				return false;
		case 'a' :
		case 'O' :
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b' :
		case 'i' :
		case 'd' :
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;\$/", $data );
	}
	return false;
}

function snapshot_utility_consume_archive_manifest($manifestFile) {

	$snapshot_manifest = array();
	$manifest_array = file($manifestFile);
	if ($manifest_array) {

		foreach($manifest_array as $file_line) {
			list($key, $value) = explode(':', $file_line, 2);
			$key = trim($key);

			if ($key == "TABLES") {
				if (is_serialized_snapshot($value)) {
					$value = maybe_unserialize_snapshot($value);
				} else {
					$table_values = explode(',', $value);

					foreach($table_values as $idx => $table_name) {
						$table_values[$idx] = trim($table_name);
					}

					$value = $table_values;
				}
			} else if (($key == "TABLES-DATA") || ($key == "ITEM") || ($key == "FILES-DATA")) {
				$value = maybe_unserialize_snapshot($value);
			} else {
				$value = trim($value);
			}

			$snapshot_manifest[$key] = $value;
		}
		//echo "snapshot_manifest<pre>"; print_r($snapshot_manifest); echo "</pre>";

		if (isset($snapshot_manifest['VERSION'])) {
			if (($snapshot_manifest['VERSION'] == "1.0") && (!isset($snapshot_manifest['TABLES-DATA']))) {

				$backupFile = trailingslashit_snapshot($sessionRestoreFolder) . 'snapshot_backups.sql';
				$table_segments = snapshot_utility_get_table_segments_from_single($backupFile);

				if ($table_segments) {
					$snapshot_manifest['TABLES-DATA'] = $table_segments;
					unlink($backupFile);
				}
			}
		}
		return $snapshot_manifest;
	}
}

function extract_wp_config_db_info($wp_config_file) {
//	$wp_config_file = $_SESSION['restore_form']['snapshot']['archive-extract-path'] ."www/wp-config.php";

	$wp_config_db_info = array();
	$wp_config_file_content = file($wp_config_file);

	if (($wp_config_file_content) && (is_array($wp_config_file_content))) {

		foreach($wp_config_file_content as $_line => $_line_data) {
			if ((stristr($_line_data, 'DB_NAME') !== false)
			 || (stristr($_line_data, 'DB_USER') !== false)
			 || (stristr($_line_data, 'DB_PASSWORD') !== false)
			 || (stristr($_line_data, 'DB_HOST') !== false)) {

				$_line_data = str_replace("define(", '', $_line_data);
				$_line_data = str_replace(");", '', $_line_data);

				list($token, $value) = explode(',', $_line_data, 2);
				$token = trim($token);
				$value = trim($value);

				if ($token[0] == "'")
					$token = str_replace("'", "", $token);
				else if ($token[0] == '"')
					$token = str_replace('"', "", $token);

				if ($value[0] == "'")
					$value = str_replace("'", "", $value);
				else if ($value[0] == '"')
					$value = str_replace('"', "", $value);

				$wp_config_db_info[$token] = $value;

			} else if (stristr($_line_data, '$table_prefix') !== false) {
				$_line_data = str_replace('$table_prefix', '', trim($_line_data));
				$_line_data = str_replace('=', '', trim($_line_data));
				$_line_data = str_replace(';', '', trim($_line_data));
				if ($_line_data[0] == "'")
					$_line_data = str_replace("'", "", $_line_data);
				else if ($_line_data[0] == '"')
					$_line_data = str_replace('"', "", $_line_data);

				//echo "line_data=[". $_line_data ."]<br />";
				//die();
				$wp_config_db_info['DB_BASE_PREFIX'] = $_line_data;
			}
		}
	}
	return $wp_config_db_info;
}

function trailingslashit_snapshot($string) {
	return untrailingslashit_snapshot($string) . '/';
}
function untrailingslashit_snapshot($string) {
	return rtrim($string, '/');
}

function utility_scandir($base='') {
	if ((!$base) || (!strlen($base)))
		return array();

	if (!file_exists($base)) {
		return array();
	}

	$data = array_diff(scandir($base), array('.', '..'));

	$subs = array();
	foreach($data as $key => $value) :
		if ( is_dir($base . '/' . $value) ) :
			unset($data[$key]);
			$subs[] = utility_scandir($base . '/' . $value);
		elseif ( is_file($base . '/' . $value) ) :
			$data[$key] = $base . '/' . $value;
		endif;
	endforeach;

	if (count($subs)) {
		foreach ( $subs as $sub ) {
			$data = array_merge($data, $sub);
		}
	}

	return $data;
}


/***************************************************************************************************/
/* Search/Replace MySQL data adapted from https://github.com/interconnectit/Search-Replace-DB      */
/***************************************************************************************************/
function search_replace_table_data($table, $connection, $search, $replace ) {

	$guid = isset( $_POST[ 'guid' ] ) && $_POST[ 'guid' ] == 1 ? 1 : 0;
	$exclude_cols = array( 'guid' );

	$fields = mysql_query( 'DESCRIBE `' . $table .'`', $connection );
	while( $column = mysql_fetch_array( $fields ) )
		$columns[ $column[ 'Field' ] ] = $column[ 'Key' ] == 'PRI' ? true : false;

	// Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
	$row_count = mysql_query( 'SELECT COUNT(*) FROM `' . $table .'`', $connection );
	$rows_result = mysql_fetch_array( $row_count );
	$row_count = $rows_result[ 0 ];
	if ( $row_count == 0 )
		continue;

	$page_size = 50000;
	$pages = ceil( $row_count / $page_size );

	for( $page = 0; $page < $pages; $page++ ) {

		$current_row = 0;
		$start = $page * $page_size;
		$end = $start + $page_size;
		// Grab the content of the table
		$data = mysql_query( sprintf( 'SELECT * FROM `%s` LIMIT %d, %d', $table, $start, $end ), $connection );

//		if ( ! $data )
//			$report[ 'errors' ][] = mysql_error( );

		while ( $row = mysql_fetch_array( $data ) ) {

			//$report[ 'rows' ]++; // Increment the row counter
			$current_row++;

			$update_sql = array( );
			$where_sql = array( );
			$upd = false;

			foreach( $columns as $column => $primary_key ) {
				if ( $guid == 1 && in_array( $column, $exclude_cols ) )
					continue;

				$edited_data = $data_to_fix = $row[ $column ];

				// Run a search replace on the data that'll respect the serialisation.
				$edited_data = recursive_unserialize_replace( $search, $replace, $data_to_fix );

				// Something was changed
				if ( $edited_data != $data_to_fix ) {
					//$report[ 'change' ]++;
					$update_sql[] = $column . ' = "' . mysql_real_escape_string( $edited_data ) . '"';
					$upd = true;
				}

				if ( $primary_key )
					$where_sql[] = $column . ' = "' . mysql_real_escape_string( $data_to_fix ) . '"';
			}

			if ( $upd && ! empty( $where_sql ) ) {
				$sql = 'UPDATE `' . $table . '` SET ' . implode( ', ', $update_sql ) . ' WHERE ' . implode( ' AND ', array_filter( $where_sql ) );
				//echo "sql=[". $sql ."]<br />";
				$result = mysql_query( $sql, $connection );
				//if ( ! $result )
				//	$report[ 'errors' ][] = mysql_error( );
				//else
				//	$report[ 'updates' ]++;

			} elseif ( $upd ) {
				//$report[ 'errors' ][] = sprintf( '"%s" has no primary key, manual change needed on row %s.', $table, $current_row );
			}
		}
	}
}

/**
 * Take a serialised array and unserialise it replacing elements as needed and
 * unserialising any subordinate arrays and performing the replace on those too.
 *
 * @param string $from       String we're looking to replace.
 * @param string $to         What we want it to be replaced with
 * @param array  $data       Used to pass any subordinate arrays back to in.
 * @param bool   $serialised Does the array passed via $data need serialising.
 *
 * @return array	The original array with all elements replaced as needed.
 */
function recursive_unserialize_replace( $from = '', $to = '', $data = '', $serialised = false ) {

	// some unseriliased data cannot be re-serialised eg. SimpleXMLElements
	try {

		if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
			$data = recursive_unserialize_replace( $from, $to, $unserialized, true );
		}

		elseif ( is_array( $data ) ) {
			$_tmp = array( );
			foreach ( $data as $key => $value ) {
				$_tmp[ $key ] = recursive_unserialize_replace( $from, $to, $value, false );
			}

			$data = $_tmp;
			unset( $_tmp );
		}

		// Submitted by Tina Matter
		elseif ( is_object( $data ) ) {
			$dataClass = get_class( $data );
			$_tmp = new $dataClass( );
			foreach ( $data as $key => $value ) {
				$_tmp->$key = recursive_unserialize_replace( $from, $to, $value, false );
			}

			$data = $_tmp;
			unset( $_tmp );
		}

		else {
			if ( is_string( $data ) )
				$data = str_replace( $from, $to, $data );
		}

		if ( $serialised )
			return serialize( $data );

	} catch( Exception $error ) {

	}

	return $data;
}

function zipFileErrMsg($errno) {
  // using constant name as a string to make this function PHP4 compatible
  $zipFileFunctionsErrors = array(
    'ZIPARCHIVE::ER_MULTIDISK' => 'Multi-disk zip archives not supported.',
    'ZIPARCHIVE::ER_RENAME' => 'Renaming temporary file failed.',
    'ZIPARCHIVE::ER_CLOSE' => 'Closing zip archive failed',
    'ZIPARCHIVE::ER_SEEK' => 'Seek error',
    'ZIPARCHIVE::ER_READ' => 'Read error',
    'ZIPARCHIVE::ER_WRITE' => 'Write error',
    'ZIPARCHIVE::ER_CRC' => 'CRC error',
    'ZIPARCHIVE::ER_ZIPCLOSED' => 'Containing zip archive was closed',
    'ZIPARCHIVE::ER_NOENT' => 'No such file.',
    'ZIPARCHIVE::ER_EXISTS' => 'File already exists',
    'ZIPARCHIVE::ER_OPEN' => 'Can\'t open file',
    'ZIPARCHIVE::ER_TMPOPEN' => 'Failure to create temporary file.',
    'ZIPARCHIVE::ER_ZLIB' => 'Zlib error',
    'ZIPARCHIVE::ER_MEMORY' => 'Memory allocation failure',
    'ZIPARCHIVE::ER_CHANGED' => 'Entry has been changed',
    'ZIPARCHIVE::ER_COMPNOTSUPP' => 'Compression method not supported.',
    'ZIPARCHIVE::ER_EOF' => 'Premature EOF',
    'ZIPARCHIVE::ER_INVAL' => 'Invalid argument',
    'ZIPARCHIVE::ER_NOZIP' => 'Not a zip archive',
    'ZIPARCHIVE::ER_INTERNAL' => 'Internal error',
    'ZIPARCHIVE::ER_INCONS' => 'Zip archive inconsistent',
    'ZIPARCHIVE::ER_REMOVE' => 'Can\'t remove file',
    'ZIPARCHIVE::ER_DELETED' => 'Entry has been deleted',
  );
  $errmsg = 'unknown';
  foreach ($zipFileFunctionsErrors as $constName => $errorMessage) {
    if (defined($constName) and constant($constName) === $errno) {
      return 'Zip File Function error: '.$errorMessage;
    }
  }
  return 'Zip File Function error: unknown';
}