<?php
if ( !class_exists( "SnapshotDestinationBase" ) ) {
	class SnapshotDestinationBase {

		// This is a reference to the main Snapshot instance. So the destinations don't need to rely on global
		var $wpmudev_snapshot;

		function __construct() {
    		global $wpmudev_snapshot;

			$this->wpmudev_snapshot = $wpmudev_snapshot;

			//run plugin construct
			$this->on_creation();

			if ((empty($this->name_slug)) || (empty($this->name_display)))
				wp_die( __("You must override all required vars in your Snapshot Destination class!", SNAPSHOT_I18N_DOMAIN) );

		}

		function SnapshotDestinationBase() {
			$this->__construct();
		}
		function display_listing_table($destinations, $edit_url, $delete_url) {
			wp_die( __("You must override the function 'display_listing_table' in your Snapshot Destination class!", SNAPSHOT_I18N_DOMAIN) );
		}

		function sendfile_to_remote($destination_info, $filename) {
			wp_die( __("You must override the function 'sendfile_to_remote' in your Snapshot Destination class!", SNAPSHOT_I18N_DOMAIN) );
		}

		function display_details_form($item=0) {
			wp_die( __("You must override the function 'display_details_form' in your Snapshot Destination class!", SNAPSHOT_I18N_DOMAIN) );
		}
	}
}

function snapshot_destination_loader() {

    $dir = dirname(__FILE__) . '/destinations';

	if (!defined('WPMUDEV_SNAPSHOT_DESTINATIONS_EXCLUDE'))
		define('WPMUDEV_SNAPSHOT_DESTINATIONS_EXCLUDE', '');

    //search the dir for files
    $spanshot_destination_files = array();
  	if ( !is_dir( $dir ) ) {
		return;
	}

  	if ( ! $dh = opendir( $dir ) ) {
		return;
	}

  	while ( ( $plugin = readdir( $dh ) ) !== false ) {
		if ($plugin[0] == '.') continue;
		if ($plugin[0] == '_') continue;	// Ignore this starting with underscore

		$_destination_dir = $dir . '/' . $plugin;
		if (is_dir($_destination_dir)) {
			$_destination_dir_file = $_destination_dir ."/index.php";
			if (is_file($_destination_dir_file)) {
  				$spanshot_destination_files[] = $_destination_dir_file;
			}
		}
  	}
  	closedir( $dh );

	//echo "spanshot_destination_files<pre>"; print_r($spanshot_destination_files); echo "</pre>";
	if (($spanshot_destination_files) && (count($spanshot_destination_files))) {
	  	sort( $spanshot_destination_files );

		foreach ($spanshot_destination_files as $file) {
			//echo "file=[". $file ."]<br />";
			include( $file );
		}
	}
	do_action('snapshot_destinations_loaded');
}

function snapshot_destination_get_object_from_type($type) {
	global $wpmudev_snapshot;

	$destinationClasses = $wpmudev_snapshot->snapshot_get_setting('destinationClasses');
	if (isset($destinationClasses[$type]))
		return $destinationClasses[$type];
}

function snapshot_destination_listing_panel() {
	//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
	if ((isset($_REQUEST['snapshot-action']))
	 && ((sanitize_text_field($_REQUEST['snapshot-action']) == 'add')
	  || (sanitize_text_field($_REQUEST['snapshot-action']) == 'edit')
	  || (sanitize_text_field($_REQUEST['snapshot-action']) == 'update'))  )
	{
		snapshot_destination_edit_panel();
	} else {
		global $wpmudev_snapshot;
		?>
		<div id="snapshot-edit-destinations-panel" class="wrap snapshot-wrap">
			<?php screen_icon('snapshot'); ?>
			<h2><?php _ex("All Snapshot Destinations", "Snapshot Destination Page Title", SNAPSHOT_I18N_DOMAIN); ?> </h2>
			<p><?php _ex("This page show all the destinations available for the Snapshot plugin. A destination is a remote system like Amazon S3, Dropbox or SFTP. Simply select the destination type from the drop down then will in the details. When you add or edit a Snapshot you will be able to assign it a destination. When the snapshot backup runs the archive file will be sent to the destination instead of stored locally.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
			<?php
			if (session_id() == "")
		  		@session_start();

//			echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
//			echo "_SESSION<pre>"; print_r($_SESSION); echo "</pre>";


				$destinations = array();
				foreach($wpmudev_snapshot->config_data['destinations'] as $key => $item) {

					$type = $item['type'];
					if (!isset($destinations[$type]))
						$destinations[$type] = array();

					$destinations[$type][$key] = $item;
				}

				$destinationClasses = $wpmudev_snapshot->snapshot_get_setting('destinationClasses');
				if (($destinationClasses) && (count($destinationClasses))) {
					ksort($destinationClasses);

					foreach($destinationClasses as $classObject) {
						//echo "classObject<pre>"; print_r($classObject); echo "</pre>";
						?>
						<h3 style="float:left;"><?php echo $classObject->name_display; ?> <?php if (current_user_can( 'manage_snapshots_destinations' )) {
							?><a class="add-new-h2" style="top:0;"
							href="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL');
							 ?>snapshots_destinations_panel&amp;snapshot-action=add&amp;type=<?php echo $classObject->name_slug; ?>">Add New</a><?php } ?></h3>
							<?php if ((isset($classObject->name_logo)) && (strlen($classObject->name_logo))) {
								?><img style="float: right; height: 40px;" src="<?php echo $classObject->name_logo; ?>"
									alt="<?php $classObject->name_display; ?>" /><?php
								} ?>
						<form id="snapshot-edit-destination-<?php echo $classObject->name_slug; ?>" action="<?php
							echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_destinations_panel" method="post">
							<input type="hidden" name="snapshot-action" value="delete-bulk" />
							<input type="hidden" name="snapshot-destination-type" value="<?php echo $classObject->name_slug; ?>" />
							<?php wp_nonce_field('snapshot-delete-destination-bulk-'. $classObject->name_slug,
								'snapshot-noonce-field-'. $classObject->name_slug); ?>
							<?php
								$edit_url 	= $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL')
									.'snapshots_destinations_panel&amp;snapshot-action=edit&amp;type='. $classObject->name_slug .'&amp;';
								$delete_url = $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL')
								 	.'snapshots_destinations_panel&amp;snapshot-action=delete&amp;';

								if (isset($destinations[$classObject->name_slug]))
									$destination_items = $destinations[$classObject->name_slug];
								else
									$destination_items = array();

								$classObject->display_listing_table($destination_items, $edit_url, $delete_url);
							?>
						</form>
						<?php
					}
				}

			?>
		</div>
		<?php
	}
}

function snapshot_destination_edit_panel() {
	global $wpmudev_snapshot;
	?>
	<div id="snapshot-metaboxes-destination_add" class="wrap snapshot-wrap">
		<?php screen_icon('snapshot'); ?>

		<?php
			$item = 0;
			if (isset($_REQUEST['snapshot-action'])) {

				if (sanitize_text_field($_REQUEST['snapshot-action']) == "edit") {

					?>
					<h2><?php _ex("Edit Snapshot Destination", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
					<p><?php _ex("", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
					<?php
					if (isset($_REQUEST['item'])) {
						$item_key = sanitize_text_field($_REQUEST['item']);
						if (isset($wpmudev_snapshot->config_data['destinations'][$item_key])) {
							$item = $wpmudev_snapshot->config_data['destinations'][$item_key];
						}
					}
				} else if (sanitize_text_field($_REQUEST['snapshot-action']) == "add") {
					?>
					<h2><?php _ex("Add Snapshot Destination", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
					<p><?php _ex("", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
					<?php
					unset($item);
					$item = array();

					if (isset($_REQUEST['type'])) {
						$item['type'] = sanitize_text_field($_REQUEST['type']);
					}
				} else if (sanitize_text_field($_REQUEST['snapshot-action']) == "update") {

					?>
					<h2><?php _ex("Edit Snapshot Destination", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
					<p><?php _ex("", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
					<?php
					if (isset($_POST['snapshot-destination'])) {
						$item = $_POST['snapshot-destination'];
					}
				}

			}
			if ($item) {
				snapshot_utility_form_ajax_panels();
				?>
				<form action="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_destinations_panel&amp;snapshot-action=<?php echo urlencode(sanitize_text_field($_GET['snapshot-action'])); ?>&amp;type=<?php echo urlencode($item['type']); ?>" method="post">
					<?php
						if ((sanitize_text_field($_GET['snapshot-action']) == "edit") || (sanitize_text_field($_GET['snapshot-action']) == "update")) {
							?>
							<input type="hidden" name="snapshot-action" value="update" />
							<input type="hidden" name="item" value="<?php echo sanitize_text_field($_GET['item']); ?>" />
							<?php wp_nonce_field('snapshot-update-destination', 'snapshot-noonce-field'); ?>
							<?php
						} else if (sanitize_text_field($_GET['snapshot-action']) == "add") {
							?>
							<input type="hidden" name="snapshot-action" value="add" />
							<?php wp_nonce_field('snapshot-add-destination', 'snapshot-noonce-field'); ?>
							<?php
						}
						$item_object = snapshot_destination_get_object_from_type($item['type']);
						if (($item_object) && (is_object($item_object))) {
							$item_object->display_details_form($item);
						}
					?>
						<input class="button-primary" type="submit" value="<?php _e('Save Destination', SNAPSHOT_I18N_DOMAIN); ?>" />
						<a class="button-secondary" href="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL');
						 	?>snapshots_destinations_panel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></a>

					</div>
				</form>
				<?php
			}
		?>
	</div>
	<?php
}