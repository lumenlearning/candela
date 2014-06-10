<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Candela LTI
 * Description:       LTI Integration for Candela
 * Version:           0.1
 * Author:            Lumen Learning
 * Author URI:        http://lumenlearning.com
 * Text Domain:       lti
 * License:           MIT
 * GitHub Plugin URI: https://github.com/lumenlearning/candela-lti
 */

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

// Do our necessary plugin setup and add_action routines.
CandelaLTI::init();

class CandelaLTI {
  /**
   * Takes care of registering our hooks and setting constants.
   */
  public static function init() {
    register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
    add_action( 'lti_launch', array( __CLASS__, 'lti_launch') );
	}

  /**
   * Ensure all dependencies are set and available.
   */
  public static function activate() {
    // Require lti plugin
    if ( ! is_plugin_active( 'lti/lti.php' ) and current_user_can( 'activate_plugins' ) ) {
      wp_die('This plugin requires the LTI plugin to be installed and active. <br /><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');' )';
    }
  }

  /**
   * Responder for action lti_launch.
   */
  public static function lti_launch() {
    // Currently just redirect to the blog/site homepage.
    wp_redirect( home_url() );
    exit;
  }

}

