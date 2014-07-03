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
    define('CANDELA_LTI_USERMETA_LASTLINK', 'candelalti_lastkey');

    register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
    register_uninstall_hook(__FILE__, array( __CLASS__, 'deactivate') );

    add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
    add_action( 'query_vars', array( __CLASS__, 'query_vars' ) );
    add_action( 'parse_request', array( __CLASS__, 'parse_request' ) );

    // Respond to LTI launches
    add_action( 'lti_setup', array( __CLASS__, 'lti_setup' ) );
    add_action( 'lti_launch', array( __CLASS__, 'lti_launch') );

    // Add a content filter with low priority to inject our mapping link
    add_filter('the_content', array( __CLASS__, 'content_map_lti_launch'), 20);
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
      wp_redirect( home_url() );
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
    // If this is a valid user store the resource_link_id so we have it later.
    if ( CandelaLTI::user_can_map_lti_links() ) {
      $current_user = wp_get_current_user();
      update_user_meta( $current_user->ID, CANDELA_LTI_USERMETA_LASTLINK, $_POST['resource_link_id'] );
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
    return $query_vars;
  }

  /**
   * Add our LTI resource_link_id mapping api endpoint
   */
  public static function add_rewrite_rule() {
    add_rewrite_rule( '^api/candelalti?(.*)', 'index.php?__candelalti=1&$matches[1]', 'top');
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

        $values = array(
          'resource_link_id' => $wp->query_vars['resource_link_id'],
          'target_action' => $wp->query_vars['target_action'],
        );

        if ( ! empty( $map->target_action ) ) {
          // update the existing map.
          $where = array( 'resource_link_id' => $wp->query_vars['resource_link_id'] );
          $result = $wpdb->update(CANDELA_LTI_TABLE, $values, $where, '%s', '%s' );
        }
        else {
          // map was empty... insert the new map.
          $result = $wpdb->insert(CANDELA_LTI_TABLE, $values, '%s');
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
    if ( is_single() && CandelaLTI::user_can_map_lti_links() ) {
      $map = CandelaLTI::get_lti_map();
      $target_action = get_permalink();
      $resource_link_id = '';
      $add_link = FALSE;
      if ( empty( $map ) || ( empty( $map->target_action ) && ! empty( $map->resource_link_id ) ) ) {
        $resource_link_id = $map->resource_link_id;
        // Map is either not set at all or needs to be set, inject content to do so.
        $translated = __('Map LTI resource_link_id(##RES##) to here');
        $url = get_site_url(1) . '/api/candelalti';
        $url = wp_nonce_url($url, 'mapping-lti-link', 'candela-lti-nonce');
        $url .= '&resource_link_id=' . urlencode($map->resource_link_id) . '&target_action=' . urlencode( $target_action );
        $add = '<div class="lti addmap"><a href="' . $url . '">' . str_replace('##RES##', $map->resource_link_id, $translated) . '</a></div>';
        $add_link = TRUE;
      }

      $maps = CandelaLTI::get_maps_by_target_action();
      if ( ! empty( $maps ) ) {
        $base_url = get_site_url(1) . '/api/candelalti';
        $base_url = wp_nonce_url($base_url, 'unmapping-lti-link', 'candela-lti-nonce');
        $translated = __('Remove LTI resource_link_id(##RES##)');
        $links = array();
        foreach ( $maps as $map ) {
          if ($map->resource_link_id == $resource_link_id ) {
            // don't include add and delete link
            $add_link = FALSE;
          }
          $url = $base_url . '&action=delete&ID=' . $map->ID;
          $links[] = '<a href="' . $url . '">' . str_replace('##RES##', $map->resource_link_id, $translated) . '</a>';
        }
        $remove = '<div class="lti removemap"><ul><li>' . implode('</li><li>', $links) . '</li></ul></div>';
      }

      // Add add link
      if ( $add_link ) {
        $content .= $add;
      }

      if ( ! empty( $remove ) ) {
        $content .= $remove;
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
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Create a database table for storing nonces.
   */
  public static function create_db_table() {
    $table_name = CANDELA_LTI_TABLE;

    $sql = "CREATE TABLE $table_name (
      ID mediumint(9) NOT NULL AUTO_INCREMENT,
      resource_link_id TINYTEXT,
      target_action TINYTEXT,
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

