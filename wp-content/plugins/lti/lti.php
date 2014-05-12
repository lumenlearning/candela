<?php
/**
 * @wordpress-plugin
 * Plugin Name:       LTI
 * Description:       IMS LTI Integration for Wordpress
 * Version:           0.1
 * Author:            Jeff Graham
 * Author URI:        http://funnymonkey.com
 * Text Domain:       lti
 * License:           MIT
 * GitHub Plugin URI: https://github.com/lumenlearning/lti
 */

/**
 * @todo move this to its own repo
 * @todo update licensing details and other plugin meta.
 * @todo Request LTI credentials UI (skip standard post UI) nothing should be
 *       user editable. Adjust settings in call to register_post_type()
 *       currently it intentionally allows editing to aid in debugging.
 * @todo Enable users to creat/view/delete their own lti_consumer posts, but not
 *       anyone other user's lti_consumer posts. Admins should be able to see
 *       delete any credentials, and to create credentials on behalf of another
 *       user. Maybe expose normal UI with author selection for admin, but only
 *       on create. We likely don't want to enable "transferring" of credentials
 * @todo refactor add_meta_box() callbacks to not display key/secret when
 *       adding.
 * @todo add enough user prompting & inline documentation for templates
 *       on how to use the LTI information. Make this pluggable so that it is
 *       easy for people to add site-specific & LMS-specific details via
 *       templating to facilitate easy overrides and straightforward pull
 *       requests
 * @todo Consider draft->published workflow for lti_consumer posts. Where draft
 *       would indicate inactive or unapproved credentials, and published
 *       indicating the credentials are active.
 */

// If file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit;

// Do our necessary plugin setup and add_action routines.
LTI::init();

class LTI {
  /**
   * Takes care of registering our hooks and setting constants.
   */
  public static function init() {
    if ( !defined( 'LTI_PLUGIN_DIR' ) ) {
      define( 'LTI_PLUGIN_DIR', __DIR__ . '/' );
    }

    if ( ! defined( 'LTI_PLUGIN_URL' ) ) {
      define( 'LTI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    // Length of token generated by OAuthProvider::generateToken()
    define ('LTI_OAUTH_TOKEN_LENGTH', 40);

    // Constants for our meta field names
    define('LTI_META_KEY_NAME', '_lti_consumer_key');
    define('LTI_META_SECRET_NAME', '_lti_consumer_secret');

    // How big of a window to allow timestamps in seconds. Default 90 minutes (5400 seconds).
    define('LTI_NONCE_TIMELIMIT', 5400);

    // LTI Nonce table name.
    define('LTI_TABLE_NAME', 'ltinonce');

    // Database version
    define('LTI_DB_VERSION', '1.0');

    register_activation_hook( __FILE__, array( __CLASS__, 'install' ) );

    add_action( 'admin_notices', array( __CLASS__, 'check_dependencies') );
    add_action( 'init', array( __CLASS__, 'register_post_type' ) );
    add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
    add_action( 'save_post', array( __CLASS__, 'save') );

    add_filter( 'template_include', array( __CLASS__, 'template_include' ) );

    # API details
    add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
    add_action( 'query_vars', array( __CLASS__, 'query_vars' ) );
    add_action( 'parse_request', array( __CLASS__, 'parse_request' ) );

	}

  /**
   * Add our nonce table to log received nonce to avoid replay attacks.
   */
  public static function install( $sitewide ) {
    global $wpdb;
    if ( is_multisite() && $sitewide ) {
      $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
      if ( ! empty($blogs) ) {
        foreach ( $blogs as $blog_id ) {
          switch_to_blog($blog_id);
          LTI::create_db_table();
          restore_current_blog();
        }
      }
    }
    LTI::create_db_table();
  }

  /**
   * Create a database table for storing nonces.
   */
  public static function create_db_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . LTI_TABLE_NAME;

    $sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      noncetime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
      noncevalue tinytext NOT NULL,
      PRIMARY KEY  id (id)
    );";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta( $sql );

    add_option( 'lti_db_version', LTI_DB_VERSION );
  }

  /**
   * Check dependencies
   */
  public static function check_dependencies() {
    if ( ! extension_loaded('oauth') ) {
      echo '<div id="message" class="error fade"><p>';
      echo 'LTI integration requires the OpenAuth library. Please see <a href="http://php.net/manual/en/book.oauth.php">oauth</a> for more information.';
      echo '</p></div>';
    }
  }

  /**
   * Register our custom post type to track LTI consumers.
   *
   * @see http://codex.wordpress.org/Function_Reference/register_post_type
   */
  public static function register_post_type() {
    $args = array(
      'labels' => array(
        'name' => __( 'LTI Consumers' ),
        'add_new' => _x('Add New', 'lti_consumer' ),
        'singular_name' => __( 'LTI Consumer' ),
        'add_new_item' => __( 'Add New LTI Consumer' ),
        'edit_item' => __( 'Edit LTI Consumer' ),
        'new_item' => __( 'New LTI Consumer' ),
        'view_item' => __( 'View LTI Consumer' ),
        'search_items' => __( 'Search LTI Consumers' ),
        'not_found' => __( 'No LTI Consumers found' ),
        'not_found_in_trash' => ( 'No LTI Consumers found in trash' ),
      ),
      'description' => 'LTI consumer credential information',
      'public' => true,
      'rewrite' => true,
      'can_export' => false,
      'supports' => array(
        'author',
      ),
    );
    register_post_type( 'lti_consumer', $args );
  }

  /**
   * Setup our custom template
   */
  public static function template_include( $template_path ) {
    if ( get_post_type() == 'lti_consumer' ) {
      if ( is_single() ) {
        if ( $theme_file = locate_template( array('single-lti_consumer.php' ) ) ) {
          $template_path = $theme_file;
        }
        else {
          $template_path = plugin_dir_path( __FILE__ ) . '/single-lti_consumer.php';
        }
      }
    }
    return $template_path;
  }

  /**
   * Attach custom meta fields.
   *
   * @see http://codex.wordpress.org/Function_Reference/add_meta_box
   */
  public static function add_meta_boxes() {
    add_meta_box('consumer_secret', 'Consumer Secret', array( __CLASS__, 'consumer_secret_meta'), 'lti_consumer', 'normal' );
    add_meta_box('consumer_key', 'Consumer Key', array( __CLASS__, 'consumer_key_meta'), 'lti_consumer', 'normal' );
  }

  /**
   * Callback for add_meta_box().
   *
   * @see http://codex.wordpress.org/Function_Reference/add_meta_box
   */
  public static function consumer_secret_meta( $post ) {
    // Add a nonce field so we can check for it later.
    wp_nonce_field( 'lti_consumer', 'lti_consumer_nonce');

    // Use get_post_meta to retrieve an existing value from the database.
    $secret = get_post_meta( $post->ID, LTI_META_SECRET_NAME, true);

    if ( empty($secret) ) {
      $secret = LTI::generateToken('secret');
    }

    // Display the form, using the current value.
    echo '<label for="lti_consumer_secret">';
    _e( 'Consumer secret used for signing LTI requests.' );
    echo '</label>';
    echo '<input type="text" id="lti_consumer_secret" name="lti_consumer_secret"';
    if ( ! is_admin() ) {
      echo ' disabled="disabled"';
    }
    echo ' value="' . esc_attr( $secret ) . '" size="40" />';

  }

  /**
   * Callback for add_meta_box().
   *
   * @see http://codex.wordpress.org/Function_Reference/add_meta_box
   */
  public static function consumer_key_meta( $post ) {
      // Use get_post_meta to retrieve an existing value from the database.
    $key = get_post_meta( $post->ID, LTI_META_KEY_NAME, true);

    if ( empty( $key ) ) {
      $key = LTI::generateToken('key');
    }

    // Display the form, using the current value.
    echo '<label for="lti_consumer_key">';
    _e( 'Consumer key used for signing LTI requests.' );
    echo '</label>';
    echo '<input type="text" id="lti_consumer_key" name="lti_consumer_key"';
    if ( ! is_admin() ) {
      echo ' disabled="disabled"';
    }
    echo ' value="' . esc_attr( $key ) . '" size="40" />';

  }

  /**
   * Create a new post.
   *
   * lti_consumer posts really have no user editable data so this helps by
   * managing all the programmatic pieces so we can just pass the $author_id the
   * post should be created on behalf of.
   */
  public static function create( $author_id = NULL ) {
    // default to current user (or admin) if none provided
    if ( $author_id == NULL ) {
      $user = wp_get_current_user();
      if ( 0 == $user->ID ) {
        // User is not logged in, refuse to create
        return FALSE;
      }
    }
    else {
      $user = get_userdata( $author_id );
    }

    $post = array(
      'post_content'          => '',
      'post_name'             => '',
      'post_title'            => 'LTI: ' . $user->display_name,
      'post_status'           => 'private',
      'post_type'             => 'lti_consumer',
      'post_author'           => $user->ID,
      'ping_status'           => 'closed',
      'post_password'         => '',
      'post_excerpt'          => '',
      'import_id'             => '',
      'comment_status'        => 'closed',
    );
    $post_id = wp_insert_post($post, false);

    // Now attach our key/secret metadata
    if ( ! empty($post_id) ) {
      update_post_meta($post_id, LTI_META_KEY_NAME, LTI::generateToken('key'));
      update_post_meta($post_id, LTI_META_SECRET_NAME, LTI::generateToken('secret'));
    }
  }

  /**
   * Create a new random token
   *
   * We pass through sha1() to return a 40 character token.
   *
   * @param string $type
   *  The type of token to generate either: 'key', 'secret'
   */
  public static function generateToken($type) {
    $token = OAuthProvider::generateToken(LTI_OAUTH_TOKEN_LENGTH);

    $args = array(
      'post_type' => 'lti_consumer',
      'meta_value' => sha1($token),
    );
    switch ($type) {
      case 'key':
        $args['meta_key'] = LTI_META_KEY_NAME;
        break;
      case 'secret':
        $args['meta_key'] = LTI_SECRET_KEY_NAME;
        break;
    }

    $posts = get_posts($args);

    // Loop until our token is unique for this meta value.
    while ( !empty($posts) ) {
      $token = OAuthProvider::generateToken(LTI_OAUTH_TOKEN_LENGTH);
      $args['meta_value'] = sha1($token);
      $posts = get_posts($args);
    }

    return sha1($token);
  }

  /**
   * Save a post submitted via form.
   *
   * This is here for completeness, but likely needs review to see if we want to
   * expose this part of the UI workflow at all.
   */
  public static function save( $post_id ) {
    // Check if our nonce is set
    if ( ! isset( $_POST['lti_consumer_nonce'] ) ) {
      return $post_id;
    }

    $nonce = $_POST['lti_consumer_nonce'];

    // Verify the nonce is valid
    if ( ! wp_verify_nonce($nonce, 'lti_consumer' ) ) {
      return $post_id;
    }

    if ( defined( 'DOING AUTOSAVE' ) && DOING_AUTOSAVE ) {
      return $post_id;
    }

    if ( 'lti_consumer' == $_POST['post_type']['lti_consumer'] ) {
      if ( ! current_user_can( 'edit_page', $post_id) ) {
        return $post_id;
      }
    }
    else {
      if ( ! current_user_can( 'edit_post', $post_id) ) {
        return $post_id;
      }
    }

    // Save to save data now
    update_post_meta( $post_id, LTI_META_KEY_NAME, $_POST['lti_consumer_key'] );
    update_post_meta( $post_id, LTI_META_SECRET_NAME, $_POST['lti_consumer_secret'] );
  }

  /**
   * Add our LTI api endpoint vars so that wordpress "understands" them.
   */
  public static function query_vars( $query_vars ) {
    $query_vars[] = '__lti';
    $query_vars[] = 'blog';
    return $query_vars;
  }

  /**
   * Add our LTI api endpoint
   */
  public static function add_rewrite_rule() {
    add_rewrite_rule( '^api/lti/([0-9]+?/?)', 'index.php?__lti=1&blog=$matches[1]', 'top');
  }

  /**
   * Implementation of action 'parse_request'.
   *
   * @see http://codex.wordpress.org/Plugin_API/Action_Reference/parse_request
   */
  public static function parse_request() {
    if ( LTI::is_lti_request() ) {
      global $wp;

      // Make sure our queries run against the appropriate site.
      switch_to_blog((int)$wp->query_vars['blog']);
      $LTIOAuth = new LTIOAuth();
      if ($LTIOAuth->oauth_error == false) {
        // @todo add hook here to process LTI request
        do_action('lti_setup');
        do_action('lti_pre');
        do_action('lti_launch');
        //echo '<div class="success">LTI Launch Request OK</div>';
      }
      else {
        // @todo error handler here.
        //echo '<div class="error">LTI Launch not OK</div>';
      }

      // If something else didn't direct us elsewhere restore the main blog.
      restore_current_blog();
    }
  }

  /**
   * Checks $_POST to see if the current post data is an incoming LTI request.
   *
   * We only check that the required LTI parameters are present. No furhter
   * validation occurs at this point.
   *
   * @return bool TRUE if POST data represents valid lti request.
   */
  public static function is_lti_request() {
    // Check required parameters.
    if (isset( $_POST['lti_message_type'] )  && isset( $_POST['lti_version'] ) && isset( $_POST['resource_link_id'] ) ) {
      // Required LTI parameters present.
      return TRUE;
    }
    return FALSE;
  }
}

class LTIOAuth {
  private $oauthProvider;

  public $oauth_error = false;

  /**
   * Attempt to validate the incoming LTI request.
   */
  public function __construct() {
    try {
      $this->oauthProvider = new OAuthProvider();
      $this->oauthProvider->consumerHandler( array( $this, 'consumerHandler' ) );
      $this->oauthProvider->timestampNonceHandler( array( $this, 'timestampNonceHandler' ) );
      $this->oauthProvider->isRequestTokenEndpoint(true);
      $this->oauthProvider->setParam('url', NULL);
      $this->oauthProvider->checkOAuthRequest();
    }
    catch (OAuthException $e) {
      // @todo Change to simple user facing error message. Log with more details.
      echo '<div class="error">';
      echo OAuthProvider::reportProblem($e);
      echo '</div>';
      $this->oauth_error = true;
    }
  }
  /**
   * Implement timestampNonceHandler for OAuthProvider.
   *
   * @see http://us3.php.net/manual/en/oauthprovider.timestampnoncehandler.php
   */
  public function timestampNonceHandler() {
    // If nonce is not within timestamp range reject it.
    if ( ( time() - (int)$_POST['oauth_timestamp'] ) > LTI_NONCE_TIMELIMIT ) {
      // Request is too old.
      return OAUTH_BAD_TIMESTAMP;
    }

    // Find out if this nonce has been used before.
    global $wpdb;

    $table_name = $wpdb->prefix . LTI_TABLE_NAME;
    $query = $wpdb->prepare("SELECT noncevalue FROM $table_name WHERE noncevalue = %s AND noncetime >= DATE_SUB(NOW(), interval %d SECOND) ", $_POST['oauth_nonce'], LTI_NONCE_TIMELIMIT);

    $results = $wpdb->get_results($query);
    if ( empty($results) ) {
      // Store the nonce as we haven't seen it before.
      $query = $wpdb->prepare("INSERT INTO $table_name (noncevalue, noncetime)VALUES(%s, FROM_UNIXTIME(%d))", array($_POST['oauth_nonce'], $_POST['oauth_timestamp']));
      $wpdb->query($query);
      return OAUTH_OK;
    }
    else {
      // Replay attack or improper refresh.
      return OAUTH_BAD_NONCE;
    }

    // We should not get here, but in case return OAUTH_BAD_NONCE.
    // @todo log error?
    return OAUTH_BAD_NONCE;
  }

  /**
   * Purge old nonces from table.
   */
  public function purgeNonces() {
    // Purge old nonces outside window of acceptable time.
    global $wpdb;
    $table_name = $wpdb->prefix . LTI_TABLE_NAME;
    $query = $wpdb->prepare("DELETE FROM $table_name WHERE noncetime < DATE_SUB(NOW(), interval %d SECOND) ", LTI_NONCE_TIMELIMIT);
    $wpdb->query($query);

  }

  /**
   * Implement consumerHandler for OAuthProvider.
   *
   * @see http://us3.php.net/manual/en/oauthprovider.consumerhandler.php
   */
  public function consumerHandler () {
    // Lookup consumer key.
    if ( ! empty($_POST['oauth_consumer_key']) ) {
      $args = array(
        'post_type' => 'lti_consumer',
        'meta_key' => LTI_META_KEY_NAME,
        'meta_value' => $_POST['oauth_consumer_key'],
      );
      $q = new WP_Query( $args );

      if ( $q->have_posts() ) {
        if ( $q->posts[0] == 'trash' ) {
          // Corresponding lti_consumer post was deleted.
          return OAUTH_CONSUMER_KEY_REFUSED;
        }
        else {
          $secret = get_post_meta( $q->posts[0]->ID, LTI_META_SECRET_NAME, TRUE);
          if ( ! empty( $secret ) ) {
            $this->oauthProvider->consumer_secret = $secret;
            return OAUTH_OK;
          }
          else {
            // This should have resulted in valid secret.
            // @todo log error?
            return OAUTH_CONSUMER_KEY_UNKOWN;
          }
        }
      }
      else {
        // We did not find a matching consumer key.
        return OAUTH_CONSUMER_KEY_UNKNOWN;
      }

    }
    else {
      // No consumer key present in POST data.
      return OAUTH_CONSUMER_KEY_UNKNOWN;
    }

    // Not sure how we would get here, but refust the key in the event
    // @todo log error?
    return OAUTH_CONSUMER_KEY_REFUSED;
  }
}
