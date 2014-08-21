<?php
/*
Snapshots Plugin Destinations Dropbox
Author: Paul Menard (Incsub)
*/

if ((!class_exists('SnapshotDestinationDropbox'))
 && (stristr(WPMUDEV_SNAPSHOT_DESTINATIONS_EXCLUDE, 'SnapshotDestinationDropbox') === false)) {

	class SnapshotDestinationDropbox extends SnapshotDestinationBase {

		// The slug and name are used to identify the Destination Class
		var $name_slug;
		var $name_display;

		// Do not change this! This is set from Dropbox and is the KEY/SECRET for this Dropbox App.
		const DROPBOX_APP_KEY 		= 'g1j0k3ob0fwcgnc';
		const DROPBOX_APP_SECRET 	= 'di1vr3xgf86f4fl';

		var $tokens = array();

		var $excluded_files = array();
		var $excluded_file_chars = array();

		var $dropbox_connection;
		var $oauth;

		var $snapshot_logger;
		var $snapshot_locker;

		// These vars are used when connecting and sending file to the destination. There is an
		// inteface function which populates these from the destination data.
		var $destination_info;
		var $error_array;
		var $form_errors;

		function load_library() {
			require_once( dirname( __FILE__ ) . '/includes/Dropbox/autoload.php' );
			set_include_path(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes/PEAR_Includes' . PATH_SEPARATOR . get_include_path());
		}

	  	function on_creation() {
			//private destination slug. Lowercase alpha (a-z) and dashes (-) only please!
			$this->name_slug 		= 'dropbox';

			// The display name for listing on admin panels
			$this->name_display 	= __('Dropbox', SNAPSHOT_I18N_DOMAIN);

			$this->sync_excluded_files 	= array(
					'.desktop.ini',
					'thumbs.db',
					'.ds_store',
					'icon\r',
					'.dropbox',
					'.dropbox.attr',
					'.git',
					'.gitignore',
					'.gitmodules',
					'.svn',
					'.sass-cache',
			);

			$this->sync_excluded_file_chars = array(
				'<', '>', ':', '/', '\\', '|', '?', '*'
			);

			//add_action('wp_ajax_snapshot_destination_dropbox', array(&$this, 'destination_ajax_proc' ));

			// When returning from Dropbox Authorize the URL Query String contains the parameter 'oauth_token'. On this indicator
			// we load the stored item option and grab the new access token. Then store the options and redirect the user to
			// the Destination Dropbox form where they will finally save the destination info.
			if ((isset($_GET['page'])) && (sanitize_text_field($_GET['page']) == 'snapshots_destinations_panel')) {

				//require_once( dirname( __FILE__ ) . '/includes/Dropbox/autoload.php' );
				//set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes/PEAR_Includes');
				$this->load_library();

				if (isset($_REQUEST['oauth_token'])) {
					$d_info = get_option('snapshot-dropbox-tokens');
					$this->oauth = new Dropbox_OAuth_PEAR(self::DROPBOX_APP_KEY, self::DROPBOX_APP_SECRET);
					$this->oauth->setToken($d_info['tokens']['request']);
					$d_info['tokens']['access'] = $this->oauth->getAccessToken();

					update_option('snapshot-dropbox-tokens', $d_info);

					$link = remove_query_arg('oauth_token');
					$link = remove_query_arg('uid', $link);
					$action = 'snapshot-destination-dropbox-authorize';
					$link = add_query_arg( 'dropbox-authorize', wp_create_nonce( $action ), $link );

					if (isset($d_info['item'])) {
						$link = add_query_arg( 'snapshot-action', 'edit', $link );
						$link = add_query_arg( 'item', $d_info['item'], $link );

					} else {
						$link = add_query_arg( 'snapshot-action', 'add', $link );
					}
					$link = add_query_arg( 'type', 'dropbox', $link );
					$d_info = get_option('snapshot-dropbox-tokens');
					wp_redirect($link);
				}
			}
		}

		function init() {

			if (isset($this->destination_info))
				unset($this->destination_info);
			$this->destination_info = array();

			if (isset($this->error_array))
				unset($this->error_array);
			$this->error_array = array();

			$this->error_array['errorStatus'] 		= false;
			$this->error_array['sendFileStatus']	= false;
			$this->error_array['errorArray'] 		= array();
			$this->error_array['responseArray'] 	= array();

			set_error_handler(array( &$this, 'ErrorHandler' ));
		}

		function validate_form_data($d_info) {

			if (isset($d_info['force-authorize'])) {
				unset($d_info['force-authorize']);

				if (isset($d_info['tokens']['access'])) {
					unset($d_info['tokens']['access']);
				}

				if (isset($_POST['item']))
					$d_info['item'] = sanitize_text_field($_POST['item']);

				//require_once( dirname( __FILE__ ) . '/includes/Dropbox/autoload.php' );
				//set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes/PEAR_Includes');
				$this->load_library();

				$this->oauth = new Dropbox_OAuth_PEAR(self::DROPBOX_APP_KEY, self::DROPBOX_APP_SECRET);
				$d_info['tokens']['request'] = $this->oauth->getRequestToken();

				update_option('snapshot-dropbox-tokens', $d_info);

				$action = "snapshot-destination-dropdown-authorize";
				$link = add_query_arg( '_wpnonce', wp_create_nonce( $action ) );
				$admin_url = admin_url();
				$admin_url_parts = parse_url($admin_url);
				$admin_url = $admin_url_parts['scheme'] ."://". $admin_url_parts['host'];
				$return_link = $admin_url . $link;
				$dropbox_url = $this->oauth->getAuthorizeUrl($return_link);
				wp_redirect($dropbox_url);
				die();
			}

			// Will contain the filtered fields from the form (d_info).
			$destination_info = array();

			if (isset($this->form_errors))
				unset($this->form_errors);

			$this->form_errors = array();

			if (isset($d_info['type']))
				$destination_info['type'] = esc_attr($d_info['type']);

			if (isset($d_info['name']))
				$destination_info['name'] = esc_attr($d_info['name']);
			else
				$this->form_errors['name'] = __("Name is required", SNAPSHOT_I18N_DOMAIN);

			if (isset($d_info['directory'])) {
				$destination_info['directory'] = esc_attr($d_info['directory']);
				$destination_info['directory'] = str_replace('\\', '/', stripslashes($destination_info['directory']));
			} //else
				//$this->form_errors['directory'] = __("Directory is required", SNAPSHOT_I18N_DOMAIN);

			if (isset($d_info['tokens']['request']['token']))
				$destination_info['tokens']['request']['token'] = esc_attr($d_info['tokens']['request']['token']);

			if (isset($d_info['tokens']['request']['token_secret']))
				$destination_info['tokens']['request']['token_secret'] = esc_attr($d_info['tokens']['request']['token_secret']);

			if (isset($d_info['tokens']['access']['token']))
				$destination_info['tokens']['access']['token'] = esc_attr($d_info['tokens']['access']['token']);

			if (isset($d_info['tokens']['access']['token_secret']))
				$destination_info['tokens']['access']['token_secret'] = esc_attr($d_info['tokens']['access']['token_secret']);

			if (isset($d_info['account_info']))
				$destination_info['account_info'] = $d_info['account_info'];

			if (count($this->form_errors))
				return false;
			else
				return $destination_info;
		}

		function display_listing_table($destinations, $edit_url, $delete_url) {

			?>
			<table class="widefat">
			<thead>
			<tr class="form-field">
				<th class="snapshot-col-delete"><?php _e('Delete', 	SNAPSHOT_I18N_DOMAIN); ?></th>
				<th class="snapshot-col-name"><?php _e('Name', SNAPSHOT_I18N_DOMAIN); ?></th>
				<th class="snapshot-col-login"><?php _e('Login', SNAPSHOT_I18N_DOMAIN); ?></th>
				<th class="snapshot-col-authorized"><?php _e('Authorized', SNAPSHOT_I18N_DOMAIN); ?></th>
				<th class="snapshot-col-directory"><?php _e('Directory', SNAPSHOT_I18N_DOMAIN); ?></th>
				<th class="snapshot-col-used"><?php _e('Used', SNAPSHOT_I18N_DOMAIN); ?></th>
			</tr>
			<thead>
			<tbody>
			<?php
				if ((isset($destinations)) && (count($destinations))) {

					foreach($destinations as $idx => $item) {
						if (!isset($row_class)) { $row_class = ""; }
						$row_class = ( $row_class == '' ? 'alternate' : '' );

						?>
						<tr class="<?php echo $row_class; ?><?php
								if (isset($item['type'])) { echo ' snapshot-row-filter-type-'. $item['type']; } ?>">
							<td class="snapshot-col-delete"><input type="checkbox"
								name="delete-bulk-destination[<?php echo $idx; ?>]" id="delete-bulk-destination-<?php echo $idx; ?>"></td>

							<td class="snapshot-col-name"><a href="<?php echo $edit_url; ?>item=<?php echo $idx; ?>"><?php
								echo stripslashes($item['name']) ?></a>
								<div class="row-actions" style="margin:0; padding:0;">
									<span class="edit"><a href="<?php echo $edit_url; ?>item=<?php echo $idx; ?>"><?php
										_e('edit', SNAPSHOT_I18N_DOMAIN); ?></a></span> | <span class="delete"><a href="<?php
										echo $delete_url; ?>item=<?php echo $idx; ?>&amp;snapshot-noonce-field=<?php
										echo wp_create_nonce( 'snapshot-delete-destination' ); ?>"><?php
										_e('delete', SNAPSHOT_I18N_DOMAIN); ?></a></span>
								</div>
							</td>
							<td class="snapshot-col-login"><?php
								if (isset($item['account_info']['display_name'])) {
									echo $item['account_info']['display_name'];

									if (isset($item['account_info']['email'])) {
										echo ' ('. $item['account_info']['email'] .')';
									}
								} ?></td>
							<td class="snapshot-col-authorized"><?php
								if ( (isset($item['tokens']['access']['token'])) && (isset($item['tokens']['access']['token_secret'])) ) {
									_e('Yes', SNAPSHOT_I18N_DOMAIN);
								} else {
									_e('Yes', SNAPSHOT_I18N_DOMAIN);
								}?></td>
							<td class="snapshot-col-directory"><?php
								if (isset($item['directory'])) {
									echo $item['directory'];
								} ?></td>
							<td class="snapshot-col-used"><?php $this->wpmudev_snapshot->snaphot_show_destination_item_count($idx); ?></td>
						</tr>
						<?php
					}
				} else {
					?><tr class="form-field"><td colspan="4"><?php
						_e('No Dropbox Destinations', SNAPSHOT_I18N_DOMAIN); ?></td></tr><?php
				}
			?>
			</tbody>
			</table>
			<?php
				if ((isset($destinations)) && (count($destinations))) {
					?>
					<div class="tablenav">
						<div class="alignleft actions">
							<input class="button-secondary" type="submit" value="<?php _e('Delete Destination', SNAPSHOT_I18N_DOMAIN); ?>" />
						</div>
					</div>
					<?php
				}
			?>
			<?php
		}

		function display_details_form($item=0) {

			?>
			<p><?php _e('Define a Dropbox destination connection. Provide a name below. You will need to save this form then return here to Authorize the connection to Dropbox', SNAPSHOT_I18N_DOMAIN); ?></p>

			<div id="poststuff" class="metabox-holder">
				<div style="display: none" id="snapshot-destination-test-result"></div>
				<div class="postbox" id="snapshot-destination-item">

					<h3 class="hndle"><span><?php echo $this->name_display; ?> <?php _e('Destination', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
					<div class="inside">

						<input type="hidden" name="snapshot-destination[type]" id="snapshot-destination-type" value="<?php echo $this->name_slug; ?>" />
						<table class="form-table">
						<?php
							if ((isset($this->form_errors)) && (count($this->form_errors))) {
								?>
								<tr class="form-field">
									<th scope="row"><?php _e("Form Errors", SNAPSHOT_I18N_DOMAIN);?></th>
									<td>
										<ul>
										<?php
											foreach($this->form_errors as $error) {
												?><li><?php echo $error; ?></li><?php
											}
										?>
										</ul>
									</td>
								</tr>
								<?php
							}
						?>
						<?php
							if ( (isset($_GET['dropbox-authorize']))
								&& (wp_verify_nonce(sanitize_text_field($_GET['dropbox-authorize']), 'snapshot-destination-dropbox-authorize')) ) {

								$item = get_option('snapshot-dropbox-tokens');
								//echo "item<pre>"; print_r($item); echo "</pre>";

								// If we make it here then the Dropbox Authorize processing has been handled or aborted. So don't keep the option stored.
								delete_option('snapshot-dropbox-tokens');

							}

							// Store the Token - Request as hidden fields
							if (isset($item['tokens']['request']['token'])) {
								?>
								<input type="hidden" name="snapshot-destination[tokens][request][token]"
									value="<?php echo $item['tokens']['request']['token']; ?>" />
								<?php
							}
							if (isset($item['tokens']['request']['token_secret'])) {
								?>
								<input type="hidden" name="snapshot-destination[tokens][request][token_secret]"
									value="<?php echo $item['tokens']['request']['token_secret']; ?>" />
								<?php
							}

							// Store the Token - Access as hidden fields
							if (isset($item['tokens']['access']['token'])) {
								?>
								<input type="hidden" name="snapshot-destination[tokens][access][token]"
									value="<?php echo $item['tokens']['access']['token']; ?>" />
								<?php
							}
							if (isset($item['tokens']['access']['token_secret'])) {
								?>
								<input type="hidden" name="snapshot-destination[tokens][access][token_secret]"
									value="<?php echo $item['tokens']['access']['token_secret']; ?>" />
								<?php
							}
						?>
						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-name"><?php _e('Destination Name', SNAPSHOT_I18N_DOMAIN); ?></label> *</th>
							<td><input type="text" name="snapshot-destination[name]" id="snapshot-destination-name"
								value="<?php if (isset($item['name'])) { echo stripslashes($item['name']); } ?>" />
							</td>
						</tr>
						<?php if (isset($item['tokens']['access']['token'])) { ?>
							<tr class="form-field">
								<th scope="row"><label for="snapshot-destination-directory"><?php
									_e('Destination Directory', SNAPSHOT_I18N_DOMAIN); ?></label></th>
								<td><input type="text" name="snapshot-destination[directory]" id="snapshot-destination-directory"
									value="<?php if (isset($item['directory'])) { echo stripslashes($item['directory']); } ?>" />
									<p class="description"><?php _e('<strong>All archive uploaded to your Dropbox account via Snapshot will be stored into /Apps/WPMU DEV Snapshot/</strong>', SNAPSHOT_I18N_DOMAIN); ?></p>
									<p class="description"><?php _e('The optional directory here allow you to store the uploaded files into a sub-directories. This can be multiple levels of sub-directories. The directory names can contain spaces. This is a global setting and will be used by all snapshot configurations using this destination. You can also defined a directory used by a specific snapshot. Please use / instead of \\', SNAPSHOT_I18N_DOMAIN); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="snapshot-destination-authorized"><?php _e('Authorized', SNAPSHOT_I18N_DOMAIN); ?></label></th>
								<td>
									<?php _e('Yes', SNAPSHOT_I18N_DOMAIN); ?><br />
								<input type="checkbox" name="snapshot-destination[force-authorize]" id="snapshot-destination-force-authorize" /> <label for="snapshot-destination-force-authorize"><?php _e('Force Re-Authorize with Dropbox', SNAPSHOT_I18N_DOMAIN); ?></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php _e('Account Info', SNAPSHOT_I18N_DOMAIN); ?></label></th>
								<td>
								<?php
									//require_once( dirname( __FILE__ ) . '/includes/Dropbox/autoload.php' );
									//set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes/PEAR_Includes');
									$this->load_library();

									$this->oauth = new Dropbox_OAuth_PEAR(self::DROPBOX_APP_KEY, self::DROPBOX_APP_SECRET);
									$this->oauth->setToken($item['tokens']['access']);
									$this->dropbox = new Dropbox_API($this->oauth, Dropbox_API::ROOT_SANDBOX);
									//$this->dropbox = new Dropbox_API($this->oauth);
									$account_info = $this->dropbox->getAccountInfo();
									if ($account_info) {
										//echo "account_info<pre>"; print_r($account_info); echo "</pre>";

										$account_info_display = '';
										if (isset($account_info['display_name'])) {
											$account_info_display .= '<li><strong>'. __('Name', SNAPSHOT_I18N_DOMAIN) .'</strong>: '.
												$account_info['display_name'] .' ('. $account_info['email'] .')</li>';
											?>
											<input type="hidden" name="snapshot-destination[account_info][display_name]"
													value="<?php echo $account_info['display_name']; ?>" />
											<input type="hidden" name="snapshot-destination[account_info][email]"
													value="<?php echo $account_info['email']; ?>" />


											<?php
										}

										if (isset($account_info['uid'])) {
											$account_info_display .= '<li><strong>'. __('UID', SNAPSHOT_I18N_DOMAIN) .'</strong>: '.
												$account_info['uid'] .'</li>';
											?><input type="hidden" name="snapshot-destination[account_info][uid]"
													value="<?php echo $account_info['uid']; ?>" /><?php
										}

										if (isset($account_info['country'])) {
											$account_info_display .= '<li><strong>'. __('Country', SNAPSHOT_I18N_DOMAIN) .'</strong>: '.
												$account_info['country'] .'</li>';
											?><input type="hidden" name="snapshot-destination[account_info][country]"
													value="<?php echo $account_info['country']; ?>" /><?php
										}
										if (isset($account_info['quota_info'])) {
											$account_info_display .= '<li><strong>'. __('Usage', SNAPSHOT_I18N_DOMAIN) .'</strong>: '.
											number_format((intval($account_info['quota_info']['normal']) /
												intval($account_info['quota_info']['quota'])) * 100, 2, '.', '') .'&#37;</li>';
											?>
											<input type="hidden" name="snapshot-destination[account_info][quota_info][normal]"
													value="<?php echo $account_info['quota_info']['normal']; ?>" />
											<input type="hidden" name="snapshot-destination[account_info][quota_info][quota]"
													value="<?php echo $account_info['quota_info']['quota']; ?>" />
											<?php
										}

										if (strlen($account_info_display)) {
											?><ul><?php echo $account_info_display; ?></ul><?php
										}
									}
								?>
								</td>
							</tr>
						<?php } else { ?>
							<tr class="form-field">
								<th scope="row"><label for="snapshot-destination-authorize"><?php _e('Authorized', SNAPSHOT_I18N_DOMAIN); ?></label></th>
								<td>
									<?php _e('No', SNAPSHOT_I18N_DOMAIN); ?><br />
									<p><?php _e('The first step in the Dropbox setup is Authorizing Snapshot to communicate with your Dropbox account. Dropbox requires that you grant Snapshot access to your account. This is required in order for Snapshot to upload files to your Dropbox account.', SNAPSHOT_I18N_DOMAIN); ?></p>
									<input type="hidden" name="snapshot-destination[force-authorize]" id="snapshot-destination-force-authorize" value="on" />
								</td>
							</tr>
						<?php } ?>
					</table>
				</div>
			</div>
			<?php
		}

		function sendfile_to_remote($destination_info, $filename) {

			$this->init();

			$this->load_class_destination($destination_info);

			$this->load_library();

			$this->oauth = new Dropbox_OAuth_PEAR(self::DROPBOX_APP_KEY, self::DROPBOX_APP_SECRET);
			//$this->oauth = new Dropbox_OAuth_WordPress(self::DROPBOX_APP_KEY, self::DROPBOX_APP_SECRET);
			//$this->oauth = new Dropbox_OAuth_Curl(self::DROPBOX_APP_KEY, self::DROPBOX_APP_SECRET);
			try {
				$this->oauth->setToken($this->destination_info['tokens']['access']);
				$this->error_array['errorArray'] = array();

			} catch(Exception $e) {
				$this->error_array['errorStatus'] 	= true;
				$this->error_array['errorArray'][] 	= $e->getMessage();
				return $this->error_array;
			}
			$this->dropbox = new Dropbox_API($this->oauth, Dropbox_API::ROOT_SANDBOX);

			//$this->dropbox->setLogger($this->snapshot_logger);
			if (!file_exists($filename)) {
				$this->error_array['errorStatus'] 	= true;
				$this->error_array['errorArray'][] 	= "File does not exists: ". basename($filename);
				return $this->error_array;
			}

			$directory_file = '/';
			if (strlen($this->destination_info['directory'])) {
				$directory_file = trailingslashit($this->destination_info['directory']);
			}
			$this->error_array['responseArray'][] 		= "Sending to Dropbox Directory:". $directory_file;
			$directory_file .= basename($filename);

			try {
				$result = $this->dropbox->putFile($directory_file, $filename, array(&$this, 'progress_of_files'), $this->snapshot_logger);
				if ($result == true) {
					$this->error_array['responseArray'][] 	= "Send file success: " . basename($filename);
					$this->error_array['sendFileStatus']	= true;

				} else {
					$this->error_array['errorArray'][] 		= $this->dropbox->last_result;
				}
				return $this->error_array;

//				$fileInfo = $this->dropbox->getMetaData($directory_file);
//				echo "fileInfo<pre>"; print_r($fileInfo); echo "</pre>";
//
//				$shareInfo = $this->dropbox->share($directory_file);
//				echo "shareInfo<pre>"; print_r($shareInfo); echo "</pre>";

			} catch (Exception $e) {
				//echo "e<pre>"; print_r($e); echo "</pre>";
				//echo 'Caught exception: ',  $e->getMessage(), "\n";
				$this->error_array['errorStatus'] 	= true;
				$this->error_array['errorArray'][] 	= $e->getMessage();
				return $this->error_array;
			}
		}

		function progress_of_files($file_array) {

			$locker_info = $this->snapshot_locker->get_locker_info();
			foreach($file_array as $_key => $_val) {
				$locker_info[$_key] = $_val;
			}
			$this->snapshot_locker->set_locker_info($locker_info);
		}

		function syncfiles_to_remote($destination_info, $sync_files, $sync_files_option='') {

			$this->init();

			$this->load_class_destination($destination_info);

			//require_once( dirname( __FILE__ ) . '/includes/Dropbox/autoload.php' );
			//set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes/PEAR_Includes');
			$this->load_library();

			$this->oauth = new Dropbox_OAuth_PEAR(self::DROPBOX_APP_KEY, self::DROPBOX_APP_SECRET);
			try {
				$this->oauth->setToken($this->destination_info['tokens']['access']);
				$this->error_array['errorArray'] = array();

			} catch(Exception $e) {
				$this->error_array['errorStatus'] 	= true;
				$this->error_array['errorArray'][] 	= $e->getMessage();
				return $this->error_array;
			}
			$this->dropbox = new Dropbox_API($this->oauth, Dropbox_API::ROOT_SANDBOX);

			$_ABSPATH = str_replace('\\', '/', ABSPATH);

			$directory_file = '';
			if (strlen($this->destination_info['directory'])) {
				$directory_file = trailingslashit($this->destination_info['directory']);
			}

			$file_counter_total = 0;
			$file_counter_item = 0;
			foreach($sync_files['included'] as $section => $section_files) {
				$file_counter_total += count($section_files);
			}
			$this->progress_of_files(array('files_count' => $file_counter_item, 'files_total' => $file_counter_total));

			foreach($sync_files['included'] as $section => $section_files) {
				$file_counter_section = count($section_files);
				$this->snapshot_logger->log_message("Files sync start for section: ". $section ." ". $file_counter_section ." files ---------------------");

				$file_consecutive_errors = 0;
				$file_send_success_count = 0;

				foreach($section_files as $file_idx => $filename) {
					$file_counter_item += 1;
					$file_send_ratio = $file_counter_item ."/". $file_counter_total;

					$_r_filename = str_replace($_ABSPATH, '', $filename);
					if (!file_exists($filename)) {
						$this->snapshot_logger->log_message("[". $file_send_ratio ."] File does not exists: ". $_r_filename ." removed");
						unset($sync_files['included'][$section][$file_idx]);
						update_option($sync_files_option, $sync_files);
						continue;
					}

					//if (filesize($filename) >= 157286400) {
					//	$this->snapshot_logger->log_message("[". $file_send_ratio ."] File is over 150Mb. Too large for Dropbox sync. ". $_r_filename);
					//	unset($sync_files['included'][$section][$file_idx]);
					//	$sync_files['excluded']['dropbox'][$section][] = $filename;
					//	update_option($sync_files_option, $sync_files);
					//	continue;
					//}

					$_filename 			= str_replace('\\', '/', $filename);
					$_filename 			= str_replace($_ABSPATH, '', $filename);
					$_directory_file 	= $directory_file . $_filename;

					$_file = strtolower(basename($_filename));
					if (array_search($_file, $this->sync_excluded_files) !== false) {

						$this->snapshot_logger->log_message("[". $file_send_ratio ."] File not allowed by Dropbox.". $_r_filename);
						unset($sync_files['included'][$section][$file_idx]);
						$sync_files['excluded']['dropbox'][$section][] = $filename;
						update_option($sync_files_option, $sync_files);
						continue;

					}

					if (strstr($_file, $this->sync_excluded_file_chars) !== false) {
						//echo "File contains an invalid character not allowed by Dropbox. ". $_r_filename;
						$this->snapshot_logger->log_message("[". $file_send_ratio ."] File contains an invalid character not allowed by Dropbox. ". $_r_filename);
						unset($sync_files['included'][$section][$file_idx]);
						$sync_files['excluded']['dropbox'][$section][] = $filename;
						update_option($sync_files_option, $sync_files);
						continue;
					}

					$_file_h = fopen($filename,'rb');
					try {
						$this->dropbox->putFile($_directory_file, $_file_h);
						fclose($_file_h);

						$this->snapshot_logger->log_message("[". $file_send_ratio ."] Sync file: ". $_filename ." -> ". $_directory_file ." success");

						unset($sync_files['included'][$section][$file_idx]);
						$file_send_success_count += 1;

						// Update our option on every 10th file to keep things updated in case of abort or failure.
						if ($file_send_success_count > 10) {
							update_option($sync_files_option, $sync_files);
							$file_send_success_count = 0;
							$this->progress_of_files(array('files_count' => $file_counter_item));
						}

						$file_consecutive_errors = 0;

					} catch (Exception $e) {
						fclose($_file_h);

						//$this->error_array['errorStatus'] 	= true;
						$this->snapshot_logger->log_message("[". $file_send_ratio ."] Sync file: ". $_filename ." -> ". $_directory ." FAILED");
						$this->snapshot_logger->log_message($e->getMessage());

						update_option($sync_files_option, $sync_files);
						$file_send_success_count = 0;
						$this->progress_of_files(array('files_count' => $file_counter_item));

						$file_consecutive_errors += 1;
						if ($file_consecutive_errors >= 10)
							break;

						$this->snapshot_logger->log_message("Sleeping after error 15 seconds");
						sleep(15);
					}
				}
				$this->progress_of_files(array('files_count' => $file_counter_item));

				$this->snapshot_logger->log_message("Files sync end for section: ". $section ." ---------------------");

			}

			update_option($sync_files_option, $sync_files);

			if ($this->error_array['errorStatus'] != true) {
				$this->error_array['sendFileStatus']	= true;
				$this->error_array['syncFilesLast']		= time();
				$this->error_array['syncFilesTotal'] 	= $file_counter_total;
			}

			return $this->error_array;
		}

		function load_class_destination($d_info) {

			if (isset($d_info['type']))
				$this->destination_info['type'] = esc_attr($d_info['type']);

			if (isset($d_info['name']))
				$this->destination_info['name'] = esc_attr($d_info['name']);

			if ((isset($d_info['directory'])) && (strlen($d_info['directory'])))
				$this->destination_info['directory'] = esc_attr($d_info['directory']);
			else
				$this->destination_info['directory'] = "";

			if (isset($d_info['tokens']['request']['token']))
				$this->destination_info['tokens']['request']['token'] = esc_attr($d_info['tokens']['request']['token']);

			if (isset($d_info['tokens']['request']['token_secret']))
				$this->destination_info['tokens']['request']['token_secret'] = esc_attr($d_info['tokens']['request']['token_secret']);

			if (isset($d_info['tokens']['access']['token']))
				$this->destination_info['tokens']['access']['token'] = esc_attr($d_info['tokens']['access']['token']);

			if (isset($d_info['tokens']['access']['token_secret']))
				$this->destination_info['tokens']['access']['token_secret'] = esc_attr($d_info['tokens']['access']['token_secret']);
		}

		function ErrorHandler($errno, $errstr, $errfile, $errline)
		{
			if (!error_reporting()) return;

			$errType = '';
		    switch ($errno) {
		    	case E_USER_ERROR:
					$errType = "Error";
					//echo "errno=[". $errno ."]<br />";
					//echo "errstr=[". $errstr ."]<br />";
					//echo "errfile=[". $errfile ."]<br />";
					//echo "errline=[". $errline ."]<br />";

		        	break;

		    	case E_USER_WARNING:
					return;
					$errType = "Warning";
		        	break;

		    	case E_USER_NOTICE:
					return;
					$errType = "Notice";
		        	break;

		    	default:
					return;
					$errType = "Unknown";
		        	break;
		    }

			$error_string = $errType .": errno:". $errno ." ". $errstr ." ". $errfile ." on line ". $errline;

			$this->error_array['errorStatus'] 	= true;
			$this->error_array['errorArray'][] 	= $error_string;

			if (defined( 'DOING_AJAX' ) && DOING_AJAX) {
				echo json_encode($error_array);
				die();
			}
		}

	}
	do_action('snapshot_register_destination', 'SnapshotDestinationDropbox');
}
?>