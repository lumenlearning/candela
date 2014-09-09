<?php
function step_verify_stepa_show_form($form_errors = array()) {
	?>
	<h2>Processing Step: 1 - Verification</h2>
	<p>Before the restore process can begin you must verify ownership website. When you click the 'Begin' button below this script will write a secret file in the DOCUMENT ROOT of this website. The filename will be similar to '_SNAPSHOT_RECOVER_cc44602b3af63cc8fae83e7335e19076.php'. Inside the file contents will be a different unique code. On the next screen you will be asked to enter both the full filename and the unique code from the file contents.</p>

	<p><strong>Note: The DOCUMENT ROOT folder must be writable. And you must have FTP access to your website or you may use a file management screen as part of your hosting</strong></p>
	<form action="?step=verify-b" method="post" class="restore_form" class="restore_form">
		<p><input type="submit" value="Begin Verify" /></p>
	</form>
	<?php
}

function step_verify_stepb_show_form($form_errors = array()) {

	?><h2>Processing Step: 1 - Verification</h2><?php

	// First remove any previous _SNAPSHOT_RECOVER_ files
	if ($dir_handle = opendir($_SERVER['DOCUMENT_ROOT'])) {

	    while (false !== ($entry = readdir($dir_handle))) {
	        if (substr($entry, 0, strlen('_SNAPSHOT_RECOVER_')) == '_SNAPSHOT_RECOVER_') {
				unlink($_SERVER['DOCUMENT_ROOT'].'/'.$entry);
				//echo "file to unlink [". $_SERVER['DOCUMENT_ROOT'].'/'.$entry ."]<br />";
			}
	    }

	    closedir($dir_handle);
	} else {
		echo "ERROR: Unable to open DOCUMENT ROOT to write _SNAPSHOT_RECOVER_ file. Aborting recover.";
		die();
	}

	$snapshot_verify_seed = md5(time().$_SERVER['HTTP_HOST'].mt_rand(25, 25));
	$snapshot_verify_file = $_SERVER['DOCUMENT_ROOT'] ."/_SNAPSHOT_RECOVER_". $snapshot_verify_seed .".php";

	$fp = fopen($snapshot_verify_file, "w+");
	if ($fp) {
		$snapshot_verify_seed = md5(mt_rand(25, 25).time().$_SERVER['HTTP_HOST']);

		fwrite($fp, "<?php
// Snapshot Verification Code: ". $snapshot_verify_seed ."
?>");
		fclose($fp);
		?><p class="restore-success">The secret '_SNAPSHOT_RECOVER_' file has successfully been written to the DOCUMENT ROOT of your website.</p><?php

	} else {
		echo "ERROR: Unable to open DOCUMENT ROOT to write _SNAPSHOT_RECOVER_ file. Aborting recover.";
		die();
	}

	?>
	<p>Using FTP or some file management software provided by your hosting provider open the file and enter the code into the form below.</p>

	<form action="?step=1" method="post" class="restore_form" class="restore_form">

		<p><label for="snapshot-restore-verify-file">Snapshot Recover Filename</label> <span class="description">Example: <strong>_SNAPSHOT_RECOVER_189abeef04bb17b9454a917e065b44b2.php</strong> This will be the entire filename including the .php extension.</span><br />
			<input type="text" id="snapshot-restore-verify-file" name="restore[verify][file]" value="" /><?php

			if (isset($form_errors['form']['verify']['file'])) {
				?><br /><span class="restore-error"><?php echo $form_errors['form']['verify']['file']; ?></span><?php
			} ?></p>

		<p><label for="snapshot-restore-verify-code">Snapshot Recover Code</label> <span class="description">Example: <strong>f4565237f0a73d72fa4444e277aec0d7</strong> When you open the filename above you will see the label 'Snapshot Verification Code:' Enter the value after the label colon.</span><br />
			<input type="text" id="snapshot-restore-verify-file" name="restore[verify][code]" value="" /><?php

			if (isset($form_errors['form']['verify']['code'])) {
				?><br /><span class="restore-error"><?php echo $form_errors['form']['verify']['code']; ?></span><?php
			} ?></p>
		<p><strong>A new Verify File and Code are created each time this form page is loaded.</strong><br />
			<input type="submit" value="Verify Codes" /></p>
	</form>
	<?php
}

function step_1b_validate_form($restore_form) {

	$form_errors = array();
	$form_errors['form'] = array();
	$form_errors['message-error'] = array();
	$form_errors['message-success'] = array();

	// Do the form validation first before the heavy processing
	if ( (!isset($restore_form['verify']['file'])) || (!strlen($restore_form['verify']['file'])) ) {
		$form_errors['form']['verify']['file'] = "Snapshot Recover Filename cannot be empty.";
	}

	if ( (!isset($restore_form['verify']['code'])) || (!strlen($restore_form['verify']['code'])) ) {
		$form_errors['form']['verify']['code'] = "Snapshot Recover Code cannot be empty.";
	}

	if (count($form_errors['form']))
		return $form_errors;

	$snapshot_verify_file = $_SERVER['DOCUMENT_ROOT'] ."/". stripslashes($restore_form['verify']['file']);
	//echo "snapshot_verify_file=[". $snapshot_verify_file ."]<br />";
	if (!file_exists($snapshot_verify_file)) {
		$form_errors['message-error'][] = "Unable to find Verify Filename [". stripslashes($restore_form['verify']['file']) ."] to process. Try again.";
		return $form_errors;
	}

	$snapshot_verify_file_contents = file_get_contents($snapshot_verify_file);
	$snapshot_verify_code_match = stristr($snapshot_verify_file_contents, stripslashes($restore_form['verify']['code']));
	if ($snapshot_verify_code_match === false) {
		$form_errors['message-error'][] = "Verify Code does not match [". stripslashes($restore_form['verify']['code']) ."]. Try again.";
		return $form_errors;
	}

	$form_errors['message-success'][] = "Verify Code SUCCESS.";

	$_SESSION['restore_form']['verify']['file'] 	= $restore_form['verify']['file'];
	$_SESSION['restore_form']['verify']['code'] 	= $restore_form['verify']['code'];

	return $form_errors;
}


function step_1_show_form($form_errors = array()) {

	//echo "_SESSION<pre>"; print_r($_SESSION); echo "</pre>";

	if ((isset($form_errors['message-success'])) && (count($form_errors['message-success']))) {
		echo "<p>";
		foreach($form_errors['message-success'] as $message) {
			echo '<span class="restore-success">'. $message ."</span><br />";
		}
		echo "</p>";
	}
	if ((isset($form_errors['message-error'])) && (count($form_errors['message-error']))) {
		echo "<p>";
		foreach($form_errors['message-error'] as $message) {
			echo '<span class="restore-error">'. $message ."</span><<br />";
		}
		echo "</p>";
	}

	if (extension_loaded('curl'))
		$_SESSION['restore_form']['settings']['curl'] = true;
	else
		$_SESSION['restore_form']['settings']['curl'] = false;

	$max_execution_time1 = ini_get('max_execution_time');
	@ini_set('max_execution_time', intval($max_execution_time1)*2);
	$max_execution_time2 = ini_get('max_execution_time');
	if (($max_execution_time1 === $max_execution_time2) && ($max_execution_time1 < 90)) {
		?><p class="restore-error">Warning: Your PHP setting 'max_execution_time' cannot be adjusted via this PHP script and is currently set below 90 seconds. Snapshot Recover needs at least 90 seconds to perform a proper restore. Proceed at your own risk.</p><?php
	}
	?>
	<h2>Processing Step: 1 - Gather Information on Snapshot Archives and WordPress</h2>

	<form action="?step=2" method="post" class="restore_form" class="restore_form">
		<ol>
			<li>
				<h2>Snapshot Archive</h2>
				<p>The Snapshot archive file can either be a file already on the server or on a remote system like Dropbox or Amazon. As long
					as it can be access via a simple URL.</p>

				<?php
					if (!isset($_SESSION['restore_form']['snapshot']['archive-file'])) {
						$_SESSION['restore_form']['snapshot']['archive-file'] = "";
					}
				?>
				<p><label for="snapshot-restore-archive-file">Snapshot Archive Location</label> <span class="description"><?php
					if ($_SESSION['restore_form']['settings']['curl'] == true) {
						?> - Relative Path, Full Path or Remote URL to Snapshot archive:<br /><?php
					} else {
						?> - Relative or Full Path of local file only. Your PHP configuration doesn't allow access to remote files. cURL is missing :<br /><?php
					}

					if (isset($form_errors['form']['snapshot']['archive-file'])) {
						?><span class="restore-error"><?php echo $form_errors['form']['snapshot']['archive-file']; ?></span></br /><?php
					}
				?></span>
				<input type="text" id="snapshot-restore-archive-file" name="restore[snapshot][archive-file]"
					value="<?php echo $_SESSION['restore_form']['snapshot']['archive-file'] ?>" /><br />
					<span="description">Current Home root: <strong><?php echo $_SERVER['DOCUMENT_ROOT']; ?>/</strong></span><br />
					<span class="debug">Relative Path to site root. Without leading slash: some/directory/snapshot-1355515665-121214-201852-e6f8d6c2.zip<br />
						Full Path. With leading slash: /some/directory/snapshot-1355515665-121214-201852-e6f8d6c2.zip<br />
						Remote URL: http://www.some-domain.com/snapshots/archive/snapshot-1111111111-222222-000000-aaaaaaaa.zip</span>
				</p>
			</li>
			<li>
				<h2>WordPress Information</h2>

				<p>In most cases if you are running this restore process your WordPress files are still in place. You can usually leave these in place and simple restore the database tables from the Snapshot archive. However if your site is broken you may want to force a restore of WordPress core files. The Snapshot archive does not contain WordPress core files. But this restore process will attempt to download the version of WordPress to match the Snapshot archive.</p>


				<?php
					if (!isset($_SESSION['restore_form']['wordpress']['reload'])) {
						$_SESSION['restore_form']['wordpress']['reload'] = "no";
					}
				?>
				<p><label for="snapshot-restore-worpress-reload">Force Fresh WordPress Install</label> - Do you want to force a fresh WordPress installed as part of this restore? <strong>If you choose 'Yes' existing wp-admin, wp-includes, wp-content and root WordPress files will be replaced. Before submitting this for please rename any files or directories you want preserved.</strong><br />
					<?php
						if (isset($form_errors['form']['wordpress']['reload'])) {
							?><span class="restore-error"><?php echo $form_errors['form']['wordpress']['reload']; ?></span><br /><?php
						}
					?>
					<select id="snapshot-restore-worpress-reload" name="restore[wordpress][reload]">
						<option value="no" <?php if ($_SESSION['restore_form']['wordpress']['reload'] == "no") {
								echo ' selected="selected" '; } ?>>Existing</option>
						<option value="yes" <?php if ($_SESSION['restore_form']['wordpress']['reload'] == "yes") {
							echo ' selected="selected" '; } ?>>Fresh</option>
					</select><br />
					<span class="description">If you force a fresh install the version of WordPress used will be determined from the Snapshot Archive. If this is an older version of WordPress you can upgrade after the restore.</span>
				</p>


				<?php
					if (!isset($_SESSION['restore_form']['wordpress']['install-path'])) {
						$_SESSION['restore_form']['wordpress']['install-path'] = $_SERVER['DOCUMENT_ROOT'];
					}
				?>
				<p><label for="snapshot-restore-wordpress-install-path">WordPress Install Path</label> - What is the path to where WordPress is/will
						be installed?<br />
				<?php
					if (isset($form_errors['form']['wordpress']['install-path'])) {
						?><span class="restore-error"><?php echo $form_errors['form']['wordpress']['install-path']; ?></span></br /><?php
					}
				?>
				<input type="text" id="snapshot-restore-wordpress-install-path" name="restore[wordpress][install-path]"
					value="<?php echo $_SESSION['restore_form']['wordpress']['install-path']; ?>" /><br />
				<span class="description">Document root: <strong><?php echo $_SERVER['DOCUMENT_ROOT']; ?>/</strong></span></p>

			</li>
		</ol>

		<p>When you submit this form the script will locate the Snapshot Archive or download from the remote URL. If you chose to install WordPress fresh an copy of the WordPress archive will also be downloaded. In the next step you will asked to verify some information like the site URL, database prefix, etc which were gathered from the extracted Snapshot archive.</p>

		<p><input type="submit" value="Go to Step 2" /></p>
	</form>
	<?php
}

function step_1_validate_form($restore_form) {

	$form_errors = array();
	$form_errors['form'] = array();
	$form_errors['message-error'] = array();
	$form_errors['message-success'] = array();

	// Do the form validation first before the heavy processing
	if ( (!isset($restore_form['snapshot']['archive-file'])) || (!strlen($restore_form['snapshot']['archive-file'])) ) {
		$form_errors['form']['snapshot']['archive-file'] = "Snapshot Archive file cannot be empty.";
	} else {
		$_SESSION['restore_form']['snapshot']['archive-file'] 	= $restore_form['snapshot']['archive-file'];
	}

	if ( (!isset($restore_form['wordpress']['reload'])) || (!strlen($restore_form['wordpress']['reload'])) ) {
		$form_errors['form']['wordpress']['reload'] = "WordPress Reload cannot be empty.";
	} else if (($restore_form['wordpress']['reload'] != "yes") && ($restore_form['wordpress']['reload'] != "no")) {
		$form_errors['form']['wordpress']['reload'] = "WordPress Reload invalid value given.";
		return $form_errors;
	} else {
		$_SESSION['restore_form']['wordpress']['reload'] = $restore_form['wordpress']['reload'];
	}

	if ( (!isset($restore_form['wordpress']['install-path'])) || (!strlen($restore_form['wordpress']['install-path'])) ) {
		$form_errors['form']['wordpress']['install-path'] = "WordPress Install Path file cannot be empty.";
	} else {
		$_SESSION['restore_form']['wordpress']['install-path'] = untrailingslashit_snapshot($restore_form['wordpress']['install-path']);
		if (!file_exists($_SESSION['restore_form']['wordpress']['install-path'])) {
			mkdir($_SESSION['restore_form']['wordpress']['install-path'], 0777, true);
		}
		$_SESSION['restore_form']['wordpress']['install-path'] = trailingslashit_snapshot($_SESSION['restore_form']['wordpress']['install-path']);

	}


	if (count($form_errors['form']))
		return $form_errors;


	// If here then the form is valid. Now get into the heavy processing
	if (!isset($_SESSION['restore_form']['snapshot']['archive-file']))
		$_SESSION['restore_form']['snapshot']['archive-file'] = '';

	unset($_SESSION['restore_form']['snapshot']['archive-file-local']);
	unset($_SESSION['restore_form']['snapshot']['archive-file-remote']);

	if (substr($_SESSION['restore_form']['snapshot']['archive-file'], 0, strlen('http')) == "http") {
		$_SESSION['restore_form']['snapshot']['archive-file-remote'] 	= $_SESSION['restore_form']['snapshot']['archive-file'];
		$_SESSION['restore_form']['snapshot']['archive-file-local'] 	= dirname(__FILE__) ."/_snapshot/file/".
			basename($_SESSION['restore_form']['snapshot']['archive-file']);

		if (file_exists($_SESSION['restore_form']['snapshot']['archive-file-local'])) {
			$unlink_ret = unlink($_SESSION['restore_form']['snapshot']['archive-file-local']);
			if ($unlink_ret !== true) {
				$form_errors['message-error'][] = "Unable to delete previous local file [". $_SESSION['restore_form']['snapshot']['archive-file-local'] ."]. Manually delete the file. Check parent folder permissions and reload the page.";
				return $form_errors;
			}
		}

		$func_ret = remote_url_to_local_file($_SESSION['restore_form']['snapshot']['archive-file-remote'],
		 	$_SESSION['restore_form']['snapshot']['archive-file-local']);
		if ( (!file_exists($_SESSION['restore_form']['snapshot']['archive-file-local']))
		  || (!filesize($_SESSION['restore_form']['snapshot']['archive-file-local'])) ) {
			$form_errors['message-error'][] = "Attempted to download remote Snapshot file to local [". $_SESSION['restore_form']['snapshot']['archive-file-local'] ."]<br />";". File not found or is empty. Check parent folder permissions and reload the page.";
			return $form_errors;
		} else {
			$form_errors['message-success'][] = "Remote Snapshot Archive [". $_SESSION['restore_form']['snapshot']['archive-file-remote'] ."] downloaded and extracted successfully.";
		}
	} else {
		$local_file = '';
		if (substr($_SESSION['restore_form']['snapshot']['archive-file'], 0, 1) == "/") {
			$local_file = $_SESSION['restore_form']['snapshot']['archive-file'];
		} else {
			$local_file = trailingslashit_snapshot($_SERVER['DOCUMENT_ROOT']) . $_SESSION['restore_form']['snapshot']['archive-file'];
		}
		if (file_exists($local_file)) {
			$_SESSION['restore_form']['snapshot']['archive-file-local'] = $local_file;
			$form_errors['message-success'][] = "Local Snapshot Archive located [". basename($local_file) ."] successfully.";
		}
	}

	if ((isset($_SESSION['restore_form']['snapshot']['archive-file-local'])) && (strlen($_SESSION['restore_form']['snapshot']['archive-file-local']))) {

		$_SESSION['restore_form']['snapshot']['extract-path'] = dirname(__FILE__) ."/_snapshot/extract/";
		unzip_archive($_SESSION['restore_form']['snapshot']['archive-file-local'], $_SESSION['restore_form']['snapshot']['extract-path']);

		// Locate and consume the Snapshot manifest file
		$_SESSION['restore_form']['snapshot']['manifest-file'] = trailingslashit_snapshot($_SESSION['restore_form']['snapshot']['extract-path'])
		 	."snapshot_manifest.txt";

		if (!file_exists($_SESSION['restore_form']['snapshot']['manifest-file'])) {
			$form_errors['message-error'][] = "Snapshot archive Manifest file missing. Cannot restore/migrate via Snapshot.";
			return $form_errors;
		}
		$manifest_data = snapshot_utility_consume_archive_manifest($_SESSION['restore_form']['snapshot']['manifest-file']);
		if (is_array($manifest_data)) {
			$_SESSION['restore_form']['snapshot']['manifest-data'] = $manifest_data;
			$form_errors['message-success'][] = "Snapshot archive Manifest located and loaded successfully.";
		}
	}



	if ( ($_SESSION['restore_form']['wordpress']['reload'] == "yes") && (isset($_SESSION['restore_form']['snapshot']['manifest-data']['WP_VERSION'])) ) {

		$_SESSION['restore_form']['wordpress']['archive-file-remote'] = 'http://wordpress.org/wordpress-'.
			$_SESSION['restore_form']['snapshot']['manifest-data']['WP_VERSION']. '.zip';

		$_SESSION['restore_form']['wordpress']['archive-file-local'] = dirname(__FILE__) ."/_wordpress/file/".
			basename($_SESSION['restore_form']['wordpress']['archive-file-remote']);

		$func_ret = remote_url_to_local_file($_SESSION['restore_form']['wordpress']['archive-file-remote'],
		 	$_SESSION['restore_form']['wordpress']['archive-file-local']);

		if ( (!file_exists($_SESSION['restore_form']['wordpress']['archive-file-local']))
		  || (!filesize($_SESSION['restore_form']['wordpress']['archive-file-local'])) ) {
			$form_errors['message-error'][] = "Attempted to download WordPress file to local [".
			$_SESSION['restore_form']['wordpress']['archive-file-local'] ."]. File not found or is empty. Check parent folder permissions and reload the page.";
			return $form_errors;

		} else {
			$form_errors['message-success'][] = "Remote WordPress  Archive [".
			 	basename($_SESSION['restore_form']['wordpress']['archive-file-local']) ."] downloaded successfully.";

			// Extract WordPress files into place
			$_SESSION['restore_form']['wordpress']['extract-path'] = dirname(__FILE__) ."/_wordpress/extract/";

			$unzip_ret = unzip_archive($_SESSION['restore_form']['wordpress']['archive-file-local'], $_SESSION['restore_form']['wordpress']['extract-path']);
			if (file_exists($_SESSION['restore_form']['wordpress']['extract-path'] ."/wordpress")) {
				$_SESSION['restore_form']['wordpress']['extract-path'] = $_SESSION['restore_form']['wordpress']['extract-path'] ."/wordpress";

				$form_errors['message-success'][] = "WordPress Archive extracted successfully.";
			}

			move_tree($_SESSION['restore_form']['wordpress']['extract-path'], $_SESSION['restore_form']['wordpress']['install-path']);
		}
	}


	return $form_errors;
}

function step_2_show_form($form_errors = array()) {

	//echo "_SESSION<pre>"; print_r($_SESSION); echo "</pre>";

	if ((isset($form_errors['message-success'])) && (count($form_errors['message-success']))) {
		echo "<p>";
		foreach($form_errors['message-success'] as $message) {
			echo '<span class="restore-success">'. $message ."</span><br />";
		}
		echo "</p>";
	}
	if ((isset($form_errors['message-error'])) && (count($form_errors['message-error']))) {
		echo "<p>";
		foreach($form_errors['message-error'] as $message) {
			echo '<span class="restore-error">'. $message ."</span><<br />";
		}
		echo "</p>";
	}
	?>
	<h2>Processing Step: 2 - Verify Information</h2>
	<p>Please review the information below. This information was gathered from the Snapshot archive. Please ensure this matches the blog and database information for this site.</p>
	<p>If the Home URL and/or Site URL value entered here are different than the values found in the manifest the URLs will be updated during the restore processing</p>

	<form action="?step=3" method="post" class="restore_form">

		<p><label for="snapshot-restore-worpress-wpconfig">WordPress Configuration File (wp-config.php)</label> - If you are recovering from a broken existing site or migrating a site you usually want to reuse the working wp-config.php file in your site root. Or you can select to use the configuration file from the Snapshot Archive.<br />
			<?php
				if (!isset($_SESSION['restore_form']['wordpress']['wp-config'])) {
					$_SESSION['restore_form']['wordpress']['wp-config'] = "existing";
				}
			?>
			<?php
				if (isset($form_errors['form']['wordpress']['wp-config'])) {
					?><span class="restore-error"><?php echo $form_errors['form']['wordpress']['wp-config']; ?></span><br /><?php
				}
			?>
			<select id="snapshot-restore-worpress-reload" name="restore[wordpress][wp-config]">
				<option value="existing" <?php if ($_SESSION['restore_form']['wordpress']['wp-config'] == "existing") {
						echo ' selected="selected" '; } ?>>Use Existing</option>
				<?php
					$snapshot_wp_config = trailingslashit_snapshot($_SESSION['restore_form']['snapshot']['extract-path']) ."www/wp-config.php";
					if (file_exists($snapshot_wp_config)) {
						?>
						<option value="snapshot" <?php if ($_SESSION['restore_form']['wordpress']['wp-config'] == "snapshot") {
							echo ' selected="selected" '; } ?>>Use Snapshot</option>
						<?php
					}
				?>
			</select><br />
		</p>

		<p><label for="snapshot-restore-wordpress-home-url">Home URL</label> <span class="description">Manifest : <?php echo $_SESSION['restore_form']['snapshot']['manifest-data']['HOME'] ?></span><br />
		<?php
			if (isset($form_errors['form']['wordpress']['home-url'])) {
				?><span class="restore-error"><?php echo $form_errors['form']['wordpress']['home-url']; ?></span><br /><?php
			}
			if (!isset($_SESSION['restore_form']['wordpress']['home-url'])) {
				$_SESSION['restore_form']['wordpress']['home-url'] = $_SESSION['restore_form']['snapshot']['manifest-data']['HOME'];
			}
		?>
		<input type="text" id="snapshot-restore-wordpress-home-url" name="restore[wordpress][home-url]"
			value="http://<?php echo $_SERVER['HTTP_HOST']; /* $_SESSION['restore_form']['wordpress']['home-url']; */ ?>" /><br />

		<p><label for="snapshot-restore-snapshot-home-url">Site URL</label> <span class="description">Manifest : <?php echo $_SESSION['restore_form']['snapshot']['manifest-data']['SITEURL'] ?></span><br />
		<?php
			if (isset($form_errors['form']['wordpress']['site-url'])) {
				?><span class="restore-error"><?php echo $form_errors['form']['wordpress']['site-url']; ?></span><br /><?php
			}
			if (!isset($_SESSION['restore_form']['wordpress']['site-url'])) {
				$_SESSION['restore_form']['wordpress']['site-url'] = $_SESSION['restore_form']['snapshot']['manifest-data']['SITEURL'];
			}
		?>
		<input type="text" id="snapshot-restore-wordpress-home-url" name="restore[wordpress][site-url]"
			value="http://<?php echo $_SERVER['HTTP_HOST']; /* $_SESSION['restore_form']['wordpress']['site-url']; */ ?>" /><br />


		<p><label for="snapshot-restore-wordpress-home-url">Upload Path</label> <span class="description">(relative to <?php echo $_SESSION['restore_form']['wordpress']['install-path']; ?>)</span><br />
		<?php
			if (isset($form_errors['form']['wordpress']['upload-path'])) {
				?><span class="restore-error"><?php echo $form_errors['form']['wordpress']['upload-path']; ?></span><br /><?php
			}
			if (!isset($_SESSION['restore_form']['wordpress']['upload-path'])) {
				$_SESSION['restore_form']['wordpress']['upload-path'] = $_SESSION['restore_form']['snapshot']['manifest-data']['UPLOAD_PATH'];
			}
		?>
		<input type="text" id="snapshot-restore-wordpress-home-url" name="restore[wordpress][upload-path]"
			value="<?php echo $_SESSION['restore_form']['wordpress']['upload-path']; ?>" /><br />

		<p>In the next step you will be asked to verify the database connection information from the WordPress Configuration file (wp-config.php).</p>
		<p><input type="submit" value="Next Step" /></p>
	</form>
	<?php
}

function step_2_validate_form($restore_form) {
	$form_errors = array();
	$form_errors['form'] = array();
	$form_errors['message-error'] = array();
	$form_errors['message-success'] = array();

	if ( (!isset($restore_form['wordpress']['wp-config'])) || (!strlen($restore_form['wordpress']['wp-config'])) ) {
		$form_errors['form']['wordpress']['wp-config'] = "WordPress wp-config cannot be empty.";
		return $form_errors;
	}
	if (($restore_form['wordpress']['wp-config'] != "existing") && ($restore_form['wordpress']['wp-config'] != "snapshot")) {
		$form_errors['form']['wordpress']['wp-config'] = "WordPress wp-config invalid value given.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['wp-config'] = $restore_form['wordpress']['wp-config'];


	if ( (!isset($restore_form['wordpress']['home-url'])) || (!strlen($restore_form['wordpress']['home-url'])) ) {
		$form_errors['form']['wordpress']['home-url'] = "Home URL cannot be empty.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['home-url'] = $restore_form['wordpress']['home-url'];

	if ( (!isset($restore_form['wordpress']['site-url'])) || (!strlen($restore_form['wordpress']['site-url'])) ) {
		$form_errors['form']['wordpress']['site-url'] = "Site URL cannot be empty.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['site-url'] = $restore_form['wordpress']['site-url'];

	if ( (!isset($restore_form['wordpress']['upload-path'])) || (!strlen($restore_form['wordpress']['upload-path'])) ) {
		$form_errors['form']['wordpress']['upload-path'] = "Upload Path cannot be empty.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['upload-path'] = $restore_form['wordpress']['upload-path'];


	// If the user chose to re-use the existing wp-config.php in the root then remove the copy from the snapshot archive
	if ($_SESSION['restore_form']['wordpress']['wp-config'] == "existing") {
		$snapshot_wp_config = trailingslashit_snapshot($_SESSION['restore_form']['snapshot']['extract-path']) ."www/wp-config.php";
		if (file_exists($snapshot_wp_config))
			unlink($snapshot_wp_config);
	}

	move_tree(trailingslashit_snapshot($_SESSION['restore_form']['snapshot']['extract-path']) ."www/wp-content",
		trailingslashit_snapshot($_SESSION['restore_form']['wordpress']['install-path']) ."wp-content" , true);

	return $form_errors;
}

function step_3_show_form($form_errors) {

	//echo "_SESSION<pre>"; print_r($_SESSION); echo "</pre>";
	echo "_SESSION<pre>"; print_r($_SESSION['restore_form']['wordpress']['wp-config-db']); echo "</pre>";

	if ((isset($form_errors['message-success'])) && (count($form_errors['message-success']))) {
		echo "<p>";
		foreach($form_errors['message-success'] as $message) {
			echo '<span class="restore-success">'. $message ."</span><br />";
		}
		echo "</p>";
	}
	if ((isset($form_errors['message-error'])) && (count($form_errors['message-error']))) {
		echo "<p>";
		foreach($form_errors['message-error'] as $message) {
			echo '<span class="restore-error">'. $message ."</span><<br />";
		}
		echo "</p>";
	}

	?>
	<h2>Processing Step: 3 - Database Information Review</h2>

	<form action="?step=4" method="post" class="restore_form">

		<?php
			if ((isset($_SESSION['restore_form']['wordpress']['wp-config-db']['DB_CONNECTION']))
			 && (count($_SESSION['restore_form']['wordpress']['wp-config-db']['DB_CONNECTION']))) {
				?><p class="restore-error"><?php
				foreach($_SESSION['restore_form']['wordpress']['wp-config-db']['DB_CONNECTION'] as $error_text) {
					echo $error_text ."<br />";
				}
				?></p><?php
			} else {
				?><p><strong>Connection to database successful</strong></p><?php
			}
		?>
		<p>
			<label for="snapshot-restore-wp-config-db-name">Database Name<br />
			<?php
				if (isset($form_errors['wordpress']['wp-config-db']['DB_NAME'])) {
					?><span class="restore-error"><?php echo $form_errors['wordpress']['wp-config-db']['DB_NAME']; ?></span><br /><?php
				}
			?>
			<input type="text" id="snapshot-restore-wp-config-db-name" name="restore[wordpress][wp-config-db][DB_NAME]"
				value="<?php echo $_SESSION['restore_form']['wordpress']['wp-config-db']['DB_NAME'] ?>" />
		</p>


		<p><label for="snapshot-restore-wp-config-db-user">Database User<br />
			<?php
				if (isset($form_errors['wordpress']['wp-config-db']['DB_USER'])) {
					?><span class="restore-error"><?php echo $form_errors['wordpress']['wp-config-db']['DB_USER']; ?></span><br /><?php
				}
			?>
			<input type="text" id="snapshot-restore-wp-config-db-user" name="restore[wordpress][wp-config-db][DB_USER]"
				value="<?php echo $_SESSION['restore_form']['wordpress']['wp-config-db']['DB_USER'] ?>" />
		</p>

		<p><label for="snapshot-restore-wp-config-db-password">Database Password<br />
			<?php
				if (isset($form_errors['wordpress']['wp-config-db']['DB_PASSWORD'])) {
					?><span class="restore-error"><?php echo $form_errors['wordpress']['wp-config-db']['DB_PASSWORD']; ?></span><br /><?php
				}
			?>
			<input type="text" id="snapshot-restore-wp-config-db-password" name="restore[wordpress][wp-config-db][DB_PASSWORD]"
				value="<?php echo $_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PASSWORD'] ?>" />
		</p>

		<p><label for="snapshot-restore-wp-config-db-host">Database Host<br />
			<?php
				if (isset($form_errors['wordpress']['wp-config-db']['DB_HOST'])) {
					?><span class="restore-error"><?php echo $form_errors['wordpress']['wp-config-db']['DB_HOST']; ?></span><br /><?php
				}
			?>
			<input type="text" id="snapshot-restore-wp-config-db-host" name="restore[wordpress][wp-config-db][DB_HOST]"
				value="<?php echo $_SESSION['restore_form']['wordpress']['wp-config-db']['DB_HOST'] ?>" />
		</p>

		<p><label for="snapshot-restore-wordpress-db-prefix">Database Table Prefix</label> <span class="description">Snapshot Manifest base prefix: <?php echo $_SESSION['restore_form']['snapshot']['manifest-data']['DB_PREFIX'] ?></span><br />
			<?php
				if (isset($form_errors['wordpress']['wp-config-db']['DB_PREFIX'])) {
					?><span class="restore-error"><?php echo $form_errors['wordpress']['wp-config-db']['DB_PREFIX']; ?></span><br /><?php
				}
				if (!isset($_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PREFIX'])) {
					$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PREFIX'] = $_SESSION['restore_form']['snapshot']['manifest-data']['PREFIX'];
				}
			?>
		<input type="text" id="snapshot-restore-wordpress-db-prefix" name="restore[wordpress][wp-config-db][DB_PREFIX]"
			value="<?php echo $_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PREFIX']; ?>" /><br />
		<span class="description">If you change the prefix you need to manually update the wp-config.php file value.</span><br />
		<?php
			$blog_id = intval($_SESSION['restore_form']['snapshot']['manifest-data']['BLOG-ID']);
			if ($blog_id > 1) {
				?><span class="description">Appears the Snapshot Archive is from a Multisite blog. The original blog ID is '<?php
					echo $_SESSION['restore_form']['snapshot']['manifest-data']['BLOG-ID']
				?>' and the original table prefix is '<?php
					echo $_SESSION['restore_form']['snapshot']['manifest-data']['PREFIX'] ?></span><?php
			}

			?>
		</p>

		<p>When you submit this form the script will start importing the database tables.</p>
		<p><input type="submit" value="Next Step" /></p>
	</form>

	<?php
}

function step_3_validate_form($restore_form) {

	$form_errors = array();

	if ( (!isset($restore_form['wordpress']['wp-config-db']['DB_NAME'])) || (!strlen($restore_form['wordpress']['wp-config-db']['DB_NAME'])) ) {
		$form_errors['wordpress']['wp-config-db']['DB_NAME'] = "Database Name cannot be empty.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_NAME'] = $restore_form['wordpress']['wp-config-db']['DB_NAME'];


	if ( (!isset($restore_form['wordpress']['wp-config-db']['DB_USER'])) || (!strlen($restore_form['wordpress']['wp-config-db']['DB_USER'])) ) {
		$form_errors['wordpress']['wp-config-db']['DB_USER'] = "Database User cannot be empty.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_USER'] = $restore_form['wordpress']['wp-config-db']['DB_USER'];


	if ( (!isset($restore_form['wordpress']['wp-config-db']['DB_PASSWORD'])) || (!strlen($restore_form['wordpress']['wp-config-db']['DB_PASSWORD'])) ) {
		$form_errors['wordpress']['wp-config-db']['DB_PASSWORD'] = "Database Password cannot be empty.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PASSWORD'] = $restore_form['wordpress']['wp-config-db']['DB_PASSWORD'];


	if ( (!isset($restore_form['wordpress']['wp-config-db']['DB_HOST'])) || (!strlen($restore_form['wordpress']['wp-config-db']['DB_HOST'])) ) {
		$form_errors['wordpress']['wp-config-db']['DB_HOST'] = "Database Host cannot be empty.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_HOST'] = $restore_form['wordpress']['wp-config-db']['DB_HOST'];

	if ( (!isset($restore_form['wordpress']['wp-config-db']['DB_PREFIX'])) || (!strlen($restore_form['wordpress']['wp-config-db']['DB_PREFIX'])) ) {
		$form_errors['wordpress']['wp-config-db']['DB_PREFIX'] = "Database Prefix cannot be empty.";
		return $form_errors;
	}
	$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PREFIX'] = $restore_form['wordpress']['wp-config-db']['DB_PREFIX'];

	$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_CONNECTION'] = db_connection_test(
		$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_NAME'],
		$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_USER'],
		$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PASSWORD'],
		$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_HOST']);

	if (count($_SESSION['restore_form']['wordpress']['wp-config-db']['DB_CONNECTION'])) {
		$form_errors['wordpress']['wp-config-db']['DB_CONNECTION'] = $_SESSION['restore_form']['wordpress']['wp-config-db']['DB_CONNECTION'];
		return $form_errors;
	}
	return $form_errors;
}

function step_4_show_form($form_errors) {

	?>
	<h2>Processing Step: 4 - Database Import</h2>

	<form action="?step=4" method="post" class="restore_form">
		<?php
			$db_link = mysql_connect($_SESSION['restore_form']['wordpress']['wp-config-db']['DB_HOST'],
				$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_USER'],
				$_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PASSWORD']);

			if (!$db_link) {
				echo "Could not connect to MySQL: ". mysql_error();
				die();
			}

			$db_selected = mysql_select_db($_SESSION['restore_form']['wordpress']['wp-config-db']['DB_NAME'], $db_link);
			if (!$db_selected) {
				echo "Can't select database [". $_SESSION['restore_form']['wordpress']['wp-config-db']['DB_NAME'] ."]: ". mysql_error();
			}
			if ($_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PREFIX'] !== $_SESSION['restore_form']['snapshot']['manifest-data']['PREFIX']) {
				$TABLE_REPLACE_PREFIX_STR = $_SESSION['restore_form']['wordpress']['wp-config-db']['DB_PREFIX'];
			} else {
				$TABLE_REPLACE_PREFIX_STR = '';
			}
			//$TABLE_REPLACE_PREFIX_STR = 'wp_br549_';

			if ((isset($_SESSION['restore_form']['snapshot']['manifest-data']['TABLES']))
			 && (is_array($_SESSION['restore_form']['snapshot']['manifest-data']['TABLES']))
			 && (count($_SESSION['restore_form']['snapshot']['manifest-data']['TABLES']))) {

				foreach($_SESSION['restore_form']['snapshot']['manifest-data']['TABLES'] as $tables_set_idx => $tables_set_data ) {
					if ((is_array($tables_set_data)) && (count($tables_set_data))) {
						foreach($tables_set_data as $table_name) {

							$table_file = trailingslashit_snapshot($_SESSION['restore_form']['snapshot']['extract-path']) . $table_name .".sql";
							if (!file_exists($table_file)) {
								echo "table_file not found [". $table_file ."]<br />";
								continue;
							}

							$table_file_handle = fopen($table_file, "r");
							if (!$table_file_handle) {
								echo "unable to open table_file [". $table_file ."]<br />";
								continue;
							}

							$table_name_new = '';
							$table_name_base = str_replace($_SESSION['restore_form']['snapshot']['manifest-data']['PREFIX'], '', $table_name);
							if (strlen($TABLE_REPLACE_PREFIX_STR)) {
								$table_name_new = $TABLE_REPLACE_PREFIX_STR . $table_name_base;
								echo "Processing database table: ". $table_name ." to ". $table_name_new;
							} else {
								echo "Processing database table: ". $table_name;
							}

							$table_file_create_sql 		= '';
							$table_file_create_done 	= false;
							$table_record_count 		= 0;
							while (($table_file_buffer = fgets($table_file_handle, 4096)) !== false) {

								if (!strlen( trim($table_file_buffer) )) {
									if ( ($table_file_create_done == false) && (strlen($table_file_create_sql)) ) {
										$table_file_create_done = true;
										$sql_split = explode(';', $table_file_create_sql);

										foreach($sql_split as $sql_str) {
											if (strlen($table_name_new)) {
												$sql_str = str_replace('`'. $table_name .'`', '`'.$table_name_new.'`', $sql_str);
											}
											$result = mysql_query($sql_str, $db_link);
										}
									}
								} else {
									if ($table_file_create_done == false) {
										$table_file_create_sql .= $table_file_buffer;
									} else {
										if (strlen($table_name_new)) {
											$table_file_buffer = str_replace('`'. $table_name .'`', '`'.$table_name_new.'`', $table_file_buffer);
										}
										$result = mysql_query($table_file_buffer, $db_link);
										$table_record_count += 1;
									}
								}
							}
							fclose($table_file_handle);
							echo " : total rows: ". $table_record_count ."<br />";

							//echo "table_name_base=[". $table_name_base ."]<br />";
							//echo "table_name_new=[". $table_name_new ."]<br />";
							//echo "table_name=[". $table_name ."]<br />";

							//echo "site_url=[". $_SESSION['restore_form']['wordpress']['site-url'] ."] [". $_SESSION['restore_form']['snapshot']['manifest-data']['SITEURL']."]<br />";
							//echo "home_url=[". $_SESSION['restore_form']['wordpress']['home-url'] ."] [". $_SESSION['restore_form']['snapshot']['manifest-data']['HOME']."]<br />";

							if (($_SESSION['restore_form']['wordpress']['site-url'] != $_SESSION['restore_form']['snapshot']['manifest-data']['SITEURL'])
							 || ($_SESSION['restore_form']['wordpress']['home-url'] != $_SESSION['restore_form']['snapshot']['manifest-data']['HOME'])) {

								if ($table_name_base == "options") {
									if (strlen($table_name_new))
										$table_name_replace = $table_name_new;
									else
										$table_name_replace = $table_name;
									//echo "table_name_replace=[". $table_name_replace ."]<br />";

									search_replace_table_data( $table_name_replace, $db_link,
										$_SESSION['restore_form']['snapshot']['manifest-data']['SITEURL'], $_SESSION['restore_form']['wordpress']['site-url'] );

									//die();
								}
							}
							flush();
						}
					}
				}
			} else {
				echo "Snapshot Archive manifest item 'TABLES' not set. Aborting";
			}
			mysql_close($db_link);
		?>
		<p><input type="submit" value="Finished" /></p>
	</form>
	<?php
}