<?php

if (!class_exists('SnapshotBackupDatabase')) {
	class SnapshotBackupDatabase {

		var $errors;

	    private $fp;
		private $status_fp;

		function __construct() {
			$this->errors = array();
		}

	    function SnapshotBackupDatabase() {
	        $this->__construct();
	    }

		/**
		 * Sets the open file point to be used when writing out the
		 * table dumps. Not needed on the import step.
		 * @param string $args
		 * @return none
		 */
		function set_fp($fp) {
			if ($fp)
				$this->fp = $fp;
		}

		/**
		 * Sets the open file point to be used when writing out the
		 * table dumps. Not needed on the import step.
		 * @param string $args
		 * @return none
		 */
		function set_status_fp($status_fp) {
			if ($status_fp)
				$this->status_fp = $status_fp;
		}

		/**
		 * Logs any error messages
		 * @param string $args
		 * @return none
		 */
		function error($error) {

			$this->errors[] = $error;
		}

		/**
		 * Write to the backup file
		 * @param string $query_line the line to write
		 * @return null
		 */
		function stow($query_line) {
			//echo "query_line=[". $query_line ."]<br />";
			if(false === @fwrite($this->fp, $query_line))
				$this->error(__('There was an error writing a line to the backup script:', SNAPSHOT_I18N_DOMAIN) . '  ' . $query_line . '  ' . $php_errormsg);
		}

		/**
		 * Better addslashes for SQL queries.
		 * Taken from phpMyAdmin.
		 */
		function sql_addslashes($a_string = '', $is_like = false) {
			if ($is_like) $a_string = str_replace('\\', '\\\\\\\\', $a_string);
			else $a_string = str_replace('\\', '\\\\', $a_string);
			return str_replace('\'', '\\\'', $a_string);
		}

		/**
		 * Add backquotes to tables and db-names in
		 * SQL queries. Taken from phpMyAdmin.
		 */
		function backquote($a_name) {
			if (!empty($a_name) && $a_name != '*') {
				if (is_array($a_name)) {
					$result = array();
					reset($a_name);
					while(list($key, $val) = each($a_name))
						$result[$key] = '`' . $val . '`';
					return $result;
				} else {
					return '`' . $a_name . '`';
				}
			} else {
				return $a_name;
			}
		}

		/**
		 * Front-end function to the backup_table() function. This
		 * function just provides the foreach looping over the
		 * tables array provided.
		 *
		 * @since 1.0.0
		 * @uses non
		 *
		 * @param array $tables an array of table names to backup.
		 * @return none
		 */

		function backup_tables($tables) {

			if (is_array($tables)) {
				foreach($tables as $table)
				{
					$this->backup_table($table);
				}
			}
		}

		/**
		 * Taken partially from phpMyAdmin and partially from
		 * Alain Wolf, Zurich - Switzerland
		 * Website: http://restkultur.ch/personal/wolf/scripts/db_backup/
		 * Modified by Scott Merrill (http://www.skippy.net/)
		 * to use the WordPress $wpdb object
		 * @param string $table
		 * @param string $segment
		 * @return void
		 */
		function backup_table($table, $rows_start=0, $rows_end = '', $rows_total = '', $sql='') {

			global $wpdb;

			$total_rows = 0;

			$table_structure = $wpdb->get_results("DESCRIBE `". $table ."`");
			if (!$table_structure) {
				$this->error(__('Error getting table details', SNAPSHOT_I18N_DOMAIN) . ": $table");
				return false;
			}

			if ($rows_start == 0) {
				//$this->stow('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"' . ";\n");
				$table_create = $wpdb->get_row("SHOW CREATE TABLE `". $table ."`", ARRAY_A);
				//echo "table_create<pre>"; print_r($table_create); echo "</pre>";
				//die();

				if (isset($table_create['Create Table'])) {
					$create_table_str = str_replace(
						'CREATE TABLE '. $this->backquote($table) .' (',
						'CREATE TABLE IF NOT EXISTS '. $this->backquote($table) .' (',
						$table_create['Create Table']);
					//echo "create_table_str=[". $create_table_str ."]<br />";
					$this->stow($create_table_str .";\n");
				}
				$this->stow("TRUNCATE TABLE " . $this->backquote($table) . ";\n");
			}

			if (!empty($sql))
				$table_data = $wpdb->get_results($sql, ARRAY_A);
			else
				$table_data = $wpdb->get_results("SELECT * FROM `". $table ."` LIMIT {$rows_start}, {$rows_end}", ARRAY_A);

			//echo "table_data<pre>"; print_r($table_data); echo "</pre>";
			$entries = 'INSERT INTO ' . $this->backquote($table) . ' VALUES (';
			//    \x08\\x09, not required
			$search = array("\x00", "\x0a", "\x0d", "\x1a");
			$replace = array('\0', '\n', '\r', '\Z');

			if($table_data) {
				foreach ($table_data as $row) {

					$values = array();
					foreach ($row as $key => $value) {

						if (isset($ints[strtolower($key)])) {
							// make sure there are no blank spots in the insert syntax,
							// yet try to avoid quotation marks around integers
							$value = ( null === $value || '' === $value) ? $defs[strtolower($key)] : $value;
							$values[] = ( '' === $value ) ? "''" : $value;
						} else {
							$values[] = "'" . str_replace($search, $replace, $this->sql_addslashes($value)) . "'";
						}
					}
					$this->stow(" \n" . $entries . implode(', ', $values) . ');');
					$total_rows += 1;
				}
			}

			if ($rows_end == $rows_total) {

				// Create footer/closing comment in SQL-file
				$this->stow("\n");
				$this->stow("# --------------------------------------------------------\n");
				$this->stow("\n");
			}
			return $total_rows;
		}


		function restore_databases($buffer)
		{
			global $wpdb;

			$sql = '';
			$start_pos = 0;
			$i = 0;
			$len= 0;
			$big_value = 2147483647;
			$delimiter_keyword = 'DELIMITER '; // include the space because it's mandatory
			$length_of_delimiter_keyword = strlen($delimiter_keyword);
			$sql_delimiter = ';';
			$finished = false;

			$len = strlen($buffer);

			//if (get_class($wpdb) === "wpdb") {
			//	$sql = 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";';
			//	$wpdb->query($sql);
			//}

			// Grab some SQL queries out of it
			while ($i < $len)
			{
				//@set_time_limit( 300 );

				$found_delimiter = false;

				// Find first interesting character
				$old_i = $i;

			    // this is about 7 times faster that looking for each sequence i
				// one by one with strpos()
				if (preg_match('/(\'|"|#|-- |\/\*|`|(?i)(?<![A-Z0-9_])' . $delimiter_keyword . ')/', $buffer, $matches, PREG_OFFSET_CAPTURE, $i))
				{
					// in $matches, index 0 contains the match for the complete
					// expression but we don't use it

					$first_position = $matches[1][1];
				}
				else
				{
					$first_position = $big_value;
				}

		        $first_sql_delimiter = strpos($buffer, $sql_delimiter, $i);
		        if ($first_sql_delimiter === FALSE)
				{
		            $first_sql_delimiter = $big_value;
		        }
				else
				{
		            $found_delimiter = true;
		        }

		        // set $i to the position of the first quote, comment.start or delimiter found
		        $i = min($first_position, $first_sql_delimiter);
				//echo "i=[". $i ."]<br />";

		        if ($i == $big_value)
				{
		            // none of the above was found in the string

		            $i = $old_i;
		            if (!$finished)
					{
		                break;
		            }

					// at the end there might be some whitespace...
		            if (trim($buffer) == '')
					{
		                $buffer = '';
		                $len = 0;
		                break;
		            }

					// We hit end of query, go there!
		            $i = strlen($buffer) - 1;
		        }

				// Grab current character
		        $ch = $buffer[$i];

		        // Quotes
		        if (strpos('\'"`', $ch) !== FALSE)
				{
		            $quote = $ch;
		            $endq = FALSE;

					while (!$endq)
					{
		                // Find next quote
		                $pos = strpos($buffer, $quote, $i + 1);

		    			// No quote? Too short string
		                if ($pos === FALSE)
						{
		                    // We hit end of string => unclosed quote, but we handle it as end of query
		                    if ($finished)
							{
		                        $endq = TRUE;
		                        $i = $len - 1;
		                    }

		                    $found_delimiter = false;
		                    break;
		                }

		                // Was not the quote escaped?
		                $j = $pos - 1;

		                while ($buffer[$j] == '\\') $j--;

		                // Even count means it was not escaped
		                $endq = (((($pos - 1) - $j) % 2) == 0);

		                // Skip the string
		                $i = $pos;

		                if ($first_sql_delimiter < $pos)
						{
		                    $found_delimiter = false;
		                }
		            }

		            if (!$endq)
					{
		                break;
		            }

		            $i++;

		            // Aren't we at the end?
		            if ($finished && $i == $len)
					{
		                $i--;
		            }
					else
					{
		                continue;
		            }
		        }

		        // Not enough data to decide
		        if ((($i == ($len - 1) && ($ch == '-' || $ch == '/'))
		          || ($i == ($len - 2) && (($ch == '-' && $buffer[$i + 1] == '-')
		            || ($ch == '/' && $buffer[$i + 1] == '*')))) && !$finished) {
		            break;
		        }


		        // Comments
		        if ($ch == '#'
		         || ($i < ($len - 1) && $ch == '-' && $buffer[$i + 1] == '-'
		          && (($i < ($len - 2) && $buffer[$i + 2] <= ' ')
		           || ($i == ($len - 1)  && $finished)))
		         || ($i < ($len - 1) && $ch == '/' && $buffer[$i + 1] == '*')
		                )
				{
		            // Copy current string to SQL
		            if ($start_pos != $i)
					{
		                $sql .= substr($buffer, $start_pos, $i - $start_pos);
		            }

		            // Skip the rest
		            $start_of_comment = $i;

		            // do not use PHP_EOL here instead of "\n", because the export
		            // file might have been produced on a different system
		            $i = strpos($buffer, $ch == '/' ? '*/' : "\n", $i);

		            // didn't we hit end of string?
		            if ($i === FALSE)
					{
		                if ($finished)
						{
		                    $i = $len - 1;
		                }
						else
						{
		                    break;
		                }
		            }

		            // Skip *
		            if ($ch == '/')
					{
		                $i++;
		            }

		            // Skip last char
		            $i++;

		            // We need to send the comment part in case we are defining
		            // a procedure or function and comments in it are valuable
		            $sql .= substr($buffer, $start_of_comment, $i - $start_of_comment);

		            // Next query part will start here
		            $start_pos = $i;

		            // Aren't we at the end?
		            if ($i == $len)
					{
		                $i--;
		            }
					else
					{
		                continue;
		            }
		        }

		        // Change delimiter, if redefined, and skip it (don't send to server!)
		        if (strtoupper(substr($buffer, $i, $length_of_delimiter_keyword)) == $delimiter_keyword
		         && ($i + $length_of_delimiter_keyword < $len))
				{
					// look for EOL on the character immediately after 'DELIMITER '
					// (see previous comment about PHP_EOL)
					$new_line_pos = strpos($buffer, "\n", $i + $length_of_delimiter_keyword);

					// it might happen that there is no EOL
					if (FALSE === $new_line_pos)
					{
						$new_line_pos = $len;
					}

					$sql_delimiter = substr($buffer, $i + $length_of_delimiter_keyword, $new_line_pos - $i - $length_of_delimiter_keyword);
					$i = $new_line_pos + 1;

					// Next query part will start here
					$start_pos = $i;
					continue;
				}

				if ($found_delimiter || ($finished && ($i == $len - 1)))
				{
		            $tmp_sql = $sql;

		            if ($start_pos < $len)
					{
		                $length_to_grab = $i - $start_pos;

		                if (! $found_delimiter)
						{
		                    $length_to_grab++;
		                }

		                $tmp_sql .= substr($buffer, $start_pos, $length_to_grab);
		                unset($length_to_grab);
		            }

		            // Do not try to execute empty SQL
		            if (! preg_match('/^([\s]*;)*$/', trim($tmp_sql)))
					{
		                $sql = $tmp_sql;
						//echo "sql=[". $sql ."]<br />";
						$ret_db = $wpdb->query($sql);
						//echo "ret_db<pre>"; print_r($ret_db); echo "</pre>";

		                $buffer = substr($buffer, $i + strlen($sql_delimiter));
		                // Reset parser:

		                $len = strlen($buffer);
		                $sql = '';
		                $i = 0;
		                $start_pos = 0;

		                // Any chance we will get a complete query?
		                //if ((strpos($buffer, ';') === FALSE) && !$GLOBALS['finished']) {
		                if ((strpos($buffer, $sql_delimiter) === FALSE) && !$finished)
						{
		                    break;
		                }
		            }
					else
					{
		                $i++;
		                $start_pos = $i;
		            }
		        }

			}

		}
	}
}

// The following is needed to patch some code whcih does not work. During a restore Snapshot load the database tables to temporary tables.
// Then once all tables have been loaded it DROPs the original table then RENAMEs the restored to replace the original table. Seems the
// WordPress wpdb cloass via the $wpdb->query() function does not treat the sql RENAME keywork like DROP, CREATE, etc. It expects there
// query result. Which a RENAME does not produce. So this class extends wpdb then replaces the query() function to include RENAME as one
// of the 'special' kewwords. See line 524 for the preg_match line containing the 'rename' use.

if (!class_exists('Snapshot_WPDB')) {
	class Snapshot_WPDB extends wpdb {

		function query( $query ) {
			if ( ! $this->ready )
				return false;

			// some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
			$query = apply_filters( 'query', $query );

			$return_val = 0;
			$this->flush();

			// Log how the function was called
			$this->func_call = "\$db->query(\"$query\")";

			// Keep track of the last query for debug..
			$this->last_query = $query;

			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
				$this->timer_start();

			$this->result = @mysql_query( $query, $this->dbh );
			$this->num_queries++;

			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
				$this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );

			// If there is an error then take note of it..
			if ( $this->last_error = mysql_error( $this->dbh ) ) {
				$this->print_error();
				return false;
			}

			if ( preg_match( '/^\s*(create|alter|truncate|drop|rename)\s/i', $query ) ) {
				$return_val = $this->result;
			} elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $query ) ) {
				$this->rows_affected = mysql_affected_rows( $this->dbh );
				// Take note of the insert_id
				if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
					$this->insert_id = mysql_insert_id($this->dbh);
				}
				// Return number of rows affected
				$return_val = $this->rows_affected;
			} else {
				$num_rows = 0;
				while ( $row = @mysql_fetch_object( $this->result ) ) {
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}

				// Log number of rows the query returned
				// and return number of rows selected
				$this->num_rows = $num_rows;
				$return_val     = $num_rows;
			}

			return $return_val;
		}

	}
}