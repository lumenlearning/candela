<?php
if ( !class_exists( "wpmudev_snapshot_admin_panels" ) ) {
	class wpmudev_snapshot_admin_panels {

		/**
		 * The PHP5 Class constructor. Used when an instance of this class is needed.
		 * Sets up the initial object environment and hooks into the various WordPress
		 * actions and filters.
		 *
		 * @since 1.0.0
		 * @uses $this->_settings array of our settings
		 * @uses $this->_messages array of admin header message texts.
		 * @param none
		 * @return self
		 */
		function __construct() {
		}

		/**
		 * The old-style PHP Class constructor. Used when an instance of this class
	 	 * is needed. If used (PHP4) this function calls the PHP5 version of the constructor.
		 *
		 * @since 1.0.2
		 * @param none
		 * @return self
		 */
		function wpmudev_snapshot_admin_panels( ) {
	        $this->__construct();
		}

		/**
		 * Panel showing form for adding new Snapshots.
		 *
		 * @since 1.0.2
		 * @uses setup in $this->admin_menu_proc()
		 * @uses $wpdb
		 *
		 * @param none
		 * @return none
		 */

		function snapshot_admin_show_add_panel() {

			global $wpdb, $wpmudev_snapshot;

			//echo "wpmudev_snapshot<pre>"; print_r($wpmudev_snapshot); echo "</pre>";
			require( $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_PLUGIN_BASE_DIR'). '/lib/snapshot_admin_metaboxes.php' );
			$_snapshot_metaboxes = new wpmudev_snapshot_admin_metaboxes( );

			$time_key = time();
			while(true) {
				if (!isset($wpmudev_snapshot->config_data['items'][$time_key]))
					break;

				$time_key = time();
			}
			?>
			<div id="snapshot-new-panel" class="wrap snapshot-wrap">
				<?php screen_icon('snapshot'); ?>
				<h2><?php _ex("Add New Snapshot", "Snapshot New Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
				<p><?php _ex("Use this form to create a new snapshot of your site. Fill in the optional Name and Notes fields. Select the tables to be included in this snapshot.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

				<?php
					if (!snapshot_utility_check_server_timeout()) {
						$current_timeout = ini_get('max_execution_time');
						?><div class='error snapshot-error'><p><?php printf(__('Your web server timeout is set very low, %d seconds. Also, it appears this timeout cannot be adjusted via the Snapshot backup process. Attempting a snapshot backup could result in a partial backup of your tables.',
						SNAPSHOT_I18N_DOMAIN), $current_timeout); ?></p></div><?php
					}
				?>
				<div id="snapshot-timout-update-panel"></div>

				<?php snapshot_utility_form_ajax_panels(); ?>

				<div id="poststuff" class="metabox-holder">
					<form id="snapshot-add-update" method="post"
						action="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_new_panel">
						<input type="hidden" id="snapshot-action" name="snapshot-action" value="add" />
						<input type="hidden" id="snapshot-item" name="snapshot-item" value="<?php echo $time_key; ?>" />
						<input type="hidden" id="snapshot-data-item" name="snapshot-data-item" value="<?php echo $time_key; ?>" />

						<?php wp_nonce_field('snapshot-add', 'snapshot-noonce-field'); ?>

						<?php $_snapshot_metaboxes->snapshot_metaboxes_show_item_header_information(
								__('Snapshot Information', SNAPSHOT_I18N_DOMAIN), null); ?>
						<?php
							$_snapshot_metaboxes->snapshot_metabox_show_backup_files_options(
							__('What Files to Archive?', SNAPSHOT_I18N_DOMAIN), null);
						?>
						<?php $_snapshot_metaboxes->snapshot_metabox_show_backup_tables_options(
								__('What Tables to Archive', SNAPSHOT_I18N_DOMAIN), null);
						?>
						<?php $_snapshot_metaboxes->snapshot_metabox_show_schedule_options(
							__('When to Archive', SNAPSHOT_I18N_DOMAIN), null);
						?>
						<?php $_snapshot_metaboxes->snapshot_metabox_show_destination_options(
								__('Where to save the Archive ', SNAPSHOT_I18N_DOMAIN), null);
						?>

						<input id="snapshot-add-button" class="button-primary" type="submit"
							value="<?php _e('Create Snapshot', SNAPSHOT_I18N_DOMAIN); ?>" />
					</form>
				</div>
			</div>
			<?php
		}

		/**
		 * Panel showing the table listing of all Snapshots.
		 *
		 * @since 1.0.0
		 * @uses setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items'] to build output
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_admin_show_items_panel() {
			global $wpmudev_snapshot;

			// If the user has clicked the link to edit a snapshot item show the edit form...
			if (isset($_REQUEST['item']))
				$item_key = intval($_REQUEST['item']);

			if (isset($item_key)) {
				$item = $wpmudev_snapshot->snapshot_get_edit_item($item_key);
				//echo "item<pre>"; print_r($item); echo "</pre>";
				//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";

				if (($item) && (isset($_REQUEST['snapshot-action'])) && (sanitize_text_field($_REQUEST['snapshot-action']) == 'edit')) {
					$this->snapshot_admin_show_edit_panel($item);
				} else if (($item) && (isset($_REQUEST['snapshot-action'])) && (sanitize_text_field($_REQUEST['snapshot-action']) == 'restore-panel')) {
					// ...or if the user clicked the button to show the restore form. Show it.
					$this->snapshot_admin_show_restore_panel($item);
				} else if (($item) && (isset($_REQUEST['snapshot-action'])) && (sanitize_text_field($_REQUEST['snapshot-action']) == 'item-archives')) {
					$this->snapshot_admin_show_item_archive_panel($item);
				} else {
					$this->snapshot_admin_show_listing();
				}
			} else {
				$this->snapshot_admin_show_listing();
			}
		}

		/**
		 * Panel showing the table listing of all Snapshots.
		 *
		 * @since 1.0.0
		 * @uses setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items'] to build output
		 *
		 * @param none
		 * @return none
		 */

		function snapshot_admin_show_listing() {
			global $wpmudev_snapshot;

			$config_data = $wpmudev_snapshot->config_data;
			$wpmudev_snapshot->items_table->prepare_items($wpmudev_snapshot->config_data['items']);
			?>
			<div id="snapshot-edit-listing-panel" class="wrap snapshot-wrap">
				<?php screen_icon('snapshot'); ?>
				<h2><?php _ex("All Snapshots", "Snapshot New Page Title", SNAPSHOT_I18N_DOMAIN); ?> <?php if (current_user_can( 'manage_snapshots_items' )) {
					?><a class="add-new-h2" href="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_new_panel">Add New</a><?php
					} ?></h2>
				<p><?php _ex("This is a listing of all Snapshots created within your site. To delete a snapshot set the checkbox then click the 'Delete Snapshots' button below the listing. To restore a snapshot click the 'Restore' button for that snapshot. To edit a snapshot click the name of the snapshot", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

				<div style="float: right" class="snapshot-system-time">
				<?php echo __('Current time:', SNAPSHOT_I18N_DOMAIN) .' <strong>'. snapshot_utility_show_date_time(time()) .'</strong><br />'; ?>
				<?php
					$timestamp = wp_next_scheduled( $wpmudev_snapshot->snapshot_get_setting( 'remote_file_cron_hook' ));
					if ($timestamp) {
						echo __('Next File Send:', SNAPSHOT_I18N_DOMAIN) .' <strong>'. snapshot_utility_show_date_time($timestamp) .'</strong>';
					}
				?><br />
				<?php _e('<span class="snapshot-error">(I)</span> indicates an imported archive', SNAPSHOT_I18N_DOMAIN); ?>
				</div>

				<?php //snapshot_utility_show_panel_messages(); ?>

				<form id="snapshot-edit-listing" action="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_edit_panel"
					 method="post">
					<input type="hidden" name="snapshot-action" value="delete-bulk" />
					<?php wp_nonce_field('snapshot-delete', 'snapshot-noonce-field'); ?>
					<?php $wpmudev_snapshot->items_table->display(); ?>
				</form>
			</div>
			<?php
		}


		/**
		 * Metabox showing form for editing previous Snapshots.
		 *
		 * @since 1.0.2
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $_REQUEST['item']
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_admin_show_edit_panel($item) {
			global $wpmudev_snapshot;

			$data_time_key = time();
			while(true) {
				if (!isset($wpmudev_snapshot->config_data['items']['data'][$data_time_key]))
					break;

				$data_time_key = time();
			}

			require( $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_PLUGIN_BASE_DIR'). '/lib/snapshot_admin_metaboxes.php' );
			$_snapshot_metaboxes = new wpmudev_snapshot_admin_metaboxes( );

			?>
			<div id="snapshot-settings-metaboxes-general" class="wrap snapshot-wrap">
				<?php screen_icon('snapshot'); ?>
				<h2><?php _ex("Edit Snapshot", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
				<p><?php _ex("Use this form to update the details for a previous snapshot. Also, provided is a link you can use to download the snapshot for sharing or archiving.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
				<?php
					$SNAPSHOT_FILE_MISSING = false;
				?>
				<?php snapshot_utility_form_ajax_panels(); ?>

				<div id="poststuff" class="metabox-holder">

					<form id="snapshot-add-update" method="post"
						action="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_edit_panel">
						<input type="hidden" id="snapshot-action" name="snapshot-action" value="update" />
						<input type="hidden" id="snapshot-item" name="snapshot-item" value="<?php echo $item['timestamp']; ?>" />
						<input type="hidden" id="snapshot-data-item" name="snapshot-data-item" value="<?php echo $data_time_key; ?>" />

						<?php wp_nonce_field('snapshot-update', 'snapshot-noonce-field'); ?>

						<?php //echo "item<pre>"; print_r($item); echo "</pre>"; ?>

						<?php
							$_snapshot_metaboxes->snapshot_metaboxes_show_item_header_information(
								__('Snapshot Information', SNAPSHOT_I18N_DOMAIN), $item ); ?>

						<?php
							$_snapshot_metaboxes->snapshot_metabox_show_backup_files_options(
								__('What Files to Archive?', SNAPSHOT_I18N_DOMAIN), $item);
						?>
						<?php
							$_snapshot_metaboxes->snapshot_metabox_show_backup_tables_options(
								__('What Tables to Archive', SNAPSHOT_I18N_DOMAIN), $item);
						?>
						<?php
							$_snapshot_metaboxes->snapshot_metabox_show_schedule_options(
								__('When to Archive', SNAPSHOT_I18N_DOMAIN), $item);
						?>
						<?php
							$_snapshot_metaboxes->snapshot_metabox_show_destination_options(
								__('Where to save the Archive ', SNAPSHOT_I18N_DOMAIN), $item);
						?>

						<?php
							$_snapshot_metaboxes->snapshot_metabox_show_archive_files(
								__('All Archives', SNAPSHOT_I18N_DOMAIN), $item );
						?>

						<input class="button-primary" id="snapshot-form-save-submit" type="submit" value="<?php _e('Save Snapshot', SNAPSHOT_I18N_DOMAIN); ?>" />
						<a class="button-secondary" href="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL');
						 	?>snapshots_edit_panel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></a>

					</form>
				</div>

			</div>
			<?php
		}

		/**
		 * Panel showing form to restore previous Snapshot.
		 *
		 * @since 1.0.2
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $_REQUEST['item']
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_admin_show_restore_panel($item) {
			global $wpmudev_snapshot;

			require( $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_PLUGIN_BASE_DIR'). '/lib/snapshot_admin_metaboxes.php' );
			$this->_snapshot_metaboxes = new wpmudev_snapshot_admin_metaboxes( );

			if ((isset($_GET['snapshot-data-item'])) && (isset($item['data'][intval($_GET['snapshot-data-item'])]))) {
				$data_item_key = intval($_GET['snapshot-data-item']);
				?>
				<div id="snapshot-settings-metaboxes-general" class="wrap snapshot-wrap">
					<?php screen_icon('snapshot'); ?>
					<h2><?php _ex("Restore Snapshot", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>

					<p class="snapshot-restore-description"><?php _ex("On this page you can restore a previous snapshot. Using the 'Restore Options' section below you can also opt to turn off all plugins as well as switch to a different theme as part of the restore.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

					<div id='snapshot-ajax-warning' class='updated fade'><p><?php _e('You are about to restore a previous version of your WordPress database. This will remove any new information added since the snapshot backup.', SNAPSHOT_I18N_DOMAIN); ?></p></div>

					<?php
						if (!snapshot_utility_check_server_timeout()) {
							$current_timeout = ini_get('max_execution_time');
							?><div class='error snapshot-error'><p><?php printf(__('Your web server timeout is set very low, %d seconds. Also, it appears this timeout cannot be adjusted via the Snapshot restore process. Attempting a snapshot restore could result in a partial restore of your tables.', SNAPSHOT_I18N_DOMAIN), $current_timeout); ?></p></div><?php
						}
					?>
					<?php snapshot_utility_form_ajax_panels(); ?>
					<?php
						if (isset($_GET['snapshot-data-item'])) {
							$data_item = $item['data'][$_GET['snapshot-data-item']];
						}

						$backupFolder = $wpmudev_snapshot->snapshot_get_item_destination_path($item, $data_item);
						if (empty($backupFolder)) {
							$backupFolder = $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull');
						}

						if ((isset($data_item['filename'])) && (strlen($data_item['filename']))) {
							$manifest_filename = snapshot_utility_extract_archive_manifest(trailingslashit($backupFolder) . $data_item['filename']);
							if ($manifest_filename) {
								//echo "manifest_filename=[". $manifest_filename ."]<br />";
								$manifest_data = snapshot_utility_consume_archive_manifest($manifest_filename);
								if ($manifest_data) {
									//echo "manifest_data<pre>"; print_r($manifest_data); echo "</pre>";
									$item['MANIFEST'] = $manifest_data;
								}
							}
						}

					?>


					<div id="poststuff" class="metabox-holder">

						<form id="snapshot-edit-restore" action="<?php
							echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_edit_panel" method="post">
							<input type="hidden" name="snapshot-action" value="restore-request" />
							<input type="hidden" name="item" value="<?php echo $item['timestamp']; ?>" />
							<?php wp_nonce_field('snapshot-restore', 'snapshot-noonce-field'); ?>

							<?php $this->_snapshot_metaboxes->snapshot_metaboxes_show_item_header_information(
								__('Snapshot Information', SNAPSHOT_I18N_DOMAIN), $item, true ); ?>

							<?php
								$this->_snapshot_metaboxes->snapshot_metabox_show_archive_files(
									__('Selected Archive to Restore', SNAPSHOT_I18N_DOMAIN), $item, true );
							?>
							<?php
								//if (is_multisite()) {
									$this->_snapshot_metaboxes->snapshot_metabox_restore_blog_options(
										__('Restore Blog Options', SNAPSHOT_I18N_DOMAIN), $item );
								//}
							?>
							<?php
								$this->_snapshot_metaboxes->snapshot_metabox_show_restore_tables_options(
									__('What Tables to Restore?', SNAPSHOT_I18N_DOMAIN), $item, $data_item_key);
							?>
							<?php
								$this->_snapshot_metaboxes->snapshot_metabox_show_restore_files_options(
									__('What Files to Restore?', SNAPSHOT_I18N_DOMAIN), $item, $data_item_key);

							?>
							<?php
								$this->_snapshot_metaboxes->snapshot_metabox_restore_options( __('Restore Theme Options', SNAPSHOT_I18N_DOMAIN), $item );
							?>
							<input id="snapshot-form-restore-submit" class="button-primary"
							<?php
								if (!$data_item_key) {	?> disabled="disabled" <?php } ?>
								type="submit" value="<?php _e('Restore Snapshot', SNAPSHOT_I18N_DOMAIN); ?>" />
							<a class="button-secondary" href="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>
									snapshots_edit_panel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></a>
						</form>
					</div>
				</div>
				<?php
			} else {
				?>
				<div id="snapshot-settings-metaboxes-general" class="wrap snapshot-wrap">
					<?php screen_icon('snapshot'); ?>
					<h2><?php _ex("Restore Snapshot", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>

					<p class="snapshot-restore-description"><?php _ex("ERROR: Missing argument. Please return to the main Snapshot panel and select the archive to restore. ", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?><a href="?page=snapshots_edit_panel">Snapshot</a>.</p>
				</div>
				<?php
			}
		}



		/**
		 * Metabox showing form for Settings.
		 *
		 * @since 1.0.2
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $_REQUEST['item']
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_admin_show_settings_panel() {
			global $wpmudev_snapshot;

			?>
			<div id="snapshot-settings-metaboxes-general" class="wrap snapshot-wrap">
				<?php screen_icon('snapshot'); ?>
				<h2><?php _ex("Snapshots Settings", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
				<p><?php _ex("The Settings panel provides access to a number of configuration options you can customize Snapshot to meet you site needs.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

				<div id="poststuff" class="metabox-holder">
					<div id="post-body" class="">
						<div id="post-body-content" class="snapshot-metabox-holder-main">
							<?php do_meta_boxes($wpmudev_snapshot->snapshot_get_pagehook('snapshots-settings'), 'normal', ''); ?>
						</div>
					</div>
				</div>
				<script type="text/javascript">
					//<![CDATA[
					jQuery(document).ready( function($) {
						// close postboxes that should be closed
						$('.if-js-closed').removeClass('if-js-closed').addClass('closed');

						// postboxes setup
						postboxes.add_postbox_toggles('<?php echo $wpmudev_snapshot->snapshot_get_pagehook('snapshots-settings'); ?>');
					});
					//]]>
				</script>
			</div>
			<?php
		}

		function snapshot_admin_show_item_archive_panel($item) {
			global $wpmudev_snapshot;

			$wpmudev_snapshot->archives_data_items_table->prepare_items($item);
			?>
		    <div class="wrap snapshot-wrap">

				<?php screen_icon('snapshot'); ?>
		        <h2>Snapshot Item Archive <a class="add-new-h2" href="<?php
					echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_edit_panel&amp;snapshot-action=edit&amp;item=<?php echo $item['timestamp']; ?>">Edit Item</a></h2>
				<?php //echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>"; ?>
		        <form id="snapshot-item-archives-form" method="get">
					<input type="hidden" name="snapshot-action" value="item-archives" />
					<input type="hidden" name="page" value="snapshots_edit_panel" />
					<input type="hidden" name="item" value="<?php echo $item['timestamp']; ?>" />

		            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
		            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />


					<div style="float: right" class="snapshot-system-time"><?php _e('Current time:', SNAPSHOT_I18N_DOMAIN); ?> <strong><?php
						echo snapshot_utility_show_date_time(time())?></strong></div>

					<?php if ($item['destination-sync'] == "mirror") {
						?><p><?php _e('This Snapshot item is setup as <strong>files sync</strong>. You cannot perform a resend of the individual items like on a normal archive. But you can click the <strong>resend</strong> on any item below to clear the last send dates on all files. This will force all files to be re-synced.', SNAPSHOT_I18N_DOMAIN); ?></p><?php
					}
					?>
		            <!-- Now we can render the completed list table -->
		            <?php 	$wpmudev_snapshot->archives_data_items_table->display(); ?>
		        </form>
		    </div>
			<?php
		}

		function snapshot_admin_show_import_panel() {
			global $wpmudev_snapshot;
			require( $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_PLUGIN_BASE_DIR'). '/lib/snapshot_admin_metaboxes.php' );
			$_snapshot_metaboxes = new wpmudev_snapshot_admin_metaboxes( );

			?>
			<div id="snapshot-settings-metaboxes-general" class="wrap snapshot-wrap">
				<?php screen_icon('snapshot'); ?>
				<h2><?php _ex("Snapshots Scan / Import", "Snapshot Plugin Page Title", SNAPSHOT_I18N_DOMAIN); ?></h2>
				<div id="poststuff" class="metabox-holder">
					<div id="post-body" class="">
						<div id="post-body-content" class="snapshot-metabox-holder-main">
							<p><?php _e('The Snapshot import form below is used to import snapshot archives from outside of this environment into view of the snapshot plugin. If you are attempting to restore an archive from a remote server for example you first need to import the archive here. This will then show the archive in the All Snapshots listing. From that page you can then perform the needed restore.', SNAPSHOT_I18N_DOMAIN); ?></p>
							<form  action="<?php echo $wpmudev_snapshot->snapshot_get_setting('SNAPSHOT_MENU_URL'); ?>snapshots_import_panel" method="post">
								<input type="hidden" value="archives-import" name="snapshot-action">
								<?php wp_nonce_field('snapshot-import', 'snapshot-noonce-field'); ?>

								<?php $_snapshot_metaboxes->snapshot_metaboxes_show_import(
									__('Import Options', SNAPSHOT_I18N_DOMAIN) ); ?>

								<input id="snapshot-add-button" class="button-primary" type="submit"
									value="<?php _e('Scan / Import Snapshots', SNAPSHOT_I18N_DOMAIN); ?>" />
							</form>
						</div>
					</div>
					<?php
						//echo "_POST<pre>"; print_r($_POST); echo "</pre>";
						//echo "_GET<pre>"; print_r($_GET); echo "</pre>";

						if ((isset($_REQUEST['snapshot-action'])) && (esc_attr($_REQUEST['snapshot-action']) == "archives-import")) {
							if ( wp_verify_nonce($_POST['snapshot-noonce-field'],'snapshot-import') ) {

								if ((isset($_POST['snapshot-import-archive-remote-url'])) && (!empty($_POST['snapshot-import-archive-remote-url']))) {
									//echo "remote url[". $_POST['snapshot-import-archive-remote-url'] ."]<br />";
									if (substr($_POST['snapshot-import-archive-remote-url'], 0, 4) == 'http') {

										if (function_exists('curl_version')) {

											$remoteFile = esc_url($_POST['snapshot-import-archive-remote-url']);

											@set_time_limit(900); // 15 minutes Technically, server to server should be quick for large files.

											?><p><?php _e("PHP max_execution_time", SNAPSHOT_I18N_DOMAIN); ?>: <?php echo ini_get('max_execution_time'); ?>s</p><?php

											?><p><?php _e("Attempting to download remote file", SNAPSHOT_I18N_DOMAIN); ?>: <?php echo $remoteFile; ?></p><?php
											flush();

											$restoreFile = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull')) . basename($remoteFile);
											//echo "remoteFile=[". $remoteFile ."]<br />";
											//echo "restoreFile=[". $restoreFile ."]<br />";

											snapshot_utility_remote_url_to_local_file($remoteFile, $restoreFile);
											if (file_exists($restoreFile)) {

												$restoreFolder = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupRestoreFolderFull')) ."_imports";

												echo "<ol>";
												echo "<li><strong>". __('Processing archive', SNAPSHOT_I18N_DOMAIN). ": ", basename($restoreFile) ."</strong> (".
												 snapshot_utility_size_format(filesize($restoreFile)) .")<ul><li>";
												flush();
												$error_status = snapshot_utility_archives_import_proc($restoreFile, $restoreFolder);
												//echo "error_status<pre>"; print_r($error_status); echo "</pre>";
												if ( (isset($error_status['errorStatus'])) && ($error_status['errorStatus'] === true) ) {
													if ((isset($error_status['errorText'])) && (strlen($error_status['errorText']))) {
														echo  '<span class="snapshot-error">Error: '. $error_status['errorText'] .'</span></br />';
													}
												} else if ((isset($error_status['errorStatus'])) && ($error_status['errorStatus'] === false)) {
													if ((isset($error_status['responseText'])) && (strlen($error_status['responseText']))) {
														echo '<span class="snapshot-success">Success: '. $error_status['responseText'] .'</span></br />';
													} else {

													}
												}
												echo "</li></ul></li>";
												echo "</ol>";
											} else {
												echo '<p>' .__('Error: Your server does not have lib_curl installed. So the import process cannot retrieve remote file.', SNAPSHOT_I18N_DOMAIN) .'</p>';
											}
										} else {
											echo "<p>". __('local import file not found. This could mean either the entered URL was not valid or the file was not publicly accessible.', SNAPSHOT_I18N_DOMAIN) ."</p>";
										}
									} else {
										// Then a local directory

										// Are we dealing with a absolote path...
										if (substr($_POST['snapshot-import-archive-remote-url'], 0, 1) == "/") {
											$dir = trailingslashit(esc_attr($_POST['snapshot-import-archive-remote-url']));
										} else {
											$dir = trailingslashit(trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull')) . esc_attr($_POST['snapshot-import-archive-remote-url']));
										}
										//echo "dir[". $dir ."]<br />";
										if (is_dir($dir)) {
											echo "<p>". __('Importing archives from', SNAPSHOT_I18N_DOMAIN). ": ". $dir ."</p>";

											if ($dh = opendir($dir)) {
												$restoreFolder = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupRestoreFolderFull')) ."_imports";

												echo "<ol>";
												while (($file = readdir($dh)) !== false) {

													if (($file == '.') || ($file == '..') || ($file == 'index.php') || ($file[0] == '.'))
														continue;

													if (pathinfo($file, PATHINFO_EXTENSION) != "zip")
														continue;

													$restoreFile = $dir . $file;
													if (is_dir($restoreFile))
														continue;

													echo "<li><strong>". __('Processing archive', SNAPSHOT_I18N_DOMAIN). ": ", basename($restoreFile) ."</strong> (".
														snapshot_utility_size_format(filesize($restoreFile)) .")<ul><li>";
													flush();
													$error_status = snapshot_utility_archives_import_proc($restoreFile, $restoreFolder);
													//echo "error_status<pre>"; print_r($error_status); echo "</pre>";
													if ( (isset($error_status['errorStatus'])) && ($error_status['errorStatus'] === true) ) {
														if ((isset($error_status['errorText'])) && (strlen($error_status['errorText']))) {
															echo  '<span class="snapshot-error">Error: '. $error_status['errorText'] .'</span></br />';
														}
													} else if ((isset($error_status['errorStatus'])) && ($error_status['errorStatus'] === false)) {
														if ((isset($error_status['responseText'])) && (strlen($error_status['responseText']))) {
															echo '<span class="snapshot-success">Success: '. $error_status['responseText'] .'</span></br />';
														} else {

														}
													}
													echo "</li></ul></li>";
												}
												echo "</ol>";

												closedir($dh);

											}
										} else {
											echo "<p>". sprintf(__('local import file not found %s. This could mean either the entered path was not valid or accessible.', SNAPSHOT_I18N_DOMAIN), $dir) ."</p>";

										}
									}

								} else {

									$dir = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull'));
									echo "<p>". __('Importing archives from', SNAPSHOT_I18N_DOMAIN). ": ". $dir ."</p>";

									if ($dh = opendir($dir)) {
										$restoreFolder = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupRestoreFolderFull')) ."_imports";

										echo "<ol>";
										while (($file = readdir($dh)) !== false) {

											if (($file == '.') || ($file == '..') || ($file == 'index.php') || ($file[0] == '.'))
												continue;

											if (pathinfo($file, PATHINFO_EXTENSION) != "zip")
												continue;

											$restoreFile = $dir . $file;
											if (is_dir($restoreFile))
												continue;

											echo "<li><strong>". __('Processing archive', SNAPSHOT_I18N_DOMAIN). ": ", basename($restoreFile) ."</strong> (".
												snapshot_utility_size_format(filesize($restoreFile)) .")<ul><li>";
											flush();
											$error_status = snapshot_utility_archives_import_proc($restoreFile, $restoreFolder);
											//echo "error_status<pre>"; print_r($error_status); echo "</pre>";
											if ( (isset($error_status['errorStatus'])) && ($error_status['errorStatus'] === true) ) {
												if ((isset($error_status['errorText'])) && (strlen($error_status['errorText']))) {
													echo  '<span class="snapshot-error">Error: '. $error_status['errorText'] .'</span></br />';
												}
											} else if ((isset($error_status['errorStatus'])) && ($error_status['errorStatus'] === false)) {
												if ((isset($error_status['responseText'])) && (strlen($error_status['responseText']))) {
													echo '<span class="snapshot-success">Success: '. $error_status['responseText'] .'</span></br />';
												} else {

												}
											}
											echo "</li></ul></li>";
										}
										echo "</ol>";

										closedir($dh);

									}
								}
							}
							echo "<p>". __('Snapshot import complete', SNAPSHOT_I18N_DOMAIN) ."</p>";
						}
					?>
				</div>
			</div>
			<?php
		}
	}	// End of class!
}