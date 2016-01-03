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
    define('CANDELA_LTI_DB_VERSION', '1.2');
    define('CANDELA_LTI_CAP_LINK_LTI', 'candela link lti launch');
    define('CANDELA_LTI_USERMETA_LASTLINK', 'candelalti_lastkey');
    define('CANDELA_LTI_USERMETA_EXTERNAL_KEY', 'candelalti_external_userid');
    define('CANDELA_LTI_PASSWORD_LENGTH', 32);

    register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
    register_uninstall_hook(__FILE__, array( __CLASS__, 'deactivate') );

    add_action( 'init', array( __CLASS__, 'update_db' ) );
    add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
    add_action( 'init', array( __CLASS__, 'setup_capabilities' ) );
    add_action( 'query_vars', array( __CLASS__, 'query_vars' ) );
    add_action( 'parse_request', array( __CLASS__, 'parse_request' ) );

    // Respond to LTI launches
    add_action( 'lti_setup', array( __CLASS__, 'lti_setup' ) );
    add_action( 'lti_launch', array( __CLASS__, 'lti_launch') );

    add_action('admin_menu', array( __CLASS__, 'admin_menu'));

    define('CANDELA_LTI_TEACHERS_ONLY', 'candela_lti_teachers_only');
    add_option( CANDELA_LTI_TEACHERS_ONLY, false );
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

    // allows deep links with an LTI launch urls like:
    // candela/api/lti/BLOGID?page_title=page_name
    // candela/api/lti/BLOGID?page_title=section_name%2Fpage_name
    if ( ! empty($wp->query_vars['page_title'] ) ) {
      switch_to_blog((int)$wp->query_vars['blog']);
      $page = $wp->query_vars['page_title'];
      if ( $page[0] ==  '/' ){
        $slash = '';
      } else {
        $slash = '/';
      }

      // todo make all the hide_* parameters copy over?
      // If it's a deep LTI link default to showing content_only
      wp_redirect( get_bloginfo('wpurl') . $slash . $page . "?content_only" );
      exit;
    }

    // allows deep links with an LTI launch urls like:
    // candela/api/lti/BLOGID?page_id=10
    if ( ! empty($wp->query_vars['page_id'] ) && is_numeric($wp->query_vars['page_id']) ) {
      switch_to_blog((int)$wp->query_vars['blog']);
      wp_redirect( get_bloginfo('wpurl') . "?p=" . $wp->query_vars['page_id'] . "&content_only&lti_context_id=" . $wp->query_vars['context_id'] );
      exit;
    }

    // allows deep links with an LTI custom parameter like:
    // custom_page_id=10
    if ( ! empty($wp->query_vars['custom_page_id'] ) && is_numeric($wp->query_vars['custom_page_id']) ) {
      switch_to_blog((int)$wp->query_vars['blog']);
      wp_redirect( get_bloginfo('wpurl') . "?p=" . $wp->query_vars['custom_page_id'] . "&content_only&lti_context_id=" . $wp->query_vars['context_id'] );
      exit;
    }

    if ( ! empty($wp->query_vars['resource_link_id'] ) ) {
      $map = CandelaLTI::get_lti_map($wp->query_vars['resource_link_id']);
      if ( ! empty( $map->target_action ) ) {
        wp_redirect( $map->target_action );
        exit;
      }
    }
    // Currently just redirect to the blog/site homepage.
    if ( ! ( empty( $wp->query_vars['blog'] ) ) ){
      switch_to_blog((int)$wp->query_vars['blog']);
      wp_redirect( get_bloginfo('wpurl') . '/?content_only' );
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
    }
  }

  /**
   * Take care of authenticating the incoming user and creating an account if
   * required.
   */
  public static function lti_accounts() {

    global $wp;

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

    CandelaLTI::update_user_if_teacher( $user );

    // If the user is not currently logged in... authenticate as the matched account.
    if ( ! is_user_logged_in() || $logged_out ) {
      CandelaLTI::login_user_no_password( $user->ID );
    }

    // Associate the external id with this account.
    if ( ! empty($_POST['user_id'] ) ) {
      CandelaLTI::set_external_id_for_userid( $user->ID, $_POST['user_id'] );
    }

    // Associate the user with this blog as a subscriber if not already associated.
    $blog = (int)$wp->query_vars['blog'];
    if ( ! empty( $blog ) && ! is_user_member_of_blog( $user->ID, $blog ) ) {
      if( CandelaLTI::is_lti_user_allowed_to_subscribe($blog)){
        add_user_to_blog( $blog, $user->ID, 'subscriber');
      }
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
   * Checks if the settings of the book allow this user to subscribe
   * That means that either all LTI users are, or only teachers/admins
   *
   * If the blog's CANDELA_LTI_TEACHERS_ONLY option is 1 then only teachers
   * are allowed
   *
   * @param $blog
   */
  public static function is_lti_user_allowed_to_subscribe($blog){
    $role = CandelaLTI::highest_lti_context_role();
    if( $role == 'admin' || $role == 'teacher' ) {
      return true;
    } else {
      // Switch to the target blog to get the correct option value
      $curr = get_current_blog_id();
      switch_to_blog($blog);
      $teacher_only = get_option(CANDELA_LTI_TEACHERS_ONLY);
      switch_to_blog($curr);

      return $teacher_only != 1;
    }
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
      $password = wp_generate_password( CANDELA_LTI_PASSWORD_LENGTH, true);

      $user_id = wp_create_user( $username, $password, CandelaLTI::default_lti_email($username) );

      $user = new WP_User( $user_id );
      $user->set_role( 'subscriber' );
      update_user_meta( $user->ID, CANDELA_LTI_USERMETA_EXTERNAL_KEY, $_POST['user_id'] );

      return $user;
    }
  }

  public static function default_lti_email( $username ) {
    return $username . '@127.0.0.1';
  }

  /**
   * If a user is a teacher or admin, set their first/last names
   * If their name wasn't sent, set their name as their role
   *
   * @param $user
   *
   */
  public static function update_user_if_teacher( $user ) {
    if( (!empty($user->last_name) || !empty($user->first_name))
         && ($user->last_name != 'Admin' && $user->last_name != 'Instructor') ){
      return;
    }

    $role = CandelaLTI::highest_lti_context_role();

    if( $role == 'admin' || $role == 'teacher' ){
      $userdata = ['ID' => $user->ID];
      if( !empty($_POST['lis_person_name_family']) || !empty($_POST['lis_person_name_given']) ){
        $userdata['last_name'] = $_POST['lis_person_name_family'];
        $userdata['first_name'] = $_POST['lis_person_name_given'];
      } elseif( empty($user->last_name) && empty($user->first_name) ) {
        $userdata['last_name'] = $role == 'admin' ? 'Admin' : 'Instructor';
      }

      if( !empty($userdata['last_name']) || !empty($userdata['first_name']) ) {
        wp_update_user($userdata);
      }
    }
  }

  /**
   * Parses the LTI roles into an array
   *
   * @return array
   */
  public static function get_current_launch_roles(){
    $roles = [];
    if( isset($_POST['ext_roles']) ) {
      // Canvas' more correct roles values are here
      $roles = $_POST['ext_roles'];
    } elseif (isset($_POST['roles'])){
      $roles = $_POST['roles'];
    } else {
      return $roles;
    }

    $roles = explode(",", $roles);
    return array_filter(array_map('trim', $roles));
  }

  /**
   * Returns the user's highest role, which in this context is defined by this order:
   *
   * Admin
   * Teacher
   * Designer
   * TA
   * Student
   * Other
   *
   * @return string admin|teacher|designer|ta|learner|other
   */
  public static function highest_lti_context_role(){
    $roles = CandelaLTI::get_current_launch_roles();
    if (in_array('urn:lti:instrole:ims/lis/Administrator', $roles) || in_array('Administrator', $roles)):
      return "admin";
    elseif (in_array('urn:lti:role:ims/lis/Instructor', $roles) || in_array('Instructor', $roles)):
      return "teacher";
    elseif (in_array('urn:lti:role:ims/lis/ContentDeveloper', $roles) || in_array('ContentDeveloper', $roles)):
      return "designer";
    elseif (in_array('urn:lti:role:ims/lis/TeachingAssistant', $roles) || in_array('TeachingAssistant', $roles)):
      return "ta";
    elseif (in_array('urn:lti:role:ims/lis/Learner', $roles) || in_array('Learner', $roles)):
      return "learner";
    else:
      return "other";
    endif;
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
    $query_vars[] = 'page_title';
    $query_vars[] = 'page_id';
    $query_vars[] = 'action';
    $query_vars[] = 'ID';
    $query_vars[] = 'context_id';
    $query_vars[] = 'candela-lti-nonce';
    $query_vars[] = 'custom_page_id';

    return $query_vars;
  }


  /**
   * Update the database
   */
  public static function update_db() {
    switch_to_blog(1);
    $version = get_option( 'candela_lti_db_version', '');
    restore_current_blog();

    if (empty($version) || $version == '1.0') {
      $meta_type = 'user';
      $user_id = 0; // ignored since delete all = TRUE
      $meta_key = 'candelalti_lti_info';
      $meta_value = ''; // ignored
      $delete_all = TRUE;
      delete_metadata( $meta_type, $user_id, $meta_key, $meta_value, $delete_all );

      switch_to_blog(1);
      update_option( 'candela_lti_db_version', CANDELA_LTI_DB_VERSION );
      restore_current_blog();
    }
    if ( $version == '1.1' ) {
      // This also updates the table.
      CandelaLTI::create_db_table();
    }
  }

  /**
   * Add our LTI resource_link_id mapping api endpoint
   */
  public static function add_rewrite_rule() {
    add_rewrite_rule( '^api/candelalti?(.*)', 'index.php?__candelalti=1&$matches[1]', 'top');
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
          'blog_id' => $wp->query_vars['blog'],
        );
        $value_format = array(
          '%s',
          '%s',
          '%d',
          '%d',
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
            if ( !empty($wp->query_vars['ID'] && is_numeric($wp->query_vars['ID']))) {
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
    if ( is_single()
        && CandelaLTI::user_can_map_lti_links()
        && empty($wp->query_vars['page_title'])
        && ! isset($_GET['content_only']) ) {

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
        $url .= '&resource_link_id=' . urlencode($map->resource_link_id) . '&target_action=' . urlencode( $target_action ) . '&blog=' . get_current_blog_id();
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
          $url = $base_url . '&action=delete&ID=' . $map->ID . '&blog=' . get_current_blog_id();
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
    global $wp;
    $switched = FALSE;
    if ( ! ( empty( $wp->query_vars['blog'] ) ) ){
      switch_to_blog( (int) $wp->query_vars['blog'] );
      $switched = TRUE;
    }

    if ( is_user_logged_in() ) {
      $current_user = wp_get_current_user();
      if ( $current_user->has_cap(CANDELA_LTI_CAP_LINK_LTI) ) {
        if ( $switched ) {
          restore_current_blog();
        }
        return TRUE;
      }
    }
    if ( $switched ) {
      restore_current_blog();
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
      blog_id mediumint(9),
      PRIMARY KEY  (id),
      UNIQUE KEY resource_link_id (resource_link_id(32))
    );";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta( $sql );

    switch_to_blog(1);
    update_option( 'candela_lti_db_version', CANDELA_LTI_DB_VERSION );
    restore_current_blog();
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
