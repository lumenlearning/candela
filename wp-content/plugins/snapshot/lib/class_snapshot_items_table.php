<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if (!class_exists('Snapshot_Items_Table')) {
	class Snapshot_Items_Table extends WP_List_Table {

		var $item;
		var $file_kb;

	    function __construct( ) {
	        global $status, $page;

	        //Set parent defaults
	        parent::__construct( array(
	            'singular'  => 'Archive',     //singular name of the listed records
	            'plural'    => 'Archive',    //plural name of the listed records
	            'ajax'      => false        //does this table support ajax?
	        ) );
	    }

		function Snapshot_Items_Table() {
	        $this->__construct();
		}

		function get_table_classes() {
			return array( 'widefat', 'fixed', 'snapshot-item-archives-table' );
		}

	    function get_bulk_actions() {
	        $actions = array(
	            'delete'    => 'Delete'
	        );
	        return $actions;
	    }

		function check_table_filters() {

			$filters = array();

			if ( (isset($_POST['snapshot-filter'])) && (isset($_POST['snapshot-filter-blog-id'])) ) {
	 			$filters['blog-id'] = intval($_POST['snapshot-filter-blog-id']);
			} else {
				$filters['blog-id'] = '';
			}

			if ( (isset($_POST['snapshot-filter'])) && (isset($_POST['snapshot-filter-destination'])) ) {
	 			$filters['destination'] = sanitize_text_field($_POST['snapshot-filter-destination']);
			} else {
				if (isset($_GET['destination'])) {
					$filters['destination'] = sanitize_text_field($_GET['destination']);
				} else {
					$filters['destination'] = '';
				}
			}

			return $filters;

		}

		function extra_tablenav( $which ) {
			global $wpmudev_snapshot;

			if ($which == "top") {
				$HAS_FILTERS = false;
				$filters = $this->check_table_filters();

				?><div class="alignleft actions"><?php
/*
				if (is_multisite()) {
					$blog_counts = array();
					if ((isset($wpmudev_snapshot->config_data['items'])) && (count($wpmudev_snapshot->config_data['items']))) {

						foreach($wpmudev_snapshot->config_data['items'] as $idx => $item) {

							if (!isset($blog_counts[$item['blog-id']]))
								$blog_counts[$item['blog-id']] = 1;
							else
								$blog_counts[$item['blog-id']] += 1;
						}
					}

					// We need to display a column showing the blog for a snapshot.
					// Simply gets all blogs then builds a local array using the blog_id as the key to the blog url.
					$blogs = snapshot_utility_get_blogs();
					if ($blogs) {
						$tmp_blogs = array();
						foreach($blogs as $blog) {
							// Does this blog_id exist in our count array?
							if (isset($blog_counts[$blog->blog_id]))
								$tmp_blogs[$blog->blog_id] = $blog->blogname ."<br /> (". $blog->domain .") (". $blog_counts[$blog->blog_id] .")";
						}
						$blogs = $tmp_blogs;
					}

					if (($blogs) && (count($blogs))) {
						$HAS_FILTERS = true;

						?>
						<select
							name="snapshot-filter-blog-id" id="snapshot-filter-blog-id">
							<option value="">Show All Blogs</option>
							<?php
								foreach($blogs as $blog_id => $blog_domain) {
									?><option <?php if ($blog_id == $filters['blog-id']) { echo ' selected="selected" '; } ?>
										value="<?php echo $blog_id ?>"><?php echo $blog_domain; ?></option><?php
								}
							?>
						</select>
						<?php
					}
				}
*/
				if ((isset($wpmudev_snapshot->config_data['destinations'])) && (count($wpmudev_snapshot->config_data['destinations']))) {
					$HAS_FILTERS = true;
					?>
					<select
						name="snapshot-filter-destination" id="snapshot-filter-destination">
						<option value=""><?php _e('Show All Destinations', SNAPSHOT_I18N_DOMAIN); ?></option>
						<?php snapshot_utility_destination_select_options_groups(
							$wpmudev_snapshot->config_data['destinations'],
							$filters['destination'],
							$wpmudev_snapshot->snapshot_get_setting('destinationClasses'));
						?>
					</select>
					<?php
				}

				if ($HAS_FILTERS) {
					?><input id="post-query-submit" class="button-secondary" type="submit" value="Filter" name="snapshot-filter"><?php
				}

				?></div><?php
			}
		}

	    function column_default($item, $column_name){
			//echo "column_name=[". $column_name ."]<br />";
			//echo "item<pre>"; print_r($item); echo "</pre>";
			echo "&nbsp;";
	  	}

		function column_cb($item) {
			if (snapshot_utility_current_user_can( 'manage_snapshots_items' )) {
				?><input type="checkbox" name="delete-bulk[]" value="<?php echo $item['timestamp']; ?>" /><?php
			}
		}

		function column_name($item) {
			//echo "item[blog-id][". $item['blog-id']."]<br />";
			//echo "item<pre>"; print_r($item); echo "</pre>";

			?>
			<a href="?page=snapshots_edit_panel&amp;snapshot-action=edit&amp;item=<?php echo $item['timestamp']; ?>"><?php
				echo stripslashes($item['name']) ?></a>
			<div class="row-actions" style="margin:0; padding:0;"><?php
				$row_actions = '';
				if (snapshot_utility_current_user_can( 'manage_snapshots_items' )) {
					$row_actions .= '<span class="edit"><a href="?page=snapshots_edit_panel&amp;snapshot-action=edit&amp;item='.
						$item['timestamp'] .'">'. __('edit', SNAPSHOT_I18N_DOMAIN) .'</a></span>';

					$show_run_now = false;
					if (is_multisite()) {
						if ($item['blog-id'] != 0) {
							$show_run_now = true;
						}
					} else {
						$show_run_now = true;
					}
					if ($show_run_now == true) {

						$row_actions .= ' | ';
						$row_actions .= '<span class="runonce"><a href="?page=snapshots_edit_panel&amp;snapshot-action=runonce&amp;item='.
							$item['timestamp'] .'&amp;snapshot-noonce-field='.
							wp_create_nonce( 'snapshot-runonce' ).'">'. __('run now', SNAPSHOT_I18N_DOMAIN) .'</a></span>';
					}
				}

				if (snapshot_utility_current_user_can( 'manage_snapshots_items' )) {
					if (strlen($row_actions))  $row_actions .= ' | ';

					$row_actions .= '<span class="restore"><a
					 	href="?page=snapshots_edit_panel&amp;snapshot-action=item-archives&amp;snapshot-action-sub=restore&amp;item='.
						$item['timestamp'] .'">'. __('restore', SNAPSHOT_I18N_DOMAIN) .'</a></span>';
				}

				if (snapshot_utility_current_user_can( 'manage_snapshots_items' )) {
					if (strlen($row_actions))  $row_actions .= ' | ';
					$row_actions .= '<span class="delete"><a href="?page=snapshots_edit_panel&amp;snapshot-action=delete-item&amp;item='.
					 	$item['timestamp'] .'&amp;snapshot-noonce-field='. wp_create_nonce( 'snapshot-delete-item' ) .'">'.
						__('delete', SNAPSHOT_I18N_DOMAIN) .'</a></span>';
				}
				if (!empty($row_actions)) echo $row_actions;
				?>
			</div>
			<?php
		}

		function column_blog($item) {

			$blog_column_output = '';

			//echo "blog_id[". $item['blog_id']."] IMPORT<pre>"; print_r($item['IMPORT']); echo "</pre>";
			if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
				$blog_column_output .= '<span class="snapshot-error">(I)</span>';
				if ((isset($item['IMPORT']['WP_BLOG_NAME'])) && (!empty($item['IMPORT']['WP_BLOG_NAME']))) {
					$blog_column_output .= $item['IMPORT']['WP_BLOG_NAME'] .'<br />(';
				}
				$blog_column_output .= $item['IMPORT']['WP_BLOG_DOMAIN'].$item['IMPORT']['WP_BLOG_PATH'];
				if ((isset($item['IMPORT']['WP_BLOG_NAME'])) && (!empty($item['IMPORT']['WP_BLOG_NAME']))) {
					$blog_column_output .= ')';
				}

			} else if (isset($item['blog-id'])) {
				if (is_multisite()) {
					$blog = get_blog_details($item['blog-id']);
					if ($blog) {
						if (isset($blog->blogname)) {
							$blog_column_output .= $blog->blogname .'<br />(';
						}
						$blog_column_output .= $blog->domain.$blog->path;
						if (isset($blog->blogname)) {
							$blog_column_output .= ")";
						}
					} else {
						$blog_column_output .= "&nbsp;";
					}
				} else {
					$blog_column_output .= get_option('blogname') .'<br />(';
					$siteurl = get_option('siteurl');
					$site_domain 	= parse_url($siteurl, PHP_URL_HOST);
					$site_path		= parse_url($siteurl, PHP_URL_PATH);
					$blog_column_output .= $site_domain.$site_path.')';
				}
			} else {
				$blog_column_output .= "&nbsp;";
			}
			echo $blog_column_output;
		}

		function column_notes($item) {
	//		if ((isset($item['data'])) && (count($item['data'])))
	//			$data_item = snapshot_utility_latest_data_item($item['data']);
	//		else
	//			return "&nbsp;";

			$tables_sections_out 	= snapshot_utility_get_tables_sections_display($item);
			$files_sections_out 	= snapshot_utility_get_files_sections_display($item);

			if ((strlen($tables_sections_out['click'])) || (strlen($files_str))) {
				?><p><?php
				echo $tables_sections_out['click'];
				if (strlen($tables_sections_out['click'])) echo "</br />";
				echo $files_sections_out['click'];
				?></p><?php
				echo $tables_sections_out['hidden'];
			}
		}

		function column_interval($item) {
			global $wpmudev_snapshot;

			if ((!isset($item['interval'])) || ($item['interval'] == "immediate") || (empty($item['interval']))) {
				_e('Manual', SNAPSHOT_I18N_DOMAIN);

				$snapshot_locker = new SnapshotLocker($wpmudev_snapshot->snapshot_get_setting('backupLockFolderFull'), $item['timestamp']);
				if (!$snapshot_locker->is_locked()) {
					$locker_info = $snapshot_locker->get_locker_info();
					if ($locker_info['item_key'] == $item['timestamp']) {
						$file_progress = '';
						if ((isset($locker_info['file_offset'])) && ($locker_info['file_size'])) {
							$file_progress = sprintf("%0d%% ", ($locker_info['file_offset']/$locker_info['file_size'])*100);
						}
						//echo "locker_info<pre>"; print_r($locker_info); echo "</pre>";
						$snapshot_process_action = $locker_info['doing'] .' '. $file_progress .'(<a class="snapshot-abort-item" href="pid='.$locker_info['pid'] .
							'&amp;item='. $item['timestamp'] .'">'.
							__('abort', SNAPSHOT_I18N_DOMAIN) .'</a>)<br /><a class="snapshot-thickbox"
							href="'. admin_url()
							.'admin-ajax.php?action=snapshot_view_log_ajax&&amp;snapshot-item='
							. $item['timestamp']
							.'&amp;snapshot-data-item='. $locker_info['data_item_key'] .'&amp;live=1">'. __('Now', SNAPSHOT_I18N_DOMAIN) .'</a>';

						$running_timestamp = $locker_info['time_start'];
						?><br /><?php echo $snapshot_process_action; ?>: <?php echo snapshot_utility_show_date_time( $running_timestamp );
					}
				}

				unset($snapshot_locker);

			} else if ((isset($item['interval'])) && (strlen($item['interval']))) {
				$interval_text = snapshot_utility_get_sched_display($item['interval']);
				if ($interval_text) echo $interval_text;

				$snapshot_locker = new SnapshotLocker($wpmudev_snapshot->snapshot_get_setting('backupLockFolderFull'), $item['timestamp']);
				if (!$snapshot_locker->is_locked()) {
					$locker_info = $snapshot_locker->get_locker_info();
					//echo "locker_info<pre>"; print_r($locker_info); echo "</pre>";
					if ($locker_info['item_key'] == $item['timestamp']) {

						$snapshot_process_action = '<br /><a class="snapshot-thickbox"  href="'. admin_url()
							.'admin-ajax.php?action=snapshot_view_log_ajax&snapshot-item='. $item['timestamp'].
							'&amp;snapshot-data-item='. $locker_info['data_item_key'] .
							'&amp;live=1">'. __('Now', SNAPSHOT_I18N_DOMAIN) .'</a>: ';
						$snapshot_process_action .= $locker_info['doing'];

						$file_progress = '';
						if ((isset($locker_info['file_offset'])) && (intval($locker_info['file_offset']))
 						 && (isset($locker_info['file_size'])) && (intval($locker_info['file_size'])) ) {
							$file_progress = sprintf(" %0d%% ", (intval($locker_info['file_offset'])/intval($locker_info['file_size']))*100);
							$snapshot_process_action .= $file_progress;
						} else if ((isset($locker_info['files_count'])) && (intval($locker_info['files_count']))
						 && (isset($locker_info['files_total'])) && (intval($locker_info['files_total'])) ) {
							$file_progress = sprintf(" %0d%% ", (intval($locker_info['files_count'])/intval($locker_info['files_total']))*100);
							$snapshot_process_action .= $file_progress;

						} else {
							$snapshot_process_action .= " ";
						}

						$snapshot_process_action .= '(<a class="snapshot-abort-item" href="pid='.$locker_info['pid'] .
							'&amp;item='. $item['timestamp'] .'">'.
							__('abort', SNAPSHOT_I18N_DOMAIN) .'</a>)';
						echo $snapshot_process_action;
					}
				} else {
					//$snapshot_process_action 	= __('Next', SNAPSHOT_I18N_DOMAIN) .": ";
					$running_timestamp 			= wp_next_scheduled( 'snapshot_backup_cron', array(intval($item['timestamp'])) );
					echo "<br />". __('Next', SNAPSHOT_I18N_DOMAIN) .": ". snapshot_utility_show_date_time( $running_timestamp );
				}
				unset($snapshot_locker);
			}
			if ((isset($item['data'])) && (count($item['data']))) {
				$data_item = snapshot_utility_latest_data_item($item['data']);

				if (isset($data_item)) {
					if (isset($data_item['timestamp'])) {
						?><br /><?php _e('Last', SNAPSHOT_I18N_DOMAIN); ?>: <?php
							echo snapshot_utility_show_date_time($data_item['timestamp']);
					}
				}
			}
		}

		function column_destination($item) {
			global $wpmudev_snapshot;

			//echo "destination=[". $item['destination'] ."]<br />";
			if (isset($item['destination'])) {

				if ($item['destination'] == "local") {
					_e("Local Server", SNAPSHOT_I18N_DOMAIN);
				} else {
					$destination_slug = $item['destination'];

					if ((isset($wpmudev_snapshot->config_data['destinations'][$destination_slug]['name']))
					 && (strlen($wpmudev_snapshot->config_data['destinations'][$destination_slug]['name']))) {
						$destination_name = stripslashes($wpmudev_snapshot->config_data['destinations'][$destination_slug]['name']);
						?><a href="admin.php?page=snapshots_destinations_panel&amp;snapshot-action=edit&amp;item=<?php
							echo $destination_slug; ?>"><?php echo $destination_name; ?></a><?php
					}

					if ((isset($item['destination-sync'])) && ($item['destination-sync'] == "mirror")) {
						echo "<br />(mirror)";
					}
				}
			}
		}

		function column_archives($item) {
			global $wpmudev_snapshot;

			$_HAS_FILE_RESTORE = false;

			if (!isset($data_item['destination-sync']))
				$data_item['destination-sync'] = "archive";

			if (!isset($data_item['files-count']))
				$data_item['files-count'] = 0;

			$output = "";

			if ((isset($item['data'])) && (count($item['data']))) {

				$data_item = snapshot_utility_latest_data_item($item['data']);
				if (!isset($data_item['timestamp'])) return;

				//echo "data_item<pre>"; print_r($data_item); echo "</pre>";
				if (isset($data_item)) {
					if ((empty($data_item['destination'])) || ($data_item['destination'] == "local")) {

						if (isset($data_item['filename'])) {

							$current_backupFolder = $wpmudev_snapshot->snapshot_get_item_destination_path($item, $data_item);
							if (empty($current_backupFolder)) {
								$current_backupFolder = $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull');
							}

							$backupFile = trailingslashit(trim($current_backupFolder)) . $data_item['filename'];
							//echo "backupFile=[". $backupFile ."]<br />";

							// If we don't find file is the alternate directory then try the default
							if (!file_exists($backupFile)) {
								if ( (isset($data_item['destination-directory'])) || (!empty($data_item['destination-directory'])) ){
									$current_backupFolder = $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull');
									$backupFile = trailingslashit(trim($current_backupFolder)) . $data_item['filename'];
								}
							}

							if (file_exists($backupFile)) {
								$_HAS_FILE_RESTORE = true;

								$output .= '<a href="?page=snapshots_edit_panel&amp;snapshot-item='. $item['timestamp']
									.'&snapshot-data-item='. $data_item['timestamp']
									.'&snapshot-action=download-archive">'. $data_item['filename'] .'</a>';

							} else {

								$output .=  $data_item['filename'];
							}

							if (isset($data_item['file_size'])) {
								$file_kb = snapshot_utility_size_format($data_item['file_size']);
								$output .= " (". $file_kb .")";
							}

							if (strlen($output)) $output .= "<br />";
						}
					} else {

						if (isset($data_item['filename'])) {

							if ( (isset($data_item['destination-directory'])) || (!empty($data_item['destination-directory'])) ){
								$current_backupFolder = $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull');
								$backupFile = trailingslashit(trim($current_backupFolder)) . $data_item['filename'];

								if (file_exists($backupFile)) {
									$_HAS_FILE_RESTORE = true;

									echo '<a href="?page=snapshots_edit_panel&amp;snapshot-item='. $item['timestamp']
										.'&snapshot-data-item='. $data_item['timestamp']
										.'&snapshot-action=download-archive">'. $data_item['filename'] .'</a>';

								} else {
									echo  $data_item['filename'];
								}

								if (isset($data_item['file_size'])) {
									$file_kb = snapshot_utility_size_format($data_item['file_size']);
									$output .= " (". $file_kb .")";
								}

								if (strlen($output)) $output .= "<br />";
							}
						}

						if ($data_item['destination-sync'] == "mirror") {
							$output .= snapshot_utility_data_item_file_processed_count($data_item) ." ".
								__('files synced to destination', SNAPSHOT_I18N_DOMAIN)."<br />";
						}
					}

					$output .= __('Archives', SNAPSHOT_I18N_DOMAIN) .': '. '<a href="?page=snapshots_edit_panel&amp;snapshot-action=item-archives&amp;item='
						. $item['timestamp']
						.'">'. __('view', SNAPSHOT_I18N_DOMAIN) .'</a> ('. count($item['data']) .')';

					$backupLogFileFull = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupLogFolderFull'))
						. $item['timestamp'] ."_". $data_item['timestamp'] .".log";

					if (file_exists($backupLogFileFull)) {
						if (strlen($output)) $output .= " ";

						$output .= __('Latest Log:', SNAPSHOT_I18N_DOMAIN) .' '. '<a class="snapshot-thickbox"
							href="'. admin_url()
							.'admin-ajax.php?action=snapshot_view_log_ajax&amp;snapshot-item='. $item['timestamp'] .
							'&amp;snapshot-data-item='. $data_item['timestamp'] .'">'. __('view', SNAPSHOT_I18N_DOMAIN) .'</a>
							<a href="?page=snapshots_edit_panel&amp;snapshot-action=download-log&amp;snapshot-item=' . $item['timestamp']
								.'&amp;snapshot-data-item='. $data_item['timestamp'] .'&amp;live=0">'
								. __('download', SNAPSHOT_I18N_DOMAIN) .'</a>';

					}

					//if (strlen($output)) $output .= "<br />";


				} else {
					if (isset($item['timestamp'])) {
						//$output .= "Last: ". snapshot_utility_show_date_time($item['timestamp']);

						//if ($wpmudev_snapshot->config_data['config']['absoluteFolder'] != true) {

							$backupLogFileFull = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupLogFolderFull'))
								. $item['timestamp'] ."_backup.log";
							if (file_exists($backupLogFileFull)) {
								if (strlen($output)) $output .= " ";
								$output .= '<a href="?page=snapshots_edit_panel&amp;snapshot-item='. $item['timestamp']
									.'&snapshot-data-item='. $data_item['timestamp']
									.'&snapshot-action=download-log">'. __('view log', SNAPSHOT_I18N_DOMAIN) .'</a>';
							}
						//}
					}

					if (strlen($output)) $output .= "<br />";

					$output .= __('No Snapshot file found', SNAPSHOT_I18N_DOMAIN);
				}

				if (strlen($output))
					echo $output;
				else
					echo "&nbsp;";
			}
			$this->column_status($item);
		}

		function column_status($item) {

			$status = array();
			$status['archives'] = array();
			$status['archives']['pending'] = 0;
			$status['archives']['fail'] = 0;
			$status['archives']['complete'] = 0;

			$status['destination']['pending'] = 0;
			$status['destination']['fail'] = 0;
			$status['destination']['complete'] = 0;

			if ((!isset($item['data'])) || (!count($item['data']))) {
				$status['archives']['pending'] += 1;
			} else {
				ksort($item['data']);
				foreach($item['data'] as $data_item) {

					if (!isset($data_item['archive-status'])) {
						$status['archives']['pending'] += 1;
					} else {
						$status_item = snapshot_utility_latest_data_item($data_item['archive-status']);
						if (!$status_item) {
							$status['archives']['pending'] += 1;

						} else if (isset($status_item['errorStatus'])) {
							if ($status_item['errorStatus'] === true)
								$status['archives']['fail'] += 1;
							else if ($status_item['errorStatus'] !== true)
								$status['archives']['complete'] += 1;
						}
					}

					if ((!isset($data_item['destination'])) || ($data_item['destination'] != $item['destination'])) {
						$data_item['destination'] = $item['destination'];
					}
					//echo "data_item[destination-status]<pre>"; print_r($)
					if ((isset($data_item['destination'])) && ($data_item['destination'] != "local") && (!empty($data_item['destination']))) {
						if (!isset($data_item['destination-status'])) {
							$status['destination']['pending'] += 1;
						} else {
							$status_item = snapshot_utility_latest_data_item($data_item['destination-status']);
							//echo "status_item<pre>"; print_r($status_item); echo "</pre>";
							if (!$status_item) {
								$status['destination']['pending'] += 1;
							} else if ((isset($status_item['sendFileStatus'])) && ($status_item['sendFileStatus'] === true)) {
								$status['destination']['complete'] += 1;
							} else if ($status_item['errorStatus'] === true) {
								$status['destination']['fail'] += 1;
							}
						}
					}
				}
			}

			//echo "status<pre>"; print_r($status); echo "</pre>";

			$status_output = '';
			foreach($status['archives'] as $_key => $_count) {
				if ($_count == 0) continue;

				switch($_key) {
					case 'pending':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= $_count ." ". __('Pending', SNAPSHOT_I18N_DOMAIN);
						break;

					case 'fail':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= $_count ." ". __('Fail', SNAPSHOT_I18N_DOMAIN);
						break;

					case 'complete':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= $_count ." ". __('Complete', SNAPSHOT_I18N_DOMAIN);
						break;
				}
			}

			//if (strlen($status_output))
			//	echo __('Archives:', SNAPSHOT_I18N_DOMAIN) ." ".$status_output ."<br />";
			//else
			//	echo __('Archives:', SNAPSHOT_I18N_DOMAIN) ." ".$status_output ."<br />";
			//echo "status_output=[". $status_output ."]<br />";


			$status_output = '';
			foreach($status['destination'] as $_key => $_count) {
				if ($_count == 0) continue;

				switch($_key) {
					case 'pending':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= $_count ." ". __('Pending', SNAPSHOT_I18N_DOMAIN);
						break;

					case 'fail':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= $_count ." ". __('Fail', SNAPSHOT_I18N_DOMAIN);
						break;

					case 'complete':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= $_count ." ". __('Complete', SNAPSHOT_I18N_DOMAIN);
						break;
				}
			}

			if (strlen($status_output))
				echo "<br />". __('Destination:', SNAPSHOT_I18N_DOMAIN) ." ".$status_output ."<br />";
			//else
			//	echo __('Archives:', SNAPSHOT_I18N_DOMAIN) ." ".$status_output ."<br />";
			//echo "status_output=[". $status_output ."]<br />";
		}

	    function get_columns() {

			$columns = array();

			$columns['cb'] 			= 	'<input type="checkbox" />';
			$columns['name']		=	__('Name', 			SNAPSHOT_I18N_DOMAIN);

			//if (is_multisite())
				$columns['blog']	=	__('Blog', 			SNAPSHOT_I18N_DOMAIN);

			$columns['notes']		= 	__('Notes', 		SNAPSHOT_I18N_DOMAIN);
			$columns['interval']	= 	__('Interval', 		SNAPSHOT_I18N_DOMAIN);
			$columns['destination']	= 	__('Destination', 	SNAPSHOT_I18N_DOMAIN);
			$columns['archives']  	= 	__('Archives', 		SNAPSHOT_I18N_DOMAIN);
			//$columns['status']  	= 	__('Status', 		SNAPSHOT_I18N_DOMAIN);

	        return $columns;
	    }

		function get_hidden_columns() {
			$screen 	= get_current_screen();
			$hidden 	= get_hidden_columns( $screen );

			// Don't want the user to hide the 'File' column
			$file_idx = array_search('file', $hidden);
			if ($file_idx !== false) {
				unset($hidden[$file_idx]);
			}

			return $hidden;
		}

	    function get_sortable_columns() {

			$sortable_columns = array();
	        return $sortable_columns;
	    }

		function display() {
			extract( $this->_args );
	//		echo "_args<pre>"; print_r($this->_args); echo "</pre>";
			$this->display_tablenav( 'top' );
			?>
			<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>" cellspacing="0">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>
			<tbody id="the-list"<?php if ( $singular ) echo " class='list:$singular'"; ?>>
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>
			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>
			</table>
			<?php
			$this->display_tablenav( 'bottom' );
		}


	    function prepare_items($items = array()) {

	        $columns 	= $this->get_columns();
			$hidden 	= $this->get_hidden_columns();
	        $sortable 	= $this->get_sortable_columns();

	        $this->_column_headers = array($columns, $hidden, $sortable);

			$filters = $this->check_table_filters();
			//echo "filters<pre>"; print_r($filters); echo "</pre>";
			if ((isset($filters['blog-id'])) && (intval($filters['blog-id']))) {
				if (count($items)) {
					$filtered_items = array();
					foreach($items as $timestamp => $item) {
						if ($item['blog-id'] == $filters['blog-id']) {
							$filtered_items[$timestamp] = $item;
						}
					}
					$items = $filtered_items;
				}
			}

			if ((isset($filters['destination'])) && (strlen($filters['destination']))) {
				if (count($items)) {

					if ($filters['destination'] == "local")
						$filters['destination'] = '';

					$filtered_items = array();
					foreach($items as $timestamp => $item) {
						if ((isset($item['destination'])) && ($item['destination'] == $filters['destination'])) {
							$filtered_items[$timestamp] = $item;
						}
					}
					$items = $filtered_items;
				}
			}

			//echo "items<pre>"; print_r($items); echo "</pre>";
			if (count($items)) {

				krsort($items);

				$per_page = get_user_meta(get_current_user_id(), 'snapshot_items_per_page', true);
				if ((!$per_page) || ($per_page < 1)) {
					$per_page = 20;
				}

				$current_page = $this->get_pagenum();

				if (count($items) > $per_page) {
					$this->items = array_slice($items, (($current_page - 1) * intval($per_page)), intval($per_page), true);
				} else {
					$this->items = $items;
				}

				$this->set_pagination_args( array(
					'total_items' => count($items),                  			// WE have to calculate the total number of items
					'per_page'    => intval($per_page),                     			// WE have to determine how many items to show on a page
					'total_pages' => ceil(intval(count($items)) / intval($per_page))   	// WE have to calculate the total number of pages
					)
				);
			}
	    }
	}
}