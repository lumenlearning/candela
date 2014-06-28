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
    define('CANDELA_LTI_TABLE', 'candelalti');
    define('CANDELA_LTI_DB_VERSION', '1.0');

    register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
    register_uninstall_hook(__FILE__, array( __CLASS__, 'deactivate'))

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

    CandelaLTI::create_db_table();
  }

  /**
   * Do any necessary cleanup.
   */
  public static function deactivate() {
    CandelaLTI::remove_db_table();
  }

  /**
   * Responder for action lti_launch.
   */
  public static function lti_launch() {
    global $wp;

    // Currently just redirect to the blog/site homepage.
    switch_to_blog((int)$wp->query_vars['blog']);
    wp_redirect( home_url() );
    exit;
  }

  /**
   * Create a database table for storing nonces.
   */
  public static function create_db_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . CANDELA_LTI_TABLE;

    $sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      resource_link_id NOT NULL TINYTEXT,
      target_action NOT NULL TINYTEXT,
      PRIMARY KEY (id),
      UNIQUE KEY resource_link_id (resource_link_id(32))
    );";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta( $sql );

    add_option( 'candela_lti_db_version', CANDELA_LTI_DB_VERSION );
  }

  /**
   * Remove database table.
   */
  public static function remove_db_table() {
    glboal $wpdb;
    $table_name = $wpdb->prefix . CANDELA_LTI_TABLE;
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    delete_option('candela_lti_db_version');
  }

}

