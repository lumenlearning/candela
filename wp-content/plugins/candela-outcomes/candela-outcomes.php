<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Candela Outcomes
 * Description:       Learning Outcomes Implementation for wordpress.
 * Version:           0.1
 * Author:            Lumen Learning
 * Author URI:        http://lumenlearning.com
 * Text Domain:       candela_outcomes
 * License:           MIT
 * GitHub Plugin URI: https://github.com/lumenlearning/candela
 */
namespace Candela\Outcomes;

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

include_once( __DIR__  . '/class.widget.php' );
include_once( __DIR__ . '/class.outcome.php' );
include_once( __DIR__ . '/class.collection.php' );

plugin_init();

/**
 * Takes care of registering our hooks and setting constants.
 */
function plugin_init() {


  define('DB_VERSION', '1.0' );

  register_activation_hook( __FILE__,  __NAMESPACE__ . '\activate' );
  register_uninstall_hook(__FILE__, __NAMESPACE__ . '\deactivate' );

  add_action( 'init', __NAMESPACE__ . '\init' );
  add_action( 'parse_request', __NAMESPACE__ . '\parse_request' );
  add_action( 'admin_init', __NAMESPACE__ . '\admin_init' );
  add_action( 'admin_menu', __NAMESPACE__ . '\admin_menu' );
  add_action( 'pressbooks_new_blog', __NAMESPACE__ . '\pressbooks_new_blog' );
  add_action( 'admin_notices', __NAMESPACE__ . '\show_admin_notices' );


  add_filter( 'wpmu_drop_tables', __NAMESPACE__ . '\delete_blog' );
  add_filter( 'template_include', __NAMESPACE__ . '\template_include' );
}

/**
 * Implementation of activation_hook
 */
function activate( $networkwide ) {
  global $wpdb;
  if ( is_multisite() && $networkwide ) {
    $current_blog = $wpdb->blogid;
    $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    foreach ($blogs as $bid) {
        switch_to_blog($bid);
        _activate();
    }
    switch_to_blog($current_blog);
    return;
  }
  _activate();
}

/**
 * Internal callback function used to do all activation details for all sites.
 */
function _activate() {
  db_collection_table();
  db_outcome_table();
  update_option('candela_outcomes_db_version', DB_VERSION);

  flush_rewrite_rules();
}

/**
 * Implementation of uninstall_hook().
 */
function deactivate() {
  flush_rewrite_rules();
}

/**
 * Implementation of filter 'wpmu_drop_tables'
 */
function delete_blog() {
  remove_db_tables();
  delete_option('candela_outcomes_db_version');
}

/**
 * Implementation of action 'pressbooks_new_blog'
 */
function pressbooks_new_blog( ) {
  _activate();
}

/**
 * Implementation of action 'init'.
 */
function init() {
  add_rewrite_endpoint( 'outcomes', EP_ROOT );

}

/**
 * Implementation of action parse_request.
 */
function parse_request() {
  global $wp;
  if ( ! empty($wp->query_vars['outcomes'] ) ) {
    // Indicate this is an outcomes request (used in template_include);
    $wp->query_vars['outcomes_page'] = TRUE;
    $params = explode('/', $wp->query_vars['outcomes'] );

    if ( ! empty( $params[0] && in_array($params[0], array('outcome', 'collection') ) ) ) {
      $wp->query_vars['outcomes_type'] = $params[0];
    }
    else {
      $wp->query_vars['outcomes_type'] = 'invalid';
    }

    if ( ! empty( $params[1] && Base::isValidUUID( $params[1] ) ) ) {
      $wp->query_vars['outcomes_uuid'] = $params[1];
    }
  }
}

function get_outcome() {
  $uuid = get_query_var( 'outcomes_uuid' );
  $outcome = new Outcome;
  $outcome->load( $uuid );
  include(__DIR__ . '/outcome.single.php');
}

function get_collection() {
  $uuid = get_query_var( 'outcomes_uuid' );
  $collection = new Collection;
  $collection->load( $uuid );
  include(__DIR__ . '/collection.single.php');
}

/**
 * Implementation of filter 'template_include'
 */
function template_include( $template_file ) {
  global $wp;

  if ( get_query_var( 'outcomes_page' ) ) {
    $uuid = get_query_var( 'outcomes_uuid' );
    $type = get_query_var( 'outcomes_type' );

    switch ( $type ) {
      case 'outcome':
        $outcome = new Outcome;
        $outcome->load( $uuid );
        if ( ! $outcome->hasErrors() ) {
          return get_template( 'outcome' );
        }
        else {
          // TODO: more descriptive error?
          return get_404_template();
        }
        break;
      case 'collection':
        $collection = new Collection;
        $collection->load( $uuid );
        if ( ! $collection->hasErrors() ) {
          return get_template( 'collection' );
        }
        else {
          // TODO: more descriptive error?
          return get_404_template();
        }
        break;

      default:
        // TODO: more descriptive error?
        return get_404_template();
        break;
    }
  }
  return $template_file;
}

/**
 * Helper function to determine proper path for a template.
 */
function get_template( $type ) {
  $filename = $type . '.template.php';
  $filepath = plugin_dir_path( __FILE__ ) . $filename;
  if (file_exists( $filepath ) ) {
    return $filepath;
  }

  return get_404_template();
}

/**
 * Implementation of action 'admin_init'
 */
function admin_init() {
  setup_capabilities();

  process_form();
}

function load_item_by_uuid( $uuid, $type ) {
  static $cache = NULL;

  if ( ! isset( $cache[$type][$uuid] ) ) {
    if ( in_array($type, array('collection', 'outcome' ) ) ) {
      global $wpdb;
      $table_name = $wpdb->prefix . 'outcomes_' . $type;
      $item = $wpdb->get_row(
        $wpdb->prepare("
          SELECT * FROM $table_name
          WHERE uuid = %s",
          $uuid
        )
      );
      $cache[$type][$uuid] = $item;
    }
    else {
      $cache[$type][$uuid] = FALSE;
    }
  }
  return $cache[$type][$uuid];
}

/**
 * Setup our new capabilities.
 */
function setup_capabilities() {
  global $wp_roles;

  $wp_roles->add_cap( 'administrator', 'manage_outcomes' );
}

/**
 * Grab incoming form submissions and dispatch so we can redirect before headers
 * are sent.
 */
function process_form() {

  if ( ! empty($_GET['page'] ) ) {
    switch ( $_GET['page'] ) {
      case 'edit_collection':
        $collection = new Collection();
        if ( $collection->isValidNonce() ) {
          $collection->processForm();
        }
        break;
      case 'edit_outcome':
        $outcome = new Outcome();
        if ( $outcome->isValidNonce() ) {
          $outcome->processForm();
        }
        break;
      default:
        break;
    }
  }
}

function admin_menu() {
  if ( current_user_can( 'manage_outcomes' ) ) {
    add_menu_page(
      __('Learning Outcomes Overview', 'candela_outcomes'),
      __('Learning Outcomes', 'candela_outcomes'),
      'manage_outcomes',
      'outcomes-overview',
      __NAMESPACE__ . '\admin_outcomes_overview'
    );

    add_submenu_page(
      'outcomes-overview',
      __('Add/Edit Collection', 'candela_outcomes'),
      __('Add/Edit Collection', 'candela_outcomes'),
      'manage_outcomes',
      'edit_collection',
      __NAMESPACE__ . '\edit_collection'
    );

    add_submenu_page(
      'outcomes-overview',
      __('Add/Edit Outcome', 'candela_outcomes'),
      __('Add/Edit Outcome', 'candela_outcomes'),
      'manage_outcomes',
      'edit_outcome',
      __NAMESPACE__ . '\edit_outcome'
    );
  }
}

/**
 * Top-level admin page callback for outcomes.
 */
function admin_outcomes_overview() {
  global $wpdb;

  if (!class_exists('Collections_Table')) {
      require_once(__DIR__ . '/table-collections.php');
  }
  // TODO: List global outcomes and select which ones are available.

  // TODO: Show list of all collections and link to edit collection links.
  print '<h2>' . __('Collections Overview' ) . '</h2>';
  $table = new Collections_Table();
  $table->prepare_items();
  $table->display();
}

/**
 * Admin page callback for collections overview.
 */
function admin_collections() {
  print 'collections';
}

/**
 * Admin page callback to add a new or edit an existing collection.
 */
function edit_collection() {
  // Load collection via form submission, then requested id
  $collection = new Collection();
  if ( $collection->isValidNonce() ) {
      $collection->processForm();
      $collection->processFormErrors();
  }
  else if ( ! empty( $_GET['uuid'] ) ) {
    if ( ! Base::isValidUUID( $_GET['uuid'] ) ) {
      error_admin_register( 'uuid', $key, $val );
    }
    else {
      $collection = new Collection();
      $collection->load( $_GET['uuid'] );
      if ( empty($collection->uuid ) ) {
        print '<div class="error">' . __('Invalid UUID or UUID not found.') . '</div>';
        return;
      }
    }
  }
  else {
    $collection = new Collection;
  }

  $collection->form();

}

/**
 * Admin page callback to add a new or edit and existing outcome.
 */
function edit_outcome() {
  // Load outcome via form submission, then requested id
  $outcome = new Outcome();
  if ( $outcome->isValidNonce() ) {
    $outcome->processForm();
    $outcome->processFormErrors();
  }
  else if ( ! empty( $_GET['uuid'] ) ) {
    if ( ! Base::isValidUUID( $_GET['uuid'] ) ) {
      error_admin_register( 'uuid', $key, $val );
    }
    else {
      $outcome = new Outcome();
      $outcome->load( $_GET['uuid'] );
      if ( empty($outcome->uuid ) ) {
        print '<div class="error">' . __('Invalid UUID or UUID not found.') . '</div>';
        return;
      }
    }
  }
  else {
    $outcome = new Outcome;
  }

  $outcome->form();

}

/**
 * Implementation of action 'admin_notices'.
 *
 * Loads the outcomes_admin_errors transient for this user and wraps all
 * all messages in error divs.
 */
function show_admin_notices() {
  $transient_name = 'outcomes_admin_errors_' . get_current_user_id();
  if ( $errors = get_transient( $transient_name ) ) {
    delete_transient( $transient_name );
    foreach ($errors as $widget => $sub_errors ) {
      print '<div class="error ' . esc_attr($widget) . '">';
      foreach ( $sub_errors as $type => $messages ) {
        foreach ( $messages as $message ) {
          print '<p>' . $message . '</p>';
        }
      }
      print '</div>';
    }
  }
}

/**
 * Set a transient to be used by show_admin_notices.
 *
 * Transient is composed of user id, context, widget, and error type.
 *
 * If you are calling this function ensure that the $widgets array in
 * show_admin_notices has corresponding entries to check for this error.
 *
 */
function error_admin_register( $widget, $error_type, $message ) {
  $transient_name = 'outcomes_admin_errors_' . get_current_user_id();
  $errors = get_transient( $transient_name );
  if ( empty( $errors ) ) {
    $errors = array();
  }

  $errors[$widget][$error_type][] = $message;

  set_transient( $transient_name, $errors );
}

/**
 * Returns NULL or a valid UUID as returned from MySQL's UUID() function.
 */
function get_uuid( ) {
  global $wpdb;
  $uuid = $wpdb->get_var('SELECT UUID();');
  return $uuid;
}

/**
 * dbDelta handling function to manage database structure for collections.
 */
function db_collection_table() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'outcomes_collection';
  $sql = "CREATE TABLE $table_name (
      uuid CHAR(36),
      user_id mediumint(9) NOT NULL,
      title TEXT,
      description LONGTEXT,
      status VARCHAR(32),
    PRIMARY KEY  (uuid)
  );";

  require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta( $sql );
}

/**
 * dbDelta handling function to manage database structure for outcomes.
 */
function db_outcome_table() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'outcomes_outcome';
  $sql = "CREATE TABLE $table_name (
      uuid CHAR(36),
      user_id mediumint(9) NOT NULL,
      title TEXT,
      description LONGTEXT,
      status VARCHAR(32),
      successor CHAR(36),
      belongs_to CHAR(36),
    PRIMARY KEY (`uuid`),
    INDEX successor (successor),
    INDEX belongs_to (belongs_to)
  );";

  require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta( $sql );
}

/**
 * Cleanup any tables that were created. Used on blog delete.
 */
function remove_db_tables() {
  global $wpdb;

  $table_name = $wpdb->base_prefix . 'outcomes_collection';
  $wpdb->query("DROP TABLE IF EXISTS $table_name");

  $table_name = $wpdb->base_prefix . 'outcomes_outcome';
  $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

