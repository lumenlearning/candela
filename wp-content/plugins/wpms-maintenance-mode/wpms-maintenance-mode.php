<?php
/*
Plugin Name: WPMS Maintenance Mode
Plugin URI: https://github.com/lumenlearning/wpms-maintenance
Description: Provides an interface to make a WPMS network unavailable to everyone during maintenance, except the admin. This is a fork of <a href="https://wordpress.org/plugins/wpms-site-maintenance-mode/">WPMS Site Maintenance mode</a>.
Author: Lumen Learning
Original Author: I.T. Damager, 7 Media Web Solutions, LLC
Author URI: http://lumenlearning.com/
Version: 0.1
License: GPL
*/

class wpms_maintenance {

	var $maintenance;
	var $retryafter;
	var $message;
	var $updated;
	var $configerror;

	function wpms_maintenance() {
		add_action( 'init', array( &$this,'wpms_maintenance_init' ), 1 );
		add_action( 'network_admin_menu', array( &$this, 'add_admin_subpanel' ) );
	}

	function wpms_maintenance_init() {
		$this->updated = false;
		$this->configerror = array();
		$this->apply_settings();
		if ( $this->maintenance ) {
			return $this->shutdown();
		}
	}

	function add_admin_subpanel() {
		add_submenu_page( 'settings.php', __('WPMS Maintenance Shutdown'), __('WPMS Maintenance'), 'manage_network_options', 'wpms_site_maint', array( &$this, 'adminpage' ) );
	}

	function get_message() {
		return '<!DOCTYPE html>
<html>
	<head>
		<title>' . get_site_option( 'site_name' ) . ' is undergoing routine maintenance</title>
		<meta http-equiv="Content-Type" content="' . get_bloginfo( 'html_type' ) . '; ' . get_bloginfo( 'charset' ) . '" />
		<link rel="stylesheet" href="' . WP_PLUGIN_URL . '/wpms-maintenance-mode/css/style.css" type="text/css" media="screen" />
	</head>
  <body>
	  <section id="content">
	    <h1>We are currently under maintenance.</h1>
			<p>Our ' . get_site_option( 'site_name' ) . ' network is undergoing maintenance that will last <strong>' . $this->retryafter . ' minutes at the most</strong>.</p>
			<p>We apologize for the inconvenience, and we are working to bring you an updated and improved site.</p>
	  </section>
	</body>
</html>';
	}

	function set_defaults() {
		// do not edit here - use the admin screen
		$this->maintenance = 0;
		$this->retryafter = 60;
		$this->message = $this->get_message();
	}

	function apply_settings($settings = false) {
		if ( ! $settings ) {
			$settings = get_site_option( 'wpms_maintenance_settings' );
		}

		if ( is_array( $settings ) ) {
			foreach( $settings as $setting => $value ) {
				$this->$setting = $value;
			}
		}
		else {
			$this->set_defaults();
		}
	}

	function save_settings() {
		global $wpdb;

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wpms-maintenance-mode' ) ) {
			die('Invalid nonce');
		}

		// validate all input!
		if ( preg_match( '/^[0-9]+$/', $_POST['maintenance'] ) ) {
			$maintenance = intval( $_POST['maintenance'] );
		}
		else {
			$this->configerror[] = 'maintenance must be numeric. Default: 0 (Normal site operation)';
		}

		if ( $_POST['retryafter'] > 0 ) {
			$retryafter = intval($_POST['retryafter']);
		}
		else {
			$this->configerror[] = 'Retry After must be greater than zero minutes. Default: 60';
		}

		//$wpdb->escape() or addslashes not needed -- string is compacted into an array then serialized before saving in db
		if ( trim( $_POST['message'] ) ) {
			$message = ( get_magic_quotes_gpc() ) ? stripslashes( trim( $_POST['message'] ) ) : trim( $_POST['message'] );
		}
		else {
			$this->configerror[] = 'Please enter a message to display to visitors when the site is down. (HTML OK!)';
		}

		if ( ! empty( $this->configerror ) ) {
			return $this->configerror;
		}

		$settings = compact('maintenance','retryafter','message');

		$changed = false;
		foreach( $settings as $setting => $value ) {
			if ( $this->$setting != $value ) {
				$changed = true;
			}
		}

		if ( $changed ) {
			update_site_option( 'wpms_maintenance_settings', $settings );
			$this->apply_settings( $settings );
			return $this->updated = true;
		}
	}

	function delete_settings() {
		global $wpdb;
		$settings = get_site_option( 'wpms_maintenance_settings' );
		if ( $settings ) {
			$wpdb->query( "DELETE FROM $wpdb->sitemeta WHERE `meta_key` = 'wpms_maintenance_settings'" );
			wp_cache_delete('wpms_maintenance_settings','site-options');

			$this->set_defaults();
			return $this->updated = true;
		}
	}

	function urlend( $end ) {
		return ( substr( $_SERVER['REQUEST_URI'], strlen($end) * -1 ) == $end) ? true : false;
	}

	function shutdown() {
		global $wpdb;
		get_currentuserinfo();
		if ( is_super_admin() ) {
			return; //allow admin to use site normally
		}

		if ( $wpdb->blogid == 1 && $this->urlend( 'wp-login.php' ) ) {
			return; //I told you *not* to log out, but you did anyway. duh!
		}

		if ( $this->maintenance == 2 && $wpdb->blogid != 1 ) {
			return; //user blogs on, main blog off
		}
		if ( $this->maintenance == 1 && $wpdb->blogid == 1 ) {
			return; //main blog on, user blogs off
		}

		header('HTTP/1.1 503 Service Unavailable');
		header('Retry-After: ' . $this->retryafter * 60 ); //seconds
		if ( !$this->urlend( 'feed/' ) && !$this->urlend( 'trackback/' ) && !$this->urlend( 'xmlrpc.php' ) ) {
			echo stripslashes($this->message);
		}
		exit();
	}

	function adminpage() {
		get_currentuserinfo();

		if ( ! is_super_admin() ) {
			die(__('<p>You do not have permission to access this page.</p>'));
		}

		if ( ! empty( $_POST['action'] ) && $_POST['action'] == 'update' ) {
			if ( empty( $_POST['reset'] ) ) {
				$this->save_settings();
			}
			else {
				$this->delete_settings();
			}
		}

		if ($this->updated) {
			print '<div id="message" class="updated fade"><p>' . __('Options saved.') . '</p></div>';
		}

		if ( !empty( $this->configerror ) ) {
			print '<div class="error"><p>' . implode('<br />',$this->configerror) . '</p></div>';
		}

		switch ( $this->maintenance ) {
			case 1:
	  		print '<div class="error"><p>' . __('WARNING: YOUR USER BLOGS ARE CURRENTLY DOWN!') . '</p></div>';
	  		break;
	  	case 2:
				print '<div class="error"><p>' . __('WARNING: YOUR MAIN BLOG IS CURRENTLY DOWN!') . '</p></div>';
				break;
			case 3:
				print '<div class="error"><p>' . __('WARNING: YOUR ENTIRE SITE IS CURRENTLY DOWN!') . '</p></div>';
				break;
			}

			$this->adminform();
	}

	function adminform() {
		?>
		<div class="wrap">
		  <h2><?php _e('WPMS Site Maintenace'); ?></h2>
		  <fieldset>
		  <p><?php _e('This plugin shuts down your site for maintenance by sending feed readers, bots, and browsers an http response code 503 and the Retry-After header'); ?> (<a href="ftp://ftp.isi.edu/in-notes/rfc2616.txt" target="_blank">rfc2616</a>). <?php _e('It displays your message except when feeds, trackbacks, or other xml pages are requested.'); ?></p>
		  <p><?php _e('Choose site UP or DOWN, retry time (in minutes) and your message.'); ?></p>
		  <p><em><?php _e('The site will remain fully functional for admin users.'); ?> <span style="color:#CC0000;"><?php _e('Do not log out while the site is down!'); ?></span><br />
		  <?php _e('If you log out (and lock yourself out of the site) visit'); ?> <?php bloginfo_rss('url') ?>/wp-login.php <?php _e('to log back in.'); ?></em></p>
		  <form name="maintenanceform" method="post" action="">
		  	<input type="hidden" name="_wpnonce" value="<?php print wp_create_nonce('wpms-maintenance-mode'); ?>" />
		    <p><label><input type="radio" name="maintenance" value="0"<?php checked(0, $this->maintenance); ?> /> <?php _e('SITE UP (Normal Operation)'); ?></label><br />
		       <label><input type="radio" name="maintenance" value="1"<?php checked(1, $this->maintenance); ?> /> <?php _e('USER BLOGS DOWN, MAIN BLOG UP!'); ?></label><br />
		       <label><input type="radio" name="maintenance" value="2"<?php checked(2, $this->maintenance); ?> /> <?php _e('MAIN BLOG DOWN, USER BLOGS UP!'); ?></label><br />
		       <label><input type="radio" name="maintenance" value="3"<?php checked(3, $this->maintenance); ?> /> <?php _e('ENTIRE SITE DOWN!'); ?></label></p>
		    <p><label><?php _e('Retry After'); ?> <input name="retryafter" type="text" id="retryafter" value="<?php echo $this->retryafter; ?>" size="3" /> <?php _e('minutes.'); ?></label></p>
		    <p><label><?php _e('HTML page displayed to site visitors:'); ?><br />
		      <textarea name="message" cols="125" rows="10" id="message"><?php echo stripslashes($this->message); ?></textarea></label></p>
			<p>&nbsp;</p>
			<p><label><input name="reset" type="checkbox" value="1" /> <?php _e('Reset all settings to default'); ?></label></p>
		    <p class="submit">
		      <input name="action" type="hidden" id="action" value="update" />
		      <input type="submit" name="Submit" value="Update Settings" />
		    </p>
		  </form>
		  </fieldset>
		</div>
		<?php
	}
}

//begin execution
if ( defined( 'ABSPATH' ) ) {
	$wpms_maintenance = new wpms_maintenance();
}
