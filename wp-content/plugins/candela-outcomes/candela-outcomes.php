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

    if ( ! empty( $params[1] ) ) {
      $errors = Base::isValidUUID( $params[1] );
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
      __('Collections Overview', 'candela_outcomes'),
      __('Collections Overview', 'candela_outcomes'),
      'manage_outcomes',
      'collections',
      __NAMESPACE__ . '\admin_collections'
    );

    add_submenu_page(
      'outcomes-overview',
      __('Add Collection', 'candela_outcomes'),
      __('Add Collection', 'candela_outcomes'),
      'manage_outcomes',
      'add_collection',
      __NAMESPACE__ . '\edit_collection'
    );


    add_submenu_page(
      'outcomes-overview',
      __('Outcomes', 'candela_outcomes'),
      __('Outcomes', 'candela_outcomes'),
      'manage_outcomes',
      'outcomes',
      __NAMESPACE__ . '\admin_outcomes'
    );

    add_submenu_page(
      'outcomes-overview',
      __('Add Outcome', 'candela_outcomes'),
      __('Add Outcome', 'candela_outcomes'),
      'manage_outcomes',
      'add_outcome',
      __NAMESPACE__ . '\edit_outcome'
    );
  }
}

/**
 * Top-level admin page callback for outcomes.
 */
function admin_outcomes_overview() {
  print 'outcomes overview';
}

/**
 * Admin page callback for collections overview.
 */
function admin_collections() {
  print 'collections';
}

/**
 * Admin page callback for outcomes overview.
 */
function admin_outcomes() {
  print 'outcomes';
}

/**
 * Admin page callback to add a new or edit an existing collection.
 */
function edit_collection() {
  global $wp;
  print 'add/edit collection';

  $uuid = '';
  if ( ! empty( $_GET['uuid'] ) ) {
    $uuid = $_GET['uuid'];
    $errors = Base::isValidUUID( $uuid );

    if ( ! empty( $errors ) ) {
      $uuid = '';

      foreach ( $errors as $key => $val ) {
        error_admin_register( 'uuid', $key, $val );
      }
    }
  }

  if ( ! empty( $uuid ) ) {
    // Try to load the corresponding collection.
    $collection = load_item_by_uuid( $uuid, 'collection' );

    if ( empty($collection ) ) {
      // 404 template?
      print 'TODO: Collection could not be loaded';
      $collection = new Collection;
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
  print 'add/edit outcome';

  $uuid = '';
  if ( ! empty( $_GET['uuid'] ) ) {
    $uuid = $_GET['uuid'];
    $errors = Base::isValidUUID( $uuid );

    if ( ! empty( $errors ) ) {
      $uuid = '';

      foreach ( $errors as $key => $val ) {
        error_admin_register( 'uuid', $key, $val );
      }
    }
  }

  if ( ! empty( $uuid ) ) {
    // Try to load the corresponding outcome.
    $outcome = load_item_by_uuid( $uuid, 'outcome' );
    if ( empty($outcome ) ) {
      // 404 template?
      print 'TODO: Outcome could not be loaded';
      $outcome = new Outcome;
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
    '' => __('None', 'candela_outcomes'),
  );

  // TODO: query for all potential successors.

  return $options;
}

/**
 * Gets a list of valid collections an outcome could belong to.
 */
function get_valid_collections( $post ) {
  $options = array(
  );

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

abstract class Base {
  public static $type = '';
  public $errors = array();
  public $uuid = '';
  public $user_id = 0;
  public $title = '';
  public $description = '';
  public $status = '';

  abstract public function getStatusOptions();
  abstract public function load( $uuid );

  public function hasErrors() {
    return empty( $errors );
  }

  public function formHeader() {
    print '<form method="POST">';
    print '<input type="hidden" id="uuid" name="uuid" value="' . esc_attr( $this->uuid ) . '" >';
    // URI is auto filled.
    text( 'title', 'title', __('Title', 'candela_outcomes'), $this->title );
    textarea( 'description', 'description', __('Description', 'candela_outcomes'), $this->description );
  }

  public function formFooter() {
    print '</form>';
  }

  public function validate() {
    $this->validateUuid();
    $this->validateURI();
    $this->validateUserID();
    $this->validateTitle();
    $this->validateDescription();
    $this->validateStatus();
  }

  public static function isValidUUID( $uuid ) {
    // uuid character
    $uc = '[a-f0-9A-F]';
    $regex = "/$uc{8}-$uc{4}-$uc{4}-$uc{4}-$uc{12}/";
    if ( preg_match( $regex, $uuid ) ) {
      return TRUE;
    }

    return FALSE;
  }

  public function validateUuid() {
    if ( ! $this->isValidUUID( $this->uuid ) ) {
      $this->errors['uuid']['invalid'] = __('Invalid UUID.', 'candela_outcomes');
    }
  }

  public function validateURI( ) {
    // TODO: validate URI.
  }

  public function validateStatus() {
    $valid = $this->getStatusOptions();

    if ( empty( $this->status ) ) {
      $this->errors['status']['empty'] = __('Empty status.', 'candela_outcomes');
    }
    else {
      if ( ! in_array( $this->status, array_keys( $valid ) ) ) {
        $this->errors['status']['invalid'] = __('Invalid status.', 'candela_outcomes');
      }
    }
  }




  public function validateTitle( ) {
    $this->validateGeneric( 'title', $this->title );
  }

  public function validateDescription( ) {
    $this->validateGeneric( 'description', $this->description );
  }



  public function validateGeneric( $field, $value ) {
    if ( empty( $value ) ) {
      $this->errors[$field]['empty'] = __('Empty value.', 'candela_outcomes');
    }
  }
}

class Collection extends Base {
  public static $type = 'collection';
  public $status = 'private';

  public function load( $uuid ) {
    $item = load_item_by_uuid( $uuid, 'collection' );

    if ( ! empty( $item ) ) {
      foreach ($item as $prop => $value ) {
        $this->$prop = $value;
      }

      $this->uuid = $uuid;
    }
    else {
      $this->errors['loader']['notfound'] = __('Unable to find item with UUID.', 'candela_outcomes' );
    }
  }

  public function form() {
    $this->formHeader();

    $options = $this->getStatusOptions();
    select('outcomes-collection-status', '_outcomes_collection_status', _e('Status'), $options, $this->status);

    $this->formFooter();
  }

  public function getStatusOptions() {
    return array(
      'private' => __('Private', 'candela_outcomes'),
      'public' => __('Public', 'candela_outcomes'),
    );
  }
}

class Outcome extends Base {
  public static $type = 'outcome';
  public $status = 'draft';
  public $successor = '';
  public $belongs_to = '';

  public function load( $uuid ) {
    $item = load_item_by_uuid( $uuid, 'collection' );

    if ( ! empty( $item ) ) {
      foreach ($item as $prop => $value ) {
        $this->$prop = $value;
      }

      $this->uuid = $uuid;
    }
    else {
      $this->errors['loader']['notfound'] = __('Unable to find item with UUID.', 'candela_outcomes' );
    }
  }

  public function form() {
    $this->formHeader();
    $options = $this->getStatusOptions();
    select('outcomes-outcome-status', '_outcomes_outcome_status', _e('Status'), $options, $this->status );

    $options = get_valid_successors( $this );
    select('outcomes-outcome-successor', '_outcomes_outcome_successor', _e('Succeeded by'), $options, $this->successor );

    $options = get_valid_collections( $this );
    select('outcomes-outcome-belongs-to', '_outcomes_outcome_belongs_to', _e('Belongs to'), $options, $this->belongs_to );

    $this->formFooter();
  }

  public function getStatusOptions() {
    return array(
      'draft' => __('Draft', 'candela_outcomes'),
      'active' => __('Active', 'candela_outcomes'),
      'deprecated' => __('Deprecated', 'candela_outcomes'),
    );
  }

  public function validate() {
    parent::validate();

    $this->validateSuccessor();
    $this->validateBelongsTo();
  }

  public function validateSuccessor() {
    if ( ! $this->isValidUUID( $this->successor ) ) {
      $this->errors['successor']['invalid'] = __('Invalid UUID.', 'candela_outcomes');
    }
  }

  public function validateBelongsTo() {
    if ( ! $this->isValidUUID( $this->belongs_to ) ) {
      $this->errors['belongs_to']['invalid'] = __('Invalid UUID.', 'candela_outcomes');
    }
  }

}
