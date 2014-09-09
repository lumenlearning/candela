<?php
function snapshot_scheduling_show_add_form($item=0) {

	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="snapshot-schedule-name"><?php _e('Name', SNAPSHOT_I18N_DOMAIN); ?></label></th>
			<td><input type="text" name="snapshot-schedule[name]" id="snapshot-schedule-name"
				value="<?php if (isset($item['name'])) { echo stripslashes($item['name']); } ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="type">Backup type</label></th>
			<td>
				<select name="#type">
					<option value="db" <?php if ( $options['interval'] == 'db' ) { echo 'selected'; } ?>>Database Only</option>
					<option value="full" <?php if ( $options['interval'] == 'full' ) { echo 'selected'; } ?>>Full (database + files)</option>
				</select>
			</td>
		</tr>
		<tr><th scope="row"><label for="interval">Backup interval</label></th>
			<td>
				<select name="#interval">
					<option value="monthly" <?php if ( $options['interval'] == 'monthly' ) { echo 'selected'; } ?>>Monthly</option>
					<option value="twicemonthly" <?php if ( $options['interval'] == 'twicemonthly' ) { echo 'selected'; } ?>>Twice Monthly</option>
					<option value="weekly" <?php if ( $options['interval'] == 'weekly' ) { echo 'selected'; } ?>>Weekly</option>
					<option value="daily" <?php if ( $options['interval'] == 'daily' ) { echo 'selected'; } ?>>Daily</option>
					<option value="hourly" <?php if ( $options['interval'] == 'hourly' ) { echo 'selected'; } ?>>Hourly</option>
				</select>
			</td>
		</tr>
		<tr><th scope="row"><label for="name">Date/time of first run</label></th>
			<td>
				<input type="text" name="#first_run" id="first_run" size="30" maxlength="45" value="<?php if ( !empty( $options['first_run'] ) ) { echo date('m/d/Y h:i a', $options['first_run'] ); } else { echo date('m/d/Y h:i a', time() + ( ( get_option( 'gmt_offset' ) * 3600 ) + 86400 ) ); } ?>" /> Currently <code><?php echo date( 'm/d/Y h:i a ' . get_option( 'gmt_offset' ), time() + ( get_option( 'gmt_offset' ) * 3600 ) ); ?> UTC</code> based on <a href="<?php echo admin_url( 'options-general.php' ); ?>">WordPress settings</a>.
				<br />
				<small>mm/dd/yyyy hh:mm [am/pm]</small>
			</td>
		</tr>
		<tr><th scope="row"><label for="send_ftp">Remote backup destination</label></th>
			<td>
				<span id="pb_backupbuddy_remotedestinations_list">
					<?php
					$remote_destinations = explode( '|', $options['remote_destinations'] );
					foreach( $remote_destinations as $destination ) {
						if ( !empty( $destination ) ) {
							//echo $this->_options['remote_destinations'][$destination]['title'];
							echo '<br />';
						}
					}
					?>
				</span>

				<input type="hidden" name="#remote_destinations" id="pb_backupbuddy_remotedestinations" value="<?php echo $options['remote_destinations']; ?>" />

				<div id="pb_backupbuddy_deleteafter" style="<?php if ( !isset( $_GET['edit'] ) ) { echo 'display: none; '; } ?>background-color: #EAF2FA; border: 1px solid #E3E3E3; width: 250px; padding: 10px; margin: 5px; margin-left: 0px;">
					<input type="hidden" name="#delete_after" value="0" />
					<input type="checkbox" name="#delete_after" id="delete_after" value="1" <?php if ( $options['delete_after'] == '1' ) { echo 'checked'; } ?> /> <label for="delete_after">Delete local copy after remote send </label>
				</div>
			</td>
		</tr>
	</table>
	<?php
}