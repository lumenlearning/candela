<?php
if ( !class_exists( "wpmudev_snapshot_admin_metaboxes" ) ) {
	class wpmudev_snapshot_admin_metaboxes {

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
		function __construct( ) {
			//$wpmudev_snapshot = $parent;
		}

		/**
		 * The old-style PHP Class constructor. Used when an instance of this class
	 	 * is needed. If used (PHP4) this function calls the PHP5 version of the constructor.
		 *
		 * @since 1.0.2
		 * @param none
		 * @return self
		 */
		function wpmudev_snapshot_admin_metaboxes( ) {
	        $this->__construct();
		}

		/**
		 * Metabox Content for Snapshot Item header
		 * @since 1.0.2
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items']
		 *
		 * @param string $title The title of the metabox
		 * @param array $item The snapshot item structure viewed
		 * @return none
		 */
		function snapshot_metaboxes_show_item_header_information($title, $item, $display_only=false) {

			global $wpdb, $current_blog;

			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">

					<table class="form-table snapshot-backup-header">
					<?php
						if ((is_multisite()) && (!$item['blog-id']) && (isset($item['IMPORT']))) {

							//echo "IMPORT<pre>"; print_r($item['IMPORT']); echo "</pre>";
							?>
							<tr class="form-field">
								<th scope="row">
									<label for="snapshot-blog-id-search"><?php _e('Blog', SNAPSHOT_I18N_DOMAIN); ?></label>
								</th>
								<td>
									<p class="snapshot-error"><?php _e('This archive is from a previous import action. The information from the archive did not match the current blog. So before you can change the configuration you need to assign it to an existing account.', SNAPSHOT_I18N_DOMAIN); ?></p>
									<input type="hidden" name="snapshot-blog-id" id="snapshot-blog-id" value="" />

									<?php if ($display_only !== true) { ?>
										<div id="snapshot-blog-search-success" style="display:block">
											<span id="snapshot-blog-name"><?php
												echo $item['IMPORT']['WP_BLOG_DOMAIN'].$item['IMPORT']['WP_BLOG_PATH'] ?></span> <button
											 	id="snapshot-blog-id-change"><?php _e('Change', SNAPSHOT_I18N_DOMAIN); ?></button>
										</div>
										<div id="snapshot-blog-search" style="display:none">
											<span id="snapshot-blog-search-error" style="color: #FF0000; display:none;"><?php
												_e('Error on blog lookup. Try again', SNAPSHOT_I18N_DOMAIN); ?><br /></span>
											<?php
												if (is_subdomain_install()) {
													$current_sub_domain = str_replace('.' . network_site_url(), '', $item['IMPORT']['WP_BLOG_DOMAIN']);
													?><input name="snapshot-blog-id-search" id="snapshot-blog-id-search" value="" style="width: 20%"/>.<?php echo network_site_url(); ?> <span class="spinner"></span><button id="snapshot-blog-id-lookup"><?php _e('Lookup', SNAPSHOT_I18N_DOMAIN); ?></button><button id="snapshot-blog-id-cancel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></button><p class="description"><?php _e('Enter the blog sub-domain prefix "site1" or blog ID "22" or mapped domain or leave blank for primary site.', SNAPSHOT_I18N_DOMAIN); ?></p><?php
												} else {
													?><?php echo network_site_url(); ?>/<input name="snapshot-blog-id-search" id="snapshot-blog-id-search" value="" style="width: 40%"/> <span class="spinner"></span><button id="snapshot-blog-id-lookup"><?php _e('Lookup', SNAPSHOT_I18N_DOMAIN); ?></button><button id="snapshot-blog-id-cancel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></button><p class="description"><?php _e('Enter the blog domain "myblog.com" or blog ID "22". Once the form is submitted this cannot be changed.', SNAPSHOT_I18N_DOMAIN); ?></p><?php
												}
											?>
										</div>
									<?php } else {
										$blog_column_output = '';
										if ((isset($item['IMPORT']['WP_BLOG_NAME'])) && (!empty($item['IMPORT']['WP_BLOG_NAME']))) {
											$blog_column_output .= $item['IMPORT']['WP_BLOG_NAME'] .' (';
										}
										$blog_column_output .= $item['IMPORT']['WP_BLOG_DOMAIN'].$item['IMPORT']['WP_BLOG_PATH'];
										if ((isset($item['IMPORT']['WP_BLOG_NAME'])) && (!empty($item['IMPORT']['WP_BLOG_NAME']))) {
											$blog_column_output .= ')';
										}
										$blog_column_output .= '<span class="snapshot-error">(I)</span>';
										echo $blog_column_output;
									} ?>
								</td>
							</tr>

							<?php
						} else {

							if (is_multisite()) {
								if (isset($item['blog-id'])) {

									$blog_info = get_blog_details($item['blog-id']);
									?>
									<tr class="form-field">
										<th scope="row">
											<label for="snapshot-blog-id"><?php _e('Blog', SNAPSHOT_I18N_DOMAIN); ?></label>
										</th>
										<td>
											<?php
												if (isset($blog_info)) {
													echo $blog_info->blogname. " (". $blog_info->domain .")";
												} else {
													_e('Unknown Blog', SNAPSHOT_I18N_DOMAIN);
												}
											?>
											<?php /* ?><input type="hidden" id="snapshot-blog-id" name="snapshot-blog-id" value="<?php echo $item['blog-id'] ?>" /><?php */ ?>
										</td>
									</tr>
									<?php

								} else {

									?>
									<tr class="form-field">
										<th scope="row">
											<label for="snapshot-blog-id-search"><?php _e('Blog to backup', SNAPSHOT_I18N_DOMAIN); ?></label>
										</th>
										<td>
											<input type="hidden" name="snapshot-blog-id" id="snapshot-blog-id" value="<?php echo $current_blog->blog_id; ?>" />

											<div id="snapshot-blog-search-success" style="display:block">
												<span id="snapshot-blog-name"><?php echo trailingslashit(network_site_url() . $current_blog->path); ?></span> <button
												 	id="snapshot-blog-id-change"><?php _e('Change', SNAPSHOT_I18N_DOMAIN); ?></button>
											</div>
											<div id="snapshot-blog-search" style="display:none">
												<span id="snapshot-blog-search-error" style="color: #FF0000; display:none;"><?php
													_e('Error on blog lookup. Try again', SNAPSHOT_I18N_DOMAIN); ?><br /></span>
												<?php
													if (is_subdomain_install()) {

														$blog_path =  trailingslashit(network_site_url() . $current_blog->path);
														$blog_path = preg_replace('/(http|https):\/\/|/', '', $blog_path );

														?><input name="snapshot-blog-id-search" id="snapshot-blog-id-search" value="" style="width: 20%" />.<?php echo $blog_path; ?> <span class="spinner"></span><button id="snapshot-blog-id-lookup"><?php _e('Lookup', SNAPSHOT_I18N_DOMAIN); ?></button><button id="snapshot-blog-id-cancel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></button><p class="description"><?php _e('Enter the blog sub-domain prefix "site1" or blog ID "22" or mapped domain or leave blank for primary site. Once the form is submitted this cannot be changed.', SNAPSHOT_I18N_DOMAIN); ?></p><?php
													} else {
														?><?php echo trailingslashit(network_site_url() . $current_blog->path); ?><input name="snapshot-blog-id-search" id="snapshot-blog-id-search" value="" style="width: 20%"/> <span class="spinner"></span><button id="snapshot-blog-id-lookup"><?php _e('Lookup', SNAPSHOT_I18N_DOMAIN); ?></button><button id="snapshot-blog-id-cancel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></button><p class="description"><?php _e('Enter the blog path or blog ID or blank for primary blog. Once the form is submitted this cannot be changed.', SNAPSHOT_I18N_DOMAIN); ?></p><?php

													}
												?>
											</div>
										</td>
									</tr>
									<?php
								}
							} else {
								?><input type="hidden" id="snapshot-blog-id" name="snapshot-blog-id" value="<?php echo $wpdb->blogid ?>" /><?php
							}
						}
					?>

					<tr class="form-field">
						<th scope="row">
							<label for="snapshot-name"><?php _e('Name', SNAPSHOT_I18N_DOMAIN); ?></label>
						</th>
						<td>
							<?php
								if (isset($_REQUEST['snapshot-name'])) {
									$snapshot_name = sanitize_text_field($_REQUEST['snapshot-name']);
								} else if (isset($item['name'])) {
									$snapshot_name = sanitize_text_field($item['name']);
								} else {
									$snapshot_name = "snapshot";
								}

								if ($display_only) {
									echo $snapshot_name;
								} else {
									?>
									<input type="text" name="snapshot-name" id="snapshot-name" value="<?php echo stripslashes($snapshot_name); ?>" />
									<p class="description"><?php _e('Give this configuration a name', SNAPSHOT_I18N_DOMAIN); ?></p>
									<?php
								}
									?>
						</td>
					</tr>
					<tr class="form-field">
						<th scope="row">
							<label for="snapshot-notes"><?php _e('Notes', SNAPSHOT_I18N_DOMAIN); ?></label>
						</th>
						<td>
							<?php
								if (isset($_REQUEST['snapshot-notes'])) {
									$snapshot_notes = sanitize_text_field($_REQUEST['snapshot-notes']);
								} else if (isset($item['notes'])) {
									$snapshot_notes = esc_attr($item['notes']);
								} else {
									$snapshot_notes = "";
								}
								if ($display_only) {
									echo $snapshot_notes;
								} else {
									?>
									<textarea id="snapshot-notes" name="snapshot-notes" cols="20" rows="5"><?php
										echo stripslashes($snapshot_notes); ?></textarea>
									<p class="description"><?php _e('Description about this configuration.',
										SNAPSHOT_I18N_DOMAIN); ?></p>
									<?php
								}
							?>
						</td>
					</tr>
					<?php
/*
						if (isset($item['user'])) {
							?>
							<tr class="form-field">
								<th scope="row">
									<label for="snapshot-created-by"><?php _e('Created by', SNAPSHOT_I18N_DOMAIN); ?></label>
								</th>
								<td>
									<?php
										$user_name = '';
										if (isset($item['user']))
											$user_name = snapshot_utility_get_user_name(intval($item['user']));
										echo $user_name;
									?>
								</td>
							</tr>
							<?php
						}
*/
					?>
					</table>
				</div>
			</div>
			<?php
		}


		/**
		 * Metabox to show the site tables with checkboxes
		 *
		 * @since 1.0.2
		 *
		 * @param string $title - Title to be displayed in header of metabox
		 * @param array  $item 	- The current viewed item
		 * @param array  $blog_tables_last 	- Array of tables used in the previous snapshot for this site.
		 * Using these tables will pre-select the table in this instance
		 * @return none
		 */
		function snapshot_metabox_show_backup_tables_options($title, $item) {

			global $wpdb;
			global $wpmudev_snapshot;

			if (!isset($item['blog-id'])) {
				$item['blog-id'] = $wpdb->blogid;
			}

			$table_sets = snapshot_utility_get_database_tables($item['blog-id']);

			if (isset($wpmudev_snapshot->config_data['config']['tables_last'][$item['blog-id']]))
				$blog_tables_last = $wpmudev_snapshot->config_data['config']['tables_last'][$item['blog-id']];
			else
				$blog_tables_last = array();

			if (!isset($item['tables-option']))
				$item['tables-option'] = "all";

			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">

					<table class="form-table snapshot-backup-tables">
					<tr class="">
						<td>
							<p><?php _e('Select the database tables backup option for this Snapshot.', SNAPSHOT_I18N_DOMAIN); ?></p>
							<ul>
								<li><input type="radio" class="snapshot-tables-option" id="snapshot-tables-option-none" value="none"
									<?php if ($item['tables-option'] == "none") { echo ' checked="checked" '; } ?>
									name="snapshot-tables-option"> <label
									for="snapshot-tables-option-none"><?php
										_e('Do not include database tables in this Snapshot', SNAPSHOT_I18N_DOMAIN); ?></label></li>

								<li><input type="radio" class="snapshot-tables-option" id="snapshot-tables-option-all" value="all"
									<?php if ($item['tables-option'] == "all") { echo ' checked="checked" '; } ?>
									name="snapshot-tables-option"> <label
									for="snapshot-tables-option-all"><?php
										_e('Include <strong>all</strong> blog database tables in this archive. This will automatically include new tables.',
										 	SNAPSHOT_I18N_DOMAIN); ?></label></li>

								<li><input type="radio" class="snapshot-tables-option" id="snapshot-tables-option-selected" value="selected"
									<?php if ($item['tables-option'] == "selected") { echo ' checked="checked" '; } ?>
									name="snapshot-tables-option"> <label
									for="snapshot-tables-option-selected"><?php
										_e('Include <strong>selected</strong> database tables in this Snapshot', SNAPSHOT_I18N_DOMAIN); ?></label>

									<div id="snapshot-selected-tables-container" style="margin-left: 30px; padding-top: 20px; <?php
										if (($item['tables-option'] == "none") || ($item['tables-option'] == "all")) { echo ' display:none; '; } ?>">
										<?php
											$tables_sets_idx = array(
												'global'	=>	__("WordPress Global Tables", SNAPSHOT_I18N_DOMAIN),
												'wp'		=>	__("WordPress core Tables", SNAPSHOT_I18N_DOMAIN),
												'non'		=>	__("Non-WordPress Tables", SNAPSHOT_I18N_DOMAIN),
												'other'		=>	__("Other Tables", SNAPSHOT_I18N_DOMAIN),
												'error'		=>	__("Error Tables - These tables are skipped for the noted reasons.", SNAPSHOT_I18N_DOMAIN)
											);

											foreach($tables_sets_idx as $table_set_key => $table_set_title)	{

												if ((isset($table_sets[$table_set_key])) && (count($table_sets[$table_set_key]))) {
													$display_set = 'block';
												} else {
													$display_set = 'none';
												}
												?>
												<div id="snapshot-tables-<?php echo $table_set_key ?>-set"
														class="snapshot-tables-set" style="display: <?php echo $display_set; ?>">

													<p class="snapshot-tables-title"><?php echo $table_set_title; ?> <?php if ($table_set_key != 'error') { ?><a
														class="button-link snapshot-table-select-all" href="#"
														id="snapshot-table-<?php echo $table_set_key ?>-select-all"><?php
														_e('Select all', SNAPSHOT_I18N_DOMAIN); ?></a><?php } ?></p>

													<?php
														if ($table_set_key == "global") {
															?><p class="description"><?php _e('These global user tables contain blog specific user information which can be included as part of the snapshot archive. Only users whose primary blog matches this selected blog will be included. <strong>Superadmin users will not be included in the sub-site archive.</strong>', SNAPSHOT_I18N_DOMAIN); ?></p><?php
														}

													?>
													<ul class="snapshot-table-list" id="snapshot-table-list-<?php echo $table_set_key; ?>">
													<?php
														if ((isset($table_sets[$table_set_key])) && (count($table_sets[$table_set_key]))) {
															$tables = $table_sets[$table_set_key];


															foreach ($tables as $table_key => $table_name) {

																$is_checked = '';
																if ($table_set_key == 'error') {

																	?><li style="clear:both"><?php echo $table_name['name']; ?> &ndash; <?php
																		echo $table_name['reason']; ?></li><?php

																} else {
																	if (isset($_REQUEST['backup-tables'])) {
																		if (isset($_REQUEST['backup-tables'][$table_set_key][$table_key])) {
																			$is_checked = ' checked="checked" ';
																		}

																	} else {
																		if ((isset($_GET['page']))
																		 && (sanitize_text_field($_GET['page']) == "snapshots_new_panel")) {

																			if ((isset($blog_tables_last[$table_set_key]))
																			 && ( array_search( $table_key, $blog_tables_last[$table_set_key] ) !== false ))
																				$is_checked = ' checked="checked" ';

																		} else 	if ((isset($_GET['page']))
																		         && (sanitize_text_field($_GET['page']) == "snapshots_edit_panel")) {
																			if (isset($item['tables-sections'])) {
																				if ( isset( $item['tables-sections'][$table_set_key][$table_key] ))
																					$is_checked = ' checked="checked" ';

																			} else if (isset($item['tables'])) {
																				if ( array_search( $table_key, $item['tables'] ) !== false )
																					$is_checked = ' checked="checked" ';

																			}
																		}
																	}

																	?><li><input type="checkbox" <?php echo $is_checked; ?> class="snapshot-table-item"
																		id="snapshot-tables-<?php echo $table_key; ?>" value="<?php echo $table_key; ?>"
																		name="snapshot-tables[<?php echo $table_set_key; ?>][<?php echo $table_key; ?>]"> <label
																		for="snapshot-tables-<?php echo $table_key; ?>"><?php
																		echo $table_name; ?></label></li><?php

																}
															}
														} else {
															?><li><?php _e('No Tables', SNAPSHOT_I18N_DOMAIN) ?></li><?php
														}
													?>
													</ul>

												</div><?php
											}
										?>
									</div>
								</li>

							</ul>
						</td>
					</tr>
					</table>
				</div><!-- end inside -->
			</div><!-- end postbox -->
			<?php
		}


		/**
		 * Metabox to show the backup file options
		 *
		 * @since 1.0.2
		 *
		 * @param string $title - Title to be displayed in header of metabox
		 * @param array  $item 	- The current viewed item
		 * @return none
		 */
		function snapshot_metabox_show_backup_files_options($title, $item) {

			global $wpdb;
			global $wpmudev_snapshot;

			if (!isset($item['blog-id']))
				$item['blog-id'] = $wpdb->blogid;

			if (!isset($item['files-option']))
				$item['files-option'] = "none";

			if (!isset($item['files-sections']))
				$item['files-sections'] = array();

			?>
			<div class="postbox">

				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">

					<table class="form-table snapshot-backup-files">
					<tr class="">
						<td class="left">
							<p><?php _e('Select the File backup option for this Snapshot.', SNAPSHOT_I18N_DOMAIN); ?></p>

							<ul>
								<li><input type="radio" class="snapshot-files-option" id="snapshot-files-option-none" value="none"
									<?php if ($item['files-option'] == "none") { echo ' checked="checked" '; } ?>
									name="snapshot-files-option"> <label
									for="snapshot-files-option-none"><?php
										_e('Do not include files', SNAPSHOT_I18N_DOMAIN); ?></label></li>

								<?php
									$blog_upload_path = snapshot_utility_get_blog_upload_path($item['blog-id']);

									if (!empty($blog_upload_path)) {
										?>
										<li><input type="radio" class="snapshot-files-option" id="snapshot-files-option-all" value="all"
											<?php if ($item['files-option'] == "all") { echo ' checked="checked" '; } ?>
											name="snapshot-files-option"> <label
											for="snapshot-files-option-all"><?php
												_e('Include <strong>common</strong> files:', SNAPSHOT_I18N_DOMAIN); ?>
													<span <?php if (!is_main_site($item['blog-id'])) { echo ' style="display:none" '; } ?>
														class="snapshot-backup-files-sections-main-only"><?php _e('Themes, Plugins,', SNAPSHOT_I18N_DOMAIN);
													?></span> <?php
													_e('Media', SNAPSHOT_I18N_DOMAIN);

													?>
													(<span style="font-weight: bold;"
														class="snapshot-media-upload-path"><?php echo $blog_upload_path; ?></span>)</label>
										</li>
										<?php
									}
								?>
								<?php if ((!isset($item['blog-id'])) || (is_main_site($item['blog-id'])))  { ?>
								<li class="snapshot-backup-files-sections-main-only"><input type="radio" class="snapshot-files-option"
									 id="snapshot-files-option-selected" value="selected"
									<?php if ($item['files-option'] == "selected") { echo ' checked="checked" '; } ?>
									name="snapshot-files-option"> <label
									for="snapshot-files-option-selected"><?php
										_e('Include <strong>selected</strong> files:', SNAPSHOT_I18N_DOMAIN); ?></label>

										<div id="snapshot-selected-files-container" style="margin-left: 30px; padding-top: 10px; <?php
											if (($item['files-option'] == "none") || ($item['files-option'] == "all")) { echo ' display:none; '; }  ?>">

											<ul id="snapshot-select-files-option">
<?php /* ?>
												<li><input type="checkbox" class="snapshot-backup-sub-options"
													<?php if (array_search('home', $item['files-sections']) !== false) { echo ' checked="checked" '; } ?>
													id="snapshot-files-option-home" value="home"
													name="snapshot-files-sections[home]"> <label
													for="snapshot-files-option-home"><?php
														_e('Home: Files in the home directory. Will automatically exclude .wp-config.php & .htaccess', SNAPSHOT_I18N_DOMAIN); ?></label></li>
<?php */ ?>
												<li><input type="checkbox" class="snapshot-backup-sub-options"
													<?php if (array_search('themes', $item['files-sections']) !== false) { echo ' checked="checked" '; } ?>
													id="snapshot-files-option-themes" value="themes"
													name="snapshot-files-sections[themes]"> <label
													for="snapshot-files-option-themes"><?php
														_e('Themes: All active and inactive themes will be included', SNAPSHOT_I18N_DOMAIN); ?></label></li>

												<li><input type="checkbox" class="snapshot-backup-sub-options"
													<?php if (array_search('plugins', $item['files-sections']) !== false) { echo ' checked="checked" '; } ?>
													id="snapshot-files-option-plugins" value="plugins"
													name="snapshot-files-sections[plugins]"> <label
													for="snapshot-files-option-plugins"><?php
														_e('Plugins: All active and inactive plugins will be included', SNAPSHOT_I18N_DOMAIN); ?></label></li>

												<?php if (is_multisite()) { ?>
<?php /* ?>
													<li><input type="checkbox" class="snapshot-backup-sub-options"
													<?php if (array_search('plugins', $item['files-sections']) !== false) { echo ' checked="checked" '; } ?>
													id="snapshot-files-option-mu-plugins" value="mu-plugins"
													name="snapshot-files-sections[mu-plugins]"> <label
													for="snapshot-files-option-mu-plugins"><?php
														_e('MU-Plugins: All active and inactive plugins will be included', SNAPSHOT_I18N_DOMAIN); ?></label></li>
<?php */ ?>
												<?php } ?>

												<li><input type="checkbox" class="snapshot-backup-sub-options"
													<?php if (array_search('media', $item['files-sections']) !== false) { echo ' checked="checked" '; } ?>
													id="snapshot-files-option-media" value="media"
													name="snapshot-files-sections[media]"> <label
													for="snapshot-files-option-media"><?php
														_e('Media Files:', SNAPSHOT_I18N_DOMAIN); ?> <span style="font-weight: bold;"
															class="snapshot-media-upload-path"><?php
																echo snapshot_utility_get_blog_upload_path($item['blog-id']); ?></span></label></li>

												<li><input type="checkbox" class="snapshot-backup-sub-options"
													<?php if (array_search('config', $item['files-sections']) !== false) { echo ' checked="checked" '; } ?>
													id="snapshot-files-option-config" value="config"
													name="snapshot-files-sections[config]"> <label
													for="snapshot-files-option-config"><?php
														_e('wp-config.php - Your current wp-config.php file', SNAPSHOT_I18N_DOMAIN); ?></label></li>

												<li><input type="checkbox" class="snapshot-backup-sub-options"
													<?php if (array_search('htaccess', $item['files-sections']) !== false) { echo ' checked="checked" '; } ?>
													id="snapshot-files-option-htaccess" value="htaccess"
													name="snapshot-files-sections[htaccess]"> <label
													for="snapshot-files-option-htaccess"><?php
														_e('.htaccess - Your current root .htaccess / web.config file', SNAPSHOT_I18N_DOMAIN); ?></label></li>

<?php /* ?>
												<li><input type="checkbox" class="snapshot-backup-sub-options"
													<?php if (array_search('core', $item['files-sections']) !== false) { echo ' checked="checked" '; } ?>
													id="snapshot-files-option-core" value="core"
													name="snapshot-backup-files-sections[core]"> <label
													for="snapshot-files-option-core"><?php
														_e('WordPress core files', SNAPSHOT_I18N_DOMAIN); ?></label></li>
<?php */ ?>
											</ul>

										</div>
								</li>
								<?php } ?>
							</ul>

							<?php if (!isset($item['destination-sync'])) $item['destination-sync'] = "archive"; ?>
							<div id="snapshot-selected-files-sync-container">
								<p><?php _e('Dropbox Only - Select Archive or Mirroring option for this Snapshot.', SNAPSHOT_I18N_DOMAIN); ?></p>
								<ul>
									<?php
										$_is_mirror_disabled 	= ' disabled="disabled" ';
										if (isset($item['destination'])) {
											$destination_key = $item['destination'];
											if (isset($wpmudev_snapshot->config_data['destinations'][$destination_key])) {
												$destination = $wpmudev_snapshot->config_data['destinations'][$destination_key];
												if ((isset($destination['type'])) && ($destination['type'] == "dropbox")) {
													$_is_mirror_disabled = '';
												}
											}
										}
									?>
									<li><input type="radio" name="snapshot-destination-sync"
										 id="snapshot-destination-sync-archive" value="archive" class="snapshot-destination-sync"
										<?php if ($item['destination-sync'] == "archive") { echo ' checked="checked" '; } ?> /> <label
										for="snapshot-destination-sync-archive"><?php _e('<strong>Archive</strong> - (Default) Selecting archive will produce a zip archive. This is standard method for backing up your site. A single zip archive will be created for files and database tables.', SNAPSHOT_I18N_DOMAIN); ?></li>


									<li><input type="radio" <?php echo $_is_mirror_disabled; ?> name="snapshot-destination-sync"
										id="snapshot-destination-sync-mirror" value="mirror" class="snapshot-destination-sync"
										<?php if ($item['destination-sync'] == "mirror") { echo ' checked="checked" '; } ?>/> <label
											for="snapshot-destination-sync-mirror"><?php _e('<strong>Mirror/Sync</strong> - <strong>Dropbox ONLY</strong> Selecting mirroring if you want to replicate the file structure of this site to a Dropbox destination. You can include Database tables. If selected they will still be send as a zip archive to the destination root folder. <strong>There is currently no restore with this option</strong>', SNAPSHOT_I18N_DOMAIN); ?>
											</li>
								</ul>
							</div>
						</td>
						<td class="right">
							<label for="snapshot-files-ignore"><?php _e('List files here to exclude from this snapshot. This is handy to exclude very large files like videos. Files should be listed one per line. You can also exclude files for all snapshots by using the Global File Exclusions on the Settings panel.', SNAPSHOT_I18N_DOMAIN); ?></label><br />
							<textarea name="snapshot-files-ignore" id="snapshot-files-ignore" cols="20" rows="5"><?php
								if ((isset($item['files-ignore'])) && (count($item['files-ignore']))) {
									echo implode("\n", $item['files-ignore']);
								}
							?></textarea>
							<p class="description"><?php _e('The exclude logic uses pattern matching. So instead of entering the complete server pathname for a file or directory you can simply use the filename of parent directory. For example to exclude the theme twentyten you could enter this one of many ways: twentyten,  themes/twentyten /wp-content/themes/twentyten, /var/www/wp-content/themes/twentyten. <strong>Regular Expression are not allowed at this time</strong>.', SNAPSHOT_I18N_DOMAIN); ?></p>
						</td>
					</tr>
					</table>
				</div>
			</div>
			<?php
		}


		/**
		 * Metabox to show the backup scheduling options
		 *
		 * @since 1.0.2
		 *
		 * @param string $title - Title to be displayed in header of metabox
		 * @param array  $item 	- The current viewed item
		 * @return none
		 */

		function snapshot_metabox_show_schedule_options($title, $item) {

			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">
					<table class="form-table snapshot-backup-interval">
					<tr class="form-field">
						<td colspan="2">
							<?php
								if ((isset($_GET['page'])) && (sanitize_text_field($_GET['page']) == "snapshots_new_panel")) {
									?><p><?php _e("Select a backup interval from the options below. If you select 'Immediate' the backup will start immediately and will only occur once. If you select any other value the initial backup will start within a few minutes then repeat on the selected interval.", SNAPSHOT_I18N_DOMAIN); ?></p><?php
								} else {
									?><p><?php _e("You can change the interval when a backup occurs by selecting from the options below. If you select 'Suspend' the recurring backups will be stopped. If you select to schedule a backup or change the interval, the initial backup will start within a few minutes then repeat on the selected interval", SNAPSHOT_I18N_DOMAIN); ?></p><?php
								}

								if ( (defined('DISABLE_WP_CRON')) && (DISABLE_WP_CRON == true)) {
									?><p style="color: #FF0000"><?php _e('Your site has disabled the WordPress Cron scheduler (WP_CRON). When scheduling an archive to be created it may not run. Check your wp-config.php for the DISABLE_WP_CRON define. If found either remove it or set the value to "false".',
									 SNAPSHOT_I18N_DOMAIN); ?></p><?php
								}
							?>
						</td>
					</tr>
					<tr class="form-field">
						<th>
							<label for="snapshot-interval"><?php _e('Backup Interval', SNAPSHOT_I18N_DOMAIN); ?></label>
						</th>
						<td>
							<?php
								if (isset($_GET['show_cron_schedules'])) {
									$scheds = (array) wp_get_schedules();
									echo "scheds<pre>"; print_r($scheds); echo "</pre>";

									global $wp_filter;
									if (isset($wp_filter['cron_schedules'])) {
										echo "cron_schedules<pre>"; print_r($wp_filter['cron_schedules']); echo "</pre>";
									} else {
										echo "no cron_schedules filters<br />";
									}
								}
							?>

							<?php
								if (isset($item['interval']))
									$item_interval = $item['interval'];
								else
									$item_interval = '';
							?>
							<select name="snapshot-interval" id="snapshot-interval">
								<optgroup label="<?php _e("Immediate Options", SNAPSHOT_I18N_DOMAIN); ?>">
								<?php
									if ((isset($_GET['page'])) && (sanitize_text_field($_GET['page']) == "snapshots_new_panel")) {
										?><option value="immediate"><?php _e('Run immediate', SNAPSHOT_I18N_DOMAIN); ?></option><?php
									} else {
										if ((!empty($item_interval)) && ($item_interval != "immediate")) {
											?><option value=""><?php _e('Manual', SNAPSHOT_I18N_DOMAIN); ?></option><?php

										} else {
											?><option value=""><?php _e('Save only', SNAPSHOT_I18N_DOMAIN); ?></option><?php
										}
										?><option value="immediate"><?php _e('Run immediate', SNAPSHOT_I18N_DOMAIN); ?></option><?php
									}
								?>
								</optgroup>

								<?php //if ( (!defined('DISABLE_WP_CRON')) || (DISABLE_WP_CRON == false)) { ?>
									<optgroup label="<?php _e("Scheduled Options", SNAPSHOT_I18N_DOMAIN); ?>">
									<?php
										$scheds = (array) wp_get_schedules();
										foreach($scheds as $sched_key => $sched_item) {
											if (substr($sched_key, 0, strlen('snapshot-')) == "snapshot-") {
												?><option value="<?php echo $sched_key; ?>" <?php
													if ( $item_interval == $sched_key ) { echo ' selected="selected" '; } ?>><?php
													echo $sched_item['display']; ?></option><?php
											}
										}
									?>
									</optgroup>
									<?php //} ?>
							</select> <sup>1</sup>
						</td>
					</tr>
					<?php if ( (!defined('DISABLE_WP_CRON')) || (DISABLE_WP_CRON == false)) { ?>

						<tr class="form-field">
							<th>
								<label for="snapshot-interval-offset"><?php _e('Start Backup', SNAPSHOT_I18N_DOMAIN); ?></label>
							</th>
							<td>
								<?php
									$timestamp = time() + ( get_option( 'gmt_offset' ) * 3600);
									$localtime = localtime($timestamp, true);
								?>

								<div id="interval-offset">
									<div class="interval-offset-none" <?php
										if ((empty($item_interval)) || ($item_interval == "immediate")) {
											echo ' style="display: block;" '; } else { echo ' style="display: none;" '; } ?> ><?php
											_e("None", SNAPSHOT_I18N_DOMAIN); ?>
									</div>
									<div class="interval-offset-hourly" <?php
										if ($item_interval == "snapshot-hourly")
										{ echo ' style="display: block;" '; } else { echo ' style="display: none;" '; } ?> >
										<label for="snapshot-interval-offset-hourly-minute"><?php _e('Minute', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-hourly-minute" name="snapshot-interval-offset[snapshot-hourly][tm_min]">
										<?php
											if (!isset($item['interval-offset']['snapshot-hourly']['tm_min']))
												$item['interval-offset']['snapshot-hourly']['tm_min'] = $localtime['tm_min'];

											snapshot_utility_form_show_minute_selector_options($item['interval-offset']['snapshot-hourly']['tm_min']);
										?>
										</select>
									</div>
									<div class="interval-offset-daily" <?php
										if (($item_interval == "snapshot-daily") || ($item_interval == "snapshot-twicedaily")) {
											echo ' style="display: block;" '; } else { echo ' style="display: none;" '; } ?> >
										<label for="snapshot-interval-offset-daily-hour"><?php _e('Hour', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-daily-hour" name="snapshot-interval-offset[snapshot-daily][tm_hour]">
										<?php
											$_hour = 0;
											if (!isset($item['interval-offset']['snapshot-daily']['tm_hour']))
													   $item['interval-offset']['snapshot-daily']['tm_hour'] = $localtime['tm_hour'];

											snapshot_utility_form_show_hour_selector_options($item['interval-offset']['snapshot-daily']['tm_hour']);
										?>
										</select>&nbsp;&nbsp;

										<label for="snapshot-interval-offset-daily-minute"><?php _e('Minute', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-daily-minute" name="snapshot-interval-offset[snapshot-daily][tm_min]">
										<?php
											if (!isset($item['interval-offset']['snapshot-daily']['tm_min']))
												$item['interval-offset']['snapshot-daily']['tm_min'] = $localtime['tm_min'];

											snapshot_utility_form_show_minute_selector_options($item['interval-offset']['snapshot-daily']['tm_min']);
										?>
										</select>
									</div>
									<div class="interval-offset-weekly" <?php
										if ($item_interval == "snapshot-weekly") {
											echo ' style="display: block;" '; } else { echo ' style="display: none;" '; } ?> >
										<label for="snapshot-interval-offset-weekly-wday"><?php _e('Weekday', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-weekly-wday" name="snapshot-interval-offset[snapshot-weekly][tm_wday]">
										<?php
											if (!isset($item['interval-offset']['snapshot-weekly']['tm_wday']))
											   	$item['interval-offset']['snapshot-weekly']['tm_wday'] = $localtime['tm_wday'];

											snapshot_utility_form_show_wday_selector_options($item['interval-offset']['snapshot-weekly']['tm_wday']);
										?>
										</select>&nbsp;&nbsp;

										<label for="snapshot-interval-offset-weekly-hour"><?php _e('Hour', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-weekly-hour" name="snapshot-interval-offset[snapshot-weekly][tm_hour]">
										<?php
											if (!isset($item['interval-offset']['snapshot-weekly']['tm_hour']))
												$item['interval-offset']['snapshot-weekly']['tm_hour'] = $localtime['tm_hour'];

											snapshot_utility_form_show_hour_selector_options($item['interval-offset']['snapshot-weekly']['tm_hour']);

										?>
										</select>&nbsp;&nbsp;

										<label for="snapshot-interval-offset-weekly-minute"><?php _e('Minute', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-weekly-minute" name="snapshot-interval-offset[snapshot-weekly][tm_min]">
										<?php
											if (!isset($item['interval-offset']['snapshot-weekly']['tm_min']))
												$item['interval-offset']['snapshot-weekly']['tm_min'] = $localtime['tm_min'];

											snapshot_utility_form_show_minute_selector_options($item['interval-offset']['snapshot-weekly']['tm_min']);
										?>
										</select>

									</div>
									<div class="interval-offset-monthly" <?php
										if (($item_interval == "snapshot-monthly") || ($item_interval == "snapshot-twicemonthly")) {
											echo ' style="display: block;" '; } else { echo ' style="display: none;" '; } ?> >

										<label for="snapshot-interval-offset-monthly-mday"><?php _e('Day', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-monthly-mday" name="snapshot-interval-offset[snapshot-monthly][tm_mday]">
										<?php
											if (!isset($item['interval-offset']['snapshot-monthly']['tm_mday']))
											   $item['interval-offset']['snapshot-monthly']['tm_mday'] = $localtime['tm_mday'];

											snapshot_utility_form_show_mday_selector_options($item['interval-offset']['snapshot-monthly']['tm_mday']);
										?>
										</select>&nbsp;&nbsp;

										<label for="snapshot-interval-offset-monthly-hour"><?php _e('Hour', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-monthly-hour" name="snapshot-interval-offset[snapshot-monthly][tm_hour]">
										<?php
											if (!isset($item['interval-offset']['snapshot-monthly']['tm_hour']))
												$item['interval-offset']['snapshot-monthly']['tm_hour'] = $localtime['tm_hour'];

											snapshot_utility_form_show_hour_selector_options($item['interval-offset']['snapshot-monthly']['tm_hour']);
										?>
										</select>&nbsp;&nbsp;

										<label for="snapshot-interval-offset-monthly-minutes"><?php _e('Minute', SNAPSHOT_I18N_DOMAIN); ?></label>
										<select id="snapshot-interval-offset-monthly-minutes" name="snapshot-interval-offset[snapshot-monthly][tm_min]">
										<?php
											if (!isset($item['interval-offset']['snapshot-monthly']['tm_min']))
												$item['interval-offset']['snapshot-monthly']['tm_min'] = $localtime['tm_min'];

											snapshot_utility_form_show_minute_selector_options($item['interval-offset']['snapshot-monthly']['tm_min']);
										?>
										</select>
									</div>
								</div>
							</td>
						</tr>
						<tr class="form-field">
							<td scope="row" colspan="2">
								<p><strong><sup>1</sup> - <?php _e("The Snapshot scheduling process uses the WordPress Cron (WPCron) system. This is the same process used to run daily checks for updates to core, plugins and themes. It should be understood this WPCron process is not precise. If you schedule a Snapshot for a specific minute of the hour WPCron may not execute at exactly that time. WPCron relies on regular front-end traffic to your website to kickoff the processing.",
								 SNAPSHOT_I18N_DOMAIN); ?></strong></p>
							</td>
						</tr>
					<?php } ?>

					<tr class="form-field">
						<td scope="row" colspan="2">
							<p><?php _e("Control the total number of <strong>local</strong> archives to keep for this snapshot. Once the archive limit is reached, older locally stored archives will be removed. In common cases you may want to set the backup interval to once a week. Then set the number of archives to keep to 52 which would give you a year or backups. But keep in mind on a large site this will be a lot of extra disk space required.", SNAPSHOT_I18N_DOMAIN); ?></p>
						</td>


					<tr class="form-field">
						<th scope="row">
							<label for="snapshot-name"><?php _e('Maximum number of local archives', SNAPSHOT_I18N_DOMAIN); ?></label>
						</th>
						<td>
							<?php
								if (!isset($item['archive-count']))
									$item['archive-count'] = 0;

								?>
								<input type="text" name="snapshot-archive-count" id="snapshot-archive-count"
									value="<?php echo stripslashes($item['archive-count']); ?>" />
								<p class="description"><?php _e('Example: 10, 100. Enter 0 to keep all', SNAPSHOT_I18N_DOMAIN); ?></p>
						</td>
					</tr>


					</table>
				</div><!-- end inside -->
			</div><!-- end postbox -->
			<?php
		}

		/**
		 * Metabox to show the backup destination options
		 *
		 * @since 1.0.2
		 *
		 * @param string $title - Title to be displayed in header of metabox
		 * @param array  $item 	- The current viewed item
		 * @return none
		 */

		function snapshot_metabox_show_destination_options($title, $item, $display_only=false) {
			global $wpmudev_snapshot;

			if (!isset($item['destination']))
				$item['destination'] = "local";
			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">
					<table class="form-table snapshot-backup-destinations">
					<tr class="form-field">
						<th scope="row">
							<label for="snapshot-destination"><?php _e('Backup Destination', SNAPSHOT_I18N_DOMAIN); ?></label><br />
							<a href="admin.php?page=snapshots_destinations_panel"><?php _e('Add more Destinations', SNAPSHOT_I18N_DOMAIN); ?></a>
						</th>
						<td>
							<select name="snapshot-destination" id="snapshot-destination">
								<?php
									// This global is set within the next calling function. Helps determine which set of descriptions to show.
									global $snapshot_destination_selected_type;

									snapshot_utility_destination_select_options_groups(
										$wpmudev_snapshot->config_data['destinations'],
										$item['destination'],
										$wpmudev_snapshot->snapshot_get_setting('destinationClasses')) ?>
							</select><sup>1</sup><br />

							<p class="description"><?php _e("If you select a remote destination and the 'Interval' is set as Immediate, the snapshot backup file will not be sent during the normal backup step. Instead the transfer of the backup file will be scheduled at a later time. This is to prevent the screen from locking while the backup file is sent to the remote destination.", SNAPSHOT_I18N_DOMAIN); ?></p>

							<p><strong><sup>1</sup> - <?php _e("The Snapshot scheduling process uses the WordPress Cron (WPCron) system. This is the same process used to run daily checks for updates to core, plugins and themes. It should be understood this WPCron process is not precise. If you schedule a Snapshot for a specific minute of the hour WPCron may not execute at exactly that time. WPCron relies on regular front-end traffic to your website to kickoff the processing.",
							 SNAPSHOT_I18N_DOMAIN); ?></strong></p>


						</td>
					</tr>
					<tr class="form-field">
						<th scope="row">
							<label for="snapshot-destination-directory"><?php _e('Directory (optional)', SNAPSHOT_I18N_DOMAIN); ?></label>
						</th>
						<td>
							<input type="text" name="snapshot-destination-directory" id="snapshot-destination-directory" value="<?php if (isset($item['destination-directory'])) { echo stripslashes($item['destination-directory']); } ?>" />


							<?php //echo "snapshot_destination_selected_type[". $snapshot_destination_selected_type ."]<br />"; ?>
							<div id="snapshot-destination-directory-description" <?php
								if ($snapshot_destination_selected_type == 'google-drive') { echo ' style="display:none" '; } ?>>

								<p class="description"><?php _e("The optional Directory can be used to override or supplement the selected destination directory value. If 'local server' is selected and if the directory does not start with a forward slash '/' the directory will be relative to the site root.", SNAPSHOT_I18N_DOMAIN); ?></p>
								<p class="description"><?php _e("This field support tokens you can use to create dynamic values. You can use any combination of the following tokens. Use the forward slash '/' to separate directory elements.", SNAPSHOT_I18N_DOMAIN); ?></p>
								<ul class="description">
									<li><strong>[DEST_PATH]</strong> &ndash; <?php _e("This represents the Directory/Bucket used by the selected Backup Destination or if local, the Settings Folder Location. This can be used to supplement a value entered into this Snapshot. If [DEST_PATH] is not used the Directory value here will override the complete value from the selected Destination."); ?></li>
									<li><strong>[SITE_DOMAIN]</strong> &ndash; <?php _e('This represents the full domain of the selected site per this snapshot'); ?>: <?php

								if (isset($item['blog-id'])) {
									if (is_multisite()) {
										$blog_info = get_blog_details($item['blog-id']);
										if ($blog_info->domain) {
											$domain = $blog_info->domain;
										}
									}
									else {
										$siteurl = get_option( 'siteurl' );
										if ($siteurl) {
											$domain = parse_url($siteurl, PHP_URL_HOST);
										}
									}

									if ((isset($domain)) && (strlen($domain))) {
										echo "<strong>". $domain ."</strong>";
									}
								}
									?></li>
									<li><strong>[SNAPSHOT_ID]</strong> &ndash; <?php _e('This is the unique ID assigned to this Snapshot set'); ?>:
									<?php
										if (isset($item['timestamp'])) {
											echo "<strong>". $item['timestamp'] ."</strong>";
										}
									?></li>
								</ul>
							</div>
							<div id="snapshot-destination-directory-description-google-drive" <?php if ($snapshot_destination_selected_type != 'google-drive') { echo ' style="display:none" '; }?>>
								<p><?php echo sprintf(__('For Google Drive destinations this is not a normal directory path. Instead you must provide the Directory ID. Go to your %s. Navigate to or create a new directory where you want to upload the Snapshot archives. Make sure you are viewing the destination directory. The URL for the directory will be something similar to <em>https://drive.google.com/#folders/0B6GD66ctHXXCOWZKNDRIRGJJXS3</em>. The Directory ID would be the last part after /#folders/ <strong><em>0B6GD66ctHXXCOWZKNDRIRGJJXS3</em></strong>.', SNAPSHOT_I18N_DOMAIN), '<a href="https://drive.google.com/#my-drive">'. __('Drive account', SNAPSHOT_I18N_DOMAIN) .'</a>') ?></p>
							</div>
						</td>
					</tr>

					</table>
				</div><!-- end inside -->
			</div><!-- end postbox -->
			<?php
		}



		/**
		 * Metabox Content for Snapshot Migration
		 * This is used to migrate snapshot files and logs from version 1.0.1 and earlier to the centralized 1.0.2 Multisite format
		 * @since 1.0.2
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_metaboxes_show_migration() {
			global $wpmudev_snapshot;

			$config_data = get_option( 'snapshot_1.0' );
			if (($config_data) && (isset($config_data['items'])) && (count($config_data['items']))) {

				?>
				<form action="?page=snapshots_settings_panel" method="post">
					<input type="hidden" name="snapshot-action" value="settings-update" />
					<input type="hidden" name="snapshot-sub-action" value="migration" />
					<input type="hidden" name="migration" value="true" />
					<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
					<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
					<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

					<p><?php _ex("Migrate Snapshot files and logs created under previous version of the Snapshot plugin.",
						'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

					<table class="form-table snapshot-backup-migration">
					<tr>
						<th scope="row">
							<label for="snapshot-migrate-blog-id"><?php _e('Migrate old Snapshots', SNAPSHOT_I18N_DOMAIN); ?></label>
						</th>
						<td><input class="button-primary" type="submit" value="<?php _e('Migrate Snapshots', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
					</tr>
					</table>

					<p><?php _ex("The following items will be converted to the new Snapshot format.",
						'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>
					<table class="form-table snapshot-backup-migration">
					<tr class="form-field" >
						<th scope="row" style="width: 10%;"><?php _e("Name", SNAPSHOT_I18N_DOMAIN); ?></th>
						<th scope="row" style="width: 30%;"><?php _e("Notes", SNAPSHOT_I18N_DOMAIN); ?></th>
						<th scope="row" style="width: 30%;"><?php _e("Filename", SNAPSHOT_I18N_DOMAIN); ?></th>
					</tr>
					<?php
						foreach($config_data['items'] as $item) {
							if (!isset($row_class)) { $row_class = ""; }
							$row_class = ( $row_class == '' ? 'alternate' : '' );

							?>
							<tr class="form-field <?php echo $row_class; ?>" >
								<td><?php echo $item['name']; ?></td>
								<td><?php echo $item['notes'] ."<br />". implode(', ', $item['tables']); ?></td>
								<td><?php
									$backupFile = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull')) . $item['file'];

									if (file_exists($backupFile)) {

										?><a href="<?php echo trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupURLFull')) .
											$item['file']; ?>"><?php echo $item['file']; ?></a><?php
									}
								?></td>
							</tr>
							<?php
						}
					?>
					</table>
				</form>
				<?php
			} else {
				?><p><?php _ex("No legacy snapshot data found to convert.",
					'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p><?php
			}
		}


		 /**
		 * Metabox Content for Snapshot Item tables
		 * @since 1.0.2
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items']
		 *
		 * @param string $title The title of the metabox
		 * @param array $item The snapshot item structure viewed
		 * @return none
		 */
/*
		function snapshot_metaboxes_show_item_tables($title, $item) {

			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">

					<table class="form-table snapshot-backup-tables">
					<tr class="form-field">
						<td>
						<?php
							if (isset($item['blog-id']))
								$site_tables = snapshot_utility_get_database_tables($item['blog-id']);
							else
								$site_tables = snapshot_utility_get_database_tables();

							if (isset($item['tables'])) {

								$wp_tables = array_keys($site_tables['wp']);
								$wp_tables = array_intersect($wp_tables, array_keys($item['tables']));

								$non_tables = array_keys($site_tables['non']);
								$non_tables = array_intersect($non_tables, array_keys($item['tables']));

								if ((count($wp_tables)) || (count($non_tables)))
								{
									?><p><?php

									if (count($wp_tables)) {
										?><a class="snapshot-list-table-wp-show" href="#"><?php printf(__('show %d core',
										 	SNAPSHOT_I18N_DOMAIN), count($wp_tables)) ?></a><?php
									}

									if (count($non_tables)) {
										if (count($wp_tables)) { echo ", "; }
										?><a class="snapshot-list-table-non-show" href="#"><?php printf(__('show %d non-core',
										 	SNAPSHOT_I18N_DOMAIN), count($non_tables)) ?></a><?php
									}
									?></p><?php

									if (count($wp_tables)) {
										?><p style="display: none" class="snapshot-list-table-wp-container"><?php
											echo implode(', ', $wp_tables); ?></p><?php
									}

									if (count($non_tables)) {
										?><p style="display: none" class="snapshot-list-table-non-container"><?php
											echo implode(', ', $non_tables); ?></p><?php
									}
								}
							}
						?>
						</td>
					</tr>
					</table>

				</div>
			</div>
			<?php
		}
*/

		/**
		 * Metabox to show the backup archived files
		 *
		 * @since 1.0.2
		 *
		 * @param string $title - Title to be displayed in header of metabox
		 * @param array  $item 	- The current viewed item
		 * @param bool  $restore_option - If shown on the restore panel.
		 * @return none
		 */

		function snapshot_metabox_show_archive_files($title, $item, $restore_option=false) {
			global $wpmudev_snapshot;

			//echo "item<pre>"; print_r($item); echo "</pre>";
			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">
				<?php
					if ($restore_option == true) {
						/* ?><p><?php _e('Select which archive to restore', SNAPSHOT_I18N_DOMAIN); ?></p><?php */
					} else {
						?>
						<p><?php _e('Below is a listing of your archives showing at most the most recent 6 entries. If the archive is local you can click on the filename to download it. You can also view or download the load file the archive.', SNAPSHOT_I18N_DOMAIN); ?> <a href="?page=snapshots_edit_panel&amp;snapshot-action=item-archives&amp;item=<?php echo $item['timestamp'] ?>"><?php
							_e('View all archives', SNAPSHOT_I18N_DOMAIN); ?></a></p>
						<?php
					}

					if ((isset($item['data'])) && (count($item['data']))) {

						$data_items = $item['data'];
						krsort($data_items);
						if (isset($_GET['snapshot-data-item'])) {
							$data_item_key = intval($_GET['snapshot-data-item']);
							if (isset($data_items[$data_item_key])) {
								$data_item = $data_items[$data_item_key];
								$data_items = array();
								$data_items[$data_item_key] = $data_item;
							}
						} else {
							if (count($data_items) > 6) {
								$data_items = array_slice($data_items, 0, 6, true);
							}
						}


						?>
						<table class="widefat">
						<thead>
							<tr class="form-field">
								<?php if ($restore_option == true) { ?><th class="snapshot-col-restore"><?php
									_e('Select', SNAPSHOT_I18N_DOMAIN); ?></th><?php } ?>
								<th class="snapshot-col-date"><?php _e('Date', SNAPSHOT_I18N_DOMAIN); ?></th>
								<th class="snapshot-col-file"><?php _e('File', SNAPSHOT_I18N_DOMAIN); ?></th>
								<th class="snapshot-col-notes"><?php _e('Notes', SNAPSHOT_I18N_DOMAIN); ?></th>
								<th class="snapshot-col-size"><?php _e('Size', SNAPSHOT_I18N_DOMAIN); ?></th>
								<?php
									if ($restore_option != true) {
										if ($wpmudev_snapshot->config_data['config']['absoluteFolder'] != true) {
											?><th class="snapshot-col-logs"><?php _e('Logs', SNAPSHOT_I18N_DOMAIN); ?></th><?php
										}
									}
								?>
							</tr>
						</thead>
						<tbody style="overflow: auto; max-height: 100px;">
						<?php
							$selected_item = "";
							foreach($data_items as $data_key => $data_item) {
								//echo "data_item<pre>"; print_r($data_item); echo "</pre>";

								if (!isset($row_class)) { $row_class = ""; }
								$row_class = ( $row_class == '' ? 'alternate' : '' );

								$backupLogFileFull = trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupLogFolderFull'))
									. $item['timestamp'] ."_". $data_item['timestamp'] .".log";

								?><tr class="form-field <?php echo $row_class; ?>"><?php

									if ($restore_option == true) {
										?><td><input type="radio" name="snapshot-restore-file" class="snapshot-restore-file"
											id="snapshot-restore-<?php echo $data_item['timestamp']; ?>"
											value="<?php echo $data_item['timestamp']; ?>" <?php
											if ((isset($_GET['snapshot-data-item'])) && (intval($_GET['snapshot-data-item']) == $data_item['timestamp'])) {
												echo ' checked="checked" ';
											} ?>/></td><?php
									}

									?><td><?php echo snapshot_utility_show_date_time($data_item['timestamp']); ?></td><?php

									?><td><?php
									if ((empty($data_item['destination'])) || ($data_item['destination'] == "local")) {
										if (isset($data_item['filename'])) {
											$current_backupFolder = $wpmudev_snapshot->snapshot_get_item_destination_path($item, $data_item);
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

												echo '<a href="?page=snapshots_edit_panel&amp;snapshot-item='. $item['timestamp']
													.'&snapshot-data-item='. $data_item['timestamp']
													.'&snapshot-action=download-archive">'. $data_item['filename'] .'</a>';

											} else {
												echo  $data_item['filename'];
											}
										}
									} else {
										if (isset($data_item['filename'])) {

											$current_backupFolder = $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull');
											$backupFile = trailingslashit(trim($current_backupFolder)) . $data_item['filename'];

											if (file_exists($backupFile)) {

												echo '<a href="?page=snapshots_edit_panel&amp;snapshot-item='. $item['timestamp']
													.'&snapshot-data-item='. $data_item['timestamp']
													.'&snapshot-action=download-archive">'. $data_item['filename'] .'</a>';

											} else {
												echo  $data_item['filename'];
											}
										}
									}
									?></td><?php

									?><td><?php

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

									?></td><?php

									?><td><?php
										if (isset($data_item['file_size'])) {
											$file_kb = snapshot_utility_size_format($data_item['file_size']);
											echo $file_kb;
										} else {
											echo "&nbsp;";
										}
									?></td><?php

									if ($restore_option != true) {
										?><td><?php
										if (file_exists($backupLogFileFull)) {

											echo '<a class="thickbox"
												href="'. admin_url()
												.'admin-ajax.php?action=snapshot_view_log_ajax&amp;width=800&amp;height=600&amp;snapshot-item='
												. $item['timestamp']
												.'&amp;snapshot-data-item='. $data_item['timestamp'] .'">'. __('view', SNAPSHOT_I18N_DOMAIN) .'</a>';
											echo " ";
											echo '<a href="?page=snapshots_edit_panel&amp;snapshot-action=download-log&amp;snapshot-item=' . $item['timestamp']
												.'&amp;snapshot-data-item='. $data_item['timestamp'] .'">'
												. __('download', SNAPSHOT_I18N_DOMAIN) .'</a>';



										} else {
											echo "&nbsp;";
										}

										?></td><?php
									}
								?></tr><?php
							}

						?></tbody></table><?php

					} else {
						_e('No Archives', SNAPSHOT_I18N_DOMAIN);
					}
				?>
				</div>
			</div>
			<?php
		}

		/**
		 * Metabox to show the restore options
		 *
		 * @since 1.0.2
		 *
		 * @param string $title - Title to be displayed in header of metabox
		 * @param array  $item 	- The current viewed item
		 * @return none
		 */

		function snapshot_metabox_restore_options($title, $item) {
			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">

					<table class="form-table snapshot-restore-options">
					<tr class="">
						<th scope="row">
							<label><?php _e('Plugins', SNAPSHOT_I18N_DOMAIN); ?></label>
						</th>
						<td>
							<input type="checkbox" id="snapshot-restore-option-plugins" name="restore-option-plugins" value="yes" /> <label
									 for="snapshot-restore-option-plugins"><?php _e('Turn off all plugins', SNAPSHOT_I18N_DOMAIN); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php _e('Set a theme to active', SNAPSHOT_I18N_DOMAIN); ?></label>
						</th>
						<td>
							<?php
								if (isset($item['blog-id'])) {
									$current_theme = snapshot_utility_get_current_theme($item['blog-id']);
								} else {
									$current_theme = snapshot_utility_get_current_theme();
								}
								//echo "current_theme=[". $current_theme ."]<br />";

								if (isset($item['blog-id'])) {
									$themes = snapshot_utility_get_blog_active_themes($item['blog-id']);
								} else {
									$themes = snapshot_utility_get_blog_active_themes();
								}

								if ($themes) {
									//echo "themes<pre>"; print_r($themes); echo "</pre>";

									?><ul><?php
									foreach($themes as $theme_key => $theme_name) {

										?>
										<li><input type="radio" id="snapshot-restore-option-theme-<?php echo $theme_key; ?>"
										<?php
											if ($theme_name == $current_theme) { echo ' checked="checked" '; }
											?> name="restore-option-theme" value="<?php echo $theme_key; ?>" />
											<label for="snapshot-restore-option-theme-<?php echo $theme_key; ?>">
												<?php if ($theme_name == $current_theme) { echo '<strong>'; } ?>
												<?php echo $theme_name ?>
												<?php if ($theme_name == $current_theme) { echo '</strong> (current active theme)'; } ?>
											</label>
										</li>
										<?php
									}
									?></ul><?php
								}
							?>
						</td>
					</tr>
					</table>
				</div>
			</div>
			<?php
		}

		/**
		 * Metabox Restore Blog options. Allows admin to select alternate restore blog within Multisite.
		 *
		 * @since 1.0.0
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_metabox_restore_blog_options($title='', $item) {
			global $wpdb, $wpmudev_snapshot, $blog_id;

			//echo "wpdb->blogid[". $wpdb->blogid ."]<br />";
			//echo "blog_id[". $blog_id ."]<br />";

			//echo "item<pre>"; print_r($item); echo "</pre>";
			//echo "MANIFEST<pre>"; print_r($item['MANIFEST']); echo "</pre>";

			$siteurl 	= '';
			$domain 	= '';
			if (isset($item['blog-id'])) {
				if (is_multisite()) {
					$blog_details = get_blog_details($item['blog-id']);
				} else {
					$blog_details = new stdClass();
					$blog_details->blog_id = $blog_id;
					$blog_details->siteurl = get_option( 'siteurl' );
					if ($blog_details->siteurl) {
						$blog_details->domain 	= parse_url($blog_details->siteurl, PHP_URL_HOST);
						$blog_details->path 	= parse_url($blog_details->siteurl, PHP_URL_PATH);
						if (empty($blog_details->path))
							$blog_details->path = '/';
					}
				}
				//echo "blog_details<pre>"; print_r($blog_details); echo "</pre>";
			}
			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">
					<?php if (is_multisite()) { ?>
					<p class="snapshot-error"><?php _e('You can restore the backup to a different blog within your Multisite environment. Note: The destination blog MUST already exist. This migration logic is considered still in beta.', SNAPSHOT_I18N_DOMAIN); ?></p>
					<?php } ?>

					<table class="form-table snapshot-restore-blog-options">
					<tr>
						<td colspan="2"><?php
							if ((is_multisite()) && ($blog_details->siteurl != $item['MANIFEST']['WP_SITEURL'])) {
								?><tr><td colspan="2"><p class="snapshot-error"><?php _e('Restore Note: URL mismatch!. The Snapshot archive does not appear made from the current WordPress system. Every attempt will be made to replace the source URL with the URL from the destination.', SNAPSHOT_I18N_DOMAIN); ?></p></td></tr><?php
								$restore_blog_id = '';
							} else {
								$restore_blog_id = $item['blog-id'];
							} ?><input type="hidden" name="snapshot-blog-id" id="snapshot-blog-id" value="<?php echo $restore_blog_id; ?>" />
						</td>
					</tr>
					<tr>
						<td style="width: 50%">
							<label><?php _e('Information from Archive', SNAPSHOT_I18N_DOMAIN); ?></label>
							<ul>
								<?php if (is_multisite()) { ?>
								<?php
									if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
										$item_blog_id = $item['IMPORT']['WP_BLOG_ID'];
									} else {
										$item_blog_id = $item['MANIFEST']['WP_BLOG_ID'];
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Blog ID:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-org-blog-id" class="snapshot-field-value"><?php echo $item_blog_id; ?></span></li>
								<?php } ?>
								<?php
									if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
										$item_siteurl = $item['IMPORT']['WP_SITEURL'];
									} else {
										$item_siteurl = $item['MANIFEST']['WP_SITEURL'];
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Site URL:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-org-site-url" class="snapshot-field-value"><?php echo $item_siteurl; ?></span></li>
								<?php /* ?>
								<li><span class="snapshot-field-label"><?php _e('Home URL:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-org-site-url" class="snapshot-field-value"><?php echo $item['MANIFEST']['WP_HOME']; ?></span></li>
								<?php */ ?>

								<?php
									if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
										$item_db_name = $item['IMPORT']['WP_DB_NAME'];
									} else {
										$item_db_name = $item['MANIFEST']['WP_DB_NAME'];
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Database Name:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-org-db-name" class="snapshot-field-value"><?php echo $item_db_name; ?></span></li>


								<?php
									if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
										$item_db_base_prefix = $item['IMPORT']['WP_DB_BASE_PREFIX'];
									} else {
										$item_db_base_prefix = $item['MANIFEST']['WP_DB_BASE_PREFIX'];
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Database Base Prefix:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-org-db-prefix"  class="snapshot-field-value"><?php echo $item_db_base_prefix; ?></span></li>


								<?php
									if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
										$item_db_prefix = $item['IMPORT']['WP_DB_PREFIX'];
									} else {
										$item_db_prefix = $item['MANIFEST']['WP_DB_PREFIX'];
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Database Prefix:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-org-db-prefix"  class="snapshot-field-value"><?php echo $item['MANIFEST']['WP_DB_PREFIX']; ?></span></li>

								<?php
									if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
										$item_upload_path = $item['IMPORT']['WP_UPLOAD_PATH'];
									} else {
										$item_upload_path = $item['MANIFEST']['WP_UPLOAD_PATH'];
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Upload Path:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-org-upload-path" class="snapshot-field-value"><?php echo $item_upload_path; ?></span></li>
							</ul>
						</td>
						<td style="width: 50%">
							<label><?php _e('Will be restored to', SNAPSHOT_I18N_DOMAIN); ?></label>
							<ul>
								<?php if (is_multisite()) { ?>
								<?php
									if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
										$item_blog_id = '';
									} else {
										$item_blog_id = $item['MANIFEST']['WP_BLOG_ID'];
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Blog ID:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-new-blog-id" class="snapshot-field-value"><?php echo $item_blog_id; ?></span></li>
								<?php } ?>

								<?php
									if (is_multisite()) {
										if ( (!$item['blog-id']) && (isset($item['IMPORT'])) ) {
											$item_siteurl = '';
										} else {
											$item_siteurl = $blog_details->siteurl;
										}
									} else {
										$item_siteurl = get_option('siteurl');
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Site URL:', SNAPSHOT_I18N_DOMAIN); ?></span> <span id="snapshot-blog-search-success" style="display:inline"><span style="font-weight: bold" id="snapshot-blog-name"><?php echo $item_siteurl; ?></span><?php if (is_multisite()) { ?>
								 <button id="snapshot-blog-id-change"><?php _e('Change', SNAPSHOT_I18N_DOMAIN); ?></button>
								<?php } ?>
								</span>
								<?php if (is_multisite()) { ?>
									<span id="snapshot-blog-search" style="display:none">
										<span id="snapshot-blog-search-error" style="color: #FF0000; display:none;"><?php
											_e('Error on blog lookup. Try again', SNAPSHOT_I18N_DOMAIN); ?><br /></span>
										<?php
											if (is_subdomain_install()) {
												$current_sub_domain = str_replace(".". network_site_url(), '', parse_url($item_siteurl, PHP_URL_HOST));
												$site_domain = untrailingslashit( preg_replace( '/^(http|https):\/\//', '', network_site_url() ) );
												$current_sub_domain = str_replace( '.' . $site_domain , '', $current_sub_domain);
												?><input name="snapshot-blog-id-search" id="snapshot-blog-id-search" value="<?php echo $current_sub_domain; ?>" style="width: 20%" />.<?php echo $site_domain; ?> <span class="spinner"></span><button id="snapshot-blog-id-lookup"><?php _e('Lookup', SNAPSHOT_I18N_DOMAIN); ?></button><button id="snapshot-blog-id-cancel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></button><p class="description"><?php _e('Enter the blog sub-domain prefix "site1" or blog ID "22" or mapped domain or leave blank for primary site.',SNAPSHOT_I18N_DOMAIN); ?></p><?php
											} else {
												$current_domain_path = parse_url($item_siteurl, PHP_URL_PATH);
												if ($current_domain_path[0] == "/") {
													$current_domain_path = substr($current_domain_path, 1);
												}
												$current_path = apply_filters( 'snapshot_current_path' . PATH_CURRENT_SITE );
												?><?php echo network_site_url() . $current_path; ?><input name="snapshot-blog-id-search" id="snapshot-blog-id-search" value="<?php echo $current_domain_path; ?>" style="width: 20%"/> <span class="spinner"></span><button id="snapshot-blog-id-lookup"><?php _e('Lookup', SNAPSHOT_I18N_DOMAIN); ?></button><button id="snapshot-blog-id-cancel"><?php _e('Cancel', SNAPSHOT_I18N_DOMAIN); ?></button><p class="description"><?php _e('Enter the blog path "site1" or blog ID "22".', SNAPSHOT_I18N_DOMAIN); ?></p><?php
											}
										?>
									</span>
									<?php } ?></li>
								<?php /* ?>
								<li><span class="snapshot-field-label"><?php _e('Home URL:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-new-blogurl"><?php echo get_option('home'); ?></span></li>
								<?php */ ?>
								<?php
									if (is_multisite()) {
										if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
											$item_db_name = '';
										} else {
											$item_db_name = DB_NAME;
										}
									} else {
										$item_db_name = DB_NAME;
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Database Name:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-new-db-name" class="snapshot-field-value"><?php echo $item_db_name; ?></span></li>

								<?php
									if (is_multisite()) {
										if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
											$item_db_base_prefix = '';
										} else {
											$item_db_base_prefix = $wpdb->base_prefix; //$wpdb->get_blog_prefix($item['blog-id']);
										}
									} else {
										$item_db_base_prefix = $wpdb->base_prefix;
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Database Base Prefix:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-new-db-base-prefix"  class="snapshot-field-value"><?php echo $item_db_base_prefix; ?></span></li>

								<?php
									if (is_multisite()) {
										if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
											$item_db_prefix = '';
										} else {
											$item_db_prefix = $wpdb->get_blog_prefix($item['MANIFEST']['WP_BLOG_ID']);
										}
									} else {
										$item_db_prefix = $wpdb->prefix;
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Database Prefix:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-new-db-prefix"  class="snapshot-field-value"><?php echo $item_db_prefix; ; ?></span></li>


								<?php
									if (is_multisite()) {
										if ((!$item['blog-id']) && (isset($item['IMPORT']))) {
											$item_upload_path = '';
										} else {
											$item_upload_path = snapshot_utility_get_blog_upload_path($item['blog-id']);
										}
									} else {
										$item_upload_path = snapshot_utility_get_blog_upload_path($blog_id);
									}
								?>
								<li><span class="snapshot-field-label"><?php _e('Upload Path:', SNAPSHOT_I18N_DOMAIN); ?></span> <span style="font-weight: bold" id="snapshot-new-upload-path" class="snapshot-field-value"><?php echo $item_upload_path; ?></span></li>
							</ul>
						</td>
					</tr>
					</table>
				</div>
			</div>
			<?php
		}

		/**
		 * Metabox Content for Snapshot Folder
		 *
		 * @since 1.0.0
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_metabox_show_folder_location() {
			global $wpmudev_snapshot;

			?>
			<form action="?page=snapshots_settings_panel" method="post">
				<input type="hidden" name="snapshot-action" value="settings-update" />
				<input type="hidden" name="snapshot-sub-action" value="backupFolder" />

				<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

				<p><?php _ex("Set a destination folder for your snapshots. This folder will be created inside your site's media upload folder.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?> /wp-content/uploads/snapshots</p>

				<p><?php _ex("Optionally, you can attempt to set the snapshot folder to a directory outside of your web site. This may not be available depending on your hosting configuration. Check with your hosting provider. If you change the folder to be outside of your web site you will not be able to access the archive via this plugin. You will need to use FTP or some other solution provided by your hosting provider.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

				<p><?php _ex("If you do change the Snapshot folder, the existing folder will be moved/renamed to the new value. <strong>The new folder must not already exist.</strong>", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

				<table class="form-table snapshot-settings-folder-locations">
				<tr class="form-field" >
					<th scope="row">
						<label for="snapshot-settings-backupFolder"><?php _e('Backup Folder', SNAPSHOT_I18N_DOMAIN); ?></label>
					</th>
					<td>
						<input type="text" name="backupFolder" id="snapshot-settings-backupFolder" value="<?php echo $wpmudev_snapshot->config_data['config']['backupFolder']; ?>" />
						<p class="description"><?php
							printf(__('Default folder is %s. If you change the folder name the previous snapshot files will be moved to the new folder.', SNAPSHOT_I18N_DOMAIN),
								'<code>snapshot</code>'); ?></p>

						<p class="description"><?php _e('Current folder', SNAPSHOT_I18N_DOMAIN); ?> <code><?php
							echo trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull')); ?></code></p>
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td><input class="button-primary" type="submit" value="<?php _e('Save Settings', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
				</table>
			</form>
			<?php

	//		if ((function_exists('crypt')) && (function_exists('base64_encode'))) {

				// Password to be encrypted for a .htpasswd file
				//$clearTextPassword = 'some password';

				// Encrypt password
				//$password = crypt($clearTextPassword, base64_encode($clearTextPassword));

				// Print encrypted password
				//echo "password=[". $password ."]<br />";
	//		}
		}


		/**
		 * Metabox Content for Snapshot segment size
		 *
		 * @since 1.0.2
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_metaboxes_show_segment_size() {
			global $wpmudev_snapshot;
			?>
			<form action="?page=snapshots_settings_panel" method="post">
				<input type="hidden" name="snapshot-action" value="settings-update" />
				<input type="hidden" name="snapshot-sub-action" value="segmentSize" />
				<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

				<p><?php _ex("The Segment Size can be defined as the number of rows to backup per table per request. The Segment Size controls the backup processing when you create a new snapshot. During the backup processing Snapshot will make a request to the server to backup each table. You can see this in the progress meters when you create a new snapshot. In most situations this backup process will attempt to backup the table in one step. But on some server configurations the timeout is set very low or the table size is very large and prevents the backup process from finishing. To control this the Snapshot backup process will breakup the requests into smaller 'chunks of work' requested to the server.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?>

				<p><?php _ex("For example you have a table with 80,000 records. This would take more than the normal 3 minutes or less most servers allow for processing it attempted in a single request. By setting the segment size to 1000 the Snapshot process will breakup the table into 80 small parts. These 1000 records per request should complete within the allow server timeout period.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

				<table class="form-table snapshot-settings-segment-size">
				<tr class="form-field" >
					<th scope="row">
						<label for="snapshot-settings-segment-size"><?php _e('Segment Size', SNAPSHOT_I18N_DOMAIN); ?></label>
					</th>
					<td>
						<input type="text" name="segmentSize" id="snapshot-settings-segment-size" value="<?php
							echo $wpmudev_snapshot->config_data['config']['segmentSize']; ?>" />
						<p class="description"><?php
							printf(__('The segment size is the number of database records to backup per request. It is recommended you keep this at least above 1000.<br />Default segment size is: %s.', SNAPSHOT_I18N_DOMAIN),
								'<code>5000</code>'); ?></p>
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td><input class="button-primary" type="submit" value="<?php _e('Save Settings', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
				</tr>
				</table>
			</form>
			<?php
		}


		function snapshot_metaboxes_show_server_info() {

			global $wpdb, $wp_version;
			global $wpmudev_snapshot;
			?>
			<p><?php _e('The following table shows version information about your server. When contacting support it might be helpful to provide this information along with your specific issues.', SNAPSHOT_I18N_DOMAIN); ?></p>
			<table class="form-table snapshot-settings-server-info">
			<tr class="form-field" >
				<th scope="row">
					<?php _e('WordPress Version', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php
						echo $wp_version;
					?>
				</td>
			</tr>
			<tr class="form-field" >
				<th scope="row">
					<?php _e('PHP Version', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php echo phpversion(); ?>
				</td>
			</tr>
			<tr class="form-field" >
				<th scope="row">
					<?php _e('MySQL Version', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php echo $wpdb->db_version(); ?>
				</td>
			</tr>
			<tr class="form-field" >
				<th scope="row">
					<?php _e('Is Multisite', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php
				if (is_multisite()) {
					$blogs_count = snapshot_utility_get_blogs(true);
					echo __('Yes, Number of Sites', SNAPSHOT_I18N_DOMAIN) .": ". number_format($blogs_count);
				} else {
					_e('No', SNAPSHOT_I18N_DOMAIN);
				}
					?>
				</td>
			</tr>
			<tr class="form-field" >
				<th scope="row">
					<?php _e('WP_CRON', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php
						echo __('Snapshot uses WP_CRON to run automated backups. If you have disabled WP_CRON via your wp-config.php you will not be able to schedule snapshots.', SNAPSHOT_I18N_DOMAIN) ."<br />";
						if ( (defined('DISABLE_WP_CRON')) && (DISABLE_WP_CRON == true)) {
							echo '<span style="color:#FF0000">'. __('WP_CRON - Disabled. Check your wp-config.php for the DISABLE_WP_CRON define. When scheduling snapshots to run you will need to call the wp-cron.php file from an external source', SNAPSHOT_I18N_DOMAIN) .'</span>';
						} else {
							echo __('WP_CRON Enabled.', SNAPSHOT_I18N_DOMAIN);
						}

						if (defined('WP_CRON_LOCK_TIMEOUT')) {
							echo "<br />" . __('WP_CRON Lock timeout: ', SNAPSHOT_I18N_DOMAIN) . WP_CRON_LOCK_TIMEOUT;
						}
					?>
				</td>
			</tr>
<?php /* ?>
			<tr class="form-field" >
				<th scope="row">
					<?php _e('_SESSION', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php
						$session_save_path = session_save_path();
						echo __('Snapshot uses _SESSIONS to store temporary information about database tables and files during the backup and restore processing. Sessions are a default part of PHP.', SNAPSHOT_I18N_DOMAIN) ."<br />";
					?>
					Session save path: <span class="description"><?php echo $session_save_path; ?><br />
					<?php
						if (empty($session_save_path)) {
							echo '<span style="color:#FF0000">'. __('Session save path is empty. This may be ok. Try running snapshot manually.',
							 	SNAPSHOT_I18N_DOMAIN) .'</span><br />';
						} else {
							if (!is_dir($session_save_path)) {
								echo '<span style="color:#FF0000">'. __('Session save path is not a valid directory.', SNAPSHOT_I18N_DOMAIN) .'</span><br />';
							} else {
								echo '<span>'. __('Session save path is a valid directory.', SNAPSHOT_I18N_DOMAIN) .'</span><br />';
							}
							if (!is_writable($session_save_path)) {
								echo '<span style="color:#FF0000">'. __('Session save path is not writeable.', SNAPSHOT_I18N_DOMAIN) .'</span><br />';
							} else {
								echo '<span>'. __('Session save path is writeable.', SNAPSHOT_I18N_DOMAIN) .'</span><br />';
							}
						}
					?>
				</td>
			</tr>
<?php */ ?>
			<tr class="form-field" >
				<th scope="row">
					<?php _e('Folder Permissions', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php
						$folders_array = array(
							$wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull'),
							$wpmudev_snapshot->snapshot_get_setting('backupBackupFolderFull'),
							$wpmudev_snapshot->snapshot_get_setting('backupRestoreFolderFull'),
							$wpmudev_snapshot->snapshot_get_setting('backupLockFolderFull'),
							$wpmudev_snapshot->snapshot_get_setting('backupLogFolderFull')
						);
						sort($folders_array);
						foreach($folders_array as $folder) {

							if (!file_exists($folder)) {
								echo '<span class="snapshot-error">'. __("Missing Folder", SNAPSHOT_I18N_DOMAIN) ." &ndash; ". $folder . "</span><br />";
							} else {
								if (!is_writable($folder))
									echo __("Not Writable ", SNAPSHOT_I18N_DOMAIN);
								else {
									echo __("Writable ", SNAPSHOT_I18N_DOMAIN);
								}
								echo "(". substr(sprintf('%o', fileperms($folder)), -4) . ") &ndash; ". str_replace(ABSPATH, '/', $folder) . "<br />";
							}
						}

					?>
				</td>
			</tr>
			<tr class="form-field" >
				<th scope="row">
					<?php _e('OpenSSL', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php
						echo __('The OpenSSL Apache module is require to make secure connections with destinations like Dropbox and Amazon AWS.', SNAPSHOT_I18N_DOMAIN) ."<br />";

						if (!extension_loaded('openssl')) {
							echo '<span style="color:#FF0000">'. __('OpenSSL not installed.', SNAPSHOT_I18N_DOMAIN) .'</span>';
						} else {
							echo __("OpenSSL installed", SNAPSHOT_I18N_DOMAIN);
						}
					?>
				</td>
			</tr>
			<tr class="form-field" >
				<th scope="row">
					<?php _e('cURL', SNAPSHOT_I18N_DOMAIN); ?>

				</th>
				<td>
					<?php
						echo __('cURL is used when connecting to remote destinations like Dropbox and Amazon AWS.', SNAPSHOT_I18N_DOMAIN) ."<br />";

						if (!extension_loaded('curl')) {
							echo '<span style="color:#FF0000">'. __('cURL not installed.', SNAPSHOT_I18N_DOMAIN) .'</span>';
						} else {
							echo __("cURL installed", SNAPSHOT_I18N_DOMAIN) ."<br />";
							$version = curl_version();

							echo __("Version", SNAPSHOT_I18N_DOMAIN) .": ". $version['version'] ."<br />";
							echo __("SSL Version", SNAPSHOT_I18N_DOMAIN) .": ". $version['ssl_version'] ."<br />";
							echo __("Libz Version", SNAPSHOT_I18N_DOMAIN) .": ". $version['libz_version'] ."<br />";
							echo __("Protocols", SNAPSHOT_I18N_DOMAIN) .": ". join(', ', $version['protocols']) ."<br />";

							//echo "version<pre>"; print_r($version); echo "</pre>";
							//$ssl_supported= ($version['features'] & CURL_VERSION_SSL);


						}
					?>
				</td>
			</tr>
			<tr class="form-field" >
				<th scope="row">
					<strong><?php _e('PHP runtime information', SNAPSHOT_I18N_DOMAIN); ?></strong>
				</th>
				<td>&nbsp;</td>
			</tr>
			<?php
				$php_vars_array = array(
					'safe_mode'					=> 	__("Running PHP in Safe Mode", SNAPSHOT_I18N_DOMAIN),
					'max_execution_time'		=> 	__("Max Execution Time (seconds)", SNAPSHOT_I18N_DOMAIN),
					'magic_quotes_gpc'			=>	__("Magic Quotes", SNAPSHOT_I18N_DOMAIN),
					'error_reporting'			=>	__("Error Reporting", SNAPSHOT_I18N_DOMAIN),
					'display_errors'			=>	__("Display Errors", SNAPSHOT_I18N_DOMAIN),
					'memory_limit'				=>	__("Memory Limit", SNAPSHOT_I18N_DOMAIN),
					'zlib.output_compression'	=>	__("ZLib Compression", SNAPSHOT_I18N_DOMAIN),
					'open_basedir'				=> 	__("Open Basedir", SNAPSHOT_I18N_DOMAIN),
					'safe_mode'					=>	__("Safe Mode", SNAPSHOT_I18N_DOMAIN)

				);
				asort($php_vars_array);
				?>
					<?php
						foreach($php_vars_array as $php_var => $php_label) {
							$php_val = ini_get($php_var);
							if (!$php_val) $php_val = "Off";

							?>
							<tr class="form-field" >
								<td><?php echo $php_label ?></td>
								<td><?php
									if ($php_var == "max_execution_time") {
										echo $php_val;
										if (snapshot_utility_check_server_timeout()) {
											_e(" The value displayed can be adjusted by Snapshot PHP scripts.", SNAPSHOT_I18N_DOMAIN);
										} else {
											_e(" The value displayed cannot be adjusted by Snapshot PHP scripts.", SNAPSHOT_I18N_DOMAIN);
										}
									} else if ($php_var == "memory_limit") {
										//echo " - PHP 'memory_limit'";
										if (defined('WP_MEMORY_LIMIT')) {
											echo WP_MEMORY_LIMIT .' - WP_MEMORY_LIMIT defined by WordPress <a target="_blank"
 href="http://codex.wordpress.org/Editing_wp-config.php#Increasing_memory_allocated_to_PHP">wp-config.php</a>.';
										}

										if (defined('WP_MAX_MEMORY_LIMIT')) {
											echo "<br />". WP_MAX_MEMORY_LIMIT .' - WP_MAX_MEMORY_LIMIT defined automatically by WordPress';
										} else {
											echo "<br />". WP_MAX_MEMORY_LIMIT .' - not defined';
										}

									} else if ($php_var == "error_reporting") {
										echo $php_val;
										$errorStr = array();
										error_reporting(E_ERROR | E_WARNING | E_CORE_ERROR);
										$current_error = error_reporting();
										if ( (defined('E_ERROR')) && ($current_error & E_ERROR) ) $errorStr[] = "E_ERROR";
										if ( (defined('E_WARNING')) && ($current_error & E_WARNING)) $errorStr[] = "E_WARNING";
										if ((defined('E_PARSE')) && ($current_error & E_PARSE)) $errorStr[] = "E_PARSE";
										if ((defined('E_NOTICE')) && ($current_error & E_NOTICE)) $errorStr[] = "E_NOTICE";

										//if ((defined('E_CORE_ERROR')) && ($current_error & E_CORE_ERROR)) $errorStr[] = "E_CORE_ERROR";
										//if ((defined('E_CORE_WARNING')) && ($current_error & E_CORE_WARNING)) $errorStr[] = "E_CORE_WARNING";

										//if ((defined('E_COMPILE_ERROR')) && ($current_error & E_COMPILE_ERROR)) $errorStr[] = "E_COMPILE_ERROR";
										//if ((defined('E_COMPILE_WARNING')) && ($current_error & E_COMPILE_WARNING)) $errorStr[] = "E_COMPILE_WARNING";

										//if ((defined('E_USER_ERROR')) && ($current_error & E_USER_ERROR)) $errorStr[] = "E_USER_ERROR";
										//if ((defined('E_USER_WARNING')) && ($current_error & E_USER_WARNING)) $errorStr[] = "E_USER_WARNING";
										//if ((defined('E_USER_NOTICE')) && ($current_error & E_USER_NOTICE)) $errorStr[] = "E_USER_NOTICE";

										//if ((defined('E_STRICT')) && ($current_error & E_STRICT)) $errorStr[] = "E_STRICT";
										//if ((defined('E_RECOVERABLE_ERROR')) && ($current_error & E_RECOVERABLE_ERROR)) $errorStr[] = "E_RECOVERABLE_ERROR";
										//if ((defined('E_DEPRECATED')) && ($current_error & E_DEPRECATED)) $errorStr[] = "E_DEPRECATED";
										//if ((defined('E_USER_DEPRECATED')) && ($current_error & E_USER_DEPRECATED)) $errorStr[] = "E_USER_DEPRECATED";

										if (count($errorStr)) {
											echo " - ". join(', ', $errorStr);
										}
									} else {
										echo $php_val;
									}

								?></td>
							</tr>
							<?php
						}
					?>
			</tr>
			</table>
			<?php
		}

		function snapshot_metaboxes_show_memory_limit() {
			global $wpmudev_snapshot;
			?>
			<form action="?page=snapshots_settings_panel" method="post">
				<input type="hidden" name="snapshot-action" value="settings-update" />
				<input type="hidden" name="snapshot-sub-action" value="memoryLimit" />
				<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

				<p><?php _ex("The Memory Limit setting can allow Snapshot to use more or limit the amount of memory used by Snapshot during the backup or restore processing. The value set here can be higher than the value you would define for WP_MEMORY_LIMIT in you wp-config.php. This is because working with zip archives takes more memory than just serving a web page. For any moderate size site this value should be at least <strong>256M</strong>.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

				<p><?php _ex("<strong>If left blank, Snapshot will compare the php.ini (memory_limit), WP_MEMORY_LIMIT and WP_MAX_MEMORY_LIMIT settings and use the largest value.</strong>", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN); ?></p>

					<p><?php
					$memory_limits = array();
					$memory_limit = ini_get('memory_limit');
					$memory_limits[$memory_limit] = snapshot_utility_size_unformat($memory_limit);
					/* ?><br /><?php echo $memory_limit; ?>: <?php _e('php.ini (memory_limit)', SNAPSHOT_I18N_DOMAIN); */

					if (defined('WP_MEMORY_LIMIT')) {
						$memory_limit = WP_MEMORY_LIMIT;
						$memory_limits[$memory_limit] = snapshot_utility_size_unformat($memory_limit);
						/* ?><br /><?php echo $memory_limit; ?>: <?php _e('WP_MEMORY_LIMIT', SNAPSHOT_I18N_DOMAIN); */
					}

					if (defined('WP_MAX_MEMORY_LIMIT')) {
						$memory_limit = WP_MAX_MEMORY_LIMIT;
						$memory_limits[$memory_limit] = snapshot_utility_size_unformat($memory_limit);
						/* ?><br /><?php echo $memory_limit; ?>: <?php _e('WP_MAX_MEMORY_LIMIT', SNAPSHOT_I18N_DOMAIN); */
					}

					?></p><?php
					arsort($memory_limits);
					//echo "memory_limits<pre>"; print_r($memory_limits); echo "</pre>";

					foreach($memory_limits as $memory_key => $memory_value) {
						$memoryLimit = $memory_key;
						break;
					}

					@ini_set('memory_limit', $memoryLimit);
					$memoryLimit_get = ini_get('memory_limit');

					if (ini_get('memory_limit') !== $memoryLimit) {
						?>
						<p style="color: #FF0000;"><?php _ex("Warning: Unable to update the 'memory_limit' via the PHP function init_set. This means in order in to increased the allowed memory limit for Snapshot you will need to make the change directly in your php.ini file. Contact your host provider for details.", 'Snapshot page description', SNAPSHOT_I18N_DOMAIN) ?></p>
						<?php
					} else {
						if ((!isset($wpmudev_snapshot->config_data['config']['memoryLimit'])) || (empty($wpmudev_snapshot->config_data['config']['memoryLimit']))) {
							$wpmudev_snapshot->config_data['config']['memoryLimit'] = $memoryLimit;
						}

						?>
						<table class="form-table snapshot-settings-server-info">
						<tr class="form-field" >
							<th scope="row">
								<label for="snapshot-settings-memory-limit"><?php _e('Memory Limit', SNAPSHOT_I18N_DOMAIN); ?></label>
							</th>
							<td>
								<input type="text" name="memoryLimit" id="snapshot-settings-memory-limit" value="<?php  echo $wpmudev_snapshot->config_data['config']['memoryLimit']; ?>" />
								<p class="description"><?php
									echo __('Important to include the size M = Megabytes, G = Gigbytes as in 256M, 1G, etc.', SNAPSHOT_I18N_DOMAIN); ?></p>
							</td>
						</tr>
						<tr>
							<td>&nbsp;</td>
							<td>
								<input class="button-primary" type="submit" value="<?php _e('Save Settings', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
						</tr>
						</table>
						<?php
					}
				?>
				</form>
			<?php
		}

		function snapshot_metaboxes_show_config_export() {

			?>
			<p><?php _e('You can export your current Snapshot configuration to save offsite.', SNAPSHOT_I18N_DOMAIN); ?></p>
			<table class="form-table">
			<tr>
				<th><?php _e('Export', SNAPSHOT_I18N_DOMAIN); ?></th>
				<td><input class="button-primary" type="submit" value="<?php _e('Export', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
			</tr>
			</table>
			<?php
		}

/*
		function snapshot_metaboxes_show_archives_import() {
			global $wpmudev_snapshot;
			?>
			<form action="?page=snapshots_settings_panel" method="post">
				<input type="hidden" name="snapshot-action" value="archives-import" />
				<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

				<p><?php _e('Scan your Snapshot base folder for extra archives. If you lost your Snapshot settings or need to import Snapshot archives. Place the zip archive into the Snapshot main folder. Then click the Scan button to import the archives.', SNAPSHOT_I18N_DOMAIN); ?></p>
				<table class="form-table">
				<tr>
					<th><?php _e('Import', SNAPSHOT_I18N_DOMAIN); ?></th>
					<td><input class="button-primary" type="submit" value="<?php _e('Scan', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
				</tr>
				</table>
			</form>
			<?php
		}
*/

		/**
		 * Metabox Global File Exclusions - Allows setting exclude patterns to be used by all snapshot instances.
		 *
		 * @since 2.0.3
		 * @uses metaboxes setup in $this->admin_menu_proc()
		 * @uses $this->config_data['items']
		 *
		 * @param none
		 * @return none
		 */
		function snapshot_metaboxes_show_global_file_excludes() {
			global $wpmudev_snapshot;
			?>
			<form action="?page=snapshots_settings_panel" method="post">
				<input type="hidden" name="snapshot-action" value="settings-update" />
				<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

				<p><?php _e('Use this setting to create global exclusions. This means the excluded files or directories will be excluded automatically from all snapshot configurations. You can also setup exclusions specific to a single snapshot via the configuration screen. The exclude logic uses pattern matching. So instead of entering the complete server pathname for a file or directory you can use simply use the filename of parent directory. For example to exclude the theme twentyten you could enter this one of many ways: twentyten,  themes/twentyten /wp-content/themes/twentyten, /var/www/wp-content/themes/twentyten. <strong>Regular Expression are not allowed at this time</strong>.', SNAPSHOT_I18N_DOMAIN); ?></p>

				<table class="form-table snapshot-settings-segment-size">
				<tr class="form-field" >
					<th scope="row">
						<label for="snapshot-files-ignore"><?php _e('Global Exclusions', SNAPSHOT_I18N_DOMAIN); ?></label>
					</th>
					<td>
						<textarea name="filesIgnore" id="filesIgnore" cols="20" rows="5"><?php
							if ((isset($wpmudev_snapshot->config_data['config']['filesIgnore']))
							 && (is_array($wpmudev_snapshot->config_data['config']['filesIgnore']))
							 && (count($wpmudev_snapshot->config_data['config']['filesIgnore']))) {
								echo implode("\n", $wpmudev_snapshot->config_data['config']['filesIgnore']);
							}
						?></textarea>
						<p class="description"><?php echo sprintf(__('The Snapshot directory %s is automatically excluded from all snapshots',
						 	SNAPSHOT_I18N_DOMAIN),
							'<code>' . trailingslashit($wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull')) .'</code>'); ?>
					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td><input class="button-primary" type="submit" value="<?php _e('Save Settings', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
				</tr>
				</table>
			</form>
			<?php
		}


		function snapshot_metaboxes_show_global_error_reporting() {
			global $wpmudev_snapshot;

			$error_reporting_errors = array(
				E_ERROR	=>	__('Errors - Fatal run-time errors. These indicate errors that can not be recovered from, such as a memory allocation problem. Execution of the script is halted.', SNAPSHOT_I18N_DOMAIN),
				E_WARNING	=>	__('Warnings - Run-time warnings (non-fatal errors). Execution of the script is not halted.', SNAPSHOT_I18N_DOMAIN),
				E_NOTICE	=>	__('Notices - Run-time notices. Indicate that the script encountered something that could indicate an error, but could also happen in the normal course of running a script.', SNAPSHOT_I18N_DOMAIN),
			);

			?>
			<form action="?page=snapshots_settings_panel" method="post">
				<input type="hidden" name="snapshot-action" value="settings-update" />
				<input type="hidden" name="snapshot-sub-action" value="errorReporting" />
				<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

				<p><?php _e("This section controls how Snapshot will handle an error condition during the backup / restore processing. There are two columns for each type of error. The 'stop' column controls if Snapshot will abort the current process should that type of error be reached. The 'log' column controls if the type of error and details will be written to the processing log. In most cases you want to set 'stop' for Errors only. And set 'log' for all.", SNAPSHOT_I18N_DOMAIN); ?></p>

				<table class="form-table snapshot-settings-error-reporting" width="100%">
				<tr>
					<th style="width:5%;"><?php _e('Stop', SNAPSHOT_I18N_DOMAIN); ?></th>
					<th style="width:5%;"><?php _e('Log', SNAPSHOT_I18N_DOMAIN); ?></th>
					<th style="width:90%;"><?php _e('Error Description', SNAPSHOT_I18N_DOMAIN); ?></th>
				</tr>
				<?php
					foreach($error_reporting_errors as $error_key => $error_label) {
						?>
						<tr>
							<td>
								<input type="checkbox" name="errorReporting[<?php echo $error_key; ?>][stop]" <?php
									if (isset($wpmudev_snapshot->config_data['config']['errorReporting'][$error_key]['stop'])) {
										echo ' checked="checked" ';
									}
								?> />
							</td>
							<td>
								<input type="checkbox" name="errorReporting[<?php echo $error_key; ?>][log]" <?php
									if (isset($wpmudev_snapshot->config_data['config']['errorReporting'][$error_key]['log'])) {
										echo ' checked="checked" ';
									}
								?> />
							</td>
							<td><label><?php echo $error_label; ?></label></td>
						</tr>
						<?php
					}
				?>
				<tr colspan="3">
					<td><input class="button-primary" type="submit" value="<?php _e('Save Settings', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
				</tr>
				</table>
			</form>
			<?php
		}

		function snapshot_metaboxes_show_zip_library() {
			global $wpmudev_snapshot;
			?>
			<form action="?page=snapshots_settings_panel" method="post">
				<input type="hidden" name="snapshot-action" value="settings-update" />
				<input type="hidden" name="snapshot-sub-action" value="zipLibrary" />
				<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

				<p><?php _e("This section lets you select which zip compression library to use. The zip library is used during the backup and restore processing by Snapshot. Chose from the options below.", SNAPSHOT_I18N_DOMAIN); ?></p>

				<table class="form-table snapshot-settings-zip-library">
				<tr>
					<th><?php _e('Select Compression Option', SNAPSHOT_I18N_DOMAIN); ?></th>

					<td>
						<?php
							if (class_exists('ZipArchive')) {
								if (!isset($wpmudev_snapshot->config_data['config']['zipLibrary']))
									$wpmudev_snapshot->config_data['config']['zipLibrary'] = 'ZipArchive';
								?>
								<input type="radio" name="zipLibrary" id="snapshot-settings-zip-library-ziparchive" value="ZipArchive" <?php
									if ($wpmudev_snapshot->config_data['config']['zipLibrary'] == 'ZipArchive') {
										echo ' checked="checked" ';} ?> /> <?php
									echo __('ZipArchive - In most cases ZipArchive is built into PHP and generally faster than PclZIP. This library primarily uses files instead of memory for temporary storage when compressing large files.',
									 SNAPSHOT_I18N_DOMAIN); ?><br />
								<?php
							} else {
								if (!isset($wpmudev_snapshot->config_data['config']['zipLibrary']))
									$wpmudev_snapshot->config_data['config']['zipLibrary'] = 'PclZip';
							}
						?>
						<input type="radio" name="zipLibrary" id="snapshot-settings-zip-library-ziparchive" value="PclZip" <?php
							if ($wpmudev_snapshot->config_data['config']['zipLibrary'] == 'PclZip') {
								echo ' checked="checked" ';} ?> /> <?php
							echo __('PclZip - is part of the WordPress core libraries. It will be slower than built-in PHP functions like ZipArchive. This library  uses memory instead of files for temporary storage when compressing large files.',
							 SNAPSHOT_I18N_DOMAIN); ?><br />

					</td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td>
						<input class="button-primary" type="submit" value="<?php _e('Save Settings', SNAPSHOT_I18N_DOMAIN); ?>" /></td>
				</tr>
				</table>
			</form>
			<?php
		}


		function snapshot_metabox_show_restore_tables_options($title, $item, $data_item_key) {

			global $wpdb;

			if ((!$item) || (!isset($item['data'][$data_item_key]))) {
				return;
			}

			$data_item = $item['data'][$data_item_key];
//			echo "data_item<pre>"; print_r($data_item); echo "</pre>";

			//echo "tables-sections<pre>"; print_r($item['data'][$data_item_key]['tables-sections']); echo "</pre>";
			if (is_multisite()) {
				if ((isset($item['data'][$data_item_key]['tables-sections'])) && (count($item['data'][$data_item_key]['tables-sections']))) {
					foreach($item['data'][$data_item_key]['tables-sections'] as $tables_section => $tables_sections_data) {
						foreach($tables_sections_data as $table_name_idx => $table_name) {
							$table_name_part = str_replace($item['MANIFEST']['WP_DB_PREFIX'], '', $table_name);
							//echo "table_name_part=[". $table_name_part ."] [". $table_name ."]<br />";

							if (array_search($table_name_part, $wpdb->global_tables) !== false) {
								if (!isset($item['data'][$data_item_key]['tables-sections']['global']))
									$item['data'][$data_item_key]['tables-sections']['global'] = array();
								$item['data'][$data_item_key]['tables-sections']['global'][$table_name] = $table_name;

								unset($item['data'][$data_item_key]['tables-sections'][$tables_section][$table_name]);

							}
						}
					}
				}
			}
			if ((!isset($data_item['tables-sections'])) || (empty($data_item['tables-sections'])))
				return;

			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">
					<?php
						if (is_multisite()
						 && (isset($item['data'][$data_item_key]['tables-sections']['global']))
						 && (count($item['data'][$data_item_key]['tables-sections']['global']))) {
							?><p class="snapshot-error"><?php _e("Restore Note: The archive you are about to restore includes the global database tables users and/or usermeta. Normally, you do not want to restore these tables unless your site is broken. To restore either of these database tables you must select them from the 'Restore selected database tables' section below. The data contained within these tables will be merged with the current global tables", SNAPSHOT_I18N_DOMAIN); ?><?php
						}

						if ((!is_multisite()) && ($item['MANIFEST']['WP_DB_PREFIX'] != $wpdb->prefix)) {
							?><p class="snapshot-error"><?php printf(__("Restore Note: The archive contains tables names with a different database prefix ( %s ) than this site ( %s ). The tables restored will automatically be renamed to the site prefix", SNAPSHOT_I18N_DOMAIN), $item['MANIFEST']['WP_DB_PREFIX'], $wpdb->prefix); ?><?php

						}
					?>
					<table class="form-table snapshot-backup-tables">
					<tr class="">
						<td>
							<p><?php _e('Select the database tables restore option.', SNAPSHOT_I18N_DOMAIN); ?></p>
							<ul>
								<li><input type="radio" class="snapshot-tables-option" id="snapshot-tables-option-all" checked="checked"
									value="all" name="snapshot-tables-option"> <label
									for="snapshot-tables-option-all"><?php
										if (is_multisite())
											_e('Restore <strong>all</strong> blog database tables contained in this archive <strong>(excluding global tables users & usermeta)</strong>', SNAPSHOT_I18N_DOMAIN);
										else
											_e('Restore <strong>all</strong> blog database tables contained in this archive ', SNAPSHOT_I18N_DOMAIN);
										?></label></li>

								<li><input type="radio" class="snapshot-tables-option" id="snapshot-tables-option-none"
									value="none" name="snapshot-tables-option"> <label
									for="snapshot-tables-option-none"><?php
										_e('Do not restore any database tables', SNAPSHOT_I18N_DOMAIN); ?></label></li>


								<li><input type="radio" class="snapshot-tables-option" id="snapshot-tables-option-selected" value="selected"
									name="snapshot-tables-option"> <label
									for="snapshot-tables-option-selected"><?php
										_e('Restore <strong>selected</strong> database tables', SNAPSHOT_I18N_DOMAIN); ?></label>

									<div id="snapshot-selected-tables-container" style="margin-left: 30px; padding-top: 20px; display: none;">
										<?php
											$tables_sets_idx = array(
												'global'	=>	__("WordPress Global Tables", SNAPSHOT_I18N_DOMAIN),
												'wp'		=>	__("WordPress Blog Tables", SNAPSHOT_I18N_DOMAIN),
												'non'		=>	__("Non-WordPress Tables", SNAPSHOT_I18N_DOMAIN),
												'other'		=>	__("Other Tables", SNAPSHOT_I18N_DOMAIN),
											);

											//echo "item<pre>"; print_r($item); echo "</pre>";

											foreach($tables_sets_idx as $table_set_key => $table_set_title)	{

												if (isset($item['data'][$data_item_key]['tables-sections'][$table_set_key])) {
													$display_set = 'block';
												} else {
													$display_set = 'none';
												}
												?>
												<div id="snapshot-tables-<?php echo $table_set_key ?>-set"
														class="snapshot-tables-set" style="display: <?php echo $display_set; ?>">

													<p class="snapshot-tables-title"><?php echo $table_set_title; ?><?php
														if ((isset($item['data'][$data_item_key]['tables-sections'][$table_set_key]))
														 && (count($item['data'][$data_item_key]['tables-sections'][$table_set_key]))) {
															?> <a class="button-link snapshot-table-select-all" href="#"
																id="snapshot-table-<?php echo $table_set_key ?>-select-all"><?php
																_e('Select all', SNAPSHOT_I18N_DOMAIN); ?></a><?php
														} ?>
													</p>
													<?php
														if ((is_multisite()) && ($table_set_key == "global")) {
															?><p class="snapshot-error"><?php _e('When restoring users and usermeta records under a Multisite environment there are a few limitations. Please read the following carefully', SNAPSHOT_I18N_DOMAIN); ?></p>
															<ol class="snapshot-error">
																<li><?php _e("If restoring to the primary blog ALL user entries will be replaced!", SNAPSHOT_I18N_DOMAIN); ?></li>
																<li><?php _e("If restoring to a non-primary blog, the user's ID and user_name fields are checked against existing users.", SNAPSHOT_I18N_DOMAIN); ?>
																	<ul>
																		<li><?php _e("- If a match is not found a new user will be created. This means a new user ID will be assigned.", SNAPSHOT_I18N_DOMAIN); ?></li>
																		<li><?php _e("- If a match is found but the user ID is different. The found user ID will be used.", SNAPSHOT_I18N_DOMAIN); ?></li>
																	</ul>
																</li>
																<li><?php _e("If the restored user ID is changed, Snapshot will update usermeta, posts and comments records with the new user ID. A new usermeta record will be added with the key '_old_user_id' with the value of the previous user ID. Snapshot cannot attempt updates to other tables like BuddyPress where the user ID fields are not known. These will need to be updated manually."); ?></li>
															</ol>
															<?php

														}
													?>
													<?php

														if ((isset($item['data'][$data_item_key]['tables-sections'][$table_set_key]))
														 && (count($item['data'][$data_item_key]['tables-sections'][$table_set_key]))) {

															$tables = $item['data'][$data_item_key]['tables-sections'][$table_set_key];

															?><ul class="snapshot-table-list" id="snapshot-table-list-<?php echo $table_set_key; ?>"><?php
															foreach ($tables as $table_key => $table_name) {

																if ($table_set_key != "global")
																	$checked = ' checked="checked" ';
																else {
																	if (is_multisite())
																		$checked = '';
																	else
																		$checked = ' checked="checked" ';
																}

																?><li><input type="checkbox" <?php echo $checked; ?> class="snapshot-table-item"
																	id="snapshot-tables-<?php echo $table_key; ?>" value="<?php echo $table_key; ?>"
																	name="snapshot-tables[<?php echo $table_set_key; ?>][<?php echo $table_key; ?>]"> <label
																	for="snapshot-tables-<?php echo $table_key; ?>"><?php
																	echo $table_name; ?></label></li><?php
															}
															?></ul><?php
														} else {
															?><p><?php _e('No tables', SNAPSHOT_I18N_DOMAIN); ?></p><?php
														}
													?>
												</div><?php
											}
										?>
									</div>
								</li>

							</ul>
						</td>
					</tr>
					</table>
				</div><!-- end inside -->
			</div><!-- end postbox -->
			<?php
		}

		function snapshot_metabox_show_restore_files_options($title, $item, $data_item_key) {

			if ((!$item) || (!isset($item['data'][$data_item_key]))) {
				return;
			}

			$data_item = $item['data'][$data_item_key];
//			echo "data_item<pre>"; print_r($data_item); echo "</pre>";
			//echo "item[MANIFEST]<pre>"; print_r($item['MANIFEST']); echo "</pre>";

			if ((!isset($data_item['files-sections'])) || (empty($data_item['files-sections'])))
				return;
			?>
			<div class="postbox">

				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">
					<?php
						if (isset($data_item['files-sections'])) {
							if ( (array_search('config', $item['data'][$data_item_key]['files-sections']) !== false)
							  || (array_search('htaccess', $item['data'][$data_item_key]['files-sections']) !== false) ) {
								?><p class="snapshot-error"><?php _e("Restore Note: The archive you are about to restore includes the .htaccess and/or the wp-config.php files. Normally you do not want to restore these files unless your site is broken. To restore either of these files you must select them from the'include selected files' section below.", SNAPSHOT_I18N_DOMAIN); ?></p><?php
							}
						}
					?>
					<table class="form-table snapshot-backup-files">
					<tr class="">
						<td class="left">
							<p><?php _e('Select the File sections to restore:', SNAPSHOT_I18N_DOMAIN); ?></p>

							<ul>
								<li><input type="radio" class="snapshot-files-option" id="snapshot-files-option-all" value="all"
									checked="checked" name="snapshot-files-option"> <label
									for="snapshot-files-option-all"><?php _e('Restore all files', SNAPSHOT_I18N_DOMAIN); ?></label>
									<?php
										if ( (array_search('config', $item['data'][$data_item_key]['files-sections']) !== false)
									  	  || (array_search('htaccess', $item['data'][$data_item_key]['files-sections']) !== false) ) {
											?> <span><strong><?php _e('(excluding .htaccess & wp-config.php files)', SNAPSHOT_I18N_DOMAIN); ?></strong></span><?php
										}
									?>
								</li>

								<li><input type="radio" class="snapshot-files-option" id="snapshot-files-option-none" value="none"
									name="snapshot-files-option"> <label for="snapshot-files-option-none"><?php
										_e('Do not include files', SNAPSHOT_I18N_DOMAIN); ?></label></li>

								<li class="snapshot-backup-files-sections-main-only"><input type="radio" class="snapshot-files-option"
									 id="snapshot-files-option-selected" value="selected"
									name="snapshot-files-option"> <label
									for="snapshot-files-option-selected"><?php
										_e('Include <strong>selected</strong> files:', SNAPSHOT_I18N_DOMAIN); ?></label>

									<div id="snapshot-selected-files-container" style="margin-left: 30px; padding-top: 10px; display: none;">

										<?php if (is_multisite()) { ?>
											<p class="snapshot-error"><?php _e("Restore Note: The files wp-config.php and .htaccess can only be restored for the primary site. Even then it is not advisable to restore these file for a working Multisite installation.", SNAPSHOT_I18N_DOMAIN); ?></p>
										<?php } ?>

										<ul id="snapshot-select-files-option">
										<?php if (array_search('themes', $item['data'][$data_item_key]['files-sections']) !== false) {?>
											<li id="snapshot-files-option-themes-li"><input type="checkbox" class="snapshot-backup-sub-options" checked="checked"
												id="snapshot-files-option-themes" value="themes"
												name="snapshot-files-sections[themes]"> <label
												for="snapshot-files-option-themes"><?php _e('Themes', SNAPSHOT_I18N_DOMAIN); ?></label></li>
										<?php } ?>
										<?php if (array_search('plugins', $item['data'][$data_item_key]['files-sections']) !== false) {?>
											<li id="snapshot-files-option-plugins-li"><input type="checkbox" class="snapshot-backup-sub-options" checked="checked"
												id="snapshot-files-option-plugins" value="plugins"
												name="snapshot-files-sections[plugins]"> <label
												for="snapshot-files-option-plugins"><?php _e('Plugins', SNAPSHOT_I18N_DOMAIN); ?></label></li>
										<?php } ?>
										<?php if (array_search('media', $item['data'][$data_item_key]['files-sections']) !== false) {?>
											<li id="snapshot-files-option-media-li"><input type="checkbox" class="snapshot-backup-sub-options" checked="checked"
												id="snapshot-files-option-media" value="media"
												name="snapshot-files-sections[media]"> <label
												for="snapshot-files-option-media"><?php _e('Media Files', SNAPSHOT_I18N_DOMAIN); ?></label></li>
										<?php } ?>
										<?php if (array_search('config', $item['data'][$data_item_key]['files-sections']) !== false) {?>
											<li id="snapshot-files-option-config-li"><input type="checkbox" class="snapshot-backup-sub-options"
												id="snapshot-files-option-config" value="config"
												name="snapshot-files-sections[config]"> <label
												for="snapshot-files-option-config"><?php _e('wp-config.php', SNAPSHOT_I18N_DOMAIN); ?></label></li>
										<?php } ?>
										<?php if (array_search('htaccess', $item['data'][$data_item_key]['files-sections']) !== false) {?>
											<li id="snapshot-files-option-htaccess-li"><input type="checkbox" class="snapshot-backup-sub-options"
												id="snapshot-files-option-htaccess" value="htaccess"
												name="snapshot-files-sections[htaccess]"> <label
												for="snapshot-files-option-htaccess"><?php _e('.htaccess', SNAPSHOT_I18N_DOMAIN); ?></label></li>
										<?php } ?>
										</ul>
									</div>
								</li>
							</ul>
						</td>
					</tr>
					</table>
				</div>
			</div>
			<?php
		}

		function snapshot_metaboxes_show_destination_items() {
			global $wpmudev_snapshot;

			//echo "config_data<pre>"; print_r($wpmudev_snapshot->config_data); echo "</pre>";
			?>
			<form action="?page=snapshots_settings_panel" method="post">
				<input type="hidden" name="snapshot-action" value="settings-update" />
				<input type="hidden" name="snapshot-sub-action" value="destination-items" />

				<?php wp_nonce_field('snapshot-settings', 'snapshot-noonce-field'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>

			<?php

			$destinationClasses = $wpmudev_snapshot->snapshot_get_setting('destinationClasses');
			if ($destinationClasses) {
				//echo "destinationClasses<pre>"; print_r($destinationClasses); echo "</pre>";
				ksort($destinationClasses);
				?><ul><?php
				foreach($destinationClasses as $_dkey => $_dobj) {
					?><li><input type="checkbox" name="" /> <?php echo $_dobj->name_display ?></li><?php
				}
				?></ul><?php
			}

			?></form><?php
		}

		function snapshot_metaboxes_show_import($title='') {
			global $wpmudev_snapshot;

			?>
			<div class="postbox">
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">
					<ul>

						<li><strong><?php _e('Remote Archives', SNAPSHOT_I18N_DOMAIN) ?></strong>:
							<?php if (function_exists('curl_version')) { ?>
								<p><?php _e('The import process can import an archive from a remote system server via FTP, Amazon S3 or Dropbox. The remote archive <strong>must</strong> by publicly accessible as this import process does not yet support authentication. See notes below on specific services.', SNAPSHOT_I18N_DOMAIN); ?></p>

								<p class="description"><?php _e('<strong>Remote FTP:</strong> When downloading from a remote FTP server you must ensure the file is moved to a location where it will be accessible via a simple http:// or https:// URL.', SNAPSHOT_I18N_DOMAIN) ?></p>

								<p class="description"><?php _e('<strong>Dropbox:</strong> If you are attempting to download a Dropbox Snapshot archive written to the App/WPMU DEV Snapshot you first need to copy the file to a public folder within your Dropbox account before grabbing the public link.', SNAPSHOT_I18N_DOMAIN) ?></p>
								<p class="description"><?php _e('<strong>Amazon S3:</strong> When downloading a file from S3 you need to ensure the file is public. ', SNAPSHOT_I18N_DOMAIN) ?></p>
							<?php } else {
								?><p><strong><?php echo sprintf(__('Import cannot be used to retreive remote files because your PHP install does not have cURL installed. This means you will need to manually upload the file to your server into the snapshot archives directory <em>%s</em>', SNAPSHOT_I18N_DOMAIN), $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull')) ?></strong></p>
							<?php } ?>
						</li>
						<li>
							<strong><?php _e('Local Archives', SNAPSHOT_I18N_DOMAIN) ?></strong>:
							<p><?php echo sprintf(__('For archives already on your server but not showing in the All Snapshots listing you can simply submit this form without entering a value below. This will scan the snapshot archivs directory (<em>%s</em>) for any missing archives and add them to the listing.', SNAPSHOT_I18N_DOMAIN), $wpmudev_snapshot->snapshot_get_setting('backupBaseFolderFull')); ?></p>

							<p><?php _e('If the missing archive is on the server but saved to a different path. Maybe you setup the archive to save to an alternate directory. Then you can enter the full server path to the <strong>directory</strong> where the archive resides.', SNAPSHOT_I18N_DOMAIN); ?></p>
						</li>
					</ul>

					<table class="form-table snapshot-settings-zip-library">
					<tr>
						<td>
							<label for="snapshot-import-archive-remote-url"><?php _e('URL or local path to Snapshot Archive', SNAPSHOT_I18N_DOMAIN); ?></label> <br /><?php
								if ((isset($_POST['snapshot-import-archive-remote-url'])) && (!empty($_POST['snapshot-import-archive-remote-url']))) {
									$remote_file = esc_attr($_POST['snapshot-import-archive-remote-url']);
								} else {
									$remote_file = '';
								}
								?>

								<input type="text" style="width: 90%;" id="snapshot-import-archive-remote-url" name="snapshot-import-archive-remote-url" value="<?php echo $remote_file; ?>" />
						</td>
					</tr>
					</table>

				</div>
			</div>
			<?php
		}
	}
}