<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Candela LTI Table Create
 * Description:       Create custom database table for LTI data
 * Version:           1.0
 * Author:            Lumen Learning
 * Author URI:        http://lumenlearning.com
 * License:           MIT
 * Plugin Github URI: https://github.com/lumenlearning/candela-lti-table-create
 */

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

Candela_LTI_Table_Create::init();

class Candela_LTI_Table_Create
{
  public static function init()
  {
    register_activation_hook(__FILE__, array(__CLASS__, 'create_db_table' ));

    add_action( 'init', array( __CLASS__, 'create_db_table' ) );
    add_action( 'init', array( __CLASS__, 'my_session' ) );
    add_action( 'wp_loaded', array( __CLASS__, 'insert_row' ) );
  }

  public static function create_db_table()
  {
    global $wpdb;
    global $charset_collate;

    $table_name = $wpdb->prefix . 'lti_table';

    if ( $wpdb->get_var('SHOW TABLES LIKE ' . $table_name) != $table_name )
    {
      $sql = "CREATE TABLE " . $table_name . "(
      id INTEGER(10) UNSIGNED AUTO_INCREMENT,
      timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      lti_user_id TEXT(10),
      lti_course_id TEXT(10),
      lti_account_id TEXT(10),
      blog_id TEXT(10),
      page_id TEXT(10),
      PRIMARY KEY (id) )";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta( $sql );

      add_option('lti_table_database_version', '1.0');
    }
  }

  public static function my_session()
  {
    global $wp_session;


    $wp_session = array(
      'user_id'    => 'user-id-123',
      'context_id' => 'context-id-456',
      'account_id' => 'account-id-789'
    );
  }

  public static function insert_row()
  {
    if (!is_admin()) {
      global $wp_session;

      if (isset($wp_session['user_id'])) {
        global $wpdb;

        $table_name = "wp_lti_table";
        $current_user = wp_get_current_user();

        $data = array(
          'timestamp' => current_time( 'mysql' ), // this gives a timestamp that is 8 hours ahead
          'lti_user_id' => $current_user->ID, // this is only wp user id...must get lti user id somehow
          'lti_course_id' => '4',
          'lti_account_id' => '4',
          'blog_id' => '1',
          'page_id' => '2'
        );

        $wpdb->insert( $table_name, $data );
      }
    }
  }

}

?>
