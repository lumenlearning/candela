<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if (!class_exists('Snapshot_Archives_Data_Items_Table')) {
	class Snapshot_Archives_Data_Items_Table extends WP_List_Table {

		var $item;

	    function __construct( ) {
	        global $status, $page;

	        //Set parent defaults
	        parent::__construct( array(
	            'singular'  => __('Archive', SNAPSHOT_I18N_DOMAIN),     //singular name of the listed records
	            'plural'    => __('Archive', SNAPSHOT_I18N_DOMAIN),    //plural name of the listed records
	            'ajax'      => false        //does this table support ajax?
	        ) );
	    }

		function Snapshot_Archives_Data_Items_Table( ) {
	        $this->__construct();
		}

		function get_table_classes() {
			return array( 'widefat', 'fixed', 'snapshot-item-archives-table' );
		}

	    function get_bulk_actions() {
	        $actions = array(
	            'delete'    => 	__('Delete', SNAPSHOT_I18N_DOMAIN),
	        );
			if ((isset($this->item['destination'])) && (!empty($this->item['destination'])) && ($this->item['destination'] !== "local")) {
				$actions['resend'] = __('Resend Archive', SNAPSHOT_I18N_DOMAIN);
			}


	        return $actions;
	    }

	    function column_default($item, $column_name){
			//echo "column_name=[". $column_name ."]<br />";
			//echo "item<pre>"; print_r($item); echo "</pre>";
	    }

		function column_cb($data_item) {
			if ((isset($_GET['snapshot-action-sub'])) && (sanitize_text_field($_GET['snapshot-action-sub']) == "restore")) {

			} else {
				?><input type="checkbox" name="data-item[]" value="<?php echo $data_item['timestamp']; ?>" /><?php
			}
		}

		function column_date($data_item) {
			echo snapshot_utility_show_date_time($data_item['timestamp']);

		}

		function column_file($data_item) {
			global $wpmudev_snapshot;

			$_HAS_FILE_RESTORE = false;

			$this->file_kb = '&nbsp;';

			//echo "data_item<pre>"; print_r($data_item); echo "</pre>";
			//echo "item<pre>"; print_r($this->item); echo "</pre>";
			if ((empty($data_item['destination'])) || ($data_item['destination'] == "local")) {
				if (isset($data_item['filename'])) {
					$current_backupFolder = $wpmudev_snapshot->snapshot_get_item_destination_path($this->item, $data_item);
					if (empty($current_backupFolder)) {
						$current_backupFolder = $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull');
					}
					$backupFile = trailingslashit(trim($current_backupFolder)) . $data_item['filename'];
					// If we don't find file is the alternate directory then try the default
					if (!file_exists($backupFile)) {
						if ( (isset($data_item['destination-directory'])) || (!empty($data_item['destination-directory'])) ){
							$current_backupFolder = $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull');
							$backupFile = trailingslashit(trim($current_backupFolder)) . $data_item['filename'];
						}
					}

					if (file_exists($backupFile)) {
						$_HAS_FILE_RESTORE = true;

						if ((isset($_GET['snapshot-action-sub']))
						 && (sanitize_text_field($_GET['snapshot-action-sub']) == "restore")
						 && (snapshot_utility_current_user_can( 'manage_snapshots_items' )) ) {
							echo '<a href="?page=snapshots_edit_panel&amp;snapshot-action=restore-panel&amp;item='.
							$this->item['timestamp'] .'&amp;snapshot-data-item='. $data_item['timestamp'] .'">'. $data_item['filename'] .'</a>';

						} else {
							echo '<a href="?page=snapshots_edit_panel&amp;snapshot-item='. $this->item['timestamp']
								.'&snapshot-data-item='. $data_item['timestamp']
								.'&snapshot-action=download-archive">'. $data_item['filename'] .'</a>';
						}

					} else {
						echo  $data_item['filename'];
					}
				}
			} else {
				if (isset($data_item['filename'])) {

					$current_backupFolder = $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull');
					$backupFile = trailingslashit(trim($current_backupFolder)) . $data_item['filename'];

					if (file_exists($backupFile)) {
						$_HAS_FILE_RESTORE = true;
						?><a href="?page=snapshots_edit_panel&amp;snapshot-item=<?php
							echo $this->item['timestamp'] ?>&amp;snapshot-data-item=<?php
							echo $data_item['timestamp'] ?>&amp;snapshot-action=download-archive"><?php
							echo $data_item['filename'] ?></a><?php

					} else {
						echo  $data_item['filename'];
					}
				}

				if ($data_item['destination-sync'] == "mirror") {
					if ($_HAS_FILE_RESTORE == true) echo "<br />";
					echo snapshot_utility_data_item_file_processed_count($data_item)  ." ". __('files synced to destination', SNAPSHOT_I18N_DOMAIN)."<br />";
				}
			}
			?>
			<div class="row-actions" style="margin:0; padding:0;">
				<span class="restore">
				<?php
				$row_actions = '';
				if ((isset($_GET['snapshot-action-sub'])) && (sanitize_text_field($_GET['snapshot-action-sub']) == "restore")) {
					if ($_HAS_FILE_RESTORE == true) {
						if (snapshot_utility_current_user_can( 'manage_snapshots_items' )) {
							if (strlen($row_actions))  $row_actions .= ' | ';
							$row_actions .= '<a href="?page=snapshots_edit_panel&amp;snapshot-action=restore-panel&amp;item='.
								$this->item['timestamp'] .'&amp;snapshot-data-item='. $data_item['timestamp'] .'">'.
								__('restore', SNAPSHOT_I18N_DOMAIN) .'</a></span>';

						}
					}
					if ($data_item['destination-sync'] == "mirror") {
						echo '<span style="color:#FF0000">' . __("File sync has no restore at this time", SNAPSHOT_I18N_DOMAIN) .'</span>';
					}
				} else {
					if ($_HAS_FILE_RESTORE == true) {
						$row_actions .= '<a href="?page=snapshots_edit_panel&amp;snapshot-item='. $this->item['timestamp']
							.'&snapshot-data-item='. $data_item['timestamp']
							.'&snapshot-action=download-archive">'. __('download', SNAPSHOT_I18N_DOMAIN) .'</a>';
					}

					if ($_HAS_FILE_RESTORE == true) {
						if (snapshot_utility_current_user_can( 'manage_snapshots_items' )) {
							if (strlen($row_actions))  $row_actions .= ' | ';
							$row_actions .= '<a href="?page=snapshots_edit_panel&amp;snapshot-action=restore-panel&amp;item='.
								$this->item['timestamp'] .'&amp;snapshot-data-item='. $data_item['timestamp'] .'">'.
								__('restore', SNAPSHOT_I18N_DOMAIN) .'</a></span>';

						}
					}

					if (snapshot_utility_current_user_can( 'manage_snapshots_items' )) {
						if (strlen($row_actions))  $row_actions .= ' | ';
						$row_actions .= '<span class="delete"><a href="?page=snapshots_edit_panel&amp;snapshot-action=item-archives&amp;item='.
							$this->item['timestamp'] .'&amp;action=delete&amp;data-item='. $data_item['timestamp'] .
							'&amp;snapshot-noonce-field='. wp_create_nonce( 'snapshot-delete-item' )
							.'">'. __('delete', SNAPSHOT_I18N_DOMAIN) .'</a></span>';
					}

					if ((isset($this->item['destination'])) && (!empty($this->item['destination'])) && ($this->item['destination'] !== "local")) {
						if (strlen($row_actions))  $row_actions .= ' | ';
						$row_actions .= '<span class="resend"><a href="?page=snapshots_edit_panel&amp;snapshot-action=item-archives&amp;item='.
							$this->item['timestamp'] .'&amp;action=resend&amp;data-item='. $data_item['timestamp'] .
							'&amp;snapshot-noonce-field='. wp_create_nonce( 'snapshot-delete-item' )
							.'">'. __('resend', SNAPSHOT_I18N_DOMAIN) .'</a></span>';
					}

				}
				if (!empty($row_actions)) echo $row_actions;

				?>
			</div>
			<?php
		}

		function column_notes($data_item) {

			$tables_sections_out 	= snapshot_utility_get_tables_sections_display($data_item);
			$files_sections_out 	= snapshot_utility_get_files_sections_display($data_item);

			if ((strlen($tables_sections_out['click'])) || (strlen($files_str))) {
				?><p><?php
				echo $tables_sections_out['click'];
				if (strlen($tables_sections_out['click'])) echo "</br />";
				echo $files_sections_out['click'];
				?></p><?php
				echo $tables_sections_out['hidden'];
			}
		}

		function column_size($data_item) {
			//echo $this->file_kb;
			if (isset($data_item['file_size'])) {
				$file_kb = snapshot_utility_size_format($data_item['file_size']);
				echo $file_kb;
			} else {
				echo "&nbsp;";
			}

		}

		function column_logs($data_item) {
			global $wpmudev_snapshot;

			$backupLogFileFull = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupLogFolderFull'))
				. $this->item['timestamp'] ."_". $data_item['timestamp'] .".log";

			if (file_exists($backupLogFileFull)) {

				echo '<a class="snapshot-thickbox"
					href="'. admin_url()
					.'admin-ajax.php?action=snapshot_view_log_ajax&amp;snapshot-item='. $this->item['timestamp'] .
					'&amp;snapshot-data-item='. $data_item['timestamp'] .'">'. __('view', SNAPSHOT_I18N_DOMAIN) .'</a>';
				echo " ";
				echo '<a href="?page=snapshots_edit_panel&amp;snapshot-action=download-log&amp;snapshot-item=' . $this->item['timestamp']
					.'&amp;snapshot-data-item='. $data_item['timestamp'] .'">'
					. __('download', SNAPSHOT_I18N_DOMAIN) .'</a>';

			} else {
				echo "&nbsp;";
			}
		}

		function column_status($data_item) {

			$status = array();
			$status['archives'] = array();
			$status['archives']['pending'] = 0;
			$status['archives']['fail'] = 0;
			$status['archives']['complete'] = 0;

			$status['destination']['pending'] = 0;
			$status['destination']['fail'] = 0;
			$status['destination']['complete'] = 0;

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

			if ($data_item['destination'] != $this->item['destination']) {
				$data_item['destination'] = $this->item['destination'];
			}

			if (($data_item['destination'] != "local") && (!empty($data_item['destination']))) {
				if (!isset($data_item['destination-status'])) {
					$status['destination']['pending'] += 1;
				} else {
					$status_item = snapshot_utility_latest_data_item($data_item['destination-status']);
					if (!$status_item) {
						$status['destination']['pending'] += 1;
					} else if ((isset($status_item['sendFileStatus'])) && ($status_item['sendFileStatus'] === true)) {
						$status['destination']['complete'] += 1;
					} else if ($status_item['errorStatus'] === true) {
						$status['destination']['fail'] += 1;
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
						$status_output .= __('Pending', SNAPSHOT_I18N_DOMAIN);
						break;

					case 'fail':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= __('Fail', SNAPSHOT_I18N_DOMAIN);
						break;

					case 'complete':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= __('Complete', SNAPSHOT_I18N_DOMAIN);
						break;
				}
			}

			if (strlen($status_output))
				echo __('Archives:', SNAPSHOT_I18N_DOMAIN) ." ".$status_output ."<br />";
			else
				echo __('Archives:', SNAPSHOT_I18N_DOMAIN) ." ".$status_output ."<br />";
			//echo "status_output=[". $status_output ."]<br />";


			$status_output = '';
			foreach($status['destination'] as $_key => $_count) {
				if ($_count == 0) continue;

				switch($_key) {
					case 'pending':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= __('Pending', SNAPSHOT_I18N_DOMAIN);
						break;

					case 'fail':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= __('Fail', SNAPSHOT_I18N_DOMAIN);
						break;

					case 'complete':
						if (strlen($status_output)) $status_output .= ', ';
						$status_output .= __('Complete', SNAPSHOT_I18N_DOMAIN);
						break;
				}
			}

			if (strlen($status_output))
				echo __('Destination:', SNAPSHOT_I18N_DOMAIN) ." ".$status_output ."<br />";
			//else
			//	echo __('Archives:', SNAPSHOT_I18N_DOMAIN) ." ".$status_output ."<br />";
			//echo "status_output=[". $status_output ."]<br />";
		}

	    function get_columns() {

			$columns = array();

			$columns['cb'] 		= 	'<input type="checkbox" />';
			$columns['file']	=	__('File', 		SNAPSHOT_I18N_DOMAIN);
			$columns['date']	= 	__('Date', 		SNAPSHOT_I18N_DOMAIN);
			$columns['notes']	= 	__('Notes', 	SNAPSHOT_I18N_DOMAIN);
			$columns['size']  	= 	__('Size', 		SNAPSHOT_I18N_DOMAIN);
			$columns['logs']  	= 	__('Logs', 		SNAPSHOT_I18N_DOMAIN);
			$columns['status']  = 	__('Status', 	SNAPSHOT_I18N_DOMAIN);

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
			$args = $this->_args;
			extract( $args );

			if ((isset($_GET['snapshot-action-sub'])) && (sanitize_text_field($_GET['snapshot-action-sub']) == "restore")) {
				?><p><?php _e('Select the snapshot item to restore', SNAPSHOT_I18N_DOMAIN); ?></p><?php
			} else {
				$this->display_tablenav( 'top' );
			}
			?>
			<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>" cellspacing="0">
			<thead>
			<tr>
				<?php $this->print_column_headers(); ?>
			</tr>
			</thead>

			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>

			<tbody id="the-list"<?php if ( $singular ) echo " class='list:$singular'"; ?>>
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>
			</table>
			<?php
			if ((isset($_GET['snapshot-action-sub'])) && (sanitize_text_field($_GET['snapshot-action-sub']) == "restore")) {
			} else {
				$this->display_tablenav( 'bottom' );
			}
		}


	    function prepare_items($item = array()) {

			$this->item = $item;

	        $columns 	= $this->get_columns();
			$hidden 	= $this->get_hidden_columns();
	        $sortable 	= $this->get_sortable_columns();

	        $this->_column_headers = array($columns, $hidden, $sortable);

			if ((isset($item['data'])) && (count($item['data']))) {

				$data_items = $item['data'];
				krsort($data_items);

				$per_page = get_user_meta(get_current_user_id(), 'snapshot_data_items_per_page', true);
				if ((!$per_page) || ($per_page < 1)) {
					$per_page = 20;
				}

				$current_page = $this->get_pagenum();

				if (count($data_items) > $per_page) {
					$this->items = array_slice($data_items, (($current_page - 1) * intval($per_page)), intval($per_page), true);
				} else {
					$this->items = $data_items;
				}

				$this->set_pagination_args( array(
					'total_items' => count($item['data']),                  			// WE have to calculate the total number of items
					'per_page'    => intval($per_page),                     			// WE have to determine how many items to show on a page
					'total_pages' => ceil(intval(count($item['data'])) / intval($per_page))   	// WE have to calculate the total number of pages
					)
				);
			}
	    }
	}
}