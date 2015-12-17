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

function create_db_table()
{
  // globalize wpdb && charset_collate so that it's accessible
  global $wpdb;
  global $charset_collate;

  // name the table using wp prefix best practice ($table_name = 'wp_lti_table')
  $table_name = $wpdb->prefix . 'lti_table';

  // test to see if the db table exists...
  // if it doesn't, make the db table
  if ( $wpdb->get_var('SHOW TABLES LIKE ' . $table_name) != $table_name )
  {
    $sql = 'CREATE TABLE ' . $table_name . '(
    id INTEGER(10) UNSIGNED AUTO_INCREMENT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lti_user_id TEXT(100),
    lti_course_id TEXT(100),
    lti_account_id TEXT(100),
    blog_id TEXT(100),
    page_id TEXT(100),
    PRIMARY KEY (id) )';

    // include dbDelta(), which compares the current table structure to the
    // desired table structure, and adds/modifies as necessary
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // record db table version number
    add_option('lti_table_database_version', '1.0');
  }
}

register_activation_hook(__FILE__, 'create_db_table');


// Start session
add_action( 'init', 'my_session' );
function my_session()
{
  global $wp_session;

  $wp_session = array(
    'user_id'    => 'user-id-123',
    'context_id' => 'context-id-456',
    'account_id' => 'account-id-789'
  );
}

// Insert row into db table when user navigates to page
add_action( 'wp_loaded', 'insert_row' );
function insert_row()
{

  if (!is_admin()) {
    global $wp_session;

    if (isset($wp_session['user_id'])) {
      global $wpdb;

      $table_name = "wp_lti_table";
      $data = array(
        'timestamp' => current_time('mysql'),
        'lti_user_id' => '4',
        'lti_course_id' => '4',
        'lti_account_id' => '4',
        'blog_id' => '1',
        'page_id' => '2'
      );

      $wpdb->insert( $table_name, $data );
    }
  }
}

?>
