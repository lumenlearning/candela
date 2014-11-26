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
  add_filter( 'wpmu_drop_tables', 'delete_blog' );

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

    if ( ! empty( $params[1] ) ) {
      $errors = validate_uuid( $params[1] );
      if ( empty( $errors ) ) {
        $wp->query_vars['outcomes_uuid'] = $params[1];
      }
      else {
        $wp->query_vars['outcomes_action'] = $params[1];
      }
    }

    if ( empty( $action ) && ! empty( $params[2] ) ) {
      if ( $params[2] == 'edit' ) {
        $wp->query_vars['outcomes_action'] = 'edit';
      }
    }
  }
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
      case 'collection':
        $item = load_item_by_uuid( $uuid, $type );
        if ( ! empty( $item ) ) {
          return get_template( $type );
        }
        else {
          return get_404_template();
        }
        break;

      default:
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

function admin_menu() {
  add_menu_page(
    __('Learning Outcomes Overview', 'candela_outcomes'),
    __('Learning Outcomes', 'candela_outcomes'),
    'manage_outcomes',
    'outcomes-overview',
    __NAMESPACE__ . '\admin_outcomes_overview'
  );

  add_submenu_page(
    'outcomes-overview',
    __('Collections', 'candela_outcomes'),
    __('Collections', 'candela_outcomes'),
    'manage_outcomes',
    'collections',
    __NAMESPACE__ . '\admin_collections'
  );

  add_submenu_page(
    'outcomes-overview',
    __('Outcomes', 'candela_outcomes'),
    __('Outcomes', 'candela_outcomes'),
    'manage_outcomes',
    'outcomes',
    __NAMESPACE__ . '\admin_outcomes'
  );
}

function admin_outcomes_overview() {
  print 'outcomes overview';
}

function admin_collections() {
  print 'collections';
}

function admin_outcomes() {
  print 'outcomes';
}



/**
 * Output an editing form for collections or outcomes.
 *
 * @param $item either a collection or outcome item.
 * @param $function specific callback for custom form items.
 *   eg. 'collection_form', 'outcome_form'
 */
function general_form ( $item, $function ) {
  print '<form method="POST">';
  print '<input type="hidden" id="uuid" name="uuid" value="' . esc_attr( $item->uuid ) . '" >';
  // URI is auto filled.
  text( 'title', 'title', __('Title', 'candela_outcomes'), $item->title );
  textarea( 'description', 'description', __('Description', 'candela_outcomes'), $item['description'] );

  // Call custom form.
  $function( $item );

  print '</form>';
}

/**
 * Custom form elements used on collection forms.
 */
function collection_form( $collection ) {
  $options = get_status_options( 'collection' );
  select('outcomes-collection-status', '_outcomes_collection_status', _e('Status'), $options, $collection->status);
}

/**
 * Custom form elements used on outcomes forms.
 */
function outcome_form( $outcome ) {
  $options = get_status_options( 'outcome' );
  select('outcomes-outcome-status', '_outcomes_outcome_status', _e('Status'), $options, $outcome->status );

  $options = get_valid_successors( $post );
  select('outcomes-outcome-successor', '_outcomes_outcome_successor', _e('Succeeded by'), $options, $outcome->successor );

  $options = get_valid_collections( $post );
  select('outcomes-outcome-belongs-to', '_outcomes_outcome_belongs_to', _e('Belongs to'), $options, $outcome->belongs_to );
}

/**
 * Helper function to return valid status and details.
 */
function get_status_options( $type ) {
  switch ( $type ) {
    case 'collection':
      return array(
        'private' => __('Private', 'candela_outcomes'),
        'public' => __('Public', 'candela_outcomes'),
      );
      break;
    case 'outcome':
      return array(
        'draft' => __('Draft', 'candela_outcomes'),
        'active' => __('Active', 'candela_outcomes'),
        'deprecated' => __('Deprecated', 'candela_outcomes'),
      );
      break;
  }
  return array();
}

/**
 * Outputs a select widget, and label.
 */
function select( $id, $name, $label, $options, $selected ) {
  print '<label for="' . $id . '">';
  print $label;
  print '</label>';
  print '<select id="' . $id . '" name="' . $name . '">';
  options( $options, $selected );
  print '</select>';
}

/**
 * Outputs a text widget
 */
function text( $id, $name, $label, $value ) {
  print '<label for="' . $id . '">';
  print $label;
  print '</label>';
  print '<input type="text" id="' . $id . '" name="' . esc_attr($name) . '" value="' . esc_attr( $value ) . '">';
}

/**
 * Outputs a text area.
 */
function textarea( $id, $name, $label, $value ) {
  print '<label for="' . $id . '">';
  print $label;
  print '</label>';
  print '<textarea class="widefat" rows="8" cols="10" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">' . esc_textarea( $value ) . '</textarea>';
}

/**
 * Outputs a set of select options, marking the appropriate value as selected.
 */
function options($options, $selected) {
  foreach ( $options as $value => $label ) {
    if ( $selected == $value ) {
      $s = 'selected';
    }
    else {
      $s = '';
    }
    print '<option value="' . esc_attr($value) . "\" $s>" . esc_html($label) . '</option>';
  }
}

/**
 * Gets a list of valid successors for an outcome.
 */
function get_valid_successors( $post ) {
  $options = array(
    '0' => __('None', 'candela_outcomes'),
  );

  // TODO: query for all potential successors.

  return $options;
}

/**
 * Gets a list of valid collections an outcome could belong to.
 */
function get_valid_collections( $post ) {
  $options = array();

  // TODO: query for all potential collections.

  return $options;
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
 * Validates a UUID
 */
function validate_uuid( $uuid, $empty_ok = FALSE ) {
  $errors = array();
  if ( ! empty( $uuid ) ) {
    // uuid character
    $uc = '[a-f0-9A-F]';
    $regex = "/$uc{8}-$uc{4}-$uc{4}-$uc{4}-$uc{12}/";
    if ( ! preg_match( $regex, $uuid ) ) {
      $errors['invalid'] = __('Invalid UUID.', 'candela_outcomes');
    }
  }
  else if ( ! $empty_ok ) {
    $errors['empty'] = __('Empty UUID.', 'candela_outcomes');
  }

  return $errors;
}

/**
 * Validates a URI
 */
function validate_uri( $uri ) {
  // TODO: validate URI.
  $errors = array();

  return $errors;
}

function validate_generic( $generic ) {
  $errors = array();
  if ( empty( $generic ) ) {
    $errors['empty'] = __('Empty value.', 'candela_outcomes');
  }
  return $errors;
}

function validate_title( $title ) {
  return validate_generic( $title );
}

function validate_description( $description ) {
  return validate_generic( $description );
}

function validate_status( $status, $type ) {
  $errors = array();

  $valid = get_status_options( $type );

  if ( empty( $status ) ) {
    $errors['empty'] = __('Empty status.', 'candela_outcomes');
  }
  else {
    if ( ! in_array( $status, array_keys( $valid ) ) ) {
      $errors['invalid'] = __('Invalid status.', 'candela_outcomes');
    }
  }

  return $errors;
}

function validate_base( $item ) {
  $errors = array();

  $errors['uuid'] = validate_uuid( $item['uuid'] );
  $errors['uri'] = validate_uri( $item['uri'] );
  $errors['title'] = validate_title( $item['title'] );
  $errors['description'] = validate_description( $item['description'] );

  return $errors;
}

function validate_collection( $collection ) {
  $errors = validate_base( $collection );
  $errors['status'] = validate_status( $collection['status'], 'colleciton' );

  return $errors;
}

function validate_outcome( $outcome ) {
  $errors = validate_base( $outcome );

  $errors['status'] = validate_status( $outcome['status'], 'outcome' );
  $errors['successor'] = validate_uuid( $outcome['uuid'], TRUE );
  $errors['belongs_to'] = validate_uuid( $outcome['belongs_to'] );
  return $errors;
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
