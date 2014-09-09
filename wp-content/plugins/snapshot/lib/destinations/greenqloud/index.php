<?php
/*
Snapshots Plugin Destinations Dropbox
Author: Paul Menard (Incsub)
*/

if ((!class_exists('SnapshotDestinationGreenQloud')) && (version_compare(phpversion(), "5.2", ">"))
 && (stristr(WPMUDEV_SNAPSHOT_DESTINATIONS_EXCLUDE, 'SnapshotDestinationGreenQloud') === false)) {
	if (!class_exists('CFRuntime'))
		require_once( dirname( __FILE__ ) . '/AWSSDKforPHP/sdk.class.php' );

	if (class_exists('AmazonS3')) {
		class SnapshotDestinationGreenQloud extends SnapshotDestinationBase {

			// The slug and name are used to identify the Destination Class
			var $name_slug;
			var $name_display;
			var $name_logo;

			var $aws_connection;

			// These vars are used when connecting and sending file to the destination. There is an
			// inteface function which populates these from the destination data.
			var $destination_info;
			var $error_array;
			var $form_errors;

			private $_regions 	= array();
			private $_ssl		= array();
			private $_storage	= array();
			private $_acl		= array();

		  	function on_creation() {
				//private destination slug. Lowercase alpha (a-z) and dashes (-) only please!
				$this->name_slug 		= 'greenqloud';

				// The display name for listing on admin panels
				$this->name_display 	= __('GreenQloud Storage', SNAPSHOT_I18N_DOMAIN);

				$this->name_logo		= plugins_url('/img/GreenQloud.gif', __FILE__);

				add_action('wp_ajax_snapshot_destination_greenqloud', array(&$this, 'destination_ajax_proc' ));
				$this->load_scripts();
			}

			function load_scripts() {

				if ((!isset($_GET['page'])) || (sanitize_text_field($_GET['page']) != "snapshots_destinations_panel"))
					return;

				if ((!isset($_GET['type'])) || (sanitize_text_field($_GET['type']) != $this->name_slug))
					return;

				wp_enqueue_script('snapshot-destination-greenqloud-js', plugins_url('/js/snapshot_destination_greenqloud.js', __FILE__), array('jquery'));
				wp_enqueue_style( 'snapshot-destination-greenqloud-css', plugins_url('/css/snapshot_destination_greenqloud.css', __FILE__));
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

				// Kill our instance of the AWS connection
				if (isset($this->aws_connection))
					unset($this->aws_connection);

				set_error_handler(array( &$this, 'ErrorHandler' ));

				$this->_ssl = array(
					'yes'	=>	'Yes',
					'no'	=>	'No'
				);

				$this->_regions = array(
					's.greenqloud.com'			=>	__('GreenQloud server', SNAPSHOT_I18N_DOMAIN)
				);

				$this->_storage = array(
					AmazonS3::STORAGE_STANDARD	=>	__('Standard', SNAPSHOT_I18N_DOMAIN)
				);

				$this->_acl = array(
						AmazonS3::ACL_PRIVATE	=>	__('Private', SNAPSHOT_I18N_DOMAIN),
						AmazonS3::ACL_PUBLIC	=>	__('Public Read', SNAPSHOT_I18N_DOMAIN),
						AmazonS3::ACL_OPEN		=>	__('Public Read/Write', SNAPSHOT_I18N_DOMAIN),
						AmazonS3::ACL_AUTH_READ	=>	__('Authenticated Read', SNAPSHOT_I18N_DOMAIN)
				);

			}

			function ErrorHandler($errno, $errstr, $errfile, $errline)
			{
				if (!error_reporting()) return;

				$errType = '';
			    switch ($errno) {
			    	case E_USER_ERROR:
						$errType = "Error";
			        	break;

			    	case E_USER_WARNING:
						$errType = "Warning";
			        	break;

			    	case E_USER_NOTICE:
						$errType = "Notice";
			        	break;

			    	default:
						$errType = "Unknown";
			        	break;
			    }

				if (!(error_reporting() & $errno)) {
		        	return;
		    	}

				$error_string = $errType .": errno:". $errno ." ". $errstr ." ". $errfile ." on line ". $errline;

				$this->error_array['errorStatus'] 	= true;
				$this->error_array['errorArray'][] 	= $error_string;

				if (defined( 'DOING_AJAX' ) && DOING_AJAX) {
					echo json_encode($this->error_array);
					die();
				}
			}

			function sendfile_to_remote($destination_info, $filename) {
				$this->init();

				$this->load_class_destination($destination_info);

				if (!$this->login()) {
					return $this->error_array;
				}

				if (!$this->send_file($filename)) {
					return $this->error_array;
				}
				return $this->error_array;
			}

			function destination_ajax_proc() {
				$this->init();

				if (!isset($_POST['snapshot_action'])) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= "Error: Missing 'snapshot_action' value.";
					echo json_encode($this->error_array);
					die();
				}

				if (!isset($_POST['destination_info'])) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= "Error: Missing 'destination_info' values.";
					echo json_encode($this->error_array);
					die();
				}
				$destination_info = $_POST['destination_info'];

				if (!$this->validate_form_data($destination_info)) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= implode(', ', $this->form_errors);
					echo json_encode($this->error_array);
					die();
				}

				$this->load_class_destination($destination_info);

				if (sanitize_text_field($_POST['snapshot_action']) == "connection-test") {

					if (!$this->login()) {
						echo json_encode($this->error_array);
						die();
					}

					$tmpfname =  tempnam(sys_get_temp_dir(), 'Snapshot_');
					$handle = fopen($tmpfname, "w");
					fwrite($handle, "WPMU DEV Snapshot Test connection file.");
					fclose($handle);

					$this->send_file($tmpfname);
					echo json_encode($this->error_array);
					die();

				} else if (sanitize_text_field($_POST['snapshot_action']) == "aws-get-bucket-list") {
					//echo "_POST<pre>"; print_r($_POST); echo "</pre>";
					//echo "destination_info<pre>"; print_r($this->destination_info); echo "</pre>";
					//die();

					if (!$this->login()) {
						echo json_encode($this->error_array);
						die();
					}

					if (!$this->get_buckets()) {
						echo json_encode($this->error_array);
						die();
					}

					echo json_encode($this->error_array);
					die();
				}

				echo json_encode($this->error_array);
				die();
			}

			function login() {
				$this->error_array['responseArray'][] = "Connecting to GreenQloud ";

				if ($this->destination_info['ssl'] == "yes") {
					$use_ssl = true;
					$this->error_array['responseArray'][] = "Using SSL: Yes";
				} else {
					$use_ssl = false;
					$this->error_array['responseArray'][] = "Using SSL: No";
				}

				try {
					$this->aws_connection = new AmazonS3(array(
						'key' 					=> 	$this->destination_info['awskey'], $this->destination_info['secretkey'], $use_ssl,
						'secret'				=>	$this->destination_info['secretkey'],
						'certificate_authority'	=>	$use_ssl));

					$this->error_array['responseArray'][] = "Setting Region: ".
						$this->_regions[$this->destination_info['region']] . " (".$this->destination_info['region'].")";
					$this->aws_connection->set_region($this->destination_info['region']);

				} catch (Exception $e) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= "Error: Could not connect to GreenQloud :". $e->getMessage();
					return false;
				}
				return true;
			}

			function get_buckets() {

				try {
					$buckets = $this->aws_connection->list_buckets();
				} catch (Exception $e) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= "Error: Could not list buckets :". $e->getMessage();
					return false;
				}

				if (($buckets->status < 200) || ($buckets->status >= 300)) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= "Error: Could not list buckets :". $buckets->body->Message;
					return false;
				}

				if ((!isset($buckets->body->Buckets->Bucket)) || ( count($buckets->body->Buckets->Bucket) < 1)) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= "Error: No Buckets found";
					return false;
			  	}

				$this->error_array['responseArray'][0] = '';
				foreach ($buckets->body->Buckets->Bucket as $bucket) {
					$this->error_array['responseArray'][0] .= '<option value="'. $bucket->Name .'" ';

					if ($this->destination_info['bucket'] == $bucket->Name) {
						$this->error_array['responseArray'][0] .= ' selected="selected" ';
					}
					$this->error_array['responseArray'][0] .= '>'. $bucket->Name .'</option>';
				}
				return true;
			}

			function send_file($filename) {

				if (!$this->aws_connection->if_bucket_exists($this->destination_info['bucket'])) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= "Error: Setting bucket :". $this->destination_info['bucket'];
					echo json_encode($this->error_array);
					die();
				}

				if (!empty($this->destination_info['directory'])) {
					if ($this->destination_info['directory'][0] == "/")
						$this->destination_info['directory'] = substr($this->destination_info['directory'], 1);

					$this->destination_info['directory'] = trailingslashit($this->destination_info['directory']);
				}
				$remote_filename = $this->destination_info['directory'] . basename($filename);

				$this->error_array['responseArray'][] = "Using Storage: ". $this->_storage[$this->destination_info['storage']];
				$this->error_array['responseArray'][] = "Using ACL: ". $this->destination_info['acl'];
				$this->error_array['responseArray'][] = "Sending file to: Bucket: ". $this->destination_info['bucket'] .
					": Directory: ". $this->destination_info['directory'];

				try {
					$result = (array)$this->aws_connection->create_object($this->destination_info['bucket'],
								$remote_filename,
								array(
									'acl'			=>	$this->destination_info['acl'],
									'fileUpload'	=>	$filename,
									'length'		=>	filesize($filename),
									'storage'		=>	$this->destination_info['storage'],
									'curlopts'		=>	array()
								)
							);

					//echo "result<pre>"; print_r($result['header']['_info']['url']); echo "</pre>";

					if (($result["status"] >= 200) && ($result["status"] < 300)) {
						$this->error_array['responseArray'][]	= "Send file success: ". basename($filename);
						$this->error_array['sendFileStatus']	= true;

						//$this->error_array['responseArray'][] = "File URL: ". $result['header']['_info']['url'];
						return true;

					} else {
						$this->error_array['errorStatus'] 		= true;
						$this->error_array['errorArray'][] 		= "Error: Send file failed :". $result["status"] ." :". $result["Message"];

						return false;
					}
				} catch (Exception $e) {
					$this->error_array['errorStatus'] 		= true;
					$this->error_array['errorArray'][] 		= "Error: Send file failed :". $e->getMessage();

					return false;
				}
			}

			function load_class_destination($d_info) {

				if (isset($d_info['type']))
					$this->destination_info['type'] = esc_attr($d_info['type']);

				if (isset($d_info['name']))
					$this->destination_info['name'] = esc_attr($d_info['name']);

				if (isset($d_info['awskey']))
					$this->destination_info['awskey'] = esc_attr($d_info['awskey']);

				if ((isset($d_info['secretkey'])) && (strlen($d_info['secretkey'])))
					$this->destination_info['secretkey'] = esc_attr($d_info['secretkey']);

				if ((isset($d_info['ssl'])) && (strlen($d_info['ssl']))) {
					if (isset($this->_ssl[esc_attr($d_info['ssl'])]))
						$this->destination_info['ssl'] = esc_attr($d_info['ssl']);
					else
						$this->destination_info['ssl'] = "no";
				} else {
					$this->destination_info['ssl'] = "no";
				}

				if ((isset($d_info['region'])) && (strlen($d_info['region']))) {
					if (isset($this->_regions[esc_attr($d_info['region'])]))
						$this->destination_info['region'] = esc_attr($d_info['region']);
					else
						$this->destination_info['region'] = AmazonS3::REGION_US_E1;
				} else {
					$this->destination_info['region'] = AmazonS3::REGION_US_E1;
				}

				if ((isset($d_info['storage'])) && (strlen($d_info['storage']))) {
					if (isset($this->_storage[esc_attr($d_info['storage'])]))
						$this->destination_info['storage'] = esc_attr($d_info['storage']);
					else
						$this->destination_info['storage'] = AmazonS3::STORAGE_STANDARD;
				} else {
					$this->destination_info['storage'] = AmazonS3::STORAGE_STANDARD;
				}

				if ((isset($d_info['acl'])) && (strlen($d_info['acl']))) {
					if (isset($this->_acl[$d_info['acl']]))
						$this->destination_info['acl'] = $d_info['acl'];
					else
						$this->destination_info['acl'] = AmazonS3::ACL_PRIVATE;
				} else {
					$this->destination_info['acl'] = AmazonS3::ACL_PRIVATE;
				}

				if ((isset($d_info['bucket'])) && (strlen($d_info['bucket'])))
					$this->destination_info['bucket'] = esc_attr($d_info['bucket']);
				else
					$this->destination_info['bucket'] = "";

				if ((isset($d_info['directory'])) && (strlen($d_info['directory'])))
					$this->destination_info['directory'] = esc_attr($d_info['directory']);
				else
					$this->destination_info['directory'] = "";
			}

			function validate_form_data($d_info) {

				//echo "d_info<pre>"; print_r($d_info); echo "</pre>";
				//exit;

				$this->init();

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

				if (isset($d_info['awskey']))
					$destination_info['awskey'] = esc_attr($d_info['awskey']);
				else
					$this->form_errors['awskey'] = __("GreenQloud API Access Key is required", SNAPSHOT_I18N_DOMAIN);

				if ((isset($d_info['secretkey'])) && (strlen($d_info['secretkey'])))
					$destination_info['secretkey'] = esc_attr($d_info['secretkey']);
				else
					$this->form_errors['secretkey'] = __("GreenQloud API Secret Key is requires", SNAPSHOT_I18N_DOMAIN);

				if ((isset($d_info['ssl'])) && (strlen($d_info['ssl']))) {
					$destination_info['ssl'] = esc_attr($d_info['ssl']);
					if (($destination_info['ssl'] != "yes") && ($destination_info['ssl'] != "no"))
						$destination_info['ssl'] = "no";
				} else {
					$destination_info['ssl'] = "no";
				}


				if ((isset($d_info['region'])) && (strlen($d_info['region']))) {
					if (isset($this->_regions[esc_attr($d_info['region'])]))
						$destination_info['region'] = esc_attr($d_info['region']);
					else
						$destination_info['region'] = AmazonS3::REGION_US_E1;

				} else {
					$destination_info['region'] = AmazonS3::REGION_US_E1;
				}


				if ((isset($d_info['storage'])) && (strlen($d_info['storage']))) {
					if (isset($this->_storage[esc_attr($d_info['storage'])]))
						$destination_info['storage'] = esc_attr($d_info['storage']);
					else
						$destination_info['storage'] = AmazonS3::STORAGE_STANDARD;

				} else {
					$destination_info['storage'] = AmazonS3::STORAGE_STANDARD;
				}

				if ((isset($d_info['acl'])) && (strlen($d_info['acl']))) {
					if (isset($this->_acl[esc_attr($d_info['acl'])]))
						$destination_info['acl'] = esc_attr($d_info['acl']);
					else
						$destination_info['acl'] = AmazonS3::ACL_PRIVATE;

				} else {
					$destination_info['acl'] = AmazonS3::ACL_PRIVATE;
				}

				if ((isset($d_info['bucket'])) && (strlen($d_info['bucket'])))
					$destination_info['bucket'] = esc_attr($d_info['bucket']);
				else
					$destination_info['bucket'] = "";

				if ((isset($d_info['directory'])) && (strlen($d_info['directory'])))
					$destination_info['directory'] = esc_attr($d_info['directory']);
				else
					$destination_info['directory'] = "";

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
					<th class="snapshot-col-access-key"><?php _e('Access Key ID', SNAPSHOT_I18N_DOMAIN); ?></th>
					<th class="snapshot-col-bucket"><?php _e('Bucket', SNAPSHOT_I18N_DOMAIN); ?></th>
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

								<td class="snapshot-col-name"><a href="<?php echo $edit_url; ?>item=<?php echo $idx; ?>"><?php echo stripslashes($item['name']) ?></a>
									<div class="row-actions" style="margin:0; padding:0;">
										<span class="edit"><a href="<?php echo $edit_url; ?>item=<?php echo $idx; ?>"><?php _e('edit', SNAPSHOT_I18N_DOMAIN); ?></a></span> | <span class="delete"><a href="<?php echo $delete_url; ?>item=<?php echo $idx; ?>&amp;snapshot-noonce-field=<?php echo wp_create_nonce( 'snapshot-delete-destination' ); ?>"><?php _e('delete', SNAPSHOT_I18N_DOMAIN); ?></a></span>
									</div>
								</td>
								<td class="snapshot-col-server"><?php
									if (isset($item['awskey'])) {
										echo $item['awskey'];
									} ?></td>
								<td class="snapshot-col-bucket"><?php
									if (isset($item['bucket'])) {
										echo $item['bucket'];
									} ?></td>
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
							_e('No GreenQloud Storage Destinations', SNAPSHOT_I18N_DOMAIN); ?></td></tr><?php
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

				$this->init();
				//echo "item<pre>"; print_r($item); echo "</pre>";
				?>
				<p><?php _e('Define an GreenQloud Storage destination connection. You can define multiple destinations which use GreenQloud. Each destination can use different security keys and/or buckets.', SNAPSHOT_I18N_DOMAIN); ?></p>
				<div id="poststuff" class="metabox-holder">
					<div style="display: none" id="snapshot-destination-test-result"></div>
					<div class="postbox" id="snapshot-destination-item">

					<h3 class="hndle"><span><?php _e('GreenQloud Storage Destination', SNAPSHOT_I18N_DOMAIN); ?></span></h3>
					<div class="inside">
						<input type="hidden" name="snapshot-destination[type]" id="snapshot-destination-type" value="<?php echo $this->name_slug; ?>" />

						<table class="form-table">
						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-name"><?php _e('Destination Name', SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td><input type="text" name="snapshot-destination[name]" id="snapshot-destination-name"
								value="<?php if (isset($item['name'])) { echo stripslashes($item['name']); } ?>" /></td>
						</tr>
						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-awskey"><?php _e('Access Key ID', SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td><input type="text" name="snapshot-destination[awskey]" id="snapshot-destination-awskey"
								value="<?php if (isset($item['awskey'])) { echo $item['awskey']; } ?>" /><br /><a href="https://my.greenqloud.com/account/apiAccess" target="_blank"><?php _e('API Access', SNAPSHOT_I18N_DOMAIN); ?></a></td>
						</tr>
						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-secretkey"><?php _e('Secret Access Key', SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td><input type="password" name="snapshot-destination[secretkey]" id="snapshot-destination-secretkey"
								value="<?php if (isset($item['secretkey'])) { echo $item['secretkey']; } ?>" /></td>
						</tr>

						<?php if (!isset($item['ssl'])) { $item['ssl'] = "yes"; } ?>
						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-ssl"><?php _e('Use SSL Connection', SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td>
								<select name="snapshot-destination[ssl]" id="snapshot-destination-ssl">
									<?php
										foreach($this->_ssl as $_key => $_name) {
											?><option value="<?php echo $_key; ?>" <?php
												if ($item['region'] == $_key) { echo ' selected="selected" '; } ?> ><?php echo $_name; ?></option><?php

										}
									?>
								</select>
							</td>
						</tr>


						<?php if (!isset($item['region'])) { $item['region'] = AmazonS3::REGION_US_E1; } ?>
						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-region"><?php _e('Region', SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td>
								<select name="snapshot-destination[region]" id="snapshot-destination-region">
								<?php
									foreach($this->_regions as $_key => $_name) {
										?><option value="<?php echo $_key; ?>" <?php
											if ($item['region'] == $_key) { echo ' selected="selected" '; } ?> ><?php
												echo $_name; ?> (<?php echo $_key ?>)</option><?php

									}
								?>
								</select>
							</td>
						</tr>

						<?php if (!isset($item['storage'])) { $item['storage'] = AmazonS3::STORAGE_STANDARD; } ?>
						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-storage"><?php _e('Storage Type', SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td>
								<select name="snapshot-destination[storage]" id="snapshot-destination-storage">
								<?php
									foreach($this->_storage as $_key => $_name) {
										?><option value="<?php echo $_key; ?>" <?php
											if ($item['region'] == $_key) { echo ' selected="selected" '; } ?> ><?php echo $_name; ?></option><?php

									}
								?>
								</select>
							</td>
						</tr>


						<tr class="form-field" id="snapshot-destination-bucket-container">
							<th scope="row"><label for="snapshot-destination-bucket"><?php _e('Bucket Name', SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td>
								<?php
									if (isset($item['bucket'])) {
										?><span id="snapshot-destination-bucket-display"><?php echo $item['bucket']; ?></span> <input
											type="hidden" name="snapshot-destination[bucket]" id="snapshot-destination-bucket"
										value="<?php if (isset($item['bucket'])) { echo $item['bucket']; } ?>" /><?php
									}
								?>
								<select name="snapshot-destination[bucket]" id="snapshot-destination-bucket-list" style="display: none">
								</select>
								<button id="snapshot-destination-aws-get-bucket-list" class="button-seconary" name=""><?php
									_e('Select Bucket', SNAPSHOT_I18N_DOMAIN); ?></button>
								<div id="snapshot-ajax-destination-bucket-error" style="display:none"></div>
							</td>
						</tr>

						<?php if (!isset($item['acl'])) { $item['acl'] = AmazonS3::ACL_PRIVATE; } ?>
						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-acl"><?php _e('File permissions for uploaded files',
								SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td>
								<select name="snapshot-destination[acl]" id="snapshot-destination-acl">
									<option value="<?php echo AmazonS3::ACL_PRIVATE; ?>" <?php if ($item['acl'] == AmazonS3::ACL_PRIVATE) {
											echo ' selected="selected" '; } ?> ><?php _e('Private', SNAPSHOT_I18N_DOMAIN) ?></option>
									<option value="<?php echo AmazonS3::ACL_PUBLIC ?>" <?php if ($item['acl'] == AmazonS3::ACL_PUBLIC) {
											echo ' selected="selected" '; } ?> ><?php _e('Public Read', SNAPSHOT_I18N_DOMAIN) ?></option>
									<option value="<?php echo AmazonS3::ACL_OPEN ?>" <?php if ($item['acl'] == AmazonS3::ACL_OPEN) {
											echo ' selected="selected" '; } ?> ><?php _e('Public Read/Write', SNAPSHOT_I18N_DOMAIN) ?></option>
									<option value="<?php echo AmazonS3::ACL_AUTH_READ ?>" <?php if ($item['acl'] == AmazonS3::ACL_AUTH_READ) {
											echo ' selected="selected" '; } ?> ><?php _e('Authenticated Read', SNAPSHOT_I18N_DOMAIN) ?></option>
								</select>
							</td>
						</tr>

						<tr class="form-field">
							<th scope="row"><label for="snapshot-destination-directory"><?php _e('Directory (optional)', SNAPSHOT_I18N_DOMAIN); ?></label></th>
							<td><input type="text" name="snapshot-destination[directory]" id="snapshot-destination-directory"
								value="<?php if (isset($item['directory'])) { echo $item['directory']; } ?>" />
								<p class="description"><?php _e('If directory is blank the snapshot file will be stored at the bucket root. If the directory is provided it will be created inside the bucket. This is a global setting and will be used by all snapshot configurations using this destination. You can also defined a directory used by a specific snapshot.', SNAPSHOT_I18N_DOMAIN); ?></p>

							</td>
						</tr>
						<tr class="form-field" id="snapshot-destination-test-connection-container">
							<th scope="row">&nbsp;</th>
							<td><button id="snapshot-destination-test-connection" class="button-seconary" name=""><?php
								_e('Test Connection', SNAPSHOT_I18N_DOMAIN); ?></button>
								<div id="snapshot-ajax-destination-test-result" style="display:none"></div>
							</td>
						</tr>
						</table>
					</div>
				</div>
				<?php
			}
		}
		do_action('snapshot_register_destination', 'SnapshotDestinationGreenQloud');
	}
}