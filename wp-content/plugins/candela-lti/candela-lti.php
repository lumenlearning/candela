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
    // Table name is always root (site)
    define('CANDELA_LTI_TABLE', 'wp_candelalti');
    define('CANDELA_LTI_DB_VERSION', '1.0');
    define('CANDELA_LTI_CAP_LINK_LTI', 'candela link lti launch');
    define('CANDELA_LTI_USERMETA_LASTLINK', 'candelalti_lastkey');
    define('CANDELA_LTI_USERMETA_LTI_INFO', 'candelalti_lti_info');
    define('CANDELA_LTI_USERMETA_EXTERNAL_KEY', 'candelalti_external_userid');
    define('CANDELA_LTI_PASSWORD_LENGTH', 32);

    register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
    register_uninstall_hook(__FILE__, array( __CLASS__, 'deactivate') );

    add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
    add_action( 'init', array( __CLASS__, 'setup_capabilities' ) );
    add_action( 'query_vars', array( __CLASS__, 'query_vars' ) );
    add_action( 'parse_request', array( __CLASS__, 'parse_request' ) );

    // Respond to LTI launches
    add_action( 'lti_setup', array( __CLASS__, 'lti_setup' ) );
    add_action( 'lti_launch', array( __CLASS__, 'lti_launch') );

    // Add a content filter with low priority to inject our mapping link
    add_filter('the_content', array( __CLASS__, 'content_map_lti_launch'), 20);

    add_action('admin_menu', array( __CLASS__, 'admin_menu'));
    
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

  public static function admin_menu() {
    add_menu_page(
      __('LTI maps', 'candela_lti'),
      __('LTI maps', 'candela_lti'),
      CANDELA_LTI_CAP_LINK_LTI,
      'lti-maps',
      array(__CLASS__, 'lti_maps_page_handler')
    );
  }

  public static function lti_maps_page_handler() {
    global $wpdb;

    include_once(__DIR__ . '/candela-lti-table.php');
    $table = new Candela_LTI_Table;
    $table->prepare_items();

    $message = '';

    if ( 'delete' === $table->current_action() ) {
      $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Maps deleted: %d', 'candela_lti'), count($_REQUEST['ID'])) . '</p></div>';
    }

    print '<div class="wrap">';
    print $message;
    print '<form id="candela-lti-maps" method="GET">';
    print '<input type="hidden" name="page" value="' . $_REQUEST['page'] . '" />';
    $table->display();
    print '</form>';
    print '</div>';
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

    if ( ! empty($wp->query_vars['resource_link_id'] ) ) {
      $map = CandelaLTI::get_lti_map($wp->query_vars['resource_link_id']);
      if ( ! empty( $map->target_action ) ) {
        wp_redirect( $map->target_action );
        exit;
      }
    }
    // Currently just redirect to the blog/site homepage.
    if ( ! ( empty( $wp->query_vars['blog'] ) ) ){
      if ( !is_numeric( $wp->query_vars['blog'] ) ) {
         $details = get_blog_details($wp->query_vars['blog']);
         if ( $details && $details->blog_id ) {
           switch_to_blog((int)$details->blog_id);
         } else {
           wp_redirect( get_site_url( 1 ) );
           exit;
         }
      } else {
         switch_to_blog((int)$wp->query_vars['blog']);
      }
      if ( ! ( empty ( $wp->query_vars['bookpage'] ) ) ) {
      	 $post = $wp->query_vars['bookpage'];
      } else {
      	 $post = 'table-of-contents';
      }
      wp_redirect( get_bloginfo('wpurl') . '/' . $post );
      exit;
    }

    // redirect to primary site
    wp_redirect( get_site_url( 1 ) );
    exit;
  }

  /**
   * Do any setup necessary to manage LTI launches.
   */
  public static function lti_setup() {
    // Manage authentication and account creation.
    CandelaLTI::lti_accounts();

    // If this is a valid user store the resource_link_id so we have it later.
    if ( CandelaLTI::user_can_map_lti_links() ) {
      $current_user = wp_get_current_user();
      update_user_meta( $current_user->ID, CANDELA_LTI_USERMETA_LASTLINK, $_POST['resource_link_id'] );
      update_user_meta( $current_user->ID, CANDELA_LTI_USERMETA_LTI_INFO, serialize($_POST) );
    }
  }

  /**
   * Take care of authenticating the incoming user and creating an account if
   * required.
   */
  public static function lti_accounts() {
    // Used to track if we call wp_logout() since is_user_logged_in() will still
    // report true after our call to that.
    // @see http://wordpress.stackexchange.com/questions/13087/wp-logout-not-logging-me-out
    $logged_out = FALSE;

    // if we do not have an external user_id skip account stuff.
    if ( empty($_POST['user_id']) ) {
      return;
    }

    // Find user account (if any) with matching ID
    $user = CandelaLTI::find_user_by_external_id( $_POST['user_id'] );

    if ( is_user_logged_in() ) {
      // if the external ID does not match this users external id, log them out.
      $current_user = wp_get_current_user();
      $external_id = CandelaLTI::get_external_id_by_userid( $current_user->ID );
      if ( ! empty( $external_id ) ) {
        if ( $external_id != $_POST['user_id'] ) {
          $logged_out = TRUE;
          wp_logout();
        }
        else {
          $user = $current_user;
        }
      }
      else {
        // Associate external id to current_user.
        CandelaLTI::set_external_id_for_userid( $current_user->ID, $_POST['user_id'] );
        $user = $current_user;
      }
    }

    if ( empty($user) ) {
      // Create a user account if we do not have a matching account
      $user = CandelaLTI::create_user_account( $_POST['user_id'] );
    }

    // If the user is not currently logged in... authenticate as the matched account.
    if ( ! is_user_logged_in() || $logged_out ) {
      CandelaLTI::login_user_no_password( $user->ID );
    }

    // Associate the external id with this account.
    if ( ! empty($_POST['user_id'] ) ) {
      CandelaLTI::set_external_id_for_userid( $user->ID, $_POST['user_id'] );
    }
  }

  public static function get_external_id_by_userid( $user_id ) {
    switch_to_blog(1);
    $external_id = get_user_meta( $user_id, CANDELA_LTI_USERMETA_EXTERNAL_KEY, TRUE );
    restore_current_blog();
    return $external_id;
  }

  public static function set_external_id_for_userid( $user_id, $external_id ) {
    switch_to_blog(1);
    update_user_meta( $user_id, CANDELA_LTI_USERMETA_EXTERNAL_KEY, $external_id );
    restore_current_blog();
  }

  /**
   * Create a user account corresponding to the current incoming LTI request.
   *
   * @param string $username
   *   The username of the new account to create. If this username already exists
   *   we return the corresponding user account.
   *
   * @todo consider using 'lis_person_contact_email_primary' if passed as email.
   * @return the newly created user account.
   */
  public static function create_user_account( $username ) {
    $existing_user = get_user_by('login', $username);
    if ( ! empty($existing_user) ) {
      return $existing_user;
    }
    else {
      $email = $username . '@127.0.0.1';
      $password = wp_generate_password( CANDELA_LTI_PASSWORD_LENGTH, true);

      $user_id = wp_create_user( $username, $password, $email );

      $user = new WP_User( $user_id );
      $user->set_role( 'subscriber' );
      update_user_meta( $user->ID, CANDELA_LTI_USERMETA_EXTERNAL_KEY, $_POST['user_id'] );

      return $user;
    }
  }

  public static function find_user_by_external_id( $id ) {
    switch_to_blog(1);
    $params = array(
      'meta_key' => CANDELA_LTI_USERMETA_EXTERNAL_KEY,
      'meta_value' => $id,
      'number' => 1,
      'count_total' => false,
    );
    $users = get_users( $params );
    $user = reset( $users );
    restore_current_blog();

    return $user;
  }

  /**
   * login the current user (if not logged in) as the user matching $user_id
   *
   * @see http://wordpress.stackexchange.com/questions/53503/can-i-programmatically-login-a-user-without-a-password
   */
  public static function login_user_no_password( $user_id ) {
    if ( ! is_user_logged_in() ) {
      wp_clear_auth_cookie();
      wp_set_current_user( $user_id );
      wp_set_auth_cookie( $user_id );
    }
  }

  /**
   * Add our LTI api endpoint vars so that wordpress "understands" them.
   */
  public static function query_vars( $query_vars ) {
    $query_vars[] = '__candelalti';
    $query_vars[] = 'resource_link_id';
    $query_vars[] = 'target_action';
    $query_vars[] = 'action';
    $query_vars[] = 'ID';
    $query_vars[] = 'candela-lti-nonce';
    $query_vars[] = 'bookpage';
    return $query_vars;
  }

  /**
   * Add our LTI resource_link_id mapping api endpoint
   */
  public static function add_rewrite_rule() {
    add_rewrite_rule( '^api/candelalti?(.*)', 'index.php?__candelalti=1&$matches[1]', 'top');
    
    //Extend the LTI api endpoint to support name-based blogs
    // and to allow link to specific posts in the launch
    add_rewrite_rule( '^api/lti/([0-9]+)/([a-z\-0-9]+)/?', 'index.php?__lti=1&blog=$matches[1]&bookpage=$matches[2]', 'top');
    add_rewrite_rule( '^api/lti/([a-z][a-z0-9]*)/([a-z\-0-9]+)/?', 'index.php?__lti=1&blog=$matches[1]&bookpage=$matches[2]', 'top');
    add_rewrite_rule( '^api/lti/([a-z][a-z0-9]*)/?\s*$', 'index.php?__lti=1&blog=$matches[1]', 'top');
  }

  /**
   * Setup our new capabilities.
   */
  public static function setup_capabilities() {
    global $wp_roles;

    $wp_roles->add_cap('administrator', CANDELA_LTI_CAP_LINK_LTI);
    $wp_roles->add_cap('editor', CANDELA_LTI_CAP_LINK_LTI);
  }

  /**
   * Implementation of action 'parse_request'.
   *
   * @see http://codex.wordpress.org/Plugin_API/Action_Reference/parse_request
   */
  public static function parse_request() {
    global $wp, $wpdb;
    if ( CandelaLTI::user_can_map_lti_links() && isset( $wp->query_vars['__candelalti'] ) && !empty($wp->query_vars['__candelalti'] ) ) {
      // Process adding link associations
      if ( wp_verify_nonce($wp->query_vars['candela-lti-nonce'], 'mapping-lti-link') &&
           ! empty( $wp->query_vars['resource_link_id']) &&
           ! empty( $wp->query_vars['target_action'] ) ) {
        // Update db record everything is valid
        $map = CandelaLTI::get_lti_map($wp->query_vars['resource_link_id'] );

        $current_user = wp_get_current_user();
        $values = array(
          'resource_link_id' => $wp->query_vars['resource_link_id'],
          'target_action' => $wp->query_vars['target_action'],
          'user_id' => $current_user->ID,
          'lti_info' => serialize( CandelaLTI::get_lti_info_by_user( $current_user->ID ) ),
        );
        $value_format = array(
          '%s',
          '%s',
          '%d',
          '%s',
        );

        if ( ! empty( $map->target_action ) ) {
          // update the existing map.
          $where = array( 'resource_link_id' => $wp->query_vars['resource_link_id'] );
          $where_format = array( '%s' );
          $result = $wpdb->update(CANDELA_LTI_TABLE, $values, $where, $value_format, $where_format );
        }
        else {
          // map was empty... insert the new map.
          $result = $wpdb->insert(CANDELA_LTI_TABLE, $values, $value_format );
        }

        if ( $result === FALSE ) {
          // die with error error
          wp_die('Failed to map resource_link_id(' . $wp->query_vars['resource_link_id'] . ') to url(' . $wp->query_vars['target_action']) . ')';
        }
      }

      // Process action items.
      if ( wp_verify_nonce($wp->query_vars['candela-lti-nonce'], 'unmapping-lti-link') && ! empty( $wp->query_vars['action'] ) ) {
        switch ( $wp->query_vars['action'] ) {
          case 'delete':
            if ( !empty($wp->query_vars['ID']) && is_numeric($wp->query_vars['ID'])) {
              $wpdb->delete( CANDELA_LTI_TABLE, array( 'ID' => $wp->query_vars['ID'] ) );
            }
            break;
        }
      }

      // If we have a target_action, redirect to it, otherwise redirect back to home.
      if ( ! empty( $wp->query_vars['target_action'] ) ) {
        wp_redirect( $wp->query_vars['target_action'] );
      }
      else if ( ! empty($_SERVER['HTTP_REFERER'] ) ) {
        wp_redirect( $_SERVER['HTTP_REFERER'] );
      }
      else {
        wp_redirect( home_url() );
      }
      exit();
    }

  }

  /**
   * Get the last LTI launch info for a given user.
   */
  public static function get_lti_info_by_user( $user_id ) {
    switch_to_blog(1);
    $lti_info = get_user_meta( $user_id, CANDELA_LTI_USERMETA_LTI_INFO, TRUE);
    if ( ! empty( $lti_info ) ) {
      $lti_info = unserialize( $lti_info );
    }
    else {
      $lti_info = array();
    }
    restore_current_blog();
    return $lti_info;
  }

  /**
   * Given a resource_link_id return the mapping row for that resource_link_id.
   *
   * @param string resource_link_id
   *   The resource_link_id to get the row for. If empty the last LTI launch link
   *   for the user if user is logged in will be used.
   *
   * @return object
   *  Either the matching row or an object with just the resource_link_id set.
   */
  public static function get_lti_map( $resource_link_id = '' ) {
    global $wpdb;

    if ( empty( $resource_link_id ) && is_user_logged_in() ) {
      $current_user = wp_get_current_user();
      // Make sure query is ran against primary site since usermeta was set via
      // lti_setup action.
      switch_to_blog(1);
      $resource_link_id = get_user_meta( $current_user->ID, CANDELA_LTI_USERMETA_LASTLINK, TRUE );
      restore_current_blog();
    }

    $table_name = CANDELA_LTI_TABLE;
    $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE resource_link_id  = %s", $resource_link_id);

    $map = $wpdb->get_row( $sql );

    if ( empty( $map ) ) {
      $map = new stdClass;
      $map->resource_link_id = $resource_link_id;
    }

    return $map;

  }

  public static function get_maps_by_target_action( $target_action = '' ) {
    global $wpdb;

    if ( empty( $target_action ) && is_single() ) {
      $target_action = get_permalink();
    }

    $table_name = CANDELA_LTI_TABLE;
    $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE target_action = %s", $target_action);
    return $wpdb->get_results($sql);
  }

  /**
   * If we have an authenticated user and unmapped LTI launch add a link to
   * associate current page with the LTI launch.
   */
  public static function content_map_lti_launch( $content ) {
    if ( is_single() && CandelaLTI::user_can_map_lti_links() ) {
      $map = CandelaLTI::get_lti_map();
      $target_action = get_permalink();
      $resource_link_id = '';
      $links = array();
      if ( empty( $map ) || ( empty( $map->target_action ) && ! empty( $map->resource_link_id ) ) ) {
        $resource_link_id = $map->resource_link_id;
        // Map is either not set at all or needs to be set, inject content to do so.
        $text = __('Add LTI link');
        $hover = __('resource_link_id(##RES##)');
        $url = get_site_url(1) . '/api/candelalti';
        $url = wp_nonce_url($url, 'mapping-lti-link', 'candela-lti-nonce');
        $url .= '&resource_link_id=' . urlencode($map->resource_link_id) . '&target_action=' . urlencode( $target_action );
        $links['add'] = '<div class="lti addmap"><a class="btn blue" href="' . $url . '" title="' . esc_attr( str_replace('##RES##', $map->resource_link_id, $hover) ) . '">' . $text . '</a></div>';
      }

      $maps = CandelaLTI::get_maps_by_target_action();
      if ( ! empty( $maps ) ) {
        $base_url = get_site_url(1) . '/api/candelalti';
        $base_url = wp_nonce_url($base_url, 'unmapping-lti-link', 'candela-lti-nonce');
        $text = __('Remove LTI link');
        $hover = __('resource_link_id(##RES##)');
        foreach ( $maps as $map ) {
          if ($map->resource_link_id == $resource_link_id ) {
            // don't include add and delete link
            unset($links['add']);
          }
          $url = $base_url . '&action=delete&ID=' . $map->ID;
          $links[] = '<a class="btn red" href="' . $url . '"title="' . esc_attr( str_replace('##RES##', $map->resource_link_id, $hover) ) . '">' . $text . '</a>';
        }
      }

      if ( ! empty( $links ) ) {
        $content .= '<div class="lti-mapping"><ul class="lti-mapping"><li>' . implode('</li><li>', $links) . '</li></ul></div>';
      }
    }
    return $content;
  }

  /**
   * See if the current user (if any) can map LTI launch links to destinations.
   *
   * @todo add proper checks, currently this just checks if the user is logged in.
   */
  public static function user_can_map_lti_links() {
    if ( is_user_logged_in() ) {
      $current_user = wp_get_current_user();
      if ( $current_user->has_cap(CANDELA_LTI_CAP_LINK_LTI) ) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Create a database table for storing LTI maps, this is a global table.
   */
  public static function create_db_table() {
    $table_name = CANDELA_LTI_TABLE;

    $sql = "CREATE TABLE $table_name (
      ID mediumint(9) NOT NULL AUTO_INCREMENT,
      resource_link_id TINYTEXT,
      target_action TINYTEXT,
      user_id mediumint(9),
      lti_info TEXT,
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
    global $wpdb;
    $table_name = CANDELA_LTI_TABLE;
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    delete_option('candela_lti_db_version');
  }

}

