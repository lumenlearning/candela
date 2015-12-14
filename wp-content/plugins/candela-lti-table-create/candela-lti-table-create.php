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

function lti_table_activate()
{
  // globalize wpdb && charset_collate so that it's accessible
  global $wpdb;
  global $charset_collate;

  // name the table using wp prefix best practice ($table_name = 'wp_bdetector')
  $table_name = $wpdb->prefix . "lti_table";

  // test to see if the db table exists...
  // if it doesn't, make the db table
  if ( $wpdb->get_var('SHOW TABLES LIKE ' . $table_name) != $table_name )
  {
    $sql = 'CREATE TABLE ' . $table_name . '(
    id INTEGER(10) UNSIGNED AUTO_INCREMENT,
    hit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INTEGER(10),
    context_id INTEGER(10),
    account_id INTEGER(10),
    PRIMARY KEY (id) )';

    // include dbDelta(), which compares the current table structure to the
    // desired table structure, and adds/modifies as necessary
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // record db table version number
    add_option('lti_table_database_version', '1.0');
  }
}

register_activation_hook(__FILE__, 'lti_table_activate');

?>
